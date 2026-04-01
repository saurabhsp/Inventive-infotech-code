<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
$active = "home";
require_once "../web_api/includes/db_config.php";
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
$user         = $_SESSION['user'];
$userid       = $user['id'];
$profile_type = $user['profile_type_id'];
$city         = $user['city_id'] ?? "";
$locality     = "";

$fcm_token  = $user['fcm_token'] ?? '';
$fcm_status = empty($fcm_token) ? "missing" : "valid";


/* ================================================================
   STEP 1 â€” JOB STATUS: Direct DB call â€” NO curl, NO HTTP
   Cached in session for 1 hour so DB is hit only once per hour
   ================================================================ */
$need_status_fetch = (
    !isset($_SESSION['job_status_list']) ||
    !isset($_SESSION['job_status_cache_time']) ||
    (time() - $_SESSION['job_status_cache_time']) > 3600
);

if ($need_status_fetch) {
    require_once __DIR__ . '/../web_api/includes/initialize.php';
    require_once __DIR__ . '/../web_api/getJobstatus.php';

    $_SESSION['job_status_list']       = getJobstatusData(1, 0);
    $_SESSION['job_status_cache_time'] = time();
}

$job_status_list = $_SESSION['job_status_list'] ?? [];


/* ================================================================
   STEP 2 â€” DASHBOARD + KYC via curl_multi with timeouts
   ================================================================ */
$api_url     = API_BASE_URL . "getRecruiterdashboard.php";
$kyc_api_url = API_BASE_URL . "checkRecruiterprofile.php";

function make_curl_handle(string $url, array $payload): \CurlHandle
{
    $json = json_encode($payload);
    $ch   = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/json",
            "Content-Length: " . strlen($json),
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
    ]);
    return $ch;
}

$ch_dashboard = make_curl_handle($api_url, [
    "userid"       => $userid,
    "profile_type" => $profile_type,
    "city"         => $city,
    "locality"     => $locality,
]);

$ch_kyc = make_curl_handle($kyc_api_url, ["userid" => $userid]);

$mh = curl_multi_init();
curl_multi_add_handle($mh, $ch_dashboard);
curl_multi_add_handle($mh, $ch_kyc);

do {
    $status = curl_multi_exec($mh, $running);
    if ($running) curl_multi_select($mh);
} while ($running > 0 && $status === CURLM_OK);

$response_dashboard = curl_multi_getcontent($ch_dashboard);
$response_kyc       = curl_multi_getcontent($ch_kyc);

curl_multi_remove_handle($mh, $ch_dashboard);
curl_multi_remove_handle($mh, $ch_kyc);
curl_multi_close($mh);


/* ================================================================
   PARSE RESPONSES
   ================================================================ */
$data = json_decode($response_dashboard, true) ?? [];

$notifications    = $data['unread_notification_count'] ?? 0;
$_SESSION['notification_count'] = $notifications;

$slider_list      = $data['sliders']           ?? [];
$welcome_message  = $data['welcome_message']   ?? '';
$combined_message = $data['combined_message']  ?? '';
$premium_jobs     = $data['walkin_interviews'] ?? [];
$standard_jobs    = $data['job_vacancies']     ?? [];

$kyc_data       = json_decode($response_kyc, true);
$show_kyc_modal = false;
$kyc_message    = "";

if ($kyc_data && isset($kyc_data['status']) && $kyc_data['status'] === false) {
    $show_kyc_modal = true;
    $kyc_message    = $kyc_data['message'] ?? "KYC Pending";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pacific iConnect â€“ Hire or Get Hired</title>
    <link rel="stylesheet" href="/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<style>
    .empty-state-wrapper {
        width: 100%;
        text-align: center;
        padding: 60px 20px;
        background: transparent;
        border-radius: 16px;
    }

    .empty-state-text {
        font-size: 1.1rem;
        color: #555;
        font-weight: 500;
        margin-bottom: 25px;
    }

    .btn-post-job-empty {
        background: #0f172a;
        color: white;
        border: none;
        padding: 14px 40px;
        border-radius: 30px;
        font-size: 1rem;
        font-weight: 600;
        transition: 0.2s;
        box-shadow: 0 5px 15px rgba(15, 23, 42, 0.2);
    }

    .btn-post-job-empty:hover {
        background: #1e293b;
        transform: translateY(-2px);
    }

    .status-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .status-modal-content {
        background: white;
        width: 100%;
        max-width: 420px;
        padding: 30px 25px;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    }

    .status-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .close-modal {
        cursor: pointer;
        font-size: 1.2rem;
        color: #888;
    }

    .close-modal:hover {
        color: #e53935;
    }

    .input-label {
        display: block;
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 6px;
        font-weight: 600;
    }

    .status-select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 1rem;
    }

    .btn-modal-save {
        background: #2563eb;
        color: white;
        border: none;
        padding: 12px;
        border-radius: 30px;
        font-weight: 700;
        width: 100%;
        cursor: pointer;
    }

    .btn-modal-save:hover {
        background: #1d4ed8;
    }

    .card {
        flex: 0 0 320px;
        height: 232px;
        border-radius: 16px;
        padding: 20px;
        color: white;
        scroll-snap-align: center;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        text-decoration: none;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }

    @media(max-width:900px) {
        .card {
            flex: 0 0 85vw;
            min-width: 85vw;
            max-width: 85vw;
            height: 190px;
        }
    }
