<?php
@ini_set('display_errors', '1');
@error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/initialize.php'; // $con, csrf_token(), verify_csrf()
require_login();
/* ---------------- Logged In User ---------------- */
$loggedUserId = (int)($_SESSION['admin_user']['id'] ?? 0);
$loggedRoleId = (int)($_SESSION['admin_user']['role_id'] ?? 0);


global $con;

/* ---------------- Tables ---------------- */
$TABLE     = 'jos_app_crm_leads';
$STATUSTBL = 'jos_app_crm_lead_statuses';
$SOURCETBL = 'jos_app_crm_lead_sources';
$PLANTBL   = 'jos_app_subscription_plans';
$HISTTBL   = 'jos_app_crm_lead_status_history';

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
function fmt_dt($dt)
{
  return $dt ? date('d-m-Y h:i A', strtotime($dt)) : '—';
}

function parse_followup_to_db($v)
{
  $v = trim((string)$v);
  if ($v === '') return null;
  $dt = DateTime::createFromFormat('d-m-Y h:i A', $v);
  if (!$dt) $dt = DateTime::createFromFormat('d-m-Y h:i a', $v);
  if (!$dt) return null;
  return $dt->format('Y-m-d H:i:s');
}

function stmt_bind(mysqli_stmt $st, string $types, array $params): void
{
  if (strlen($types) !== count($params)) {
    throw new RuntimeException("bind_param mismatch");
  }
  $refs = [];
  foreach ($params as $k => $v) $refs[$k] = &$params[$k];
  array_unshift($refs, $types);
  call_user_func_array([$st, 'bind_param'], $refs);
}

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

/* Plans */
$plans = [];
$plansByType = [0 => [], 1 => [], 2 => []];

if (table_exists($con, $PLANTBL)) {
  $rs = mysqli_query($con, "SELECT id,profile_type,plan_name FROM `$PLANTBL` WHERE plan_status=1");
  while ($r = mysqli_fetch_assoc($rs)) {
    $pid = (string)$r['id'];
    $ptype = (int)$r['profile_type'];
    $plans[$pid] = ['name' => $r['plan_name'], 'ptype' => $ptype];
    if (isset($plansByType[$ptype])) {
      $plansByType[$ptype][] = ['id' => $pid, 'name' => $r['plan_name']];
    }
  }
}

function plan_allowed_for_profile(array $plans, ?string $plan_id, int $profile_type): bool
{
  if (!$plan_id) return false;
  if (!isset($plans[$plan_id])) return false;
  $ptype = (int)$plans[$plan_id]['ptype'];
  return ($ptype === 0 || $ptype === $profile_type);
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

/* =========================
   ACTION HANDLERS
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (!verify_csrf($_POST['csrf'] ?? null)) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid CSRF']);
    exit;
  }

  /* -------- DELETE -------- */
  if (isset($_POST['delete'])) {

    $id = (int)$_POST['id'];

    $st = $con->prepare("DELETE FROM `$TABLE` WHERE id=?");
    $st->bind_param("i", $id);

    if ($st->execute()) {
      header("Location: lead_list.php?ok=Deleted");
      exit;
    }
  }


  /* -------- STATUS UPDATE -------- */
  if (isset($_POST['status_update'])) {

    header('Content-Type: application/json');

    $lead_id = (int)$_POST['lead_id'];
    $status_id = (int)$_POST['to_status_id'];
    $remark = trim($_POST['remark']);
    $followup = parse_followup_to_db($_POST['followup_at'] ?? '');
    $plan_id = (string)($_POST['onboarded_plan_id'] ?? '0');

    $st = $con->prepare("SELECT profile_type,status_id FROM `$TABLE` WHERE id=?");
    $st->bind_param("i", $lead_id);
    $st->execute();
    $lead = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$lead) {
      echo json_encode(['ok' => false, 'msg' => 'Lead not found']);
      exit;
    }

    $profile_type = (int)$lead['profile_type'];
    $old_status = (int)$lead['status_id'];

    if ($plan_id != '0') {
      if (!plan_allowed_for_profile($plans, $plan_id, $profile_type)) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid plan']);
        exit;
      }
    } else {
      $plan_id = null;
    }

    $sql = "UPDATE `$TABLE`
          SET status_id=?,
              last_status_reason=?,
              followup_at=?,
              onboarded_plan_id=?
          WHERE id=?";

    $st = $con->prepare($sql);

    stmt_bind($st, "isssi", [
      $status_id,
      $remark,
      $followup,
      $plan_id,
      $lead_id
    ]);

    if (!$st->execute()) {
      echo json_encode(['ok' => false, 'msg' => 'Update failed']);
      exit;
    }

    $st->close();

    echo json_encode(['ok' => true, 'msg' => 'Updated']);
    exit;
  }
}
/* ---------------- POST Filters (Advanced) ---------------- */
$ptypePost   = isset($_POST['profile_type_id']) ? (int)$_POST['profile_type_id'] : 0;
$acmPost     = isset($_POST['ac_manager_id']) ? (int)$_POST['ac_manager_id'] : 0;
$dateFrom    = !empty($_POST['date_from']) ? $_POST['date_from'] : '';
$dateTo      = !empty($_POST['date_to']) ? $_POST['date_to'] : '';

