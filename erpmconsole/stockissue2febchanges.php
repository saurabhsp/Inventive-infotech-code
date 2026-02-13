<?php
/* ============================================================
 * operations/stock_issue.php  — Stock Issue (Core ERP, ui_autoshell)
 * Tables: jos_ierp_stkrequest + jos_ierp_stkrequest_grid
 *
 * Updates in THIS build (as per your last msgs + screenshots):
 * ✅ UI placement restored like your OLD screen (SS1): Date+FY+Bill row, From+To row, Items, Remark at bottom, Save/clear center
 * ✅ FY Code + Bill No readonly LOCKED look restored
 * ✅ PRG enabled (POST-Redirect-GET) to stop duplicate insert on refresh
 * ✅ Cleaned dead code paths + safer guards
 * ✅ IMPORTANT: DO NOT INSERT/UPDATE stkno & status anywhere (header + grid)
 * ✅ Prepared statements + transaction
 * ============================================================ */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/initialize.php';
require_once __DIR__ . '/../includes/aclhelper.php';
require_once __DIR__ . '/../includes/stock_helper.php';


if (!function_exists('is_logged_in') || !is_logged_in()) {
  redirect('../login.php');
}
date_default_timezone_set('Asia/Kolkata');

$con = $con ?? null;
if (!$con instanceof mysqli) { die('DB connection missing.'); }

/* ============================================================
 * TABLES (FROZEN)
 * ============================================================ */
$TABLE_HDR      = 'jos_ierp_stkrequest';
$TABLE_GRID     = 'jos_ierp_stkrequest_grid';
$TABLE_LOC      = 'jos_erp_gidlocation';     // gid, location_name
$TABLE_PRODUCTS = 'jos_crm_mproducts';
$TABLE_MUNIT    = 'jos_ierp_munit';
$TABLE_FY       = 'jos_ierp_mfinancialyear';

const DOC_TYPE   = 5;
const COMPANY_ID = 1;

/* ============================================================
 * Helpers
 * ============================================================ */
function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function clean_ident($s){ return preg_replace('/[^a-zA-Z0-9_]/', '', (string)$s); }

function table_exists(mysqli $con, string $table): bool {
  $table = clean_ident($table);
  if ($table==='') return false;
  $db = $con->query("SELECT DATABASE() AS db")->fetch_assoc();
  $dbn = $db['db'] ?? '';
  if ($dbn==='') return false;
  $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1";
  $st = $con->prepare($sql);
  if(!$st) return false;
  $st->bind_param("ss", $dbn, $table);
  $st->execute();
  $rs = $st->get_result();
  $ok = ($rs && $rs->num_rows > 0);
  $st->close();
  return $ok;
}
function col_exists(mysqli $con, string $table, string $col): bool {
  $table = clean_ident($table);
  $col   = clean_ident($col);
  if ($table==='' || $col==='') return false;
  $sql = "SHOW COLUMNS FROM `$table` LIKE '".$con->real_escape_string($col)."'";
  $rs  = $con->query($sql);
  return ($rs && $rs->num_rows > 0);
}

function parse_dmy_to_ymd($val){
  $val = trim((string)$val);
  if ($val === '') return '';
  $dt = DateTime::createFromFormat('d-m-Y', $val);
  if (!$dt) $dt = DateTime::createFromFormat('d/m/Y', $val);
  if (!$dt) $dt = DateTime::createFromFormat('Y-m-d', $val);
  return $dt ? $dt->format('Y-m-d') : '';
}
function ymd_to_dmy($val){
  $val = trim((string)$val);
  if ($val === '') return '';
  $dt = DateTime::createFromFormat('Y-m-d', $val);
  if (!$dt) $dt = DateTime::createFromFormat('d-m-Y', $val);
  return $dt ? $dt->format('d-m-Y') : '';
}

function flash_set($k,$v){ $_SESSION[$k]=$v; }
function flash_get($k){
  if(!empty($_SESSION[$k])){ $v=$_SESSION[$k]; unset($_SESSION[$k]); return $v; }
  return '';
}

function redirect_self(){
  header('Location: stock_issue.php');
  exit;
}


/* CSRF */
function csrf_token() {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['_csrf'];
}
function verify_csrf() {
    $t = $_POST['_csrf'] ?? '';
    return $t !== '' && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $t);
}


function current_admin_id(): int {
  if (!empty($_SESSION['admin_user']['id'])) return (int)$_SESSION['admin_user']['id'];
  if (!empty($_SESSION['admin_id'])) return (int)$_SESSION['admin_id'];
  return 0;
}
function get_admin_gid(mysqli $con, int $uid): int {
  if ($uid <= 0) return 0;
  if (!table_exists($con,'jos_admin_users')) return 0;
  if (!col_exists($con,'jos_admin_users','gid')) return 0;
  $sql = "SELECT gid FROM jos_admin_users WHERE id=? LIMIT 1";
  $st = $con->prepare($sql);
  if(!$st) return 0;
  $st->bind_param("i",$uid);
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  $st->close();
  return (int)($r['gid'] ?? 0);
}
function get_location_name_by_gid(mysqli $con, string $TABLE_LOC, int $gid): string {
  $TABLE_LOC = clean_ident($TABLE_LOC);
  if ($gid<=0 || $TABLE_LOC==='' || !table_exists($con,$TABLE_LOC)) return '';
  if (!col_exists($con,$TABLE_LOC,'gid')) return '';
  $nameCol = col_exists($con,$TABLE_LOC,'location_name') ? 'location_name' : (col_exists($con,$TABLE_LOC,'name') ? 'name' : '');
  if ($nameCol==='') return '';
  $sql = "SELECT `$nameCol` AS nm FROM `$TABLE_LOC` WHERE gid=? LIMIT 1";
  $st = $con->prepare($sql);
  if(!$st) return '';
  $st->bind_param("i",$gid);
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  $st->close();
  return (string)($r['nm'] ?? '');
}

function unit_map(mysqli $con, string $TABLE_MUNIT): array {
  $map = [];
  $TABLE_MUNIT = clean_ident($TABLE_MUNIT);
  if ($TABLE_MUNIT==='' || !table_exists($con,$TABLE_MUNIT)) return $map;

  $idCol = col_exists($con,$TABLE_MUNIT,'id') ? 'id'
        : (col_exists($con,$TABLE_MUNIT,'unit_id') ? 'unit_id'
        : (col_exists($con,$TABLE_MUNIT,'uid') ? 'uid' : ''));

  $nmCol = col_exists($con,$TABLE_MUNIT,'unit') ? 'unit'
        : (col_exists($con,$TABLE_MUNIT,'name') ? 'name'
        : (col_exists($con,$TABLE_MUNIT,'unit_name') ? 'unit_name'
        : (col_exists($con,$TABLE_MUNIT,'title') ? 'title' : '')));

  if ($idCol==='' || $nmCol==='') return $map;

  $rs  = $con->query("SELECT `$idCol` AS id, `$nmCol` AS nm FROM `$TABLE_MUNIT`");
  if ($rs) {
    while($r=$rs->fetch_assoc()){
      $map[(string)$r['id']] = trim((string)($r['nm'] ?? ''));
    }
    $rs->free();
  }
  return $map;
}

