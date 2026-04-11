<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "../web_api/includes/db_config.php";

/* ---------------------------------------------------------------
   AUTH CHECK
--------------------------------------------------------------- */
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

$user   = $_SESSION['user'];
$userid = $user['id'];
$profile_id = $user['profile_id'];


/* ---------------------------------------------------------------
   HELPER — build cURL handle with timeouts
--------------------------------------------------------------- */
function make_json_curl(string $url, array $payload): \CurlHandle
{
    $json = json_encode($payload);
    $ch   = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CONNECTTIMEOUT => 5,   // FIX: 5s connect timeout
        CURLOPT_TIMEOUT        => 10,  // FIX: 10s total timeout
    ]);
    return $ch;
}





/* ---------------------------------------------------------------
   HANDLE: LOGO UPLOAD (sent as multipart/form-data blob from JS)
--------------------------------------------------------------- */
if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {

    $file = $_FILES['company_logo']['tmp_name'];

    // FIX: validate file exists and is a real image BEFORE using $info
    if (!$file || !file_exists($file)) {
        echo json_encode(['status' => false, 'message' => 'Invalid file upload']);
        exit;
    }

    $info = getimagesize($file);   // FIX: actually assign $info here

    if ($info === false) {
        echo json_encode(['status' => false, 'message' => 'Invalid image file']);
        exit;
    }

    $width  = $info[0];
    $height = $info[1];
    $mime   = $info['mime'];

    // Build GD image source
    if ($mime === 'image/jpeg') {
        $src = imagecreatefromjpeg($file);
    } elseif ($mime === 'image/png') {
        $src = imagecreatefrompng($file);
    } else {
        echo json_encode(['status' => false, 'message' => 'Only JPG and PNG allowed']);
        exit;
    }

    // Crop square from center and resize to 512×512
    $size  = min($width, $height);
    $src_x = ($width  - $size) / 2;
    $src_y = ($height - $size) / 2;

    $dst = imagecreatetruecolor(512, 512);
    imagecopyresampled($dst, $src, 0, 0, $src_x, $src_y, 512, 512, $size, $size);

    $temp_file = sys_get_temp_dir() . "/logo_" . $profile_id . "_" . time() . ".png";
    imagepng($dst, $temp_file);
    imagedestroy($src);
    imagedestroy($dst);

    // FIX: include userid in the multipart upload
    $cfile = new CURLFile($temp_file, 'image/png', 'logo.png');

    $ch = curl_init("https://pacificconnect2.0.inv51.in/webservices/addRecruiter_logo.php");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'id'           => $profile_id,      // FIX: id was missing from JS fetch
            'company_logo' => $cfile,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,       // slightly longer for file upload
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("Logo upload cURL error: " . curl_error($ch));
    }

    curl_close($ch);

    // FIX: removed print_r + exit that blocked the upload
    if (file_exists($temp_file)) {
        unlink($temp_file);
    }

    // Return JSON so the JS fetch knows it's done
    header('Content-Type: application/json');
    echo $response ?: json_encode(['status' => false, 'message' => 'No response from API']);
    exit;
}


/* ---------------------------------------------------------------
   FETCH PROFILE + KYC IN PARALLEL  (FIX: curl_multi)
--------------------------------------------------------------- */
$ch_profile = make_json_curl(
    API_BASE_URL . "getRecruiter_profile.php",
    ["id" => $profile_id]
);

$ch_kyc = make_json_curl(
    API_BASE_URL . "getRecruiterkyclog.php",
    ["recruiter_id" => $profile_id]
);

$mh = curl_multi_init();
curl_multi_add_handle($mh, $ch_profile);
curl_multi_add_handle($mh, $ch_kyc);

do {
    $status = curl_multi_exec($mh, $running);
    if ($running) curl_multi_select($mh);
} while ($running > 0 && $status === CURLM_OK);

$response_profile = curl_multi_getcontent($ch_profile);
$response_kyc     = curl_multi_getcontent($ch_kyc);

curl_multi_remove_handle($mh, $ch_profile);
curl_multi_remove_handle($mh, $ch_kyc);
curl_multi_close($mh);

// Parse profile
$result       = json_decode($response_profile, true) ?? [];
$profile      = $result['data']         ?? [];
$subscription = $result['subscription'] ?? [];

