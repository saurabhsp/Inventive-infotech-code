<?php
if (!isset($con)) die("Database not initialized.");

date_default_timezone_set('Asia/Kolkata');

/* ---------------- LOGGED IN USER ---------------- */
$me = function_exists('current_user') ? current_user() : [];

$logged_admin_id   = (int)($me['id'] ?? 0);
// $logged_admin_id   = 1;
$logged_admin_name = htmlspecialchars($me['name'] ?? '', ENT_QUOTES, 'UTF-8');

$logged_role_id = (int)($me['role_id'] ?? 0);
$logged_role_name = '';

if ($logged_role_id > 0) {
    $stmt = $con->prepare("SELECT name FROM jos_admin_roles WHERE id = ?");
    $stmt->bind_param("i", $logged_role_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $logged_role_name = $row['name'];
    }
    $stmt->close();
}


if ($logged_admin_id <= 0) {
    die("Invalid user session.");
}

/* ---------------- DATE RANGE FILTER ---------------- */

$range = $_GET['range'] ?? 'daily';   // default = today

if ($range === 'monthly') {

    $from = date('Y-m-01 00:00:00');
    $to   = date('Y-m-t 23:59:59');
} elseif ($range === 'lifetime') {

    $from = null;
    $to   = null;
} else { // daily

    $from = date('Y-m-d 00:00:00');
    $to   = date('Y-m-d 23:59:59');
}

/* ---------------- HELPERS ---------------- */
if (!function_exists('fetch_one')) {
    function fetch_one(mysqli $con, string $sql, string $types, array $params, string $field = 'total'): int
    {
        $stmt = $con->prepare($sql);
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return (int)($row[$field] ?? 0);
    }
}


/* ---------------- TABLE ---------------- */
$usersTbl = 'jos_app_users';
$crmLeads_tbl = 'jos_app_crm_leads';
$subscriptionTbl = 'jos_app_usersubscriptionlog';
$historyTbl = 'jos_app_crm_lead_status_history';
$statusTbl  = 'jos_app_crm_lead_statuses';
/* ============================================================
   FOLLOW-UP DASHBOARD COUNTS
   ============================================================ */

/* ---------------- TODAY FOLLOW-UP ---------------- */
$todayFollowup = fetch_one(
    $con,
    "SELECT COUNT(*) AS total
    FROM $historyTbl h
    JOIN (
        SELECT lead_id, MAX(id) AS max_id
        FROM $historyTbl
        GROUP BY lead_id
    ) x ON x.max_id = h.id
    JOIN $statusTbl s ON s.id = h.to_status_id
    JOIN $crmLeads_tbl l ON l.id = h.lead_id
    WHERE s.status_code = 'FOLLOW_UP'
    AND DATE(h.next_followup_dt) = CURDATE()
    AND (
        l.assigned_by = ?
        OR (
            (l.assigned_by IS NULL OR l.assigned_by = 0)
            AND l.created_by = ?
        )
    )",
    "ii",
    [$logged_admin_id, $logged_admin_id]
);

/* ---------------- MISSED FOLLOW-UP ---------------- */
$missedFollowup = fetch_one(
    $con,
    "SELECT COUNT(*) AS total
    FROM $historyTbl h
    JOIN (
        SELECT lead_id, MAX(id) AS max_id
        FROM $historyTbl
        GROUP BY lead_id
    ) x ON x.max_id = h.id
    JOIN $statusTbl s ON s.id = h.to_status_id
    JOIN $crmLeads_tbl l ON l.id = h.lead_id
    WHERE s.status_code = 'FOLLOW_UP'
    AND h.next_followup_dt IS NOT NULL
    AND h.next_followup_dt < NOW()
    AND (
        l.assigned_by = ?
        OR (
            (l.assigned_by IS NULL OR l.assigned_by = 0)
            AND l.created_by = ?
        )
    )",
    "ii",
    [$logged_admin_id, $logged_admin_id]
);

/* ---------------- COMPLETED FOLLOW-UP ---------------- */
$completedFollowup = fetch_one(
    $con,
    "SELECT COUNT(DISTINCT h1.lead_id) AS total
    FROM $historyTbl h1
    JOIN $statusTbl s1 ON s1.id = h1.to_status_id
    JOIN $historyTbl h2 ON h2.lead_id = h1.lead_id
    JOIN $statusTbl s2 ON s2.id = h2.to_status_id
    JOIN $crmLeads_tbl l ON l.id = h1.lead_id
    WHERE s1.status_code = 'FOLLOW_UP'
    AND s2.status_code != 'FOLLOW_UP'
    AND h2.id > h1.id
    AND (
        l.assigned_by = ?
        OR (
            (l.assigned_by IS NULL OR l.assigned_by = 0)
            AND l.created_by = ?
        )
    )",
    "ii",
    [$logged_admin_id, $logged_admin_id]
);



