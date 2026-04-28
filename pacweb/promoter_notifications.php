<?php
session_start();
require_once 'includes/session.php';
require_once 'includes/db_config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ===============================
   âœ… LOGIN CHECK
================================ */
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userid = $_SESSION['user_id'];

/* ===============================
   âœ… API URLs
================================ */
$getApi = API_BASE_URL . "getNotificationlist.php";
$updateApi = API_BASE_URL . "updatenotification.php";

/* ===============================
   âœ… MARK AS READ
================================ */
if (isset($_GET['read_id'])) {

    $data = json_encode([
        "notification_id" => intval($_GET['read_id'])
    ]);

    $ch = curl_init($updateApi);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10
    ]);

    curl_exec($ch);
    curl_close($ch);

    // redirect
    if (isset($_GET['redirect']) && $_GET['redirect'] == 'referral') {
        header("Location: referral_list.php");
    } else {
        header("Location: promotor_notification.php");
    }
    exit;
}

/* ===============================
   âœ… FETCH NOTIFICATIONS
================================ */
$postData = json_encode([
    "userid" => $userid
]);

$ch = curl_init($getApi);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Pacific iConnect</title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">

    <style>
        :root {
            --primary: #483EA8;
            --blue-btn: #2563eb;
            --text-muted: #64748b;
            --border-light: #e2e8f0;
            --bg-body: #f8fafc;
            --white: #ffffff;
            --info-bg: #f8faff;
        }

        body {
            background: var(--bg-body);
            font-family: 'Segoe UI', Roboto, sans-serif;
        }

        .notifications-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .noti-card {
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border-light);
            margin-bottom: 20px;
            position: relative;
        }

        /* ðŸ”µðŸ”˜ DOT STYLE */
        .status-dot {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .dot-blue {
            background: #ef4444;
        }

        /* unread */
        .dot-grey {
            background: #cbd5e1;
        }

        /* read */

        .noti-header {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .icon-box {
            width: 45px;
            height: 45px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
        }

        .noti-title {
            font-weight: 700;
        }

        .noti-body {
            background: var(--info-bg);
            padding: 15px 20px;
            color: var(--text-muted);
        }

        .noti-footer {
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
        }

        .view-link {
            color: var(--blue-btn);
            font-weight: 600;
            text-decoration: none;
        }

        .text-center {
            text-align: center;
            margin-top: 40px;
        }
    </style>
</head>

<body>

    <?php include "includes/preloader.php"; ?>
    <?php include "includes/promoter_header.php"; ?>

    <div class="notifications-container">

        <?php if (!empty($result['notifications'])): ?>

            <?php foreach ($result['notifications'] as $noti): ?>

                <div class="noti-card">

                    <!-- ðŸ”µ / âšª STATUS DOT -->
                    <div class="status-dot <?php echo ($noti['readstatus'] == 0) ? 'dot-blue' : 'dot-grey'; ?>"></div>

                    <div class="noti-header">
                        <div class="icon-box">
                            <i class="fas fa-bell"></i>
                        </div>

                        <div class="noti-title">
                            <?php echo htmlspecialchars($noti['title']); ?>
                        </div>
                    </div>

                    <div class="noti-body">
                        <?php echo htmlspecialchars($noti['msg']); ?>
                    </div>

                    <div class="noti-footer">
                        <span><?php echo $noti['datetime']; ?></span>

                        <!-- ALWAYS CLICKABLE -->
                        <?php if ($noti['readstatus'] == 0): ?>
                            <a href="?read_id=<?php echo $noti['id']; ?>&redirect=referral" class="view-link">
                                View Details
                            </a>
                        <?php else: ?>
                            <a href="referral_list.php" class="view-link">
                                View Details
                            </a>
                        <?php endif; ?>
                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="text-center">
                <p>No notifications found</p>
            </div>

        <?php endif; ?>

    </div>

</body>

</html>