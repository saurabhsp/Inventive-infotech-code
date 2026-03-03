<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

/* ------------------ View-only ACL guard (inserted) ------------------ */
global $con;
if (!isset($con) || !$con) {
    http_response_code(500);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Server error</title>';
    echo '<link rel="stylesheet" href="/adminconsole/assets/ui.css">';
    echo '</head><body><div class="master-wrap"><div class="card">';
    echo '<h2>Server error</h2><div class="alert danger">DB connection not initialized.</div>';
    echo '</div></div></body></html>';
    exit;
}

/* Normalize current script path for menu matching */
$script_path = $_SERVER['PHP_SELF'];
$script_basename = basename($script_path);

$menu_id_override = isset($_GET['menu_id']) ? (int)$_GET['menu_id'] : 0;
$can_view = 0;

if ($menu_id_override > 0) {
    $stmt = $con->prepare("SELECT can_view FROM jos_admin_menus WHERE id=? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $menu_id_override);
        $stmt->execute();
        $stmt->bind_result($can_view);
        $stmt->fetch();
        $stmt->close();
    }
} else {
    $a = $script_path;
    $b = $script_basename;
    $stmt = $con->prepare("SELECT can_view FROM jos_admin_menus WHERE menu_link IN (?,?) LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ss', $a, $b);
        $stmt->execute();
        $stmt->bind_result($can_view);
        $stmt->fetch();
        $stmt->close();
    }
}

/* loose LIKE match */
if ((int)$can_view !== 1) {
    $like_pattern = '%' . $script_basename;
    $stmt = $con->prepare("SELECT can_view FROM jos_admin_menus WHERE menu_link LIKE ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $like_pattern);
        $stmt->execute();
        $stmt->bind_result($can_view);
        $stmt->fetch();
        $stmt->close();
    }
}

if ((int)$can_view !== 1) {
    http_response_code(403);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>403 Access denied</title>';
    echo '<link rel="stylesheet" href="/adminconsole/assets/ui.css">';
    echo '</head><body>';
    echo '<div class="master-wrap" style="padding:40px 0">';
    echo '  <div class="card" style="max-width:820px;margin:0 auto;text-align:left">';
    echo '    <h2 style="margin-top:0">403 — Access denied</h2>';
    echo '    <div class="alert danger">You do not have permission to view this page.</div>';
    echo '    <p style="color:#6b7280">If you believe this is an error, contact an administrator or use a menu testing override by adding <code>?menu_id=</code> to the URL (for admins only).</p>';
    echo '    <div style="margin-top:12px"><a class="btn secondary" href="/adminconsole/">Return to dashboard</a></div>';
    echo '  </div>';
    echo '</div>';
    echo '</body></html>';
    exit;
}

/* -------- page config -------- */
$page_title = 'Users (Employer-wise List)';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

if (!defined('DOMAIN_URL')) { define('DOMAIN_URL', '/'); }

