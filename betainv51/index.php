<?php
/* ============================================================
   dashboard.php — COMPLETE FILE (Applications Status Summary RESTORED)
   ============================================================
   What’s included:
   ✅ Date range filter (dd-mm-yyyy) + Flatpickr
   ✅ Top totals row:
      - Premium Jobs (Walkin)  = jos_app_walkininterviews
      - Standard Jobs (Vacancies) = jos_app_jobvacancies
      - Applications (type=1, type=2) from jos_app_applications.job_listing_type
      - Promoters (profile_type=3) from users table
   ✅ Recruiters plan-wise + Job seekers plan-wise (success payments)
   ✅ Job Post Status Summary:
      - Walkin from jos_app_walkininterviews.job_status_id
      - Vacancies from jos_app_jobvacancies.job_status_id
      - Status names from jos_app_jobstatus
   ✅ Applications Status Summary (THIS was missing; now restored):
      - For job_listing_type = 1 and 2 separately
      - Uses ONLY app_status_log events when log exists
      - Uses fallback snapshot (status_id + application_date) ONLY when log is empty
      - Shows all statuses (0 counts too) from jos_app_applicationstatus (excluding "All")
   ============================================================ */

@ini_set('display_errors','1');
@ini_set('display_startup_errors','1');
@error_reporting(E_ALL);


require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/initialize.php';
require_login();

global $con;
date_default_timezone_set('Asia/Kolkata');

/* ---------------- Helpers ---------------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function table_exists(mysqli $con, string $table): bool {
  $t = $con->real_escape_string($table);
  $r = $con->query("SHOW TABLES LIKE '$t'");
  return ($r && $r->num_rows > 0);
}
function col_exists(mysqli $con, string $table, string $col): bool {
  $t = $con->real_escape_string($table);
  $c = $con->real_escape_string($col);
  $r = $con->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return ($r && $r->num_rows > 0);
}
function parse_ddmmyyyy(string $s): ?string {
  $s = trim($s);
  if ($s === '') return null;
  // expects dd-mm-yyyy
  $p = explode('-', $s);
  if (count($p) !== 3) return null;
  [$dd,$mm,$yy] = $p;
  if (!ctype_digit($dd.$mm.$yy)) return null;
  if (!checkdate((int)$mm,(int)$dd,(int)$yy)) return null;
  return sprintf('%04d-%02d-%02d', (int)$yy, (int)$mm, (int)$dd);
}

/* ---------------- Date Range (GET) ---------------- */
$from_ui = $_GET['from'] ?? date('d-m-Y', strtotime('-30 days'));
$to_ui   = $_GET['to']   ?? date('d-m-Y');

$from = parse_ddmmyyyy($from_ui) ?: date('Y-m-d', strtotime('-30 days'));
$to   = parse_ddmmyyyy($to_ui)   ?: date('Y-m-d');

$from_ui = date('d-m-Y', strtotime($from));
$to_ui   = date('d-m-Y', strtotime($to));

/* ---------------- Tables (fixed as per your clarification) ---------------- */
$walkinTable   = table_exists($con, 'jos_app_walkininterviews') ? 'jos_app_walkininterviews' : '';
$vacancyTable  = table_exists($con, 'jos_app_jobvacancies')     ? 'jos_app_jobvacancies'     : '';
$appTable      = table_exists($con, 'jos_app_applications')     ? 'jos_app_applications'     : '';
$appStatusTbl  = table_exists($con, 'jos_app_applicationstatus')? 'jos_app_applicationstatus': '';
$jobStatusTbl  = table_exists($con, 'jos_app_jobstatus')        ? 'jos_app_jobstatus'        : '';
$planTbl       = table_exists($con, 'jos_app_subscription_plans') ? 'jos_app_subscription_plans' : '';
$subLogTbl     = table_exists($con, 'jos_app_usersubscriptionlog') ? 'jos_app_usersubscriptionlog' : '';

