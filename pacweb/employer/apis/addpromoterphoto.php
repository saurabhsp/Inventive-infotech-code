<?php
include("includes/initialize.php");
header('Content-Type: application/json');

// Get userid from POST (this is jos_app_users.id)
$userid = isset($_POST['userid']) ? intval($_POST['userid']) : 0;
if ($userid <= 0) {
    echo json_encode(["status" => false, "message" => "Invalid userid"]);
    exit;
}

// Fetch profile_id from jos_app_users
$stmt = $con->prepare("SELECT profile_id FROM jos_app_users WHERE id = ?");
$stmt->bind_param("i", $userid);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo json_encode(["status" => false, "message" => "User not found"]);
    exit;
}
$row = $result->fetch_assoc();
$profile_id = intval($row['profile_id']);

if ($profile_id <= 0) {
    echo json_encode(["status" => false, "message" => "Invalid profile_id in user record"]);
    exit;
}

// Validate uploaded file
if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["status" => false, "message" => "No profile photo uploaded or upload error"]);
    exit;
}

// File setup
$ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
$filename = "profile_" . $profile_id . "." . strtolower($ext);
$relative_path = "webservices/uploads/profilephoto/" . $filename;
$absolute_path = $_SERVER['DOCUMENT_ROOT'] . "/" . $relative_path;

// Ensure folder exists
$folder_path = $_SERVER['DOCUMENT_ROOT'] . "/webservices/uploads/profilephoto/";
if (!is_dir($folder_path)) {
    mkdir($folder_path, 0777, true);
}

// Move uploaded file
if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $absolute_path)) {
    // Update DB
    $update_stmt = $con->prepare("UPDATE jos_app_promoter_profile SET profile_photo = ? WHERE id = ?");
    $update_stmt->bind_param("si", $relative_path, $profile_id);

    if ($update_stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Profile photo uploaded successfully",
            "photo_url" => DOMAIN_URL . $relative_path
        ]);
    } else {
        echo json_encode(["status" => false, "message" => "Photo uploaded but DB update failed"]);
    }
} else {
    echo json_encode(["status" => false, "message" => "Failed to move uploaded file"]);
}
?>
