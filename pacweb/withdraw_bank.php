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


$profile_id = $_SESSION['user']['profile_id'] ?? 0;
$profile_type_id = $_SESSION['user']['profile_type_id'] ?? 0;

$pan_no = "";

// API URL
$url = API_WEB_URL . "webservices/getPAN.php";

$data = [
  "profile_id" => $profile_id,
  "profile_type_id" => $profile_type_id
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

// âœ… SUCCESS CHECK
if (!empty($result['status'])) {
  $pan_no = $result['pan_no'] ?? "";
}


// âœ… HANDLE PAN UPDATE
if (isset($_POST['update_pan'])) {

  $pan_input = trim($_POST['pan_no']);

  $update_url = API_WEB_URL . "webservices/updatePAN.php";

  $postData = [
    "profile_id" => $profile_id,
    "profile_type_id" => $profile_type_id,
    "pan_no" => $pan_input
  ];

  $ch = curl_init($update_url);

  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  $result = json_decode($response, true);

  if (!empty($result['status'])) {
    $_SESSION['pan_message'] = $result['message'];
    $_SESSION['pan_status'] = "success";
  } else {
    $_SESSION['pan_message'] = $result['message'] ?? "Update failed";
    $_SESSION['pan_status'] = "error";
  }

  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}




$url = "https://pacificconnect2.0.inv51.in/webservices/getUserPayoutAccounts.php";

$data = [
  "userid" => 1
];

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);

// JSON body
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

// Headers
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Content-Type: application/json"
]);

$response = curl_exec($ch);

// Error handling
if (curl_errno($ch)) {
  echo "Curl Error: " . curl_error($ch);
} else {
  $result = json_decode($response, true);
  $tips = $result['tips'];
  $withdrawal = $tips['withdrawal'];

  // echo "<pre>";
  // print_r($result);
  // echo "</pre>";
}

curl_close($ch);



?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Wallet Withdrawal</title>
  <link rel="stylesheet" href="/style.css" />
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    rel="stylesheet" />

  <style>
    .bank-container {
      max-width: 1100px;
      margin: auto;
    }

    .bank-card {
      background: #fff;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
      margin-bottom: 20px;
    }

    .bank-header {
      background: #dce6f5;
      text-align: center;
      font-weight: bold;
      font-size: 20px;
      padding: 20px;
      border-radius: 12px;
      color: #1a3d7c;
      margin: 20px 0;
    }

    .notice {
      background: #fff6d9;
      border-radius: 10px;
      padding: 15px;
      color: #6b5500;
      border: 1px solid #f0d98c;
    }

    .form-group {
      margin-bottom: 15px;
    }

    label {
      display: block;
      margin-bottom: 5px;
      font-weight: 600;
    }

    input {
      width: 100%;
      padding: 12px;
      border-radius: 8px;
      border: 1px solid #ccc;
      font-size: 14px;
    }

    .account-row input {
      width: 0%;
    }

    .tips {
      background: #fff6d9;
      padding: 15px;
      border-radius: 10px;
      border: 1px solid #f0d98c;
      color: #6b5500;
    }

    .tips ul {
      padding-left: 18px;
      margin: 10px 0;
    }

    .btn {
      width: 100%;
      padding: 14px;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      cursor: pointer;
      margin-top: 10px;
    }

    .btn-primary {
      background: #3b63b5;
      color: white;
    }

    .btn-secondary {
      background: #fff;
      border: 2px solid #3b63b5;
      color: #3b63b5;
    }

    .section-title {
      text-align: center;
      background: #e5edf8;
      padding: 12px;
      border-radius: 8px;
      font-weight: bold;
      margin-top: 20px;
    }

    /* âœ… Desktop Layout */
    @media (min-width: 768px) {
      .flex {
        display: flex;
        gap: 20px;
      }

      .left,
      .right {
        flex: 1;
      }
    }

    .account-list-vertical {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .account-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 8px;
      cursor: pointer;
      background: #fff;
      transition: 0.2s;
    }

    /* hover */
    .account-row:hover {
      background: #f5f8ff;
    }

    /* radio */
    .account-row input {
      accent-color: #2d5cff;
      transform: scale(1.2);
    }

    /* info */
    .account-info {
      flex: 1;
    }

    /* top row */
    .top-line {
      display: flex;
      justify-content: space-between;
      font-weight: 600;
    }

    .name {
      color: #222;
    }

    .type {
      font-size: 12px;
      background: #eef3ff;
      padding: 2px 8px;
      border-radius: 6px;
      color: #2d5cff;
    }

    /* detail */
    .detail {
      font-size: 13px;
      color: #555;
      margin-top: 4px;
    }

    /* selected highlight */
    /* .account-row input:checked+.account-info {
      border-left: 4px solid #2d5cff;
      padding-left: 10px;
    } */
    .account-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border: 1px solid #ddd;
  border-radius: 10px;
  cursor: pointer;
  background: #fff;
  transition: 0.2s;
}

