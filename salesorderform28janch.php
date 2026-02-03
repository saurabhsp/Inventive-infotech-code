<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/initialize.php';
require_once __DIR__ . '/../includes/aclhelper.php';

if (!function_exists('is_logged_in') || !is_logged_in()) {
    redirect('../login.php');
}

date_default_timezone_set('Asia/Kolkata');

$user = current_user();
$uid  = (int)($user['id'] ?? 0);


/* =========================
   CURRENT FINANCIAL YEAR
========================= */
$month = (int)date('n'); // 1–12
$year  = (int)date('Y');

/*
 Jan–Mar 2026  → FY 25-26 → year = 2025
 Apr–Dec 2026  → FY 26-27 → year = 2026
*/
$fyStartYear = ($month >= 4) ? $year : ($year - 1);

$stFy = $con->prepare("
    SELECT id, code
    FROM jos_ierp_mfinancialyear
    WHERE year = ?
    LIMIT 1
");
$stFy->bind_param('i', $fyStartYear);
$stFy->execute();
$fyRow = $stFy->get_result()->fetch_assoc();
$stFy->close();

if (!$fyRow) {
    die('Financial year not configured for year: ' . $fyStartYear);
}

$yrid   = (int)$fyRow['id'];     // ✅ correct yrid
$fyCode = $fyRow['code'];        // ✅ 25-26 / 26-27


/* =============================
   FETCH GID (same as complaint)
============================= */
$gid = get_admin_gid($con, $uid);

/* ==========================
   EDIT MODE (POST)
========================== */
$isEdit = false;
$editId = 0;
$editData = [];
$editProducts = [];

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['mode'] ?? '') === 'edit'
    && !empty($_POST['sale_id'])
) {
    $isEdit = true;
    $editId = (int)$_POST['sale_id'];

    // MAIN ORDER
    $st = $con->prepare(
        "SELECT * FROM jos_erp_sale_order WHERE id = ? LIMIT 1"
    );
    $st->bind_param('i', $editId);
    $st->execute();
    $editData = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$editData) {
        die('Invalid Sales Order');
    }

    // GRID PRODUCTS
    $st = $con->prepare(
        // "SELECT * FROM jos_erp_saleorder_grid WHERE saleid = ?"
        "SELECT g.*, m.name
FROM jos_erp_saleorder_grid g
LEFT JOIN jos_crm_mproducts m ON m.id = g.propid
WHERE g.saleid = ?"
    );
    $st->bind_param('i', $editId);
    $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        $editProducts[] = $r;
    }
    $st->close();
}



/* =========================
   SALES NO (UI)
========================= */
$uiSaleNo = '';