/* FY by date: year starts April; table has id, code, year */
function fy_from_date(mysqli $con, string $TABLE_FY, string $ymd): array {
  $out = ['yrid'=>0,'fy_code'=>''];
  $TABLE_FY = clean_ident($TABLE_FY);
  if ($ymd==='' || $TABLE_FY==='' || !table_exists($con,$TABLE_FY)) return $out;
  if (!col_exists($con,$TABLE_FY,'id') || !col_exists($con,$TABLE_FY,'year') || !col_exists($con,$TABLE_FY,'code')) return $out;

  $dt = DateTime::createFromFormat('Y-m-d', $ymd);
  if(!$dt) return $out;
  $Y = (int)$dt->format('Y');
  $M = (int)$dt->format('n');
  $fyYear = ($M >= 4) ? $Y : ($Y - 1);

  $sql = "SELECT id, code FROM `$TABLE_FY` WHERE `year`=? LIMIT 1";
  $st = $con->prepare($sql);
  if(!$st) return $out;
  $st->bind_param("i", $fyYear);
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  $st->close();
  if($r){
    $out['yrid'] = (int)($r['id'] ?? 0);
    $out['fy_code'] = (string)($r['code'] ?? '');
  }
  return $out;
}

/* billno: MAX(billno) where doc=5 + 1 */
function next_billno(mysqli $con, string $TABLE_HDR, int $docType): int {
  $TABLE_HDR = clean_ident($TABLE_HDR);
  if ($TABLE_HDR==='' || !table_exists($con,$TABLE_HDR)) return 1;
  if (!col_exists($con,$TABLE_HDR,'billno')) return 1;
  $docWhere = (col_exists($con,$TABLE_HDR,'doc')) ? " WHERE doc=? " : "";
  $sql = "SELECT COALESCE(MAX(billno),0) AS mx FROM `$TABLE_HDR` ".$docWhere;
  $st = $con->prepare($sql);
  if(!$st) return 1;
  if($docWhere!==''){ $st->bind_param("i",$docType); }
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  $st->close();
  return ((int)($r['mx'] ?? 0)) + 1;
}

/* ------------------------------------------------------------
 * Column-safe add helpers
 * ----------------------------------------------------------*/
function add_ins(mysqli $con, string $table, array &$cols, array &$types, array &$vals, string $col, string $type, $val){
  if (col_exists($con,$table,$col)) { $cols[]=$col; $types[]=$type; $vals[]=$val; }
}
function add_set(mysqli $con, string $table, array &$sets, array &$types, array &$vals, string $col, string $type, $val){
  if (col_exists($con,$table,$col)) { $sets[]="`$col`=?"; $types[]=$type; $vals[]=$val; }
}

/* ============================================================
 * AJAX
 * ============================================================ */
function json_out($arr){
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}


if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ajax'])) {
  $ajax = (string)$_POST['ajax'];

  if ($ajax === 'fy_bill') {
    $dmy = trim((string)($_POST['issue_date'] ?? ''));
    $ymd = parse_dmy_to_ymd($dmy);
    if($ymd==='') json_out(['ok'=>false,'msg'=>'Invalid date']);
    $fy = fy_from_date($con, $TABLE_FY, $ymd);
    $bill = next_billno($con, $TABLE_HDR, DOC_TYPE);
    json_out(['ok'=>true,'ymd'=>$ymd,'yrid'=>$fy['yrid'],'fy_code'=>$fy['fy_code'],'billno'=>$bill]);
  }

  if ($ajax === 'product_search') {
    $q = trim((string)($_POST['q'] ?? ''));
    if ($q === '') json_out(['ok'=>true,'items'=>[]]);
    $like = "%".$q."%";

    $nameCol = col_exists($con,$TABLE_PRODUCTS,'name') ? 'name' : (col_exists($con,$TABLE_PRODUCTS,'product_name') ? 'product_name' : '');
    $codeCol = col_exists($con,$TABLE_PRODUCTS,'product_code') ? 'product_code' : '';
    $idCol   = col_exists($con,$TABLE_PRODUCTS,'id') ? 'id' : (col_exists($con,$TABLE_PRODUCTS,'product_id') ? 'product_id' : '');
    if ($nameCol==='' || $idCol==='') json_out(['ok'=>false,'msg'=>'Product columns not found']);

    $where = "`$nameCol` LIKE ?";
    $types = "s";
    $b1 = $like; $b2 = null;
    if ($codeCol!=='') { $where = "(`$nameCol` LIKE ? OR `$codeCol` LIKE ?)"; $types="ss"; $b2=$like; }

    $sql = "SELECT `$idCol` AS id, `$nameCol` AS name"
         . ($codeCol!=='' ? ", `$codeCol` AS code" : ", '' AS code")
         . " FROM `$TABLE_PRODUCTS` WHERE $where ORDER BY `$nameCol` LIMIT 20";
    $st = $con->prepare($sql);
    if(!$st) json_out(['ok'=>false,'msg'=>'Prepare failed']);
    if ($types==='ss') $st->bind_param($types,$b1,$b2); else $st->bind_param($types,$b1);
    $st->execute();
    $rs = $st->get_result();

    $items = [];
    while($r=$rs->fetch_assoc()){
      $label = trim(($r['name'] ?? '').($r['code'] ? " (".$r['code'].")" : ''));
      $items[] = ['id'=>$r['id'], 'label'=>$label, 'name'=>$r['name'], 'code'=>$r['code']];
    }
    $st->close();
    json_out(['ok'=>true,'items'=>$items]);
  }

  if ($ajax === 'product_details') {
    $pid = (int)($_POST['product_id'] ?? 0);
    if ($pid<=0) json_out(['ok'=>false,'msg'=>'Invalid product']);

    $idCol = col_exists($con,$TABLE_PRODUCTS,'id') ? 'id' : (col_exists($con,$TABLE_PRODUCTS,'product_id') ? 'product_id' : '');
    if ($idCol==='') json_out(['ok'=>false,'msg'=>'Product id column not found']);

    $cols = [
      'unit','secondaryunit','unitconversion','thirdunit','secondconversion',
      'sec_width','sec_height','third_width','third_height'
    ];

    $select = "`$idCol` AS id";
    foreach($cols as $c){
      $select .= col_exists($con,$TABLE_PRODUCTS,$c) ? ", `$c` AS `$c`" : ", '' AS `$c`";
    }

    $sql = "SELECT $select FROM `$TABLE_PRODUCTS` WHERE `$idCol`=? LIMIT 1";
    $st  = $con->prepare($sql);
    if(!$st) json_out(['ok'=>false,'msg'=>'Prepare failed']);
    $st->bind_param("i",$pid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if(!$row) json_out(['ok'=>false,'msg'=>'Not found']);

    $uMap = unit_map($con, $TABLE_MUNIT);
    $row['_unit_name']      = $uMap[(string)($row['unit'] ?? '')] ?? '';
    $row['_secondary_name'] = $uMap[(string)($row['secondaryunit'] ?? '')] ?? '';
    $row['_third_name']     = $uMap[(string)($row['thirdunit'] ?? '')] ?? '';

    json_out(['ok'=>true,'row'=>$row]);
  }

  if ($ajax === 'get_conversion') {
    $pid = isset($_POST['pid']) ? (int)$_POST['pid'] : 0;
    $qty = isset($_POST['qty']) ? (float)$_POST['qty'] : 0;
    if ($pid <= 0) json_out(['ok'=>false,'error'=>'Invalid pid']);
    if ($qty < 0) $qty = 0;

    $idCol = col_exists($con,$TABLE_PRODUCTS,'id') ? 'id' : (col_exists($con,$TABLE_PRODUCTS,'product_id') ? 'product_id' : '');
    if ($idCol==='') json_out(['ok'=>false,'error'=>'Product id column not found']);

    $needCols = ['secondaryunit','thirdunit','unitconversion','secondconversion','sec_width','sec_height','third_width','third_height'];
    $sel = "`$idCol` AS id";
    foreach($needCols as $c){
      $sel .= col_exists($con,$TABLE_PRODUCTS,$c) ? ", `$c` AS `$c`" : ", 0 AS `$c`";
    }

    $sql = "SELECT $sel FROM `$TABLE_PRODUCTS` WHERE `$idCol`=? LIMIT 1";
    $st = $con->prepare($sql);
    if(!$st) json_out(['ok'=>false,'error'=>'Prepare failed: products']);
    $st->bind_param("i",$pid);
    $st->execute();
    $p  = $st->get_result()->fetch_assoc();
    $st->close();
    if(!$p) json_out(['ok'=>false,'error'=>'Product not found','code'=>200]);

    $secondaryunit   = (int)($p['secondaryunit'] ?? 0);
    $thirdunit       = (int)($p['thirdunit'] ?? 0);
    $unitconversionV = (float)($p['unitconversion'] ?? 0);
    $secondconvV     = (float)($p['secondconversion'] ?? 0);

    $sec_width    = (float)($p['sec_width'] ?? 0);
    $sec_height   = (float)($p['sec_height'] ?? 0);
    $third_width  = (float)($p['third_width'] ?? 0);
    $third_height = (float)($p['third_height'] ?? 0);

    $uMap = unit_map($con, $TABLE_MUNIT);
    $sec_unit_label   = $uMap[(string)$secondaryunit] ?? '';
    $third_unit_label = $uMap[(string)$thirdunit] ?? '';

    $unitconversion   = ($unitconversionV > 0) ? ($qty * $unitconversionV) : '';
    $secondconversion = ($secondconvV > 0) ? ($qty * $secondconvV) : '';

    $sec_measurement = ($sec_width > 0 && $sec_height > 0 && $qty > 0)
      ? (($sec_width * $qty) . ' x ' . ($sec_height * $qty))
      : '';

    $third_measurement = ($third_width > 0 && $third_height > 0 && $qty > 0)
      ? (($third_width * $qty) . ' x ' . ($third_height * $qty))
      : '';

    json_out([
      'ok' => true,
      'pid' => $pid,
      'qty' => $qty,
      'unitconversion' => $unitconversion,
      'secondconversion' => $secondconversion,
      'sec_unit_label' => $sec_unit_label,
      'third_unit_label' => $third_unit_label,
      'sec_measurement' => $sec_measurement,
      'third_measurement' => $third_measurement,
      'sec_base_width' => $sec_width,
      'sec_base_height' => $sec_height,
      'third_base_width' => $third_width,
      'third_base_height' => $third_height,
    ]);
  }

 if ($ajax === 'get_stock') {

  $pid  = (int)($_POST['pid'] ?? 0);
  $gid  = (int)($_POST['from_gid'] ?? 0);
  $yrid = (int)($_POST['yrid'] ?? 0);

  if ($pid<=0 || $gid<=0 || $yrid<=0) {
    json_out(['ok'=>false,'stock'=>0]);
  }

  $stock = get_actual_stock(
      $con,
      $pid,
      $gid,
      $yrid
  );

  json_out([
    'ok'    => true,
    'stock' => $stock
  ]);
}



  json_out(['ok'=>false,'msg'=>'Unknown ajax']);
}