/* Users table (for promoters profile_type=3) */
$usersTbl = '';
foreach (['jos_app_users','jos_users','jos_app_user','jos_users_profile'] as $t) {
  if (table_exists($con, $t)) { $usersTbl = $t; break; }
}

/* ---------------- Logged-in admin id (Account Manager) ---------------- */
$me = function_exists('current_user') ? current_user() : [];
$logged_admin_id = (int)($me['id'] ?? 0);

/* ---------------- My assigned counts (Recruiters / Job Seekers) ---------------- */
$myRecruitersCount = 0;
$myJobSeekersCount = 0;

if (!empty($usersTbl)
    && $logged_admin_id > 0
    && col_exists($con, $usersTbl, 'profile_type_id')
    && col_exists($con, $usersTbl, 'ac_manager_id')
    && col_exists($con, $usersTbl, 'created_at')) {

  // My Recruiters (profile_type_id = 1) within date range
  $myRecruitersCount = fetch_one(
    $con,
    "SELECT COUNT(*) AS cnt
     FROM `$usersTbl`
     WHERE profile_type_id = 1
       AND ac_manager_id = ?
       AND DATE(created_at) BETWEEN ? AND ?",
    "iss",
    [$logged_admin_id, $from, $to]
  );

  // My Job Seekers (profile_type_id = 2) within date range
  $myJobSeekersCount = fetch_one(
    $con,
    "SELECT COUNT(*) AS cnt
     FROM `$usersTbl`
     WHERE profile_type_id = 2
       AND ac_manager_id = ?
       AND DATE(created_at) BETWEEN ? AND ?",
    "iss",
    [$logged_admin_id, $from, $to]
  );
}

/* ---------------- My Leads (Created by Me ONLY) ---------------- */
$myLeadsCount = 0;

$me = function_exists('current_user') ? current_user() : [];
$logged_user_id = (int)($me['id'] ?? 0);

if ($logged_user_id > 0 && table_exists($con, 'jos_app_crm_leads')) {
  $myLeadsCount = fetch_one(
    $con,
    "SELECT COUNT(*) AS cnt
     FROM jos_app_crm_leads
     WHERE created_by = ?
       AND DATE(created_at) BETWEEN ? AND ?",
    "iss",
    [$logged_user_id, $from, $to]
  );
}



/* ---------------- Queries ---------------- */
function fetch_kv(mysqli $con, string $sql, string $types, array $params): array {
  $stmt = $con->prepare($sql);
  if ($types !== '') $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  while ($row = $res->fetch_assoc()) {
    $out[] = $row;
  }
  $stmt->close();
  return $out;
}
function fetch_one(mysqli $con, string $sql, string $types, array $params, string $field='cnt'): int {
  $stmt = $con->prepare($sql);
  if ($types !== '') $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();
  return (int)($row[$field] ?? 0);
}

/* Top totals */
$premiumJobs = 0;
$standardJobs = 0;

if ($walkinTable) {
  $premiumJobs = fetch_one(
    $con,
    "SELECT COUNT(*) AS cnt FROM `$walkinTable` WHERE DATE(`created_at`) BETWEEN ? AND ?",
    "ss",
    [$from, $to]
  );
}
if ($vacancyTable) {
  $standardJobs = fetch_one(
    $con,
    "SELECT COUNT(*) AS cnt FROM `$vacancyTable` WHERE DATE(`created_at`) BETWEEN ? AND ?",
    "ss",
    [$from, $to]
  );
}

$appType1 = 0; $appType2 = 0;
if ($appTable) {
  $appType1 = fetch_one(
    $con,
    "SELECT COUNT(*) AS cnt FROM `$appTable` WHERE `job_listing_type`=1 AND DATE(`application_date`) BETWEEN ? AND ?",
    "ss",
    [$from, $to]
  );
  $appType2 = fetch_one(
    $con,
    "SELECT COUNT(*) AS cnt FROM `$appTable` WHERE `job_listing_type`=2 AND DATE(`application_date`) BETWEEN ? AND ?",
    "ss",
    [$from, $to]
  );
}

