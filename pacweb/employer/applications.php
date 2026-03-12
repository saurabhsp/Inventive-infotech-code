<?php
session_start();
$userid = 1;




















/* Example session */
$recruiter_id = 1; // recruiter profile id
$status_id = $_POST['status_id'] ?? 6;

$api_url = "https://beta.inv51.in/webservices/getApplicationlist.php";


$request = [
    "job_id" => 0,
    "status_id" => $status_id,
    "job_listing_type" => 1,
    "recruiter_id" => $recruiter_id,
    "page" => 1,
    "limit" => 10
];

$ch = curl_init($api_url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($request)
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

$applications = $result['data'] ?? [];













//status 
$status_api = "https://beta.inv51.in/webservices/getApplicationstatus.php";
$status_request = [
    "recruiter" => null,
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







// STATUS DROPDOWN FOR MODAL
$status_modal_api = "https://beta.inv51.in/webservices/getApplicationstatus.php";
$status_modal_request = [
    "recruiter" => 1
];
$ch = curl_init($status_modal_api);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode($status_modal_request)
]);
$status_modal_response = curl_exec($ch);
curl_close($ch);
$status_modal_result = json_decode($status_modal_response, true);
$status_modal_dropdown = $status_modal_result['data'] ?? [];
// STATUS DROPDOWN FOR MODAL END






//INTERVIEW MODE DROPDOWN
// interview types
$interview_api = "https://beta.inv51.in/webservices/getInterviewTypes.php";

$ch = curl_init($interview_api);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode([])
]);

$interview_response = curl_exec($ch);
curl_close($ch);

$interview_result = json_decode($interview_response, true);

$interview_types = $interview_result['data'] ?? [];






// SCHEDULE INTERVIEW API CALL
if (isset($_POST['schedule_interview'])) {

    $application_id = $_POST['application_id'];
    $interview_date = date("d-m-Y", strtotime($_POST['interview_date']));
    $interview_time = $_POST['interview_time'];
    $interview_type_id = $_POST['interview_type_id'];

    $schedule_api = "https://beta.inv51.in/webservices/scheduleInterview.php";

    $schedule_request = [
        "application_id" => $application_id,
        "interview_date" => $interview_date,
        "interview_time" => $interview_time,
        "interview_type_id" => $interview_type_id,
        "updated_by" => $userid
    ];

    print_r($schedule_request);
    exit;

    $ch = curl_init($schedule_api);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($schedule_request)
    ]);

    $schedule_response = curl_exec($ch);
    curl_close($ch);

    $schedule_result = json_decode($schedule_response, true);

    if ($schedule_result['status'] == true) {

        $_SESSION['success'] = "Interview Scheduled Successfully";
    } else {

        $_SESSION['error'] = $schedule_result['message'];
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}






