<?php
@ini_set('display_errors', '1');
@error_reporting(E_ALL);

/* ---- Auth / DB ---- */
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/initialize.php'; // $con (mysqli)

/* ---- Page constants ---- */
$page_title = 'Stream Master';
$TABLE = 'jos_crm_stream';

/* ---- Helpers ---- */

function keep_params(array $changes = [])
{
    $qs = $_GET;
    foreach ($changes as $k => $v) {
        if ($v === null) unset($qs[$k]);
        else $qs[$k] = $v;
    }
    $q = http_build_query($qs);
    $path = basename($_SERVER['PHP_SELF']);
    return $path . ($q ? ('?' . $q) : '');
}

function clean($v)
{
    return trim((string)$v);
}
function to_int($v)
{
    return (int)($v ?? 0);
}

function back_to_list(string $msg = '')
{
    $qs = $_GET;
    unset($qs['add'], $qs['edit']);
    if ($msg !== '') $qs['ok'] = $msg;
    $query = http_build_query($qs);
    $url = basename($_SERVER['PHP_SELF']) . ($query ? ('?' . $query) : '');
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    } else {
        echo "<script>window.location.href = " . json_encode($url) . ";</script>";
        echo "<noscript><a href='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "'>Continue</a></noscript>";
        exit;
    }
}

/* capability wrapper (adapt to your project) */
function user_can(string $cap): bool
{
    if (function_exists('current_user_can')) {
        try {
            return (bool) current_user_can($cap);
        } catch (Throwable $e) { /* ignore */
        }
    }
    if (!empty($_SESSION['user']['caps']) && is_array($_SESSION['user']['caps'])) {
        return in_array($cap, $_SESSION['user']['caps'], true);
    }
    if (!empty($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') return true;
    error_log("user_can: capability '{$cap}' not configured; defaulting to allow.");
    return true;
}

/* ---- Schema helpers ---- */
if (!isset($con) || !($con instanceof mysqli)) die('Database connection ($con) not available.');
function ensure_schema(mysqli $con, string $table)
{
    $r = mysqli_query($con, "SHOW COLUMNS FROM `$table` LIKE 'status'");
    if (!$r || mysqli_num_rows($r) === 0) {
        mysqli_query($con, "ALTER TABLE `$table` ADD `status` TINYINT NOT NULL DEFAULT 1");
    }
    $r2 = mysqli_query($con, "SHOW COLUMNS FROM `$table` LIKE 'orderby'");
    if (!$r2 || mysqli_num_rows($r2) === 0) {
        mysqli_query($con, "ALTER TABLE `$table` ADD `orderby` INT NOT NULL DEFAULT 0");
    }
}
ensure_schema($con, $TABLE);

function next_orderby(mysqli $con, string $table): int
{
    $rs = mysqli_query($con, "SELECT COALESCE(MAX(orderby),0)+1 AS nxt FROM `$table`");
    $row = $rs ? mysqli_fetch_assoc($rs) : ['nxt' => 1];
    return max(1, (int)$row['nxt']);
}

/* ---- Load title from jos_admin_menus (best-effort) ---- */
try {
    $self = basename($_SERVER['PHP_SELF']);
    $menuTitle = null;
    $mst = $con->prepare("SELECT title FROM `jos_admin_menus` WHERE `link` LIKE CONCAT('%',?,'%') LIMIT 1");
    if ($mst) {
        $mst->bind_param('s', $self);
        $mst->execute();
        $res = $mst->get_result();
        if ($res && ($row = $res->fetch_assoc())) $menuTitle = trim($row['title']);
        $mst->close();
    }
    if ($menuTitle) $page_title = $menuTitle;
} catch (Throwable $e) {
    error_log('Could not load menu title: ' . $e->getMessage());
}

/* ---- POST ---- */
$err = '';
$ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('verify_csrf') || !verify_csrf($_POST['csrf'] ?? null)) {
        $err = 'Invalid request (CSRF).';
    } else {
        if (isset($_POST['save'])) {
            $id      = to_int($_POST['id'] ?? 0);
            $name    = clean($_POST['name'] ?? '');
            $status  = to_int($_POST['status'] ?? 1);
            $orderby = to_int($_POST['orderby'] ?? 0);

            if ($id) {
                if (!user_can('stream_edit')) $err = 'You do not have permission to edit streams.';
            } else {
                if (!user_can('stream_add')) $err = 'You do not have permission to add streams.';
            }

            if ($err === '') {
                if ($name === '') $err = 'Name is required.';
                else {
                    if ($orderby <= 0) $orderby = next_orderby($con, $TABLE);

                    $sqlDup = "SELECT id FROM `$TABLE` WHERE LOWER(name)=LOWER(?)" . ($id ? " AND id<>?" : "");
                    $st = $con->prepare($sqlDup);
                    if (!$st) $err = 'Database error: ' . $con->error;
                    else {
                        if ($id) $st->bind_param('si', $name, $id);
                        else $st->bind_param('s', $name);
                        $st->execute();
                        $dup = $st->get_result();
                        $st->close();

                        if ($dup && $dup->num_rows > 0) $err = 'This stream already exists.';
                        else {
                            if ($id) {
                                $st = $con->prepare("UPDATE `$TABLE` SET name=?, status=?, orderby=? WHERE id=?");
                                if (!$st) $err = 'Database error: ' . $con->error;
                                else {
                                    $st->bind_param('siii', $name, $status, $orderby, $id);
                                    $ok = $st->execute() ? 'Updated successfully' : 'Update failed';
                                    $st->close();
                                    back_to_list($ok);
                                }
                            } else {
                                $st = $con->prepare("INSERT INTO `$TABLE` (name, status, orderby) VALUES (?,?,?)");
                                if (!$st) $err = 'Database error: ' . $con->error;
                                else {
                                    $st->bind_param('sii', $name, $status, $orderby);
                                    $ok = $st->execute() ? 'Added successfully' : 'Insert failed';
                                    $st->close();
                                    back_to_list($ok);
                                }
                            }
                        }
                    }
                }
            }
        }

        if (isset($_POST['delete'])) {
            if (!user_can('stream_delete')) $err = 'You do not have permission to delete streams.';
            else {
                $id = to_int($_POST['id'] ?? 0);
                $st = $con->prepare("DELETE FROM `$TABLE` WHERE id=?");
                if (!$st) $err = 'Database error: ' . $con->error;
                else {
                    $st->bind_param('i', $id);
                    $ok = $st->execute() ? 'Deleted successfully' : 'Delete failed';
                    $st->close();
                    back_to_list($ok);
                }
            }
        }
    }
}

