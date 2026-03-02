<?php
include("includes/initialize.php");
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$data = json_decode(file_get_contents("php://input"), true);

function sanitize($con, $value) {
    return mysqli_real_escape_string($con, trim($value));
}

$id = isset($data['id']) ? intval($data['id']) : 0;
if ($id <= 0) {
    echo json_encode([
        'status' => false,
        'message' => 'Valid candidate ID (id > 0) is required.'
    ]);
    exit;
}

$fields = [];

if (isset($data['candidate_name'])) {
    $fields[] = "candidate_name = '" . sanitize($con, $data['candidate_name']) . "'";
}
if (isset($data['mobile_no'])) {
    $fields[] = "mobile_no = '" . sanitize($con, $data['mobile_no']) . "'";
}
if (isset($data['gender_id'])) {
    $fields[] = "gender_id = " . intval($data['gender_id']);
}
if (isset($data['birthdate'])) {
    $birthdate_raw = sanitize($con, $data['birthdate']);
    $birthdate_parts = explode('-', $birthdate_raw);
    if (count($birthdate_parts) === 3 && strlen($birthdate_parts[2]) === 4) {
        $birthdate = $birthdate_parts[2] . '-' . $birthdate_parts[1] . '-' . $birthdate_parts[0];
        $fields[] = "birthdate = '" . $birthdate . "'";
    }
}
if (isset($data['email'])) {
    $fields[] = "email = '" . sanitize($con, $data['email']) . "'";
}
if (isset($data['address'])) {
    $fields[] = "address = '" . sanitize($con, $data['address']) . "'";
}
if (isset($data['job_position_ids'])) {
    $fields[] = "job_position_ids = '" . sanitize($con, $data['job_position_ids']) . "'";
}
if (isset($data['experience_type'])) {
    $fields[] = "experience_type = " . intval($data['experience_type']);
}
if (isset($data['experience_period'])) {
    $fields[] = "experience_period = " . intval($data['experience_period']);
}

/* ------------ NEW: optional country/state/district updates ------------ */
if (isset($data['country'])) {
    $fields[] = "country = '" . sanitize($con, $data['country']) . "'";
}
if (isset($data['state'])) {
    $fields[] = "state = '" . sanitize($con, $data['state']) . "'";
}
if (isset($data['district'])) {
    $fields[] = "district = '" . sanitize($con, $data['district']) . "'";
}
/* --------------------------------------------------------------------- */

/* Store city and locality as string (no lookup) */
if (isset($data['city_id'])) {
    $fields[] = "city_id = '" . sanitize($con, $data['city_id']) . "'";
}
if (isset($data['locality_id'])) {
    $fields[] = "locality_id = '" . sanitize($con, $data['locality_id']) . "'";
}

if (empty($fields)) {
    echo json_encode([
        'status' => false,
        'message' => 'No fields provided to update.'
    ]);
    exit;
}

$sql = "UPDATE jos_app_candidate_profile SET " . implode(", ", $fields) . " WHERE id = $id";
$result = mysqli_query($con, $sql);

if ($result) {
    echo json_encode([
        'status' => true,
        'message' => 'Candidate profile updated successfully.'
    ]);
} else {
    echo json_encode([
        'status' => false,
        'message' => 'Update failed.',
        'error' => mysqli_error($con)
    ]);
}
?>