// UPDATE STATUS API
if (isset($_POST['update_status'])) {

    $application_id = $_POST['application_id'];
    $status_id = $_POST['status_id'];

    $update_api = "https://beta.inv51.in/webservices/updateApplicationstatus.php";

    $update_payload = [
        "application_id" => $application_id,
        "status_id" => $status_id,
        "updated_by" => $userid
    ];

    $ch = curl_init($update_api);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($update_payload)
    ]);
    $update_response = curl_exec($ch);
    curl_close($ch);

    $update_result = json_decode($update_response, true);
    if ($update_result['status'] == true) {
        echo "<script>
        window.onload = function(){
            openStatusSuccessModal(
                '" . addslashes($update_result['notification']['title']) . "',
                '" . addslashes($update_result['notification']['message']) . "'
            );
        }
        </script>";
    } else {
        echo "<script>
        window.onload = function(){
            openStatusSuccessModal(
                'Error',
                '" . addslashes($update_result['message']) . "'
            );
        }
        </script>";
    }
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications Received | Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            /* Pacific iConnect Theme Colors */
            --primary: #483EA8;
            --primary-light: #eceaf9;
            --primary-dark: #322b7a;
            --blue-btn: #2563eb;
            --success-green: #10b981;
            --success-bg: #d1fae5;
            --danger-red: #e53935;
            --text-dark: #1a1a1a;
            --text-muted: #555555;
            --border-light: #e5e7eb;
            --bg-body: #f4f6f9;
            --white: #ffffff;
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
            margin: 40px auto;
            padding: 0 20px;
            flex: 1;
            width: 100%;
        }

        /* Page Title */
        .page-header {
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text-dark);
            border-left: 5px solid var(--primary);
            padding-left: 12px;
            line-height: 1.2;
        }

        /* Filters */
        .filters {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            overflow-x: auto;
            padding-bottom: 5px;
            -webkit-overflow-scrolling: touch;
        }

        .filters::-webkit-scrollbar {
            height: 0px;
        }

        .filter-pill {
            padding: 8px 20px;
            border: 1px solid var(--border-light);
            border-radius: 25px;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted);
            background: var(--white);
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .filter-pill:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .filter-pill.active {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        /* --- 3. HORIZONTAL APPLICATION CARDS --- */
        .app-card {
            background: var(--white);
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 20px;
            border: 1px solid var(--border-light);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            gap: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .app-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
            border-color: #cbd5e1;
        }

        /* Top Section: Avatar & Name */
        .app-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .app-user-info {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .avatar-lg {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.8rem;
            color: #94a3b8;
        }

        .user-details h3 {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .kyc-badge {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--success-green);
            background: var(--success-bg);
            padding: 2px 8px;
            border-radius: 12px;
            border: 1px solid var(--success-green);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .job-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        /* UNIFIED Dynamic Status Badge */
        .status-badge {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--blue-btn);
            background: #eff6ff;
            padding: 6px 14px;
            border-radius: 6px;
            border: 1px solid #bfdbfe;
            white-space: nowrap;
            text-align: center;
        }

        /* Middle Section: Info Grid */
        .app-info-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            background: #f8fafc;
            padding: 12px 20px;
            border-radius: 8px;
            border: 1px solid #f1f5f9;
        }

        .info-col {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .info-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-val {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-val i {
            color: #94a3b8;
            font-size: 0.85rem;
        }

        /* Bottom Section: Actions */
        .app-card-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
            flex-wrap: wrap;
            gap: 15px;
        }

        .applied-date {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-muted);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* UPDATED: Action buttons match the screenshot (colored outline) */
        .btn-action {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--primary);
            /* Colored border */
            background: var(--white);
            color: var(--primary);
            /* Colored text */
            transition: all 0.2s;
        }

        .btn-action:hover {
            background: var(--primary-light);
        }

        /* Keep Update Status button as solid primary color */
        .btn-primary {
            padding: 8px 24px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--blue-btn);
            background: var(--blue-btn);
            color: var(--white);
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }


        /* --- 4. SCHEDULE INTERVIEW MODAL --- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: var(--white);
            width: 90%;
            max-width: 450px;
            border-radius: 12px;
            transform: translateY(20px);
            transition: transform 0.3s ease;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-light);
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.3rem;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: var(--danger-red);
        }

        .modal-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.95rem;
            color: var(--text-dark);
            outline: none;
            transition: border-color 0.2s;
            background: var(--white);
        }

        .form-control:focus {
            border-color: var(--blue-btn);
            box-shadow: 0 0 0 3px #eff6ff;
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        select.form-control {
            cursor: pointer;
            appearance: auto;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-light);
            background: #f8fafc;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-cancel {
            padding: 10px 20px;
            border: 1px solid #cbd5e1;
            background: var(--white);
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-cancel:hover {
            background: #f1f5f9;
            color: var(--text-dark);
        }


        /* --- 5. MOBILE NAV & RESPONSIVENESS --- */
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

            body {
                padding-bottom: 80px;
            }

            .container {
                margin: 20px auto;
                padding: 0 15px;
            }

            .app-card-top {
                flex-direction: column;
                gap: 15px;
            }

            .status-badge {
                align-self: flex-start;
            }

            .app-info-grid {
                gap: 15px 30px;
            }

            .app-card-bottom {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .action-buttons {
                width: 100%;
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .btn-primary {
                grid-column: 1 / -1;
                justify-content: center;
                padding: 12px;
            }

            .btn-action {
                justify-content: center;
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
        <span class="mobile-header-title">Applications List</span>
    </div>

    <div class="container">

        <div class="page-header">
            <h1 class="page-title">Applications List</h1>
        </div>


        <div class="filters">
            <?php foreach ($statuses as $status): ?>

                <button
                    class="filter-pill <?= ($status_id == $status['id']) ? 'active' : '' ?>"
                    onclick="filterStatus(<?= $status['id'] ?>)">

                    <?= htmlspecialchars($status['name']) ?>

                </button>

            <?php endforeach; ?>
        </div>

        <?php foreach ($applications as $app): ?>

            <div class="app-card">

                <div class="app-card-top">

                    <div class="app-user-info">

                        <div class="avatar-lg">
                            <img src="<?= $app['profile_photo'] ?>"
                                style="width:55px;height:55px;border-radius:50%">
                        </div>

                        <div class="user-details">
                            <h3>
                                <?= htmlspecialchars($app['candidate_name']) ?>

                                <?php if ($app['kycstatus']) { ?>
                                    <span class="kyc-badge">
                                        <i class="fas fa-check-circle"></i> KYC Verified
                                    </span>
                                <?php } ?>

                            </h3>

                            <span class="job-title">
                                Applied for: <b><?= $app['jobname'] ?? 'N/A' ?></b>
                            </span>
                        </div>

                    </div>

                    <div class="status-badge">
                        <?= $app['status'] ?>
                    </div>

                </div>


                <div class="app-info-grid">

                    <div class="info-col">
                        <span class="info-label">Gender</span>
                        <span class="info-val">
                            <i class="fas fa-user"></i> <?= $app['gender'] ?>
                        </span>
                    </div>

                    <div class="info-col">
                        <span class="info-label">Age</span>
                        <span class="info-val">
                            <i class="fas fa-birthday-cake"></i> <?= $app['age'] ?> Yrs
                        </span>
                    </div>

                    <div class="info-col">
                        <span class="info-label">City</span>
                        <span class="info-val">
                            <i class="fas fa-map-marker-alt"></i> <?= $app['location'] ?>
                        </span>
                    </div>

                    <div class="info-col">
                        <span class="info-label">Mobile</span>
                        <span class="info-val">
                            <i class="fas fa-phone-alt"></i> <?= $app['mobile_no'] ?>
                        </span>
                    </div>

                </div>


                <div class="app-card-bottom">

                    <div class="applied-date">
                        Applied on: <?= $app['application_date'] ?>
                    </div>

                    <div class="action-buttons">

                        <a href="tel:<?= $app['mobile_no'] ?>" class="btn-action">
                            <i class="fas fa-phone"></i> Call
                        </a>

                        <a href="https://wa.me/91<?= $app['mobile_no'] ?>"
                            target="_blank"
                            class="btn-action">
                            <i class="fas fa-comment-dots"></i> Chat
                        </a>

                        <a class="btn-action" href="view-profile.php?application_id=<?= $app['application_id'] ?>">
                            <i class="fas fa-eye"></i> View
                        </a>

                        <button class="btn-action"
                            onclick="openInterviewModal('interviewModal', <?= $app['application_id'] ?>)">
                            <i class="fas fa-users"></i> Interview
                        </button>

                        <button class="btn-primary"
                            onclick="openStatusModal('statusModal', <?= $app['application_id'] ?>)">
                            Update Status
                        </button>

                    </div>

                </div>

            </div>

        <?php endforeach; ?>

        <?php if (empty($applications)) { ?>

            <div style="text-align:center;padding:40px;color:#666">
                No applications found
            </div>

        <?php } ?>



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
        <a href="#" class="nav-icon active">
            <div class="icon-wrap"><i class="fas fa-file-alt"></i></div>
            Applications
        </a>
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-user"></i></div>
            Profile
        </a>
    </div>

    <div class="modal-overlay" id="interviewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Schedule Interview</h3>
                <button class="modal-close" onclick="closeInterviewModal('interviewModal')"><i class="fas fa-times"></i></button>
            </div>




            <!-- INTERVIEW STATUS MODAL -->
            <form method="POST">

                <div class="modal-body">

                    <input type="hidden" name="application_id" id="application_id">

                    <div class="form-group">
                        <label class="form-label">Select Date *</label>
                        <input type="date" name="interview_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Select Time *</label>
                        <input type="time" name="interview_time" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Select Interview Mode *</label>

                        <select name="interview_type_id" class="form-control" required>

                            <option value="">Select Interview Mode</option>

                            <?php foreach ($interview_types as $type): ?>

                                <option value="<?= $type['id'] ?>">
                                    <?= htmlspecialchars($type['name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn-cancel"
                        onclick="closeInterviewModal('interviewModal')">
                        Cancel
                    </button>

                    <button type="submit" name="schedule_interview" class="btn-primary">
                        Schedule
                    </button>

                </div>

            </form>
        </div>
    </div>




    <!-- UPDATE INTERVIEW STATUS MODAL -->
    <div class="modal-overlay" id="statusModal">
        <div class="modal-content">

            <div class="modal-header">
                <h3 class="modal-title">Update Application Status</h3>
                <button class="modal-close"
                    onclick="closeStatusModal('statusModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST">
                <div class="modal-body">
                    <input type="hidden"
                        name="application_id"
                        id="status_application_id">

                    <div class="form-group">
                        <label class="form-label">Select Status *</label>

                        <select name="status_id"
                            class="form-control"
                            required>

                            <option value="">Select Status</option>
                            <?php foreach ($status_modal_dropdown as $status): ?>
                                <option value="<?= $status['id'] ?>">
                                    <?= htmlspecialchars($status['name']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button"
                        class="btn-cancel"
                        onclick="closeStatusModal('statusModal')">
                        Cancel
                    </button>

                    <button type="submit"
                        name="update_status"
                        class="btn-primary">
                        Update
                    </button>

                </div>

            </form>

        </div>
    </div>



    <!-- STATUS SUCCESS MODAL -->
    <div class="modal-overlay" id="statusSuccessModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="successTitle">Success</h3>
                <button class="modal-close"
                    onclick="closeStatusSuccessModal('statusSuccessModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="successMessage"
                    style="font-size:16px;font-weight:600;color:#333;">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-primary"
                    onclick="closeStatusSuccessModal('statusSuccessModal')">
                    OK
                </button>
            </div>
        </div>
    </div>

    <script>
        // Open Modal
        function openInterviewModal(modalId, application_id) {
            document.getElementById(modalId).classList.add('active');
            document.getElementById("application_id").value = application_id;
        }

        // Close Modal
        function closeInterviewModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modal when clicking outside the modal content
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        function filterStatus(status_id) {

            const form = document.createElement("form");
            form.method = "POST";

            const input = document.createElement("input");
            input.type = "hidden";
            input.name = "status_id";
            input.value = status_id;

            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }

        // OPEN STATUS MODAL
        function openStatusModal(modalId, application_id) {
            document.getElementById(modalId).classList.add('active');
            document.getElementById("status_application_id").value = application_id;
        }

        function closeStatusModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function openStatusSuccessModal(title, message) {
            document.getElementById("successTitle").innerText = title;
            document.getElementById("successMessage").innerText = message;
            document.getElementById("statusSuccessModal").classList.add("active");
        }

        function closeStatusSuccessModal(modalId) {
            document.getElementById(modalId).classList.remove("active");
        }
    </script>
</body>

</html>