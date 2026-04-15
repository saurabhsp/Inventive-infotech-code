<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/initialize.php';
header('Content-Type: application/json');

/**
 * Helpers
 */
function formatDate($date) {
    return (!empty($date) && $date !== '0000-00-00') ? date("d-m-Y", strtotime($date)) : null;
}
function prefixDomainIfNeeded($path) {
    if (empty($path)) return null;
    // If already absolute (http/https), return as is
    if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) return $path;
    return (defined('DOMAIN_URL') ? DOMAIN_URL : '') . $path;
}

/**
 * Read JSON
 */
$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['userid']) || intval($data['userid']) <= 0) {
    echo json_encode(["status" => false, "message" => "Valid userid is required."]);
    exit;
}
$user_id = intval($data['userid']);

/**
 * Step 1: Get profile_id, active_plan_id, myreferral_code from users
 */
$user_query = "SELECT profile_id, active_plan_id, myreferral_code FROM jos_app_users WHERE id = ? LIMIT 1";
$stmt_user = $con->prepare($user_query);
if (!$stmt_user) {
    echo json_encode(["status" => false, "message" => "DB error (prepare users): " . $con->error]);
    exit;
}
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();

if ($user_result->num_rows === 0) {
    echo json_encode(["status" => false, "message" => "User not found."]);
    exit;
}
$user_row = $user_result->fetch_assoc();
$profile_id       = intval($user_row['profile_id']);
$active_plan_id   = intval($user_row['active_plan_id']);
$myreferral_code  = $user_row['myreferral_code'] ?? null;

/**
 * Step 2: Get promoter profile by profile_id
 * Table: jos_app_promoter_profile
 * Known fields from your structure: id, userid, contact_person_name, mobile_no, address, profile_photo, pan_no
 * We’ll SELECT * and read using null-coalescing to avoid notices if some optional columns exist or not.
 */
$promoter_query = "SELECT * FROM jos_app_promoter_profile WHERE id = ? LIMIT 1";
$stmt_promoter = $con->prepare($promoter_query);
if (!$stmt_promoter) {
    echo json_encode(["status" => false, "message" => "DB error (prepare promoter): " . $con->error]);
    exit;
}
$stmt_promoter->bind_param("i", $profile_id);
$stmt_promoter->execute();
$promoter_result = $stmt_promoter->get_result();

if (!$promoter_result || $promoter_result->num_rows === 0) {
    echo json_encode(["status" => false, "message" => "Promoter profile not found."]);
    exit;
}

$row = $promoter_result->fetch_assoc();

/**
 * Step 3: Normalize profile photo
 */
$profile_photo = prefixDomainIfNeeded($row['profile_photo'] ?? null);

/**
 * Step 4: Resolve subscription details for this user & active plan
 */
$subscription = [
    "status" => "no_subscription",
    "valid_from" => null,
    "valid_to" => null,
    "plan_name" => null,
    "validity_months" => null
];

if ($active_plan_id > 0) {
    $sub_query = "
        SELECT log.start_date, log.end_date, plan.plan_name, plan.validity_months 
        FROM jos_app_usersubscriptionlog log
        LEFT JOIN jos_app_subscription_plans plan ON plan.id = log.plan_id
        WHERE log.userid = ? AND log.plan_id = ? AND log.payment_status = 'success'
        ORDER BY log.start_date DESC
        LIMIT 1
    ";
    $stmt_sub = $con->prepare($sub_query);
    if ($stmt_sub) {
        $stmt_sub->bind_param("ii", $user_id, $active_plan_id);
        $stmt_sub->execute();
        $sub_result = $stmt_sub->get_result();
        if ($sub_result && $sub_result->num_rows > 0) {
            $sub = $sub_result->fetch_assoc();
            $today = date("Y-m-d");
            $subscription = [
                "status" => (!empty($sub['end_date']) && $sub['end_date'] >= $today) ? "active" : "expired",
                "valid_from" => formatDate($sub['start_date'] ?? null),
                "valid_to" => formatDate($sub['end_date'] ?? null),
                "plan_name" => $sub['plan_name'] ?? null,
                "validity_months" => $sub['validity_months'] ?? null
            ];
        }
    }
}

/**
 * Step 5: Build response (use ?? null to avoid undefined index notices)
 * Note: Field names mapped to a clean API shape; adjust/add keys if you later add columns
 * like city_id, locality_id, latitude, longitude, email, aadhar_no, etc.
 */
$response = [
    "id" => $row['id'] ?? null,
    "userid" => $row['userid'] ?? null,

    // Display name for the promoter (from your schema: contact_person_name)
    "promoter_name" => $row['contact_person_name'] ?? null,

    "mobile_no" => $row['mobile_no'] ?? null,
    "email" => $row['email'] ?? null,                   // if column exists; else null
    "address" => $row['address'] ?? null,
    "pan_no" => $row['pan_no'] ?? null,
    "profile_photo" => $profile_photo,

    // Optional geo & locality if present in your table (kept safe)
    "city_id" => $row['city_id'] ?? null,
    "area_id" => $row['area_id'] ?? null,
    "locality_id" => $row['locality_id'] ?? null,
    "latitude" => $row['latitude'] ?? null,
    "longitude" => $row['longitude'] ?? null,

    "created_at" => formatDate($row['created_at'] ?? null),

    // Subscription + referral
    "subscription_details" => $subscription,
    "myreferral_code" => $myreferral_code,

    // A friendly banner copy for app UI
    "welcome_message" => "Hi " . (($row['contact_person_name'] ?? "Promoter")) . ", let’s start promoting and earning rewards."
];

/**
 * Output
 */
echo json_encode([
    "status" => true,
    "message" => "Promoter profile and subscription details fetched successfully",
    "data" => $response
]);
