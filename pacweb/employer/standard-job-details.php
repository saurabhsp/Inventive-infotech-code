<?php
$jobid = $_POST['id'] ?? '';
$userid = 741;

$url = "https://beta.inv51.in/webservices/getSinglejobvacany.php";

$data = [
    "id" => $jobid,
    "userid" => $userid
];

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);

curl_close($ch);

$result = json_decode($response, true);

$job = $result['data'] ?? [];

$logo = !empty($job['company_logo'])
    ? $job['company_logo']
    : '/assets/images/default-company.png';

$alreadyApplied = $job['application_status'] ?? false;

function safe($v)
{
    return ($v && trim($v) != "") ? htmlspecialchars($v) : "Not specified";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= safe($job['job_position']); ?> | Pacific iConnect</title>


    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">





    <!-- ✅ COPY CSS FROM jobdetails.html -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="/assets/css/main.css">

    <!-- ================= KEEP SAME CSS ================= -->
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

            /* Standard Job Specific */
            --border-light: #e0e0e0;
            --share-bg: #e3f2fd;
            --share-text: #1565c0;
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
            font-size: 15.5px;
            /* Base font size */
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
            padding: 0 15px;
        }

        /* --- 1. HEADER --- */
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
            padding: 30px 0;
        }

        .job-grid-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            /* 66% Left, 33% Right */
            gap: 25px;
            align-items: start;
        }

        /* Common Card Style */
        .content-card {
            background: var(--white);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #eee;
            margin-bottom: 25px;
        }

        /* --- 3. STANDARD HEADER (Simple) --- */
        .job-header-card {
            display: flex;
            gap: 20px;
            align-items: center;
            background: var(--white);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #eee;
            margin-bottom: 25px;
        }

        /* Standard Logo (No Ring) */
        .logo-box-standard {
            width: 80px;
            height: 80px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #eee;
            border-radius: 50%;
            padding: 5px;
        }

        .company-logo {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: contain;
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
            display: block;
        }

        /* --- 4. SIDEBAR (Overview & Actions) --- */
        .job-sidebar {
            position: sticky;
            top: 90px;
        }

        .overview-grid {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        /* Boxed Style for Stats */
        .stat-box {
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            background: #fff;
        }

        .stat-icon {
            font-size: 1.2rem;
            color: var(--text-dark);
        }

        .stat-text {
            font-weight: 400;
            font-size: 1rem;
            color: var(--text-dark);
        }

        .stat-label {
            font-size: 0.85rem;
            color: #666;
            font-weight: bolder;
            margin-right: 5px;
        }

        .btn-apply-main {
            width: 100%;
            background: var(--primary);
            color: white;
            padding: 15px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1.1rem;
            border: none;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(72, 62, 168, 0.3);
            transition: 0.2s;
        }

        .btn-apply-main:hover {
            background: #322b7a;
            transform: translateY(-2px);
        }

        /* --- 5. MAIN CONTENT --- */
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 15px;
            display: block;
        }

        /* Requirements Box */
        .req-box {
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 20px;
            background: #fafafa;
            margin-bottom: 30px;
        }

        .req-list {
            list-style: none;
            padding-left: 5px;
        }

        .req-list li {
            position: relative;
            padding-left: 15px;
            margin-bottom: 8px;
            color: #444;
            font-size: 0.95rem;
        }

        .req-list li::before {
            content: '•';
            position: absolute;
            left: 0;
            color: var(--text-dark);
            font-weight: bold;
        }

        /* Contact Details */
        .contact-section {
            margin-top: 20px;
        }

        .hr-name {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.1rem;
            display: block;
            margin-bottom: 5px;
        }

        .hr-address {
            color: #444;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        /* Action Buttons Row */
        .contact-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-action-outline {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
            border: 1px solid var(--primary);
            border-radius: 30px;
            background: white;
            color: var(--primary);
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-action-outline:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
        }

        /* Share Box */
        .share-box {
            background: #f0f7ff;
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
            border: 1px solid #d0e4f7;
        }

        .share-title {
            font-weight: 700;
            color: #1976d2;
            margin-bottom: 5px;
            font-size: 1rem;
        }

        .share-desc {
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 15px;
        }

        .btn-share {
            background: #1565c0;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .btn-share:hover {
            background: #0d47a1;
        }



        @media (max-width: 900px) {

            .nav-menu {
                display: none;
            }

            .main-content {
                padding: 15px 0;
            }

            .job-grid-layout {
                display: flex;
                flex-direction: column;
            }

            .job-sidebar {
                order: -1;
                width: 100%;
                position: static;
            }

            /* HEADER */
            .job-header-card {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 10px;
                padding: 15px;
            }

            .logo-box-standard {
                width: 60px;
                height: 60px;
            }

            .job-title-info h1 {
                font-size: 1.3rem;
            }

            .company-name {
                font-size: 0.95rem;
            }

            /* CARD */
            .content-card {
                padding: 15px;
                border-radius: 12px;
            }

            .req-box {
                padding: 15px;
            }

            /* STATS */
            .overview-grid {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .stat-box {
                padding: 10px;
                border-radius: 10px;
            }

            .stat-text {
                font-size: 0.9rem;
            }

            /* CONTACT BUTTONS */
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

            /* ✅ FIXED STICKY BUTTON */
            .btn-apply-main {
                position: fixed;
                bottom: 15px;
                /* 🔥 IMPORTANT */
                left: 10px;
                right: 10px;
                width: auto;

                background: var(--primary);
                color: white;
                padding: 14px;
                font-size: 1rem;
                font-weight: 700;
                border: none;

                border-radius: 30px;
                /* 🔥 modern look */
                z-index: 9999;

                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);

                padding-bottom: calc(14px + env(safe-area-inset-bottom));
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
        }

        /* ✅ IMPORTANT: keep this OUTSIDE media */
        body {
            padding-bottom: 90px;
        }

        @media (max-width: 900px) {

            footer,
            .footer {
                display: none !important;
            }
        }
    </style>

</head>

<body>
    <?php // include "includes/preloader.php"; 
    ?>
    <?php // include "includes/header.php"; 
    ?>

    <div class="container main-content">

        <div style="margin-bottom:20px;color:#666;font-size:.9rem;">
            <a href="/">Home</a> › <?= safe($job['job_position']); ?>
        </div>

        <!-- HEADER -->

        <div class="job-header-card">

            <div class="logo-box-standard">
                <!--<img src="<?= $logo ?>" class="company-logo">-->
                <img src="<?= $job['company_logo']; ?>" class="company-logo">

            </div>

            <div class="job-title-info">
                <h1><?= safe($job['job_position']); ?></h1>
                <span class="company-name"><?= safe($job['company_name']); ?></span>
            </div>

        </div>

        <div class="job-grid-layout">

            <!-- LEFT -->

            <main>

                <div class="content-card">

                    <h3 class="section-title">Job Requirements</h3>

                    <div class="req-box">
                        <ul>
                            <li>Gender: <?= safe($job['gender']); ?></li>
                            <li>Education: <?= safe($job['qualification']); ?></li>
                        </ul>
                    </div>

                    <h3 class="section-title">Contact Details</h3>

                    <div>

                        <b><?= safe($job['contact_person']); ?></b>
                        <p><?= safe($job['interview_address']); ?></p>

                        <div class="contact-actions">

                            <?php // if (!empty($_SESSION['is_logged_in'])): 
                            ?>

                            <!-- CALL -->
                            <a href="tel:<?= safe($job['mobile_no']); ?>"
                                class="btn-action-outline"
                                onclick="logAction(1)">
                                <i class="fas fa-phone-alt"></i> Call HR
                            </a>

                            <!-- WHATSAPP -->
                            <a target="_blank"
                                href="https://wa.me/91<?= preg_replace('/\D/', '', $job['mobile_no']); ?>"
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


                        </div>

                        <div class="share-box">
                            <div class="share-title">Share This Job</div>
                            <p class="share-desc">Know someone who might be interested? Let them know now.</p>
                            <button class="btn-share"><i class="fas fa-paper-plane"></i> Share Now</button>
                        </div>
                    </div>

            </main>

            <!-- RIGHT -->

            <aside class="job-sidebar">

                <div class="content-card">

                    <!-- REQUIRED WRAPPER -->
                    <div class="overview-grid">

                        <div class="stat-box">
                            <i class="fas fa-map-marker-alt stat-icon" style="color:#e53935;"></i>
                            <div>
                                <span class="stat-label"><b>Location:</b></span>
                                <span class="stat-text"><?= safe($job['city']); ?></span>
                            </div>
                        </div>

                        <div class="stat-box">
                            <i class="fas fa-rupee-sign stat-icon" style="color:#fbc02d;"></i>
                            <div>
                                <span class="stat-label">Salary:</span>
                                <span class="stat-text">
                                    ₹<?= safe($job['salary_from']); ?> – <?= safe($job['salary_to']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="stat-box">
                            <i class="fas fa-briefcase stat-icon" style="color:#795548;"></i>
                            <div>
                                <span class="stat-label">Experience:</span>
                                <span class="stat-text">
                                    <?= safe($job['experience_from']); ?> – <?= safe($job['experience_to']); ?>
                                </span>
                            </div>
                        </div>

                    </div>

                    <?php // if (empty($_SESSION['is_logged_in'])): 
                    ?>

                    <!-- <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']); ?>" 
   class="btn-apply-main"
   style="display:block;text-align:center;">
   Apply Now
</a> -->

                    <?php // else: 
                    ?>

                    <?php //if($alreadyApplied): 
                    ?>

                    <!-- <button
class="btn-apply-main"
disabled
style="display:block;text-align:center;cursor:not-allowed;background:#bdbdbd;">
Applied
</button> -->

                    <?php // else: 
                    ?>

                    <button
                        class="btn-apply-main"
                        id="applyBtn"
                        data-job="<?= $job['id']; ?>"
                        data-type="2"
                        style="display:block;text-align:center;">
                        Apply Now
                    </button>

                    <?php // endif; 
                    ?>

                    <?php //  endif; 
                    ?>
                </div>

            </aside>




        </div>
    </div>


</body>

</html>