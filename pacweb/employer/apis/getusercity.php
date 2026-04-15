<?php
header("Content-Type: application/json");
require_once "includes/initialize.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Make mysqli throw exceptions for cleaner error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['userid'])) {
        echo json_encode([
            "status"  => "error",
            "message" => "User ID is required",
            "data"    => (object)[]
        ]);
        exit;
    }

    $userid = (int)$data['userid'];
    if ($userid <= 0) {
        echo json_encode([
            "status"  => "error",
            "message" => "Invalid User ID",
            "data"    => (object)[]
        ]);
        exit;
    }

    // Step 1: Get profile_type_id and profile_id from jos_app_users
    $sqlUser = "SELECT profile_type_id, profile_id FROM jos_app_users WHERE id = ? LIMIT 1";
    $stmt = $con->prepare($sqlUser);
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode([
            "status"  => "not_found",
            "message" => "User not found",
            "data"    => (object)[]
        ]);
        exit;
    }

    $u = $res->fetch_assoc();
    $profile_type_id = (int)($u['profile_type_id'] ?? 0);
    $profile_id      = (int)($u['profile_id'] ?? 0);

    if ($profile_id <= 0) {
        echo json_encode([
            "status"  => "not_found",
            "message" => "No profile linked to this user",
            "data"    => (object)[]
        ]);
        exit;
    }

    // Step 2: Route to correct profile table based on profile_type_id
    if ($profile_type_id === 1) {
        // Recruiter profile table
        $sqlProfile = "
            SELECT 
                rp.city_id     AS city_name,
                rp.locality_id AS locality_name
            FROM jos_app_recruiter_profile rp
            WHERE rp.id = ?
            LIMIT 1
        ";
    } elseif ($profile_type_id === 2) {
        // Candidate profile table
        $sqlProfile = "
            SELECT 
                cp.city_id     AS city_name,
                cp.locality_id AS locality_name
            FROM jos_app_candidate_profile cp
            WHERE cp.id = ?
            LIMIT 1
        ";
    } elseif ($profile_type_id === 3) {
        // Candidate profile table
        $sqlProfile = "
            SELECT 
                cp.city_id     AS city_name,
                cp.locality_id AS locality_name
            FROM jos_app_promoter_profile cp
            WHERE cp.id = ?
            LIMIT 1
        ";
    } else {
        echo json_encode([
            "status"  => "error",
            "message" => "Unsupported profile_type_id. Expected 1 (Recruiter) or 2 (Candidate).",
            "data"    => (object)[]
        ]);
        exit;
    }

    $stmtP = $con->prepare($sqlProfile);
    $stmtP->bind_param("i", $profile_id);
    $stmtP->execute();
    $resP = $stmtP->get_result();

    if ($resP->num_rows === 0) {
        echo json_encode([
            "status"  => "not_found",
            "message" => "Profile data not found",
            "data"    => (object)[]
        ]);
        exit;
    }

    $row = $resP->fetch_assoc();

    echo json_encode([
        "status"  => "success",
        "message" => "Location fetched successfully",
        "data"    => [
            "profile_type_id" => $profile_type_id,
            "profile_id"      => $profile_id,
            "city_name"       => $row['city_name'] ?? "",
            "locality_name"   => $row['locality_name'] ?? ""
        ]
    ]);
    exit;
} catch (Throwable $e) {
    echo json_encode([
        "status"  => "error",
        "message" => "Fetch failed: " . $e->getMessage(),
        "data"    => (object)[]
    ]);
    exit;
}
