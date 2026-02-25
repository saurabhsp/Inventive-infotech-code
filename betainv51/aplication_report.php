<?php
/* ======================================================================
   Applications – Date-wise (Premium + Standard) + Job View + Candidate View
   Single file with modes:
     - default/list view:      ? (no mode)
     - candidate view:         ?mode=candidate&userid=#
     - job view (walk-in):     ?mode=job&lt=1&id=#
     - job view (vacancy):     ?mode=job&lt=2&id=#
   Tables used:
     jos_app_applications, jos_app_applicationstatus,
     jos_app_walkininterviews, jos_app_jobvacancies,
     jos_crm_jobpost, jos_app_recruiter_profile,
     jos_app_candidate_profile, plus lookups used in job detail
   ====================================================================== */
@ini_set('display_errors', '1');
@error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/../includes/initialize.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

global $con;
if (!$con) {
  die('DB connection not initialized');
}
if (!defined('DOMAIN_URL')) define('DOMAIN_URL', '/');

/* ----------------------------------------------------------------------
   ACL: view-only for this report (uses jos_admin_menus.menu_link)
   ---------------------------------------------------------------------- */
if (!function_exists('pacific_norm_path')) {
  function pacific_norm_path(string $p): string
  {
    $p = str_replace(["\r", "\n", "\t"], '', $p);
    $p = str_replace('\\', '/', $p);
    $p = preg_replace('#\s+#', '', $p);
    $p = preg_replace('#/+#', '/', $p);
    $p = ltrim($p, '/');
    return strtolower($p);
  }
}
if (!function_exists('pacific_current_role_id')) {
  function pacific_current_role_id(mysqli $con): ?int
  {
    if (function_exists('current_user')) {
      $u = current_user();
      $uid = isset($u['id']) ? (int)$u['id'] : 0;
      if (!empty($u['role_id'])) return (int)$u['role_id'];
      if ($uid > 0) {
        $rs = mysqli_query($con, "SELECT role_id FROM jos_admin_users_roles WHERE user_id={$uid} LIMIT 1");
        if ($rs && $r = mysqli_fetch_assoc($rs)) return (int)$r['role_id'];
      }
    }
    return null;
  }
}
if (!function_exists('pacific_menu_id_for_path')) {
  function pacific_menu_id_for_path(mysqli $con): ?int
  {
    // allow ?menu_id=32 override
    $qid = isset($_GET['menu_id']) ? (int)$_GET['menu_id'] : 0;
    if ($qid > 0) return $qid;

    $script = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
    $full   = pacific_norm_path($script);                    // adminconsole/operations/applications_report.php
    $nopref = preg_replace('#^adminconsole/#', '', $full);     // operations/applications_report.php
    $base   = basename($full);                               // applications_report.php
    $stem   = preg_replace('/\.php$/i', '', $base);            // applications_report

    $cands = array_unique(array_filter([$full, $nopref, "adminconsole/$nopref", $base, $stem]));

    // fetch all menus (menu_link can contain newlines) and match in PHP
    if ($rs = mysqli_query($con, "SELECT id, menu_link FROM jos_admin_menus WHERE status=1")) {
      while ($r = mysqli_fetch_assoc($rs)) {
        $ml = pacific_norm_path((string)$r['menu_link']);
        foreach ($cands as $candRaw) {
          $cand = pacific_norm_path($candRaw);
          if (
            $ml === $cand || ($cand !== '' && str_ends_with($ml, $cand)) ||
            ($base && str_ends_with($ml, strtolower($base))) ||
            ($stem && str_contains($ml, '/' . $stem))
          ) {
            return (int)$r['id'];
          }
        }
      }
    }
    return null;
  }
}
if (!function_exists('pacific_can_view_this_page')) {
  function pacific_can_view_this_page(mysqli $con): bool
  {
    $rid = pacific_current_role_id($con);
    $mid = pacific_menu_id_for_path($con);
    if ($rid && $mid) {
      $q = "SELECT can_view FROM jos_admin_rolemenus
          WHERE role_id=$rid AND menu_id=$mid AND (status IS NULL OR status=1)
          LIMIT 1";
      if ($rs = mysqli_query($con, $q)) {
        if ($r = mysqli_fetch_assoc($rs)) return (int)$r['can_view'] === 1;
      }
    }
    return false;
  }
}
if (!pacific_can_view_this_page($con)) {
  http_response_code(403);
?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8" />
    <title>403 – Forbidden</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/adminconsole/assets/ui.css">
  </head>

  <body>
    <div class="master-wrap">
      <div class="card" style="margin:24px; padding:24px;">
        <h2 style="margin-top:0">Access denied</h2>
        <p>You don’t have permission to view this report.</p>
      </div>
    </div>
  </body>

  </html>
<?php exit;
}

