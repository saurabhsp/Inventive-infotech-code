<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "../web_api/includes/db_config.php";

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

$user = $_SESSION['user'];


$userid = $user['id'];
$jobid = $_POST['id'] ?? '';



$url = API_BASE_URL . "getSinglewalkininterview.php";

$data = [
    "id" => $jobid,
    "userid" => $userid
];

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

if (!$result || $result['status'] != 'success') {
    die("Job Not Found");
}

$job = $result['data'];
$plan = $job['plan_display_status'];

if ($plan == 1) {
    $plan_class = "gold";
    $plan_text  = "GOLD MEMBER";
} elseif ($plan == 2) {
    $plan_class = "silver";
    $plan_text  = "SILVER MEMBER";
} else {
    $plan_class = "bronze";
    $plan_text  = "BRONZE MEMBER";
}


function safe($v)
{
    return ($v && trim($v) != "") ? htmlspecialchars($v) : "Not specified";
}

?>




<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= safe($job['job_position']); ?> – Pacific iConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">


    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">




    <!-- ✅ COPY CSS FROM jobdetails.html -->
    <link rel="stylesheet" href="style.css">

    <style>
        :root {
            /* Theme Colors */
            --primary: #483EA8;
            --primary-light: #eceaf9;
            --secondary: #ff6f00;
            --bg-body: #f4f6f9;
            --white: #ffffff;
            --text-dark: #1a1a1a;
            --text-grey: #555555;
            --green: #25D366;
            --silver-bg: #9e9e9e;
            --primary-dark: #392f9a;

            /* Gradients */
            --gold-ring: conic-gradient(#FFD700, #FDB931, #FFED86, #FDB931, #FFD700);
            --silver-ring: conic-gradient(#E0E0E0, #B0B0B0, #F5F5F5, #B0B0B0, #E0E0E0);
            --band-silver: linear-gradient(to bottom, #ecf0f1, #bdc3c7);
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
        }

        a {
            text-decoration: none;
            transition: 0.3s;
            color: inherit;
        }

        ul {
            list-style: none;
        }

        button {
            cursor: pointer;
        }

        .container {
            max-width: 1150px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* --- 1. HEADER (Same as Home) --- */
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

        .nav-menu {
            display: flex;
            gap: 25px;
        }

        .nav-item {
            font-weight: 600;
            color: #555;
            font-size: 1rem;
        }

        .nav-item:hover {
            color: var(--primary);
        }

        .nav-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .login-link {
            font-weight: 700;
            color: var(--primary);
        }

        .btn-header-cta {
            background: var(--primary);
            color: white;
            padding: 8px 22px;
            border-radius: 30px;
            font-weight: 600;
            border: none;
        }

        /* --- 2. LAYOUT GRID --- */
        .main-content {
            padding: 40px 0;
        }

        .job-grid-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            /* 66% Left, 33% Right */
            gap: 30px;
            align-items: start;
        }

        /* Common Card Style */
        .content-card {
            background: var(--white);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #eee;
            margin-bottom: 25px;
        }

        /* --- 3. LEFT COLUMN (Main Info) --- */

        /* Header Block */
        .job-header-block {
            display: flex;
            gap: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 25px;
            margin-bottom: 25px;
        }

        /* Logo Logic (Silver Member) */
        .logo-box {
            position: relative;
            width: 80px;
            height: 80px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .shiny-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            padding: 3px;
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
        }

        .ring-silver {
            background: var(--silver-ring);
            animation: spin 4s linear infinite;
        }

        .company-logo {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            object-fit: contain;
            background: #fff;
            z-index: 2;
            border: 2px solid #fff;
        }

        .band-badge {
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            padding: 3px 10px;
            border-radius: 12px;
            z-index: 5;
            white-space: nowrap;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            color: #333;
            background: var(--band-silver);
        }

        .job-title-info h1 {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 5px;
            line-height: 1.2;
        }

        .company-name {
            font-size: 1.1rem;
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .kyc-verified {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--green);
            border: 1px solid var(--green);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        /* Tags */
        .tags-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 0;
        }

        .tag-pill {
            background: var(--primary-light);
            color: var(--primary);
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Content Sections */
        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary);
        }

        .req-list {
            list-style: none;
            padding-left: 5px;
        }

        .req-list li {
            position: relative;
            padding-left: 20px;
            margin-bottom: 10px;
            color: #444;
        }

        .req-list li::before {
            content: '•';
            position: absolute;
            left: 0;
            color: var(--primary);
            font-weight: bold;
            font-size: 1.2rem;
            line-height: 1;
            top: -2px;
        }

        .contact-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid var(--primary);
        }

        .contact-name {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 5px;
            display: block;
        }

        .contact-address {
            font-size: 0.95rem;
            color: #666;
        }

        /* --- 4. RIGHT COLUMN (Sidebar) --- */
        .job-sidebar {
            position: sticky;
            top: 90px;
        }

        .overview-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .overview-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .icon-box {
            width: 45px;
            height: 45px;
            background: #fff5e6;
            color: var(--secondary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        /* Varied Icon Colors */
        .icon-box.salary {
            background: #e8f5e9;
            color: #2e7d32;
        }

        /* Green */
        .icon-box.loc {
            background: #e3f2fd;
            color: #1565c0;
        }

        /* Blue */
        .icon-box.exp {
            background: #fff3e0;
            color: #ef6c00;
        }

        /* Orange */
        .icon-box.type {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        /* Purple */

        .ov-label {
            font-size: 0.85rem;
            color: #888;
            font-weight: 600;
            display: block;
            margin-bottom: 2px;
        }

        .ov-value {
            font-size: 1rem;
            color: var(--text-dark);
            font-weight: 700;
            line-height: 1.3;
        }

        .btn-apply-now {
            width: 100%;
            background: var(--primary);
            color: white;
            padding: 15px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            border: none;
            margin-top: 15px;
            box-shadow: 0 5px 15px rgba(72, 62, 168, 0.3);
            transition: 0.2s;
        }

        .btn-apply-now:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Animation */
        @keyframes spin {
            100% {
                transform: rotate(360deg);
            }
        }

        /* ================= MOBILE RESPONSIVE FINAL ================= */
        @media (max-width: 900px) {

            /* Hide desktop nav */
            .nav-menu {
                display: none;
            }

            /* Container spacing */
            .container {
                padding: 0 12px;
            }

            /* Layout spacing */
            .main-content {
                padding: 15px 0 80px;
            }

            /* Stack layout */
            .job-grid-layout {
                display: flex;
                flex-direction: column;
            }

            /* Content first */
            main {
                order: 1;
            }

            /* Sidebar below */
            .job-sidebar {
                order: 2;
                width: 100%;
                position: static;
            }

            /* Cards */
            .content-card {
                padding: 15px;
                border-radius: 12px;
            }

            /* Header fix */
            .job-header-block {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 10px;
            }

            .job-title-info h1 {
                font-size: 1.3rem;
                line-height: 1.3;
            }

            .company-name {
                font-size: 0.95rem;
            }

            /* Contact buttons */
            .contact-actions {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .btn-action-outline {
                width: 100%;
                font-size: 0.85rem;
                padding: 10px;
            }

            /* ================= APPLY BUTTON ================= */
            .job-sidebar .btn-apply-now {
                position: fixed;
                bottom: calc(70px + env(safe-area-inset-bottom));
                left: 12px;
                right: 12px;

                background: var(--primary);
                color: #fff;

                padding: 14px;
                font-size: 1rem;
                font-weight: 700;

                border-radius: 30px;
                border: none;

                z-index: 9999;

                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
            }

            /* Prevent content hidden behind button */
            body {
                padding-bottom: 90px;
            }

            /* ================= HIDE FOOTER ================= */
            footer,
            .footer,
            .footer-grid,
            .mega-footer,
            .footer-bottom {
                display: none !important;
                height: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
                overflow: hidden !important;
            }

        }

        .shiny-ring.gold {
            background: conic-gradient(#FFD700, #fff4cc, #FFD700);
            animation: spin 3s linear infinite;
        }

        .shiny-ring.silver {
            background: conic-gradient(#C0C0C0, #ffffff, #C0C0C0);
            animation: spin 3s linear infinite;
        }

        .shiny-ring.bronze {
            background: conic-gradient(#cd7f32, #f3c299, #cd7f32);
            animation: spin 3s linear infinite;
        }

        .member-badge.gold {
            background: #DAA520;
        }

        .member-badge.silver {
            background: #9e9e9e;
        }

        .member-badge.bronze {
            background: #cd7f32;
        }
    </style>


</head>

<body>

    <?php include "includes/preloader.php";
    ?>
    <?php include "includes/header.php"; ?>

    <div class="container main-content">

        <!-- ================= BREADCRUMB ================= -->
        <div style="margin-bottom:20px;color:#666;font-size:.9rem;">
            <!--<a href="index.php">Home</a>-->
            <a href="/">Home</a>

            <i class="fas fa-chevron-right"></i>
            <?= safe($job['job_position']); ?>
        </div>

        <div class="job-grid-layout">

            <!-- ================= LEFT ================= -->
            <main>

                <div class="content-card">

                    <div class="job-header-block">

                        <div class="logo-box">

                            <div class="shiny-ring <?= $plan_class ?>"></div>

                            <img src="<?= $job['company_logo']; ?>" class="company-logo large">

                            <div class="member-badge <?= $plan_class ?>">
                                <?= $plan_text ?>
                            </div>

                        </div>

                        <div class="job-title-info">
                            <h1><?= safe($job['job_position']); ?></h1>
                            <span class="company-name"><?= safe($job['company_name']); ?></span>
                            <?php if (!empty($job['kyc_verified']) && $job['kyc_verified'] === true): ?>
                                <div class="kyc-verified">
                                    <i class="fas fa-check-circle"></i> KYC Verified
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- TAGS -->
                    <div class="tags-container">
                        <span class="tag-pill"><?= safe($job['work_model']); ?></span>
                        <span class="tag-pill"><?= safe($job['work_shift']); ?></span>
                        <span class="tag-pill"><?= safe($job['job_type']); ?></span>
                    </div>

                    <!-- REQUIREMENTS -->
                    <h3 class="section-title">
                        <i class="fas fa-list-check"></i> Job Requirements
                    </h3>

                    <ul class="req-list">
                        <li><b>Gender:</b> <?= safe($job['gender']); ?></li>
                        <li><b>Education:</b> <?= safe($job['qualification']); ?></li>
                        <li><b>Skills:</b> <?= safe($job['skills_required']); ?></li>
                        <li><b>Assets:</b> <?= safe($job['work_equipment']); ?></li>
                    </ul>

                    <!-- DESCRIPTION -->
                    <h3 class="section-title">
                        <i class="fas fa-file-alt"></i> Job Description
                    </h3>

                    <p><?= safe($job['job_description']); ?></p>

                    <!-- CONTACT -->
                    <h3 class="section-title">
                        <i class="fas fa-address-card"></i> Contact Details
                    </h3>

                    <div class="contact-box">

                        <span class="contact-name"><?= safe($job['contact_person_name']); ?></span>

                        <div class="contact-address">
                            <?= safe($job['interview_address']); ?>
                        </div>

                        <div class="contact-actions">

                            <?php if (!empty($_SESSION['is_logged_in'])): ?>

                                <!-- CALL -->
                                <a href="tel:<?= $job['contact_no'] ?: $job['mobile_no']; ?>"
                                    class="btn-action-outline"
                                    onclick="logAction(1)">
                                    <i class="fas fa-phone-alt"></i> Call HR
                                </a>

                                <!-- WHATSAPP -->
                                <a target="_blank"
                                    href="https://wa.me/91<?= preg_replace('/\D/', '', $job['contact_no'] ?: $job['mobile_no']); ?>"
                                    class="btn-action-outline"
                                    onclick="logAction(2)">
                                    <i class="fas fa-comment-dots"></i> Chat
                                </a>

                                <!-- MAP -->
                                <a target="_blank"
                                    href="https://www.google.com/maps?q=<?= urlencode($job['interview_address']); ?>"
                                    class="btn-action-outline"
                                    onclick="logAction(3)">
                                    <i class="fas fa-map-marker-alt" style="color:#e53935;"></i> View Map
                                </a>

                            <?php else: ?>

                                <!-- CALL LOGIN -->
                                <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']); ?>"
                                    class="btn-action-outline">
                                    <i class="fas fa-phone-alt"></i> Call HR
                                </a>

                                <!-- CHAT LOGIN -->
                                <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']); ?>"
                                    class="btn-action-outline">
                                    <i class="fas fa-comment-dots"></i> Chat
                                </a>

                                <!-- MAP LOGIN -->
                                <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']); ?>"
                                    class="btn-action-outline">
                                    <i class="fas fa-map-marker-alt" style="color:#e53935;"></i> View Map
                                </a>

                            <?php endif; ?>

                        </div>
                    </div>


                    <div class="share-box">

                        <div class="share-title">Share This Job</div>

                        <p class="share-desc">
                            Know someone who might be interested? Let them know now.
                        </p>

                        <button class="btn-share">
                            <i class="fas fa-paper-plane"></i> Share Now
                        </button>

                    </div>


                </div>


            </main>

            <!-- ================= RIGHT ================= -->
            <aside class="job-sidebar">

                <div class="content-card">

                    <h3>Job Overview</h3>

                    <div class="overview-item">
                        <div class="icon-box salary"><i class="fas fa-rupee-sign"></i></div>
                        <div>
                            <span class="ov-label">Salary</span>
                            <span class="ov-value">
                                ₹<?= safe($job['salary_from']); ?> – <?= safe($job['salary_to']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="overview-item">
                        <div class="icon-box loc"><i class="fas fa-map-marker-alt"></i></div>
                        <div>
                            <span class="ov-label">Location</span>
                            <span class="ov-value">
                                <?= safe($job['locality']); ?>, <?= safe($job['city']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="overview-item">
                        <div class="icon-box exp"><i class="fas fa-briefcase"></i></div>
                        <div>
                            <span class="ov-label">Experience</span>
                            <span class="ov-value">
                                <?= safe($job['experience_from']); ?> – <?= safe($job['experience_to']); ?>
                            </span>
                        </div>

                    </div>
                    <div class="overview-item">
                        <div class="icon-box type"><i class="fas fa-user-clock"></i></div>
                        <div>
                            <span class="ov-label">Job Type</span>
                            <span class="ov-value"><?= safe($job['job_type']); ?></span>

                        </div>
                    </div>


                    <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']); ?>"
                        class="btn-apply-now"
                        style="display:block;text-align:center;">
                        Apply Now
                    </a>

                    <div style="text-align:center; margin-top:15px; font-size:0.85rem; color:#888;">
                        <i class="fas fa-shield-alt"></i> 100% Safe & Verified Job
                    </div>

                </div>

            </aside>

        </div>
    </div>

    <?php include "includes/bottom-bar.php"; ?>

    <script>
        window.onload = () => document.getElementById("global-preloader")?.remove();
    </script>
</body>

</html>