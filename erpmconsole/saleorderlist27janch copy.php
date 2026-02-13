<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__.'/../includes/initialize.php';
require_once __DIR__.'/../includes/aclhelper.php';

if (!function_exists('is_logged_in') || !is_logged_in()) {
    redirect('../login.php');
}
date_default_timezone_set('Asia/Kolkata');

/* =========================
   HELPERS
========================= */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* =========================
   FILTER INPUTS
========================= */
$from = $to = $company = '';
$view = 'last50';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['mode'] ?? '') === 'reset') {
        $from = $to = $company = '';
        $view = 'last50';
    } else {
        $from    = trim($_POST['from'] ?? '');
        $to      = trim($_POST['to'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $view    = ($_POST['view'] ?? 'last50') === 'all' ? 'all' : 'last50';
    }
}

/* =========================
   QUERY BUILD
========================= */
$where = [];
$params = [];
$types  = '';

if ($from) {
    $d = DateTime::createFromFormat('d-m-Y', $from);
    if ($d) {
        $where[] = "so.salesdate >= ?";
        $params[] = $d->format('Y-m-d');
        $types .= 's';
    }
}
if ($to) {
    $d = DateTime::createFromFormat('d-m-Y', $to);
    if ($d) {
        $where[] = "so.salesdate <= ?";
        $params[] = $d->format('Y-m-d');
        $types .= 's';
    }
}
if ($company) {
    $where[] = "so.customer LIKE ?";
    $params[] = "%$company%";
    $types .= 's';
}

$sql = "
SELECT so.id, so.salesdate, so.customer,
       p.name AS plant_name,
       s.sitename,
       u.name AS created_user,
       so.modifydate
FROM jos_erp_sale_order so
LEFT JOIN jos_erp_plantname p ON p.id = so.plantname
LEFT JOIN jos_crm_siteaddress_grid s ON s.id = so.sitename
LEFT JOIN jos_users u ON u.id = so.created_by
".($where ? " WHERE ".implode(' AND ',$where) : "")."
ORDER BY so.id DESC
";

if ($view === 'last50') {
    $sql .= " LIMIT 50";
}

$stmt = $con->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* =========================
   UI
========================= */
ob_start();
?>

<div class="master-wrap">

  <!-- HEADER -->
  <div class="headbar">
    <div>
      <h1 class="page-title">Sales Order List</h1>
      <div class="page-subtitle">Filter and manage sales orders</div>
    </div>

    <button type="button" class="btn secondary" id="toggleFiltersBtn">
      Hide Filters
    </button>
  </div>

  <!-- FILTERS -->
  <div class="card" id="filtersCard" style="margin-top:14px;">
    <form method="post" autocomplete="off">
      <input type="hidden" name="mode" value="filter">

      <div class="filter-grid">

        <div class="field">
          <label>From</label>
          <input type="text" name="from" class="inp datepick" value="<?=h($from)?>">
        </div>

        <div class="field">
          <label>To</label>
          <input type="text" name="to" class="inp datepick" value="<?=h($to)?>">
        </div>

        <div class="field">
          <label>Company</label>
          <input type="text" name="company" class="inp" value="<?=h($company)?>">
        </div>

        <div class="field">
          <label>View</label>
          <select name="view" class="inp">
            <option value="last50" <?=$view==='last50'?'selected':''?>>Last 50</option>
            <option value="all" <?=$view==='all'?'selected':''?>>View All</option>
          </select>
        </div>

        <div class="field actions">
          <button class="btn success">Search</button>
          <button class="btn secondary" name="mode" value="reset">Reset</button>
        </div>

      </div>
    </form>
  </div>

  <!-- LIST -->
  <div class="card" style="margin-top:14px;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <strong>Total: <?=$result->num_rows?></strong>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th style="width:60px">SR</th>
            <th style="width:120px">Date</th>
            <th>Customer</th>
            <th>Plant</th>
            <th>Site</th>
            <th>User</th>
            <th style="width:240px;text-align:center">Actions</th>
          </tr>
        </thead>
        <tbody>

        <?php if ($result->num_rows === 0): ?>
          <tr>
            <td colspan="7" class="muted" style="text-align:center;padding:14px;">
              No records found
            </td>
          </tr>
        <?php else: ?>
          <?php $i=1; while($r=$result->fetch_assoc()): ?>
          <tr>
            <td><?=$i++?></td>
            <td><?=date('d-m-Y',strtotime($r['salesdate']))?></td>
            <td><?=h($r['customer'])?></td>
            <td><?=h($r['plant_name'])?></td>
            <td><?=h($r['sitename'])?></td>
            <td>
              <strong><?=h($r['created_user'])?></strong><br>
              <small><?=date('d-m-Y',strtotime($r['modifydate']))?></small>
            </td>
            <td style="text-align:center">
              <div class="btn-group">

                <form method="post" action="../operations/saleorderform.php">
                  <input type="hidden" name="mode" value="edit">
                  <input type="hidden" name="sale_id" value="<?=$r['id']?>">
                  <button class="btn primary">Edit</button>
                </form>

                <form method="post" action="saleorder_print.php" target="_blank">
                  <input type="hidden" name="sale_id" value="<?=$r['id']?>">
                  <button class="btn secondary">Print</button>
                </form>

                <form method="post" onsubmit="return confirm('Delete this order?')">
                  <input type="hidden" name="mode" value="delete">
                  <input type="hidden" name="sale_id" value="<?=$r['id']?>">
                  <button class="btn danger">Delete</button>
                </form>

              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>

        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- DATE PICKER -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
flatpickr('.datepick',{dateFormat:'d-m-Y',allowInput:true,disableMobile:true});

const btn=document.getElementById('toggleFiltersBtn');
const card=document.getElementById('filtersCard');
btn.onclick=()=>card.style.display =
  card.style.display==='none'?'':'none';
</script>

<style>
.page-title{font-size:20px;font-weight:600}
.page-subtitle{font-size:13px;color:#6b7280}
.card{background:#fff;border-radius:16px;padding:14px;box-shadow:0 4px 12px rgba(0,0,0,.06)}
.filter-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;align-items:end}
.field label{font-size:13px;color:#374151}
.inp{height:38px;border-radius:10px;width:100%}
.actions{display:flex;gap:8px;justify-content:flex-end}
.table-wrap{overflow:auto;margin-top:10px;border-radius:14px}
.table{width:100%;border-collapse:collapse}
.table th{background:#2563eb;color:#fff;padding:10px;position:sticky;top:0}
.table td{padding:10px;border:1px solid #e5e7eb;vertical-align:top}
.btn{padding:6px 14px;border-radius:10px;border:none;cursor:pointer}
.success{background:#16a34a;color:#fff}
.primary{background:#2563eb;color:#fff}
.secondary{background:#e5e7eb}
.danger{background:#ef4444;color:#fff}
.btn-group{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
.muted{color:#6b7280}
</style>

<?php
$CONTENT = ob_get_clean();
require_once __DIR__.'/../includes/ui_autoshell.php';
