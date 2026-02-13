<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/initialize.php';
require_once __DIR__ . '/../includes/aclhelper.php';

if (!is_logged_in()) redirect('../login.php');
date_default_timezone_set('Asia/Kolkata');

$con = $con ?? null;
if (!$con instanceof mysqli) die('DB missing');
if (session_status() === PHP_SESSION_NONE) session_start();

/* ==========================
   TABLE
========================== */
$TABLE = 'jos_ierp_complaint_quotation';
$TABLE_GRID   = 'jos_ierp_complaint_quotationgrid';
$TABLE_PRODUCTS  = 'jos_crm_mproducts';

/* ==========================
   HELPERS
========================== */
function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function dmy($d)
{
    if (!$d || $d === '0000-00-00') return '';
    return date('d-m-Y', strtotime($d));
}
function csrf()
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['_csrf'];
}
function fy_from_date($date)
{
    if (!$date) return '';
    $y = (int)date('Y', strtotime($date));
    $m = (int)date('m', strtotime($date));

    // April se FY start hota hai
    if ($m >= 4) {
        return substr($y, 2, 2) . '-' . substr($y + 1, 2, 2);
    } else {
        return substr($y - 1, 2, 2) . '-' . substr($y, 2, 2);
    }
}


/* ==========================
   ACL
========================== */
$acl = erp_get_menu_meta_and_acl($con);
$canView  = $acl['can_view'] ?? false;

if (!$canView) {
    $CONTENT = '<div class="alert danger">Access Denied</div>';
    require '../includes/ui_autoshell.php';
    exit;
}

/* ==========================
   FILTER INPUTS (POST)
========================== */
$from = '';
$to = '';
$year = '';
$qutid = '';
$complaint_id = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Reset button
    if (isset($_POST['reset'])) {
        $from = $to = $year = $qutid = $complaint_id = '';
        $perpage = '25';
    }

    // View dropdown changed
    elseif (isset($_POST['view_change'])) {
        // keep existing filters
        $from   = trim($_POST['from'] ?? '');
        $to     = trim($_POST['to'] ?? '');
        $year   = trim($_POST['year'] ?? '');
        $qutid = trim($_POST['qutid'] ?? '');
        $complaint_id = trim($_POST['complaint_id'] ?? '');
        $perpage = $_POST['perpage'] ?? '25';
    }

    // Go button clicked
    else {
        $from   = trim($_POST['from'] ?? '');
        $to     = trim($_POST['to'] ?? '');
        $year   = trim($_POST['year'] ?? '');
        $qutid = trim($_POST['qutid'] ?? '');
        $perpage = $_POST['perpage'] ?? '25';
    }
}


$perpage = $_POST['perpage'] ?? '25';

$limit = null;
$page = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['page'])) {
    $page = (int)$_POST['page'];
} elseif (isset($_GET['page'])) {
    $page = (int)$_GET['page'];
}

$page  = max(1, $page);
$offset = 0;

if ($perpage !== 'all') {
    $limit  = (int)$perpage;
    $offset = ($page - 1) * $limit;
}

/* ==========================
   QUERY BUILD
========================== */
$where = [];
$types = '';
$params = [];

/* ✅ Always filter doc = 5 */
// $where[] = 's.doc = 5';

if ($from) {
    $where[] = 's.date >= ?';
    $types .= 's';
    $params[] = date('Y-m-d', strtotime($from));
}
if ($to) {
    $where[] = 's.date <= ?';
    $types .= 's';
    $params[] = date('Y-m-d', strtotime($to));
}
if ($year) {
    [$fyStart, $fyEnd] = explode('-', $year);

    $startYear = '20' . $fyStart;
    $endYear   = '20' . $fyEnd;

    $fromFY = $startYear . '-04-01';
    $toFY   = $endYear . '-03-31';

    $where[] = 's.date BETWEEN ? AND ?';
    $types  .= 'ss';
    $params[] = $fromFY;
    $params[] = $toFY;
}
if ($qutid) {
    $where[] = 'g.qutid LIKE ?';
    $types .= 's';
    $params[] = "%$qutid%";
}
if ($complaint_id) {
    $where[] = 's.complaint_id LIKE ?';
    $types .= 's';
    $params[] = "%$complaint_id%";
}



