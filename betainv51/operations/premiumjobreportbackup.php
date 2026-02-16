<?php
@ini_set('display_errors','1'); @error_reporting(E_ALL);

require_once __DIR__ . '/../includes/initialize.php';
global $con;
if (!$con) { die('DB connection not initialized'); }

require_once __DIR__ . '/../includes/auth.php';
require_login();

/* ----------------------------
   ACL: view-only guard
   ---------------------------- */
try {
  $current_user_id = null;
  if (function_exists('current_user')) {
    $cu = current_user();
    if (is_array($cu) && isset($cu['id'])) {
      $current_user_id = (int)$cu['id'];
    } elseif (is_object($cu) && isset($cu->id)) {
      $current_user_id = (int)$cu->id;
    }
  }
  if (!$current_user_id && isset($_SESSION) && isset($_SESSION['user_id'])) {
    $current_user_id = (int)$_SESSION['user_id'];
  }

  $menu_id = null;
  if (isset($_GET['menu_id']) && is_numeric($_GET['menu_id'])) {
    $menu_id = (int)$_GET['menu_id'];
  } else {
    $req_uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
    $parsed  = parse_url($req_uri);
    $path    = isset($parsed['path']) ? $parsed['path'] : '/';
    $script  = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : $path;

    $q = "SELECT id, menu_link FROM jos_admin_menus
          WHERE menu_link = ? OR menu_link = ? OR menu_link LIKE CONCAT('%', ?, '%')
          LIMIT 1";
    $stmt = $con->prepare($q);
    if ($stmt) {
      $stmt->bind_param('sss', $path, $script, $path);
      $stmt->execute();
      $res      = $stmt->get_result();
      $menu_row = $res ? $res->fetch_assoc() : null;
      $stmt->close();
      if ($menu_row && isset($menu_row['id'])) {
        $menu_id = (int)$menu_row['id'];
      }
    }
  }

  if ($menu_id && $current_user_id) {
    $role_id = null;
    $q = "SELECT role_id FROM jos_admin_users_roles WHERE user_id = ? LIMIT 1";
    $stmt = $con->prepare($q);
    if ($stmt) {
      $stmt->bind_param('i', $current_user_id);
      $stmt->execute();
      $stmt->bind_result($rid);
      if ($stmt->fetch()) { $role_id = (int)$rid; }
      $stmt->close();
    }

    if ($role_id) {
      $can_view = 0;
      $q = "SELECT can_view FROM jos_admin_rolemenus WHERE role_id = ? AND menu_id = ? LIMIT 1";
      $stmt = $con->prepare($q);
      if ($stmt) {
        $stmt->bind_param('ii', $role_id, $menu_id);
        $stmt->execute();
        $stmt->bind_result($cv);
        if ($stmt->fetch()) { $can_view = (int)$cv; }
        $stmt->close();
      }

      if ($can_view !== 1) {
        http_response_code(403);
        ob_start(); ?>
        <link rel="stylesheet" href="/adminconsole/assets/ui.css">
        <div class="master-wrap" style="padding:40px">
          <div class="headbar"><h2 style="margin:0">Access denied</h2></div>
          <div class="card" style="padding:20px;margin-top:12px">
            <div style="font-size:18px;font-weight:700;color:#fff">403 — Access denied</div>
            <div style="color:#9ca3af;margin-top:8px">
              You do not have permission to view this page.
            </div>
            <div style="margin-top:12px">
              <a class="btn secondary" href="javascript:history.back()">← Go back</a>
            </div>
          </div>
        </div>
        <?php
        echo ob_get_clean();
        exit;
      }
    }
  }
} catch (Exception $e) {
  // Silent: preserve old behaviour on errors
}

/* ---------- Ensure DOMAIN_URL if not defined ---------- */
if (!defined('DOMAIN_URL')) {
    define('DOMAIN_URL', '/');
}

/* ---------------- Helpers ---------------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function keep_params(array $changes=[]){
  $qs = $_GET; foreach($changes as $k=>$v){ if($v===null){unset($qs[$k]);} else {$qs[$k]=$v;} }
  $q = http_build_query($qs); return $q?('?'.$q):'';
}
function get_int($key,$default=0){ return isset($_GET[$key]) ? (int)$_GET[$key] : $default; }
function get_str($key,$default=''){ return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default; }
function fmt_date($s){ return $s ? date('d M Y', strtotime($s)) : ''; }
function safe_date_label($raw){
  if(!$raw) return '';
  $ts = strtotime($raw);
  if($ts === false) return '';
  $y = (int)date('Y', $ts);
  if($y < 1900) return '';
  return date('d M Y', $ts);
}
function chip_list($csv){
  $csv = trim((string)$csv);
  if($csv==='') return '';
  $parts = array_filter(array_map('trim', explode(',', $csv)));
  if(!$parts) return '';
  $html = '<div style="display:flex;flex-wrap:wrap;gap:8px">';
  foreach($parts as $p){
    $html .= '<span class="badge" style="background:#0b1220;color:#cbd5e1;border:1px solid #243045">'.h($p).'</span>';
  }
  $html .= '</div>';
  return $html;
}

/* -------- Date parser for DD-MM-YY / DD/MM/YY -------- */
function dfmt_in($dateStr){
  $dateStr = trim((string)$dateStr);
  if ($dateStr === '') return null;
  $patterns = ['d-m-y','d/m/y','d-m-Y','d/m/Y','Y-m-d'];
  foreach ($patterns as $p) {
    $dt = DateTime::createFromFormat($p, $dateStr);
    if ($dt instanceof DateTime) {
      $errors = DateTime::getLastErrors();
      if (empty($errors['warning_count']) && empty($errors['error_count'])) {
        return $dt->format('Y-m-d');
      }
    }
  }
  return null;
}

