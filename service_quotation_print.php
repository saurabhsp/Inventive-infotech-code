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
if (!$con instanceof mysqli) { die('DB connection missing.'); }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

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
function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function csrf_token(){
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['_csrf'];
}
function verify_csrf(): bool {
    $t = (string)($_POST['_csrf'] ?? '');
    return $t !== '' && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $t);
}
function col_exists(mysqli $con, string $table, string $col): bool {
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
function fmt_dmy($ymd){
    $ymd = trim((string)$ymd);
    if ($ymd === '' || $ymd === '0000-00-00') return '';
    $dt = DateTime::createFromFormat('Y-m-d', substr($ymd,0,10));
    return $dt ? $dt->format('d-m-Y') : '';
}
function num($v){ $v = (float)($v ?? 0); return is_nan($v) ? 0.0 : $v; }
function money2($v){ return number_format((float)$v, 2, '.', ''); }

/* Indian number to words (simple, sufficient for invoices) */
function amount_in_words_indian($number): string {
    $number = round((float)$number, 2);
    $rupees = (int)floor($number);
    $paise  = (int)round(($number - $rupees) * 100);

    $ones = [
        '', 'One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten',
        'Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'
    ];
    $tens = ['', '', 'Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];

    $toWords2 = function($n) use ($ones, $tens) {
        $n = (int)$n;
        if ($n < 20) return $ones[$n];
        $t = (int)floor($n/10);
        $o = $n % 10;
        return trim($tens[$t].' '.$ones[$o]);
    };

    $toWords3 = function($n) use ($toWords2, $ones) {
        $n = (int)$n;
        if ($n < 100) return $toWords2($n);
        $h = (int)floor($n/100);
        $r = $n % 100;
        return trim($ones[$h].' Hundred '.($r ? $toWords2($r) : ''));
    };

    if ($rupees === 0) $words = 'Zero';
    else {
        $parts = [];

        $crore = (int)floor($rupees / 10000000);
        $rupees %= 10000000;
        if ($crore) $parts[] = $toWords3($crore).' Crore';

        $lakh = (int)floor($rupees / 100000);
        $rupees %= 100000;
        if ($lakh) $parts[] = $toWords3($lakh).' Lakh';

        $thousand = (int)floor($rupees / 1000);
        $rupees %= 1000;
        if ($thousand) $parts[] = $toWords3($thousand).' Thousand';

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


$subCompany = $hdr['subcompany'] ?? 'AKASHGANGA';
$company    = getCompanyHeader($subCompany);

// Financial Year
$fy = date('y') . '-' . (date('y') + 1);

// Company code from DB (AGCM / AMSS / etc.)
$companyCode = trim($hdr['companyname'] ?? 'AGCM');

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
foreach (['address','addr','address1'] as $cc) {
    if ($cust && isset($cust[$cc]) && trim((string)$cust[$cc]) !== '') { $custAddrLines[] = trim((string)$cust[$cc]); break; }
}
$city = ($cust && isset($cust['city']) && trim((string)$cust['city']) !== '') ? trim((string)$cust['city']) : '';
$tal  = ($cust && isset($cust['taluka']) && trim((string)$cust['taluka']) !== '') ? trim((string)$cust['taluka']) : '';
$dist = ($cust && isset($cust['district']) && trim((string)$cust['district']) !== '') ? trim((string)$cust['district']) : '';
$state= ($cust && isset($cust['state']) && trim((string)$cust['state']) !== '') ? trim((string)$cust['state']) : '';
$pin  = ($cust && isset($cust['pincode']) && trim((string)$cust['pincode']) !== '') ? trim((string)$cust['pincode']) : '';
$mob  = ($cust && isset($cust['mobile']) && trim((string)$cust['mobile']) !== '') ? trim((string)$cust['mobile']) : '';
$email= ($cust && isset($cust['email']) && trim((string)$cust['email']) !== '') ? trim((string)$cust['email']) : '';

$placeLine = trim(implode(', ', array_filter([$city ?: null, $tal ?: null, $dist ?: null, $state ?: null])));
if ($placeLine !== '') $custAddrLines[] = $placeLine;
if ($pin !== '') $custAddrLines[] = 'Pin: '.$pin;
if ($mob !== '') $custAddrLines[] = 'Mobile No : '.$mob;
if ($email !== '') $custAddrLines[] = 'E-Mail : '.$email;

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


$stg->close();

while ($rsG && ($r = $rsG->fetch_assoc())) $grid[] = $r;

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
    $sub = 0.0; $gst = 0.0;
    foreach ($grid as $r) {
        $sub += isset($r['discountedamt']) ? num($r['discountedamt']) : (isset($r['amt']) ? num($r['amt']) : 0.0);
        $gst += isset($r['gstamt']) ? num($r['gstamt']) : 0.0;
    }
    // If discountedamt already includes no gst, keep
    if ($gst > 0) { $subTotal = $sub; $totalGst = $gst; $basicWithGst = $subTotal + $totalGst; $grandTotal = $basicWithGst; }
    else { $subTotal = $sub; $basicWithGst = $subTotal; $grandTotal = $basicWithGst; }
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
    $desc = trim((string)($r['product_name'] ?? ''));  
if ($desc === '') {
    $desc = trim((string)($r['description'] ?? ''));
}
if ($desc === '') $desc = 'Item';

    // If you later want product name, you can join product master in query
   
    $qty = isset($r['qty']) ? num($r['qty']) : 0.0;
    $amt = isset($r['amt']) ? num($r['amt']) : 0.0;
    $rowsHtml .= '
      <tr>
        <td class="c" style="width:45px;">'.(int)$sr.'</td>
        <td>'.h($desc).'</td>
        <td class="r" style="width:90px;">'.h(money2($qty)).'</td>
        <td class="r" style="width:110px;">'.h(money2($amt)).'</td>
      </tr>';
    $sr++;
}

if ($rowsHtml === '') {
    $rowsHtml = '<tr><td colspan="4" class="c" style="padding:10px;">No items</td></tr>';
}

$amountWords = amount_in_words_indian($grandTotal);


$termsHtml = '';
if (!empty($terms)) {
    foreach ($terms as $t) {
        $termsHtml .= '
        <div style="margin-bottom:10px;">
          <div class="b">'.h($t['title']).'</div>
          <div style="margin-top:4px;">'.$t['description'].'</div>
        </div>';
    }
}

$customerBlock = '';
foreach ($custAddrLines as $ln) {
    $customerBlock .= h($ln)."<br>";
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
        <td colspan="3" class="r b">Packing Charges</td>
        <td class="r b">'.h(money2($packingTotal)).'</td>
    </tr>';
}

if ($transportTotal > 0) {
    $chargesHtml .= '
    <tr class="totline">
        <td colspan="3" class="r b">Transport Charges</td>
        <td class="r b">'.h(money2($transportTotal)).'</td>
    </tr>';
}

if ($insuranceTotal > 0) {
    $chargesHtml .= '
    <tr class="totline">
        <td colspan="3" class="r b">Insurance Charges</td>
        <td class="r b">'.h(money2($insuranceTotal)).'</td>
    </tr>';
}

$chargesHtml .= '
<tr class="totline">
    <td colspan="3" class="r b">Grand Total</td>
    <td class="r b">'.h(money2($grandTotal)).'</td>
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


$pdfHtml = '
<!doctype html>
<html>
 
<head>
<meta charset="utf-8">
<style>
  @page { margin: 18px; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color:#111; }
  .box { border:1px solid #111; }
  .row { width:100%; border-collapse:collapse; }
  .row td { vertical-align:top; }
  .title { text-align:center; font-weight:bold; font-size:16px; padding:8px 0; }
  .subtitle { text-align:center; font-size:11px; padding-bottom:6px; }
  .pad { padding:8px; }
  .b { font-weight:bold; }
  .small { font-size:11px; }
  .c { text-align:center; }
  .r { text-align:right; }
  .tbl { width:100%; border-collapse:collapse; }
  .tbl th, .tbl td { border:1px solid #111; padding:6px; }
  .tbl th { background:#e9e9e9; font-weight:bold; }
  .totline td { border:1px solid #111; padding:6px; }
  .muted { color:#333; }
</style>
</head>
<body>
  <div class="box">
    <div class="title">QUOTATION</div>
    <div class="subtitle">(Subject to Satara Jurisdiction)</div>

    <table class="row" style="border-top:1px solid #111;">
      <tr>
        <td style="width:50%; border-right:1px solid #111;" class="pad">
          <div class="b">To,</div>
          <div>'.$customerBlock.'</div>
        </td>
        <td style="width:50%;" class="pad">
'.($logoBase64
 ? '<img src="'.$logoBase64.'" style="height:60px;"><br><br>'
 : ''
).'



<div style="font-size:16px; font-weight:bold;">
    '.h($company['name'] ?? '').'
</div>


<div class="small">'.h($company['addr']).'</div>
<div class="small">Ph: '.h($company['phone']).'</div>
<div class="small">Email: '.h($company['email']).'</div>
<div class="small">Web: '.h($company['web']).'</div>

<div class="small b">GST NO: '.h($company['gst']).'</div>
'.($company['cin'] ? '<div class="small b">CIN: '.h($company['cin']).'</div>' : '').'

        </td>
      </tr>
    </table>

    <table class="row" style="border-top:1px solid #111;">
      <tr>
        <td class="pad" style="width:50%; border-right:1px solid #111;">
          <div><span class="b">Quotation No</span> &nbsp; '.h($quoteNo).'</div>
          <div style="margin-top:4px;"><span class="b">Quotation Date</span> : '.h($qDate).'</div>
        </td>
        <td class="pad" style="width:50%;">
          <div><span class="b">Kind Attn</span> : '.h($kindAttn).'</div>
        </td>
      </tr>
    </table>

    <div class="pad" style="border-top:1px solid #111;">
      <div class="b">Subject:</div>
      <div style="margin:4px 0 10px 0;">'.nl2br(h($subject)).'</div>

      <div><span class="b">Ref:</span> '.nl2br(h($refe)).'</div>
      <div style="margin-top:10px;">Dear sir,</div>
      <div style="margin-top:6px;">'.nl2br(h($descr)).'</div>
    </div>

    <table class="tbl" style="border-top:1px solid #111;">
      <thead>
        <tr>
          <th style="width:45px;">Sr No</th>
          <th>Description</th>
          <th style="width:90px;">Qty</th>
          <th style="width:110px;">Amount</th>
        </tr>
      </thead>
      <tbody>
        '.$rowsHtml.'
        <tr class="totline">
          <td colspan="2" class="r b">Total</td>
          <td class="r b">'.h(money2($totalQty)).'</td>
          <td class="r b">'.h(money2($totalAmtLines > 0 ? $totalAmtLines : $subTotal)).'</td>
        </tr>
        <tr class="totline">
          <td colspan="3" class="r b">Sub Total</td>
          <td class="r b">'.h(money2($subTotal)).'</td>
        </tr>
        <tr class="totline">
          <td colspan="3" class="r b">GST</td>
          <td class="r b">'.h(money2($totalGst)).'</td>
        </tr>
        <tr class="totline">
          <td colspan="3" class="r b">Basic Cost With GST</td>
          <td class="r b">'.h(money2($basicWithGst)).'</td>
        </tr>
        
       '.$chargesHtml.'

      </tbody>
    </table>

       <div class="pad" style="border-top:1px solid #111;">
      <div class="b">Amount in Words: </div>
      <div>'.h($amountWords).'</div>
    </div>

    '.(!empty($termsHtml) ? '
    <div class="pad" style="border-top:1px solid #111;">
      <div class="b">Terms & Conditions</div>
      <div style="margin-top:6px;">
        '.$termsHtml.'
      </div>
    </div>
    ' : '').'

    <div class="pad c" style="border-top:1px solid #111; font-size:11px;">
      Page 1 of 1
    </div>

    </div>
  </div>
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

/* If user directly asked to stream the pdf (optional future use) */
if (isset($_POST['download_pdf']) && $_POST['download_pdf'] === '1' && $dompdfOk && $pdfBytes) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Quotation_'.$quoteNo.'.pdf"');
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
      <?php endif; ?>

      <button type="button" class="btn secondary" onclick="window.close()">Close</button>
    </div>
  </div>

  <div class="card" style="margin-top:14px; padding:0;">
    <?php if ($dompdfOk && $pdfBytes): ?>
      <?php $b64 = base64_encode($pdfBytes); ?>
      <iframe id="pdfFrame"
              src="data:application/pdf;base64,<?php echo $b64; ?>"
              style="width:100%; height:82vh; border:0; border-radius:12px;"></iframe>
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
function printPreview(){
  const iframe = document.getElementById('pdfFrame');
  if (iframe && iframe.contentWindow) {
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
    return;
  }
  // fallback
  window.print();
}
</script>

<?php
$CONTENT = ob_get_clean();
require_once __DIR__ . '/../includes/ui_autoshell.php';