$sql = "
SELECT 
    s.id,
    s.complaint_id,
    s.date,

    cm.name AS customer_name,

    g.qutid,
    p.name AS product_name,
    g.rate,
    g.qty,
    g.gstamt,
    g.amt

FROM $TABLE s

INNER JOIN $TABLE_GRID g 
    ON g.qutid = s.id

LEFT JOIN jos_ierp_customermaster cm
    ON cm.id = s.custid

LEFT JOIN jos_crm_mproducts p
    ON p.id = g.propid
";






if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY s.id DESC";

if ($perpage !== 'all') {
    $sql .= " LIMIT ? OFFSET ?";
    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;
}


$totalRows = 0;
$totalPages = 1;

if ($perpage !== 'all') {

    $countSql = "
SELECT COUNT(*) AS total
FROM $TABLE s
LEFT JOIN $TABLE_GRID g 
    ON g.qutid = s.id
";


    if ($where) {
        $countSql .= " WHERE " . implode(' AND ', $where);
    }

    $st = $con->prepare($countSql);

    if ($types) {
        $countParams = array_slice($params, 0, count($params) - 2);
        $countTypes  = substr($types, 0, -2);
        if ($countTypes) {
            $st->bind_param($countTypes, ...$countParams);
        }
    }

    $st->execute();
    $totalRows = (int)$st->get_result()->fetch_assoc()['total'];
    $st->close();

    $totalPages = max(1, ceil($totalRows / $limit));
}





