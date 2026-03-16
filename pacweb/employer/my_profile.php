<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();



$user = $_SESSION['user'] ?? null;
$userid = $user['id'] ?? 0;
$userid = 166;




if (isset($_POST['update_profile'])) {

    $api_url = "https://pacweb.inv11.in/web_api/updateRecruiter_profile.php";

    $post_data = [
        "id" => $userid,
        "contact_person_name" => $_POST['contact_person_name'],
        "designation" => $_POST['designation'],
        "mobile_no" => $_POST['mobile_no'],
        "email" => $_POST['email']
    ];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));

    $response = curl_exec($ch);

    curl_close($ch);

    header("Location: my_profile.php");
    exit;
}







if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {

    $file = $_FILES['company_logo']['tmp_name'];

    // if (!$file || !file_exists($file)) {
    //     die("Invalid file upload");
    // }

    // $info = getimagesize($file);

    // if ($info === false) {
    //     die("Invalid image");
    // }

    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'];

    // Create image source
    if ($mime == "image/jpeg") {
        $src = imagecreatefromjpeg($file);
    } elseif ($mime == "image/png") {
        $src = imagecreatefrompng($file);
    } else {
        die("Only JPG and PNG allowed");
    }

    // Crop square from center
    $size = min($width, $height);
    $src_x = ($width - $size) / 2;
    $src_y = ($height - $size) / 2;

    $dst = imagecreatetruecolor(512, 512);

    imagecopyresampled(
        $dst,
        $src,
        0,
        0,
        $src_x,
        $src_y,
        512,
        512,
        $size,
        $size
    );

    // Save temp image
    $temp_file = sys_get_temp_dir() . "/logo_" . time() . ".png";

    imagepng($dst, $temp_file);

    imagedestroy($src);
    imagedestroy($dst);

    // Prepare CURL upload
    $cfile = new CURLFile($temp_file, 'image/png', 'logo.png');

    $post_data = [
        "id" => $userid,
        "company_logo" => $cfile
    ];

    print_r( $post_data);
    exit;

    $ch = curl_init("https://pacweb.inv11.in/web_api/addRecruiter_logo.php");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

    $response = curl_exec($ch);

    print_r( $response);
    exit;

    if (curl_errno($ch)) {
        echo curl_error($ch);
    }

    curl_close($ch);

    unlink($temp_file);

    echo "<script>alert('Logo Uploaded Successfully');window.location.reload();</script>";
}
















$api_url = "https://pacweb.inv11.in/web_api/getRecruiter_profile.php";
$data = [
    "id" => $userid
];

$ch = curl_init($api_url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo curl_error($ch);
}

curl_close($ch);

$result = json_decode($response, true);


$profile = isset($result['data']) ? $result['data'] : [];
$subscription = isset($result['subscription']) ? $result['subscription'] : [];

$company_name = $profile['organization_name'] ?? '';
$owner_name = $profile['contact_person_name'] ?? '';
$designation = $profile['designation'] ?? '';
$mobile = $profile['mobile_no'] ?? '';
$email = $profile['email'] ?? '';
$logo = $profile['company_logo'] ?? '';

$subscription_plan_name = $subscription['plan_name'] ?? '';
$subscription_valid_to = $subscription['valid_to'] ?? '';







/* =========================
   GET KYC DOCUMENT STATUS
========================= */

$kyc_api = "https://pacweb.inv11.in/web_api/getRecruiterkyclog.php";

$kycData = [
    "recruiter_id" => $userid
];

$ch = curl_init($kyc_api);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($kycData));

$kycResponse = curl_exec($ch);

if (curl_errno($ch)) {
    echo curl_error($ch);
}

curl_close($ch);

$kycResult = json_decode($kycResponse, true);

$kycList = $kycResult['data'] ?? [];
?>


