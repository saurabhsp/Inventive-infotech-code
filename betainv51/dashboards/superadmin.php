<?php
/* ============================================================
   SUPERADMIN DASHBOARD – ROLE BASED (MULTI ADMIN SAFE)
   Shows data based on logged-in admin
   ============================================================ */

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

/* ---------------- HELPERS ---------------- */
if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('fetch_one')) {
    function fetch_one(mysqli $con, string $sql, string $types, array $params, string $field='cnt'): int {
        $stmt = $con->prepare($sql);
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return (int)($row[$field] ?? 0);
    }
}
if (!function_exists('fetch_kv')) {
    function fetch_kv(mysqli $con, string $sql, string $types, array $params): array {
        $stmt = $con->prepare($sql);
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $out=[];
        while($row=$res->fetch_assoc()) $out[]=$row;
        $stmt->close();
        return $out;
    }
}

/* ---------------- LOGGED IN USER ---------------- */
$me = function_exists('current_user') ? current_user() : [];
$logged_admin_id = (int)($me['id'] ?? 0);

/* ---------------- DATE FILTER ---------------- */
$from_ui = $_GET['from'] ?? date('d-m-Y', strtotime('-30 days'));
$to_ui   = $_GET['to']   ?? date('d-m-Y');

function parse_date($d){
    $p = explode('-', $d);
    return (count($p)==3) ? "$p[2]-$p[1]-$p[0]" : date('Y-m-d');
}

$from = parse_date($from_ui);
$to   = parse_date($to_ui);

/* ---------------- TABLES ---------------- */
$usersTbl='jos_app_users';
$walkinTable='jos_app_walkininterviews';
$vacancyTable='jos_app_jobvacancies';
$appTable='jos_app_applications';
$jobStatusTbl='jos_app_jobstatus';
$appStatusTbl='jos_app_applicationstatus';
$planTbl='jos_app_subscription_plans';
$subLogTbl='jos_app_usersubscriptionlog';

/* ============================================================
   MY ASSIGNED COUNTS (IMPORTANT CONDITION MATCHED)
   ============================================================ */

$myRecruitersCount = fetch_one($con,
"SELECT COUNT(*) cnt FROM `$usersTbl`
 WHERE profile_type_id=1
 AND ac_manager_id=?
 AND DATE(created_at) BETWEEN ? AND ?",
"iss", [$logged_admin_id,$from,$to]);

$myJobSeekersCount = fetch_one($con,
"SELECT COUNT(*) cnt FROM `$usersTbl`
 WHERE profile_type_id=2
 AND ac_manager_id=?
 AND DATE(created_at) BETWEEN ? AND ?",
"iss", [$logged_admin_id,$from,$to]);

$myLeadsCount = fetch_one($con,
"SELECT COUNT(*) cnt FROM jos_app_crm_leads
 WHERE created_by=?
 AND DATE(created_at) BETWEEN ? AND ?",
"iss", [$logged_admin_id,$from,$to]);

/* ============================================================
   GLOBAL COUNTS (FILTERED BY ADMIN IF NEEDED)
   ============================================================ */

$premiumJobs = fetch_one($con,
"SELECT COUNT(*) cnt FROM `$walkinTable`
 WHERE DATE(created_at) BETWEEN ? AND ?",
"ss",[$from,$to]);

$standardJobs = fetch_one($con,
"SELECT COUNT(*) cnt FROM `$vacancyTable`
 WHERE DATE(created_at) BETWEEN ? AND ?",
"ss",[$from,$to]);

$appType1 = fetch_one($con,
"SELECT COUNT(*) cnt FROM `$appTable`
 WHERE job_listing_type=1
 AND DATE(application_date) BETWEEN ? AND ?",
"ss",[$from,$to]);

$appType2 = fetch_one($con,
"SELECT COUNT(*) cnt FROM `$appTable`
 WHERE job_listing_type=2
 AND DATE(application_date) BETWEEN ? AND ?",
"ss",[$from,$to]);

$promoters = fetch_one($con,
"SELECT COUNT(*) cnt FROM `$usersTbl`
 WHERE profile_type_id=3
 AND DATE(created_at) BETWEEN ? AND ?",
"ss",[$from,$to]);

