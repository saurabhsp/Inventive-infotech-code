<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../includes/db_config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Make mysqli throw exceptions so try/catch works cleanly
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Read JSON
    $data = json_decode(file_get_contents("php://input"), true);

    if (
        !isset($data['userid']) ||
        !isset($data['city_id']) ||
        !isset($data['locality_id'])
    ) {
        echo json_encode([
            "status"  => "error",
            "message" => "userid, city_id and locality_id are required"
        ]);
        exit;
    }

    // Sanitize / normalize
    $userid        = (int)$data['userid'];
    $city_string   = trim((string)$data['city_id']);      // e.g., "Satara"
    $locality_str  = trim((string)$data['locality_id']);  // e.g., "Shahupuri"

    // ✅ Optional extra fields (keep null if not sent)
    $district = array_key_exists('district', $data) ? trim((string)$data['district']) : null;
    $state    = array_key_exists('state',    $data) ? trim((string)$data['state'])    : null;
    $country  = array_key_exists('country',  $data) ? trim((string)$data['country'])  : null;

    if ($userid <= 0 || $city_string === '' || $locality_str === '') {
        echo json_encode([
            "status"  => "error",
            "message" => "Invalid userid or empty city/locality."
        ]);
        exit;
    }

    // 1) Fetch profile_type_id and profile_id from users
    $sqlUser = "SELECT profile_type_id, profile_id FROM jos_app_users WHERE id = ? LIMIT 1";
    $stmt = $con->prepare($sqlUser);
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode([
            "status"  => "error",
            "message" => "User not found"
        ]);
        exit;
    }

    $u = $res->fetch_assoc();
    $profile_type_id = (int)($u['profile_type_id'] ?? 0);
    $profile_id      = (int)($u['profile_id'] ?? 0);

    if ($profile_id <= 0) {
        echo json_encode([
            "status"  => "error",
            "message" => "No profile linked to this user"
        ]);
        exit;
    }

    // 2) Decide table/column targets based on profile_type_id
    if ($profile_type_id === 1) {
        // Recruiter
        $profileTable = "jos_app_recruiter_profile";
        $profilePK    = "id";
    } elseif ($profile_type_id === 2) {
        // Candidate
        $profileTable = "jos_app_candidate_profile";
        $profilePK    = "id";
    } elseif ($profile_type_id === 3) {
        // Candidate
        $profileTable = "jos_app_promoter_profile";
        $profilePK    = "id";
    } else {
        echo json_encode([
            "status"  => "error",
            "message" => "Unsupported profile_type_id. Expected 1 (Recruiter) or 2 (Candidate)."
        ]);
        exit;
    }

    // 3) Begin transaction
    $con->begin_transaction();

    // 4) Update city/locality (strings)
    $sqlProfile = "
        UPDATE {$profileTable}
           SET city_id = ?, locality_id = ?
         WHERE {$profilePK} = ?
         LIMIT 1
    ";
    $stmtP = $con->prepare($sqlProfile);
    $stmtP->bind_param("ssi", $city_string, $locality_str, $profile_id);
    $stmtP->execute();

    // 4b) Conditionally update district/state/country
    $extraSets   = [];
    $extraTypes  = '';
    $extraParams = [];

    if ($district !== null) { $extraSets[] = "district = ?"; $extraTypes .= 's'; $extraParams[] = $district; }
    if ($state    !== null) { $extraSets[] = "state = ?";    $extraTypes .= 's'; $extraParams[] = $state; }
    if ($country  !== null) { $extraSets[] = "country = ?";  $extraTypes .= 's'; $extraParams[] = $country; }

    if (!empty($extraSets)) {
        $sqlExtra = "UPDATE {$profileTable} SET " . implode(', ', $extraSets) . " WHERE {$profilePK} = ? LIMIT 1";
        $extraTypes .= 'i';
        $extraParams[] = $profile_id;

        $stmtExtra = $con->prepare($sqlExtra);
        $bind = [$extraTypes];
        foreach ($extraParams as $k => $v) { $bind[] = &$extraParams[$k]; }
        call_user_func_array([$stmtExtra, 'bind_param'], $bind);
        $stmtExtra->execute();
    }

    // 5) Mirror city to users for quick filters
    $sqlUserUpd = "UPDATE jos_app_users SET city_id = ? WHERE id = ? LIMIT 1";
    $stmtU = $con->prepare($sqlUserUpd);
    $stmtU->bind_param("si", $city_string, $userid);
    $stmtU->execute();

    // 6) Commit
    $con->commit();
    
    // ✅ ADD THIS BLOCK HERE
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['city_name'] = $city_string;

    // ✅ Backward-compat fields added: city_name & locality_name
    echo json_encode([
        "status"  => "success",
        "message" => "City, locality and extra fields updated successfully",
        "data"    => [
            "profile_type_id" => $profile_type_id,
            "profile_id"      => $profile_id,
            "city_id"         => $city_string,      // existing
            "city_name"       => $city_string,      // added back for old apps
            "locality_id"     => $locality_str,     // existing
            "locality_name"   => $locality_str,     // added back for old apps
            "district"        => $district,         // may be null
            "state"           => $state,            // may be null
            "country"         => $country           // may be null
        ]
    ]);
    exit;

} catch (Throwable $e) {
    // Rollback if transaction active
    if ($con) { try { $con->rollback(); } catch (Throwable $ignored) {} }

    echo json_encode([
        "status"  => "error",
        "message" => "Update failed: " . $e->getMessage()
    ]);
    exit;
}