/* ============================================================
   Jobseeker DASHBOARD COUNTS
   ============================================================ */

/* 1️⃣ Assigned Jobseeker (My Recruiters) 
   profile_type = 2 (Jobseeker)
   assigned_to = logged in user
*/
if ($range === 'lifetime') {

    $assignedEmployersCount = fetch_one(
        $con,
        "SELECT COUNT(*) as total 
         FROM `$usersTbl`
         WHERE profile_type_id = 2
         AND ac_manager_id = ?
         AND ac_manager_assigned_at IS NOT NULL",
        "i",
        [$logged_admin_id]
    );
} else {

    $assignedEmployersCount = fetch_one(
        $con,
        "SELECT COUNT(*) as total 
         FROM `$usersTbl`
         WHERE profile_type_id = 2
         AND ac_manager_id = ?
         AND ac_manager_assigned_at IS NOT NULL
         AND ac_manager_assigned_at BETWEEN ? AND ?",
        "iss",
        [$logged_admin_id, $from, $to]
    );
}




/* 2️⃣ Self Converted Jobseekers (No logic for now) */
$selfConvertedEmployersCount = 0;


/* 3️⃣ Assigned Leads
   profile_type = 1
   assigned_to = logged in user
*/
if ($range === 'lifetime') {

    $assignedLeadsCount = fetch_one(
        $con,
        "SELECT COUNT(*) as total 
         FROM `$crmLeads_tbl`
         WHERE profile_type = 2
         AND assigned_to = ?",
        "i",
        [$logged_admin_id]
    );
} else {

    $assignedLeadsCount = fetch_one(
        $con,
        "SELECT COUNT(*) as total 
         FROM `$crmLeads_tbl`
         WHERE profile_type = 2
         AND assigned_to = ?
         AND created_at BETWEEN ? AND ?",
        "iss",
        [$logged_admin_id, $from, $to]
    );
}



/* 4️⃣ Self Leads
   profile_type = 1
   created_by = logged in user
*/
if ($range === 'lifetime') {

    $selfLeadsCount = fetch_one(
        $con,
        "SELECT COUNT(*) as total 
         FROM `$crmLeads_tbl`
         WHERE profile_type = 2
         AND created_by = ?",
        "i",
        [$logged_admin_id]
    );
} else {

    $selfLeadsCount = fetch_one(
        $con,
        "SELECT COUNT(*) as total 
         FROM `$crmLeads_tbl`
         WHERE profile_type = 2
         AND created_by = ?
         AND created_at BETWEEN ? AND ?",
        "iss",
        [$logged_admin_id, $from, $to]
    );
}





/* 5️⃣ Revenue + Purchase Count */


/* 5️⃣ Revenue + Purchase Count */
// createdat > assigned_at
/* Condition added:
   usl.created_at > u.ac_manager_assigned_at
*/