if ($isEdit && !empty($editData['saleno'])) {
    $uiSaleNo = $editData['saleno'];
} else {
    $rs = $con->query("
        SELECT saleno
        FROM jos_erp_sale_order
        WHERE saleno > 0
        ORDER BY id DESC
        LIMIT 1
    ");
    $row = $rs->fetch_assoc();
    $uiSaleNo = (int)($row['saleno'] ?? 0) + 1;
}

//HELPERS

/**
 * Get GID (location / group id) of logged-in admin user
 */
function get_admin_gid(mysqli $con, int $admin_id): int
{
    $sql = "SELECT COALESCE(gid,0) AS gid
            FROM jos_admin_users
            WHERE id = ?
            LIMIT 1";
    $st = $con->prepare($sql);
    $st->bind_param("i", $admin_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (int)($row['gid'] ?? 0);
}

/* =========================================================
   AJAX HANDLERS
========================================================= */
if (isset($_GET['ajax'])) {

    /* CUSTOMER AUTOCOMPLETE */
    if ($_GET['ajax'] === 'cust_autocomplete') {
        $term = '%' . ($_GET['term'] ?? '') . '%';
        $out = [];

        $st = $con->prepare(
            "SELECT id, name, address
             FROM jos_ierp_customermaster
             WHERE name LIKE ?
             ORDER BY name ASC
             LIMIT 20"
        );
        $st->bind_param('s', $term);
        $st->execute();
        $rs = $st->get_result();

        while ($r = $rs->fetch_assoc()) {
            $out[] = [
                'id'      => $r['id'],
                'label'   => $r['name'],
                'value'   => $r['name'],
                'address' => $r['address'] ?? ''
            ];
        }
        echo json_encode($out);
        exit;
    }

    /* SITE LIST */
    if ($_GET['ajax'] === 'sites') {
        $cid = (int)$_GET['customer_id'];
        $out = [];

        $st = $con->prepare(
            "SELECT id, sitename
             FROM jos_crm_siteaddress_grid
             WHERE cid = ?
             ORDER BY sitename ASC"
        );
        $st->bind_param('i', $cid);
        $st->execute();
        $rs = $st->get_result();

        while ($r = $rs->fetch_assoc()) {
            $out[] = ['id' => $r['id'], 'name' => $r['sitename']];
        }
        echo json_encode($out);
        exit;
    }



    /* PRODUCT AUTOCOMPLETE */
    if ($_GET['ajax'] === 'product_autocomplete') {
        $term = '%' . ($_GET['term'] ?? '') . '%';
        $out = [];

        $st = $con->prepare(
            "SELECT id, name, modelcode
             FROM jos_crm_mproducts
             WHERE name LIKE ?
             ORDER BY name ASC
             LIMIT 20"
        );
        $st->bind_param('s', $term);
        $st->execute();
        $rs = $st->get_result();

        while ($r = $rs->fetch_assoc()) {
            $out[] = [
                'id'    => $r['id'],
                'label' => $r['name'] . ' ' . $r['modelcode'],
                'value' => $r['name'] . ' ' . $r['modelcode']
            ];
        }
        echo json_encode($out);
        exit;
    }

    if ($_GET['ajax'] === 'plant_autocomplete') {
        $term = '%' . ($_GET['term'] ?? '') . '%';
        $out = [];

        $st = $con->prepare(
            "SELECT id, CONCAT(name,' ',modelcode) AS name
     FROM jos_crm_mproducts
     WHERE name LIKE ?
     ORDER BY name ASC
     LIMIT 20"
        );
        $st->bind_param('s', $term);
        $st->execute();
        $rs = $st->get_result();

        while ($r = $rs->fetch_assoc()) {
            $out[] = [
                'id'    => $r['id'],
                'label' => $r['name'],
                'value' => $r['name']
            ];
        }
        echo json_encode($out);
        exit;
    }

    if ($_GET['ajax'] === 'get_plant_no') {
        $out = [];

        $rs = $con->query("SELECT id, name FROM jos_erp_plantname ORDER BY id");
        while ($r = $rs->fetch_assoc()) {
            $out[] = [
                'id'   => $r['id'],
                'name' => $r['name']
            ];
        }

        echo json_encode($out);
        exit;
    }
}






/* =========================================================
   SAVE SALES ORDER
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sale_form'])) {

    $isUpdate = !empty($_POST['edit_id']);
    $saleid   = $isUpdate ? (int)$_POST['edit_id'] : 0;


    $dt = DateTime::createFromFormat('d-m-Y', $_POST['salesdate']);
    $salesdate = $dt ? $dt->format('Y-m-d') : null;

    $custid   = (int)$_POST['custid'];
    $customer = trim($_POST['customer']);
    $address  = trim($_POST['address']);
    $sitename = (int)$_POST['sitename'];
    $plantno = trim($_POST['plantno']);   // PLANT I / PLANT II
    $productIdMain   = (int)($_POST['productid_main'] ?? 0);
    $productNameMain = trim($_POST['product_main'] ?? '');
    $prod = $_POST['productid'] ?? [];
    $desc = $_POST['description'] ?? [];
    $qty  = $_POST['qty'] ?? [];

    if (!$custid || !$salesdate || empty($prod)) {
        echo "<script>alert('Please add at least one product');history.back();</script>";
        exit;
    }

    // // ================= FINANCIAL YEAR ID (yrid) =================
    // $todayMonth = (int)date('n'); // 1–12
    // $todayYear  = (int)date('Y');

    // if ($todayMonth >= 4) {
    //     // April to Dec → current-next
    //     $fyCode = substr($todayYear, 2, 2) . '-' . substr($todayYear + 1, 2, 2);
    // } else {
    //     // Jan to March → previous-current
    //     $fyCode = substr($todayYear - 1, 2, 2) . '-' . substr($todayYear, 2, 2);
    // }

    // $stFy = $con->prepare(
    //     "SELECT id FROM jos_ierp_mfinancialyear WHERE code = ? LIMIT 1"
    // );
    // $stFy->bind_param('s', $fyCode);
    // $stFy->execute();
    // $rsFy = $stFy->get_result();
    // $rowFy = $rsFy->fetch_assoc();

    // $yrid = (int)($rowFy['id'] ?? 0);
    // $stFy->close();

    // ============================================================

    // AUTO SALE NO
    $saleno = 0;
    if (!$isUpdate) {
        $rs = $con->query("
        SELECT saleno
        FROM jos_erp_sale_order
        WHERE saleno > 0
        ORDER BY id DESC
        LIMIT 1
    ");
        $row = $rs->fetch_assoc();
        $saleno = (int)($row['saleno'] ?? 0) + 1;
    }

    $con->begin_transaction();
    try {

        $modifydate = date('Y-m-d H:i:s');

        /* =========================
       MAIN ORDER
    ========================= */

        if ($isUpdate) {
            

            // 🔹 UPDATE HEADER
            $st = $con->prepare("
            UPDATE jos_erp_sale_order SET
                salesdate   = ?,
                custid      = ?,
                customer    = ?,
                address     = ?,
                sitename    = ?,
                plantname   = ?,
                productid   = ?,
                product     = ?,
                modified_by = ?,
                modifydate  = ?
            WHERE id = ?
            LIMIT 1
        ");

            $st->bind_param(
                'sissisissii',
                $salesdate,
                $custid,
                $customer,
                $address,
                $sitename,
                $plantno,
                $productIdMain,
                $productNameMain,
                $uid,
                $modifydate,
                $saleid
            );

            $st->execute();
            $st->close();
        } else {

            // 🔹 INSERT HEADER
            $st = $con->prepare("
            INSERT INTO jos_erp_sale_order
            (
                salesdate, saleno, custid, customer, address,
                sitename, plantname, productid, product,
                created_by, modified_by, modifydate, yrid, gid
            )
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

            $st->bind_param(
                'siissiiisiisii',
                $salesdate,
                $saleno,
                $custid,
                $customer,
                $address,
                $sitename,
                $plantno,
                $productIdMain,
                $productNameMain,
                $uid,
                $uid,
                $modifydate,
                $yrid,
                $gid
            );

            $st->execute();
            $saleid = $con->insert_id; // ✅ ONLY HERE
            $st->close();
        }

        /* =========================
       GRID SECTION (IMPORTANT)
    ========================= */

        if ($isUpdate) {
            // delete old grid
            $std = $con->prepare(
                "DELETE FROM jos_erp_saleorder_grid WHERE saleid = ?"
            );
            $std->bind_param('i', $saleid);
            $std->execute();
            $std->close();
        }

        // 🔹 INSERT grid rows (COMMON)
        $stg = $con->prepare("
                INSERT INTO jos_erp_saleorder_grid
                (saleid, salesdate, propid, description, qty, userid)
                VALUES (?,?,?,?,?,?)
            ");
            
            foreach ($prod as $i => $pid) {
                if (!$pid || empty($qty[$i])) continue;
            
                $stg->bind_param(
                    'isisii',
                    $saleid,          // i
                    $salesdate,       // s
                    $pid,             // i
                    $desc[$i],        // s
                    $qty[$i],         // i
                    $uid              // i
                );
                $stg->execute();
            }
            
            $stg->close();




        //     // 🔹 Insert grid rows (common for insert + update)
        //     $stg = $con->prepare("
        //     INSERT INTO jos_erp_saleorder_grid
        //     (saleid, propid, description, qty, userid)
        //     VALUES (?,?,?,?,?)
        // ");
        // foreach ($prod as $i => $pid) {
        //     if (!$pid || !$qty[$i]) continue;

        //     $stg->bind_param(
        //         'iisii',
        //         $saleid,
        //         $pid,
        //         $desc[$i],
        //         $qty[$i],
        //         $uid
        //     );
        //     $stg->execute();
        // }
        // $stg->close();

        /* ========================= */

        $con->commit();

        $_SESSION['success_msg'] = $isUpdate
            ? 'Sales Order updated successfully'
            : 'Sales Order saved successfully';

        header('Location: saleorderform.php');
        exit;
    } catch (Exception $e) {
        $con->rollback();
        die($e->getMessage());
    }
}

/* =========================================================
   UI
========================================================= */
ob_start();
?>





<div class="master-wrap">
    <div class="headbar">
        <h1 class="page-title">Sales Order</h1>
    </div>

    <div class="card" style="padding:24px;border-radius:16px">
        <?php if (!empty($_SESSION['success_msg'])): ?>
            <div style="
        background:#dcfce7;
        color:#166534;
        padding:12px 16px;
        border-radius:8px;
        margin-bottom:16px;
        font-weight:600;
    ">
                <?= $_SESSION['success_msg']; ?>
            </div>
        <?php unset($_SESSION['success_msg']);
        endif; ?>

        <form method="post">
            <?php if ($isEdit): ?>
                <input type="hidden" name="edit_id" value="<?= $editId ?>">
            <?php endif; ?>
            <!-- Grid -->
            <?php if ($isEdit && !empty($editProducts)): ?>
                <script>
                    const existingProducts = <?= json_encode($editProducts) ?>;
                </script>
            <?php endif; ?>

            <input type="hidden" name="sale_form" value="1">
            <input type="hidden" id="custid" name="custid" value="<?= $isEdit ? htmlspecialchars($editData['custid']) : '' ?>">
            <input type="hidden" name="productid_main" id="productid_main" value="<?= $isEdit ? htmlspecialchars($editData['productid']) : '' ?>">
            <input type="hidden" name="product_main" id="product_main" value="<?= $isEdit ? htmlspecialchars($editData['product']) : '' ?>">


            <div class="form-grid">

                <div class="field">
                    <label>Sales Date</label>
                    <input type="text" name="salesdate"
                        class="inp datepick"
                        value="<?= $isEdit ? date('d-m-Y', strtotime($editData['salesdate'])) : date('d-m-Y') ?>">
                </div>

                <div class="field">
    <label>Sales No</label>
    <input type="text"
           class="inp"
           value="<?= htmlspecialchars($uiSaleNo) ?>"
           readonly>
</div>

<div class="field">
    <label>Financial Year</label>
    <input type="text"
           class="inp"
           value="<?= htmlspecialchars($fyCode) ?>"
           readonly>
</div>


                <div class="field">
                    <label>Customer Name</label>
                    <input type="text" id="customer_name" name="customer" class="inp" value="<?= $isEdit ? htmlspecialchars($editData['customer']) : '' ?>" autocomplete="off">
                </div>
                <div class="field">
                    <label>Address</label>
                    <textarea id="address_display"
                        name="address"
                        class="inp"
                        readonly><?= $isEdit ? htmlspecialchars($editData['address']) : '' ?></textarea>

                </div>

                <div class="field">
                    <label>Sitename</label>
                    <select id="sitename" name="sitename" class="inp"></select>
                </div>

                <div class="field">
                    <label>Plant Name</label>
                    <input type="text" id="plantname_text" class="inp" value="<?= $isEdit ? htmlspecialchars($editData['product']) : '' ?>" autocomplete="off">
                    <input type="hidden" id="plantname" name="plantname">
                </div>

                <div class="field">
                    <label>Plant No</label>
                    <select id="plantno" name="plantno" class="inp">
                        <option value="">Select Plant No</option>
                    </select>
                </div>

            </div>




            <div class="section-header" style="justify-content:space-between">
                <div style="display:flex;align-items:center;gap:12px">
                    <button type="button" class="btn-add" onclick="openProductModal()">+ Add Product</button>
                    <span class="section-title">Products</span>
                </div>

                <div style="font-size:13px;color:#374151">
                    Click + to add, edit or delete product rows.
                </div>
            </div>



            <div id="product-grid" class="product-grid">
                <div id="product-empty" class="empty-row">No products added yet.</div>
            </div>


            <div style="text-align:center;margin-top:20px">
                <button type="submit" class="btn success">Submit</button>
            </div>

        </form>
    </div>
</div>

<!-- PRODUCT MODAL -->
<div id="product-modal" class="nature-modal hidden">
    <div class="nature-modal-backdrop" onclick="closeProductModal()"></div>
    <div class="nature-modal-dialog">
        <h3>Add Product</h3>

        <input type="hidden" id="pm-product-id">

        <div class="field">
            <label>Product</label>
            <input type="text" id="pm-product" class="inp">
        </div>

        <div class="field">
            <label>Description</label>
            <textarea id="pm-desc" class="inp"></textarea>
        </div>

        <div class="field">
            <label>Qty</label>
            <input type="number" id="pm-qty" class="inp">
        </div>

        <div style="text-align:right">
            <button type="button" class="btn secondary" onclick="closeProductModal()">Cancel</button>
            <button type="button" class="btn success" onclick="saveProduct()">Save</button>
        </div>
    </div>
</div>

<!-- REQUIRED JS -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
    .nature-modal.hidden {
        display: none
    }

    .nature-modal {
        position: fixed;
        inset: 0;
        z-index: 9999
    }

    .nature-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, .4)
    }

    .nature-modal-dialog {
        background: #fff;
        border-radius: 14px;
        padding: 18px;
        width: 420px;
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
    }

    .blue-panel {
        background: #e5f1ff;
        padding: 10px;
        border-radius: 10px;
        margin-top: 16px;
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .nature-row {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 10px;
        margin-top: 8px;
        display: flex;
        justify-content: space-between;
        background: #fafafa;
    }

    .ui-autocomplete {
        z-index: 100000 !important;
        max-height: 220px;
        overflow-y: auto;
    }

    .section-header {
        background: #eaf4ff;
        padding: 10px 14px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 18px;
    }

    .btn-add {
        background: #2563eb;
        color: #fff;
        border: none;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 14px;
        cursor: pointer;
    }

    .section-title {
        font-weight: 600;
    }

    .product-grid {
        margin-top: 12px;
    }

    .empty-row {
        color: #6b7280;
        font-size: 14px;
        padding: 12px;
    }

    .product-row {
        background: #fff;
        border-radius: 12px;
        padding: 14px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
        margin-bottom: 10px;
    }

    .product-name {
        font-weight: 600;
        margin-bottom: 4px;
    }

    .product-desc {
        font-size: 13px;
        color: #374151;
        margin-bottom: 4px;
    }

    .product-qty {
        display: inline-block;
        background: #eef2ff;
        color: #1e40af;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 12px;
    }

    .btn-delete {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        background: #ef4444;
        color: #fff;
        font-size: 18px;
        cursor: pointer;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px 24px;
    }

    @media(max-width:900px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    let editingRow = null;
    flatpickr('.datepick', {
        dateFormat: 'd-m-Y',
        allowInput: true
    });


    $("#customer_name").autocomplete({
        minLength: 2,

        source: function(request, response) {
            $.getJSON("saleorderform.php", {
                ajax: "cust_autocomplete",
                term: request.term
            }, response);
        },

        focus: function(event, ui) {
            $("#customer_name").val(ui.item.label);
            return false;
        },

        select: function(event, ui) {
            $("#customer_name").val(ui.item.label);
            $("#custid").val(ui.item.id);

            $("#address_display").val(ui.item.address);
            $("#sitename").html('<option value="">Select Site</option>');
            $("#plantname").val("");
            $("#plantname_text").val("");
            $("#plantno").val("");


            loadSites(ui.item.id);
            return false;
        }
    });
    $("#customer_name").on("blur", function() {
        if (!$("#custid").val()) {
            $(this).val("");
            $("#address_display").val("");
            $("#sitename").html('<option value="">Select Site</option>');
            $("#plantname").val("");
            $("#plantname_text").val("");
            $("#plantno").val("");

        }
    });


    function loadSites(cid) {
        $.getJSON("saleorderform.php", {
            ajax: "sites",
            customer_id: cid
        }, d => {
            let h = '<option value="">Select Site</option>';
            d.forEach(x => h += `<option value="${x.id}">${x.name}</option>`);
            $("#sitename").html(h);
        });
    }


    $("#sitename").on("change", function() {
        $("#plantname_text").val("");
        $("#plantname").val("");
        $("#plantno").val("");
    });

    function openProductModal() {
        $("#pm-product,#pm-desc,#pm-qty,#pm-product-id").val('');
        $("#product-modal").removeClass('hidden');
    }

    function closeProductModal() {
        $("#product-modal").addClass('hidden');
    }

    $("#pm-product").autocomplete({
        minLength: 2,
        appendTo: "#product-modal .nature-modal-dialog",

        source: function(request, response) {
            $.getJSON("saleorderform.php", {
                ajax: "product_autocomplete",
                term: request.term
            }, response);
        },

        select: function(event, ui) {
            $("#pm-product").val(ui.item.label);
            $("#pm-product-id").val(ui.item.id);
            return false; // ✅ nothing else
        }
    });


    $(function() {

        $("#plantname_text").autocomplete({
            minLength: 1,

            source: function(req, res) {
                $.getJSON("saleorderform.php", {
                    ajax: "plant_autocomplete",
                    term: req.term
                }, res);
            },

            select: function(e, ui) {
                $("#plantname_text").val(ui.item.label);

                // main product fields
                $("#productid_main").val(ui.item.id);
                $("#product_main").val(ui.item.value);

                // ✅ YAHI SE plant no list lao
                loadPlantNo(ui.item.id);

                return false;
            }
        });


    });


    // function saveProduct(){
    // const pid=$("#pm-product-id").val();
    // const pname=$("#pm-product").val();
    // const desc=$("#pm-desc").val();
    // const qty=$("#pm-qty").val();

    // if(!pid||!qty){alert("Product & Qty required");return;}

    // $("#product-empty").remove();

    // $("#product-grid").append(`
    // <div class="product-row">
    // <input type="hidden" name="productid[]" value="${pid}">
    // <input type="hidden" name="productname[]" value="${pname}">
    // <input type="hidden" name="description[]" value="${desc}">
    // <input type="hidden" name="qty[]" value="${qty}">


    // <div class="product-info">
    // <div class="product-name">${pname}</div>
    // ${desc ? `<div class="product-desc">${desc}</div>` : ``}
    // <div class="product-qty">Qty: ${qty}</div>
    // </div>

    // <div style="display:flex;gap:8px">
    //     <button type="button" class="btn primary"
    //         onclick="editProduct(this)">Edit</button>

    //     <button type="button" class="btn danger"
    //         onclick="$(this).closest('.product-row').remove()">Delete</button>
    // </div>

    // `);

    // closeProductModal();
    // }

    function saveProduct() {
        const pid = $("#pm-product-id").val();
        const pname = $("#pm-product").val();
        const desc = $("#pm-desc").val();
        const qty = $("#pm-qty").val();

        if (!pid || !qty) {
            alert("Product & Qty required");
            return;
        }

        const html = `
        <input type="hidden" name="productid[]" value="${pid}">
        <input type="hidden" name="productname[]" value="${pname}">
        <input type="hidden" name="description[]" value="${desc}">
        <input type="hidden" name="qty[]" value="${qty}">

        <div class="product-info">
            <div class="product-name">${pname}</div>
            ${desc ? `<div class="product-desc">${desc}</div>` : ``}
            <div class="product-qty">Qty: ${qty}</div>
        </div>

        <div style="display:flex;gap:8px">
            <button type="button" class="btn primary" onclick="editProduct(this)">Edit</button>
            <button type="button" class="btn danger"
                onclick="$(this).closest('.product-row').remove()">Delete</button>
        </div>
    `;

        if (editingRow) {
            // ✅ EDIT MODE
            editingRow.html(html);
            editingRow = null;
        } else {
            // ✅ ADD MODE
            $("#product-empty").remove();
            $("#product-grid").append(`<div class="product-row">${html}</div>`);
        }

        closeProductModal();
    }

    function renderProductRow(pid, pname, desc, qty) {

        $("#product-empty").remove();

        const html = `
        <div class="product-row">
            <input type="hidden" name="productid[]" value="${pid}">
            <input type="hidden" name="productname[]" value="${pname}">
            <input type="hidden" name="description[]" value="${desc}">
            <input type="hidden" name="qty[]" value="${qty}">

            <div class="product-info">
                <div class="product-name">${pname}</div>
                ${desc ? `<div class="product-desc">${desc}</div>` : ``}
                <div class="product-qty">Qty: ${qty}</div>
            </div>

            <div style="display:flex;gap:8px">
                <button type="button" class="btn primary"
                    onclick="editProduct(this)">Edit</button>
                <button type="button" class="btn danger"
                    onclick="$(this).closest('.product-row').remove()">Delete</button>
            </div>
        </div>
    `;

        $("#product-grid").append(html);
    }




    // function editProduct(btn){
    //     const row = $(btn).closest('.product-row');

    //     $("#pm-product").val(row.find(".product-name").text());
    //     $("#pm-desc").val(row.find(".product-desc").text() || "");
    //     $("#pm-qty").val(row.find(".product-qty").text().replace("Qty:","").trim());

    //     $("#pm-product-id").val(row.find("input[name='productid[]']").val());

    //     $("#product-modal").removeClass("hidden");
    // }
    function editProduct(btn) {
        editingRow = $(btn).closest('.product-row');

        $("#pm-product").val(editingRow.find(".product-name").text());
        $("#pm-desc").val(editingRow.find(".product-desc").text() || "");
        $("#pm-qty").val(
            editingRow.find(".product-qty").text().replace("Qty:", "").trim()
        );
        $("#pm-product-id").val(
            editingRow.find("input[name='productid[]']").val()
        );

        $("#product-modal").removeClass("hidden");
    }

    function closeProductModal() {
        editingRow = null;
        $("#product-modal").addClass('hidden');
    }



    function loadPlantNo(productid) {

        if (!productid) {
            $("#plantno").html('<option value="">Select Plant No</option>');
            return;
        }

        $.getJSON("saleorderform.php", {
            ajax: "get_plant_no",
            productid: productid
        }, function(res) {
            let h = '<option value="">Select Plant No</option>';
            res.forEach(p => {
                h += `<option value="${p.id}">${p.name}</option>`;
            });
            $("#plantno").html(h);
        });
    }
</script>
<script>
    $(document).ready(function() {

        <?php if ($isEdit): ?>

            const custId = <?= (int)$editData['custid'] ?>;
            const siteId = <?= (int)$editData['sitename'] ?>;
            const plantId = <?= (int)$editData['productid'] ?>;
            const plantNoId = <?= (int)$editData['plantname'] ?>;

            // 1️⃣ Load SITE list & select saved site
            if (custId) {
                loadSites(custId);

                setTimeout(function() {
                    $("#sitename").val(siteId);
                }, 400);
            }

            // 2️⃣ Load PLANT NO list & select saved plant no
            if (plantId) {
                loadPlantNo(plantId);

                setTimeout(function() {
                    $("#plantno").val(plantNoId);
                }, 400);
            }

        <?php endif; ?>

        //GRID
        <?php if ($isEdit && !empty($editProducts)): ?>

            if (typeof existingProducts !== "undefined") {
                existingProducts.forEach(p => {
                    renderProductRow(
                        p.propid,
                        p.name, // OR product name if you store it separately
                        p.description,
                        p.qty
                    );
                });
            }

        <?php endif; ?>


    });
</script>

<?php
$CONTENT = ob_get_clean();
require_once __DIR__ . '/../includes/ui_autoshell.php';