ob_start();
?>
<link rel="stylesheet" href="/adminconsole/assets/ui.css">
<!-- Flatpickr for dd-mm-yyyy datepicker -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
.table a.ref-link { text-decoration:none; color:#3b82f6; }
.table a.ref-link:hover { text-decoration:underline; }

body { overflow-x: hidden; }

.master-wrap .card .table-wrap {
  width: 100%;
  overflow-x: hidden;
}

.master-wrap .card .table-wrap .table {
  width: 100%;
  max-width: 100%;
  min-width: 0 !important;
  table-layout: fixed;
}

.master-wrap .card .table-wrap .table th,
.master-wrap .card .table-wrap .table td {
  word-wrap: break-word;
  word-break: break-word;
}
</style>

<script>
function copyToClipboard(text){
  try {
    navigator.clipboard.writeText(text).then(
      ()=>alert("Copied: " + text),
      ()=>window.prompt("Press Ctrl/Cmd+C then Enter", text)
    );
  } catch(e){ window.prompt("Press Ctrl/Cmd+C then Enter", text); }
}

document.addEventListener('DOMContentLoaded', function () {
  if (window.flatpickr) {
    flatpickr(".js-date-ddmmyyyy", {
      dateFormat: "d-m-Y",
      allowInput: true
    });
  }
});
</script>

<div class="master-wrap">
  <div class="headbar">
    <h2 style="margin:0"><?=htmlspecialchars($page_title)?></h2>
  </div>
     <!-- Show / Hide Filter Button -->
<div style="margin-left:auto; align-items:center;">
  <button type="button"
    onclick="toggleFilterBox()"
    id="toggleFilterBtn"
    class="btn secondary"
    style="white-space:nowrap;">
    Show Filters
  </button>
</div>
  <div class="card">
<?php
/* --------- PHP helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function keep_params(array $changes=[]){
  $qs = $_GET;
  foreach($changes as $k=>$v){ if($v===null){unset($qs[$k]);} else {$qs[$k]=$v;} }
  $q = http_build_query($qs);
  return $q?('?'.$q):'';
}
function get_int($key,$default=0){ return isset($_GET[$key]) ? (int)$_GET[$key] : $default; }
function get_str($key,$default=''){ return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default; }
function parse_ddmmyyyy_to_ymd($s){
  $s = trim((string)$s);
  if ($s === '') return null;
  if (!preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $s, $m)) return null;
  return $m[3] . '-' . $m[2] . '-' . $m[1];
}

/* ----------------- MODE SWITCH: LIST vs PROFILE ----------------- */
$mode = get_str('mode','list');

if ($mode === 'profile') {
    $rid = get_int('rid', 0);
    $back_url = $_SERVER['PHP_SELF'];   // always go back to plain list URL

    if ($rid <= 0) {
        echo '<div class="alert danger">Invalid Employer ID.</div>';
        echo '<div style="margin-top:10px"><a class="btn secondary" href="'.h($back_url).'">Back to list</a></div>';
        echo '</div></div>';
        echo ob_get_clean();
        exit;
    }

    /* ====== Employer profile logic (from your API) ====== */
    $sql = "
        SELECT 
            r.*, 
            u.id AS userid,
            u.active_plan_id
        FROM jos_app_recruiter_profile r
        LEFT JOIN jos_app_users u ON u.profile_id = r.id AND u.profile_type_id = 1
        WHERE r.id = ?
    ";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $stmt->close();

        $data['city_name'] = $data['city_id'];
        $data['locality_name'] = $data['locality_id'];

        if (!empty($data['company_logo'])) {
            $data['company_logo'] = rtrim(DOMAIN_URL, "/") . "/webservices/" . ltrim($data['company_logo'], "/");
        }

        $userid = intval($data['userid'] ?? 0);
        $active_plan_id = intval($data['active_plan_id'] ?? 0);

        $subscription = [
            "status" => "no_subscription",
            "valid_from" => null,
            "valid_to" => null,
            "plan_name" => null,
            "validity_months" => null,
            "is_expired" => null
        ];

        if ($userid > 0 && $active_plan_id > 0) {
            $sub_stmt = $con->prepare("
                SELECT log.start_date, log.end_date, plan.plan_name, plan.validity_months 
                FROM jos_app_usersubscriptionlog log
                LEFT JOIN jos_app_subscription_plans plan ON plan.id = log.plan_id
                WHERE log.userid = ? AND log.plan_id = ? AND log.payment_status = 'success'
                ORDER BY log.start_date DESC
                LIMIT 1
            ");
            $sub_stmt->bind_param("ii", $userid, $active_plan_id);
            $sub_stmt->execute();
            $sub_result = $sub_stmt->get_result();

            if ($sub_result && $sub_result->num_rows > 0) {
                $sub = $sub_result->fetch_assoc();
                $valid_from = !empty($sub['start_date']) ? date("d-m-Y", strtotime($sub['start_date'])) : null;
                $valid_to   = !empty($sub['end_date'])   ? date("d-m-Y", strtotime($sub['end_date']))   : null;
                $is_expired = (!empty($sub['end_date']) && $sub['end_date'] < date("Y-m-d"));

                $subscription = [
                    "status" => "active",
                    "valid_from" => $valid_from,
                    "valid_to" => $valid_to,
                    "plan_name" => $sub['plan_name'],
                    "validity_months" => $sub['validity_months'],
                    "is_expired" => $is_expired
                ];
            }
            $sub_stmt->close();
        }

        /* --------- Render PROFILE VIEW HTML ---------- */
        echo '<div style="margin-bottom:12px;"><a class="btn secondary" href="'.h($back_url).'">← Back to Employer List</a></div>';

        echo '<div style="display:flex; gap:18px; align-items:flex-start;">';

        echo '<div style="width:140px; height:140px; background:#f3f4f6; border-radius:12px; display:flex;align-items:center;justify-content:center;overflow:hidden;">';
        if (!empty($data['company_logo'])) {
            echo '<img src="'.h($data['company_logo']).'" style="width:100%;height:100%;object-fit:contain;" alt="Logo">';
        } else {
            echo '<span style="color:#9ca3af;font-size:12px;">No Logo</span>';
        }
        echo '</div>';

        echo '<div style="flex:1;">';
        echo '<h3 style="margin:0 0 6px;">'.h($data['organization_name'] ?: 'N/A').'</h3>';
        echo '<div style="margin-bottom:4px;"><strong>Contact Person:</strong> '.h($data['contact_person_name'] ?? '').'</div>';
        echo '<div style="margin-bottom:4px;"><strong>Designation:</strong> '.h($data['designation'] ?? '').'</div>';
        echo '<div style="margin-bottom:4px;"><strong>Mobile:</strong> '.h($data['contact_no'] ?? $data['mobile_no'] ?? '').'</div>';
        echo '<div style="margin-bottom:4px;"><strong>Email:</strong> '.h($data['email'] ?? '').'</div>';
        echo '<div style="margin-bottom:4px;"><strong>Alt. Email:</strong> '.h($data['alternate_email'] ?? '').'</div>';
        echo '</div>';

        echo '</div>'; // top flex

        echo '<hr style="margin:16px 0;">';

        echo '<div style="display:flex;flex-wrap:wrap;gap:16px;">';
        echo '<div><strong>City:</strong> '.h($data['city_name']).'</div>';
        echo '<div><strong>Locality:</strong> '.h($data['locality_name']).'</div>';
        echo '<div><strong>Pincode:</strong> '.h($data['pincode'] ?? '').'</div>';
        echo '<div style="flex-basis:100%;"><strong>Address:</strong> '.h($data['address'] ?? '').'</div>';
        echo '</div>';

        echo '<hr style="margin:16px 0;">';

        echo '<h3 style="margin-top:0;">Subscription</h3>';
        $badge = '<span class="badge">No subscription</span>';
        if ($subscription['status'] === 'active') {
            if ($subscription['is_expired']) {
                $badge = '<span class="badge danger">Expired</span>';
            } else {
                $badge = '<span class="badge success">Active</span>';
            }
        }
        echo '<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">';
        echo '<div><strong>Plan:</strong> '.h($subscription['plan_name'] ?? 'No Plan').'</div>';
        echo '<div><strong>Valid From:</strong> '.h($subscription['valid_from'] ?? '-').'</div>';
        echo '<div><strong>Valid To:</strong> '.h($subscription['valid_to'] ?? '-').'</div>';
        echo '<div>'.$badge.'</div>';
        echo '</div>';

    } else {
        if ($stmt) { $stmt->close(); }
        echo '<div class="alert danger">Employer not found.</div>';
        echo '<div style="margin-top:10px"><a class="btn secondary" href="'.h($back_url).'">Back to list</a></div>';
    }

    echo '</div></div>';
    echo ob_get_clean();
    exit;
}

/* ====================== LIST MODE BELOW ======================= */

global $con;
if (!$con) {
  echo '<div class="alert danger">DB connection not initialized.</div>';
  echo ob_get_clean(); exit;
}

/* ---- filters ---- */
$q                   = get_str('q','');
$city_id             = get_str('city_id','');
$status_id           = isset($_GET['status_id']) ? (int)$_GET['status_id'] : 1;
$referral_code_in    = get_str('referral_code','');
$plan_access_in      = get_int('plan_access',0);
$subscription_status = strtolower(get_str('subscription_status',''));

$created_from_raw    = get_str('created_from',''); // dd-mm-yyyy
$created_to_raw      = get_str('created_to','');   // dd-mm-yyyy

$created_from = parse_ddmmyyyy_to_ymd($created_from_raw);
$created_to   = parse_ddmmyyyy_to_ymd($created_to_raw);

$sort = get_str('sort','newest');
$view = get_str('view','last50');
$page = max(1, get_int('page',1));
$per_page = ($view==='all') ? 1000 : 50;
$offset = ($page-1)*$per_page;

/* ---- build SQL ---- */
$sql_base = "
  FROM jos_app_users u
  LEFT JOIN jos_app_recruiter_profile rp ON (u.profile_type_id=1 AND rp.id=u.profile_id)
  LEFT JOIN jos_app_candidate_profile  cp ON (u.profile_type_id=2 AND cp.id=u.profile_id)
  LEFT JOIN jos_crm_gender g ON g.id = cp.gender_id
  LEFT JOIN jos_app_promoter_profile   pp ON (u.profile_type_id=3 AND pp.id=u.profile_id)

  LEFT JOIN jos_app_users ur ON ur.id = u.referred_by
  LEFT JOIN jos_app_recruiter_profile rrp ON (ur.profile_type_id=1 AND rrp.id=ur.profile_id)
  LEFT JOIN jos_app_candidate_profile  rcp ON (ur.profile_type_id=2 AND rcp.id=ur.profile_id)
  LEFT JOIN jos_app_promoter_profile   rpp ON (ur.profile_type_id=3 AND rpp.id=ur.profile_id)

  LEFT JOIN (
    SELECT x.userid, x.plan_id, x.start_date, x.end_date
    FROM jos_app_usersubscriptionlog x
    INNER JOIN (
      SELECT userid, MAX(CONCAT(IFNULL(DATE_FORMAT(end_date,'%Y%m%d%H%i%s'),'00000000000000'), LPAD(id,10,'0'))) AS maxk
      FROM jos_app_usersubscriptionlog
      GROUP BY userid
    ) m ON m.userid=x.userid
       AND CONCAT(IFNULL(DATE_FORMAT(x.end_date,'%Y%m%d%H%i%s'),'00000000000000'), LPAD(x.id,10,'0'))=m.maxk
  ) ls ON ls.userid = u.id
  LEFT JOIN jos_app_subscription_plans sp ON sp.id = COALESCE(ls.plan_id, u.active_plan_id)
  LEFT JOIN (
    SELECT referred_by AS uid, COUNT(*) AS total_referrals
    FROM jos_app_users
    WHERE referred_by IS NOT NULL AND referred_by<>0
    GROUP BY referred_by
  ) rc ON rc.uid = u.id
";

$where = [];
$types = '';
$params = [];

$where[] = "u.profile_type_id = 1";

if ($q!==''){
  $where[]="(u.mobile_no LIKE CONCAT('%',?,'%')
          OR u.referral_code LIKE CONCAT('%',?,'%')
          OR u.myreferral_code LIKE CONCAT('%',?,'%')
          OR rp.organization_name LIKE CONCAT('%',?,'%')
          OR rp.contact_person_name LIKE CONCAT('%',?,'%')
          OR cp.candidate_name LIKE CONCAT('%',?,'%')
          OR pp.name LIKE CONCAT('%',?,'%'))";
  $types .= 'sssssss';
  $params = array_merge($params, array_fill(0,7,$q));
}

