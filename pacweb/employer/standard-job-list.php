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
$profile_id = $user['profile_id'];
$jobid = $_POST['id'] ?? '';



/* ================================================================
   JOB STATUS FETCH (same as dashboard)
   ================================================================ */
$need_status_fetch = (
    !isset($_SESSION['job_status_list']) ||
    !isset($_SESSION['job_status_cache_time']) ||
    (time() - $_SESSION['job_status_cache_time']) > 3600
);

if ($need_status_fetch) {
    require_once __DIR__ . '/../web_api/includes/initialize.php';
    require_once __DIR__ . '/../web_api/getJobstatus.php';

    $_SESSION['job_status_list']       = getJobstatusData(1, 0);
    $_SESSION['job_status_cache_time'] = time();
}

$job_status_list = $_SESSION['job_status_list'] ?? [];



$status_id = $_POST['status'] ?? 1; // default = active
$filterStatus = $status_id;
$pages = 1;
$limit = 10;

$url = API_BASE_URL . "getJobvacancylist.php";

$payload = json_encode([
    "job_status_id"       => $filterStatus,
    "page" => $pages,
    "limit" => $limit,
    "recruiter_id"     => $profile_id
]);

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
curl_close($ch);


$result = json_decode($response, true);


$jobs = $result['data'] ?? [];
// print_r($jobs);







//status 
$status_api = API_BASE_URL . "getJobstatus.php";
$status_request = [
    "display_status" => 1,
];
$ch = curl_init($status_api);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode($status_request)
]);
$status_response = curl_exec($ch);
// print_r($status_response);
// exit;
curl_close($ch);
$status_result = json_decode($status_response, true);
$statuses = $status_result['data'] ?? [];







function safe($v)
{
    return ($v && trim($v) != "") ? htmlspecialchars($v) : "Not specified";
}




?>