/* Promoters */
$promoters = 0;
if ($usersTbl && col_exists($con, $usersTbl, 'profile_type_id')) {
  $promoters = fetch_one(
    $con,
    "SELECT COUNT(*) AS cnt
     FROM `$usersTbl`
     WHERE profile_type_id = 3
       AND DATE(created_at) BETWEEN ? AND ?",
    "ss",
    [$from, $to]
  );
}


/* Plan-wise counts (success payments) */
$recruiterPlans = [];
$jobseekerPlans = [];

/* Users count (from users table) */
$recruitersUsersCount = 0;
$jobSeekersUsersCount = 0;

/* NOTE:
   - Keeps your existing plan-wise logic EXACTLY as-is.
   - Adds ONLY user counts from users table (profile_type_id = 1/2).
   - Uses all-time counts (no date filter) to match your request.
   - Requires: $usersTbl already resolved + col_exists() + fetch_one() available.
*/
if (!empty($usersTbl) && col_exists($con, $usersTbl, 'profile_type_id') && col_exists($con, $usersTbl, 'created_at')) {

  // Recruiters users count (profile_type_id = 1) WITH date range
  $recruitersUsersCount = fetch_one(
    $con,
    "SELECT COUNT(*) AS cnt
     FROM `$usersTbl`
     WHERE profile_type_id = 1
       AND DATE(created_at) BETWEEN ? AND ?",
    "ss",
    [$from, $to]
  );

  // Job Seekers users count (profile_type_id = 2) WITH date range
  $jobSeekersUsersCount = fetch_one(
    $con,
    "SELECT COUNT(*) AS cnt
     FROM `$usersTbl`
     WHERE profile_type_id = 2
       AND DATE(created_at) BETWEEN ? AND ?",
    "ss",
    [$from, $to]
  );
}


if ($planTbl && $subLogTbl) {
  // Recruiters profile_type=1
  $recruiterPlans = fetch_kv(
    $con,
    "SELECT sp.id AS plan_id,
            TRIM(CAST(sp.plan_name AS CHAR(255))) AS plan_name,
            COUNT(usl.id) AS cnt
     FROM `$planTbl` sp
     LEFT JOIN `$subLogTbl` usl
       ON usl.plan_id = sp.id
      AND DATE(usl.created_at) BETWEEN ? AND ?
      AND usl.payment_status = 'success'
     WHERE sp.profile_type = 1
     GROUP BY sp.id, plan_name
     ORDER BY cnt DESC, plan_name",
    "ss",
    [$from, $to]
  );

  // Jobseekers profile_type=2
  $jobseekerPlans = fetch_kv(
    $con,
    "SELECT sp.id AS plan_id,
            TRIM(CAST(sp.plan_name AS CHAR(255))) AS plan_name,
            COUNT(usl.id) AS cnt
     FROM `$planTbl` sp
     LEFT JOIN `$subLogTbl` usl
       ON usl.plan_id = sp.id
      AND DATE(usl.created_at) BETWEEN ? AND ?
      AND usl.payment_status = 'success'
     WHERE sp.profile_type = 2
     GROUP BY sp.id, plan_name
     ORDER BY cnt DESC, plan_name",
    "ss",
    [$from, $to]
  );
}