/* ---------- Shared params from Job Position Summary ---------- */
$returnUrl    = isset($_GET['return']) && $_GET['return'] !== '' ? $_GET['return'] : null;
$position_id  = get_int('position_id', 0);
$positionName = '';

if ($position_id > 0) {
  $stmt = $con->prepare("SELECT name FROM jos_crm_jobpost WHERE id = ? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('i', $position_id);
    $stmt->execute();
    $stmt->bind_result($nm);
    if ($stmt->fetch()) { $positionName = (string)$nm; }
    $stmt->close();
  }
}

/* ======================================================================
   MODE: Candidate Profile Details  (?candidate={userid})
   ====================================================================== */
if (isset($_GET['candidate'])) {
  $userid = (int)$_GET['candidate'];

  $candidate_query = "
    SELECT 
        c.*,
        g.name  AS gender_name,
        COALESCE(e.name, et.name) AS experience_type_name,
        ep.name AS experience_period_name
    FROM jos_app_candidate_profile c
    LEFT JOIN jos_crm_gender g
           ON c.gender_id = g.id
    LEFT JOIN jos_crm_experience e
           ON (
                CAST(c.experience_type AS UNSIGNED) = e.id
                OR LOWER(c.experience_type) = LOWER(e.name)
              )
    LEFT JOIN jos_app_experience_list et
           ON (
                CAST(c.experience_type AS UNSIGNED) = et.id
                OR LOWER(c.experience_type) = LOWER(et.name)
              )
    LEFT JOIN jos_app_experience_list ep
           ON (
                CAST(c.experience_period AS UNSIGNED) = ep.id
                OR LOWER(c.experience_period) = LOWER(ep.name)
              )
    WHERE c.userid = ?
    LIMIT 1
  ";
  $stmt = $con->prepare($candidate_query);
  $stmt->bind_param('i', $userid);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  if(!$row){
    ob_start(); ?>
    <link rel="stylesheet" href="/adminconsole/assets/ui.css">
    <div class="master-wrap">
      <div class="headbar">
        <h2 style="margin:0">Premium Jobs List</h2>
        <div style="margin-left:auto;display:flex;gap:8px">
          <?php if($returnUrl): ?>
            <a class="btn secondary" href="<?= h($returnUrl) ?>">← Back to Position Summary</a>
          <?php endif; ?>
          <a class="btn secondary" href="<?= h(keep_params(['candidate'=>null])) ?>">← Back</a>
        </div>
      </div>
      <div class="card" style="padding:20px"><div class="badge">No profile</div></div>
    </div>
    <?php echo ob_get_clean(); exit;
  }

  $job_positions = '';
  if (!empty($row['job_position_ids'])) {
    $ids = array_filter(array_map('intval', explode(',', (string)$row['job_position_ids'])));
    if ($ids) {
      $id_list = implode(',', $ids);
      $q = "SELECT name FROM jos_crm_jobpost WHERE id IN ($id_list)";
      if ($rs = mysqli_query($con, $q)) {
        $names = [];
        while($jr = mysqli_fetch_assoc($rs)){ $names[] = $jr['name']; }
        $job_positions = implode(', ', $names);
      }
    }
  }

  $photo = isset($row['profile_photo']) ? trim((string)$row['profile_photo']) : '';
  if ($photo === '' || $photo === null) {
      $photo_url = DOMAIN_URL . 'webservices/uploads/nophoto_greyscale_circle.png';
  } elseif (stripos($photo, 'http://') === 0 || stripos($photo, 'https://') === 0) {
      $photo_url = $photo;
  } else {
      $photo_url = DOMAIN_URL . $photo;
  }

  ob_start(); ?>
  <link rel="stylesheet" href="/adminconsole/assets/ui.css">
  <div class="master-wrap">
    <div class="headbar">
      <h2 style="margin:0"><?= h($row['candidate_name'] ?: 'Candidate') ?></h2>
      <div style="margin-left:auto;display:flex;gap:8px">
        <?php if($returnUrl): ?>
          <a class="btn secondary" href="<?= h($returnUrl) ?>">← Back to Position Summary</a>
        <?php endif; ?>
        <a class="btn secondary" href="<?= h(keep_params(['candidate'=>null])) ?>">← Back</a>
        <button class="btn secondary" onclick="window.print()">Print</button>
      </div>
    </div>

    <div class="card" style="padding:20px">
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
        <div style="height:72px;width:72px;border-radius:50%;background:#111827;overflow:hidden;display:flex;align-items:center;justify-content:center">
          <img src="<?= h($photo_url) ?>" alt="photo" style="height:100%;width:100%;object-fit:cover">
        </div>
        <div>
          <div style="font-size:18px;font-weight:700;color:#fff"><?= h($row['candidate_name']) ?></div>
          <div style="color:#9ca3af"><?= h($row['email'] ?: '') ?><?= ($row['email'] && $row['mobile_no'])?' • ':'' ?><?= h($row['mobile_no'] ?: '') ?></div>
          <?php if(!empty($job_positions)){ ?>
            <div style="margin-top:6px"><?= chip_list($job_positions) ?></div>
          <?php } ?>
        </div>
      </div>

      <div style="height:1px;background:#1f2937;margin:6px 0 16px"></div>

      <div style="display:grid;grid-template-columns:repeat(2,minmax(260px,1fr));gap:12px 24px">
        <?php
          $specs = [
            'Gender'            => $row['gender_name'] ?? '',
            'Birthdate'         => safe_date_label($row['birthdate'] ?? ''),
            'Experience Type'   => $row['experience_type_name'] ?? '',
            'Experience Period' => $row['experience_period_name'] ?? '',
            'Address'           => $row['address'] ?? '',
            'City ID'           => $row['city_id'] ?? '',
            'Locality ID'       => $row['locality_id'] ?? '',
            'Latitude'          => (isset($row['latitude']) ? trim((string)$row['latitude']) : ''),
            'Longitude'         => (isset($row['longitude']) ? trim((string)$row['longitude']) : ''),
            'Created'           => safe_date_label($row['created_at'] ?? ''),
          ];
          foreach($specs as $label=>$val){
            $val = trim((string)$val);
            if($val==='') continue;
            echo '<div style="display:flex;gap:8px"><div style="min-width:160px;color:#94a3b8">'.h($label).'</div><div style="color:#e5e7eb">'.h($val).'</div></div>';
          }
        ?>
      </div>

      <?php
        $skillsHTML = chip_list($row['skills'] ?? '');
        if($skillsHTML || !empty($row['exp_description'])){
          echo '<div style="height:1px;background:#1f2937;margin:16px 0"></div>';
        }
      ?>

      <?php if($skillsHTML){ ?>
        <div style="margin-bottom:12px">
          <div style="font-weight:600;color:#cbd5e1;margin-bottom:6px">Skills</div>
          <?= $skillsHTML ?>
        </div>
      <?php } ?>

      <?php if(!empty($row['exp_description'])){ ?>
        <div>
          <div style="font-weight:600;color:#cbd5e1;margin-bottom:6px">Profile Summary</div>
          <div style="white-space:pre-wrap;color:#e5e7eb"><?= h($row['exp_description']) ?></div>
        </div>
      <?php } ?>
    </div>
  </div>
  <?php
  echo ob_get_clean();
  exit;
}