/* ----------------- helpers ----------------- */
function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function get_str($k, $d = '')
{
  return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d;
}
function get_int($k, $d = 0)
{
  return isset($_GET[$k]) ? (int)$_GET[$k] : $d;
}
function fmt_date($s)
{
  return $s ? date('d M Y', strtotime($s)) : '';
}
function fmt_dt_ampm($s)
{
  return $s ? date('d M Y h:i A', strtotime($s)) : '';
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
function base_back_to_list()
{
  // remove view-specific params to go back to listing with same filters
  return h(keep_params([
    'mode' => null,
    'userid' => null,
    'id' => null,
    'lt' => get_int('lt', 0),
  ]));
}

/* ---- robust fetch-all for prepared statements (no mysqlnd) ---- */
function stmt_fetch_all_assoc(mysqli_stmt $stmt)
{
  $meta = $stmt->result_metadata();
  if (!$meta) {
    return [];
  }
  $out = [];
  $fields = [];
  $row = [];
  $bind = [];
  while ($f = $meta->fetch_field()) {
    $fields[] = $f->name;
    $row[$f->name] = null;
    $bind[] = &$row[$f->name];
  }
  call_user_func_array([$stmt, 'bind_result'], $bind);
  while ($stmt->fetch()) {
    $copy = [];
    foreach ($fields as $f) {
      $copy[$f] = $row[$f];
    }
    $out[] = $copy;
  }
  return $out;
}
function stmt_fetch_one_assoc(mysqli_stmt $stmt)
{
  $rows = stmt_fetch_all_assoc($stmt);
  return $rows ? $rows[0] : null;
}

/* ======================================================================
   MODE ROUTER
   ====================================================================== */
$mode = get_str('mode', '');

/* **********************************************************************
   MODE: CANDIDATE PROFILE  (?mode=candidate&userid=123)
   ********************************************************************** */
if ($mode === 'candidate') {
  $userid = get_int('userid', 0);
  if ($userid <= 0) {
    die('Invalid userid');
  }

  // user basics
  $u_sql = "SELECT active_plan_id, myreferral_code FROM jos_app_users WHERE id=? LIMIT 1";
  $st = $con->prepare($u_sql);
  $st->bind_param('i', $userid);
  $st->execute();
  $U = stmt_fetch_one_assoc($st);
  $st->close();
  if (!$U) {
    die('User not found');
  }
  $active_plan_id  = (int)$U['active_plan_id'];
  $myreferral_code = $U['myreferral_code'];

  // candidate profile (joins as per your API)
  $c_sql = "
    SELECT c.*,
           c.resume_generated,
           g.name AS gender_name,
           COALESCE(e.name, et.name) AS experience_type_name,
           ep.name AS experience_period_name
    FROM jos_app_candidate_profile c
    LEFT JOIN jos_crm_gender g ON c.gender_id = g.id
    LEFT JOIN jos_crm_experience e
           ON (CAST(c.experience_type AS UNSIGNED)=e.id OR LOWER(c.experience_type)=LOWER(e.name))
    LEFT JOIN jos_app_experience_list et
           ON (CAST(c.experience_type AS UNSIGNED)=et.id OR LOWER(c.experience_type)=LOWER(et.name))
    LEFT JOIN jos_app_experience_list ep
           ON (CAST(c.experience_period AS UNSIGNED)=ep.id OR LOWER(c.experience_period)=LOWER(ep.name))
    WHERE c.userid = ?
    LIMIT 1";
  $st = $con->prepare($c_sql);
  $st->bind_param('i', $userid);
  $st->execute();
  $C = stmt_fetch_one_assoc($st);
  $st->close();
  if (!$C) {
    die('Candidate profile not found');
  }

  // job positions
  $job_positions = [];
  if (!empty($C['job_position_ids'])) {
    $ids = array_filter(array_map('intval', explode(',', (string)$C['job_position_ids'])));
    if ($ids) {
      $id_list = implode(',', $ids);
      $rs = mysqli_query($con, "SELECT name FROM jos_crm_jobpost WHERE id IN ($id_list)");
      while ($r = mysqli_fetch_assoc($rs)) {
        $job_positions[] = $r['name'];
      }
    }
  }

  // photo URL
  $photo = isset($C['profile_photo']) ? trim((string)$C['profile_photo']) : '';
  if ($photo === '' || $photo === null) {
    $photo_url = DOMAIN_URL . 'webservices/uploads/nophoto_greyscale_circle.png';
  } elseif (stripos($photo, 'http://') === 0 || stripos($photo, 'https://') === 0) {
    $photo_url = $photo;
  } else {
    $photo_url = DOMAIN_URL . $photo;
  }

  // subscription
  $subscription = ['status' => 'no_subscription', 'valid_from' => '', 'valid_to' => '', 'plan_name' => '', 'validity_months' => null];
  if ($active_plan_id > 0) {
    $sq = "
      SELECT log.start_date, log.end_date, plan.plan_name, plan.validity_months
      FROM jos_app_usersubscriptionlog log
      LEFT JOIN jos_app_subscription_plans plan ON plan.id=log.plan_id
      WHERE log.userid=? AND log.plan_id=? AND log.payment_status='success'
      ORDER BY log.start_date DESC LIMIT 1";
    $st = $con->prepare($sq);
    $st->bind_param('ii', $userid, $active_plan_id);
    $st->execute();
    $S = stmt_fetch_one_assoc($st);
    $st->close();
    if ($S) {
      $today = date('Y-m-d');
      $subscription = [
        'status' => (!empty($S['end_date']) && $S['end_date'] >= $today) ? 'active' : 'expired',
        'valid_from' => fmt_date($S['start_date']),
        'valid_to' => fmt_date($S['end_date']),
        'plan_name' => $S['plan_name'],
        'validity_months' => is_null($S['validity_months']) ? null : (int)$S['validity_months'],
      ];
    }
  }

  // render
  ob_start(); ?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8" />
    <title>Application List</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/adminconsole/assets/ui.css">
    <style>
      .headbar {
        margin: 0;
        padding: 8px 0 6px;
        position: sticky;
        top: 0;
        z-index: 5;
        background: #0b0f1a;
      }

      .headbar h2 {
        margin: 0;
      }

      .chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
      }

      .chip {
        padding: 4px 10px;
        border-radius: 999px;
        border: 1px solid #243045;
        background: #0b1220;
        color: #cbd5e1;
        font-size: 12px;
      }

      .grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(260px, 1fr));
        gap: 12px 24px;
      }

      .row {
        display: flex;
        gap: 8px;
      }

      .lbl {
        min-width: 160px;
        color: #94a3b8;
      }

      .val {
        color: #e5e7eb;
      }

      .muted {
        color: #9aa0a6;
      }
    </style>
  </head>

  <body>
    <div class="master-wrap">
      <div class="headbar" style="display:flex;align-items:center;gap:12px">
        <h2>Candidate Profile</h2>
        <div style="margin-left:auto;display:flex;gap:8px">
          <a class="btn secondary" href="<?= base_back_to_list() ?>">← Back to List</a>
          <button class="btn secondary" onclick="window.print()">Print</button>
        </div>
      </div>

      <div class="card" style="padding:20px">
        <!-- Header -->
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
          <div style="height:72px;width:72px;border-radius:50%;background:#111827;overflow:hidden;display:flex;align-items:center;justify-content:center">
            <img src="<?= h($photo_url) ?>" alt="photo" style="height:100%;width:100%;object-fit:cover">
          </div>
          <div>
            <div style="font-size:18px;font-weight:700;color:#fff"><?= h($C['candidate_name'] ?: 'Candidate') ?></div>
            <div class="muted">
              <?= h($C['email'] ?: '') ?><?= ($C['email'] && $C['mobile_no']) ? ' • ' : '' ?><?= h($C['mobile_no'] ?: '') ?>
            </div>
            <?php if ($job_positions) { ?>
              <div class="chips" style="margin-top:6px">
                <?php foreach ($job_positions as $jp) { ?><span class="chip"><?= h($jp) ?></span><?php } ?>
              </div>
            <?php } ?>
          </div>
        </div>

        <div style="height:1px;background:#1f2937;margin:6px 0 16px"></div>

        <!-- Details -->
        <div class="grid">
          <?php
          $specs = [
            'Gender'            => $C['gender_name'] ?? '',
            'Birthdate'         => fmt_date($C['birthdate'] ?? ''),
            'Experience Type'   => $C['experience_type_name'] ?? '',
            'Experience Period' => $C['experience_period_name'] ?? '',
            'Address'           => $C['address'] ?? '',
            'City'              => $C['city_id'] ?? '', // city string in your data
            'Locality ID'       => $C['locality_id'] ?? '',
            'Latitude'          => isset($C['latitude']) ? trim((string)$C['latitude']) : '',
            'Longitude'         => isset($C['longitude']) ? trim((string)$C['longitude']) : '',
            'Created'           => fmt_date($C['created_at'] ?? ''),
          ];
          foreach ($specs as $label => $val) {
            $val = trim((string)$val);
            if ($val === '') continue;
            echo '<div class="row"><div class="lbl">' . h($label) . '</div><div class="val">' . h($val) . '</div></div>';
          }
          ?>
        </div>

        <?php if (!empty($C['skills']) || !empty($C['exp_description'])) { ?>
          <div style="height:1px;background:#1f2937;margin:16px 0"></div>
        <?php } ?>

        <?php if (!empty($C['skills'])) { ?>
          <div style="margin-bottom:12px">
            <div style="font-weight:600;color:#cbd5e1;margin-bottom:6px">Skills</div>
            <div class="chips">
              <?php foreach (array_filter(array_map('trim', explode(',', (string)$C['skills']))) as $s): ?>
                <span class="chip"><?= h($s) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php } ?>

        <?php if (!empty($C['exp_description'])) { ?>
          <div>
            <div style="font-weight:600;color:#cbd5e1;margin-bottom:6px">Profile Summary</div>
            <div style="white-space:pre-wrap;color:#e5e7eb"><?= h($C['exp_description']) ?></div>
          </div>
        <?php } ?>

        <div style="height:1px;background:#1f2937;margin:16px 0"></div>

        <!-- Subscription -->
        <div class="grid">
          <div class="row">
            <div class="lbl">Subscription</div>
            <div class="val">
              <?= h(ucfirst($subscription['status'])) ?>
              <?php if ($subscription['plan_name']) {
                echo ' • ' . h($subscription['plan_name']);
              } ?>
              <?php if ($subscription['valid_from'] || $subscription['valid_to']) { ?>
                <div class="muted">
                  <?= $subscription['valid_from'] ? 'From: ' . h($subscription['valid_from']) : '' ?>
                  <?= ($subscription['valid_from'] && $subscription['valid_to']) ? ' • ' : '' ?>
                  <?= $subscription['valid_to'] ? 'To: ' . h($subscription['valid_to']) : '' ?>
                </div>
              <?php } ?>
            </div>
          </div>
          <?php if ($myreferral_code) { ?>
            <div class="row">
              <div class="lbl">Referral Code</div>
              <div class="val"><?= h($myreferral_code) ?></div>
            </div>
          <?php } ?>
        </div>
      </div>
    </div>
  </body>

  </html>
