<?php

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db_config.php';

$mobile = $_POST['mobile'] ?? '';
$otp = $_POST['otp'] ?? '';

if (empty($_POST['mobile']) || empty($_POST['otp'])) {
    header("Location: login.php");
    exit;
}

$_SESSION['otp_verified'] = true;

$message = "";
$isError = false;

// ✅ Show success when coming from verify_otp.php
// if (!isset($_POST['submit']) && !empty($mobile) && !empty($otp)) {
//     $message = "OTP verified successfully";
//     $isError = false;
// }


if (isset($_POST['submit'])) {

    // $mobile = $_SESSION['user']['mobile_no'] ?? '';
    // $otp = $_POST['otp'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // ✅ Validation
    if (empty($otp) || empty($new_password) || empty($confirm_password)) {
        $message = "All fields are required";
        $isError = true;
    } elseif ($new_password !== $confirm_password) {
        $message = "New password and confirm password do not match";
        $isError = true;
    } elseif (strlen($new_password) < 6) {
        $message = "Password must be at least 6 characters";
        $isError = true;
    } else {

        // ✅ API CALL
        $payload = json_encode([
            "mobile_number" => $mobile,
            "otp" => $otp,
            "new_password" => $new_password
        ]);

        $ch = curl_init(API_BASE_URL . "forgot_pass.php");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json"
        ]);

        // $response = curl_exec($ch);
        // curl_close($ch);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            $message = "API Error";
            $isError = true;
        } else {

            curl_close($ch);

            $result = json_decode($response, true);

            if (!empty($result) && isset($result['success']) && $result['success'] == true) {
                header("Location: login.php");
                exit;
            } else {
                $message = $result['message'] ?? "Failed to change password";
                $isError = true;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">
    <style>
        :root {
            --primary: #3f63b8;
            --bg: #f6f7fb;
            --text: #111;
            --input-border: #ddd;
            --error: #e53935;
            --success: #2e7d32;
        }


        .pass-container {
            width: 100%;
            max-width: 600px;
            margin: 60px auto;
            padding: 0 20px;
        }

        /* Bigger screens */
        @media (min-width: 1024px) {
            .pass-container {
                max-width: 700px;
            }
        }

        .cp-card {
            background: #fff;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .heading {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 20px;
        }

        /* Message */
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .error {
            background: #fdecea;
            color: var(--error);
        }

        .success {
            background: #e8f5e9;
            color: var(--success);
        }

        .label {
            font-weight: 700;
            margin-bottom: 8px;
            display: block;
        }

        .input-field {
            width: 100%;
            padding: 16px;
            border-radius: 10px;
            border: 1px solid var(--input-border);
            margin-bottom: 20px;
        }

        .btn {
            width: 100%;
            padding: 16px;
            border-radius: 30px;
            border: none;
            background: var(--primary);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }

        /* Modal CSS */
        .modal-full-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            /* dark overlay */
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .modal-map-cp-card {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            width: 350px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            animation: popup 0.3s ease;
        }

        @keyframes popup {
            from {
                transform: scale(0.8);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Toast Notification (Bottom) */
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 12px 25px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            opacity: 0;
            transition: opacity 0.5s;
            pointer-events: none;
        }

        .toast.show {
            opacity: 1;
        }

        .toast-icon {
            background: white;
            color: var(--primary);
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }
    </style>

</head>

<body>

    <?php include "includes/preloader.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="pass-container">
        <div class="cp-card">

            <div class="heading">Reset Password</div>

            <div class="modal-full-overlay" id="successModal" style="display:none;">
                <div class="modal-map-cp-card" style="max-width:400px;height:auto;padding:30px;text-align:center;">
                    <h3 style="margin-bottom:15px;color:#2e7d32;">Success</h3>
                    <p id="successText"></p>
                    <button onclick="closeSuccessModal()"
                        style="margin-top:20px;padding:10px 20px;background:#2563eb;color:white;border-radius:6px;border:none;">
                        OK
                    </button>
                </div>
            </div>


            <div class="modal-full-overlay" id="errorModal" style="display:none;">
                <div class="modal-map-cp-card" style="max-width:400px;height:auto;padding:30px;text-align:center;">
                    <h3 style="margin-bottom:15px;color:#e53935;">Error</h3>
                    <p id="errorText"></p>
                    <button onclick="closeErrorModal()"
                        style="margin-top:20px;padding:10px 20px;background:#2563eb;color:white;border-radius:6px;border:none;">
                        OK
                    </button>
                </div>
            </div>



            <form method="POST">

                <input type="hidden" name="otp" value="<?php echo htmlspecialchars($otp); ?>">
                <input type="hidden" name="mobile" value="<?php echo htmlspecialchars($mobile); ?>">

                <label class="label">New Password</label>
                <input type="password" name="new_password" class="input-field" placeholder="Password">

                <label class="label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="input-field" placeholder="Password">

                <button type="submit" name="submit" class="btn">Continue</button>

            </form>

        </div>
    </div>
    <div class="toast" id="toast">
        <div class="toast-icon"><i class="fas fa-check"></i></div>
        <span>OTP Verified successfully</span>
    </div>
    <script>
        window.onload = function() {
            document.getElementById("global-preloader")?.remove();

            const message = <?php echo json_encode($message); ?>;
            const isError = <?php echo json_encode($isError); ?>;
            const toast = document.getElementById('toast');

            // ✅ ONLY show when coming from verify_otp
            const hasOtp = <?php echo isset($_SESSION['otp_verified']) ? 'true' : 'false'; ?>;

            if (hasOtp) {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }

            // modal logic
            if (message) {
                if (isError) {
                    document.getElementById("errorText").innerText = message;
                    document.getElementById("errorModal").style.display = "flex";
                } else {
                    document.getElementById("successText").innerText = message;
                    document.getElementById("successModal").style.display = "flex";
                }
            }
        };


        function closeSuccessModal() {
            document.getElementById("successModal").style.display = "none";
        }

        function closeErrorModal() {
            document.getElementById("errorModal").style.display = "none";
        }


    </script>
    <?php unset($_SESSION['otp_verified']); ?>
</body>

</html>