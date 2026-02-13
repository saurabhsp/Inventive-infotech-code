<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/initialize.php';
require_once __DIR__ . '/../includes/aclhelper.php';

if (!function_exists('is_logged_in') || !is_logged_in()) {
    redirect('../login.php');
}

date_default_timezone_set('Asia/Kolkata');

$user = current_user();
$uid  = (int)($user['id'] ?? 0);

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
            $out[] = ['id'=>$r['id'],'name'=>$r['sitename']];
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
                'label' => $r['name'].' '.$r['modelcode'],
                'value' => $r['name'].' '.$r['modelcode']
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

    $rs = $con->query("SELECT name FROM jos_erp_plantname ORDER BY id");
    while ($r = $rs->fetch_assoc()) {
        $out[] = $r['name']; // PLANT I
    }

    echo json_encode($out);
    exit;
}


}






/* =========================================================
   SAVE SALES ORDER
========================================================= */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['sale_form'])) {

    $dt = DateTime::createFromFormat('d-m-Y', $_POST['salesdate']);
    $salesdate = $dt ? $dt->format('Y-m-d') : null;

    $custid   = (int)$_POST['custid'];
    $customer = trim($_POST['customer']);
    $address  = trim($_POST['address']);
    $sitename = (int)$_POST['sitename'];
   $plant    = trim($_POST['plantname']); // Plant Name
$plantno = trim($_POST['plantno']);   // PLANT I / PLANT II



    $prod = $_POST['productid'] ?? [];
    $desc = $_POST['description'] ?? [];
    $qty  = $_POST['qty'] ?? [];

    if (!$custid || !$salesdate || empty($prod)) {
       echo "<script>alert('Please add at least one product');history.back();</script>";
exit;

    }

    $con->begin_transaction();
    try {

        $st = $con->prepare(
            "INSERT INTO jos_erp_sale_order
(salesdate,custid,customer,address,sitename,plantname,product,created_by)
VALUES (?,?,?,?,?,?,?,?)"

        );
       $st->bind_param(
    'sisssssi',

    $salesdate,
    $custid,
    $customer,
    $address,
    $sitename,
    $plantno,    // ✅ plant number → plantname column
    $plant,      // ✅ plant name → product column
    $uid
);



        $st->execute();
        $saleid = $con->insert_id;
        $st->close();

        $stg = $con->prepare(
            "INSERT INTO jos_erp_saleorder_grid
             (saleid,propid,description,qty,userid)
             VALUES (?,?,?,?,?)"
        );

        foreach ($prod as $i=>$pid) {
            if (!$pid || !$qty[$i]) continue;
            $stg->bind_param('iisii',$saleid,$pid,$desc[$i],$qty[$i],$uid);
            $stg->execute();
        }
        $stg->close();

        $con->commit();

$_SESSION['success_msg'] = 'Sales Order saved successfully';

header('Location: saleorderform.php');
exit;


    } catch(Exception $e) {
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
<?php unset($_SESSION['success_msg']); endif; ?>

<form method="post">

<input type="hidden" name="sale_form" value="1">
<input type="hidden" id="custid" name="custid">

<div class="form-grid">

    <div class="field">
        <label>Sales Date</label>
        <input type="text" name="salesdate" class="inp datepick" value="<?=date('d-m-Y')?>">
    </div>

    <div class="field">
        <label>Customer Name</label>
        <input type="text" id="customer_name" name="customer" class="inp" autocomplete="off">
    </div>

    <div class="field">
        <label>Address</label>
        <textarea id="address_display" name="address" class="inp" readonly></textarea>
    </div>

    <div class="field">
        <label>Sitename</label>
        <select id="sitename" name="sitename" class="inp"></select>
    </div>

    <div class="field">
        <label>Plant Name</label>
        <input type="text" id="plantname_text" class="inp" autocomplete="off">
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
.nature-modal.hidden{display:none}
.nature-modal{position:fixed;inset:0;z-index:9999}
.nature-modal-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.4)}
.nature-modal-dialog{
background:#fff;border-radius:14px;padding:18px;width:420px;
position:absolute;left:50%;top:50%;
transform:translate(-50%,-50%);
}
.blue-panel{
background:#e5f1ff;padding:10px;border-radius:10px;margin-top:16px;
display:flex;gap:10px;align-items:center;
}
.nature-row{
border:1px solid #e5e7eb;border-radius:10px;padding:10px;margin-top:8px;
display:flex;justify-content:space-between;background:#fafafa;
}

.ui-autocomplete{
    z-index:100000 !important;
    max-height:220px;
    overflow-y:auto;
}
.section-header{
background:#eaf4ff;
padding:10px 14px;
border-radius:10px;
display:flex;
align-items:center;
gap:12px;
margin-top:18px;
}
.btn-add{
background:#2563eb;
color:#fff;
border:none;
padding:6px 14px;
border-radius:20px;
font-size:14px;
cursor:pointer;
}
.section-title{font-weight:600;}

