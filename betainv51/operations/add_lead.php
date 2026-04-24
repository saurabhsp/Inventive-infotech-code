<?php
@ini_set('display_errors', '1');
@error_reporting(E_ALL);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/initialize.php'; // $con, csrf_token(), verify_csrf()
require_login();

// Logged in admin 
$me = $_SESSION['admin_user'] ?? [];
$MY_ID = (int)($me['id'] ?? 0);
$MY_ROLE_ID = isset($me['role_id']) ? (int)$me['role_id'] : 0;



/* ---------------- Tables ---------------- */
$TABLE     = 'jos_app_crm_leads';
$STATUSTBL = 'jos_app_crm_lead_statuses';
$SOURCETBL = 'jos_app_crm_lead_sources';
$PLANTBL   = 'jos_app_subscription_plans';
$HISTTBL   = 'jos_app_crm_lead_status_history';

/* ---------------- Plan allowed check ---------------- */
function plan_allowed_for_profile(array $plans, ?string $plan_id, int $profile_type): bool
{
    if (!$plan_id) return false;
    if (!isset($plans[$plan_id])) return false;
    $ptype = (int)($plans[$plan_id]['ptype'] ?? -999);
    // allow exact match OR common (0)
    return ($ptype === 0 || $ptype === $profile_type);
}

$msg = "";

/* ---------------- Helpers ---------------- */
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function clean($v)
{
    return trim((string)$v);
}
function table_exists(mysqli $con, string $name): bool
{
    $name = mysqli_real_escape_string($con, $name);
    $rs = mysqli_query($con, "SHOW TABLES LIKE '$name'");
    return ($rs && mysqli_num_rows($rs) > 0);
}
function keep_params(array $changes = [])
{
    $qs = $_GET;
    foreach ($changes as $k => $v) {
        if ($v === null) unset($qs[$k]);
        else $qs[$k] = $v;
    }
    $q = http_build_query($qs);
    if ($q) return '?' . $q;
    $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? basename(__FILE__));
    return $script;
}
function fmt_date($dt)
{
    return $dt ? date('d-m-Y', strtotime($dt)) : '—';
}
function fmt_dt($dt)
{
    return $dt ? date('d-m-Y h:i A', strtotime($dt)) : '—';
}

/* dd-mm-yyyy hh:mm AM/PM => Y-m-d H:i:s */
function parse_followup_to_db($v)
{
    $v = trim((string)$v);
    if ($v === '') return null;
    $dt = DateTime::createFromFormat('d-m-Y h:i A', $v);
    if (!$dt) $dt = DateTime::createFromFormat('d-m-Y h:i a', $v);
    if (!$dt) return null;
    return $dt->format('Y-m-d H:i:s');
}

/* Safe bind helper */
function stmt_bind(mysqli_stmt $st, string $types, array $params): void
{
    if (strlen($types) !== count($params)) {
        throw new RuntimeException("bind_param mismatch: types=" . strlen($types) . " vars=" . count($params));
    }
    $refs = [];
    foreach ($params as $k => $v) $refs[$k] = &$params[$k];
    array_unshift($refs, $types);
    call_user_func_array([$st, 'bind_param'], $refs);
}

/* ---------------- Menu + ACL ---------------- */
$page_title = 'Add Lead- Excel Import';


/* ---------------- Load masters ---------------- */
$statuses = []; // id => ['name'=>, 'code'=>]
if (table_exists($con, $STATUSTBL)) {
    $rs = mysqli_query($con, "SELECT id,status_name,status_code FROM `$STATUSTBL` WHERE is_active=1 ORDER BY sort_order ASC, status_name ASC");
    while ($rs && $r = mysqli_fetch_assoc($rs)) {
        $statuses[(int)$r['id']] = ['name' => $r['status_name'], 'code' => $r['status_code']];
    }
}