/* ============================================================
 * Load Locations
 * ============================================================ */
$locations = [];
$locOk = table_exists($con,$TABLE_LOC) && col_exists($con,$TABLE_LOC,'gid');
$locNameCol = ($locOk && col_exists($con,$TABLE_LOC,'location_name')) ? 'location_name'
            : (($locOk && col_exists($con,$TABLE_LOC,'name')) ? 'name' : '');

if ($locOk && $locNameCol!=='') {
  $rs  = $con->query("SELECT gid, `$locNameCol` AS nm FROM `$TABLE_LOC` ORDER BY `$locNameCol`");
  if ($rs) {
    while($r=$rs->fetch_assoc()){
      $locations[] = ['gid'=>(int)$r['gid'], 'nm'=>(string)$r['nm']];
    }
    $rs->free();
  }
}

$prodIdCol = col_exists($con,$TABLE_PRODUCTS,'id') ? 'id'
           : (col_exists($con,$TABLE_PRODUCTS,'product_id') ? 'product_id' : '');

$prodNameCol = col_exists($con,$TABLE_PRODUCTS,'name') ? 'name'
             : (col_exists($con,$TABLE_PRODUCTS,'product_name') ? 'product_name' : '');

/* ============================================================
 * Defaults + Prefill for Edit
 * ============================================================ */
$adminId      = current_admin_id();
$adminGid     = get_admin_gid($con, $adminId);
$adminLocName = get_location_name_by_gid($con, $TABLE_LOC, $adminGid);

$mode    = $_POST['mode'] ?? 'form';
$edit_id = (int)($_POST['edit_id'] ?? 0);

$hdr = [
  'issue_date' => date('d-m-Y'),
  'from_gid'   => (string)$adminGid,
  'from_name'  => (string)$adminLocName,
  'to_gid'     => '',
  'remark'     => '',
  'fy_code'    => '',
  'billno'     => '',
  'yrid'       => 0,
];

$rows = [];

/* -------- Load for edit (POST only) -------- */
if ($mode === 'edit_load') {
  verify_csrf();
  $edit_id = (int)($_POST['edit_id'] ?? 0);
  if ($edit_id <= 0) {
    flash_set('err','Invalid edit id');
    $mode = 'form';
  } else {
    $sql = "SELECT * FROM `$TABLE_HDR` WHERE id=? LIMIT 1";
    $st = $con->prepare($sql);
    $st->bind_param("i",$edit_id);
    $st->execute();
    $H = $st->get_result()->fetch_assoc();
    $st->close();

    if(!$H){
      flash_set('err','Record not found');
      $mode='form';
    } else {
      $ymd = (string)($H['date'] ?? $H['issue_date'] ?? '');
      $hdr['issue_date'] = ymd_to_dmy($ymd);
      $hdr['from_gid']   = (string)($H['fromlc'] ?? $H['from_gid'] ?? $adminGid);
      $hdr['from_name']  = get_location_name_by_gid($con,$TABLE_LOC,(int)$hdr['from_gid']);
      $hdr['to_gid']     = (string)($H['tolc'] ?? $H['to_gid'] ?? '');
      $hdr['remark']     = (string)($H['remark'] ?? '');
      $hdr['billno']     = (string)($H['billno'] ?? '');
      $hdr['yrid']       = (int)($H['yrid'] ?? 0);

      if ($hdr['yrid'] > 0 && table_exists($con,$TABLE_FY) && col_exists($con,$TABLE_FY,'id')) {
        $st = $con->prepare("SELECT code FROM `$TABLE_FY` WHERE id=? LIMIT 1");
        $st->bind_param("i",$hdr['yrid']);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        $hdr['fy_code'] = (string)($r['code'] ?? '');
      }

      $rows = [];
    //   $sql = "SELECT * FROM `$TABLE_GRID` WHERE billid=? ORDER BY id ASC";
            $sql = "
          SELECT g.*, p.`$prodNameCol` AS product_name
          FROM `$TABLE_GRID` g
          LEFT JOIN `$TABLE_PRODUCTS` p ON p.`$prodIdCol` = g.propid
          WHERE g.billid=?
          ORDER BY g.id ASC
        ";

      $st = $con->prepare($sql);
      $st->bind_param("i",$edit_id);
      $st->execute();
      $rs = $st->get_result();
      while($r=$rs->fetch_assoc()){
        $rows[] = [
          'product_id' => (int)($r['propid'] ?? 0),
          'product_name' => (string)($r['product_name'] ?? ''),
          'qty' => (float)($r['qty'] ?? 0),
          'stock' => (string)($r['stock'] ?? ''),
          // 'uom' => (string)($r['uom'] ?? ''),
          'uom_id'   => (int)($r['uom'] ?? 0),
          'uom_name' => unit_map($con,$TABLE_MUNIT)[(string)($r['uom'] ?? '')] ?? '',
          'sec_width' => (string)($r['sec_width'] ?? ''),
          'sec_height'=> (string)($r['sec_height'] ?? ''),
          'third_width' => (string)($r['third_width'] ?? ''),
          'third_height'=> (string)($r['third_height'] ?? ''),
          'unitconversion' => (string)($r['sec_qty'] ?? ''),
          'secondconversion'=> (string)($r['thirdqty'] ?? ''),
          'description' => (string)($r['description'] ?? ''),
          'secondaryunit' => '',
          'thirdunit' => '',
        ];
      }
      $st->close();
      $mode='form';
    }
  }
}

