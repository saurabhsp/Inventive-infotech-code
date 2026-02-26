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
$script_path = $_SERVER['PHP_SELF'];            // e.g. /adminconsole/operations/users.php
$script_basename = basename($script_path);      // e.g. users.php

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

/* -------- page config -------- */
$page_title = 'Assign Account Operator';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
} // ensure session for flash
ob_start();
?>
<link rel="stylesheet" href="/adminconsole/assets/ui.css">
<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<style>
    .table a.ref-link {
        text-decoration: none;
        color: #3b82f6;
    }

    .table a.ref-link:hover {
        text-decoration: underline;
    }

    /* Simple modal (confirm) */
    #confirmModal {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.4);
        z-index: 9999;
    }

    #confirmModal .box {
        background: #fff;
        border-radius: 12px;
        padding: 16px;
        width: 360px;
        max-width: 90%;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }

    #confirmModal h3 {
        margin: 0 0 8px;
    }

    #confirmModal p {
        margin: 6px 0 12px;
        color: #374151;
    }

    #confirmModal .row {
        display: flex;
        gap: 8px;
        margin-top: 12px;
        justify-content: flex-end;
    }

    /* Info modal (for delete-blocked) */
    #infoModal {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.45);
        z-index: 10000;
    }

    #infoModal .box {
        background: #fff;
        border-radius: 12px;
        padding: 18px;
        width: 560px;
        max-width: 92%;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
    }

    #infoModal h3 {
        margin: 0 0 8px;
    }

    #infoModal .content {
        max-height: 55vh;
        overflow: auto;
        color: #374151;
    }

    #infoModal .row {
        display: flex;
        gap: 8px;
        margin-top: 12px;
        justify-content: flex-end;
    }

    /* ---- keep users table inside same width as top card ---- */
    body {
        overflow-x: hidden;
        /* safety: page itself never scrolls sideways */
    }

    /* wrapper around the table */
    .master-wrap .card .table-wrap {
        width: 100%;
        overflow-x: hidden;
        /* no inner horizontal scroll either */
    }

    /* force table to respect card width (override global .table rules) */
    .master-wrap .card .table-wrap .table {
        width: 100%;
        max-width: 100%;
        min-width: 0 !important;
        /* important: cancels any min-width in ui.css */
        table-layout: fixed;
        /* columns share the available width */
    }

    /* allow long text (codes, names, plans) to wrap instead of stretching table */
    .master-wrap .card .table-wrap .table th,
    .master-wrap .card .table-wrap .table td {
        word-wrap: break-word;
        word-break: break-word;
    }
</style>

<script>
    /* Clipboard helper */
    function copyToClipboard(text) {
        try {
            navigator.clipboard.writeText(text).then(
                () => alert("Copied: " + text),
                () => window.prompt("Press Ctrl/Cmd+C then Enter", text)
            );
        } catch (e) {
            window.prompt("Press Ctrl/Cmd+C then Enter", text);
        }
    }

    /* Modal-driven delete confirmation with text input */
    let pendingDeleteFormId = null;

    function openDeleteModal(formId, noteText) {
        pendingDeleteFormId = formId;
        document.getElementById('confirmNote').textContent = noteText;
        document.getElementById('confirmInput').value = '';
        document.getElementById('confirmModal').style.display = 'flex';
        setTimeout(() => document.getElementById('confirmInput').focus(), 10);
    }

    function closeDeleteModal() {
        pendingDeleteFormId = null;
        document.getElementById('confirmModal').style.display = 'none';
    }

    function submitDeleteIfConfirmed() {
        const val = document.getElementById('confirmInput').value.trim();
        if (val !== 'DELETE') {
            alert('You must type DELETE to confirm.');
            return false;
        }
        if (!pendingDeleteFormId) return false;
        const form = document.getElementById(pendingDeleteFormId);
        if (!form) return false;
        const hidden = form.querySelector('input[name="confirm_text"]');
        if (hidden) hidden.value = val;
        closeDeleteModal();
        form.submit();
        return true;
    }

    /* Info modal (server-pushed) */
    function openInfoModal(html) {
        const box = document.getElementById('infoModalBox');
        const body = document.getElementById('infoModalBody');
        if (!box || !body) return;
        body.innerHTML = html;
        document.getElementById('infoModal').style.display = 'flex';
    }

    function closeInfoModal() {
        document.getElementById('infoModal').style.display = 'none';
    }
</script>

<!-- Confirm Modal -->


<!-- Info Modal (Delete Blocked / Any server message) -->