$sources = []; // id => name
if (table_exists($con, $SOURCETBL)) {
    $rs = mysqli_query($con, "SELECT id,source_name FROM `$SOURCETBL` WHERE is_active=1 ORDER BY sort_order ASC, source_name ASC");
    while ($rs && $r = mysqli_fetch_assoc($rs)) {
        $sources[(int)$r['id']] = $r['source_name'];
    }
}

/* Plans: keep profile_type for filtering (0 = common) */
$plans = []; // id(string) => ['name'=>..., 'ptype'=>int]
$plansByType = [0 => [], 1 => [], 2 => []]; // for JS
if (table_exists($con, $PLANTBL)) {
    $rs = mysqli_query($con, "SELECT id,profile_type,plan_name FROM `$PLANTBL` WHERE plan_status=1 ORDER BY profile_type ASC, plan_name ASC");
    while ($rs && $r = mysqli_fetch_assoc($rs)) {
        $pid = (string)$r['id'];
        $ptype = (int)$r['profile_type'];
        $pname = (string)$r['plan_name'];
        $plans[$pid] = ['name' => $pname, 'ptype' => $ptype];

        // keep only types we need for dropdown: common(0), employer(1), jobseeker(2)
        if ($ptype === 0 || $ptype === 1 || $ptype === 2) {
            $plansByType[$ptype][] = ['id' => $pid, 'name' => $pname, 'ptype' => $ptype];
        }
    }
}

/* admin users for assignment */
$ADMINUSERS = table_exists($con, 'jos_admin_users') ? 'jos_admin_users' : (table_exists($con, 'jos_admin') ? 'jos_admin' : '');
$adminUsers = []; // id=>name
if ($ADMINUSERS) {
    $cols = [];
    $cr = mysqli_query($con, "SHOW COLUMNS FROM `$ADMINUSERS`");
    while ($cr && $c = mysqli_fetch_assoc($cr)) $cols[] = $c['Field'];
    $nameCol = in_array('name', $cols, true) ? 'name' : (in_array('username', $cols, true) ? 'username' : 'id');
    $statusCol = in_array('status', $cols, true) ? 'status' : (in_array('is_active', $cols, true) ? 'is_active' : '');
    $sql = "SELECT id, `$nameCol` AS nm FROM `$ADMINUSERS`" . ($statusCol ? (" WHERE `$statusCol`=1") : '') . " ORDER BY `$nameCol` ASC";
    $rs = mysqli_query($con, $sql);
    while ($rs && $r = mysqli_fetch_assoc($rs)) $adminUsers[(int)$r['id']] = $r['nm'];
}