/* ============================================================
 * DELETE
 * ============================================================ */
if ($mode === 'delete') {
  verify_csrf();
  $did = (int)($_POST['edit_id'] ?? 0);
  if ($did<=0){ flash_set('err','Invalid delete id'); $mode='form'; }
  else{
    $con->begin_transaction();
    try{
      $st = $con->prepare("DELETE FROM `$TABLE_GRID` WHERE billid=?");
      $st->bind_param("i",$did);
      $st->execute();
      $st->close();

      $st = $con->prepare("DELETE FROM `$TABLE_HDR` WHERE id=?");
      $st->bind_param("i",$did);
      $st->execute();
      $st->close();

      $con->commit();
      flash_set('ok','Deleted successfully.');
      redirect_self(); // PRG
    }catch(Throwable $e){
      $con->rollback();
      flash_set('err','Delete failed: '.$e->getMessage());
      $mode='form';
    }
  }
}

/* ============================================================
 * SAVE  (NO stkno/status insert/update)
 * ============================================================ */
if ($mode === 'save') {
  verify_csrf();

  $issue_date_dmy = trim((string)($_POST['issue_date'] ?? ''));
  $issue_date_ymd = parse_dmy_to_ymd($issue_date_dmy);

  $from_gid = (int)($_POST['from_gid'] ?? 0);
  if ($from_gid <= 0) $from_gid = (int)$adminGid;

  $to_gid   = (int)($_POST['to_gid'] ?? 0);
  $remark   = trim((string)($_POST['remark'] ?? ''));

  $rows_json = (string)($_POST['rows_json'] ?? '[]');
  $grid = json_decode($rows_json, true);
  if (!is_array($grid)) $grid = [];

  $errors = [];
  if ($issue_date_ymd==='') $errors[]='Date is required.';
  if ($from_gid<=0) $errors[]='From location missing.';
  if ($to_gid<=0) $errors[]='To location required.';
  if (count($grid)===0) $errors[]='Add at least 1 item.';

  $fy = fy_from_date($con, $TABLE_FY, $issue_date_ymd);
  $yrid = (int)($fy['yrid'] ?? 0);
  $fy_code = (string)($fy['fy_code'] ?? '');
  if ($yrid<=0) $errors[]='Financial year not found for this date.';

  if ($errors){
    flash_set('err', implode(' ', $errors));
    $hdr['issue_date']=$issue_date_dmy;
    $hdr['to_gid']=(string)$to_gid;
    $hdr['remark']=$remark;
    $hdr['yrid']=$yrid;
    $hdr['fy_code']=$fy_code;
    $rows=$grid;
    $mode='form';
  } else {

    $editing = (int)($_POST['edit_id'] ?? 0);
    $billno = 0;

    if ($editing > 0) {
      $st = $con->prepare("SELECT billno FROM `$TABLE_HDR` WHERE id=? LIMIT 1");
      $st->bind_param("i",$editing);
      $st->execute();
      $r = $st->get_result()->fetch_assoc();
      $st->close();
      $billno = (int)($r['billno'] ?? 0);
      if ($billno<=0 && col_exists($con,$TABLE_HDR,'billno')) $billno = next_billno($con,$TABLE_HDR,DOC_TYPE);
    } else {
      $billno = next_billno($con,$TABLE_HDR,DOC_TYPE);
    }

    $con->begin_transaction();
    try{
      $now = date('Y-m-d H:i:s');
      $uid = current_admin_id();

      if ($editing > 0) {
        $sets=[]; $types=[]; $vals=[];

        add_set($con,$TABLE_HDR,$sets,$types,$vals,'date','s',$issue_date_ymd);
        add_set($con,$TABLE_HDR,$sets,$types,$vals,'yrid','i',$yrid);
        add_set($con,$TABLE_HDR,$sets,$types,$vals,'tolc','i',$to_gid);
        add_set($con,$TABLE_HDR,$sets,$types,$vals,'fromlc','i',$from_gid);
        add_set($con,$TABLE_HDR,$sets,$types,$vals,'gid','i',$from_gid);
        add_set($con,$TABLE_HDR,$sets,$types,$vals,'doc','i',DOC_TYPE);
        add_set($con,$TABLE_HDR,$sets,$types,$vals,'remark','s',$remark);

        add_set($con,$TABLE_HDR,$sets,$types,$vals,'modifyby','i',$uid);
        add_set($con,$TABLE_HDR,$sets,$types,$vals,'modifydate','s',$now);

        add_set($con,$TABLE_HDR,$sets,$types,$vals,'company','i',COMPANY_ID);

        // keep billno (do not change except if missing)
        if ($billno>0) add_set($con,$TABLE_HDR,$sets,$types,$vals,'billno','i',$billno);

        if ($sets){
          $sql = "UPDATE `$TABLE_HDR` SET ".implode(',',$sets)." WHERE id=?";
          $st = $con->prepare($sql);
          if(!$st) throw new Exception('Header update prepare failed.');
          $typesStr = implode('',$types).'i';
          $vals[] = $editing;
          $st->bind_param($typesStr, ...$vals);
          $st->execute();
          $st->close();
        }

        $st = $con->prepare("DELETE FROM `$TABLE_GRID` WHERE billid=?");
        $st->bind_param("i",$editing);
        $st->execute();
        $st->close();

        $new_id = $editing;

      } else {
        $cols=[]; $types=[]; $vals=[];

        add_ins($con,$TABLE_HDR,$cols,$types,$vals,'billno','i',$billno);
        add_ins($con,$TABLE_HDR,$cols,$types,$vals,'created_by','i',$uid);
        add_ins($con,$TABLE_HDR,$cols,$types,$vals,'gid','i',$from_gid);
        add_ins($con,$TABLE_HDR,$cols,$types,$vals,'sysdate','s',$now);
        add_ins($con,$TABLE_HDR,$cols,$types,$vals,'date','s',$issue_date_ymd);
        add_ins($con,$TABLE_HDR,$cols,$types,$vals,'yrid','i',$yrid);
        add_ins($con,$TABLE_HDR,$cols,$types,$vals,'tolc','i',$to_gid);
        add_ins($con,$TABLE_HDR,$cols,$types,$vals,'fromlc','i',$from_gid);
        add_ins($con,$TABLE_HDR,$cols,$types,$vals,'doc','i',DOC_TYPE);
        add_ins($con,$TABLE_HDR,$cols,$types,$vals,'remark','s',$remark);

        add_ins($con,$TABLE_HDR,$cols,$types,$vals,'company','i',COMPANY_ID);

        // NOTE: intentionally NOT inserting stkno & status (as per you)

        if (!$cols) throw new Exception('No header columns available to insert.');
        $sql = "INSERT INTO `$TABLE_HDR` (`".implode('`,`',$cols)."`) VALUES (".implode(',', array_fill(0,count($cols),'?')).")";
        $st = $con->prepare($sql);
        if(!$st) throw new Exception('Header insert prepare failed.');
        $typesStr = implode('',$types);
        $st->bind_param($typesStr, ...$vals);
        $st->execute();
        $new_id = (int)$st->insert_id;
        $st->close();
      }

      /* Grid insert (NO stkno/status insert) */
      $gCols=[]; $gTypes=[]; $gValsTemplate=[];

      $possible = [
        ['billid','i',null],
        ['machine','i',0],
        ['date','s',null],
        ['propid','i',null],
        ['serialno','s',''],
        ['hsncode','s',''],
        ['qty','d',null],
        ['sec_qty','d',null],
        ['thirdqty','d',null],
        ['sec_width','d',null],
        ['sec_height','d',null],
        ['third_width','d',null],
        ['third_height','d',null],
        ['rate','d',0],
        ['total','d',0],
        ['batch','i',0],
        ['description','s',''],
        ['doc','i',DOC_TYPE],
        ['company','i',COMPANY_ID],
        ['yrid','i',$yrid],
        ['userid','i',$uid],
        ['fromlc','i',$from_gid],
        ['tolc','i',$to_gid],
        ['uom','i',0],
        ['gid','i',$from_gid],
        ['stock','i',0],
        ['remark','s',''],
        // NOTE: intentionally NOT inserting stkno & status
      ];

      foreach($possible as $p){
        [$col,$t,$def] = $p;
        if(col_exists($con,$TABLE_GRID,$col)){
          $gCols[] = $col;
          $gTypes[] = $t;
          $gValsTemplate[] = $def;
        }
      }
      if(!$gCols) throw new Exception('No grid columns available to insert.');

      $gSql = "INSERT INTO `$TABLE_GRID` (`".implode('`,`',$gCols)."`) VALUES (".implode(',', array_fill(0,count($gCols),'?')).")";
      $gSt = $con->prepare($gSql);
      if(!$gSt) throw new Exception('Grid insert prepare failed.');
      $gTypeStr = implode('',$gTypes);

      foreach($grid as $r){
        $qty = (float)($r['qty'] ?? 0);
        $pid = (int)($r['product_id'] ?? 0);
        if($pid<=0 || $qty<=0) continue;

        $sec_qty = (float)($r['unitconversion'] ?? 0);
        $thirdqty= (float)($r['secondconversion'] ?? 0);

        $sec_base_w = (float)($r['sec_width'] ?? 0);
        $sec_base_h = (float)($r['sec_height'] ?? 0);
        $third_base_w = (float)($r['third_width'] ?? 0);
        $third_base_h = (float)($r['third_height'] ?? 0);

        $sec_w = ($sec_base_w>0 && $qty>0) ? ($sec_base_w * $qty) : 0;
        $sec_h = ($sec_base_h>0 && $qty>0) ? ($sec_base_h * $qty) : 0;
        $third_w = ($third_base_w>0 && $qty>0) ? ($third_base_w * $qty) : 0;
        $third_h = ($third_base_h>0 && $qty>0) ? ($third_base_h * $qty) : 0;

        $desc = trim((string)($r['description'] ?? ''));

        $vals = $gValsTemplate;
        foreach($gCols as $i=>$col){
          switch($col){
            case 'billid': $vals[$i] = $new_id; break;
            case 'date':   $vals[$i] = $issue_date_ymd; break;
            case 'propid': $vals[$i] = $pid; break;
            case 'qty':    $vals[$i] = $qty; break;
            case 'uom':  $vals[$i] = (int)($r['uom_id'] ?? 0); break;
            case 'sec_qty': $vals[$i] = $sec_qty; break;
            case 'thirdqty': $vals[$i] = $thirdqty; break;
            case 'sec_width': $vals[$i] = $sec_w; break;
            case 'sec_height': $vals[$i] = $sec_h; break;
            case 'third_width': $vals[$i] = $third_w; break;
            case 'third_height': $vals[$i] = $third_h; break;
            case 'description': $vals[$i] = $desc; break;
            case 'yrid': $vals[$i] = $yrid; break;
            case 'fromlc': $vals[$i] = $from_gid; break;
            case 'tolc': $vals[$i] = $to_gid; break;
            case 'gid': $vals[$i] = $from_gid; break;
            case 'company': $vals[$i] = COMPANY_ID; break;
            case 'doc': $vals[$i] = DOC_TYPE; break;
            case 'userid': $vals[$i] = $uid; break;
          }
        }

        $gSt->bind_param($gTypeStr, ...$vals);
        $gSt->execute();
      }
      $gSt->close();

      $con->commit();
      flash_set('ok','Saved successfully.');
      redirect_self(); // PRG (prevents duplicate insert on refresh)

    } catch(Throwable $e){
      $con->rollback();
      flash_set('err','Save failed: '.$e->getMessage());
      $hdr['issue_date']=$issue_date_dmy;
      $hdr['to_gid']=(string)$to_gid;
      $hdr['remark']=$remark;
      $hdr['billno']=(string)$billno;
      $hdr['fy_code']=$fy_code;
      $hdr['yrid']=$yrid;
      $rows=$grid;
      $edit_id=$editing;
      $mode='form';
    }
  }
}