<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Standard Job List – Pacific iConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">


    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">




    <!-- ✅ COPY CSS FROM jobdetails.html -->
    <link rel="stylesheet" href="/style.css">

    <style>
        :root {
            /* Theme Colors */
            --primary: #483EA8;
            --primary-light: #eceaf9;
            --secondary: #ff6f00;
            --bg-body: #f4f6f9;
            --white: #ffffff;
            --text-dark: #1a1a1a;
            --blue-btn: #2563eb;
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

        .boxed-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        /* Tablet */
        @media (max-width: 992px) {
            .boxed-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Mobile */
        @media (max-width: 576px) {
            .boxed-container {
                grid-template-columns: 1fr;
            }
        }

        .r-job-card {
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .load-more-wrapper {
            display: flex;
            justify-content: center;
            margin: 30px 0;
        }

        .load-more-btn {
            background: var(--blue-btn);
            color: white;
            padding: 12px 40px;
            border-radius: 30px;
            border: none;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 5px 15px rgba(72, 62, 168, 0.3);
            transition: 0.3s;
        }

        .load-more-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        @media (max-width: 576px) {
            .load-more-btn {
                width: 80%;
                padding: 14px;
                font-size: 1rem;
            }
        }

        /* MODAL CSS */
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
        }

        .close-modal {
            cursor: pointer;
        }

        .status-select {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
        }

        .btn-modal-save {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            color: white;
            border-radius: 30px;
        }

        .status-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        /* X button */
        .close-modal {
            background: transparent;
            border: none;
            font-size: 22px;
            font-weight: bold;
            color: #888;
            cursor: pointer;
            line-height: 1;
        }

        .close-modal:hover {
            color: #e53935;
        }

        .close-modal {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: 0.2s;
        }

        .close-modal:hover {
            background: #fee2e2;
            color: #dc2626;
        }







        /* Filters */
        /* ================= FILTER UI (MATCH APPLICATION PAGE) ================= */

        :root {
            --primary: #483EA8;
            --primary-light: #eceaf9;
            --white: #ffffff;
            --text-muted: #555555;
            --border-light: #e5e7eb;
        }

        /* Container */
        .filters {
            display: flex;
            justify-content: flex-start;
            /* important for scroll */
            align-items: center;
            gap: 12px;
            margin: 20px auto 30px;
            flex-wrap: nowrap;
            /* ❌ no wrapping */
            overflow-x: auto;
            /* ✅ enable horizontal scroll */
            max-width: 100%;
            padding-bottom: 5px;
        }

        /* Hide scrollbar (optional clean UI) */
        .filters::-webkit-scrollbar {
            display: none;
        }

        /* Hide scrollbar */
        .filters::-webkit-scrollbar {
            height: 0px;
        }

        /* Pill button */
        .filter-pill {
            padding: 8px 18px;
            border: 1px solid var(--border-light);
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-muted);
            background: var(--white);
            cursor: pointer;
            transition: all 0.25s ease;
        }

        /* Hover effect */
        .filter-pill:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }

        /* Active state */
        .filter-pill.active {
            background: var(--blue-btn);
            color: var(--white);
            border-color: var(--blue-btn);
            box-shadow: 0 3px 8px rgba(72, 62, 168, 0.25);
        }

        .filters-wrapper {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        .filter-pill {
            white-space: nowrap;
            /* prevent text breaking */
            flex-shrink: 0;
            /* prevent shrinking */
        }

        @media (max-width: 576px) {
            .filters {
                justify-content: flex-start;
                padding-left: 10px;
            }
        }

        .page-heading {
            text-align: center;
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 15px;
        }
    </style>


</head>

<body>

    <?php include "includes/preloader.php"; ?>
    <?php include "includes/header.php"; ?>


    <div id="editErrorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:white;padding:25px;border-radius:10px;width:350px;text-align:center">
            <h3 style="margin-bottom:10px">Edit Not Allowed</h3>
            <p id="editErrorMessage"></p>
            <button onclick="closeEditModal()" style="margin-top:15px;padding:8px 20px;background:#2563eb;color:white;border:none;border-radius:6px">OK</button>
        </div>
    </div>





    <!-- STATUS MODAL -->
    <div class="status-modal-overlay" id="statusModal">
        <div class="status-modal-content">
            <div class="status-modal-header">
                <h3>Update Job Status</h3>
                <button class="close-modal" onclick="closeStatusModal()">&times;</button>
            </div>
            <div style="margin-bottom:25px;">
                <label class="input-label">Select New Status</label>
                <select id="jobStatusSelect" class="status-select">
                    <?php foreach ($job_status_list as $status): ?>
                        <option value="<?= $status['id'] ?>"><?= htmlspecialchars($status['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn-modal-save" onclick="updateJobStatus()">Save Status</button>
        </div>
    </div>



    <div class="container main-content">
        <form id="statusForm" method="POST">
            <input type="hidden" name="status" id="statusInput">
        </form>

        <h1 class="page-heading">Standard Job List</h1>

        <div class="filters-wrapper">

            <div class="filters">
                <?php foreach ($statuses as $status): ?>
                    <button type="button"
                        class="filter-pill <?= ($status_id == $status['id']) ? 'active' : '' ?>"
                        onclick="applyFilter(<?= $status['id'] ?>)">

                        <?= htmlspecialchars($status['name']) ?>

                    </button>

                <?php endforeach; ?>
            </div>
        </div>

        <div class="boxed-container">


            <?php
            $limit = 3;
            $total = count($jobs);
            ?>

            <?php foreach ($jobs as $index => $job): ?>


                <div class="r-job-card job-item"
                    style="<?= $index >= $limit ? 'display:none;' : '' ?>">

                    <div class="menu-dot-container">
                        <div class="menu-dot-icon" onclick="toggleCardMenu(event,this)"><i class="fas fa-ellipsis-v"></i></div>
                        <div class="card-menu-dropdown">
                            <a href="javascript:void(0)" onclick="checkStandardEdit(<?= $job['id'] ?>)"><i class="fas fa-edit"></i> Edit Job</a>
                        </div>
                    </div>

                    <!-- HEADER -->
                    <div class="card-head">
                        <div class="logo-box" style="width:65px;height:65px;">
                            <!-- <img src="<?= htmlspecialchars($job['company_logo']) ?>"
                                style="width:100%;height:100%;border-radius:50%;object-fit:cover;border:1px solid #eee;"> -->
                            <?php
                            $logo = $job['company_logo'] ?? '';

                            if (!$logo || trim($logo) == '') {
                                $logo = "https://pacificconnect2.0.inv51.in/webservices/uploads/logos/nologo.png";
                            }

                            $logo = str_replace('http://', 'https://', $logo);
                            ?>

                            <img src="<?= htmlspecialchars($logo) ?>"
                                style="width:100%;height:100%;border-radius:50%;object-fit:contain;background:#fff;">
                        </div>

                        <div class="job-info">
                            <div class="job-title"><?= htmlspecialchars($job['job_position']) ?></div>
                            <div class="job-meta">Date: <?= htmlspecialchars($job['created_at']) ?></div>
                            <div class="job-status">
                                Status: <?= htmlspecialchars($job['job_status']) ?>
                            </div>
                        </div>
                    </div>

                    <!-- STATS -->
                    <div class="emp-stats-row">
                        <div class="emp-stat-box">
                            Views: <span class="emp-stat-num"><?= intval($job['visit_count'] ?? 0) ?></span>
                        </div>

                        <div class="emp-stat-box">
                            Calls: <span class="emp-stat-num"><?= intval($job['call_count'] ?? 0) ?></span>
                        </div>

                        <div class="emp-stat-box" style="border:none;">
                            Chats: <span class="emp-stat-num"><?= intval($job['whatsapp_count'] ?? 0) ?></span>
                        </div>
                    </div>

                    <!-- APPLICATION COUNT -->
                    <div class="apps-count">
                        Applications <span class="apps-num"><?= intval($job['application_count'] ?? 0) ?></span>
                    </div>

                    <!-- ACTIONS -->
                    <div class="card-actions">

                        <form action="applications.php" method="POST" style="flex:1;display:flex;">
                            <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                            <button type="submit" class="btn-card">
                                View<br>Applications
                            </button>
                        </form>

                        <form action="standard-job-details.php" method="POST" style="flex:1;display:flex;">
                            <input type="hidden" name="id" value="<?= $job['id'] ?>">
                            <button type="submit" class="btn-card">View Job</button>
                        </form>

                        <button class="btn-card"
                            onclick="openStatusModal(<?= $job['id'] ?>)">
                            Change Status
                        </button>

                    </div>

                </div>

            <?php endforeach; ?>
        </div>

    </div>

    <?php if (empty($jobs)) { ?>

        <div style="
        text-align:center;
        padding:50px 20px;
        color:#64748b;
        font-size:16px;
        font-weight:600;
    ">
            No jobs found
        </div>

    <?php } ?>


    <?php if ($total > $limit): ?>
        <div class="load-more-wrapper">
            <button id="loadMoreBtn" class="load-more-btn">
                Load More..
            </button>
        </div>
    <?php endif; ?>

    </div>
    <?php include "includes/bottom-bar.php"; ?>

    <script>
        window.onload = () => document.getElementById("global-preloader")?.remove();
        let visible = 3;
        const step = 6;

        document.getElementById("loadMoreBtn")?.addEventListener("click", function() {

            const jobs = document.querySelectorAll(".job-item");

            for (let i = visible; i < visible + step; i++) {
                if (jobs[i]) {
                    jobs[i].style.display = "block";
                    jobs[i].style.opacity = "0";
                    setTimeout(() => {
                        jobs[i].style.transition = "0.3s";
                        jobs[i].style.opacity = "1";
                    }, 50);
                }
            }

            visible += step;

            // Hide button if all shown
            if (visible >= jobs.length) {
                this.style.display = "none";
            }
        });
        window.scrollBy({
            top: 200,
            behavior: "smooth"
        });

        let selectedJobId = 0;

        /* OPEN MODAL */
        function openStatusModal(jobId) {
            selectedJobId = jobId;
            document.getElementById("statusModal").style.display = "flex";
        }

        /* CLOSE MODAL */
        function closeStatusModal() {
            document.getElementById("statusModal").style.display = "none";
        }




        function toggleCardMenu(event, element) {
            event.stopPropagation();
            const dropdown = element.nextElementSibling;
            document.querySelectorAll('.card-menu-dropdown').forEach(menu => {
                if (menu !== dropdown) menu.classList.remove('show');
            });
            dropdown.classList.toggle('show');
        }
        document.addEventListener('click', () => {
            document.querySelectorAll('.card-menu-dropdown').forEach(m => m.classList.remove('show'));
        });

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

                        // Create form dynamically (same as premium)
                        let form = document.createElement("form");
                        form.method = "POST";
                        form.action = "add-standard-job.php";

                        // job_id field
                        let jobInput = document.createElement("input");
                        jobInput.type = "hidden";
                        jobInput.name = "job_id";
                        jobInput.value = jobId;

                        // mode field
                        let modeInput = document.createElement("input");
                        modeInput.type = "hidden";
                        modeInput.name = "mode";
                        modeInput.value = "edit";

                        // detect current page dynamically
                        let fromInput = document.createElement("input");
                        fromInput.type = "hidden";
                        fromInput.name = "from_page";
                        fromInput.value = window.location.pathname.split("/").pop(); // standard-job-list.php

                        form.appendChild(jobInput);
                        form.appendChild(modeInput);
                        form.appendChild(fromInput);

                        document.body.appendChild(form);
                        form.submit();

                    } else {
                        document.getElementById("editErrorMessage").innerText = data.message;
                        document.getElementById("editErrorModal").style.display = "flex";
                    }
                })
                .catch(err => console.error(err));
        }

        function closeEditModal() {
            document.getElementById("editErrorModal").style.display = "none";
        }








        /* UPDATE STATUS API CALL */
        function updateJobStatus() {

            const statusId = document.getElementById("jobStatusSelect").value;

            fetch("/web_api/updateVacancyjobstatus.php", {
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
                .catch(err => console.error(err));
        }

        function applyFilter(statusId) {
            document.getElementById("statusInput").value = statusId;
            document.getElementById("statusForm").submit();
        }
    </script>
</body>


</html>