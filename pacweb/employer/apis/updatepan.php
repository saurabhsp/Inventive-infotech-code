<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
require_once 'includes/initialize.php'; // DB connection ($con)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST method allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$profile_id       = isset($data['profile_id']) ? intval($data['profile_id']) : 0;
$profile_type_id  = isset($data['profile_type_id']) ? intval($data['profile_type_id']) : 0; // 1=Recruiter, 2=Candidate, 3=Promoter
$pan_no           = isset($data['pan_no']) ? trim($data['pan_no']) : '';

if ($profile_id <= 0 || $profile_type_id <= 0 || $pan_no === '') {
    echo json_encode(["status" => false, "message" => "profile_id, profile_type_id and pan_no are required"]);
    exit;
}

// Normalize PAN: uppercase, remove spaces
$pan_no = strtoupper(str_replace(' ', '', $pan_no));

// PAN format: ABCDE1234F
if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan_no)) {
    echo json_encode(["status" => false, "message" => "Invalid PAN format"]);
    exit;
}

/*
 * Table map by profile type
 * 1 = Recruiter, 2 = Candidate, 3 = Promoter
 * All assumed to have column pan_no and PK id
 */
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

// Ensure record exists (helps return clean 'not found' message)
$chk = $con->prepare("SELECT id FROM {$table} WHERE id = ? LIMIT 1");
if (!$chk) {
    echo json_encode(["status" => false, "message" => "Prepare failed (check)"]);
    exit;
}
$chk->bind_param("i", $profile_id);
$chk->execute();
$res = $chk->get_result();
if (!$res || $res->num_rows === 0) {
    echo json_encode(["status" => false, "message" => "Profile not found for given profile_id"]);
    $chk->close();
    exit;
}
$chk->close();

// Update PAN
$sql = "UPDATE {$table} SET pan_no = ? WHERE id = ?";
$stmt = $con->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => false, "message" => "Prepare failed (update)"]);
    exit;
}
$stmt->bind_param("si", $pan_no, $profile_id);

if ($stmt->execute()) {
    // Note: affected_rows can be 0 if same PAN already present
    echo json_encode([
        "status" => true,
        "message" => "PAN updated successfully",
        "profile_type_id" => $profile_type_id
    ]);
} else {
    echo json_encode(["status" => false, "message" => "Failed to update PAN"]);
}

$stmt->close();
$con->close();