<div class="master-wrap">
    <div class="headbar">
        <h2 style="margin:0"><?= htmlspecialchars($page_title) ?></h2>
    </div>
    <div class="card">
        <?php
        global $con;
        if (!$con) {
            echo '<div class="alert danger">DB connection not initialized.</div>';
            echo ob_get_clean();
            exit;
        }

        /* ---- AC Managers list (dropdown) ---- */
        $acManagers = [];
        $qam = mysqli_query($con, "SELECT id, name, email, status FROM jos_admin_users WHERE status=1 ORDER BY name ASC");
        if ($qam) {
            while ($a = mysqli_fetch_assoc($qam)) {
                $acManagers[] = $a;
            }
        }


        /* ---- helpers ---- */
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

        /* CSRF init */
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }

        /* Tiny helper for COUNT(*) existence checks */
        function table_has($con, $sql, $types, ...$params)
        {
            $stmt = $con->prepare($sql);
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $cnt = 0;
            $stmt->bind_result($cnt);
            $stmt->fetch();
            $stmt->close();
            return (int)$cnt;
        }

        function assign_ac_manager(mysqli $con, int $user_id, ?int $new_mgr_id, int $changed_by, string $remark = ''): array
        {
            mysqli_begin_transaction($con);
            try {
                // lock user row
                $stmt = $con->prepare("SELECT ac_manager_id FROM jos_app_users WHERE id=? FOR UPDATE");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $stmt->close();

                if (!$row) throw new Exception("User not found");

                $old = ($row['ac_manager_id'] !== null) ? (int)$row['ac_manager_id'] : null;

                // no change -> skip
                if ($old === $new_mgr_id) {
                    mysqli_commit($con);
                    return ['ok' => true, 'msg' => 'No change'];
                }

                // update current assignment
                if ($new_mgr_id === null) {
                    $stmt2 = $con->prepare("UPDATE jos_app_users
                              SET ac_manager_id=NULL, ac_manager_assigned_at=NOW(), ac_manager_assigned_by=?
                              WHERE id=?");
                    $stmt2->bind_param("ii", $changed_by, $user_id);
                } else {
                    $stmt2 = $con->prepare("UPDATE jos_app_users
                              SET ac_manager_id=?, ac_manager_assigned_at=NOW(), ac_manager_assigned_by=?
                              WHERE id=?");
                    $stmt2->bind_param("iii", $new_mgr_id, $changed_by, $user_id);
                }
                $stmt2->execute();
                $stmt2->close();

                // log
                $stmt3 = $con->prepare("INSERT INTO jos_app_user_ac_manager_log
        (user_id, old_ac_manager_id, new_ac_manager_id, changed_by, remark)
        VALUES (?,?,?,?,?)");
                $old_i = $old;
                $new_i = $new_mgr_id;
                $stmt3->bind_param("iiiis", $user_id, $old_i, $new_i, $changed_by, $remark);
                $stmt3->execute();
                $stmt3->close();

                mysqli_commit($con);
                return ['ok' => true, 'msg' => 'Assigned'];
            } catch (Throwable $e) {
                mysqli_rollback($con);
                return ['ok' => false, 'msg' => $e->getMessage()];
            }
        }


        /* Flash helper for modal messages */
        function flash_modal_and_redirect($html)
        {
            $_SESSION['modal_html'] = $html;
            $to = $_SERVER['PHP_SELF'] . (strpos($_SERVER['PHP_SELF'], '?') === false ? '?' : '&') . 'blocked=1';
            header('Location: ' . $to);
            exit;
        }


        /* ---- AC Manager assignment (single + bulk) ---- */
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_ac_manager') {

            $csrf  = $_POST['csrf'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
                flash_modal_and_redirect('<div class="alert danger">Invalid CSRF token.</div>');
            }

            $me = $_SESSION['admin_user'] ?? [];
            $changed_by = (int)($me['id'] ?? 0);  // ensure your session has id
            if ($changed_by <= 0) {
                flash_modal_and_redirect('<div class="alert danger">Admin session missing (id).</div>');
            }

            $mgr_id = (int)($_POST['ac_manager_id'] ?? 0);
            $new_mgr_id = ($mgr_id > 0) ? $mgr_id : null; // 0 => Unassign
            $remark = trim((string)($_POST['remark'] ?? ''));

            // SINGLE assign
            if (!empty($_POST['user_id'])) {
                $user_id = (int)$_POST['user_id'];
                $r = assign_ac_manager($con, $user_id, $new_mgr_id, $changed_by, $remark);
                if ($r['ok']) {
                    header("Location: " . $_SERVER['PHP_SELF'] . keep_params(['ok' => 'AC+Manager+Assigned']));
                    exit;
                }
                flash_modal_and_redirect('<div class="alert danger">Assign failed: ' . h($r['msg']) . '</div>');
            }

            // BULK assign
            $selected = $_POST['selected_users'] ?? [];
            if (!is_array($selected) || count($selected) === 0) {
                flash_modal_and_redirect('<div class="alert danger">No users selected for bulk assignment.</div>');
            }

            $okCount = 0;
            $fail = [];
            foreach ($selected as $sid) {
                $uid = (int)$sid;
                if ($uid <= 0) continue;
                $r = assign_ac_manager($con, $uid, $new_mgr_id, $changed_by, $remark);
                if ($r['ok']) $okCount++;
                else $fail[] = "User#$uid: " . $r['msg'];
            }

            if (!empty($fail)) {
                $html = '<div class="alert warn">Bulk done. Success: <strong>' . $okCount . '</strong>, Failed: <strong>' . count($fail) . '</strong></div>';
                $html .= '<div style="margin-top:8px"><ul style="margin:0;padding-left:18px">';
                foreach ($fail as $f) {
                    $html .= '<li>' . h($f) . '</li>';
                }
                $html .= '</ul></div>';
                flash_modal_and_redirect($html);
            }

            header("Location: " . $_SERVER['PHP_SELF'] . keep_params(['ok' => "Bulk+Assigned+$okCount"]));

            exit;
        }


        /* ---- secure DELETE handling with dependency checks ---- */
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
            $uid   = (int)$_POST['delete_user_id'];
            $csrf  = $_POST['csrf'] ?? '';
            $typed = trim($_POST['confirm_text'] ?? '');

            // Role restriction
            $me = $_SESSION['admin_user'] ?? [];
            $myrole = strtolower($me['role'] ?? '');
            $ALLOW_DELETE = ['admin', 'superadmin', 'owner'];
            if (!in_array($myrole, $ALLOW_DELETE, true)) {
                flash_modal_and_redirect('<div class="alert danger" style="margin-bottom:8px">Permission denied.</div>');
            }

            // CSRF check
            if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
                flash_modal_and_redirect('<div class="alert danger" style="margin-bottom:8px">Invalid CSRF token.</div>');
            }

            // Require DELETE typed (filled by modal)
            if ($typed !== 'DELETE') {
                flash_modal_and_redirect('<div class="alert danger" style="margin-bottom:8px">You must type <strong>DELETE</strong> to confirm.</div>');
            }

            // Fetch profile + referral info first
            $stmt = $con->prepare("SELECT profile_type_id, profile_id, myreferral_code FROM jos_app_users WHERE id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $info = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$info) {
                flash_modal_and_redirect('<div class="alert danger" style="margin-bottom:8px">User not found.</div>');
            }

            $ptype = (int)$info['profile_type_id'];
            $pid   = (int)$info['profile_id'];
            $mycode = trim((string)$info['myreferral_code']);

            /* ---- dependency checks (block delete if any hit) ---- */
            $reasons = [];

            // 0) Referral dependencies for ALL profile types:
            if ($mycode !== '') {
                $cnt_refdeps = table_has(
                    $con,
                    "SELECT COUNT(*) FROM jos_app_users WHERE referral_code=? AND id<>?",
                    "si",
                    $mycode,
                    $uid
                );
                if ($cnt_refdeps > 0) {
                    $reasons[] = "This user's referral code (<strong>" . h($mycode) . "</strong>) is used by <strong>$cnt_refdeps</strong> other user(s).";
                }
            }

            // 1) Recruiter (type 1) dependencies:
            if ($ptype === 1) {
                $cnt_walkins = table_has(
                    $con,
                    "SELECT COUNT(*) FROM jos_app_walkininterviews WHERE recruiter_id=?",
                    "i",
                    $pid
                );
                if ($cnt_walkins > 0) {
                    $reasons[] = "Recruiter has <strong>$cnt_walkins</strong> walk-in interview record(s).";
                }
                $cnt_jobs = table_has(
                    $con,
                    "SELECT COUNT(*) FROM jos_app_jobvacancies WHERE recruiter_id=?",
                    "i",
                    $pid
                );
                if ($cnt_jobs > 0) {
                    $reasons[] = "Recruiter has <strong>$cnt_jobs</strong> job vacancy record(s).";
                }
            }

            // 2) Candidate (type 2) dependencies:
            if ($ptype === 2) {
                $cnt_apps = table_has(
                    $con,
                    "SELECT COUNT(*) FROM jos_app_applications WHERE userid=?",
                    "i",
                    $uid
                );
                if ($cnt_apps > 0) {
                    $reasons[] = "Candidate has <strong>$cnt_apps</strong> application record(s).";
                }
            }

            // 3) Promoter (type 3): referral handled above

            if (!empty($reasons)) {
                // Build HTML once and push to modal via flash + redirect (PRG)
                ob_start();
                echo '<div style="font-weight:600;margin-bottom:6px">Delete blocked — dependencies found:</div>';
                echo '<ul style="margin:0;padding-left:18px">';
                foreach ($reasons as $r) {
                    echo '<li>' . $r . '</li>';
                }
                echo '</ul>';
                echo '<div style="margin-top:10px;color:#6b7280">Resolve or remove these dependencies first. ';
                echo 'Ensure the user is logged out from the app before retrying delete.</div>';
                $html = ob_get_clean();
                flash_modal_and_redirect($html);
            } else {
                // No dependencies → safe to delete
                $con->begin_transaction();
                try {
                    if ($ptype === 1) {
                        $con->query("DELETE FROM jos_app_recruiter_profile WHERE id=$pid");
                    } elseif ($ptype === 2) {
                        $con->query("DELETE FROM jos_app_candidate_profile WHERE id=$pid");
                    } elseif ($ptype === 3) {
                        $con->query("DELETE FROM jos_app_promoter_profile WHERE id=$pid");
                    }
                    $con->query("DELETE FROM jos_app_users WHERE id=$uid");
                    $con->commit();
                    header("Location: " . $_SERVER['PHP_SELF'] . "?ok=User+Deleted");
                    exit;
                } catch (Exception $e) {
                    $con->rollback();
                    $err = '<div class="alert danger">Delete failed: ' . h($e->getMessage()) . '</div>';
                    flash_modal_and_redirect($err);
                }
            }
        }

        /* ---- filters (GET) ---- */
        $profile_type_id     = get_int('profile_type_id', 0); // 1,2,3 or 0=all
        $q                   = get_str('q', '');               // main search
        $referrer_q          = get_str('referrer_q', '');      // referrer name/mobile
        $referrer_id         = get_int('referrer_id', 0);     // exact referrer id
        $city_id             = get_str('city_id', '');
        $status_id           = isset($_GET['status_id']) ? (int)$_GET['status_id'] : 1; // default Active
        $has_referrer        = (isset($_GET['has_referrer']) && $_GET['has_referrer'] !== '') ? (int)$_GET['has_referrer'] : -1;
        $has_fcm             = (isset($_GET['has_fcm']) && $_GET['has_fcm'] !== '') ? (int)$_GET['has_fcm'] : -1;
        $has_location        = (isset($_GET['has_location']) && $_GET['has_location'] !== '') ? (int)$_GET['has_location'] : -1;
        $referral_code_in    = get_str('referral_code', '');
        $myreferral_code_in  = get_str('myreferral_code', '');
        $active_plan_id_in   = get_int('active_plan_id', 0);
        $plan_access_in      = get_int('plan_access', 0); // 1 free, 2 premium
        $subscription_status = strtolower(get_str('subscription_status', '')); // active|expired|''
        $created_from        = get_str('created_from', ''); // YYYY-MM-DD
        $created_to          = get_str('created_to', '');
        $ac_manager_filter = isset($_GET['ac_manager_filter']) ? (int)$_GET['ac_manager_filter'] : -1; // -1 any, 0 unassigned, >0 manager id
        $sort = get_str('sort', 'newest'); // newest, oldest, name_asc, name_desc, city_asc, city_desc
        $view = get_str('view', 'last50'); // last50|all
        $page = max(1, get_int('page', 1));
        $per_page = ($view === 'all') ? 1000 : 50;
        $offset = ($page - 1) * $per_page;



        /* ---- build SQL ---- */
        $sql_base = "
  FROM jos_app_users u

  LEFT JOIN jos_app_recruiter_profile rp ON (u.profile_type_id=1 AND rp.id=u.profile_id)
  LEFT JOIN jos_app_candidate_profile  cp ON (u.profile_type_id=2 AND cp.id=u.profile_id)
  LEFT JOIN jos_crm_gender g ON g.id = cp.gender_id                 /* gender join */
  LEFT JOIN jos_app_promoter_profile   pp ON (u.profile_type_id=3 AND pp.id=u.profile_id)
  LEFT JOIN jos_admin_users am ON am.id = u.ac_manager_id

  /* --- REFERRER joins --- */
  LEFT JOIN jos_app_users ur ON ur.id = u.referred_by
  LEFT JOIN jos_app_recruiter_profile rrp ON (ur.profile_type_id=1 AND rrp.id=ur.profile_id)
  LEFT JOIN jos_app_candidate_profile  rcp ON (ur.profile_type_id=2 AND rcp.id=ur.profile_id)
  LEFT JOIN jos_app_promoter_profile   rpp ON (ur.profile_type_id=3 AND rpp.id=ur.profile_id)