/* ---- Mode ---- */
$mode = (isset($_GET['edit']) || isset($_GET['add'])) ? 'form' : 'list';

/* ---- Fetch row for edit ---- */
$edit = null;
if ($mode === 'form' && isset($_GET['edit'])) {
    $eid = to_int($_GET['edit']);
    $st = $con->prepare("SELECT id, name, status, orderby FROM `$TABLE` WHERE id=?");
    if ($st) {
        $st->bind_param('i', $eid);
        $st->execute();
        $edit = $st->get_result()->fetch_assoc();
        $st->close();
    }
}

/* ---- Authorization gating for form mode ---- */
if ($mode === 'form') {
    if (isset($_GET['add']) && !user_can('stream_add')) {
        $err = 'You do not have permission to add streams.';
        $mode = 'list';
    }
    if (isset($_GET['edit']) && !user_can('stream_edit')) {
        $err = 'You do not have permission to edit streams.';
        $mode = 'list';
    }
}

/* ---- Filters (List) ---- */
$q     = clean($_GET['q'] ?? '');
$level = ($_GET['level'] ?? '') !== '' ? to_int($_GET['level']) : null;
$sort  = clean($_GET['sort'] ?? 'id_desc');
$all   = isset($_GET['all']);
$lim   = $all ? 0 : 50;

$where = " WHERE 1=1 ";
$binds = [];
$types = '';

if ($q !== '') {
    $where .= " AND name LIKE ?";
    $binds[] = "%$q%";
    $types .= 's';
}
if ($level !== null) {
    $where .= " AND orderby=?";
    $binds[] = $level;
    $types .= 'i';
}

$ORDER = "id DESC";
switch ($sort) {
    case 'order_asc':
        $ORDER = "orderby ASC, id DESC";
        break;
    case 'order_desc':
        $ORDER = "orderby DESC, id DESC";
        break;
    case 'name_asc':
        $ORDER = "name ASC, id DESC";
        break;
    case 'name_desc':
        $ORDER = "name DESC, id DESC";
        break;
    case 'id_asc':
        $ORDER = "id ASC";
        break;
    default:
        $ORDER = "id DESC";
        break;
}

/* ---- View permission ---- */
if (!user_can('stream_view')) {
    ob_start();
?>
    <!doctype html>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="stylesheet" href="/adminconsole/assets/ui.css">
    <div class="master-wrap">
        <div class="headbar">
            <h2><?= htmlspecialchars($page_title) ?></h2>
        </div>
        <div class="card">
            <div class="alert warn">You do not have permission to view this page.</div>
        </div>
    </div>
<?php
    echo ob_get_clean();
    exit;
}

/* Counts & List */
$st = $con->prepare("SELECT COUNT(*) c FROM `$TABLE` $where");
if ($st) {
    if ($binds) $st->bind_param($types, ...$binds);
    $st->execute();
    $total = (int)$st->get_result()->fetch_assoc()['c'];
    $st->close();
} else {
    $total = 0;
}

$sql = "SELECT id, name, status, orderby FROM `$TABLE` $where ORDER BY $ORDER";
if (!$all) $sql .= " LIMIT $lim";
$st = $con->prepare($sql);
$rows = [];
if ($st) {
    if ($binds) $st->bind_param($types, ...$binds);
    $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) $rows[] = $r;
    $st->close();
}

