<?php
/* ============================================================
 * reports/service_quotation_print.php
 * Service Quotation Print (Preview + PDF)
 * ============================================================
 * - POST ONLY (no URL id)
 * - Shows PDF preview on page + Print button
 * - Generates PDF similar to provided sample
 * ============================================================ */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/initialize.php';
require_once __DIR__ . '/../includes/aclhelper.php';
require_once __DIR__ . '/../includes/Header.php';




if (!function_exists('is_logged_in') || !is_logged_in()) {
    redirect('../login.php');
}
date_default_timezone_set('Asia/Kolkata');

$con = $con ?? null;
if (!$con instanceof mysqli) {
    die('DB connection missing.');
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ============================================================
 * TABLES (FROZEN)
 * ============================================================ */
$TABLE_PRODUCTS = 'jos_crm_mproducts';
$TABLE_Q_HEADER   = 'jos_ierp_complaint_quotation';
$TABLE_Q_GRID     = 'jos_ierp_complaint_quotationgrid';
$TABLE_Q_TERMS    = 'jos_ierp_complaint_quotationterms';
$TABLE_CUSTOMERS  = 'jos_ierp_customermaster';

/* ============================================================
 * HELPERS
 * ============================================================ */
function h($v)
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function csrf_token()
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['_csrf'];
}
function verify_csrf(): bool
{
    $t = (string)($_POST['_csrf'] ?? '');
    return $t !== '' && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $t);
}
function col_exists(mysqli $con, string $table, string $col): bool
{
    $dbRow = $con->query("SELECT DATABASE() d");
    $db = $dbRow ? ($dbRow->fetch_assoc()['d'] ?? '') : '';
    if ($db === '') return false;

    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
    $st = $con->prepare($sql);
    $st->bind_param('sss', $db, $table, $col);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_row();
    $st->close();
    return $ok;
}
function fmt_dmy($ymd)
{
    $ymd = trim((string)$ymd);
    if ($ymd === '' || $ymd === '0000-00-00') return '';
    $dt = DateTime::createFromFormat('Y-m-d', substr($ymd, 0, 10));
    return $dt ? $dt->format('d-m-Y') : '';
}
function num($v)
{
    $v = (float)($v ?? 0);
    return is_nan($v) ? 0.0 : $v;
}
function money2($v)
{
    return number_format((float)$v, 2, '.', '');
}

/* Indian number to words (simple, sufficient for invoices) */
function amount_in_words_indian($number): string
{
    $number = round((float)$number, 2);
    $rupees = (int)floor($number);
    $paise  = (int)round(($number - $rupees) * 100);

    $ones = [
        '',
        'One',
        'Two',
        'Three',
        'Four',
        'Five',
        'Six',
        'Seven',
        'Eight',
        'Nine',
        'Ten',
        'Eleven',
        'Twelve',
        'Thirteen',
        'Fourteen',
        'Fifteen',
        'Sixteen',
        'Seventeen',
        'Eighteen',
        'Nineteen'
    ];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    $toWords2 = function ($n) use ($ones, $tens) {
        $n = (int)$n;
        if ($n < 20) return $ones[$n];
        $t = (int)floor($n / 10);
        $o = $n % 10;
        return trim($tens[$t] . ' ' . $ones[$o]);
    };

    $toWords3 = function ($n) use ($toWords2, $ones) {
        $n = (int)$n;
        if ($n < 100) return $toWords2($n);
        $h = (int)floor($n / 100);
        $r = $n % 100;
        return trim($ones[$h] . ' Hundred ' . ($r ? $toWords2($r) : ''));
    };

    if ($rupees === 0) $words = 'Zero';
    else {
        $parts = [];

        $crore = (int)floor($rupees / 10000000);
        $rupees %= 10000000;
        if ($crore) $parts[] = $toWords3($crore) . ' Crore';

        $lakh = (int)floor($rupees / 100000);
        $rupees %= 100000;
        if ($lakh) $parts[] = $toWords3($lakh) . ' Lakh';

        $thousand = (int)floor($rupees / 1000);
        $rupees %= 1000;
        if ($thousand) $parts[] = $toWords3($thousand) . ' Thousand';

        $hundreds = $rupees;
        if ($hundreds) $parts[] = $toWords3($hundreds);

        $words = trim(implode(' ', $parts));
    }

    $result = $words . ' Only';
    if ($paise > 0) {
        $result = $words . ' and ' . $toWords2($paise) . ' Paise Only';
    }
    return preg_replace('/\s+/', ' ', trim($result));
}

