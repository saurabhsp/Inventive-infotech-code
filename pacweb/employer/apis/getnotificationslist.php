<?php
require_once '../includes/db_config.php'; // database connection file

header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['userid'])) {
        echo json_encode([
            "status" => false,
            "message" => "userid is required"
        ]);
        exit;
    }

    $userid = intval($data['userid']);

    $query = "SELECT 
                id,
                datetime,
                title,
                msg,
                type,
                typeid,
                job_listing_type,
                job_id,
                application_id,
                action_type,
                readstatus,
                useridfrom,
                useridto
              FROM jos_app_notifications
              WHERE useridto = ?
              ORDER BY datetime DESC";

    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $row['datetime'] = date("d-m-Y h:i A", strtotime($row['datetime']));
        $notifications[] = $row;
    }

    $response = [
        "status" => true,
        "message" => count($notifications) ? "Notifications fetched successfully" : "No notifications found",
        "notifications" => $notifications
    ];

    echo json_encode($response);
} else {
    echo json_encode([
        "status" => false,
        "message" => "Invalid request method"
    ]);
}
?>