/* radio button */
.account-row input[type="radio"] {
  width: auto;
  margin: 0;
  accent-color: #2d5cff;
  transform: scale(1.2);
}

/* selected full box */
.account-row:has(input:checked) {
  background-color: #e5ebff;
  border: 1px solid #2d5cff;
}

/* text styling */
.account-info {
  flex: 1;
}

    /* FULL ROW SELECTED */
    .account-row input:checked {
      accent-color: #2d5cff;
    }

    /* ðŸ”¥ IMPORTANT: full label highlight */
    .account-row:has(input:checked) {
      background-color: #e5ebff;
      border-color: #2d5cff;
    }
  </style>
</head>

<body>
  <?php if (!empty($_SESSION['success'])): ?>
    <div style="color: green;">
      <?= $_SESSION['success']; ?>
    </div>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>

  <?php if (!empty($_SESSION['error'])): ?>
    <div style="color: red;">
      <?= $_SESSION['error']; ?>
    </div>
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>


  <?php include "includes/preloader.php"; ?> <?php if ($profile_type_id == 3) {
                                                include "includes/promoter_header.php";
                                              } else {
                                                include
                                                  "includes/header.php";
                                              } ?>
  <div class="bank-container">
    <div class="bank-header">Your Wallet Balance</div>

    <div class="bank-card notice">
      <h3><?= $tips['minimum_amount']['title'] ?></h3>
      <p>
        <?= $tips['minimum_amount']['message'] ?>
      </p>
      <p>
        <strong> <?= $tips['minimum_amount']['example'] ?></strong>
      </p>
    </div>

    <div class="flex">
      <form method="POST">

        <!-- LEFT SIDE -->
        <div class="left bank-card">
          <div class="form-group">
            <label>Amount :</label>
            <input type="text" placeholder="Enter withdrawal amount" />
          </div>


          <div class="form-group">
            <label>PAN Number :</label>
            <input type="text" name="pan_no" value="<?= $pan_no ?>" placeholder="Enter PAN Number" />
          </div>

          <div class="form-group">
            <label>Choose account for withdrawal</label>

            <?php if (!empty($result['data'])): ?>

              <div class="account-list-vertical">

                <?php foreach ($result['data'] as $index => $acc): ?>

                  <label class="account-row">

                    <input
                      type="radio"
                      name="account"
                      value="<?= $acc['id'] ?>"
                      <?= $index == 0 ? 'checked' : '' ?>>

                    <div class="account-info">

                      <div class="top-line">
                        <span class="name"><?= $acc['name'] ?></span>
                        <span class="type"><?= $acc['ac_type_name'] ?></span>
                      </div>

                      <?php if ($acc['ac_type_name'] == "UPI"): ?>
                        <div class="detail"><?= $acc['vpa'] ?></div>
                      <?php else: ?>
                        <div class="detail">
                          A/C: <?= $acc['account_no'] ?> | IFSC: <?= $acc['ifsc'] ?>
                        </div>
                      <?php endif; ?>

                    </div>

                  </label>

                <?php endforeach; ?>

              </div>

            <?php else: ?>
              <p style="color:red;">No accounts available</p>
            <?php endif; ?>
          </div>

          <button type="submit" name="update_pan" class="btn btn-primary">
            Proceed to Withdraw</button>
          <a href="add-beneficiary.php"><button class="btn btn-secondary">+ Add New Beneficiary</button></a>
        </div>

      </form>
      <!-- RIGHT SIDE -->
      <div class="right bank-card">
        <div class="tips">
          <h3><?= $withdrawal['title'] ?></h3>

          <ul>
            <?php if (!empty($withdrawal['points'])): ?>
              <?php foreach ($withdrawal['points'] as $point): ?>
                <li><?= $point ?></li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>

          <p><strong>Example:</strong> <?= $withdrawal['example'] ?></p>
        </div>
      </div>
    </div>

    <div class="section-title">Transaction History</div>
  </div>
</body>

</html>