/* ============================================================
 * UI
 * ============================================================ */
$pageTitle = 'Stock Issue';
ob_start();
?>
<style>
/* restore locked readonly look + focus highlight */
.inp.locked{
  background: #f3f4f6 !important;
  color: #111827 !important;
  cursor: not-allowed !important;
  border-style: dashed !important;
  opacity: .95 !important;
}
.table td.focus-td{ outline: 2px solid rgba(59,130,246,.35); outline-offset: -2px; background: rgba(59,130,246,.06); }

/* ===== COMMON SUGGEST DROPDOWN (ERP STANDARD) ===== */
.suggest-box{
  position:absolute;
  left:0;
  right:0;
  top:100%;
  margin-top:4px;
  background:#fff;
  border:1px solid #e5e7eb;
  border-radius:10px;
  box-shadow:0 10px 30px rgba(0,0,0,.12);
  max-height:240px;
  overflow:auto;
  z-index:99999;
}

.suggest-item{
  padding:10px 12px;
  cursor:pointer;
  border-bottom:1px solid #f1f5f9;
}

.suggest-item:last-child{ border-bottom:none; }

.suggest-item.active{
  background:#2563eb;
  color:#fff;
}


</style>

<div class="card" style="margin-bottom:14px;">
  <!-- page title like your Complaint Order -->
  <div style="font-size:34px; font-weight:800; margin-bottom:12px;">Stock Issue</div>

  <?php if ($m = flash_get('ok')): ?>
    <div class="alert success" style="margin-bottom:10px;"><?= h($m) ?></div>
  <?php endif; ?>
  <?php if ($m = flash_get('err')): ?>
    <div class="alert danger" style="margin-bottom:10px;"><?= h($m) ?></div>
  <?php endif; ?>

  <form method="post" autocomplete="off" id="mainForm">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="mode" id="mode" value="save">
    <input type="hidden" name="edit_id" id="edit_id" value="<?= (int)$edit_id ?>">
    <input type="hidden" name="rows_json" id="rows_json" value="<?= h(json_encode($rows)) ?>">
    <input type="hidden" name="from_gid" id="from_gid" value="<?= h($hdr['from_gid']) ?>">

    <!-- ROW 1 (SS1): Date + FY + Bill -->
    <div style="display:grid; grid-template-columns: 220px 180px 180px 1fr; gap:12px; align-items:end;">
      <div>
        <label>Date <span style="color:#ef4444;">*</span></label>
        <input type="text" name="issue_date" id="issue_date" class="inp" value="<?= h($hdr['issue_date']) ?>">
      </div>

      <div>
        <label>FY Code</label>
        <input type="text" id="fy_code" class="inp locked" value="<?= h($hdr['fy_code']) ?>" readonly>
        <input type="hidden" id="yrid" value="<?= (int)$hdr['yrid'] ?>">
      </div>

      <div>
        <label>Bill No</label>
        <input type="text" id="billno" class="inp locked" value="<?= h($hdr['billno']) ?>" readonly>
      </div>

      <div></div>
    </div>

    <!-- ROW 2 (SS1): From + To same line -->
    <div style="margin-top:10px; display:grid; grid-template-columns: 1fr 1fr; gap:12px; align-items:end;">
      <div>
        <label>From Location <span style="color:#ef4444;">*</span></label>
        <input type="text" class="inp locked" value="<?= h($hdr['from_name']) ?>" readonly>
      </div>
      <div>
        <label>To Location <span style="color:#ef4444;">*</span></label>
        <select name="to_gid" class="inp" required>
          <option value="">-- Select --</option>
          <?php foreach($locations as $L): ?>
            <option value="<?= (int)$L['gid'] ?>" <?= ((string)$hdr['to_gid']===(string)$L['gid'])?'selected':''; ?>>
              <?= h($L['nm']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Items header -->
    <div style="margin-top:14px; display:flex; justify-content:space-between; align-items:center;">
      <div>
        <strong>Items</strong>
    
      </div>
      <div style="display:flex; gap:8px; align-items:center;">
        <button type="button" class="btn primary" id="btnAdd">+ Add Product</button>
        <?php if ($edit_id > 0): ?>
          <!--<button type="button" class="btn secondary" id="btnDeleteDoc">Delete Doc</button>-->
        <?php endif; ?>
      </div>
    </div>

    <div class="table-wrap" style="margin-top:10px; overflow:auto;">
      <table class="table" style="min-width:1180px;">
        <thead>
          <tr>
            <th style="width:420px;">Product</th>
            <th style="width:70px;">Stock</th>
            <th style="width:90px; text-align:right;">Qty</th>
            <th style="width:170px;">1st Unit</th>
            <th style="width:170px;">2nd Unit</th>
            <th style="width:170px;">3rd Unit</th>
            <th style="width:240px;">Description</th>
            <th style="width:120px;">Action</th>
          </tr>
        </thead>
        <tbody id="gridBody">
          <tr class="emptyRow"><td colspan="9" style="text-align:center; opacity:.7;">No items added yet.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Remark at LAST (SS1) -->
    <div style="margin-top:12px;">
      <label>Remark</label>
      <input type="text" name="remark" class="inp" value="<?= h($hdr['remark']) ?>" placeholder="Optional remark">
    </div>

    <div style="margin-top:16px; display:flex; gap:10px; justify-content:center;">
      <button type="submit" class="btn success" style="min-width:140px;">Save</button>
      <button type="button" class="btn secondary" id="btnClear" style="min-width:120px;">Clear</button>
    </div>

    <?php if ($edit_id <= 0): ?>
      <div class="alert" style="margin-top:12px; opacity:.85;">
        Tip: Date change automatically loads FY Code + next Bill No.
      </div>
    <?php endif; ?>
  </form>
</div>

<!-- Modal -->
<div id="modalBackdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9998;"></div>
<div id="itemModal" style="display:none; position:fixed; z-index:9999; left:50%; top:50%; transform:translate(-50%,-50%); width:min(860px, 92vw); background:#fff; border-radius:14px; overflow:hidden;">
  <div style="padding:14px 16px; font-weight:700; font-size:18px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between;">
    <div>Add / Edit Item</div>
    <button type="button" class="btn secondary" id="m_x" style="padding:6px 10px;">✕</button>
  </div>

  <div style="padding:14px 16px;">
    <input type="hidden" id="m_row_index" value="-1">
    <input type="hidden" id="m_product_id" value="">
    <input type="hidden" id="m_sec_base_w" value="">
    <input type="hidden" id="m_sec_base_h" value="">
    <input type="hidden" id="m_third_base_w" value="">
    <input type="hidden" id="m_third_base_h" value="">

    <div style="margin-bottom:10px; position:relative;">
      <label>Product <span style="color:#ef4444;">*</span></label>
      <input type="text" id="m_product" class="inp" placeholder="Select product from suggestions">
      <div id="m_suggest" style="position:relative;"></div>
      <div style="font-size:12px; opacity:.75; margin-top:4px;">Select product from suggestions.</div>
    </div>

    <div style="display:grid; grid-template-columns: 120px 120px 1fr; gap:10px; align-items:end;">
      <div>
        <label>Stock</label>
        <input type="text" id="m_stock" class="inp locked" placeholder="-" readonly style="max-width:120px;">
      </div>
      <div>
        <label>Qty <span style="color:#ef4444;">*</span></label>
        <input type="number" id="m_qty" class="inp" min="0.001" step="0.001" value="1" style="max-width:120px;">
      </div>
      <div>
        <label>1st Unit</label>
        <input type="text" id="m_unit" class="inp locked" readonly>
      </div>
    </div>

    <div style="margin-top:12px; display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
      <div><label>2nd Unit </label><input type="text" id="m_sec_qty" class="inp locked" readonly></div>
      <div><label>3rd Unit </label><input type="text" id="m_third_qty" class="inp locked" readonly></div>

      <div id="m_sec_measure_wrap" style="display:none;">
        <label>2nd Unit Measure</label>
        <input type="text" id="m_sec_measure" class="inp locked" readonly>
      </div>
      <div id="m_third_measure_wrap" style="display:none;">
        <label>3rd Unit Measure</label>
        <input type="text" id="m_third_measure" class="inp locked" readonly>
      </div>
    </div>

    <div style="margin-top:12px;">
      <label>Description</label>
      <textarea id="m_description" class="inp" rows="2" placeholder="Optional description"></textarea>
    </div>

    <input type="hidden" id="m_unitconversion" value="">
    <input type="hidden" id="m_secondconversion" value="">

    <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:14px;">
      <button type="button" class="btn secondary" id="m_cancel">Cancel</button>
      <button type="button" class="btn success" id="m_save">Save</button>
    </div>
  </div>
</div>

<script>
// ---------- FLATPICKR (dd-mm-yyyy) ----------
(function(){
  function loadCss(href){ var l=document.createElement('link'); l.rel='stylesheet'; l.href=href; document.head.appendChild(l); }
  function loadJs(src, cb){ var s=document.createElement('script'); s.src=src; s.onload=cb; document.head.appendChild(s); }
  function init(){
    if (!window.flatpickr) return;
    flatpickr('#issue_date', { dateFormat: 'd-m-Y', allowInput: true, onChange: function(){ fetchFyBill(); } });
  }
  if (window.flatpickr) init();
  else { loadCss('https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css'); loadJs('https://cdn.jsdelivr.net/npm/flatpickr', init); }
})();



// ---------- state ----------
let grid = [];
try { grid = JSON.parse(document.getElementById('rows_json').value || '[]') || []; } catch(e){ grid=[]; }
function setJson(){ document.getElementById('rows_json').value = JSON.stringify(grid || []); }
function esc(s){ return String(s??'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

function calcMeasure(baseW, baseH, qty){
  const W = parseFloat(baseW || '0'), H = parseFloat(baseH || '0'), Q = parseFloat(qty || '0');
  if (!(W>0 && H>0 && Q>0)) return '';
  return (W*Q) + ' x ' + (H*Q);
}
function showUnitQty(conv, unitName){
  const u = (unitName || '').trim();
  const c = (conv === null || conv === undefined) ? '' : String(conv).trim();
  if (c && u) return (c + ' ' + u).trim();
  if (c) return c;
  if (u) return u;
  return '';
}

function renderGrid(){
  const tb = document.getElementById('gridBody');
  tb.innerHTML = '';
  if (!grid.length){
    const tr=document.createElement('tr');
    tr.className='emptyRow';
    tr.innerHTML='<td colspan="9" style="text-align:center; opacity:.7;">No items added yet.</td>';
    tb.appendChild(tr);
    setJson();
    return;
  }

  grid.forEach((r, idx) => {
    const secMeas   = calcMeasure(r.sec_width, r.sec_height, r.qty);
    const thirdMeas = calcMeasure(r.third_width, r.third_height, r.qty);

    const tr=document.createElement('tr');
    tr.innerHTML = `
      <td>${esc(r.product_name || '')}</td>
      <td style="max-width:70px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${esc(r.stock || '')}</td>
      <td style="text-align:right;">${esc(r.qty || '')}</td>
      <td>${esc(r.uom_name || '')}</td>
      <td>${esc(showUnitQty(r.unitconversion, r.secondaryunit))}</td>
      <td>${esc(showUnitQty(r.secondconversion, r.thirdunit))}</td>
      <td style="max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${esc(r.description || '')}</td>
      <td>
        <button type="button" class="btn secondary" onclick="editRow(${idx})">Edit</button>
        <button type="button" class="btn danger" onclick="delRow(${idx})">Del</button>
      </td>
    `;
    tb.appendChild(tr);
  });

  setJson();
}

function delRow(i){
  if (!confirm('Delete this item?')) return;
  grid.splice(i,1);
  renderGrid();
}
window.editRow = function(i){ openModal(i, grid[i]); };

// ---------- table focus highlight ----------
document.addEventListener('focusin', function(e){
  const td = e.target && e.target.closest ? e.target.closest('td') : null;
  document.querySelectorAll('td.focus-td').forEach(x=>x.classList.remove('focus-td'));
  if(td) td.classList.add('focus-td');
});
document.addEventListener('click', function(e){
  const td = e.target && e.target.closest ? e.target.closest('td') : null;
  document.querySelectorAll('td.focus-td').forEach(x=>x.classList.remove('focus-td'));
  if(td) td.classList.add('focus-td');
});

// ---------- modal ----------
const modal = document.getElementById('itemModal');
const back  = document.getElementById('modalBackdrop');
function showModal(){ back.style.display='block'; modal.style.display='block'; }
function hideModal(){ back.style.display='none'; modal.style.display='none'; }

document.getElementById('btnAdd').addEventListener('click', () => openModal(-1, null));
document.getElementById('m_cancel').addEventListener('click', hideModal);
document.getElementById('m_x').addEventListener('click', hideModal);
back.addEventListener('click', hideModal);
document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal.style.display === 'block') hideModal(); });

