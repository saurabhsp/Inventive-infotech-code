<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db_config.php';

// change this based on your login
$userid = $_SESSION['userid'] ?? 1;

// API URLs
$getApi = API_BASE_URL . "getNotificationlist.php";
$updateApi = API_BASE_URL . "updatenotification.php";

// MARK AS READ USING API
if (isset($_GET['read_id'])) {
    $data = json_encode([
        "notification_id" => intval($_GET['read_id'])
    ]);

    $ch = curl_init($updateApi);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],

        // âœ… ADD THIS
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10
    ]);

    curl_exec($ch);
    curl_close($ch);

    header("Location: notification.php");
    exit;
}

// FETCH DATA USING API
$postData = json_encode([
    "userid" => $userid
]);

$ch = curl_init($getApi);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],

    // âœ… ADD THIS
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$notifications = [];

if (!empty($data) && isset($data['status']) && $data['status'] == true) {
    $notifications = $data['notifications'];
    print_r($notifications);
}





if (isset($_POST['read_id'])) {

    require_once __DIR__ . '/includes/db_config.php';

    $updateApi = API_BASE_URL . "updatenotification.php";

    $data = json_encode([
        "notification_id" => intval($_POST['read_id'])
    ]);

    $ch = curl_init($updateApi);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10
    ]);

    curl_exec($ch);
    curl_close($ch);
}








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
            --unread-dot: #2563eb;
            --location-icon: #ef4444;
        }



        body {
            background-color: var(--bg-body);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* --- HEADER NAVIGATION --- */


        /* --- MAIN CONTENT --- */
        .noti-container {
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

            .container {
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
    </style>
</head>

<body>

    <?php include "includes/preloader.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="container">

        <?php if (!empty($notifications)): ?>

            <?php foreach ($notifications as $n): ?>

                <div class="noti-card">

                    <?php if ($n['readstatus'] == 0): ?>
                        <div class="unread-indicator"></div>
                    <?php endif; ?>

                    <div class="noti-header">
                        <div class="icon-box 
                    <?php echo ($n['type'] == 'location') ? 'icon-location' : 'icon-call'; ?>">

                            <i class="<?php
                                        echo ($n['type'] == 'location')
                                            ? 'fas fa-map-marker-alt'
                                            : 'fas fa-phone-alt'; ?>">
                            </i>
                        </div>

                        <div class="noti-title">
                            <?php echo htmlspecialchars($n['title']); ?>
                        </div>
                    </div>

                    <div class="noti-body">
                        <?php echo htmlspecialchars($n['msg']); ?>
                    </div>

                    <div class="noti-footer">
                        <span class="noti-time">
                            <?php echo $n['datetime']; ?>
                        </span>

                        <div style="display:flex; gap:10px; align-items:center;">

                            <!-- âœ… VIEW DETAILS BUTTON -->
                            <!-- âœ… VIEW DETAILS ALWAYS VISIBLE -->
                            <?php if (in_array($n['type'], ['status_update', 'Interview_Notice', 'Application_Action'])): ?>

                                <!-- ðŸ‘‰ REAL REDIRECT (POST) -->
                                <form action="application-details.php" method="POST" style="margin:0;">
                                    <input type="hidden" name="read_id" value="<?php echo $n['id']; ?>">
                                    <input type="hidden" name="job_id" value="<?php echo $n['job_id']; ?>">
                                    <input type="hidden" name="application_id" value="<?php echo $n['application_id']; ?>">

                                    <button type="submit" class="view-link" style="border:none; background:none; cursor:pointer;">
                                        View Details
                                    </button>
                                </form>


                            <?php elseif (in_array($n['type'], ['job', 'Job_Actionlog'])): ?>

                                <!-- âœ… JOB REDIRECT -->
                                <?php
                                $jobPage = ($n['job_listing_type'] == 1)
                                    ? 'premium-job-details.php'
                                    : 'standard-job-details.php';
                                ?>

                                <form action="<?php echo $jobPage; ?>" method="POST" style="margin:0;">
                                    <input type="hidden" name="job_listing_type" value="<?php echo $n['job_listing_type']; ?>">
                                    <input type="hidden" name="job_id" value="<?php echo $n['job_id']; ?>">
                                    <input type="hidden" name="application_id" value="<?php echo $n['application_id']; ?>">
                                    <input type="hidden" name="read_id" value="<?php echo $n['id']; ?>">

                                    <button type="submit" class="view-link" style="border:none; background:none; cursor:pointer;">
                                        View Job
                                    </button>
                                </form>

                            <?php else: ?>

                                <!-- ðŸ‘‰ DUMMY LINK (#) -->
                                <a href="#" class="view-link" onclick="return false;">
                                    View Details
                                </a>

                            <?php endif; ?>

                            <!-- EXISTING MARK AS READ -->
                            <?php if ($n['readstatus'] == 0): ?>
                                <a href="?read_id=<?php echo $n['id']; ?>" class="view-link">
                                    Mark as read
                                </a>
                            <?php else: ?>
                                <span style="color:green;">Read</span>
                            <?php endif; ?>

                        </div>
                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>
            <p>No notifications found</p>
        <?php endif; ?>

    </div>

</body>

</html>