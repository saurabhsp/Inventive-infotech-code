<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// if (!isset($_SESSION['user'])) {
//     header("Location: ../login.php");
//     exit();
// }

// $user = $_SESSION['user'];

$user_json = '{
    "status": "success",
    "message": "Login successful",
    "user_exists": true,
    "user": {
        "id": 741,
        "mobile_no": "7057520379",
        "profile_type_id": 3,
        "profile_id": 6,
        "ac_manager_id": "",
        "ac_manager_assigned_at": "",
        "ac_manager_assigned_by": "",
        "verfied_status": 0,
        "verified_by": 0,
        "verified_at": "0000-00-00 00:00:00",
        "city_id": "THANE",
        "address": "Bldg. No. 15, Haware Citi, Kasarvadavali Thane",
        "latitude": "",
        "longitude": "",
        "referral_code": "",
        "referred_by": 0,
        "referral_type": 0,
        "active_plan_id": 10,
        "status_id": 1,
        "created_at": "2025-09-06 19:08:04",
        "fcm_token": "f5UzuN2ZTLuF6Cf4oGl1YM:APA91bGbkGM1AWnTvB4Z89aamQnhURMeByF1uDyhwInfimlHdHyW6mkYPbsQm3Onq7_URTTMr_AqO_dHNPKfxCxRiH4MyO0yYBu4Z_cWZmcQXllOzKN4ZXw",
        "myreferral_code": "REFPAC-D0F4A611-TU3E",
        "city_name": "THANE",
        "valid_from": "06-09-2025",
        "valid_to": "06-09-2026",
        "plan_name": "Standard Plan",
        "validity_months": 12,
        "final_amount": 0,
        "subscription_status": "active",
        "subscription_message": "Your subscription is valid for 174 days."
    }
}';

$data = json_decode($user_json, true);
$user = $data['user'];

$userid = $user['id'];
$profile_type = $user['profile_type_id'];
$city = $user['city_id'] ?? "";
$locality = "";

/* Verify FCM token */
$fcm_token = $user['fcm_token'] ?? '';

if (empty($fcm_token)) {
    $fcm_status = "missing";
} else {
    $fcm_status = "valid";
}


/* API CALL */

$api_url = "https://beta.inv51.in/webservices/getRecruiterdashboard.php";

$post_data = [
    "userid" => $userid,
    "profile_type" => $profile_type,
    "city" => $city,
    "locality" => $locality
];

$ch = curl_init($api_url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);

/* SEND JSON BODY */
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));

/* IMPORTANT HEADER */
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Content-Length: " . strlen(json_encode($post_data))
]);

curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);

if ($response === false) {
    echo "Curl Error: " . curl_error($ch);
    exit;
}

curl_close($ch);


$data = json_decode($response, true);

if (!$data) {
    echo "JSON Decode Failed";
    exit;
}

$notifications = $data['unread_notification_count'] ?? 0;

/* SAVE IN SESSION */
$_SESSION['notification_count'] = $notifications;



$slider_list = $data['sliders'] ?? [];
$welcome_message = $data['welcome_message'] ?? '';
$combined_message = $data['combined_message'] ?? '';
$notifications = $data['unread_notification_count'] ?? 0;
$premium_jobs = $data['walkin_interviews'] ?? [];
$standard_jobs = $data['job_vacancies'] ?? [];






/* ================= KYC STATUS CHECK ================= */

$kyc_api_url = "https://beta.inv51.in/webservices/checkRecruiterprofile.php";

$kyc_post_data = [
    "userid" => $userid
];

$kyc_ch = curl_init($kyc_api_url);

curl_setopt($kyc_ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($kyc_ch, CURLOPT_POST, true);
curl_setopt($kyc_ch, CURLOPT_POSTFIELDS, json_encode($kyc_post_data));

curl_setopt($kyc_ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Content-Length: " . strlen(json_encode($kyc_post_data))
]);