/* ---------------- Filters ---------------- */
$q         = trim($_GET['q'] ?? '');
$ptype     = (int)($_GET['ptype'] ?? 0);
$stFilter  = (int)($_GET['status'] ?? 0);
$srcFilter = (int)($_GET['source'] ?? 0);
$assFilter = (int)($_GET['assignee'] ?? 0);
$created_from = $_GET['created_from'] ?? '';
$created_to   = $_GET['created_to'] ?? '';



$all = isset($_GET['all']);
$lim = $all ? 0 : 50;

/**
 * Build WHERE.
 * We make 2 versions:
 *  - $whereBase : excludes status filter (used for top cards counts)
 *  - $whereFull : includes status filter (used for listing)
 */
$whereBase = " WHERE 1=1 ";
$bindBase  = [];
$typesBase = '';


/* ---------------- Dashboard Mode Filters ---------------- */

$mode        = $_POST['mode'] ?? $_GET['mode'] ?? '';
$admin_id    = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : (int)($_GET['admin_id'] ?? 0);
$profileType = isset($_POST['profile_type_id']) ? (int)$_POST['profile_type_id'] : (int)($_GET['profile_type_id'] ?? 0);

/* Dashboard date handling */
/* Dashboard date handling (NO REDIRECT) */
/* ---------------- Dashboard POST Handling (NO REDIRECT) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Receive everything from dashboard
  $mode        = $_POST['mode'] ?? '';
  $admin_id    = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;
  $profileType = isset($_POST['profile_type_id']) ? (int)$_POST['profile_type_id'] : 0;

  $dateFrom = $_POST['from'] ?? '';
  $dateTo   = $_POST['to'] ?? '';

  if (!empty($dateFrom) && !empty($dateTo)) {
    $created_from = date('Y-m-d H:i:s', strtotime($dateFrom));
    $created_to   = date('Y-m-d H:i:s', strtotime($dateTo));
  }
}



/* Show debug info (REMOVE after testing) */
// if ($mode) {
//     echo "<div style='background:#1e293b;padding:10px;margin:10px 0;border-radius:6px'>
//             <strong>Dashboard Params:</strong><br>
//             Mode: {$mode} <br>
//             Admin ID: {$admin_id} <br>
//             Profile Type: {$profileType} <br>
//             From: {$dateFrom} <br>
//             To: {$dateTo}
//           </div>";
//           exit();
// }


if ($profileType > 0) {
  $whereBase .= " AND l.profile_type = ?";
  $bindBase[] = $profileType;
  $typesBase .= 'i';
}

