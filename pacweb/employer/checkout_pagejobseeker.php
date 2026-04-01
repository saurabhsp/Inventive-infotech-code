<?php

session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
if (isset($_SESSION['payment_done']) && $_SESSION['payment_done'] === true) {
    header("Location: subscription_plans.php");
    exit;
}

if (!isset($_POST['plan_id'])) {
    header("Location: subscription_plans.php");
    exit;
}
require_once "web_api/includes/db_config.php";





$user = $_SESSION['user'];
$userid = $user['id'];
$profile_id = $user['profile_id'];
$profile_type_id = $user['profile_type_id'];
$planName = $_POST['plan_name'] ?? '';
$planId = $_POST['plan_id'] ?? 0;

$selectedPlanData = [];

if (!empty($planId)) {

    $getPlanUrl = API_BASE_URL . "getSubscriptionplans.php";

    $payload = json_encode([
        "profile_type" => $profile_type_id
    ]);

    $ch = curl_init($getPlanUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $plans = json_decode($response, true);

    if (!empty($plans['data'])) {
        foreach ($plans['data'] as $plan) {
            if ($plan['id'] == $planId) {
                $selectedPlanData = $plan;  // ✅ ONLY VARIABLE
                break;
            }
        }
    }
}
// ✅ Now everything comes from API
$planid   = $selectedPlanData['id'] ?? 0;
$amount   = $selectedPlanData['final_amount'] ?? 0;
$validity = $selectedPlanData['validity_months'] ?? 0;
$planName = $selectedPlanData['plan_name'] ?? '';
$finalAmount = $amount; // ✅ CORRECT PLACE

$apierror = "";
$successMsg = "";









//=============RAZORPAY KEYS==========
$getKeyUrl = "https://pacificconnect2.0.inv51.in/webservices/razorpay_keys.php";

$ch = curl_init($getKeyUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

$responsefour = curl_exec($ch);


curl_close($ch);

$keyresult = json_decode($responsefour, true);

// ✅ HANDLE RESPONSE
if (!empty($keyresult) && $keyresult['status'] === "success") {

    $razorpay_key = $keyresult['data']['key_id'] ?? '';
} else {

    $apierror = $keyresult['message'] ?? "Failed to fetch Razorpay key";
    $razorpay_key = '';
}





//=============documents==========
if (isset($_POST['upload_doc']) && $profile_type_id == 2) {

    $doc_type = $_POST['doc_type'];

    // Get correct doc number
    if ($doc_type == 2) {
        $doc_no = $_POST['doc_no']; // Aadhaar
    } else {
        $doc_no = $_POST['doc_no_pan']; // PAN
    }

    $filePath = $_FILES['doc_img']['tmp_name'];
    $fileName = $_FILES['doc_img']['name'];

    $cfile = new CURLFile($filePath, $_FILES['doc_img']['type'], $fileName);

    $postData = [
        "userid" => $userid,
        "doc_type" => $doc_type,
        "doc_no" => $doc_no,
        "doc_img" => $cfile
    ];



    $ch = curl_init("https://pacificconnect2.0.inv51.in/webservices/addDoc.php");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo "Curl error: " . curl_error($ch);
    }

    curl_close($ch);

    $docresult = json_decode($response, true);

    if (!empty($docresult) && $docresult['status'] == true) {
        $successMsg = $docresult['message'] ?? "Document uploaded successfully";
    } else {
        $apierror = $docresult['message'] ?? "Upload failed";
    }
}









//============= APPLY COUPON ==========
$couponMessage = "";
$discountAmount = 0;

// if (isset($_POST['coupon_code']) && !isset($_POST['pay_now'])) {
if (isset($_POST['coupon_code']) && (isset($_POST['apply_coupon']) || isset($_POST['upload_doc']))) {

    $couponCode = $_POST['coupon_code'];

    $couponPayload = json_encode([
        "coupon_code" => $couponCode,
        "cart_total" => $amount,
        "profile_type_id" => $profile_type_id
    ]);

    $ch = curl_init(API_BASE_URL . "applyCoupon.php");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $couponPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);

    $couponResponse = curl_exec($ch);

    if (curl_errno($ch)) {
        $apierror = "Curl error: " . curl_error($ch);
    }

    curl_close($ch);

    $couponResult = json_decode($couponResponse, true);


    // default
    if (!empty($couponResult) && $couponResult['status'] == true) {
        // ONLY show message when user clicks APPLY
        if (isset($_POST['apply_coupon'])) {
            $successMsg = $couponResult['message'] ?? "Coupon applied successfully";
        }
        $discountAmount = $couponResult['discount_amount'] ?? 0;

        $finalAmount = $couponResult['final_total'] ?? $amount; // ✅ keep separate
    } else {
        if (isset($_POST['apply_coupon'])) {
            $apierror = $couponResult['message'] ?? "Invalid coupon";
        }
    }

    $couponCodeVar = $couponCode ?? ($_POST['coupon_code'] ?? '');
    $discountVar   = $discountAmount ?? 0;
    $finalAmountVar = $finalAmount ?? $amount;
}


