<?php
/* ============================================================
 * reports/service_quotation_list.php
 * Service Quotation — Report/List (Core ERP)
 * ============================================================
 * REQUIREMENTS:
 * - Filters: 1) Date 2) Quote No 3) Customer Name 4) Company 5) Complaint No
 * - Actions: Edit / Delete / Print (buttons only; Print handled in separate file)
 * - NO data exposure in URL (NO ?id=, NO GET filters)
 * - Prepared statements only
 * - Delete must remove header + grid + terms (transaction)
 * - ACL + ui_autoshell (ERP Console)
 * ============================================================ */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/initialize.php';
require_once __DIR__ . '/../includes/aclhelper.php';

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
$TABLE_Q_HEADER   = 'jos_ierp_complaint_quotation';
$TABLE_Q_GRID     = 'jos_ierp_complaint_quotationgrid';
$TABLE_Q_TERMS    = 'jos_ierp_complaint_quotationterms';
$TABLE_CUSTOMERS  = 'jos_ierp_customermaster';
$TABLE_COMPLAINT  = 'jos_ierp_complaint';

/* ============================================================
 * HELPERS
 * ============================================================ */
function h($v)
{
  return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function set_flash($k, $m)
{
  $_SESSION[$k] = $m;
}
function get_flash($k)
{
  if (!empty($_SESSION[$k])) {
    $m = $_SESSION[$k];
    unset($_SESSION[$k]);
    return $m;
  }
  return '';
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

function parse_dmy_to_ymd($d)
{
  $d = trim((string)$d);
  if ($d === '') return null;
  $dt = DateTime::createFromFormat('d-m-Y', $d);
  if (!$dt) return null;
  return $dt->format('Y-m-d');
}
function fmt_dmy($ymd)
{
  $ymd = trim((string)$ymd);
  if ($ymd === '' || $ymd === '0000-00-00') return '';
  $dt = DateTime::createFromFormat('Y-m-d', substr($ymd, 0, 10));
  return $dt ? $dt->format('d-m-Y') : '';
}

/* schema helpers */
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
function table_exists(mysqli $con, string $table): bool
{
  $dbRow = $con->query("SELECT DATABASE() d");
  $db = $dbRow ? ($dbRow->fetch_assoc()['d'] ?? '') : '';
  if ($db === '') return false;
  $st = $con->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1");
  $st->bind_param('ss', $db, $table);
  $st->execute();
  $ok = (bool)$st->get_result()->fetch_row();
  $st->close();
  return $ok;
}

/* ============================================================
 * MENU META + ACL (ERP Console)
 * ============================================================ */
$aclMeta = erp_get_menu_meta_and_acl($con);

$menuMetaTitle  = $aclMeta['title']      ?? 'Service Quotation Report';
$menuMetaRemark = $aclMeta['remark']     ?? 'Filter and manage quotations';
$canView        = $aclMeta['can_view']   ?? false;
$canAdd         = $aclMeta['can_add']    ?? false;
$canEdit        = $aclMeta['can_edit']   ?? false;
$canDelete      = $aclMeta['can_delete'] ?? false;

$userObj       = current_user() ?? [];
$currentUserId = isset($userObj['id']) ? (int)$userObj['id'] : 0;

/* Access guard */
$user        = $userObj;
$pageTitle   = $menuMetaTitle;
$systemTitle = 'ERP Console';
$systemCode  = 'AGCM';
$userName    = $user['name'] ?? 'User';
$userLoginId = $user['login_id'] ?? ($user['email'] ?? ($user['mobile_no'] ?? ''));

if (!$canView) {
  ob_start(); ?>
  <div class="master-wrap">
    <div class="headbar" style="display:flex; justify-content:space-between; align-items:flex-end; gap:12px; flex-wrap:wrap;">
      <div>
        <h1 class="page-title"><?php echo h($menuMetaTitle); ?></h1>
        <div class="page-subtitle"><?php echo h($menuMetaRemark); ?></div>
      </div>

      <div style="display:flex; gap:8px; align-items:center;">
        <button type="button" class="btn secondary" id="toggleFiltersBtn" style="padding:8px 12px;">
          Hide Filters
        </button>
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

/* sanity */
if (!table_exists($con, $TABLE_Q_HEADER) || !table_exists($con, $TABLE_Q_GRID) || !table_exists($con, $TABLE_Q_TERMS)) {
  ob_start(); ?>
  <div class="master-wrap">
    <div class="headbar">
      <div>
        <h1 class="page-title"><?php echo h($menuMetaTitle); ?></h1>
        <div class="page-subtitle"><?php echo h($menuMetaRemark); ?></div>
      </div>
    </div>
    <div class="card" style="margin-top:20px;">
      <div class="alert danger">
        Required tables not found. Please verify:
        <br>- <?php echo h($TABLE_Q_HEADER); ?>
        <br>- <?php echo h($TABLE_Q_GRID); ?>
        <br>- <?php echo h($TABLE_Q_TERMS); ?>
      </div>
    </div>
  </div>
<?php
  $CONTENT = ob_get_clean();
  require_once __DIR__ . '/../includes/ui_autoshell.php';
  exit;
}

/* ============================================================
 * POST MODES (no URL params)
 * ============================================================ */
$mode = 'list';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = (string)($_POST['mode'] ?? 'list');
    if (!in_array($mode, ['list','filter','reset','delete','export'], true)) {
        $mode = 'list';
    }
}

/* ============================================================
 * DELETE (POST only)
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mode === 'delete') {
  if (!$canDelete) {
    set_flash('r_error', 'No permission to delete.');
    header('Location: service_quotation_list.php');
    exit;
  }
  if (!verify_csrf()) {
    set_flash('r_error', 'Invalid token. Please refresh and try again.');
    header('Location: service_quotation_list.php');
    exit;
  }

  $del_id = (int)($_POST['del_id'] ?? 0);
  if ($del_id <= 0) {
    set_flash('r_error', 'Invalid record.');
    header('Location: service_quotation_list.php');
    exit;
  }

  $con->begin_transaction();
  try {
    $st1 = $con->prepare("DELETE FROM {$TABLE_Q_GRID} WHERE qutid=?");
    $st1->bind_param('i', $del_id);
    $st1->execute();
    $st1->close();

    $st2 = $con->prepare("DELETE FROM {$TABLE_Q_TERMS} WHERE qutid=?");
    $st2->bind_param('i', $del_id);
    $st2->execute();
    $st2->close();

    $st3 = $con->prepare("DELETE FROM {$TABLE_Q_HEADER} WHERE id=? LIMIT 1");
    $st3->bind_param('i', $del_id);
    $st3->execute();
    $st3->close();

    $con->commit();
    set_flash('r_ok', 'Quotation deleted successfully.');
    header('Location: service_quotation_list.php');
    exit;
  } catch (Throwable $e) {
    $con->rollback();
    set_flash('r_error', 'Delete failed: ' . $e->getMessage());
    header('Location: service_quotation_list.php');
    exit;
  }
}

/* ============================================================
 * FILTER INPUTS (POST only; defaults)
 * ============================================================ */
$from_dmy   = '';
$to_dmy     = '';
$quote_no   = '';
$customer_q = '';
$company_q  = '';
$complaint_no = '';
$view       = 'last50';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($mode === 'reset') {
    // keep defaults
  } else {
    $from_dmy     = trim((string)($_POST['from_date'] ?? ''));
    $to_dmy       = trim((string)($_POST['to_date'] ?? ''));
    $quote_no     = trim((string)($_POST['quote_no'] ?? ''));
    $customer_q   = trim((string)($_POST['customer_name'] ?? ''));
    $company_q    = trim((string)($_POST['company_name'] ?? ''));
    $complaint_no = trim((string)($_POST['complaint_no'] ?? ''));
    $view         = (string)($_POST['view'] ?? 'last50');
    if (!in_array($view, ['last50', 'all'], true)) $view = 'last50';
  }
}

/* build WHERE */
$where = [];
$types = '';
$params = [];

$from_ymd = $from_dmy ? parse_dmy_to_ymd($from_dmy) : null;
$to_ymd   = $to_dmy ? parse_dmy_to_ymd($to_dmy) : null;

if ($from_ymd) {
  $where[] = "q.`date` >= ?";
  $types .= 's';
  $params[] = $from_ymd;
}
if ($to_ymd) {
  $where[] = "q.`date` <= ?";
  $types .= 's';
  $params[] = $to_ymd;
}

if ($quote_no !== '') {
  // match billno (if exists) else id; also allow partial
  if (col_exists($con, $TABLE_Q_HEADER, 'billno')) {
    $where[] = "CAST(q.`billno` AS CHAR) LIKE ?";
    $types .= 's';
    $params[] = '%' . $quote_no . '%';
  } else {
    $where[] = "CAST(q.`id` AS CHAR) LIKE ?";
    $types .= 's';
    $params[] = '%' . $quote_no . '%';
  }
}

if ($customer_q !== '') {
  // prefer stored q.customer (already saved in header); fallback join customer master if needed
  if (col_exists($con, $TABLE_Q_HEADER, 'customer')) {
    $where[] = "q.`customer` LIKE ?";
    $types .= 's';
    $params[] = '%' . $customer_q . '%';
  } else {
    $where[] = "c.`name` LIKE ?";
    $types .= 's';
    $params[] = '%' . $customer_q . '%';
  }
}

if ($company_q !== '') {
  if (col_exists($con, $TABLE_Q_HEADER, 'companyname')) {
    $where[] = "q.`companyname` LIKE ?";
    $types .= 's';
    $params[] = '%' . $company_q . '%';
  }
}

if ($complaint_no !== '') {
  // primary: header complaint_id
  if (col_exists($con, $TABLE_Q_HEADER, 'complaint_id')) {
    $where[] = "CAST(q.`complaint_id` AS CHAR) LIKE ?";
    $types .= 's';
    $params[] = '%' . $complaint_no . '%';
  }
}

/* SELECT columns */
$selectQuoteNo = col_exists($con, $TABLE_Q_HEADER, 'billno') ? "q.`billno` AS quote_no" : "q.`id` AS quote_no";
$hasTotal      = col_exists($con, $TABLE_Q_HEADER, 'total') ? "q.`total` AS grand_total" : "0 AS grand_total";

/* join customer master only if helpful */
$joinCustomer = table_exists($con, $TABLE_CUSTOMERS) && col_exists($con, $TABLE_Q_HEADER, 'custid') && col_exists($con, $TABLE_CUSTOMERS, 'id');

$sql = "
SELECT
    q.`id`,
    {$selectQuoteNo},
    q.`date`,
    " . (col_exists($con, $TABLE_Q_HEADER, 'customer') ? "q.`customer` AS customer_name" : ($joinCustomer ? "c.`name` AS customer_name" : "'' AS customer_name")) . ",
    " . (col_exists($con, $TABLE_Q_HEADER, 'companyname') ? "q.`companyname` AS company_name" : "'' AS company_name") . ",
    " . (col_exists($con, $TABLE_Q_HEADER, 'complaint_id') ? "q.`complaint_id` AS complaint_id" : "0 AS complaint_id") . ",
    {$hasTotal}
FROM {$TABLE_Q_HEADER} q
" . ($joinCustomer ? "LEFT JOIN {$TABLE_CUSTOMERS} c ON c.`id` = q.`custid`" : "") . "
" . (!empty($where) ? ("WHERE " . implode(" AND ", $where)) : "") . "
ORDER BY q.`id` DESC
";

if ($view === 'last50') {
  $sql .= " LIMIT 50";
}

$list = [];
try {
  $st = $con->prepare($sql);
  if (!$st) throw new Exception($con->error);
  if ($types !== '') {
    $st->bind_param($types, ...$params);
  }
  $st->execute();
  $rs = $st->get_result();
  while ($rs && ($r = $rs->fetch_assoc())) $list[] = $r;
  $st->close();
} catch (Throwable $e) {
  set_flash('r_error', 'List load failed: ' . $e->getMessage());
}

$okMsg  = get_flash('r_ok');
$errMsg = get_flash('r_error');


/* ============================================================
 * EXCEL EXPORT (POST only)
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mode === 'export') {

    if (!verify_csrf()) {
        die('Invalid token.');
    }

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=service_quotation_report_" . date('d-m-Y') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF"; // UTF-8 BOM

    echo "<table border='1'>";
    echo "<tr>
            <th>Sr No</th>
            <th>Quote No</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Company</th>
            <th>Complaint No</th>
            <th>Grand Total</th>
          </tr>";

    $sr = 1;
    foreach ($list as $r) {

        echo "<tr>
                <td>{$sr}</td>
                <td>".h($r['quote_no'] ?? '')."</td>
                <td>".h(fmt_dmy($r['date'] ?? ''))."</td>
                <td>".h($r['customer_name'] ?? '')."</td>
                <td>".h($r['company_name'] ?? '')."</td>
                <td>".h(($r['complaint_id'] ?? 0) ?: '')."</td>
                <td>".h(number_format((float)($r['grand_total'] ?? 0), 2))."</td>
              </tr>";

        $sr++;
    }

    echo "</table>";
    exit;
}

/* ============================================================
 * UI
 * ============================================================ */
ob_start();
?>
<div class="master-wrap">
  <div class="headbar">
    <div>
      <h1 class="page-title"><?php echo h($menuMetaTitle); ?></h1>
      <div class="page-subtitle"><?php echo h($menuMetaRemark); ?></div>
    </div>
  </div>

  <?php if ($errMsg): ?><div class="alert danger"><?php echo h($errMsg); ?></div><?php endif; ?>
  <?php if ($okMsg): ?><div class="alert success"><?php echo h($okMsg); ?></div><?php endif; ?>

  <!-- Filters (POST only) -->
  <div class="card" style="margin-top:14px; padding:14px;" id="filtersCard">
    <form method="post" autocomplete="off" id="filtersForm">
      <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
      <input type="hidden" name="mode" value="filter">

      <div style="display:grid; grid-template-columns:repeat(6, minmax(0, 1fr)); gap:10px; align-items:end;">
        <div class="field">
          <label>From</label>
          <input type="text" name="from_date" class="inp datepick" placeholder="DD-MM-YYYY" value="<?php echo h($from_dmy); ?>">
        </div>

        <div class="field">
          <label>To</label>
          <input type="text" name="to_date" class="inp datepick" placeholder="DD-MM-YYYY" value="<?php echo h($to_dmy); ?>">
        </div>

        <div class="field">
          <label>Quote No</label>
          <input type="text" name="quote_no" class="inp" placeholder="Quote..." value="<?php echo h($quote_no); ?>">
        </div>

        <div class="field">
          <label>Customer</label>
          <input type="text" name="customer_name" class="inp" placeholder="Customer..." value="<?php echo h($customer_q); ?>">
        </div>

        <div class="field">
          <label>Company</label>
          <input type="text" name="company_name" class="inp" placeholder="Company..." value="<?php echo h($company_q); ?>">
        </div>

        <div class="field">
          <label>Complaint No</label>
          <input type="text" name="complaint_no" class="inp" placeholder="Complaint..." value="<?php echo h($complaint_no); ?>">
        </div>

        <div class="field" style="grid-column:1 / span 2;">
          <label>View</label>
          <select name="view" class="inp">
            <option value="last50" <?php echo ($view === 'last50') ? 'selected' : ''; ?>>Last 50</option>
            <option value="all" <?php echo ($view === 'all') ? 'selected' : ''; ?>>View All</option>
          </select>
        </div>

        <!-- Buttons -->
        <div class="field" style="grid-column:3 / span 4;">
          <label>&nbsp;</label>
          <div style="display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap;">
            <button type="submit" class="btn success">Search</button>

            <button type="submit" class="btn secondary" name="mode" value="reset">
              Reset
            </button>

            <button type="submit" class="btn primary" name="mode" value="export">
              Export Excel
            </button>
          </div>

        </div>
      </div>
    </form>
  </div>


  <!-- List -->
  <div class="card" style="margin-top:14px;">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
      <div><strong>Total:</strong> <?php echo (int)count($list); ?></div>
      <div class="muted" style="font-size:12px;">
        Edit/Delete/Print are POST-only actions (no URL ids).
      </div>
    </div>

    <div class="table-wrap" style="margin-top:10px;">
      <table class="table" style="width:100%;">
        <thead style="position:sticky; top:0;">
          <tr>
            <th style="width:60px;">SR</th>
            <th style="width:120px;">Quote No</th>
            <th style="width:120px;">Date</th>
            <th>Customer</th>
            <th style="min-width:180px;">Company</th>
            <th style="width:140px;">Complaint No</th>
            <th style="width:140px; text-align:right;">Grand Total</th>
            <th style="width:260px; text-align:center;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($list)): ?>
            <tr>
              <td colspan="8" class="muted" style="padding:14px; text-align:center;">No records found.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($list as $i => $r): ?>
              <tr>
                <td class="muted"><?php echo (int)($i + 1); ?></td>
                <td><strong><?php echo h($r['quote_no'] ?? ''); ?></strong></td>
                <td><?php echo h(fmt_dmy($r['date'] ?? '')); ?></td>
                <td><?php echo h($r['customer_name'] ?? ''); ?></td>
                <td><?php echo h($r['company_name'] ?? ''); ?></td>
                <td><?php echo h(($r['complaint_id'] ?? 0) ?: ''); ?></td>
                <td style="text-align:right;"><strong><?php echo h(number_format((float)($r['grand_total'] ?? 0), 2)); ?></strong></td>
                <td style="text-align:center;">
                  <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
                    <?php if ($canEdit): ?>
                      <!-- EDIT (POST to operations/service_quotation.php) -->
                      <form method="post" action="../operations/service_quotation.php" style="margin:0;">
                        <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
                        <input type="hidden" name="mode" value="edit">
                        <input type="hidden" name="edit_id" value="<?php echo (int)$r['id']; ?>">
                        <button type="submit" class="btn primary">Edit</button>
                      </form>
                    <?php endif; ?>

                    <!-- PRINT (POST to separate print file; do not implement print here) -->
                    <form method="post" action="service_quotation_print.php" style="margin:0;" target="_blank">
                      <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
                      <input type="hidden" name="quotation_id" value="<?php echo (int)$r['id']; ?>">
                      <button type="submit" class="btn secondary">Print</button>
                    </form>

                    <?php if ($canDelete): ?>
                      <!-- DELETE (POST to self) -->
                      <form method="post" style="margin:0;" onsubmit="return confirm('Delete this quotation? This will delete header + grid + terms.');">
                        <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
                        <input type="hidden" name="mode" value="delete">
                        <input type="hidden" name="del_id" value="<?php echo (int)$r['id']; ?>">

                        <!-- keep current filters in POST -->
                        <input type="hidden" name="from_date" value="<?php echo h($from_dmy); ?>">
                        <input type="hidden" name="to_date" value="<?php echo h($to_dmy); ?>">
                        <input type="hidden" name="quote_no" value="<?php echo h($quote_no); ?>">
                        <input type="hidden" name="customer_name" value="<?php echo h($customer_q); ?>">
                        <input type="hidden" name="company_name" value="<?php echo h($company_q); ?>">
                        <input type="hidden" name="complaint_no" value="<?php echo h($complaint_no); ?>">
                        <input type="hidden" name="view" value="<?php echo h($view); ?>">

                        <button type="submit" class="btn danger">Delete</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Flatpickr (optional; safe if already used in shell elsewhere) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (window.flatpickr) {
      flatpickr('.datepick', {
        dateFormat: 'd-m-Y',
        allowInput: true,
        disableMobile: true
      });
    }
  });
</script>


<script>
  (function() {
    const btn = document.getElementById('toggleFiltersBtn');
    const card = document.getElementById('filtersCard');
    if (!btn || !card) return;

    const KEY = 'sq_filters_hidden';

    function setHidden(hidden) {
      card.style.display = hidden ? 'none' : '';
      btn.textContent = hidden ? 'Show Filters' : 'Hide Filters';
      try {
        localStorage.setItem(KEY, hidden ? '1' : '0');
      } catch (e) {}
    }

    // restore
    let hidden = false;
    try {
      hidden = (localStorage.getItem(KEY) === '1');
    } catch (e) {}
    setHidden(hidden);

    btn.addEventListener('click', function() {
      hidden = (card.style.display !== 'none');
      setHidden(hidden);
    });
  })();
</script>


<?php
$CONTENT = ob_get_clean();
require_once __DIR__ . '/../includes/ui_autoshell.php';
