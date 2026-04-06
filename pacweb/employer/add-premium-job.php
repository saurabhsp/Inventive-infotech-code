<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
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

$active = "post";

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
$user = $_SESSION['user'] ?? null;
$userid = $user['id'] ?? 0;
$profile_id = $user['profile_id'];






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
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
    ],
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
]);

$sub_response = curl_exec($ch);
curl_close($ch);

$sub_result = json_decode($sub_response, true);

// flags
$premium_limit_dialog = $sub_result['walkin_limit_dialog'] ?? true;
$premium_upgrade_dialog = $sub_result['walkin_upgrade_dialog'] ?? true;

// condition
if (($premium_limit_dialog == true || $premium_upgrade_dialog == true)) {
    header("Location: upgrade.php");
    exit();
}
/* ================= SUBSCRIPTION CHECK FOR DIRECT URLEND ================= */










//Array ( [job_id] => 23 [mode] => edit )
$job_id = $_POST['job_id'] ?? null;
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
        CURLOPT_URL => API_BASE_URL . "getSinglewalkininterview.php",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    $result = json_decode($response, true);

    if ($result['status'] === "success") {
        $editData = $result['data'];
        $selected_skills = !empty($editData['skills_required_ids'])
            ? array_map('trim', explode(',', $editData['skills_required_ids']))
            : [];
        $selected_languages = !empty($editData['languages_required_ids'])
            ? array_map('trim', explode(',', $editData['languages_required_ids']))
            : [];
        $selected_equipment = !empty($editData['work_equipment_ids'])
            ? array_map('trim', explode(',', $editData['work_equipment_ids']))
            : [];
    }
}























$company_name = "";
$interview_address = "";
$contact_person = "";
$contact_mobile = "";
$api_error = "";



/* ====================1. Get Logged in user data========================== */

$post_user_data1 = json_encode([
    "user_id" => $userid
]);
$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => API_BASE_URL . "getRecuriterdetails.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_POST => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POSTFIELDS => $post_user_data1,
));

$loggedinUserResponse = curl_exec($curl);

if (curl_errno($curl)) {
    $api_error = "Unable to connect to recruiter service.";
}

curl_close($curl);

if (!$api_error && $loggedinUserResponse) {
    $result = json_decode($loggedinUserResponse, true);

    if (isset($result['status']) && $result['status'] == "success") {

        $company_name = $result['data']['organization_name'] ?? "";
        $interview_address = $result['data']['address'] ?? "";
        $contact_person = $result['data']['contact_person_name'] ?? "";
        $contact_mobile = $result['data']['contact_no'] ?? "";
    } else {
        $api_error = "Recruiter details not found.";
    }
}





/* ====================2. Get Job positions for dropdown========================== */

$job_positions = [];

$post_user_data2 = json_encode([
    "user_id" => $userid
]);

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => API_BASE_URL . "getPosition.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post_user_data2,
));

$getJobPositions = curl_exec($curl);

if (curl_errno($curl)) {
    $api_error = "Unable to fetch job positions.";
}

curl_close($curl);

if (!$api_error && $getJobPositions) {
    $result = json_decode($getJobPositions, true);
    if (isset($result['status']) && $result['status'] == "success") {
        $job_positions = $result['data']['position'];    // store positions
    }
}


/* ====================3. Get Job Types========================== */

$job_types = [];
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => API_BASE_URL . "getJobtype.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
));
$getJobTypes = curl_exec($curl);

if (curl_errno($curl)) {
    $api_error = "Unable to fetch job types.";
}
curl_close($curl);

if (!$api_error && $getJobTypes) {
    $result = json_decode($getJobTypes, true);

    if (isset($result['status']) && $result['status'] == "success") {
        $job_types = $result['job_types'];   // store job types
    } else {
        $api_error = "Job types not found.";
    }
}




/* ====================4. Get Work Models========================== */

$work_models = [];

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => API_BASE_URL . "getWorkmodel.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
));

$getWorkModels = curl_exec($curl);

if (curl_errno($curl)) {
    $api_error = "Unable to fetch work models.";
}

curl_close($curl);

if (!$api_error && $getWorkModels) {

    $result = json_decode($getWorkModels, true);

    if (isset($result['status']) && $result['status'] == "success") {
        $work_models = $result['data'];   // store work models
    } else {
        $api_error = "Work models not found.";
    }
}










/* ====================5. Get Degrees (Qualifications)========================== */

$degrees = [];

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => API_BASE_URL . "getDegrees.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
));

$getDegrees = curl_exec($curl);

if (curl_errno($curl)) {
    $api_error = "Unable to fetch degrees.";
}

curl_close($curl);

if (!$api_error && $getDegrees) {

    $result = json_decode($getDegrees, true);

    if (isset($result['status']) && $result['status'] == "success") {
        $degrees = $result['data']['degree'];   // store degrees
    } else {
        $api_error = "Degrees not found.";
    }
}






/* ====================6. Get Salary Range========================== */

$salary_ranges = [];

$post_salary = json_encode([
    "status" => 1
]);

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => API_BASE_URL . "getSalaryrange.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post_salary,
));

$getSalaryRanges = curl_exec($curl);

if (curl_errno($curl)) {
    $api_error = "Unable to fetch salary ranges.";
}

curl_close($curl);

if (!$api_error && $getSalaryRanges) {

    $result = json_decode($getSalaryRanges, true);

    if (isset($result['status']) && $result['status'] == "success") {
        $salary_ranges = $result['data']; // store salary ranges
    } else {
        $api_error = "Salary ranges not found.";
    }
}




/* ====================7. Get Experience List========================== */

$experience_list = [];
$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => API_BASE_URL . "getExperience_list.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
));

$getExperience = curl_exec($curl);
if (curl_errno($curl)) {
    $api_error = "Unable to fetch experience list.";
}

curl_close($curl);
if (!$api_error && $getExperience) {
    $result = json_decode($getExperience, true);
    if (isset($result['status']) && $result['status'] == "success") {
        $experience_list = $result['data']; // store experience list
    } else {
        $api_error = "Experience list not found.";
    }
}








/* ====================8. Get Skills List========================== */

$skills = [];
$position_post = json_encode([
    "position" => 9
]);
$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => API_BASE_URL . "getMskill_list.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POSTFIELDS => $position_post,

));

$getSkills = curl_exec($curl);

if (curl_errno($curl)) {
    $api_error = "Unable to fetch skills.";
}

curl_close($curl);

if (!$api_error && $getSkills) {

    $result = json_decode($getSkills, true);

    if (isset($result['status']) && $result['status'] == "success") {
        $skills = $result['data'];
    } else {
        $api_error = "Skills not found.";
    }
}




/* ====================  9. Get Languages List========================== */

$languages = [];

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => API_BASE_URL . "getMLanguage_list.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POST => true
));

