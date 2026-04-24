<?php
session_start();
require_once 'includes/db_config.php';

// ✅ GET JSON INPUT
$data = json_decode(file_get_contents("php://input"), true);

$mobile = $data['mobile'] ?? ($_SESSION['otp_mobile'] ?? '');
$purpose = $data['purpose'] ?? 'signup';

if (!$mobile) {
    echo json_encode(["status" => "error", "message" => "Session expired"]);
    exit;
}

// Generate OTP
$otp = rand(100000, 999999);

// ✅ USE DYNAMIC PURPOSE
$query = "SELECT * FROM user_otp WHERE mobile_number = '$mobile' AND purpose='$purpose'";
$result = mysqli_query($con, $query);

if (mysqli_num_rows($result) > 0) {
    mysqli_query($con, "UPDATE user_otp 
        SET otp_code='$otp', status='sent', created_at=NOW() 
        WHERE mobile_number='$mobile' AND purpose='$purpose'");
} else {
    mysqli_query($con, "INSERT INTO user_otp (mobile_number, otp_code, status, purpose) 
        VALUES ('$mobile', '$otp', 'sent', '$purpose')");
}


// ðŸ”¥ SAME SMS API (IMPORTANT)
$api_key = 'c57522ea-2cb5-11eb-83d4-0200cd936042';
$url = "https://2factor.in/API/V1/$api_key/SMS/$mobile/$otp/newotp1";

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,

    // ✅ ADD TIMEOUT HERE
    CURLOPT_CONNECTTIMEOUT => 5,  // connection time
    CURLOPT_TIMEOUT => 10         // total request time
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    curl_close($ch);
    echo json_encode(["status" => "error", "message" => "SMS failed"]);
    exit;
}

curl_close($ch);

// ✅ RETURN JSON (IMPORTANT)
echo json_encode([
    "status" => "success",
    "message" => "OTP resent successfully"
]);