/* ======================================================================
   MODE: Applications list for a Job  (?apps={job_id})
   ====================================================================== */
if (isset($_GET['apps'])) {
  $jobId = (int)$_GET['apps'];

  $stmt = $con->prepare("SELECT j.name AS job_position
                         FROM jos_crm_jobpost j 
                         JOIN jos_app_walkininterviews w ON w.job_position_id=j.id
                         WHERE w.id=? LIMIT 1");
  $stmt->bind_param('i',$jobId);
  $stmt->execute();
  $res = $stmt->get_result();
  $job = $res->fetch_assoc();
  $stmt->close();

  $sql = "SELECT a.id,
                 a.userid,
                 a.application_date,
                 a.status_id,
                 a.interview_date_time,
                 cp.candidate_name,
                 cp.mobile_no,
                 cp.email
          FROM jos_app_applications a
          LEFT JOIN jos_app_candidate_profile cp ON cp.userid = a.userid
          WHERE a.job_listing_type = 1 AND a.job_id = ?
          ORDER BY a.application_date DESC";
  $stmt = $con->prepare($sql);
  $stmt->bind_param('i', $jobId);
  $stmt->execute();
  $apps = $stmt->get_result();
  $rows = [];
  while($r=$apps->fetch_assoc()){ $rows[]=$r; }
  $stmt->close();

  ob_start(); ?>
  <link rel="stylesheet" href="/adminconsole/assets/ui.css">
  <div class="master-wrap">
    <div class="headbar">
      <h2 style="margin:0">Applications — <?= h($job['job_position'] ?? ('Job #'.$jobId)) ?></h2>
      <div style="margin-left:auto;display:flex;gap:8px">
        <?php if($returnUrl): ?>
          <a class="btn secondary" href="<?= h($returnUrl) ?>">← Back to Position Summary</a>
        <?php endif; ?>
        <a class="btn secondary" href="<?= h(keep_params(['apps'=>null])) ?>">← Back</a>
        <span class="badge">Total: <?= (int)count($rows) ?></span>
      </div>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th style="width:80px">ID</th>
              <th>Candidate</th>
              <th>Contact</th>
              <th>Applied On</th>
              <th>Status</th>
              <th>Interview</th>
              <th style="width:140px">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!$rows){ ?>
            <tr><td colspan="7" style="text-align:center;color:#9ca3af">No applications yet.</td></tr>
          <?php } else {
            foreach($rows as $r){
              $viewCandUrl = h(keep_params(['candidate'=>(int)$r['userid']]));
              echo '<tr>';
              echo '<td>'.(int)$r['id'].'</td>';
              echo '<td>'.h($r['candidate_name'] ?: ('User #'.$r['userid'])).'</td>';
              echo '<td>';
                $contact = [];
                if(!empty($r['mobile_no'])) $contact[] = h($r['mobile_no']);
                if(!empty($r['email']))     $contact[] = h($r['email']);
                echo implode('<br>', $contact);
              echo '</td>';
              echo '<td>'.h(fmt_date($r['application_date'])).'</td>';
              echo '<td>'.h((string)$r['status_id']).'</td>';
              echo '<td>'.h(safe_date_label($r['interview_date_time'])).'</td>';
              echo '<td><a class="btn secondary" href="'.$viewCandUrl.'" target="_blank" rel="noopener">View Details</a></td>';
              echo '</tr>';
            }
          } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php
  echo ob_get_clean();
  exit;
}