/* ============================================================
 * ACL (same ERP console style)
 * ============================================================ */
$aclMeta = erp_get_menu_meta_and_acl($con);
$menuMetaTitle  = $aclMeta['title']      ?? 'Quotation Print';
$menuMetaRemark = $aclMeta['remark']     ?? '';
$canView        = $aclMeta['can_view']   ?? false;
$canEdit        = $aclMeta['can_edit']   ?? false;

$userObj       = current_user() ?? [];
$user          = $userObj;

$pageTitle   = $menuMetaTitle;
$systemTitle = 'ERP Console';
$systemCode  = 'AGCM';
$userName    = $user['name'] ?? 'User';
$userLoginId = $user['login_id'] ?? ($user['email'] ?? ($user['mobile_no'] ?? ''));

if (!$canView) {
    ob_start(); ?>
    <div class="master-wrap">
        <div class="headbar">
            <div>
                <h1 class="page-title"><?php echo h($menuMetaTitle); ?></h1>
                <div class="page-subtitle"><?php echo h($menuMetaRemark); ?></div>
            </div>
        </div>
        <div class="card" style="margin-top:20px;">
            <div class="alert danger">You do not have permission to view this page.</div>
        </div>
    </div>
<?php
    $CONTENT = ob_get_clean();
    require_once __DIR__ . '/../includes/ui_autoshell.php';
    exit;
}

/* ============================================================
 * POST INPUT
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_start(); ?>
    <div class="master-wrap">
        <div class="card" style="margin-top:20px;">
            <div class="alert danger">Invalid access. Open via Print button only.</div>
        </div>
    </div>
<?php
    $CONTENT = ob_get_clean();
    require_once __DIR__ . '/../includes/ui_autoshell.php';
    exit;
}

if (!verify_csrf()) {
    ob_start(); ?>
    <div class="master-wrap">
        <div class="card" style="margin-top:20px;">
            <div class="alert danger">Invalid token. Please go back and try again.</div>
        </div>
    </div>
<?php
    $CONTENT = ob_get_clean();
    require_once __DIR__ . '/../includes/ui_autoshell.php';
    exit;
}

$quotation_id = (int)($_POST['quotation_id'] ?? 0);
if ($quotation_id <= 0) {
    ob_start(); ?>
    <div class="master-wrap">
        <div class="card" style="margin-top:20px;">
            <div class="alert danger">Quotation ID missing.</div>
        </div>
    </div>
<?php
    $CONTENT = ob_get_clean();
    require_once __DIR__ . '/../includes/ui_autoshell.php';
    exit;
}

/* ============================================================
 * LOAD DATA
 * ============================================================ */
$st = $con->prepare("SELECT * FROM {$TABLE_Q_HEADER} WHERE id=? LIMIT 1");
$st->bind_param('i', $quotation_id);
$st->execute();
$hdr = $st->get_result()->fetch_assoc();
$st->close();

if (!$hdr) {
    ob_start(); ?>
    <div class="master-wrap">
        <div class="card" style="margin-top:20px;">
            <div class="alert danger">Quotation not found.</div>
        </div>
    </div>
<?php
    $CONTENT = ob_get_clean();
    require_once __DIR__ . '/../includes/ui_autoshell.php';
    exit;
}



/* Quote No: prefer billno if exists */
/*($quoteNo = '';
if (isset($hdr['billno']) && $hdr['billno'] !== '' && $hdr['billno'] !== null) {
    $quoteNo = (string)$hdr['billno'];
} else {
    $quoteNo = (string)$hdr['id'];
}*/


// Company code from DB (AGCM / AMSS / etc.)
$companyCode = trim($hdr['companyname'] ?? 'AGCM');
$companyCode = strtoupper($companyCode);
$company     = getCompanyHeader($companyCode);

// Financial Year
$fy = date('y') . '-' . (date('y') + 1);


// Company code from DB (AGCM / AMSS / etc.)
//$companyCode = trim($hdr['companyname'] ?? 'AGCM');  

// Bill No (fallback to id if empty)
$billNo = (int)($hdr['billno'] ?? 0);
if ($billNo <= 0) {
    $billNo = (int)$hdr['id'];
}

// Build Quotation No
$quoteNo = $companyCode . '/SQ/' . $fy . '/' . str_pad($billNo, 3, '0', STR_PAD_LEFT);


$qDate = fmt_dmy($hdr['date'] ?? '');
$kindAttn = (string)($hdr['kindattn'] ?? '');
$refe     = (string)($hdr['refe'] ?? '');
$subject  = (string)($hdr['subject'] ?? '');
$descr    = (string)($hdr['descr'] ?? '');