<?php
  echo ob_get_clean();
  exit;
}

/* **********************************************************************
   MODE: JOB VIEW  (?mode=job&lt=1&id=#)  OR (?mode=job&lt=2&id=#)
   ********************************************************************** */
if ($mode === 'job') {
  $lt = get_int('lt', 0); // 1=walk-in (premium), 2=vacancy (standard)
  $id = get_int('id', 0);
  if ($lt !== 1 && $lt !== 2) {
    die('Invalid listing type');
  }
  if ($id <= 0) {
    die('Invalid job id');
  }

  if ($lt === 1) {
    /* ---------------- Walk-in / Premium ---------------- */
    $sql = "SELECT 
      w.id AS walkin_id,
      w.recruiter_id,
      rp.mobile_no AS recruiter_mobile_no,
      rp.company_logo,
      w.company_name,
      w.job_position_id,
      j.name AS job_position,
      w.city_id AS city,
      w.locality_id AS locality,
      w.number_of_openings,
      w.job_type AS job_type_id,
      jt.name AS job_type,
      w.work_model AS work_model_id,
      wm.name AS work_model,
      w.field_work AS field_work_id,
      CASE w.field_work WHEN 0 THEN 'No' WHEN 1 THEN 'Yes' END AS field_work,
      w.work_shift AS work_shift_id,
      ws.shift_name AS work_shift,
      w.gender AS gender_id,
      g.name AS gender,
      w.qualification AS qualification_id,
      q.name AS qualification,
      w.experience_from AS experience_from_id,
      ef.name AS experience_from,
      w.experience_to AS experience_to_id,
      et.name AS experience_to,
      w.salary_from AS salary_from_id,
      sf.salaryrange AS salary_from,
      w.salary_to AS salary_to_id,
      st.salaryrange AS salary_to,
      w.job_description,
      w.skills_required AS skills_required_ids,
      (SELECT GROUP_CONCAT(DISTINCT TRIM(s.title) ORDER BY s.title SEPARATOR ', ')
         FROM jos_crm_skills s 
         WHERE FIND_IN_SET(CAST(s.id AS CHAR), REPLACE(w.skills_required, ' ', ''))
      ) AS skills_required,
      w.languages_required AS languages_required_ids,
      (SELECT GROUP_CONCAT(DISTINCT TRIM(lang.name) ORDER BY lang.name SEPARATOR ', ')
         FROM jos_crm_languages lang 
         WHERE FIND_IN_SET(CAST(lang.id AS CHAR), REPLACE(w.languages_required, ' ', ''))
      ) AS languages_required,
      w.work_equipment AS work_equipment_ids,
      (SELECT GROUP_CONCAT(DISTINCT TRIM(we.name) ORDER BY we.name SEPARATOR ', ')
         FROM jos_app_workequipment we
         WHERE FIND_IN_SET(CAST(we.id AS CHAR), REPLACE(w.work_equipment, ' ', ''))
      ) AS work_equipment,
      w.contact_person_name,
      w.contact_no AS contact_no,
      w.interview_address,
      w.validity_apply AS validity_apply_id,
      CASE w.validity_apply WHEN 0 THEN 'No' WHEN 1 THEN 'Yes' END AS validity_apply,
      w.valid_till_date,
      w.valid_till_time,
      w.job_status_id AS job_status_id,
      js.name AS job_status,
      w.created_at
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
  } else {
    /* ---------------- Vacancy / Standard ---------------- */
    $sql = "SELECT 
      jv.id,
      jv.recruiter_id,
      jv.company_name,
      jv.contact_person,
      jv.contact_no,
      jv.interview_address,
      jv.job_position_id,
      jp.name AS job_position,
      jv.city_id AS city,
      jv.locality_id AS locality,
      jv.gender_id,
      g.name AS gender,
      jv.qualification_id,
      qs.name AS qualification,
      jv.experience_from,
      exp_from.name AS experience_from_name,
      jv.experience_to,
      exp_to.name   AS experience_to_name,
      jv.salary_from,
      sr_from.salaryrange AS salary_from_value,
      jv.salary_to,
      sr_to.salaryrange   AS salary_to_value,
      jv.job_status_id,
      js.name AS job_status,
      jv.created_at
    FROM jos_app_jobvacancies jv
    LEFT JOIN jos_crm_jobpost jp       ON jv.job_position_id = jp.id
    LEFT JOIN jos_crm_gender g         ON jv.gender_id       = g.id
    LEFT JOIN jos_crm_education_status qs ON jv.qualification_id = qs.id
    LEFT JOIN jos_app_experience_list exp_from ON jv.experience_from = exp_from.id
    LEFT JOIN jos_app_experience_list exp_to   ON jv.experience_to   = exp_to.id
    LEFT JOIN jos_crm_salary_range sr_from ON jv.salary_from = sr_from.id
    LEFT JOIN jos_crm_salary_range sr_to   ON jv.salary_to   = sr_to.id
    LEFT JOIN jos_app_jobstatus js ON jv.job_status_id = js.id
    WHERE jv.id = ? LIMIT 1";
  }

  $st = $con->prepare($sql);
  if (!$st) {
    die('Prepare failed: ' . h($con->error));
  }
  $st->bind_param('i', $id);
  $st->execute();
  $row = stmt_fetch_one_assoc($st);
  $st->close();
  if (!$row) {
    die('Job not found');
  }

  // company logo
  $company_logo = DOMAIN_URL . 'webservices/uploads/nologo.png';
  if (!empty($row['recruiter_id'])) {
    $rid = (int)$row['recruiter_id'];
    $rs = mysqli_query($con, "SELECT company_logo, organization_name FROM jos_app_recruiter_profile WHERE id=" . $rid . " LIMIT 1");
    if ($rs && $r = mysqli_fetch_assoc($rs)) {
      if (!empty($r['company_logo'])) $company_logo = DOMAIN_URL . 'webservices/' . $r['company_logo'];
      if (empty($row['company_name']) && !empty($r['organization_name'])) $row['company_name'] = $r['organization_name'];
    }
  }

  // apps count for this job
  $apps_count = 0;
  $rs = mysqli_query($con, "SELECT COUNT(*) c FROM jos_app_applications WHERE job_listing_type=" . (int)$lt . " AND job_id=" . (int)$id);
  if ($rs && $r = mysqli_fetch_assoc($rs)) {
    $apps_count = (int)$r['c'];
  }

  // render
  ob_start(); ?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8" />
    <title>Job Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/adminconsole/assets/ui.css">
    <style>
      .headbar {
        margin: 0;
        padding: 8px 0 6px;
        position: sticky;
        top: 0;
        z-index: 5;
        background: #0b0f1a;
      }

      .headbar h2 {
        margin: 0;
      }

      .muted {
        color: #9aa0a6;
      }

      .grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(260px, 1fr));
        gap: 14px 24px;
      }

      .row {
        display: flex;
        gap: 8px;
      }

      .lbl {
        min-width: 140px;
        color: #94a3b8;
      }

      .val {
        color: #e5e7eb;
      }

      .chip {
        padding: 3px 8px;
        border-radius: 999px;
        border: 1px solid #243045;
        background: #0b1220;
        color: #cbd5e1;
        font-size: 12px;
      }

      .chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
      }
    </style>
  </head>

  <body>
    <div class="master-wrap">
      <div class="headbar" style="display:flex;align-items:center;gap:12px">
        <h2>Job Details (<?= $lt === 1 ? 'Premium / Walk-in' : 'Standard / Vacancy' ?>)</h2>
        <div style="margin-left:auto;display:flex;gap:8px">
          <a class="btn secondary" href="<?= base_back_to_list() ?>">← Back to List</a>
          <button class="btn secondary" onclick="window.print()">Print</button>
        </div>
      </div>

      <div class="card" style="padding:20px">
        <!-- Header -->
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
          <div style="height:64px;width:64px;flex:0 0 64px;border-radius:12px;background:#111827;display:flex;align-items:center;justify-content:center;overflow:hidden">
            <img src="<?= h($company_logo) ?>" alt="logo" style="max-height:100%;max-width:100%">
          </div>
          <div style="min-width:0">
            <div style="font-size:20px;font-weight:700;color:#fff;line-height:1.2"><?= h($row['job_position'] ?: '') ?></div>
            <div class="muted" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              <?= h($row['company_name'] ?? '') ?>
            </div>
            <?php if (!empty($row['created_at'])) { ?>
              <div class="muted" style="font-size:12px;margin-top:2px">Posted on <?= h(fmt_date($row['created_at'])) ?></div>
            <?php } ?>
          </div>
          <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
            <span class="badge" style="background:#101a2e;border:1px solid #1f2e50;color:#cbd5e1">Applications: <?= (int)$apps_count ?></span>
          </div>
        </div>

        <div style="height:1px;background:#1f2937;margin:6px 0 16px"></div>

        <!-- Spec grid -->
        <div class="grid">
          <?php
          if ($lt === 1) {
            $specs = [
              'Openings'        => $row['number_of_openings'] ?? '',
              'Job Type'        => $row['job_type'] ?? '',
              'Work Model'      => $row['work_model'] ?? '',
              'Field Work'      => $row['field_work'] ?? '',
              'Work Shift'      => $row['work_shift'] ?? '',
              'Gender'          => $row['gender'] ?? '',
              'Qualification'   => $row['qualification'] ?? '',
              'Experience From' => $row['experience_from'] ?? '',
              'Experience To'   => $row['experience_to'] ?? '',
              'Salary From'     => $row['salary_from'] ?? '',
              'Salary To'       => $row['salary_to'] ?? '',
              'City'            => $row['city'] ?? '',
              'Locality'        => $row['locality'] ?? '',
              'Valid Apply'     => $row['validity_apply'] ?? '',
              'Valid Till'      => trim(($row['valid_till_date'] ?? '') . ' ' . ($row['valid_till_time'] ?? '')),
              'Job Status'      => $row['job_status'] ?? '',
            ];
          } else {
            $specs = [
              'Gender'          => $row['gender'] ?? '',
              'Qualification'   => $row['qualification'] ?? '',
              'Experience From' => $row['experience_from_name'] ?? '',
              'Experience To'   => $row['experience_to_name'] ?? '',
              'Salary From'     => $row['salary_from_value'] ?? '',
              'Salary To'       => $row['salary_to_value'] ?? '',
              'City'            => $row['city'] ?? '',
              'Locality'        => $row['locality'] ?? '',
              'Job Status'      => $row['job_status'] ?? '',
            ];
          }
          foreach ($specs as $label => $val) {
            $val = trim((string)$val);
            if ($val === '') continue;
            echo '<div class="row"><div class="lbl">' . h($label) . '</div><div class="val">' . h($val) . '</div></div>';
          }
          ?>
        </div>

        <?php if ($lt === 1 && (!empty($row['job_description']) || !empty($row['skills_required']) || !empty($row['languages_required']) || !empty($row['work_equipment']))) { ?>
          <div style="height:1px;background:#1f2937;margin:16px 0"></div>
          <?php if (!empty($row['job_description'])) { ?>
            <div style="margin-bottom:12px">
              <div style="font-weight:600;color:#cbd5e1;margin-bottom:6px">Job Description</div>
              <div style="white-space:pre-wrap;color:#e5e7eb"><?= h($row['job_description']) ?></div>
            </div>
          <?php } ?>
          <?php if (!empty($row['skills_required'])) { ?>
            <div style="margin-bottom:12px">
              <div style="font-weight:600;color:#cbd5e1;margin-bottom:6px">Skills</div>
              <div class="chips">
                <?php foreach (array_filter(array_map('trim', explode(',', (string)$row['skills_required']))) as $s): ?>
                  <span class="chip"><?= h($s) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php } ?>
          <?php if (!empty($row['languages_required'])) { ?>
            <div style="margin-bottom:12px">
              <div style="font-weight:600;color:#cbd5e1;margin-bottom:6px">Languages</div>
              <div class="chips">
                <?php foreach (array_filter(array_map('trim', explode(',', (string)$row['languages_required']))) as $s): ?>
                  <span class="chip"><?= h($s) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php } ?>
          <?php if (!empty($row['work_equipment'])) { ?>
            <div style="margin-bottom:12px">
              <div style="font-weight:600;color:#cbd5e1;margin-bottom:6px">Work Equipment</div>
              <div class="chips">
                <?php foreach (array_filter(array_map('trim', explode(',', (string)$row['work_equipment']))) as $s): ?>
                  <span class="chip"><?= h($s) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php } ?>
        <?php } ?>

        <div style="height:1px;background:#1f2937;margin:16px 0"></div>

        <!-- Contact -->
        <div class="grid">
          <?php
          if ($lt === 1) {
            $contact = [
              'Contact Person'    => $row['contact_person_name'] ?? '',
              'Mobile'            => !empty($row['contact_no']) ? $row['contact_no'] : ($row['recruiter_mobile_no'] ?? ''),
              'Interview Address' => $row['interview_address'] ?? '',
            ];
          } else {
            $contact = [
              'Contact Person'    => $row['contact_person'] ?? '',
              'Mobile'            => $row['contact_no'] ?? '',
              'Interview Address' => $row['interview_address'] ?? '',
            ];
          }
          foreach ($contact as $label => $val) {
            $val = trim((string)$val);
            if ($val === '') continue;
            echo '<div class="row"><div class="lbl">' . h($label) . '</div><div class="val">' . h($val) . '</div></div>';
          }
          ?>
        </div>
      </div>
    </div>
  </body>

  </html>
