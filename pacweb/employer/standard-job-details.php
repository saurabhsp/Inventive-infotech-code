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

$logo = $job['company_logo'] ?? '';

?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Standard Jobs | Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        :root {
            /* Theme Colors */
            --primary: #483EA8;
            --bg-body: #f4f6f9;
            --white: #ffffff;
            --text-dark: #1a1a1a;
            --text-grey: #666666;
            --green: #25D366;

            /* Badge Colors */
            --gold: #FFC107;
            --silver: #9E9E9E;
            --bronze: #A16B49;
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
            font-size: 14.5px;
            line-height: 1.5;
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
            max-width: 1200px;
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

        /* --- PAGE LAYOUT --- */
        .page-wrapper {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 25px;
            padding: 30px 0;
            align-items: start;
        }

        .page-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
            padding-left: 12px;
        }

        /* --- SIDEBAR --- */
        .filter-sidebar {
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #eee;
            position: sticky;
            top: 90px;
            max-height: 80vh;
            overflow-y: auto;
        }

        /* Slim Scrollbar */
        .filter-sidebar::-webkit-scrollbar {
            width: 5px;
        }

        .filter-sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .filter-sidebar::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 4px;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .filter-header h3 {
            font-size: 1rem;
            font-weight: 700;
        }

        .clear-btn {
            color: var(--primary);
            font-size: 0.85rem;
            font-weight: 600;
            background: none;
            border: none;
        }

        .filter-group {
            margin-bottom: 20px;
            border-bottom: 1px solid #f5f5f5;
            padding-bottom: 15px;
        }

        .filter-group:last-child {
            border: none;
            margin: 0;
            padding: 0;
        }

        .filter-label {
            font-weight: 700;
            margin-bottom: 10px;
            display: block;
            font-size: 0.9rem;
        }

        .filter-search {
            position: relative;
            margin-bottom: 10px;
        }

        .filter-search input {
            width: 100%;
            padding: 8px 10px 8px 30px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.85rem;
            outline: none;
            background: #fdfdfd;
        }

        .filter-search i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 0.8rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: #555;
            cursor: pointer;
        }

        .checkbox-item input {
            accent-color: var(--primary);
            width: 15px;
            height: 15px;
        }

        .hidden-options {
            display: none;
        }

        .show-more-btn {
            color: var(--primary);
            font-size: 0.85rem;
            font-weight: 600;
            background: none;
            border: none;
            padding: 0;
            margin-top: 5px;
            cursor: pointer;
        }

        /* --- JOB GRID (2 Columns) --- */
        .job-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        /* --- CARD DESIGN --- */
        .job-card {
            background: var(--white);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
            border: 1px solid #f0f0f0;
            transition: 0.2s;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .job-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border-color: rgba(72, 62, 168, 0.15);
        }

        /* Header Grid */
        .card-header-grid {
            display: grid;
            grid-template-columns: 80px 1fr;
            gap: 10px;
            margin-bottom: 25px;
        }

        /* Logo Section */
        .logo-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            position: relative;
        }

        .logo-ring-container {
            width: 60px;
            height: 60px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
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

        .ring-gold {
            background: var(--gold);
        }

        .ring-silver {
            background: var(--silver);
        }

        .ring-bronze {
            background: var(--bronze);
        }

        .company-img {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            /* 🔥 better than contain */
            border: 2px solid white;
            background: #fff;
        }



        /* Job Details */
        .job-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .job-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 2px;
            line-height: 1.2;
        }

        .job-company {
            font-size: 0.9rem;
            color: #444;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .job-meta {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 2px;
        }

        .job-meta i {
            font-size: 0.85rem;
            color: #888;
            width: 14px;
        }

        .job-salary {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-dark);
            margin-top: 4px;
        }

        /* Tags */
        .tags-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            margin-top: auto;
        }

        .tag-pill {
            font-size: 0.75rem;
            color: #555;
            background: #fff;
            border: 1px solid #ddd;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 500;
        }

        /* Footer */
        .card-footer {
            display: flex;
            align-items: center;
            margin-top: 10px;
            padding-top: 12px;
            border-top: 1px solid #f7f7f7;
        }

        .btn-arrow {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid #3b82f6;
            color: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            transition: 0.2s;
            background: white;
            margin-left: auto;
            /* ✅ RIGHT SIDE */
        }

        .btn-arrow:hover {
            background: #3b82f6;
            color: white;
        }

        /* Error/No Results */
        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: var(--white);
            border-radius: 12px;
            border: 1px solid #f0f0f0;
        }

        .no-results i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }

        .no-results h3 {
            font-size: 1.3rem;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .no-results p {
            color: var(--text-grey);
            font-size: 0.95rem;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            padding: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            color: var(--text-dark);
            font-weight: 600;
            transition: 0.2s;
        }

        .pagination a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination .current {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination .disabled {
            opacity: 0.4;
            pointer-events: none;
        }

        /* Mobile FAB */
        .mobile-filter-fab {
            display: none;
            position: fixed;
            bottom: 30px;
            right: 20px;
            background: var(--primary);
            color: white;
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: 600;
            box-shadow: 0 5px 20px rgba(72, 62, 168, 0.4);
            align-items: center;
            gap: 8px;
            z-index: 2000;
            border: none;
        }

        @media (max-width: 900px) {
            .nav-menu {
                display: none;
            }

            .page-wrapper {
                grid-template-columns: 1fr;
                padding: 20px 0;
                gap: 0;
            }

            .job-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .mobile-filter-fab {
                display: flex;
            }

            .job-card {
                padding: 15px;
            }

            /* 🔥 Sidebar as overlay */
            .filter-sidebar {
                position: fixed;
                top: 0;
                left: -100%;
                width: 80%;
                height: 100%;
                background: #fff;
                z-index: 9999;
                transition: 0.3s;
                overflow-y: auto;
            }

            /* ✅ OPEN */
            #filterToggle:checked~.filter-sidebar {
                left: 0;
            }

        }

        @media (max-width: 900px) {
            #filterToggle:checked~.container::before {
                content: "";
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.4);
                z-index: 9998;
            }

            @media (max-width: 900px) {

                footer,
                .footer,
                .footer-grid {
                    display: none !important;
                }
            }

        }

        
    </style>
