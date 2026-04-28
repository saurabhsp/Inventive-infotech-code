<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
require_once("includes/initialize.php");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['userid']) || !isset($data['profile_type'])) {
    echo json_encode(["status" => "error", "message" => "Required parameters missing"]);
    exit;
}

$userid = intval($data['userid']);
$profile_type = intval($data['profile_type']);
$city_id = isset($data['city']) ? intval($data['city']) : 0;
$locality_id = isset($data['locality']) ? intval($data['locality']) : 0;
$job_status_id = 1;

// ✅ Unread notifications
$notification_stmt = $con->prepare("SELECT COUNT(*) as unread_count FROM jos_app_notifications WHERE useridto = ? AND readstatus = 0");
$notification_stmt->bind_param("i", $userid);
$notification_stmt->execute();
$notification_res = $notification_stmt->get_result();
$unread_notification = $notification_res->fetch_assoc()['unread_count'] ?? 0;

// ✅ Fetch user & recruiter info
$user = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM jos_app_users WHERE id = '$userid' LIMIT 1"));
if (!$user) {
    echo json_encode(["status" => "not_found", "message" => "User does not exist"]);
    exit;
}
unset($user['password']);
$recruiter_id = $user['profile_id'];
$user['recruiter_id'] = $recruiter_id;

$profile_stmt = $con->prepare("SELECT organization_name AS name, mobile_no, company_logo, city_id FROM jos_app_recruiter_profile WHERE id = ?");
$profile_stmt->bind_param("i", $recruiter_id);
$profile_stmt->execute();
$profile_res = $profile_stmt->get_result();
if ($profile_res->num_rows > 0) {
    $profileData = $profile_res->fetch_assoc();
    $user['name'] = $profileData['name'];
    $user['mobile_no'] = $profileData['mobile_no'];

    if (!empty($profileData['company_logo'])) {
        $user['logo'] = DOMAIN_URL . "webservices/" . ltrim($profileData['company_logo'], '/');
    } else {
        $user['logo'] = DOMAIN_URL . "webservices/uploads/logos/nologo.png";
    }

    $city_stmt = $con->prepare("SELECT name FROM jos_crm_mcitys WHERE id = ?");
    $city_stmt->bind_param("i", $profileData['city_id']);
    $city_stmt->execute();
    $city_res = $city_stmt->get_result();
    $user['city_name'] = $city_res->num_rows > 0 ? $city_res->fetch_assoc()['name'] : "";
}


// ✅ Plan display status (based on logged-in recruiter active plan)
$plan_display_status = 0;
$active_plan_id = (int)($user['active_plan_id'] ?? 0);