<?php
  echo ob_get_clean();
  exit;
}

/* **********************************************************************
   MODE: LIST (default)
   ********************************************************************** */

/* ----------------- filters ----------------- */
// $date_from    = get_str('from', '');      // YYYY-MM-DD on A.application_date
// $date_to      = get_str('to', '');        // YYYY-MM-DD
$date_from = get_str('from', '');
$date_to   = get_str('to', '');

/* convert dd/mm/yyyy to yyyy-mm-dd for DB */
if ($date_from) {
  $d = DateTime::createFromFormat('d-m-Y', $date_from);
  if ($d) $date_from = $d->format('Y-m-d');
}
if ($date_to) {
  $d = DateTime::createFromFormat('d-m-Y', $date_to);
  if ($d) $date_to = $d->format('Y-m-d');
}

$listing_type = get_int('lt', 0);         // 0=All, 1=Premium (walk-in), 2=Standard (vacancy)
$status_id    = get_int('status_id', 0);  // jos_app_applicationstatus.id
$candidate_name = get_str('candidate_name', '');
$company_name = get_str('company_name', '');
$view_all     = get_int('all', 0);        // 1=View All
$limit        = $view_all ? 1000 : 50;


/* ----------------- status options ----------------- */
$status_opts = [];
$status_name_by_id = [];
if ($rs = mysqli_query($con, "SELECT id,name FROM jos_app_applicationstatus WHERE status=1 ORDER BY COALESCE(order_by,0), id")) {
  while ($r = mysqli_fetch_assoc($rs)) {
    $status_opts[] = $r;
    $status_name_by_id[(int)$r['id']] = $r['name'];
  }
}

