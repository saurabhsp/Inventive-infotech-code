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
$TABLE = 'jos_ierp_stkrequest';
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



/* ==========================
   FILTER INPUTS (POST)
========================== */
$from = '';
$to = '';
$company = '';
$perpage = '25';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['reset'])) {
        $from = $to = $company = '';
        $perpage = '25';
    } else {
        $from    = trim($_POST['from'] ?? '');
        $to      = trim($_POST['to'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $perpage = $_POST['perpage'] ?? '25';
    }
}







$page  = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$page  = max(1, $page);

$limit  = null;
$offset = 0;

if ($perpage !== 'all') {
    $limit  = (int)$perpage;
    $offset = ($page - 1) * $limit;
}

/* =========================
   QUERY BUILD
========================= */
$where = [];
$params = [];
$types = '';

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

$countSql = "
SELECT COUNT(*) as cnt
FROM jos_erp_sale_order so
" . ($where ? " WHERE " . implode(' AND ', $where) : "");

$countStmt = $con->prepare($countSql);
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['cnt'];

$totalPages = ($perpage !== 'all' && $limit > 0)
    ? (int)ceil($totalRows / $limit)
    : 1;

// $sql = "
// SELECT 
//     so.id,
//     so.salesdate,
//     so.customer,
//     p.name AS plant_name,
//     s.sitename,
//     au.name AS created_user,
//     so.modifydate
// FROM jos_erp_sale_order so
// LEFT JOIN jos_erp_plantname p ON p.id = so.plantname
// LEFT JOIN jos_crm_siteaddress_grid s ON s.id = so.sitename
// LEFT JOIN jos_admin_users au ON au.id = so.created_by
// ".($where ? " WHERE ".implode(' AND ', $where) : "")."
// ORDER BY so.id DESC
// ";

$sql = "
SELECT 
    so.id,
    so.salesdate,
    so.customer,

    p.name AS plant_name,
    so.plantname AS plant_no,        

    s.sitename,
    so.address AS site_address,      

    au.name AS created_user,
    so.modifydate
FROM jos_erp_sale_order so
LEFT JOIN jos_erp_plantname p ON p.id = so.plantname
LEFT JOIN jos_crm_siteaddress_grid s ON s.id = so.sitename
LEFT JOIN jos_admin_users au ON au.id = so.created_by
" . ($where ? " WHERE " . implode(' AND ', $where) : "") . "
ORDER BY so.id DESC
";


if ($perpage !== 'all') {
    $sql .= " LIMIT $limit OFFSET $offset";
}


$stmt = $con->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();





if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['export'] ?? '') === 'excel') {

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=sales_order_" . date('d-m-Y') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF"; // UTF-8 BOM

    echo "<table border='1'>";
    echo "<tr>
                <th>Sr No</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Plant</th>
                <th>Plant No</th>        
                <th>Site</th>
                <th>Address</th>      
                <th>User</th>
                <th>Modify Date</th>
        </tr>";


    $sr = ($perpage === 'all') ? 1 : ($offset + 1);

    $stmt->data_seek(0); // reset result pointer

    while ($r = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$sr}</td>
                <td>" . date('d-m-Y', strtotime($r['salesdate'])) . "</td>
                <td>" . h($r['customer']) . "</td>
                <td>" . h($r['plant_name']) . "</td>
                <td>" . h($r['plant_no']) . "</td>        
                <td>" . h($r['sitename']) . "</td>
                <td>" . h($r['site_address']) . "</td> 
                <td>" . h($r['created_user']) . "</td>
                <td>" . ($r['modifydate'] ? date('d-m-Y', strtotime($r['modifydate'])) : '') . "</td>
             </tr>";
        $sr++;
    }

    echo "</table>";
    exit;
}



/* ==========================
   DELETE HANDLER
========================== */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['mode'] ?? '') === 'delete'
) {
    $delId = (int)($_POST['del_id'] ?? 0);

    if ($delId > 0) {

        $con->begin_transaction();

        try {

            // 1️⃣ Delete products (child table)
            $st1 = $con->prepare(
                "DELETE FROM jos_erp_saleorder_grid WHERE saleid = ?"
            );
            $st1->bind_param('i', $delId);
            $st1->execute();
            $st1->close();

            // 2️⃣ Delete sales order (parent table)
            $st2 = $con->prepare(
                "DELETE FROM jos_erp_sale_order WHERE id = ? LIMIT 1"
            );
            $st2->bind_param('i', $delId);
            $st2->execute();
            $st2->close();

            $con->commit();

            $_SESSION['success_msg'] = 'Sales Order deleted successfully';

            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $con->rollback();
            die("Delete failed: " . $e->getMessage());
        }
    }
}

/* ==========================
   UI
========================== */
ob_start();
?>
<div class="master-wrap">

    <div class="headbar">
        <div>
            <h1 class="page-title">Sales Order List</h1>
            <!-- <div class="page-subtitle"></div> -->
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="card" style="margin-top:12px;">
        <form method="post">
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

                <div>
                    <label>Company</label>
                    <input type="text" name="company" class="inp" value="<?= h($company) ?>">
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
                        <th>SR</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Plant</th>
                        <th>Site</th>
                        <th>User</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>

                    <?php $i = 1;
                    while ($r = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= date('d-m-Y', strtotime($r['salesdate'])) ?></td>
                            <td><?= h($r['customer']) ?></td>
                            <td><?= h($r['plant_name']) ?></td>
                            <td><?= h($r['sitename']) ?></td>
                            <td>
                                <strong><?= h($r['created_user']) ?></strong><br>
                                <small><?= h(date('d-m-Y', strtotime($r['modifydate']))) ?></small>
                            </td>
                            <td style="text-align:center;">
                                <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
                                    <?php // if ($canEdit): 
                                    ?>
                                    <!-- EDIT (POST to operations/service_quotation.php) -->
                                    <form method="post" action="../operations/saleorderform.php" style="margin:0;">
                                        <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
                                        <input type="hidden" name="mode" value="edit">
                                        <input type="hidden" name="sale_id" value="<?php echo (int)$r['id']; ?>">
                                        <button type="submit" class="btn primary">Edit</button>
                                    </form>
                                    <?php // endif; 
                                    ?>

                                    <!-- PRINT (POST to separate print file; do not implement print here) -->
                                    <form method="post" action="/operations/salesorderprint.php" style="margin:0;" target="_blank">
                                        <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
                                        <input type="hidden" name="quotation_id" value="<?php echo (int)$r['id']; ?>">
                                        <button type="submit" class="btn secondary">Print</button>
                                    </form>

                                    <?php // if ($canDelete): 
                                    ?>
                                    <!-- DELETE (POST to self) -->
                                    <form method="post" style="margin:0;" onsubmit="return confirm('Delete this order?')">
                                        <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
                                        <input type="hidden" name="mode" value="delete">
                                        <input type="hidden" name="del_id" value="<?php echo (int)$r['id']; ?>">
                                        <button type="submit" class="btn danger">Delete</button>
                                    </form>
                                    <?php // endif; 
                                    ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($i === 1): ?>
                        <tr>
                            <td colspan="7" class="no-data">No records found</td>
                        </tr>
                    <?php endif; ?>

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
                    toInput.readOnly = false;

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
                    toInput.readOnly = true;
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