$customerName = (string)($hdr['customer'] ?? '');
$custId = (int)($hdr['custid'] ?? 0);


/* customer address block (best-effort; guarded columns) */
$cust = null;
if ($custId > 0 && $TABLE_CUSTOMERS) {
    $stc = $con->prepare("SELECT * FROM {$TABLE_CUSTOMERS} WHERE id=? LIMIT 1");
    $stc->bind_param('i', $custId);
    $stc->execute();
    $cust = $stc->get_result()->fetch_assoc();
    $stc->close();
    if ($customerName === '' && !empty($cust['name'])) $customerName = (string)$cust['name'];
}

$custAddrLines = [];
if ($customerName !== '') $custAddrLines[] = $customerName;

/* try typical columns if present */
foreach (['address', 'addr', 'address1'] as $cc) {
    if ($cust && isset($cust[$cc]) && trim((string)$cust[$cc]) !== '') {
        $custAddrLines[] = trim((string)$cust[$cc]);
        break;
    }
}
$city = ($cust && isset($cust['city']) && trim((string)$cust['city']) !== '') ? trim((string)$cust['city']) : '';
$tal  = ($cust && isset($cust['taluka']) && trim((string)$cust['taluka']) !== '') ? trim((string)$cust['taluka']) : '';
$dist = ($cust && isset($cust['district']) && trim((string)$cust['district']) !== '') ? trim((string)$cust['district']) : '';
$state = ($cust && isset($cust['state']) && trim((string)$cust['state']) !== '') ? trim((string)$cust['state']) : '';
$pin  = ($cust && isset($cust['pincode']) && trim((string)$cust['pincode']) !== '') ? trim((string)$cust['pincode']) : '';
$mob  = ($cust && isset($cust['mobile']) && trim((string)$cust['mobile']) !== '') ? trim((string)$cust['mobile']) : '';
$email = ($cust && isset($cust['email']) && trim((string)$cust['email']) !== '') ? trim((string)$cust['email']) : '';

$placeLine = trim(implode(', ', array_filter([$city ?: null, $tal ?: null, $dist ?: null, $state ?: null])));
if ($placeLine !== '') $custAddrLines[] = $placeLine;
if ($pin !== '') $custAddrLines[] = 'Pin: ' . $pin;
if ($mob !== '') $custAddrLines[] = 'Mobile No : ' . $mob;
if ($email !== '') $custAddrLines[] = 'E-Mail : ' . $email;

/* Grid */
$seqExists = col_exists($con, $TABLE_Q_GRID, 'sequence');
$order = $seqExists ? "ORDER BY sequence ASC, id ASC" : "ORDER BY id ASC";

$sqlG = "
SELECT 
  g.*,
  TRIM(CONCAT(p.name,' ',IFNULL(p.modelcode,''))) AS product_name
FROM {$TABLE_Q_GRID} g
LEFT JOIN {$TABLE_PRODUCTS} p ON p.id = g.propid
WHERE g.qutid = ?
{$order}
";

$stg = $con->prepare($sqlG);
$stg->bind_param('i', $quotation_id);
$stg->execute();
$rsG = $stg->get_result();
$grid = [];

while ($rsG && ($r = $rsG->fetch_assoc())) {
    $grid[] = $r;
}
$stg->close();

/* ============================================================
 * CHECK IF DISCOUNT COLUMNS SHOULD BE SHOWN
 * ============================================================ */
// $showDiscountCols = false;


// foreach ($grid as $r) {
//     if (
//         num($r['discount'] ?? 0) > 0 ||
//         num($r['addloyality_per'] ?? 0) > 0 ||
//         num($r['adddiscount_per'] ?? 0) > 0
//     ) {
//         $showDiscountCols = true;
//         break;
//     }
// }

$showDiscCol   = false;
$showLoyalCol = false;
$showAddCol   = false;

foreach ($grid as $r) {
    if (num($r['discount'] ?? 0) > 0 || num($r['instantdisc'] ?? 0) > 0) {
        $showDiscCol = true;
    }
    if (num($r['addiloyality_per'] ?? 0) > 0 || num($r['addiloyalilty_rs'] ?? 0) > 0) {
        $showLoyalCol = true;
    }
    if (num($r['addidiscount_per'] ?? 0) > 0 || num($r['addidiscount_rs'] ?? 0) > 0) {
        $showAddCol = true;
    }
}

$showDiscountCols = ($showDiscCol || $showLoyalCol || $showAddCol);