<!-- Update recruiter profile section  -->
 <?php
    // if (isset($_POST['update_profile'])) {

    //     $api_url = "https://pacweb.inv11.in/web_api/updateRecruiter_profile.php";

    //     $post_data = [
    //         "id" => $userid,
    //         "contact_person_name" => $_POST['contact_person_name'],
    //         "designation" => $_POST['designation'],
    //         "mobile_no" => $_POST['mobile_no'],
    //         "email" => $_POST['email']
    //     ];

    //     $ch = curl_init();

    //     curl_setopt($ch, CURLOPT_URL, $api_url);
    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //     curl_setopt($ch, CURLOPT_POST, true);
    //     curl_setopt($ch, CURLOPT_HTTPHEADER, [
    //         "Content-Type: application/json"
    //     ]);
    //     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));

    //     $response = curl_exec($ch);

    //     if(curl_errno($ch)){
    //         echo curl_error($ch);
    //         exit;
    //     }

    //     curl_close($ch);

    //     $result = json_decode($response, true);

    //     // header("Location: my_profile.php");
    //         header("Location: my_profile.php");
    //     exit;
    // }
    // 
    ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Profile | Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
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

        .location-pin {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--primary);
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 8px;
            transition: 0.2s;
        }

        .location-pin:hover {
            background: var(--primary-light);
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

        .text-danger {
            color: #d32f2f;
        }

        .text-danger:hover {
            color: #c62828;
            background: #ffebee;
        }

        /* --- 2. LAYOUT GRID --- */
        .container {
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

        .card {
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

        /* --- 3. LEFT COLUMN (Logo + Subscription) --- */
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

        /* Subscription embedded in Left Panel */
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

        /* Action Buttons */
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

        /* Help Links */
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


        /* --- 4. RIGHT COLUMN (Owner Info & KYC) --- */

        /* Owner Info Grid */
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

        /* KYC Card */
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

        /* Status Badges */
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

        .status-review .dot {
            background: #ffb300;
        }

        /* Yellow for review */
        .status-verified .dot {
            background: #4caf50;
        }

        /* Green for verified */
        .status-rejected .dot {
            background: #e53935;
        }

        /* Red for rejected */


        /* --- 5. MOBILE RESPONSIVE TWEAKS --- */
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

            .container {
                padding: 15px;
                margin: 0 auto;
                width: 100%;
            }

            /* FIXED: Use align-items: stretch so all cards take 100% width and don't shrink wrap */
            .profile-grid {
                display: flex;
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
                width: 100%;
            }

            .card {
                width: 100%;
            }

            /* Ensure cards span full width */

            /* Order changes for Mobile Flow (Logo -> Company Info -> KYC -> Post Job -> Sub -> Help) */
            .column-left,
            .column-right {
                display: contents;
            }

            .card-logo {
                order: 1;
                margin-bottom: 0;
            }

            .card-company-info {
                order: 2;
                margin-bottom: 0;
            }

            .card-kyc {
                order: 3;
                margin-bottom: 0;
            }

            .btn-post-job {
                order: 4;
                margin-top: 10px;
                margin-bottom: 10px;
            }

            .card-subscription {
                order: 5;
                margin-bottom: 0;
            }

            .card-help {
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

            /* Prevent text overflow on mobile */

            .bottom-nav {
                display: flex;
            }

            body {
                padding-bottom: 80px;
            }
        }

        /* --- 6. MODALS --- */
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
            /* Bottom sheet by default */
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

            /* Center modal on desktop */
            .modal-content {
                border-radius: 16px;
                transform: translateY(20px);
            }
        }

        /* //croper code */
        .cropper-view-box,
.cropper-face {
    border-radius: 50%;
}

.cropper-view-box {
    outline: 2000px solid rgba(0,0,0,0.5);
}
        .user-avatar-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>

<body>

    <?php
    include "includes/header.php";
    ?>


<!-- modal for upload image -->
 <div id="cropModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; padding:20px; border-radius:12px; width:400px; text-align:center;">
        <h3>Crop Logo</h3>
        <div style="width:350px;height:350px;margin:auto;">
    <img id="cropImage" style="max-width:100%;">
</div>
        <br>
        <button id="cropUpload" style="padding:10px 20px;background:#2563eb;color:white;border:none;border-radius:6px;">
            Crop & Upload
        </button>
        <button onclick="closeCrop()" style="padding:10px 20px;margin-left:10px;">
            Cancel
        </button>
    </div>
</div>







    <div class="mobile-header">
        <i class="fas fa-arrow-left mobile-back"></i>
        <span class="mobile-header-title">My Profile</span>
        <i class="fas fa-user-circle mobile-user"></i>
    </div>

    <div class="container">
        <div class="profile-grid">

            <div class="column-left">

                <div class="card card-logo">

                    <form method="POST" enctype="multipart/form-data">

                        <a href="#" class="section-edit" style="position: absolute; top: 20px; right: 25px;">Edit</a>
                        <div class="profile-top-row" style="margin-bottom: 0;">
                            <div class="avatar-container">

                                <img src="<?= !empty($logo) ? htmlspecialchars($logo) : '/assets/default-logo.png' ?>"
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

                    </form>

                </div>

                <div class="card card-subscription">
                    <div class="section-header" style="margin-bottom: 15px;">
                        <span class="section-title" style="font-size: 1.15rem;">Your Subscription</span>
                    </div>

                    <div class="sub-box">
                        <div>
                            <span class="plan-info">Plan: <b><?= htmlspecialchars($subscription_plan_name) ?></b></span>
                            <span class="plan-info">Valid Till: <b><?= htmlspecialchars($subscription_valid_to) ?></b></span>
                        </div>
                        <i class="fas fa-crown" style="color:var(--bronze); font-size: 2rem;"></i>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <button class="btn-primary" style="margin: 0;">Upgrade Now</button>
                        <button class="btn-outline" style="margin: 0;">Transaction History</button>
                    </div>
                </div>

                <button class="btn-primary btn-post-job" style="padding: 16px; font-size: 1.1rem;">Start Job Posting</button>

                <div class="card card-help">
                    <div class="section-header" style="margin-bottom: 10px;">
                        <span class="section-title" style="font-size: 1.15rem;">Need Help?</span>
                    </div>
                    <div class="help-list">
                        <a href="#">Change Password <i class="fas fa-chevron-right"></i></a>
                        <a href="#">Contact us <i class="fas fa-chevron-right"></i></a>
                        <a href="#">Frequently Asked Questions <i class="fas fa-chevron-right"></i></a>
                        <a href="#">Terms and Conditions <i class="fas fa-chevron-right"></i></a>
                        <a href="#">Privacy Policy <i class="fas fa-chevron-right"></i></a>
                    </div>
                </div>

            </div>

            <div class="column-right">

                <div class="card card-company-info">
                    <div class="section-header">
                        <span class="section-title">Company Info</span>
                        <a href="#" class="section-edit" onclick="openModal('editOwnerModal')">Edit</a>
                    </div>

                    <div class="info-grid">
                        <div class="info-group">
                            <span class="info-label">Owner Name</span>
                            <span class="info-value"><?= htmlspecialchars($owner_name) ?></span>
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
                    </div>
                </div>

                <div class="card card-kyc">
                    <div class="section-header">
                        <span class="section-title">KYC Status</span>
                        <a href="kyc_upload.php" class="section-edit">Edit Documents</a>
                    </div>

                    <p class="kyc-note">
                        <i class="fas fa-info-circle"></i> Note: Click on Verification Pending or Verified status of the document to view the submitted document.
                    </p>

                    <ul class="kyc-list">

                        <?php foreach ($kycList as $kyc) {

                            $docName = $kyc['kycdoctype_name'] ?? '';
                            $statusName = $kyc['kycstatus_name'] ?? '';
                            $statusColor = $kyc['kycstatus_color'] ?? '#999';
                            $docUrl = $kyc['docurl'] ?? '';

                        ?>

                            <li class="kyc-item">

                                <div class="doc-info">
                                    <i class="fas fa-file-alt doc-icon"></i>
                                    <span><?= htmlspecialchars($docName) ?></span>
                                </div>

                                <div class="status-badge" style="border-color:<?= $statusColor ?>">

                                    <span class="dot" style="background:<?= $statusColor ?>;border-color:<?= $statusColor ?>"></span>

                                    <?php if (!empty($docUrl)) { ?>
                                        <a href="<?= htmlspecialchars($docUrl) ?>" target="_blank" style="text-decoration:none;color:inherit;">
                                            <?= htmlspecialchars($statusName) ?>
                                        </a>
                                    <?php } else { ?>
                                        <?= htmlspecialchars($statusName) ?>
                                    <?php } ?>

                                </div>

                            </li>

                        <?php } ?>

                    </ul>
                </div>

            </div>
        </div>
    </div>


    <div class="modal-overlay" id="locationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Your Current Location</h3>
            </div>
            <div class="input-group">
                <label class="input-label">Where are you currently staying?</label>
                <input type="text" class="modal-input" value="Satara">
            </div>
            <div class="input-group">
                <label class="input-label">Select Locality</label>
                <input type="text" class="modal-input" value="Ravivar Peth">
            </div>
            <div class="input-group">
                <label class="input-label">Pin Code</label>
                <input type="text" class="modal-input" value="415001" placeholder="Enter Pin Code">
            </div>
            <div class="modal-btn-row">
                <button class="btn-modal-cancel" onclick="closeModal('locationModal')">Cancel</button>
                <button class="btn-modal-save" onclick="closeModal('locationModal')">Update</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="editOwnerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Company Info</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="id" value="<?= $userid ?>">

                <div class="input-group">
                    <label class="input-label">Owner Name</label>
                    <input type="text" name="contact_person_name" class="modal-input" value="<?= htmlspecialchars($owner_name) ?>">
                </div>

                <div class="input-group">
                    <label class="input-label">Designation</label>
                    <input type="text" name="designation" class="modal-input" value="<?= htmlspecialchars($designation) ?>">
                </div>

                <div class="input-group">
                    <label class="input-label">Phone Number</label>
                    <input type="text" name="mobile_no" class="modal-input" value="<?= htmlspecialchars($mobile) ?>">
                </div>

                <div class="input-group">
                    <label class="input-label">Email Address</label>
                    <input type="email" name="email" class="modal-input" value="<?= htmlspecialchars($email) ?>">
                </div>

                <div class="modal-btn-row">
                    <button type="button" class="btn-modal-cancel" onclick="closeModal('editOwnerModal')">Cancel</button>
                    <button type="submit" name="update_profile" class="btn-modal-save">Save Changes</button>
                </div>

            </form>
        </div>
    </div>


    <div class="bottom-nav">
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-home"></i></div>
            Home
        </a>
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-file-alt"></i></div>
            Post Jobs
        </a>
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-user-friends"></i></div>
            Applications
        </a>
        <a href="#" class="nav-icon active">
            <div class="icon-wrap"><i class="fas fa-user"></i></div>
            Profile
        </a>
    </div>

    <script>

let cropper;
let selectedFile;

document.getElementById("logoUpload").addEventListener("change", function(e){

    const file = e.target.files[0];

    if(!file) return;

    const reader = new FileReader();

    reader.onload = function(event){

        const image = document.getElementById("cropImage");
        image.src = event.target.result;

        document.getElementById("cropModal").style.display = "flex";

        if(cropper) cropper.destroy();

        cropper = new Cropper(image,{
    aspectRatio: 1,
    viewMode: 1,
    dragMode: "move",
    cropBoxResizable: false,
    cropBoxMovable: false,
    guides:false,
    center:false,
    highlight:false
});

    }

    reader.readAsDataURL(file);

});


document.getElementById("cropUpload").addEventListener("click", function(){

    const canvas = cropper.getCroppedCanvas({
        width:512,
        height:512
    });

    canvas.toBlob(function(blob){

        let formData = new FormData();
        formData.append("company_logo", blob, "logo.png");

        fetch(window.location.href,{
            method:"POST",
            body:formData
        })
        .then(res=>location.reload());

    });

});


function closeCrop(){
    document.getElementById("cropModal").style.display="none";
}

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modal when clicking on the dark overlay background
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>

</html>