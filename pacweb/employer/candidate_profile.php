<?php
session_start();

require_once "../web_api/includes/db_config.php";

$user = $_SESSION['user'];
$userid = $user['id'];


require_once "../web_api/includes/db_config.php";

// Get values first
$candidate_id = $_POST['candidate_id'] ?? $_SESSION['candidate_id'] ?? null;
$application_id = $_POST['application_id'] ?? $_SESSION['application_id'] ?? null;
$job_cp_id = $_POST['job_cp_id'] ?? $_SESSION['job_cp_id'] ?? null;

// Then store in session (only if coming from POST)
if (isset($_POST['candidate_id'])) {
    $_SESSION['candidate_id'] = $candidate_id;
    $_SESSION['application_id'] = $application_id;
}

if (!$candidate_id && !$application_id) {
    header("Location: applications.php");
    exit();
}

// API call

// ================= CALL / CHAT ACTION API =================
if (isset($_POST['call_action']) || isset($_POST['chat_action'])) {

    $action_type = isset($_POST['call_action']) ? 1 : 2;

    // 🔧 You must have these values (set properly)
    $job_listing_type = $_POST['job_listing_type'] ?? 1;

    $action_api = API_BASE_URL . "addAppactionlog.php";

    $payload = [
        "action_type" => $action_type,
        "userid" => $userid,
        "job_id" => $job_cp_id,
        "job_listing_type" => $job_listing_type, //1 for walking and 2 for job vacancy
        "application_id" => $application_id
    ];

    $ch = curl_init($action_api);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $api_response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($api_response, true);

    // ✅ HANDLE RESPONSE
    if (!empty($result['status'])) {
        $_SESSION['success_message'] = $result['message'] ?? "Action completed";
        //APACTION ADD
        if ($action_type == 1) {
            echo "<script>window.location.href='tel:{$candidate['mobile_no']}';</script>";
        }
        if ($action_type == 2) {
            echo "<script>window.open('https://wa.me/91{$candidate['mobile_no']}', '_blank');</script>";
        }
    } else {
        $_SESSION['error_message'] = $result['message'] ?? "Something went wrong";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// STATUS DROPDOWN FOR MODAL
$status_modal_api = API_BASE_URL . "getApplicationstatus.php";
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








$url =  API_BASE_URL . "getCandidateprofile.php";
$data = json_encode([
    "userid" => $candidate_id
]);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

$candidate = $result['data'];





//INTERVIEW MODE DROPDOWN
// interview types
$interview_api = API_BASE_URL . "getInterviewTypes.php";

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
    $interview_time = $_POST['interview_time'] . ":00";
    $interview_type_id = $_POST['interview_type_id'];

    // $schedule_api = API_BASE_URL . "scheduleInterview.php";
    $schedule_api =  "https://beta.inv51.in/webservices/scheduleInterview.php";

    $schedule_request = [
        "application_id" => $application_id,
        "interview_date" => $interview_date,
        "interview_time" => $interview_time,
        "interview_type_id" => $interview_type_id,
        "updated_by" => $userid
    ];


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
        $_SESSION['success_message'] = $schedule_result['message'] ?? "Interview Scheduled Successfully";
    } else {
        $_SESSION['error_message'] = $schedule_result['message'] ?? "Something went wrong";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}






// UPDATE STATUS API
if (isset($_POST['update_status'])) {

    $application_id = $_POST['application_id'];
    $status_id = $_POST['status_id'];

    $update_api = API_BASE_URL . "updateApplicationstatus.php";

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
        $_SESSION['success_message'] = $update_result['notification']['message'] ?? "Success";
    } else {
        $_SESSION['error_message'] = $update_result['message'] ?? "Something went wrong";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}










?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Profile | Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="/style.css">
    <style>
        :root {
            --primary: #483EA8;
            --primary-light: #eceaf9;
            --primary-dark: #322b7a;
            --blue-btn: #2563eb;
            --blue-hover: #1d4ed8;
            --text-dark: #1a1a1a;
            --text-muted: #64748b;
            --border-light: #cbd5e1;
            --bg-body: #f8fafc;
            --white: #ffffff;
        }



        /* --- CARD candidate-container --- */
        /* .candidate-container {
            max-width: 900px;
            margin: 20px auto;
        } */
        .candidate-container {
            max-width: 900px;
            /* increase width */
            width: 100%;
            margin: 20px auto;
        }

        .candidate-card {
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border-light);
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        /* --- CARD HEADER WITH BACK BUTTON --- */
        .card-header-nav {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            background: #fafafa;
        }

        .btn-back {
            background: none;
            border: none;
            color: var(--text-dark);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: color 0.2s;
        }

        .btn-back:hover {
            color: var(--primary);
        }

        /* --- USER PROFILE SECTION --- */
        .profile-main {
            display: flex;
            padding: 25px;
            gap: 20px;
            align-items: center;
        }

        .avatar-circle {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: #e0e7ff;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .avatar-circle i {
            font-size: 2rem;
            color: var(--primary);
        }

        .user-meta h2 {
            font-size: 1.25rem;
            color: var(--primary-dark);
            margin-bottom: 2px;
        }

        .user-meta p {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* --- DETAILS SECTION --- */
        .details-section {
            padding: 0 25px 20px 25px;
        }

        .section-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 15px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .data-item {
            display: flex;
            flex-direction: column;
        }

        .label {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 700;
            margin-bottom: 4px;
        }

        .value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        /* --- ACTION BUTTONS (MATCHING SCREENSHOT) --- */
        .card-actions {
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            border-top: 1px solid #f1f5f9;
        }

        .btn {
            padding: 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-outline {
            background: white;
            border: 1.5px solid var(--blue-btn);
            color: var(--blue-btn);
        }

        .btn-outline:hover {
            background: #f0f7ff;
        }

        .btn-full-width {
            grid-column: 1 / -1;
        }

        .btn-primary {
            background: var(--blue-btn);
            color: white;
            border: none;
            font-size: 1rem;
        }

        .btn-primary:hover {
            background: var(--blue-hover);
        }

        /* --- DESKTOP VIEW --- */
        @media (min-width: 768px) {
            .data-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .card-actions {
                display: flex;
                justify-content: flex-end;
            }

            .btn {
                width: auto;
                min-width: 140px;
            }

            .btn-full-width {
                grid-column: auto;
            }
        }

        /* ================= MODAL CSS START ================= */

        /* Overlay */
        .modal-full-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.55);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        /* Modal Card */
        .success-card {
            background: #ffffff;
            width: 100%;
            max-width: 480px;
            border-radius: 16px;
            padding: 40px 25px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
            animation: modalFadeIn 0.3s ease;
        }

        /* Animation */
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Icon */
        .success-icon-wrap {
            width: 90px;
            height: 90px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .success-icon-wrap i {
            color: #fff;
            font-size: 36px;
        }

        /* Error Icon */
        .error-icon {
            background: #e53935 !important;
        }

        /* Title */
        .success-title {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 10px;
        }

        /* Error Title */
        .error-title {
            color: #e53935;
        }

        /* Message */
        .success-subtitle {
            font-size: 15px;
            color: #374151;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        /* Buttons container */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        /* Buttons */
        .btn {
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            display: inline-block;
        }

        /* Primary Button */
        .btn-primary {
            background: #2563eb;
            color: #fff;
            border: none;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        /* Outline Button */
        .btn-outline {
            background: #fff;
            border: 1px solid #2563eb;
            color: #2563eb;
        }

        .btn-outline:hover {
            background: #eff6ff;
        }

        /* ================= MODAL CSS END ================= */
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

        /* Primary Button */
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
    </style>
</head>

<body>
    <?php
    include "includes/header.php";
    include "includes/preloader.php";
    ?>

    <!--===================== Success MODAL======================== -->
    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="modal-full-overlay active">
            <div class="success-card">
                <div class="success-icon-wrap">
                    <i class="fas fa-check"></i>
                </div>
                <div class="success-title">Success</div>
                <div class="success-subtitle">
                    <?= $_SESSION['success_message']; ?>
                </div>
                <div class="action-buttons">
                    <a href="<?= $_SERVER['PHP_SELF']; ?>" class="btn btn-primary">
                        OK
                    </a>
                </div>
            </div>
        </div>

    <?php unset($_SESSION['success_message']);
    endif; ?>


    <!--==================== ERROR MODAL======================== -->
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="modal-full-overlay active">
            <div class="success-card" style="border:1px solid #e53935;">
                <div class="success-icon-wrap error-icon" style="background:#e53935;">
                    <i class="fas fa-times"></i>
                </div>
                <div class="success-title error-title" style="color:#e53935;">Error</div>
                <div class="success-subtitle error-subtitle">
                    <?= $_SESSION['error_message']; ?>
                </div>
                <div class="action-buttons">
                    <a href="<?= $_SERVER['PHP_SELF']; ?>" class="btn btn-outline">
                        Close
                    </a>
                </div>

            </div>
        </div>
    <?php unset($_SESSION['error_message']);
    endif; ?>

    <div class="candidate-container">
        <div class="candidate-card">

            <div class="card-header-nav">
                <button class="btn-back" onclick="window.location.href='applications.php'">
                    <i class="fas fa-arrow-left"></i> Back to List
                </button>
            </div>

            <div class="profile-main">
                <div class="avatar-circle">
                    <!-- <i class="fas fa-user-tie"></i> -->
                    <img src="<?= $candidate['profile_photo'] ?>" width="70" height="70" style="border-radius:50%;">
                </div>
                <div class="user-meta">
                    <h2><?= $candidate['candidate_name'] ?></h2>
                    <p>Job Seeker</p>
                </div>
            </div>

            <div class="details-section">
                <span class="section-title">Personal Details</span>
                <div class="data-grid">
                    <div class="data-item"><span class="label">Mobile No</span> <span class="value"><?= $candidate['mobile_no'] ?></span></div>
                    <div class="data-item"><span class="label">Gender</span> <span class="value"><?= $candidate['gender'] ?></span>
                    </div>
                    <div class="data-item"><span class="label">Birthdate</span> <span class="value"><?= $candidate['birthdate'] ?></span>
                    </div>
                    <div class="data-item"><span class="label">Email</span> <span class="value"><?= $candidate['email'] ?></span></div>
                    <div class="data-item" style="grid-column: 1 / -1;"><span class="label">Address</span> <span class="value"><?= $candidate['address'] ?></span>
                    </div>
                    <div class="data-item"><span class="label">City</span><span class="value"><?= $candidate['city_id'] ?></span></div>
                    <div class="data-item"><span class="label">Locality</span><span class="value"><?= $candidate['locality_id'] ?></span>
                    </div>
                </div>

                <span class="section-title">Job Preferences</span>
                <div class="data-grid">
                    <div class="data-item" style="grid-column: 1 / -1;">
                        <span class="label">Job Positions</span>
                        <span class="value">
                            <?= !empty($candidate['job_positions']) ? $candidate['job_positions'] : 'N/A' ?>
                        </span>
                    </div>
                    <div class="data-item">
                        <span class="label">Experience Type</span><span class="value"><?= $candidate['experience_type_name'] ?></span>

                    </div>
                    <div class="data-item">
                        <span class="label">Experience</span><span class="value"><?= $candidate['experience_period_name'] ?></span>
                    </div>
                </div>
            </div>


            <div class="card-actions">
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="job_id" value="<?= $job_cp_id ?? 0 ?>">
                    <input type="hidden" name="job_listing_type" value="1">
                    <button type="submit" name="call_action" class="btn btn-outline">
                        <i class="fas fa-phone"></i> Call
                    </button>
                </form>

                <form method="POST" style="display:inline;">
                    <input type="hidden" name="job_id" value="<?= $job_cp_id ?? 0 ?>">
                    <input type="hidden" name="job_listing_type" value="1">
                    <button type="submit" name="chat_action" class="btn btn-outline">
                        <i class="fas fa-comment-dots"></i> Chat
                    </button>
                </form>
                <button class="btn btn-outline btn-full-width"
                    onclick="openInterviewModal('interviewModal', <?= $application_id ?>)">
                    <i class="fas fa-users"></i> Schedule Interview
                </button>
                <button class="btn btn-outline btn-full-width"
                    onclick="openStatusModal('statusModal', <?= $application_id ?>)">
                    Update Status
                </button>
            </div>

        </div>
    </div>




    <!-- INTERVIEW STATUS MODAL -->
    <div class="modal-overlay" id="interviewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Schedule Interview</h3>
                <button class="modal-close" onclick="closeInterviewModal('interviewModal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="application_id">
                    <div class="form-group">
                        <label class="form-label">Select Date *</label>
                        <input type="text" name="interview_date" placeholder="DD-MM-YYYY" class="form-control datepicker" required>
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


    <?php include "includes/bottom-bar.php"; ?>
    <script>
        window.onload = () => document.getElementById("global-preloader")?.remove();

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

        document.addEventListener("DOMContentLoaded", function() {
            flatpickr(".datepicker", {
                altInput: true,
                altFormat: "d-m-Y",
                dateFormat: "Y-m-d",
                allowInput: false,
                minDate: "today"
            });
        });
    </script>
</body>

</html>