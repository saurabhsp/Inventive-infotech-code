<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/includes/session.php';

require_once __DIR__ . '/includes/db_config.php';
$userid = $_SESSION['user_id'] ?? 0;
$profile_type_id = (int)$_SESSION['user']['profile_type_id'];

// $userid = 5;
/* ===============================
   ✅ LOGIN CHECK
================================ */
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// SESSION se user id le

// API URL
$ref_url = API_WEB_URL . "getReferrallist.php";
// $ref_url = "https://pacificconnect2.0.inv51.in/webservices/getReferrallist.php";

// POST DATA
$postData = json_encode([
    "user_id" => $userid
]);

$ch = curl_init($ref_url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "Curl error: " . curl_error($ch);
    exit;
}

curl_close($ch);

$result = json_decode($response, true);
// print_r($result);
$total_referrals = $result['total_referrals'] ?? 0;
$total_amount    = $result['total_amount'] ?? "0.00";
$referrals       = $result['data'] ?? [];

$grouped_referrals = [];

foreach ($referrals as $ref) {
    // convert date
    $date = DateTime::createFromFormat('d-m-Y h:i A', $ref['created_at']);

    if ($date) {
        $monthKey = $date->format('F Y'); // August 2025
    } else {
        $monthKey = 'Unknown';
    }

    $grouped_referrals[$monthKey][] = $ref;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Cashback | Pacific iConnect</title>
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
            --success: #2e7d32;
        }


        /* --- MAIN CONTAINER --- */
        .main-container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 25px;
        }

        .page-title {
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 25px;
            border-left: 5px solid var(--primary);
            padding-left: 15px;
            color: #2d3748;
        }

        /* --- SUMMARY BAR --- */
        .summary-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        }

        .ref-stat-box {
            font-size: 0.95rem;
            font-weight: 700;
            color: #4a5568;
        }

        .stat-count {
            color: var(--primary);
        }

        .stat-earn {
            color: var(--success);
        }

        /* --- MONTH SEPARATOR --- */
        .month-divider {
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin: 30px 0 15px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .month-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* --- REFERRAL CARDS --- */
        .cashback-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 15px 25px;
            margin-bottom: 10px;
            display: grid;
            grid-template-columns: 120px 1fr 180px;
            /* Precise Tabular Alignment */
            align-items: center;
            transition: 0.2s ease-in-out;
        }

        .cashback-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .cb-amount {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--success);
        }

        .cb-info b {
            font-size: 0.95rem;
            display: block;
            margin-bottom: 2px;
            color: #2d3748;
        }

        .cb-info span {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .cb-date {
            text-align: right;
            font-size: 0.8rem;
            color: #a0aec0;
            font-weight: 600;
        }

        /* --- MOBILE OPTIMIZATION --- */
        @media (max-width: 768px) {
            header {
                padding: 0 15px;
            }

            .desktop-nav {
                display: none;
            }

            .main-container {
                margin: 15px auto;
                padding: 0 15px;
            }

            .page-title {
                font-size: 1.2rem;
            }

            .summary-card {
                padding: 12px 15px;
                font-size: 0.85rem;
            }

            .ref-stat-box {
                font-size: 0.85rem;
            }

            .cashback-card {
                grid-template-columns: 1fr;
                gap: 8px;
                padding: 15px;
            }

            .cb-amount {
                font-size: 1.05rem;
            }

            .cb-info b {
                font-size: 0.85rem;
            }

            .cb-info span {
                font-size: 0.75rem;
            }

            .cb-date {
                text-align: left;
                margin-top: 5px;
                font-size: 0.75rem;
            }
        }
    </style>
</head>

<body>

 <?php include "includes/preloader.php"; ?> <?php if ($profile_type_id == 3)
    { include "includes/promoter_header.php"; } else { include
    "includes/header.php"; } ?>

    <div class="main-container">
        <h1 class="page-title">Referral Cashback</h1>

        <div class="summary-card">
            <div class="ref-stat-box">Total Referrals: <span class="stat-count"><?= $total_referrals  ?></span></div>
            <div class="ref-stat-box">Total Earned: <span class="stat-earn"><?= $total_amount  ?></span></div>
        </div>



        <?php if (!empty($grouped_referrals)): ?>

            <?php foreach ($grouped_referrals as $month => $items): ?>

                <!-- MONTH DIVIDER -->
                <div class="month-divider"><?php echo $month; ?></div>

                <?php foreach ($items as $ref): ?>

                    <div class="cashback-card">
                        <div class="cb-amount">₹<?php echo $ref['amount']; ?></div>

                        <div class="cb-info">
                            <b><?php echo $ref['remark']; ?></b>
                            <span>Referred: <?php echo $ref['referred_name']; ?></span>
                        </div>

                        <div class="cb-date"><?php echo $ref['created_at']; ?></div>
                    </div>

                <?php endforeach; ?>

            <?php endforeach; ?>

        <?php else: ?>
            <p>No referrals found</p>
        <?php endif; ?>

    </div>

</body>

</html>