/* ======================================================================
   MODE: JOB VIEW (detail) — ?view=ID (only numeric)
   ====================================================================== */
if (isset($_GET['view']) && $_GET['view'] !== '' && ctype_digit((string)$_GET['view'])) {
  $id = (int)$_GET['view'];

  $sql = "SELECT w.*,
                 j.name AS job_position,
                 jt.name AS job_type,
                 wm.name AS work_model,
                 ws.shift_name AS work_shift,
                 g.name AS gender,
                 q.name AS qualification,
                 ef.name AS experience_from,
                 et.name AS experience_to,
                 sf.salaryrange AS salary_from,
                 st.salaryrange AS salary_to,
                 js.name AS job_status,
                 rp.organization_name AS recruiter_org,
                 rp.company_logo,
                 rp.mobile_no AS recruiter_mobile_no,
                 (SELECT COUNT(*) FROM jos_app_applications a WHERE a.job_listing_type=1 AND a.job_id=w.id) AS apps_count,
                 (SELECT GROUP_CONCAT(DISTINCT TRIM(s.title) ORDER BY s.title SEPARATOR ', ')
                  FROM jos_crm_skills s 
                  WHERE FIND_IN_SET(CAST(s.id AS CHAR), REPLACE(w.skills_required,' ',''))) AS skills_required,
                 (SELECT GROUP_CONCAT(DISTINCT TRIM(lang.name) ORDER BY lang.name SEPARATOR ', ')
                  FROM jos_crm_languages lang 
                  WHERE FIND_IN_SET(CAST(lang.id AS CHAR), REPLACE(w.languages_required,' ',''))) AS languages_required,
                 (SELECT GROUP_CONCAT(DISTINCT TRIM(we.name) ORDER BY we.name SEPARATOR ', ')
                  FROM jos_app_workequipment we
                  WHERE FIND_IN_SET(CAST(we.id AS CHAR), REPLACE(w.work_equipment,' ',''))) AS work_equipment
          FROM jos_app_walkininterviews w
          LEFT JOIN jos_crm_jobpost j          ON w.job_position_id = j.id
          LEFT JOIN jos_app_jobtypes jt        ON w.job_type        = jt.id
          LEFT JOIN jos_app_workmodel wm       ON w.work_model      = wm.id
          LEFT JOIN jos_app_workshift ws       ON w.work_shift      = ws.id
          LEFT JOIN jos_crm_gender g           ON w.gender          = g.id
          LEFT JOIN jos_crm_education_status q ON w.qualification   = q.id
          LEFT JOIN jos_app_experience_list ef ON w.experience_from = ef.id
          LEFT JOIN jos_app_experience_list et ON w.experience_to   = et.id
          LEFT JOIN jos_crm_salary_range sf    ON w.salary_from     = sf.id
          LEFT JOIN jos_crm_salary_range st    ON w.salary_to       = st.id
          LEFT JOIN jos_app_jobstatus js       ON w.job_status_id   = js.id
          LEFT JOIN jos_app_recruiter_profile rp ON w.recruiter_id  = rp.id
          WHERE w.id = ? LIMIT 1";
  $stmt = $con->prepare($sql);
  $stmt->bind_param('i',$id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  ob_start(); ?>
  <link rel="stylesheet" href="/adminconsole/assets/ui.css">
  <style>
    /* Dark floating popup styling for jQuery-UI datepicker */
    .ui-datepicker {
      z-index: 99999 !important;
      background: #020617;
      border: 1px solid #1f2937;
      color: #e5e7eb;
      box-shadow: 0 12px 30px rgba(0,0,0,0.5);
      border-radius: 8px;
    }
    .ui-datepicker-header {
      background: #0f172a;
      border-bottom: 1px solid #1f2937;
      color: #e5e7eb;
    }
    .ui-datepicker .ui-state-default {
      background: #020617;
      border: 1px solid transparent;
      color: #e5e7eb;
    }
    .ui-datepicker .ui-state-hover {
      background: #111827;
      border-color: #1f2937;
    }
    .ui-datepicker .ui-state-active {
      background: #2563eb;
      border-color: #2563eb;
      color: #ffffff;
    }
  </style>
  <div class="master-wrap">
    <div class="headbar">
      <h2 style="margin:0"><?= h($row ? ($row['job_position'] ?: 'Job Details') : 'Job not found') ?></h2>
      <div style="margin-left:auto;display:flex;gap:8px">
        <?php if($returnUrl): ?>
          <a class="btn secondary" href="<?= h($returnUrl) ?>">← Back to Position Summary</a>
        <?php endif; ?>
        <?php if($row){ 
          $appsUrl = h(keep_params(['apps'=>(int)$row['id']]));
          ?>
          <a class="btn secondary" href="<?= $appsUrl ?>" target="_blank">View Applications (<?= (int)$row['apps_count'] ?>)</a>
        <?php } ?>
        <a class="btn secondary" href="<?=h(keep_params(['view'=>null]))?>">← Back to List</a>
        <button class="btn secondary" onclick="window.print()">Print</button>
      </div>
    </div>

    <?php if(!$row){ ?>
      <div class="card" style="padding:20px"><div class="badge">Job not found</div></div>
    <?php } else { 
        $logo = !empty($row['company_logo']) 
                  ? DOMAIN_URL.'webservices/'.$row['company_logo'] 
                  : DOMAIN_URL.'webservices/uploads/nologo.png';
        $company = $row['company_name'] ?: ($row['recruiter_org'] ?? '-');
        $posted  = safe_date_label($row['created_at'] ?? '');
        $validOn = safe_date_label($row['valid_till_date'] ?? '');
    ?>
    <div class="card" style="padding:20px">

      <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
        <div style="height:64px;width:64px;flex:0 0 64px;border-radius:12px;background:#111827;display:flex;align-items:center;justify-content:center;overflow:hidden">
          <?php if($logo){ ?><img src="<?=h($logo)?>" alt="logo" style="max-height:100%;max-width:100%"><?php } ?>
        </div>
        <div style="min-width:0">
          <div style="font-size:20px;font-weight:700;color:#fff;line-height:1.2"><?= h($row['job_position'] ?: '') ?></div>
          <div style="color:#9ca3af;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($company) ?></div>
          <?php if($posted){ ?><div style="color:#6b7280;font-size:12px;margin-top:2px">Posted on <?= h($posted) ?></div><?php } ?>
        </div>
        <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
          <?php if(!empty($row['job_status'])){ ?>
            <span class="badge" style="background:#0b3b2a;color:#a7f3d0;border:1px solid #14532d"><?= h($row['job_status']) ?></span>
          <?php } ?>
          <?php if($validOn){ ?>
            <span class="badge" title="Valid till" style="background:#231d0b;color:#fde68a;border:1px solid #3f2f0a">Valid: <?= h($validOn) ?><?= $row['valid_till_time'] ? ' • '.h($row['valid_till_time']) : '' ?></span>
          <?php } ?>
            <span class="badge" style="background:#101a2e;border:1px solid #1f2e50;color:#cbd5e1">Applications: <?= (int)$row['apps_count'] ?></span>
        </div>
      </div>

      <div style="height:1px;background:#1f2937;margin:6px 0 16px"></div>

      <div style="display:grid;grid-template-columns:repeat(2,minmax(260px,1fr));gap:14px 24px">
        <?php
          $specs = [
            'Openings'         => $row['number_of_openings'] ?? '',
            'Job Type'         => $row['job_type'] ?? '',
            'Work Model'       => $row['work_model'] ?? '',
            'Work Shift'       => $row['work_shift'] ?? '',
            'Gender'           => $row['gender'] ?? '',
            'Qualification'    => $row['qualification'] ?? '',
            'Experience From'  => $row['experience_from'] ?? '',
            'Experience To'    => $row['experience_to'] ?? '',
            'Salary From'      => $row['salary_from'] ?? '',
            'Salary To'        => $row['salary_to'] ?? '',
            'Validity Apply'   => (isset($row['validity_apply']) ? ($row['validity_apply'] ? 'Yes' : 'No') : ''),
          ];
          foreach($specs as $label=>$val){
            $val = trim((string)$val);
            if($val==='') continue;
            echo '<div style="display:flex;gap:8px"><div style="min-width:140px;color:#94a3b8">'.h($label).'</div><div style="color:#e5e7eb">'.h($val).'</div></div>';
          }
        ?>
      </div>

      <?php
        $skillsHTML = chip_list($row['skills_required'] ?? '');
        $langHTML   = chip_list($row['languages_required'] ?? '');
        $equipHTML  = chip_list($row['work_equipment'] ?? '');
        $hasBlocks = (trim($row['job_description'] ?? '') !== '') || $skillsHTML || $langHTML || $equipHTML;
        if($hasBlocks){ echo '<div style="height:1px;background:#1f2937;margin:16px 0"></div>'; }
      ?>

      <?php if(trim((string)($row['job_description'] ?? ''))!==''){ ?>
        <div style="margin-bottom:12px">
          <div style="font-weight:600;color:#cbd5e1;margin-bottom:6px">Job Description</div>
          <div style="white-space:pre-wrap;color:#e5e7eb"><?= h($row['job_description']) ?></div>
        </div>
      <?php } ?>

      <?php if($skillsHTML){ ?>
        <div style="margin-bottom:12px">
          <div style="font-weight:600;color:#cbd5e1;margin-bottom:6px">Skills</div>
          <?= $skillsHTML ?>
        </div>
      <?php } ?>

      <?php if($langHTML){ ?>
        <div style="margin-bottom:12px">
          <div style="font-weight:600;color:#cbd5e1;margin-bottom:6px">Languages</div>
          <?= $langHTML ?>
        </div>
      <?php } ?>

      <?php if($equipHTML){ ?>
        <div style="margin-bottom:12px">
          <div style="font-weight:600;color:#cbd5e1;margin-bottom:6px">Work Equipment</div>
          <?= $equipHTML ?>
        </div>
      <?php } ?>

      <div style="height:1px;background:#1f2937;margin:16px 0"></div>

      <div style="display:grid;grid-template-columns:repeat(2,minmax(260px,1fr));gap:12px 24px">
        <?php
          $contact = [
            'Contact Person'   => $row['contact_person_name'] ?? '',
            'Contact Mobile'   => ($row['contact_no'] ?: ($row['recruiter_mobile_no'] ?? '')),
            'Interview Address'=> $row['interview_address'] ?? '',
          ];
          foreach($contact as $label=>$val){
            $val = trim((string)$val);
            if($val==='') continue;
            echo '<div style="display:flex;gap:8px"><div style="min-width:140px;color:#94a3b8">'.h($label).'</div><div style="color:#e5e7eb">'.h($val).'</div></div>';
          }
        ?>
      </div>
    </div>
    <?php } ?>
  </div>

<script>
(function() {
  function initDatepickers() {
    if (!window.jQuery || typeof jQuery.fn.datepicker !== 'function') {
      return false;
    }
    jQuery('.datepick').datepicker({
      dateFormat: 'dd-mm-yy',
      changeMonth: true,
      changeYear: true,
      appendTo: 'body' // popup attached to body so it floats above panels
    });
    return true;
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (initDatepickers()) return;

    var needJQ = !window.jQuery;
    var scriptsToLoad = needJQ ? 2 : 1;
    var loaded = 0;

    function done() {
      loaded++;
      if (loaded >= scriptsToLoad) {
        initDatepickers();
      }
    }

    if (needJQ) {
      var jq = document.createElement('script');
      jq.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
      jq.onload = done;
      document.head.appendChild(jq);
    }

    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css';
    document.head.appendChild(link);

    var jqUi = document.createElement('script');
    jqUi.src = 'https://code.jquery.com/ui/1.13.2/jquery-ui.min.js';
    jqUi.onload = done;
    document.head.appendChild(jqUi);
  });
})();
</script>
<?php
  echo ob_get_clean();
  exit;
}