curl_setopt($kyc_ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($kyc_ch, CURLOPT_SSL_VERIFYHOST, false);

$kyc_response = curl_exec($kyc_ch);

curl_close($kyc_ch);

$kyc_data = json_decode($kyc_response, true);

$show_kyc_modal = false;
$kyc_message = "";

if ($kyc_data && $kyc_data['status'] === false) {
    $show_kyc_modal = true;
    $kyc_message = $kyc_data['message'] ?? "KYC Pending";
}






/* ================= JOB STATUS LIST ================= */

$status_api = "https://beta.inv51.in/webservices/getJobstatus.php";

$status_post = [
    "display_status" => 1
];

$status_ch = curl_init($status_api);

curl_setopt($status_ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($status_ch, CURLOPT_POST, true);
curl_setopt($status_ch, CURLOPT_POSTFIELDS, json_encode($status_post));

curl_setopt($status_ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Content-Length: " . strlen(json_encode($status_post))
]);

curl_setopt($status_ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($status_ch, CURLOPT_SSL_VERIFYHOST, false);

$status_response = curl_exec($status_ch);

curl_close($status_ch);

$status_data = json_decode($status_response, true);

$job_status_list = [];

if ($status_data && $status_data['status'] == "success") {
    $job_status_list = $status_data['data'];
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


</head>
<style>
    /* --- 5. EMPTY STATE UI --- */
    .empty-state-wrapper {
        width: 100%;
        text-align: center;
        padding: 60px 20px;
        background: transparent;
        border-radius: 16px;
    }

    .empty-state-text {
        font-size: 1.1rem;
        color: #555;
        font-weight: 500;
        margin-bottom: 25px;
    }

    .btn-post-job-empty {
        background: #0f172a;
        color: white;
        border: none;
        padding: 14px 40px;
        border-radius: 30px;
        font-size: 1rem;
        font-weight: 600;
        transition: 0.2s;
        box-shadow: 0 5px 15px rgba(15, 23, 42, 0.2);
    }

    .btn-post-job-empty:hover {
        background: #1e293b;
        transform: translateY(-2px);
    }

    /* ===== MODAL UI FIX ===== */

    .status-modal-overlay {
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
    }

    .status-modal-content {
        background: white;
        width: 100%;
        max-width: 420px;
        padding: 30px 25px;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    }

    .status-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .modal-header h3 {
        font-size: 1.2rem;
        font-weight: 800;
    }

    .close-modal {
        cursor: pointer;
        font-size: 1.2rem;
        color: #888;
    }

    .close-modal:hover {
        color: #e53935;
    }

    .input-label {
        display: block;
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 6px;
        font-weight: 600;
    }

    .status-select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 1rem;
    }

    .btn-modal-save {
        background: #2563eb;
        color: white;
        border: none;
        padding: 12px;
        border-radius: 30px;
        font-weight: 700;
        width: 100%;
        cursor: pointer;
    }

    .btn-modal-save:hover {
        background: #1d4ed8;
    }
</style>











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

    .profile-dropdown-wrap {
        position: relative;
        padding-bottom: 10px;
        margin-bottom: -10px;
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

    .dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        min-width: 180px;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border: 1px solid #eee;
        opacity: 0;
        visibility: hidden;
        transform: translateY(10px);
        transition: all 0.3s ease;
        z-index: 1000;
        padding: 10px 0;
    }

    .profile-dropdown-wrap:hover .dropdown-menu {
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
        .user-profile,
        .brand span {
            display: none;
        }

        .header-container {
            padding: 0 15px;
        }

        .header-actions {
            gap: 15px;
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
</style>

<body>
    <div id="editErrorModal" style="display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.5);
    z-index:9999;
    align-items:center;
    justify-content:center;">

        <div style="background:white;padding:25px;border-radius:10px;width:350px;text-align:center">

            <h3 style="margin-bottom:10px">Edit Not Allowed</h3>

            <p id="editErrorMessage"></p>

            <button onclick="closeEditModal()"
                style="margin-top:15px;padding:8px 20px;background:#2563eb;color:white;border:none;border-radius:6px">
                OK
            </button>

        </div>
    </div>

    <!-- KYC STATUS MODAL -->
    <div id="kycModal" style="display:none;
position:fixed;
top:0;
left:0;
width:100%;
height:100%;
background:rgba(0,0,0,0.5);
z-index:9999;
align-items:center;
justify-content:center;">

        <div style="background:white;padding:25px;border-radius:10px;width:350px;text-align:center">

            <h3 style="margin-bottom:10px">KYC Required</h3>

            <p><?php echo htmlspecialchars($kyc_message); ?></p>

            <div style="margin-top:20px;display:flex;gap:10px;justify-content:center">

                <a href="kyc_upload.php"
                    style="padding:8px 18px;background:#16a34a;color:white;border-radius:6px;text-decoration:none">
                    Do KYC
                </a>

                <button onclick="closeKycModal()"
                    style="padding:8px 18px;background:#2563eb;color:white;border:none;border-radius:6px">
                    Close
                </button>

            </div>

        </div>

    </div>
    <!-- KYC MODAL END -->


    <?php  // include "includes/header.php"; 
    ?>

    <div class="boxed-container welcome-text">
        <?php echo $welcome_message; ?>
    </div>




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
                        class="card <?= $theme ?>" loading="lazy"
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
                <div class="dot active"></div>
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>

        </div>

    <?php endif; ?>


    <?php if (empty($premium_jobs) && empty($standard_jobs)) { ?>


        <div class="empty-state-wrapper">
            <div class="empty-state-text"> <?php echo $combined_message; ?></div>
            <button class="btn-post-job-empty">Post Jobs</button>
        </div>

    <?php } else { ?>
        <!-- ================= PREMIUM JOBS ================= -->

        <div class="boxed-container section-header">
            <div class="section-title">Premium Jobs</div>
            <?php if (!empty($premium_jobs)) { ?><button class="btn-view-all">View All</button> <?php } ?>
        </div>
        <?php if (!empty($premium_jobs)) { ?>

            <div class="boxed-container">

                <button class="scroll-arrow left-arrow" onclick="scrollJobSlider(this, -1)"><i class="fas fa-chevron-left"></i></button>
                <button class="scroll-arrow right-arrow" onclick="scrollJobSlider(this, 1)"><i class="fas fa-chevron-right"></i></button>

                <div class="jobs-slider-container drag-slider">


                    <?php foreach ($premium_jobs as $job):
                        $plan = intval($job['plan_display_status'] ?? 3);

                        if ($plan == 1) {
                            $plan_class = "gold";
                            $plan_text = "GOLD MEMBER";
                        } elseif ($plan == 2) {
                            $plan_class = "silver";
                            $plan_text = "SILVER MEMBER";
                        } else {
                            $plan_class = "bronze";
                            $plan_text = "BRONZE MEMBER";
                        } ?>

                        <div class="r-job-card">

                            <div class="menu-dot-container">
                                <div class="menu-dot-icon" onclick="toggleCardMenu(event, this)"><i class="fas fa-ellipsis-v"></i></div>
                                <div class="card-menu-dropdown">
                                    <!-- premium job modal -->
                                    <a href="javascript:void(0)" onclick="checkPremiumEdit(<?= $job['id'] ?>)">
                                        <i class="fas fa-edit"></i> Edit Job
                                    </a>
                                </div>
                            </div>

                            <div class="card-head">

                                <div class="logo-box">


                                    <div class="shiny-ring <?= $plan_class ?>"></div>

                                    <img
                                        src="<?= htmlspecialchars($job['company_logo']) ?>"
                                        class="company-logo">

                                    <div class="member-badge <?= $plan_class ?>">
                                        <?= $plan_text ?>
                                    </div>

                                </div>

                                <div class="job-info">

                                    <div class="job-title">
                                        <?= htmlspecialchars($job['job_position']) ?>
                                    </div>

                                    <div class="job-meta">
                                        Date: <?= htmlspecialchars($job['created_at']) ?>
                                    </div>

                                    <div class="job-status">
                                        Status: <?= htmlspecialchars($job['job_status']) ?>
                                    </div>

                                </div>

                            </div>



                            <div class="emp-stats-row">

                                <div class="emp-stat-box">
                                    Views:
                                    <span class="emp-stat-num"><?= intval($job['visit_count'] ?? 0) ?></span>
                                </div>

                                <div class="emp-stat-box">
                                    Calls:
                                    <span class="emp-stat-num"><?= intval($job['call_count'] ?? 0) ?></span>
                                </div>

                                <div class="emp-stat-box">
                                    Chats:
                                    <span class="emp-stat-num"><?= intval($job['whatsapp_count'] ?? 0) ?></span>
                                </div>

                                <div class="emp-stat-box">
                                    Locations:
                                    <span class="emp-stat-num"><?= intval($job['location_count'] ?? 0) ?></span>
                                </div>

                            </div>


                            <div class="apps-count">
                                Applications
                                <span class="apps-num"><?= $job['application_count'] ?></span>
                            </div>


                            <div class="card-actions">

                                <a href="applications.php?job_id=<?= $job['id'] ?>" class="btn-card">
                                    View<br>Applications
                                </a>

                                <form action="premium-job-details.php" method="POST" style="flex:1; display:flex;">
        <input type="hidden" name="id" value="<?= $job['id'] ?>">
        <input type="hidden" name="job_profile" value="premium">

        <button type="submit" class="btn-card" style="width:100%;">
            View Job
        </button>
    </form>

                                <button class="btn-card" onclick="openPremiumStatusModal(<?= $job['id'] ?>)">
                                    Change Status
                                </button>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>
            </div>

        <?php } else { ?>

            <div class="empty-state-wrapper">
                <div class="empty-state-text"> <?php echo $data['walkin_message']; ?></div>
                <button class="btn-post-job-empty">Post Jobs</button>
            </div>


        <?php } ?>


        <!-- ================= STANDARD JOBS ================= -->



        <div class="boxed-container section-header" style="margin-top: 20px;">
            <div class="section-title">Standard Jobs</div>
            <?php if (!empty($standard_jobs)) { ?>
                <button class="btn-view-all">View All</button><?php } ?>
        </div>

        <?php if (!empty($standard_jobs)) { ?>
            <div class="boxed-container" style="margin-bottom: 50px;">

                <button class="scroll-arrow left-arrow" onclick="scrollJobSlider(this, -1)">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <button class="scroll-arrow right-arrow" onclick="scrollJobSlider(this, 1)">
                    <i class="fas fa-chevron-right"></i>
                </button>

                <div class="jobs-slider-container drag-slider">

                    <?php foreach ($standard_jobs as $job): ?>

                        <div class="r-job-card">

                            <div class="menu-dot-container">
                                <div class="menu-dot-icon" onclick="toggleCardMenu(event, this)"><i class="fas fa-ellipsis-v"></i></div>
                                <div class="card-menu-dropdown">
                                    <!-- standard job modal -->
                                    <a href="javascript:void(0)" onclick="checkStandardEdit(<?= $job['id'] ?>)">
                                        <i class="fas fa-edit"></i> Edit Job
                                    </a>
                                </div>
                            </div>

                            <div class="card-head">

                                <div class="logo-box" style="width:65px;height:65px;">

                                    <img
                                        src="<?= htmlspecialchars($job['company_logo']) ?>"
                                        style="width:100%;height:100%;border-radius:50%;object-fit:cover;border:1px solid #eee;">

                                </div>

                                <div class="job-info">

                                    <div class="job-title">
                                        <?= htmlspecialchars($job['job_position']) ?>
                                    </div>

                                    <div class="job-meta">
                                        Date: <?= htmlspecialchars($job['created_at']) ?>
                                    </div>

                                    <div class="job-status">
                                        Status: <?= htmlspecialchars($job['job_status']) ?>
                                    </div>

                                </div>

                            </div>


                            <div class="emp-stats-row">

                                <div class="emp-stat-box">
                                    Views:
                                    <span class="emp-stat-num"><?= intval($job['visit_count']) ?></span>
                                </div>

                                <div class="emp-stat-box">
                                    Calls:
                                    <span class="emp-stat-num"><?= intval($job['call_count']) ?></span>
                                </div>

                                <div class="emp-stat-box" style="border:none;">
                                    Chats:
                                    <span class="emp-stat-num"><?= intval($job['whatsapp_count']) ?></span>
                                </div>

                            </div>


                            <div class="apps-count">
                                Applications
                                <span class="apps-num"><?= intval($job['application_count']) ?></span>
                            </div>


                            <div class="card-actions">

                                <a href="applications.php?job_id=<?= $job['id'] ?>" class="btn-card">
                                    View<br>Applications
                                </a>

                                <form action="standard-job-details.php.php" method="POST" style="flex:1; display:flex;">

                                    <input type="hidden" name="id" value="<?= $job['id'] ?>">
                                    <input type="hidden" name="job_profile" value="standard">

                                    <button type="submit" class="btn-card">
                                        View Job
                                    </button>

                                </form>

                                <button class="btn-card" onclick="openStandardStatusModal(<?= $job['id'] ?>)">
                                    Change Status
                                </button>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php } else { ?>



            <div class="empty-state-wrapper">
                <div class="empty-state-text"> <?php echo $data['vacancy_message']; ?></div>
                <button class="btn-post-job-empty">Post Jobs</button>
            </div>

        <?php } ?>



        <!-- COMBINED MESSAGE LOOP END -->


    <?php } ?>

    <!-- ================= PREMIUM JOB STATUS CHANGE MODAL ================= -->

    <div class="status-modal-overlay" id="statusModal">
        <div class="status-modal-content">

            <div class="status-modal-header">
                <h3>Update Job Status</h3>
                <i class="fas fa-times close-modal" onclick="closeStatusModal()"></i>
            </div>

            <div style="margin-bottom:25px;">

                <label class="input-label">Select New Status</label>

                <select id="jobStatusSelect" class="status-select">
                    <?php foreach ($job_status_list as $status) { ?>
                        <option value="<?= $status['id'] ?>">
                            <?= htmlspecialchars($status['name']) ?>
                        </option>
                    <?php } ?>

                </select>

            </div>

            <button class="btn-modal-save" style="width:100%;" onclick="updateJobStatus()">
                Save Status
            </button>

        </div>

    </div>

    <!-- ================= STANDARD JOB STATUS CHANGE MODAL ================= -->

    <div class="status-modal-overlay" id="standardStatusModal">
        <div class="status-modal-content">

            <div class="status-modal-header">
                <h3>Update Job Status</h3>
                <i class="fas fa-times close-modal" onclick="closeStandardStatusModal()"></i>
            </div>

            <div style="margin-bottom:25px;">

                <label class="input-label">Select New Status</label>

                <select id="standardJobStatusSelect" class="status-select">
                    <?php foreach ($job_status_list as $status) { ?>
                        <option value="<?= $status['id'] ?>">
                            <?= htmlspecialchars($status['name']) ?>
                        </option>
                    <?php } ?>

                </select>

            </div>

            <button class="btn-modal-save" style="width:100%;" onclick="updateStandardJobStatus()">
                Save Status
            </button>

        </div>

    </div>









    <script>
        // 1. Generic Left/Right Arrow Scrolling Logic (For Job Sliders Only)
        function scrollJobSlider(btn, direction) {
            const container = btn.parentElement.querySelector('.jobs-slider-container');
            const scrollAmount = 385; // card width + gap
            container.scrollBy({
                left: direction * scrollAmount,
                behavior: 'smooth'
            });
        }


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

        // 4. Job Card 3-Dots Menu Logic
        function toggleCardMenu(event, element) {
            event.stopPropagation(); // Prevent trigger document click
            const dropdown = element.nextElementSibling;

            // Close all other open menus first
            document.querySelectorAll('.card-menu-dropdown').forEach(menu => {
                if (menu !== dropdown) menu.classList.remove('show');
            });

            // Toggle the clicked one
            dropdown.classList.toggle('show');
        }
        // modal SCript
        function checkPremiumEdit(jobId) {

            fetch("/web_api/cehck24hrswalkinjobstatus.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        job_id: jobId
                    })
                })
                .then(res => res.json())
                .then(data => {

                    if (data.status === true && data.can_update === true) {
                        window.location.href = "edit-premium-jobs.php?id=" + jobId;
                    } else {
                        document.getElementById("editErrorMessage").innerText = data.message;
                        document.getElementById("editErrorModal").style.display = "flex";
                    }

                })
                .catch(err => {
                    console.log(err);
                });

        }



        function checkStandardEdit(jobId) {

            fetch("/web_api/check24hrsvacancyjobstatus.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        job_id: jobId
                    })
                })
                .then(res => res.json())
                .then(data => {

                    if (data.status === true && data.can_update === true) {
                        window.location.href = "edit-standard-jobs.php?id=" + jobId;
                    } else {
                        document.getElementById("editErrorMessage").innerText = data.message;
                        document.getElementById("editErrorModal").style.display = "flex";
                    }

                })
                .catch(err => {
                    console.log(err);
                });

        }








        //Modal end
        function closeEditModal() {
            document.getElementById("editErrorModal").style.display = "none";
        }


        //Status Update modal Premium
        let selectedJobId = 0;

        function openPremiumStatusModal(jobId) {
            selectedJobId = jobId;
            document.getElementById("statusModal").style.display = "flex";
        }

        function closeStatusModal() {
            document.getElementById("statusModal").style.display = "none";
        }

        function updateJobStatus() {

            let statusId = document.getElementById("jobStatusSelect").value;

            fetch("/web_api/UpdateWalkininterviewstatus.php", {

                    method: "POST",

                    headers: {
                        "Content-Type": "application/json"
                    },

                    body: JSON.stringify({
                        id: selectedJobId,
                        job_status_id: statusId
                    })

                })

                .then(res => res.json())

                .then(data => {

                    if (data.status) {
                        alert("Status Updated Successfully");
                        location.reload();
                    } else {
                        alert(data.message);
                    }

                })

                .catch(err => {
                    console.log(err);
                });

        }

        // STANDARD JOB STATUS UPDATE

        let selectedStandardJobId = 0;

        function openStandardStatusModal(jobId) {
            selectedStandardJobId = jobId;
            document.getElementById("standardStatusModal").style.display = "flex";
        }

        function closeStandardStatusModal() {
            document.getElementById("standardStatusModal").style.display = "none";
        }

        function updateStandardJobStatus() {

            let statusId = document.getElementById("standardJobStatusSelect").value;

            fetch("/web_api/updateVacancyjobstatus.php", {

                    method: "POST",

                    headers: {
                        "Content-Type": "application/json"
                    },

                    body: JSON.stringify({
                        id: selectedStandardJobId,
                        job_status_id: statusId
                    })

                })

                .then(res => res.json())

                .then(data => {

                    if (data.status) {
                        alert("Status Updated Successfully");
                        location.reload();
                    } else {
                        alert(data.message);
                    }

                })

                .catch(err => {
                    console.log(err);
                });

        }

        function closeKycModal() {
            document.getElementById("kycModal").style.display = "none";
        }

        <?php if ($show_kyc_modal) { ?>
            document.addEventListener("DOMContentLoaded", function() {
                document.getElementById("kycModal").style.display = "flex";

            });
        <?php } ?>
    </script>

</body>

</html>