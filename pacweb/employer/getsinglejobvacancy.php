<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/db_config.php'; // DB connection + DOMAIN_URL

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id']) || empty($data['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Job vacancy ID is required.']);
        exit;
    }

    $id = intval($data['id']);
    $userid = isset($data['userid']) ? intval($data['userid']) : 0;

    // Main job query
    $query = "
        SELECT 
            jv.id,
            jv.recruiter_id,
            jv.company_name,
            jv.contact_person,
            jv.contact_no,  -- ✅ newly added
            jv.interview_address,
            jv.state,
            jv.country,
            jv.valid_till_date,
            jv.validity_apply,

            jv.job_position_id,
            jp.name AS job_position,

            jv.city_id AS city,
            jv.locality_id AS locality,

            jv.gender_id,
            g.name AS gender,

            jv.qualification_id,
            qs.name AS qualification,

            jv.experience_from,
            exp_from.name AS experience_from_name,

            jv.experience_to,
            exp_to.name AS experience_to_name,

            jv.salary_from,
            sr_from.salaryrange AS salary_from_value,

            jv.salary_to,
            sr_to.salaryrange AS salary_to_value,

            jv.job_status_id,
            js.name AS job_status
        FROM jos_app_jobvacancies AS jv
        LEFT JOIN jos_crm_jobpost AS jp ON jv.job_position_id = jp.id
        LEFT JOIN jos_crm_gender AS g ON jv.gender_id = g.id
        LEFT JOIN jos_crm_education_status AS qs ON jv.qualification_id = qs.id
        LEFT JOIN jos_app_experience_list AS exp_from ON jv.experience_from = exp_from.id
        LEFT JOIN jos_app_experience_list AS exp_to ON jv.experience_to = exp_to.id
        LEFT JOIN jos_crm_salary_range AS sr_from ON jv.salary_from = sr_from.id
        LEFT JOIN jos_crm_salary_range AS sr_to ON jv.salary_to = sr_to.id
        LEFT JOIN jos_app_jobstatus AS js ON jv.job_status_id = js.id
        WHERE jv.id = ?";

    $stmt = $con->prepare($query);
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . $con->error]);
        exit;
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'No job vacancy found.']);
        exit;
    }

    $job = $result->fetch_assoc();

    $company_logo = DOMAIN_URL . "webservices/uploads/logos/nologo.png"; // Default fallback

    if (!empty($job['recruiter_id'])) {
        $logoQuery = "SELECT company_logo FROM jos_app_recruiter_profile WHERE id = ?";
        $logoStmt = $con->prepare($logoQuery);
        if ($logoStmt) {
            $logoStmt->bind_param("i", $job['recruiter_id']);
            $logoStmt->execute();
            $logoRes = $logoStmt->get_result();
            if ($logoRow = $logoRes->fetch_assoc()) {
                if (!empty($logoRow['company_logo'])) {
                    $company_logo = DOMAIN_URL . "webservices/" . $logoRow['company_logo'];
                }
            }
        }
    }

    // ✅ Prepare response
    $response_data = [
        'id' => $job['id'],
        'company_name' => $job['company_name'],
        'contact_person' => $job['contact_person'],
         'mobile_no' => $job['contact_no'], // ✅ Renamed here
        'interview_address' => $job['interview_address'],

        'job_position_id' => $job['job_position_id'],
        'job_position' => $job['job_position'],

        'city' => $job['city'],
        'locality' => $job['locality'],
        'state' => $job['state'],
        'country' => $job['country'],

        'validity_apply' => $job['validity_apply'],
        'valid_till_date' => $job['valid_till_date'],

        'gender_id' => $job['gender_id'],
        'gender' => $job['gender'],

        'qualification_id' => $job['qualification_id'],
        'qualification' => $job['qualification'],

        'experience_from_id' => $job['experience_from'],
        'experience_from' => $job['experience_from_name'],

        'experience_to_id' => $job['experience_to'],
        'experience_to' => $job['experience_to_name'],

        'salary_from_id' => $job['salary_from'],
        'salary_from' => $job['salary_from_value'],

        'salary_to_id' => $job['salary_to'],
        'salary_to' => $job['salary_to_value'],

        'job_status_id' => $job['job_status_id'],
        'job_status' => $job['job_status'],

        'company_logo' => $company_logo,

        'application_status' => false,
        'application_status_message' => 'Not yet applied to this job.'
    ];

    // ✅ Check application status
    if ($userid > 0) {
        $appQuery = "SELECT id, application_date FROM jos_app_applications WHERE job_listing_type = 2 AND job_id = ? AND userid = ?";
        $appStmt = $con->prepare($appQuery);
        if ($appStmt) {
            $appStmt->bind_param("ii", $id, $userid);
            $appStmt->execute();
            $appResult = $appStmt->get_result();

            if ($appResult->num_rows > 0) {
                $appRow = $appResult->fetch_assoc();
                $appliedDateTime = $appRow['application_date'];
                $formattedDate = date('d-M-Y \a\t h:i A', strtotime($appliedDateTime));

                $response_data['application_status'] = true;
                $response_data['application_status_message'] = "Already applied to this job on $formattedDate";
            }
        }
    }

    echo json_encode(['status' => 'success', 'data' => $response_data]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