if ($admin_id > 0) {

  if ($mode === 'leads_assigned') {
    $whereBase .= " AND l.assigned_to = ?";
    $bindBase[] = $admin_id;
    $typesBase .= 'i';
  }

  if ($mode === 'leads_self') {
    $whereBase .= " AND l.created_by = ?";
    $bindBase[] = $admin_id;
    $typesBase .= 'i';
  }
}

if ($dateFrom && $dateTo) {
  $whereBase .= " AND l.created_at BETWEEN ? AND ?";
  $bindBase[] = $dateFrom;
  $bindBase[] = $dateTo;
  $typesBase .= 'ss';
}

/* ---------------- Default Role Based Restriction ---------------- */
/* Apply ONLY when coming normally (not from dashboard mode) */

if (empty($mode)) {

  // If NOT Super Admin (role_id != 1)
  if ($loggedRoleId !== 1 && $loggedUserId > 0) {

    $whereBase .= " AND (l.created_by = ? OR l.assigned_to = ?)";
    $bindBase[] = $loggedUserId;
    $bindBase[] = $loggedUserId;
    $typesBase .= 'ii';
  }
}


if ($ptype === 1 || $ptype === 2) {
  $whereBase .= " AND l.profile_type=?";
  $bindBase[] = $ptype;
  $typesBase .= 'i';
}
if ($srcFilter > 0) {
  $whereBase .= " AND l.source_id=?";
  $bindBase[] = $srcFilter;
  $typesBase .= 'i';
}
if ($assFilter > 0) {
  $whereBase .= " AND l.assigned_to=?";
  $bindBase[] = $assFilter;
  $typesBase .= 'i';
}

if ($q !== '') {
  $whereBase .= " AND (
    l.company_name LIKE ? OR l.candidate_name LIKE ? OR
    l.phone1 LIKE ? OR l.phone2 LIKE ? OR
    l.email LIKE ?
  )";
  $like = "%$q%";
  array_push($bindBase, $like, $like, $like, $like, $like);
  $typesBase .= 'sssss';
}

if (!empty($created_from)) {
  $fromDateTime = $created_from . ' 00:00:00';
  $whereBase .= " AND l.created_at >= ?";
  $bindBase[] = $fromDateTime;
  $typesBase .= 's';
}

if (!empty($created_to)) {
  $toDateTime = $created_to . ' 23:59:59';
  $whereBase .= " AND l.created_at <= ?";
  $bindBase[] = $toDateTime;
  $typesBase .= 's';
}



$whereFull = $whereBase;
$bindFull  = $bindBase;
$typesFull = $typesBase;

if ($stFilter > 0) {
  $whereFull .= " AND l.status_id=?";
  $bindFull[] = $stFilter;
  $typesFull .= 'i';
}



/* ---------------- Top Cards Counts (by status) ---------------- */
$statusCounts = []; // status_id => cnt
$totalAll = 0;

$st = $con->prepare("SELECT COUNT(*) c FROM `$TABLE` l $whereBase");
if ($bindBase) $st->bind_param($typesBase, ...$bindBase);
$st->execute();
$totalAll = (int)$st->get_result()->fetch_assoc()['c'];
$st->close();

$st = $con->prepare("SELECT l.status_id, COUNT(*) cnt FROM `$TABLE` l $whereBase GROUP BY l.status_id");
if ($bindBase) $st->bind_param($typesBase, ...$bindBase);
$st->execute();
$rs = $st->get_result();
while ($r = $rs->fetch_assoc()) $statusCounts[(int)$r['status_id']] = (int)$r['cnt'];
$st->close();

/* ---------------- List Count ---------------- */
$total = 0;
$st = $con->prepare("SELECT COUNT(*) c FROM `$TABLE` l $whereFull");
if ($bindFull) $st->bind_param($typesFull, ...$bindFull);
$st->execute();
$total = (int)$st->get_result()->fetch_assoc()['c'];
$st->close();

