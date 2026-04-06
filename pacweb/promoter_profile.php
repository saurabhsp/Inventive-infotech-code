<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/includes/session.php';

require_once __DIR__ . '/includes/db_config.php';
$userid = $_SESSION['user_id'] ?? 0;

/* ===============================
   ✅ LOGIN CHECK
================================ */
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* ===============================
   1. GET PROFILE API
================================ */

$profile = [];

$api = API_BASE_URL . "getPromoterprofile.php";


$payload = json_encode([
    "userid" => $userid
]);

$ch = curl_init($api);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],

    // ✅ ADD
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15
]);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    die("API Error: " . curl_error($ch));
}

curl_close($ch);


$result = json_decode($response, true);

print_r($result);

if (isset($result['status']) && $result['status'] == true && isset($result['data'])) {
    $profile = $result['data'];
} else {
    $profile = [];
}

/* ===============================
   GET ALL JOB POSITIONS FROM DB
================================ */

$conn = new mysqli('localhost', 'inv11_pacificiconnect_beta', '$JbDLi@evoaag(gV', 'inv11_pacificiconnect_beta26');

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$all_roles = [];

$sql = "SELECT id,name FROM jos_crm_jobpost ORDER BY name ASC";
$res = $conn->query($sql);

while ($row = $res->fetch_assoc()) {
    $all_roles[] = $row;
}

$selected_roles = [];

if (!empty($profile['job_position_ids'])) {
    $selected_roles = explode(',', $profile['job_position_ids']);
    $selected_roles = array_map('trim', $selected_roles);
}
/* ===============================
   2. UPDATE PROFILE API
================================ */

if (isset($_POST['updateProfile'])) {

    $update_api = API_BASE_URL . "updateCandidateprofile.php";

    $data = json_encode([
        "id" => $profile['id'] ?? 0,

        "candidate_name" => $_POST['candidate_name'] ?? $profile['candidate_name'],
        "mobile_no" => $_POST['mobile_no'] ?? $profile['mobile_no'],
        "gender_id" => $_POST['gender_id'] ?? $profile['gender_id'],
        "birthdate" => $_POST['birthdate'] ?? $profile['birthdate'],
        "email" => $_POST['email'] ?? $profile['email'],
        "address" => $_POST['address'] ?? $profile['address'],

        "job_position_ids" => $_POST['job_position_ids'] ?? $profile['job_position_ids'],
        "experience_type" => $_POST['experience_type'] ?? $profile['experience_type'],
        "experience_period" => $_POST['experience_period'] ?? $profile['experience_period'],

        "country" => $_POST['country'] ?? $profile['country'],
        "state" => $_POST['state'] ?? $profile['state'],
        "district" => $_POST['district'] ?? $profile['district'],
        "city_id" => $_POST['city_id'] ?? $profile['city_id'],
        "locality_id" => $_POST['locality_id'] ?? $profile['locality_id']
    ]);

    $ch = curl_init($update_api);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],

        // ✅ ADD
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        die("API Error: " . curl_error($ch));
    }

    curl_close($ch);

    // âœ… ðŸ‘‰ YEH ADD KIYA
    $city = $_POST['city_id'] ?? '';

    if ($city != '') {
        $stmt = $conn->prepare("
        UPDATE jos_app_users 
        SET city_id = ? 
        WHERE id = ?
    ");
        $stmt->bind_param("si", $city, $userid);
        $stmt->execute();
    }

    header("Location: promoter_profile.php");
    exit;
}
/* ===============================
   3. PROFILE PHOTO UPLOAD (512px resize)
================================ */