if ($range === 'lifetime') {

    $stmt = $con->prepare("
        SELECT 
            COUNT(usl.id) AS purchases,
            COALESCE(SUM(usl.amount_paid),0) AS subscriptionrev
        FROM `$usersTbl` u
        JOIN `$subscriptionTbl` usl ON usl.userid = u.id
        WHERE u.profile_type_id = 2
        AND u.ac_manager_id = ?
        AND u.ac_manager_assigned_at IS NOT NULL
        AND usl.payment_status = 'success'
        AND usl.created_at > u.ac_manager_assigned_at
    ");

    $stmt->bind_param("i", $logged_admin_id);
} else {

    $stmt = $con->prepare("
        SELECT 
            COUNT(usl.id) AS purchases,
            COALESCE(SUM(usl.amount_paid),0) AS subscriptionrev
        FROM `$usersTbl` u
        JOIN `$subscriptionTbl` usl ON usl.userid = u.id
        WHERE u.profile_type_id = 2
        AND u.ac_manager_id = ?
        AND u.ac_manager_assigned_at IS NOT NULL
        AND usl.payment_status = 'success'
        AND usl.created_at > u.ac_manager_assigned_at
        AND usl.created_at BETWEEN ? AND ?
    ");

    $stmt->bind_param(
        "iss",
        $logged_admin_id,
        $from,
        $to
    );
}

$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$revenue_subscription   = (float)($row['subscriptionrev'] ?? 0);
$purchases = (int)($row['purchases'] ?? 0);
$net_revenue = $revenue_subscription * 0.75;

?>


<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>
        <?php if (!empty($logged_role_name)) : ?>
            <?= htmlspecialchars($logged_role_name) ?>
        <?php else: ?>
            Dashboard
        <?php endif; ?>
    </title>


    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --border: #334155;
            --primary: #3b82f6;
            --shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
        }

        body {
            margin: 0;
            font-family: Segoe UI, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        .wrapper {
            max-width: 1200px;
            margin: auto;
            padding: 30px;
        }

        h1 {
            margin: 0 0 5px 0;
        }

        .muted {
            color: var(--muted);
            font-size: 14px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 12px;
            background: none;
        }

        .range-buttons button {
            background: var(--card);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            margin-right: 6px;
            transition: .2s;
        }

        .range-buttons button:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .section-label {
            margin: 25px 0 15px;
            font-weight: 600;
            font-size: 18px;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 15px;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: .2s;
            position: relative;
        }

        .card:hover {
            transform: translateY(-3px);
        }

        /* UPDATED CARD TITLE (more visible + bold) */
        .card-title {
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.3px;
            margin-bottom: 5px;
        }

        .card-value {
            font-size: 34px;
            font-weight: 700;
            margin: 10px 0;
        }

        .kpi-card {
            min-height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .btn-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--primary);
            font-size: 12px;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-link:hover {
            text-decoration: underline;
        }

        .card-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 8px;
        }

        .actions {
            margin-top: 20px;
        }

        .actions a {
            color: var(--primary);
            text-decoration: none;
            margin-right: 15px;
        }

        .actions a:hover {
            text-decoration: underline;
        }

        /* Today date line */
        .todayline {
            margin-top: 6px;
            font-size: 13px;
            color: var(--muted);
        }

        /* follow up css card */
        .followup-grid {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            /* allow wrapping */
        }

        .followup-card {
            flex: 1;
            min-width: 220px;
            /* controls when it breaks */
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow);
            transition: .2s;
        }

        .followup-card:hover {
            transform: translateY(-2px);
        }

        .followup-title {
            font-size: 13px;
            color: #94a3b8;
            font-weight: 600;
        }

        .followup-value {
            background: #3b82f6;
            color: #fff;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
        }

        .topbar {
            position: static !important;
            top: auto !important;
            z-index: auto !important;
        }
    </style>
</head>

