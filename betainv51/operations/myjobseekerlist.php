<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

/* ------------------ View-only ACL guard (inserted) ------------------
   Same behaviour as applications_report.php:
   - Use jos_admin_menus.menu_link to find the menu row matching this script.
   - Allow ?menu_id=NN override for testing.
   - If can_view != 1 then return a 403 Access Denied page styled with ui.css
   - Do not modify any other behaviour in this file.
------------------------------------------------------------------------ */

global $con;
if (!isset($con) || !$con) {
    // If DB connection isn't ready, fail early (keeps behaviour consistent).
    http_response_code(500);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Server error</title>';
    echo '<link rel="stylesheet" href="/adminconsole/assets/ui.css">';
    echo '</head><body><div class="master-wrap"><div class="card">';
    echo '<h2>Server error</h2><div class="alert danger">DB connection not initialized.</div>';
    echo '</div></div></body></html>';
    exit;
}

/* Normalize current script path for menu matching */
$script_path = $_SERVER['PHP_SELF'];            // e.g. /adminconsole/operations/candidate_list.php
$script_basename = basename($script_path);      // e.g. candidate_list.php

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
    // Try to match by menu_link. Check two likely forms: full PHP_SELF and basename.
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

/* If no explicit menu row matched, attempt a looser match:
   try matching entries that end with the basename (use LIKE). This helps when menu_link stores a relative path.
*/
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

