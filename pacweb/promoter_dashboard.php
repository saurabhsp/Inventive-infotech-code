<?php
require_once 'includes/session.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ===============================
   ✅ LOGIN CHECK
================================ */
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* ===============================
   ✅ DB CONNECTION
================================ */
require_once 'includes/db_config.php';

$user_id = $_SESSION['user_id'];

/* ===============================
   ✅ FETCH CITY + LOCALITY FROM DB
================================ */
$city = '';
$locality = '';

$stmt = $con->prepare("
    SELECT u.city_id, cp.locality_id
    FROM jos_app_users u
    LEFT JOIN jos_app_candidate_profile cp 
        ON u.profile_id = cp.id
    WHERE u.id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $city = trim($row['city_id'] ?? '');
        $locality = trim($row['locality_id'] ?? '');
    }
}

/* ===============================
   ✅ SAVE INTO SESSION (SYNC FIX)
================================ */
$_SESSION['user']['city_name'] = $city;
$_SESSION['user']['locality_name'] = $locality;


/* ===============================
   LOAD SLIDER DIRECT FROM DB
================================ */

$slider_list = [];
$profile_type = 3;

$sliderQuery = "SELECT id, title, image, action_type, action_value
                FROM jos_app_slider
                WHERE profile_type = ? AND status = 1
                ORDER BY id DESC";

$stmtS = $con->prepare($sliderQuery);
$stmtS->bind_param("i", $profile_type);
$stmtS->execute();
$resS = $stmtS->get_result();

while ($row = $resS->fetch_assoc()) {
    $row['image'] = !empty($row['image'])
        ? DOMAIN_URL . "webservices/" . ltrim($row['image'], '/')
        : DOMAIN_URL . "assets/img/slider-default.jpg";

    $slider_list[] = $row;
}

/*API FETCHING STARTED HERE*/

$apierror = '';

$user_id = $_SESSION['user_id']; // or set manually
// define('API_WEB_URL', 'https://pacweb.inv11.in/webservices/');

$url = API_WEB_URL . "/getWalletbalance.php";

$data = [
    "user_id" => $user_id
];

// INIT CURL
$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

$response = curl_exec($ch);

// ERROR HANDLING (CURL LEVEL)
if (curl_errno($ch)) {
    $apierror = "Curl Error: " . curl_error($ch);
}

curl_close($ch);

$result = json_decode($response, true);

// print_r($result);
// exit;

if (isset($result['status']) || $result['status'] == true) {
    // ✅ SUCCESS → STORE VARIABLES
    $wallet_balance = $result['wallet_balance'] ?? "0.00";
    $total_cashback_earned = $result['total_cashback_earned'] ?? "0.00";
    $total_referrals = $result['total_referrals'] ?? 0;
} else {
    $apierror = $result['message'] ?? "API returned error";
}