$languageResponse = curl_exec($curl);

if (curl_errno($curl)) {
    $api_error = "Unable to fetch languages.";
}

curl_close($curl);

if (!$api_error && $languageResponse) {

    $result = json_decode($languageResponse, true);

    if (isset($result['status']) && $result['status'] == "success") {
        $languages = $result['data'];
    }
}


/* ====================10. Get Job positions for dropdown========================== */

$work_equip = [];
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => API_BASE_URL . "getWorkequipments.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POST => true,
));

$work_equipments = curl_exec($curl);

if (curl_errno($curl)) {
    $api_error = "Unable to fetch job positions.";
}

curl_close($curl);

if (!$api_error && $work_equipments) {
    $result = json_decode($work_equipments, true);
    if (isset($result['status']) && $result['status'] == "success") {
        $work_equip = $result['data'];    // store equipment name
    }
}


/* ====================11. Get Work Shifts========================== */

$work_shifts = [];

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => API_BASE_URL . "getWorkshift.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
));

$getWorkShifts = curl_exec($curl);

if (curl_errno($curl)) {
    $api_error = "Unable to fetch work models.";
}

curl_close($curl);

if (!$api_error && $getWorkShifts) {

    $result = json_decode($getWorkShifts, true);

    if (isset($result['status']) && $result['status'] == "success") {
        $work_shifts = $result['data'];   // store work shifts
    } else {
        $api_error = "Work models not found.";
    }
}

/* ====================12. Get Gender List========================== */

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