/* ===============================
 * FINAL COLUMN CALCULATION
 * =============================== */
$totalCols = $showDiscountCols ? 8 : 5;

// rows that end at Amount only
$labelToAmountColspan = $totalCols - 1;

// "Total" row (Qty + Amount)
$labelToQtyColspan = $showDiscountCols ? 4 : 3;

/* ===============================
 * TABLE COLUMN CONTROLS
 * =============================== */
// Total columns before last numeric column
$totalColspan = $showDiscountCols ? 6 : 3;

// Grand total spans all except Amount
$colspan = $showDiscountCols ? 7 : 4;




/* ============================================================
 * LOAD TERMS & CONDITIONS
 * ============================================================ */
$terms = [];

$stt = $con->prepare("
    SELECT title, description
    FROM {$TABLE_Q_TERMS}
    WHERE qutid = ?
    ORDER BY id ASC
");
$stt->bind_param('i', $quotation_id);
$stt->execute();
$rsT = $stt->get_result();

while ($rsT && ($r = $rsT->fetch_assoc())) {
    $terms[] = $r;
}
$stt->close();


/* Totals (prefer header saved values if exist) */
/* Base Totals */
$subTotal      = num($hdr['subtotal'] ?? 0);
$totalGst      = num($hdr['gst'] ?? 0);
$basicWithGst  = num($hdr['nettotal'] ?? 0);

/* Extra Charges (FINAL values) */
/* Extra Charges (FINAL DB values) */
$packingTotal   = isset($hdr['packing_total'])   ? num($hdr['packing_total'])   : 0.0;
$transportTotal = isset($hdr['trans_total'])     ? num($hdr['trans_total'])     : 0.0;
$insuranceTotal = isset($hdr['insurance_total']) ? num($hdr['insurance_total']) : 0.0;

/* Final Grand Total */
$grandTotal = isset($hdr['total']) ? num($hdr['total']) : 0.0;

/* Final Grand Total */
$grandTotal = num($hdr['total'] ?? 0);


/* If header totals missing, calculate from grid */
if ($subTotal <= 0 && $totalGst <= 0 && !empty($grid)) {
    $sub = 0.0;
    $gst = 0.0;
    foreach ($grid as $r) {
        $sub += isset($r['discountedamt']) ? num($r['discountedamt']) : (isset($r['amt']) ? num($r['amt']) : 0.0);
        $gst += isset($r['gstamt']) ? num($r['gstamt']) : 0.0;
    }
    // If discountedamt already includes no gst, keep
    if ($gst > 0) {
        $subTotal = $sub;
        $totalGst = $gst;
        $basicWithGst = $subTotal + $totalGst;
        $grandTotal = $basicWithGst;
    } else {
        $subTotal = $sub;
        $basicWithGst = $subTotal;
        $grandTotal = $basicWithGst;
    }
}

/* Qty total + Total amount (sum of line amounts) */
$totalQty = 0.0;
$totalAmtLines = 0.0;
foreach ($grid as $r) {
    $totalQty += isset($r['qty']) ? num($r['qty']) : 0.0;
    $totalAmtLines += isset($r['amt']) ? num($r['amt']) : 0.0;
}
if ($totalAmtLines > 0 && $grandTotal <= 0) $grandTotal = $totalAmtLines;

/* COMPANY BLOCK (best effort from header.companyname) */
$companyName = trim((string)($hdr['companyname'] ?? ''));
if ($companyName === '') $companyName = 'COMPANY NAME';


/* ============================================================
 * BUILD PDF HTML (layout similar to sample)
 * ============================================================ */
$rowsHtml = '';
$sr = 1;

foreach ($grid as $r) {

    $desc = trim($r['product_name'] ?? $r['description'] ?? 'Item');

    $qty   = num($r['qty'] ?? 0);
    $rate  = num($r['rate'] ?? 0);

    // percentages
    $discPer   = num($r['discount'] ?? 0);
    $loyalPer  = num($r['addiloyality_per'] ?? 0);
    $addPer    = num($r['addidiscount_per'] ?? 0);

    //  amount
    $discCalc  = num($r['instantdisc'] ?? 0);
    $loyalCalc = num($r['addiloyalilty_rs'] ?? 0);
    $addCalc   = num($r['addidiscount_rs'] ?? 0);

    // stored absolute amounts (fallback)
    $discDb    = num($r['totaldiscount'] ?? 0);
    $loyalDb   = num($r['additionalloyality'] ?? 0);
    $addDb     = num($r['additionaldiscount'] ?? 0);

    $amt   = num($r['amt'] ?? 0);

    $rowsHtml .= '<tr>';
    $rowsHtml .= '<td class="c">' . $sr . '</td>';
    $rowsHtml .= '<td>' . h($desc) . '</td>';
    $rowsHtml .= '<td class="r">' . money2($qty) . '</td>';
    $rowsHtml .= '<td class="r">' . money2($rate) . '</td>';







    // Discounted Price
    $discDisplay = ($discPer > 0 || $discCalc > 0)
        ? $discDb
        : 0;

    // Additional Loyalty
    $loyalDisplay = ($loyalPer > 0 || $loyalCalc > 0)
        ? $loyalDb
        : 0;

    // Additional Discount
    $addDisplay = ($addPer > 0 || $addCalc > 0)
        ? $addDb
        : 0;


    if ($showDiscCol) {
        $discDisplay = ($discPer > 0 || $discCalc > 0) ? $discDb : 0;
        $rowsHtml .= '<td class="r">' . money2($discDisplay) . '</td>';
    }

    if ($showLoyalCol) {
        $loyalDisplay = ($loyalPer > 0 || $loyalCalc > 0) ? $loyalDb : 0;
        $rowsHtml .= '<td class="r">' . money2($loyalDisplay) . '</td>';
    }

    if ($showAddCol) {
        $addDisplay = ($addPer > 0 || $addCalc > 0) ? $addDb : 0;
        $rowsHtml .= '<td class="r">' . money2($addDisplay) . '</td>';
    }

    // $rowsHtml .= '<td class="r">' . money2($discDisplay) . '</td>';
    // $rowsHtml .= '<td class="r">' . money2($loyalDisplay) . '</td>';
    // $rowsHtml .= '<td class="r">' . money2($addDisplay) . '</td>';


    $rowsHtml .= '<td class="r">' . money2($amt) . '</td>';
    $rowsHtml .= '</tr>';

    $sr++;
}


if ($rowsHtml === '') {
    $rowsHtml = '<tr><td colspan="4" class="c" style="padding:10px;">No items</td></tr>';
}

$finalPayable = $basicWithGst
    + $packingTotal
    + $transportTotal
    + $insuranceTotal;

if ($grandTotal > 0) {
    $finalPayable = $grandTotal;
}

$amountWords = amount_in_words_indian($finalPayable);



$termsHtml = '';
if (!empty($terms)) {
    foreach ($terms as $t) {
        $termsHtml .= '
        <div style="margin-bottom:10px;">
          <div class="b">' . h($t['title']) . '</div>
          <div style="margin-top:4px;">' . $t['description'] . '</div>
        </div>';
    }
}

$customerBlock = '';
foreach ($custAddrLines as $ln) {
    $customerBlock .= h($ln) . "<br>";
}

/* Final Grand Total */
$grandTotal = isset($hdr['total']) ? num($hdr['total']) : 0.0;

/* ===============================
 * BUILD EXTRA CHARGES HTML
 * =============================== */
$chargesHtml = '';

if ($packingTotal > 0) {
    $chargesHtml .= '
    <tr class="totline">
        <td colspan="' . $totalColspan . '" class="r b">
Packing Charges</td>
        <td class="r b">' . h(money2($packingTotal)) . '</td>
    </tr>';
}

if ($transportTotal > 0) {
    $chargesHtml .= '
    <tr class="totline">
        <td colspan="' . $totalColspan . '" class="r b">
Transport Charges</td>
        <td class="r b">' . h(money2($transportTotal)) . '</td>
    </tr>';
}

if ($insuranceTotal > 0) {
    $chargesHtml .= '
    <tr class="totline">
        <td colspan="' . $totalColspan . '" class="r b">
Insurance Charges</td>
        <td class="r b">' . h(money2($insuranceTotal)) . '</td>
    </tr>';
}

$chargesHtml .= '
<tr class="totline">
    <td colspan="' . ($showDiscountCols ? 7 : 4) . '" class="r b">Grand Total</td>
<td class="r b">' . h(money2($grandTotal)) . '</td>

    
</tr>';

$logoBase64 = '';
$logoPath = $company['logo'];

if ($logoPath && file_exists($logoPath)) {
    $imgData = @file_get_contents($logoPath);
    if ($imgData !== false) {
        $mime = 'image/jpeg'; // force jpeg (dompdf safe)
        $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode($imgData);
    }
}


$discPer     = 0;
$loyaltyPer  = 0;
$addDiscPer  = 0;

foreach ($grid as $r) {
    if ($discPer == 0 && num($r['discount'] ?? 0) > 0) {
        $discPer = num($r['discount']);
    }
    if ($loyaltyPer == 0 && num($r['addiloyality_per'] ?? 0) > 0) {
        $loyaltyPer = num($r['addiloyality_per']);
    }
    if ($addDiscPer == 0 && num($r['addidiscount_per'] ?? 0) > 0) {
        $addDiscPer = num($r['addidiscount_per']);
    }
}

$theadHtml = '
<tr>
    <th>S.<br>No.</th>
    <th>Particular</th>
    <th>Qty</th>
    <th>Rate Rs.<br>Each</th>
';


$theadHtml = '
<tr>
    <th>S.<br>No.</th>
    <th>Particular</th>
    <th>Qty</th>
    <th>Rate Rs.<br>Each</th>
';

$colgroupHtml = '
<col style="width:5%">
<col style="width:25%">
<col style="width:7%">
<col style="width:12%">
';

if ($showDiscCol) {
    $theadHtml .= '
    <th>
        Discounted<br>
        Price ' . ($discPer ? '@' . $discPer . '%' : '') . '<br>
        Rate Rs.<br>
        Each
    </th>';
}

if ($showLoyalCol) {
    $theadHtml .= '
    <th>
        Additional<br>
        Loyalty<br>
        Benefits ' . ($loyaltyPer ? '@' . $loyaltyPer . '%' : '') . '<br>
        Rs.<br>
        Each
    </th>';
}

if ($showAddCol) {
    $theadHtml .= '
    <th>
        Additional<br>
        Discount ' . ($addDiscPer ? '@' . $addDiscPer . '%' : '') . '<br>
        Rs.<br>
        Each
    </th>';
}

$theadHtml .= '<th>Amount</th></tr>';






$pdfHtml = '
<!doctype html>
<html>



<head>
<meta charset="utf-8">
<style>
@page {
  margin: 38px 10px 50px 10px;
}


.page-border {
  position: fixed;
  top: 10px;
  bottom: 10px;
  left: 10px;
  right: 10px;
  border: 1px solid #111;
  z-index: -1;
}

.page-content {
  padding: 10px;
  padding-top: 1cm;   /* 1 cm gap from top on every page */
  box-sizing: border-box;
}


  body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 12px;
    color:#111;
  }