LEFT JOIN (
  SELECT x.userid, x.plan_id, x.start_date, x.end_date
  FROM jos_app_usersubscriptionlog x
  INNER JOIN (
    SELECT userid, MAX(id) AS max_id
    FROM jos_app_usersubscriptionlog
    WHERE payment_id <> 'free_signup'
    GROUP BY userid
  ) m ON m.userid = x.userid AND m.max_id = x.id
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


        if ($profile_type_id > 0) {
            $where[] = "u.profile_type_id=?";
            $types .= 'i';
            $params[] = $profile_type_id;
        }
        if ($q !== '') {
            $where[] = "(u.mobile_no LIKE CONCAT('%',?,'%')
          OR u.referral_code LIKE CONCAT('%',?,'%')
          OR u.myreferral_code LIKE CONCAT('%',?,'%')
          OR rp.organization_name LIKE CONCAT('%',?,'%')
          OR rp.contact_person_name LIKE CONCAT('%',?,'%')
          OR cp.candidate_name LIKE CONCAT('%',?,'%')
          OR pp.name LIKE CONCAT('%',?,'%'))";
            $types .= 'sssssss';
            $params = array_merge($params, array_fill(0, 7, $q));
        }
        if ($referrer_q !== '') {
            $where[] = "(ur.mobile_no LIKE CONCAT('%',?,'%')
          OR rrp.organization_name LIKE CONCAT('%',?,'%')
          OR rrp.contact_person_name LIKE CONCAT('%',?,'%')
          OR rcp.candidate_name LIKE CONCAT('%',?,'%')
          OR rpp.name LIKE CONCAT('%',?,'%'))";
            $types .= 'sssss';
            $params = array_merge($params, array_fill(0, 5, $referrer_q));
        }
        if ($referrer_id > 0) {
            $where[] = "u.referred_by=?";
            $types .= 'i';
            $params[] = $referrer_id;
        }

        if ($city_id !== '') {
            $where[] = "u.city_id=?";
            $types .= 's';
            $params[] = $city_id;
        }
        if ($status_id >= 0) {
            $where[] = "u.status_id=?";
            $types .= 'i';
            $params[] = $status_id;
        }

        if ($has_referrer === 1) {
            $where[] = "u.referred_by IS NOT NULL AND u.referred_by<>0";
        } elseif ($has_referrer === 0) {
            $where[] = "(u.referred_by IS NULL OR u.referred_by=0)";
        }

        if ($has_fcm === 1) {
            $where[] = "u.fcm_token IS NOT NULL AND u.fcm_token<>''";
        } elseif ($has_fcm === 0) {
            $where[] = "(u.fcm_token IS NULL OR u.fcm_token='')";
        }

        if ($has_location === 1) {
            $where[] = "(u.latitude<>'' AND u.longitude<>'')";
        } elseif ($has_location === 0) {
            $where[] = "(u.latitude='' OR u.longitude='')";
        }

        if ($referral_code_in !== '') {
            $where[] = "u.referral_code=?";
            $types .= 's';
            $params[] = $referral_code_in;
        }
        if ($myreferral_code_in !== '') {
            $where[] = "u.myreferral_code=?";
            $types .= 's';
            $params[] = $myreferral_code_in;
        }

        if ($active_plan_id_in > 0) {
            $where[] = "u.active_plan_id=?";
            $types .= 'i';
            $params[] = $active_plan_id_in;
        }
        if ($plan_access_in > 0) {
            $where[] = "CAST(sp.plan_access AS UNSIGNED)=?";
            $types .= 'i';
            $params[] = $plan_access_in;
        }

        if ($subscription_status === 'active') {
            $where[] = "(ls.end_date IS NOT NULL AND ls.end_date>=NOW())";
        } elseif ($subscription_status === 'expired') {
            $where[] = "(ls.end_date IS NOT NULL AND ls.end_date<NOW())";
        }

        if ($created_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $created_from)) {
            $where[] = "DATE(u.created_at)>=?";
            $types .= 's';
            $params[] = $created_from;
        }
        if ($created_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $created_to)) {
            $where[] = "DATE(u.created_at)<=?";
            $types .= 's';
            $params[] = $created_to;
        }
        if ($ac_manager_filter === 0) {
            $where[] = "u.ac_manager_id IS NULL";
        } elseif ($ac_manager_filter > 0) {
            $where[] = "u.ac_manager_id = ?";
            $types .= "i";
            $params[] = $ac_manager_filter;
        }

        $sql_where = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        /* sort */
        switch ($sort) {
            case 'oldest':
                $order = ' ORDER BY u.id ASC';
                break;
            case 'name_asc':
                $order = " ORDER BY COALESCE(NULLIF(rp.organization_name,''), NULLIF(rp.contact_person_name,''), NULLIF(cp.candidate_name,''), NULLIF(pp.name,''), u.mobile_no) ASC";
                break;
            case 'name_desc':
                $order = " ORDER BY COALESCE(NULLIF(rp.organization_name,''), NULLIF(rp.contact_person_name,''), NULLIF(cp.candidate_name,''), NULLIF(pp.name,''), u.mobile_no) DESC";
                break;
            case 'city_asc':
                $order = ' ORDER BY u.city_id ASC, u.id DESC';
                break;
            case 'city_desc':
                $order = ' ORDER BY u.city_id DESC, u.id DESC';
                break;
            default:
                $order = ' ORDER BY u.id DESC'; // newest
        }

        /* total count */
        $sql_count = "SELECT COUNT(*) AS c " . $sql_base . $sql_where;
        $stmt = $con->prepare($sql_count);
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

        /* main query — include plan_name/plan_access + REFERRER FIELDS + gender name */
        $sql = "