/* =======================
   HANDLE FORM SUBMIT
======================= */
if (isset($_POST['import'])) {

    $profile_type   = (int)($_POST['profile_type'] ?? 1);
    $source_id      = (int)($_POST['source_id'] ?? 0);
    $source_detail  = clean($_POST['source_detail'] ?? '');
    $status_id      = (int)($_POST['to_status_id'] ?? 0);
    $assigned_to    = (int)($_POST['assigned_to'] ?? 0);
    $plan_id   = trim($_POST['onboarded_plan_id'] ?? '0'); // string

    if (!isset($statuses[$status_id])) {
        $msg = "Invalid Status Selected";
    } elseif (!empty($_FILES['excel']['tmp_name'])) {

        $file = $_FILES['excel']['tmp_name'];

        if (($handle = fopen($file, "r")) !== FALSE) {

            $count = 0;
            $row = 0;

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

                $row++;
                if ($row == 1) continue;

                // CSV Columns
                $candidate_name = clean($data[0] ?? '');
                $phone1         = clean($data[1] ?? '');
                $phone2         = clean($data[2] ?? '');
                $email          = clean($data[3] ?? '');
                $city           = clean($data[4] ?? '');

                if (
                    empty(array_filter($data)) ||   // pura row blank
                    trim($phone1) == ''            // phone empty
                ) {
                    continue;
                }

                /* ================= STATUS ================= */
                $status_code = $statuses[$status_id]['code'];

                $followup_db = null;
                $plan_db = null;
                $not_contactable_flag = 0;

                if ($status_code === 'FOLLOW_UP') {

                    $followup_input = trim($_POST['followup_at'] ?? '');

                    // ❌ empty check
                    if ($followup_input === '') {
                        $msg = "Error: Follow-up date is required for FOLLOW UP status";
                        break;
                    }

                    // ❌ invalid format check
                    $followup_db = parse_followup_to_db($followup_input);

                    if (!$followup_db) {
                        $msg = "Error: Invalid Follow-up date format";
                        break;
                    }
                }

                if ($status_code === 'NOT_CONTACTABLE') {
                    $not_contactable_flag = 1;
                }
                if ($status_code === 'ON_BOARDED') {

                    if ($plan_id === '0' || $plan_id === '') {
                        $msg = "Error: Please select valid On-boarded Plan";
                        break; // 🔥 STOP import immediately
                    }

                    // profile type validation (same as main file)
                    if (!isset($plans[$plan_id])) {
                        $msg = "Error: Invalid Plan";
                        break;
                    }

                    $ptype = (int)$plans[$plan_id]['ptype'];

                    if (!($ptype === 0 || $ptype === $profile_type)) {
                        $msg = "Error: Plan not allowed for this profile type";
                        break;
                    }

                    $plan_db = $plan_id;
                }

                /* ================= ASSIGN ================= */
                $assigned_to_db = $assigned_to > 0 ? $assigned_to : null;
                $assigned_by_db = $MY_ID > 0 ? $MY_ID : null;
                $assigned_at_db = $assigned_to_db ? date('Y-m-d H:i:s') : null;
                $reassigned_at_db = null;

                $source_id_db = $source_id > 0 ? $source_id : null;

                /* ================= PROFILE HANDLING ================= */
                $company_name = '';
                $owner_hr_name = '';
                $sector = '';

                if ($profile_type == 1) {
                    $company_name = $candidate_name;
                    $candidate_name = '';
                }

                /* ================= INSERT ================= */
                $sql = "INSERT INTO `$TABLE`
                (profile_type,company_name,owner_hr_name,sector,candidate_name,
                 phone1,phone2,email,city_location,
                 source_id,source_detail,status_id,
                 assigned_to,assigned_by,assigned_at,reassigned_at,
                 last_status_reason,followup_at,onboarded_plan_id,not_contactable_flag,
                 created_by)
                VALUES
                (?,?,?,?,?,
                 ?,?,?,?,
                 ?,?,?,
                 ?,?,?,?,
                 ?,?,?,?,
                 ?)";

                $stmt = $con->prepare($sql);

                $reason = trim($_POST['remark'] ?? '');
                if ($reason === '') {
                    $reason = 'Imported via CSV';
                }
                $created_by_db = $MY_ID ?: null;

                $params = [
                    $profile_type,
                    $company_name,
                    $owner_hr_name,
                    $sector,
                    $candidate_name,
                    $phone1,
                    $phone2,
                    $email,
                    $city,
                    $source_id_db,
                    $source_detail,
                    $status_id,
                    $assigned_to_db,
                    $assigned_by_db,
                    $assigned_at_db,
                    $reassigned_at_db,
                    $reason,
                    $followup_db,
                    $plan_db,
                    $not_contactable_flag,
                    $created_by_db
                ];

                $types = "issssssssisiiisssssii";

                stmt_bind($stmt, $types, $params);

                if ($stmt->execute()) {

                    $lead_id = $stmt->insert_id;
                    $count++;

                    /* ================= HISTORY ================= */
                    if (table_exists($con, $HISTTBL)) {

                        $meta = json_encode([
                            'mode' => 'csv_import'
                        ], JSON_UNESCAPED_UNICODE);

                        $stH = $con->prepare("INSERT INTO `$HISTTBL`
                        (lead_id,from_status_id,to_status_id,changed_by,reason,meta_json,next_followup_dt)
                        VALUES (?,NULL,?,?,?,?,?)");

                        $changed_by = $MY_ID ?: null;

                        stmt_bind($stH, "iissss", [
                            $lead_id,
                            $status_id,
                            $changed_by,
                            $reason,
                            $meta,
                            $followup_db
                        ]);

                        $stH->execute();
                        $stH->close();
                    }
                }

                $stmt->close();
            }

            fclose($handle);
            if (!$msg) {
                $msg = "$count Leads Imported Successfully";
            }
        }
    }
}
$pt = (int)($r['profile_type'] ?? 1);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Add Lead -Excel Import</title>
    <link rel="stylesheet" href="/adminconsole/assets/ui.css">