$recruiterData = [];
$ch = curl_init(API_BASE_URL . "getRecruiter_profile.php");
$recruiterPayload = json_encode([
    "id" => $profile_id
]);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $recruiterPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (!empty($result) && $result['status'] == true) {
    $recruiterData = $result['data'];
    $user_name = $recruiterData['contact_person_name'] ?? '';
    $user_email = $recruiterData['email'] ?? '';
    $user_contact = $recruiterData['mobile_no'] ?? '';
    $user_company = $recruiterData['organization_name'] ?? '';
} else {
    $apierror = $result['message'] ?? "Unable to get recruiter Details";
}










$orderData = null;

if (isset($_POST['pay_now'])) {


    // ✅ PAYMENT CONTINUES
    // restore values
    $couponCode = $_POST['coupon_code'] ?? '';
    $discountAmount = $_POST['discount'] ?? 0;

    // recalculate final amount
    $finalAmount = $amount - $discountAmount;

    // safety check
    if ($finalAmount < 0) {
        $finalAmount = 0;
    }

    $payload = json_encode([
        "receipt" => "ORD_" . time(),
        "amount" => $finalAmount, // ✅ correct
        "notes" => [
            "user_id" => $userid,
            "plan" => $planName
        ]
    ]);

    $ch = curl_init(API_BASE_URL . "create_order.php");
    // $ch = curl_init("https://pacificconnect2.0.inv51.in/webservices/create_order.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $orderData = json_decode($response, true);
    if (!empty($orderData) && $orderData['status'] == true) {
        // $successMsg = "Order Created Successfully " . $orderData['receipt'];
        $amount_paise = $orderData['amount_paise']; //AMOUNT IN PAISE FOR RAZORPAY
        $amount_rupees = $orderData['amount_rupees']; //AMOUNT IN rupee 
        $status = $orderData['status'];
        $order_id = $orderData['order_id'] ?? '';
    } else {
        $apierror = $orderData['status'] ?? "Failed to Create Order";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">

    <style>
        :root {
            --primary: #483EA8;
            --primary-dark: #322b7a;
            --secondary: #ff6f00;
            --bg-body: #f8f9fa;
            --white: #ffffff;
            --text-dark: #1a1a1a;
            --text-grey: #666;
            --border-light: #e0e0e0;
            --blue-btn: #1565c0;
            --plan-bg: #fffde7;
            --plan-border: #fbc02d;
            --green: #25D366;
        }





        /* --- CHECKOUT LAYOUT --- */
        .checkout-container {
            max-width: 1150px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .breadcrumb {
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: var(--text-grey);
        }

        .breadcrumb a {
            color: var(--text-grey);
            text-decoration: none;
        }

        .breadcrumb span {
            color: var(--primary);
            font-weight: 600;
        }

        /* GRID SYSTEM */
        .checkout-layout {
            display: grid;
            grid-template-columns: 1.8fr 1fr;
            /* Left (Content) vs Right (Sidebar) */
            gap: 25px;
            align-items: start;
        }

        /* --- CARDS COMMON --- */
        .sub-card {
            background: var(--white);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-light);
            margin-bottom: 25px;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .card-header i {
            color: var(--primary);
        }

        /* --- LEFT COLUMN CONTENT --- */


        /* 1. Plan Details */
        .subscription-plan-box {
            background: var(--plan-bg);
            border: 1px solid var(--plan-border);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .plan-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: #f57f17;
            margin-bottom: 5px;
        }

        .plan-sub {
            color: #555;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .plan-price-lg {
            font-size: 1.6rem;
            font-weight: 800;
            color: #333;
        }

        /* Coupon */
        .coupon-row {
            display: flex;
            gap: 10px;
        }

        /* Coupon */
        .gst-row {
            display: flex;
            gap: 10px;
        }

        .coupon-input {
            flex: 1;
            padding: 12px;
            border: 1px dashed #ccc;
            border-radius: 6px;
            font-size: 0.95rem;
            text-transform: uppercase;
            font-weight: 600;
            outline: none;
        }

        .gstno-input {
            flex: 1;
            padding: 12px;
            border: 1px dashed #ccc;
            border-radius: 6px;
            font-size: 0.95rem;
            text-transform: uppercase;
            font-weight: 600;
            /* margin-top: 20px; */
            margin-bottom: 20px;
            outline: none;
        }

        .btn-apply {
            background: var(--green);
            color: white;
            border: none;
            padding: 0 25px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
        }

        /* 2. KYC Form */
        .kyc-alert {
            background: #f0f7ff;
            border-left: 4px solid var(--primary);
            padding: 12px;
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .label-bold {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .doc-type-group {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .doc-btn {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            text-align: center;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            transition: 0.2s;
        }

        .doc-btn.active {
            border-color: var(--primary);
            background: var(--primary-light);
            color: var(--primary);
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
        }

        .std-input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            outline: none;
        }

        .std-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(72, 62, 168, 0.1);
        }

        /* --- RIGHT COLUMN: SIDEBAR --- */
        .sidebar {
            position: sticky;
            top: 90px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            color: #555;
            font-size: 0.95rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-weight: 800;
            font-size: 1.2rem;
            color: #000;
        }

        .btn-pay {
            width: 100%;
            background: var(--blue-btn);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1.1rem;
            margin-top: 20px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(21, 101, 192, 0.2);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .btn-pay:hover {
            background: #0d47a1;
        }

        .secure-msg {
            text-align: center;
            font-size: 0.8rem;
            color: #2e7d32;
            font-weight: 600;
            margin-top: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
        }

        /* --- MOBILE RESPONSIVE --- */
        @media (max-width: 900px) {
            .nav-menu {
                display: none;
            }

            .checkout-layout {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            /* Stack Grid */
            .sidebar {
                position: static;
            }

            /* Unstick on mobile */

            /* Visual Tweaks */
            .plan-price-lg {
                font-size: 1.4rem;
            }

            .sub-card {
                padding: 20px;
            }
        }

        /* Saurabh */
        body {
            display: block !important;
            overflow-x: auto !important;
        }

        .checkout-container {
            flex: none !important;
            width: 100%;
        }

        .checkout-layout {
            display: grid !important;
        }

        /* Modal CSS */
        .modal-full-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            /* dark overlay */
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .modal-map-card {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            width: 350px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            animation: popup 0.3s ease;
        }

        @keyframes popup {
            from {
                transform: scale(0.8);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .gst-row {
            display: flex;
            flex-direction: column;
        }
    </style>
</head>

<body>


    <div class="modal-full-overlay" id="successModal" style="display:none;">
        <div class="modal-map-card" style="max-width:400px;height:auto;padding:30px;text-align:center;">
            <h3 style="margin-bottom:15px;color:#2e7d32;">Success</h3>
            <p id="successText"></p>
            <button onclick="closeSuccessModal()"
                style="margin-top:20px;padding:10px 20px;background:#2563eb;color:white;border-radius:6px;border:none;">
                OK
            </button>
        </div>
    </div>


    <div class="modal-full-overlay" id="errorModal" style="display:none;">
        <div class="modal-map-card" style="max-width:400px;height:auto;padding:30px;text-align:center;">
            <h3 style="margin-bottom:15px;color:#e53935;">Error</h3>
            <p><?php echo $apierror ?></p>
            <button onclick="closeErrorModal()"
                style="margin-top:20px;padding:10px 20px;background:#2563eb;color:white;border-radius:6px;border:none;">
                OK
            </button>
        </div>
    </div>
    <?php unset($_SESSION['error_message']); ?>

    <?php include "includes/preloader.php";
    ?>
    <?php include "includes/header.php";
    ?>

    <div class="checkout-container">

        <div class="breadcrumb">
            <a href="index.php">Home</a> &nbsp;/&nbsp; <a href="subscription_plans.php">Subscription</a> &nbsp;/&nbsp; <span>Checkout</span>
        </div>

        <div class="checkout-layout">

            <div class="left-content">

                <div class="sub-card">
                    <div class="card-header"><i class="fas fa-receipt"></i> Plan Details</div>

                    <div class="subscription-plan-box">
                        <div>
                            <div class="plan-title"> <?= htmlspecialchars($planName) ?> Plan</div>
                            <div class="plan-sub"> <?= $validity ?> Months Validity • Premium</div>
                        </div>
                        <div class="plan-price-lg"> ₹<?= number_format($amount) ?></div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="plan_name" value="<?= htmlspecialchars($planName) ?>">
                        <input type="hidden" name="plan_id" value="<?= $planid ?>">

                        <div class="coupon-row">
                            <input type="text" name="coupon_code"
                                value="<?= htmlspecialchars($_POST['coupon_code'] ?? '') ?>"
                                class="coupon-input" placeholder="ENTER COUPON CODE">
                            <button type="submit" name="apply_coupon" class="btn-apply">APPLY</button>
                        </div>
                    </form>

                </div>


                <?php if ($profile_type_id == 2) { ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="plan_name" value="<?= htmlspecialchars($planName) ?>">
                        <input type="hidden" name="plan_id" value="<?= $planid ?>">
                        <input type="hidden" name="coupon_code" value="<?= htmlspecialchars($couponCodeVar) ?>">
                        <input type="hidden" name="discount" value="<?= $discountVar ?>">

                        <div class="sub-card">
                            <div class="card-header"><i class="fas fa-id-card"></i> Complete KYC</div>

                            <div class="kyc-alert">
                                <i class="fas fa-info-circle"></i>
                                Kindly provide valid documents.
                            </div>

                            <!-- Document Toggle -->
                            <label class="label-bold">Select Document Type</label>
                            <div class="doc-type-group">
                                <div class="doc-btn active" data-type="2">Aadhaar Card</div>
                                <div class="doc-btn" data-type="1">PAN Card</div>
                            </div>

                            <!-- Hidden doc_type -->
                            <input type="hidden" name="doc_type" id="doc_type" value="2">

                            <!-- Aadhaar Input -->
                            <div id="aadhaarField">
                                <label class="label-bold">Aadhaar Number</label>
                                <input type="text" name="doc_no" class="std-input" maxlength="12" placeholder="Enter Aadhaar Number">
                            </div>

                            <!-- PAN Input -->
                            <div id="panField" style="display:none;">
                                <label class="label-bold">PAN Number</label>
                                <input type="text" name="doc_no_pan" class="std-input" maxlength="10" placeholder="Enter PAN Number">
                            </div>

                            <!-- File Upload -->
                            <label class="label-bold">Upload Document</label>
                            <input type="file" name="doc_img" class="std-input" required>

                            <button type="submit" name="upload_doc" class="btn-pay" style="margin-top:15px;">
                                Upload Document
                            </button>
                        </div>
                    </form>
                <?php } ?>


            </div>

            <aside class="sidebar">
                <div class="sub-card">
                    <div class="card-header"><i class="fas fa-wallet"></i> Payment Summary</div>
                    <form method="POST">
                        <input type="hidden" name="plan_name" value="<?= htmlspecialchars($planName) ?>">
                        <input type="hidden" name="plan_id" value="<?= $planid ?>">
                        <input type="hidden" name="coupon_code" value="<?= htmlspecialchars($_POST['coupon_code'] ?? '') ?>">
                        <input type="hidden" name="discount" value="<?= $discountAmount ?>">

                        <div class="summary-row">
                            <span>Base Price</span>
                            <span>₹<?= number_format($amount) ?></span>
                        </div>
                        <div class="summary-row">
                            <span>GST (18%)</span>
                            <span>Included</span>
                        </div>


                        <?php if (!empty($discountAmount)) { ?>
                            <div class="summary-row">
                                <span>Discount</span>
                                <span>- ₹<?= number_format($discountAmount) ?></span>
                            </div>
                        <?php } ?>

                        <div class="total-row">
                            <span>Total Payable</span>
                            <span>₹<?= number_format($finalAmount) ?></span>
                        </div>

                        <button type="submit" name="pay_now" class="btn-pay">
                            Pay Securely ₹<?= number_format($finalAmount) ?>
                        </button>

                    </form>
                </div>
            </aside>

        </div>
    </div>
    <?php include "includes/bottom-bar.php";
    ?>

    <script>
        window.onload = () => document.getElementById("global-preloader")?.remove();

        window.addEventListener("pageshow", function(event) {
            if (event.persisted || window.performance.getEntriesByType("navigation")[0].type === "back_forward") {
                window.location.href = "subscription_plans.php";
            }
        });




        const successMsg = <?php echo json_encode($successMsg); ?>;
        const errorMsg = <?php echo json_encode($apierror); ?>;

        if (successMsg) {
            document.getElementById("successText").innerText = successMsg;
            document.getElementById("successModal").style.display = "flex";
        }

        if (errorMsg) {
            document.querySelector("#errorModal p").innerText = errorMsg;
            document.getElementById("errorModal").style.display = "flex";
        }

        function closeSuccessModal() {
            const modal = document.getElementById("successModal");
            if (modal) {
                modal.style.display = "none";
            }
        }

        function closeErrorModal() {
            const modal = document.getElementById("errorModal");
            if (modal) {
                modal.style.display = "none";
            }
        }


        // Adhar & Pan Upload
        const buttons = document.querySelectorAll('.doc-btn');
        const aadhaarField = document.getElementById('aadhaarField');
        const panField = document.getElementById('panField');
        const docTypeInput = document.getElementById('doc_type');

        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                buttons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const type = btn.getAttribute('data-type');
                docTypeInput.value = type;

                if (type == "2") {
                    aadhaarField.style.display = 'block';
                    panField.style.display = 'none';
                } else {
                    aadhaarField.style.display = 'none';
                    panField.style.display = 'block';
                }
            });
        });
    </script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        function redirectToStatus(status, error = null) {

            var form = document.createElement("form");
            form.method = "POST";
            form.action = "payment_status.php";

            function addField(name, value) {
                var input = document.createElement("input");
                input.type = "hidden";
                input.name = name;
                input.value = value;
                form.appendChild(input);
            }

            addField("payment_status", status);

            if (status === "failed") {
                addField("error_message", error?.description || "Payment failed");
            }

            addField("plan_id", "<?php echo $planid; ?>");
            addField("plan_amount", "<?php echo $amount; ?>");
            addField("amount_paid", "<?php echo $finalAmount; ?>");
            addField("discount", "<?php echo $_POST['discount'] ?? 0; ?>");

            document.body.appendChild(form);
            form.submit();
        }
        const razorpay_key = "<?php echo $razorpay_key; ?>";
        const order_id = "<?php echo $order_id; ?>";
        const amount_paise = "<?php echo $amount_paise ?? 0; ?>"; // ✅ IMPORTANT
        const user_name = "<?php echo $user_name; ?>";
        const user_email = "<?php echo $user_email; ?>";
        const user_contact = "<?php echo $user_contact; ?>";
        const user_company = "<?php echo $user_company; ?>";



        function openRazorpay() {
            if (!order_id || amount_paise == 0) {
                alert("Order not created properly");
                return;
            }
            var options = {

                "key": razorpay_key, // from API
                "amount": amount_paise, // ✅ THIS IS YOUR ANSWER (IN PAISE)
                "currency": "INR",
                "name": user_company,
                "description": "Subscription Payment",
                "order_id": order_id, // from backend

                "modal": {
                    "ondismiss": function() {
                        redirectToStatus("failed", {
                            description: "User closed payment popup"
                        });
                    }
                },

                "handler": function(response) {

                    var form = document.createElement("form");
                    form.method = "POST";
                    form.action = "payment_status.php";

                    function addField(name, value) {
                        var input = document.createElement("input");
                        input.type = "hidden";
                        input.name = name;
                        input.value = value;
                        form.appendChild(input);
                    }

                    // ONLY SAFE DATA
                    addField("payment_id", response.razorpay_payment_id);
                    addField("order_id", response.razorpay_order_id);
                    addField("signature", response.razorpay_signature);

                    // YOUR DYNAMIC DATA (FROM PHP)
                    addField("plan_id", "<?php echo $planid; ?>");
                    addField("plan_amount", "<?php echo $amount; ?>");
                    // addField("amount_paid", "<?php echo $_POST['discount'] ? ($amount - $_POST['discount']) : $amount; ?>");
                    addField("amount_paid", "<?php echo $finalAmount; ?>");
                    addField("discount", "<?php echo $_POST['discount'] ?? 0; ?>");
                    addField("payment_status", "success");

                    document.body.appendChild(form);
                    form.submit();
                },

                "prefill": {
                    "name": user_name,
                    "email": user_email,
                    "contact": user_contact
                },

                "theme": {
                    "color": "#1565c0"
                }
            };

            var rzp = new Razorpay(options);
            rzp.on('payment.failed', function(response) {
                redirectToStatus("failed", response.error);
            });
            rzp.open();
        }

        <?php if (!empty($order_id) && !empty($amount_paise)) { ?>
            setTimeout(function() {
                openRazorpay();
            }, 500);
    </script>
<?php } ?>
</body>

</html>