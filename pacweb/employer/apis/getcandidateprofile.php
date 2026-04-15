<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'includes/initialize.php';
header('Content-Type: application/json');

function formatDate($date) {
    return (!empty($date) && $date !== '0000-00-00') ? date("d-m-Y", strtotime($date)) : null;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['userid']) || intval($data['userid']) <= 0) {
    echo json_encode(["status" => false, "message" => "Valid userid is required."]);
    exit;
}

$user_id = intval($data['userid']);

/* ---------------------------------------------------------
   Step 1: Get user basics (keep original fields)
--------------------------------------------------------- */
$user_query = "SELECT profile_id, profile_type_id, active_plan_id, myreferral_code 
               FROM jos_app_users WHERE id = ? LIMIT 1";
$stmt_user = $con->prepare($user_query);
if (!$stmt_user) {
    echo json_encode(["status" => false, "message" => "DB error (prepare user): " . $con->error]);
    exit;
}
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();

if ($user_result->num_rows == 0) {
    echo json_encode(["status" => false, "message" => "User not found."]);
    exit;
}

$user_row         = $user_result->fetch_assoc();
$profile_id       = intval($user_row['profile_id']);      // kept for compatibility
$profile_type_id  = intval($user_row['profile_type_id']); // 1=recruiter, 2=candidate (not used here)
$active_plan_id   = intval($user_row['active_plan_id']);
$myreferral_code  = $user_row['myreferral_code'];

/* ---------------------------------------------------------
   Step 2: Get candidate profile by USERID
   (Joins resolve names for gender/experience type/period)
--------------------------------------------------------- */
$candidate_query = "
    SELECT 
        c.*,
        c.resume_generated,

        -- gender
        g.name AS gender_name,

        -- Experience TYPE: support both legacy crm_experience and the app_experience_list
        -- Works whether c.experience_type stores an ID or a text name
        COALESCE(e.name, et.name) AS experience_type_name,

        -- Experience PERIOD: from app_experience_list, also supports ID or text in c.experience_period
        ep.name AS experience_period_name

    FROM jos_app_candidate_profile c
    LEFT JOIN jos_crm_gender g
           ON c.gender_id = g.id

    LEFT JOIN jos_crm_experience e
           ON (
                CAST(c.experience_type AS UNSIGNED) = e.id
                OR LOWER(c.experience_type) = LOWER(e.name)
              )

    LEFT JOIN jos_app_experience_list et
           ON (
                CAST(c.experience_type AS UNSIGNED) = et.id
                OR LOWER(c.experience_type) = LOWER(et.name)
              )

    LEFT JOIN jos_app_experience_list ep
           ON (
                CAST(c.experience_period AS UNSIGNED) = ep.id
                OR LOWER(c.experience_period) = LOWER(ep.name)
              )

    WHERE c.userid = ?
    LIMIT 1
";
$stmt_candidate = $con->prepare($candidate_query);
if (!$stmt_candidate) {
    echo json_encode(["status" => false, "message" => "DB error (prepare candidate): " . $con->error]);
    exit;
}
$stmt_candidate->bind_param("i", $user_id);
$stmt_candidate->execute();
$result = $stmt_candidate->get_result();

if (!$result || $result->num_rows == 0) {
    echo json_encode(["status" => false, "message" => "Candidate profile not found."]);
    exit;
}

$row = $result->fetch_assoc();

/* ---------------------------------------------------------
   Step 3: Convert job_position_ids to names (keep approach)
--------------------------------------------------------- */
$job_position_names = [];
$job_ids = [];
if (!empty($row['job_position_ids'])) {
    $job_ids = array_filter(array_map('intval', explode(',', $row['job_position_ids'])));
    if (!empty($job_ids)) {
        $id_list = implode(',', $job_ids);
        $job_query = "SELECT name FROM jos_crm_jobpost WHERE id IN ($id_list)";
        $job_result = mysqli_query($con, $job_query);
        if ($job_result) {
            while ($job_row = mysqli_fetch_assoc($job_result)) {
                $job_position_names[] = $job_row['name'];
            }
        }
    }
}

/* ---------------------------------------------------------
   Step 4: Format profile photo (guard + fallback)
--------------------------------------------------------- */
$photo = isset($row['profile_photo']) ? trim((string)$row['profile_photo']) : '';
if ($photo === '' || $photo === null) {
    // adjust fallback path if different in your project
    $row['profile_photo'] = DOMAIN_URL . 'webservices/uploads/nophoto_greyscale_circle.png';
} elseif (stripos($photo, 'http://') === 0 || stripos($photo, 'https://') === 0) {
    $row['profile_photo'] = $photo;
} else {
    $row['profile_photo'] = DOMAIN_URL . $photo;
}

/* ---------------------------------------------------------
   Step 5: Subscription details (keep your active_plan_id logic)
--------------------------------------------------------- */
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
            $sub   = $sub_result->fetch_assoc();
            $today = date("Y-m-d");
            $subscription = [
                "status" => (!empty($sub['end_date']) && $sub['end_date'] >= $today) ? "active" : "expired",
                "valid_from" => formatDate($sub['start_date']),
                "valid_to" => formatDate($sub['end_date']),
                "plan_name" => $sub['plan_name'],
                "validity_months" => is_null($sub['validity_months']) ? null : (int)$sub['validity_months']
            ];
        }
    }
}

/* ---------------------------------------------------------
   Step 6: Final response (unchanged key names)
   NOTE: city_id/locality_id are strings in your schema and are returned as-is
--------------------------------------------------------- */
$response = [
    "id" => isset($row['id']) ? (int)$row['id'] : null,
    "candidate_name" => $row['candidate_name'],
    "mobile_no" => $row['mobile_no'],
    "gender_id" => isset($row['gender_id']) ? (int)$row['gender_id'] : null,
    "gender" => $row['gender_name'],
    "birthdate" => formatDate($row['birthdate']),
    "email" => $row['email'],
    "address" => $row['address'],

    "job_position_ids" => !empty($job_ids) ? implode(',', $job_ids) : '',
    "job_positions" => !empty($job_position_names) ? implode(', ', $job_position_names) : '',

    "experience_type" => isset($row['experience_type']) ? (int)$row['experience_type'] : null, // kept as-is
    "experience_type_name" => $row['experience_type_name'],                                     // now resolved

    "experience_period" => isset($row['experience_period']) ? (int)$row['experience_period'] : null, // kept as-is
    "experience_period_name" => $row['experience_period_name'],                                      // now resolved

    "profile_photo" => $row['profile_photo'],

    // Keep variable names same; values are strings as stored in DB
    "city_id"     => isset($row['city_id']) ? $row['city_id'] : null,
    "locality_id" => isset($row['locality_id']) ? $row['locality_id'] : null,

    "latitude" => isset($row['latitude']) ? (float)$row['latitude'] : null,
    "longitude" => isset($row['longitude']) ? (float)$row['longitude'] : null,
    "created_at" => formatDate($row['created_at']),

    "subscription_details" => $subscription,
    "myreferral_code" => $myreferral_code,
    "resume_generated" => (isset($row['resume_generated']) && (int)$row['resume_generated'] === 1) ? true : false,
    "welcome_message" => "Hi " . $row['candidate_name'] . ", let’s explore the latest job openings."
];

echo json_encode([
    "status" => true,
    "message" => "Candidate profile and subscription details fetched successfully",
    "data" => $response
]);
