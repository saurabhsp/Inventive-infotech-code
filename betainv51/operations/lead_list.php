<?php
@ini_set('display_errors', '1');
@error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/initialize.php'; // $con, csrf_token(), verify_csrf()
require_login();

global $con;

/* ---------------- Tables ---------------- */
$TABLE     = 'jos_app_crm_leads';
$STATUSTBL = 'jos_app_crm_lead_statuses';
$SOURCETBL = 'jos_app_crm_lead_sources';
$PLANTBL   = 'jos_app_subscription_plans';

/* ---------------- Helpers ---------------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function clean($v){ return trim((string)$v); }
function table_exists(mysqli $con, string $name): bool {
  $name = mysqli_real_escape_string($con, $name);
  $rs = mysqli_query($con, "SHOW TABLES LIKE '$name'");
  return ($rs && mysqli_num_rows($rs) > 0);
}
function keep_params(array $changes = []) {
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
function fmt_dt($dt){
  return $dt ? date('d-m-Y h:i A', strtotime($dt)) : '—';
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

$mode        = $_POST['mode'] ?? '';
$admin_id    = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;
$profileType = isset($_POST['profile_type_id']) ? (int)$_POST['profile_type_id'] : 0;
$dateFrom    = $_POST['from'] ?? '';
$dateTo      = $_POST['to'] ?? '';

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


if ($ptype === 1 || $ptype === 2) { $whereBase .= " AND l.profile_type=?"; $bindBase[]=$ptype; $typesBase.='i'; }
if ($srcFilter > 0)              { $whereBase .= " AND l.source_id=?";    $bindBase[]=$srcFilter; $typesBase.='i'; }
if ($assFilter > 0)              { $whereBase .= " AND l.assigned_to=?";  $bindBase[]=$assFilter; $typesBase.='i'; }

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
            p.plan_name AS onboarded_plan_name
        FROM `$TABLE` l
        LEFT JOIN `$STATUSTBL` s ON s.id=l.status_id
        LEFT JOIN `$SOURCETBL` src ON src.id=l.source_id
        LEFT JOIN `$PLANTBL` p ON p.id=l.onboarded_plan_id
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

<style>
  .pac-panel{
    background:#0b1220;border:1px solid rgba(148,163,184,.18);
    border-radius:16px;padding:16px;box-shadow:0 14px 50px rgba(0,0,0,.45);
  }
  .pac-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px;}
  .pac-sub{color:#9ca3af;font-size:12px;margin-top:6px;}
  .hide{display:none !important;}
  .topcards{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 14px;}
  .scard{
    min-width:160px;flex:1;max-width:220px;
    background:#0f1a2e;border:1px solid rgba(148,163,184,.18);
    border-radius:14px;padding:12px;cursor:pointer;
    transition:transform .08s ease;
  }
  .scard:hover{transform:translateY(-1px);}
  .scard .k{color:#9ca3af;font-size:12px;}
  .scard .v{font-size:22px;font-weight:800;margin-top:6px;color:#e5e7eb;}
  .scard.active{outline:2px solid rgba(34,197,94,.65);}
  .filtersbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0 12px;}
</style>

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
    <div class="scard <?= ($stFilter===0?'active':'') ?>" data-status="0">
      <div class="k">Total Records</div>
      <div class="v"><?= (int)$totalAll ?></div>
    </div>

    <?php foreach ($statuses as $sid => $s): 
      $cnt = (int)($statusCounts[$sid] ?? 0);
      ?>
      <div class="scard <?= ($stFilter===$sid?'active':'') ?>" data-status="<?= (int)$sid ?>">
        <div class="k"><?= h($s['name']) ?></div>
        <div class="v"><?= $cnt ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Filters -->
  <div id="filterPanel">
    <form method="get" class="filtersbar" id="filterForm">
      <input type="text" name="q" class="inp" placeholder="Search company/candidate/phone/email" value="<?= h($q) ?>" style="min-width:240px">

      <select name="ptype" class="inp">
        <option value="0">All Types</option>
        <option value="1" <?= $ptype===1?'selected':'' ?>>Employer</option>
        <option value="2" <?= $ptype===2?'selected':'' ?>>Jobseeker</option>
      </select>

      <select name="status" class="inp" id="statusSelect">
        <option value="0">All Status</option>
        <?php foreach ($statuses as $sid => $s): ?>
          <option value="<?= (int)$sid ?>" <?= ($stFilter===$sid?'selected':'') ?>><?= h($s['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="source" class="inp">
        <option value="0">All Sources</option>
        <?php foreach ($sources as $sid => $nm): ?>
          <option value="<?= (int)$sid ?>" <?= ($srcFilter===$sid?'selected':'') ?>><?= h($nm) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="assignee" class="inp">
        <option value="0">All Assignees</option>
        <?php foreach ($adminUsers as $uid => $nm): ?>
          <option value="<?= (int)$uid ?>" <?= ($assFilter===$uid?'selected':'') ?>><?= h($nm) ?></option>
        <?php endforeach; ?>
      </select>

      <button class="btn secondary" type="submit">Apply</button>

      <?php if (!$all && $total > $lim): ?>
        <a class="btn secondary" href="<?= h(keep_params(['all'=>1])) ?>">View All (<?= (int)$total ?>)</a>
      <?php endif; ?>
      <?php if ($all): ?>
        <a class="btn secondary" href="<?= h(keep_params(['all'=>null])) ?>">Last 50</a>
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
          <th>Type</th>
          <th>Company / Candidate</th>
          <th>Phone</th>
          <th>City</th>
          <th>Source</th>
          <th>Status</th>
          <th>On-boarded Plan</th>
          <th>Assigned</th>
          <th>Updated</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="10" style="color:#9ca3af">No records</td></tr>
        <?php endif; ?>

        <?php $sr=0; foreach ($rows as $r): $sr++;
          $pt = (int)($r['profile_type'] ?? 1);
          $typeLabel = ($pt===1) ? 'Employer' : 'Jobseeker';
          $name = ($pt===1) ? ($r['company_name'] ?: '—') : ($r['candidate_name'] ?: '—');
          $ass = (int)($r['assigned_to'] ?? 0);
          $assName = $ass>0 ? ($adminUsers[$ass] ?? ('#'.$ass)) : '—';
        ?>
          <tr>
            <td><?= (int)$sr ?></td>
            <td><?= h($typeLabel) ?></td>
            <td><?= h($name) ?></td>
            <td><?= h($r['phone1'] ?: '—') ?></td>
            <td><?= h($r['city_location'] ?: '—') ?></td>
            <td><?= h($r['source_name'] ?? '—') ?></td>
            <td><span class="badge on"><?= h($r['status_name'] ?? '—') ?></span></td>
            <td><?= h($r['onboarded_plan_name'] ?? '—') ?></td>
            <td><?= h($assName) ?></td>
            <td><?= h(fmt_dt($r['updated_at'] ?? null)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  // hide/show filter panel (remember)
  (function(){
    const panel = document.getElementById('filterPanel');
    const btn = document.getElementById('btnToggleFilters');
    const key = 'lead_filters_hidden';

    function setHidden(h){
      panel.classList.toggle('hide', !!h);
      btn.textContent = h ? 'Show Filters' : 'Hide Filters';
      try{ localStorage.setItem(key, h ? '1':'0'); }catch(e){}
    }

    let hidden = false;
    try{ hidden = (localStorage.getItem(key) === '1'); }catch(e){}
    setHidden(hidden);

    btn.addEventListener('click', function(){
      hidden = !hidden;
      setHidden(hidden);
    });
  })();

  // click status card => set status filter and submit
  (function(){
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
</script>

<?php
echo ob_get_clean();
