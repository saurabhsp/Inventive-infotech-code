<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/initialize.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
echo "<pre>";
print_r($_POST);
print_r(file_get_contents('php://input'));
exit;
// Check if mobile number and purpose are provided
if (isset($input['mobile_number']) && isset($input['purpose'])) {
    $mobile_number = $input['mobile_number'];
    $purpose = $input['purpose']; // 'signup' or 'forgot_password'

    // Handle signup flow
    if ($purpose == 'signup') {
        $query = "SELECT * FROM user_otp WHERE mobile_number = '$mobile_number' AND status = 'verified'";
        $result = mysqli_query($con, $query);

        if ($result === false) {
            echo json_encode(['message' => 'Database query failed: ' . mysqli_error($con)]);
            exit;
        }

        if (mysqli_num_rows($result) > 0) {
            // Check if user already exists
            $userCheck = mysqli_query($con, "SELECT id FROM jos_app_users WHERE mobile_no = '$mobile_number'");
            if ($userCheck === false) {
                echo json_encode(['message' => 'User check failed: ' . mysqli_error($con)]);
                exit;
            }

            if (mysqli_num_rows($userCheck) == 0) {
                // User not created, delete old verified OTP and treat as new
                mysqli_query($con, "DELETE FROM user_otp WHERE mobile_number = '$mobile_number' AND status = 'verified'");
            } else {
                // User exists, stop
                echo json_encode(['message' => 'User already exists with this mobile number']);
                exit;
            }
        }
    }

    // Generate a random 6-digit OTP
    $otp = rand(100000, 999999);

    // Check if OTP already exists for this mobile number and purpose
    $query = "SELECT * FROM user_otp WHERE mobile_number = '$mobile_number' AND purpose = '$purpose'";
    $result = mysqli_query($con, $query);

    if ($result === false) {
        echo json_encode(['message' => 'Database query failed: ' . mysqli_error($con)]);
        exit;
    }

    if (mysqli_num_rows($result) > 0) {
        // Update existing OTP record
        $update_query = "UPDATE user_otp 
                         SET otp_code = '$otp', status = 'sent', created_at = NOW() 
                         WHERE mobile_number = '$mobile_number' AND purpose = '$purpose'";
        mysqli_query($con, $update_query);
    } else {
        // Insert new OTP record
        $insert_query = "INSERT INTO user_otp (mobile_number, otp_code, status, purpose) 
                         VALUES ('$mobile_number', '$otp', 'sent', '$purpose')";
        mysqli_query($con, $insert_query);
    }

    // Send OTP using SMS API (2Factor)
    $api_key = 'c57522ea-2cb5-11eb-83d4-0200cd936042';  // Replace with your real key
    $url = "https://2factor.in/API/V1/$api_key/SMS/$mobile_number/$otp/newotp1";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPGET, 1);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode(['message' => 'SMS sending failed: ' . curl_error($ch)]);
        curl_close($ch);
        exit;
    }
    curl_close($ch);

    // Parse and validate SMS API response
    $sms_response = json_decode($response, true);

    if ($sms_response && $sms_response['Status'] == 'Success') {
        echo json_encode(['message' => 'OTP sent successfully']);
    } else {
        echo json_encode(['message' => 'Failed to send OTP']);
    }

} else {
    echo json_encode(['message' => 'Mobile number and purpose are required']);
}
?>