/* ======================================================================
   LIST MODE (Premium Jobs List) + Applications count
   ====================================================================== */
$page_title = 'Premium Jobs List';
if ($positionName !== '') {
  $page_title .= ' – '.$positionName;
}
$DEFAULT_PAGE_SIZE = 50;

$SQL_SELECT = 'w.id, w.created_at,
               j.name AS job_position,
               w.company_name,
               rp.organization_name AS recruiter_org,
               js.name AS job_status,
               (SELECT COUNT(*) FROM jos_app_applications a WHERE a.job_listing_type=1 AND a.job_id=w.id) AS apps_count';
$SQL_FROM   = 'FROM jos_app_walkininterviews w
               LEFT JOIN jos_crm_jobpost j ON w.job_position_id = j.id
               LEFT JOIN jos_app_jobstatus js ON w.job_status_id = js.id
               LEFT JOIN jos_app_recruiter_profile rp ON w.recruiter_id = rp.id';
$SORT_MAP = [
  'newest'    => 'ORDER BY w.id DESC',
  'oldest'    => 'ORDER BY w.id ASC',
  'name_asc'  => 'ORDER BY j.name ASC',
  'name_desc' => 'ORDER BY j.name DESC'
];

$COLUMNS = [
  ['label'=>'SR No','width'=>'70px','render'=>function($row,$sr){echo (int)$sr;}],
  ['label'=>'Job Position','render'=>function($row){
    echo h($row['job_position'] ?? '');
    $company = $row['company_name'] ?: $row['recruiter_org'];
    if($company) echo '<div style="font-size:12px;color:#9ca3af">Company: '.h($company).'</div>';
  }],
  ['label'=>'Status','render'=>function($row){echo h($row['job_status']??'');}],
  ['label'=>'Posted On','render'=>function($row){echo h(fmt_date($row['created_at']));}],
  ['label'=>'Applications','render'=>function($row){ echo (int)($row['apps_count'] ?? 0); }],
  ['label'=>'Actions','render'=>function($row){
    $viewUrl  = h(keep_params(['view'=>(int)$row['id']]));
    $appsUrl  = h(keep_params(['apps'=>(int)$row['id']]));
    echo '<div style="display:flex;gap:6px;flex-wrap:wrap">';
    echo   '<a class="btn secondary" href="'.$viewUrl.'" target="_blank">View</a>';
    echo   '<a class="btn secondary" href="'.$appsUrl.'" target="_blank">View Applications ('.(int)$row['apps_count'].')</a>';
    echo '</div>';
  }]
];

