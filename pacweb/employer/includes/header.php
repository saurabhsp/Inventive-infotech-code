<?php
require_once "../web_api/includes/initialize.php";

$user = $_SESSION['user'] ?? null;
$userid = $user['id'] ?? 0;

$notification_count = $_SESSION['notification_count'] ?? 0;
$city_name = '';

if ($userid > 0) {
    $stmt = $con->prepare("SELECT city_id FROM jos_app_users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $city_name = $row['city_id'] ?? '';
    }
}
$plan_name = $user['plan_name'] ?? '';
$valid_to = $user['valid_to'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruiter Dashboard | Pacific iConnect</title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            /* Theme Colors (Pacific iConnect) */
            --primary: #483EA8;
            --primary-light: #eceaf9;
            --primary-dark: #322b7a;
            --bg-body: #f4f6f9;
            --white: #ffffff;
            --text-dark: #1a1a1a;
            --text-grey: #555555;
            --blue-btn: #2563eb;
            --border-light: #e5e7eb;
            --danger-red: #e53935;

            /* Badges / Accents */
            --bronze-grad: conic-gradient(#cd7f32, #f3c299, #cd7f32);
            --silver-grad: conic-gradient(#C0C0C0, #ffffff, #C0C0C0);
            --gold-grad: conic-gradient(#FFD700, #fff4cc, #FFD700);

            --bronze-bg: #cd7f32;
            --silver-bg: #9e9e9e;
            --gold-bg: #DAA520;
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
        }

        /* --- 1. HEADER --- */
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

        .location-pin {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--primary);
            font-weight: 700;
            font-size: 1.1rem;
        }

        .location-pin i {
            color: var(--danger-red);
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
            gap: 12px;
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

        .profile-dropdown-wrap {
            position: relative;
            padding-bottom: 10px;
            margin-bottom: -10px;
        }

        @media (max-width: 900px) {

            .desktop-nav,
            .brand span {
                display: none;
            }

            .user-profile {
                display: flex;
                padding: 5px;
                background: transparent;
            }

            .user-name {
                display: none;
                /* hide text, keep icon only */
            }
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
            width: 36px;
            height: 36px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            min-width: 200px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid #eee;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 1000;
            padding: 10px 0;
            z-index: 9999;

        }

        .profile-dropdown-wrap.active .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            color: #555;
            font-weight: 600;
            font-size: 0.95rem;
            transition: 0.2s;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
            color: var(--primary);
        }

        /* --- 2. GLOBAL LAYOUT CONTAINERS --- */
        /* Strict alignment to the 1200px menu bar */
        .boxed-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
        }

        .welcome-text {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 25px auto 15px;
            text-align: left;
        }

        /* --- 3. SLIDERS (Top Promo & Jobs) --- */
        .slider-scroll,
        .jobs-slider-container {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            gap: 25px;
            scrollbar-width: none;
            cursor: grab;
            padding: 10px 5px 25px 5px;
            /* Clean padding within 1200px box */
        }

        .slider-scroll::-webkit-scrollbar,
        .jobs-slider-container::-webkit-scrollbar {
            display: none;
        }

        .slider-scroll:active,
        .jobs-slider-container:active {
            cursor: grabbing;
        }

        /* Desktop Arrows relative to the 1200px container */
        .scroll-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: white;
            border: 1px solid var(--border-light);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 100;
            color: var(--primary);
            font-size: 1.2rem;
            transition: 0.2s;
            margin-top: -10px;
            /* Offset for padding */
        }

        .scroll-arrow:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .left-arrow {
            left: 0px;
        }

        .right-arrow {
            right: 0px;
        }

        @media (max-width: 1000px) {
            .scroll-arrow {
                display: none;
            }
        }

        /* --- 4. TOP PROMO CARDS --- */
        .promo-card {
            flex: 0 0 350px !important;
            min-width: 350px;
            max-width: 350px;
            height: 210px;
            border-radius: 20px;
            padding: 25px;
            color: white;
            scroll-snap-align: start;
            position: relative;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.3s ease;
            user-select: none;
        }

        .c1 {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
        }

        .c2 {
            background: linear-gradient(135deg, #232526, #414345);
        }

        .c3 {
            background: linear-gradient(135deg, #cc2b5e, #753a88);
        }

        .c4 {
            background: linear-gradient(135deg, #00b09b, #96c93d);
        }

        .chip {
            width: 45px;
            height: 35px;
            background: linear-gradient(135deg, #f1c40f, #d4af37);
            border-radius: 6px;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .card-no {
            font-size: 1.4rem;
            letter-spacing: 2px;
            font-family: 'Courier New', monospace;
            margin: 15px 0;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.4);
        }

        .label {
            font-size: 0.7rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .amount {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .dots {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: -5px;
            margin-bottom: 25px;
        }

        .dot {
            width: 10px;
            height: 10px;
            background: #cbd5e0;
            border-radius: 50%;
            transition: 0.3s;
        }

        .dot.active {
            background: var(--primary);
            width: 25px;
            border-radius: 5px;
        }

        /* --- 5. JOB SECTIONS HEADERS --- */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px auto 10px;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
        }

        .btn-view-all {
            background: var(--blue-btn);
            color: white;
            border: none;
            padding: 6px 15px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-view-all:hover {
            background: #1d4ed8;
        }

        /* --- 6. RECRUITER JOB CARD --- */
        .r-job-card {
            flex: 0 0 360px !important;
            min-width: 360px;
            max-width: 360px;
            scroll-snap-align: start;
            background: var(--white);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border-light);
            position: relative;
            user-select: none;
            overflow: visible;
            /* Prevents dropdown from hiding inside card */
        }

        /* 3 DOTS MENU (Dropdown - Removed Delete) */
        .menu-dot-container {
            position: absolute;
            top: 15px;
            right: 10px;
            z-index: 20;
        }

        .menu-dot-icon {
            color: #888;
            cursor: pointer;
            font-size: 1.1rem;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: 0.2s;
        }

        .menu-dot-icon:hover {
            background: #f0f0f0;
            color: var(--primary);
        }

        .card-menu-dropdown {
            position: absolute;
            top: 35px;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            border: 1px solid #ddd;
            min-width: 140px;
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 50;
        }

        .card-menu-dropdown.show {
            display: flex;
        }

        .card-menu-dropdown a {
            padding: 12px 15px;
            color: #444;
            font-size: 0.9rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: 0.2s;
        }

        .card-menu-dropdown a:hover {
            background: #f4f6f9;
            color: var(--primary);
        }

        .card-head {
            display: flex;
            gap: 15px;
            align-items: flex-start;
            margin-bottom: 15px;
            position: relative;
        }

        /* LOGO RING ANIMATION */
        @keyframes spin {
            100% {
                transform: rotate(360deg);
            }
        }

        .logo-box {
            position: relative;
            width: 75px;
            height: 75px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .shiny-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            animation: spin 3s linear infinite;
        }

        .shiny-ring.bronze {
            background: var(--bronze-grad);
        }

        .shiny-ring.silver {
            background: var(--silver-grad);
        }

        .shiny-ring.gold {
            background: var(--gold-grad);
        }

        .company-logo {
            width: 63px;
            height: 63px;
            border-radius: 50%;
            object-fit: cover;
            background: #fff;
            z-index: 2;
            border: 3px solid #fff;
            position: relative;
        }

        .member-badge {
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.55rem;
            font-weight: 800;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            z-index: 5;
            white-space: nowrap;
            border: 1px solid white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .member-badge.bronze {
            background: var(--bronze-bg);
        }

        .member-badge.silver {
            background: var(--silver-bg);
            color: white;
        }

        .member-badge.gold {
            background: var(--gold-bg);
        }

        .job-info {
            flex: 1;
            padding-top: 5px;
        }

        .job-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.2;
            margin-bottom: 5px;
            padding-right: 20px;
        }

        .job-meta {
            font-size: 0.85rem;
            color: var(--text-grey);
            margin-bottom: 3px;
        }

        .job-status {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .emp-stats-row {
            display: flex;
            justify-content: space-between;
            text-align: center;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
            padding: 12px 2px;
            margin-bottom: 15px;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .emp-stat-box {
            flex: 1;
            border-right: 1px solid #eee;
        }

        .emp-stat-box:last-child {
            border-right: none;
        }

        .emp-stat-num {
            display: inline-block;
            margin-left: 3px;
        }

        .apps-count {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            text-align: center;
            padding: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-dark);
            margin-bottom: 15px;
        }

        .apps-num {
            font-size: 1.1rem;
            display: block;
            margin-top: 3px;
        }

        .card-actions {
            display: flex;
            gap: 10px;
        }

        .btn-card {
            flex: 1;
            background: var(--blue-btn);
            color: white;
            border: none;
            padding: 10px 5px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            transition: 0.2s;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
            text-align: center;
        }

        .btn-card:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }

        /* --- 7. CHANGE STATUS MODAL --- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 30px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            transform: translateY(20px);
            transition: transform 0.3s;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            font-size: 1.2rem;
            font-weight: 800;
            margin: 0;
        }

        .close-modal {
            cursor: pointer;
            font-size: 1.2rem;
            color: #888;
            transition: 0.2s;
        }

        .close-modal:hover {
            color: var(--danger-red);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #555;
        }

        .status-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            outline: none;
            font-weight: 600;
            cursor: pointer;
        }

        .status-select:focus {
            border-color: var(--primary);
        }

        .btn-modal-save {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-modal-save:hover {
            background: var(--primary-dark);
        }

        /* --- 8. MOBILE RESPONSIVE TWEAKS --- */
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

        @media (max-width: 900px) {

            .desktop-nav,
            .brand span {
                display: none;
            }

            .header-container {
                padding: 0 15px;
            }



            .nav-action-icon {
                font-size: 1.6rem;
            }

            .mob-profile {
                font-size: 1.8rem;
                color: var(--primary);
                cursor: pointer;
            }

            .boxed-container {
                padding: 0 15px;
            }

            /* Break padding on mobile so cards slide to screen edge smoothly */
            .slider-scroll,
            .jobs-slider-container {
                padding: 10px 15px 25px 15px;
                margin: 0 -15px;
                /* Offset the container padding */
            }

            .promo-card {
                flex: 0 0 85vw !important;
                min-width: 85vw;
                max-width: 85vw;
                height: 190px;
            }

            .r-job-card {
                flex: 0 0 85vw !important;
                min-width: 85vw;
                max-width: 85vw;
            }

            .bottom-nav {
                display: flex;
            }

            body {
                padding-bottom: 80px;
            }

            .btn-card {
                font-size: 0.75rem;
                padding: 12px 5px;
            }
        }

        @media (min-width: 901px) {
            .mob-profile {
                display: none;
            }
        }

        /* --- LOCATION MODAL (Same design as main dashboard) --- */
        /* Clickable elements show finger pointer */

        .location-pin,
        .menu-dot-icon,
        .scroll-arrow,
        .btn-view-all,
        .btn-card,
        .btn-post-job-empty,
        .nav-action-icon,
        .dropdown-item,
        .close-modal,
        .btn-modal-save,
        .btn-modal-cancel {
            cursor: pointer;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;

            background: rgba(0, 0, 0, 0.6);

            z-index: 2000;

            display: none;
            align-items: flex-end;
            justify-content: center;

            opacity: 0;
            transition: opacity 0.3s;
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background: white;

            width: 100%;
            max-width: 500px;

            padding: 30px 25px;

            border-radius: 24px 24px 0 0;

            transform: translateY(100%);
            transition: transform 0.3s;

            box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.1);
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;

            margin-bottom: 25px;
        }

        .modal-header h3 {
            font-size: 1.2rem;
            font-weight: 800;
        }

        /* FORM FIELDS */

        .input-group {
            margin-bottom: 20px;
        }

        .input-label {
            display: block;

            font-size: 0.85rem;
            color: #666;

            margin-bottom: 5px;
            font-weight: 600;
        }

        .modal-input {
            width: 100%;

            border: none;
            border-bottom: 1px solid #ccc;

            padding: 10px 0;

            font-size: 1rem;

            outline: none;

            transition: 0.2s;

            color: var(--text-dark);
            font-weight: 500;
        }

        .modal-input:focus {
            border-bottom-color: var(--primary);
        }

        /* BUTTONS */

        .modal-btn-row {
            display: flex;
            justify-content: flex-end;
            gap: 15px;

            margin-top: 10px;
        }

        .btn-modal-cancel {
            background: white;
            color: #333;

            border: 1px solid #ccc;

            padding: 12px 25px;

            border-radius: 30px;

            font-weight: 600;

            transition: 0.2s;
        }

        .btn-modal-cancel:hover {
            background: #f4f4f4;
        }

        .btn-modal-save {
            background: var(--blue-btn);

            color: white;

            border: none;

            padding: 12px 30px;

            border-radius: 30px;

            font-weight: 700;

            transition: 0.2s;
        }

        .btn-modal-save:hover {
            background: #1d4ed8;
        }

        /* Desktop modal center */

        @media (min-width:768px) {

            .modal-overlay {
                align-items: center;
            }

            .modal-content {
                border-radius: 16px;
                transform: translateY(20px);
            }

        }

        /* LOCAITION SUGGESTION BOX CSS */
        .suggestion-box {
            position: absolute;
            width: 100%;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            max-height: 200px;
            overflow-y: auto;
            z-index: 9999;
            margin-top: 5px;
        }

        .suggestion-item {
            padding: 10px;
            cursor: pointer;
        }

        .suggestion-item:hover {
            background: #f1f5ff;
        }

        .input-group {
            position: relative;
        }

        .site-logo {
            height: 40px;
            width: auto;
            display: block;
        }


        .mobile-avatar {
            display: none;
        }

        @media (max-width: 900px) {
            .mobile-avatar {
                display: flex;
                width: 36px;
                height: 36px;
                background: var(--primary);
                color: white;
                border-radius: 50%;
                align-items: center;
                justify-content: center;
                font-size: 16px;
                cursor: pointer;
            }
        }

        @media (max-width: 900px) {
            .user-name {
                display: none;
                /* hide only text */
            }

            .user-profile {
                padding: 5px;
            }
        }

        .user-profile {
            -webkit-tap-highlight-color: transparent;
        }
    </style>

</head>

<body>

    <header>
        <div class="header-container">

            <div class="brand-group">

                <div class="brand">
                    <a href="index.php">
                        <img src="/assets/pacific_iconnect.png" width="200" alt="Logo">
                    </a>
                </div>

                <div class="location-pin" onclick="openLocationModal()">
                    <i class="fas fa-map-marker-alt"></i>
                    <span id="headerCity"><?php echo htmlspecialchars($city_name); ?></span>
                </div>

            </div>

            <nav class="desktop-nav">
                <a href="index.php" class="nav-link active">Dashboard</a>
                <a href="post-job.php" class="nav-link">Post Jobs</a>
                <a href="applications.php" class="nav-link">Applications</a>
            </nav>

            <div class="header-actions">

            <a href="notifications.php">
                <div class="nav-action-icon">
                    <i class="fas fa-bell"></i>
                    <span class="noti-badge"><?= $notification_count ?></span>
                </div></a>
                <!-- <div class="mobile-avatar">
                    <i class="fas fa-user"></i>
                </div> -->

                <div class="profile-dropdown-wrap">
                    <div class="user-profile">
                        <div class="user-avatar"><i class="fas fa-user"></i></div>
                        <span class="user-name">
                            Profile
                            <i class="fas fa-chevron-down" style="font-size:0.75rem;"></i>
                        </span>
                    </div>

                    <div class="dropdown-menu">
                        <a href="my_profile.php" class="dropdown-item">
                            <i class="fas fa-building"></i> Company Profile
                        </a>

                        <a href="../logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>

                </div>

            </div>

        </div>
    </header>

    <!-- Location Modal -->

    <div class="modal-overlay" id="locationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Your Current Location</h3>
            </div>

            <div class="input-group">
                <label class="input-label">Where are you currently staying?</label>
                <input type="text" id="headerCityInput" class="modal-input" autocomplete="off">

                <!-- hidden fields -->
                <input type="hidden" id="headerStateInput">
                <input type="hidden" id="headerCountryInput">

                <!-- suggestion box -->
                <div id="headerCitySuggestions" class="suggestion-box"></div>
            </div>

            <div class="input-group">
                <label class="input-label">Select Locality</label>

                <input type="text"
                    id="headerLocalityInput"
                    class="modal-input"
                    autocomplete="off"
                    placeholder="Enter locality">

                <div id="headerLocalitySuggestions" class="suggestion-box"></div>
            </div>

            <div class="input-group">
                <label class="input-label">Pin Code</label>
                <input type="text" id="headerPincodeInput" class="modal-input" placeholder="Enter Pin Code">
            </div>

            <div class="modal-btn-row">
                <button class="btn-modal-cancel" onclick="closeLocationModal()">Cancel</button>
                <button class="btn-modal-save" onclick="updateLocation()">Update</button>
            </div>
        </div>
    </div>

</body>

<script>
    let headerService;
    let headerPlaceService;

    let headerSelectedCountry = "";
    let headerSelectedState = "";
    let headerSelectedCity = "";

    function initHeaderCityAutocomplete() {

        headerService = new google.maps.places.AutocompleteService();
        headerPlaceService = new google.maps.places.PlacesService(document.createElement('div'));

        const input = document.getElementById("headerCityInput");

        input.addEventListener("keyup", function() {

            let query = input.value;

            if (query.length < 2) return;

            headerService.getPlacePredictions({
                input: query,
                componentRestrictions: {
                    country: "in"
                }
            }, function(predictions, status) {

                if (!predictions) return;

                showHeaderCitySuggestions(predictions);

            });

        });

    }

    function showHeaderLocalitySuggestions(list, query) {

        let box = document.getElementById("headerLocalitySuggestions");
        box.innerHTML = "";

        if (list.length === 0) {
            box.style.display = "none";
            return;
        }

        box.style.display = "block";

        list.forEach(function(item) {

            if (
                item.types.includes("sublocality") ||
                item.types.includes("sublocality_level_1") ||
                item.types.includes("neighborhood") ||
                item.types.includes("premise")
            ) {

                if (headerSelectedCity && item.description.toLowerCase().includes(headerSelectedCity.toLowerCase())) {

                    let div = document.createElement("div");
                    div.className = "suggestion-item";
                    div.innerHTML = item.description;

                    div.onclick = function() {

                        let parts = item.description.split(",");
                        let cleaned = [];

                        for (let i = 0; i < parts.length; i++) {

                            let p = parts[i].trim();

                            if (p === headerSelectedCity) break;

                            cleaned.push(p);
                        }

                        document.getElementById("headerLocalityInput").value = cleaned.join(", ");

                        box.innerHTML = "";

                    }

                    box.appendChild(div);

                }

            }

        });

    }

    function showHeaderCitySuggestions(list) {

        let box = document.getElementById("headerCitySuggestions");
        box.innerHTML = "";

        if (list.length === 0) {
            box.style.display = "none";
            return;
        }

        box.style.display = "block";

        list.forEach(function(item) {

            if (!item.types.includes("locality")) return;

            let div = document.createElement("div");
            div.className = "suggestion-item";
            div.innerHTML = item.description;

            div.onclick = function() {

                getHeaderPlaceDetails(item.place_id);

                box.innerHTML = "";
            }

            box.appendChild(div);

        });

    }

    function getHeaderPlaceDetails(placeId) {

        headerPlaceService.getDetails({
            placeId: placeId,
            fields: ["address_components", "name"]
        }, function(place, status) {

            if (status !== "OK") return;

            let city = "";
            let state = "";
            let country = "";


            place.address_components.forEach(function(c) {

                if (c.types.includes("locality")) {
                    city = c.long_name;
                }

                if (c.types.includes("administrative_area_level_1")) {
                    state = c.long_name;
                }

                if (c.types.includes("country")) {
                    country = c.long_name;
                }

            });

            document.getElementById("headerCityInput").value = city;
            document.getElementById("headerStateInput").value = state;
            document.getElementById("headerCountryInput").value = country;
            headerSelectedCity = city;

        });

    }

    document.getElementById("headerLocalityInput").addEventListener("keyup", function() {

        let query = this.value;

        if (query.length < 2) return;

        headerService.getPlacePredictions({
            input: query,
            componentRestrictions: {
                country: "in"
            }
        }, function(predictions, status) {

            if (!predictions) return;

            showHeaderLocalitySuggestions(predictions, query);

        });

    });
























    function openLocationModal() {

        document.getElementById('locationModal').classList.add('active');

        const userid = <?php echo (int)$user['id']; ?>;

        fetch("/web_api/getUsercity.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    userid: userid
                })
            })
            .then(res => res.json())
            .then(data => {

                if (data.status === "success") {

                    const city = data.data.city_name ?? "";
                    const locality = data.data.locality_name ?? "";

                    // set input values
                    document.getElementById("headerCityInput").value = city;
                    document.getElementById("headerLocalityInput").value = locality;

                    // ✅ ADD THIS LINE (MAIN FIX)
                    headerSelectedCity = city;

                } else {
                    console.log(data.message);
                }

            })
            .catch(err => {
                console.log(err);
            });

    }

    function closeLocationModal() {
        document.getElementById('locationModal').classList.remove('active');
    }

    function updateLocation() {

        const city = document.getElementById("headerCityInput").value.trim();
        const locality = document.getElementById("headerLocalityInput").value.trim();
        const state = document.getElementById("headerStateInput").value;
        const country = document.getElementById("headerCountryInput").value;
        const pincode = document.getElementById("headerPincodeInput").value;
        const userid = <?php echo (int)$user['id']; ?>;

        if (city === "") {
            alert("City is required");
            return;
        }

        const payload = {
            userid: userid,
            city_id: city,
            locality_id: locality,
            state: state,
            country: country,
            pincode: pincode
        };

        console.log("Sending to API:", payload);

        fetch("/web_api/updateCity.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    userid: userid,
                    city_id: city,
                    locality_id: locality,
                    state: state,
                    country: country,
                    pincode: pincode
                })
            })
            .then(res => res.json())
            .then(data => {

                if (data.status === "success") {

                    // update header instantly
                    document.getElementById("headerCity").innerText = city;

                    closeLocationModal();

                } else {
                    alert(data.message);
                }

            })
            .catch(err => {
                console.log(err);
                alert("Failed to update location");
            });

    }

    const profileWrap = document.querySelector(".profile-dropdown-wrap");
    const profileBtn = document.querySelector(".user-profile");

    // toggle dropdown (ONLY ONE EVENT)
    profileBtn.addEventListener("click", function(e) {
        e.stopPropagation();

        if (profileWrap.classList.contains("active")) {
            profileWrap.classList.remove("active");
        } else {
            profileWrap.classList.add("active");
        }
    });

    // close on outside click (WORKS FOR BOTH MOBILE + DESKTOP)
    document.addEventListener("click", function(e) {
        if (!e.target.closest(".profile-dropdown-wrap")) {
            profileWrap.classList.remove("active");
        }
    });
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key=
    AIzaSyCokcdTmQxRaopu75ourz-nNmZNie1wQkY&libraries=places&callback=initHeaderCityAutocomplete"
    async defer></script>