if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {

    $upload_api = API_BASE_URL . "addCandidatephoto.php";

    $tmp = $_FILES['profile_photo']['tmp_name'];
    if ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
        echo json_encode(["status" => "file_too_large"]);
        exit;
    }
    $type = mime_content_type($tmp);

    $allowed = ['image/jpeg', 'image/png', 'image/jpg'];

    if (!in_array($type, $allowed)) {
        echo json_encode(["status" => "invalid_file"]);
        exit;
    }

    $src = imagecreatefromstring(file_get_contents($tmp));
    if (!$src) {
        echo json_encode(["status" => "invalid_image"]);
        exit;
    }

    $dst = imagecreatetruecolor(512, 512);

    imagecopyresampled(
        $dst,
        $src,
        0,
        0,
        0,
        0,
        512,
        512,
        imagesx($src),
        imagesy($src)
    );

    $tempFile = sys_get_temp_dir() . "/profile_" . $userid . "_" . time() . ".jpg";

    imagejpeg($dst, $tempFile, 90);

    imagedestroy($src);
    imagedestroy($dst);

    $post = [
        'userid' => $userid,
        'profile_photo' => new CURLFile($tempFile, 'image/jpeg', 'profile.jpg')
    ];

    $ch = curl_init($upload_api);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,

        // ✅ ADD
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30   // bigger for upload
    ]);

    $response = curl_exec($ch);

    curl_close($ch);
    unlink($tempFile);

    echo json_encode(["status" => "success"]);
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Pacific iConnect</title>

    <link rel="stylesheet" href="/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #483EA8;
            --primary-light: #f4f2ff;
            --blue-btn: #3f51b5;
            --bg-body: #f4f7fa;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-grey: #666666;
            --border-light: #e0e0e0;
            --bronze: #cd7f32;
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
            font-size: 14.5px;
            line-height: 1.5;
            overflow-x: hidden;
        }



        /* --- LAYOUT --- */
        .profile-container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 15px;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 25px;
            align-items: start;
        }

        .profile-card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            margin-bottom: 20px;
            border: 1px solid white;
            position: relative;
            width: 100%;
        }

        /* --- SIDEBAR --- */
        .sidebar-wrapper {
            position: sticky;
            top: 85px;
        }

        .profile-card-header {
            height: 100px;
            background: linear-gradient(135deg, var(--primary) 0%, #6a5acd 100%);
            border-radius: 16px 16px 0 0;
            position: relative;
        }

        .avatar-container {
            position: absolute;
            bottom: -50px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid var(--white);
            background: var(--white);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            z-index: 5;
        }

        /* Progress bar under avatar */
        #uploadProgressContainer {
            position: absolute;
            bottom: -12px;
            left: 0;
            width: 100%;
        }

        #uploadProgressBar {
            transition: width .25s ease;
        }

        .profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .camera-badge {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--text-dark);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            border: 2px solid white;
            cursor: pointer;
        }

        .profile-details-text {
            text-align: center;
            padding: 60px 20px 25px;
        }

        .profile-name {
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 4px;
            color: var(--text-dark);
        }

        .user-role {
            font-size: 0.95rem;
            color: var(--text-grey);
            font-weight: 500;
        }

        .edit-profile-btn {
            margin-top: 15px;
            display: inline-block;
            color: var(--blue-btn);
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            padding: 8px 25px;
            border-radius: 20px;
            background: var(--primary-light);
            transition: 0.2s;
            cursor: pointer;
        }

        .edit-profile-btn:hover {
            background: #e0dcff;
        }

        .sub-box {
            background: #fff8f0;
            border-radius: 10px;
            padding: 15px;
            border: 1px solid #ffe0b2;
            margin: 10px 20px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .plan-info {
            font-size: 0.9rem;
            line-height: 1.4;
            display: block;
            color: #5d4037;
        }

        .plan-info b {
            font-weight: 700;
            color: #d84315;
        }

        .crown-icon {
            color: var(--bronze);
            font-size: 1.5rem;
        }

        .btn-upgrade {
            width: calc(100% - 40px);
            margin: 0 20px 20px;
            background: var(--blue-btn);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-upgrade:hover {
            background: #303f9f;
        }

        .side-menu {
            padding: 10px 0;
            border-top: 1px solid #f0f0f0;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 25px;
            color: var(--text-dark);
            font-weight: 500;
            transition: 0.2s;
            cursor: pointer;
            text-decoration: none;
        }

        .menu-item:hover {
            background: var(--bg-body);
            color: var(--primary);
        }

        .menu-item i {
            width: 20px;
            color: var(--text-grey);
        }

        .menu-item.logout {
            color: #d32f2f;
            margin-top: 10px;
            border-top: 1px solid var(--border-light);
            padding-top: 20px;
        }

        /* --- CONTENT --- */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-light);
        }

        .section-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-dark);
        }

        .section-edit {
            color: var(--blue-btn);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .info-container {
            padding: 10px 25px 25px;
        }

        .desktop-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 40px;
            margin-top: 15px;
        }

        .info-group {
            display: flex;
            flex-direction: column;
        }

        .label {
            font-size: 0.85rem;
            color: var(--text-grey);
            margin-bottom: 4px;
            font-weight: 500;
        }

        .value {
            font-size: 1rem;
            color: #222;
            font-weight: 600;
            word-break: break-word;
        }

        /* --- MODALS COMMON --- */
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
            backdrop-filter: blur(4px);
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: white;
            width: 90%;
            max-width: 420px;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
            /* Scroll if tall */
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .close-modal {
            cursor: pointer;
            font-size: 1.2rem;
            color: #666;
        }

        .modal-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.95rem;
            outline: none;
            background: #fafafa;
        }

        .modal-input:focus {
            border-color: var(--primary);
            background: #fff;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-modal {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-save {
            background: var(--primary);
            color: white;
        }

        .btn-cancel {
            background: #f0f0f0;
            color: #333;
        }

        /* --- MULTI-SELECT DROPDOWN STYLES --- */
        .multi-select-wrapper {
            position: relative;
            margin-bottom: 15px;
        }

        .select-box {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fafafa;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .select-box span {
            font-size: 0.95rem;
            color: #333;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            max-width: 90%;
        }

        .dropdown-options {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            z-index: 10;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 5px;
        }

        .dropdown-options.show {
            display: block;
        }

        .dropdown-options label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f9f9f9;
            font-size: 0.9rem;
        }

        .dropdown-options label:hover {
            background: #f0f4ff;
        }

        .dropdown-options input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
        }

        /* --- MOBILE --- */
        @media (max-width: 900px) {
            .container {
                margin: 0;
                padding: 15px;
                width: 100%;
            }

            header {
                height: 55px;
            }

            .profile-grid {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }

            .card {
                width: 100%;
                margin-bottom: 15px;
            }

            .sidebar-wrapper {
                order: 1;
                position: static;
                width: 100%;
            }

            .content-wrapper {
                order: 2;
                width: 100%;
            }

            .profile-card-header {
                height: 85px;
            }

            .avatar-container {
                width: 90px;
                height: 90px;
                bottom: -45px;
            }

            .profile-details-text {
                padding-top: 55px;
            }

            .desktop-info-grid {
                display: flex;
                flex-direction: column;
                gap: 0;
                margin-top: 0;
            }

            .info-group {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 15px 0;
                border-bottom: 1px solid #eee;
            }

            .info-group:last-child {
                border-bottom: none;
            }

            .label {
                margin-bottom: 0;
                font-size: 0.9rem;
            }

            .value {
                text-align: right;
                font-size: 0.95rem;
            }

            .side-menu {
                display: none;
            }
        }

        .autocomplete-wrapper {
            position: relative;
        }

        .autocomplete-list {
            position: absolute;
            width: 100%;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            max-height: 180px;
            overflow-y: auto;
            display: none;
            z-index: 999;
        }

        .autocomplete-item {
            padding: 10px;
            cursor: pointer;
        }

        .autocomplete-item:hover {
            background: #f0f4ff;
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
        }

        .suggestion-item {
            padding: 10px;
            cursor: pointer;
        }

        .suggestion-item:hover {
            background: #f1f5ff;
        }

        .suggestion-box {
            top: 100%;
            left: 0;
        }
    </style>