$company_name           = $profile['organization_name']  ?? '';
$owner_name             = $profile['contact_person_name'] ?? '';
$designation            = $profile['designation']         ?? '';
$mobile                 = $profile['mobile_no']           ?? '';
$email                  = $profile['email']               ?? '';
$logo                   = $profile['company_logo']        ?? '';
$subscription_plan_name = $subscription['plan_name']      ?? '';
$subscription_valid_to  = $subscription['valid_to']       ?? '';

// Parse KYC
$kycResult = json_decode($response_kyc, true) ?? [];
$kycList   = $kycResult['data'] ?? [];


/* ---------------------------------------------------------------
   HANDLE: PROFILE TEXT UPDATE
--------------------------------------------------------------- */
if (isset($_POST['update_profile'])) {

    $ch = make_json_curl(API_BASE_URL . "updateRecruiter_profile.php", [
        "id" => $profile_id,

        "contact_person_name" => $_POST['contact_person_name'] ?? '',
        "organization_name"   => $company_name  ?? $_POST['organization_name'],
        "industry_type"       => $_POST['industry_type'] ?? '',
        "designation"         => $_POST['designation'] ?? '',
        "mobile_no"           => $mobile ?? $_POST['mobile_no'],
        "email"               => $_POST['email'] ?? '',
        "website"             => $_POST['website'] ?? '',
        "company_size"        => $_POST['company_size'] ?? '',
        "established_year"    => $_POST['established_year'] ?? '',

        // ✅ IMPORTANT MAPPING
        "city_id"             => $_POST['city_id'] ?? '',
        "locality_id"         => $_POST['locality_id'] ?? '',

        "address"             => $_POST['address'] ?? '',
    ]);

    curl_exec($ch);
    curl_close($ch);

    header("Location: my_profile.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Profile | Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <style>
        :root {
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
            --bronze: #cd7f32;
        }







        .text-danger {
            color: #d32f2f;
        }

        .text-danger:hover {
            color: #c62828;
            background: #ffebee;
        }

        .profile-container {
            max-width: 1200px;
            margin: 30px auto 60px;
            padding: 0 20px;
            flex: 1;
            width: 100%;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 30px;
            align-items: start;
        }

        .profile-card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            margin-bottom: 20px;
            border: 1px solid var(--border-light);
            padding: 25px;
            position: relative;
            width: 100%;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--text-dark);
        }

        .section-edit {
            color: var(--blue-btn);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            transition: 0.2s;
            padding: 4px 12px;
            border-radius: 20px;
            background: #f0f7ff;
        }

        .section-edit:hover {
            background: #e0edff;
        }

        .profile-top-row {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
        }

        .avatar-container {
            position: relative;
            width: 80px;
            height: 80px;
            flex-shrink: 0;
        }

        .user-avatar-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #eee;
        }

        .camera-badge {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--text-dark);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            border: 2px solid white;
            cursor: pointer;
        }

        .company-info h2 {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .company-info p {
            font-size: 0.9rem;
            color: var(--text-grey);
        }

        .subscription-section {
            padding-top: 20px;
            border-top: 1px solid var(--border-light);
        }

        .sub-box {
            background: #f0f7ff;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--blue-btn);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .plan-info {
            font-size: 0.95rem;
            line-height: 1.5;
            display: block;
            color: #333;
        }

        .plan-info b {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 1.05rem;
        }

        .btn-primary {
            width: 100%;
            background: var(--blue-btn);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1.05rem;
            cursor: pointer;
            transition: 0.2s;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            margin-bottom: 20px;
            text-align: center;
            display: block;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }

        .btn-outline {
            width: 100%;
            background: transparent;
            color: var(--blue-btn);
            border: 2px solid var(--blue-btn);
            padding: 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: 0.2s;
            margin-bottom: 10px;
            text-align: center;
            display: block;
        }

        .btn-outline:hover {
            background: var(--primary-light);
        }

        .help-list a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            color: var(--text-dark);
            font-weight: 500;
            font-size: 0.95rem;
            border-bottom: 1px solid #f5f5f5;
            transition: 0.2s;
        }

        .help-list a:hover {
            color: var(--primary);
            padding-left: 5px;
        }

        .help-list a:last-child {
            border-bottom: none;
        }

        .help-list a i {
            color: #ccc;
            font-size: 0.8rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 30px;
        }

        .info-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            font-size: 0.85rem;
            color: var(--text-grey);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 1.05rem;
            color: var(--text-dark);
            font-weight: 700;
            word-break: break-all;
        }

        .kyc-note {
            font-size: 0.9rem;
            color: var(--text-grey);
            margin-bottom: 20px;
            line-height: 1.5;
            background: #fffde7;
            padding: 12px 15px;
            border-radius: 8px;
            border-left: 3px solid #fbc02d;
        }

        .kyc-list {
            list-style: none;
        }

        .kyc-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 0;
            border-bottom: 1px solid #eee;
        }

        .kyc-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .doc-info {
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1rem;
        }

        .doc-icon {
            color: #4285f4;
            font-size: 1.4rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-weight: 700;
            font-size: 0.85rem;
            color: #333;
            background: #fff;
            white-space: nowrap;
        }

        .status-badge .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #000;
        }

        /* Upload feedback */
        #uploadFeedback {
            display: none;
            position: fixed;
            bottom: 90px;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            color: white;
            padding: 10px 24px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 600;
            z-index: 9999;
        }

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

        @media (max-width: 900px) {
            header {
                display: none;
            }

            .mobile-header {
                display: flex;
            }

            .profile-container {
                padding: 15px;
                margin: 0 auto;
                width: 100%;
            }

            .profile-grid {
                display: flex;
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
                width: 100%;
            }

            .profile-card {
                width: 100%;
            }

            .column-left,
            .column-right {
                display: contents;
            }

            .profile-card-logo {
                order: 1;
                margin-bottom: 0;
            }

            .profile-card-company-info {
                order: 2;
                margin-bottom: 0;
            }

            .profile-card-kyc {
                order: 3;
                margin-bottom: 0;
            }

            .btn-post-job {
                order: 4;
                margin-top: 10px;
                margin-bottom: 10px;
            }

            .profile-card-subscription {
                order: 5;
                margin-bottom: 0;
            }

            .profile-card-help {
                order: 6;
                margin-bottom: 30px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .info-group {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }

            .info-group:last-child {
                border-bottom: none;
                padding-bottom: 0;
            }

            .info-label {
                margin-bottom: 0;
            }

            .info-value {
                text-align: right;
                font-size: 1rem;
                max-width: 60%;
            }

            .kyc-item {
                flex-direction: row;
                gap: 10px;
            }

            .doc-info {
                font-size: 0.9rem;
            }

            .doc-info span {
                max-width: 180px;
            }

            .bottom-nav {
                display: flex;
            }

            body {
                padding-bottom: 80px;
            }
        }

        /* Modals */
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

        @media (min-width: 768px) {
            .modal-overlay {
                align-items: center;
            }

            .modal-content {
                border-radius: 16px;
                transform: translateY(20px);
            }
        }

        /* Cropper */
        .cropper-view-box,
        .cropper-face {
            border-radius: 50%;
        }

        .cropper-view-box {
            outline: 2000px solid rgba(0, 0, 0, 0.5);
        }

        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px 20px;
        }

        .modal-grid .input-group {
            margin-bottom: 0;
        }

        .modal-grid .full-width {
            grid-column: span 2;
        }

        /* Mobile */
        @media (max-width: 600px) {
            .modal-grid {
                grid-template-columns: 1fr;
            }

            .modal-grid .full-width {
                grid-column: span 1;
            }
        }

        /* readonly style */
        .modal-input[readonly] {
            background: #e5e7eb;
            color: #555;
            cursor: not-allowed;
        }
    </style>