function openModal(idx, r){
  document.getElementById('m_row_index').value = String(idx ?? -1);

  document.getElementById('m_product_id').value = r ? (r.product_id||'') : '';
  document.getElementById('m_product').value    = r ? (r.product_name||'') : '';
  document.getElementById('m_stock').value      = r ? (r.stock||'') : '';
  document.getElementById('m_qty').value        = r ? (r.qty||1) : 1;
  document.getElementById('m_description').value = r ? (r.description || '') : '';

  document.getElementById('m_unit').value = r ? (r.uom || '') : '';


  document.getElementById('m_unitconversion').value = r ? (r.unitconversion || '') : '';
  document.getElementById('m_secondconversion').value = r ? (r.secondconversion || '') : '';

  document.getElementById('m_sec_base_w').value = r ? (r.sec_width || '') : '';
  document.getElementById('m_sec_base_h').value = r ? (r.sec_height || '') : '';
  document.getElementById('m_third_base_w').value = r ? (r.third_width || '') : '';
  document.getElementById('m_third_base_h').value = r ? (r.third_height || '') : '';

  const secMeas = r ? calcMeasure(r.sec_width, r.sec_height, r.qty) : '';
  const thirdMeas = r ? calcMeasure(r.third_width, r.third_height, r.qty) : '';
  document.getElementById('m_sec_measure').value = secMeas || '';
  document.getElementById('m_sec_measure_wrap').style.display = secMeas ? '' : 'none';
  document.getElementById('m_third_measure').value = thirdMeas || '';
  document.getElementById('m_third_measure_wrap').style.display = thirdMeas ? '' : 'none';

  const secUnitName = r ? (r.secondaryunit || '') : '';
  const thirdUnitName = r ? (r.thirdunit || '') : '';
  document.getElementById('m_sec_qty').value = showUnitQty(r ? r.unitconversion : '', secUnitName);
  document.getElementById('m_third_qty').value = showUnitQty(r ? r.secondconversion : '', thirdUnitName);

  showModal();
  setTimeout(()=>document.getElementById('m_product').focus(), 30);
}