/* ----------------- company options ----------------- */
$company_opts = [];
$rs = mysqli_query($con, "
    SELECT DISTINCT organization_name 
    FROM jos_app_recruiter_profile 
    WHERE organization_name IS NOT NULL 
    AND organization_name != ''
    ORDER BY organization_name ASC
");

while ($r = mysqli_fetch_assoc($rs)) {
  $company_opts[] = $r['organization_name'];
}

/* ----------------- build query ----------------- */
$sql = [];
$sql[] = "SELECT";
$sql[] = "  A.id                  AS app_id,";
$sql[] = "  A.userid             AS candidate_userid,";
$sql[] = "  A.job_id             AS job_id,";
$sql[] = "  A.job_listing_type   AS job_lt,";
$sql[] = "  A.application_date,";
$sql[] = "  A.status_id,";
$sql[] = "  COALESCE(S.name,'')  AS status_name,";
$sql[] = "  COALESCE(JP1.name, JP2.name, '') AS job_position,";
$sql[] = "  COALESCE(JW.company_name, JV.company_name, RP1.organization_name, RP2.organization_name, '') AS company_name,";
$sql[] = "  CP.candidate_name, CP.mobile_no, CP.email,";
$sql[] = "  CP.city_id AS candidate_city";
$sql[] = "FROM jos_app_applications A";
$sql[] = "LEFT JOIN jos_app_applicationstatus S ON S.id = A.status_id";
/* Premium / Walk-in */
$sql[] = "LEFT JOIN jos_app_walkininterviews JW ON (A.job_listing_type=1 AND JW.id=A.job_id)";
$sql[] = "LEFT JOIN jos_crm_jobpost JP1 ON JP1.id = JW.job_position_id";
$sql[] = "LEFT JOIN jos_app_recruiter_profile RP1 ON RP1.id = JW.recruiter_id";
/* Standard / Vacancy */
$sql[] = "LEFT JOIN jos_app_jobvacancies JV ON (A.job_listing_type=2 AND JV.id=A.job_id)";
$sql[] = "LEFT JOIN jos_crm_jobpost JP2 ON JP2.id = JV.job_position_id";
$sql[] = "LEFT JOIN jos_app_recruiter_profile RP2 ON RP2.id = JV.recruiter_id";
/* Candidate */
$sql[] = "LEFT JOIN jos_app_candidate_profile CP ON CP.userid = A.userid";
$sql[] = "WHERE 1=1";



$types = '';
$binds = [];
if ($date_from !== '') {
  $sql[] = "AND DATE(A.application_date) >= ?";
  $types .= 's';
  $binds[] = $date_from;
}
if ($date_to  !== '') {
  $sql[] = "AND DATE(A.application_date) <= ?";
  $types .= 's';
  $binds[] = $date_to;
}
if ($listing_type === 1) {
  $sql[] = "AND A.job_listing_type = 1";
} elseif ($listing_type === 2) {
  $sql[] = "AND A.job_listing_type = 2";
}
if ($status_id > 0) {
  $sql[] = "AND A.status_id = ?";
  $types .= 'i';
  $binds[] = $status_id;
}
if ($candidate_name !== '') {
  $sql[] = "AND CP.candidate_name LIKE ?";
  $types .= 's';
  $binds[] = "%" . $candidate_name . "%";
}

if ($company_name !== '') {
  $sql[] = "AND (
      JW.company_name LIKE ?
      OR JV.company_name LIKE ?
      OR RP1.organization_name LIKE ?
      OR RP2.organization_name LIKE ?
  )";
  $types .= 'ssss';
  $binds[] = "%" . $company_name . "%";
  $binds[] = "%" . $company_name . "%";
  $binds[] = "%" . $company_name . "%";
  $binds[] = "%" . $company_name . "%";
}


