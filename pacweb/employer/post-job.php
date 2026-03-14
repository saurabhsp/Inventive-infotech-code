<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$userid = 741;


// $user = $_SESSION['user'];

// $userid = $user['id'];


/* ================= USER SUBSCRIPTION CHECK ================= */

// $subscription_api = "https://pacweb.inv11.in/webservices/checkUsersubscription.php";
$subscription_api = "https://pacificconnect2.0.inv51.in/webservices/checkUsersubscription.php";

$subscription_post = [
    "user_id" => $userid
];

$sub_ch = curl_init($subscription_api);

curl_setopt($sub_ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($sub_ch, CURLOPT_POST, true);
curl_setopt($sub_ch, CURLOPT_POSTFIELDS, json_encode($subscription_post));

curl_setopt($sub_ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Content-Length: " . strlen(json_encode($subscription_post))
]);

curl_setopt($sub_ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($sub_ch, CURLOPT_SSL_VERIFYHOST, false);

$subscription_response = curl_exec($sub_ch);

if ($subscription_response === false) {
    echo "Curl Error: " . curl_error($sub_ch);
    exit;
    }
    // print_r($subscription_response);
    // exit;

curl_close($sub_ch);

$subscription_data = json_decode($subscription_response, true);

if (!$subscription_data) {
    echo "Subscription JSON Decode Failed";
    exit;
}

$plan_name = $subscription_data['plan_name'] ?? '';
$subscription_status = $subscription_data['subscription_status'] ?? '';

$premium_remaining = $subscription_data['walkin_remaining'] ?? 0;
$premium_posted = $subscription_data['walkin_posted'] ?? 0;
$premium_limit = $subscription_data['walkin_limit'] ?? 0;
$premium_upgrade_dialog = $subscription_data['walkin_upgrade_dialog'] ;
$premium_limit_dialog = $subscription_data['walkin_limit_dialog'] ;

$standard_remaining = $subscription_data['vacancy_remaining'] ?? 0;
$standard_posted = $subscription_data['vacancy_posted'] ?? 0;
$standard_limit = $subscription_data['vacancy_limit'] ?? 0;
$standard_upgrade_dialog = $subscription_data['vacancy_upgrade_dialog'] ; 
$standard_limit_dialog = $subscription_data['vacancy_limit_dialog'] ; 

$jobseeker_upgrade_cv_dialog = $subscription_data['jobseeker_upgrade_cv_dialog'] ; 


?>







<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Jobs | Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            /* Pacific iConnect Theme Colors */
            --primary: #483EA8;
            --primary-light: #eceaf9;
            --primary-dark: #322b7a;
            --blue-btn: #2563eb;
            --blue-hover: #1d4ed8;
            --success-green: #10b981;
            --text-dark: #1a1a1a;
            --text-muted: #64748b;
            --border-light: #e5e7eb;
            --bg-body: #f4f6f9;
            --white: #ffffff;

            /* Specific Colors for Plans */
            --premium-bg: #fffdeb;
            --premium-border: #fcd34d;
            --premium-badge-bg: #fbbf24;
            --premium-badge-text: #78350f;
            --check-color: #2563eb;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-dark);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a {
            text-decoration: none;
            transition: 0.3s;
            color: inherit;
        }

        button {
            cursor: pointer;
            outline: none;
            border: none;
        }

        /* --- 1. UNIFIED HEADER --- */
        header {
            background: var(--white);
            height: 70px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
        }

        .header-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand-group {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            font-weight: 800;
            font-size: 1.3rem;
        }

        .desktop-nav {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-link {
            font-weight: 600;
            color: #555;
            font-size: 1rem;
            padding: 5px 10px;
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--primary);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nav-action-icon {
            position: relative;
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            transition: 0.2s;
        }

        .noti-badge {
            position: absolute;
            top: -5px;
            right: -8px;
            background: var(--danger-red);
            color: white;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 10px;
            border: 2px solid white;
            line-height: 1.1;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 15px 5px 5px;
            background: var(--primary-light);
            border-radius: 30px;
            cursor: pointer;
            transition: 0.2s;
        }

        .user-profile:hover {
            background: #e0dcf5;
        }

        .user-name {
            font-weight: 700;
            color: var(--primary);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        /* --- 2. MAIN CONTENT AREA --- */
        .container {
            max-width: 1000px;
            margin: 40px auto 80px auto;
            padding: 0 20px;
            flex: 1;
            width: 100%;
        }

        /* NEW CENTERED PAGE TITLE */
        .page-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.2;
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 1rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Pricing Grid */
        .pricing-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            align-items: stretch;
        }

        /* Base Card Styling */
        .plan-card {
            border-radius: 16px;
            padding: 30px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .plan-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
        }

        /* Premium Plan specific */
        .premium-plan {
            background-color: var(--premium-bg);
            border: 2px solid var(--premium-border);
        }

        /* Standard Plan specific */
        .standard-plan {
            background-color: var(--white);
            border: 1px solid var(--border-light);
        }

        /* Card Header Elements */
        .plan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .plan-header h2 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .premium-badge {
            background: var(--premium-badge-bg);
            color: var(--premium-badge-text);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .plan-count {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .plan-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            margin-bottom: 20px;
            line-height: 1.5;
        }

        /* Feature List */
        .feature-list {
            list-style: none;
            margin-bottom: 30px;
            flex-grow: 1;
            /* Pushes buttons to the bottom evenly */
        }

        .feature-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 0.95rem;
            color: var(--text-dark);
            font-weight: 500;
            line-height: 1.4;
        }

        .feature-list li i {
            color: var(--check-color);
            margin-top: 3px;
            font-size: 0.9rem;
        }

        /* Buttons */
        .btn-primary {
            width: 100%;
            background-color: var(--blue-btn);
            color: var(--white);
            padding: 14px;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 15px;
            transition: background 0.2s;
        }

        .btn-primary:hover {
            background-color: var(--blue-hover);
        }

        .btn-light {
            width: 100%;
            background-color: #eff6ff;
            /* light blue */
            color: var(--blue-btn);
            padding: 14px;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 700;
            transition: background 0.2s;
        }

        .btn-light:hover {
            background-color: #dbeafe;
        }


        /* --- 3. MOBILE HEADER & NAV --- */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: white;
            height: 70px;
            border-top: 1px solid #eee;
            justify-content: space-around;
            align-items: center;
            z-index: 1000;
            padding-bottom: 5px;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.03);
        }

        .nav-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #888;
            font-size: 0.75rem;
            gap: 5px;
            font-weight: 600;
            text-decoration: none;
        }

        .nav-icon i {
            font-size: 1.3rem;
        }

        .nav-icon.active {
            color: var(--primary);
        }

        .nav-icon.active .icon-wrap {
            background: var(--primary-light);
            padding: 5px 15px;
            border-radius: 20px;
        }

        .mobile-header {
            display: none;
            align-items: center;
            justify-content: center;
            height: 60px;
            background: white;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid #eee;
        }

        .mobile-header-title {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .mobile-back {
            position: absolute;
            left: 20px;
            font-size: 1.2rem;
            color: #333;
            cursor: pointer;
        }

        .mobile-user {
            position: absolute;
            right: 20px;
            font-size: 1.5rem;
            color: var(--primary);
            cursor: pointer;
        }

        /* Responsive */
        @media (max-width: 900px) {
            header {
                display: none;
            }

            .mobile-header {
                display: flex;
            }

            .bottom-nav {
                display: flex;
            }

            .container {
                margin: 20px auto;
                padding: 0 15px;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .page-subtitle {
                font-size: 0.95rem;
            }

            .pricing-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .plan-card {
                padding: 24px 20px;
            }
        }
    </style>
</head>

<body>

    <header>
        <div class="header-container">
            <div class="brand-group">
                <div class="brand">
                    <i class="fas fa-user-tie"></i> <span>PACIFIC iCONNECT</span>
                </div>
            </div>

            <nav class="desktop-nav">
                <a href="#" class="nav-link">Find Jobs</a>
                <a href="#" class="nav-link">Companies</a>
                <a href="#" class="nav-link">For Employers</a>
            </nav>

            <div class="header-actions">
                <div class="nav-action-icon" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="noti-badge">3</span>
                </div>
                <div class="user-profile">
                    <div class="user-avatar">A</div>
                    <span class="user-name">Ashwin Jawale <i class="fas fa-chevron-down" style="font-size:0.75rem;"></i></span>
                </div>
            </div>
        </div>
    </header>

    <div class="mobile-header">
        <i class="fas fa-arrow-left mobile-back"></i>
        <span class="mobile-header-title">Post Jobs</span>
        <i class="fas fa-user-circle mobile-user"></i>
    </div>

    <div class="container">

        <div class="page-header">
            <h1 class="page-title">Begin your Hiring</h1>
            <p class="page-subtitle">Smarter Tools, Better Candidates</p>
        </div>

        <div class="pricing-grid">

            <div class="plan-card premium-plan">
                <div class="plan-header">
                    <h2>🚀 Premium Jobs</h2>
                    <span class="premium-badge"><i class="fas fa-star"></i> PREMIUM</span>
                </div>

                <div class="plan-count"><?= $premium_posted . '/' . $premium_limit ?> (<?= $premium_remaining ?> Left)</div>
                <p class="plan-desc">Faster Hiring with Premium Visibility and Urgent Outreach</p>

                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> Premium Listing</li>
                    <li><i class="fas fa-check"></i> Highlighted Company Logo</li>
                    <li><i class="fas fa-check"></i> Membership Badge Display</li>
                    <li><i class="fas fa-check"></i> KYC Verified Tag</li>
                    <li><i class="fas fa-check"></i> Google Maps Interview Location</li>
                    <li><i class="fas fa-check"></i> Detailed Job Vacancy Format</li>
                    <li><i class="fas fa-check"></i> Custom Job Description</li>
                    <li><i class="fas fa-check"></i> Perfect for All Levels, Bulk and Urgent Hiring</li>
                </ul>

<button class="btn-primary" onclick="handlePremiumPost()">Post Premium Vacancy</button>
                <button class="btn-light">View Premium Listings</button>
            </div>

            <div class="plan-card standard-plan">
                <div class="plan-header">
                    <h2>📝 Standard Jobs</h2>
                </div>

                <div class="plan-count"><?= $standard_posted . '/' . $standard_limit ?> (<?= $standard_remaining ?> Left)</div>
                <p class="plan-desc">Post Jobs for Free with Wide Visibility for Regular Hiring</p>

                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> Free Job Posting</li>
                    <li><i class="fas fa-check"></i> General Listing</li>
                    <li><i class="fas fa-check"></i> Reach Across All Job Categories</li>
                    <li><i class="fas fa-check"></i> Simple Job Vacancy Format</li>
                    <li><i class="fas fa-check"></i> Max 2 Active Job</li>
                </ul>

                <button class="btn-primary" onclick="handleStandardPost()">Post Standard Job</button>
                <button class="btn-light">View Standard Listings</button>
            </div>

        </div>

    </div>

    <div class="bottom-nav">
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-home"></i></div>
            Home
        </a>
        <a href="#" class="nav-icon active">
            <div class="icon-wrap"><i class="fas fa-plus-square"></i></div>
            Post Jobs
        </a>
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-file-alt"></i></div>
            Applications
        </a>
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-user"></i></div>
            Profile
        </a>
    </div>

</body>
<script>

const premiumLimitDialog = <?= $premium_limit_dialog ? 'true' : 'false' ?>;
const premiumUpgradeDialog = <?= $premium_upgrade_dialog ? 'true' : 'false' ?>;

const standardLimitDialog = <?= $standard_limit_dialog ? 'true' : 'false' ?>;
const standardUpgradeDialog = <?= $standard_upgrade_dialog ? 'true' : 'false' ?>;


/* PREMIUM JOB POST */
function handlePremiumPost(){
    if(!premiumLimitDialog && !premiumUpgradeDialog){
        window.location.href = "add-premium-job.php";
    }else{
        window.location.href = "upgrade.php";
    }
}


/* STANDARD JOB POST */
function handleStandardPost(){
    if(!standardLimitDialog && !standardUpgradeDialog){
        window.location.href = "add-standard-job.php";
    }else{
        window.location.href = "upgrade.php";
    }
}

</script>
</html>