/* -------- Filters (with dd-mm-yy datepicker support) -------- */
$q                 = get_str('q','');
$status_in         = get_str('status','');
$created_from_raw  = get_str('created_from','');
$created_to_raw    = get_str('created_to','');
$created_from      = dfmt_in($created_from_raw);
$created_to        = dfmt_in($created_to_raw);
$sort              = get_str('sort','newest');
$view              = get_str('view','last50');
$page              = max(1,get_int('page',1));
$per_page          = ($view==='all')?1000:$DEFAULT_PAGE_SIZE;
$offset            = ($page-1)*$per_page;

$where=[];$types='';$params=[];
if($q!==''){
  $where[]="(w.company_name LIKE CONCAT('%',?,'%') OR j.name LIKE CONCAT('%',?,'%'))";
  $types.='ss'; $params[]=$q; $params[]=$q;
}
if ($status_in==='active'){       $where[] = "w.job_status_id = 1"; }
elseif ($status_in==='inactive'){ $where[] = "w.job_status_id = 0"; }

if ($position_id > 0) {
  $where[]  = "w.job_position_id = ?";
  $types   .= 'i';
  $params[] = $position_id;
}

if ($created_from !== null){
  $where[]  = "DATE(w.created_at)>=?";
  $types   .= 's'; 
  $params[] = $created_from;
}
if ($created_to !== null){
  $where[]  = "DATE(w.created_at)<=?";
  $types   .= 's'; 
  $params[] = $created_to;
}