?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pacific iConnect – Hire or Get Hired</title>


    <link rel="stylesheet" href="/style.css">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">


    <style>
        :root {
            --primary: #483ea8;
            --blue-gradient: linear-gradient(135deg, #60a5fa 0%, #2563eb 100%);
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --bg-body: #f8fafc;
            --white: #ffffff;
            --border: #e2e8f0;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }




        /* --- CONTENT CONTAINER --- */
        .main-container {
            max-width: 1300px;
            margin: 20px auto;
            padding: 0 20px;
        }

        /* --- SLIDER --- */
        .slider-box {
            width: 100%;
            height: 320px;
            border-radius: 24px;
            overflow: hidden;
            background: #ddd;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .slider-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* --- EARNINGS SECTION (PARALLEL ON ALL DEVICES) --- */
        .earnings-container {
            background: var(--white);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            margin-bottom: 40px;
            display: flex;
            justify-content: space-around;
            align-items: center;
            text-align: center;
        }

        .earn-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .earn-icon-box {
            width: 50px;
            height: 50px;
            background: var(--blue-gradient);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
            box-shadow: 0 6px 15px rgba(37, 99, 235, 0.2);
        }

        .earn-info span {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .earn-info b {
            font-size: 1.2rem;
            color: var(--text-dark);
            display: block;
            margin-top: 2px;
        }

        /* --- QUICK ACTIONS --- */
        .section-title {
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 20px;
            padding-left: 5px;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .quick-action-card {
            background: white;
            border-radius: 18px;
            padding: 15px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: 0.3s;
            cursor: pointer;
            box-shadow: var(--shadow);
        }

        .a-icon-circle {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        /* --- MOBILE RESPONSIVE --- */
        @media (max-width: 768px) {
            header {
                padding: 12px 15px;
            }

            .logo {
                font-size: 1.1rem;
            }

            .location,
            .profile-name {
                display: none;
            }

            .slider-box {
                height: 180px;
                border-radius: 16px;
            }

            /* Parallel icons on mobile */
            .earnings-container {
                padding: 15px 10px;
                gap: 5px;
            }

            .earn-icon-box {
                width: 42px;
                height: 42px;
                font-size: 1.1rem;
                border-radius: 10px;
            }

            .earn-info span {
                font-size: 0.6rem;
            }

            .earn-info b {
                font-size: 0.9rem;
            }

            .quick-actions-grid {
                grid-template-columns: 1fr 1fr;
            }

            .quick-action-card {
                padding: 12px;
                gap: 8px;
            }

            .quick-action-card b {
                font-size: 0.8rem;
            }

            .quick-action-card span {
                font-size: 0.65rem;
            }
        }

        .quick-action-card {
            text-decoration: none;
            color: inherit;
        }

        .quick-action-card:hover {
            background: #f1f5f9;
            /* light grey */
            transform: translateY(-2px);
        }

        .quick-action-card {
            transition: all 0.25s ease;
        }

        /* layout fix */
        .quick-action-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* left section */
        .a-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* arrow style */
        .a-arrow {
            color: #94a3b8;
            font-size: 14px;
            transition: 0.2s;
        }

        /* hover animation */
        .quick-action-card:hover .a-arrow {
            transform: translateX(4px);
            color: #475569;
        }
    </style>

</head>

<body>

    <?php include "includes/preloader.php"; ?>
    <?php include "includes/promoter_header.php"; ?>

    <!-- ================= SLIDER ================= -->
    <div class="main-container">
        <?php if (!empty($slider_list)): ?>

            <div class="slider-wrapper">
                <div class="slider-scroll" id="cardSlider">

                    <?php
                    $themes = ['c1', 'c2', 'c3', 'c4'];
                    $i = 0;

                    foreach ($slider_list as $slide):

                        $theme = $themes[$i % 4];

                        /* Handle action types */
                        $url = '#';

                        if (($slide['action_type'] ?? '') === 'link') {
                            $url = $slide['action_value'];
                        } elseif (($slide['action_type'] ?? '') === 'job') {
                            $url = "/jobs/" . $slide['action_value'];
                        }

                    ?>

                        <a
                            href="<?= htmlspecialchars($url); ?>"
                            class="card <?= $theme ?>"
                            style="background-image:url('<?= htmlspecialchars($slide['image'] ?? '/assets/img/slider-default.jpg'); ?>');
        background-size:cover;
        background-position:center;">


                        </a>

                    <?php
                        $i++;
                    endforeach;
                    ?>

                </div>

                <div class="dots">

                    <?php foreach ($slider_list as $k => $v): ?>
                        <div class="dot <?= $k == 0 ? 'active' : '' ?>"></div>
                    <?php endforeach; ?>

                </div>

            </div>

        <?php endif; ?>





        <div class="earnings-container">

            <a href="transaction_history.php" class="earn-item">
                <div class="earn-icon-box">
                    <i class="fas fa-wallet" style="color:#ffd700"></i>
                </div>
                <div class="earn-info">
                    <span>Wallet Balance</span>
                    <b>₹ <?= $wallet_balance ?></b>
                </div>
            </a>

            <a href="referral_list.php" class="earn-item">
                <div class="earn-icon-box">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="earn-info">
                    <span>Referrals Converted</span>
                    <b><?= $total_referrals ?></b>
                </div>
            </a>

            <a href="#" class="earn-item">
                <div class="earn-icon-box">
                    <i class="fas fa-chart-line" style="color:#4ade80"></i>
                </div>
                <div class="earn-info">
                    <span>Referral Cashback</span>
                    <b><?= $total_cashback_earned ?></b>
                </div>
            </a>

        </div>

        <h2 class="section-title">Quick Actions</h2>
        <div class="quick-actions-grid">

            <a href="refer_and_earn.php" class="quick-action-card">

                <div class="a-left">
                    <div class="a-icon-circle" style="background:#fffbeb; color:#f59e0b;">
                        <i class="fas fa-gift"></i>
                    </div>

                    <div class="a-text">
                        <b>Refer & Earn</b><br>
                        <span>Invite friends</span>
                    </div>
                </div>

                <!-- ✅ RIGHT ARROW -->
                <div class="a-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>

            </a>
            <a href="#" class="quick-action-card">

                <div class="a-left">
                    <div class="a-icon-circle" style="background:#fffbeb; color:#f59e0b;">
                        <i class="fas fa-bullhorn"></i>
                    </div>

                    <div class="a-text">
                        <b>Promote</b><br>
                        <span>Marketing</span>
                    </div>
                </div>

                <!-- ✅ RIGHT ARROW -->
                <div class="a-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>

            </a>
            <a href="#" class="quick-action-card">

                <div class="a-left">
                    <div class="a-icon-circle" style="background:#fffbeb; color:#f59e0b;">
                        <i class="fas fa-trophy"></i>
                    </div>

                    <div class="a-text">
                        <b>Performance</b><br>
                        <span>Rankings</span>
                    </div>
                </div>

                <!-- ✅ RIGHT ARROW -->
                <div class="a-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>

            </a>

            <a href="wallet.php" class="quick-action-card">

                <div class="a-left">
                    <div class="a-icon-circle" style="background:#fffbeb; color:#f59e0b;">
                        <i class="fas fa-wallet"></i>
                    </div>

                    <div class="a-text">
                        <b>My Wallet</b><br>
                        <span>Balance</span>
                    </div>
                </div>

                <!-- ✅ RIGHT ARROW -->
                <div class="a-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>

            </a>



        </div>
    </div>

    <script>
        const words = [
            "<?= addslashes($welcome_message ?: 'Instantly.') ?>",
            "Directly.",
            "Securely."
        ];
        let i = 0;
        const heading = document.getElementById("typewriter");

        setInterval(() => {
            if (!heading) return;
            heading.textContent = words[i];
            i = (i + 1) % words.length;
        }, 2500);
    </script>

    <script>
        const slider = document.getElementById('cardSlider');
        const dots = document.querySelectorAll('.dot');

        if (slider) {

            let scrollInterval;
            let isUserInteracting = false;

            function updateActiveDot() {

                const cardWidth = slider.querySelector('.card').offsetWidth + 20;
                const index = Math.round(slider.scrollLeft / cardWidth);

                dots.forEach((dot, i) => {
                    dot.classList.toggle('active', i === index);
                });

            }

            function startAutoSlide() {

                scrollInterval = setInterval(() => {

                    if (!isUserInteracting) {

                        const cardWidth = slider.querySelector('.card').offsetWidth + 20;
                        const maxScroll = slider.scrollWidth - slider.clientWidth;

                        if (slider.scrollLeft >= maxScroll - 10) {

                            slider.scrollTo({
                                left: 0,
                                behavior: 'smooth'
                            });

                        } else {

                            slider.scrollBy({
                                left: cardWidth,
                                behavior: 'smooth'
                            });

                        }

                    }

                }, 3000);

            }

            slider.addEventListener('touchstart', () => isUserInteracting = true);
            slider.addEventListener('mousedown', () => isUserInteracting = true);

            window.addEventListener('touchend', () => {
                setTimeout(() => isUserInteracting = false, 2000);
            });

            window.addEventListener('mouseup', () => {
                setTimeout(() => isUserInteracting = false, 2000);
            });

            slider.addEventListener('scroll', updateActiveDot);

            startAutoSlide();

        }
    </script>



</body>

</html>