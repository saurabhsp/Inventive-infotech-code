<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/session.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['is_logged_in'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/db_config.php';

$userId = (int)$_SESSION['user_id'];
$api_url = API_WEB_URL ."getReferralcode.php";
// Default values
$referral_link = '';
$qr_code_url = '';
$whatsapp_text = '';
$earn_amount = '500';
$error_message = '';
$success_message = '';

// ✅ Prepare request
$postData = json_encode([
    "userid" => $userId
]);

$ch = curl_init($api_url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);
// ❌ cURL error
if (curl_errno($ch)) {
    $error_message = "cURL Error: " . curl_error($ch);
    curl_close($ch);
} else {

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // ❌ Invalid HTTP response
    if ($httpCode !== 200) {
        $error_message = "API HTTP Error: " . $httpCode;
    } else {

        $data = json_decode($response, true);

        // ❌ Invalid JSON
        if (!$data) {
            $error_message = "Invalid API Response: " . $response;
        } else {

            // ✅ Success
            if (isset($data['status']) && $data['status'] === true) {

                $referral_link = $data['referral_link'] ?? '';
                $qr_code_url = $data['qr_code_url'] ?? '';
                $whatsapp_text = urlencode($data['whatsapp_share_text'] ?? '');
                $earn_amount = $data['premium_referral_amount'] ?? '500';
                $success_message = $data['message'] ?? '';
            } else {
                // ❌ API returned error
                $error_message = $data['message'] ?? 'Something went wrong';
            }
        }
    }
}
print_r( $earn_amount );
// exit;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refer & Earn | Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">
    <style>
        :root {
            --primary: #483EA8;
            --primary-light: #eceaf9;
            --bg-body: #f4f6f9;
            --white: #ffffff;
            --text-dark: #1a1a1a;
            --text-grey: #555555;
            --green-wa: #25D366;
            --blue-copy: #1565c0;
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
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

        .nav-menu {
            display: flex;
            gap: 30px;
        }

        .nav-item {
            font-weight: 600;
            color: #555;
            font-size: 1rem;
            text-decoration: none;
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

        /* --- PAGE CONTAINER --- */
        .page-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* --- THE UNIFIED CARD (Desktop) --- */
        .refer-card {
            background: var(--white);
            width: 100%;
            max-width: 1000px;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: grid;
            grid-template-columns: 400px 1fr;
            /* Fixed Visual Left, Content Right */
            min-height: 600px;
        }

        /* LEFT PANEL: Visual Banner */
        .refer-visual {
            background: linear-gradient(135deg, rgba(72, 62, 168, 0.95) 0%, rgba(40, 30, 110, 0.9) 100%),
                url('https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .visual-content {
            position: relative;
            z-index: 2;
        }

        .gift-icon {
            font-size: 3rem;
            color: #FFD700;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 15px;
        }

        .hero-desc {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 30px;
        }

        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            padding: 10px 15px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* RIGHT PANEL: Content & Tools */
        .refer-content {
            padding: 40px 50px;
            overflow-y: auto;
        }

        /* Referral Link Box */
        .link-section {
            margin-bottom: 30px;
        }

        .section-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }

        .copy-box {
            display: flex;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 5px;
            background: #fafafa;
        }

        .link-text {
            flex: 1;
            border: none;
            background: transparent;
            padding: 10px 15px;
            font-size: 1rem;
            color: #333;
            font-weight: 600;
            outline: none;
        }

        .btn-copy {
            background: var(--blue-copy);
            color: white;
            border: none;
            padding: 0 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-copy:hover {
            background: #0d47a1;
        }

        /* Buttons */
        .btn-whatsapp {
            width: 100%;
            background: var(--green-wa);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-whatsapp:hover {
            background: #1ebe57;
            transform: translateY(-2px);
        }

        .btn-qr {
            width: 100%;
            background: white;
            color: #333;
            border: 1px solid #ddd;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-qr:hover {
            background: #f5f5f5;
        }

        /* Steps */
        .how-title {
            text-align: center;
            font-size: 1.2rem;
            font-weight: 700;
            margin: 30px 0 20px;
            color: var(--primary);
        }

        .step-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .step-row {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #f0f0f0;
            background: white;
            transition: 0.2s;
        }

        .step-row:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.03);
            border-color: var(--primary-light);
        }

        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            background: #f0f4ff;
            color: var(--blue-copy);
            flex-shrink: 0;
        }

        .step-info h4 {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .step-info p {
            font-size: 0.85rem;
            color: #666;
            margin: 0;
        }

        /* --- FOOTER --- */
        .mega-footer {
            background: #111;
            color: #bbb;
            padding: 50px 0 20px;
        }

        .footer-grid {
            width: 100%;
            max-width: 1150px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 30px;
            padding: 0 20px;
        }

        .f-brand h3 {
            color: white;
            margin-bottom: 10px;
            font-size: 1.3rem;
        }

        .f-links ul li {
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        @keyframes bounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        /* --- MOBILE LAYOUT (Specific Fixes) --- */
        @media (max-width: 900px) {
            .nav-menu {
                display: none;
            }

            .page-content {
                padding: 0;
                align-items: flex-start;
            }

            .refer-card {
                grid-template-columns: 1fr;
                /* Stack vertically */
                border-radius: 0;
                box-shadow: none;
                display: flex;
                flex-direction: column;
            }

            /* 1. Mobile Top Banner (Matches Screenshot) */
            .refer-visual {
                height: 320px;
                /* Taller banner */
                padding: 40px 25px;
                justify-content: flex-start;
                padding-top: 60px;
                /* Space from top */
                text-align: center;
            }

            .hero-title {
                font-size: 2.2rem;
                margin-bottom: 10px;
            }

            .hero-desc {
                font-size: 1rem;
                max-width: 300px;
                margin: 0 auto 20px;
            }

            .stats-badge {
                background: rgba(255, 255, 255, 0.2);
                border: none;
            }

            /* 2. Mobile Content Card (Overlapping) */
            .refer-content {
                background: white;
                margin-top: -40px;
                /* Negative margin creates overlap */
                border-radius: 25px 25px 0 0;
                /* Rounded top corners */
                padding: 30px 20px;
                position: relative;
                z-index: 10;
                box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.05);
            }

            .link-section {
                margin-bottom: 25px;
            }

            .copy-box {
                padding: 8px;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
        }
    </style>
</head>

<body>
    <?php include "includes/preloader.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="page-content">
        <div class="refer-card">

            <div class="refer-visual">
                <div class="visual-content">
                    <i class="fas fa-gift gift-icon"></i>
                    <h1 class="hero-title">Refer & Earn<br>Real Cash.</h1>
                    <p class="hero-desc">Invite your friends to Pacific iConnect. When they get hired, you get paid. It's that simple.</p>

                    <div class="stats-badge">
                        <i class="fas fa-coins" style="color:#FFD700;"></i>
                        <span>Earn up to ₹<?php echo $earn_amount; ?> per friend</span>
                    </div>
                </div>
            </div>

            <div class="refer-content">
                <?php if (!empty($success_message)) { ?>
                    <div style="background:#e8f5e9;color:#2e7d32;padding:10px;border-radius:8px;margin-bottom:15px;">
                        <?php echo $success_message; ?>
                    </div>
                <?php } ?>

                <?php if (!empty($error_message)) { ?>
                    <div style="background:#ffebee;color:#c62828;padding:10px;border-radius:8px;margin-bottom:15px;">
                        <?php echo $error_message; ?>
                    </div>
                <?php } ?>
                <div class="link-section">
                    <span class="section-label">Your Referral Link</span>
                    <div class="copy-box">
                        <input type="text" class="link-text" value="<?php echo htmlspecialchars($referral_link); ?>" readonly>
                        <button class="btn-copy">Copy</button>
                    </div>
                </div>

                <button class="btn-whatsapp"
                    onclick="window.open('https://wa.me/?text=<?php echo $whatsapp_text; ?>','_blank')"><i class="fab fa-whatsapp"></i> Invite via WhatsApp</button>
                <button class="btn-qr" onclick="window.open('<?php echo $qr_code_url; ?>','_blank')"><i class="fas fa-qrcode"></i> Show QR Code</button>

                <h3 class="how-title">How It Works</h3>

                <div class="step-list">
                    <div class="step-row">
                        <div class="step-icon"><i class="fas fa-link"></i></div>
                        <div class="step-info">
                            <h4>1. Get Referral Link</h4>
                            <p>Copy your unique link or QR code.</p>
                        </div>
                    </div>

                    <div class="step-row">
                        <div class="step-icon" style="color:#25D366; background:#e8f5e9;"><i class="fab fa-whatsapp"></i></div>
                        <div class="step-info">
                            <h4>2. Share With Friends</h4>
                            <p>Send via WhatsApp or SMS.</p>
                        </div>
                    </div>

                    <div class="step-row">
                        <div class="step-icon" style="color:#f50057; background:#fce4ec;"><i class="fas fa-user-plus"></i></div>
                        <div class="step-info">
                            <h4>3. Friend Joins App</h4>
                            <p>They sign up using your link.</p>
                        </div>
                    </div>

                    <div class="step-row">
                        <div class="step-icon" style="color:#2979ff; background:#e3f2fd;"><i class="fas fa-wallet"></i></div>
                        <div class="step-info">
                            <h4>4. You Earn Cashback</h4>
                            <p>Money credited to your wallet instantly.</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <script>
        document.querySelector(".btn-copy").addEventListener("click", function() {
            const input = document.querySelector(".link-text");
            input.select();
            document.execCommand("copy");

            this.innerText = "Copied!";
            setTimeout(() => this.innerText = "Copy", 2000);
        });
    </script>
</body>

</html>