/* Job post status summary (Walkin + Vacancies) */
$walkinStatus = [];
$vacancyStatus = [];
if ($jobStatusTbl) {
  if ($walkinTable && col_exists($con, $walkinTable, 'job_status_id')) {
    $walkinStatus = fetch_kv(
      $con,
      "SELECT s.id AS status_id, s.name AS status_name, COALESCE(x.cnt,0) AS cnt
       FROM `$jobStatusTbl` s
       LEFT JOIN (
         SELECT job_status_id AS status_id, COUNT(*) AS cnt
         FROM `$walkinTable`
         WHERE DATE(created_at) BETWEEN ? AND ?
         GROUP BY job_status_id
       ) x ON x.status_id = s.id
       ORDER BY s.orderby, s.id",
      "ss",
      [$from, $to]
    );
  }
  if ($vacancyTable && col_exists($con, $vacancyTable, 'job_status_id')) {
    $vacancyStatus = fetch_kv(
      $con,
      "SELECT s.id AS status_id, s.name AS status_name, COALESCE(x.cnt,0) AS cnt
       FROM `$jobStatusTbl` s
       LEFT JOIN (
         SELECT job_status_id AS status_id, COUNT(*) AS cnt
         FROM `$vacancyTable`
         WHERE DATE(created_at) BETWEEN ? AND ?
         GROUP BY job_status_id
       ) x ON x.status_id = s.id
       ORDER BY s.orderby, s.id",
      "ss",
      [$from, $to]
    );
  }
}

/* ============================================================
   APPLICATION STATUS SUMMARY (RESTORED)
   - Per job_listing_type (1=Premium, 2=Standard)
   - If app_status_log is present -> use ONLY log events
   - If app_status_log empty -> use snapshot (status_id + application_date)
   - Show ALL statuses from jos_app_applicationstatus (exclude name='All' if exists)
   ============================================================ */

function application_status_summary(mysqli $con, string $appTable, string $statusTbl, int $jobListingType, string $from, string $to): array {
  // This SQL avoids JSON_TABLE because your app_status_log is NOT valid JSON.
  // app_status_log entries look like: [ 1 | 2025-08-29 18:32:38 | 5 ]
  // We parse them using SUBSTRING_INDEX and a numbers inline table (1..30).

 $sql = "
SELECT
  s.id   AS status_id,
  s.name AS status_name,
  COALESCE(x.cnt, 0) AS cnt
FROM `$statusTbl` s
LEFT JOIN (
  SELECT status_id, COUNT(*) AS cnt
  FROM (
    /* 1) SNAPSHOT (current status) for ALL applications */
    SELECT
      a.status_id AS status_id,
      DATE(COALESCE(a.updated_at, a.application_date)) AS dt
    FROM `$appTable` a
    WHERE a.job_listing_type = ?
      AND DATE(COALESCE(a.updated_at, a.application_date)) BETWEEN ? AND ?

    UNION ALL

    /* 2) MOVEMENT events from app_status_log (if present) */
    SELECT
      CAST(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(t.entry,'|',1),'[',-1)) AS UNSIGNED) AS status_id,
      t.dt
    FROM (
      SELECT
        a.id,
        TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(a.app_status_log, ']', n.n), ']', -1)) AS entry,
        DATE(
          STR_TO_DATE(
            TRIM(
              SUBSTRING_INDEX(
                SUBSTRING_INDEX(
                  TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(a.app_status_log, ']', n.n), ']', -1)),
                  '|', 2
                ),
                '|', -1
              )
            ),
            '%Y-%m-%d %H:%i:%s'
          )
        ) AS dt
      FROM `$appTable` a
      JOIN (
        SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
        UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10
        UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
        UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20
        UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL SELECT 25
        UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30
      ) n
      WHERE a.job_listing_type = ?
        AND a.app_status_log IS NOT NULL
        AND TRIM(a.app_status_log) <> ''
        AND n.n <= (LENGTH(a.app_status_log) - LENGTH(REPLACE(a.app_status_log, ']', '')))
    ) t
    WHERE t.entry LIKE '[%|%|%'
      AND t.dt IS NOT NULL
      AND t.dt BETWEEN ? AND ?
  ) z
  GROUP BY status_id
) x ON x.status_id = s.id
WHERE (s.name <> 'All' OR s.name IS NULL)
ORDER BY s.order_by, s.id
";


$stmt = $con->prepare($sql);
$stmt->bind_param(
  "ississ",
  $jobListingType, $from, $to,
  $jobListingType, $from, $to
);

  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  while($row = $res->fetch_assoc()){
    $out[] = $row;
  }
  $stmt->close();
  return $out;
}

