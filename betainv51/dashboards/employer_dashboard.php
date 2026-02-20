<?php
if (!isset($con)) die("Database not initialized.");

date_default_timezone_set('Asia/Kolkata');

/* ---------------- LOGGED IN USER ---------------- */
$me = function_exists('current_user') ? current_user() : [];

$logged_admin_id   = (int)($me['id'] ?? 0);
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

/* ============================================================
   EMPLOYER DASHBOARD COUNTS
   ============================================================ */

/* 1️⃣ Assigned Employers (My Recruiters) 
   profile_type = 1 (Employer)
   assigned_to = logged in user
*/
if ($range === 'lifetime') {

    $assignedEmployersCount = fetch_one(
        $con,
        "SELECT COUNT(*) as total 
         FROM `$usersTbl`
         WHERE profile_type_id = 1
         AND ac_manager_id = ?",
        "i",
        [$logged_admin_id]
    );
} else {

    $assignedEmployersCount = fetch_one(
        $con,
        "SELECT COUNT(*) as total 
         FROM `$usersTbl`
         WHERE profile_type_id = 1
         AND ac_manager_id = ?
         AND created_at BETWEEN ? AND ?",
        "iss",
        [$logged_admin_id, $from, $to]
    );
}




/* 2️⃣ Self Converted Employers (No logic for now) */
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
         WHERE profile_type = 1
         AND assigned_to = ?",
        "i",
        [$logged_admin_id]
    );
} else {

    $assignedLeadsCount = fetch_one(
        $con,
        "SELECT COUNT(*) as total 
         FROM `$crmLeads_tbl`
         WHERE profile_type = 1
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
         WHERE profile_type = 1
         AND created_by = ?",
        "i",
        [$logged_admin_id]
    );
} else {

    $selfLeadsCount = fetch_one(
        $con,
        "SELECT COUNT(*) as total 
         FROM `$crmLeads_tbl`
         WHERE profile_type = 1
         AND created_by = ?
         AND created_at BETWEEN ? AND ?",
        "iss",
        [$logged_admin_id, $from, $to]
    );
}



?>


<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>
        <?php if (!empty($logged_role_name)) : ?>
            <?= htmlspecialchars($logged_role_name) ?> -Dashboard
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
    </style>
</head>

<body>

    <div class="wrapper">

        <div class="topbar">
            <div>
                <h1>
                    <?php if (!empty($logged_role_name)) : ?>
                        <?= htmlspecialchars($logged_role_name) ?>
                    <?php else: ?>
                        Dashboard
                    <?php endif; ?>
                </h1>
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
                    <div class="card-title">Assigned Employers</div>
                    <div class="card-value" id="assigned_emp"><?= $assignedEmployersCount ?></div>
                </div>
                <div class="card-actions">
                    <a class="btn-link" href="#" onclick="openBreakdown('employers_assigned'); return false;">View Details →</a>
                </div>
            </div>

            <div class="card kpi-card">
                <div>
                    <div class="card-title">Self Converted Employers</div>
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
                    <div class="card-value" id="revenue">₹0</div>
                </div>
                <div class="card-actions">
                    <a class="btn-link" href="#" onclick="openBreakdown('revenue'); return false;">View Details →</a>
                </div>
            </div>

            <div class="card kpi-card">
                <div>
                    <div class="card-title">Conversion %</div>
                    <div class="card-value" id="conversion">0%</div>
                </div>
                <div class="card-actions">
                    <span class="muted" style="font-size:12px;">Self Converted / Self Leads</span>
                </div>
            </div>

        </div>

        <div class="actions">
            <a href="#" onclick="openBreakdown('leads_self'); return false;">View My Leads</a>
            <a href="#" onclick="openBreakdown('employers_assigned'); return false;">View Employers</a>
            <a href="#" onclick="openBreakdown('revenue'); return false;">Revenue Details</a>
        </div>

    </div>

    <form id="dashboardPostForm" method="post" action="/adminconsole/operations/lead_list.php" style="display:none;">
        <input type="hidden" name="mode" id="f_mode">
        <input type="hidden" name="range" value="<?= $range ?>">
        <input type="hidden" name="admin_id" value="<?= $logged_admin_id ?>">
        <input type="hidden" name="profile_type_id" value="1">
        <input type="hidden" name="from" value="<?= $range !== 'lifetime' ? $from : '' ?>">
        <input type="hidden" name="to" value="<?= $range !== 'lifetime' ? $to : '' ?>">
    </form>

    <script>
        function openBreakdown(mode) {

            const form = document.getElementById('dashboardPostForm');

            document.getElementById('f_mode').value = mode;

            // 🔹 Assigned Jobseekers → POST to my_recruiter_list.php
            if (mode === 'employers_assigned') {
                form.action = "/adminconsole/operations/my_recruiter_list.php";
            } else {
                // 🔹 Default → lead list
                form.action = "/adminconsole/operations/lead_list.php";
            }

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