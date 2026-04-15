<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
require_once 'includes/initialize.php'; // DB connection

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST method allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$profile_id      = isset($data['profile_id']) ? intval($data['profile_id']) : 0;
$profile_type_id = isset($data['profile_type_id']) ? intval($data['profile_type_id']) : 0; // 1=Recruiter, 2=Candidate, 3=Promoter

if ($profile_id <= 0 || $profile_type_id <= 0) {
    echo json_encode(["status" => false, "message" => "profile_id and profile_type_id are required"]);
    exit;
}

// Whitelist table by profile type
$table_map = [
    1 => 'jos_app_recruiter_profile',
    2 => 'jos_app_candidate_profile',
    3 => 'jos_app_promoter_profile'
];

if (!isset($table_map[$profile_type_id])) {
    echo json_encode(["status" => false, "message" => "Unsupported profile_type_id"]);
    exit;
}

$table = $table_map[$profile_type_id];

// Fetch PAN
$sql = "SELECT pan_no FROM {$table} WHERE id = ? LIMIT 1";
$stmt = $con->prepare($sql);

if (!$stmt) {
    echo json_encode(["status" => false, "message" => "Prepare failed"]);
    exit;
}

$stmt->bind_param("i", $profile_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo json_encode(["status" => false, "message" => "Profile not found"]);
    $stmt->close();
    $con->close();
    exit;
}

$row = $result->fetch_assoc();
$pan_no = isset($row['pan_no']) ? trim($row['pan_no']) : '';

if ($pan_no === '') {
    echo json_encode(["status" => false, "message" => "PAN not set for this profile", "pan_no" => ""]);
} else {
    echo json_encode(["status" => true, "message" => "PAN fetched successfully", "pan_no" => $pan_no]);
}

$stmt->close();
$con->close();