/* Final decision: if can_view is not exactly 1, block with 403 */
if ((int)$can_view !== 1) {
    http_response_code(403);
    // minimal styled 403 using ui.css already used in this project
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

/* -------- shared helpers / config -------- */
if (!defined('DOMAIN_URL')) {
    define('DOMAIN_URL', '/');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function get_int($key, $default = 0)
{
    return isset($_GET[$key]) ? (int)$_GET[$key] : $default;
}
function get_str($key, $default = '')
{
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

/* Date helpers – hide weird / zero dates */
function fmt_date($s)
{
    $s = trim((string)$s);
    if ($s === '' || $s === '0000-00-00' || $s === '0000-00-00 00:00:00') return '';
    $ts = strtotime($s);
    if ($ts === false) return '';
    $y = (int)date('Y', $ts);
    if ($y <= 0) return '';
    return date('d M Y', $ts);
}
function fmt_dt_ampm($s)
{
    $s = trim((string)$s);
    if ($s === '' || $s === '0000-00-00' || $s === '0000-00-00 00:00:00') return '';
    $ts = strtotime($s);
    if ($ts === false) return '';
    $y = (int)date('Y', $ts);
    if ($y <= 0) return '';
    return date('d M Y h:i A', $ts);
}
function fmt_reg_short($s)
{
    $s = trim((string)$s);
    if ($s === '' || $s === '0000-00-00' || $s === '0000-00-00 00:00:00') return '';
    $ts = strtotime($s);
    if ($ts === false) return '';
    $y = (int)date('Y', $ts);
    if ($y <= 0) return '';
    return date('d-m-y', $ts);
}

/* Registration dates: allow Y-m-d to pass as-is; still accept dd-mm-yy if user types it manually */
function normalize_reg_date($s)
{
    $s = trim((string)$s);
    if ($s === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s; // already Y-m-d
    if (preg_match('/^\d{2}-\d{2}-\d{2,4}$/', $s)) {
        $dt = DateTime::createFromFormat('d-m-Y', $s);
        if (!$dt) {
            $dt = DateTime::createFromFormat('d-m-y', $s);
        }
        if ($dt) {
            return $dt->format('Y-m-d');
        }
    }
    return '';
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

/* Back to main list URL (works even when no records found) */
function back_to_list_url()
{
    $path = $_SERVER['PHP_SELF'] ?? '';
    $qs = $_GET;
    unset($qs['mode'], $qs['userid'], $qs['id'], $qs['lt'], $qs['from']);
    $q = http_build_query($qs);
    return $path . ($q ? ('?' . $q) : '');
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

    // candidate profile
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

    // count applications for this candidate
    $apps_count = 0;
    if ($rs = mysqli_query($con, "SELECT COUNT(*) AS c FROM jos_app_applications WHERE userid=" . $userid)) {
        if ($r = mysqli_fetch_assoc($rs)) {
            $apps_count = (int)$r['c'];
        }
    }

    $apps_url = keep_params(['mode' => 'cand_apps', 'userid' => $userid]);
    $back_url = back_to_list_url();

    ob_start(); ?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8" />
        <title>Candidate Profile</title>
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <link rel="stylesheet" href="/adminconsole/assets/ui.css">
        <style>
            body {
                background: #020617;
            }

            .headbar {
                margin: 0;
                padding: 8px 0 6px;
                position: sticky;
                top: 0;
                z-index: 5;
                background: #020617;
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
                    <?php if ($apps_count > 0) { ?>
                        <a class="btn secondary" href="<?= h($apps_url) ?>">View Applications</a>
                    <?php } else { ?>
                        <span class="muted" style="font-size:12px;margin-top:2px;">No applications yet</span>
                    <?php } ?>
                    <a class="btn secondary" href="<?= h($back_url) ?>">← Back to List</a>
                    <button class="btn secondary" onclick="window.print()">Print</button>
                </div>
            </div>

            <div class="card" style="padding:20px">
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
                            <div style="margin-top:6px">
                                <div class="muted" style="font-size:12px;margin-bottom:4px">Preferred Job Positions</div>
                                <div class="chips">
                                    <?php foreach ($job_positions as $jp) { ?><span class="chip"><?= h($jp) ?></span><?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div style="height:1px;background:#1f2937;margin:6px 0 16px"></div>

                <div class="grid">
                    <?php
                    $specs = [
                        'Gender'            => $C['gender_name'] ?? '',
                        'Birthdate'         => fmt_date($C['birthdate'] ?? ''),
                        'Experience Type'   => $C['experience_type_name'] ?? '',
                        'Experience Period' => $C['experience_period_name'] ?? '',
                        'Address'           => $C['address'] ?? '',
                        'City'              => $C['city_id'] ?? '',
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
   MODE: CANDIDATE APPLICATIONS  (?mode=cand_apps&userid=123)
   ********************************************************************** */
if ($mode === 'cand_apps') {
    $userid = get_int('userid', 0);
    if ($userid <= 0) {
        die('Invalid userid');
    }

    $c_sql = "SELECT candidate_name, mobile_no, email, city_id FROM jos_app_candidate_profile WHERE userid=? LIMIT 1";
    $st = $con->prepare($c_sql);
    $st->bind_param('i', $userid);
    $st->execute();
    $C = stmt_fetch_one_assoc($st);
    $st->close();
    if (!$C) {
        die('Candidate not found');
    }

    $status_opts = [];
    $status_name_by_id = [];
    if ($rs = mysqli_query($con, "SELECT id,name FROM jos_app_applicationstatus WHERE status=1 ORDER BY COALESCE(order_by,0), id")) {
        while ($r = mysqli_fetch_assoc($rs)) {
            $status_opts[] = $r;
            $status_name_by_id[(int)$r['id']] = $r['name'];
        }
    }

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
    $sql[] = "  COALESCE(JW.company_name, JV.company_name, RP1.organization_name, RP2.organization_name, '') AS company_name";
    $sql[] = "FROM jos_app_applications A";
    $sql[] = "LEFT JOIN jos_app_applicationstatus S ON S.id = A.status_id";
    $sql[] = "LEFT JOIN jos_app_walkininterviews JW ON (A.job_listing_type=1 AND JW.id=A.job_id)";
    $sql[] = "LEFT JOIN jos_crm_jobpost JP1 ON JP1.id = JW.job_position_id";
    $sql[] = "LEFT JOIN jos_app_recruiter_profile RP1 ON RP1.id = JW.recruiter_id";
    $sql[] = "LEFT JOIN jos_app_jobvacancies JV ON (A.job_listing_type=2 AND JV.id=A.job_id)";
    $sql[] = "LEFT JOIN jos_crm_jobpost JP2 ON JP2.id = JV.job_position_id";
    $sql[] = "LEFT JOIN jos_app_recruiter_profile RP2 ON RP2.id = JV.recruiter_id";
    $sql[] = "WHERE A.userid = ?";
    $sql[] = "ORDER BY A.application_date DESC, A.id DESC";
    $q = implode("\n", $sql);

    $st = $con->prepare($q);
    if (!$st) {
        die('Prepare failed: ' . h($con->error));
    }
    $st->bind_param('i', $userid);
    if (!$st->execute()) {
        die('Execute failed: ' . h($st->error));
    }
    $rows = stmt_fetch_all_assoc($st);
    $st->close();

    $back_profile = $_SERVER['PHP_SELF'] . '?' . http_build_query(['mode' => 'candidate', 'userid' => $userid]);
    $back_list    = back_to_list_url();

    ob_start(); ?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8" />
        <title>Applications – Candidate</title>
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <link rel="stylesheet" href="/adminconsole/assets/ui.css">
        <style>
            body {
                background: #020617;
            }

            .headbar {
                margin: 0;
                padding: 8px 0 6px;
                position: sticky;
                top: 0;
                z-index: 5;
                background: #020617;
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
                        <h2 style="margin:0">Applications – Candidate</h2>
                        <div class="muted">
                            <?= h($C['candidate_name'] ?: ('User #' . $userid)) ?>
                            <?php if ($C['city_id']) { ?> • <?= h($C['city_id']) ?><?php } ?>
                        </div>
                    </div>
                    <div style="margin-left:auto;display:flex;gap:8px">
                        <a class="btn secondary" href="<?= h($back_profile) ?>">← Back to Profile</a>
                        <a class="btn secondary" href="<?= h($back_list) ?>">Back to List</a>
                    </div>
                </div>
            </div>

            <div class="card table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:60px;">SR</th>
                            <th>Applied On</th>
                            <th>Job</th>
                            <th>Company</th>
                            <th>Listing</th>
                            <th>Status</th>
                            <th style="width:200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;padding:12px;">No applications found for this candidate</td>
                            </tr>
                            <?php else: $sr = 1;
                            foreach ($rows as $r):
                                $lt_text = ((int)$r['job_lt'] === 1) ? 'Premium' : 'Standard';
                                $pill_cls = ((int)$r['job_lt'] === 1) ? 'pill-premium' : 'pill-standard';
                                $status = $r['status_name'] ?: ($status_name_by_id[(int)$r['status_id']] ?? '—');
                                $jobHref = keep_params([
                                    'mode'   => 'job',
                                    'lt'     => (int)$r['job_lt'],
                                    'id'     => (int)$r['job_id'],
                                    'userid' => $userid,
                                    'from'   => 'candapps'
                                ]);
                                $appliedTs = $r['application_date'] ? strtotime($r['application_date']) : false;
                            ?>
                                <tr>
                                    <td><?= $sr++ ?></td>
                                    <td>
                                        <div class="nowrap"><?= h(fmt_date($r['application_date'])) ?></div>
                                        <div class="muted"><?= h($appliedTs ? date('h:i A', $appliedTs) : '') ?></div>
                                    </td>
                                    <td><?= h($r['job_position'] ?: '—') ?></td>
                                    <td><?= h($r['company_name'] ?: '—') ?></td>
                                    <td><span class="pill <?= $pill_cls ?>"><?= h($lt_text) ?></span></td>
                                    <td><span class="badge"><?= h($status) ?></span></td>
                                    <td><a class="btn secondary" href="<?= h($jobHref) ?>" target="_blank" rel="noopener">View Job</a></td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
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

    $company_logo = DOMAIN_URL . 'webservices/uploads/nologo.png';
    if (!empty($row['recruiter_id'])) {
        $rid = (int)$row['recruiter_id'];
        $rs = mysqli_query($con, "SELECT company_logo, organization_name FROM jos_app_recruiter_profile WHERE id=" . $rid . " LIMIT 1");
        if ($rs && $r = mysqli_fetch_assoc($rs)) {
            if (!empty($r['company_logo'])) $company_logo = DOMAIN_URL . 'webservices/' . $r['company_logo'];
            if (empty($row['company_name']) && !empty($r['organization_name'])) $row['company_name'] = $r['organization_name'];
        }
    }

    $apps_count = 0;
    $rs = mysqli_query($con, "SELECT COUNT(*) c FROM jos_app_applications WHERE job_listing_type=" . (int)$lt . " AND job_id=" . (int)$id);
    if ($rs && $r = mysqli_fetch_assoc($rs)) {
        $apps_count = (int)$r['c'];
    }

    $userid_for_back = get_int('userid', 0);
    $from = get_str('from', '');
    if ($from === 'candapps' && $userid_for_back > 0) {
        $back_url = $_SERVER['PHP_SELF'] . '?' . http_build_query(['mode' => 'cand_apps', 'userid' => $userid_for_back]);
    } elseif ($userid_for_back > 0) {
        $back_url = $_SERVER['PHP_SELF'] . '?' . http_build_query(['mode' => 'candidate', 'userid' => $userid_for_back]);
    } else {
        $back_url = back_to_list_url();
    }

    ob_start(); ?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8" />
        <title>Job Details</title>
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <link rel="stylesheet" href="/adminconsole/assets/ui.css">
        <style>
            body {
                background: #020617;
            }

            .headbar {
                margin: 0;
                padding: 8px 0 6px;
                position: sticky;
                top: 0;
                z-index: 5;
                background: #020617;
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
                    <a class="btn secondary" href="<?= h($back_url) ?>">← Back</a>
                    <button class="btn secondary" onclick="window.print()">Print</button>
                </div>
            </div>

            <div class="card" style="padding:20px">
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
   DEFAULT MODE: MAIN LIST (CANDIDATES) + PLAN CARDS + HIDE FILTERS
   ********************************************************************** */

$page_title = 'Users – Candidate-wise List';

/* ==============================
   IMPORTANT: AC MANAGER FILTER
============================== */
$me = function_exists('current_user') ? current_user() : [];
$logged_admin_id = (int)($me['id'] ?? 0);

/* ==========================================================
   DASHBOARD POST FILTER SUPPORT (Assigned Jobseekers)
========================================================== */




/* ---- filters (GET) ---- */
$profile_type_id     = 2;                           // ONLY candidates
$q                   = get_str('q', '');
$referrer_q          = get_str('referrer_q', '');
$city_id             = get_str('city_id', '');
$status_id           = isset($_GET['status_id']) ? (int)$_GET['status_id'] : -1; // Any by default
$has_referrer        = (isset($_GET['has_referrer']) && $_GET['has_referrer'] !== '') ? (int)$_GET['has_referrer'] : -1;
$referral_code_in    = get_str('referral_code', '');
$plan_access_in      = get_int('plan_access', 0);     // 1 free, 2 premium
$subscription_status = strtolower(get_str('subscription_status', '')); // active|expired|''
$created_from_raw    = get_str('created_from', '');
$created_to_raw      = get_str('created_to', '');
$created_from_sql    = normalize_reg_date($created_from_raw);
$created_to_sql      = normalize_reg_date($created_to_raw);



/* ==========================================================
   DASHBOARD POST FILTER SUPPORT (Assigned Jobseekers)
========================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $posted_admin_id = (int)($_POST['admin_id'] ?? 0);
    $range           = $_POST['range'] ?? '';
    $from            = $_POST['from'] ?? '';
    $to              = $_POST['to'] ?? '';

    // 🔐 Security: Prevent tampering
    if ($posted_admin_id !== $logged_admin_id) {
        die("Unauthorized access.");
    }

    // Override date filters ONLY if coming from dashboard
    if ($range !== 'lifetime' && !empty($from) && !empty($to)) {
        $created_from_sql = date('Y-m-d', strtotime($from));
        $created_to_sql   = date('Y-m-d', strtotime($to));
    }

    // Optional: Clear GET filters when coming from dashboard
    $q = '';
    $city_id = '';
    $status_id = -1;
    $subscription_status = '';
}


/* Plan filter (via cards) */
$plan_id_filter      = get_int('plan_id', 0); // 0=All Plans

/* Preferred job positions filter (multi-select) */
$preferred_job_ids = [];
if (isset($_GET['job_position_ids']) && is_array($_GET['job_position_ids'])) {
    $preferred_job_ids = array_values(array_unique(array_filter(array_map('intval', $_GET['job_position_ids']))));
}

$sort = get_str('sort', 'newest'); // newest, oldest, name_asc, name_desc, city_asc, city_desc
$view = get_str('view', 'last50'); // last50|all
$page = max(1, get_int('page', 1));
$per_page = ($view === 'all') ? 1000 : 50;
$offset = ($page - 1) * $per_page;

/* ---- subscription plans opts for cards (profile_type=2) ---- */
$subscription_plan_opts = [];
if ($rs = mysqli_query($con, "
    SELECT id, plan_name
    FROM jos_app_subscription_plans
    WHERE profile_type = 2
    ORDER BY plan_name
")) {
    while ($r = mysqli_fetch_assoc($rs)) {
        $subscription_plan_opts[] = $r;
    }
}

/* ---- get job position options for filter ---- */
$job_positions_opts = [];
if ($rs = mysqli_query($con, "SELECT id,name FROM jos_crm_jobpost ORDER BY name")) {
    while ($r = mysqli_fetch_assoc($rs)) {
        $job_positions_opts[] = $r;
    }
}

/* ---- base joins (same logic used everywhere) ---- */
$sql_base = "
  FROM jos_app_users u
  LEFT JOIN jos_app_candidate_profile  cp ON (u.profile_type_id=2 AND cp.id=u.profile_id)
  LEFT JOIN jos_crm_gender g ON g.id = cp.gender_id

  /* --- REFERRER joins --- */
  LEFT JOIN jos_app_users ur ON ur.id = u.referred_by
  LEFT JOIN jos_app_recruiter_profile rrp ON (ur.profile_type_id=1 AND rrp.id=ur.profile_id)
  LEFT JOIN jos_app_candidate_profile  rcp ON (ur.profile_type_id=2 AND rcp.id=ur.profile_id)
  LEFT JOIN jos_app_promoter_profile   rpp ON (ur.profile_type_id=3 AND rpp.id=ur.profile_id)

  /* --- last subscription (latest by end_date then id) --- */
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

  LEFT JOIN (
    SELECT userid, COUNT(*) AS apps_count
    FROM jos_app_applications
    GROUP BY userid
  ) ac ON ac.userid = u.id
";

/* ---- common where (WITHOUT plan_id_filter) ---- */
$where_common = [];
$types_common = '';
$params_common = [];

/* only candidates */
$where_common[] = "u.profile_type_id=2";

/* AC manager restriction */
$where_common[] = "u.ac_manager_id = ?";
$types_common  .= "i";
$params_common[] = $logged_admin_id;

if ($q !== '') {
    $where_common[] = "(u.mobile_no LIKE CONCAT('%',?,'%')
          OR u.referral_code LIKE CONCAT('%',?,'%')
          OR cp.candidate_name LIKE CONCAT('%',?,'%'))";
    $types_common .= 'sss';
    $params_common = array_merge($params_common, array_fill(0, 3, $q));
}
if ($referrer_q !== '') {
    $where_common[] = "(ur.mobile_no LIKE CONCAT('%',?,'%')
          OR rrp.organization_name LIKE CONCAT('%',?,'%')
          OR rrp.contact_person_name LIKE CONCAT('%',?,'%')
          OR rcp.candidate_name LIKE CONCAT('%',?,'%')
          OR rpp.name LIKE CONCAT('%',?,'%'))";
    $types_common .= 'sssss';
    $params_common = array_merge($params_common, array_fill(0, 5, $referrer_q));
}
if ($city_id !== '') {
    $where_common[] = "u.city_id=?";
    $types_common .= 's';
    $params_common[] = $city_id;
}

if ($status_id >= 0) {
    $where_common[] = "u.status_id=?";
    $types_common .= 'i';
    $params_common[] = $status_id;
}

if ($has_referrer === 1) {
    $where_common[] = "u.referred_by IS NOT NULL AND u.referred_by<>0";
} elseif ($has_referrer === 0) {
    $where_common[] = "(u.referred_by IS NULL OR u.referred_by=0)";
}

if ($referral_code_in !== '') {
    $where_common[] = "u.referral_code=?";
    $types_common .= 's';
    $params_common[] = $referral_code_in;
}

if ($plan_access_in > 0) {
    $where_common[] = "CAST(sp.plan_access AS UNSIGNED)=?";
    $types_common .= 'i';
    $params_common[] = $plan_access_in;
}

if ($subscription_status === 'active') {
    $where_common[] = "(ls.end_date IS NOT NULL AND ls.end_date>=NOW())";
} elseif ($subscription_status === 'expired') {
    $where_common[] = "(ls.end_date IS NOT NULL AND ls.end_date<NOW())";
}

if ($created_from_sql !== '') {
    $where_common[] = "DATE(u.created_at)>=?";
    $types_common .= 's';
    $params_common[] = $created_from_sql;
}
if ($created_to_sql  !== '') {
    $where_common[] = "DATE(u.created_at)<=?";
    $types_common .= 's';
    $params_common[] = $created_to_sql;
}

/* preferred job positions filter */
if (!empty($preferred_job_ids)) {
    $parts = [];
    foreach ($preferred_job_ids as $id) {
        $parts[] = "FIND_IN_SET(?, REPLACE(cp.job_position_ids,' ',''))";
        $types_common .= 's';
        $params_common[] = (string)$id;
    }
    if ($parts) {
        $where_common[] = '(' . implode(' OR ', $parts) . ')';
    }
}

$sql_where_common = $where_common ? (' WHERE ' . implode(' AND ', $where_common)) : '';

/* ---- PLAN CARDS COUNTS (group by sp.id) ---- */
$plan_counts = [];   // plan_id => count
$cards_total = 0;

$sql_cards = "
SELECT
  sp.id AS plan_id,
  COUNT(DISTINCT u.id) AS cnt
" . $sql_base . $sql_where_common . "
GROUP BY sp.id
";
$stmt = $con->prepare($sql_cards);
if (!$stmt) {
    die('Prepare failed: ' . h($con->error));
}
if ($types_common !== '') {
    $stmt->bind_param($types_common, ...$params_common);
}
$stmt->execute();
$rows_cards = stmt_fetch_all_assoc($stmt);
$stmt->close();

foreach ($rows_cards as $r) {
    $pid = (int)($r['plan_id'] ?? 0);
    $cnt = (int)($r['cnt'] ?? 0);
    if ($pid > 0) $plan_counts[$pid] = $cnt;
    $cards_total += $cnt;
}

/* ---- Now build LIST where = common + optional plan filter ---- */
$where = $where_common;
$types = $types_common;
$params = $params_common;

if ($plan_id_filter > 0) {
    $where[] = "sp.id = ?";
    $types  .= "i";
    $params[] = $plan_id_filter;
}

$sql_where = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

/* sort */
switch ($sort) {
    case 'oldest':
        $order = ' ORDER BY u.id ASC';
        break;
    case 'name_asc':
        $order = " ORDER BY COALESCE(NULLIF(cp.candidate_name,''), u.mobile_no) ASC";
        break;
    case 'name_desc':
        $order = " ORDER BY COALESCE(NULLIF(cp.candidate_name,''), u.mobile_no) DESC";
        break;
    case 'city_asc':
        $order = ' ORDER BY u.city_id ASC, u.id DESC';
        break;
    case 'city_desc':
        $order = ' ORDER BY u.city_id DESC, u.id DESC';
        break;
    default:
        $order = ' ORDER BY u.id DESC';
}

/* total count */
$sql_count = "SELECT COUNT(DISTINCT u.id) AS c " . $sql_base . $sql_where;
$stmt = $con->prepare($sql_count);
if (!$stmt) {
    die('Prepare failed: ' . h($con->error));
}
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = 0;
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

/* page clamp */
if ($view !== 'all') {
    $pages = max(1, (int)ceil($total / $per_page));
    if ($page > $pages) {
        $page = $pages;
        $offset = ($page - 1) * $per_page;
    }
} else {
    $pages = 1;
    $page = 1;
    $offset = 0;
}

/* main list query */
$sql = "
SELECT
  u.id, u.mobile_no, u.profile_type_id, u.profile_id, u.city_id, u.address,
  u.latitude, u.longitude, u.fcm_token, u.referral_code, u.myreferral_code,
  u.referred_by, u.active_plan_id, u.status_id, u.created_at,

  cp.candidate_name, cp.gender_id, g.name AS gender_name,

  ls.plan_id AS last_plan_id, ls.start_date AS last_start_date, ls.end_date AS last_end_date,
  sp.id AS plan_id, sp.plan_name AS plan_name, CAST(sp.plan_access AS UNSIGNED) AS plan_access_num,

  IFNULL(rc.total_referrals,0) AS total_referrals,
  IFNULL(ac.apps_count,0)      AS apps_count,

  ur.mobile_no AS ref_mobile,
  COALESCE(
    NULLIF(rrp.organization_name,''),
    NULLIF(rrp.contact_person_name,''),
    NULLIF(rcp.candidate_name,''),
    NULLIF(rpp.name,''),
    ur.mobile_no
  ) AS ref_name
" . $sql_base . $sql_where . $order . ($view === 'all' ? "" : " LIMIT $per_page OFFSET $offset");

$stmt = $con->prepare($sql);
if (!$stmt) {
    die('Prepare failed: ' . h($con->error));
}
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = stmt_fetch_all_assoc($stmt);
$stmt->close();

/* ===================== RENDER ===================== */
ob_start();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title><?= h($page_title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/adminconsole/assets/ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        body {
            background: #020617;
        }

        .table a.ref-link {
            text-decoration: none;
            color: #3b82f6;
        }

        .table a.ref-link:hover {
            text-decoration: underline;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .table-wrap .table {
            min-width: 1100px;
        }

        .table th,
        .table td {
            padding: 6px 8px;
            vertical-align: top;
        }

        /* Cards */
        .cards-row {
            display: flex;
            gap: 12px;
            overflow: auto;
            padding: 6px 2px 10px;
        }

        .stat-card {
            display: block;
            min-width: 190px;
            padding: 14px 14px;
            border: 1px solid #233045;
            border-radius: 14px;
            background: #0b1220;
            color: #e5e7eb;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
            text-decoration: none;
        }

        .stat-card:hover {
            border-color: #3b82f6;
        }

        .stat-card.active {
            border-color: #22c55e;
            box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.25);
        }

        .stat-title {
            color: #9ca3af;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .stat-num {
            font-size: 28px;
            font-weight: 800;
            color: #fff;
            line-height: 1;
        }

        /* Hide filters */
        .filters-wrap {
            margin-top: 8px;
        }

        .filters-hidden {
            display: none;
        }
    </style>
</head>

<body>
    <div class="master-wrap">
        <div class="headbar" style="display:flex;align-items:center;gap:12px">
            <h2 style="margin:0"><?= h($page_title) ?></h2>
            <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
                <button class="btn secondary" type="button" id="toggleFiltersBtn">Hide Filters</button>
            </div>
        </div>

        <div class="card" style="padding:14px">

            <!-- PLAN CARDS -->
            <div class="cards-row">
                <a class="stat-card <?= ($plan_id_filter == 0 ? 'active' : '') ?>"
                    href="<?= h(keep_params(['plan_id' => null, 'page' => 1])) ?>">
                    <div class="stat-title">Total Records</div>
                    <div class="stat-num"><?= (int)$cards_total ?></div>
                </a>

                <?php foreach ($subscription_plan_opts as $p):
                    $pid = (int)$p['id'];
                    $cnt = (int)($plan_counts[$pid] ?? 0);
                    // If you want to hide zero-count plans, uncomment:
                    // if ($cnt === 0) continue;
                    $isActive = ($plan_id_filter === $pid);
                ?>
                    <a class="stat-card <?= $isActive ? 'active' : '' ?>"
                        href="<?= h(keep_params(['plan_id' => $pid, 'page' => 1])) ?>">
                        <div class="stat-title"><?= h($p['plan_name']) ?></div>
                        <div class="stat-num"><?= $cnt ?></div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Filters -->
            <div class="filters-wrap" id="filtersWrap">
                <form method="get" class="toolbar" style="gap:10px;flex-wrap:wrap;align-items:flex-end">
                    <!-- preserve plan filter -->
                    <input type="hidden" name="plan_id" value="<?= (int)$plan_id_filter ?>">

                    <input class="inp" type="text" name="q" value="<?= h($q) ?>" placeholder="Search name/mobile/referral..." style="min-width:240px">
                    <input class="inp" type="text" name="city_id" value="<?= h($city_id) ?>" placeholder="City ID">

                    <div style="display:flex;flex-direction:column;min-width:180px">
                        <span style="font-size:12px;color:#9ca3af;margin-bottom:2px">Registration Date From</span>
                        <input class="inp datepicker" type="text"
                            name="created_from"
                            value="<?= h($created_from_raw) ?>"
                            placeholder="DD-MM-YYYY">
                    </div>

                    <div style="display:flex;flex-direction:column;min-width:180px">
                        <span style="font-size:12px;color:#9ca3af;margin-bottom:2px">Registration Date To</span>
                        <input class="inp datepicker" type="text"
                            name="created_to"
                            value="<?= h($created_to_raw) ?>"
                            placeholder="DD-MM-YYYY">
                    </div>

                    <select class="inp" name="status_id" title="Status">
                        <option value="-1" <?= $status_id === -1 ? 'selected' : '' ?>>Status: Any</option>
                        <option value="1" <?= $status_id === 1 ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $status_id === 0 ? 'selected' : '' ?>>Inactive</option>
                    </select>

                    <select class="inp" name="has_referrer">
                        <option value="" <?= $has_referrer === -1 ? 'selected' : '' ?>>Referrer: Any</option>
                        <option value="1" <?= $has_referrer === 1 ? 'selected' : '' ?>>Has Referrer</option>
                        <option value="0" <?= $has_referrer === 0 ? 'selected' : '' ?>>No Referrer</option>
                    </select>

                    <input class="inp" type="text" name="referral_code" value="<?= h($referral_code_in) ?>" placeholder="Referral Code">

                    <select class="inp" name="plan_access" title="Plan Access">
                        <option value="0" <?= $plan_access_in === 0 ? 'selected' : '' ?>>Plan Access: Any</option>
                        <option value="1" <?= $plan_access_in === 1 ? 'selected' : '' ?>>Free</option>
                        <option value="2" <?= $plan_access_in === 2 ? 'selected' : '' ?>>Premium</option>
                    </select>

                    <select class="inp" name="subscription_status">
                        <option value="" <?= $subscription_status === '' ? 'selected' : '' ?>>Subscription: Any</option>
                        <option value="active" <?= $subscription_status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="expired" <?= $subscription_status === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>

                    <select class="inp" name="job_position_ids[]" multiple size="3" title="Preferred Job Positions" style="min-width:220px;">
                        <?php foreach ($job_positions_opts as $jp):
                            $id = (int)$jp['id'];
                            $sel = in_array($id, $preferred_job_ids, true) ? 'selected' : '';
                        ?>
                            <option value="<?= $id ?>" <?= $sel ?>><?= h($jp['name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <input class="inp" type="text" name="referrer_q" value="<?= h($referrer_q) ?>" placeholder="Search Referrer: name/mobile" style="min-width:220px">

                    <select class="inp" name="sort">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A–Z</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z–A</option>
                        <option value="city_asc" <?= $sort === 'city_asc' ? 'selected' : '' ?>>City ↑</option>
                        <option value="city_desc" <?= $sort === 'city_desc' ? 'selected' : '' ?>>City ↓</option>
                    </select>

                    <button class="btn primary" type="submit">Apply</button>

                    <div style="flex:1"></div>
                    <a class="btn secondary" href="<?= h(keep_params(['view' => 'last50', 'page' => 1])) ?>">Last 50</a>
                    <a class="btn secondary" href="<?= h(keep_params(['view' => 'all', 'page' => 1])) ?>">View All</a>
                </form>
            </div>

            <div style="display:flex;align-items:center;gap:12px;margin:10px 0 12px">
                <span class="badge">Total: <?= (int)$total ?></span>
                <span class="badge">Showing: <?= ($view === 'all') ? 'All' : (int)count($rows) ?></span>
                <?php if ($plan_id_filter > 0) { ?>
                    <span class="badge">Plan Filter: #<?= (int)$plan_id_filter ?></span>
                <?php } ?>
                <?php if ($view !== 'all') { ?>
                    <div style="margin-left:auto;display:flex;gap:6px;align-items:center">
                        <?php if ($page > 1) { ?>
                            <a class="btn secondary" href="<?= h(keep_params(['page' => $page - 1])) ?>">‹ Prev</a>
                        <?php } ?>
                        <span class="badge">Page <?= (int)$page ?> / <?= (int)$pages ?></span>
                        <?php if ($page < $pages) { ?>
                            <a class="btn secondary" href="<?= h(keep_params(['page' => $page + 1])) ?>">Next ›</a>
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
                            <th>Mobile</th>
                            <th>City</th>
                            <th>Referral Code</th>
                            <th>Referred By</th>
                            <th>Plan</th>
                            <th>Subscr.</th>
                            <th>Referral Count</th>
                            <th>Applications</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (empty($rows)) {
                            echo '<tr><td colspan="12" style="text-align:center;color:#9ca3af;padding:12px">No records found.</td></tr>';
                        } else {
                            $sr = ($view === 'all') ? 1 : ($offset + 1);
                            foreach ($rows as $row) {
                                $display = $row['candidate_name'] ?: $row['mobile_no'];
                                $profile_summary = 'Gender: ' . h($row['gender_name'] ?? '');
                                $apps_count = (int)($row['apps_count'] ?? 0);

                                if (!empty($row['plan_name'])) {
                                    $plan_label = $row['plan_name'] . ' ' . (((int)$row['plan_access_num'] == 2) ? '(Premium)' : '(Free)');
                                } elseif (!empty($row['plan_id'])) {
                                    $plan_label = 'Plan #' . (int)$row['plan_id'] . ' ' . (((int)$row['plan_access_num'] == 2) ? '(Premium)' : '(Free)');
                                } elseif (!empty($row['active_plan_id'])) {
                                    $plan_label = 'Plan #' . (int)$row['active_plan_id'];
                                } else {
                                    $plan_label = '—';
                                }

                                $sub_status = (!empty($row['last_end_date']))
                                    ? ((strtotime($row['last_end_date']) >= time()) ? '<span class="badge success">Active</span>' : '<span class="badge warn">Expired</span>')
                                    : '<span class="badge">—</span>';

                                $refByDisplay = '—';
                                $refLinkHref = null;
                                if (!empty($row['referred_by'])) {
                                    $refName = trim((string)($row['ref_name'] ?? ''));
                                    $refMobile = trim((string)($row['ref_mobile'] ?? ''));
                                    if ($refName === '' && $refMobile === '') {
                                        $refByDisplay = '#' . (int)$row['referred_by'];
                                    } elseif ($refName !== '' && $refMobile !== '') {
                                        $refByDisplay = h($refName) . ' (' . h($refMobile) . ')';
                                    } else {
                                        $refByDisplay = h($refName ?: $refMobile);
                                    }
                                    $refLinkHref = keep_params(['referrer_q' => $refMobile ?: $refName, 'page' => 1]);
                                }

                                $status_badge = ((int)$row['status_id'] === 1) ? '<span class="badge success">Active</span>' : '<span class="badge danger">Inactive</span>';

                                $viewHref  = keep_params(['mode' => 'candidate', 'userid' => (int)$row['id']]);
                                $appsHref  = keep_params(['mode' => 'cand_apps', 'userid' => (int)$row['id']]);
                                $reg_date  = fmt_reg_short($row['created_at']);
                        ?>
                                <tr>
                                    <td><?= (int)$sr++; ?></td>
                                    <td><?= h($reg_date) ?></td>
                                    <td>
                                        <div style="font-weight:600"><?= h($display) ?></div>
                                        <div style="font-size:12px;color:#9ca3af"><?= $profile_summary ?></div>
                                        <div style="margin-top:2px"><?= $status_badge ?></div>
                                    </td>
                                    <td><?= h($row['mobile_no']) ?></td>
                                    <td><?= h($row['city_id']) ?></td>
                                    <td><?= h($row['referral_code'] ?: '—') ?></td>
                                    <td>
                                        <?php if ($refLinkHref) { ?>
                                            <a class="ref-link" href="<?= h($refLinkHref) ?>"><?= $refByDisplay ?></a>
                                        <?php } else {
                                            echo $refByDisplay;
                                        } ?>
                                    </td>
                                    <td><?= h($plan_label) ?></td>
                                    <td>
                                        <?= $sub_status ?>
                                        <?php if (!empty($row['last_start_date']) || !empty($row['last_end_date'])) { ?>
                                            <div style="font-size:11px;color:#9ca3af">
                                                <?= h($row['last_start_date'] ? date('d M Y', strtotime($row['last_start_date'])) : '—') ?> →
                                                <?= h($row['last_end_date']   ? date('d M Y', strtotime($row['last_end_date']))   : '—') ?>
                                            </div>
                                        <?php } ?>
                                    </td>
                                    <td><?= (int)($row['total_referrals'] ?? 0) ?></td>
                                    <td>
                                        <?php if ($apps_count > 0) { ?>
                                            <span class="badge success"><?= (int)$apps_count ?></span>
                                        <?php } else { ?>
                                            <span class="badge">0</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;flex-direction:column;gap:4px">
                                            <a class="btn secondary" href="<?= h($viewHref) ?>">View Details</a>
                                            <?php if ($apps_count > 0) { ?>
                                                <a class="btn secondary" href="<?= h($appsHref) ?>">View Applications</a>
                                            <?php } else { ?>
                                                <span style="font-size:11px;color:#9ca3af;">No apps</span>
                                            <?php } ?>
                                        </div>
                                    </td>
                                </tr>
                        <?php
                            } // foreach
                        } // else
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
            const btn = document.getElementById('toggleFiltersBtn');
            const wrap = document.getElementById('filtersWrap');
            let hidden = false;

            // restore state (optional)
            try {
                hidden = (localStorage.getItem('cand_filters_hidden') === '1');
            } catch (e) {}

            function apply() {
                if (hidden) {
                    wrap.classList.add('filters-hidden');
                    btn.textContent = 'Show Filters';
                } else {
                    wrap.classList.remove('filters-hidden');
                    btn.textContent = 'Hide Filters';
                }
                try {
                    localStorage.setItem('cand_filters_hidden', hidden ? '1' : '0');
                } catch (e) {}
            }

            btn.addEventListener('click', function() {
                hidden = !hidden;
                apply();
            });

            apply();
        })();


        document.addEventListener("DOMContentLoaded", function() {
            flatpickr(".datepicker", {
                altInput: true, // user sees formatted date
                altFormat: "d-m-Y", // display format (DD-MM-YYYY)
                dateFormat: "Y-m-d", // value sent in GET
                allowInput: false
            });
        });
    </script>


</body>


</html>
<?php
echo ob_get_clean();
