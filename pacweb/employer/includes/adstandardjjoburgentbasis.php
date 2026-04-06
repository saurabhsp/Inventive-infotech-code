<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
$active = "post";
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
if (!empty($_POST['from_page'])) {
    $_SESSION['prev_page'] = $_POST['from_page'];
} elseif (empty($_SESSION['prev_page'])) {
    $_SESSION['prev_page'] = 'index.php';
}
require_once "../web_api/includes/db_config.php";

date_default_timezone_set("Asia/Kolkata");


$user         = $_SESSION['user'];
$userid       = $user['id'];
$recruiterid = $user['profile_id'];;

/* ================= SUBSCRIPTION CHECK FOR DIRECT URL ================= */

$sub_api = API_BASE_URL . "checkUsersubscription.php";

$sub_payload = json_encode([
    "user_id" => $userid
]);

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $sub_api,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $sub_payload,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
    ]
]);

$sub_response = curl_exec($ch);
curl_close($ch);

$sub_result = json_decode($sub_response, true);

// flags
$standard_limit_dialog = $sub_result['vacancy_limit_dialog'] ?? true;
$standard_upgrade_dialog = $sub_result['vacancy_upgrade_dialog'] ?? true;

// condition
if (($standard_limit_dialog == true || $standard_upgrade_dialog == true)) {
    header("Location: upgrade.php");
    exit();
}

/* ================= SUBSCRIPTION CHECK FOR DIRECT URLEND ================= */



//Array ( [job_id] => 23 [mode] => edit )
$job_id = $_POST['job_id'] ?? null;
// print_r($job_id);
// exit;
$mode = $_POST['mode'] ?? null;
$is_edit = ($mode === 'edit' && !empty($job_id));
$editData = [];
if ($is_edit) {
    $postData = json_encode([
        "id" => $job_id,
        "userid" => $userid
    ]);

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => API_BASE_URL . "getSinglejobvacancy.php",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($curl);
    curl_close($curl);
    // print_r( $response );
    // exit;

    $result = json_decode($response, true);
    if (isset($result['status']) && $result['status'] === 'success') {
        $editData = $result['data'];
    } else {
        $editData = [];
    }
}












$company_name = "";
$contact_person = "";
$interview_address =  "";
$contact_mobile = "";
$api_error = "";
/* ====================1. Get Recruiter Details========================== */

$post_user = json_encode([
    "user_id" => $userid
]);

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => API_BASE_URL . "getRecuriterdetails.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post_user
]);

$response = curl_exec($curl);
curl_close($curl);

if ($response) {
    $result = json_decode($response, true);

    if ($result['status'] == "success") {

        $company_name = $result['data']['organization_name'];
        $contact_person = $result['data']['contact_person_name'];
        $contact_mobile = $result['data']['contact_no'];
        $interview_address = $result['data']['address'] ?? "";
    }
}


/* ====================2. Get Job Positions========================== */

$job_positions = [];

$post_user = json_encode([
    "user_id" => $userid
]);

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => API_BASE_URL . "getPosition.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post_user
]);

$response = curl_exec($curl);
curl_close($curl);

if ($response) {

    $result = json_decode($response, true);

    if ($result['status'] == "success") {

        $job_positions = $result['data']['position'];
    }
}

/* ====================3. Get Degrees========================== */

$degrees = [];

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => API_BASE_URL . "getDegrees.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
]);

$response = curl_exec($curl);
curl_close($curl);

if ($response) {

    $result = json_decode($response, true);

    if ($result['status'] == "success") {

        $degrees = $result['data']['degree'];
    }
}

/* ====================4. Experience List========================== */

$experience_list = [];

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => API_BASE_URL . "getExperience_list.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
]);

$response = curl_exec($curl);
curl_close($curl);

if ($response) {

    $result = json_decode($response, true);

    if ($result['status'] == "success") {

        $experience_list = $result['data'];
    }
}

/* ====================5. Salary Range========================== */

$salary_ranges = [];