/* ---- View ---- */
ob_start();
?>
<!doctype html>
<meta charset="utf-8">
<title><?= htmlspecialchars($page_title) ?></title>
<link rel="stylesheet" href="/adminconsole/assets/ui.css">

<div class="master-wrap">
    <div class="headbar">
        <h2><?= htmlspecialchars($page_title) ?></h2>
    </div>

    <?php if (isset($_GET['ok']) && $_GET['ok'] !== ''): ?>
        <div class="alert ok"><?= htmlspecialchars($_GET['ok']) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="alert warn"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <?php if ($mode === 'list'): ?>
        <div class="card">
            <div class="toolbar">
                <form method="get" class="search" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <input type="text" name="q" class="inp" placeholder="Search by name..." value="<?= htmlspecialchars($q) ?>">
                    <input type="number" name="level" class="inp" placeholder="Order Level (exact)" value="<?= htmlspecialchars($level ?? '') ?>" min="0">
                    <select name="sort" class="inp">
                        <option value="order_asc" <?= $sort === 'order_asc' ? 'selected' : '' ?>>Order Level ↑</option>
                        <option value="order_desc" <?= $sort === 'order_desc' ? 'selected' : '' ?>>Order Level ↓</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A–Z</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z–A</option>
                        <option value="id_desc" <?= $sort === 'id_desc' ? 'selected' : '' ?>>Newest first</option>
                        <option value="id_asc" <?= $sort === 'id_asc' ? 'selected' : '' ?>>Oldest first</option>
                    </select>
                    <button class="btn gray" type="submit">Search</button>

                    <?php if (!$all && !empty($total) && $total > $lim): ?>
                        <a class="btn gray" href="<?= htmlspecialchars(keep_params(['all' => 1])) ?>">View All (<?= $total ?>)</a>
                    <?php endif; ?>
                    <?php if ($all): ?>
                        <a class="btn gray" href="<?= htmlspecialchars(keep_params(['all' => null])) ?>">Last 50</a>
                    <?php endif; ?>
                </form>

                <?php if (user_can('stream_add')): ?>
                    <a class="btn green" href="<?= htmlspecialchars(keep_params(['add' => 1, 'edit' => null])) ?>">Add New</a>
                <?php endif; ?>
            </div>

            <div style="margin:8px 0;color:#9ca3af">
                Showing <strong><?= !$all ? count($rows) : $total ?></strong> of <strong><?= $total ?></strong>
                <?= $q !== '' ? 'for “' . htmlspecialchars($q) . '”' : '' ?>
                <?= $level !== null ? ' | Order Level = ' . htmlspecialchars((string)$level) : '' ?>
                <?= !$all ? '(latest first)' : '' ?>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:80px">SR No</th>
                            <th>Name</th>
                            <th style="width:140px">Order Level</th>
                            <th style="width:120px">Status</th>
                            <th style="width:220px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="5" style="color:#9ca3af">No records</td>
                            </tr>
                        <?php endif; ?>
                        <?php $sr = 0;
                        foreach ($rows as $r): $sr++; ?>
                            <tr>
                                <td><?= $sr ?></td>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td><?= (int)$r['orderby'] ?></td>
                                <td><span class="badge <?= ((int)$r['status']) ? 'on' : 'off' ?>"><?= ((int)$r['status']) ? 'Active' : 'Inactive' ?></span></td>
                                <td>
                                    <?php if (user_can('stream_edit')): ?>
                                        <a class="btn gray" href="<?= htmlspecialchars(keep_params(['edit' => (int)$r['id'], 'add' => null])) ?>">Edit</a>
                                    <?php endif; ?>
                                    <?php if (user_can('stream_delete')): ?>
                                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this record?');">
                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(function_exists('csrf_token') ? csrf_token() : '') ?>">
                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <button class="btn red" name="delete" type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: /* ---- FORM MODE ---- */ ?>
        <div class="card" style="max-width:820px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <h3 style="margin:0"><?= $edit ? 'Edit Stream' : 'Add Stream' ?></h3>
                <a class="btn gray" href="<?= htmlspecialchars(keep_params(['add' => null, 'edit' => null])) ?>">Back to List</a>
            </div>

            <form method="post" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(function_exists('csrf_token') ? csrf_token() : '') ?>">
                <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>

                <div style="grid-column:1/-1">
                    <label>Name*</label>
                    <input name="name" class="inp" required value="<?= htmlspecialchars($edit['name'] ?? '') ?>">
                </div>

                <div>
                    <label>Order Level</label>
                    <input type="number" name="orderby" class="inp" min="0" placeholder="Auto if blank or 0" value="<?= htmlspecialchars((string)($edit['orderby'] ?? 0)) ?>">
                </div>

                <div>
                    <label>Status</label>
                    <?php $st = isset($edit['status']) ? (int)$edit['status'] : 1; ?>
                    <select name="status" class="inp">
                        <option value="1" <?= $st === 1 ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $st === 0 ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div style="grid-column:1/-1">
                    <!-- Always "Save" label now. Delete button removed from the edit form as requested. -->
                    <button class="btn green" name="save" type="submit">Save</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php
echo ob_get_clean();
