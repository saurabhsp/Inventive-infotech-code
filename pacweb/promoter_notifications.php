<?php
session_start();
require_once 'includes/session.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ===============================
   ✅ LOGIN CHECK
================================ */
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* ===============================
   ✅ DB CONNECTION
================================ */
require_once 'includes/db_config.php';

$userid = $_SESSION['user_id'];


$url = API_BASE_URL . "getNotificationlist.php";
// $url = "https://pacificconnect2.0.inv51.in/webservices/getNotificationlist.php";

$data = [
    "userid" => $userid
];

$options = [
    "http" => [
        "header"  => "Content-Type: application/json\r\n",
        "method"  => "POST",
        "content" => json_encode($data),
    ]
];

$context  = stream_context_create($options);
$response = file_get_contents($url, false, $context);

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
            --primary-light: #eceaf9;
            --blue-btn: #2563eb;
            --text-dark: #1a1a1a;
            --text-muted: #64748b;
            --border-light: #e2e8f0;
            --bg-body: #f8fafc;
            --white: #ffffff;
            --info-bg: #f8faff;
            --unread-dot: #cbd5e1;
            --location-icon: #ef4444;
        }

        * {

            font-family: 'Segoe UI', Roboto, sans-serif;
        }





        .back-btn {
            font-size: 1.2rem;
            color: var(--text-dark);
            cursor: pointer;
        }

        .header-title {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .user-icon {
            font-size: 1.5rem;
            color: var(--blue-btn);
        }

        /* --- MAIN CONTENT --- */
        .notifications-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* --- NOTIFICATION CARD (MATCHING DESKTOP STYLE) --- */
        .noti-card {
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border-light);
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            overflow: hidden;
            position: relative;
            transition: 0.2s;
        }

        .noti-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        /* Status Dot */
        .unread-indicator {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 10px;
            height: 10px;
            background: var(--unread-dot);
            border-radius: 50%;
        }

        /* Top Bar of Card */
        .noti-header {
            padding: 20px 25px;
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
            font-size: 1.2rem;
        }

        .icon-call {
            background: #f1f5f9;
            color: #333;
        }

        .icon-location {
            background: #fef2f2;
            color: var(--location-icon);
        }

        .noti-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        /* Content Area (Grey background like Applications List) */
        .noti-body {
            background: var(--info-bg);
            padding: 15px 25px;
            border-top: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* Footer Area */
        .noti-footer {
            padding: 12px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .noti-time {
            font-size: 0.8rem;
            color: #94a3b8;
            font-weight: 600;
        }

        .view-link {
            font-size: 0.85rem;
            color: var(--blue-btn);
            font-weight: 700;
            text-decoration: none;
        }

        /* --- MOBILE ADJUSTMENTS --- */
        @media (max-width: 600px) {
            header {
                height: 60px;
            }

            .notifications-container {
                margin: 15px auto;
                padding: 0 15px;
            }

            .noti-header {
                padding: 15px;
            }

            .noti-body {
                padding: 15px;
                font-size: 0.9rem;
            }

            .noti-footer {
                padding: 10px 15px;
            }
        }

        .text-center {
            text-align: center;
            margin-top: 40px;
            font-size: 1rem;
            color: #64748b;
            font-weight: 600;
        }
    </style>
</head>

<body>


    <?php include "includes/preloader.php"; ?>
    <?php include "includes/promoter_header.php"; ?>

    <div class="notifications-container">

        <?php if ($result['status'] && !empty($result['notifications'])): ?>

            <?php foreach ($result['notifications'] as $noti): ?>

                <div class="noti-card">

                    <?php if ($noti['readstatus'] == 0): ?>
                        <div class="unread-indicator"></div>
                    <?php endif; ?>

                    <div class="noti-header">

                        <?php
                        // icon based on action_type
                        $iconClass = "fa-bell";
                        $iconBox = "icon-call";

                        if ($noti['action_type'] == 1) {
                            $iconClass = "fa-phone";
                            $iconBox = "icon-call";
                        } elseif ($noti['action_type'] == 2) {
                            $iconClass = "fa-whatsapp";
                            $iconBox = "icon-call";
                        } elseif ($noti['action_type'] == 3) {
                            $iconClass = "fa-map-marker-alt";
                            $iconBox = "icon-location";
                        }
                        ?>

                        <div class="icon-box <?php echo $iconBox; ?>">
                            <i class="fas <?php echo $iconClass; ?>"></i>
                        </div>

                        <div class="noti-title">
                            <?php echo htmlspecialchars($noti['title']); ?>
                        </div>

                    </div>

                    <div class="noti-body">
                        <?php echo htmlspecialchars($noti['msg']); ?>
                    </div>

                    <div class="noti-footer">
                        <span class="noti-time">
                            <?php echo $noti['datetime']; ?>
                        </span>

                        <a href="#" class="view-link">View Details</a>
                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="text-center">
                <p><?php echo $result['message'] ?> </p>
            </div>


        <?php endif; ?>

    </div>
</body>

</html>