</style>

<body>
    <?php include "includes/preloader.php"; ?>
    <?php include "includes/header.php"; ?>

    <div id="editErrorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:white;padding:25px;border-radius:10px;width:350px;text-align:center">
            <h3 style="margin-bottom:10px">Edit Not Allowed</h3>
            <p id="editErrorMessage"></p>
            <button onclick="closeEditModal()" style="margin-top:15px;padding:8px 20px;background:#2563eb;color:white;border:none;border-radius:6px">OK</button>
        </div>
    </div>

    <div id="kycModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:white;padding:25px;border-radius:10px;width:350px;text-align:center">
            <h3 style="margin-bottom:10px">KYC Required</h3>
            <p><?php echo htmlspecialchars($kyc_message); ?></p>
            <div style="margin-top:20px;display:flex;gap:10px;justify-content:center">
                <a href="kyc_upload.php" style="padding:8px 18px;background:#16a34a;color:white;border-radius:6px;text-decoration:none">Do KYC</a>
                <button onclick="closeKycModal()" style="padding:8px 18px;background:#2563eb;color:white;border:none;border-radius:6px">Close</button>
            </div>
        </div>
    </div>

    <div class="boxed-container welcome-text"><?php echo $welcome_message; ?></div>

    <?php if (!empty($slider_list)): ?>
        <div class="slider-wrapper">
            <div class="slider-scroll" id="cardSlider">
                <?php
                $themes = ['c1', 'c2', 'c3', 'c4'];
                $i = 0;
                foreach ($slider_list as $slide):
                    $theme = $themes[$i % 4];
                    $url = '#';
                    if (($slide['action_type'] ?? '') === 'link') $url = $slide['action_value'];
                    elseif (($slide['action_type'] ?? '') === 'job') $url = "/jobs/" . $slide['action_value'];
                ?>
                    <a href="<?= htmlspecialchars($url) ?>" class="card <?= $theme ?>" loading="lazy"
                        style="background-image:url('<?= htmlspecialchars($slide['image'] ?? '/assets/img/slider-default.jpg') ?>');background-size:cover;background-position:center;">
                    </a>
                <?php $i++;
                endforeach; ?>
            </div>
            <div class="dots">
                <div class="dot active"></div>
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($premium_jobs) && empty($standard_jobs)): ?>
        <div class="empty-state-wrapper">
            <div class="empty-state-text"><?php echo $combined_message; ?></div>
            <button class="btn-post-job-empty">Post Jobs</button>
        </div>
    <?php else: ?>

        <div class="boxed-container section-header">
            <div class="section-title">Premium Jobs</div>
            <?php if (!empty($premium_jobs)): ?><a href="premium-job-list.php" class="btn-view-all">View All</a><?php endif; ?>
        </div>

        <?php if (!empty($premium_jobs)): ?>
            <div class="boxed-container">
                <button class="scroll-arrow left-arrow" onclick="scrollJobSlider(this,-1)"><i class="fas fa-chevron-left"></i></button>
                <button class="scroll-arrow right-arrow" onclick="scrollJobSlider(this,1)"><i class="fas fa-chevron-right"></i></button>
                <div class="jobs-slider-container drag-slider">
                    <?php foreach ($premium_jobs as $job):
                        $plan = intval($job['plan_display_status'] ?? 3);
                        if ($plan == 1) {
                            $plan_class = "gold";
                            $plan_text = "GOLD MEMBER";
                        } elseif ($plan == 2) {
                            $plan_class = "silver";
                            $plan_text = "SILVER MEMBER";
                        } else {
                            $plan_class = "bronze";
                            $plan_text = "BRONZE MEMBER";
                        }
                    ?>
                        <div class="r-job-card">
                            <div class="menu-dot-container">
                                <div class="menu-dot-icon" onclick="toggleCardMenu(event,this)"><i class="fas fa-ellipsis-v"></i></div>
                                <div class="card-menu-dropdown">
                                    <a href="javascript:void(0)" onclick="checkPremiumEdit(<?= $job['id'] ?>)"><i class="fas fa-edit"></i> Edit Job</a>
                                </div>
                            </div>
                            <div class="card-head">
                                <div class="logo-box">
                                    <div class="shiny-ring <?= $plan_class ?>"></div>
                                    <img src="<?= htmlspecialchars($job['company_logo']) ?>" class="company-logo">
                                    <div class="member-badge <?= $plan_class ?>"><?= $plan_text ?></div>
                                </div>
                                <div class="job-info">
                                    <div class="job-title"><?= htmlspecialchars($job['job_position'] ?? '') ?></div>
                                    <div class="job-meta">Date: <?= htmlspecialchars($job['created_at']) ?? '' ?></div>
                                    <div class="job-status">Status: <?= htmlspecialchars($job['job_status']) ?? '' ?></div>
                                </div>
                            </div>
                            <div class="emp-stats-row">
                                <div class="emp-stat-box">Views: <span class="emp-stat-num"><?= intval($job['visit_count'] ?? 0) ?></span></div>
                                <div class="emp-stat-box">Calls: <span class="emp-stat-num"><?= intval($job['call_count'] ?? 0) ?></span></div>
                                <div class="emp-stat-box">Chats: <span class="emp-stat-num"><?= intval($job['whatsapp_count'] ?? 0) ?></span></div>
                                <div class="emp-stat-box">Locations: <span class="emp-stat-num"><?= intval($job['location_count'] ?? 0) ?></span></div>
                            </div>
                            <div class="apps-count">Applications <span class="apps-num"><?= $job['application_count'] ?></span></div>
                            <div class="card-actions">
                                <form action="applications.php" method="POST" style="flex:1;display:flex;">
                                    <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                    <button type="submit" class="btn-card">
                                        View<br>Applications
                                    </button>
                                </form>
                                <form action="premium-job-details.php" method="POST" style="flex:1;display:flex;">
                                    <input type="hidden" name="id" value="<?= $job['id'] ?>">
                                    <input type="hidden" name="plan_display_status" value="<?= htmlspecialchars($job['plan_display_status'] ?? 3) ?>">
                                    <button type="submit" class="btn-card">View Job</button>
                                </form>
                                <button class="btn-card" onclick="openPremiumStatusModal(<?= $job['id'] ?>)">Change Status</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state-wrapper">
                <div class="empty-state-text"><?php echo $data['walkin_message'] ?? ''; ?></div>
                <button class="btn-post-job-empty">Post Jobs</button>
            </div>
        <?php endif; ?>

        <div class="boxed-container section-header">
            <div class="section-title">Standard Jobs</div>
            <?php if (!empty($standard_jobs)): ?><a href="standard-job-list.php" class="btn-view-all">View All</a><?php endif; ?>
        </div>

        <!--<div class="boxed-container section-header" style="margin-top:20px;">-->
        <!--    <div class="section-title">Standard Jobs</div>-->
        <!--    <?php if (!empty($standard_jobs)): ?><button class="btn-view-all">View All</button><?php endif; ?>-->
        <!--</div>-->

        <?php if (!empty($standard_jobs)): ?>
            <div class="boxed-container" style="margin-bottom:50px;">
                <button class="scroll-arrow left-arrow" onclick="scrollJobSlider(this,-1)"><i class="fas fa-chevron-left"></i></button>
                <button class="scroll-arrow right-arrow" onclick="scrollJobSlider(this,1)"><i class="fas fa-chevron-right"></i></button>
                <div class="jobs-slider-container drag-slider">
                    <?php foreach ($standard_jobs as $job): ?>
                        <div class="r-job-card">
                            <div class="menu-dot-container">
                                <div class="menu-dot-icon" onclick="toggleCardMenu(event,this)"><i class="fas fa-ellipsis-v"></i></div>
                                <div class="card-menu-dropdown">
                                    <a href="javascript:void(0)" onclick="checkStandardEdit(<?= $job['id'] ?>)"><i class="fas fa-edit"></i> Edit Job</a>
                                </div>
                            </div>
                            <div class="card-head">
                                <div class="logo-box" style="width:65px;height:65px;">
                                    <img src="<?= htmlspecialchars($job['company_logo']) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;border:1px solid #eee;">
                                </div>
                                <div class="job-info">
                                    <div class="job-title"><?= htmlspecialchars($job['job_position']) ?></div>
                                    <div class="job-meta">Date: <?= htmlspecialchars($job['created_at']) ?></div>
                                    <div class="job-status">Status: <?= htmlspecialchars($job['job_status']) ?></div>
                                </div>
                            </div>
                            <div class="emp-stats-row">
                                <div class="emp-stat-box">Views: <span class="emp-stat-num"><?= intval($job['visit_count']) ?></span></div>
                                <div class="emp-stat-box">Calls: <span class="emp-stat-num"><?= intval($job['call_count']) ?></span></div>
                                <div class="emp-stat-box" style="border:none;">Chats: <span class="emp-stat-num"><?= intval($job['whatsapp_count']) ?></span></div>
                            </div>
                            <div class="apps-count">Applications <span class="apps-num"><?= intval($job['application_count']) ?></span></div>
                            <div class="card-actions">
                                <form action="applications.php" method="POST" style="flex:1;display:flex;">
                                    <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                    <button type="submit" class="btn-card">
                                        View<br>Applications
                                    </button>
                                </form>

                                <form action="standard-job-details.php" method="POST" style="flex:1;display:flex;">
                                    <input type="hidden" name="id" value="<?= $job['id'] ?>">
                                    <button type="submit" class="btn-card">View Job</button>
                                </form>
                                <button class="btn-card" onclick="openStandardStatusModal(<?= $job['id'] ?>)">Change Status</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state-wrapper">
                <div class="empty-state-text"><?php echo $data['vacancy_message'] ?? ''; ?></div>
                <button class="btn-post-job-empty">Post Jobs</button>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <div class="status-modal-overlay" id="statusModal">
        <div class="status-modal-content">
            <div class="status-modal-header">
                <h3>Update Job Status</h3><i class="fas fa-times close-modal" onclick="closeStatusModal()"></i>
            </div>
            <div style="margin-bottom:25px;">
                <label class="input-label">Select New Status</label>
                <select id="jobStatusSelect" class="status-select">
                    <?php foreach ($job_status_list as $status): ?>
                        <option value="<?= $status['id'] ?>"><?= htmlspecialchars($status['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn-modal-save" onclick="updateJobStatus()">Save Status</button>
        </div>
    </div>

    <div class="status-modal-overlay" id="standardStatusModal">
        <div class="status-modal-content">
            <div class="status-modal-header">
                <h3>Update Job Status</h3><i class="fas fa-times close-modal" onclick="closeStandardStatusModal()"></i>
            </div>
            <div style="margin-bottom:25px;">
                <label class="input-label">Select New Status</label>
                <select id="standardJobStatusSelect" class="status-select">
                    <?php foreach ($job_status_list as $status): ?>
                        <option value="<?= $status['id'] ?>"><?= htmlspecialchars($status['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn-modal-save" onclick="updateStandardJobStatus()">Save Status</button>
        </div>
    </div>

    <?php include "includes/bottom-bar.php"; ?>
    <script>
        window.onload = () => document.getElementById("global-preloader")?.remove();

        function scrollJobSlider(btn, direction) {
            const container = btn.parentElement.querySelector('.jobs-slider-container');
            container.scrollBy({
                left: direction * 385,
                behavior: 'smooth'
            });
        }

        const slider = document.getElementById('cardSlider');
        const dots = document.querySelectorAll('.dot');

        if (slider) {
            let isUserInteracting = false;

            function updateActiveDot() {
                const cardWidth = slider.querySelector('.card').offsetWidth + 20;
                const index = Math.round(slider.scrollLeft / cardWidth);
                dots.forEach((dot, i) => dot.classList.toggle('active', i === index));
            }
            setInterval(() => {
                if (isUserInteracting) return;
                const cardWidth = slider.querySelector('.card').offsetWidth + 20;
                const maxScroll = slider.scrollWidth - slider.clientWidth;
                if (slider.scrollLeft >= maxScroll - 10) slider.scrollTo({
                    left: 0,
                    behavior: 'smooth'
                });
                else slider.scrollBy({
                    left: cardWidth,
                    behavior: 'smooth'
                });
            }, 3000);
            slider.addEventListener('touchstart', () => isUserInteracting = true);
            slider.addEventListener('mousedown', () => isUserInteracting = true);
            window.addEventListener('touchend', () => setTimeout(() => isUserInteracting = false, 2000));
            window.addEventListener('mouseup', () => setTimeout(() => isUserInteracting = false, 2000));
            slider.addEventListener('scroll', updateActiveDot);
        }

        function toggleCardMenu(event, element) {
            event.stopPropagation();
            const dropdown = element.nextElementSibling;
            document.querySelectorAll('.card-menu-dropdown').forEach(menu => {
                if (menu !== dropdown) menu.classList.remove('show');
            });
            dropdown.classList.toggle('show');
        }
        document.addEventListener('click', () => {
            document.querySelectorAll('.card-menu-dropdown').forEach(m => m.classList.remove('show'));
        });

        function checkPremiumEdit(jobId) {
            fetch("/web_api/cehck24hrswalkinjobstatus.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        job_id: jobId
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === true && data.can_update === true) {

                        // Create form dynamically
                        let form = document.createElement("form");
                        form.method = "POST";
                        form.action = "add-premium-job.php";

                        // job_id field
                        let jobInput = document.createElement("input");
                        jobInput.type = "hidden";
                        jobInput.name = "job_id";
                        jobInput.value = jobId;

                        // mode field
                        let modeInput = document.createElement("input");
                        modeInput.type = "hidden";
                        modeInput.name = "mode";
                        modeInput.value = "edit";

                        // ðŸ”¥ ADD THIS (MOST IMPORTANT)
                        let fromInput = document.createElement("input");
                        fromInput.type = "hidden";
                        fromInput.name = "from_page";
                        fromInput.value = "index.php";

                        // Append inputs to form
                        form.appendChild(jobInput);
                        form.appendChild(modeInput);
                        form.appendChild(fromInput);


                        // Append form to body and submit
                        document.body.appendChild(form);
                        form.submit();

                    } else {
                        document.getElementById("editErrorMessage").innerText = data.message;
                        document.getElementById("editErrorModal").style.display = "flex";
                    }
                })
                .catch(err => console.error(err));
        }

        function checkStandardEdit(jobId) {
            fetch("/web_api/check24hrsvacancyjobstatus.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        job_id: jobId
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === true && data.can_update === true) {

                        // Create form dynamically (same as premium)
                        let form = document.createElement("form");
                        form.method = "POST";
                        form.action = "add-standard-job.php";

                        // job_id field
                        let jobInput = document.createElement("input");
                        jobInput.type = "hidden";
                        jobInput.name = "job_id";
                        jobInput.value = jobId;

                        // mode field
                        let modeInput = document.createElement("input");
                        modeInput.type = "hidden";
                        modeInput.name = "mode";
                        modeInput.value = "edit";

                        // detect current page dynamically
                        let fromInput = document.createElement("input");
                        fromInput.type = "hidden";
                        fromInput.name = "from_page";
                        fromInput.value = window.location.pathname.split("/").pop(); // index.php OR standard-job-list.php

                        form.appendChild(jobInput);
                        form.appendChild(modeInput);
                        form.appendChild(fromInput);

                        document.body.appendChild(form);
                        form.submit();

                    } else {
                        document.getElementById("editErrorMessage").innerText = data.message;
                        document.getElementById("editErrorModal").style.display = "flex";
                    }
                })
                .catch(err => console.error(err));
        }

        function closeEditModal() {
            document.getElementById("editErrorModal").style.display = "none";
        }

        let selectedJobId = 0;

        function openPremiumStatusModal(jobId) {
            selectedJobId = jobId;
            document.getElementById("statusModal").style.display = "flex";
        }

        function closeStatusModal() {
            document.getElementById("statusModal").style.display = "none";
        }

        function updateJobStatus() {
            const statusId = document.getElementById("jobStatusSelect").value;
            fetch("/web_api/UpdateWalkininterviewstatus.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        id: selectedJobId,
                        job_status_id: statusId
                    })
                })
                .then(res => res.json()).then(data => {
                    if (data.status) {
                        alert("Status Updated Successfully");
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                }).catch(err => console.error(err));
        }

        let selectedStandardJobId = 0;

        function openStandardStatusModal(jobId) {
            selectedStandardJobId = jobId;
            document.getElementById("standardStatusModal").style.display = "flex";
        }

        function closeStandardStatusModal() {
            document.getElementById("standardStatusModal").style.display = "none";
        }

        function updateStandardJobStatus() {
            const statusId = document.getElementById("standardJobStatusSelect").value;
            fetch("/web_api/updateVacancyjobstatus.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        id: selectedStandardJobId,
                        job_status_id: statusId
                    })
                })
                .then(res => res.json()).then(data => {
                    if (data.status) {
                        alert("Status Updated Successfully");
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                }).catch(err => console.error(err));
        }

        function closeKycModal() {
            document.getElementById("kycModal").style.display = "none";
        }

        <?php if ($show_kyc_modal): ?>
            document.addEventListener("DOMContentLoaded", function() {
                document.getElementById("kycModal").style.display = "flex";
            });
        <?php endif; ?>
    </script>

</body>

</html>