// ---------- ajax helper ----------
async function postForm(data){
  const fd = new FormData();
  Object.keys(data).forEach(k => fd.append(k, data[k]));
  const res = await fetch(location.href, { method:'POST', body:fd, credentials:'same-origin' });
  return res.json();
}

// ---------- FY + BillNo fetch ----------
async function fetchFyBill(){
  const d = (document.getElementById('issue_date').value || '').trim();
  if (!d) return;
  const editId = parseInt(document.getElementById('edit_id').value || '0', 10);
  try{
    const j = await postForm({ ajax:'fy_bill', issue_date:d });
    if(j && j.ok){
      document.getElementById('fy_code').value = j.fy_code || '';
      document.getElementById('yrid').value = j.yrid || 0;

      if(editId <= 0){
        document.getElementById('billno').value = j.billno || '';
      }else{
        if(!document.getElementById('billno').value){
          document.getElementById('billno').value = j.billno || '';
        }
      }
    }
  }catch(e){}
}


/* ===== REUSABLE DROPDOWN ENGINE ===== */
function attachSuggest({
  input,
  fetchItems,     // async (query) => [{id,label,...}]
  onSelect        // (item) => void
}) {
  let box=null, items=[], active=-1;

  function close(){
    if(box){ box.remove(); box=null; }
    items=[]; active=-1;
  }

  function highlight(i){
    items.forEach(x=>x.classList.remove('active'));
    if(items[i]){
      items[i].classList.add('active');
      items[i].scrollIntoView({block:'nearest'});
    }
  }

  function render(list){
    close();
    if(!list || !list.length) return;

    const host = input.parentElement;
    box = document.createElement('div');
    box.className='suggest-box';

    list.forEach((it,i)=>{
      const d=document.createElement('div');
      d.className='suggest-item';
      d.textContent=it.label;
      d.onclick=()=>{ onSelect(it); close(); };
      box.appendChild(d);
    });

    items=[...box.children];
    host.appendChild(box);
  }

  input.addEventListener('input', async ()=>{
    const q=input.value.trim();
    if(q.length<2){ close(); return; }
    const list = await fetchItems(q);
    render(list);
  });

  input.addEventListener('keydown',e=>{
    if(!box) return;

    if(e.key==='ArrowDown'){ e.preventDefault(); active=Math.min(active+1,items.length-1); highlight(active); }
    if(e.key==='ArrowUp'){ e.preventDefault(); active=Math.max(active-1,0); highlight(active); }
    if(e.key==='Enter' && active>=0){ e.preventDefault(); items[active].click(); }
    if(e.key==='Escape'){ close(); }
  });

  document.addEventListener('click',e=>{
    if(!box) return;
    if(e.target===input) return;
    if(box.contains(e.target)) return;
    close();
  });
}