.product-grid{margin-top:12px;}
.empty-row{color:#6b7280;font-size:14px;padding:12px;}

.product-row{
background:#fff;
border-radius:12px;
padding:14px 16px;
display:flex;
justify-content:space-between;
align-items:center;
box-shadow:0 1px 3px rgba(0,0,0,.08);
margin-bottom:10px;
}
.product-name{font-weight:600;margin-bottom:4px;}
.product-desc{font-size:13px;color:#374151;margin-bottom:4px;}
.product-qty{
display:inline-block;
background:#eef2ff;
color:#1e40af;
padding:3px 10px;
border-radius:999px;
font-size:12px;
}
.btn-delete{
width:36px;
height:36px;
border-radius:50%;
border:none;
background:#ef4444;
color:#fff;
font-size:18px;
cursor:pointer;
}

.form-grid{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:18px 24px;
}

@media(max-width:900px){
    .form-grid{grid-template-columns:1fr;}
}



</style>

<script>
flatpickr('.datepick',{dateFormat:'d-m-Y',allowInput:true});

$("#customer_name").autocomplete({
    minLength: 2,

    source: function (request, response) {
        $.getJSON("saleorderform.php", {
            ajax: "cust_autocomplete",
            term: request.term
        }, response);
    },

    focus: function (event, ui) {
        $("#customer_name").val(ui.item.label);
        return false;
    },

    select: function (event, ui) {
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
$("#customer_name").on("blur", function () {
    if (!$("#custid").val()) {
        $(this).val("");
        $("#address_display").val("");
        $("#sitename").html('<option value="">Select Site</option>');
        $("#plantname").val("");
$("#plantname_text").val("");
$("#plantno").val("");

    }
});


function loadSites(cid){
$.getJSON("saleorderform.php",{ajax:"sites",customer_id:cid},d=>{
let h='<option value="">Select Site</option>';
d.forEach(x=>h+=`<option value="${x.id}">${x.name}</option>`);
$("#sitename").html(h);
});
}


$("#sitename").on("change",function(){
    $("#plantname_text").val("");
    $("#plantname").val("");
    $("#plantno").val("");
});

function openProductModal(){
$("#pm-product,#pm-desc,#pm-qty,#pm-product-id").val('');
$("#product-modal").removeClass('hidden');
}

function closeProductModal(){
$("#product-modal").addClass('hidden');
}

$("#pm-product").autocomplete({
    minLength: 2,
    appendTo: "#product-modal .nature-modal-dialog",

    source: function (request, response) {
        $.getJSON("saleorderform.php", {
            ajax: "product_autocomplete",
            term: request.term
        }, response);
    },

    focus: function (event, ui) {
        $("#pm-product").val(ui.item.label);
        return false;
    },

   select: function (event, ui) {
    $("#pm-product").val(ui.item.label);
    $("#pm-product-id").val(ui.item.id);

    loadPlantNo(ui.item.id);
    return false;
}

});

$(function () {

    $("#plantname_text").autocomplete({
        minLength: 1,

        source: function (req, res) {
    $.getJSON("saleorderform.php", {
        ajax: "plant_autocomplete",
        term: req.term
    }, res);
},

       select: function (e, ui) {
    $("#plantname_text").val(ui.item.label);
    $("#plantname").val(ui.item.id);   // product id

    loadPlantNo(ui.item.id);            // ← DEPENDS ON PLANT NAME
    return false;
}

    });

});


function saveProduct(){
const pid=$("#pm-product-id").val();
const pname=$("#pm-product").val();
const desc=$("#pm-desc").val();
const qty=$("#pm-qty").val();

if(!pid||!qty){alert("Product & Qty required");return;}

$("#product-empty").remove();

$("#product-grid").append(`
<div class="product-row">
<input type="hidden" name="productid[]" value="${pid}">
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

`);

closeProductModal();
}




function editProduct(btn){
    const row = $(btn).closest('.product-row');

    $("#pm-product").val(row.find(".product-name").text());
    $("#pm-desc").val(row.find(".product-desc").text() || "");
    $("#pm-qty").val(row.find(".product-qty").text().replace("Qty:","").trim());

    $("#pm-product-id").val(row.find("input[name='productid[]']").val());

    $("#product-modal").removeClass("hidden");
}


function loadPlantNo(productid){

    if(!productid){
        $("#plantno").html('<option value="">Select Plant No</option>');
        return;
    }

    $.getJSON("saleorderform.php",{
        ajax:"get_plant_no",
        productid: productid
    },function(res){
        let h = '<option value="">Select Plant No</option>';
        res.forEach(p=>{
            h += `<option value="${p}">${p}</option>`;
        });
        $("#plantno").html(h);
    });
}


</script>
<?php
$CONTENT = ob_get_clean();
require_once __DIR__ . '/../includes/ui_autoshell.php';
