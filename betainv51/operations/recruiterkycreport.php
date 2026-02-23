<?php
ob_start(); // avoid headers already sent on redirects

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/initialize.php'; // $con, csrf_token(), verify_csrf()
date_default_timezone_set('Asia/Kolkata');


/* ----------------
   VIEW-ONLY ACL GUARD (robust)
   - Uses jos_admin_menus.menu_link for matching
   - Normalizes path variants and allows ?menu_id= for testing
   - First checks whether jos_admin_menus has a 'can_view' column.
     * If the column does not exist -> skip deny logic (no crash).
     * If the column exists and a matching menu row is found and can_view != 1 -> 403.
   - Only restricts VIEW access. If no matching row found -> allow access.
   ---------------- */
if (!function_exists('acl_view_guard_executed')) {
    function acl_view_guard_executed()
    {
        return true;
    }
    global $con;

    // defensive: ensure $con exists
    if ($con instanceof mysqli) {
        // check whether can_view column exists in jos_admin_menus
        $has_can_view = false;
        $colCheck = @mysqli_query($con, "SHOW COLUMNS FROM `jos_admin_menus` LIKE 'can_view'");
        if ($colCheck && mysqli_num_rows($colCheck) > 0) {
            $has_can_view = true;
        }

        // If no can_view column present, do not enforce view-deny (avoid crash).
        if ($has_can_view) {
            // allow test override by menu_id (useful when menu_link isn't exact in DB)
            $test_menu_id = isset($_GET['menu_id']) ? (int)$_GET['menu_id'] : 0;

            // derive normalized path variants to match against jos_admin_menus.menu_link
            $req_path = '';
            if (!empty($_SERVER['REQUEST_URI'])) {
                $req_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
            }
            if ($req_path === '' && !empty($_SERVER['SCRIPT_NAME'])) {
                $req_path = $_SERVER['SCRIPT_NAME'];
            }
            $req_path = preg_replace('#/+#', '/', str_replace('\\', '/', (string)$req_path));
            $req_path = ltrim($req_path, '/');
            $req_basename = basename($req_path);

            $variants = array_unique([
                $req_path,
                '/' . $req_path,
                $req_basename,
            ]);

            $can_view = null;

            if ($test_menu_id > 0) {
                $sql = "SELECT can_view FROM jos_admin_menus WHERE id = ? LIMIT 1";
                if ($stmt = @mysqli_prepare($con, $sql)) {
                    mysqli_stmt_bind_param($stmt, "i", $test_menu_id);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    if ($row = mysqli_fetch_assoc($res)) $can_view = (int)$row['can_view'];
                    mysqli_stmt_close($stmt);
                }
            } else {
                // build dynamic IN clause depending on number of variants; use prepared stmt
                $placeholders = implode(',', array_fill(0, count($variants), '?'));
                $sql = "SELECT can_view, menu_link FROM jos_admin_menus WHERE menu_link IN ($placeholders) LIMIT 1";
                if ($stmt = @mysqli_prepare($con, $sql)) {
                    // bind params as strings
                    $types = str_repeat('s', count($variants));
                    mysqli_stmt_bind_param($stmt, $types, ...$variants);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    if ($row = mysqli_fetch_assoc($res)) $can_view = (int)$row['can_view'];
                    mysqli_stmt_close($stmt);
                }
            }

            // If a menu row exists and can_view != 1 => deny
            if ($can_view !== null && $can_view !== 1) {
                http_response_code(403);
?>
                <!doctype html>
                <html lang="en">

                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width,initial-scale=1">
                    <title>403 Access denied</title>
                    <link rel="stylesheet" href="/adminconsole/assets/ui.css">
                    <style>
                        body {
                            background: #0b1324;
                            color: var(--text);
                            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial;
                        }

                        .denied-wrap {
                            max-width: 720px;
                            margin: 6vh auto;
                            padding: 2rem;
                            border-radius: 12px;
                            background: rgba(255, 255, 255, 0.02);
                            box-shadow: 0 10px 30px rgba(2, 6, 23, .6);
                        }

                        .big {
                            font-size: 2.6rem;
                            font-weight: 700;
                            margin-bottom: .25rem
                        }

                        .muted {
                            opacity: .7;
                            margin-bottom: 1rem
                        }

                        .meta {
                            background: rgba(255, 255, 255, 0.02);
                            padding: .8rem;
                            border-radius: 8px;
                            font-family: monospace
                        }

                        .btn {
                            display: inline-block;
                            padding: .6rem .9rem;
                            border-radius: 8px;
                            text-decoration: none;
                            background: #334155;
                            color: white;
                            margin-top: 1rem
                        }
                    </style>
                </head>

                <body>
                    <div class="denied-wrap">
                        <div class="big">403 — Access denied</div>
                        <div class="muted">You don't have permission to view this page.</div>
                        <div class="meta">
                            <?php if (!empty($req_path)): ?>
                                Requested path: <?= htmlspecialchars($req_path, ENT_QUOTES, 'UTF-8') ?><br>
                            <?php endif; ?>
                            <?php if (!empty($req_basename)): ?>
                                Script: <?= htmlspecialchars($req_basename, ENT_QUOTES, 'UTF-8') ?><br>
                            <?php endif; ?>
                            <?php if ($test_menu_id > 0): ?>
                                Menu ID (testing): <?= (int)$test_menu_id ?><br>
                            <?php endif; ?>
                            Permission: <?= htmlspecialchars((string)$can_view, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <a class="btn" href="/adminconsole/">Back to dashboard</a>
                    </div>
                </body>

                </html>
<?php
                exit;
            }
        } // end if $has_can_view
        // if $has_can_view is false -> skip ACL enforcement (no crash)
    }
}
/* ---------------- end ACL guard ---------------- */

$page_title = 'Recruiter KYC Report (Recruiter-wise)';

/* ---------------- config ---------------- */
// Base for document URLs (works with relative paths like uploads/kyc/..)
$FILE_BASE = 'https://pacificconnect2.0.inv51.in/webservices/';

/* ---------------- helpers ---------------- */
function clean($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function keep_params(array $merge = [], array $drop = [])
{
    $q = $_GET;
    foreach ($drop as $k) unset($q[$k]);
    foreach ($merge as $k => $v) $q[$k] = $v;
    $qs = http_build_query($q);
    return $qs ? ('?' . $qs) : '';
}
function current_user_id_guess()
{
    if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
    foreach (['user_id', 'userid', 'id'] as $k) if (!empty($_SESSION[$k])) return (int)$_SESSION[$k];
    return 0;
}
function redirect_now($url)
{
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    }
    echo '<meta http-equiv="refresh" content="0;url=' . clean($url) . '">';
    echo '<script>location.href=' . json_encode($url) . '</script>';
    exit;
}
function abs_doc_url($base, $path)
{
    $path = (string)$path;
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/* ---------------- inputs ---------------- */
$status_id = $_GET['status_id'] ?? '';        // real id OR 'NOT_SUBMITTED'
$from      = trim($_GET['from'] ?? '');
$to        = trim($_GET['to'] ?? '');
$view      = $_GET['view'] ?? 'last50';       // last50|all
$q         = trim($_GET['q'] ?? '');          // recruiter name filter (org/contact)
/* ===== OVERRIDE FILTERS IF COMING FROM EMPLOYER LIST (POST) ===== */

/* ===== OVERRIDE FILTERS IF COMING FROM EMPLOYER LIST (POST) ===== */

$force_recruiter_id = 0;
$from_employer_list = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recruiter_id'])) {

    $from_employer_list = true;
    $force_recruiter_id = (int)$_POST['recruiter_id'];

    $posted_status = $_POST['status'] ?? '';

    if ($posted_status === 'NOT_SUBMITTED') {
        $status_id = 'NOT_SUBMITTED';
    } elseif ($posted_status !== '') {
        $status_id = (string)((int)$posted_status);
    }
}
/* date column preference */
$DATE_COL = 'l.created_at';
$colCheck = mysqli_query($con, "SHOW COLUMNS FROM `jos_app_recruiterkyclog` LIKE 'created_at'");
if (!$colCheck || mysqli_num_rows($colCheck) === 0) $DATE_COL = 'l.submission_date';

/* ---------------- POST: update / quick review ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['update_status', 'quick_status'], true)) {
    if (function_exists('verify_csrf')) verify_csrf($_POST['csrf'] ?? '');
    else {
        http_response_code(400);
        echo "CSRF validator missing";
        exit;
    }

    $log_id     = (int)($_POST['log_id'] ?? 0);
    $new_status = (int)($_POST['new_status'] ?? 0);
    $remarks    = trim($_POST['remarks'] ?? '');

    if ($log_id > 0 && $new_status > 0) {
        $has_updated_by = mysqli_num_rows(mysqli_query($con, "SHOW COLUMNS FROM `jos_app_recruiterkyclog` LIKE 'updated_by'")) > 0;
        $has_updated_at = mysqli_num_rows(mysqli_query($con, "SHOW COLUMNS FROM `jos_app_recruiterkyclog` LIKE 'updated_at'")) > 0;

        $set   = "status=?, remarks=?";
        $types = "is";
        $vals  = [$new_status, $remarks];

        if ($has_updated_at) {
            $set .= ", updated_at=NOW()";
        }
        if ($has_updated_by) {
            $set .= ", updated_by=?";
            $types .= "i";
            $vals[] = current_user_id_guess();
        }
        $types .= "i";
        $vals[] = $log_id;

        $sql  = "UPDATE jos_app_recruiterkyclog SET $set WHERE id=?";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$vals);
        mysqli_stmt_execute($stmt);
    }

    $ok = ($_POST['action'] === 'quick_status') ? "Marked In Review" : "Status updated";
    redirect_now(basename(__FILE__) . keep_params(['ok' => $ok]));
}

/* ---------------- lookups ---------------- */
$docTypes = [];
$res = mysqli_query($con, "SELECT id,name FROM jos_app_kycdoctype WHERE status=1 ORDER BY id");
while ($r = mysqli_fetch_assoc($res)) $docTypes[$r['id']] = $r['name'];

$statuses = [];
$res = mysqli_query($con, "SELECT id,name,colorcode FROM jos_app_kycstatus ORDER BY id");
while ($r = mysqli_fetch_assoc($res)) $statuses[$r['id']] = $r;

/* ids named "In Review" */
$inReviewIds = [];
foreach ($statuses as $sid => $st) if (strcasecmp(trim($st['name']), 'In Review') === 0) $inReviewIds[] = (int)$sid;
$reviewStatusId = $inReviewIds ? $inReviewIds[0] : 0;

/* flags for UI/logic */
$status_is_real = ($status_id !== '' && $status_id !== 'NOT_SUBMITTED' && ctype_digit($status_id));
$selected_status_name = $status_is_real && isset($statuses[(int)$status_id])
    ? $statuses[(int)$status_id]['name']
    : '';

/* ---------------- recruiters (KEY BY PROFILE ID) ---------------- */
$limitSql   = ($view === 'last50') ? "LIMIT 50" : "";
$recruiters = [];
$recWhere = [];
$recParams = [];
$recTypes  = '';

// If coming from employer list, filter exact recruiter only
if (!empty($force_recruiter_id)) {
    $recWhere[] = "id = ?";
    $recParams[] = $force_recruiter_id;
    $recTypes .= 'i';
}

if ($q !== '') {
    $recWhere[] = "(organization_name LIKE ? OR contact_person_name LIKE ?)";
    $like = '%' . $q . '%';
    $recParams[] = $like;
    $recParams[] = $like;
    $recTypes .= 'ss';
}
$recWhereSql = $recWhere ? "WHERE " . implode(" AND ", $recWhere) : '';

$recSql = "SELECT id, userid, organization_name, contact_person_name, email, mobile_no
           FROM jos_app_recruiter_profile
           $recWhereSql
           ORDER BY id DESC $limitSql";
$recStmt = mysqli_prepare($con, $recSql);
if ($recTypes !== '') mysqli_stmt_bind_param($recStmt, $recTypes, ...$recParams);
mysqli_stmt_execute($recStmt);
$recRes = mysqli_stmt_get_result($recStmt);
while ($r = mysqli_fetch_assoc($recRes)) $recruiters[$r['id']] = $r;

/* recruiter name suggestions (datalist) */
$sugg = [];
$sg = mysqli_query($con, "SELECT DISTINCT organization_name FROM jos_app_recruiter_profile ORDER BY organization_name LIMIT 5000");
while ($row = mysqli_fetch_assoc($sg)) {
    if (trim($row['organization_name']) !== '') $sugg[] = $row['organization_name'];
}

/* ---------------- build WHERE for logs ---------------- */
$where = [];
$params = [];
$types = '';

// Filter by recruiter (when coming from list)
if (!empty($force_recruiter_id)) {
    $where[] = "l.recruiter_id = ?";
    $params[] = $force_recruiter_id;
    $types .= 'i';
}

if ($from !== '') {
    $where[] = "$DATE_COL >= ?";
    $params[] = $from . ' 00:00:00';
    $types .= 's';
}

if ($to !== '') {
    $where[] = "$DATE_COL <= ?";
    $params[] = $to . ' 23:59:59';
    $types .= 's';
}

if ($status_is_real) {
    $where[] = "l.status=?";
    $params[] = (int)$status_id;
    $types .= 'i';
}
$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

/* ---------------- fetch logs ---------------- */
$sql = "SELECT l.*,
               s.name AS status_name, s.colorcode,
               d.name AS doctype_name,
               DATE_FORMAT($DATE_COL, '%Y-%m-%d %H:%i:%s') AS submitted_at
        FROM jos_app_recruiterkyclog l
        LEFT JOIN jos_app_kycstatus s  ON s.id=l.status
        LEFT JOIN jos_app_kycdoctype d ON d.id=l.kycdoctype_id
        $whereSql";
$stmt = mysqli_prepare($con, $sql);
if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$logsByRecruiter = []; // recruiter_profile.id -> [doctype_id] => row
while ($r = mysqli_fetch_assoc($res)) $logsByRecruiter[$r['recruiter_id']][$r['kycdoctype_id']] = $r;

/* ---------------- recruiter-level filtering ---------------- */
// Apply recruiter-level filtering ONLY if a status filter is active
if ($status_id !== '') {

    foreach ($recruiters as $pid => $rec) {

        $docs   = $logsByRecruiter[$pid] ?? [];
        $hasAny = !empty($docs);

        if ($status_id === 'NOT_SUBMITTED') {
            if ($hasAny) unset($recruiters[$pid]);
            continue;
        }

        if ($status_is_real && !$hasAny) {
            unset($recruiters[$pid]);
            continue;
        }
    }
}

/* ---------------- sort: In-Review first, then newest ---------------- */
$recruitersSorted = array_values($recruiters);
usort($recruitersSorted, function ($a, $b) use ($logsByRecruiter, $inReviewIds) {
    $aid = (int)$a['id'];
    $bid = (int)$b['id'];
    $aLogs = $logsByRecruiter[$aid] ?? [];
    $bLogs = $logsByRecruiter[$bid] ?? [];
    $aHasIR = 0;
    $aDate = 0;
    foreach ($aLogs as $L) {
        if (in_array((int)$L['status'], $inReviewIds, true)) $aHasIR = 1;
        $ts = strtotime($L['created_at'] ?? $L['submission_date'] ?? '');
        if ($ts && $ts > $aDate) $aDate = $ts;
    }
    $bHasIR = 0;
    $bDate = 0;
    foreach ($bLogs as $L) {
        if (in_array((int)$L['status'], $inReviewIds, true)) $bHasIR = 1;
        $ts = strtotime($L['created_at'] ?? $L['submission_date'] ?? '');
        if ($ts && $ts > $bDate) $bDate = $ts;
    }
    if ($aHasIR !== $bHasIR) return $bHasIR - $aHasIR;
    return $bDate <=> $aDate;
});

/* ---------------- flash ---------------- */
$ok = $_GET['ok'] ?? '';

/* ---------------- render ---------------- */
ob_start();
?>
<link rel="stylesheet" href="/adminconsole/assets/ui.css">
<style>
    /* SOLID modal (not transparent) */
    .modal {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, .75);
        z-index: 1000;
    }

    .modal.open {
        display: flex;
    }

    .modal .box {
        background: #0b1324;
        color: var(--text);
        width: min(560px, 92vw);
        border-radius: 14px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, .6);
    }

    .modal .head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        border-bottom: 1px solid rgba(255, 255, 255, .08);
        font-weight: 600
    }

    .modal .body {
        padding: 16px;
        display: grid;
        gap: .75rem;
    }

    .modal .foot {
        padding: 12px 16px;
        border-top: 1px solid rgba(255, 255, 255, .08);
        display: flex;
        gap: .5rem;
        justify-content: flex-end
    }

    /* compact rows */
    .doc-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .45rem .65rem;
        border-radius: .6rem;
        background: var(--table-row-bg);
    }

    .doc-name {
        font-weight: 600
    }

    /* chips and buttons */
    .chips {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem
    }

    .chip {
        padding: .2rem .55rem;
        border-radius: 999px;
        background: #999;
        color: #fff;
        font-size: .8em;
        white-space: nowrap
    }

    .chip.missing {
        background: #DC3545;
        color: #fff
    }

    .chip.ok {
        background: #198754;
        color: #fff
    }

    /* status shown as a button (same sizing as other buttons) */
    a.btn,
    button.btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .35rem;
        text-decoration: none;
        padding: .45rem .75rem;
        border-radius: .6rem;
        font-weight: 600;
    }

    .btn.primary {
        background: #2563eb;
        color: #fff;
    }

    /* Apply/Reset defaults */
    .btn.secondary {
        background: #334155;
        color: #fff;
    }

    .btn.success {
        background: #22c55e;
        color: #fff;
    }

    /* Update */
    .btn.info {
        background: #1d4ed8;
        color: #fff;
    }

    /* Open */
    .btn.warning {
        background: #f59e0b;
        color: #1b1400;
    }

    /* Data Review */
    .btn:hover {
        filter: brightness(1.05);
    }

    .btn.status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: .45rem .75rem;
        border-radius: .6rem;
        font-weight: 600;
        font-size: .9em;
        cursor: default;
        pointer-events: none;
    }

    .btn.status.inreview {
        background: #f59e0b;
        color: #000;
    }

    /* In Review text black */

    /* Missing line: plain names + red "Missing" tag */
    .missing-line {
        display: flex;
        align-items: center;
        gap: .5rem;
        flex-wrap: wrap
    }

    .missing-item {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        margin-right: .6rem
    }

    .missing-doc {
        font-weight: 500
    }

    /* name filter input fits toolbar */
    .toolbar .name-wrap {
        display: flex;
        align-items: center;
        gap: .5rem
    }

    .toolbar .name-wrap input[type=text] {
        min-width: 260px
    }
