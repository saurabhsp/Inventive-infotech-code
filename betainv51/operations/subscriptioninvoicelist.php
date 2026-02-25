<?php
@ini_set('display_errors', '1');
@error_reporting(E_ALL);

require_once __DIR__ . '/../includes/initialize.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

/* -------------------------------------------------
   If coming from Dashboard via POST
   ------------------------------------------------- */
$is_from_dashboard = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_id'])) {

    $is_from_dashboard = true;

    $dashboard_admin_id   = (int)$_POST['admin_id'];
    $dashboard_profile_id = (int)($_POST['profile_type_id'] ?? 0);
    $dashboard_from       = $_POST['from'] ?? '';
    $dashboard_to         = $_POST['to'] ?? '';
}

/* ---------------- LOGGED IN USER ---------------- */
$me = function_exists('current_user') ? current_user() : [];
$logged_admin_id   = (int)($me['id'] ?? 0);
$logged_admin_roleid   = (int)($me['role_id'] ?? 0);
// $logged_admin_roleid   = 1;
// $logged_admin_id   = 1;

/* ---------------- ACL: attempt to ensure can_view exists (auto-create if possible), then check it.
   - Tries to add `can_view` column if it's missing (useful for quick fix).
   - If DB user lacks ALTER privileges or query fails, falls back to safe behavior (deny).
   - Default created column value is 1 (allow) so existing menus remain viewable.
   - If you prefer DENY-by-default for auto-created column, change DEFAULT 1 to DEFAULT 0 below.
*/

if (!isset($con) || !$con) {
    http_response_code(403);
?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8" />
        <title>403 Access denied</title>
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <link rel="stylesheet" href="/adminconsole/assets/ui.css">
        <style>
            .center {
                text-align: center;
                padding: 40px;
            }
        </style>
    </head>

    <body>
        <div class="master-wrap">
            <div class="card center">
                <h1>403 — Access denied</h1>
                <p>Unable to validate permissions. Database not available.</p>
            </div>
        </div>
    </body>

    </html>
<?php
    exit;
}

$menu_table = 'jos_admin_menus';
$menu_id = isset($_GET['menu_id']) ? (int)$_GET['menu_id'] : 0;
$can_view = 0;

/* helper to check table existence */
function table_exists($con, $table)
{
    $t = $con->real_escape_string($table);
    $q = "SHOW TABLES LIKE '{$t}'";
    $r = $con->query($q);
    return ($r && $r->num_rows > 0);
}

/* helper to check column existence */
function column_exists($con, $table, $col)
{
    $t = $con->real_escape_string($table);
    $c = $con->real_escape_string($col);
    $q = "SHOW COLUMNS FROM `{$t}` LIKE '{$c}'";
    $r = $con->query($q);
    return ($r && $r->num_rows > 0);
}