if ($active_plan_id > 0) {
    $stmtPlan = $con->prepare("
        SELECT COALESCE(display_status, 0) AS display_status
        FROM jos_app_subscription_plans
        WHERE id = ?
        LIMIT 1
    ");
    $stmtPlan->bind_param("i", $active_plan_id);
    $stmtPlan->execute();
    $resPlan = $stmtPlan->get_result();

    if ($resPlan && $resPlan->num_rows > 0) {
        $plan_display_status = (int)$resPlan->fetch_assoc()['display_status'];
    }
}

// ✅ Sliders
$slider_list = [];
$sliderQuery = "SELECT id, title, image, action_type, action_value FROM jos_app_slider WHERE profile_type = ? AND status = 1 ORDER BY id DESC";
$stmtS = $con->prepare($sliderQuery);
$stmtS->bind_param("i", $profile_type);
$stmtS->execute();
$resS = $stmtS->get_result();
while ($row = $resS->fetch_assoc()) {
    $row['image'] = DOMAIN_URL . "webservices/" . ltrim($row['image'], '/');
    $slider_list[] = $row;
}

// ✅ Job Vacancies
$job_vacancies = [];
$vacancyQuery = "
    SELECT 
        j.id, 
        cjp.name AS job_position, 
        j.company_name, 
        cmc.name AS city, 
        cl.name AS locality, 
        sfrom.salaryrange AS salary_from, 
        sto.salaryrange AS salary_to, 
        js.name AS job_status,
        j.created_at,
        rp.company_logo,
        (
            SELECT COUNT(*) FROM jos_app_applications WHERE job_id = j.id AND job_listing_type = 2
        ) AS application_count,
        (
            SELECT COUNT(*) FROM jos_app_jobvisitlog WHERE job_id = j.id AND job_listing_type = 2
        ) AS visit_count
    FROM jos_app_jobvacancies j
    LEFT JOIN jos_crm_jobpost cjp ON j.job_position_id = cjp.id
    LEFT JOIN jos_crm_mcitys cmc ON j.city_id = cmc.id
    LEFT JOIN jos_crm_locality cl ON j.locality_id = cl.id
    LEFT JOIN jos_crm_salary_range sfrom ON j.salary_from = sfrom.id
    LEFT JOIN jos_crm_salary_range sto ON j.salary_to = sto.id
    LEFT JOIN jos_app_jobstatus js ON j.job_status_id = js.id
    LEFT JOIN jos_app_recruiter_profile rp ON j.recruiter_id = rp.id
    WHERE j.job_status_id = ? AND j.recruiter_id = ?";
$params = [$job_status_id, $recruiter_id];
$types = "ii";
if ($city_id > 0) {
    $vacancyQuery .= " AND j.city_id = ?";
    $types .= "i";
    $params[] = $city_id;
}
if ($locality_id > 0) {
    $vacancyQuery .= " AND j.locality_id = ?";
    $types .= "i";
    $params[] = $locality_id;
}

$stmtV = $con->prepare($vacancyQuery);
$stmtV->bind_param($types, ...$params);
$stmtV->execute();
$resV = $stmtV->get_result();
while ($row = $resV->fetch_assoc()) {
    $row['visit_status'] = false;
    $row['visit_message'] = "";
    $row['created_at'] = (new DateTime($row['created_at']))->format('d-m-Y h:i A');

    // ✅ Company logo
    if (!empty($row['company_logo'])) {
        $row['company_logo'] = DOMAIN_URL . "webservices/" . ltrim($row['company_logo'], '/');
    } else {
        $row['company_logo'] = DOMAIN_URL . "webservices/uploads/logos/nologo.png";
    }

    // ✅ Action log counts
    $aid = $row['id'];
    $stmtAction = $con->prepare("SELECT 
        SUM(CASE WHEN action_type = 1 THEN 1 ELSE 0 END) AS call_count,
        SUM(CASE WHEN action_type = 2 THEN 1 ELSE 0 END) AS whatsapp_count,
        SUM(CASE WHEN action_type = 3 THEN 1 ELSE 0 END) AS location_count
        FROM jos_app_jobaction_logs WHERE job_listing_type = 2 AND job_id = ?");
    $stmtAction->bind_param("i", $aid);
    $stmtAction->execute();
    $act = $stmtAction->get_result()->fetch_assoc();

    $row['call_count'] = intval($act['call_count']);
    $row['whatsapp_count'] = intval($act['whatsapp_count']);
    $row['location_count'] = intval($act['location_count']);
    $row['total_actions'] = $row['call_count'] + $row['whatsapp_count'] + $row['location_count'];

    $job_vacancies[] = $row;
}

// ✅ Walk-in Interviews
$walkin_interviews = [];
$walkinQuery = "
    SELECT 
        j.id, 
        cjp.name AS job_position, 
        j.company_name, 
        cmc.name AS city, 
        cl.name AS locality, 
        sfrom.salaryrange AS salary_from, 
        sto.salaryrange AS salary_to, 
        js.name AS job_status,
        jt.name AS job_type, 
        wm.name AS work_model, 
        ws.shift_name AS work_shift,
        j.created_at,
        rp.company_logo,
        (
            SELECT COUNT(*) FROM jos_app_applications WHERE job_id = j.id AND job_listing_type = 1
        ) AS application_count,
        (
            SELECT COUNT(*) FROM jos_app_jobvisitlog WHERE job_id = j.id AND job_listing_type = 1
        ) AS visit_count
    FROM jos_app_walkininterviews j
    LEFT JOIN jos_app_jobtypes jt ON j.job_type = jt.id
    LEFT JOIN jos_app_workmodel wm ON j.work_model = wm.id
    LEFT JOIN jos_app_workshift ws ON j.work_shift = ws.id
    LEFT JOIN jos_crm_jobpost cjp ON j.job_position_id = cjp.id
    LEFT JOIN jos_crm_mcitys cmc ON j.city_id = cmc.id
    LEFT JOIN jos_crm_locality cl ON j.locality_id = cl.id
    LEFT JOIN jos_crm_salary_range sfrom ON j.salary_from = sfrom.id
    LEFT JOIN jos_crm_salary_range sto ON j.salary_to = sto.id
    LEFT JOIN jos_app_jobstatus js ON j.job_status_id = js.id
    LEFT JOIN jos_app_recruiter_profile rp ON j.recruiter_id = rp.id
    WHERE j.job_status_id = ? AND j.recruiter_id = ?";
$params = [$job_status_id, $recruiter_id];
$types = "ii";
if ($city_id > 0) {
    $walkinQuery .= " AND j.city_id = ?";
    $types .= "i";
    $params[] = $city_id;
}
if ($locality_id > 0) {
    $walkinQuery .= " AND j.locality_id = ?";
    $types .= "i";
    $params[] = $locality_id;
}

$stmtW = $con->prepare($walkinQuery);
$stmtW->bind_param($types, ...$params);
$stmtW->execute();
$resW = $stmtW->get_result();
while ($row = $resW->fetch_assoc()) {
    $row['visit_status'] = false;
    $row['visit_message'] = "";
    $row['created_at'] = (new DateTime($row['created_at']))->format('d-m-Y h:i A');

    // ✅ Company logo
    if (!empty($row['company_logo'])) {
        $row['company_logo'] = DOMAIN_URL . "webservices/" . ltrim($row['company_logo'], '/');
    } else {
        $row['company_logo'] = DOMAIN_URL . "webservices/uploads/logos/nologo.png";
    }

    // ✅ Action log counts
    $aid = $row['id'];
    $stmtAction = $con->prepare("SELECT 
        SUM(CASE WHEN action_type = 1 THEN 1 ELSE 0 END) AS call_count,
        SUM(CASE WHEN action_type = 2 THEN 1 ELSE 0 END) AS whatsapp_count,
        SUM(CASE WHEN action_type = 3 THEN 1 ELSE 0 END) AS location_count
        FROM jos_app_jobaction_logs WHERE job_listing_type = 1 AND job_id = ?");
    $stmtAction->bind_param("i", $aid);
    $stmtAction->execute();
    $act = $stmtAction->get_result()->fetch_assoc();

    $row['call_count'] = intval($act['call_count']);
    $row['whatsapp_count'] = intval($act['whatsapp_count']);
    $row['location_count'] = intval($act['location_count']);
    $row['total_actions'] = $row['call_count'] + $row['whatsapp_count'] + $row['location_count'];
    $row['plan_display_status'] = $plan_display_status;


    $walkin_interviews[] = $row;
}

// ✅ Final messages
$combined_message = "";
$walkin_message = "";
$vacancy_message = "";
if (empty($walkin_interviews) && empty($job_vacancies)) {
    $combined_message = "You haven’t posted any jobs yet. Get started, post now.";
} elseif (empty($walkin_interviews)) {
    $walkin_message = "You haven't posted any Premium Jobs. Get started, post now.";
} elseif (empty($job_vacancies)) {
    $vacancy_message = "You haven't posted any Standard jobs yet. Get started, post now.";
}

// ✅ Final output
$response = [
    "status" => "success",
    "message" => "Dashboard loaded",
    "sliders" => $slider_list,
    "profile_data" => $user,
    "welcome_message" => "Welcome back, " . $user['name'] . "! Let’s find your next great hire.",
    "walkin_interviews" => $walkin_interviews,
    "job_vacancies" => $job_vacancies,
    "document_helpline_no" => "7030933999",
    "unread_notification_count" => $unread_notification
];

if (!empty($combined_message)) {
    $response["combined_message"] = $combined_message;
} else {
    if (!empty($walkin_message)) $response["walkin_message"] = $walkin_message;
    if (!empty($vacancy_message)) $response["vacancy_message"] = $vacancy_message;
}

echo json_encode($response);