$sql[] = "ORDER BY A.application_date DESC, A.id DESC";
$sql[] = "LIMIT " . (int)$limit;
$q = implode("\n", $sql);

/* ----------------- execute ----------------- */
$rows = [];
if ($types) {
  $stmt = $con->prepare($q);
  if (!$stmt) {
    die('Prepare failed: ' . h($con->error));
  }
  $stmt->bind_param($types, ...$binds);
  if (!$stmt->execute()) {
    die('Execute failed: ' . h($stmt->error));
  }
  $rows = stmt_fetch_all_assoc($stmt);
  $stmt->close();
} else {
  $res = mysqli_query($con, $q);
  if (!$res) {
    die('Query failed: ' . h(mysqli_error($con)));
  }
  while ($r = mysqli_fetch_assoc($res)) {
    $rows[] = $r;
  }
}

/* ----------------- render list ----------------- */
ob_start(); ?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <title>Applications – Date-wise</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="/adminconsole/assets/ui.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

  <style>
    .headbar {
      margin: 0;
      padding: 8px 0 6px;
      position: sticky;
      top: 0;
      z-index: 5;
      background: #0b0f1a;
    }

    .headbar h2 {
      margin: 0;
    }

    .headbar+.card {
      margin-top: 8px;
    }

    .toolbar .row {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: end;
    }

    .toolbar .row .group {
      display: flex;
      flex-direction: column;
      min-width: 160px;
    }

    .table-wrap {
      overflow: auto;
    }

    .pill {
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 12px;
      border: 1px solid #555;
    }

    .pill-premium {
      border-color: #d4af37;
    }

    .pill-standard {
      border-color: #666;
    }

    .muted {
      color: #9aa0a6;
      font-size: 12px;
    }

    .nowrap {
      white-space: nowrap;
    }
  </style>