/* ---------------- Rows ---------------- */
$rows = [];
$sql = "SELECT l.*,
            s.status_name, s.status_code,
            src.source_name,
            p.plan_name AS onboarded_plan_name,
            au.name AS assigned_by_name
        FROM `$TABLE` l
        LEFT JOIN `$STATUSTBL` s ON s.id=l.status_id
        LEFT JOIN `$SOURCETBL` src ON src.id=l.source_id
        LEFT JOIN `$PLANTBL` p ON p.id=l.onboarded_plan_id
        LEFT JOIN `$ADMINUSERS` au ON au.id = l.assigned_by
        $whereFull
        ORDER BY l.id DESC";
if (!$all) $sql .= " LIMIT $lim";

$st = $con->prepare($sql);
if ($bindFull) $st->bind_param($typesFull, ...$bindFull);
$st->execute();
$rs = $st->get_result();
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$st->close();


/* ---------------- VIEW ---------------- */
ob_start(); ?>
<link rel="stylesheet" href="/adminconsole/assets/ui.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<style>
  /* .pac-modal{
position:fixed;
top:0;
left:0;
width:100%;
height:100%;
background:rgba(0,0,0,.6);
z-index:9999;
display:none;
padding-top:100px;
} */



  .hide {
    display: none !important;
  }

  .topcards {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin: 10px 0 14px;
  }

  .scard {
    min-width: 160px;
    flex: 1;
    max-width: 220px;
    background: #0f1a2e;
    border: 1px solid rgba(148, 163, 184, .18);
    border-radius: 14px;
    padding: 12px;
    cursor: pointer;
    transition: transform .08s ease;
  }

  .scard:hover {
    transform: translateY(-1px);
  }

  .scard .k {
    color: #9ca3af;
    font-size: 12px;
  }

  .scard .v {
    font-size: 22px;
    font-weight: 800;
    margin-top: 6px;
    color: #e5e7eb;
  }

  .scard.active {
    outline: 2px solid rgba(34, 197, 94, .65);
  }

  .filtersbar {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    margin: 10px 0 12px;
  }

  /* Modal CSS */
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
</style>
<script>
  window.PACIFIC_PLANS = <?= json_encode($plansByType, JSON_UNESCAPED_UNICODE) ?>;

  function buildPlanOptions(profileType, selectedId) {

    const common = window.PACIFIC_PLANS[0] || [];
    const typed = window.PACIFIC_PLANS[profileType] || [];

    const list = [...common, ...typed];

    let html = '<option value="0">— Select Plan —</option>';

    list.forEach(p => {
      const sel = String(p.id) === String(selectedId) ? ' selected' : '';
      html += `<option value="${p.id}" ${sel}>${p.name}</option>`;
    });

    return html;
  }
</script>

<h2 style="margin:8px 0 12px">Leads</h2>

<?php if (isset($_GET['ok'])): ?>
  <div class="alert ok"><?= h($_GET['ok']) ?></div>
<?php endif; ?>