if ($city_id!==''){ $where[]="u.city_id LIKE CONCAT('%',?,'%')"; $types.='s'; $params[]=$city_id; }
if ($status_id>=0){ $where[]="u.status_id=?"; $types.='i'; $params[]=$status_id; }
if ($referral_code_in!==''){ $where[]="u.referral_code=?"; $types.='s'; $params[]=$referral_code_in; }
if ($plan_access_in>0){ $where[]="CAST(sp.plan_access AS UNSIGNED)=?"; $types.='i'; $params[]=$plan_access_in; }

if ($subscription_status==='active'){ $where[]="(ls.end_date IS NOT NULL AND ls.end_date>=NOW())"; }
elseif ($subscription_status==='expired'){ $where[]="(ls.end_date IS NOT NULL AND ls.end_date<NOW())"; }

if ($created_from){ $where[]="DATE(u.created_at)>=?"; $types.='s'; $params[]=$created_from; }
if ($created_to){   $where[]="DATE(u.created_at)<=?"; $types.='s'; $params[]=$created_to; }

$sql_where = $where ? (' WHERE '.implode(' AND ',$where)) : '';

/* sort */
switch($sort){
  case 'oldest':    $order = ' ORDER BY u.id ASC'; break;
  case 'name_asc':  $order = " ORDER BY COALESCE(NULLIF(rp.organization_name,''), NULLIF(rp.contact_person_name,''), NULLIF(cp.candidate_name,''), NULLIF(pp.name,''), u.mobile_no) ASC"; break;
  case 'name_desc': $order = " ORDER BY COALESCE(NULLIF(rp.organization_name,''), NULLIF(rp.contact_person_name,''), NULLIF(cp.candidate_name,''), NULLIF(pp.name,''), u.mobile_no) DESC"; break;
  case 'city_asc':  $order = ' ORDER BY u.city_id ASC, u.id DESC'; break;
  case 'city_desc': $order = ' ORDER BY u.city_id DESC, u.id DESC'; break;
  default:          $order = ' ORDER BY u.id DESC';
}

