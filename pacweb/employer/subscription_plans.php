<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "../web_api/includes/db_config.php";
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
unset($_SESSION['payment_done']); 

$user = $_SESSION['user'];




$userid = $user['id'];
$profile_id = $user['profile_id'];
$profile_type_id = $user['profile_type_id'];
$jobid = $_POST['id'] ?? '';
// print_r($user);

$checkurl = API_BASE_URL . "checkUsersubscription.php";

$data = json_encode([
    "user_id" => $userid
]);

$ch = curl_init($checkurl);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

$responseone = curl_exec($ch);

if (curl_errno($ch)) {
    echo "Curl error: " . curl_error($ch);
}

curl_close($ch);

$result = json_decode($responseone, true);






//=============2.Get subscriptionplans==========
$getPlanUrl = API_BASE_URL . "getSubscriptionplans.php";
$getDataPayload = json_encode([
    "profile_type" => $profile_type_id
]);
$ch = curl_init($getPlanUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $getDataPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

$responsetwo = curl_exec($ch);

if (curl_errno($ch)) {
    echo "Curl error: " . curl_error($ch);
}

curl_close($ch);

$subplanresult = json_decode($responsetwo, true);


$bronze = $silver = $gold = [];

if (!empty($subplanresult['data'])) {
    foreach ($subplanresult['data'] as $plan) {
        if (strtolower($plan['plan_name']) == 'bronze') {
            $bronze = $plan;
        }
        if (strtolower($plan['plan_name']) == 'silver') {
            $silver = $plan;
        }
        if (strtolower($plan['plan_name']) == 'gold') {
            $gold = $plan;
        }
    }
}
// echo "<pre>";
// print_r($bronze['features']);
// print_r($silver['features']);
// print_r($gold['features']);
// exit;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade Plan | Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">
    <style>
        :root {
            --primary: #483EA8;
            --primary-dark: #322b7a;
            --primary-light: #eceaf9;
            --secondary: #ff6f00;
            --bg-body: #f4f6f9;
            --white: #ffffff;
            --text-dark: #1a1a1a;
            --text-grey: #555555;
            --green: #2ecc71;
            --red: #cc2e2e;
            --border-light: #e0e0e0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-dark);
            font-size: 15px;
            line-height: 1.6;
        }

        .subplan-wrapper .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* --- 1. WEBSITE HEADER --- */
        header {
            background: var(--white);
            height: 70px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            font-weight: 800;
            font-size: 1.3rem;
        }

        .brand i {
            font-size: 1.5rem;
        }

        .nav-menu {
            display: flex;
            gap: 25px;
        }

        .nav-item {
            font-weight: 600;
            color: #555;
            font-size: 1rem;
            text-decoration: none;
        }

        .nav-item:hover {
            color: var(--primary);
        }

        .nav-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .login-link {
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }

        .btn-header-cta {
            background: var(--primary);
            color: white;
            padding: 8px 22px;
            border-radius: 30px;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }

        /* --- 2. CURRENT PLAN STATUS BAR --- */
        .subplan-wrapper .status-section {
            padding: 40px 0 20px;
        }

        .current-plan-bar {
            background: white;
            border-radius: 12px;
            padding: 20px 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            /* border-left: 5px solid var(--green); */
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .bar-active {
            border-left: 5px solid var(--green);
        }

        .bar-inactive {
            border-left: 5px solid var(--red);
        }

        .cp-info h3 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--text-dark);
        }

        .cp-info p {
            margin: 0;
            font-size: 0.95rem;
            color: var(--text-grey);
            margin-top: 5px;
        }

        .subplan-wrapper .badge-active {
            background: #e8f5e9;
            color: var(--green);
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .subplan-wrapper .badge-inactive {
            background: #f5e8e8;
            color: var(--red);
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
        }

        /* --- 3. PRICING PLANS GRID --- */
        .pricing-section {
            padding: 20px 0 60px;
        }

        .sec-title {
            text-align: center;
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .sec-sub {
            text-align: center;
            color: var(--text-grey);
            margin-bottom: 40px;
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            align-items: start;
        }

        /* Card Design */
        .subplan-wrapper .plan-card {
            background: var(--white);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-light);
            position: relative;
            transition: 0.3s;
            display: flex;
            flex-direction: column;
        }

        .subplan-wrapper .plan-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }

        /* Highlighted Card (Gold) */
        .subplan-wrapper .plan-card.recommended {
            border: 2px solid var(--primary);
            transform: scale(1.05);
            /* Slightly bigger */
            z-index: 2;
        }

        .subplan-wrapper .plan-card.recommended:hover {
            transform: scale(1.05) translateY(-10px);
        }

        .ribbon {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--secondary);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            box-shadow: 0 4px 10px rgba(255, 111, 0, 0.3);
        }

        /* Card Content */
        .plan-header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 20px;
        }

        .plan-name {
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 10px;
            display: block;
        }

        .plan-price {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text-dark);
        }

        .plan-price small {
            font-size: 1rem;
            color: var(--text-grey);
            font-weight: 500;
        }

        .plan-duration {
            display: block;
            font-size: 0.9rem;
            color: var(--text-grey);
            margin-top: 5px;
        }

        /* Features List (USPs) */
        .features-list {
            list-style: none;
            margin-bottom: 30px;
            flex: 1;
        }

        .features-list li {
            margin-bottom: 12px;
            font-size: 0.95rem;
            color: #444;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .features-list li i {
            color: var(--green);
            font-size: 1rem;
        }

        .features-list li.disabled {
            color: #aaa;
            text-decoration: line-through;
        }

        .features-list li.disabled i {
            color: #ccc;
        }

        /* Button */
        .btn-plan {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-weight: 700;
            border: 1px solid var(--primary);
            background: transparent;
            color: var(--primary);
            cursor: pointer;
            transition: 0.2s;
            font-size: 1rem;
        }

        .btn-plan:hover {
            background: var(--primary-light);
        }

        .btn-plan.filled {
            background: var(--primary);
            color: white;
        }

        .btn-plan.filled:hover {
            background: var(--primary-dark);
        }

        /* Colors for Plan Names */
        .text-bronze {
            color: #cd7f32;
        }

        .text-silver {
            color: #7f8c8d;
        }

        .text-gold {
            color: #f1c40f;
        }

        /* Mobile */
        @media (max-width: 900px) {
            .nav-menu {
                display: none;
            }

            .plans-grid {
                grid-template-columns: 1fr;
                max-width: 400px;
                margin: 0 auto;
            }

            .subplan-wrapper .plan-card.recommended {
                transform: scale(1);
                border: 2px solid var(--primary);
            }

            .subplan-wrapper .plan-card.recommended:hover {
                transform: translateY(-5px);
            }
        }
    </style>