<div class="pac-panel">
  <div class="pac-head">
    <div>
      <h3 style="margin:0">Leads List</h3>
      <div class="pac-sub">Click a status card to filter the list</div>
    </div>

    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <button class="btn secondary" type="button" id="btnToggleFilters">Hide Filters</button>
      <?php /* keep your Add button if you want later; currently list-only file */ ?>
    </div>
  </div>

  <!-- Status cards -->
  <div class="topcards" id="statusCards">
    <div class="scard <?= ($stFilter === 0 ? 'active' : '') ?>" data-status="0">
      <div class="k">Total Records</div>
      <div class="v"><?= (int)$totalAll ?></div>
    </div>

    <?php foreach ($statuses as $sid => $s):
      $cnt = (int)($statusCounts[$sid] ?? 0);
    ?>
      <div class="scard <?= ($stFilter === $sid ? 'active' : '') ?>" data-status="<?= (int)$sid ?>">
        <div class="k"><?= h($s['name']) ?></div>
        <div class="v"><?= $cnt ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Filters -->
  <div id="filterPanel">
    <form method="get" class="filtersbar" id="filterForm">
      <input type="text" name="q" class="inp" placeholder="Search company/candidate/phone/email" value="<?= h($q) ?>" style="min-width:240px">


      <div style="display:flex;flex-direction:column;min-width:160px">
        <span style="font-size:12px;color:#9ca3af;margin-bottom:2px">From Date</span>
        <input type="text"
          name="created_from"
          class="inp datepicker"
          value="<?= h($created_from ?? '') ?>"
          placeholder="DD-MM-YYYY">
      </div>

      <div style="display:flex;flex-direction:column;min-width:160px">
        <span style="font-size:12px;color:#9ca3af;margin-bottom:2px">To Date</span>
        <input type="text"
          name="created_to"
          class="inp datepicker"
          value="<?= h($created_to ?? '') ?>"
          placeholder="DD-MM-YYYY">
      </div>


      <?php if ($loggedRoleId === 1): ?> <!-- Super Admin Only -->
        <select name="ptype" class="inp">
          <option value="0">All Types</option>
          <option value="1" <?= $ptype === 1 ? 'selected' : '' ?>>Employer</option>
          <option value="2" <?= $ptype === 2 ? 'selected' : '' ?>>Jobseeker</option>
        </select>
      <?php endif; ?>


      <select name="status" class="inp" id="statusSelect">
        <option value="0">All Status</option>
        <?php foreach ($statuses as $sid => $s): ?>
          <option value="<?= (int)$sid ?>" <?= ($stFilter === $sid ? 'selected' : '') ?>><?= h($s['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="source" class="inp">
        <option value="0">All Sources</option>
        <?php foreach ($sources as $sid => $nm): ?>
          <option value="<?= (int)$sid ?>" <?= ($srcFilter === $sid ? 'selected' : '') ?>><?= h($nm) ?></option>
        <?php endforeach; ?>
      </select>

      <?php if ($loggedRoleId === 1): ?> <!-- Super Admin Only -->

        <select name="assignee" class="inp">
          <option value="0">All Assignees</option>
          <?php foreach ($adminUsers as $uid => $nm): ?>
            <option value="<?= (int)$uid ?>" <?= ($assFilter === $uid ? 'selected' : '') ?>><?= h($nm) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>


      <button class="btn secondary" type="submit">Apply</button>

      <?php if (!$all && $total > $lim): ?>
        <a class="btn secondary" href="<?= h(keep_params(['all' => 1])) ?>">View All (<?= (int)$total ?>)</a>
      <?php endif; ?>
      <?php if ($all): ?>
        <a class="btn secondary" href="<?= h(keep_params(['all' => null])) ?>">Last 50</a>
      <?php endif; ?>
    </form>
  </div>

  <div style="margin:6px 0 10px;color:#9ca3af">
    Showing <strong><?= !$all ? count($rows) : $total ?></strong> of <strong><?= (int)$total ?></strong>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>SR</th>
          <th>Lead Date</th>
          <th>Type</th>
          <th>Company / Candidate</th>
          <th>Phone</th>
          <th>City</th>
          <th>Source</th>
          <th>Status</th>
          <th>On-boarded Plan</th>
          <th>Assigned By</th>
          <th>Assigned To</th>
          <th>Updated</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="10" style="color:#9ca3af">No records</td>
          </tr>
        <?php endif; ?>

        <?php $sr = 0;
        foreach ($rows as $r): $sr++;
          $pt = (int)($r['profile_type'] ?? 1);
          $typeLabel = ($pt === 1) ? 'Employer' : 'Jobseeker';
          $name = ($pt === 1) ? ($r['company_name'] ?: '—') : ($r['candidate_name'] ?: '—');
          $ass = (int)($r['assigned_to'] ?? 0);
          $assName = $ass > 0 ? ($adminUsers[$ass] ?? ('#' . $ass)) : '—';
          $assignedByName = $r['assigned_by_name'] ?? '—';
          $assignedAt     = fmt_dt($r['assigned_at'] ?? null);
        ?>
          <tr>
            <td><?= (int)$sr ?></td>
            <td><?= h(date('d-m-Y', strtotime($r['created_at']))) ?></td>
            <td><?= h($typeLabel) ?></td>
            <td><?= h($name) ?></td>
            <td><?= h($r['phone1'] ?: '—') ?></td>
            <td><?= h($r['city_location'] ?: '—') ?></td>
            <td>
              <?= h($r['source_name'] ?? '—') ?>
              <?php if (!empty($r['source_detail'])): ?>
                <br>
                <small style="color:#9ca3af">
                  (<?= h($r['source_detail']) ?>)
                </small>
              <?php endif; ?>
            </td>
            <td><span class="badge on"><?= h($r['status_name'] ?? '—') ?></span></td>
            <td><?= h($r['onboarded_plan_name'] ?? '—') ?></td>
            <td>
              <?= h($assignedByName) ?><br>
              <small style="color:#9ca3af"><?= h($assignedAt) ?></small>
            </td>
            <td><?= h($assName) ?></td>
            <td><?= h(fmt_dt($r['updated_at'] ?? null)) ?></td>
            <td>

              <button class="btn secondary"
                onclick='openStatusModal(<?= (int)$r["id"] ?>,
<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                Update Status
              </button>

              <a class="btn secondary"
                href="lead.php?edit=<?= (int)$r['id'] ?>"
                onclick="sessionStorage.setItem('lead_back', window.location.href)">
                Edit
              </a>

              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= h($r['id']) ?>">

                <button type="submit"
                  name="delete"
                  class="btn red"
                  onclick="return confirmDelete();">
                  Delete
                </button>
              </form>

            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>


<!-- STATUS UPDATE MODAL -->
<div id="statusModal" class="pac-modal">
  <div class="pac-panel">
    <div class="pac-head">
      <h3 style="margin:0">Update Lead Status</h3>
      <button class="btn gray" type="button" onclick="closeStatusModal()">Close</button>
    </div>

    <div id="leadLabels" class="pac-labelgrid"></div>

    <hr style="opacity:.18;margin:14px 0">

    <form id="statusForm" method="post" class="pac-grid3">
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

      <div class="actions">
        <button class="btn green" type="submit">Update</button>
        <span id="m_msg" style="color:#9ca3af"></span>
      </div>
    </form>
  </div>
</div>
<script>
  // hide/show filter panel (remember)
  (function() {
    const panel = document.getElementById('filterPanel');
    const btn = document.getElementById('btnToggleFilters');
    const key = 'lead_filters_hidden';

    function setHidden(h) {
      panel.classList.toggle('hide', !!h);
      btn.textContent = h ? 'Show Filters' : 'Hide Filters';
      try {
        localStorage.setItem(key, h ? '1' : '0');
      } catch (e) {}
    }

    let hidden = false;
    try {
      hidden = (localStorage.getItem(key) === '1');
    } catch (e) {}
    setHidden(hidden);

    btn.addEventListener('click', function() {
      hidden = !hidden;
      setHidden(hidden);
    });
  })();

  // click status card => set status filter and submit
  (function() {
    const cards = document.querySelectorAll('#statusCards .scard');
    const statusSelect = document.getElementById('statusSelect');
    const form = document.getElementById('filterForm');

    cards.forEach(c => {
      c.addEventListener('click', () => {
        const sid = c.getAttribute('data-status') || '0';
        if (statusSelect) statusSelect.value = sid;

        // remove "all" when changing filter (optional)
        const url = new URL(window.location.href);
        url.searchParams.set('status', sid);
        url.searchParams.delete('all'); // back to last 50
        window.location.href = url.toString();
      });
    });
  })();

  document.addEventListener("DOMContentLoaded", function() {
    flatpickr(".datepicker", {
      altInput: true, // user sees formatted date
      altFormat: "d-m-Y", // display format
      dateFormat: "Y-m-d", // value sent to backend
      allowInput: false
    });
  });

  let _modalProfileType = 1;
  let _modalFP = null;

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g,
      m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      } [m])
    );
  }

  function openStatusModal(id, row) {

    document.getElementById("m_lead_id").value = id;

    const pt = parseInt(row.profile_type || "1");
    _modalProfileType = pt;

    const name = (pt === 1) ?
      (row.company_name || "—") :
      (row.candidate_name || "—");

    const labels = [
      ["Type", pt === 1 ? "Employer" : "Jobseeker"],
      ["Name", name],
      ["Phone 1", row.phone1 || "—"],
      ["Phone 2", row.phone2 || "—"],
      ["Email", row.email || "—"],
      ["City", row.city_location || "—"],
      ["Source", row.source_name || "—"],
      ["Current Status", row.status_name || "—"],
      ["On-boarded Plan", row.onboarded_plan_name || "—"],
      ["Assigned To", row.assigned_to || "—"],
      ["Updated", row.updated_at || "—"]
    ];

    const wrap = document.getElementById("leadLabels");

    wrap.innerHTML = labels.map(x => `
 <div class="pac-label">
  <div class="k">${escapeHtml(x[0])}</div>
  <div class="v">${escapeHtml(x[1])}</div>
 </div>
 `).join("");

    if (row.status_id)
      document.getElementById("m_status_id").value = row.status_id;

    document.getElementById("m_followup_at").value = "";
    document.getElementById("m_remark").value = "";
    document.getElementById("m_msg").innerHTML = "";

    document.getElementById("m_plan_id").innerHTML =
      buildPlanOptions(pt, row.onboarded_plan_id || "0");

    document.getElementById("statusModal").style.display = "block";

    syncModalFields();

    if (window.flatpickr) {

      if (_modalFP) _modalFP.destroy();

      _modalFP = flatpickr("#m_followup_at", {

        enableTime: true,
        time_24hr: false,
        dateFormat: "d-m-Y h:i K",
        allowInput: true,
        appendTo: document.getElementById("statusModal")

      });

    }
  }

  function syncModalFields() {

    const sel = document.getElementById("m_status_id");

    const opt = sel.options[sel.selectedIndex];

    const code = opt.getAttribute("data-code");

    document.getElementById("m_followup_box")
      .classList.toggle("hide", code !== "FOLLOW_UP");

    document.getElementById("m_plan_box")
      .classList.toggle("hide", code !== "ON_BOARDED");

    if (code === "ON_BOARDED") {

      const cur = document.getElementById("m_plan_id").value;

      document.getElementById("m_plan_id").innerHTML =
        buildPlanOptions(_modalProfileType, cur);

    }

  }

  function closeStatusModal() {

    document.getElementById("statusModal").style.display = "none";

  }

  document.getElementById("statusForm").addEventListener("submit", async function(e) {

    e.preventDefault();

    const msg = document.getElementById("m_msg");

    msg.innerHTML = "Saving...";

    const fd = new FormData(this);

    const res = await fetch("", {
      method: "POST",
      body: fd
    });

    const data = await res.json();

    if (data.ok) {

      msg.innerHTML = "Saved";

      setTimeout(() => {
        location.reload();
      }, 500);

    } else {

      msg.innerHTML = data.msg;

    }

  });

  function confirmDelete() {
    return confirm("Are you sure you want to delete this lead?");
  }
</script>

<?php
echo ob_get_clean();