$sql_where = $where ? (' WHERE '.implode(' AND ',$where)) : '';
$order     = $SORT_MAP[$sort] ?? reset($SORT_MAP);

/* count */
$sql_count="SELECT COUNT(*) ".$SQL_FROM.$sql_where;
$stmt=$con->prepare($sql_count);
if($types){$stmt->bind_param($types,...$params);}
$stmt->execute();$stmt->bind_result($total);$stmt->fetch();$stmt->close();

/* pagination */
$pages=($view!=='all')?max(1,ceil($total/$per_page)):1;
if($page>$pages){$page=$pages;$offset=($page-1)*$per_page;}

/* main */
$sql="SELECT ".$SQL_SELECT." ".$SQL_FROM.$sql_where." ".$order." ".($view==='all'?"":" LIMIT $per_page OFFSET $offset");
$stmt=$con->prepare($sql);
if($types){$stmt->bind_param($types,...$params);}
$stmt->execute();$res=$stmt->get_result();

/* render */
ob_start(); ?>
<link rel="stylesheet" href="/adminconsole/assets/ui.css">
<style>
  /* Dark floating popup styling for jQuery-UI datepicker (list view) */
  .ui-datepicker {
    z-index: 99999 !important;
    background: #020617;
    border: 1px solid #1f2937;
    color: #e5e7eb;
    box-shadow: 0 12px 30px rgba(0,0,0,0.5);
    border-radius: 8px;
  }
  .ui-datepicker-header {
    background: #0f172a;
    border-bottom: 1px solid #1f2937;
    color: #e5e7eb;
  }
  .ui-datepicker .ui-state-default {
    background: #020617;
    border: 1px solid transparent;
    color: #e5e7eb;
  }
  .ui-datepicker .ui-state-hover {
    background: #111827;
    border-color: #1f2937;
  }
  .ui-datepicker .ui-state-active {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
  }