SELECT
  u.id, u.mobile_no, u.profile_type_id, u.profile_id, u.city_id, u.address,
  u.latitude, u.longitude, u.fcm_token, u.referral_code, u.myreferral_code,
  u.referred_by, u.active_plan_id, u.status_id, u.created_at,
  u.ac_manager_id,
  am.name AS ac_manager_name,

  rp.organization_name, rp.contact_person_name, rp.designation,
  cp.candidate_name, cp.gender_id, g.name AS gender_name,
  pp.name AS promoter_name, pp.pan_no,

  ls.plan_id AS last_plan_id, ls.start_date AS last_start_date, ls.end_date AS last_end_date,
  sp.id AS plan_id, sp.plan_name AS plan_name, CAST(sp.plan_access AS UNSIGNED) AS plan_access_num,
  IFNULL(rc.total_referrals,0) AS total_referrals,

  ur.mobile_no AS ref_mobile,
  COALESCE(
    NULLIF(rrp.organization_name,''),
    NULLIF(rrp.contact_person_name,''),
    NULLIF(rcp.candidate_name,''),
    NULLIF(rpp.name,''),
    ur.mobile_no
  ) AS ref_name
" . $sql_base . $sql_where . $order . " " . ($view === 'all' ? "" : " LIMIT $per_page OFFSET $offset");

        $stmt = $con->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        /* ---- Show modal if server pushed one ---- */
        if (isset($_GET['blocked']) && !empty($_SESSION['modal_html'])) {
            $safeHtml = $_SESSION['modal_html'];
            unset($_SESSION['modal_html']);
            echo "<script>document.addEventListener('DOMContentLoaded',function(){openInfoModal(" . json_encode($safeHtml) . ");});</script>";
        }

        /* ---- filters UI ---- */
        ?>
        <form method="get" class="toolbar" style="gap:10px;flex-wrap:wrap">
            <input class="inp" type="text" name="q" value="<?= h($q) ?>" placeholder="Search name/mobile/referral..." style="min-width:240px">

            <select class="inp" name="profile_type_id" title="Profile Type">
                <option value="0" <?= $profile_type_id === 0 ? 'selected' : '' ?>>All Types</option>
                <option value="1" <?= $profile_type_id === 1 ? 'selected' : '' ?>>Recruiter</option>
                <option value="2" <?= $profile_type_id === 2 ? 'selected' : '' ?>>Candidate</option>
                <option value="3" <?= $profile_type_id === 3 ? 'selected' : '' ?>>Promoter</option>
            </select>

            <input class="inp" type="text" name="city_id" value="<?= h($city_id) ?>" placeholder="City Name">

            <select class="inp" name="status_id" title="Status">
                <option value="1" <?= $status_id === 1 ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= $status_id === 0 ? 'selected' : '' ?>>Inactive</option>
                <option value="-1" <?= $status_id === -1 ? 'selected' : '' ?>>Any</option>
            </select>



            <!---select class="inp" name="has_referrer">
    <option value=""   <?= $has_referrer === -1 ? 'selected' : '' ?>>Referrer: Any</option>
    <option value="1"  <?= $has_referrer === 1 ? 'selected' : '' ?>>Has Referrer</option>
    <option value="0"  <?= $has_referrer === 0 ? 'selected' : '' ?>>No Referrer</option>
  </select-->

            <!---select class="inp" name="has_fcm">
    <option value=""  <?= $has_fcm === -1 ? 'selected' : '' ?>>FCM: Any</option>
    <option value="1" <?= $has_fcm === 1 ? 'selected' : '' ?>>Has FCM</option>
    <option value="0" <?= $has_fcm === 0 ? 'selected' : '' ?>>No FCM</option>
  </select-->

            <!--select class="inp" name="has_location">
    <option value=""  <?= $has_location === -1 ? 'selected' : '' ?>>Location: Any</option>
    <option value="1" <?= $has_location === 1 ? 'selected' : '' ?>>Has Lat/Lng</option>
    <option value="0" <?= $has_location === 0 ? 'selected' : '' ?>>No Lat/Lng</option>
  </select-->

            <!--input class="inp" type="text" name="referral_code" value="<?= h($referral_code_in) ?>" placeholder="Referral Code (input)"-->
            <!--input class="inp" type="text" name="myreferral_code" value="<?= h($myreferral_code_in) ?>" placeholder="MyReferral Code"-->
            <!--input class="inp" type="number" name="active_plan_id" value="<?= h($active_plan_id_in) ?>" placeholder="Plan ID" min="0"-->

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

           <input class="inp flatpickr" type="text" name="created_from"
       value="<?= h($created_from) ?>"
       placeholder="DD-MM-YYYY"
       autocomplete="off">

