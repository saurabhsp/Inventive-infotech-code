<?php
require_once __DIR__ . '/includes/session.php';
require_once 'includes/db_config.php';

$mobile = $_SESSION['otp_mobile'] ?? '';
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_otp') {

    $otp = implode('', $_POST['otp'] ?? []);
    $mobile = $_SESSION['otp_mobile'] ?? '';

    // ❌ Step 1: Basic validation
    if (!$mobile || strlen($otp) != 6) {

        $error = "Invalid OTP";

    } else {

        // ✅ Step 2: Check OTP in DB
        $stmt = $con->prepare("SELECT * FROM user_otp 
            WHERE mobile_number=? AND otp_code=? AND status='sent' 
            ORDER BY created_at DESC LIMIT 1");

        $stmt->bind_param("ss", $mobile, $otp);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows < 1) {

            $error = "Invalid OTP";

        } else {

            // ✅ Step 3: Only if OTP is correct
            $con->query("UPDATE user_otp SET status='verified' WHERE mobile_number='$mobile'");

            $_SESSION['mobile'] = $mobile;
            unset($_SESSION['otp_mobile']);

            header("Location: register.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP | Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">
    <style>
        :root {
            --primary: #483EA8;       
            --primary-light: #eceaf9;
            --secondary: #ff6f00;     
            --bg-body: #f4f6f9;       
            --white: #ffffff;
            --text-dark: #1a1a1a;
            --text-grey: #666666;
            --success: #25D366;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        
        body { 
            background-color: var(--bg-body); 
            color: var(--text-dark); 
            font-size: 15px; 
            line-height: 1.6;
            min-height: 100vh;
            display: flex; flex-direction: column;
        }

        .container { max-width: 1150px; margin: 0 auto; padding: 0 15px; }

        /* --- 1. HEADER --- */
        header {
            background: var(--white); height: 70px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky; top: 0; z-index: 1000;
        }
        .nav-wrapper { display: flex; justify-content: space-between; align-items: center; height: 100%; }
        .brand { display: flex; align-items: center; gap: 8px; color: var(--primary); font-weight: 800; font-size: 1.3rem; }
        .brand i { font-size: 1.5rem; }
        .nav-right { display: flex; gap: 15px; align-items: center; }
        .login-link { font-weight: 700; color: var(--primary); text-decoration: none; }

        /* --- 2. OTP SECTION --- */
        .otp-section {
            flex: 1;
            display: flex; align-items: center; justify-content: center;
            padding: 40px 0;
        }

        .otp-card {
            background: var(--white);
            width: 100%; max-width: 900px;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 500px;
        }

        /* Left Side: Visual */
        .otp-visual {
            background: linear-gradient(135deg, rgba(72, 62, 168, 0.9) 0%, rgba(50, 43, 122, 0.9) 100%), 
                        url('https://images.unsplash.com/photo-1614064641938-3bcee52970f9?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80');
            background-size: cover; background-position: center;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            color: white; padding: 40px; text-align: center;
        }
        .visual-icon { font-size: 5rem; margin-bottom: 20px; opacity: 0.9; }
        .visual-text h2 { font-size: 2rem; margin-bottom: 10px; }
        .visual-text p { font-size: 1rem; opacity: 0.8; max-width: 250px; margin: 0 auto; }

        /* Right Side: Form */
        .otp-form-wrapper {
            padding: 50px;
            display: flex; flex-direction: column; justify-content: center;
        }

        .back-btn { 
            color: var(--text-dark); font-size: 1.2rem; margin-bottom: 20px; 
            cursor: pointer; align-self: flex-start; 
        }

        .form-title { font-size: 1.8rem; font-weight: 800; margin-bottom: 10px; }
        
        .otp-sent-info { 
            color: var(--text-grey); margin-bottom: 30px; font-size: 0.95rem; 
            display: flex; align-items: center; gap: 8px;
        }
        .edit-icon { color: var(--primary); cursor: pointer; font-size: 0.9rem; }

        /* OTP Inputs */
        .otp-input-group {
            display: flex; gap: 10px; margin-bottom: 25px;
        }
        .otp-box {
            width: 50px; height: 50px;
            border: 1px solid #ccc; border-radius: 8px;
            text-align: center; font-size: 1.5rem; font-weight: 700;
            color: var(--text-dark); outline: none; transition: 0.3s;
        }
        .otp-box:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(72, 62, 168, 0.1); }

        /* Timer & Resend */
        .resend-wrapper {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; font-size: 0.9rem;
        }
        .timer-text { color: var(--text-grey); }
        .resend-btn { 
            color: var(--primary); font-weight: 700; cursor: pointer; 
            text-decoration: none; opacity: 0.5; pointer-events: none; /* Disabled initially */
        }
        .resend-btn.active { opacity: 1; pointer-events: auto; }

        /* Verify Button */
        .btn-verify {
            width: 100%; background: var(--primary); color: white; padding: 15px; 
            border-radius: 30px; font-size: 1.1rem; font-weight: 700; border: none;
            transition: 0.3s; box-shadow: 0 5px 15px rgba(72, 62, 168, 0.3);
            cursor: pointer;
        }
        .btn-verify:hover { background: #322b7a; transform: translateY(-2px); }

        /* Toast Notification (Bottom) */
        .toast {
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            background: #333; color: white; padding: 12px 25px; border-radius: 30px;
            display: flex; align-items: center; gap: 10px; font-size: 0.9rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            opacity: 0; transition: opacity 0.5s; pointer-events: none;
        }
        .toast.show { opacity: 1; }
        .toast-icon { 
            background: white; color: var(--primary); width: 20px; height: 20px; 
            border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;
        }

        /* Mobile Responsive */
        @media (max-width: 900px) {
            .otp-card { grid-template-columns: 1fr; max-width: 500px; min-height: auto; }
            .otp-visual { display: none; }
            .otp-form-wrapper { padding: 30px 20px; }
            .otp-input-group { justify-content: space-between; }
            .otp-box { width: 15%; height: 50px; } /* Flexible width on mobile */
        }
    </style>
</head>
<body>

   <?php include "includes/header.php"; ?>

    <section class="otp-section">
        <div class="container">
            <div class="otp-card">
                
                <div class="otp-visual">
                    <i class="fas fa-shield-alt visual-icon"></i>
                    <div class="visual-text">
                        <h2>Secure Login</h2>
                        <p>We use Two-Factor Authentication to keep your account safe.</p>
                    </div>
                </div>

                <div class="otp-form-wrapper">
                    <a href="#" class="back-btn"><i class="fas fa-arrow-left"></i></a>
                    
                    <h1 class="form-title">Enter OTP</h1>
                    <div class="otp-sent-info">
                        <span>Code sent to +91 <?php echo htmlspecialchars($mobile); ?>
</span>
                        <i class="fas fa-pencil-alt edit-icon"></i>
                    </div>

                    <form method="POST">
    <input type="hidden" name="action" value="verify_otp">

                        <div class="otp-input-group">
        <input type="text" name="otp[]" class="otp-box" maxlength="1" autofocus>
        <input type="text" name="otp[]" class="otp-box" maxlength="1">
        <input type="text" name="otp[]" class="otp-box" maxlength="1">
        <input type="text" name="otp[]" class="otp-box" maxlength="1">
        <input type="text" name="otp[]" class="otp-box" maxlength="1">
        <input type="text" name="otp[]" class="otp-box" maxlength="1">
    </div>

                        <div class="resend-wrapper">
                            <span class="timer-text">Resend OTP in <span id="timer" style="color:var(--text-dark); font-weight:600;">00:30</span>s</span>
                            <a href="#" class="resend-btn" id="resendBtn">Resend</a>
                        </div>

                        <button type="submit" class="btn-verify">Verify</button>

                    </form>
                </div>

            </div>
        </div>
    </section>

    <div class="toast" id="toast">
        <div class="toast-icon"><i class="fas fa-check"></i></div>
        <span>OTP sent successfully</span>
    </div>

    <script>
        // 1. Auto-Focus Logic for OTP Inputs
        const inputs = document.querySelectorAll('.otp-box');
        
        inputs.forEach((input, index) => {
            input.addEventListener('keyup', (e) => {
                if (e.key >= 0 && e.key <= 9) {
                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                } else if (e.key === 'Backspace') {
                    if (index > 0) {
                        inputs[index - 1].focus();
                    }
                }
            });
        });

        // 2. Timer Logic
        let timeLeft = 30;
        const timerElem = document.getElementById('timer');
        const resendBtn = document.getElementById('resendBtn');

        const countdown = setInterval(() => {
            if (timeLeft <= 0) {
                clearInterval(countdown);
                timerElem.innerText = "00:00";
                resendBtn.classList.add('active'); // Enable Resend
            } else {
                let seconds = timeLeft < 10 ? `0${timeLeft}` : timeLeft;
                timerElem.innerText = `00:${seconds}`;
                timeLeft--;
            }
        }, 1000);

        // 3. Show Toast on Load (Simulation)
        window.onload = function() {
            const toast = document.getElementById('toast');
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 3000);
        }

        function verifyOTP() {
            // Add verification logic here
            alert("Verifying Code...");
        }
        
        
        // 4. Resend OTP Click Handler
resendBtn.addEventListener('click', function(e) {
    e.preventDefault();

    if (!resendBtn.classList.contains('active')) return;

    resendBtn.classList.remove('active');

    // Reset timer
    timeLeft = 30;

    const newCountdown = setInterval(() => {
        if (timeLeft <= 0) {
            clearInterval(newCountdown);
            timerElem.innerText = "00:00";
            resendBtn.classList.add('active');
        } else {
            let seconds = timeLeft < 10 ? `0${timeLeft}` : timeLeft;
            timerElem.innerText = `00:${seconds}`;
            timeLeft--;
        }
    }, 1000);

    // Call backend
    fetch('resend_otp.php', {
        method: 'POST'
    })
    .then(res => res.text())
    .then(data => {
        console.log(data);
    });
});
    </script>
    <?php if (!empty($error)) : ?>
<script>
    alert("<?php echo $error; ?>");
</script>
<?php endif; ?>

</body>
</html>