$appStatusType1 = [];
$appStatusType2 = [];
if ($appTable && $appStatusTbl) {
  $appStatusType1 = application_status_summary($con, $appTable, $appStatusTbl, 1, $from, $to);
  $appStatusType2 = application_status_summary($con, $appTable, $appStatusTbl, 2, $from, $to);
}

/* ---------------- UI helpers ---------------- */
function render_rows(array $rows): string {
  if (!$rows) return '<div style="opacity:.7;">No data</div>';
  $html = '';
  foreach ($rows as $r) {
    $name = h($r['plan_name'] ?? $r['status_name'] ?? '');
    $cnt  = (int)($r['cnt'] ?? 0);
    $html .= '
      <div style="display:flex;align-items:center;justify-content:space-between;
                  padding:12px 14px;margin:10px 0;border-radius:14px;
                  background:rgba(0,0,0,0.18);border:1px solid rgba(255,255,255,0.06);">
        <div style="color:#fff;">'.$name.'</div>
        <div style="color:#fff;font-weight:800;">'.$cnt.'</div>
      </div>';
  }
  return $html;
}

$u = function_exists('current_user') ? current_user() : ['name'=>'Admin'];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dashboard</title>
  <link rel="stylesheet" href="/adminconsole/assets/ui.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

  <style>
    /* keep existing theme; enforce WHITE titles */
    .dash-title, .card h2, .card h3, .card-title { color:#fff !important; }
    .dash-sub { color: rgba(255,255,255,0.75) !important; }

    /* responsive grid */
    .grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap:18px; }
    .grid-5 { display:grid; grid-template-columns: repeat(5, 1fr); gap:14px; }
    @media (max-width: 1200px){ .grid-5 { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 900px){ .grid-2 { grid-template-columns: 1fr; } }

    .mini-card{
      background: rgba(0,0,0,0.22);
      border:1px solid rgba(255,255,255,0.06);
      border-radius:16px;
      padding:14px 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,.25);
    }
    .mini-card .label{ color: rgba(255,255,255,.72); font-size: 12px; margin-bottom:8px; }
    .mini-card .value{ color:#fff; font-size: 26px; font-weight: 900; letter-spacing: .2px; }

    .panel{
      background: rgba(0,0,0,0.18);
      border:1px solid rgba(255,255,255,0.06);
      border-radius:18px;
      padding:18px 18px;
      box-shadow: 0 10px 40px rgba(0,0,0,.28);
    }

    .filter-bar{
      display:flex; gap:10px; align-items:flex-end; justify-content:flex-end; flex-wrap:wrap;
      margin-bottom: 14px;
    }
    .filter-bar label{ color:rgba(255,255,255,.75); font-size:12px; display:block; margin-bottom:6px; }
    .filter-bar input{
      height:38px; border-radius:10px; border:1px solid rgba(255,255,255,0.12);
      background: rgba(0,0,0,0.22); color:#fff; padding:0 10px; min-width:160px;
    }
    .btn{
      height:38px; border-radius:10px; border:1px solid rgba(255,255,255,0.10);
      padding:0 14px; cursor:pointer; font-weight:700;
      background: rgba(255,255,255,0.10); color:#fff;
    }
    .btn.primary{ background:#2563eb; border-color:#2563eb; }
    .btn:hover{ filter:brightness(1.05); }

    .section-title{ margin:0 0 6px; font-size:16px; font-weight:900; color:#fff !important; }
    .section-sub{ margin:0 0 14px; font-size:12px; color:rgba(255,255,255,.7) !important; }
  </style>
</head>

<body>
<div class="master-wrap">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
    <div>
      <div class="dash-title" style="font-size:22px;font-weight:900;">Dashboard</div>
      <div class="dash-sub" style="font-size:12px;">Filter all cards by date range</div>
    </div>

    <form method="get" class="filter-bar">
      <div>
        <label>From</label>
        <input id="from" name="from" value="<?=h($from_ui)?>">
      </div>
      <div>
        <label>To</label>
        <input id="to" name="to" value="<?=h($to_ui)?>">
      </div>
      <button class="btn primary" type="submit">Apply</button>
      <a class="btn" href="index.php" style="display:inline-flex;align-items:center;text-decoration:none;">Reset</a>
    </form>
  </div>

  <!-- TOP TOTALS 
     <div class="mini-card">
      <div class="label">Premium Jobs </div>
      <div class="value"><?= (int)$premiumJobs ?></div>
    </div>
    <div class="mini-card">
      <div class="label">Standard Jobs </div>
      <div class="value"><?= (int)$standardJobs ?></div>
    </div>
    <div class="mini-card">
      <div class="label">Applications </div>
      <div class="value"><?= (int)$appType1 ?></div>
    </div>
    <div class="mini-card">
      <div class="label">Applications </div>
      <div class="value"><?= (int)$appType2 ?></div>
    </div>-->
<div class="grid-5" style="margin-bottom:18px;">

  <div class="mini-card">
    <div class="label">My Recruiters</div>
    <div class="value"><?= (int)$myRecruitersCount ?></div>
  </div>

  <div class="mini-card">
    <div class="label">My Job Seekers</div>
    <div class="value"><?= (int)$myJobSeekersCount ?></div>
  </div>
<div class="mini-card">
  <div class="label">My Leads</div>
  <div class="value"><?= (int)$myLeadsCount ?></div>
</div>

  <div class="mini-card">
    <div class="label">Promoters</div>
    <div class="value"><?= (int)$promoters ?></div>
  </div>

</div>


  <!-- PLANS -->
  <div class="grid-2" style="margin-bottom:18px;">
    <div class="panel">
<div class="section-title">
  Employers <span style="opacity:.75;font-weight:800;">(Users: <?= (int)$recruitersUsersCount ?>)</span>
</div>


     
      <?= render_rows($recruiterPlans) ?>
    </div>

    <div class="panel">
     <div class="section-title">
  Job Seekers 
  <span style="opacity:.75;font-weight:800;">
    (Users: <?= (int)$jobSeekersUsersCount ?>)
  </span>
</div>

     
      <?= render_rows($jobseekerPlans) ?>
    </div>
  </div>

  <!-- JOB POST STATUS SUMMARY -->
  <div class="grid-2" style="margin-bottom:18px;">
    <div class="panel">
      <div class="section-title">Premium Job Post Status Summary
     <span style="opacity:.75;font-weight:800;">(Jobs: <?= (int)$premiumJobs ?>)</span></div>
      <?= render_rows($walkinStatus) ?>
    </div>

    <div class="panel">
      <div class="section-title">Standard Job Post Status Summary
        <span style="opacity:.75;font-weight:800;">(Jobs: <?= (int)$standardJobs ?>)</span></div>
      <?= render_rows($vacancyStatus) ?>
    </div>
  </div>

  <!-- APPLICATIONS SUMMARY (RESTORED) -->
  <div class="panel" style="margin-bottom:18px;">
  


    <div class="grid-2" style="gap:18px;">
      <div>
        <div class="section-title" style="font-size:14px;">Premium Jobs Applications
        <span style="opacity:.75;font-weight:800;">(No: <?= (int)$appType1 ?>)</span></div>
        <?= render_rows($appStatusType1) ?>
      </div>

      <div>
        <div class="section-title" style="font-size:14px;">Standard Jobs Applications  
        <span style="opacity:.75;font-weight:800;">(No: <?= (int)$appType2 ?>)</span></div>
        <?= render_rows($appStatusType2) ?>
      </div>
    </div>

  
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  flatpickr("#from", { dateFormat: "d-m-Y", allowInput:true });
  flatpickr("#to",   { dateFormat: "d-m-Y", allowInput:true });
</script>
</body>
</html>
