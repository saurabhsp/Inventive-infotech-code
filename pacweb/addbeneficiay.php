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
   âœ… LOGIN CHECK
================================ */
if (empty($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}






if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name   = $_POST['name'] ?? "";
    $mobile = $_POST['mobile'] ?? "";

    // Bank fields
    $accountNo = $_POST['account_number'] ?? "";
    $ifsc      = $_POST['ifsc'] ?? "";

    // UPI field
    $vpa = $_POST['vpa'] ?? "";

    // ✅ LOGIC (CORE)
    if (!empty($vpa)) {
        // UPI selected
        $accountNo = "";
        $ifsc = "";
    } else {
        // Bank selected
        $vpa = "";
    }

    // ✅ FINAL DATA
    $data = [
        "userid"    => $userid,
        "name"      => $name,
        "mobile"    => $mobile,
        "accountNo" => $accountNo,
        "ifsc"      => $ifsc,
        "vpa"       => $vpa,
        "email"     => ""
    ];

    // API URL
    $url = API_WEB_URL . "add_beneficiary.php";

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    // print_r( $result);exit;

    // ✅ RESPONSE HANDLE
    if (isset($result['status']) && $result['status'] == true) {
        $_SESSION['success'] = $result['message'] ?? "Success";
    } else {
        $_SESSION['error'] = $result['message'] ?? "Something went wrong";
    }

    header("Location: bank_withdrawl.php");
    exit;
}

?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Refer & Earn | Pacific iConnect</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/style.css">

  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f6f9;
      margin: 0;
    }

    /* benf-container */
    .benf-container {
      max-width: 1000px;
      margin: 40px auto;
      background: #fff;
      padding: 30px 40px;
      border-radius: 12px;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    }

    /* benf-header */
    .benf-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }

    .benf-header h2 {
      margin: 0;
    }

    /* Dropdown */
    .select-box {
      width: 100%;
      margin-bottom: 20px;
    }

    select {
      width: 100%;
      padding: 12px;
      border-radius: 8px;
      border: 1px solid #ccc;
      font-size: 15px;
    }

    /* Info box */
    .benf-info-box {
      background: #eaf2fb;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: none;
    }

    /* Form grid */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    /* Full width */
    .full {
      grid-column: span 2;
    }

    /* Inputs */
    input {
      width: 100%;
      padding: 12px;
      border-radius: 8px;
      border: 1px solid #ccc;
    }

    /* Button */
    .btn {
      margin-top: 25px;
      width: 100%;
      padding: 14px;
      background: #3b5fbf;
      color: white;
      border: none;
      border-radius: 30px;
      font-size: 16px;
      cursor: pointer;
    }

    .btn:hover {
      background: #2e4ca3;
    }

    /* Hide forms */
    .hidden {
      display: none;
    }

    .field {
      width: 100%;
    }

    .field input {
      width: 100%;
      padding: 14px;
      border-radius: 10px;
      border: 1px solid #ccc;
      box-sizing: border-box;
    }

    input {
      box-sizing: border-box;
      /* ðŸ”¥ VERY IMPORTANT */
    }

    .benf-info-box ul {
      list-style: none;
      /* remove default bullets */
      padding-left: 0;
      /* remove extra space */
      margin: 10px 0 0 0;
    }

    .benf-info-box li {
      position: relative;
      padding-left: 18px;
      /* space for custom bullet */
      margin-bottom: 6px;
      font-size: 14px;
      color: #1e293b;
    }

    .benf-info-box li::before {
      content: "â€¢";
      position: absolute;
      left: 0;
      top: 0;
      color: #1e293b;
      font-size: 16px;
      line-height: 1;
    }
  </style>
</head>

<body>
  <?php include "includes/preloader.php"; ?>
  <?php
  if ($profile_type_id == 3) {
    include "includes/promoter_header.php";
  } else {
    include "includes/header.php";
  }
  ?>
  <div class="benf-container">
    <div class="benf-header">
      <h2>Add New Beneficiary</h2>
    </div>

    <!-- Dropdown -->
    <div class="select-box">
      <select id="type" onchange="changeType()">
        <option value="">Select Beneficiary Type</option>
        <option value="bank">Add Bank Account</option>
        <option value="upi">Add UPI ID</option>
      </select>
    </div>

    <!-- BANK INFO -->
    <div id="bankInfo" class="benf-info-box">
      <b>Bank Tips:</b>
      <ul>
        <li>Name should match bank account</li>
        <li>Check account number & IFSC</li>
        <li>Use active account only</li>
      </ul>
    </div>

    <!-- UPI INFO -->
    <div id="upiInfo" class="benf-info-box">
      <b>UPI Tips:</b>
      <ul>
        <li>UPI must be active</li>
        <li>Avoid merchant UPI IDs</li>
      </ul>
    </div>

    <!-- BANK FORM -->
    <form id="bankForm" class="hidden" method="POST">
      <div class="form-grid">
        <div class="field">
          <input type="text" name="name" placeholder="Account Holder Name" />
        </div>

        <div class="field">
          <input type="text" name="mobile" placeholder="Mobile Number" />
        </div>

        <div class="field">
          <input type="text" name="account_number" placeholder="Account Number" />
        </div>

        <div class="field">
          <input type="text" name="confirm_ac_no" placeholder="Confirm Account Number" />
        </div>

        <div class="field full">
          <input type="text" name="ifsc" placeholder="IFSC Code" />
        </div>
      </div>

      <button class="btn">Add Beneficiary</button>
    </form>

    <!-- UPI FORM -->
    <form id="upiForm" class="hidden" method="POST">
      <div class="form-grid">
        <input type="text" name="name" placeholder="Name" />

        <input type="text" name="mobile" placeholder="Contact Number" />

        <input type="text" name="vpa" placeholder="UPI ID" class="full" />
      </div>

      <button class="btn">Add Beneficiary</button>
    </form>
  </div>
  <?php if ($profile_type_id != 3) {
    include "includes/footer.php";
  } ?>

  <script>
    function changeType() {
      let type = document.getElementById("type").value;

      // hide all
      document.getElementById("bankForm").classList.add("hidden");
      document.getElementById("upiForm").classList.add("hidden");
      document.getElementById("bankInfo").style.display = "none";
      document.getElementById("upiInfo").style.display = "none";

      if (type === "bank") {
        document.getElementById("bankForm").classList.remove("hidden");
        document.getElementById("bankInfo").style.display = "block";
      }

      if (type === "upi") {
        document.getElementById("upiForm").classList.remove("hidden");
        document.getElementById("upiInfo").style.display = "block";
      }
    }
  </script>
</body>

</html>