</head>

<body>
  <div class="master-wrap">
    <div class="headbar">
      <div style="display:flex;align-items:center;gap:12px">
        <div>
          <h2 style="margin:0">Applications – Date-wise</h2>
          <div class="muted">Last <?= $view_all ? 'All' : '50'; ?> records • Filter by date, listing type, and status.</div>
        </div>
        <div style="margin-left:auto">
          <a class="btn secondary" href="<?= h(keep_params(['from' => null, 'to' => null, 'lt' => null, 'status_id' => null, 'candidate_name' => null, 'all' => null, 'company_name' => null])) ?>">
            Reset</a>
        </div>
      </div>
    </div>

    <div class="card toolbar">
      <form method="get">
        <div class="row">
          <div class="group">
            <label>From</label>
            <input class="inp datepicker" type="text" name="from" value="<?= h($date_from) ?>" placeholder="DD-MM-YYYY" />
          </div>
          <div class="group">
            <label>To</label>
            <input class="inp datepicker" type="text" name="to" value="<?= h($date_to) ?>" placeholder="DD-MM-YYYY" />
          </div>

          <div class="group">
            <label>Listing Type</label>
            <select class="inp" name="lt">
              <option value="0" <?= $listing_type === 0 ? 'selected' : ''; ?>>All</option>
              <option value="1" <?= $listing_type === 1 ? 'selected' : ''; ?>>Premium (Walk-in)</option>
              <option value="2" <?= $listing_type === 2 ? 'selected' : ''; ?>>Standard (Vacancy)</option>
            </select>
          </div>
          <div class="group">
            <label>Application Status</label>
            <select class="inp" name="status_id">
              <option value="0">All</option>
              <?php foreach ($status_opts as $op): ?>
                <option value="<?= (int)$op['id'] ?>" <?= $status_id == (int)$op['id'] ? 'selected' : ''; ?>>
                  <?= h($op['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="group">
            <label>Rows</label>
            <select class="inp" name="all">
              <option value="0" <?= $view_all ? '' : 'selected'; ?>>Last 50</option>
              <option value="1" <?= $view_all ? 'selected' : ''; ?>>View All</option>
            </select>
          </div>

          <div class="group">
            <label>Candidate Name</label>
            <input class="inp" type="text" name="candidate_name"
              value="<?= h($candidate_name) ?>"
              placeholder="Enter candidate name" />
          </div>

          <div class="group">
            <label>Company</label>
            <input class="inp"
              type="text"
              name="company_name"
              value="<?= h($company_name) ?>"
              placeholder="Enter company name" />
          </div>


          <div class="group">
            <label>&nbsp;</label>
            <button class="btn primary" type="submit">Apply</button>
          </div>
        </div>
      </form>
    </div>

    <div class="card table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th style="width:60px;">SR</th>
            <th>Applied On</th>
            <th>Candidate</th>
            <th>Job</th>
            <th>Company</th>
            <th>Listing</th>
            <th>Status</th>
            <th style="width:280px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="8" style="text-align:center;padding:18px;">No records found</td>
            </tr>
            <?php else: $sr = 1;
            foreach ($rows as $r):
              $lt_text = ((int)$r['job_lt'] === 1) ? 'Premium' : 'Standard';
              $pill_cls = ((int)$r['job_lt'] === 1) ? 'pill-premium' : 'pill-standard';
              $status = $r['status_name'] ?: ($status_name_by_id[(int)$r['status_id']] ?? '—');
              $jobHref = h(keep_params(['mode' => 'job', 'lt' => (int)$r['job_lt'], 'id' => (int)$r['job_id']]));
              $profileHref = h(keep_params(['mode' => 'candidate', 'userid' => (int)$r['candidate_userid']]));
            ?>
              <tr>
                <td><?= $sr++ ?></td>
                <td>
                  <div class="nowrap"><?= h(fmt_date($r['application_date'])) ?></div>
                  <div class="muted"><?= h(date('h:i A', strtotime($r['application_date']))) ?></div>
                </td>
                <td>
                  <?= h($r['candidate_name'] ?: ('User #' . (int)$r['candidate_userid'])) ?>
                  <?php if (!empty($r['candidate_city'])): ?>
                    <div class="muted" style="font-size:12px;"><?= h($r['candidate_city']) ?></div>
                  <?php endif; ?>
                  <div class="muted" style="font-size:12px">
                    <?= h($r['mobile_no'] ?: '') ?><?= ($r['mobile_no'] && $r['email']) ? ' • ' : '' ?><?= h($r['email'] ?: '') ?>
                  </div>
                </td>
                <td><?= h($r['job_position'] ?: '—') ?></td>
                <td><?= h($r['company_name'] ?: '—') ?></td>
                <td><span class="pill <?= $pill_cls ?>"><?= h($lt_text) ?></span></td>
                <td><span class="badge"><?= h($status) ?></span></td>
                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <a class="btn secondary" href="<?= $jobHref ?>" target="_blank" rel="noopener">View Job</a>
                    <a class="btn secondary" href="<?= $profileHref ?>" target="_blank" rel="noopener">View Profile</a>
                  </div>
                </td>
              </tr>
          <?php endforeach;
          endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      flatpickr(".datepicker", {
        altInput: true, // user sees formatted date
        altFormat: "d-m-Y", // display format
        dateFormat: "Y-m-d", // value sent to backend
        allowInput: false
      });
    });
  </script>

</body>

</html>
<?php
echo ob_get_clean();