</head>
<link rel="stylesheet" href="/adminconsole/assets/ui.css">

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>


<style>
    .pac-panel {
        background: #0b1220;
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 16px;
        padding: 16px;
        box-shadow: 0 14px 50px rgba(0, 0, 0, .45);
    }

    .pac-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
    }

    .pac-sub {
        color: #9ca3af;
        font-size: 12px;
        margin-top: 6px;
    }

    .pac-grid3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px 16px;
        align-items: end;
    }

    .pac-grid3 .full {
        grid-column: 1/-1;
    }

    .pac-grid3 .actions {
        grid-column: 1/-1;
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .pac-grid3 label {
        display: block;
        margin: 0 0 6px;
        font-weight: 600;
    }

    .pac-hint {
        color: #9ca3af;
        font-size: 12px;
        margin-top: 6px;
    }

    .hide {
        display: none !important;
    }

    @media (max-width:1100px) {
        .pac-grid3 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width:720px) {
        .pac-grid3 {
            grid-template-columns: 1fr;
        }

        .pac-grid3 .full {
            grid-column: 1;
        }
    }

    .pac-modal {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .55);
        z-index: 9999;
        display: none;
        overflow: auto;
        padding: 18px;
    }

    .pac-modal .pac-panel {
        max-width: 980px;
        margin: 24px auto;
    }

    .pac-labelgrid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-top: 12px;
    }

    .pac-label {
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 12px;
        padding: 10px;
        background: #0f1a2e;
    }

    .pac-label .k {
        color: #9ca3af;
        font-size: 12px;
    }

    .pac-label .v {
        font-weight: 600;
        margin-top: 2px;
        color: #e5e7eb;
    }

    @media (max-width:900px) {
        .pac-labelgrid {
            grid-template-columns: 1fr;
        }
    }

    /* Flatpickr solid */
    .flatpickr-calendar {
        z-index: 100000 !important;
        background: #0b1220 !important;
        border: 1px solid rgba(148, 163, 184, .25) !important;
        box-shadow: 0 16px 55px rgba(0, 0, 0, .70) !important;
        opacity: 1 !important;
    }

    .flatpickr-months,
    .flatpickr-weekdays,
    .flatpickr-days,
    .flatpickr-time {
        background: #0b1220 !important;
        opacity: 1 !important;
    }

    .flatpickr-weekday,
    .flatpickr-day {
        color: #e5e7eb !important;
    }

    .flatpickr-day.today {
        border-color: rgba(96, 165, 250, .9) !important;
    }

    .flatpickr-day.selected,
    .flatpickr-day.startRange,
    .flatpickr-day.endRange {
        background: rgba(34, 197, 94, .85) !important;
        border-color: rgba(34, 197, 94, .85) !important;
        color: #04110a !important;
    }

    .flatpickr-day:hover {
        background: rgba(148, 163, 184, .18) !important;
    }

    .flatpickr-time input,
    .flatpickr-time .flatpickr-am-pm {
        color: #e5e7eb !important;
        background: #0b1220 !important;
    }

    .flatpickr-current-month .flatpickr-monthDropdown-months,
    .flatpickr-current-month input.cur-year {
        color: #e5e7eb !important;
    }

    .flatpickr-calendar.arrowTop:before,
    .flatpickr-calendar.arrowTop:after,
    .flatpickr-calendar.arrowBottom:before,
    .flatpickr-calendar.arrowBottom:after {
        display: none !important;
    }

    .btn.gray {
        margin: 2px;
    }