// conversion updater
async function applyConversion(pid, qty){
  if (!pid || !(qty > 0)) return;
  try{
    const j = await postForm({ ajax:'get_conversion', pid:String(pid), qty:String(qty) });
    if (j && j.ok){
      document.getElementById('m_unitconversion').value = (j.unitconversion === '' ? '' : String(j.unitconversion));
      document.getElementById('m_secondconversion').value = (j.secondconversion === '' ? '' : String(j.secondconversion));

      const secName = (window._sec_unitname || '').trim();
      const thirdName = (window._third_unitname || '').trim();
      document.getElementById('m_sec_qty').value = showUnitQty(document.getElementById('m_unitconversion').value, secName);
      document.getElementById('m_third_qty').value = showUnitQty(document.getElementById('m_secondconversion').value, thirdName);

      document.getElementById('m_sec_base_w').value = j.sec_base_width || '';
      document.getElementById('m_sec_base_h').value = j.sec_base_height || '';
      document.getElementById('m_third_base_w').value = j.third_base_width || '';
      document.getElementById('m_third_base_h').value = j.third_base_height || '';

      document.getElementById('m_sec_measure').value = j.sec_measurement || '';
      document.getElementById('m_sec_measure_wrap').style.display = j.sec_measurement ? '' : 'none';
      document.getElementById('m_third_measure').value = j.third_measurement || '';
      document.getElementById('m_third_measure_wrap').style.display = j.third_measurement ? '' : 'none';
    }
  }catch(e){}
}

document.getElementById('m_qty').addEventListener('input', function(){
  const pid = parseInt(document.getElementById('m_product_id').value || '0', 10);
  const qty = parseFloat(this.value || '0');
  if (pid > 0 && qty > 0) applyConversion(pid, qty);
});

// async function selectProduct(it){
//   document.getElementById('m_product').value = it.label || it.name || '';
//   document.getElementById('m_product_id').value = it.id || '';


//   try{
//     const j = await postForm({ ajax:'product_details', product_id:String(it.id||'') });
//     if (j && j.ok && j.row){
//       const r = j.row;
//       document.getElementById('m_unit').value = (r._unit_name || '');
//       window._unit_id = parseInt(r.unit || 0, 10); // ✅ ADD THIS
//       window._sec_unitname = (r._secondary_name || '').trim();
//       window._third_unitname = (r._third_name || '').trim();

//       document.getElementById('m_sec_qty').value   = showUnitQty('', window._sec_unitname);
//       document.getElementById('m_third_qty').value = showUnitQty('', window._third_unitname);

//       const qty = parseFloat(document.getElementById('m_qty').value || '0');
//       if (qty > 0) applyConversion(parseInt(it.id,10), qty);
//     }
//   }catch(e){}
// }


async function selectProduct(it){

  document.getElementById('m_product').value = it.label || it.name || '';
  document.getElementById('m_product_id').value = it.id || '';

  const from_gid = document.getElementById('from_gid').value;
  const yrid     = document.getElementById('yrid').value;

  try{
    const j = await postForm({
      ajax:'get_stock',
      pid: it.id,
      from_gid: from_gid,
      yrid: yrid
    });

    document.getElementById('m_stock').value =
      (j && j.ok) ? j.stock : '0';

  }catch(e){
    document.getElementById('m_stock').value = '0';
  }

  // keep your existing product_details logic BELOW this
    try{
    const j = await postForm({ ajax:'product_details', product_id:String(it.id||'') });
    if (j && j.ok && j.row){
      const r = j.row;
      document.getElementById('m_unit').value = (r._unit_name || '');
      window._unit_id = parseInt(r.unit || 0, 10); // ✅ ADD THIS
      window._sec_unitname = (r._secondary_name || '').trim();
      window._third_unitname = (r._third_name || '').trim();

      document.getElementById('m_sec_qty').value   = showUnitQty('', window._sec_unitname);
      document.getElementById('m_third_qty').value = showUnitQty('', window._third_unitname);

      const qty = parseFloat(document.getElementById('m_qty').value || '0');
      if (qty > 0) applyConversion(parseInt(it.id,10), qty);
    }
  }catch(e){}
}



// ---------- save modal row ----------
document.getElementById('m_save').addEventListener('click', function(){
  const product_id = (document.getElementById('m_product_id').value || '').trim();
  const product_name = (document.getElementById('m_product').value || '').trim();
  const qty = parseFloat(document.getElementById('m_qty').value || '0');
  const stock = (document.getElementById('m_stock').value || '').trim();
  const description = (document.getElementById('m_description').value || '').trim();

  if (!product_id){ alert('Select product from suggestions.'); return; }
  if (!(qty > 0)){ alert('Qty must be greater than 0.'); return; }

  const row = {
    product_id: parseInt(product_id,10),
    product_name: product_name,
    description: description,
    qty: qty,
    uom_id: parseInt(window._unit_id || 0, 10),
    uom_name: document.getElementById('m_unit').value,
    // uom: document.getElementById('m_unit').value, 
    stock: stock,
    // unit: document.getElementById('m_unit').value,
    secondaryunit: (window._sec_unitname || ''),
    thirdunit: (window._third_unitname || ''),
    unitconversion: document.getElementById('m_unitconversion').value,
    secondconversion: document.getElementById('m_secondconversion').value,
    sec_width: document.getElementById('m_sec_base_w').value,
    sec_height: document.getElementById('m_sec_base_h').value,
    third_width: document.getElementById('m_third_base_w').value,
    third_height: document.getElementById('m_third_base_h').value
  };

  const idx = parseInt(document.getElementById('m_row_index').value || '-1', 10);
  if (idx >= 0) grid[idx] = row; else grid.push(row);

  hideModal();
  renderGrid();
});

// ---------- clear ----------
document.getElementById('btnClear').addEventListener('click', function(){
  if(!confirm('Clear form?')) return;
  document.querySelector('[name="to_gid"]').value='';
  document.querySelector('[name="remark"]').value='';
  document.getElementById('billno').value='';
  document.getElementById('fy_code').value='';
  document.getElementById('yrid').value='0';
  grid = [];
  renderGrid();
});

// ---------- delete doc ----------
const btnDeleteDoc = document.getElementById('btnDeleteDoc');
if(btnDeleteDoc){
  btnDeleteDoc.addEventListener('click', function(){
    if(!confirm('Delete this document?')) return;
    document.getElementById('mode').value = 'delete';
    document.getElementById('mainForm').submit();
  });
}


// ---------- initial ----------
renderGrid();
fetchFyBill();

attachSuggest({
  input: document.getElementById('m_product'),

  fetchItems: async (q) => {
    const res = await fetch(location.href, {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({
        ajax:'product_search',
        q:q
      })
    });
    const j = await res.json();
    return (j && j.ok) ? (j.items || []) : [];
  },

  onSelect: (it) => {
    selectProduct(it); 
  }
});

</script>

<?php
$CONTENT = ob_get_clean();
require_once __DIR__ . '/../includes/ui_autoshell.php';
