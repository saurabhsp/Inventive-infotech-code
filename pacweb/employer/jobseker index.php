text/x-generic index.php ( PHP script, UTF-8 Unicode text )
<?php
require_once __DIR__ . '/../includes/session.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ===============================
   ✅ LOGIN CHECK
================================ */
if (empty($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

/* ===============================
   ✅ DB CONNECTION
================================ */
require_once '../includes/db_config.php';

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
   ✅ API CALL FUNCTION
================================ */
function callAPI($url, $postData)
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        die("API Error: " . curl_error($ch));
    }

    curl_close($ch);

    return json_decode($response, true);
}

/* ===============================
   ✅ FIRST API CALL (WITH LOCALITY)
================================ */
$api_url = "https://pacweb.inv11.in/web_api/getJobseekerDashboard.php";

$postData = [
    "userid" => $user_id,
    "profile_type" => $_SESSION['user']['profile_type_id'] ?? 2,
    "city" => $city
];

if (!empty($locality)) {
    $postData["locality"] = $locality;
}

$data = callAPI($api_url, $postData);

/* ===============================
   ✅ FALLBACK (WITHOUT LOCALITY)
================================ */
if (
    empty($data['job_vacancies']) &&
    empty($data['walkin_interviews'])
) {
    unset($postData['locality']); // remove locality

    $data = callAPI($api_url, $postData);
}

/* ===============================
   ✅ FAIL SAFE (NO CRASH)
================================ */
if (!$data || !isset($data['status'])) {
    $data = [];
}

/* ===============================
   ✅ SAFE DATA FOR FRONTEND
================================ */
$walkin_interviews = $data['walkin_interviews'] ?? [];
$job_vacancies = $data['job_vacancies'] ?? [];
$slider_list = $data['sliders'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pacific iConnect – Hire or Get Hired</title>


    <link rel="stylesheet" href="/style.css">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">


</head>

<body>
    <?php
    include "../includes/preloader.php"; ?>

    <?php
    include "../includes/header.php"; ?>


    <!-- ================= SLIDER ================= -->

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

                        <!--<div style="display:flex;justify-content:space-between;">
            <span style="font-weight:bold;">Pacific iConnect</span>
            </div>
            -->

                                    <!--
            <div style="margin-top:auto;font-size:18px;font-weight:bold;">
            <?= htmlspecialchars($slide['title'] ?? ''); ?>
            </div>
            -->

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

    <!-- ================= HERO ================= -->

    <div class="power-hero">
        <div class="container">

            <h1 class="hero-title">
                Hire or Get Hired.
                <span class="typing-text" id="typewriter">Instantly.</span>
            </h1>

            <p class="hero-sub">India's Fastest Growing Direct-Hiring Platform</p>

        </div>
    </div>


    <!-- ================= PREMIUM ================= -->

    <div class="section-common bg-light">

        <div class="container">

            <div class="section-head">
                <div class="title-group">
                    <h2>Premium Jobs</h2>
                    <span class="urgent-badge">URGENT</span>
                </div>
                <a href="/premium_jobs_list.php" class="view-all-link">View All</a>

            </div>

            <div class="prem-grid">

                <?php foreach ($walkin_interviews as $job): ?>

                    <div class="job-card">

                        <div class="card-header-grid">

                            <div class="logo-col">
                                <div class="logo-ring-wrap">
                                    <div class="shiny-ring ring-silver"></div>
                                    <img src="<?= $job['company_logo']; ?>" class="company-logo">
                                </div>
                                <span class="silver-badge">PREMIUM</span>
                            </div>

                            <div class="info-col">
                                <h4><?= htmlspecialchars($job['job_position'] ?? ''); ?></h4>
                                <p><?= htmlspecialchars($job['company_name'] ?? ''); ?></p>

                                <span class="loc">
                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($job['city'] ?? ''); ?>
                                </span>

                                <div class="salary">
                                    ₹<?= $job['salary_from'] ?? '0'; ?> - ₹<?= $job['salary_to'] ?? '0'; ?>
                                </div>


                            </div>
                        </div>

                        <div class="tags-row">
                            <span class="job-tag"><?= htmlspecialchars($job['job_type'] ?? ''); ?></span>
                            <span class="job-tag"><?= htmlspecialchars($job['work_shift'] ?? ''); ?></span>

                        </div>

                        <div class="card-footer premium-footer">

                            <div class="kyc-badge">
                                <i class="fas fa-check-circle"></i> KYC Verified
                            </div>

                            <!-- SEO LINK -->

                            <a href="/jobs/<?= urlencode(strtolower($job['city'] ?? 'city')); ?>/<?= urlencode($job['slug'] ?? ''); ?>"
                                class="btn-arrow-circle">

                                <i class="fas fa-arrow-right"></i>
                            </a>


                        </div>

                    </div>

                <?php endforeach; ?>

            </div>
        </div>
    </div>



    <!-- ================= STANDARD ================= -->

    <div class="section-common bg-white">

        <div class="container">

            <div class="section-head">
                <h2>Standard Jobs</h2>
                <a href="/standard_jobdetails_list.php" class="view-all-link">View All</a>
            </div>

            <div class="prem-grid">

                <?php foreach ($job_vacancies as $job): ?>

                    <div class="job-card">

                        <div class="card-header-grid">

                            <div class="logo-col">
                                <img src="<?= $job['company_logo']; ?>"
                                    class="company-logo" style="border:1px solid #eee;">
                            </div>

                            <div class="info-col">

                                <h4><?= htmlspecialchars($job['job_position'] ?? ''); ?></h4>
                                <p><?= htmlspecialchars($job['company_name'] ?? ''); ?></p>


                                <span class="loc">
                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($job['city'] ?? ''); ?>
                                </span>

                                <div class="salary">
                                    ₹<?= $job['salary_from'] ?? '0'; ?> - ₹<?= $job['salary_to'] ?? '0'; ?>

                                </div>

                            </div>
                        </div>

                        <div class="tags-row">
                            <span class="job-tag">Standard</span>
                        </div>

                        <div class="card-footer standard-footer">

                            <!-- SEO LINK -->

                            <a href="/jobs/<?= urlencode(strtolower($job['city'] ?? 'city')); ?>/<?= urlencode($job['slug'] ?? ''); ?>"
                                class="job-arrow-btn">

                                <i class="fas fa-arrow-right"></i>
                            </a>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>
        </div>
    </div>
    <?php
    include "../includes/footer.php"; ?>


    <!-- UI JS ONLY -->
    <script>
        const words = ["Instantly.", "Directly.", "Securely."];
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