</style>
<div class="master-wrap">
  <div class="headbar">
    <h2 style="margin:0"><?=h($page_title)?></h2>
    <div style="margin-left:auto;display:flex;gap:8px">
      <?php if($returnUrl): ?>
        <a class="btn secondary" href="<?= h($returnUrl) ?>">← Back to Position Summary</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <form method="get" class="toolbar" style="gap:10px;flex-wrap:wrap">
      <?php if($position_id > 0): ?>
        <input type="hidden" name="position_id" value="<?= (int)$position_id ?>">
      <?php endif; ?>
      <?php if($returnUrl): ?>
        <input type="hidden" name="return" value="<?= h($returnUrl) ?>">
      <?php endif; ?>

      <input class="inp" type="text" name="q" value="<?=h($q)?>" placeholder="Search job/company..." style="min-width:240px">

      <select class="inp" name="status">
        <option value="" <?= $status_in===''?'selected':''?>>Status: Any</option>
        <option value="active" <?= $status_in==='active'?'selected':''?>>Active</option>
        <option value="inactive" <?= $status_in==='inactive'?'selected':''?>>Inactive</option>
      </select>

      <input class="inp datepick" type="text" name="created_from"
             value="<?=h($created_from_raw)?>"
             placeholder="DD-MM-YY">
      <input class="inp datepick" type="text" name="created_to"
             value="<?=h($created_to_raw)?>"
             placeholder="DD-MM-YY">

      <select class="inp" name="sort">
        <?php foreach($SORT_MAP as $k=>$v): ?>
        <option value="<?=$k?>" <?=$sort===$k?'selected':''?>><?=h($k)?></option>
        <?php endforeach;?>
      </select>

      <button class="btn primary" type="submit">Apply</button>

      <div style="flex:1"></div>
      <a class="btn secondary" href="<?=h(keep_params(['view'=>'last50','page'=>1]))?>">Last <?=$DEFAULT_PAGE_SIZE?></a>
      <a class="btn secondary" href="<?=h(keep_params(['view'=>'all','page'=>1]))?>">View All</a>
    </form>

    <div style="display:flex;align-items:center;gap:12px;margin:8px 0 12px">
      <span class="badge">Total: <?= (int)$total ?></span>
      <span class="badge">Showing: <?= ($view==='all') ? 'All' : ($res->num_rows) ?></span>
      <?php if($view!=='all'){ ?>
        <div style="margin-left:auto;display:flex;gap:6px;align-items:center">
          <?php if($page>1){ ?><a class="btn secondary" href="<?=h(keep_params(['page'=>$page-1]))?>">‹ Prev</a><?php } ?>
          <span>Page <?= (int)$page ?> / <?= (int)$pages ?></span>
          <?php if($page<$pages){ ?><a class="btn secondary" href="<?=h(keep_params(['page'=>$page+1]))?>">Next ›</a><?php } ?>
        </div>
      <?php } ?>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <?php foreach($COLUMNS as $col): ?>
              <th<?=isset($col['width'])?' style="width:'.$col['width'].';"':''?>><?=h($col['label'])?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php
          $srStart = ($view==='all') ? 1 : ($offset+1);
          $sr = $srStart;
          while($row = $res->fetch_assoc()):
            echo '<tr>';
            foreach($COLUMNS as $col){
              echo '<td>';
              $col['render']($row, $sr);
              echo '</td>';
            }
            echo '</tr>';
            $sr++;
          endwhile;
          $stmt->close();

          if ($sr === $srStart){
            echo '<tr><td colspan="'.count($COLUMNS).'" style="text-align:center;color:#9ca3af">No records found.</td></tr>';
          }
        ?>
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
(function() {
  function initDatepickers() {
    if (!window.jQuery || typeof jQuery.fn.datepicker !== 'function') {
      return false;
    }
    jQuery('.datepick').datepicker({
      dateFormat: 'dd-mm-yy',
      changeMonth: true,
      changeYear: true,
      appendTo: 'body' // popup attached to body so it floats above panels
    });
    return true;
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (initDatepickers()) return;

    var needJQ = !window.jQuery;
    var scriptsToLoad = needJQ ? 2 : 1;
    var loaded = 0;

    function done() {
      loaded++;
      if (loaded >= scriptsToLoad) {
        initDatepickers();
      }
    }

    if (needJQ) {
      var jq = document.createElement('script');
      jq.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
      jq.onload = done;
      document.head.appendChild(jq);
    }

    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css';
    document.head.appendChild(link);

    var jqUi = document.createElement('script');
    jqUi.src = 'https://code.jquery.com/ui/1.13.2/jquery-ui.min.js';
    jqUi.onload = done;
    document.head.appendChild(jqUi);
  });
})();
</script>
<?php
echo ob_get_clean();
