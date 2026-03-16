<?php
require_once __DIR__ . '/includes/session.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "includes/db_config.php";

/* ================= LOAD BY SLUG ================= */

if (!isset($_GET['slug'])) {
    header("HTTP/1.0 404 Not Found");
    exit("Job Not Found");
}

$slug = trim($_GET['slug']);

/* ================= FETCH STANDARD JOB ================= */

$sql = "
SELECT 
    j.*,

    jp.name AS job_position,

    sf.salaryrange AS salary_from,
    st.salaryrange AS salary_to,

    ef.name AS experience_from,
    et.name AS experience_to,

    g.name AS gender,
    q.name AS qualification,

    rp.company_logo

FROM jos_app_jobvacancies j

LEFT JOIN jos_crm_jobpost jp 
    ON j.job_position_id = jp.id

LEFT JOIN jos_crm_salary_range sf 
    ON j.salary_from = sf.id

LEFT JOIN jos_crm_salary_range st 
    ON j.salary_to = st.id

LEFT JOIN jos_app_experience_list ef 
    ON j.experience_from = ef.id

LEFT JOIN jos_app_experience_list et 
    ON j.experience_to = et.id

LEFT JOIN jos_crm_gender g 
    ON j.gender_id = g.id

LEFT JOIN jos_crm_education_status q 
    ON j.qualification_id = q.id

LEFT JOIN jos_app_recruiter_profile rp 
    ON j.recruiter_id = rp.id

WHERE j.slug = ?
LIMIT 1
";

$stmt = $con->prepare($sql);
$stmt->bind_param("s", $slug);
$stmt->execute();

$res = $stmt->get_result();

if (!$job = $res->fetch_assoc()) {
    header("HTTP/1.0 404 Not Found");
    exit("Job Not Found");
}

/* ================= LOGO ================= */

$job['company_logo'] =
    !empty($job['company_logo'])
    ? "/webservices/" . $job['company_logo']
    : "/webservices/uploads/logos/nologo.png";


/* ================= CHECK ALREADY APPLIED ================= */

$alreadyApplied = false;

if (isset($_SESSION['user_id'])) {

    $check = $con->prepare("
SELECT id
FROM jos_app_applications
WHERE userid = ? AND job_id = ?
LIMIT 1
");

    $check->bind_param("ii", $_SESSION['user_id'], $job['id']);
    $check->execute();
    $resultCheck = $check->get_result();

    if ($resultCheck->num_rows > 0) {
        $alreadyApplied = true;
    }
}

function safe($v)
{
    return ($v && trim($v) !== "") ? htmlspecialchars($v) : "Not specified";
}
?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= safe($job['job_position']); ?> | Pacific iConnect</title>

    <meta name="description"
        content="<?= safe($job['job_position']); ?> job in <?= safe($job['city_id']); ?>. Apply now on Pacific iConnect. Salary ₹<?= $job['salary_from']; ?> – ₹<?= $job['salary_to']; ?>">

    <link rel="canonical"
        href="https://pacweb.inv11.in/jobs/<?= urlencode(strtolower($job['city_id'])); ?>/<?= $job['slug']; ?>">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">
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
    </style>
    <!-- ================= GOOGLE JOBS STRUCTURED DATA ================= -->

    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "JobPosting",

            "title": "<?= addslashes($job['job_position']); ?>",

            "description": "<?= addslashes(strip_tags($job['job_description'] ?? '')); ?>",

            "datePosted": "<?= date('Y-m-d', strtotime($job['created_at'])); ?>",

            "validThrough": "<?= date('Y-m-d', strtotime($job['valid_till_date'])); ?>",

            "employmentType": "FULL_TIME",

            "identifier": {
                "@type": "PropertyValue",
                "name": "Pacific iConnect",
                "value": "<?= $job['id']; ?>"
            },

            "hiringOrganization": {
                "@type": "Organization",
                "name": "<?= addslashes($job['company_name']); ?>",
                "logo": "https://pacweb.inv11.in/<?= $job['company_logo']; ?>"
            },

            "jobLocation": {
                "@type": "Place",
                "address": {
                    "@type": "PostalAddress",
                    "addressLocality": "<?= addslashes($job['city_id']); ?>",
                    "addressCountry": "IN"
                }
            },

            "baseSalary": {
                "@type": "MonetaryAmount",
                "currency": "INR",
                "value": {
                    "@type": "QuantitativeValue",
                    "minValue": <?= (int)$job['salary_from']; ?>,
                    "maxValue": <?= (int)$job['salary_to']; ?>,
                    "unitText": "MONTH"
                }
            }
        }
    </script>
