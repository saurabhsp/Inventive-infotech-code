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
$TABLE = 'jos_ierp_deliverychallan';
$TABLE_GRID   = 'jos_ierp_deliverychallan_grid';
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
$canEdit  = $acl['can_edit'] ?? false;
$canDelete  = $acl['can_delete'] ?? false;

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
$billno = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Reset button
    if (isset($_POST['reset'])) {
        $from = $to = $year = $billno = '';
        $perpage = '25';
    }

    // View dropdown changed
    elseif (isset($_POST['view_change'])) {
        // keep existing filters
        $from   = trim($_POST['from'] ?? '');
        $to     = trim($_POST['to'] ?? '');
        $year   = trim($_POST['year'] ?? '');
        $billno = trim($_POST['billno'] ?? '');
        $perpage = $_POST['perpage'] ?? '25';
    }

    // Go button clicked
    else {
        $from   = trim($_POST['from'] ?? '');
        $to     = trim($_POST['to'] ?? '');
        $year   = trim($_POST['year'] ?? '');
        $billno = trim($_POST['billno'] ?? '');
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

/* ✅ Always filter doc */
$where[] = 's.doc = 29';

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
if ($billno) {
    $where[] = 's.billno LIKE ?';
    $types .= 's';
    $params[] = "%$billno%";
}


// $sql = "
// SELECT
//     s.id,
//     s.billno,
//     s.date,
//     s.doc,
//     s.modifydate,

//     lf.location_name AS from_location,
//     lt.location_name AS to_location,

//     u.name AS created_by_name
// FROM jos_ierp_deliverychallan s

// LEFT JOIN jos_erp_gidlocation lf ON lf.gid = s.fromlc
// LEFT JOIN jos_erp_gidlocation lt ON lt.gid = s.tolc

// LEFT JOIN jos_admin_users u ON u.id = s.created_by
// ";

$sql = "
SELECT
    s.id,
    s.billno,
    s.date,
    s.modifydate,
    s.tolc,

    lf.location_name AS from_location,
    u.name AS created_by_name,
    g.propid,
    g.qty,
    g.description,

    p.name AS product_name

FROM jos_ierp_deliverychallan s

LEFT JOIN jos_ierp_deliverychallan_grid g 
    ON g.billid = s.id

LEFT JOIN jos_crm_mproducts p 
    ON p.id = g.propid

LEFT JOIN jos_erp_gidlocation lf 
    ON lf.gid = s.fromlc

LEFT JOIN jos_erp_gidlocation lt 
    ON lt.gid = s.tolc

LEFT JOIN jos_admin_users u 
    ON u.id = s.created_by
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
    FROM jos_ierp_deliverychallan s
    LEFT JOIN jos_ierp_deliverychallan_grid g 
        ON g.billid = s.id
    LEFT JOIN jos_crm_mproducts p 
        ON p.id = g.propid
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
    header("Content-Disposition: attachment; filename=delivery_challan_products" . date('d-m-Y') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<tr>
            <th>Sr No</th>
            <th>Delivery Challan No. </th>
            <th>Date</th>
            <th>Document</th>
            <th>From</th>
            <th>To</th>
            <th>Product Name</th>
            <th>Qty</th>
            <th>User / Modify by</th>
            <th>Modify Date</th>
          </tr>";

    foreach ($rows as $i => $r) {

        $sr = ($perpage === 'all')
            ? ($i + 1)
            : ($offset + $i + 1);

        echo "<tr>
                <td>{$sr}</td>
                <td>" . fy_from_date($r['date']) . "/" . h($r['billno']) . "</td>
                <td>" . dmy($r['date']) . "</td>
                <td>Delivery Challan</td>
                <td>" . h($r['from_location']) . "</td>
                <td>" . h($r['tolc']) . "</td>
                <td>" . h($r['product_name']) . "</td>
                <td>" . h($r['qty']) . "</td>
                <td>" . h($r['created_by_name']) . "</td>
                <td>" . ($r['modifydate'] ? date('d-m-Y', strtotime($r['modifydate'])) : '') . "</td>
              </tr>";
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
            <h1 class="page-title">Delivery Challan Product List</h1>
            <!-- <div class="page-subtitle">Internal Stock Movement</div> -->
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
                    <label>Delivery Challan No. </label>
                    <input type="text" name="billno" class="inp" value="<?= h($billno) ?>">
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
                        <th>Delivery Challan No. </th>
                        <th>Document</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Product Name</th>
                        <th>Qty</th>
                        <th>User / Modify by</th>
                        <th>Actions</th>
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
                                    <?= fy_from_date($r['date']) ?>/<?= h($r['billno']) ?>
                                </strong>
                                <br>
                                <?= dmy($r['date']) ?>
                            </td>


                            <td>Delivery Challan</td>
                            <td><?= h($r['from_location']) ?></td>
                            <td><?= h($r['tolc']) ?></td>
                            <td><?= h($r['product_name']) ?></td>
                            <td><?= h($r['qty']) ?></td>
                            <td class="muted">
                                <?= h($r['created_by_name']) ?>
                                <br>
                                <?php
                                $md = $r['modifydate'] ?? '';
                                echo $md ? date('d-m-Y', strtotime($md)) : '';
                                ?>
                            <td>
                                <div style="display:flex; gap:6px;">

                                    <!-- PRINT (POST to separate print file; do not implement print here) -->
                                    <form method="post" action="/operations/deliverychallan_print.php" style="margin:0;" target="_blank">
                                        <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
                                        <input type="hidden" name="gatepass_id" value="<?php echo (int)$r['id']; ?>">
                                        <button type="submit" class="btn secondary" title="Print">
                                            <i class="fa-solid fa-print"></i>
                                        </button>

                                    </form>


                                </div>
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
