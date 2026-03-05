<?php
@ini_set('display_errors','1'); 
@error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_login();

global $con;
$page_title = "Add Sponsorship";

// database tables
$SPONSERSHIP = 'jos_app_sponsorship';

/* logged user */
$me  = $_SESSION['admin_user'] ?? [];
$uid = (int)($me['id'] ?? 0);

/* save form */
$msg = "";

if(isset($_POST['save']))
{
    $sponsor_name = trim($_POST['sponsor_name']);
    $address      = trim($_POST['address']);
    $contact_no   = trim($_POST['contact_no']);
    $valid_from   = $_POST['valid_from'];
    $valid_to     = $_POST['valid_to'];
    $amount       = $_POST['amount'];
    $status       = $_POST['status'];

    $stmt = $con->prepare("INSERT INTO $SPONSERSHIP
        (sponsor_name,address,contact_no,valid_from,valid_to,amount,status,created_by)
        VALUES (?,?,?,?,?,?,?,?)");

    $stmt->bind_param(
        "sssssdii",
        $sponsor_name,
        $address,
        $contact_no,
        $valid_from,
        $valid_to,
        $amount,
        $status,
        $uid
    );

    if($stmt->execute()){
        $msg = "Sponsorship added successfully!";
    } else {
        $msg = "Error saving record";
    }

    $stmt->close();
}
?>

<link rel="stylesheet" href="/adminconsole/assets/ui.css">

<style>

.form-grid{
display:grid;
grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
gap:16px;
padding:20px;
}

.form-group{
display:flex;
flex-direction:column;
gap:6px;
}

.form-group label{
font-weight:600;
font-size:13px;
}

.form-group input,
.form-group textarea,
.form-group select{
padding:10px;
border-radius:8px;
border:1px solid #ccc;
}

.btn.primary{
background:#2563eb;
color:#fff;
border:none;
padding:10px 20px;
border-radius:999px;
font-weight:600;
}

.btn.primary:hover{
opacity:.9;
}

</style>

<div class="master-wrap">

<div class="card" style="margin-top:20px">

<div class="headbar">
<div style="font-size:16px;font-weight:700">
Add Sponsorship
</div>
</div>

<?php if($msg): ?>
<div style="padding:10px;color:green;font-weight:600;">
<?=htmlspecialchars($msg)?>
</div>
<?php endif; ?>

<form method="post">

<div class="form-grid">

<div class="form-group">
<label>Sponsor Name</label>
<input type="text" name="sponsor_name" required>
</div>

<div class="form-group">
<label>Contact Number</label>
<input type="text" name="contact_no" maxlength="10">
</div>

<div class="form-group">
<label>Valid From</label>
<input type="text" placeholder="DD-MM-YYYY" name="valid_from" id="valid_from" required>
</div>



<div class="form-group">
<label>Duration (Months)</label>
<select name="months" id="months">
<option value="">Select Months</option>

<?php for($i=1;$i<=12;$i++): ?>
<option value="<?=$i?>"><?=$i?> Month<?=$i>1?'s':''?></option>
<?php endfor; ?>

</select>
</div>

<div class="form-group">
<label>Valid To</label>
<input type="text" placeholder="DD-MM-YYYY" name="valid_to" id="valid_to" readonly required>
</div>

<div class="form-group">
<label>Amount</label>
<input type="number" step="0.01" name="amount" required>
</div>

<div class="form-group">
<label>Status</label>
<select name="status">
<option value="1">Active</option>
<option value="0">Inactive</option>
</select>
</div>

<div class="form-group" style="grid-column:1/-1">
<label>Address</label>
<textarea name="address" rows="3"></textarea>
</div>

</div>

<div style="padding:20px">
<button type="submit" name="save" class="btn primary">
Save Sponsorship
</button>
</div>

</form>

</div>

</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>

$(document).ready(function(){

let validFromPicker;
let validToPicker;

/* Calculate Valid To */

function calculateDate(){

    let validFrom = $('#valid_from').val();
    let months    = $('#months').val();

    if(validFrom && months)
    {
        let date = new Date(validFrom);

        date.setMonth(date.getMonth() + parseInt(months));

        validToPicker.setDate(date, true); // updates UI + value
    }
}


/* Flatpickr - Valid From */

validFromPicker = flatpickr("#valid_from", {
    dateFormat: "Y-m-d",
    altInput: true,
    altFormat: "d-m-Y",
    onChange: function(){
        calculateDate();
    }
});


/* Flatpickr - Valid To */

validToPicker = flatpickr("#valid_to", {
    dateFormat: "Y-m-d",
    altInput: true,
    altFormat: "d-m-Y",
    clickOpens: false,   // prevents opening picker
    allowInput: false    // prevents typing
});


/* Month Change */

$('#months').on('change', function(){
    calculateDate();
});

});

</script>