<body>

    <div class="wrapper">

        <div class="topbar">
            <div>
                <h1> <?php if (!empty($logged_role_name)) : ?>
                        <?= htmlspecialchars($logged_role_name) ?>
                    <?php else: ?>
                        Dashboard
                    <?php endif; ?></h1>
                <div class="muted">Stats for <?= $logged_admin_name ?></div>
                <div id="todayDate" class="todayline"></div>
            </div>

            <div class="range-buttons">
                <a href="?range=daily"><button type="button">Today</button></a>
                <a href="?range=monthly"><button type="button">This Month</button></a>
                <a href="?range=lifetime"><button type="button">Lifetime</button></a>
            </div>

        </div>

        <div class="section-label">
            <?= ucfirst($range) ?> Performance
        </div>

        <div class="kpi-grid">

            <div class="card kpi-card">
                <div>
                    <div class="card-title">Assigned Jobseekers</div>
                    <div class="card-value" id="assigned_emp"><?= $assignedEmployersCount ?></div>
                </div>
                <div class="card-actions">
                    <a class="btn-link" href="#" onclick="openBreakdown('employers_assigned'); return false;">View Details →</a>
                </div>
            </div>

            <div class="card kpi-card">
                <div>
                    <div class="card-title">Self Converted Jobseekers</div>
                    <div class="card-value" id="self_converted"> <?= $selfConvertedEmployersCount ?></div>
                </div>
                <div class="card-actions">
                    <a class="btn-link" href="#" onclick="openBreakdown('employers_converted'); return false;">View Details →</a>
                </div>
            </div>

            <div class="card kpi-card">
                <div>
                    <div class="card-title">Assigned Leads</div>
                    <div class="card-value" id="assigned_leads"><?= $assignedLeadsCount ?></div>
                </div>
                <div class="card-actions">
                    <a class="btn-link" href="#" onclick="openBreakdown('leads_assigned'); return false;">View Details →</a>
                </div>
            </div>

            <div class="card kpi-card">
                <div>
                    <div class="card-title">Self Leads</div>
                    <div class="card-value" id="self_leads"><?= $selfLeadsCount ?></div>
                </div>
                <div class="card-actions">
                    <a class="btn-link" href="#" onclick="openBreakdown('leads_self'); return false;">View Details →</a>
                </div>
            </div>

            <div class="card kpi-card">
                <div>
                    <div class="card-title">Revenue</div>

                    <div class="card-value" id="subscriptionrev">
                        ₹<?= number_format($net_revenue, 2) ?>
                    </div>

                    <div style="font-size:16px; ">
                        Subscription: ₹<?= number_format($revenue_subscription, 2) ?>
                    </div>
                </div>
                <div class="card-actions">
                    <a class="btn-link" href="#" onclick="openBreakdown('subscriptionrev'); return false;">View Details →</a>
                </div>
            </div>

            <!--<div class="card kpi-card">-->
            <!--    <div>-->
            <!--        <div class="card-title">Conversion %</div>-->
            <!--        <div class="card-value" id="conversion">0%</div>-->
            <!--    </div>-->
            <!--    <div class="card-actions">-->
            <!--        <span class="muted" style="font-size:12px;">Self Converted / Self Leads</span>-->
            <!--    </div>-->
            <!--</div>-->

        </div>


        <hr style="opacity:.18;margin:14px 0">

        <div class="section-label">
            Follow-up Counts
        </div>

        <div class="followup-grid">

            <div class="followup-card" onclick="openFollowupBreakdown('today');">
                <div class="followup-title">Today's Follow-up</div>
                <div class="followup-value"><?= $todayFollowup ?></div>
            </div>

            <div class="followup-card" onclick="openFollowupBreakdown('completed');">
                <div class="followup-title">Completed</div>
                <div class="followup-value"><?= $completedFollowup ?></div>
            </div>

            <div class="followup-card" onclick="openFollowupBreakdown('missed');">
                <div class="followup-title">Missed</div>
                <div class="followup-value"><?= $missedFollowup ?></div>
            </div>

        </div>


        <div class="actions">
            <a href="/adminconsole/operations/jobpost_summary.php">View Matching Jobs </a>
            <a href="/adminconsole/operations/lead.php">Add New Lead</a>
            <!-- <a href="#">Revenue Details</a>-->
        </div>

    </div>

    <form id="dashboardPostForm" method="post" style="display:none;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="mode" id="f_mode">
        <input type="hidden" name="range" value="<?= $range ?>">
        <input type="hidden" name="admin_id" value="<?= $logged_admin_id ?>">
        <input type="hidden" name="profile_type_id" value="2">
        <input type="hidden" name="from" value="<?= $range !== 'lifetime' ? $from : '' ?>">
        <input type="hidden" name="to" value="<?= $range !== 'lifetime' ? $to : '' ?>">
    </form>

    <script>
        function openBreakdown(mode) {

            const form = document.getElementById('dashboardPostForm');

            document.getElementById('f_mode').value = mode;

            // 🔹 Assigned Jobseekers → POST to my_jobseeker_list.php
            if (mode === 'employers_assigned') {
                form.action = "/adminconsole/operations/my_jobseeker_list.php";
            } else if (mode === 'subscriptionrev') {
                form.action = "/adminconsole/operations/subscription_invoicelist.php";
            } else {
                // 🔹 Default → lead list
                form.action = "/adminconsole/operations/lead_list.php";
            }

            form.submit();
        }

          //for folowup
        function openFollowupBreakdown(type) {

            const form = document.getElementById('dashboardPostForm');

            // Set mode based on card clicked
            if (type === 'today') {
                document.getElementById('f_mode').value = 'followup_today';
            } else if (type === 'completed') {
                document.getElementById('f_mode').value = 'followup_completed';
            } else if (type === 'missed') {
                document.getElementById('f_mode').value = 'followup_missed';
            }
            // Redirect to followup listing page
            form.action = "/adminconsole/operations/lead_list.php";

            form.submit();
        }


        function showTodayDate() {
            const now = new Date();
            const days = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
            const dayName = days[now.getDay()];
            const options = {
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            };
            const formattedDate = now.toLocaleDateString("en-IN", options);
            document.getElementById("todayDate").innerText = dayName + ", " + formattedDate;
        }

        showTodayDate();
        setRange("daily");
    </script>

</body>

</html>