</style>

<body>

    <h2 style="margin:8px 0 12px">Excel Lead Import</h2>

    <div class="pac-panel" style="max-width:980px">
        <div class="pac-head">
            <h3 style="margin:0">Add Lead</h3>
            <a class="btn gray" href="lead_list.php">Back to List</a>
        </div>
        <?php
        $isError = (stripos($msg, 'error') !== false);
        ?>

        <?php if ($msg): ?>
            <div class="badge <?= $isError ? 'off' : 'on' ?>" style="margin:10px 0">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <!-- SAME GRID -->
        <form method="post" enctype="multipart/form-data" class="pac-grid3">

            <!-- Profile -->
            <div>
                <label>Profile Type*</label>
                <select name="profile_type"
                    id="profile_type"
                    class="inp"
                    required>

                    <?php if ($MY_ROLE_ID == 3): ?>
                        <option value="2" selected>Jobseeker </option>

                    <?php elseif ($MY_ROLE_ID == 13): ?>
                        <option value="1" selected>Employer </option>

                    <?php else: ?>
                        <option value="1" <?= $pt === 1 ? 'selected' : '' ?>>
                            Employer
                        </option>
                        <option value="2" <?= $pt === 2 ? 'selected' : '' ?>>
                            Jobseeker
                        </option>
                    <?php endif; ?>

                </select>

                <?php if ($MY_ROLE_ID == 3): ?>
                    <input type="hidden" name="profile_type" value="2">
                <?php elseif ($MY_ROLE_ID == 13): ?>
                    <input type="hidden" name="profile_type" value="1">
                <?php endif; ?>
            </div>

            <!-- Source -->
            <div>
                <label>Source</label>
                <select name="source_id" class="inp">
                    <option value="0">— Select —</option>
                    <?php
                    foreach ($sources as $sid => $nm): ?>
                        <option value="<?= $sid ?>"><?= h($nm) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Source Details -->
            <div class="full">
                <label>Source Details</label>
                <textarea name="source_detail" class="inp" rows="3"></textarea>
            </div>

            <!-- Assign -->

            <?php if ($MY_ROLE_ID == 1): ?>
                <div>
                    <label>Assign To</label>
                    <select name="assigned_to" class="inp">
                        <option value="0">— Not assigned —</option>
                        <?php
                        foreach ($adminUsers as $uid => $nm): ?>
                            <option value="<?= $uid ?>"><?= h($nm) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pac-hint"></div>
                </div>
            <?php endif; ?>

            <!-- EMPTY GRID MATCH (important for alignment like original form) -->
            <div></div>
            <div></div>

            <!-- CSV Upload (yeh main field hai) -->
            <div class="full">
                <label>Upload CSV*</label>
                <input type="file" name="excel" accept=".csv" required class="inp">
            </div>

            <!-- Status -->
            <!-- STATUS UPDATE (same as existing) -->
            <!-- <hr style="opacity:.18;margin:14px 0"> -->

            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="status_update" value="1">
            <input type="hidden" name="lead_id" id="m_lead_id" value="0">

            <div>
                <label>Status*</label>
                <select class="inp" name="to_status_id" id="m_status_id" required onchange="syncModalFields()">
                    <?php foreach ($statuses as $sid => $s): ?>
                        <option value="<?= $sid ?>" data-code="<?= h($s['code']) ?>"><?= h($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="m_followup_box" class="hide">
                <label>Follow-up Date/Time*</label>
                <input type="text" class="inp" name="followup_at" id="m_followup_at" placeholder="dd-mm-yyyy hh:mm AM/PM">
            </div>

            <div id="m_plan_box" class="hide">
                <label>On-boarded Plan*</label>
                <select class="inp" name="onboarded_plan_id" id="m_plan_id">
                    <option value="0">— Select Plan —</option>
                </select>
            </div>

            <div class="full">
                <label>Remark*</label>
                <textarea class="inp" rows="3" name="remark" id="m_remark"
                    placeholder="Remark will be saved in history each time you update status."></textarea>
            </div>




            <hr style="margin:15px 0;opacity:.2">

            <!-- EMPTY FOR ALIGNMENT -->
            <div></div>
            <div></div>

            <!-- Buttons -->
            <div class="actions">
                <button class="btn green" name="import" type="submit">Save</button>
                <a href="lead_list.php" class="btn gray">Cancel</a>
            </div>

        </form>
    </div>
    <script>
        (function() {

            function byId(id) {
                return document.getElementById(id);
            }

            function setHide(el, hide) {
                if (!el) return;
                el.classList.toggle('hide', !!hide);
            }

            function toggleStatus() {
                var sel = byId('m_status_id');
                var opt = sel.options[sel.selectedIndex];
                var code = opt ? (opt.getAttribute('data-code') || '') : '';

                setHide(byId('m_followup_box'), code !== 'FOLLOW_UP');
                setHide(byId('m_plan_box'), code !== 'ON_BOARDED');

                // 🔥 FINAL FIX (THIS WAS MISSING)
                if (code === 'ON_BOARDED') {

                    // ✅ ADD THIS LINE (IMPORTANT)
                    _modalProfileType = parseInt(document.getElementById('profile_type').value || '1', 10);

                    const planSel = document.getElementById('m_plan_id');
                    const cur = planSel.value || "0";
                    planSel.innerHTML = buildPlanOptions(_modalProfileType, cur);
                }
            }

            // event
            byId('m_status_id').addEventListener('change', toggleStatus);

            // init
            toggleStatus();

            // ✅ FLATPICKR FIX
            if (window.flatpickr) {
                flatpickr(byId('m_followup_at'), {
                    enableTime: true,
                    time_24hr: false,
                    dateFormat: "d-m-Y h:i K",
                    allowInput: true
                });
            }

        })();
    </script>
    <script>
        // Plans for JS filtering (common type=0 + specific type=1/2)
        window.PACIFIC_PLANS = <?= json_encode($plansByType, JSON_UNESCAPED_UNICODE) ?>;

        function buildPlanOptions(profileType, selectedId) {
            const common = (window.PACIFIC_PLANS && window.PACIFIC_PLANS[0]) ? window.PACIFIC_PLANS[0] : [];
            const typed = (window.PACIFIC_PLANS && window.PACIFIC_PLANS[profileType]) ? window.PACIFIC_PLANS[profileType] : [];
            const list = [...common, ...typed];

            let html = '<option value="0">— Select Plan —</option>';
            for (const p of list) {
                const sel = (String(p.id) === String(selectedId)) ? ' selected' : '';
                html += '<option value="' + String(p.id).replace(/"/g, '&quot;') + '"' + sel + '>' + String(p.name) + '</option>';
            }
            return html;
        }
    </script>
    <script>
        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, (m) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [m]));
        }

        let _modalProfileType = 1;


        function syncModalFields() {
            const sel = document.getElementById('m_status_id');
            const opt = sel.options[sel.selectedIndex];
            const code = opt ? (opt.getAttribute('data-code') || '') : '';
            document.getElementById('m_followup_box').classList.toggle('hide', code !== 'FOLLOW_UP');
            document.getElementById('m_plan_box').classList.toggle('hide', code !== 'ON_BOARDED');

            // keep plan list in sync with lead type (if user keeps modal open and switches status)
            if (code === 'ON_BOARDED') {
                const planSel = document.getElementById('m_plan_id');
                const cur = planSel.value || "0";
                planSel.innerHTML = buildPlanOptions(_modalProfileType, cur);
            }
        }
    </script>
</body>

</html>