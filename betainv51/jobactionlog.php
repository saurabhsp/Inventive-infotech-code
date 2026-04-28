<?php
/* ======================================================================
   Applications – Date-wise (Premium + Standard) + Job View + Jobseeker View
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


/* =========================
   PROFILE VIEW (same page)
   ========================= */

if (isset($_GET['user'])) {

    $userid = (int)$_GET['user'];

    /* STEP 1: get profile type */
    $sql = "SELECT profile_type_id 
            FROM jos_app_users 
            WHERE id = ? 
            LIMIT 1";

    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();



    if (!$user) {
        echo "<div class='card'>User not found</div>";
        exit;
    }

    $type = (int)$user['profile_type_id'];

    /* STEP 2: fetch profile based on type */

    /* STEP 2: fetch full profile */

    if ($type == 2) {
        // ✅ Candidate
        $sql = "SELECT 
            cp.candidate_name AS name,
            cp.email,
            cp.mobile_no,
            cp.address,
            cp.locality_id,
            cp.latitude,
            cp.longitude,

            g.name AS gender_name,

            cp.birthdate,
            ce.name AS experience_type,
            cp.experience_period,
            cp.city_id AS city_name,
            cp.skills,
            cp.profile_photo,
            cp.created_at,
            u.referral_code

        FROM jos_app_candidate_profile cp

        LEFT JOIN jos_crm_gender g 
            ON g.id = cp.gender_id

        LEFT JOIN jos_crm_experience ce 
        ON ce.id = cp.experience_type

        LEFT JOIN jos_app_users u
            ON u.id = cp.userid

        WHERE cp.userid = ?";
    } elseif ($type == 1) {
        // ✅ Recruiter
        $sql = "SELECT 
            rp.organization_name AS name,
            rp.email,
            rp.mobile_no,
            rp.address,
            rp.contact_person_name,
            rp.designation,
            rp.industry_type,
            rp.company_size,
            rp.established_year,
            rp.company_logo AS profile_photo,
            rp.created_at

        FROM jos_app_recruiter_profile rp
        WHERE rp.userid = ?";
    } elseif ($type == 3) {
        // ✅ Promoter
        $sql = "SELECT 
            pp.name,
            pp.mobile_no,
            pp.address,

            g.name AS gender_name,   -- ✅ proper gender

            pp.city_id,
            pp.profile_photo,
            pp.created_at

        FROM jos_app_promoter_profile pp

        LEFT JOIN jos_crm_gender g 
            ON g.id = pp.gender_id

        WHERE pp.userid = ?";
    } else {
        echo "<div class='card'>Unknown profile type</div>";
        exit;
    }

    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $res = $stmt->get_result();
    $profile = $res->fetch_assoc();

    if (!$profile) {
        echo "<div class='card'>Profile not found</div>";
        exit;
    }

    $type_label = 'User';

    if ($type == 1) $type_label = 'Employer';
    elseif ($type == 2) $type_label = 'Jobseeker';
    elseif ($type == 3) $type_label = 'Promoter';
?>
    <link rel="stylesheet" href="/adminconsole/assets/ui.css">
    <style>
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px 40px;
        }

        .profile-item {
            display: flex;
            flex-direction: column;
        }

        .profile-item .lbl {
            color: #94a3b8;
            font-size: 13px;
        }

        .profile-item .val {
            color: #fff;
            font-weight: 500;
        }
    </style>
    <div class="master-wrap">

        <div class="headbar" style="display:flex;align-items:center">
            <h2><?= htmlspecialchars($profile['name']) ?> (<?= $type_label ?>)</h2>

            <!-- <div style="margin-left:auto">
                <a class="btn secondary" href="job_action_log.php">← Back</a>
            </div> -->
        </div>

        <div class="card" style="padding:20px">

            <!-- TOP INFO -->
            <div style="display:flex;gap:15px;align-items:center">

                <img src="<?= htmlspecialchars($profile['profile_photo'] ?? 'adminconsole/assets/no-image.png') ?>"
                    style="width:70px;height:70px;border-radius:50%;object-fit:cover">

                <div>
                    <div style="font-size:18px;font-weight:700;color:#fff">
                        <?= htmlspecialchars($profile['name']) ?>
                    </div>

                    <div style="color:#9ca3af">
                        <?= htmlspecialchars($profile['email'] ?? '-') ?> •
                        <?= htmlspecialchars($profile['mobile_no'] ?? '-') ?>
                    </div>
                </div>
            </div>

            <div style="height:1px;background:#1f2937;margin:15px 0"></div>

            <!-- DETAILS GRID -->
            <div class="grid">

                <?php if ($type == 1): ?>

                    <div class="profile-grid">

                        <div class="profile-item">
                            <div class="lbl">Company</div>
                            <div class="val"><?= $profile['name'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Contact Person</div>
                            <div class="val"><?= $profile['contact_person_name'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Designation</div>
                            <div class="val"><?= $profile['designation'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Mobile</div>
                            <div class="val"><?= $profile['mobile_no'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Email</div>
                            <div class="val"><?= $profile['email'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Industry</div>
                            <div class="val"><?= $profile['industry_type'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Company Size</div>
                            <div class="val"><?= $profile['company_size'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Established</div>
                            <div class="val"><?= $profile['established_year'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Address</div>
                            <div class="val"><?= $profile['address'] ?? '-' ?></div>
                        </div>

                    </div>

                <?php endif; ?>

                <!-- DETAILS -->
                <?php if ($type == 2): ?>

                    <div class="profile-grid">

                        <div class="profile-item">
                            <div class="lbl">Gender</div>
                            <div class="val"><?= $profile['gender_name'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Birthdate</div>
                            <div class="val"><?= date('d M Y', strtotime($profile['birthdate'])) ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Experience</div>
                            <div class="val"><?= $profile['experience_type'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Experience Period</div>
                            <div class="val">
                                <?= !empty($profile['experience_period']) ? $profile['experience_period'] . ' Years' : '-' ?>
                            </div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">City</div>
                            <div class="val"><?= $profile['city_name'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Address</div>
                            <div class="val"><?= $profile['address'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Locality</div>
                            <div class="val"><?= $profile['locality_id'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Latitude</div>
                            <div class="val"><?= $profile['latitude'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Longitude</div>
                            <div class="val"><?= $profile['longitude'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Created</div>
                            <div class="val"><?= date('d M Y', strtotime($profile['created_at'])) ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Referral Code</div>
                            <div class="val"><?= $profile['referral_code'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Preferred Jobs</div>
                            <div class="val"><?= $profile['skills'] ?? '-' ?></div>
                        </div>

                    </div>

                <?php endif; ?>
                <?php if ($type == 3): ?>

                    <div class="profile-grid">

                        <div class="profile-item">
                            <div class="lbl">Name</div>
                            <div class="val"><?= $profile['name'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Mobile</div>
                            <div class="val"><?= $profile['mobile_no'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Gender</div>
                            <div class="val"><?= $profile['gender_name'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">City</div>
                            <div class="val"><?= $profile['city_id'] ?? '-' ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="lbl">Address</div>
                            <div class="val"><?= $profile['address'] ?? '-' ?></div>
                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </div>
    </div>

<?php
    exit;
}


/* Visits case */


$job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
$action_type = isset($_POST['action_type']) ? (int)$_POST['action_type'] : 0;

/* IMPORTANT: force listing type if premium */
$listing_type = isset($_POST['job_listing_type']) ? (int)$_POST['job_listing_type'] : 2;

/* Base WHERE */
$where = "WHERE al.job_id = ? AND al.job_listing_type = ?";
$params = [$job_id, $listing_type];
$types = "ii";

/* Filter by action if clicked */
if ($action_type > 0) {
    $where .= " AND al.action_type = ?";
    $params[] = $action_type;
    $types .= "i";
}

/* FINAL QUERY */
/* =========================
   VISITS (separate table)
   ========================= */
if ($action_type == 4) {

    $sql = "SELECT 
                v.id,
                v.visit_datetime AS datetime,
                COALESCE(cp.candidate_name, 'Unknown') AS candidate_name,
                COALESCE(j.name, 'Unknown Job') AS job_position,
                'Visit' AS action_type,
                CASE 
                    WHEN v.job_listing_type = 1 THEN 'Standard Job'
                    WHEN v.job_listing_type = 2 THEN 'Premium Job'
                    ELSE 'Other'
                END AS listing,
                v.userid,
                v.job_id
            FROM jos_app_jobvisitlog v
            LEFT JOIN jos_app_candidate_profile cp ON cp.userid = v.userid
            LEFT JOIN jos_crm_jobpost j ON j.id = v.job_id
            WHERE v.job_id = ? AND v.job_listing_type = ?
            ORDER BY v.visit_datetime DESC";

    $stmt = $con->prepare($sql);

    if (!$stmt) {
        die("SQL Error (Visit): " . $con->error);
    }

    $stmt->bind_param("ii", $job_id, $listing_type);
}

/* =========================
   OTHER ACTIONS (call/chat/location)
   ========================= */ else {

    $where = "WHERE al.job_id = ? AND al.job_listing_type = ?";
    $params = [$job_id, $listing_type];
    $types = "ii";

    if ($action_type > 0) {
        $where .= " AND al.action_type = ?";
        $params[] = $action_type;
        $types .= "i";
    }

    $sql = "SELECT 
                al.id,
                al.date AS datetime,
                COALESCE(cp.candidate_name, 'Unknown') AS candidate_name,
                COALESCE(j.name, 'Unknown Job') AS job_position,

                CASE 
                    WHEN al.action_type = 1 THEN 'Call'
                    WHEN al.action_type = 2 THEN 'Chat'
                    WHEN al.action_type = 3 THEN 'Location'
                    ELSE CONCAT('Type-', al.action_type)
                END AS action_type,

                CASE 
                    WHEN al.job_listing_type = 1 THEN 'Standard Job'
                    WHEN al.job_listing_type = 2 THEN 'Premium Job'
                    ELSE CONCAT('Type-', al.job_listing_type)
                END AS listing,

                al.userid,
                al.job_id

            FROM jos_app_jobaction_logs al
            LEFT JOIN jos_app_candidate_profile cp ON cp.userid = al.userid
            LEFT JOIN jos_crm_jobpost j ON j.id = al.job_id
            $where
            ORDER BY al.date DESC";

    $stmt = $con->prepare($sql);

    if (!$stmt) {
        die("SQL Error (Action): " . $con->error);
    }

    $stmt->bind_param($types, ...$params);
}

/* EXECUTE (common) */
$stmt->execute();
$res = $stmt->get_result();



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
    <?php
    $action_label = 'All';

    if ($action_type == 1) $action_label = 'Call';
    elseif ($action_type == 2) $action_label = 'Chat';
    elseif ($action_type == 3) $action_label = 'Location';
    elseif ($action_type == 4) $action_label = 'Visit';
    ?>
    <div class="master-wrap">
        <div class="headbar" style="display:flex;align-items:center;gap:12px">
            <h2>Action Logs (<?= htmlspecialchars($action_label) ?>)</h2>
            <!-- <div style="margin-left:auto;display:flex;gap:8px">
                <a class="btn secondary" href="<?= base_back_to_list() ?>">← Back to List</a>
                <button class="btn secondary" onclick="window.print()">Print</button>
            </div> -->
        </div>

        <div class="card" style="padding:20px">


            <?php if ($res && $res->num_rows > 0): ?>
                <!-- <div style="font-size:18px;font-weight:700;color:#fff;margin-bottom:10px">
                    Job Actions (<?= htmlspecialchars($action_label) ?>)
                </div> -->

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Sr</th>
                                <th>Datetime</th>
                                <th>Candidate Name</th>
                                <th>Action Type</th>
                                <th>Listing</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php
                            $sr = 1;
                            while ($row = $res->fetch_assoc()):
                            ?>
                                <tr>

                                    <td><?= $sr++ ?></td>
                                    <td><?= htmlspecialchars($row['datetime']) ?></td>
                                    <td><?= htmlspecialchars($row['candidate_name']) ?></td>
                                    <td><?= htmlspecialchars($row['action_type']) ?></td>
                                    <td><?= htmlspecialchars($row['listing']) ?></td>


                                    <td>

                                        <a class="btn secondary"
                                            href="job_action_log.php?user=<?= (int)$row['userid'] ?>"
                                            target="_blank">
                                            View Profile
                                        </a>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <div style="height:1px;background:#1f2937;margin:20px 0"></div>

                <div class="badge">
                    No Actions Performed.
                </div>

            <?php endif; ?>
        </div>
    </div>
</body>

</html>