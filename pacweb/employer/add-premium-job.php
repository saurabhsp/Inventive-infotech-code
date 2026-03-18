<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

$user = $_SESSION['user'] ?? null;
$userid = $user['id'] ?? 0;



$company_name = "";
$interview_address = "";
$contact_person = "";
$contact_mobile = "";
$api_error = "";



/* ====================1. Get Logged in user data========================== */

$post_user_data1 = json_encode([
    "user_id" => $user
]);
$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => "https://beta.inv51.in/webservices/getRecuriterdetails.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_POST => true,
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
    "user_id" => $user
]);

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => "https://beta.inv51.in/webservices/getPosition.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
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
    CURLOPT_URL => "https://beta.inv51.in/webservices/getJobtype.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
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
    CURLOPT_URL => "https://beta.inv51.in/webservices/getWorkmodel.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
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
    CURLOPT_URL => "https://beta.inv51.in/webservices/getDegrees.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
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
    CURLOPT_URL => "https://beta.inv51.in/webservices/getSalaryrange.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
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
    CURLOPT_URL => "https://beta.inv51.in/webservices/getExperience_list.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
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
    CURLOPT_URL => "https://beta.inv51.in/webservices/getMskill_list.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
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
    CURLOPT_URL => "https://beta.inv51.in/webservices/getMLanguage_list.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
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
    CURLOPT_URL => "https://beta.inv51.in/webservices/getWorkequipments.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
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
    CURLOPT_URL => "https://beta.inv51.in/webservices/getWorkshift.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
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
    CURLOPT_URL => "https://beta.inv51.in/webservices/getGender.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $postData = [
        "recruiter_id" => $user,
        "company_name" => $company_name,
        "job_position_id" => $_POST['job_position'] ?? "",
        "state" => $_POST['state'] ?? "",
        "country" => $_POST['country'] ?? "",
        "city_id" => $_POST['city'] ?? "",
        "locality_id" => $_POST['locality'] ?? "",
        "number_of_openings" => $_POST['number_of_openings'] ?? "",
        "job_type" => $_POST['job_type'] ?? "",
        "work_model" => $_POST['work_model'] ?? "",
        "field_work" => $_POST['fieldwork'] ?? "",
        "work_shift" => "Morning",
        "gender" => $_POST['gender'] ?? "",
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
        "validity_apply" => 1,
        "valid_till_date" => $_POST['valid_till_date'] ?? "",
        "job_status_id" => 1,
        "lat" => "18.5204",
        "lon" => "73.8567"
    ];

    $jsonData = json_encode($postData);
    // print_r($_POST['gender']);
    // exit;

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://beta.inv51.in/webservices/addWalkininterview.php",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    $apiResult = json_decode($response, true);

    if ($apiResult['status'] == 1) {

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
    </style>
</head>

<body>


   <?php include "includes/preloader.php"; ?>
    <?php  include "includes/header.php"; 
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
                    <button class="btn btn-primary"
                        onclick="window.location.href='view-job.php'">
                        View Job Post
                    </button>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>


    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="modal-full-overlay active" id="errorModal">
            <div class="modal-map-card" style="max-width:400px;height:auto;padding:30px;text-align:center;">
                <h3 style="margin-bottom:15px;color:#e53935;">Error</h3>
                <p><?php echo $_SESSION['error_message']; ?></p>
                <button onclick="closeErrorModal()"
                    style="margin-top:20px;padding:10px 20px;background:#2563eb;color:white;border-radius:6px;border:none;">
                    OK
                </button>
            </div>
        </div>
        <?php unset($_SESSION['error_message']); ?>
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

            <form method="POST">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Company Name</label>
                        <input type="text" class="form-control" name="company_name"
                            value="<?php echo htmlspecialchars($company_name); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Job Position</label>
                        <select class="form-control" name="job_position">
                            <option value="">Select Job Position</option>
                            <?php if (!empty($job_positions)) { ?>
                                <?php foreach ($job_positions as $position) { ?>
                                    <option value="<?php echo htmlspecialchars($position['id']); ?>">
                                        <?php echo htmlspecialchars($position['name']); ?>
                                    </option>
                                <?php } ?>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">District / Tehsil / City</label>
                        <input type="text" name="city" id="cityInput" class="form-control" autocomplete="off">
                        <!-- hidden fields -->
                        <input type="hidden" id="stateInput" name="state">
                        <input type="hidden" id="countryInput" name="country">
                        <div id="citySuggestions" class="suggestion-box"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Area / Locality / Village</label>
                        <input type="text" id="localityInput" name="locality" class="form-control" autocomplete="off">
                        <div id="localitySuggestions" class="suggestion-box"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Number of Openings</label>
                        <input type="text" name="number_of_openings" class="form-control" placeholder="Eg :- 3, 5, 10">
                    </div>

                    <div class="form-group" style="display: flex; flex-direction: column; gap: 15px;">
                        <div>
                            <label class="form-label">Does this Job require Fieldwork?</label>
                            <div class="toggle-container" id="fieldworkToggle">
                                <input type="hidden" name="fieldwork" id="fieldworkInput" value="Yes">
                                <button
                                    type="button"
                                    class="btn-toggle"
                                    data-value="Yes"
                                    onclick="toggleGroup(this, 'fieldworkToggle')">
                                    Yes
                                </button>

                                <button
                                    type="button"
                                    class="btn-toggle"
                                    data-value="No"
                                    onclick="toggleGroup(this, 'fieldworkToggle')">
                                    No
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Job Type</label>
                        <div class="toggle-container" id="jobTypeToggle">
                            <input type="hidden" name="job_type" id="jobTypeInput">
                            <?php if (!empty($job_types)) { ?>
                                <?php foreach ($job_types as $index => $type) { ?>
                                    <button
                                        type="button"
                                        class="btn-toggle"
                                        onclick="toggleGroup(this, 'jobTypeToggle')"
                                        data-name="<?php echo $type['name']; ?>">
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </button>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="form-group">
                            <label class="form-label">Work Model</label>
                            <div class="toggle-container" id="workModelToggle">
                                <?php if (!empty($work_models)) { ?>
                                    <?php foreach ($work_models as $index => $model) { ?>
                                        <button
                                            type="button"
                                            class="btn-toggle"
                                            data-name="<?php echo $model['name']; ?>"
                                            onclick="toggleGroup(this,'workModelToggle')">
                                            <?php echo htmlspecialchars($model['name']); ?>
                                        </button>
                                    <?php } ?>
                                <?php } ?>

                            </div>

                            <input type="hidden" name="work_model" id="workModelInput">

                        </div>
                    </div>



                    <div class="form-group">
                        <div class="form-group">
                            <label class="form-label">Work Shift</label>
                            <div class="toggle-container" id="workShiftToggle">
                                <?php if (!empty($work_shifts)) { ?>
                                    <?php foreach ($work_shifts as $index => $shift) { ?>
                                        <button
                                            type="button"
                                            class="btn-toggle"
                                            data-name="<?php echo $shift['shift_name']; ?>"
                                            onclick="toggleGroup(this,'workShiftToggle')">
                                            <?php echo htmlspecialchars($shift['shift_name']); ?>
                                        </button>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                            <input type="hidden" name="work_shift" id="workShiftInput">
                        </div>
                    </div>




                    <div class="form-group">
                        <label class="form-label">Gender</label>

                        <div class="toggle-container" id="genderToggle">

                            <input type="hidden" name="gender" id="genderInput">

                            <?php if (!empty($genders)) { ?>
                                <?php foreach ($genders as $gender) { ?>

                                    <button
                                        type="button"
                                        class="btn-toggle"
                                        data-name="<?php echo $gender['name']; ?>"
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
                                    <option value="<?php echo $degree['id']; ?>">
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
                                            <option value="<?php echo $exp['id']; ?>">
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
                                            <option value="<?php echo $exp['id']; ?>">
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
                                            <option value="<?php echo $salary['id']; ?>">
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
                                            <option value="<?php echo $salary['id']; ?>">
                                                ₹<?php echo number_format($salary['name']); ?>
                                            </option>
                                        <?php } ?>
                                    <?php } ?>
                                </select>
                            </div>

                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Skills & Languages</label>
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
                                                    <input type="checkbox" name="skills_required[]" value="<?php echo htmlspecialchars($skill['title']); ?>" onchange="updateSelectedSkills()">
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
                                                    <input type="checkbox" name="languages_required[]"
                                                        value="<?php echo htmlspecialchars($language['name']); ?>" onchange="updateSelectedLanguages()">
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
                        <label class="form-label">Work Equipment Needed</label>

                        <div class="multi-select">

                            <div class="select-box" id="equipmentSelectBox" onclick="toggleEquipmentDropdown()">
                                Select Work Equipment
                            </div>

                            <div class="checkbox-container" id="equipmentDropdown">

                                <?php if (!empty($work_equip)) { ?>
                                    <?php foreach ($work_equip as $equipments) { ?>

                                        <label class="checkbox-item">
                                            <input
                                                type="checkbox"
                                                name="work_equipment[]"
                                                value="<?php echo htmlspecialchars($equipments['name']); ?>"
                                                onchange="updateSelectedEquipment()">
                                            <?php echo htmlspecialchars($equipments['name']); ?>
                                        </label>

                                    <?php } ?>
                                <?php } ?>

                            </div>

                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; height: 100%;">
                        <label class="form-label">Job Description : (Optional)</label>
                        <textarea class="form-control" name="job_description" placeholder="Enter Job Description" style="flex:1; min-height: 80px;"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Details</label>
                        <div class="form-row">
                            <div class="form-col"><input type="text" class="form-control"
                                    value="<?php echo htmlspecialchars($contact_person); ?>"
                                    placeholder="Contact Person Name" name="contact_person_name"></div>
                            <div class="form-col"><input type="tel" class="form-control" name="contact_no" value="<?php echo htmlspecialchars($contact_mobile); ?>" placeholder="Mobile No"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Interview Location</label>
                        <div class="map-input-container" onclick="openMapModal()">
                            <input type="text" class="form-control" id="mainLocationInput" name="interview_location" placeholder="Choose map location" readonly>
                            <i class="fas fa-map-marker-alt map-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Interview Address</label>
                        <textarea class="form-control" id="mainAddressTextarea" name="interview_address" placeholder="Address will auto-fill based on map location..."><?php echo htmlspecialchars($interview_address); ?></textarea>
                    </div>

                    <!-- <div class="form-group full-width" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;"> -->
                    <div>
                        <label class="form-label">Does this Job have a deadline?</label>
                        <div class="toggle-container" id="deadlineToggle">
                            <button type="button" class="btn-toggle" onclick="handleDeadlineToggle(true, this)">Yes</button>
                            <button type="button" class="btn-toggle active" onclick="handleDeadlineToggle(false, this)">No</button>
                        </div>
                        <div class="deadline-date-wrapper" id="deadlineDateGroup">
                            <input type="date" name="valid_till_date" class="form-control" placeholder="Select Date">
                        </div>
                    </div>

                    <!-- <div style="display:flex; flex-direction:column; height: 100%;">
                        <label class="form-label">Job Description : (Optional)</label>
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
                <button class="map-close-btn" onclick="closeMapModal()"><i class="fas fa-arrow-left"></i></button>
                <div class="map-header-title">Confirm Interview Location</div>
            </div>

            <div class="map-search-bar">
                <i class="fas fa-search map-search-icon"></i>
                <input type="text" class="map-search-input" id="mapSearchInput" placeholder="Enter your city or Locality..." onkeyup="simulateSearch(this.value)">

                <div class="search-suggestions" id="searchSuggestions">
                    <div class="suggestion-item" onclick="selectSuggestion('Suhela, Chhattisgarh, India')">
                        <i class="fas fa-map-marker-alt suggestion-icon"></i>
                        <div class="suggestion-text">
                            <h4>Suhela</h4>
                            <p>Suhela, Chhattisgarh, India</p>
                        </div>
                    </div>
                    <div class="suggestion-item" onclick="selectSuggestion('Suhagi, Madhya Pradesh, India')">
                        <i class="fas fa-map-marker-alt suggestion-icon"></i>
                        <div class="suggestion-text">
                            <h4>Suhagi</h4>
                            <p>Suhagi, Madhya Pradesh, India</p>
                        </div>
                    </div>
                    <div class="suggestion-item" onclick="selectSuggestion('Ravivar Peth, Satara, Maharashtra 415001, India')">
                        <i class="fas fa-map-marker-alt suggestion-icon"></i>
                        <div class="suggestion-text">
                            <h4>Ravivar Peth</h4>
                            <p>Satara, Maharashtra, India</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="map-view-area">
                <div class="map-center-pin">
                    <div class="map-info-tooltip">
                        <h4>Your interview location will be here</h4>
                        <p>Move pin to your exact location</p>
                    </div>
                    <div class="map-pin-bubble">
                        <i class="fas fa-map-marker-alt map-pin-icon"></i>
                    </div>
                </div>

                <div class="map-controls">
                    <div class="map-ctrl-btn"><i class="fas fa-plus"></i></div>
                    <div class="map-ctrl-btn"><i class="fas fa-minus"></i></div>
                </div>

                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/1/11/Google_logo_2015_colors.svg/512px-Google_logo_2015_colors.svg.png" style="position:absolute; bottom:15px; left:15px; height:20px; opacity:0.8;">
            </div>

            <div class="map-footer">
                <div class="selected-address-label">Selected Address:</div>
                <div class="selected-address-text" id="modalAddressText">A PANTACHA GOT RAVIVAR PETH, 82, Khalcha Rasta, Ravivar Peth, Satara, Maharashtra 415001, India</div>
                <button class="btn-confirm-loc" onclick="confirmLocation()">Confirm Location</button>
            </div>

        </div>
    </div>

    <script>
        window.onload = () => document.getElementById("global-preloader")?.remove();
        // Generic Toggle Logic for button groups
        function toggleGroup(clickedBtn, groupId) {
            const container = document.getElementById(groupId);
            const buttons = container.querySelectorAll('.btn-toggle');
            buttons.forEach(btn => btn.classList.remove('active'));
            clickedBtn.classList.add('active');
        }

        // Specific Deadline Logic
        function handleDeadlineToggle(isYes, clickedBtn) {
            toggleGroup(clickedBtn, 'deadlineToggle');
            const dateGroup = document.getElementById('deadlineDateGroup');
            if (isYes) {
                dateGroup.classList.add('active');
            } else {
                dateGroup.classList.remove('active');
            }
        }

        // --- MAP MODAL LOGIC ---
        const mapModal = document.getElementById('mapModal');
        const searchInput = document.getElementById('mapSearchInput');
        const searchSuggestions = document.getElementById('searchSuggestions');
        const modalAddressText = document.getElementById('modalAddressText');

        function openMapModal() {
            mapModal.classList.add('active');
            searchInput.value = '';
            searchSuggestions.classList.remove('active');
        }

        function closeMapModal() {
            mapModal.classList.remove('active');
        }

        // Simulate typing "suh" to show suggestions
        function simulateSearch(val) {
            if (val.length > 1) {
                searchSuggestions.classList.add('active');
            } else {
                searchSuggestions.classList.remove('active');
            }
        }

        // Click a suggestion
        function selectSuggestion(address) {
            searchInput.value = address.split(',')[0]; // Put title in search
            modalAddressText.innerText = address; // Update selected text
            searchSuggestions.classList.remove('active'); // Hide dropdown
        }

        // Confirm Location and populate main form
        function confirmLocation() {
            const finalAddress = modalAddressText.innerText;
            // Set the read-only input to show we selected a map pin
            document.getElementById('mainLocationInput').value = "Location Selected on Map";
            // Auto-fill the address text area
            document.getElementById('mainAddressTextarea').value = finalAddress;
            closeMapModal();
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
                    clickedBtn.getAttribute("data-name");
            }

            // Work Model value
            if (groupId === "workModelToggle") {
                document.getElementById("workModelInput").value =
                    clickedBtn.getAttribute("data-name");
            }
            // Work Shift value
            if (groupId === "workShiftToggle") {
                document.getElementById("workShiftInput").value =
                    clickedBtn.getAttribute("data-name");
            }
            // Gender value
            if (groupId === "genderToggle") {
                document.getElementById("genderInput").value =
                    clickedBtn.getAttribute("data-name");
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
                selected.push(cb.value);
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
                selected.push(cb.value);
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
                selected.push(cb.value);
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



        function closeSuccessModal() {
            const modal = document.getElementById("successModal");
            if (modal) {
                modal.style.display = "none";
            }
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
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCokcdTmQxRaopu75ourz-nNmZNie1wQkY&libraries=places&callback=initCityAutocomplete" async defer></script>

</body>

</html>