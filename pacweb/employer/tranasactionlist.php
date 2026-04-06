<?php
session_start();
require_once "../web_api/includes/db_config.php";
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
$user = $_SESSION['user'];

$userid = $user['id'] ?? 0;

/* API CALL */
$api_url = API_BASE_URL . "getTransactionlist.php";

$request = [
    "userid" => $userid
];

$ch = curl_init($api_url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($request),
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

$transactions = $result['data'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History</title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">



    <style>
        :root {
            --primary: #483EA8;
            --blue-btn: #1d4ed8;
            --success-green: #22c55e;
            --text-dark: #1a1a1a;
            --text-muted: #666;
            --border-light: #e5e7eb;
            --bg-body: #f4f6f9;
            --white: #ffffff;
        }

        /* MOBILE HEADER */
        .mobile-header {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 60px;
            background: white;
            position: sticky;
            top: 0;
            border-bottom: 1px solid #eee;
            font-weight: 700;
        }

        .mobile-back {
            position: absolute;
            left: 15px;
            font-size: 18px;
        }

        /* WRAPPER TO ISOLATE CSS */
        .transaction-page {
            background: var(--bg-body);
        }

        /* CONTAINER */
        .transaction-page .transaction-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* CARD */
        .transaction-page .transaction-card {
            background: white;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 18px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        /* TOP */
        .transaction-page .transaction-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
            font-size: 16px;
        }

        /* ðŸ”´ FIXED (renamed from .amount) */
        .transaction-page .txn-amount {
            font-weight: 800;
        }

        /* DETAILS */
        .transaction-page .transaction-details {
            margin-top: 10px;
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* STATUS */
        .transaction-page .status {
            color: var(--success-green);
            font-weight: 600;
        }

     

        /* HEADER SECTION */
.transaction-page .txn-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 10px;
}

/* TITLE */
.transaction-page .txn-page-title {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--text-dark);
    border-left: 5px solid var(--blue-btn);
    padding-left: 12px;
}

/* BACK BUTTON */
.transaction-page .btn-back-profile {
    background: var(--primary);
    color: white;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: 0.2s;
}

.transaction-page .btn-back-profile:hover {
    background: #322b7a;
}


/* RIGHT SIDE (amount + icon) */
.transaction-page .txn-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* SMALL DOWNLOAD ICON */
.transaction-page .txn-download-link {
    color: var(--blue-btn);
    font-size: 14px;
    border: 1px solid var(--blue-btn);
    padding: 6px 8px;
    border-radius: 6px;
    transition: 0.2s;
}

.transaction-page .txn-download-link:hover {
    background: var(--blue-btn);
    color: white;
}
    </style>
</head>

<body>

    <?php include "includes/preloader.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="transaction-page">



       <div class="transaction-container">

    <!-- PAGE HEADER -->
    <div class="txn-page-header">
        <h1 class="txn-page-title">Transaction History</h1>

        <a href="my_profile.php" class="btn-back-profile">
            <i class="fas fa-arrow-left"></i> Back to Profile
        </a>
    </div>

            <?php foreach ($transactions as $txn) { ?>

                <div class="transaction-card">

                    <div class="transaction-top">
    <span><?= $txn['plan_name'] ?></span>

    <div class="txn-right">
        <span class="txn-amount">â‚¹<?= $txn['amount_paid'] ?></span>

        <a 
            href="<?= DOMAIN_URL ?>/webservices/genrateInvoicepdf.php?invoice_no=<?= urlencode($txn['invoiceno']) ?>"
            target="_blank"
            class="txn-download-link"
        >
            Download Invoice <i class="fas fa-download"></i>
        </a>
    </div>
</div>

                    <div class="transaction-details">
                        Invoice: <?= $txn['invoiceno'] ?> <br>
                        Date: <?= $txn['invoice_date'] ?> <br>
                        Status: <span class="status"><?= $txn['payment_status'] ?></span> <br>
                        Validity: <?= $txn['validity'] ?>
                    </div>


                </div>

            <?php } ?>

        </div>

    </div>

    <?php include "includes/bottom-bar.php"; ?>
    <script>
        window.onload = () => document.getElementById("global-preloader")?.remove();
    </script>

</body>

</html>