<input class="inp flatpickr" type="text" name="created_to"
       value="<?= h($created_to) ?>"
       placeholder="DD-MM-YYYY"
       autocomplete="off">

            <select class="inp" name="ac_manager_filter" title="AC Operator">
                <option value="-1" <?= $ac_manager_filter === -1 ? 'selected' : '' ?>>AC Operator: Any</option>
                <option value="0" <?= $ac_manager_filter === 0 ? 'selected' : ''  ?>>AC Operator: Unassigned</option>
                <?php foreach ($acManagers as $a) { ?>
                    <option value="<?= (int)$a['id'] ?>" <?= $ac_manager_filter === (int)$a['id'] ? 'selected' : '' ?>>
                        <?= h($a['name']) ?>
                    </option>
                <?php } ?>
            </select>
            <!-- referrer filters -->
            <!--input class="inp" type="text"   name="referrer_q"  value="<?= h($referrer_q) ?>" placeholder="Search Referrer: name/mobile" style="min-width:220px"-->
            <!--input class="inp" type="number" name="referrer_id" value="<?= $referrer_id ?: '' ?>" placeholder="Referrer User ID" min="1" style="width:160px"-->

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

        <div style="display:flex;align-items:center;gap:12px;margin:8px 0 12px">
            <span class="badge">Total: <?= (int)$total ?></span>
            <span class="badge">Showing: <?= ($view === 'all') ? 'All' : ($res->num_rows) ?></span>
            <?php if ($view !== 'all') { ?>
                <div style="margin-left:auto;display:flex;gap:6px;align-items:center">
                    <?php if ($page > 1) { ?>
                        <a class="btn secondary" href="<?= h(keep_params(['page' => $page - 1])) ?>">‹ Prev</a>
                    <?php } ?>
                    <span>Page <?= (int)$page ?> / <?= (int)$pages ?></span>
                    <?php if ($page < $pages) { ?>
                        <a class="btn secondary" href="<?= h(keep_params(['page' => $page + 1])) ?>">Next ›</a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <form id="bulkAssignForm" method="post" class="toolbar" style="gap:10px;flex-wrap:wrap;margin:10px 0">

            <input type="hidden" name="action" value="assign_ac_manager">
            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_token']) ?>">

            <select class="inp" name="ac_manager_id" required>
                <option value="">Select AC Operator</option>
                <?php foreach ($acManagers as $a) { ?>
                    <option value="<?= (int)$a['id'] ?>"><?= h($a['name']) ?> (<?= h($a['email']) ?>)</option>
                <?php } ?>
                <option value="0">Unassign</option>
            </select>

            <input class="inp" type="text" name="remark" placeholder="Remark (optional)" style="min-width:260px">

            <button class="btn primary" type="submit">Bulk Assign</button>

            <span class="badge" style="margin-left:auto">Apply filters → select users → bulk assign</span>
        </form>

        <script>
            function toggleAllUsers(cb) {
                document.querySelectorAll('input[name="selected_users[]"]').forEach(x => x.checked = cb.checked);
            }
        </script>

        <div class="table-wrap">
            <table class="table">


                <thead>
                    <tr>
                        <th style="width:40px">
                            <input type="checkbox" form="bulkAssignForm" onclick="toggleAllUsers(this)">

                        </th>
                        <th style="width:60px">SR No</th>
                        <th>Name / Profile</th>
                        <th>Type</th>
                        <th>Mobile</th>
                        <th>City</th>
                        <th>My Code</th>
                        <th>Referred By</th>
                        <th>Plan</th>
                        <th>Plan</th>
                        <!--th>Referral Count</th-->

                        <th>Created</th>
                        <th>Assign Operator</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sr = ($view === 'all') ? 1 : ($offset + 1);
                    while ($row = $res->fetch_assoc()):
                        $ptype = (int)$row['profile_type_id'];

                        if ($ptype === 1) {
                            $display = $row['organization_name'] ?: $row['contact_person_name'] ?: $row['mobile_no'];
                            $summary = [];
                            if ($row['organization_name']) $summary[] = 'Org: ' . $row['organization_name'];
                            if ($row['contact_person_name']) $summary[] = 'Person: ' . $row['contact_person_name'];
                            if ($row['designation']) $summary[] = 'Desig: ' . $row['designation'];
                            $profile_summary = implode(' | ', $summary);
                            $ptype_badge = '<span class="badge">Recruiter</span>';
                        } elseif ($ptype === 2) {
                            $display = $row['candidate_name'] ?: $row['mobile_no'];
                            $profile_summary = 'Gender: ' . h($row['gender_name'] ?? '');
                            $ptype_badge = '<span class="badge">Candidate</span>';
                        } else {
                            $display = $row['promoter_name'] ?: $row['mobile_no'];
                            $profile_summary = 'PAN: ' . h($row['pan_no'] ?? '');
                            $ptype_badge = '<span class="badge">Promoter</span>';
                        }

                        // Plan label
                        if (!empty($row['plan_name'])) {
                            $plan_label = $row['plan_name'] . ' ' . (($row['plan_access_num'] == 2) ? '(Premium)' : '(Free)');
                        } elseif (!empty($row['plan_id'])) {
                            $plan_label = 'Plan #' . (int)$row['plan_id'] . ' ' . (($row['plan_access_num'] == 2) ? '(Premium)' : '(Free)');
                        } elseif (!empty($row['active_plan_id'])) {
                            $plan_label = 'Plan #' . (int)$row['active_plan_id'];
                        } else {
                            $plan_label = '—';
                        }

                        $sub_status = ($row['last_end_date'])
                            ? ((strtotime($row['last_end_date']) >= time()) ? '<span class="badge success">Active</span>' : '<span class="badge warn">Expired</span>')
                            : '<span class="badge">—</span>';

                        $fcm = ($row['fcm_token']) ? '<span class="badge success">Yes</span>' : '<span class="badge">No</span>';
                        $loc = ($row['latitude'] !== '' && $row['longitude'] !== '') ? '<span class="badge success">Yes</span>' : '<span class="badge">No</span>';

                        // Referrer display/link
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
                            $refLinkHref = keep_params(['referrer_id' => (int)$row['referred_by'], 'page' => 1]);
                        }

                        $status_badge = ((int)$row['status_id'] === 1) ? '<span class="badge success">Active</span>' : '<span class="badge danger">Inactive</span>';

                        $formId = 'del-' . (int)$row['id'];
                    ?>


                        <tr>
                            <td>
                                <input type="checkbox" form="bulkAssignForm" name="selected_users[]" value="<?= (int)$row['id'] ?>">

                            </td>
                            <td><?= (int)$sr++; ?></td>
                            <td>
                                <div style="font-weight:600"><?= h($display) ?></div>
                                <div style="font-size:12px;color:#9ca3af"><?= h($profile_summary) ?></div>
                                <div style="margin-top:4px"><?= $status_badge ?></div>
                            </td>
                            <td><?= $ptype_badge ?></td>
                            <td><?= h($row['mobile_no']) ?></td>
                            <td><?= h($row['city_id']) ?></td>
                            <td>
                                <?php if (!empty($row['myreferral_code'])) { ?>
                                    <span><?= h($row['myreferral_code']) ?></span>
                                    <button class="btn secondary" style="padding:2px 6px;font-size:11px"
                                        onclick="copyToClipboard(<?= json_encode((string)$row['myreferral_code']) ?>);return false;">Copy</button>
                                <?php } else {
                                    echo '—';
                                } ?>
                            </td>
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
                                <?php if ($row['last_start_date'] || $row['last_end_date']) { ?>
                                    <div style="font-size:12px;color:#9ca3af">
                                        <?= h($row['last_start_date'] ? date('d M Y', strtotime($row['last_start_date'])) : '—') ?> →
                                        <?= h($row['last_end_date']   ? date('d M Y', strtotime($row['last_end_date']))   : '—') ?>
                                    </div>
                                <?php } ?>
                            </td>
                            <!--td><?= (int)$row['total_referrals'] ?></td-->

                            <td><?= h(date('d M Y', strtotime($row['created_at']))) ?></td>
                            <td>
                                <div style="font-size:12px;color:#6b7280;margin-bottom:4px">
                                    Assigned to: <strong><?= h($row['ac_manager_name'] ?? '—') ?></strong>
                                </div>

                                <form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                                    <input type="hidden" name="action" value="assign_ac_manager">
                                    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">

                                    <select class="inp" name="ac_manager_id" style="min-width:180px" required>
                                        <option value="">Select</option>
                                        <?php foreach ($acManagers as $a) { ?>
                                            <option value="<?= (int)$a['id'] ?>" <?= ((int)$row['ac_manager_id'] === (int)$a['id']) ? 'selected' : '' ?>>
                                                <?= h($a['name']) ?>
                                            </option>
                                        <?php } ?>
                                        <option value="0">Unassign</option>
                                    </select>

                                    <button class="btn secondary" type="submit">Assign</button>
                                </form>
                            </td>

                        </tr>
                    <?php endwhile;
                    $stmt->close(); ?>
                    <?php if ($sr === (($view === 'all') ? 1 : ($offset + 1))) { ?>
                        <tr>
                            <td colspan="14" style="text-align:center;color:#9ca3af">No records found.</td>
                        </tr>
                    <?php } ?>
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
    document.addEventListener("DOMContentLoaded", function() {
      flatpickr(".flatpickr", {
        altInput: true, // user sees formatted date
        altFormat: "d-m-Y", // display format
        dateFormat: "Y-m-d", // value sent to backend
        allowInput: false
      });
    });
  </script>
<?php
echo ob_get_clean();
