<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/includes/session.php';

require_once __DIR__ . '/includes/db_config.php';
$userid = $_SESSION['user_id'] ?? 0;
$profile_type_id = (int)$_SESSION['user']['profile_type_id'];

// $userid = 1;
/* ===============================
   âœ… LOGIN CHECK
================================ */
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// SESSION se user id le

// API URL
$trans_url = API_WEB_URL . "getWallet_transactions.php";
// $trans_url = "https://pacificconnect2.0.inv51.in/webservices/getWallet_transactions.php";

// POST DATA
$postData = json_encode([
    "user_id" => $userid
]);

// cURL init
$ch = curl_init($trans_url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "Curl Error: " . curl_error($ch);
}

curl_close($ch);

// JSON decode
$data = json_decode($response, true);


// transactions array
$transactions = $data['transactions'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History | Pacific iConnect</title>
    <link rel="stylesheet" href="/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #483EA8;
            --primary-light: #eceaf9;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --bg-body: #f8fafc;
            --white: #ffffff;
            --border: #e2e8f0;
            --danger: #d32f2f;
            --success: #2e7d32;
            --warning: #ed6c02;
        }



        /* --- MAIN CONTENT --- */
        .trans-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title {
            font-size: 1.3rem;
            font-weight: 800;
            border-left: 4px solid var(--primary);
            padding-left: 15px;
        }

        /* --- MONTH GROUPING HEADER --- */
        .month-group-header {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 30px 0 15px 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .month-group-header::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* --- TRANSACTION CARDS --- */
        .txn-card {
            background: var(--white);
            border-radius: 12px;
            padding: 15px 25px;
            margin-bottom: 12px;
            border: 1px solid var(--border);
            display: grid;
            grid-template-columns: 50px 1fr 1fr 150px;
            align-items: center;
            transition: 0.2s;
        }

        .txn-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .status-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .bg-pending {
            background: #fff4e5;
            color: var(--warning);
        }

        .bg-success {
            background: #edf7ed;
            color: var(--success);
        }

        .bg-failed {
            background: #fdecea;
            color: #d32f2f;
        }

        .txn-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: #334155;
        }

        .txn-date {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .txn-amt {
            font-size: 1rem;
            font-weight: 800;
            text-align: right;
        }

        .neg {
            color: var(--danger);
        }

        .pos {
            color: var(--success);
        }

        @media (max-width: 768px) {
            header {
                padding: 0 20px;
            }

            .desktop-nav {
                display: none;
            }

            .txn-card {
                grid-template-columns: 45px 1fr 110px;
                gap: 8px;
                padding: 12px 15px;
            }

            .txn-date {
                grid-column: 2;
            }

            .txn-amt {
                grid-row: 1 / span 2;
                grid-column: 3;
                align-self: center;
            }
        }
    </style>
</head>

<body>

     <?php include "includes/preloader.php"; ?> <?php if ($profile_type_id == 3)
    { include "includes/promoter_header.php"; } else { include
    "includes/header.php"; } ?>

    <div class="trans-container">
        <div class="page-header">
            <i class="fas fa-arrow-left" onclick="window.history.back()" style="cursor:pointer"></i>
            <h1 class="page-title">Transaction History</h1>
        </div>

        <?php if (empty($transactions)): ?>

            <div style="text-align:center; margin-top:60px; color:#64748b;">
                <i class="fas fa-receipt" style="font-size:40px; margin-bottom:10px;"></i>
                <p style="font-weight:600;">No transactions found</p>
            </div>

        <?php else: ?>

            <?php foreach ($transactions as $month => $txnList): ?>

                <!-- Month Header -->
                <div class="month-group-header"><?php echo $month; ?></div>

                <?php foreach ($txnList as $txn):

                    $isCredit = ($txn['transaction_type'] == 1);

                    // status
                    // $statusClass = ($txn['status'] == 1) ? "bg-success" : "bg-pending";
                    // $icon = ($txn['status'] == 1) ? "fa-check" : "fa-clock";



                    // NEW ðŸ‘‡
                    if ($txn['status'] == 1) {
                        // SUCCESS
                        $statusClass = "bg-success";
                        $icon = "fa-check";
                    } elseif ($txn['status'] == 2) {
                        // PENDING
                        $statusClass = "bg-pending";
                        $icon = "fa-clock";
                    } else {
                        // FAILED
                        $statusClass = "bg-failed";
                        $icon = "fa-times";
                    }

                    $amountClass = $isCredit ? "pos" : "neg";
                    $sign = $isCredit ? "+" : "-";
                ?>

                    <div class="txn-card">
                        <div class="status-icon <?php echo $statusClass; ?>">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>

                        <div class="txn-name">
                            <?php echo $txn['remark']; ?>
                        </div>

                        <div class="txn-date">
                            <?php echo $txn['transaction_datetime']; ?>
                        </div>

                        <div class="txn-amt <?php echo $amountClass; ?>">
                            <?php echo $sign; ?> â‚¹<?php echo $txn['amount']; ?>
                        </div>
                    </div>

                <?php endforeach; ?>

            <?php endforeach; ?>

        <?php endif; ?>
    </div>

</body>

</html>