/* ============================================================
   PLAN COUNTS
   ============================================================ */

$recruiterPlans = fetch_kv($con,
"SELECT sp.plan_name, COUNT(usl.id) cnt
 FROM `$planTbl` sp
 LEFT JOIN `$subLogTbl` usl
   ON usl.plan_id=sp.id
   AND usl.payment_status='success'
   AND DATE(usl.created_at) BETWEEN ? AND ?
 WHERE sp.profile_type=1
 GROUP BY sp.id",
"ss",[$from,$to]);

$jobseekerPlans = fetch_kv($con,
"SELECT sp.plan_name, COUNT(usl.id) cnt
 FROM `$planTbl` sp
 LEFT JOIN `$subLogTbl` usl
   ON usl.plan_id=sp.id
   AND usl.payment_status='success'
   AND DATE(usl.created_at) BETWEEN ? AND ?
 WHERE sp.profile_type=2
 GROUP BY sp.id",
"ss",[$from,$to]);

/* ============================================================
   STATUS
   ============================================================ */

$walkinStatus = fetch_kv($con,
"SELECT s.name status_name, COUNT(w.id) cnt
 FROM `$jobStatusTbl` s
 LEFT JOIN `$walkinTable` w
   ON w.job_status_id=s.id
   AND DATE(w.created_at) BETWEEN ? AND ?
 GROUP BY s.id",
"ss",[$from,$to]);

$vacancyStatus = fetch_kv($con,
"SELECT s.name status_name, COUNT(v.id) cnt
 FROM `$jobStatusTbl` s
 LEFT JOIN `$vacancyTable` v
   ON v.job_status_id=s.id
   AND DATE(v.created_at) BETWEEN ? AND ?
 GROUP BY s.id",
"ss",[$from,$to]);

/* ============================================================
   APPLICATION STATUS
   ============================================================ */

$appStatusType1 = fetch_kv($con,
"SELECT s.name status_name, COUNT(a.id) cnt
 FROM `$appStatusTbl` s
 LEFT JOIN `$appTable` a
   ON a.status_id=s.id
   AND a.job_listing_type=1
   AND DATE(a.application_date) BETWEEN ? AND ?
 WHERE s.name<>'All'
 GROUP BY s.id",
"ss",[$from,$to]);

$appStatusType2 = fetch_kv($con,
"SELECT s.name status_name, COUNT(a.id) cnt
 FROM `$appStatusTbl` s
 LEFT JOIN `$appTable` a
   ON a.status_id=s.id
   AND a.job_listing_type=2
   AND DATE(a.application_date) BETWEEN ? AND ?
 WHERE s.name<>'All'
 GROUP BY s.id",
"ss",[$from,$to]);

/* ---------------- RENDER ---------------- */
function render_rows($rows){
$html='';
foreach($rows as $r){
$html.='<div style="display:flex;justify-content:space-between;padding:12px 14px;margin:10px 0;border-radius:14px;background:rgba(0,0,0,0.18);border:1px solid rgba(255,255,255,0.06);">
<div>'.h($r['plan_name'] ?? $r['status_name']).'</div>
<div style="font-weight:800;">'.(int)$r['cnt'].'</div>
</div>';
}
return $html ?: '<div style="opacity:.6;">No Data</div>';
}
?>

<!-- ================= DASHBOARD UI ================= -->
<style>
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

            <!-- <div class="range-buttons">
                <a href="?range=daily"><button type="button">Today</button></a>
                <a href="?range=monthly"><button type="button">This Month</button></a>
                <a href="?range=lifetime"><button type="button">Lifetime</button></a>
            </div> -->

        </div>


<div class="grid-5" style="margin-bottom:18px;">
  <div class="mini-card"><div class="label">My Recruiters</div><div class="value"><?=$myRecruitersCount?></div></div>
  <div class="mini-card"><div class="label">My Job Seekers</div><div class="value"><?=$myJobSeekersCount?></div></div>
  <div class="mini-card"><div class="label">My Leads</div><div class="value"><?=$myLeadsCount?></div></div>
  <div class="mini-card"><div class="label">Promoters</div><div class="value"><?=$promoters?></div></div>
</div>

</div>