/* total count */
$sql_count = "SELECT COUNT(*) AS c ".$sql_base.$sql_where;
$stmt = $con->prepare($sql_count);
if($types!==''){ $stmt->bind_param($types, ...$params); }
$stmt->execute();
$total = 0; $stmt->bind_result($total); $stmt->fetch(); $stmt->close();

/* page clamp */
if ($view!=='all'){
  $pages = max(1, (int)ceil($total / $per_page));
  if ($page>$pages){ $page=$pages; $offset=($page-1)*$per_page; }
} else { $pages = 1; $page = 1; $offset = 0; }

/* main query */
$sql = "
SELECT
  u.id, u.mobile_no, u.profile_type_id, u.profile_id, u.city_id, u.address,
  u.latitude, u.longitude, u.fcm_token, u.referral_code, u.myreferral_code,
  u.referred_by, u.active_plan_id, u.status_id, u.created_at,

  rp.organization_name, rp.contact_person_name, rp.designation,
  cp.candidate_name, cp.gender_id, g.name AS gender_name,
  pp.name AS promoter_name, pp.pan_no,

  ls.plan_id AS last_plan_id, ls.start_date AS last_start_date, ls.end_date AS last_end_date,
  sp.id AS plan_id, sp.plan_name AS plan_name, CAST(sp.plan_access AS UNSIGNED) AS plan_access_num,
  IFNULL(rc.total_referrals,0) AS total_referrals,
  (SELECT COUNT(*) FROM jos_app_walkininterviews w WHERE w.recruiter_id = rp.id) AS premium_jobs_count,
  (SELECT COUNT(*) FROM jos_app_jobvacancies jv WHERE jv.recruiter_id = rp.id)    AS standard_jobs_count,

  ur.mobile_no AS ref_mobile,
  COALESCE(
    NULLIF(rrp.organization_name,''),
    NULLIF(rrp.contact_person_name,''),
    NULLIF(rcp.candidate_name,''),
    NULLIF(rpp.name,''),
    ur.mobile_no
  ) AS ref_name
