<?php
session_start();
$_SESSION['payment_done'] = true;


// if (empty($_POST['payment_id'])) {
//     header("Location: subscription_plans.php");
//     exit;
// }
$payment_status = $_POST['payment_status'] ?? 'failed';
if ($payment_status == "success") {
    // existing success logic
} else {
    $isSuccess = false;
    $message = $_POST['error_message'] ?? "Payment failed";
}
require_once "../web_api/includes/db_config.php";


$responseData = null;
$isSuccess = false;
$message = "";

// Get POST data from Razorpay
$userid = $_SESSION['user']['id'] ?? 0;

$payload = json_encode([
    "userid" => $userid,
    "plan_id" => $_POST['plan_id'] ?? 0,
    "amount_paid" => $_POST['amount_paid'] ?? 0,
    "payment_id" => $_POST['payment_id'] ?? '',
    "payment_status" => "success",
    "plan_amount" => $_POST['plan_amount'] ?? 0,
    "discount" => $_POST['discount'] ?? 0,
    "coupon_code" => $_POST['coupon_code'] ?? ''
]);

$ch = curl_init(API_BASE_URL . "renewSubscription.php");

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (!empty($result) && $result['status'] == true) {
    $isSuccess = true;
    $responseData = $result['data'];
    $message = $result['message'];
} else {
    $isSuccess = false;
    $message = $result['message'] ?? "Payment failed";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status | Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            /* Pacific iConnect Theme Colors */
            --primary: #483EA8;
            --primary-light: #eceaf9;
            --blue-btn: #2563eb;
            --blue-hover: #1d4ed8;
            --success-green: #22c55e;
            --danger-red: #ef4444;
            --text-dark: #1a1a1a;
            --text-muted: #4b5563;
            --bg-body: #f8fafc;
            --white: #ffffff;
            --border-light: #e2e8f0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-dark);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a {
            text-decoration: none;
            transition: 0.3s;
            color: inherit;
        }

        button {
            cursor: pointer;
            outline: none;
            border: none;
            font-family: inherit;
        }

        /* --- 1. UNIFIED DESKTOP HEADER --- */
        header {
            background: var(--white);
            height: 70px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
        }

        .header-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand-group {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            font-weight: 800;
            font-size: 1.3rem;
        }

        .desktop-nav {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-link {
            font-weight: 600;
            color: #555;
            font-size: 1rem;
            padding: 5px 10px;
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--primary);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nav-action-icon {
            position: relative;
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            transition: 0.2s;
        }

        .noti-badge {
            position: absolute;
            top: -5px;
            right: -8px;
            background: var(--danger-red);
            color: white;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 10px;
            border: 2px solid white;
            line-height: 1.1;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 15px 5px 5px;
            background: var(--primary-light);
            border-radius: 30px;
            cursor: pointer;
            transition: 0.2s;
        }

        .user-profile:hover {
            background: #e0dcf5;
        }

        .user-name {
            font-weight: 700;
            color: var(--primary);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        /* --- 2. MAIN CONTENT AREA --- */
        .main-wrapper {
            flex: 1;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 20px;
        }

        /* Grid to show both states side-by-side for the developer */
        .status-grid {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
        }


        .status-container {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            background: var(--white);
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            border: 1px solid var(--border-light);
        }

        .status-container {
            max-width: 450px;
            width: 100%;
        }

        /* Animated Icons */
        .status-icon-wrap {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 25px;
            animation: scaleIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        .success-icon {
            background-color: var(--success-green);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
        }

        .failure-icon {
            background-color: var(--danger-red);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        }

        .status-icon-wrap i {
            color: var(--white);
            font-size: 3rem;
            animation: checkFade 0.5s 0.3s forwards;
            opacity: 0;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0);
            }

            100% {
                transform: scale(1);
            }
        }

        @keyframes checkFade {
            0% {
                opacity: 0;
                transform: translateY(10px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Text Headings */
        .status-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .status-subtitle {
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 30px;
        }

        /* Detailed Invoice Card */
        .invoice-card {
            background: var(--white);
            width: 100%;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            margin-bottom: 25px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            text-align: left;
            /* Left align contents */
            border: 1px solid var(--border-light);
        }

        .invoice-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-dark);
        }

        .invoice-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 25px;
        }

        .invoice-row {
            font-size: 0.95rem;
            color: var(--text-dark);
        }

        .invoice-label {
            color: var(--text-muted);
        }

        /* Status Text Colors */
        .text-success {
            color: var(--success-green);
            font-weight: 600;
        }

        .text-danger {
            color: var(--danger-red);
            font-weight: 600;
        }

        /* Buttons inside and outside card */
        .btn-download {
            width: 100%;
            background-color: var(--blue-btn);
            color: var(--white);
            padding: 14px;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 700;
            transition: background 0.2s;
        }

        .btn-download:hover {
            background-color: var(--blue-hover);
        }

        .btn-retry {
            width: 100%;
            background-color: var(--white);
            color: var(--danger-red);
            border: 2px solid var(--danger-red);
            padding: 12px;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 700;
            transition: background 0.2s;
        }

        .btn-retry:hover {
            background-color: #fef2f2;
        }

        /* Back Button */
        .btn-back {
            background-color: var(--blue-btn);
            color: var(--white);
            padding: 12px 30px;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 700;
            transition: background 0.2s;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            min-width: 200px;
        }

        .btn-back:hover {
            background-color: var(--blue-hover);
        }


        /* --- 3. MOBILE HEADER & NAV --- */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: white;
            height: 70px;
            border-top: 1px solid #eee;
            justify-content: space-around;
            align-items: center;
            z-index: 1000;
            padding-bottom: 5px;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.03);
        }

        .nav-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #888;
            font-size: 0.75rem;
            gap: 5px;
            font-weight: 600;
            text-decoration: none;
        }

        .nav-icon i {
            font-size: 1.3rem;
        }

        .nav-icon.active {
            color: var(--primary);
        }

        .nav-icon.active .icon-wrap {
            background: var(--primary-light);
            padding: 5px 15px;
            border-radius: 20px;
        }

        .mobile-header {
            display: none;
            align-items: center;
            justify-content: center;
            height: 60px;
            background: white;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid #eee;
        }

        .mobile-header-title {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .mobile-back {
            position: absolute;
            left: 20px;
            font-size: 1.2rem;
            color: #333;
            cursor: pointer;
        }

        /* --- RESPONSIVE SETTINGS --- */
        @media (max-width: 900px) {
            header {
                display: none;
            }

            body {
                background-color: var(--bg-body);
                padding-bottom: 70px;
            }

            .main-wrapper {
                padding: 20px 15px;
            }

            /* Stack cards vertically on mobile */
            .status-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .status-container {
                padding: 30px 20px;
                border: none;
                box-shadow: none;
                background: transparent;
            }

            .invoice-card {
                padding: 20px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            }

            .status-title {
                font-size: 1.5rem;
            }

            .status-subtitle {
                font-size: 0.95rem;
                margin-bottom: 25px;
            }

            .bottom-nav {
                display: flex;
            }
        }
    </style>
</head>

<body>



    <main class="main-wrapper">

        <div class="status-grid">
            <!-- ============================Success Response=============================== -->
            <?php if ($isSuccess): ?>
                <div class="status-container success-state">

                    <div class="status-icon-wrap success-icon">
                        <i class="fas fa-check"></i>
                    </div>

                    <h1 class="status-title">Payment Successful!</h1>
                    <p class="status-subtitle">Thank you for your payment</p>

                    <div class="invoice-card">
                        <div class="invoice-header-row">
                            <span><?= $responseData['plan_name'] ?></span>
                            <span>₹<?= $responseData['amount_paid'] ?></span>
                        </div>

                        <div class="invoice-details">
                            <div class="invoice-row">
                                <span class="invoice-label">Invoice:</span> <?= $responseData['invoiceno'] ?>
                            </div>

                            <div class="invoice-row">
                                <span class="invoice-label">Date:</span> <?= $responseData['start_date'] ?>
                            </div>

                            <div class="invoice-row">
                                <span class="invoice-label">Status:</span>
                                <span class="text-success"><?= $responseData['payment_status'] ?></span>
                            </div>

                            <div class="invoice-row">
                                <span class="invoice-label">Validity:</span>
                                <?= $responseData['start_date'] ?> to <?= $responseData['end_date'] ?>
                            </div>
                        </div>

                        <!-- <button class="btn-download"
                            onclick="window.open('<?= DOMAIN_URL ?>webservices/genrateInvoicepdf.php?invoice_no=<?= $responseData['invoiceno'] ?>', '_blank')">
                            Download Invoice
                        </button> -->
                        <form method="GET" action="<?= DOMAIN_URL ?>webservices/genrateInvoicepdf.php">
                            <input type="hidden" name="invoice_no" value="<?= $responseData['invoiceno'] ?>">
                            <button type="submit" class="btn-download">
                                Download Invoice
                            </button>
                        </form>
                    </div>

                    <button class="btn-back" onclick="window.location.href='index.php'">Back to Home</button>

                </div>
            <?php endif; ?>

            <!-- ============================Failure Response=============================== -->
            <?php if (!$isSuccess): ?>
                <div class="status-container failure-state">

                    <div class="status-icon-wrap failure-icon">
                        <i class="fas fa-times"></i>
                    </div>

                    <h1 class="status-title">Payment Failed!</h1>
                    <p class="status-subtitle"><?= $message ?></p>
                    <div class="invoice-card">
                        <div class="invoice-header-row">
                            <!-- <span><?= $responseData['plan_name'] ?></span>
                            <span>₹<?= $responseData['amount_paid'] ?></span> -->
                        </div>

                        <div class="invoice-row">
                            <span class="invoice-label">Invoice:</span> <?= $responseData['invoiceno'] ?? 'N/A' ?>
                        </div>

                        <div class="invoice-row">
                            <span class="invoice-label">Date:</span> <?= $responseData['start_date'] ?? 'N/A' ?>
                        </div>

                        <div class="invoice-row">
                            <span class="invoice-label">Status:</span>
                            <span class="text-danger"><?= $responseData['payment_status']  ?? 'Failed'?></span>
                        </div>

                        <div class="invoice-row">
                            <span class="invoice-label">Validity:</span>
                            <?= $responseData['start_date'] ?> to <?= $responseData['end_date'] ?? 'N/A' ?>
                        </div>

                      <a href="subscription_plans.php">  <button class="btn-retry">Try Again</button></a>
                    </div>

                    <button class="btn-back" onclick="window.location.href='index.php'">Back to Home</button>

                </div>
        </div>
    <?php endif; ?>

    </div>

    </main>


    <script>
        const isSuccess = <?php echo json_encode($isSuccess); ?>;
        const message = <?php echo json_encode($message); ?>;

        window.onload = function() {

            if (isSuccess) {
                document.getElementById("successModal").style.display = "flex";
                document.getElementById("successText").innerText = message;
            } else {
                document.getElementById("errorModal").style.display = "flex";
                document.querySelector("#errorModal p").innerText = message;
            }
        };
    </script>
</body>

</html>