</head>

<body>


    <?php include "includes/preloader.php"; ?>
    <?php include "includes/header.php"; ?>

    <!-- Crop Modal -->
    <div id="cropModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:white; padding:20px; border-radius:12px; width:400px; text-align:center;">
            <h3 style="margin-bottom:15px;">Crop Logo</h3>
            <div style="width:350px; height:350px; margin:auto; overflow:hidden;">
                <img id="cropImage" style="max-width:100%;">
            </div>
            <br>
            <button id="cropUpload" style="padding:10px 20px; background:#2563eb; color:white; border:none; border-radius:6px;">
                Crop &amp; Upload
            </button>
            <button onclick="closeCrop()" style="padding:10px 20px; margin-left:10px; border:1px solid #ccc; border-radius:6px; background:white;">
                Cancel
            </button>
        </div>
    </div>

    <!-- Upload feedback toast -->
    <div id="uploadFeedback">Uploading logo...</div>

    <div class="mobile-header">
        <i class="fas fa-arrow-left mobile-back" onclick="history.back()"></i>
        <span class="mobile-header-title">My Profile</span>
        <i class="fas fa-user-circle mobile-user"></i>
    </div>

    <div class="profile-container">
        <div class="profile-grid">

            <div class="column-left">

                <!-- Logo profile-card -->
                <div class="profile-card profile-card-logo">
                    <a href="#" class="section-edit" style="position:absolute; top:20px; right:25px;">Edit</a>
                    <div class="profile-top-row" style="margin-bottom:0;">
                        <div class="avatar-container">
                            <img id="logoPreview"
                                src="<?= !empty($logo) ? htmlspecialchars($logo) : '/assets/default-logo.png' ?>"
                                class="user-avatar-img">
                            <input type="file" id="logoUpload" accept="image/png,image/jpeg" hidden>
                            <div class="camera-badge" onclick="document.getElementById('logoUpload').click()">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        <div class="company-info">
                            <h5><?= htmlspecialchars($company_name) ?></h5>
                            <p>Company Logo</p>
                        </div>
                    </div>
                </div>

                <!-- Subscription profile-card -->
                <div class="profile-card profile-card-subscription">
                    <div class="section-header" style="margin-bottom:15px;">
                        <span class="section-title" style="font-size:1.15rem;">Your Subscription</span>
                    </div>
                    <div class="sub-box">
                        <div>
                            <span class="plan-info">Plan: <b><?= htmlspecialchars($subscription_plan_name) ?></b></span>
                            <span class="plan-info">Valid Till: <b><?= htmlspecialchars($subscription_valid_to) ?></b></span>
                        </div>
                        <!-- <i class="fas fa-crown" style="color:var(--bronze); font-size:2rem;"></i> -->
                    </div>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <a href="upgrade.php" class="btn-primary" style="margin:0;">
                            Upgrade Now
                        </a>

                        <a href="transactions.php" class="btn-outline" style="margin:0;">
                            Transaction History
                        </a>
                    </div>
                </div>

                <a href="post-job.php" class="btn-primary btn-post-job" style="padding:16px; font-size:1.1rem;">
                    Start Job Posting
                </a>
                <!-- Help profile-card -->
                <div class="profile-card profile-card-help">
                    <div class="section-header" style="margin-bottom:10px;">
                        <span class="section-title" style="font-size:1.15rem;">Need Help?</span>
                    </div>
                    <div class="help-list">
                        <a href="change_password.php">Change Password <i class="fas fa-chevron-right"></i></a>
                        <a href="#">Contact us <i class="fas fa-chevron-right"></i></a>
                        <a href="#">Frequently Asked Questions <i class="fas fa-chevron-right"></i></a>
                        <a href="#">Terms and Conditions <i class="fas fa-chevron-right"></i></a>
                        <a href="#">Privacy Policy <i class="fas fa-chevron-right"></i></a>
                    </div>
                </div>

            </div>

            <div class="column-right">

                <!-- Company Info profile-card -->
                <div class="profile-card profile-card-company-info">
                    <div class="section-header">
                        <span class="section-title">Company Info</span>
                        <a href="#" class="section-edit" onclick="openModal('editOwnerModal'); return false;">Edit</a>
                    </div>
                    <div class="info-grid">
                        <div class="info-group">
                            <span class="info-label">Owner Name</span>
                            <span class="info-value"><?= htmlspecialchars($owner_name) ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Organization Name</span>
                            <span class="info-value"><?= htmlspecialchars($profile['organization_name'] ?? '') ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Industry Type</span>
                            <span class="info-value"><?= htmlspecialchars($profile['industry_type'] ?? '-') ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Designation</span>
                            <span class="info-value"><?= htmlspecialchars($designation) ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Phone Number</span>
                            <span class="info-value"><?= htmlspecialchars($mobile) ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Email Address</span>
                            <span class="info-value"><?= htmlspecialchars($email) ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Website</span>
                            <span class="info-value"><?= htmlspecialchars($profile['website'] ?? '-') ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">No. of Employee</span>
                            <span class="info-value"><?= htmlspecialchars($profile['company_size'] ?? '-') ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Established in Year</span>
                            <span class="info-value"><?= htmlspecialchars($profile['established_year'] ?? '-') ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">District/Tehsil/City</span>
                            <span class="info-value"><?= htmlspecialchars($profile['city_id'] ?? '-') ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Area/Locality/Village</span>
                            <span class="info-value"><?= htmlspecialchars($profile['locality_id'] ?? '-') ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Address</span>
                            <span class="info-value"><?= htmlspecialchars($profile['address'] ?? '-') ?></span>
                        </div>
                    </div>
                </div>

                <!-- KYC profile-card -->
                <div class="profile-card profile-card-kyc">
                    <div class="section-header">
                        <span class="section-title">KYC Status</span>
                        <a href="kyc_upload.php" class="section-edit">Edit Documents</a>
                    </div>
                    <p class="kyc-note">
                        <i class="fas fa-info-circle"></i> Note: Click on Verification Pending or Verified status to view the submitted document.
                    </p>
                    <ul class="kyc-list">
                        <?php foreach ($kycList as $kyc):
                            $docName    = $kyc['kycdoctype_name']  ?? '';
                            $statusName = $kyc['kycstatus_name']   ?? '';
                            $statusColor = $kyc['kycstatus_color'] ?? '#999';
                            $docUrl     = $kyc['docurl']           ?? '';
                        ?>
                            <li class="kyc-item">
                                <div class="doc-info">
                                    <i class="fas fa-file-alt doc-icon"></i>
                                    <span><?= htmlspecialchars($docName) ?></span>
                                </div>
                                <div class="status-badge" style="border-color:<?= $statusColor ?>">
                                    <span class="dot" style="background:<?= $statusColor ?>; border-color:<?= $statusColor ?>"></span>
                                    <?php if (!empty($docUrl)): ?>
                                        <a href="<?= htmlspecialchars($docUrl) ?>" target="_blank" style="text-decoration:none; color:inherit;">
                                            <?= htmlspecialchars($statusName) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($statusName) ?>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            </div>
        </div>
    </div>

    <!-- Edit Company Info Modal -->
    <div class="modal-overlay" id="editOwnerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Company Info</h3>
                <i class="fas fa-times close-modal" onclick="closeModal('editOwnerModal')"></i>
            </div>
            <form method="POST">
                <input type="hidden" name="id" value="<?= $userid ?>">

                <div class="modal-grid">

                    <div class="input-group">
                        <label class="input-label">Owner Name</label>
                        <input type="text" name="contact_person_name" class="modal-input"
                            value="<?= htmlspecialchars($owner_name) ?>">
                    </div>

                    <div class="input-group">
                        <label class="input-label">Organization</label>
                        <input type="text" name="organization_name" class="modal-input"
                            value="<?= htmlspecialchars($profile['organization_name'] ?? '') ?>" readonly>
                    </div>

                    <div class="input-group">
                        <label class="input-label">Industry</label>
                        <input type="text" name="industry_type" class="modal-input"
                            value="<?= htmlspecialchars($profile['industry_type'] ?? '') ?>">
                    </div>

                    <div class="input-group">
                        <label class="input-label">Designation</label>
                        <input type="text" name="designation" class="modal-input"
                            value="<?= htmlspecialchars($designation) ?>">
                    </div>

                    <div class="input-group">
                        <label class="input-label">Phone</label>
                        <input type="text" name="mobile_no" class="modal-input"
                            value="<?= htmlspecialchars($mobile) ?>" readonly>
                    </div>

                    <div class="input-group">
                        <label class="input-label">Email</label>
                        <input type="email" name="email" class="modal-input"
                            value="<?= htmlspecialchars($email) ?>">
                    </div>

                    <div class="input-group">
                        <label class="input-label">Website</label>
                        <input type="text" name="website" class="modal-input"
                            value="<?= htmlspecialchars($profile['website'] ?? '') ?>">
                    </div>

                    <div class="input-group">
                        <label class="input-label">Company Size</label>
                        <input type="text" name="company_size" class="modal-input"
                            value="<?= htmlspecialchars($profile['company_size'] ?? '') ?>">
                    </div>

                    <div class="input-group">
                        <label class="input-label">Established</label>
                        <input type="text" name="established_year" class="modal-input"
                            value="<?= htmlspecialchars($profile['established_year'] ?? '') ?>">
                    </div>

                    <!-- ✅ IMPORTANT -->
                    <div class="input-group">
                        <label class="input-label">District</label>
                        <input type="text" id="profileDistrictInput" class="modal-input" autocomplete="off"
                            value="<?= htmlspecialchars($profile['district'] ?? '') ?>">

                        <input type="hidden" name="district" id="profileDistrictId">

                        <div id="profileDistrictSuggestions" class="suggestion-box"></div>
                    </div>


                    <div class="input-group">
                        <label class="input-label">City</label>
                        <input type="text" id="profileCityInput" class="modal-input" autocomplete="off"
                            value="<?= htmlspecialchars($profile['city_id'] ?? '') ?>">

                        <input type="hidden" name="city_id" id="profileCityId">

                        <div id="profileCitySuggestions" class="suggestion-box"></div>
                    </div>

                    <div class="input-group">
                        <label class="input-label">Locality</label>
                        <input type="text" id="profileLocalityInput" class="modal-input" autocomplete="off"
                            value="<?= htmlspecialchars($profile['locality_id'] ?? '') ?>">

                        <input type="hidden" name="locality_id" id="profileLocalityId">

                        <div id="profileLocalitySuggestions" class="suggestion-box"></div>
                    </div>

                    <div class="input-group full-width">
                        <label class="input-label">Address</label>
                        <input type="text" name="address" class="modal-input"
                            value="<?= htmlspecialchars($profile['address'] ?? '') ?>">
                    </div>

                </div>

                <div class="modal-btn-row">
                    <button type="button" class="btn-modal-cancel" onclick="closeModal('editOwnerModal')">Cancel</button>
                    <button type="submit" name="update_profile" class="btn-modal-save">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bottom Nav -->
    <div class="bottom-nav">
        <a href="index.php" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-home"></i></div>
            Home
        </a>
        <a href="post-job.php" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-file-alt"></i></div>
            Post Jobs
        </a>
        <a href="applications.php" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-user-friends"></i></div>
            Applications
        </a>
        <a href="my_profile.php" class="nav-icon active">
            <div class="icon-wrap"><i class="fas fa-user"></i></div>
            Profile
        </a>
    </div>

    <script>
        window.onload = () => document.getElementById("global-preloader")?.remove();

        /* ---- Cropper / Logo Upload ---- */
        let cropper;

        document.getElementById("logoUpload").addEventListener("change", function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(event) {
                const image = document.getElementById("cropImage");
                image.src = event.target.result;

                document.getElementById("cropModal").style.display = "flex";

                if (cropper) cropper.destroy();

                cropper = new Cropper(image, {
                    aspectRatio: 1,
                    viewMode: 1,
                    dragMode: "move",
                    cropBoxResizable: false,
                    cropBoxMovable: false,
                    guides: false,
                    center: false,
                    highlight: false
                });
            };
            reader.readAsDataURL(file);
        });

        document.getElementById("cropUpload").addEventListener("click", function() {

            const canvas = cropper.getCroppedCanvas({
                width: 512,
                height: 512
            });

            canvas.toBlob(function(blob) {

                // FIX: include id in the FormData so PHP receives it
                const formData = new FormData();
                formData.append("company_logo", blob, "logo.png");
                // id is embedded server-side via session; no need to send from JS
                // (PHP already uses $userid from session)

                // Show feedback
                const feedback = document.getElementById("uploadFeedback");
                feedback.style.display = "block";
                feedback.innerText = "Uploading...";

                closeCrop();

                fetch(window.location.href, {
                        method: "POST",
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status) {

                            // ✅ instantly update preview using cropped image
                            const previewURL = URL.createObjectURL(blob);
                            document.getElementById("logoPreview").src = previewURL;

                            feedback.innerText = "Logo updated!";
                        } else {
                            feedback.innerText = data.message || "Upload failed";
                        }

                        setTimeout(() => feedback.style.display = "none", 3000);
                    })
                    .catch(() => {
                        feedback.innerText = "Upload error. Try again.";
                        setTimeout(() => feedback.style.display = "none", 3000);
                    });

            }, "image/png");
        });

        function closeCrop() {
            document.getElementById("cropModal").style.display = "none";
        }

        /* ---- Modals ---- */
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('active');
            });
        });
    </script>
    <script>
        let profileService;
        let profilePlaceService;

        let profileSelectedCity = "";
        let profileSelectedDist = "";
        let profileSelectedState = "";
        let profileSelectedCountry = "";

        // INIT
        function initProfileCityAutocomplete() {

            profileService = new google.maps.places.AutocompleteService();
            profilePlaceService = new google.maps.places.PlacesService(document.createElement('div'));

            const input = document.getElementById("profileCityInput");

            input.addEventListener("keyup", function() {

                let query = input.value;

                if (query.length < 2) return;

                profileService.getPlacePredictions({
                    input: query,
                    types: ["(cities)"], // ✅ ONLY CITY
                    componentRestrictions: {
                        country: "in"
                    }
                }, function(predictions) {

                    if (!predictions) return;

                    showProfileCitySuggestions(predictions);
                });
            });
        }



        // SHOW DISTRICT

        document.getElementById("profileDistrictInput").addEventListener("keyup", function() {

            let query = this.value;

            if (query.length < 2) return;

            profileService.getPlacePredictions({
                    input: query,
                    types: ["(cities)"], // ✅ ONLY CITY
                    componentRestrictions: {
                        country: "in"
                    }
                },
                function(predictions) {

                    if (!predictions) return;

                    showProfileDistrictSuggestions(predictions);

                });
        });


        function showProfileDistrictSuggestions(list) {

            let box = document.getElementById("profileDistrictSuggestions");
            box.innerHTML = "";

            list.forEach(function(item) {

                let div = document.createElement("div");
                div.className = "suggestion-item";
                div.innerHTML = item.description;

                div.onclick = function() {

                    document.getElementById("profileDistrictInput").value = item.description;
                    document.getElementById("profileDistrictId").value = item.description;

                    
                    profileSelectedDist = item.description;

                    box.innerHTML = "";
                }

                box.appendChild(div);
            });

            box.style.display = "block";
        }


        // SHOW CITY
        function showProfileCitySuggestions(list) {

            let box = document.getElementById("profileCitySuggestions");
            box.innerHTML = "";
            box.style.display = "block";

            list.forEach(function(item) {

                let div = document.createElement("div");
                div.className = "suggestion-item";
                div.innerHTML = item.description;

                div.onclick = function() {

                    document.getElementById("profileCityInput").value = item.description;
                    document.getElementById("profileCityId").value = item.description;

                    profileSelectedCity = item.description;

                    box.innerHTML = "";
                }

                box.appendChild(div);
            });
        }

        // LOCALITY
        document.getElementById("profileLocalityInput").addEventListener("keyup", function() {

            let query = this.value;

            if (query.length < 2) return;

            profileService.getPlacePredictions({
                input: query,
                types: ["(cities)"], // ✅ ONLY cities
                componentRestrictions: {
                    country: "in"
                }
            }, function(predictions) {

                if (!predictions) return;

                showProfileCitySuggestions(predictions);
            });
        });

        // SHOW LOCALITY (FILTER BY CITY)
        function showProfileLocalitySuggestions(list) {

            let box = document.getElementById("profileLocalitySuggestions");
            box.innerHTML = "";

            list.forEach(function(item) {

                if (
                    item.description.toLowerCase().includes(profileSelectedCity.toLowerCase())
                ) {

                    let div = document.createElement("div");
                    div.className = "suggestion-item";
                    div.innerHTML = item.description;

                    div.onclick = function() {

                        document.getElementById("profileLocalityInput").value = item.description;
                        document.getElementById("profileLocalityId").value = item.description;

                        box.innerHTML = "";
                    }

                    box.appendChild(div);
                }
            });

            box.style.display = "block";
        }

        // INIT CALL
        document.addEventListener("DOMContentLoaded", function() {
            initProfileCityAutocomplete();
        });
    </script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key=
    AIzaSyCokcdTmQxRaopu75ourz-nNmZNie1wQkY&libraries=places&callback=initProfileCityAutocomplete"
        async defer></script>

</body>

</html>