p {
  margin: 0;
  padding: 0;
}

.desc-text {
  line-height: 1.35;
  margin-top: 4px;
}


  .row { width:100%; border-collapse:collapse; }
  .row td { vertical-align:top; }
  .title { text-align:center; font-weight:bold; font-size:16px; padding:8px 0; }
  .subtitle { text-align:center; font-size:11px; padding-bottom:6px; }
  .pad { padding:8px; }
  .b { font-weight:bold; }
  .small { font-size:11px; }
  .c { text-align:center; }
  .r { text-align:right; }

 .tbl {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.tbl th, .tbl td {
    border: 1px solid #111;
    padding: 6px;
    font-size: 12px;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.tbl th {
    background: #e9e9e9;
    font-weight: bold;
    text-align: center;
}

  .terms-block {
  page-break-inside: avoid;
  margin-top: 6px;
}
.term-item {
  margin-bottom: 6px;
  page-break-inside: avoid;
}

 .tbl {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.tbl th, .tbl td {
    border: 1px solid #111;
    padding: 6px;
    font-size: 12px;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.tbl th {
    background: #e9e9e9;
    font-weight: bold;
    text-align: center;
}

.totline td {
    border: 1px solid #111;
    padding: 6px;
}

  .totline td { border:1px solid #111; padding:6px; }

  thead { display: table-header-group; }
  tfoot { display: table-footer-group; }

.desc-text {
  page-break-inside: avoid;
}

.tbl {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;   /* 🔥 VERY IMPORTANT */
}

.tbl th, .tbl td {
    word-wrap: break-word;
    overflow-wrap: break-word;
}


</style>
</head>

<body>

<!-- PAGE BORDER (AUTO ON EVERY PAGE) -->
<div class="page-border"></div>



<!-- PAGE CONTENT -->
<div class="page-content">

  <div class="title">QUOTATION</div>
  <div class="subtitle">(Subject to Satara Jurisdiction)</div>

  <table class="row" style="border-top:1px solid #111;">
    <tr>
      <td style="width:50%; border-right:1px solid #111;" class="pad">
        <div class="b">To,</div>
        <div>' . $customerBlock . '</div>
      </td>
      <td style="width:50%;" class="pad">
        ' . ($logoBase64 ? '<img src="' . $logoBase64 . '" style="height:60px;"><br><br>' : '') . '
        <div style="font-size:16px; font-weight:bold;">
    ' . h($company['name'] ?? '') . '
</div>
        <div class="small">' . h($company['addr']) . '</div>
        <div class="small">Ph: ' . h($company['phone']) . '</div>
        <div class="small">Email: ' . h($company['email']) . '</div>
        <div class="small">Web: ' . h($company['web']) . '</div>
        <div class="small b">GST NO: ' . h($company['gst']) . '</div>
        ' . ($company['cin'] ? '<div class="small b">CIN: ' . h($company['cin']) . '</div>' : '') . '
      </td>
    </tr>
  </table>

  <table class="row" style="border-top:1px solid #111;">
    <tr>
      <td class="pad" style="width:50%; border-right:1px solid #111;">
        <div><span class="b">Quotation No</span> : ' . h($quoteNo) . '</div>
        <div><span class="b">Quotation Date</span> : ' . h($qDate) . '</div>
      </td>
      <td class="pad" style="width:50%;">
        <div><span class="b">Kind Attn</span> : ' . h($kindAttn) . '</div>
      </td>
    </tr>
  </table>

  <div class="pad" style="border-top:1px solid #111;">
    <div class="b">Subject:</div>
    <div>' . nl2br(h($subject)) . '</div>
    <div style="margin-top:6px;"><span class="b">Ref:</span> ' . nl2br(h($refe)) . '</div>
    <div style="margin-top:6px;">Dear Sir,</div>
    <div class="desc-text">
    ' . nl2br(h(trim($descr))) . '
</div>

  </div>

<table class="tbl" style="border-top:1px solid #111; margin-top:6px;">
<colgroup>
' . $colgroupHtml . '
</colgroup>


<thead>
' . $theadHtml . '
</thead>


    <tbody>
      ' . $rowsHtml . '
    <tr class="totline">
    <td colspan="6" class="r b">Total</td>
    <td class="r b">' . h(money2($totalQty)) . '</td>
    <td class="r b">' . h(money2($totalAmtLines ?: $subTotal)) . '</td>
</tr>

<tr class="totline">
    <td colspan="7" class="r b">Sub Total</td>
    <td class="r b">' . h(money2($subTotal)) . '</td>
</tr>

<tr class="totline">
    <td colspan="7" class="r b">GST</td>
    <td class="r b">' . h(money2($totalGst)) . '</td>
</tr>

<tr class="totline">
    <td colspan="7" class="r b">Basic Cost With GST</td>
    <td class="r b">' . h(money2($basicWithGst)) . '</td>
</tr>





' . ($packingTotal > 0 ? '
<tr class="totline">
    <td colspan="7" class="r b">Packing Charges</td>
    <td class="r b">' . h(money2($packingTotal)) . '</td>
</tr>' : '') . '

' . ($transportTotal > 0 ? '
<tr class="totline">
    <td colspan="7" class="r b">Transport Charges</td>
    <td class="r b">' . h(money2($transportTotal)) . '</td>
</tr>' : '') . '

' . ($insuranceTotal > 0 ? '
<tr class="totline">
    <td colspan="7" class="r b">Insurance Charges</td>
    <td class="r b">' . h(money2($insuranceTotal)) . '</td>
</tr>' : '') . '

<tr class="totline">
    <td colspan="7" class="r b">Grand Total</td>
    <td class="r b">' . h(money2($grandTotal)) . '</td>
</tr>

  
     
    </tbody>
  </table>

  <div class="pad" style="border-top:1px solid #111;">
    <div class="b">Amount in Words:</div>
    <div>' . h($amountWords) . '</div>
  </div>

 ' . (!empty($termsHtml) ? '
<div class="pad terms-block" style="border-top:1px solid #111;">
  <div class="b">Terms & Conditions</div>
  <div class="term-item">' . $termsHtml . '</div>
</div>' : '') . '


</div>

<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->get_font("Helvetica", "normal");
    $size = 10;

    // X = center of A4 (595px wide)
    $x = 270;

    // Y = bottom position
    $y = 820;

    $pdf->page_text(
        $x,
        $y,
        "Page {PAGE_NUM} of {PAGE_COUNT}",
        $font,
        $size,
        array(0, 0, 0)
    );
}
</script>



</body>
</html> 
';


/* ============================================================
 * GENERATE PDF BY DOMPDF (preferred)
 * ============================================================ */
$pdfBytes = null;
$dompdfOk = false;

try {
    $autoload1 = __DIR__ . '/../includes/dompdf/vendor/autoload.php';
    $autoload2 = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($autoload1)) require_once $autoload1;
    elseif (file_exists($autoload2)) require_once $autoload2;

    if (class_exists(\Dompdf\Dompdf::class)) {
        $dompdfOk = true;
        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'enable_php' => true   // Ñ‚Ð½Ð  REQUIRED
        ]);

        $dompdf->loadHtml($pdfHtml, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfBytes = $dompdf->output();
    }
} catch (Throwable $e) {
    $dompdfOk = false;
    $pdfBytes = null;
}

/* STREAM PDF FOR PREVIEW + DOWNLOAD (correct filename) */
if (
    isset($_POST['download_pdf']) &&
    $_POST['download_pdf'] === '1' &&
    $dompdfOk &&
    $pdfBytes
) {
    if (ob_get_length()) {
        ob_clean();
    }
    flush();

    $safeCustomer = trim($customerName);
    if ($safeCustomer === '') {
        $safeCustomer = 'Quotation';
    }

    $safeCustomer = preg_replace('/[^A-Za-z0-9 \-]/', '', $safeCustomer);
    $safeCustomer = preg_replace('/\s+/', ' ', $safeCustomer);

    $fileName = $safeCustomer . '.pdf';

    header('Content-Type: application/pdf');

    // 🔥 THIS LINE DECIDES FILENAME
    if (isset($_POST['force_download'])) {
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $fileName . '"');
    }

    header('Content-Length: ' . strlen($pdfBytes));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    echo $pdfBytes;
    exit;
}



/* ============================================================
 * PREVIEW PAGE (iframe + print button)
 * ============================================================ */
ob_start();
?>
<div class="master-wrap">
    <div class="headbar">
        <div>
            <h1 class="page-title">Quotation Preview</h1>
            <div class="page-subtitle">Quotation No: <b><?php echo h($quoteNo); ?></b> &nbsp; | &nbsp; Date: <b><?php echo h($qDate); ?></b></div>
        </div>
    </div>

    <div class="card" style="margin-top:14px;">
        <div style="display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;">
            <button type="button" class="btn primary" onclick="printPreview()">Print</button>

            <?php if ($dompdfOk && $pdfBytes): ?>
                <form method="post" style="margin:0;" target="_blank">
                    <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
                    <input type="hidden" name="quotation_id" value="<?php echo (int)$quotation_id; ?>">
                    <input type="hidden" name="download_pdf" value="1">
                    <button type="submit" class="btn secondary">Open PDF</button>
                </form>

                <form method="post" style="margin:0;">
                    <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
                    <input type="hidden" name="quotation_id" value="<?php echo (int)$quotation_id; ?>">
                    <input type="hidden" name="download_pdf" value="1">
                    <input type="hidden" name="force_download" value="1">
                    <button type="submit" class="btn danger">
                        Download PDF
                    </button>
                </form>



            <?php endif; ?>

            <button type="button" class="btn secondary" onclick="window.close()">Close</button>
        </div>
    </div>


    <div class="card" style="margin-top:14px; padding:0;">
        <?php if ($dompdfOk && $pdfBytes): ?>

            <form id="pdfStreamForm" method="post" target="pdfFrame" style="display:none;">
                <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="quotation_id" value="<?php echo (int)$quotation_id; ?>">
                <input type="hidden" name="download_pdf" value="1">
            </form>

            <iframe
                name="pdfFrame"
                style="width:100%; height:82vh; border:0; border-radius:12px;">
            </iframe>

            <script>
                document.getElementById('pdfStreamForm').submit();
            </script>

        <?php else: ?>

            <!-- fallback: show HTML preview if dompdf not available -->
            <div style="padding:12px;">
                <div class="alert warn">Dompdf not found on server. Showing HTML preview. Print will use browser print.</div>
            </div>
            <div id="htmlPreview" style="padding:12px;">
                <?php echo $pdfHtml; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function printPreview() {
        // Open real PDF in new tab and print
        const form = document.createElement('form');
        form.method = 'post';
        form.target = '_blank';
        form.action = window.location.href;

        form.innerHTML = `
        <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
        <input type="hidden" name="quotation_id" value="<?php echo (int)$quotation_id; ?>">
        <input type="hidden" name="download_pdf" value="1">
    `;

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
</script>


<?php
$CONTENT = ob_get_clean();
require_once __DIR__ . '/../includes/ui_autoshell.php';