".$sql_base.$sql_where.$order." ".($view==='all' ? "" : " LIMIT $per_page OFFSET $offset");

$stmt = $con->prepare($sql);
if($types!==''){ $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();

/* ---- filters UI ---- */
?>
<form id="filterBox" method="get" class="toolbar" style="gap:10px;flex-wrap:wrap; display:none;">  <input class="inp" type="text" name="q" value="<?=h($q)?>" placeholder="Search name/mobile/referral/org..." style="min-width:240px">

  <input class="inp" type="text" name="city_id" value="<?=h($city_id)?>" placeholder="City Name">

  <select class="inp" name="status_id" title="Status">
    <option value="1"  <?= $status_id===1?'selected':''?>>Active</option>
    <option value="0"  <?= $status_id===0?'selected':''?>>Inactive</option>
    <option value="-1" <?= $status_id===-1?'selected':''?>>Any</option>
  </select>

  <!-- <input class="inp" type="text" name="referral_code" value="<?=h($referral_code_in)?>" placeholder="Referral Code (input)"> -->

  <select class="inp" name="plan_access" title="Plan Access">
    <option value="0" <?= $plan_access_in===0?'selected':''?>>Plan Access: Any</option>
    <option value="1" <?= $plan_access_in===1?'selected':''?>>Free</option>
    <option value="2" <?= $plan_access_in===2?'selected':''?>>Premium</option>
  </select>

  <select class="inp" name="subscription_status">
    <option value="" <?= $subscription_status===''?'selected':''?>>Subscription: Any</option>
    <option value="active" <?= $subscription_status==='active'?'selected':''?>>Active</option>
    <option value="expired" <?= $subscription_status==='expired'?'selected':''?>>Expired</option>
  </select>

  <input class="inp js-date-ddmmyyyy" type="text" name="created_from" value="<?=h($created_from_raw)?>" placeholder="Reg Date From (dd-mm-yyyy)" autocomplete="off">
  <input class="inp js-date-ddmmyyyy" type="text" name="created_to"   value="<?=h($created_to_raw)?>" placeholder="Reg Date To (dd-mm-yyyy)"   autocomplete="off">

  <select class="inp" name="sort">
    <option value="newest"   <?=$sort==='newest'?'selected':''?>>Newest first</option>
    <option value="oldest"   <?=$sort==='oldest'?'selected':''?>>Oldest first</option>
    <option value="name_asc" <?=$sort==='name_asc'?'selected':''?>>Name A–Z</option>
    <option value="name_desc"<?=$sort==='name_desc'?'selected':''?>>Name Z–A</option>
    <option value="city_asc" <?=$sort==='city_asc'?'selected':''?>>City ↑</option>
    <option value="city_desc"<?=$sort==='city_desc'?'selected':''?>>City ↓</option>
  </select>

  <button class="btn primary" type="submit">Apply</button>

  <div style="flex:1"></div>
  <a class="btn secondary" href="<?=h(keep_params(['view'=>'last50','page'=>1]))?>">Last 50</a>
  <a class="btn secondary" href="<?=h(keep_params(['view'=>'all','page'=>1]))?>">View All</a>
</form>

<div style="display:flex;align-items:center;gap:12px;margin:8px 0 12px">
  <span class="badge">Total: <?= (int)$total ?></span>
  <span class="badge">Showing: <?= ($view==='all') ? 'All' : ($res->num_rows) ?></span>
  <?php if($view!=='all'){ ?>
    <div style="margin-left:auto;display:flex;gap:6px;align-items:center">
      <?php if($page>1){ ?>
        <a class="btn secondary" href="<?=h(keep_params(['page'=>$page-1]))?>">‹ Prev</a>
      <?php } ?>
      <span>Page <?= (int)$page ?> / <?= (int)$pages ?></span>
      <?php if($page<$pages){ ?>
        <a class="btn secondary" href="<?=h(keep_params(['page'=>$page+1]))?>">Next ›</a>
      <?php } ?>
    </div>
  <?php } ?>
</div>

<div class="table-wrap">
<table class="table">
  <thead>
    <tr>
      <th style="width:60px">SR No</th>
      <th>Reg Date</th>
      <th>Name / Profile</th>
      <th>Contact Info</th>
      <th>Mobile</th>
      <th>Referred By</th>
      <th>Plan / Subscr.</th>
      <th>Referral Count</th>
      <th>Premium Jobs</th>
      <th>Standard Jobs</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
<?php
$sr = ($view==='all') ? 1 : ($offset+1);
while($row = $res->fetch_assoc()):
  // main display: org or contact person or mobile
  $display = $row['organization_name'] ?: $row['contact_person_name'] ?: $row['mobile_no'];

  // summary under name: ONLY city, no Org duplicate
  $summary = [];
  if (!empty($row['city_id'])) {
      $summary[] = 'City: '.$row['city_id'];
  }
  $profile_summary = implode(' | ', $summary);

  // Contact info column: "Roshan Chavhan | HR Lead" (no "Desig:" label)
  $contact_parts = [];
  if (!empty($row['contact_person_name'])) {
      $contact_parts[] = $row['contact_person_name'];
  }
  if (!empty($row['designation'])) {
      $contact_parts[] = $row['designation'];
  }
  $contact_info = implode(' | ', $contact_parts);
  if ($contact_info === '') {
      $contact_info = '—';
  }

  if (!empty($row['plan_name'])) {
    $plan_label = $row['plan_name'].' '.(($row['plan_access_num']==2)?'(Premium)':'(Free)');
  } elseif (!empty($row['plan_id'])) {
    $plan_label = 'Plan #'.(int)$row['plan_id'].' '.(($row['plan_access_num']==2)?'(Premium)':'(Free)');
  } elseif (!empty($row['active_plan_id'])) {
    $plan_label = 'Plan #'.(int)$row['active_plan_id'];
  } else { $plan_label = 'No plan'; }

  $sub_status = 'No subscription';
  $sub_status_class = 'badge';
  $tooltip_lines = [];

  if ($row['last_start_date'] || $row['last_end_date']) {
    $startTxt = $row['last_start_date'] ? date('d M Y', strtotime($row['last_start_date'])) : '—';
    $endTxt   = $row['last_end_date']   ? date('d M Y', strtotime($row['last_end_date']))   : '—';

    if ($row['last_end_date'] && strtotime($row['last_end_date']) >= time()) {
      $sub_status = 'Active';
      $sub_status_class = 'badge success';
    } else {
      $sub_status = 'Expired';
      $sub_status_class = 'badge warn';
    }

    $tooltip_lines[] = 'Plan: '.$plan_label;
    $tooltip_lines[] = 'Start: '.$startTxt;
    $tooltip_lines[] = 'End: '.$endTxt;
  } else {
    $tooltip_lines[] = 'Plan: '.$plan_label;
    $tooltip_lines[] = 'No subscription log found';
  }

  $tooltip = implode("\n", $tooltip_lines);

  $refByDisplay = '—';
  $refLinkHref = null;
  if (!empty($row['referred_by'])) {
    $refName   = trim((string)($row['ref_name'] ?? ''));
    $refMobile = trim((string)($row['ref_mobile'] ?? ''));
    if ($refName === '' && $refMobile === '') {
      $refByDisplay = '#'.(int)$row['referred_by'];
    } elseif ($refName !== '' && $refMobile !== '') {
      $refByDisplay = h($refName).' ('.h($refMobile).')';
    } else {
      $refByDisplay = h($refName ?: $refMobile);
    }
    $refLinkHref = keep_params(['q'=>$refByDisplay,'page'=>1]);
  }

  $status_badge = ((int)$row['status_id']===1)
    ? '<span class="badge success">Active</span>'
    : '<span class="badge danger">Inactive</span>';

  $recruiterProfileId = (int)$row['profile_id'];
  $premiumJobsCount   = (int)($row['premium_jobs_count'] ?? 0);
  $standardJobsCount  = (int)($row['standard_jobs_count'] ?? 0);

  $premiumJobsUrl  = '/adminconsole/operations/premium_jobs_report.php?recruiter_id='.$recruiterProfileId;
  $standardJobsUrl = '/adminconsole/operations/standard_jobs_report.php?recruiter_id='.$recruiterProfileId;

  $profileUrl      = keep_params(['mode'=>'profile','rid'=>$recruiterProfileId, 'page'=>null]);
?>
    <tr>
      <td><?= (int)$sr++; ?></td>
      <td><?= h(date('d M Y', strtotime($row['created_at']))) ?></td>
      <td>
        <div style="font-weight:600"><?= h($display) ?></div>
        <?php if($profile_summary !== ''){ ?>
          <div style="font-size:12px;color:#9ca3af"><?= h($profile_summary) ?></div>
        <?php } ?>
        <div style="margin-top:4px"><?= $status_badge ?></div>
      </td>
      <td><?= h($contact_info) ?></td>
      <td><?= h($row['mobile_no']) ?></td>
      <td>
        <?php if($refLinkHref){ ?>
          <a class="ref-link" href="<?= h($refLinkHref) ?>"><?= $refByDisplay ?></a>
        <?php } else { echo $refByDisplay; } ?>
      </td>
      <td>
        <div><?= h($plan_label) ?></div>
        <div style="margin-top:4px;font-size:12px;">
          <span class="<?= h($sub_status_class) ?>" title="<?= h($tooltip) ?>">
            <?= h($sub_status) ?>
          </span>
        </div>
      </td>
      <td><?= (int)$row['total_referrals'] ?></td>
      <td>
        <?php if($premiumJobsCount > 0){ ?>
          <a href="<?= h($premiumJobsUrl) ?>" class="ref-link" title="View Premium Jobs">
            <?= $premiumJobsCount ?>
          </a>
        <?php } else { ?>
          <?= $premiumJobsCount ?>
        <?php } ?>
      </td>
      <td>
        <?php if($standardJobsCount > 0){ ?>
          <a href="<?= h($standardJobsUrl) ?>" class="ref-link" title="View Standard Jobs">
            <?= $standardJobsCount ?>
          </a>
        <?php } else { ?>
          <?= $standardJobsCount ?>
        <?php } ?>
      </td>
      <td>
        <a class="btn secondary" href="<?= h($profileUrl) ?>">View</a>
      </td>
    </tr>
<?php endwhile; $stmt->close(); ?>
<?php if($sr=== (($view==='all')?1:($offset+1))){ ?>
    <tr><td colspan="11" style="text-align:center;color:#9ca3af">No records found.</td></tr>
<?php } ?>
  </tbody>
</table>
</div>

<?php if($view!=='all'){ ?>
<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
  <?php if($page>1){ ?><a class="btn secondary" href="<?=h(keep_params(['page'=>$page-1]))?>">‹ Prev</a><?php } ?>
  <span class="badge">Page <?= (int)$page ?> / <?= (int)$pages ?></span>
  <?php if($page<$pages){ ?><a class="btn secondary" href="<?=h(keep_params(['page'=>$page+1]))?>">Next ›</a><?php } ?>
</div>
<?php } ?>

  </div>
</div>

<script>
function toggleFilterBox() {
    var box = document.getElementById('filterBox');
    var btn = document.getElementById('toggleFilterBtn');

    if (!box) return;

    if (box.style.display === "none" || box.style.display === "") {
        box.style.display = "flex";   // important because your form uses flex-wrap
        btn.innerText = "Hide Filters";
    } else {
        box.style.display = "none";
        btn.innerText = "Show Filters";
    }
}
</script>
<?php
echo ob_get_clean();
