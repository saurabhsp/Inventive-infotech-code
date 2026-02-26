<?php
@ini_set('display_errors', '1');
@error_reporting(E_ALL);

require_once __DIR__ . '/../includes/initialize.php';
global $con;
if (!$con) {
  die('DB connection not initialized');
}

require_once __DIR__ . '/../includes/auth.php';
require_login();

// ACCEPT RECRUITER ID FROM LIST
$recruiter_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $recruiter_id = isset($_POST['recruiter_id']) ? (int)$_POST['recruiter_id'] : 0;
}
/* ----------------------------
   ACL: view-only guard (same as standard_jobs_report.php)
   ---------------------------- */
try {
  // Helper: get current logged-in user's id
  $current_user_id = null;
  if (function_exists('current_user')) {
    $cu = current_user();
    if (is_array($cu) && isset($cu['id'])) {
      $current_user_id = (int)$cu['id'];
    } elseif (is_object($cu) && isset($cu->id)) {
      $current_user_id = (int)$cu->id;
    }
  }
  // Fallback: some systems store user id in session
  if (!$current_user_id && isset($_SESSION) && isset($_SESSION['user_id'])) {
    $current_user_id = (int)$_SESSION['user_id'];
  }

  // Determine menu id override (testing)
  $menu_id = null;
  if (isset($_GET['menu_id']) && is_numeric($_GET['menu_id'])) {
    $menu_id = (int)$_GET['menu_id'];
  } else {
    // Normalize current request path for matching with jos_admin_menus.menu_link
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

  // If we resolved a menu_id and we also have a user id, check role permissions.
  if ($menu_id && $current_user_id) {
    // Resolve role_id for the current user (jos_admin_users_roles)
    $role_id = null;
    $q = "SELECT role_id FROM jos_admin_users_roles WHERE user_id = ? LIMIT 1";
    $stmt = $con->prepare($q);
    if ($stmt) {
      $stmt->bind_param('i', $current_user_id);
      $stmt->execute();
      $stmt->bind_result($rid);
      if ($stmt->fetch()) {
        $role_id = (int)$rid;
      }
      $stmt->close();
    }

    // If we have a resolved role_id, check jos_admin_rolemenus.can_view
    if ($role_id) {
      $can_view = 0;
      $q = "SELECT can_view FROM jos_admin_rolemenus WHERE role_id = ? AND menu_id = ? LIMIT 1";
      $stmt = $con->prepare($q);
      if ($stmt) {
        $stmt->bind_param('ii', $role_id, $menu_id);
        $stmt->execute();
        $stmt->bind_result($cv);
        if ($stmt->fetch()) {
          $can_view = (int)$cv;
        }
        $stmt->close();
      }

      if ($can_view !== 1) {
        // Render 403 Access denied (styled with adminconsole/assets/ui.css)
        http_response_code(403);
        ob_start(); ?>
        <link rel="stylesheet" href="/adminconsole/assets/ui.css">
        <div class="master-wrap" style="padding:40px">
          <div class="headbar">
            <h2 style="margin:0">Access denied</h2>
          </div>
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
    // If no role_id found, behave permissively (do not block).
  }
  // If no menu_id resolved or no current_user_id, do not block (preserve existing behavior).
} catch (Exception $e) {
  // On any unexpected error during ACL check, do not block; preserve existing behavior.
}

/* ---------- Ensure DOMAIN_URL if not defined (used for images) ---------- */
if (!defined('DOMAIN_URL')) {
  define('DOMAIN_URL', '/');
}

/* ---------------- Helpers ---------------- */
function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function keep_params(array $changes = [])
{
  $qs = $_GET;
  foreach ($changes as $k => $v) {
    if ($v === null) {
      unset($qs[$k]);
    } else {
      $qs[$k] = $v;
    }
  }
  $q = http_build_query($qs);
  return $q ? ('?' . $q) : '';
}
function get_int($key, $default = 0)
{
  return isset($_GET[$key]) ? (int)$_GET[$key] : $default;
}
function get_str($key, $default = '')
{
  return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}
function fmt_date($s)
{
  return $s ? date('d M Y', strtotime($s)) : '';
}
function fmt_dt($s)
{
  return $s ? date('d M Y h:i A', strtotime($s)) : '';
}
function safe_date_label($raw)
{
  if (!$raw) return '';
  $ts = strtotime($raw);
  if ($ts === false) return '';
  $y = (int)date('Y', $ts);
  if ($y < 1900) return '';
  return date('d M Y', $ts);
}
function chip_list($csv)
{
  $csv = trim((string)$csv);
  if ($csv === '') return '';
  $parts = array_filter(array_map('trim', explode(',', $csv)));
  if (!$parts) return '';
  $html = '<div style="display:flex;flex-wrap:wrap;gap:8px">';
  foreach ($parts as $p) {
    $html .= '<span class="badge" style="background:#0b1220;color:#cbd5e1;border:1px solid #243045">' . h($p) . '</span>';
  }
  $html .= '</div>';
  return $html;
}

/* SAFER date parser -> supports DD-MM-YY, DD/MM/YY, DD-MM-YYYY, etc, returns Y-m-d or null */
function dfmt_in($dateStr)
{
  $dateStr = trim((string)$dateStr);
  if ($dateStr === '') return null;
  $fmts = ['d-m-y', 'd/m/y', 'd-m-Y', 'd/m/Y', 'Y-m-d'];
  foreach ($fmts as $f) {
    $dt = DateTime::createFromFormat($f, $dateStr);
    if ($dt instanceof DateTime) {
      $err = DateTime::getLastErrors();
      if (empty($err['warning_count']) && empty($err['error_count'])) {
        return $dt->format('Y-m-d');
      }
    }
  }
  return null;
}

/* ---------- Params for "Back to Position Summary" ---------- */
$returnUrl    = isset($_GET['return']) && $_GET['return'] !== '' ? $_GET['return'] : null;
$position_id  = get_int('position_id', 0);
$positionName = '';

if ($position_id > 0) {
  $stmt = $con->prepare("SELECT name FROM jos_crm_jobpost WHERE id = ? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('i', $position_id);
    $stmt->execute();
    $stmt->bind_result($nm);
    if ($stmt->fetch()) {
      $positionName = (string)$nm;
    }
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

  ob_start(); ?>
  <link rel="stylesheet" href="/adminconsole/assets/ui.css">
  <div class="master-wrap">
    <div class="headbar">
      <h2 style="margin:0"><?= h($row['candidate_name'] ?? 'Candidate') ?></h2>
      <div style="margin-left:auto;display:flex;gap:8px">

        <?php if ($returnUrl): ?>
          <a class="btn secondary" href="<?= h($returnUrl) ?>">← Back</a>
        <?php endif; ?>
        <a class="btn secondary" href="standard_jobs_report.php">← Back to List</a>
      </div>
    </div>

    <div class="card" style="padding:20px">
      <?php if (!$row) { ?>
        <div class="badge">No profile</div>
      <?php } else {
        $job_positions = '';
        if (!empty($row['job_position_ids'])) {
          $ids = array_filter(array_map('intval', explode(',', (string)$row['job_position_ids'])));
          if ($ids) {
            $id_list = implode(',', $ids);
            $q = "SELECT name FROM jos_crm_jobpost WHERE id IN ($id_list)";
            if ($rs = mysqli_query($con, $q)) {
              $names = [];
              while ($jr = mysqli_fetch_assoc($rs)) {
                $names[] = $jr['name'];
              }
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
      ?>
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
          <div style="height:72px;width:72px;border-radius:50%;background:#111827;overflow:hidden;display:flex;align-items:center;justify-content:center">
            <img src="<?= h($photo_url) ?>" alt="photo" style="height:100%;width:100%;object-fit:cover">
          </div>
          <div>
            <div style="font-size:18px;font-weight:700;color:#fff"><?= h($row['candidate_name']) ?></div>
            <div style="color:#9ca3af"><?= h($row['email'] ?: '') ?><?= ($row['email'] && $row['mobile_no']) ? ' • ' : '' ?><?= h($row['mobile_no'] ?: '') ?></div>
            <?php if (!empty($job_positions)) { ?>
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
          foreach ($specs as $label => $val) {
            $val = trim((string)$val);
            if ($val === '') continue;
            echo '<div style="display:flex;gap:8px"><div style="min-width:160px;color:#94a3b8">' . h($label) . '</div><div style="color:#e5e7eb">' . h($val) . '</div></div>';
          }
          ?>
        </div>

        <?php
        $skillsHTML = chip_list($row['skills'] ?? '');
        if ($skillsHTML || !empty($row['exp_description'])) {
          echo '<div style="height:1px;background:#1f2937;margin:16px 0"></div>';
        }
        ?>

        <?php if ($skillsHTML) { ?>
          <div style="margin-bottom:12px">
            <div style="font-weight:600;color:#cbd5e1;margin-bottom:6px">Skills</div>
            <?= $skillsHTML ?>
          </div>
        <?php } ?>

        <?php if (!empty($row['exp_description'])) { ?>
          <div>
            <div style="font-weight:600;color:#cbd5e1;margin-bottom:6px">Profile Summary</div>
            <div style="white-space:pre-wrap;color:#e5e7eb"><?= h($row['exp_description']) ?></div>
          </div>
        <?php } ?>
      <?php } ?>
    </div>
  </div>
<?php
  echo ob_get_clean();
  exit;
}

/* ======================================================================
   MODE: Applications list for a Standard Job  (?apps={job_id})
   Uses jos_app_applications with job_listing_type = 2
   ====================================================================== */
if (isset($_GET['apps'])) {
  $jobId = (int)$_GET['apps'];

  // Fetch job title + company label
  $stmt = $con->prepare("
      SELECT jv.id, jp.name AS job_position,
             COALESCE(jv.company_name, rp.organization_name) AS company_label
      FROM jos_app_jobvacancies jv
      LEFT JOIN jos_crm_jobpost jp ON jv.job_position_id = jp.id
      LEFT JOIN jos_app_recruiter_profile rp ON jv.recruiter_id = rp.id
      WHERE jv.id=? LIMIT 1
  ");
  $stmt->bind_param('i', $jobId);
  $stmt->execute();
  $res = $stmt->get_result();
  $job = $res->fetch_assoc();
  $stmt->close();

  // Applications + candidate basics
  // $sql = "SELECT a.id,
  //                a.userid,
  //                a.application_date,
  //                a.status_id,
  //                a.interview_date_time,
  //                cp.candidate_name,
  //                cp.mobile_no,
  //                cp.email
  //         FROM jos_app_applications a
  //         LEFT JOIN jos_app_candidate_profile cp ON cp.userid = a.userid
  //         WHERE a.job_listing_type = 2 AND a.job_id = ?
  //         ORDER BY a.application_date DESC";
  $sql = "SELECT a.id,
               a.userid,
               a.application_date,
               a.status_id,
               s.name AS status_name,
               a.interview_date_time,
               cp.candidate_name,
               cp.mobile_no,
               cp.email
        FROM jos_app_applications a
        LEFT JOIN jos_app_candidate_profile cp 
               ON cp.userid = a.userid
        LEFT JOIN jos_app_applicationstatus s
               ON s.id = a.status_id
        WHERE a.job_listing_type = 2 
        AND a.job_id = ?
        ORDER BY a.application_date DESC";
  $stmt = $con->prepare($sql);
  $stmt->bind_param('i', $jobId);
  $stmt->execute();
  $apps = $stmt->get_result();
  $rows = [];
  while ($r = $apps->fetch_assoc()) {
    $rows[] = $r;
  }
  $stmt->close();

  ob_start(); ?>
  <link rel="stylesheet" href="/adminconsole/assets/ui.css">
  <div class="master-wrap">
    <div class="headbar">
      <h2 style="margin:0">Applications — <?= h($job['job_position'] ?? ('Job #' . $jobId)) ?></h2>
      <div style="margin-left:auto;display:flex;gap:8px">
        <?php if (!empty($job['company_label'])) { ?>
          <span class="badge"><?= h($job['company_label']) ?></span>
        <?php } ?>
        <?php if ($returnUrl): ?>
          <a class="btn secondary" href="<?= h($returnUrl) ?>">← Back</a>
        <?php else: ?>
          <a class="btn secondary" href="standard_jobs_report.php">← Back</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Sr No.</th>
              <th>Application ID</th>
              <th>Candidate</th>
              <th>Contact</th>
              <th>Applied On</th>
              <th>Status</th>
              <th>Interview</th>
              <th style="width:160px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $sr = 1;
            if (!$rows) { ?>
              <tr>
                <td colspan="7" style="text-align:center;color:#9ca3af">No applications yet.</td>
              </tr>
            <?php } else {
              foreach ($rows as $r) {
                $currentUrl = $_SERVER['REQUEST_URI'];
                $basePath   = $_SERVER['SCRIPT_NAME'];

                $viewCandUrl = $basePath
                  . '?candidate=' . (int)$r['userid']
                  . '&return=' . urlencode($currentUrl);
                echo '<tr>';
                echo '<td>' . $sr++ . '</td>';
                echo '<td>' . (int)$r['id'] . '</td>';
                echo '<td>' . h($r['candidate_name'] ?: ('User #' . $r['userid'])) . '</td>';
                echo '<td>';
                $contact = [];
                if (!empty($r['mobile_no'])) $contact[] = h($r['mobile_no']);
                if (!empty($r['email']))     $contact[] = h($r['email']);
                echo implode('<br>', $contact);
                echo '</td>';
                echo '<td>' . h(fmt_dt($r['application_date'])) . '</td>';
                echo '<td>' . h((string)$r['status_name']) . '</td>';
                echo '<td>' . h(fmt_dt($r['interview_date_time'])) . '</td>';
                echo '<td>
                <a class="btn secondary"
                  href="' . h($viewCandUrl) . '"
                  target="_blank"
                  rel="noopener">
                  View Candidate
                </a>
                </td>';
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
   MODE: JOB VIEW (detail) — ?view=ID  (Standard Job from jos_app_jobvacancies)
   IMPORTANT: only treat numeric view as ID so view=last50 / all doesn't break
   ====================================================================== */
if (isset($_GET['view']) && $_GET['view'] !== '' && ctype_digit((string)$_GET['view'])) {
  $id = (int)$_GET['view'];

  $sql = "SELECT 
            jv.*,
            jp.name AS job_position,
            g.name  AS gender,
            qs.name AS qualification,
            exp_from.name AS experience_from_name,
            exp_to.name   AS experience_to_name,
            sr_from.salaryrange AS salary_from_value,
            sr_to.salaryrange   AS salary_to_value,
            js.name AS job_status,
            rp.organization_name AS recruiter_org,
            rp.company_logo,
            rp.mobile_no AS recruiter_mobile_no,
            (SELECT COUNT(*) FROM jos_app_applications a WHERE a.job_listing_type=2 AND a.job_id=jv.id) AS apps_count
          FROM jos_app_jobvacancies jv
          LEFT JOIN jos_crm_jobpost jp ON jv.job_position_id = jp.id
          LEFT JOIN jos_crm_gender g ON jv.gender_id = g.id
          LEFT JOIN jos_crm_education_status qs ON jv.qualification_id = qs.id
          LEFT JOIN jos_app_experience_list exp_from ON jv.experience_from = exp_from.id
          LEFT JOIN jos_app_experience_list exp_to   ON jv.experience_to   = exp_to.id
          LEFT JOIN jos_crm_salary_range sr_from ON jv.salary_from = sr_from.id
          LEFT JOIN jos_crm_salary_range sr_to   ON jv.salary_to   = sr_to.id
          LEFT JOIN jos_app_jobstatus js ON jv.job_status_id = js.id
          LEFT JOIN jos_app_recruiter_profile rp ON jv.recruiter_id = rp.id
          WHERE jv.id = ? LIMIT 1";
  $stmt = $con->prepare($sql);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  ob_start(); ?>
  <link rel="stylesheet" href="/adminconsole/assets/ui.css">
  <style>
    .ui-datepicker {
      z-index: 99999 !important;
      background: #020617;
      border: 1px solid #1f2937;
      color: #e5e7eb;
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5);
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


        <?php
        if ($row) {

          $currentUrl = $_SERVER['REQUEST_URI'];

          $basePath = '/adminconsole/operations/standard_jobs_report.php';

          $appsUrl = $basePath
            . '?apps=' . (int)$row['id']
            . '&return=' . urlencode($currentUrl);
        ?>
          <a class="btn secondary" href="<?= h($appsUrl) ?>" target="_blank">
            View Applications (<?= (int)$row['apps_count'] ?>)
          </a>
        <?php } ?>


        <?php if ($returnUrl): ?>
          <a class="btn secondary" href="<?= h($returnUrl) ?>">← Back to List</a>
        <?php else: ?>
          <a class="btn secondary" href="standard_jobs_report.php">← Back to List</a>
        <?php endif; ?>
        <button class="btn secondary" onclick="window.print()">Print</button>
      </div>
    </div>

    <?php if (!$row) { ?>
      <div class="card" style="padding:20px">
        <div class="badge">Job not found</div>
      </div>
    <?php } else {
      $logo = !empty($row['company_logo'])
        ? DOMAIN_URL . 'webservices/' . $row['company_logo']
        : DOMAIN_URL . 'webservices/uploads/nologo.png';
      $company = $row['company_name'] ?: ($row['recruiter_org'] ?? '-');
      $posted  = safe_date_label($row['created_at'] ?? '');
    ?>
      <div class="card" style="padding:20px">

        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
          <div style="height:64px;width:64px;flex:0 0 64px;border-radius:12px;background:#111827;display:flex;align-items:center;justify-content:center;overflow:hidden">
            <?php if ($logo) { ?><img src="<?= h($logo) ?>" alt="logo" style="max-height:100%;max-width:100%"><?php } ?>
          </div>
          <div style="min-width:0">
            <div style="font-size:20px;font-weight:700;color:#fff;line-height:1.2"><?= h($row['job_position'] ?: '') ?></div>
            <div style="color:#9ca3af;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($company) ?></div>
            <?php if ($posted) { ?><div style="color:#6b7280;font-size:12px;margin-top:2px">Posted on <?= h($posted) ?></div><?php } ?>
          </div>
          <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
            <?php if (!empty($row['job_status'])) { ?>
              <span class="badge" style="background:#0b3b2a;color:#a7f3d0;border:1px solid #14532d"><?= h($row['job_status']) ?></span>
            <?php } ?>
            <span class="badge" style="background:#101a2e;border:1px solid #1f2e50;color:#cbd5e1">Applications: <?= (int)$row['apps_count'] ?></span>
          </div>
        </div>

        <div style="height:1px;background:#1f2937;margin:6px 0 16px"></div>

        <div style="display:grid;grid-template-columns:repeat(2,minmax(260px,1fr));gap:14px 24px">
          <?php
          $specs = [
            'Gender'           => $row['gender'] ?? '',
            'Qualification'    => $row['qualification'] ?? '',
            'Experience From'  => $row['experience_from_name'] ?? '',
            'Experience To'    => $row['experience_to_name'] ?? '',
            'Salary From'      => $row['salary_from_value'] ?? '',
            'Salary To'        => $row['salary_to_value'] ?? '',
            'City ID'          => $row['city'] ?? $row['city_id'] ?? '',
            'Locality ID'      => $row['locality'] ?? $row['locality_id'] ?? '',
            'Status'           => $row['job_status'] ?? '',
          ];
          foreach ($specs as $label => $val) {
            $val = trim((string)$val);
            if ($val === '') continue;
            echo '<div style="display:flex;gap:8px"><div style="min-width:140px;color:#94a3b8">' . h($label) . '</div><div style="color:#e5e7eb">' . h($val) . '</div></div>';
          }
          ?>
        </div>

        <div style="height:1px;background:#1f2937;margin:16px 0"></div>

        <div style="display:grid;grid-template-columns:repeat(2,minmax(260px,1fr));gap:12px 24px">
          <?php
          $contact = [
            'Contact Person'   => $row['contact_person'] ?? '',
            'Contact Mobile'   => $row['contact_no'] ?? ($row['recruiter_mobile_no'] ?? ''),
            'Interview Address' => $row['interview_address'] ?? '',
          ];
          foreach ($contact as $label => $val) {
            $val = trim((string)$val);
            if ($val === '') continue;
            echo '<div style="display:flex;gap:8px"><div style="min-width:140px;color+#94a3b8">' . h($label) . '</div><div style="color:#e5e7eb">' . h($val) . '</div></div>';
          }
          ?>
        </div>











        <?php
        /* ==========================================
   EMBED APPLICATIONS TABLE (Standard Job)
   job_listing_type = 2
   ========================================== */

        $appSql = "SELECT a.id,
                  a.userid,
                  a.application_date,
                  a.status_id,
                  a.interview_date_time,
                  cp.candidate_name,
                  cp.mobile_no,
                  cp.email
           FROM jos_app_applications a
           LEFT JOIN jos_app_candidate_profile cp 
                  ON cp.userid = a.userid
           WHERE a.job_listing_type = 2
           AND a.job_id = ?
           ORDER BY a.application_date DESC";

        $appStmt = $con->prepare($appSql);
        $appStmt->bind_param('i', $row['id']);
        $appStmt->execute();
        $appRes = $appStmt->get_result();

        $appRows = [];
        while ($ar = $appRes->fetch_assoc()) {
          $appRows[] = $ar;
        }
        $appStmt->close();
        ?>

        <div style="height:1px;background:#1f2937;margin:20px 0"></div>

        <div style="font-size:18px;font-weight:700;color:#fff;margin-bottom:10px">
          Applications (<?= count($appRows) ?>)
        </div>

        <?php if ($appRows) { ?>

          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>SR No.</th>
                  <th>Application ID</th>
                  <th>Candidate</th>
                  <th>Contact</th>
                  <th>Applied On</th>
                  <th>Status</th>
                  <th>Interview</th>
                  <th style="width:150px">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $sr = 1;
                foreach ($appRows as $r):
                  $viewCandUrl = h(keep_params(['candidate' => (int)$r['userid']]));
                ?>
                  <tr>
                    <td><?= $sr++ ?></td>
                    <td><?= (int)$r['id'] ?></td>

                    <td>
                      <?= h($r['candidate_name'] ?: ('User #' . $r['userid'])) ?>
                    </td>

                    <td>
                      <?php
                      $contact = [];
                      if (!empty($r['mobile_no'])) $contact[] = h($r['mobile_no']);
                      if (!empty($r['email']))     $contact[] = h($r['email']);
                      echo implode('<br>', $contact);
                      ?>
                    </td>

                    <td><?= h(fmt_dt($r['application_date'])) ?></td>

                    <td><?= h((string)$r['status_id']) ?></td>

                    <td><?= h(fmt_dt($r['interview_date_time'])) ?></td>

                    <td>
                      <a class="btn secondary"
                        href="<?= $viewCandUrl ?>"
                        target="_blank"
                        rel="noopener">
                        View Candidate
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

        <?php } else { ?>

          <div class="badge">No applications yet.</div>

        <?php } ?>





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
          appendTo: 'body'
        });
        return true;
      }

      document.addEventListener('DOMContentLoaded', function() {
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
   LIST MODE (Standard Jobs) + Applications count (listing_type = 2)
   ====================================================================== */
$page_title = 'Standard Jobs List';
if ($positionName !== '') {
  $page_title .= ' – ' . $positionName;
}
$DEFAULT_PAGE_SIZE = 50;

$SQL_SELECT = 'jv.id, jv.created_at, jv.state,
               jv.city_id,
               jv.locality_id,
               jp.name AS job_position,
               COALESCE(jv.company_name, rp.organization_name) AS company_label,
               js.name AS job_status,
               (SELECT COUNT(*) FROM jos_app_applications a WHERE a.job_listing_type=2 AND a.job_id=jv.id) AS apps_count';
$SQL_FROM   = 'FROM jos_app_jobvacancies jv
               LEFT JOIN jos_crm_jobpost jp ON jv.job_position_id = jp.id
               LEFT JOIN jos_app_jobstatus js ON jv.job_status_id = js.id
               LEFT JOIN jos_app_recruiter_profile rp ON jv.recruiter_id = rp.id';
$SORT_MAP = [
  'newest' => 'ORDER BY jv.id DESC',
  'oldest' => 'ORDER BY jv.id ASC',
  'name_asc' => 'ORDER BY jp.name ASC',
  'name_desc' => 'ORDER BY jp.name DESC'
];

/* -------- Filters (with dynamic Job Status + dd-mm-yy dates) -------- */
$q                 = get_str('q', ''); // searches job position & company/org
$status_id         = get_int('status_id', 0); // from dropdown
$created_from_raw  = get_str('created_from', '');
$created_to_raw    = get_str('created_to', '');
$created_from      = dfmt_in($created_from_raw);
$created_to        = dfmt_in($created_to_raw);
$sort              = get_str('sort', 'newest');
$view              = get_str('view', 'last50');
$page              = max(1, get_int('page', 1));
$per_page          = ($view === 'all') ? 1000 : $DEFAULT_PAGE_SIZE;
$offset            = ($page - 1) * $per_page;
$state     = get_str('state', '');
$city      = get_str('city', '');
$locality  = get_str('locality', '');

$where = [];
$types = '';
$params = [];

// Apply recruiter filter only if recruiter_id exists
if ($recruiter_id > 0) {
  $where[] = "jv.recruiter_id = ?";
  $types  .= 'i';
  $params[] = $recruiter_id;
}

if ($q !== '') {
  $where[] = "(COALESCE(jv.company_name, rp.organization_name) LIKE CONCAT('%',?,'%') OR jp.name LIKE CONCAT('%',?,'%'))";
  $types .= 'ss';
  $params[] = $q;
  $params[] = $q;
}
if ($status_id > 0) {
  $where[] = "jv.job_status_id = ?";
  $types .= 'i';
  $params[] = $status_id;
}
if ($state !== '') {
  $where[] = "jv.state LIKE CONCAT('%',?,'%')";
  $types  .= 's';
  $params[] = $state;
}

if ($city !== '') {
  $where[] = "jv.city_id LIKE CONCAT('%',?,'%')";
  $types  .= 's';
  $params[] = $city;
}

if ($locality !== '') {
  $where[] = "jv.locality_id LIKE CONCAT('%',?,'%')";
  $types  .= 's';
  $params[] = $locality;
}
if ($position_id > 0) {
  $where[] = "jv.job_position_id = ?";
  $types .= 'i';
  $params[] = $position_id;
}
if ($created_from !== null) {
  $where[] = "DATE(jv.created_at)>=?";
  $types .= 's';
  $params[] = $created_from;
}
if ($created_to !== null) {
  $where[] = "DATE(jv.created_at)<=?";
  $types .= 's';
  $params[] = $created_to;
}
$sql_where = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
$order = $SORT_MAP[$sort] ?? reset($SORT_MAP);

/* count */
$sql_count = "SELECT COUNT(*) " . $SQL_FROM . $sql_where;
$stmt = $con->prepare($sql_count);
if ($types) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

/* pagination */
$pages = ($view !== 'all') ? max(1, ceil($total / $per_page)) : 1;
if ($page > $pages) {
  $page = $pages;
  $offset = ($page - 1) * $per_page;
}

/* main */
$sql = "SELECT " . $SQL_SELECT . " " . $SQL_FROM . $sql_where . " " . $order . " " . ($view === 'all' ? "" : " LIMIT $per_page OFFSET $offset");
$stmt = $con->prepare($sql);
if ($types) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

/* Fetch statuses for dropdown (display_status=1) */
$statuses = [];
if ($rs = $con->query("SELECT id, name FROM jos_app_jobstatus WHERE display_status=1 ORDER BY orderby, id")) {
  while ($r = $rs->fetch_assoc()) {
    $statuses[] = $r;
  }
}

/* columns */
$COLUMNS = [
  ['label' => 'SR No', 'width' => '70px', 'render' => function ($row, $sr) {
    echo (int)$sr;
  }],
  ['label' => 'Job / Company', 'render' => function ($row) {
    echo '<div>' . h($row['job_position'] ?? '') . '</div>';
    if (!empty($row['company_label'])) {
      echo '<div style="font-size:12px;color:#9ca3af">' . h($row['company_label']) . '</div>';
    }
  }],
  ['label' => 'State', 'render' => function ($row) {
    echo h($row['state'] ?? '');
  }],
  ['label' => 'City', 'render' => function ($row) {
    echo h($row['city_id'] ?? '');
  }],
  ['label' => 'Locality', 'render' => function ($row) {
    echo h($row['locality_id'] ?? '');
  }],
  ['label' => 'Status', 'render' => function ($row) {
    echo h($row['job_status'] ?? '');
  }],
  ['label' => 'Posted On', 'render' => function ($row) {
    echo h(fmt_date($row['created_at']));
  }],
  ['label' => 'Applications', 'render' => function ($row) {
    echo (int)($row['apps_count'] ?? 0);
  }],
  ['label' => 'Actions', 'render' => function ($row) {
    $viewUrl  = h(keep_params(['view' => (int)$row['id']]));
    $appsUrl  = h(keep_params(['apps' => (int)$row['id']]));
    echo '<div style="display:flex;gap:6px;flex-wrap:wrap">';
    echo   '<a class="btn secondary" href="' . $viewUrl . '" target="_blank">View Job</a>';
    echo   '<a class="btn secondary" href="' . $appsUrl . '" target="_blank">View Applications (' . (int)$row['apps_count'] . ')</a>';
    echo '</div>';
  }]
];

/* render */
ob_start(); ?>
<link rel="stylesheet" href="/adminconsole/assets/ui.css">
<style>
  .ui-datepicker {
    z-index: 99999 !important;
    background: #020617;
    border: 1px solid #1f2937;
    color: #e5e7eb;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5);
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
    <h2 style="margin:0"><?= h($page_title) ?></h2>
    <div style="margin-left:auto;display:flex;gap:8px">
      <?php if ($returnUrl): ?>
        <a class="btn secondary" href="<?= h($returnUrl) ?>">← Back to Position Summary</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <form method="get" style="display:flex;flex-direction:column;gap:16px">
      <?php if ($position_id > 0): ?>
        <input type="hidden" name="position_id" value="<?= (int)$position_id ?>">
      <?php endif; ?>
      <?php if ($returnUrl): ?>
        <input type="hidden" name="return" value="<?= h($returnUrl) ?>">
      <?php endif; ?>

      <?php if ($recruiter_id > 0): ?>
        <input type="hidden" name="recruiter_id" value="<?= (int)$recruiter_id ?>">
      <?php endif; ?>


      <!-- ROW 1 (4 columns) -->
      <div style="
      display:grid;
      grid-template-columns:repeat(4, 1fr);
      gap:16px;">

        <input class="inp" type="text" name="q" value="<?= h($q) ?>" placeholder="Search job/company..." style="min-width:240px">


        <select class="inp" name="status_id">
          <option value="0" <?= $status_id === 0 ? 'selected' : ''; ?>>All Status</option>
          <?php foreach ($statuses as $st): ?>
            <option value="<?= h($st['id']) ?>" <?= $status_id === $st['id'] ? 'selected' : ''; ?>><?= h($st['name']) ?></option>
          <?php endforeach; ?>
        </select>

        <input class="inp datepick" type="text" name="created_from"
          value="<?= h($created_from_raw) ?>" placeholder="DD-MM-YY">

        <input class="inp datepick" type="text" name="created_to"
          value="<?= h($created_to_raw) ?>" placeholder="DD-MM-YY">
      </div>


      <!-- ROW 2 (4 columns) -->
      <div style="
      display:grid;
      grid-template-columns:repeat(4, 1fr);
      gap:16px;
  ">
        <select class="inp" name="sort">
          <?php foreach ($SORT_MAP as $k => $v): ?>
            <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= h($k) ?></option>
          <?php endforeach; ?>
        </select>

        <input class="inp" type="text" name="state"
          value="<?= h($state) ?>"
          placeholder="Search State">

        <input class="inp" type="text" name="city"
          value="<?= h($city) ?>"
          placeholder="Search City">

        <input class="inp" type="text" name="locality"
          value="<?= h($locality) ?>"
          placeholder="Search Locality">
      </div>


      <!-- BUTTON ROW -->
      <div style="display:flex;gap:12px;">
        <button class="btn primary" type="submit">Apply</button>

        <a class="btn secondary"
          href="<?= h(keep_params(['view' => 'last50', 'page' => 1])) ?>">
          Last <?= $DEFAULT_PAGE_SIZE ?>
        </a>

        <a class="btn secondary"
          href="<?= h(keep_params(['view' => 'all', 'page' => 1])) ?>">
          View All
        </a>
      </div>

    </form>

    <div style="display:flex;align-items:center;gap:12px;margin:8px 0 12px">
      <span class="badge">Total: <?= (int)$total ?></span>
      <span class="badge">Showing: <?= ($view === 'all') ? 'All' : ($res->num_rows) ?></span>
      <?php if ($view !== 'all') { ?>
        <div style="margin-left:auto;display:flex;gap:6px;align-items:center">
          <?php if ($page > 1) { ?><a class="btn secondary" href="<?= h(keep_params(['page' => $page - 1])) ?>">‹ Prev</a><?php } ?>
          <span>Page <?= (int)$page ?> / <?= (int)$pages ?></span>
          <?php if ($page < $pages) { ?><a class="btn secondary" href="<?= h(keep_params(['page' => $page + 1])) ?>">Next ›</a><?php } ?>
        </div>
      <?php } ?>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <?php foreach ($COLUMNS as $col): ?>
              <th<?= isset($col['width']) ? ' style="width:' . $col['width'] . ';"' : '' ?>><?= h($col['label']) ?></th>
              <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php
          $srStart = ($view === 'all') ? 1 : ($offset + 1);
          $sr = $srStart;
          while ($row = $res->fetch_assoc()):
            echo '<tr>';
            foreach ($COLUMNS as $col) {
              echo '<td>';
              $col['render']($row, $sr);
              echo '</td>';
            }
            echo '</tr>';
            $sr++;
          endwhile;
          $stmt->close();

          if ($sr === $srStart) {
            echo '<tr><td colspan="' . count($COLUMNS) . '" style="text-align:center;color:#9ca3af">No records found.</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>

    <?php if ($view !== 'all') { ?>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
        <?php if ($page > 1) { ?><a class="btn secondary" href="<?= h(keep_params(['page' => $page - 1])) ?>">‹ Prev</a><?php } ?>
        <span class="badge">Page <?= (int)$page ?> / <?= (int)$pages ?></span>
        <?php if ($page < $pages) { ?><a class="btn secondary" href="<?= h(keep_params(['page' => $page + 1])) ?>">Next ›</a><?php } ?>
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
        appendTo: 'body'
      });
      return true;
    }

    document.addEventListener('DOMContentLoaded', function() {
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
