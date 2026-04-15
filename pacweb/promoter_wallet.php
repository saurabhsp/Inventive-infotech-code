<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db_config.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['is_logged_in'])) {
  header("Location: login.php");
  exit();
}
$profile_type_id = (int)$_SESSION['user']['profile_type_id'];
$user_id = $_SESSION['user_id'];

$url = API_WEB_URL . "getWalletbalance.php";

$data = [
  "user_id" => $user_id
];

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Content-Type: application/json"
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
print_r($result );exit;
// ✅ DEFAULT VALUES
$wallet_balance = "0.00";
$total_cashback_earned = "0.00";
$total_referrals = 0;

// ✅ CHECK STATUS
if (!empty($result['status'])) {
  $wallet_balance = $result['wallet_balance'] ?? "0.00";
  $total_cashback_earned = $result['total_cashback_earned'] ?? "0.00";
  $total_referrals = $result['total_referrals'] ?? 0;
}
?>


<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Wallet</title>

  <link rel="stylesheet" href="/style.css" />
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    rel="stylesheet" />

  <style>
    /* PAGE BACKGROUND */
    body {
      background: linear-gradient(135deg, #eef2f7, #dde6f2);
      font-family: "Segoe UI", sans-serif;
    }

    /* MAIN WRAPPER */
    .wallet-wrapper {
      max-width: 1100px;
      margin: 30px auto;
      padding: 20px;
    }

    /* HEADING */
    .wallet-heading {
      text-align: center;
      font-size: 28px;
      font-weight: 600;
      margin-bottom: 25px;
    }

    /* GRID LAYOUT */
    .wallet-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    /* FULL WIDTH */
    .full-width {
      grid-column: span 2;
    }

    /* CARD */
    .wallet-card {
      background: #fff;
      border-radius: 18px;
      padding: 22px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    }

    /* HIGHLIGHT BOX */
    .highlight {
      background: #fff8e6;
      border: 1px solid #ffe0a3;
      text-align: center;
    }

    .highlight h3 {
      margin: 0;
      color: #c68a00;
    }

    .highlight p {
      margin-top: 8px;
    }

    /* BALANCE */
    .balance {
      text-align: center;
    }

    .balance h1 {
      font-size: 40px;
      margin: 0;
    }

    .balance p {
      color: #777;
    }

    /* ROW */
    .row {
      display: flex;
      justify-content: space-between;
      padding: 14px 0;
      border-bottom: 1px solid #eee;
    }

    .row:last-child {
      border-bottom: none;
    }

    /* BUTTON */
    .btn {
      display: block;
      width: fit-content;
      /* key */
      margin: 15px auto;
      /* centers horizontally */
      padding: 16px 40px;
      /* better spacing */
      border-radius: 14px;
      background: linear-gradient(135deg, #00c853, #64dd17);
      color: #fff;
      font-weight: bold;
      text-decoration: none;
      transition: 0.3s;
    }

    .btn:hover {
      transform: translateY(-2px);
    }

    /* RIGHT PANEL */
    .side-panel {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }

    /* LIST ITEMS */
    .list-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px;
      border-radius: 14px;
      background: #fff;
      box-shadow: 0 5px 12px rgba(0, 0, 0, 0.06);
      cursor: pointer;
      transition: 0.2s;
    }

    .list-item:hover {
      background: #f5f7fb;
      transform: translateX(3px);
    }

    /* SMALL TEXT */
    .small-text {
      font-size: 13px;
      color: #666;
      text-align: center;
      margin-top: 10px;
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
      .wallet-grid {
        grid-template-columns: 1fr;
      }

      .full-width {
        grid-column: span 1;
      }
    }
  </style>
</head>

<body>
  <?php include "includes/preloader.php"; ?> <?php include
                                                "includes/promoter_header.php"; ?>

  <div class="wallet-wrapper">
    <div class="wallet-heading">My Wallet</div>

    <div class="wallet-grid">
      <!-- Highlight -->
      <div class="wallet-card highlight full-width">
        <h3>Every Rupee Counts!</h3>
        <p>Refer more friends and grow your wallet. You're doing great!</p>
      </div>

      <!-- LEFT SIDE -->
      <div class="wallet-card balance">
        <h1>₹<?= $wallet_balance ?></h1>
        <p>Wallet Balance</p>
      </div>

      <!-- RIGHT SIDE -->
      <div class="wallet-card">
        <div class="row">
          <span>Referral Cashback</span>
          <span>₹<?= $total_cashback_earned ?></span>
        </div>
        <div class="row">
          <span>Referrals Converted</span>
          <span><?= $total_referrals ?></span>
        </div>
      </div>

      <!-- BUTTON + INFO -->
      <div class="wallet-card full-width">
        <div class="small-text">
          Cashback is credited when your referrals sign up and qualify.<br />
          Keep sharing!
        </div>

        <a href="bank_withdrawl.php" class="btn">Withdraw to Bank</a>
      </div>

      <!-- SIDE OPTIONS -->
      <div class="wallet-card full-width side-panel">
        <a href="transaction_history.php">
          <div class="list-item">
            <span>Transaction History</span>
            <i class="fas fa-chevron-right"></i>
          </div>
        </a>

        <a href="#">
          <div class="list-item">
            <span>Get Help</span>
            <i class="fas fa-chevron-right"></i>
          </div>
        </a>
      </div>
    </div>
  </div>
</body>

</html>