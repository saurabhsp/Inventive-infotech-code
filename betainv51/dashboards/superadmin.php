<?php
/* ============================================================
   SUPERADMIN DASHBOARD – ROLE BASED (MULTI ADMIN SAFE)
   Shows data based on logged-in admin
   ============================================================ */

if (!isset($con)) die("Database not initialized.");

date_default_timezone_set('Asia/Kolkata');

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

<div class="grid-5" style="margin-bottom:18px;">
  <div class="mini-card"><div class="label">My Recruiters</div><div class="value"><?=$myRecruitersCount?></div></div>
  <div class="mini-card"><div class="label">My Job Seekers</div><div class="value"><?=$myJobSeekersCount?></div></div>
  <div class="mini-card"><div class="label">My Leads</div><div class="value"><?=$myLeadsCount?></div></div>
  <div class="mini-card"><div class="label">Promoters</div><div class="value"><?=$promoters?></div></div>
</div>

<div class="grid-2" style="margin-bottom:18px;">
  <div class="panel">
    <div class="section-title">Employers Plans</div>
    <?=render_rows($recruiterPlans)?>
  </div>
  <div class="panel">
    <div class="section-title">Job Seekers Plans</div>
    <?=render_rows($jobseekerPlans)?>
  </div>
</div>

<div class="grid-2" style="margin-bottom:18px;">
  <div class="panel">
    <div class="section-title">Premium Job Status</div>
    <?=render_rows($walkinStatus)?>
  </div>
  <div class="panel">
    <div class="section-title">Standard Job Status</div>
    <?=render_rows($vacancyStatus)?>
  </div>
</div>

<div class="panel">
  <div class="grid-2">
    <div>
      <div class="section-title">Premium Applications</div>
      <?=render_rows($appStatusType1)?>
    </div>
    <div>
      <div class="section-title">Standard Applications</div>
      <?=render_rows($appStatusType2)?>
    </div>
  </div>
</div>