</head>

<body>
    <?php include "includes/preloader.php"; ?>
    <?php include "includes/header.php"; ?>

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
                                <span class="stat-text"><?= safe($job['city_id']); ?></span>
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

                    <?php if (empty($_SESSION['is_logged_in'])): ?>

                        <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']); ?>"
                            class="btn-apply-main"
                            style="display:block;text-align:center;">
                            Apply Now
                        </a>

                    <?php else: ?>

                        <?php if ($alreadyApplied): ?>

                            <button
                                class="btn-apply-main"
                                disabled
                                style="display:block;text-align:center;cursor:not-allowed;background:#bdbdbd;">
                                Applied
                            </button>

                        <?php else: ?>

                            <button
                                class="btn-apply-main"
                                id="applyBtn"
                                data-job="<?= $job['id']; ?>"
                                data-type="2"
                                style="display:block;text-align:center;">
                                Apply Now
                            </button>

                        <?php endif; ?>

                    <?php endif; ?>
                </div>

            </aside>




        </div>
    </div>

    <div id="applyPopup" class="dialog-overlay">

        <div class="dialog-box">

            <div class="dialog-header">
                <span class="dialog-site">pacweb.inv11.in</span>
            </div>

            <div class="dialog-body">
                Application Submitted Successfully.
            </div>

            <div class="dialog-footer">
                <button onclick="closeApplyPopup()" class="dialog-btn">OK</button>
            </div>

        </div>

    </div>


    <?php include "includes/footer.php"; ?>



    <script>
        /* ================= BUTTON ELEMENT ================= */

        const applyBtn = document.getElementById("applyBtn");


        /* ================= PAGE LOAD: CHECK APPLICATION STATUS ================= */

        document.addEventListener("DOMContentLoaded", function() {

            if (!applyBtn) return;

            fetch("/web_api/getSinglejobvacancy.php", {

                    method: "POST",

                    headers: {
                        "Content-Type": "application/json"
                    },

                    body: JSON.stringify({

                        id: applyBtn.dataset.job,
                        userid: <?= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0 ?>

                    })

                })

                .then(res => res.json())

                .then(data => {

                    if (data.application_status === true) {

                        applyBtn.innerText = "Applied";
                        applyBtn.disabled = true;
                        applyBtn.style.background = "#bdbdbd";
                        applyBtn.style.cursor = "not-allowed";
                        applyBtn.style.boxShadow = "none";

                    }

                })

                .catch(err => {
                    console.log("Application status check error:", err);
                });

        });


        /* ================= APPLY JOB ================= */

        if (applyBtn) {

            applyBtn.addEventListener("click", function(e) {

                e.preventDefault();

                if (applyBtn.disabled) return;

                const jobId = this.dataset.job;
                const jobType = this.dataset.type;


                /* ================= CHECK PROFILE COMPLETION ================= */

                fetch("/web_api/checkJobseekerprofile.php", {

                        method: "POST",

                        headers: {
                            "Content-Type": "application/json"
                        },

                        body: JSON.stringify({

                            userid: <?= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0 ?>

                        })

                    })

                    .then(res => res.json())

                    .then(profileData => {

                        /* ===== PROFILE NOT COMPLETE ===== */

                        if (profileData.data && profileData.data.profile_complete === false) {

                            let msg = profileData.message + "<br><br>";

                            if (profileData.data.missing_fields && profileData.data.missing_fields.length > 0) {

                                msg += "<b>Please complete the following details:</b><br><ul>";

                                profileData.data.missing_fields.forEach(function(field) {
                                    msg += "<li>" + field + "</li>";
                                });

                                msg += "</ul>";

                            }

                            document.querySelector("#applyPopup .dialog-body").innerHTML = msg;

                            document.getElementById("applyPopup").style.display = "flex";

                            return;

                        }


                        /* ================= APPLY JOB ================= */

                        applyBtn.innerText = "Applying...";
                        applyBtn.disabled = true;
                        applyBtn.style.cursor = "not-allowed";

                        fetch("/web_api/addJobapplication.php", {

                                method: "POST",

                                headers: {
                                    "Content-Type": "application/json"
                                },

                                body: JSON.stringify({

                                    userid: <?= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0 ?>,
                                    job_id: jobId,
                                    job_listing_type: jobType

                                })

                            })

                            .then(res => res.json())

                            .then(data => {

                                if (data.status === true) {

                                    applyBtn.innerText = "Applied";
                                    applyBtn.disabled = true;
                                    applyBtn.style.background = "#bdbdbd";
                                    applyBtn.style.cursor = "not-allowed";
                                    applyBtn.style.boxShadow = "none";

                                    document.querySelector("#applyPopup .dialog-body").innerText =
                                        "Application Submitted Successfully.";

                                    document.getElementById("applyPopup").style.display = "flex";

                                } else if (data.message && data.message.toLowerCase().includes("already")) {

                                    applyBtn.innerText = "Applied";
                                    applyBtn.disabled = true;
                                    applyBtn.style.background = "#bdbdbd";
                                    applyBtn.style.cursor = "not-allowed";
                                    applyBtn.style.boxShadow = "none";

                                    document.querySelector("#applyPopup .dialog-body").innerText =
                                        "You have already applied for this job.";

                                    document.getElementById("applyPopup").style.display = "flex";

                                } else {

                                    document.querySelector("#applyPopup .dialog-body").innerText =
                                        data.message || "Something went wrong.";

                                    document.getElementById("applyPopup").style.display = "flex";

                                    applyBtn.innerText = "Apply Now";
                                    applyBtn.disabled = false;
                                    applyBtn.style.cursor = "pointer";

                                }

                            })

                            .catch(err => {

                                console.log("Apply Error:", err);

                                document.querySelector("#applyPopup .dialog-body").innerText =
                                    "Server error. Please try again.";

                                document.getElementById("applyPopup").style.display = "flex";

                                applyBtn.innerText = "Apply Now";
                                applyBtn.disabled = false;
                                applyBtn.style.cursor = "pointer";

                            });

                    })

                    .catch(err => {

                        console.log("Profile check error:", err);

                        document.querySelector("#applyPopup .dialog-body").innerText =
                            "Unable to verify profile. Please try again.";

                        document.getElementById("applyPopup").style.display = "flex";

                    });

            });

        }


        /* ================= CLOSE POPUP ================= */

        function closeApplyPopup() {

            document.getElementById("applyPopup").style.display = "none";

            /* redirect to My Applications */

            window.location.href = "/my_applications.php";

        }


        /* ================= ACTION LOG ================= */

        function logAction(type) {

            fetch("/web_api/addJobactionlogs.php", {

                    method: "POST",

                    headers: {
                        "Content-Type": "application/json"
                    },

                    body: JSON.stringify({

                        action_type: type,
                        userid: <?= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0 ?>,
                        job_id: <?= (int)$job['id'] ?>,
                        job_listing_type: 2

                    })

                })

                .then(res => res.json())
                .then(data => {
                    console.log("Action Logged:", data);
                })
                .catch(err => {
                    console.log("Action log error:", err);
                });

        }
    </script>
</body>

</html>