</head>

<body>
    <?php include "includes/preloader.php"; ?>
    <?php include "includes/promoter_header.php"; ?>

    <div class="profile-container">
        <div class="profile-grid">

            <aside class="sidebar-wrapper">
                <div class="profile-card">
                    <div class="profile-card-header">
                        <div class="avatar-container">

                            <img
                                src="<?= !empty($profile['profile_photo']) ? $profile['profile_photo'] : 'https://via.placeholder.com/100' ?>"
                                class="profile-avatar"
                                alt="Profile Photo">

                            <div class="camera-badge" onclick="document.getElementById('profile_photo_input').click()">
                                <i class="fas fa-camera"></i>
                            </div>

                            <input type="file"
                                id="profile_photo_input"
                                name="profile_photo"
                                accept="image/png,image/jpeg,image/jpg"
                                style="display:none">

                            <div id="uploadProgressContainer" style="display:none; margin-top:10px;">
                                <div style="width:100%; background:#eee; border-radius:6px;">
                                    <div id="uploadProgressBar"
                                        style="width:0%; height:6px; background:#483EA8; border-radius:6px;">
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="profile-details-text">
                        <h2 class="profile-name"><?= $profile['candidate_name'] ?? 'User' ?></h2>
                        <p class="user-role">Promoter</p>
                    </div>
              
                  
                    <div class="side-menu">
                        <!-- <a href="#" class="menu-item"><i class="fas fa-file-alt"></i> My Resume</a>

                        <a href="change_password.php" class="menu-item">
                            <i class="fas fa-key"></i> Change Password
                        </a> -->
                        <a href="/logout.php" class="menu-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </aside>

            <main class="content-wrapper">

                <div class="profile-card">
                    <div class="section-header">
                        <span class="section-title">Personal Details</span>
                    </div>
                    <div class="info-container desktop-info-grid">

                        <div class="info-group">
                            <span class="label">Mobile Number</span>
                            <span class="value"><?= $profile['mobile_no'] ?? '' ?></span>
                        </div>

                        <div class="info-group">
                            <span class="label">Email Address</span>
                            <span class="value"><?= $profile['email'] ?? '' ?></span>
                        </div>

                        <div class="info-group">
                            <span class="label">Gender</span>
                            <span class="value"><?= $profile['gender'] ?? '' ?></span>
                        </div>

                        <div class="info-group">
                            <span class="label">Date of Birth</span>
                            <span class="value"><?= $profile['birthdate'] ?? '' ?></span>
                        </div>

                        <div class="info-group">
                            <span class="label">City</span>
                            <span class="value"><?= $profile['city_name'] ?? $profile['city_id'] ?? '' ?></span>
                        </div>

                        <div class="info-group">
                            <span class="label">Locality</span>
                            <span class="value"><?= $profile['locality_name'] ?? $profile['locality_id'] ?? '' ?></span>
                        </div>

                    </div>
                </div>

            

            </main>
        </div>
    </div>

  

    <script>
        // Modal Logic
        function openModal(id) {
            document.getElementById(id).classList.add('active');

            if (id === 'jobPrefModal') {
                setTimeout(function() {
                    updateSelectedJobs();
                }, 200);
            }
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }


        // Multi-Select Logic
        function toggleMultiSelect() {
            const dropdown = document.getElementById('jobOptions');
            dropdown.classList.toggle('show');

            if (dropdown.classList.contains('show')) {
                setTimeout(() => {
                    document.getElementById("jobSearch").focus();
                }, 100);
            }
        }


        function updateSelectedJobs() {

            const checkboxes = document.querySelectorAll('#jobOptions input[type="checkbox"]:checked');

            const ids = [];
            const names = [];

            checkboxes.forEach(cb => {
                ids.push(cb.value);
                names.push(cb.parentNode.textContent.trim());
            });

            // update hidden input
            document.getElementById("job_position_ids").value = ids.join(",");

            const displayBox = document.getElementById("selected-jobs-text");

            // update text only if checkbox found
            if (names.length > 0) {
                displayBox.innerText = names.join(", ");
                displayBox.style.color = "#333";
            }
        }

        function filterJobs() {

            const input = document.getElementById("jobSearch").value.toLowerCase();
            const items = document.querySelectorAll("#jobOptions .job-item");

            items.forEach(item => {

                const text = item.textContent.toLowerCase();

                if (input === "" || text.includes(input)) {
                    item.style.display = "flex";
                } else {
                    item.style.display = "none";
                }

            });
        }

        // Save Job Preferences
        function saveJobPrefs() {

            const checkboxes = document.querySelectorAll('#jobOptions input[type="checkbox"]:checked');
            const selected = Array.from(checkboxes).map(cb => cb.value).join(',');

            document.getElementById('job_position_ids').value = selected;

            document.getElementById("jobPrefForm").submit();
        }


        // Close dropdown when clicking outside
        window.onclick = function(event) {

            // close modal
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
            }

            // close dropdown
            if (!event.target.closest('.multi-select-wrapper')) {
                document.getElementById('jobOptions').classList.remove('show');
            }

            // close city dropdown
            if (!event.target.closest('#profileCityInput')) {
                document.getElementById('profileCitySuggestions').innerHTML = "";
            }

            // close locality dropdown
            if (!event.target.closest('#profileLocalityInput')) {
                document.getElementById('profileLocalitySuggestions').innerHTML = "";
            }

        }
    </script>
    <script>
        document.getElementById("profile_photo_input").addEventListener("change", function() {

            const file = this.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append("profile_photo", file);

            const progressBox = document.getElementById("uploadProgressContainer");
            const progressBar = document.getElementById("uploadProgressBar");

            progressBox.style.display = "block";
            progressBar.style.width = "0%";

            const xhr = new XMLHttpRequest();

            xhr.open("POST", "promoter_profile.php", true);

            xhr.upload.onprogress = function(e) {

                if (e.lengthComputable) {

                    let percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percent + "%";

                }

            };

            xhr.onload = function() {

                if (xhr.status === 200) {

                    progressBar.style.width = "100%";

                    setTimeout(function() {
                        location.reload();
                    }, 500);

                }

            };

            xhr.send(formData);

        });
    </script>
    <script>
        let profileService;
        let profilePlaceService;
        let profileSelectedCity = "";

        // INIT GOOGLE AUTOCOMPLETE
        function initProfileCityAutocomplete() {

            profileService = new google.maps.places.AutocompleteService();
            profilePlaceService = new google.maps.places.PlacesService(document.createElement('div'));

            const input = document.getElementById("profileCityInput");

            input.addEventListener("keyup", function() {

                let query = input.value;

                if (query.length < 2) return;

                profileService.getPlacePredictions({
                    input: query,
                    componentRestrictions: {
                        country: "in"
                    }
                }, function(predictions) {

                    if (!predictions) return;

                    showProfileCitySuggestions(predictions);

                });

            });

        }

        // SHOW CITY SUGGESTIONS
        function showProfileCitySuggestions(list) {

            let box = document.getElementById("profileCitySuggestions");
            box.innerHTML = "";
            box.style.display = "block";

            list.forEach(function(item) {

                if (!item.types.includes("locality")) return;

                let div = document.createElement("div");
                div.className = "suggestion-item";
                div.innerHTML = item.description;

                div.onclick = function() {
                    getProfilePlaceDetails(item.place_id);
                    box.innerHTML = "";
                }

                box.appendChild(div);

            });

        }

        // GET CITY DETAILS
        function getProfilePlaceDetails(placeId) {

            profilePlaceService.getDetails({
                placeId: placeId,
                fields: ["address_components"]
            }, function(place, status) {

                if (status !== "OK") return;

                let city = "";

                place.address_components.forEach(function(c) {

                    if (c.types.includes("locality")) {
                        city = c.long_name;
                    }

                });

                document.getElementById("profileCityInput").value = city;
                profileSelectedCity = city;

                // reset locality
                document.getElementById("profileLocalityInput").value = "";

            });

        }


        // LOCALITY SEARCH
        document.getElementById("profileLocalityInput").addEventListener("keyup", function() {

            let query = this.value;

            if (query.length < 2) return;

            profileService.getPlacePredictions({
                input: query,
                componentRestrictions: {
                    country: "in"
                }
            }, function(predictions) {

                if (!predictions) return;

                showProfileLocalitySuggestions(predictions);

            });

        });

        // SHOW LOCALITY
        function showProfileLocalitySuggestions(list) {

            let box = document.getElementById("profileLocalitySuggestions");
            box.innerHTML = "";
            box.style.display = "block";

            list.forEach(function(item) {

                if (
                    item.types.includes("sublocality") ||
                    item.types.includes("sublocality_level_1") ||
                    item.types.includes("neighborhood") ||
                    item.types.includes("premise")
                ) {

                    if (profileSelectedCity && item.description.includes(profileSelectedCity)) {

                        let div = document.createElement("div");
                        div.className = "suggestion-item";
                        div.innerHTML = item.description;

                        div.onclick = function() {

                            let parts = item.description.split(",");
                            let cleaned = [];

                            for (let i = 0; i < parts.length; i++) {

                                let p = parts[i].trim();

                                if (p === profileSelectedCity) break;

                                cleaned.push(p);
                            }

                            document.getElementById("profileLocalityInput").value = cleaned.join(", ");
                            box.innerHTML = "";

                        }

                        box.appendChild(div);
                    }

                }

            });

        }
    </script>
    <script>
        window.addEventListener("load", function() {

            if (typeof google !== "undefined") {
                initProfileCityAutocomplete();

                // preload selected city (important for locality filter)
                profileSelectedCity = document.getElementById("profileCityInput").value;
            }

        });
    </script>

</body>

</html>