</head>

<body>


    <?php include "includes/preloader.php"; ?>
    <?php include "includes/header.php"; ?>



    <div class="subplan-wrapper">


        <div class="container status-section">
            <?php
            $status = strtolower($result['subscription_status'] ?? 'not active');

            $barClass = ($status === 'active') ? 'bar-active' : 'bar-inactive';
            ?>

            <div class="current-plan-bar <?= $barClass ?>">
                <div class="cp-info">
                    <h3>Current Plan: <b><?= $result['plan_name'] ?? 'N/A' ?></b></h3>
                    <p>
                        Your plan expires on
                        <b>
                            <?= !empty($result['valid_to'])
                                ? DateTime::createFromFormat('d-m-Y', $result['valid_to'])->format('d M, Y')
                                : 'N/A'; ?>
                        </b>
                    </p>
                </div>
                <?php
                $status = strtolower($result['subscription_status'] ?? 'Not Active');

                if ($status === 'active') {
                    $badgeClass = 'badge-active';
                    $icon = 'fa-check-circle';
                } else {
                    $badgeClass = 'badge-inactive';
                    $icon = 'fa-times-circle';
                }
                ?>

                <div class="<?= $badgeClass ?>">
                    <i class="fas <?= $icon ?>"></i>
                    <?= ucfirst($status) ?>
                </div>
            </div>
        </div>

        <div class="container pricing-section">
            <h2 class="sec-title">Choose Your Upgrade</h2>
            <p class="sec-sub">Unlock premium features to get hired faster.</p>

            <div class="plans-grid">


                <!-- ===============bronze card ============== -->
                <div class="plan-card">
                    <div class="plan-header">
                        <span class="plan-name text-bronze">BRONZE</span>
                        <div class="plan-price">
                            ₹<?= number_format($bronze['final_amount'] ?? 0) ?>
                            <small>/<?= $bronze['validity_months'] ?? 0 ?> mo</small>
                        </div>
                        <span class="plan-duration">Quarterly Plan</span>
                    </div>

                    <ul class="features-list">
                        <?php if (!empty($bronze['features'])): ?>
                            <?php foreach ($bronze['features'] as $f): ?>
                                <li><i class="fas fa-check"></i> <?= htmlspecialchars($f) ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>

                    <form method="POST" action="checkout-plans.php">
                        <input type="hidden" name="plan_name" value="Bronze">

                        <button type="submit" class="btn-plan">Choose Bronze</button>
                    </form>
                </div>

                <!-- ===============Gold card ============== -->
                <div class="plan-card recommended">
                    <div class="ribbon">Best Value</div>
                    <div class="plan-header">
                        <span class="plan-name text-gold">GOLD</span>
                        <div class="plan-price">
                            ₹<?= number_format($gold['final_amount'] ?? 0) ?>
                            <small>/<?= $gold['validity_months'] ?? 0 ?> mo</small>
                        </div>
                        <span class="plan-duration">Annual Plan</span>
                    </div>

                    <ul class="features-list">
                        <?php if (!empty($gold['features'])): ?>
                            <?php foreach ($gold['features'] as $f): ?>
                                <li><i class="fas fa-check"></i> <?= htmlspecialchars($f) ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>

                    <form method="POST" action="checkout-plans.php">
                        <input type="hidden" name="plan_name" value="Gold">

                        <button type="submit" class="btn-plan filled">Upgrade to Gold</button>
                    </form>
                </div>

                <!-- ===============Silver card ============== -->
                <div class="plan-card">
                    <div class="plan-header">
                        <span class="plan-name text-silver">SILVER</span>
                        <div class="plan-price">
                            ₹<?= number_format($silver['final_amount'] ?? 0) ?>
                            <small>/<?= $silver['validity_months'] ?? 0 ?> mo</small>
                        </div>
                        <span class="plan-duration">Half-Yearly Plan</span>
                    </div>

                    <ul class="features-list">
                        <?php if (!empty($silver['features'])): ?>
                            <?php foreach ($silver['features'] as $f): ?>
                                <li><i class="fas fa-check"></i> <?= htmlspecialchars($f) ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>

                    <form method="POST" action="checkout-plans.php">
                        <input type="hidden" name="plan_name" value="Silver">

                        <button type="submit" class="btn-plan">Choose Silver</button>
                    </form>
                </div>

            </div>
        </div>

    </div>
    <?php include "includes/bottom-bar.php"; ?>
    <script>
        window.onload = () => document.getElementById("global-preloader")?.remove();
    </script>
</body>

</html>