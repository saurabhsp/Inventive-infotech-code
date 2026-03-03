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

$script_path     = $_SERVER['PHP_SELF'];
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
    echo '    <p style="color:#6b7280">If you believe this is an error, contact an administrator.</p>';
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

/* ======================================================================
   JOB POSITION WISE jobseeker REPORT
   ====================================================================== */

$page_title = ' Matching Jobseekers Summary (Job Postiion Based) ';

$q          = get_str('q', '');   // search job position name
$status_id  = isset($_GET['status_id']) ? (int)$_GET['status_id'] : -1; // -1 any, 1 active, 0 inactive

$sql = "
    SELECT
        jp.id,
        jp.name,
        COUNT(DISTINCT u.id) AS candidate_count
    FROM jos_crm_jobpost jp
    LEFT JOIN jos_app_candidate_profile cp 
        ON FIND_IN_SET(CAST(jp.id AS CHAR), REPLACE(cp.job_position_ids, ' ', '')) > 0
    LEFT JOIN jos_app_users u
        ON u.profile_type_id = 2 AND u.profile_id = cp.id
    WHERE 1=1
";

$types  = '';
$params = [];

/* filter by job position name */
if ($q !== '') {
    $sql .= " AND jp.name LIKE CONCAT('%', ?, '%')";
    $types .= 's';
    $params[] = $q;
}

/* filter by jobseeker status */
if ($status_id === 0 || $status_id === 1) {
    $sql .= " AND u.status_id = ?";
    $types .= 'i';
    $params[] = $status_id;
}

$sql .= "
    GROUP BY jp.id, jp.name
    HAVING candidate_count > 0
    ORDER BY candidate_count DESC, jp.name ASC
";

$stmt = $con->prepare($sql);
if (!$stmt) {
    echo '<div class="master-wrap"><div class="card">';
    echo '<div class="alert danger">Query prepare failed: ' . h($con->error) . '</div>';
    echo '</div></div>';
    exit;
}
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

/* path to jobseeker list */
$candidate_list_script = '/adminconsole/operations/jobseeker_list.php';

ob_start();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title><?= h($page_title) ?></title>
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

        .headbar h2 {
            margin: 0;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .table-wrap .table {
            min-width: 600px;
        }

        .table th,
        .table td {
            padding: 6px 8px;
            vertical-align: middle;
        }

        .badge.count-link {
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            background: #facc15;
            /* bright yellow for visibility */
            border-color: #facc15;
            color: #111827;
            margin-bottom: 4px;
        }
    </style>
</head>

<body>
    <div class="master-wrap">
        <div class="headbar" style="display:flex;align-items:center;gap:12px">
            <h2><?= h($page_title) ?></h2>
            <!-- Dashboard button removed as requested -->
        </div>

        <div class="card">

            <!-- Filters -->
            <form method="get" class="toolbar" style="gap:10px;flex-wrap:wrap">
                <input class="inp" type="text" name="q" value="<?= h($q) ?>" placeholder="Search job position..." style="min-width:240px">

                <select class="inp" name="status_id">
                    <option value="-1" <?= $status_id === -1 ? 'selected' : ''; ?>>Jobseeker Status: Any</option>
                    <option value="1" <?= $status_id === 1  ? 'selected' : ''; ?>>Active Jobseekers Only</option>
                    <option value="0" <?= $status_id === 0  ? 'selected' : ''; ?>>Inactive Jobseekers Only</option>
                </select>

                <button class="btn primary" type="submit">Apply</button>
                <a class="btn secondary" href="<?= h(parse_url($_SERVER['PHP_SELF'], PHP_URL_PATH)) ?>">Reset</a>
            </form>

            <div style="margin:8px 0 12px">
                <span class="badge">Total Positions: <?= (int)$res->num_rows ?></span>
                <span style="margin-left:8px;font-size:12px;color:#9ca3af">
                    Click on the jobseeker count to view the list of jobseekers for that position.
                </span>
            </div>

            <!-- Table -->
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:60px">SR No</th>
                            <th>Job Position</th>
                            <!-- Count + button in SAME column -->
                            <th style="width:200px">Jobseekers / Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sr = 1;
                        if ($res->num_rows === 0): ?>
                            <tr>
                                <td colspan="3" style="text-align:center;color:#9ca3af;padding:12px;">
                                    No job positions found with jobseekers for the selected filters.
                                </td>
                            </tr>
                            <?php else:
                            while ($row = $res->fetch_assoc()):
                                $jp_id   = (int)$row['id'];
                                $jp_name = $row['name'];
                                $cnt     = (int)$row['candidate_count'];

                                $qs = [
                                    'job_position_ids[]' => $jp_id,
                                ];
                                if ($status_id === 0 || $status_id === 1) {
                                    $qs['status_id'] = $status_id;
                                }
                                $candidate_url = $candidate_list_script . '?' . http_build_query($qs);
                            ?>
                                <tr>
                                    <td><?= $sr++; ?></td>
                                    <td>
                                        <div style="font-weight:600"><?= h($jp_name) ?></div>
                                    </td>

                                    <td>
                                        <a class="btn secondary"
                                            href="<?= h($candidate_url) ?>"
                                            target="_blank"
                                            rel="noopener"
                                            style="display:inline-flex;align-items:center;gap:6px;">

                                            View Jobseekers
                                            <span class="badge"
                                                style="background:#334155;border:1px solid #475569;color:#f1f5f9;">
                                                <?= $cnt ?>
                                            </span>
                                        </a>
                                    </td>

                                </tr>
                        <?php
                            endwhile;
                        endif;
                        $stmt->close();
                        ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</body>

</html>
<?php
echo ob_get_clean();
