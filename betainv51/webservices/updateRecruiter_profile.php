<?php
include("includes/initialize.php");
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Read JSON input
$input = json_decode(file_get_contents("php://input"), true);

$id = isset($input['id']) ? intval($input['id']) : 0;
if ($id <= 0) {
    echo json_encode(["status" => false, "message" => "Invalid recruiter ID"]);
    exit;
}

// Fields to update (city_id and locality_id are treated as string values)
$fields = [
    'organization_name', 'contact_person_name', 'designation',
    'mobile_no', 'email', 'website', 'about_company', 'address',
    'industry_type', 'company_size', 'established_year',
    'city_id', 'locality_id',
    'latitude', 'longitude'
];

$updateFields = [];
$params = [];
$types = '';

foreach ($fields as $field) {
    if (isset($input[$field])) {
        $updateFields[] = "$field = ?";
        $params[] = $input[$field];
        $types .= 's'; // Default to string
    }
}

// Fields to treat as integer or float
$intFields = ['company_size', 'established_year', 'state_id', 'district_id'];
$floatFields = ['latitude', 'longitude'];

foreach ($updateFields as $index => $clause) {
    $fieldName = explode(' = ', $clause)[0];
    if (in_array($fieldName, $intFields)) {
        $types[$index] = 'i';
    } elseif (in_array($fieldName, $floatFields)) {
        $types[$index] = 'd';
    }
}

if (empty($updateFields)) {
    echo json_encode(["status" => false, "message" => "No data to update"]);
    exit;
}

$params[] = $id;
$types .= 'i';

$sql = "UPDATE jos_app_recruiter_profile SET " . implode(", ", $updateFields) . " WHERE id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(["status" => true, "message" => "Recruiter profile updated successfully"]);
} else {
    echo json_encode(["status" => false, "message" => "Update failed"]);
}
?>