</head>

<div class="container main-content">

    <div class="job-header-card">

        <div class="logo-box-standard">
            <img src="<?= $logo ?>" class="company-logo">
        </div>

        <div class="job-title-info">
            <h1><?= $job['job_position'] ?? '' ?></h1>
            <span class="company-name"><?= $job['company_name'] ?? '' ?></span>
        </div>

    </div>


    <div class="job-grid-layout">

        <!-- LEFT -->

        <main>

            <div class="content-card">

                <h3 class="section-title">Job Requirements</h3>

                <div class="req-box">
                    <ul>

                        <li><b>Gender:</b> <?= $job['gender'] ?? '' ?></li>

                        <li><b>Education:</b> <?= $job['qualification'] ?? '' ?></li>

                        <li><b>Experience:</b>
                            <?= $job['experience_from'] ?? '' ?> -
                            <?= $job['experience_to'] ?? '' ?>
                        </li>

                    </ul>
                </div>


                <h3 class="section-title">Contact Details</h3>

                <div>

                    <b><?= $job['contact_person'] ?? '' ?></b>

                    <p><?= $job['interview_address'] ?? '' ?></p>

                    <div class="contact-actions">

                        <a href="tel:<?= $job['mobile_no'] ?? '' ?>" class="btn-action-outline">
                            <i class="fas fa-phone-alt"></i> Call HR
                        </a>

                        <a target="_blank"
                            href="https://wa.me/91<?= preg_replace('/\D/', '', $job['mobile_no'] ?? '') ?>"
                            class="btn-action-outline">

                            <i class="fas fa-comment-dots"></i> Chat

                        </a>

                        <a target="_blank"
                            href="https://www.google.com/maps?q=<?= urlencode($job['interview_address'] ?? '') ?>"
                            class="btn-action-outline">

                            <i class="fas fa-map-marker-alt"></i> View Map

                        </a>

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

            </div>

        </main>



        <!-- RIGHT -->

        <aside class="job-sidebar">

            <div class="content-card">

                <div class="overview-grid">

                    <div class="stat-box">

                        <i class="fas fa-map-marker-alt stat-icon"></i>

                        <div>

                            <span class="stat-label"><b>Location:</b></span>

                            <span class="stat-text">
                                <?= $job['city'] ?? '' ?>, <?= $job['locality'] ?? '' ?>
                            </span>

                        </div>

                    </div>


                    <div class="stat-box">

                        <i class="fas fa-rupee-sign stat-icon"></i>

                        <div>

                            <span class="stat-label">Salary:</span>

                            <span class="stat-text">

                                ₹<?= $job['salary_from'] ?? '' ?> –
                                ₹<?= $job['salary_to'] ?? '' ?>

                            </span>

                        </div>

                    </div>


                    <div class="stat-box">

                        <i class="fas fa-briefcase stat-icon"></i>

                        <div>

                            <span class="stat-label">Experience:</span>

                            <span class="stat-text">

                                <?= $job['experience_from'] ?? '' ?> –
                                <?= $job['experience_to'] ?? '' ?>

                            </span>

                        </div>

                    </div>

                </div>


                <button class="btn-apply-main">

                    Apply Now

                </button>


                <div style="margin-top:10px;font-size:12px;color:#888;text-align:center;">

                    100% Safe & Verified Job

                </div>

            </div>

        </aside>

    </div>

</div>