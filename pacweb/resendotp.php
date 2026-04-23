<?php
session_start();
require_once 'includes/db_config.php';

$mobile = $_SESSION['otp_mobile'] ?? '';

if (!$mobile) {
    echo "Session expired";
    exit;
}

// Generate OTP
$otp = rand(100000, 999999);

// Update or Insert
$query = "SELECT * FROM user_otp WHERE mobile_number = '$mobile' AND purpose='signup'";
$result = mysqli_query($con, $query);

if (mysqli_num_rows($result) > 0) {
    mysqli_query($con, "UPDATE user_otp 
        SET otp_code='$otp', status='sent', created_at=NOW() 
        WHERE mobile_number='$mobile' AND purpose='signup'");
} else {
    mysqli_query($con, "INSERT INTO user_otp (mobile_number, otp_code, status, purpose) 
        VALUES ('$mobile', '$otp', 'sent', 'signup')");
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
    echo "SMS failed: " . curl_error($ch);
    curl_close($ch);
    exit;
}

curl_close($ch);

echo "OTP resent successfully";