</style>

<div class="master-wrap">
    <div class="headbar">
        <div class="title"><?= clean($page_title) ?></div>
        <div class="actions" style="display:flex;gap:.5rem">
            <a class="btn secondary" href="<?= clean(keep_params(['view' => 'last50'])) ?>">Last 50 recruiters</a>
            <a class="btn secondary" href="<?= clean(keep_params(['view' => 'all'])) ?>">View All</a>
        </div>
    </div>

    <?php if ($ok): ?>
        <div class="card" style="border-left:4px solid #22c55e; color:#bdf0c9; background:#0f2f1d;"><?= clean($ok) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="get" class="toolbar" style="gap:.75rem;flex-wrap:wrap">
            <!-- Status filter -->
            <select name="status_id" class="inp">
                <option value="">All Status (submitted)</option>
                <?php foreach ($statuses as $sid => $st): ?>
                    <option value="<?= (int)$sid ?>" <?= ($status_is_real && (int)$status_id === $sid) ? 'selected' : '' ?>>
                        <?= clean($st['name']) ?>
                    </option>
                <?php endforeach; ?>
                <option value="NOT_SUBMITTED" <?= $status_id === 'NOT_SUBMITTED' ? 'selected' : '' ?>>Not Submitted (no docs)</option>
            </select>

            <!-- Date range -->
            <input type="date" class="inp" name="from" value="<?= clean($from) ?>">
            <input type="date" class="inp" name="to" value="<?= clean($to) ?>">

            <!-- Recruiter name filter with autocomplete -->
            <span class="name-wrap">
                <input type="text" class="inp" name="q" list="recruiterNames" placeholder="Filter by recruiter name..." value="<?= clean($q) ?>">
                <datalist id="recruiterNames">
                    <?php foreach ($sugg as $nm): ?>
                        <option value="<?= clean($nm) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </span>

            <!-- View -->
            <select name="view" class="inp">
                <option value="last50" <?= $view === 'last50' ? 'selected' : '' ?>>Last 50</option>
                <option value="all" <?= $view === 'all'   ? 'selected' : '' ?>>View All</option>
            </select>

            <button class="btn primary">Apply</button>
            <a class="btn secondary" href="<?= clean(basename(__FILE__)) ?>">Reset</a>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Recruiter</th>
                        <th>Documents</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recruitersSorted): ?>
                        <tr>
                            <td colspan="3" style="text-align:center">No recruiters found</td>
                        </tr>
                        <?php else:
                        $sr = 0;
                        foreach ($recruitersSorted as $rec):
                            $sr++;
                            $profileId = (int)$rec['id'];
                            $userId = (int)$rec['userid'];
                            $docs = $logsByRecruiter[$profileId] ?? [];

                            /* Missing block only when NOT filtering by a real status */
                            $showMissingUI = !$status_is_real;
                            $missing = [];
                            if ($showMissingUI) {
                                foreach ($docTypes as $did => $nm) if (!isset($docs[$did])) $missing[$did] = $nm;
                            }

                            // SUBMITTED rows
                            ob_start();
                            if (!$docs) {
                                echo "<div class='chip' style='background:#DC3545'>No documents submitted</div>";
                            } else {
                                foreach ($docTypes as $did => $dname) {
                                    if (!isset($docs[$did])) continue;
                                    $d = $docs[$did];
                                    $color = $d['colorcode'] ?: '#555';
                                    $statusName = $d['status_name'] ?: 'Unknown';
                                    $file = abs_doc_url($FILE_BASE, $d['docurl'] ?: '');
                        ?>
                                    <div class="doc-row">
                                        <div>
                                            <div class="doc-name"><?= clean($dname) ?></div>
                                            <div style="font-size:.82em;opacity:.85">Doc No: <?= clean($d['docno'] ?: '—') ?> • <?= clean($d['submitted_at'] ?: '') ?></div>
                                        </div>
                                        <div class="chips">
                                            <?php $isIR = (strcasecmp($statusName, 'In Review') === 0); ?>
                                            <span class="btn status<?= $isIR ? ' inreview' : '' ?>"
                                                style="<?= $isIR ? '' : 'background: ' . clean($color) . '; color:#fff' ?>">
                                                <?= clean($statusName) ?>
                                            </span>

                                            <?php if ($file): ?>
                                                <a class="btn info" href="<?= clean($file) ?>" target="_blank">Open</a>
                                            <?php endif; ?>

                                            <?php if ($reviewStatusId): ?>
                                                <button type="button"
                                                    class="btn warning btn-review"
                                                    data-log-id="<?= (int)$d['id'] ?>"
                                                    data-status-id="<?= (int)$reviewStatusId ?>">

                                                </button>
                                            <?php endif; ?>

                                            <button type="button"
                                                class="btn success btn-update"
                                                data-log-id="<?= (int)$d['id'] ?>"
                                                data-doc-name="<?= clean($dname) ?>"
                                                data-status="<?= (int)$d['status'] ?>"
                                                data-remarks="<?= clean($d['remarks'] ?? '') ?>">
                                                Update
                                            </button>
                                        </div>
                                    </div>
                            <?php
                                }
                            }
                            $submittedBlock = ob_get_clean();

                            // MISSING compact line (names plain + red "Missing" tag)
                            ob_start();
                            if ($showMissingUI) {
                                if (!$missing) {
                                    echo "<span class='chip ok'>All required docs submitted</span>";
                                } else {
                                    echo "<div class='missing-line'><span style='opacity:.85'>Missing:</span>";
                                    $i = 0;
                                    $total = count($missing);
                                    foreach ($missing as $nm) {
                                        if ($i === 6) {
                                            echo "<button type='button' class='btn secondary btn-view-missing' data-list='"
                                                . clean(json_encode(array_values($missing)))
                                                . "'>+" . ($total - $i) . " more</button>";
                                            break;
                                        }
                                        echo "<span class='missing-item'><span class='missing-doc'>" . clean($nm) . "</span><span class='chip missing'>Missing</span></span>";
                                        $i++;
                                    }
                                    echo "</div>";
                                }
                            }
                            $missingLine = ob_get_clean();
                            ?>
                            <tr>
                                <td><?= $sr ?></td>
                                <td>
                                    <div style="font-weight:600"><?= clean($rec['organization_name']) ?></div>
                                    <div style="font-size:.85em;opacity:.85">
                                        <?= clean($rec['contact_person_name']) ?> • <?= clean($rec['email']) ?> • <?= clean($rec['mobile_no']) ?>
                                        <div style="opacity:.7">ProfileID: <?= $profileId ?> • UserID: <?= $userId ?></div>
                                        <?php if ($status_is_real): ?>
                                            <div style="opacity:.7;margin-top:.15rem">
                                                Matched<?= $selected_status_name ? ' (' . clean($selected_status_name) . ')' : '' ?>:
                                                <?= count($docs) ?>
                                            </div>
                                        <?php else: ?>
                                            <div style="opacity:.7;margin-top:.15rem">
                                                Submitted: <?= count($docs) ?>/<?= count($docTypes) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;flex-direction:column;gap:.6rem">
                                        <?= $submittedBlock ?>
                                        <?= $missingLine ?>
                                    </div>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Hidden form for quick "Data Review" action -->
