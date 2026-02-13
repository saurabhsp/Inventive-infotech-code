<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once __DIR__.'/../includes/initialize.php';
require_once __DIR__.'/../includes/aclhelper.php';

if (!is_logged_in()) redirect('../login.php');
date_default_timezone_set('Asia/Kolkata');

$con = $con ?? null;
if (!$con instanceof mysqli) die('DB missing');
if (session_status() === PHP_SESSION_NONE) session_start();

/* ==========================
   TABLE
========================== */
$TABLE = 'jos_ierp_stkrequest';

/* ==========================
   HELPERS
========================== */
function h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function dmy($d){
    if(!$d || $d==='0000-00-00') return '';
    return date('d-m-Y',strtotime($d));
}
function csrf(){
    if(empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(16));
    return $_SESSION['_csrf'];
}
function fy_from_date($date){
    if(!$date) return '';
    $y = (int)date('Y', strtotime($date));
    $m = (int)date('m', strtotime($date));

    // April se FY start hota hai
    if($m >= 4){
        return substr($y,2,2).'-'.substr($y+1,2,2);
    }else{
        return substr($y-1,2,2).'-'.substr($y,2,2);
    }
}


/* ==========================
   ACL
========================== */
$acl = erp_get_menu_meta_and_acl($con);
$canView  = $acl['can_view'] ?? false;
$canEdit  = $acl['can_edit'] ?? false;

// if(!$canView){
//     $CONTENT='<div class="alert danger">Access Denied</div>';
//     require '../includes/ui_autoshell.php';
//     exit;
// }

/* ==========================
   FILTER INPUTS (POST)
========================== */
$from=''; $to=''; $year=''; $billno='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $from   = trim($_POST['from'] ?? '');
    $to     = trim($_POST['to'] ?? '');
    $year   = trim($_POST['year'] ?? '');
    $billno = trim($_POST['billno'] ?? '');
}


/* ==========================
   QUERY BUILD
========================== */
$where=[]; $types=''; $params=[];

if($from){
    $where[]='date >= ?';
    $types.='s'; $params[] = date('Y-m-d',strtotime($from));
}
if($to){
    $where[]='date <= ?';
    $types.='s'; $params[] = date('Y-m-d',strtotime($to));
}
if($year){
    $where[]='yrid = ?';
    $types.='i'; $params[] = $year;
}
if($billno){
    $where[]='billno LIKE ?';
    $types.='s'; $params[] = "%$billno%";
}

$sql = "
SELECT
    s.id,
    s.billno,
    s.date,
    s.doc,
    s.modifydate,

    lf.location_name AS from_location,
    lt.location_name AS to_location,

    u.name AS created_by_name
FROM jos_ierp_stkrequest s

LEFT JOIN jos_erp_gidlocation lf ON lf.gid = s.fromlc
LEFT JOIN jos_erp_gidlocation lt ON lt.gid = s.tolc

LEFT JOIN jos_admin_users u ON u.id = s.created_by
";

if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY s.id DESC";



$rows=[];
$st=$con->prepare($sql);
if($types) $st->bind_param($types,...$params);
$st->execute();
$rs=$st->get_result();
while($r=$rs->fetch_assoc()) $rows[]=$r;
$st->close();

/* ==========================
   UI
========================== */
ob_start();
?>
<div class="master-wrap">

<div class="headbar">
  <div>
    <h1 class="page-title">Stock Transfer Order List</h1>
    <div class="page-subtitle">Internal Stock Movement</div>
  </div>
</div>

<!-- FILTER BAR -->
<div class="card" style="margin-top:12px;">
<form method="post">
<input type="hidden" name="_csrf" value="<?=csrf()?>">

<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;align-items:end">

<div class="field">
<label>From</label>
<input type="text" name="from" class="inp datepick" value="<?=h($from)?>">
</div>

<div class="field">
<label>To</label>
<input type="text" name="to" class="inp datepick" value="<?=h($to)?>">
</div>

<div class="field">
<label>Year</label>
<input type="text" name="year" class="inp" value="<?=h($year)?>">
</div>

<div class="field">
<label>Challan No.</label>
<input type="text" name="billno" class="inp" value="<?=h($billno)?>">
</div>

<div class="field" style="grid-column:5/7">
<label>&nbsp;</label>
<div style="display:flex;gap:8px;justify-content:flex-end">
<button class="btn success">Go</button>
<button class="btn secondary" type="submit" name="reset" value="1">Reset</button>
</div>
</div>

</div>
</form>
</div>

<!-- LIST -->
<div class="card" style="margin-top:12px;">
<div class="table-wrap">
<table class="table">
<thead>
<tr>
<th>Sr No</th>
<th>Challan No.</th>
<th>Date</th>
<th>Document</th>
<th>From</th>
<th>To</th>
<th>User/Modify By</th>
<th>Actions</th>
</tr>
</thead>
<tbody>

<?php if(!$rows): ?>
<tr><td colspan="8" class="muted" style="text-align:center">No records</td></tr>
<?php endif; ?>

<?php foreach($rows as $i=>$r): ?>
<tr>
<td><?=($i+1)?></td>
<td>
  <strong>
    <?= fy_from_date($r['date']) ?>/<?= h($r['billno']) ?>
  </strong>
</td>
<td><?=dmy($r['date'])?></td>
<td>Stock Transfer</td>
<td><?=h($r['from_location'] ?? '')?></td>
<td><?=h($r['to_location'] ?? '')?></td>
<td class="muted">
<?=h($r['created_by_name'])?>
<br>
<?php
$md = $r['modifydate'] ?? '';
echo $md ? date('d-m-Y', strtotime($md)) : '';
?>
</td>
<td>
<div style="display:flex;gap:6px;justify-content:center">
<?php if($canEdit): ?>
<form method="post" action="../operations/stock_transfer.php">
<input type="hidden" name="id" value="<?=$r['id']?>">
<button class="btn primary">Edit</button>
</form>
<?php endif; ?>

<form method="post" action="stock_transfer_print.php" target="_blank">
<input type="hidden" name="id" value="<?=$r['id']?>">
<button class="btn secondary">Print</button>
</form>
</div>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>
</div>

</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr('.datepick',{dateFormat:'d-m-Y',allowInput:true});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {

  const fromInput = document.querySelector('input[name="from"]');
  const toInput   = document.querySelector('input[name="to"]');

  if (!fromInput || !toInput || !window.flatpickr) return;

  // Initially disable To Date
  toInput.disabled = true;

  // From Date Picker
  const fromPicker = flatpickr(fromInput, {
    dateFormat: "d-m-Y",
    allowInput: true,
    disableMobile: true,

    onChange: function (selectedDates) {
      if (selectedDates.length) {
        // Enable To Date
        toInput.disabled = false;

        // Set min date for To Date
        toPicker.set('minDate', selectedDates[0]);

        // If To Date is before From Date → clear it
        if (toInput.value) {
          const toDate = toPicker.selectedDates[0];
          if (toDate && toDate < selectedDates[0]) {
            toPicker.clear();
          }
        }
      } else {
        // If From Date cleared → reset To Date
        toInput.disabled = true;
        toPicker.clear();
      }
    }
  });

  // To Date Picker
  const toPicker = flatpickr(toInput, {
    dateFormat: "d-m-Y",
    allowInput: true,
    disableMobile: true
  });

});
</script>


<?php
$CONTENT=ob_get_clean();
require '../includes/ui_autoshell.php';