/* ==================== 13. SUBMIT ALL DATA ========================== */

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_submitted'])) {

    $postData = [
        "recruiter_id" => $profile_id,
        "company_name" => $company_name,
        "job_position_id" => $_POST['job_position'] ?? "",
        "state" => $_POST['state'] ?? "",
        "country" => $_POST['country'] ?? "",
        "city_id" => $_POST['city'] ?? "",
        "locality_id" => $_POST['locality'] ?? "",
        "number_of_openings" => $_POST['number_of_openings'] ?? "",
        "work_model" => !empty($_POST['work_model']) ? $_POST['work_model'] : ($editData['work_model_id'] ?? ""),
        "work_shift" => !empty($_POST['work_shift']) ? $_POST['work_shift'] : ($editData['work_shift_id'] ?? ""),
        "job_type" => !empty($_POST['job_type']) ? $_POST['job_type'] : ($editData['job_type_id'] ?? ""),
        "gender" => !empty($_POST['gender']) ? $_POST['gender'] : ($editData['gender_id'] ?? ""),
        "field_work" => !empty($_POST['fieldwork']) ? $_POST['fieldwork'] : ($editData['field_work_id'] ?? ""),
        // "job_type" => $_POST['job_type'] ?? "",
        // "work_model" => $_POST['work_model'] ?? "",
        // "field_work" => $_POST['fieldwork'] ?? "",
        // "work_shift" => $_POST['work_shift'] ?? "",
        // "gender" => $_POST['gender'] ?? "",
        "qualification" => $_POST['qualification'] ?? "",
        "experience_from" => $_POST['experience_from'] ?? "",
        "experience_to" => $_POST['experience_to'] ?? "",
        "salary_from" => $_POST['salary_from'] ?? "",
        "salary_to" => $_POST['salary_to'] ?? "",
        "job_description" => $_POST['job_description'] ?? "",
        "skills_required" => $_POST['skills_required'] ?? [],
        "languages_required" => $_POST['languages_required'] ?? [],
        "work_equipment" => $_POST['work_equipment'] ?? [],
        "contact_person_name" => $_POST['contact_person_name'] ?? "",
        "contact_no" => $_POST['contact_no'] ?? "",
        "interview_address" => $_POST['interview_address'] ?? "",
        "validity_apply" => $_POST['validity_apply'] ?? 0,
        "valid_till_date" => $_POST['valid_till_date'] ?? "",
        "job_status_id" => 1,
        "lat" => $_POST['lat'] ?? "",
        "lon" => $_POST['lon'] ?? "",
    ];


    // ✅ ADD ID BEFORE ENCODE
    if ($is_edit && !empty($job_id)) {
        $postData['id'] = (int)$job_id;
    }

    // NOW encode
    $jsonData = json_encode($postData);
    // $api_url = API_BASE_URL . "addWalkininterview.php";
    $api_url = "https://pacificconnect2.0.inv51.in/webservices/addWalkininterview.php";



    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL =>  $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($curl);
    curl_close($curl);


    $apiResult = json_decode($response, true);

    if ($apiResult['status'] == 1) {
        // 🔥 ADD THIS
        $_SESSION['last_job_id'] = $apiResult['data']['id'] ?? $job_id;
        $_SESSION['success_message'] = $apiResult['message'];
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
    <title>Post Premium Job | Pacific iConnect</title>
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
            padding: 20px;
        }

        .form-card {
            background: var(--white);
            width: 100%;
            max-width: 1200px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            padding: 20px 40px 30px;
        }

        .desktop-page-title {
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 20px;
            color: var(--text-dark);
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Premium specific title styling */
        .desktop-page-title .premium-star {
            color: #fbbf24;
        }

        /* Grid Layout for Desktop (Highly Compact) */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px 30px;
            align-items: start;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .form-label {
            display: block;
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-col {
            flex: 1;
        }

        .form-control {
            width: 100%;
            padding: 9px 12px;
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
            min-height: 80px;
            font-family: inherit;
            line-height: 1.5;
        }

        /* Special Map Input */
        .map-input-container {
            position: relative;
            cursor: pointer;
        }

        .map-input-container .form-control {
            padding-right: 40px;
            cursor: pointer;
            background-color: #f8fafc;
        }

        .map-input-container .map-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--danger-red);
            font-size: 1.2rem;
            pointer-events: none;
        }

        /* Toggle Buttons */
        .toggle-container {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-toggle {
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
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
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .btn-submit {
            width: auto;
            min-width: 250px;
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

        .modal-map-card {
            background: var(--white);
            width: 100%;
            max-width: 600px;
            height: 90vh;
            max-height: 800px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: translateY(20px);
            transition: transform 0.3s ease;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-full-overlay.active .modal-map-card {
            transform: translateY(0);
        }

        .map-header {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-light);
        }

        .map-header-title {
            font-size: 1.2rem;
            font-weight: 800;
            flex: 1;
            text-align: center;
        }

        .map-close-btn {
            background: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: var(--text-dark);
        }

        .map-search-bar {
            padding: 15px;
            position: relative;
            z-index: 10;
            background: var(--white);
        }

        .map-search-input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border-radius: 30px;
            border: 1px solid var(--border-light);
            font-size: 1rem;
            outline: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .map-search-icon {
            position: absolute;
            left: 30px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        /* Search Dropdown */
        .search-suggestions {
            position: absolute;
            top: calc(100% - 5px);
            left: 15px;
            right: 15px;
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            display: none;
            flex-direction: column;
            z-index: 20;
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .search-suggestions.active {
            display: flex;
        }

        .suggestion-item {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: background 0.2s;
        }

        .suggestion-item:hover {
            background: #f8fafc;
        }

        .suggestion-icon {
            color: var(--text-muted);
            font-size: 1.2rem;
        }

        .suggestion-text h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 2px;
        }

        .suggestion-text p {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Simulated Map Area */
        .map-view-area {
            flex: 1;
            position: relative;
            background: #e2e8f0;
            /* Simulated map pattern using CSS grid */
            background-image:
                linear-gradient(to right, #cbd5e1 1px, transparent 1px),
                linear-gradient(to bottom, #cbd5e1 1px, transparent 1px);
            background-size: 40px 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .map-center-pin {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .map-pin-bubble {
            background: rgba(239, 68, 68, 0.2);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px solid rgba(239, 68, 68, 0.4);
        }

        .map-pin-icon {
            color: var(--danger-red);
            font-size: 2.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .map-info-tooltip {
            position: absolute;
            top: -60px;
            background: #1a1a1a;
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            text-align: center;
            white-space: nowrap;
        }

        .map-info-tooltip h4 {
            font-size: 0.9rem;
            margin-bottom: 2px;
        }

        .map-info-tooltip p {
            font-size: 0.75rem;
            color: #ccc;
        }

        .map-info-tooltip::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 50%;
            transform: translateX(-50%);
            border-width: 6px 6px 0;
            border-style: solid;
            border-color: #1a1a1a transparent transparent transparent;
        }

        .map-controls {
            position: absolute;
            right: 15px;
            bottom: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }

        .map-ctrl-btn {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: center;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .map-ctrl-btn:last-child {
            border-bottom: none;
        }

        /* Map Footer */
        .map-footer {
            padding: 20px;
            background: var(--white);
            border-top: 1px solid var(--border-light);
        }

        .selected-address-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 5px;
        }

        .selected-address-text {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .btn-confirm-loc {
            width: 100%;
            background: var(--blue-btn);
            color: var(--white);
            padding: 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1.05rem;
            transition: 0.2s;
        }

        .btn-confirm-loc:hover {
            background: var(--blue-hover);
        }

        /* --- 4. MOBILE NAV & RESPONSIVE --- */
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
                background: var(--white);
            }

            .main-wrapper {
                padding: 15px 10px;
            }

            .desktop-page-title {
                display: none;
            }

            .form-card {
                padding: 0;
                border: none;
                box-shadow: none;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .btn-submit {
                width: 100%;
            }

            .modal-map-card {
                height: 100vh;
                max-height: 100vh;
                border-radius: 0;
            }
        }

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









        /* ================= MAP MODAL CLEAN CSS ================= */

        /* Overlay */
        #mapModal.modal-full-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: 0.3s ease;
        }

        #mapModal.modal-full-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Card */
        #mapModal .modal-map-card {
            width: 100%;
            max-width: 650px;
            height: 90vh;
            background: #fff;
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            overflow: visible;
            /* âœ… allow dropdown to float */
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
            transform: translateY(20px);
            transition: 0.3s ease;
        }

        #mapModal.active .modal-map-card {
            transform: translateY(0);
        }

        /* Header */
        #mapModal .map-header {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            border-bottom: 1px solid #e5e7eb;
            background: #fff;
        }

        #mapModal .map-header-title {
            flex: 1;
            text-align: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        #mapModal .map-close-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
        }

        /* Search bar */
        #mapModal .map-search-bar {
            position: relative;
            padding: 14px;
            background: #fff;
            z-index: 1000;
        }

        #mapModal .map-search-input {
            width: 100%;
            padding: 12px 14px 12px 38px;
            border-radius: 30px;
            border: 1px solid #cbd5e1;
            outline: none;
            font-size: 0.95rem;
        }

        #mapModal .map-search-icon {
            position: absolute;
            left: 26px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }

        /* ðŸ”¥ Dropdown FIX */
        #mapModal .search-results {
            position: absolute;
            top: calc(100% - 8px);
            left: 15px;
            right: 15px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.14);
            max-height: 260px;
            overflow-y: auto;
            display: none;
            z-index: 9999;
            /* high enough */
        }

        #mapModal .search-results.active {
            display: block;
        }

        /* Scrollbar */
        #mapModal .search-results::-webkit-scrollbar {
            width: 6px;
        }

        #mapModal .search-results::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        /* Items */
        #mapModal .search-result-item {
            padding: 12px 14px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
            transition: 0.2s;
        }

        #mapModal .search-result-item:hover {
            background: #f8fafc;
        }

        #mapModal .search-result-item:last-child {
            border-bottom: none;
        }

        /* Map */
        #mapModal .map-view-area {
            position: relative;
            z-index: 1;
        }

        #mapModal #googleMap {
            width: 100%;
            height: 100%;
        }

        /* Footer */
        #mapModal .map-footer {
            padding: 16px;
            border-top: 1px solid #e5e7eb;
            background: #fff;
        }

        #mapModal .selected-address-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
        }

        #mapModal .selected-address-text {
            font-size: 0.9rem;
            font-weight: 700;
            margin: 6px 0;
        }

        #mapModal .selected-coords {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 10px;
        }

        #mapModal .btn-confirm-loc {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            background: #2563eb;
            color: #fff;
            font-weight: 700;
            border: none;
        }

        #mapModal .btn-confirm-loc:hover {
            background: #1d4ed8;
        }

        /* Mobile */
        @media (max-width: 768px) {
            #mapModal .modal-map-card {
                height: 100vh;
                border-radius: 0;
            }
        }

        .search-result-item {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .search-result-icon {
            color: #e53935;
            margin-top: 2px;
        }

        .search-result-main {
            font-weight: 700;
        }

        .search-result-sub {
            font-size: 0.8rem;
            color: #64748b;
        }

        .req {
            color: red;
            margin-left: 3px;
        }

        .error {
            border: 1px solid red !important;
        }

        .custom-dropdown {
            position: relative;
        }

        .dropdown-box {
            border: 1px solid #cbd5e1;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            background: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dropdown-list {
            display: none;
            position: absolute;
            width: 100%;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 999;
            margin-top: 5px;
        }

        .dropdown-search {
            width: 100%;
            padding: 8px;
            border: none;
            border-bottom: 1px solid #eee;
            outline: none;
        }

        .dropdown-item {
            padding: 10px;
            cursor: pointer;
        }

        .dropdown-item:hover {
            background: #f1f5ff;
        }

        .dropdown-item.active {
            background: #e0e7ff;
            font-weight: bold;
        }
    </style>
</head>

<body>


    <?php if (empty($_SESSION['success_message'])) {
        include "includes/preloader.php";
    } ?>
    <?php include "includes/header.php";
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
                        onclick="goBackPage()">
                        Close
                    </button>
                    <form action="premium-job-details.php" method="POST">
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
        <?php unset($_SESSION['prev_page']); ?>
    <?php endif; ?>


    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="modal-full-overlay active" id="errorModal">
            <div class="modal-map-card" style="max-width:400px;height:auto;padding:30px;text-align:center;">
                <h3 style="margin-bottom:15px;color:#e53935;">Error</h3>
                <p><?php echo $_SESSION['error_message']; ?></p>

                <button onclick="goBackPage()"
                    style="margin-top:20px;padding:10px 20px;background:#2563eb;color:white;border-radius:6px;border:none;">
                    OK
                </button>
            </div>
        </div>
        <?php unset($_SESSION['error_message']); ?>
        <?php unset($_SESSION['prev_page']); ?>
    <?php endif; ?>

    <!-- Error for all api message -->
    <?php if (!empty($api_error)): ?>
        <div class="modal-full-overlay active" id="apiErrorModal">
            <div class="modal-map-card" style="max-width:400px;height:auto;padding:30px;text-align:center;">
                <h3 style="margin-bottom:15px;color:#e53935;">API Error</h3>
                <p><?php echo $api_error; ?></p>
                <button onclick="document.getElementById('apiErrorModal').style.display='none'"
                    style="margin-top:20px;padding:10px 20px;background:#2563eb;color:white;border-radius:6px;border:none;">
                    OK
                </button>
            </div>
        </div>
    <?php endif; ?>


    <div class="mobile-header">
        <i class="fas fa-arrow-left mobile-back"></i>
        <span class="mobile-header-title">Post Premium Job</span>
    </div>

    <main class="main-wrapper">
        <div class="form-card">
            <h1 class="desktop-page-title"><i class="fas fa-star premium-star"></i> Post Premium Job</h1>

            <form method="POST" class="job-form">
                <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                <input type="hidden" name="form_submitted" value="1">
                <input type="hidden" name="from_page"
                    value="<?php echo $_SESSION['prev_page'] ?? ''; ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Company Name<span class="req">*</span></label>
                        <input type="text" class="form-control" name="company_name"
                            value="<?php echo htmlspecialchars($is_edit ? $editData['company_name'] : $company_name); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Job Position<span class="req">*</span></label>
                        <div class="custom-dropdown">
                            <!-- ✅ VALUE AUTO SET (EDIT MODE) -->
                            <input type="hidden" name="job_position" id="jobPositionValue"
                                value="<?php echo ($is_edit) ? $editData['job_position_id'] : ''; ?>">
                            <!-- ✅ TEXT AUTO SET (EDIT MODE) -->
                            <div class="dropdown-box" onclick="toggleJobDropdown()">
                                <span id="selectedJobText">
                                    <?php
                                    if ($is_edit) {
                                        foreach ($job_positions as $position) {
                                            if ($editData['job_position_id'] == $position['id']) {
                                                echo htmlspecialchars($position['name']);
                                                break;
                                            }
                                        }
                                    } else {
                                        echo "Select Job Position";
                                    }
                                    ?>
                                </span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="dropdown-list" id="jobDropdown">
                                <!-- 🔍 SEARCH -->
                                <input type="text" id="jobSearch" placeholder="Search job..." onkeyup="filterJobs()" class="dropdown-search">
                                <!-- OPTIONS -->
                                <?php foreach ($job_positions as $position) { ?>
                                    <div class="dropdown-item
                <?php echo ($is_edit && $editData['job_position_id'] == $position['id']) ? 'active' : ''; ?>"

                                        data-id="<?php echo $position['id']; ?>"
                                        data-name="<?php echo strtolower($position['name']); ?>"
                                        onclick="selectJob(this)">
                                        <?php echo htmlspecialchars($position['name']); ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">District / Tehsil / City<span class="req">*</span></label>
                        <input type="text" name="city" id="cityInput" class="form-control" value="<?php echo $is_edit ? $editData['city'] : ''; ?>" autocomplete="off">
                        <!-- hidden fields -->
                        <input type="hidden" id="stateInput" name="state">
                        <input type="hidden" id="countryInput" name="country">
                        <div id="citySuggestions" class="suggestion-box"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Area / Locality / Village<span class="req">*</span></label>
                        <input type="text" id="localityInput" name="locality" class="form-control" value="<?php echo $is_edit ? $editData['locality'] : ''; ?>" autocomplete="off">
                        <div id="localitySuggestions" class="suggestion-box"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Number of Openings<span class="req">*</span></label>
                        <input type="text" name="number_of_openings" class="form-control" value="<?php echo $is_edit ? $editData['number_of_openings'] : ''; ?>" placeholder="Eg :- 3, 5, 10">
                    </div>

                    <div class="form-group" style="display: flex; flex-direction: column; gap: 15px;">
                        <div>
                            <label class="form-label">Does this Job require Fieldwork?<span class="req">*</span></label>
                            <div class="toggle-container" id="fieldworkToggle">
                                <input type="hidden" name="fieldwork" id="fieldworkInput"
                                    value="<?php echo $is_edit ? $editData['field_work_id'] : ''; ?>">
                                <button
                                    type="button"
                                    class="btn-toggle"
                                    data-value="1"
                                    onclick="toggleGroup(this, 'fieldworkToggle')">
                                    Yes
                                </button>

                                <button
                                    type="button"
                                    class="btn-toggle"
                                    data-value="0"
                                    onclick="toggleGroup(this, 'fieldworkToggle')">
                                    No
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Job Type<span class="req">*</span></label>
                        <div class="toggle-container" id="jobTypeToggle">
                            <input type="hidden" name="job_type" id="jobTypeInput"
                                value="<?php echo $is_edit ? $editData['job_type_id'] : ''; ?>">
                            <?php if (!empty($job_types)) { ?>
                                <?php foreach ($job_types as $index => $type) { ?>
                                    <button
                                        type="button"
                                        class="btn-toggle"
                                        onclick="toggleGroup(this, 'jobTypeToggle')"
                                        data-id="<?php echo $type['id']; ?>">
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </button>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="form-group">
                            <label class="form-label">Work Model<span class="req">*</span></label>
                            <div class="toggle-container" id="workModelToggle">
                                <?php if (!empty($work_models)) { ?>
                                    <?php foreach ($work_models as $index => $model) { ?>
                                        <button
                                            type="button"
                                            class="btn-toggle"
                                            data-id="<?php echo $model['id']; ?>"
                                            onclick="toggleGroup(this,'workModelToggle')">
                                            <?php echo htmlspecialchars($model['name']); ?>
                                        </button>
                                    <?php } ?>
                                <?php } ?>

                            </div>

                            <input type="hidden" name="work_model" id="workModelInput"
                                value="<?php echo $is_edit ? $editData['work_model_id'] : ''; ?>">

                        </div>
                    </div>



                    <div class="form-group">
                        <div class="form-group">
                            <label class="form-label">Work Shift<span class="req">*</span></label>
                            <div class="toggle-container" id="workShiftToggle">
                                <?php if (!empty($work_shifts)) { ?>
                                    <?php foreach ($work_shifts as $index => $shift) { ?>
                                        <button
                                            type="button"
                                            class="btn-toggle"
                                            data-id="<?php echo $shift['id']; ?>"
                                            onclick="toggleGroup(this,'workShiftToggle')">
                                            <?php echo htmlspecialchars($shift['shift_name']); ?>
                                        </button>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                            <input type="hidden" name="work_shift" id="workShiftInput"
                                value="<?php echo $is_edit ? $editData['work_shift_id'] : ''; ?>">
                        </div>
                    </div>




                    <div class="form-group">
                        <label class="form-label">Gender<span class="req">*</span></label>

                        <div class="toggle-container" id="genderToggle">

                            <input type="hidden" name="gender" id="genderInput"
                                value="<?php echo $is_edit ? $editData['gender_id'] : ''; ?>">

                            <?php if (!empty($genders)) { ?>
                                <?php foreach ($genders as $gender) { ?>

                                    <button
                                        type="button"
                                        class="btn-toggle"
                                        data-id="<?php echo $gender['id']; ?>"
                                        onclick="toggleGroup(this,'genderToggle')">

                                        <?php echo htmlspecialchars($gender['name']); ?>

                                    </button>

                                <?php } ?>
                            <?php } ?>

                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Basic Qualification<span class="req">*</span></label>
                        <select class="form-control" name="qualification">
                            <option value="">Choose Basic Qualification</option>
                            <?php foreach ($degrees as $degree) {
                                $selected = ($is_edit && $editData['qualification_id'] == $degree['id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $degree['id']; ?>" <?= $selected; ?>>
                                    <?= htmlspecialchars($degree['name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Experience<span class="req">*</span></label>
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
                        <label class="form-label">Monthly Salary Range<span class="req">*</span></label>

                        <div class="form-row">

                            <div class="form-col">
                                <select class="form-control" name="salary_from">
                                    <option value="">From </option>
                                    <?php if (!empty($salary_ranges)) { ?>
                                        <?php foreach ($salary_ranges as $salary) { ?>
                                            <option value="<?php echo $salary['id']; ?>"
                                                <?php echo ($is_edit && $editData['salary_from_id'] == $salary['id']) ? 'selected' : ''; ?>>

                                                <?php echo number_format($salary['name']); ?>
                                            </option>
                                        <?php } ?>
                                    <?php } ?>
                                </select>
                            </div>


                            <div class="form-col">
                                <select class="form-control" name="salary_to">
                                    <option value="">To </option>
                                    <?php if (!empty($salary_ranges)) { ?>
                                        <?php foreach ($salary_ranges as $salary) { ?>
                                            <option value="<?php echo $salary['id']; ?>"
                                                <?php echo ($is_edit && $editData['salary_to_id'] == $salary['id']) ? 'selected' : ''; ?>>
                                                <?php echo number_format($salary['name']); ?>
                                            </option>
                                        <?php } ?>
                                    <?php } ?>
                                </select>
                            </div>

                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Skills & Languages<span class="req">*</span></label>
                        <div class="form-row">


                            <div class="form-col">
                                <div class="multi-select">
                                    <div class="select-box" id="skillSelectBox" onclick="toggleSkillDropdown()">
                                        Select Skills
                                    </div>
                                    <div class="checkbox-container" id="skillDropdown">
                                        <?php if (!empty($skills)) { ?>
                                            <?php foreach ($skills as $skill) { ?>
                                                <label class="checkbox-item">
                                                    <input type="checkbox"
                                                        name="skills_required[]"
                                                        value="<?php echo $skill['id']; ?>"
                                                        <?php echo ($is_edit && in_array($skill['id'], $selected_skills)) ? 'checked' : ''; ?>>
                                                    <?php echo htmlspecialchars($skill['title']); ?>
                                                </label>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>




                            <div class="form-col">
                                <div class="multi-select">
                                    <div class="select-box" id="languageSelectBox" onclick="toggleLanguageDropdown()">
                                        Select Languages
                                    </div>
                                    <div class="checkbox-container" id="languageDropdown">
                                        <?php if (!empty($languages)) { ?>
                                            <?php foreach ($languages as $language) { ?>
                                                <label class="checkbox-item">
                                                    <input type="checkbox"
                                                        name="languages_required[]"
                                                        value="<?php echo $language['id']; ?>"
                                                        <?php echo ($is_edit && in_array($language['id'], $selected_languages)) ? 'checked' : ''; ?>>
                                                    <?php echo htmlspecialchars($language['name']); ?>
                                                </label>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>







                        </div>
                    </div>


                    <div class="form-group">
                        <label class="form-label">Work Equipment Needed<span class="req">*</span></label>

                        <div class="multi-select">

                            <div class="select-box" id="equipmentSelectBox" onclick="toggleEquipmentDropdown()">
                                Select Work Equipment
                            </div>

                            <div class="checkbox-container" id="equipmentDropdown">

                                <?php if (!empty($work_equip)) { ?>
                                    <?php foreach ($work_equip as $equipments) { ?>

                                        <label class="checkbox-item">
                                            <input type="checkbox"
                                                name="work_equipment[]"
                                                value="<?php echo $equipments['id']; ?>"
                                                <?php echo ($is_edit && in_array($equipments['id'], $selected_equipment)) ? 'checked' : ''; ?>>
                                            <?php echo htmlspecialchars($equipments['name']); ?>
                                        </label>

                                    <?php } ?>
                                <?php } ?>

                            </div>

                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; height: 100%;">
                        <label class="form-label">Job Description : (Optional)</label>
                        <textarea class="form-control" name="job_description"><?= trim($editData['job_description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Details<span class="req">*</span></label>
                        <div class="form-row">
                            <div class="form-col"><input type="text" class="form-control"
                                    value="<?php echo htmlspecialchars($is_edit ? $editData['contact_person_name'] : $contact_person); ?>"
                                    placeholder="Contact Person Name" name="contact_person_name"></div>
                            <div class="form-col"><input type="tel" class="form-control" name="contact_no" value="<?php echo htmlspecialchars($is_edit ? $editData['contact_no'] : $contact_mobile); ?>" placeholder="Mobile No"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Interview Location<span class="req">*</span></label>
                        <div class="map-input-container" onclick="openMapModal()">
                            <input type="text" class="form-control" id="mainLocationInput" value="<?php echo $is_edit ? 'Lat=' . $editData['lat'] . ', Lng=' . $editData['lon'] : ''; ?>" name="interview_location" placeholder="Choose map location" readonly>
                            <i class="fas fa-map-marker-alt map-icon"></i>
                            <input type="hidden" name="lat" value="<?php echo $is_edit ? $editData['lat'] : ''; ?>" id="interview_lat">
                            <input type="hidden" name="lon" value="<?php echo $is_edit ? $editData['lon'] : ''; ?>" id="interview_lng">
                            <input type="hidden" name="interview_full_address" id="interview_full_address">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Interview Address<span class="req">*</span></label>
                        <textarea class="form-control" id="mainAddressTextarea" name="interview_address" placeholder="Address will auto-fill based on map location..."><?php echo $is_edit ? $editData['interview_address'] : ''; ?></textarea>
                    </div>

                    <!-- <div class="form-group full-width" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;"> -->
                    <div>
                        <label class="form-label">Does this Job have a deadline?<span class="req">*</span></label>

                        <div class="toggle-container" id="deadlineToggle">
                            <button type="button" class="btn-toggle" onclick="handleDeadlineToggle(true, this)">Yes</button>

                            <button type="button" class="btn-toggle" onclick="handleDeadlineToggle(false, this)">No</button>
                        </div>
                        <div class="deadline-date-wrapper" id="deadlineDateGroup">
                            <input value="<?php echo ($is_edit && $editData['valid_till_date'] != '0000-00-00') ? $editData['valid_till_date'] : ''; ?>" type="text" placeholder="DD-MM-YYYY" name="valid_till_date" class="form-control datepicker" placeholder="Select Date">
                            <input type="hidden" name="validity_apply" id="validityApplyInput" value="<?php echo $is_edit ? $editData['validity_apply_id'] : ''; ?>">
                        </div>
                    </div>

                    <!-- <div style="display:flex; flex-direction:column; height: 100%;">
                        <label class="form-label">Job Description : (Optional)<span class="req">*</span></label>
                        <textarea class="form-control" name="job_description" placeholder="Enter Job Description" style="flex:1; min-height: 80px;"></textarea>
                    </div> -->
                    <!-- </div> -->
                </div>

                <div class="submit-container">
                    <button type="submit" class="btn-submit">Submit</button>
                </div>

            </form>
        </div>
    </main>

    <div class="bottom-nav">
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-home"></i></div>Home
        </a>
        <a href="#" class="nav-icon active">
            <div class="icon-wrap"><i class="fas fa-plus-square"></i></div>Post Jobs
        </a>
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-file-alt"></i></div>Applications
        </a>
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-user"></i></div>Profile
        </a>
    </div>

    <div class="modal-full-overlay" id="mapModal">
        <div class="modal-map-card">

            <div class="map-header">
                <button class="map-close-btn" onclick="closeMapModal()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="map-header-title">Confirm Interview Location</div>
                <div style="width:40px;"></div>
            </div>

            <div class="map-search-bar">
                <i class="fas fa-search map-search-icon"></i>
                <input type="text" class="map-search-input" id="mapSearchInput"
                    placeholder="Search area, locality, city..." autocomplete="off">
                <div id="searchResults" class="search-results"></div>
            </div>

            <div class="map-view-area">
                <div id="googleMap" style="width:100%; height:100%;"></div>
            </div>

            <div class="map-footer">
                <div class="selected-address-label">Selected Address:</div>
                <div class="selected-address-text" id="modalAddressText">Move marker or search</div>
                <div class="selected-coords" id="modalCoordsText">Lat: -, Lng: -</div>

                <button class="btn-confirm-loc" onclick="confirmLocation()">
                    Confirm Location
                </button>
            </div>

        </div>
    </div>
    <?php include "includes/bottom-bar.php"; ?>


    <script>
        function toggleJobDropdown() {
            document.getElementById("jobDropdown").style.display = "block";
        }

        function selectJob(el) {
            let id = el.getAttribute("data-id");
            let text = el.innerText;

            document.getElementById("jobPositionValue").value = id;
            document.getElementById("selectedJobText").innerText = text;

            document.getElementById("jobDropdown").style.display = "none";
        }

        function filterJobs() {
            let input = document.getElementById("jobSearch").value.toLowerCase();
            let items = document.querySelectorAll("#jobDropdown .dropdown-item");

            items.forEach(item => {
                let name = item.getAttribute("data-name");

                if (name.includes(input)) {
                    item.style.display = "block";
                } else {
                    item.style.display = "none";
                }
            });
        }

        // click outside close
        document.addEventListener("click", function(e) {
            if (!e.target.closest(".custom-dropdown")) {
                document.getElementById("jobDropdown").style.display = "none";
            }
        });

        document.querySelector(".job-form").addEventListener("submit", function(e) {

            function showError(field, message) {
                e.preventDefault();

                if (field) {
                    field.classList.add("error");
                    field.focus();
                }

                alert(message);
                throw "stop"; // stop execution
            }

            function checkField(name, message) {
                let field = document.querySelector(`[name="${name}"]`);
                if (!field || field.value.trim() === "") {
                    showError(field, message);
                }
            }

            try {

                // ✅ BASIC REQUIRED
                checkField("job_position", "Job Position is required");
                checkField("city", "City is required");
                checkField("locality", "Locality is required");
                checkField("number_of_openings", "No. of opening is required");

                // ✅ FIELDWORK
                let fieldwork = document.getElementById("fieldworkInput").value;
                if (fieldwork === "") {
                    // document.getElementById("fieldworkToggle").style.border = "1px solid red";
                    showError(null, "Fieldwork selection is required");
                }

                // ✅ JOB TYPE
                let jobType = document.getElementById("jobTypeInput").value;
                if (jobType === "") {
                    // document.getElementById("jobTypeToggle").style.border = "1px solid red";
                    showError(null, "Job Type is required");
                }

                // ✅ WORK MODEL
                let workModel = document.getElementById("workModelInput").value;
                if (workModel === "") {
                    // document.getElementById("workModelToggle").style.border = "1px solid red";
                    showError(null, "Work Model is required");
                }

                // ✅ WORK SHIFT
                let workShift = document.getElementById("workShiftInput").value;
                if (workShift === "") {
                    // document.getElementById("workShiftToggle").style.border = "1px solid red";
                    showError(null, "Work Shift is required");
                }

                // ✅ GENDER
                let gender = document.getElementById("genderInput").value;
                if (gender === "") {
                    // document.getElementById("genderToggle").style.border = "1px solid red";
                    showError(null, "Gender is required");
                }

                // ✅ QUALIFICATION
                checkField("qualification", "Qualification is required");

                // ✅ EXPERIENCE
                checkField("experience_from", "Experience From is required");
                checkField("experience_to", "Experience To is required");

                // ✅ SALARY
                checkField("salary_from", "Salary From is required");
                checkField("salary_to", "Salary To is required");

                // Skills + Languages (combined validation)
                const skills = document.querySelectorAll('input[name="skills_required[]"]:checked');
                const languages = document.querySelectorAll('input[name="languages_required[]"]:checked');
                const equipment = document.querySelectorAll('input[name="work_equipment[]"]:checked');

                if (skills.length === 0) {
                    alert("Please select at least one Skill.");
                    e.preventDefault();
                    return false;
                }
                if (languages.length === 0) {
                    alert("Please select at least one Language.");
                    e.preventDefault();
                    return false;
                }

                if (equipment.length === 0) {
                    alert("Please select at least one Work Equipment.");
                    e.preventDefault();
                    return false;
                }

                // ✅ CONTACT
                checkField("contact_person_name", "Contact Person is required");
                checkField("contact_no", "Contact Number is required");

                // ✅ INTERVIEW LOCATION (MAP)
                let lat = document.getElementById("interview_lat").value;
                let lng = document.getElementById("interview_lng").value;

                if (!lat || !lng) {
                    showError(null, "Please select Interview Location from map");
                }

                // ✅ INTERVIEW ADDRESS
                checkField("interview_address", "Interview Address is required");

                // ✅ DEADLINE
                let validity = document.getElementById("validityApplyInput").value;
                let dateField = document.querySelector("[name='valid_till_date']");

                if (validity == "1" && (!dateField.value || dateField.value.trim() === "")) {
                    showError(dateField, "Valid Till Date is required");
                }

            } catch (err) {
                return false;
            }

        });


        document.querySelectorAll(".form-control").forEach(field => {

            field.addEventListener("input", function() {
                this.classList.remove("error");
            });

            field.addEventListener("change", function() {
                this.classList.remove("error");
            });

        });











        // EDIT DATA SCRIPT
        function setActiveToggle(groupId, selectedId) {

            const container = document.getElementById(groupId);
            const buttons = container.querySelectorAll('.btn-toggle');

            buttons.forEach(btn => {

                let btnId = btn.getAttribute('data-id') || btn.getAttribute('data-value');

                if (btnId == selectedId) {
                    btn.classList.add('active');

                    let input = container.querySelector('input[type="hidden"]');
                    if (input) input.value = btnId;

                } else {
                    btn.classList.remove('active');
                }
            });
        }
        <?php if ($is_edit): ?>
            document.addEventListener("DOMContentLoaded", function() {
                setActiveToggle("jobTypeToggle", "<?php echo $editData['job_type_id']; ?>");
                setActiveToggle("workModelToggle", "<?php echo $editData['work_model_id']; ?>");
                setActiveToggle("workShiftToggle", "<?php echo $editData['work_shift_id']; ?>");
                setActiveToggle("genderToggle", "<?php echo $editData['gender_id']; ?>");
                setActiveToggle("fieldworkToggle", "<?php echo $editData['field_work_id']; ?>");
                // 🔥 ADD THESE
                updateSelectedSkills();
                updateSelectedLanguages();
                updateSelectedEquipment();
            });
        <?php endif; ?>
        document.querySelectorAll('#skillDropdown input[type="checkbox"]').forEach(cb => {
            cb.addEventListener("change", updateSelectedSkills);
        });

        document.querySelectorAll('#languageDropdown input[type="checkbox"]').forEach(cb => {
            cb.addEventListener("change", updateSelectedLanguages);
        });

        document.querySelectorAll('#equipmentDropdown input[type="checkbox"]').forEach(cb => {
            cb.addEventListener("change", updateSelectedEquipment);
        });
        <?php if ($is_edit): ?>
            document.addEventListener("DOMContentLoaded", function() {

                let validity = "<?php echo $editData['validity_apply']; ?>";
                let date = "<?php echo $editData['valid_till_date']; ?>";

                let buttons = document.querySelectorAll("#deadlineToggle .btn-toggle");
                let dateGroup = document.getElementById("deadlineDateGroup");

                if (validity === "Yes") {

                    buttons[0].classList.add("active"); // Yes button
                    dateGroup.classList.add("active");

                } else {

                    buttons[1].classList.add("active"); // No button
                    dateGroup.classList.remove("active");

                }

            });
        <?php endif; ?>









        function initCityAutocomplete() {
            initCitySearch(); // city suggestion working
            initMap(); // map working
        }
        window.onload = () => document.getElementById("global-preloader")?.remove();
        // Generic Toggle Logic for button groups
        function toggleGroup(clickedBtn, groupId) {

            const container = document.getElementById(groupId);
            const buttons = container.querySelectorAll('.btn-toggle');

            buttons.forEach(btn => btn.classList.remove('active'));

            clickedBtn.classList.add('active');

            let value = clickedBtn.getAttribute('data-id') || clickedBtn.getAttribute('data-value');

            let input = container.querySelector('input[type="hidden"]');
            if (input) input.value = value;
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


        function closeMapModal() {
            mapModal.classList.remove('active');
        }


        function toggleGroup(clickedBtn, groupId) {

            const container = document.getElementById(groupId);
            const buttons = container.querySelectorAll('.btn-toggle');

            buttons.forEach(btn => btn.classList.remove('active'));

            clickedBtn.classList.add('active');

            // Fieldwork value
            if (groupId === "fieldworkToggle") {
                document.getElementById("fieldworkInput").value =
                    clickedBtn.getAttribute("data-value");
            }

            // Job Type value
            if (groupId === "jobTypeToggle") {
                document.getElementById("jobTypeInput").value =
                    clickedBtn.getAttribute("data-id");
            }

            // Work Model value
            if (groupId === "workModelToggle") {
                document.getElementById("workModelInput").value =
                    clickedBtn.getAttribute("data-id");
            }
            // Work Shift value
            if (groupId === "workShiftToggle") {
                document.getElementById("workShiftInput").value =
                    clickedBtn.getAttribute("data-id");
            }
            // Gender value
            if (groupId === "genderToggle") {
                document.getElementById("genderInput").value =
                    clickedBtn.getAttribute("data-id");
            }
        }


        function toggleSkillDropdown() {
            let dropdown = document.getElementById("skillDropdown");
            if (dropdown.style.display === "block") {
                dropdown.style.display = "none";
            } else {
                dropdown.style.display = "block";
            }
        }

        function toggleLanguageDropdown() {
            let dropdown = document.getElementById("languageDropdown");
            if (dropdown.style.display === "block") {
                dropdown.style.display = "none";
            } else {
                dropdown.style.display = "block";
            }
        }

        function updateSelectedSkills() {
            let checkboxes = document.querySelectorAll('#skillDropdown input[type="checkbox"]:checked');
            let selectBox = document.getElementById("skillSelectBox");
            let selected = [];

            checkboxes.forEach(cb => {
                // selected.push(cb.value);
                selected.push(cb.parentElement.textContent.trim());
            });

            if (selected.length === 0) {
                selectBox.innerText = "Select Skills";
            } else if (selected.length <= 2) {
                selectBox.innerText = selected.join(", ");
            } else {
                selectBox.innerText = selected.length + " Skills Selected";
            }

        }

        function updateSelectedLanguages() {
            let checkboxes = document.querySelectorAll('#languageDropdown input[type="checkbox"]:checked');
            let selectBox = document.getElementById("languageSelectBox");
            let selected = [];

            checkboxes.forEach(cb => {
                selected.push(cb.parentElement.textContent.trim());
                // selected.push(cb.value);
            });

            if (selected.length === 0) {
                selectBox.innerText = "Select Languages";
            } else if (selected.length <= 2) {
                selectBox.innerText = selected.join(", ");
            } else {
                selectBox.innerText = selected.length + " Languages Selected";
            }

        }

        // Equipments
        function toggleEquipmentDropdown() {

            let dropdown = document.getElementById("equipmentDropdown");

            if (dropdown.style.display === "block") {
                dropdown.style.display = "none";
            } else {
                dropdown.style.display = "block";
            }

        }

        function updateSelectedEquipment() {

            let checkboxes = document.querySelectorAll('#equipmentDropdown input[type="checkbox"]:checked');
            let selectBox = document.getElementById("equipmentSelectBox");

            let selected = [];

            checkboxes.forEach(cb => {
                // selected.push(cb.value);
                selected.push(cb.parentElement.textContent.trim());
            });

            if (selected.length === 0) {
                selectBox.innerText = "Select Work Equipment";
            } else if (selected.length <= 2) {
                selectBox.innerText = selected.join(", ");
            } else {
                selectBox.innerText = selected.length + " Equipment Selected";
            }

        }


        // Close the Multiselect Dropdwon
        document.addEventListener("click", function(e) {

            const skillBox = document.querySelector(".multi-select");
            const skillDropdown = document.getElementById("skillDropdown");

            const languageBox = document.querySelector("#languageDropdown").parentElement;
            const equipmentBox = document.querySelector("#equipmentDropdown").parentElement;

            // Skills
            if (!e.target.closest("#skillSelectBox") && !e.target.closest("#skillDropdown")) {
                skillDropdown.style.display = "none";
            }

            // Languages
            if (!e.target.closest("#languageSelectBox") && !e.target.closest("#languageDropdown")) {
                document.getElementById("languageDropdown").style.display = "none";
            }

            // Equipment
            if (!e.target.closest("#equipmentSelectBox") && !e.target.closest("#equipmentDropdown")) {
                document.getElementById("equipmentDropdown").style.display = "none";
            }

        });



        function goBackPage() {
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

        function initCitySearch() {

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
    </script>
    <script>
        let map, marker, geocoder, placesService, autocompleteService;

        const defaultLocation = {
            lat: 18.5204,
            lng: 73.8567
        };

        const mapModal = document.getElementById('mapModal');
        const searchInput = document.getElementById('mapSearchInput');
        const searchResults = document.getElementById('searchResults');

        const modalAddressText = document.getElementById('modalAddressText');
        const modalCoordsText = document.getElementById('modalCoordsText');

        const mainLocationInput = document.getElementById('mainLocationInput');
        const mainAddressTextarea = document.getElementById('mainAddressTextarea');

        const latInput = document.getElementById('interview_lat');
        const lngInput = document.getElementById('interview_lng');
        const fullAddressInput = document.getElementById('interview_full_address');


        function renderPredictions(predictions) {
            let html = "";

            predictions.forEach(function(item) {

                const mainText = item.structured_formatting?.main_text || item.description;
                const secondaryText = item.structured_formatting?.secondary_text || "";

                html += `
            <div class="search-result-item"
                onclick="selectPlace('${item.place_id.replace(/'/g, "\\'")}')">

                <div class="search-result-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>

                <div class="search-result-text">
                    <div class="search-result-main">${mainText}</div>
                    <div class="search-result-sub">${secondaryText || item.description}</div>
                </div>

            </div>
        `;
            });

            searchResults.innerHTML = html;
            searchResults.classList.add("active");
        }

        function initMap() {

            geocoder = new google.maps.Geocoder();

            map = new google.maps.Map(document.getElementById("googleMap"), {
                center: defaultLocation,
                zoom: 15
            });

            marker = new google.maps.Marker({
                position: defaultLocation,
                map: map,
                draggable: true
            });

            placesService = new google.maps.places.PlacesService(map);
            autocompleteService = new google.maps.places.AutocompleteService();

            marker.addListener("dragend", function(event) {
                updateLocation(event.latLng.lat(), event.latLng.lng());
            });

            map.addListener("click", function(event) {
                updateLocation(event.latLng.lat(), event.latLng.lng());
            });

            searchInput.addEventListener("input", function() {
                let query = this.value;
                if (query.length < 2) return;

                autocompleteService.getPlacePredictions({
                    input: query,
                    componentRestrictions: {
                        country: "in"
                    }
                }, function(predictions) {

                    if (!predictions) return;

                    let html = "";


                    renderPredictions(predictions);
                });
            });
        }

        function selectPlace(placeId) {

            placesService.getDetails({
                placeId: placeId,
                fields: ["geometry", "formatted_address"]
            }, function(place) {

                let lat = place.geometry.location.lat();
                let lng = place.geometry.location.lng();

                map.setCenter({
                    lat,
                    lng
                });
                marker.setPosition({
                    lat,
                    lng
                });

                updateLocation(lat, lng, place.formatted_address);

                searchResults.classList.remove("active");
            });
        }

        function updateLocation(lat, lng, address = "") {

            latInput.value = lat;
            lngInput.value = lng;

            modalCoordsText.innerText = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;

            if (address) {
                modalAddressText.innerText = address;
                fullAddressInput.value = address;
                return;
            }

            geocoder.geocode({
                location: {
                    lat,
                    lng
                }
            }, function(results) {
                if (results[0]) {
                    modalAddressText.innerText = results[0].formatted_address;
                    fullAddressInput.value = results[0].formatted_address;
                }
            });
        }

        function openMapModal() {
            mapModal.classList.add("active");

            // ðŸ”¥ CLEAR PREVIOUS SEARCH
            searchInput.value = "";
            searchResults.innerHTML = "";
            searchResults.classList.remove("active");

            setTimeout(() => {
                google.maps.event.trigger(map, "resize");
                map.setCenter(defaultLocation);
            }, 300);
        }

        function closeMapModal() {
            mapModal.classList.remove("active");
        }

        function confirmLocation() {

            const address = fullAddressInput.value;
            const lat = latInput.value;
            const lng = lngInput.value;

            // ðŸ”¥ interview_location = lat & lon string
            mainLocationInput.value = `Lat=${lat}, Lng=${lng}`;

            // âœ… interview_address = full address
            mainAddressTextarea.value = address;

            closeMapModal();
        }
        document.addEventListener("DOMContentLoaded", function() {
            flatpickr(".datepicker", {
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