/* Try to ensure table exists and column exists; if column missing, attempt to add it. */
$tbl_ok = table_exists($con, $menu_table);
if ($tbl_ok && !column_exists($con, $menu_table, 'can_view')) {
    // Attempt to add the column. Default is 1 so existing pages stay viewable.
    // If DB user lacks ALTER privilege this will fail; we silently catch and leave $can_view=0 (deny).
    $alter_sql = "ALTER TABLE `{$con->real_escape_string($menu_table)}` 
                  ADD COLUMN `can_view` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status`";
    try {
        $con->query($alter_sql);
    } catch (Exception $e) {
        // ignore: fall back to deny if we can't create the column
        // Optionally log: error_log("ACL: could not add can_view column: ".$e->getMessage());
    }
}

/* Now check column again and fetch can_view if available */
if (table_exists($con, $menu_table) && column_exists($con, $menu_table, 'can_view')) {
    if ($menu_id > 0) {
        $q = "SELECT `can_view` FROM `{$menu_table}` WHERE `id` = " . (int)$menu_id . " LIMIT 1";
        $res = $con->query($q);
        if ($res && $row = $res->fetch_assoc()) {
            $can_view = (int)$row['can_view'];
        }
    } else {
        $req_uri = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
        $script_name = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $req_no_lead = ltrim($req_uri, '/');
        $basename = basename($script_name);

        // Escape string values
        $e_req_uri   = $con->real_escape_string($req_uri);
        $e_script    = $con->real_escape_string($script_name);
        $e_req_nolead = $con->real_escape_string($req_no_lead);
        $e_base      = $con->real_escape_string($basename);

        $q = "
          SELECT `can_view` FROM `{$menu_table}`
          WHERE `menu_link` IN ('{$e_req_uri}', '{$e_script}', '{$e_req_nolead}')
             OR `menu_link` LIKE '%{$e_base}%'
          LIMIT 1
        ";
        $res = $con->query($q);
        if ($res && $row = $res->fetch_assoc()) {
            $can_view = (int)$row['can_view'];
        }
    }
} else {
    // Table or column not available and unable to create it: keep $can_view = 0 (deny).
    // If you want to ALLOW by default when column is missing/uncreatable, set $can_view = 1 here.
    // $can_view = 1; // <-- uncomment to allow by default
}

/* If permission not granted, show 403 and exit */
if ($can_view !== 1) {
    http_response_code(403);
?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8" />
        <title>403 Access denied</title>
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <link rel="stylesheet" href="/adminconsole/assets/ui.css">
        <style>
            .wrap {
                max-width: 820px;
                margin: 48px auto;
            }

            .center {
                text-align: center;
                padding: 36px 24px;
            }

            .muted {
                color: #6b7280;
            }
        </style>
    </head>

    <body>
        <div class="master-wrap wrap">
            <div class="card center">
                <h1>403 — Access denied</h1>
                <p class="muted">You do not have permission to view this page. If you believe this is an error, contact the administrator.</p>
            </div>
        </div>
    </body>

    </html>
<?php
    exit;
}

/* ---------------- Existing Logic (unchanged) ---------------- */
if (!defined('DOMAIN_URL')) {
    define('DOMAIN_URL', '/');
}

/* ---------------- Helpers (mirror cashback_report style) ---------------- */
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function get_int($key, $default = 0)
{
    return isset($_GET[$key]) ? (int)$_GET[$key] : $default;
}
function get_str($key, $default = '')
{
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

/* date input: accept multiple formats, return Y-m-d (or null) */
function dfmt_in($dateStr)
{
    $dateStr = trim((string)$dateStr);
    if ($dateStr === '') return null;
    $fmts = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'Y/m/d', 'd.m.Y', 'd m Y'];
    foreach ($fmts as $f) {
        $dt = DateTime::createFromFormat($f, $dateStr);
        $err = DateTime::getLastErrors();
        if ($dt && $err['warning_count'] == 0 && $err['error_count'] == 0) {
            return $dt->format('Y-m-d');
        }
    }
    $t = strtotime($dateStr);
    return $t ? date('Y-m-d', $t) : null;
}

/* UI formatters */
function fmt_d($ts)
{
    $t = strtotime((string)$ts);
    return $t ? date('d/m/y', $t) : '';
}
function fmt_dmy($ts)
{
    $t = strtotime((string)$ts);
    return $t ? date('d-m-Y', $t) : '';
}

/* keep params helper */
function keep_params(array $changes = [])
{
    $qs = $_GET;
    foreach ($changes as $k => $v) {
        if ($v === null) {
            unset($qs[$k]);
        } else {
            $qs[$k] = $v;
        }
    }
    $q = http_build_query($qs);
    return $q ? ('?' . $q) : '';
}

/* ---------------- Inputs / Filters (same pattern) ---------------- */
if ($is_from_dashboard) {

    $from_date = dfmt_in($dashboard_from);
    $to_date   = dfmt_in($dashboard_to);
    $profile_type_id = $dashboard_profile_id;
} else {

    $df_in = get_str('from', '');
    $dt_in = get_str('to', '');
    $from_date = dfmt_in($df_in);
    $to_date   = dfmt_in($dt_in);
    $profile_type_id = get_int('profile_type_id', 0); //0=All, 1=Recruiter, 2=Job Seeker, 3=Promoter
}
$payment_status  = get_str('payment_status', '');  // '', success, failed, pending, free...
$invoice_type    = get_str('invoice_type', 'all'); // all | paid | free
$q               = get_str('q', '');               // invoice/payment/user
$show            = get_str('show', 'last50');      // last50 | all
$ac_manager_filter = get_int('ac_manager_filter', -1);
// -1 = All, 0 = Unassigned, >0 = specific manager

/* ---------------- SQL ---------------- */
$sql = "
  SELECT 
    log.id,
    log.invoiceno,
    log.userid,
    log.profile_type_id,
    log.profile_id,
    log.plan_id,
    log.amount_paid,
    log.payment_id,
    log.payment_status,
    log.start_date,
    log.end_date,
    pt.profile_name,
    plans.plan_name,
    plans.validity_months,
    CASE log.profile_type_id
      WHEN 1 THEN COALESCE(r.organization_name, CONCAT('Recruiter #', log.profile_id))
      WHEN 2 THEN COALESCE(c.candidate_name,    CONCAT('Candidate #', log.profile_id))
      WHEN 3 THEN COALESCE(p.name,              CONCAT('Promoter #', log.profile_id))
      ELSE CONCAT('Profile #', log.profile_id)
    END AS party_name
  FROM jos_app_usersubscriptionlog AS log
  INNER JOIN jos_app_users AS u ON u.id = log.userid
  LEFT JOIN jos_app_profile_types        AS pt    ON pt.id = log.profile_type_id
  LEFT JOIN jos_app_subscription_plans   AS plans ON plans.id = log.plan_id
  LEFT JOIN jos_app_recruiter_profile    AS r     ON (log.profile_type_id = 1 AND r.id = log.profile_id)
  LEFT JOIN jos_app_candidate_profile    AS c     ON (log.profile_type_id = 2 AND c.id = log.profile_id)
  LEFT JOIN jos_app_promoter_profile     AS p     ON (log.profile_type_id = 3 AND p.id = log.profile_id)
  WHERE 1=1
";

$types = '';
$args  = [];

/* ROLE BASED FILTER */
if ($logged_admin_roleid != 1) {
    // Not superadmin → show only assigned users
    $sql .= " AND u.ac_manager_id = ? ";
    $types .= 'i';
    $args[] = $logged_admin_id;
}
/* ROLE BASED PROFILE TYPE RESTRICTION */
if ($logged_admin_roleid == 13) {
    // Employer role → only profile_type_id = 1
    $sql .= " AND log.profile_type_id = 1 ";
} elseif ($logged_admin_roleid == 3) {
    // Role 3 → only profile_type_id = 2
    $sql .= " AND log.profile_type_id = 2 ";
}
/* AC Manager Dropdown Filter (only for superadmin normally) */
/* Superadmin Manual Filter */
if ($logged_admin_roleid == 1) {
    if ($ac_manager_filter === 0) {
        $sql .= " AND u.ac_manager_id IS NULL ";
    } elseif ($ac_manager_filter > 0) {
        $sql .= " AND u.ac_manager_id = ? ";
        $types .= 'i';
        $args[] = $ac_manager_filter;
    }
}

if ($from_date && $to_date) {
    $sql .= " AND DATE(log.start_date) BETWEEN ? AND ? ";
    $types .= 'ss';
    $args[] = $from_date;
    $args[] = $to_date;
}

if ($profile_type_id > 0) {
    $sql  .= " AND log.profile_type_id = ? ";
    $types .= 'i';
    $args[] = $profile_type_id;
}
if ($payment_status !== '') {
    $sql  .= " AND log.payment_status = ? ";
    $types .= 's';
    $args[] = $payment_status;
}
/* Invoice Type: free vs paid (same logic we discussed) */
if ($invoice_type === 'free') {
    $sql .= " AND (COALESCE(log.amount_paid,0)=0 
                 OR COALESCE(log.payment_id,'')='' 
                 OR LOWER(COALESCE(log.payment_status,'')) IN ('free','complimentary')) ";
} elseif ($invoice_type === 'paid') {
    $sql .= " AND (COALESCE(log.amount_paid,0) > 0 
                 AND LOWER(COALESCE(log.payment_status,'')) NOT IN ('free','complimentary')) ";
}
if ($q !== '') {
    $like = '%' . $q . '%';
    $sql .= " AND (log.invoiceno LIKE ? OR log.payment_id LIKE ? OR CAST(log.userid AS CHAR) LIKE ?) ";
    $types .= 'sss';
    $args[] = $like;
    $args[] = $like;
    $args[] = $like;
}

$sql .= " ORDER BY log.start_date DESC, log.id DESC ";
if ($show !== 'all') {
    $sql .= " LIMIT 50 ";
}

/* ---------------- Execute ---------------- */
// $stmt = $con->prepare($sql);
// if (!$stmt) {
//     die('DB error: ' . $con->error);
// }
// $stmt->bind_param($types, ...$args);
// $stmt->execute();
// $res = $stmt->get_result();


$stmt = $con->prepare($sql);
if (!$stmt) {
    die('DB error: ' . $con->error);
}

if ($types !== '') {
    $stmt->bind_param($types, ...$args);
}

$stmt->execute();
$res = $stmt->get_result();

$rows = [];
$total_amount = 0.0;
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
    $total_amount += (float)$r['amount_paid'];
}

/* ---------------- Options (same naming style) ---------------- */
$ptype_opts   = [0 => 'All', 1 => 'Recruiter', 2 => 'Job Seeker', 3 => 'Promoter'];
$pstatus_opts = ['' => 'All', 'success' => 'success', 'failed' => 'failed', 'pending' => 'pending', 'free' => 'free'];
$itype_opts   = ['all' => 'All', 'paid' => 'Paid Invoices', 'free' => 'Free Signup Invoices'];
$show_opts    = ['last50' => 'Last 50', 'all' => 'View All'];


$acManagers = [];
$qam = mysqli_query($con, "SELECT id, name FROM jos_admin_users WHERE status=1 ORDER BY name ASC");
if ($qam) {
    while ($a = mysqli_fetch_assoc($qam)) {
        $acManagers[] = $a;
    }
}
/* ---------------- Render (STANDARD) ---------------- */
ob_start();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Subscription Invoice List</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/adminconsole/assets/ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* Keep inline CSS minimal & in line with cashback_report */
        .table thead th {
            position: sticky;
            top: 0;
        }

        .mono {
            font-variant-numeric: tabular-nums;
        }

        .muted {
            color: #9ca3af;
        }

        .toolbar .row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .toolbar .row>* {
            flex: 1 1 auto;
        }

        .toolbar .row .grow {
            flex: 2 1 320px;
        }

        .badge.pt {
            background: #dbeafe;
            color: #1e3a8a;
            font-weight: 600;
        }

        /* readable 'chip' */
        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .center {
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="master-wrap">

        <div class="headbar">
            <div class="title">Subscription Invoice List</div>
        </div>

        <div class="card">

            <form method="get" class="toolbar">
                <div class="row">
                    <div>
                        <label class="lbl">From (start date)</label>
<input class="inp flatpickr" 
       type="text" 
       name="from" 
       placeholder="DD-MM-YYYY"
       value="<?= $from_date ? h(date('d-m-Y', strtotime($from_date))) : '' ?>">
                    </div>
                    <div>
                        <label class="lbl">To (start date)</label>
<input class="inp flatpickr" 
       type="text" 
       name="to" 
       placeholder="DD-MM-YYYY"
       value="<?= $to_date ? h(date('d-m-Y', strtotime($to_date))) : '' ?>">                    </div>
                    <?php if ($logged_admin_roleid == 1): ?>
                        <div>
                            <label class="lbl">Profile Type</label>
                            <select class="inp" name="profile_type_id">
                                <?php foreach ($ptype_opts as $k => $v): ?>
                                    <option value="<?= $k ?>" <?= ($k === $profile_type_id) ? 'selected' : '' ?>><?= h($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <?php if ($logged_admin_roleid == 1): ?>
                        <div>
                            <label class="lbl">AC Operator</label>
                            <select class="inp" name="ac_manager_filter">
                                <option value="-1" <?= $ac_manager_filter === -1 ? 'selected' : '' ?>>All</option>
                                <option value="0" <?= $ac_manager_filter === 0 ? 'selected' : '' ?>>Unassigned</option>
                                <?php foreach ($acManagers as $a): ?>
                                    <option value="<?= (int)$a['id'] ?>"
                                        <?= $ac_manager_filter === (int)$a['id'] ? 'selected' : '' ?>>
                                        <?= h($a['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div>
                        <label class="lbl">Payment Status</label>
                        <select class="inp" name="payment_status">
                            <?php foreach ($pstatus_opts as $k => $v): ?>
                                <option value="<?= h($k) ?>" <?= ($k === $payment_status) ? 'selected' : '' ?>><?= h($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div>
                        <label class="lbl">Invoice Type</label>
                        <select class="inp" name="invoice_type">
                            <?php foreach ($itype_opts as $k => $v): ?>
                                <option value="<?= h($k) ?>" <?= ($k === $invoice_type) ? 'selected' : '' ?>><?= h($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grow">
                        <label class="lbl">Search (Invoice / Payment / User)</label>
                        <input class="inp" type="text" name="q" placeholder="APP2025-00041 / pay_xxx / 123" value="<?= h($q) ?>">
                    </div>
                    <div>
                        <label class="lbl">Show</label>
                        <select class="inp" name="show">
                            <?php foreach ($show_opts as $k => $v): ?>
                                <option value="<?= h($k) ?>" <?= ($k === $show) ? 'selected' : '' ?>><?= h($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="align-self:flex-end">
                        <button class="btn primary" type="submit">Apply</button>
                        <a class="btn secondary" href="<?= h(strtok($_SERVER['REQUEST_URI'], '?')) ?>">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="card table-wrap" style="margin-top:12px">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:70px">SR No</th>
                        <th style="width:210px">Invoice Date / No</th>
                        <th>Party</th>
                        <th style="width:140px">Amount (₹)</th>
                        <th style="width:140px">Profile</th>
                        <th style="width:200px">Plan / Validity</th>
                        <th style="width:220px">Payment ID</th>
                        <th style="width:120px">Status</th>
                        <th style="width:100px">User ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center;color:#9ca3af">No records for the selected filters.</td>
                        </tr>
                        <?php else: $sr = 1;
                        foreach ($rows as $r): ?>
                            <tr>
                                <td class="center"><?= $sr++ ?></td>
                                <td>
                                    <strong><?= h(fmt_dmy($r['start_date'])) ?></strong><br>
                                    <span class="muted">Exp: <?= h(fmt_dmy($r['end_date'])) ?></span><br>
                                    <strong><?= h($r['invoiceno']) ?></strong>
                                </td>
                                <td><?= h($r['party_name']) ?></td>
                                <td class="amount mono">₹ <?= number_format((float)$r['amount_paid'], 2) ?></td>
                                <td><span class="badge pt"><?= h($r['profile_name'] ?: ($ptype_opts[$r['profile_type_id']] ?? 'Unknown')) ?></span></td>
                                <td>
                                    <?= h($r['plan_name']) ?><br>
                                    <span class="muted"><?= (int)$r['validity_months'] ?> mo</span>
                                </td>
                                <td class="mono"><?= h($r['payment_id']) ?></td>
                                <td><?= h($r['payment_status']) ?></td>
                                <td class="center mono"><?= (int)$r['userid'] ?></td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                    <tfoot>
                        <tr>
                            <th colspan="3" style="text-align:right">Total</th>
                            <th class="amount mono">₹ <?= number_format($total_amount, 2) ?></th>
                            <th colspan="5"></th>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>

            <p class="muted" style="margin-top:8px;">
                <?php if ($from_date && $to_date): ?>
                    Showing <?= count($rows) ?> record(s) from <?= h(fmt_d($from_date)) ?> to <?= h(fmt_d($to_date)) ?>
                <?php else: ?>
                    Showing <?= count($rows) ?> lifetime record(s).
                <?php endif; ?> <?= $show === 'all' ? '(all records)' : '(last 50)' ?>.
            </p>
        </div>

    </div><!-- /master-wrap -->
    <!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
     document.addEventListener("DOMContentLoaded", function() {
    flatpickr(".flatpickr", {
        altInput: true, // user sees formatted date
        altFormat: "d-m-Y", // display format
        dateFormat: "Y-m-d", // value sent to backend
        allowInput: false
      });
    });
</script>
</body>

</html>
<?php
echo ob_get_clean();