$rows = [];
$st = $con->prepare($sql);
if ($types) $st->bind_param($types, ...$params);
$st->execute();
$rs = $st->get_result();
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$st->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['export'] ?? '') === 'excel') {

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=service_quotation_products_" . date('d-m-Y') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<tr>
            <th>Sr No</th>
            <th>Quotation No</th>
            <th>Date</th>
            <th>Complaint No</th>
            <th>Customer Name</th>
            <th>Product Name</th>
            <th>Rate</th>
            <th>Quantity</th>
            <th>GST Amt</th>
            <th>Amount</th>
          </tr>";

    foreach ($rows as $i => $r) {

        $sr = ($perpage === 'all')
            ? ($i + 1)
            : ($offset + $i + 1);

        echo "<tr>";
        echo "<td>" . $sr . "</td>";
        echo "<td>" . fy_from_date($r['date']) . "/" . h($r['qutid']) . "</td>";
        echo "<td>" . dmy($r['date']) . "</td>";
        echo "<td>" . h($r['complaint_id']) . "</td>";
        echo "<td>" . h($r['customer_name']) . "</td>";
        echo "<td>" . h($r['product_name']) . "</td>";
        echo "<td>" . h($r['rate']) . "</td>";
        echo "<td>" . h($r['qty']) . "</td>";
        echo "<td>" . h($r['gstamt']) . "</td>";
        echo "<td>" . h($r['amt']) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
    exit;
}




/* ==========================
   UI
========================== */
ob_start();
?>
<style>
    .btn i {
        pointer-events: none;
    }
</style>
<div class="master-wrap">
    <div class="headbar">
        <div>
            <h1 class="page-title">Service Quotation Product List</h1>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="card" style="margin-top:12px;">
        <form method="post">
            <input type="hidden" name="page" value="<?= (int)$page ?>">
            <input type="hidden" name="_csrf" value="<?= csrf() ?>">

            <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;align-items:end">

                <div class="field">
                    <label>From</label>
                    <input type="text" name="from" class="inp datepick" value="<?= h($from) ?>">
                </div>
                <input type="hidden" name="view_change" id="view_change" value="">

                <div class="field">
                    <label>To</label>
                    <input type="text" name="to" class="inp datepick" value="<?= h($to) ?>">
                </div>

                <div class="field">
                    <label>Year</label>
                    <select name="year" class="inp">
                        <option value="">Year</option>
                        <?php
                        $startFY = 2014; // 14-15
                        $endFY   = date('Y'); // current year

                        for ($y = $startFY; $y <= $endFY; $y++) {
                            $fy = substr($y, 2, 2) . '-' . substr($y + 1, 2, 2);
                            $sel = ($year === $fy) ? 'selected' : '';
                            echo "<option value='$fy' $sel>$fy</option>";
                        }
                        ?>
                    </select>
                </div>


                <div class="field">
                    <label>Quotation No</label>
                    <input type="text" name="qutid" class="inp" value="<?= h($qutid) ?>">
                </div>
                <div class="field">
                    <label>Complaint No</label>
                    <input type="text" name="complaint_id" class="inp" value="<?= h($complaint_id) ?>">
                </div>

                <div class="field">
                    <label>View</label>
                    <select name="perpage" class="inp" onchange="submitViewChange()">
                        <option value="25" <?= ($perpage == '25' ? 'selected' : '') ?>>Last 25 View</option>
                        <option value="50" <?= ($perpage == '50' ? 'selected' : '') ?>>Last 50 View</option>
                        <option value="all" <?= ($perpage == 'all' ? 'selected' : '') ?>>View All</option>
                    </select>
                </div>



                <div class="field" style="grid-column:5/7">
                    <label>&nbsp;</label>
                    <div style="display:flex;gap:8px;justify-content:flex-end">
                        <button class="btn success">Go</button>
                        <button class="btn secondary" type="submit" name="reset" value="1">Reset</button>
                        <button class="btn primary" type="submit" name="export" value="excel">Export Excel</button>
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
                        <th>Quotation No</th>
                        <th>Complaint No</th>
                        <th>Customer Name</th>
                        <th>Product Name</th>
                        <th>Rate</th>
                        <th>Quantity</th>
                        <th>GST Amt</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>


                <tbody>

                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="8" class="muted" style="text-align:center">No records</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($rows as $i => $r): ?>
                        <tr>
                            <td><?= $offset + $i + 1 ?></td>

                            <td>
                                <strong>
                                    <?= fy_from_date($r['date']) ?>/<?= h($r['qutid']) ?>
                                </strong>
                                <br>
                                <?= dmy($r['date']) ?>
                            </td>

                            <td><?= h($r['complaint_id']) ?></td>

                            <td><?= h($r['customer_name']) ?></td>

                            <td><?= h($r['product_name']) ?></td>

                            <td><?= h($r['rate']) ?></td>

                            <td><?= h($r['qty']) ?></td>

                            <td><?= h($r['gstamt']) ?></td>

                            <td><?= h($r['amt']) ?></td>
                            <td> <!-- PRINT (POST to separate print file; do not implement print here) -->
                                <form method="post" action="service_quotation_print.php" style="margin:0;" target="_blank">
                                    <input type="hidden" name="_csrf" value="<?php echo h(csrf()); ?>">
                                    <input type="hidden" name="quotation_id" value="<?php echo (int)$r['id']; ?>">
                                    <!-- <button type="submit" class="btn secondary">Print</button> -->
                                    <button type="submit" class="btn secondary" title="Print">
                                        <i class="fa-solid fa-print"></i>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>




                </tbody>
            </table>

            <?php if ($perpage !== 'all' && $totalPages > 1): ?>
                <div style="margin-top:15px; display:flex; justify-content:center;">
                    <ul style="list-style:none; display:flex; gap:6px; padding:0;">

                        <!-- PREV -->
                        <li>
                            <a class="btn secondary <?= ($page == 1 ? 'disabled' : '') ?>"
                                href="?page=<?= max(1, $page - 1) ?>">Prev</a>
                        </li>

                        <!-- NEXT -->
                        <li>
                            <a class="btn secondary <?= ($page == $totalPages ? 'disabled' : '') ?>"
                                href="?page=<?= min($totalPages, $page + 1) ?>">Next</a>
                        </li>

                    </ul>
                </div>
            <?php endif; ?>




        </div>
    </div>

</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<script>
    flatpickr('.datepick', {
        dateFormat: 'd-m-Y',
        allowInput: true
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        const fromInput = document.querySelector('input[name="from"]');
        const toInput = document.querySelector('input[name="to"]');

        if (!fromInput || !toInput || !window.flatpickr) return;

        // Initially disable To Date
        toInput.readOnly = true;

        // From Date Picker
        const fromPicker = flatpickr(fromInput, {
            dateFormat: "d-m-Y",
            allowInput: true,
            disableMobile: true,

            onChange: function(selectedDates) {
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

    function submitViewChange() {
        document.getElementById('view_change').value = '1';
        document.querySelector('form').submit();
    }
</script>


<?php
$CONTENT = ob_get_clean();
require '../includes/ui_autoshell.php';
