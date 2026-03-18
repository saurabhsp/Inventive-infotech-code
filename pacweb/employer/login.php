<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db_config.php';

/* ---- STORE REDIRECT IF EXISTS ---- */
if (!empty($_GET['redirect']) && strpos($_GET['redirect'], '/') === 0) {
    $_SESSION['login_redirect'] = $_GET['redirect'];
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ---------------- CSRF ---------------- */
/*function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}*/

/* ---------------- Normalize ---------------- */
function normalize_nulls($row)
{
    if (!is_array($row)) return [];
    foreach ($row as $k => $v) {
        if ($v === null) $row[$k] = "";
    }
    return $row;
}

/* ---------------- Messages ---------------- */
function set_message(string $text, string $type = 'error'): void
{
    $_SESSION['message'] = $text;
    $_SESSION['message_type'] = $type;
}

function get_message(): array
{
    $msg = $_SESSION['message'] ?? '';
    $type = $_SESSION['message_type'] ?? 'error';
    unset($_SESSION['message'], $_SESSION['message_type']);
    return ['text' => $msg, 'type' => $type];
}

/* ---------------- OTP FUNCTION ---------------- */
function send_otp($mobile_no)
{
    $url = "https://pacweb.inv11.in/web_api/generate_otp.php";

    $postData = [
        'mobile_number' => $mobile_no,
        'purpose' => 'signup'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    return ($result && $result['status'] === 'success');
}

/* ---------------- FORM ---------------- */
$show_password_step = false;
$mobile_no = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($csrf)) {
        set_message("Invalid CSRF token.", "error");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $mobile_no = preg_replace('/\D+/', '', $_POST['mobile_no'] ?? '');

    /* ===== RESET ===== */
    if ($action === 'reset') {
        unset($_SESSION['temp_mobile']);
        set_message("Enter mobile again", "success");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    /* ===== STEP 1: CHECK MOBILE ===== */
    if ($action === 'check_mobile') {

        if (strlen($mobile_no) !== 10) {
            set_message("Mobile number must be 10 digits.", "error");
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        $stmt = $con->prepare("SELECT * FROM jos_app_users WHERE mobile_no = ? LIMIT 1");
        $stmt->bind_param("s", $mobile_no);
        $stmt->execute();
        $res = $stmt->get_result();

        if (!$res) {
            die("Query Error: " . $con->error);
        }

        /* ===== USER NOT FOUND → SEND OTP ===== */
        if ($res->num_rows < 1) {

            // Rate limit
            if (isset($_SESSION['otp_time']) && time() - $_SESSION['otp_time'] < 30) {
                set_message("Please wait before requesting OTP again.", "error");
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }

            $_SESSION['otp_mobile'] = $mobile_no;
            $_SESSION['otp_time'] = time();

            if (!send_otp($mobile_no)) {
                set_message("Failed to send OTP", "error");
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }

            header("Location: verify_otp.php");
            exit;
        }

        $user = $res->fetch_assoc();

        if ((int)$user['status_id'] !== 1) {
            set_message("User inactive", "error");
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        $_SESSION['temp_mobile'] = $mobile_no;
        header("Location: " . $_SERVER['PHP_SELF'] . "?step=password");
        exit;
    }

    /* ===== STEP 2: LOGIN ===== */
    if ($action === 'login') {

        $mobile_no = $_SESSION['temp_mobile'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!$mobile_no || !$password) {
            set_message("Session expired", "error");
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        $stmt = $con->prepare("SELECT * FROM jos_app_users WHERE mobile_no = ? LIMIT 1");
        $stmt->bind_param("s", $mobile_no);
        $stmt->execute();
        $res = $stmt->get_result();

        if (!$res || $res->num_rows < 1) {

            // fallback → OTP
            $_SESSION['otp_mobile'] = $mobile_no;

            if (!send_otp($mobile_no)) {
                set_message("Failed to send OTP", "error");
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }

            header("Location: verify_otp.php");
            exit;
        }

        $user = $res->fetch_assoc();

        if (!password_verify($password, $user['password'])) {
            set_message("Invalid Password", "error");
            header("Location: " . $_SERVER['PHP_SELF'] . "?step=password");
            exit;
        }

        /* ===== EXTRA USER DATA ===== */
        $user['city_name'] = $user['city_id'] ?? "";

        $user_id = (int)$user['id'];
        $plan_id = (int)($user['active_plan_id'] ?? 0);

        $user['subscription_status'] = "none";
        $user['subscription_message'] = "No active subscription";

        if ($plan_id > 0) {

            $q = "SELECT start_date,end_date FROM jos_app_usersubscriptionlog 
                  WHERE userid=? AND plan_id=? AND payment_status='success'
                  ORDER BY start_date DESC LIMIT 1";

            $s = $con->prepare($q);
            $s->bind_param("ii", $user_id, $plan_id);
            $s->execute();
            $r = $s->get_result();

            if ($r && $r->num_rows > 0) {

                $row = $r->fetch_assoc();
                $today = date("Y-m-d");

                if ($row['end_date'] >= $today) {
                    $user['subscription_status'] = "active";
                } else {
                    $user['subscription_status'] = "expired";
                }
            }
        }

        unset($user['password']);
        $user = normalize_nulls($user);

        session_regenerate_id(true);

        $_SESSION['is_logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = $user;
        $_SESSION['profile_type_id'] = $user['profile_type_id'] ?? 0;
        $_SESSION['profile_id'] = $user['profile_id'] ?? 0;

        /* FETCH CANDIDATE NAME */

        $stmt = $con->prepare("
SELECT candidate_name 
FROM jos_app_candidate_profile 
WHERE id = ?
LIMIT 1
");

        $stmt->bind_param("i", $user['profile_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $_SESSION['username'] = $row['candidate_name'] ?? 'User';


        unset($_SESSION['temp_mobile']);



        /* ===== REDIRECT ===== */

        /* 1️⃣ If redirect parameter exists → go back there */
        if (!empty($_SESSION['login_redirect'])) {

            $redirect = $_SESSION['login_redirect'];
            unset($_SESSION['login_redirect']);

            header("Location: " . $redirect);
            exit;
        }

        /* 2️⃣ Otherwise → normal dashboard redirect */
        $type = (int)$user['profile_type_id'];

        if ($type === 1) {
            header("Location: /employer/index.php");   //recruiter_profile
        } elseif ($type === 2) {
            header("Location: /jobseeker/index.php");  //candidate_profile
        } elseif ($type === 3) {
            header("Location: /promoter/index.php");
        } else {
            header("Location: /index.php");
        }
        exit;
    }
}

/* ---------------- VIEW ---------------- */
$step = $_GET['step'] ?? 'mobile';

if ($step === 'password' && isset($_SESSION['temp_mobile'])) {
    $show_password_step = true;
    $mobile_no = $_SESSION['temp_mobile'];
}

$message = get_message();
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">
    <style>
        :root {
            /* Brand Colors */
            --primary: #483EA8;
            --primary-dark: #322b7a;
            --primary-light: #eceaf9;
            --bg-body: #f4f6f9;
            --white: #ffffff;
            --text-dark: #1a1a1a;
            --text-grey: #666666;
            --border-color: #e0e0e0;
            --success: #10b981;
            --error: #ef4444;
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
            line-height: 1.5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        a {
            text-decoration: none;
            transition: 0.3s;
            color: inherit;
        }

        button {
            cursor: pointer;
        }

        .container {
            max-width: 1150px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* --- HEADER --- */
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

        .nav-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .register-text {
            font-size: 0.95rem;
            color: var(--text-grey);
            font-weight: 600;
        }

        .btn-header-cta {
            background: var(--primary);
            color: white;
            padding: 8px 25px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.9rem;
            border: none;
        }

        .btn-header-cta:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* --- LOGIN HERO SECTION --- */
        .login-hero {
            flex: 1;
            background: linear-gradient(135deg, rgba(72, 62, 168, 0.95) 0%, rgba(42, 34, 114, 0.9) 100%),
                url('https://images.unsplash.com/photo-1521737604893-d14cc237f11d?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
        }

        /* --- LOGIN CARD --- */
        .login-card {
            background: var(--white);
            width: 100%;
            max-width: 420px;
            border-radius: 16px;
            padding: 40px 35px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
            text-align: center;
            position: relative;
        }

        .form-header h2 {
            font-size: 1.8rem;
            color: var(--text-dark);
            margin-bottom: 5px;
            font-weight: 800;
        }

        .form-header p {
            color: var(--text-grey);
            font-size: 0.95rem;
            margin-bottom: 30px;
        }

        /* Message Box */
        .message-box {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: left;
        }

        .message-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .message-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        /* Form Styling */
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-dark);
        }

        /* Mobile Input with Fixed +91 */
        .mobile-input-group {
            position: relative;
            display: flex;
            align-items: center;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: #fcfcfc;
            transition: 0.3s;
            overflow: hidden;
        }

        .mobile-input-group:focus-within {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(72, 62, 168, 0.1);
        }

        .prefix {
            background: #eee;
            color: #555;
            padding: 12px 15px;
            font-weight: 700;
            font-size: 1rem;
            border-right: 1px solid var(--border-color);
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: none;
            font-size: 1.1rem;
            outline: none;
            background: transparent;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        /* Password Input Wrapper */
        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 1.1rem;
        }

        .pass-input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 1rem;
            outline: none;
            transition: 0.3s;
            background: #fcfcfc;
        }

        .pass-input:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(72, 62, 168, 0.1);
        }

        /* Action Row */
        .action-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-grey);
            cursor: pointer;
        }

        .forgot-link {
            color: var(--primary);
            font-weight: 700;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        /* Buttons */
        .btn-main {
            width: 100%;
            background: var(--primary);
            color: white;
            padding: 14px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1.1rem;
            border: none;
            cursor: pointer;
            transition: 0.2s;
            box-shadow: 0 5px 15px rgba(72, 62, 168, 0.3);
        }

        .btn-main:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            width: 100%;
            background: transparent;
            color: var(--primary);
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            border: 2px solid var(--primary);
            cursor: pointer;
            transition: 0.2s;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background: var(--primary-light);
        }

        /* Mobile Display */
        .mobile-display {
            background: var(--primary-light);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: left;
            border-left: 4px solid var(--primary);
        }

        .mobile-display strong {
            color: var(--primary);
        }



        /* Mobile */
        @media (max-width: 500px) {
            .login-card {
                padding: 30px 20px;
            }

            .login-hero {
                padding: 40px 15px;
            }

            .nav-wrapper {
                justify-content: center;
            }

            .nav-right {
                display: none;
            }
        }
    </style>
</head>

<body>

    <?php
    include "includes/header.php";
    ?>

    <div class="login-hero">
        <div class="login-card">

            <div class="form-header">
                <h2>Welcome Back</h2>
                <p>Login with your mobile number</p>
            </div>

            <?php if (!empty($message['text'])): ?>
                <div class="message-box message-<?php echo $message['type']; ?>">
                    <?php echo htmlspecialchars($message['text']); ?>
                </div>
            <?php endif; ?>

            <?php if (!$show_password_step): ?>
                <!-- STEP 1: Mobile Number -->
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="check_mobile">

                    <div class="form-group">
                        <label class="form-label">Mobile Number</label>
                        <div class="mobile-input-group">
                            <span class="prefix">+91</span>
                            <input type="tel" name="mobile_no" class="form-input"
                                placeholder="Enter 10-digit number"
                                maxlength="10"
                                pattern="[0-9]{10}"
                                required
                                autofocus>
                        </div>
                    </div>

                    <button type="submit" class="btn-main">Continue</button>
                </form>

            <?php else: ?>
                <!-- STEP 2: Password -->
                <div class="mobile-display">
                    <strong>Mobile:</strong> +91 <?php echo htmlspecialchars($mobile_no); ?>
                </div>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="login">

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" class="pass-input"
                                placeholder="Enter your password"
                                required
                                autofocus>
                        </div>
                    </div>

                    <div class="action-row">
                        <label class="remember-me">
                            <input type="checkbox" name="remember_me"> Remember me
                        </label>
                        <a href="/forgot-password.php" class="forgot-link">Forgot Password?</a>
                    </div>

                    <button type="submit" class="btn-main">Login</button>
                </form>

                <!-- Change Mobile Form -->
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="reset">
                    <button type="submit" class="btn-secondary">Change Mobile Number</button>
                </form>

            <?php endif; ?>

        </div>
    </div>

    <?php
    include "includes/footer.php" ?>

</body>

</html>