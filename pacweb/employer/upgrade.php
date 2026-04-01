<?php
session_start();
$user = $_SESSION['user'] ?? null;
$userid = $user['id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade Plan | Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">
    <style>
        :root {
            /* Pacific iConnect Theme Colors */
            --primary: #483EA8;
            --primary-light: #eceaf9;
            --primary-dark: #322b7a;
            --blue-btn: #2563eb;
            --success-green: #10b981;
            --danger-red: #e53935;
            --text-dark: #1a1a1a;
            --text-muted: #64748b;
            --border-light: #e2e8f0;
            --bg-body: #f8fafc;
            --white: #ffffff;

            /* Premium Plan Colors */
            --premium-bg: #fffbeb;
            --premium-border: #fbbf24;
            --premium-badge-bg: #fcd34d;
            --premium-badge-text: #78350f;
            --premium-btn: #f59e0b;
            --premium-btn-hover: #d97706;
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
            overflow-x: hidden;
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
            font-family: inherit;
        }

        /* --- 1. UNIFIED DESKTOP HEADER --- */
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

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.2;
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 1.05rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Grid Layout */
        .pricing-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            align-items: stretch;
        }

        /* Card Styling */
        .plan-card {
            border-radius: 16px;
            padding: 35px 30px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .plan-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
        }

        /* Card Variations */
        .current-plan {
            background-color: var(--white);
            border: 1px solid var(--border-light);
        }

        .premium-plan {
            background-color: var(--premium-bg);
            border: 2px solid var(--premium-border);
        }

        /* Card Headers */
        .plan-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .plan-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .plan-title.no-margin {
            margin-bottom: 0;
        }

        .blue-dot {
            width: 12px;
            height: 12px;
            background-color: var(--blue-btn);
            border-radius: 50%;
            display: inline-block;
        }

        .red-dot {
            width: 12px;
            height: 12px;
            background-color: var(--danger-red);
            border-radius: 50%;
            display: inline-block;
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

        /* Plan Details (Bronze Info) */
        .plan-details {
            margin-bottom: 25px;
        }

        .plan-details p {
            font-size: 1.05rem;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .plan-details b {
            font-weight: 700;
        }

        /* Lists */
        .feature-list {
            list-style: none;
            margin-bottom: 30px;
            flex-grow: 1;
            /* Pushes button to bottom */
        }

        .feature-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 14px;
            font-size: 1rem;
            color: var(--text-dark);
            line-height: 1.4;
        }

        /* Blue checkmarks for standard plan */
        .blue-checks li i {
            color: var(--blue-btn);
            margin-top: 4px;
            font-size: 0.95rem;
        }

        /* Emojis for premium plan */
        .emoji-list li .emoji {
            font-size: 1.15rem;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* Button - UPDATED TO WHITE TEXT */
        .btn-upgrade {
            width: 100%;
            background-color: var(--premium-btn);
            color: var(--white);
            /* Changed to white */
            padding: 16px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 800;
            transition: background 0.2s;
        }

        .btn-upgrade:hover {
            background-color: var(--premium-btn-hover);
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

        /* Responsive Settings */
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
                padding: 25px 20px;
            }
        }
    </style>
</head>

<body>

    <?php include "includes/header.php"; ?>
    <?php include "includes/preloader.php"; ?>


    <div class="mobile-header">
        <i class="fas fa-arrow-left mobile-back"></i>
        <span class="mobile-header-title">Upgrade</span>
        <i class="fas fa-user-circle mobile-user"></i>
    </div>

    <div class="container">

        <div class="page-header">
            <h1 class="page-title">Upgrade Plan Options for Employers</h1>
            <p class="page-subtitle">Choose the plan that matches your hiring goals</p>
        </div>

        <div class="pricing-grid">

            <div class="plan-card current-plan">
                <h2 class="plan-title"><span class="red-dot"></span> Your Current Plan</h2>

                <div class="plan-details">
                    <p><b>Plan:</b> Standard Plan</p>
                    <p><b>Cost:</b> ₹0 (Free)</p>
                    <p><b>Validity:</b> Lifetime</p>
                </div>

                <ul class="feature-list blue-checks">
                    <li><i class="fas fa-check"></i> Free Job Posting</li>
                    <li><i class="fas fa-check"></i> General Listing</li>
                    <li><i class="fas fa-check"></i> Reach Across All Job Categories</li>
                    <li><i class="fas fa-check"></i> Simple Job Vacancy Format</li>
                    <li><i class="fas fa-check"></i> Max 2 Active Job</li>
                    <li><i class="fas fa-check"></i> Ideal for Entry-Level and Non-Urgent Hiring</li>
                </ul>
            </div>

            <div class="plan-card premium-plan">
                <div class="plan-header-flex">
                    <h2 class="plan-title no-margin">Paid Subscription Benefits</h2>
                    <span class="premium-badge"><i class="fas fa-star"></i> PREMIUM</span>
                </div>

                <ul class="feature-list emoji-list">
                    <li><span class="emoji">🔝</span> <span><b>Premium Job Placement</b> – Top of job search results</span></li>
                    <li><span class="emoji">🏢</span> <span><b>Highlighted Company Logo</b> - Bronze, Silver or Gold</span></li>
                    <li><span class="emoji">✅</span> <span><b>Verified Employer Badge</b> – Shows your credibility</span></li>
                    <li><span class="emoji">🆔</span> <span><b>KYC Verified Tag</b> – Builds trust with applicants</span></li>
                    <li><span class="emoji">📍</span> <span><b>Google Maps Interview Location</b> – Makes your office easy to find</span></li>
                    <li><span class="emoji">📝</span> <span><b>Advanced Job Format</b> – Structured and professional</span></li>
                    <li><span class="emoji">✍️</span> <span><b>Custom Job Descriptions</b> – Tailored to attract ideal candidates</span></li>
                    <li><span class="emoji">🚀</span> <span><b>Ideal for Bulk, Mid-Senior & Urgent Hiring</b></span></li>
                </ul>

                <a href="subscription_plans.php">
                    <button class="btn-upgrade">Upgrade to Premium</button>
                </a>
            </div>

        </div>

    </div>

    <div class="bottom-nav">
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-home"></i></div>
            Home
        </a>
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-plus-square"></i></div>
            Post Jobs
        </a>
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-file-alt"></i></div>
            Applications
        </a>
        <a href="#" class="nav-icon active">
            <div class="icon-wrap"><i class="fas fa-user"></i></div>
            Profile
        </a>
    </div>
    <?php include "includes/bottom-bar.php"; ?>

    <script>
        window.onload = () => document.getElementById("global-preloader")?.remove();
    </script>
</body>

</html>