$post_salary = json_encode([
    "status" => 1
]);

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => API_BASE_URL . "getSalaryrange.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POSTFIELDS => $post_salary
]);

$response = curl_exec($curl);
curl_close($curl);

if ($response) {

    $result = json_decode($response, true);

    if ($result['status'] == "success") {

        $salary_ranges = $result['data'];
    }
}


/* ====================6. Get Gender List========================== */

$genders = [];

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => API_BASE_URL . "getGender.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
));

$getGender = curl_exec($curl);

if (curl_errno($curl)) {
    $api_error = "Unable to fetch genders.";
}

curl_close($curl);

if (!$api_error && $getGender) {

    $result = json_decode($getGender, true);

    if (isset($result['status']) && $result['status'] == "success") {
        $genders = $result['data'];
    } else {
        $api_error = "Gender list not found.";
    }
}
/* ====================SUBMIT JOB========================== */

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_submitted'])) {
    // $postData = [
    //     "recruiter_id" => $recruiterid,
    //     "company_name" => $company_name,
    //     "job_position_id" => $_POST['job_position'],
    //     "city_id" => $_POST['city'],
    //     "locality_id" => $_POST['locality'],
    //     "gender_id" => $_POST['gender'],
    //     "qualification_id" => $_POST['qualification'],
    //     "experience_from" => $_POST['experience_from'],
    //     "experience_to" => $_POST['experience_to'],
    //     "salary_from" => $_POST['salary_from'],
    //     "salary_to" => $_POST['salary_to'],
    //     "contact_person" => $_POST['contact_person'],
    //     "contact_no" => $_POST['contact_no'],
    //     "interview_address" => $_POST['interview_address'],

    //     "created_date" => date("Y-m-d"),
    //     "created_time" => date("H:i:s"),

    //     "valid_till_date" => $_POST['valid_till_date'] ?? "",
    //     "valid_till_time" => "23:59:59",
    //     "validity_apply" => $_POST['validity_apply'] ?? 0,
    //     "country" => $_POST['country'] ?? "",
    //     "state" => $_POST['state'] ?? "",
    //     "district" => $_POST['city'] ?? ""
    // ];

    $postData = [
        "recruiter_id" => $recruiterid,
        "company_name" => $company_name,
        "job_position_id" => $_POST['job_position'],
        "city_id" => $_POST['city'],
        "locality_id" => $_POST['locality'],
        "gender_id" => $_POST['gender'],
        "qualification_id" => $_POST['qualification'],
        "experience_from" => $_POST['experience_from'],
        "experience_to" => $_POST['experience_to'],
        "salary_from" => $_POST['salary_from'],
        "salary_to" => $_POST['salary_to'],
        "contact_person" => $_POST['contact_person'],
        "contact_no" => $_POST['contact_no'],
        "interview_address" => $_POST['interview_address'],
        "created_date" => date("Y-m-d"),
        "created_time" => date("H:i:s"),
        "validity_apply" => $_POST['validity_apply'] ?? 0,
        "country" => $_POST['country'] ?? "",
        "state" => $_POST['state'] ?? "",
        "district" => $_POST['city'] ?? ""
    ];

    // ✅ ADD HERE
    if ($_POST['validity_apply'] == 1 && !empty($_POST['valid_till_date'])) {
        $postData["valid_till_date"] = $_POST['valid_till_date'];
        $postData["valid_till_time"] = "23:59:59";
    } else {
        $postData["valid_till_date"] = "0000-00-00";
        $postData["valid_till_time"] = "00:00:00";
    }

    $postData = array_filter($postData, function ($value) {
        return $value !== null && $value !== "";
    });

    // ✅ ADD ID BEFORE ENCODE
    if ($is_edit && !empty($job_id)) {
        $postData['id'] = (int)$job_id;
    }
    $postjson = json_encode($postData);

    $curl = curl_init();

    curl_setopt_array($curl, [

        // CURLOPT_URL => API_BASE_URL . "addJobvacancy.php",
        CURLOPT_URL => "https://pacificconnect2.0.inv51.in/webservices/addJobvacancy.php",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postjson,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ]
    ]);
    $response = curl_exec($curl);
    curl_close($curl);
    $apiResult = json_decode($response, true);

    if ($apiResult['status'] == "success") {
        $_SESSION['success_message'] = $apiResult['message'];
        $_SESSION['last_job_id'] = $apiResult['job_data']['id'] ?? $job_id;
    } else {
        $_SESSION['error_message'] = $apiResult['message'] ?? "Something went wrong";
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
    <title>Post Standard Job | Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="/style.css">

    <style>
        :root {
            /* Pacific iConnect Theme Colors */
            --primary: #483EA8;
            --primary-light: #eceaf9;
            --primary-dark: #322b7a;
            --blue-btn: #2563eb;
            --blue-hover: #1d4ed8;
            --success-green: #10b981;
            --danger-red: #e53935;
            --text-dark: #1a1a1a;
            --text-muted: #64748b;
            --border-light: #cbd5e1;
            --bg-body: #f8fafc;
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

        /* --- 2. MAIN CONTENT AREA (FORM) --- */
        .main-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 25px 20px 60px;
        }

        .form-card {
            background: var(--white);
            width: 100%;
            max-width: 1200px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            padding: 25px 40px 30px;
        }

        .desktop-page-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            color: var(--text-dark);
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        /* Grid Layout for Desktop (Horizontal) */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 40px;
            align-items: start;
        }

        .form-group {
            margin-bottom: 0;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .form-label {
            display: block;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        /* Used for 'From' / 'To' dropdowns within a single grid cell */
        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-col {
            flex: 1;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-size: 0.95rem;
            color: var(--text-dark);
            background-color: var(--white);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .form-control:focus {
            border-color: var(--blue-btn);
            box-shadow: 0 0 0 3px #eff6ff;
        }

        select.form-control {
            appearance: auto;
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 95px;
            font-family: inherit;
            line-height: 1.5;
            flex: 1;
        }

        /* Toggle Buttons */
        .toggle-container {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
            /* Allows wrapping on very small screens */
        }

        .btn-toggle {
            padding: 8px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            background: #f8fafc;
            color: var(--text-muted);
            border: 1px solid var(--border-light);
            transition: all 0.2s;
            flex: 1;
            text-align: center;
            white-space: nowrap;
        }

        .btn-toggle.active {
            background: var(--blue-btn);
            color: var(--white);
            border-color: var(--blue-btn);
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.2);
        }

        .deadline-date-wrapper {
            display: none;
            margin-top: 5px;
            animation: fadeIn 0.3s ease;
        }

        .deadline-date-wrapper.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Submit Button Container */
        .submit-container {
            text-align: center;
            margin-top: 25px;
        }

        .btn-submit {
            width: auto;
            min-width: 300px;
            background: var(--blue-btn);
            color: var(--white);
            padding: 12px 30px;
            border-radius: 30px;
            font-size: 1.05rem;
            font-weight: 700;
            transition: background 0.2s;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            display: inline-block;
        }

        .btn-submit:hover {
            background: var(--blue-hover);
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

        /* --- 4. RESPONSIVE SETTINGS (Mobile Vertical Layout) --- */
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
                background: var(--white);
            }

            .main-wrapper {
                padding: 20px 15px;
            }

            .desktop-page-title {
                display: none;
            }

            .form-card {
                padding: 0;
                border: none;
                box-shadow: none;
            }

            /* Switch to vertical layout on mobile */
            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .submit-container {
                margin-top: 15px;
            }

            .btn-submit {
                width: 100%;
            }
        }

        /* saurabh css */
        /*  CSS BY SAURABH */
        .multi-select {
            position: relative;
            width: 100%;
        }

        .select-box {
            border: 1px solid #cbd5e1;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            background: #fff;
        }

        .checkbox-container {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
            width: 100%;
            z-index: 1000;
            padding: 10px;
        }

        .checkbox-item {
            display: block;
            margin-bottom: 6px;
            cursor: pointer;
        }

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

        /* --- 2. SUCCESS SCREEN CONTENT --- */

        /* --- 3. MAP LOCATION MODAL --- */
        .modal-full-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-full-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* ERROR MODAL */

        .error-title {
            font-size: 2.2rem;
            font-weight: 800;
            color: #e53935;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .error-icon-wrap {
            width: 120px;
            height: 120px;
            background-color: #e53935;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(229, 57, 53, 0.3);
            animation: scaleIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        .error-icon-wrap i {
            color: #ffffff;
            font-size: 4rem;
            animation: checkFade 0.5s 0.3s forwards;
            opacity: 0;
        }

        .success-card {
            background: var(--white);
            width: 100%;
            max-width: 500px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-light);
            padding: 50px 30px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Animated Checkmark Icon */
        .success-icon-wrap {
            width: 120px;
            height: 120px;
            background-color: var(--success-green);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
            animation: scaleIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        .success-icon-wrap i {
            color: var(--white);
            font-size: 4rem;
            animation: checkFade 0.5s 0.3s forwards;
            opacity: 0;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0);
            }

            100% {
                transform: scale(1);
            }
        }

        @keyframes checkFade {
            0% {
                opacity: 0;
                transform: translateY(10px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-title {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--success-dark);
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .success-subtitle {
            font-size: 1.3rem;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 40px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            width: 100%;
            justify-content: center;
        }

        .btn {
            padding: 14px 24px;
            border-radius: 8px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            flex: 1;
            /* Makes buttons equal width */
            max-width: 200px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
        }

        .btn-outline {
            background: var(--white);
            color: var(--blue-btn);
            border: 2px solid var(--blue-btn);
        }

        .btn-outline:hover {
            background: #eff6ff;
        }

        .btn-primary {
            background: var(--blue-btn);
            color: var(--white);
            border: 2px solid var(--blue-btn);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover {
            background: var(--blue-hover);
            border-color: var(--blue-hover);
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

        /* --- 4. RESPONSIVE SETTINGS --- */
        @media (max-width: 900px) {
            header {
                display: none;
            }

            .mobile-header {
                display: none;
            }

            /* Hide top header on success screen for clean look */
            .bottom-nav {
                display: flex;
            }

            body {
                background: var(--white);
                /* White background on mobile like native app */
                padding-bottom: 70px;
                /* Space for bottom nav */
            }

            .main-wrapper {
                padding: 20px;
            }

            .success-card {
                padding: 40px 20px;
                border: none;
                box-shadow: none;
            }

            .action-buttons {
                flex-direction: column;
                gap: 15px;
            }

            .btn {
                max-width: 100%;
                /* Full width buttons on mobile */
                padding: 16px;
                /* Slightly taller for touch */
            }
        }
    </style>
</head>

<body>

    <?php include "includes/header.php";
    include "includes/preloader.php";
    ?>
    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="modal-full-overlay active" id="successModal">
            <div class="success-card">
                <h1 class="success-title">Success!</h1>
                <p class="success-subtitle">
                    <?php echo $_SESSION['success_message']; ?>
                </p>
                <div class="success-icon-wrap">
                    <i class="fas fa-check"></i>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-outline"
                        onclick="closeSuccessModal()">
                        Close
                    </button>
                    <form action="standard-job-details.php" method="POST">
                        <input type="hidden" name="id"
                            value="<?php echo $_SESSION['last_job_id'] ?? ''; ?>">

                        <button type="submit" class="btn btn-primary">
                            View Job
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>







    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="modal-full-overlay active" id="errorModal">
            <div class="success-card">
                <h1 class="error-title">Error!</h1>
                <p class="success-subtitle">
                    <?php echo $_SESSION['error_message']; ?>
                </p>
                <div class="error-icon-wrap">
                    <i class="fas fa-times"></i>
                </div>

                <div class="action-buttons">
                    <button class="btn btn-outline" onclick="closeErrorModal()">
                        Close
                    </button>
                </div>

            </div>
        </div>



        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>



    <div class="mobile-header">
        <i class="fas fa-arrow-left mobile-back"></i>
        <span class="mobile-header-title">Post Standard Job</span>
    </div>

    <main class="main-wrapper">
        <div class="form-card">
            <h1 class="desktop-page-title">Post Standard Job</h1>

            <form method="POST">
                <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                <input type="hidden" name="form_submitted" value="1">
                <input type="hidden" name="from_page"
                    value="<?php echo $_SESSION['prev_page'] ?? ''; ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Company Name</label>
                        <input type="text" class="form-control" name="company_name"
                            value="<?php echo $is_edit ? htmlspecialchars($editData['company_name']) : htmlspecialchars($company_name); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Job Position</label>
                        <select class="form-control" name="job_position">
                            <option value="">Select Job Position</option>
                            <?php if (!empty($job_positions)) { ?>
                                <?php foreach ($job_positions as $position) { ?>
                                    <option value="<?php echo $position['id']; ?>"
                                        <?php echo ($is_edit && $editData['job_position_id'] == $position['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($position['name']); ?>
                                    </option>
                                <?php } ?>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">District / Tehsil / City</label>
                        <input type="text" name="city" id="cityInput" class="form-control" autocomplete="off" value="<?php echo $is_edit ? htmlspecialchars($editData['city']) : ''; ?>">
                        <!-- hidden fields -->
                        <input type="hidden" id="stateInput" value="<?php echo $is_edit ? htmlspecialchars($editData['state']) : ''; ?>" name="state">
                        <input type="hidden" id="countryInput" value="<?php echo $is_edit ? htmlspecialchars($editData['country']) : ''; ?>" name="country">
                        <div id="citySuggestions" class="suggestion-box"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Area / Locality / Village</label>
                        <input type="text" id="localityInput" name="locality" class="form-control" value="<?php echo $is_edit ? htmlspecialchars($editData['locality']) : ''; ?>" autocomplete="off">
                        <div id="localitySuggestions" class="suggestion-box"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <div class="toggle-container" id="genderToggle">
                            <input type="hidden" name="gender" id="genderInput"
                                value="<?php echo $is_edit ? $editData['gender_id'] : ''; ?>"> <?php if (!empty($genders)) { ?>
                                <?php foreach ($genders as $gender) { ?>
                                    <button
                                        type="button"
                                        class="btn-toggle <?php echo ($is_edit && $editData['gender_id'] == $gender['id']) ? 'active' : ''; ?>"
                                        data-id="<?php echo $gender['id']; ?>"
                                        onclick="toggleGroup(this,'genderToggle')">
                                        <?php echo htmlspecialchars($gender['name']); ?>
                                    </button>

                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>


                    <div class="form-group">
                        <label class="form-label">Basic Qualification</label>
                        <select class="form-control" name="qualification">
                            <option value="">Choose Basic Qualification</option>
                            <?php if (!empty($degrees)) { ?>
                                <?php foreach ($degrees as $degree) { ?>
                                    <option value="<?php echo $degree['id']; ?>"
                                        <?php echo ($is_edit && $editData['qualification_id'] == $degree['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($degree['name']); ?>
                                    </option>
                                <?php } ?>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Experience</label>
                        <div class="form-row">
                            <div class="form-col">
                                <select class="form-control" name="experience_from">
                                    <option value="">From</option>
                                    <?php if (!empty($experience_list)) { ?>
                                        <?php foreach ($experience_list as $exp) { ?>
                                            <option value="<?php echo $exp['id']; ?>"
                                                <?php echo ($is_edit && $editData['experience_from_id'] == $exp['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($exp['name']); ?>
                                            </option>
                                        <?php } ?>
                                    <?php } ?>
                                </select>
                            </div>


                            <div class="form-col">
                                <select class="form-control" name="experience_to">
                                    <option value="">To</option>
                                    <?php if (!empty($experience_list)) { ?>
                                        <?php foreach ($experience_list as $exp) { ?>
                                            <option value="<?php echo $exp['id']; ?>"
                                                <?php echo ($is_edit && $editData['experience_to_id'] == $exp['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($exp['name']); ?>
                                            </option>
                                        <?php } ?>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Monthly Salary Range</label>

                        <div class="form-row">

                            <div class="form-col">
                                <select class="form-control" name="salary_from">
                                    <option value="">From ₹</option>
                                    <?php if (!empty($salary_ranges)) { ?>
                                        <?php foreach ($salary_ranges as $salary) { ?>
                                            <option value="<?php echo $salary['id']; ?>"
                                                <?php echo ($is_edit && $editData['salary_from_id'] == $salary['id']) ? 'selected' : ''; ?>>
                                                ₹<?php echo number_format($salary['name']); ?>
                                            </option>
                                        <?php } ?>
                                    <?php } ?>
                                </select>
                            </div>


                            <div class="form-col">
                                <select class="form-control" name="salary_to">
                                    <option value="">To ₹</option>
                                    <?php if (!empty($salary_ranges)) { ?>
                                        <?php foreach ($salary_ranges as $salary) { ?>
                                            <option value="<?php echo $salary['id']; ?>"
                                                <?php echo ($is_edit && $editData['salary_to_id'] == $salary['id']) ? 'selected' : ''; ?>>
                                                ₹<?php echo number_format($salary['name']); ?>
                                            </option>
                                        <?php } ?>
                                    <?php } ?>
                                </select>
                            </div>

                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Person for Interview :</label>
                        <input type="text" class="form-control" name="contact_person" value="<?php echo $is_edit ? htmlspecialchars($editData['contact_person']) : htmlspecialchars($contact_person); ?>" placeholder="Enter name">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Interviewer/ HR Contact No</label>
                        <input type="tel" class="form-control" name="contact_no" value="<?php echo $is_edit ? htmlspecialchars($editData['mobile_no']) : htmlspecialchars($contact_mobile); ?>" placeholder="Enter contact number">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Interview Location</label>
                        <textarea class="form-control" name="interview_address"><?php echo $is_edit ? htmlspecialchars($editData['interview_address']) : htmlspecialchars($interview_address); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Does this Job have a deadline?</label>
                        <input type="hidden" name="validity_apply" id="validityApplyInput"
                            value="<?php echo $is_edit ? ($editData['validity_apply'] ?? 0) : 0; ?>">
                        <div class="toggle-container" id="deadlineToggle">
                            <button type="button"
                                class="btn-toggle <?php echo ($is_edit && ($editData['validity_apply'] ?? 0) == 1) ? 'active' : ''; ?>"
                                onclick="handleDeadlineToggle(true, this)">Yes</button>

                            <button type="button"
                                class="btn-toggle <?php echo (!$is_edit || ($editData['validity_apply'] ?? 0) == 0) ? 'active' : ''; ?>"
                                onclick="handleDeadlineToggle(false, this)">No</button>
                        </div>

                        <div class="deadline-date-wrapper <?php echo ($is_edit && ($editData['validity_apply'] ?? 0) == 1) ? 'active' : ''; ?>" id="deadlineDateGroup">
                            <input type="text" name="valid_till_date" placeholder="DD-MM-YYYY" class="form-control validity-datepicker" placeholder="Select Date" value="<?php echo ($is_edit && !empty($editData['valid_till_date'])) ? $editData['valid_till_date'] : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="submit-container">
                    <button type="submit" class="btn-submit">Submit</button>
                </div>

            </form>
        </div>
    </main>


    <?php include "includes/bottom-bar.php"; ?>
    <script>
        window.onload = () => document.getElementById("global-preloader")?.remove();
        // Logic for Yes/No Deadline toggle
        function toggleDeadline(isYes) {
            const btnYes = document.getElementById('btnYes');
            const btnNo = document.getElementById('btnNo');
            const dateGroup = document.getElementById('deadlineDateGroup');

            if (isYes) {
                btnYes.classList.add('active');
                btnNo.classList.remove('active');
                dateGroup.classList.add('active');
            } else {
                btnNo.classList.add('active');
                btnYes.classList.remove('active');
                dateGroup.classList.remove('active');
            }
        }



        // Specific Deadline Logic
        function handleDeadlineToggle(isYes, clickedBtn) {
            toggleGroup(clickedBtn, 'deadlineToggle');

            const dateGroup = document.getElementById('deadlineDateGroup');
            const validityInput = document.getElementById('validityApplyInput');

            if (isYes) {
                dateGroup.classList.add('active');
                validityInput.value = 1;
            } else {
                dateGroup.classList.remove('active');
                validityInput.value = 0;
            }
        }

        function toggleGroup(clickedBtn, groupId) {

            const container = document.getElementById(groupId);
            const buttons = container.querySelectorAll('.btn-toggle');

            buttons.forEach(btn => btn.classList.remove('active'));
            clickedBtn.classList.add('active');

            // Gender value
            if (groupId === "genderToggle") {
                document.getElementById("genderInput").value =
                    clickedBtn.getAttribute("data-id");
            }
        }

        //success modal close 
        function closeSuccessModal() {
            let url = "<?php echo $_SESSION['prev_page'] ?? 'index.php'; ?>";

            // session clear via redirect param
            window.location.href = url;
        }

        function closeErrorModal() {
            const modal = document.getElementById("errorModal");
            if (modal) {
                modal.style.display = "none";
            }
        }
















        // Fetching city and locality through google api
        let service;
        let placeService;

        let selectedCountry = "";
        let selectedState = "";
        let selectedCity = "";

        function initCityAutocomplete() {

            service = new google.maps.places.AutocompleteService();
            placeService = new google.maps.places.PlacesService(document.createElement('div'));

            const input = document.getElementById("cityInput");

            input.addEventListener("keyup", function() {

                let query = input.value;

                if (query.length < 2) return;

                service.getPlacePredictions({
                    input: query,
                    componentRestrictions: {
                        country: "in"
                    }
                }, function(predictions, status) {

                    if (!predictions) return;

                    showCitySuggestions(predictions);

                });

            });

        }

        function showLocalitySuggestions(list, query) {

            let box = document.getElementById("localitySuggestions");
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

                    if (selectedCity && item.description.toLowerCase().includes(selectedCity.toLowerCase())) {

                        let div = document.createElement("div");
                        div.className = "suggestion-item";
                        div.innerHTML = item.description;

                        div.onclick = function() {

                            let parts = item.description.split(",");
                            let cleaned = [];

                            for (let i = 0; i < parts.length; i++) {

                                let p = parts[i].trim();

                                if (p === selectedCity) break;

                                cleaned.push(p);
                            }

                            document.getElementById("localityInput").value = cleaned.join(", ");

                            box.innerHTML = "";

                        }

                        box.appendChild(div);

                    }

                }

            });

        }

        function showCitySuggestions(list) {

            let box = document.getElementById("citySuggestions");
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

                    getPlaceDetails(item.place_id);

                    box.innerHTML = "";
                }

                box.appendChild(div);

            });

        }

        function getPlaceDetails(placeId) {

            placeService.getDetails({
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

                document.getElementById("cityInput").value = city;
                document.getElementById("stateInput").value = state;
                document.getElementById("countryInput").value = country;
                selectedCity = city;
            });

        }

        document.getElementById("localityInput").addEventListener("keyup", function() {

            let query = this.value;

            if (query.length < 2) return;

            service.getPlacePredictions({
                input: query,
                componentRestrictions: {
                    country: "in"
                }
            }, function(predictions, status) {

                if (!predictions) return;

                showLocalitySuggestions(predictions, query);

            });

        });

        document.addEventListener("DOMContentLoaded", function() {
            flatpickr(".validity-datepicker", {
                altInput: true,
                altFormat: "d-m-Y",
                dateFormat: "Y-m-d",
                allowInput: false
            });
        });
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCokcdTmQxRaopu75ourz-nNmZNie1wQkY&libraries=places&callback=initCityAutocomplete" async defer></script>

</body>

</html>