<form id="quickStatusForm" method="post" style="display:none">
    <input type="hidden" name="action" value="quick_status">
    <input type="hidden" name="csrf" value="<?= clean(csrf_token()) ?>">
    <input type="hidden" name="log_id" value="">
    <input type="hidden" name="new_status" value="">
    <input type="hidden" name="remarks" value="Marked In Review">
</form>

<!-- Modal: Update Status -->
<div class="modal" id="statusModal" aria-hidden="true">
    <div class="box">
        <div class="head">
            <div>Update Document Status</div>
            <button type="button" class="btn secondary" id="statusModalClose">Close</button>
        </div>
        <form method="post">
            <div class="body">
                <div class="inp" style="background:transparent;border:none;padding:0">
                    <div style="opacity:.8;font-size:.9em">Document</div>
                    <div id="mDocName" style="font-weight:600">—</div>
                </div>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="csrf" value="<?= clean(csrf_token()) ?>">
                <input type="hidden" name="log_id" id="mLogId" value="">
                <label> Status
                    <select name="new_status" id="mStatus" class="inp">
                        <?php foreach ($statuses as $sid => $st): ?>
                            <option value="<?= (int)$sid ?>"><?= clean($st['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label> Remarks
                    <input type="text" name="remarks" id="mRemarks" class="inp" placeholder="Remarks (optional)">
                </label>
            </div>
            <div class="foot">
                <button type="button" class="btn secondary" id="statusModalCancel">Cancel</button>
                <button type="submit" class="btn success">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Missing list -->
<div class="modal" id="missingModal" aria-hidden="true">
    <div class="box">
        <div class="head">
            <div>Missing Documents</div>
            <button type="button" class="btn secondary" id="missingModalClose">Close</button>
        </div>
        <div class="body" id="missingListBody"></div>
        <div class="foot">
            <button type="button" class="btn secondary" id="missingModalCancel">Close</button>
        </div>
    </div>
</div>

<script>
    (function() {
        const $ = s => document.querySelector(s);
        const $$ = s => Array.from(document.querySelectorAll(s));

        // status modal
        const modal = $('#statusModal');
        const openStatus = (logId, docName, statusId, remarks) => {
            $('#mLogId').value = logId;
            $('#mDocName').textContent = docName || '—';
            $('#mStatus').value = String(statusId || '');
            $('#mRemarks').value = remarks || '';
            modal.classList.add('open');
            setTimeout(() => $('#mStatus').focus(), 50);
        };
        const closeStatus = () => modal.classList.remove('open');

        $$('.btn-update').forEach(btn => {
            btn.addEventListener('click', () => {
                openStatus(
                    btn.dataset.logId,
                    btn.dataset.docName,
                    btn.dataset.status,
                    btn.dataset.remarks
                );
            });
        });
        $('#statusModalClose')?.addEventListener('click', closeStatus);
        $('#statusModalCancel')?.addEventListener('click', closeStatus);
        modal?.addEventListener('click', e => {
            if (e.target === modal) closeStatus();
        });

        // quick review button -> hidden form submit
        const qForm = $('#quickStatusForm');
        $$('.btn-review').forEach(btn => {
            btn.addEventListener('click', () => {
                qForm.querySelector('[name=log_id]').value = btn.dataset.logId;
                qForm.querySelector('[name=new_status]').value = btn.dataset.statusId;
                qForm.submit();
            });
        });

        // missing modal
        const missingModal = $('#missingModal');
        const openMissing = (arr) => {
            const body = $('#missingListBody');
            body.innerHTML = '';
            if (!arr || !arr.length) {
                body.innerHTML = '<div class="chip ok">No missing docs</div>';
            } else {
                const ul = document.createElement('ul');
                ul.style.margin = '0';
                ul.style.paddingLeft = '1rem';
                arr.forEach(name => {
                    const li = document.createElement('li');
                    const nameSpan = document.createElement('span');
                    nameSpan.textContent = name;

                    const tag = document.createElement('span');
                    tag.className = 'chip missing';
                    tag.textContent = 'Missing';

                    li.appendChild(nameSpan);
                    li.appendChild(document.createTextNode(' '));
                    li.appendChild(tag);
                    ul.appendChild(li);
                });
                body.appendChild(ul);
            }
            missingModal.classList.add('open');
        };
        const closeMissing = () => missingModal.classList.remove('open');

        $$('.btn-view-missing').forEach(btn => {
            btn.addEventListener('click', () => {
                try {
                    openMissing(JSON.parse(btn.dataset.list || '[]'));
                } catch (e) {
                    openMissing([]);
                }
            });
        });
        $('#missingModalClose')?.addEventListener('click', closeMissing);
        $('#missingModalCancel')?.addEventListener('click', closeMissing);
        missingModal?.addEventListener('click', e => {
            if (e.target === missingModal) closeMissing();
        });
    })();
</script>
<?php
echo ob_get_clean();
?>