<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$page_title = 'Slider Master';
$TABLE = 'jos_app_slider';

/* ---------- helpers ---------- */
/**
 * Build a URL to this script with modified query params.
 * Always returns the script path with optional ?query — never blank.
 */
function keep_params(array $changes = [])
{
    $qs = $_GET;
    foreach ($changes as $k => $v) {
        if ($v === null) unset($qs[$k]);
        else $qs[$k] = $v;
    }
    $q = http_build_query($qs);
    $script = $_SERVER['PHP_SELF'] ?? basename(__FILE__);
    return $script . ($q !== '' ? ('?' . $q) : '');
}

function clean($v)
{
    return trim((string)$v);
}
function col_exists($con, $table, $col)
{
    $r = mysqli_query($con, "SHOW COLUMNS FROM `$table` LIKE '" . mysqli_real_escape_string($con, $col) . "'");
    return ($r && mysqli_num_rows($r) > 0);
}
function ensure_schema_minimal($con, $table)
{
    if (!col_exists($con, $table, 'status'))  mysqli_query($con, "ALTER TABLE `$table` ADD `status` TINYINT(1) NOT NULL DEFAULT 1");
    if (!col_exists($con, $table, 'orderby')) mysqli_query($con, "ALTER TABLE `$table` ADD `orderby` INT NOT NULL DEFAULT 0");
    else                                    mysqli_query($con, "ALTER TABLE `$table` MODIFY `orderby` INT NOT NULL DEFAULT 0");
}
ensure_schema_minimal($con, $TABLE);

/* ---------- permission wrapper ---------- */
function has_cap($cap)
{
    if (function_exists('current_user_can')) return (bool) current_user_can($cap);
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        if (!empty($_SESSION['user']['caps']) && is_array($_SESSION['user']['caps'])) {
            if (in_array($cap, $_SESSION['user']['caps'], true)) return true;
        }
        if (!empty($_SESSION['user']['permissions']) && is_array($_SESSION['user']['permissions'])) {
            if (in_array($cap, $_SESSION['user']['permissions'], true)) return true;
        }
        if (!empty($_SESSION['user']['permissions_map']) && is_array($_SESSION['user']['permissions_map'])) {
            if (!empty($_SESSION['user']['permissions_map'][$cap])) return true;
        }
    }
    return true; // permissive fallback (change to false to deny by default)
}

/* ---------- auto title from jos_admin_menus ---------- */
try {
    $script_name = basename($_SERVER['PHP_SELF']);
    $sqls = [
        "SELECT menu_name FROM jos_admin_menus WHERE menu_link = ? LIMIT 1",
        "SELECT menu_name FROM jos_admin_menus WHERE url = ? LIMIT 1",
        "SELECT menu_name FROM jos_admin_menus WHERE menu_link LIKE CONCAT('%', ?, '%') LIMIT 1",
        "SELECT menu_name FROM jos_admin_menus WHERE url LIKE CONCAT('%', ?, '%') LIMIT 1"
    ];
    foreach ($sqls as $s) {
        if ($st = @$con->prepare($s)) {
            $st->bind_param('s', $script_name);
            $st->execute();
            $res = $st->get_result();
            if ($row = $res->fetch_assoc()) {
                if (!empty($row['menu_name'])) {
                    $page_title = $row['menu_name'];
                }
            }
            $st->close();
            if ($page_title !== 'Slider Master') break;
        }
    }
} catch (Exception $e) {
    // silent fallback
}

/* ---------- redirect helper ---------- */
function back_to_list($msg = '', $qs = '')
{
    $self = $_SERVER['PHP_SELF'];
    $qs = (string)$qs;
    if ($qs !== '') {
        if ($qs[0] !== '?') $qs = '?' . ltrim($qs, '?');
        // Append ok param if provided
        if ($msg !== '') {
            // if qs already has params, append with &
            if (strpos($qs, '?') !== false && substr($qs, 1) !== '') {
                $qs .= '&ok=' . urlencode($msg);
            } else {
                $qs .= 'ok=' . urlencode($msg);
            }
        }
        header('Location: ' . $self . $qs);
        exit;
    } else {
        header('Location: ' . $self . ($msg !== '' ? ('?ok=' . urlencode($msg)) : ''));
        exit;
    }
}

$ok  = clean($_GET['ok'] ?? '');
$err = '';

/* ---------- upload paths ---------- */
$UPLOAD_DIR_FS = realpath(__DIR__ . '/../../webservices/uploads/slider');
if (!$UPLOAD_DIR_FS) $UPLOAD_DIR_FS = __DIR__ . '/../../webservices/uploads/slider';
if (!is_dir($UPLOAD_DIR_FS)) @mkdir($UPLOAD_DIR_FS, 0775, true);
$UPLOAD_URL_REL = 'uploads/slider/';

function full_img_for_display($dbPath)
{
    if (!$dbPath) return '';
    if (defined('DOMAIN_URL') && DOMAIN_URL) {
        $base = rtrim(DOMAIN_URL, '/') . '/';
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = $scheme . '://' . $host . '/';
    }
    if (preg_match('~^https?://~i', $dbPath)) return $dbPath;
    $p = ltrim($dbPath, '/');
    if (stripos($p, 'webservices/') === 0) return $base . $p;
    if (stripos($p, 'uploads/slider/') === 0) return $base . 'webservices/' . $p;
    return $base . 'webservices/' . $p;
}

/* ---------- image save ---------- */
function save_slider_image($file)
{
    global $UPLOAD_DIR_FS, $UPLOAD_URL_REL;
    $max = 5 * 1024 * 1024;
    $ok_ext  = ['jpg', 'jpeg', 'png', 'webp'];
    $ok_mime = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return [false, null, 'No file uploaded'];

    $tmp  = $file['tmp_name'];
    $name = $file['name'];
    $size = (int)$file['size'];
    $type = $file['type'] ?? 'application/octet-stream';
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($size <= 0 || $size > $max) return [false, null, 'File too large (max 5MB)'];
    if (!in_array($ext, $ok_ext, true)) return [false, null, 'Invalid extension'];

    $mime = $type;
    if (class_exists('finfo')) {
        $f = @new finfo(FILEINFO_MIME_TYPE);
        if ($f) {
            $m = @$f->file($tmp);
            if ($m) $mime = $m;
        }
    }
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($tmp);
        if ($m) $mime = $m;
    }
    $gi = @getimagesize($tmp);
    if (is_array($gi) && !empty($gi['mime'])) $mime = $gi['mime'];
    if (!in_array($mime, $ok_mime, true)) return [false, null, 'Invalid file type'];

    $fname = 'slider_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest  = rtrim($UPLOAD_DIR_FS, '/') . '/' . $fname;

    if (!@move_uploaded_file($tmp, $dest)) {
        if (!@rename($tmp, $dest) && !@copy($tmp, $dest)) return [false, null, 'Failed to save file'];
    }
    @chmod($dest, 0644);

    return [true, $UPLOAD_URL_REL . $fname, null];
}

/* ---------- mode ---------- */
$mode = (isset($_GET['edit']) || isset($_GET['add'])) ? 'form' : 'list';

/* ---------- POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $err = 'Invalid request. Please refresh.';
    } else {
        $return_qs = clean($_POST['return_qs'] ?? '');

        if (isset($_POST['delete'])) {
            if (!has_cap('slider.delete')) {
                $err = 'Permission denied.';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $st = $con->prepare("DELETE FROM $TABLE WHERE id=?");
                $st->bind_param('i', $id);
                if ($st->execute()) {
                    $st->close();
                    back_to_list('Deleted successfully.', $return_qs);
                }
                $err = 'Delete failed.';
                $st->close();
            }
        }

        if (isset($_POST['save'])) {
            $id           = (int)($_POST['id'] ?? 0);
            if ($id > 0 && !has_cap('slider.edit')) {
                $err = 'Permission denied.';
            }
            if ($id === 0 && !has_cap('slider.add')) {
                $err = 'Permission denied.';
            }

            $title        = clean($_POST['title'] ?? '');
            $action_type  = clean($_POST['action_type'] ?? '');
            $action_value = clean($_POST['action_value'] ?? '');
            $profile_type = (int)($_POST['profile_type'] ?? 0);
            $status       = (int)($_POST['status'] ?? 1);

            if ($title === '') {
                $err = 'Title is required.';
            } else {
                $img_path = null;
                if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    [$okImg, $relPath, $imgErr] = save_slider_image($_FILES['image']);
                    if (!$okImg) $err = $imgErr;
                    else $img_path = $relPath;
                }
                if (!$err) {
                    if ($id > 0) {
                        if ($img_path) {
                            $st = $con->prepare("UPDATE $TABLE SET title=?, image=?, action_type=?, action_value=?, profile_type=?, status=?, updated_at=NOW() WHERE id=?");
                            $st->bind_param('ssssiii', $title, $img_path, $action_type, $action_value, $profile_type, $status, $id);
                        } else {
                            $st = $con->prepare("UPDATE $TABLE SET title=?, action_type=?, action_value=?, profile_type=?, status=?, updated_at=NOW() WHERE id=?");
                            $st->bind_param('sssiii', $title, $action_type, $action_value, $profile_type, $status, $id);
                        }
                        if ($st->execute()) {
                            $st->close();
                            back_to_list('Updated successfully.', $return_qs);
                        }
                        $err = 'Update failed.';
                        $st->close();
                    } else {
                        $st = $con->prepare("INSERT INTO $TABLE (title,image,action_type,action_value,profile_type,status,created_at,updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())");
                        $img_for_insert = $img_path ?? '';
                        $st->bind_param('ssssii', $title, $img_for_insert, $action_type, $action_value, $profile_type, $status);
                        if ($st->execute()) {
                            $st->close();
                            back_to_list('Added successfully.', $return_qs);
                        }
                        $err = 'Insert failed.';
                        $st->close();
                    }
                }
            }
        }
    }
}

/* ---------- edit row ---------- */
$edit = null;
if ($mode === 'form' && isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $st = $con->prepare("SELECT id,title,image,action_type,action_value,profile_type,status FROM $TABLE WHERE id=?");
    $st->bind_param('i', $eid);
    $st->execute();
    $edit = $st->get_result()->fetch_assoc();
    $st->close();
}

/* ---------- filters ---------- */
$q      = clean($_GET['q'] ?? '');
$pflt   = clean($_GET['ptype'] ?? '');
$sort   = clean($_GET['sort'] ?? 'id_desc');
$all    = isset($_GET['all']);
$lim = $all ? 0 : 50;

$where = " WHERE 1=1 ";
$bind = [];
$type = '';
if ($q !== '') {
    $like = "%$q%";
    $where .= " AND title LIKE ?";
    $bind[] = $like;
    $type .= 's';
}
if ($pflt !== '' && is_numeric($pflt)) {
    $pp = (int)$pflt;
    $where .= " AND profile_type=?";
    $bind[] = $pp;
    $type .= 'i';
}

switch ($sort) {
    case 'name_asc':
        $order = "ORDER BY title ASC";
        break;
    case 'name_desc':
        $order = "ORDER BY title DESC";
        break;
    case 'id_asc':
        $order = "ORDER BY id ASC";
        break;
    default:
        $order = "ORDER BY id DESC";
}

/* ---------- counts / list ---------- */
$rows = [];
$total = 0;
if ($mode === 'list') {
    if (!has_cap('slider.view')) {
        ob_start(); ?>
        <link rel="stylesheet" href="/adminconsole/assets/ui.css">
        <div class="master-wrap">
            <div class="headbar">
                <div class="headbar-left">
                    <h2 style="margin:0"><?php echo htmlspecialchars($page_title); ?></h2>
                </div>
            </div>
            <div class="card" style="border-left:4px solid #ef4444">
                <div>Permission denied: you do not have rights to view this page.</div>
            </div>
        </div>
<?php echo ob_get_clean();
        exit;
    }

    $st = $con->prepare("SELECT COUNT(*) c FROM $TABLE $where");
    if ($bind) $st->bind_param($type, ...$bind);
    $st->execute();
    $total = (int)$st->get_result()->fetch_assoc()['c'];
    $st->close();

    $sql = "SELECT id,title,image,action_type,action_value,profile_type,status FROM $TABLE $where $order";
    if (!$all) $sql .= " LIMIT $lim";
    $st = $con->prepare($sql);
    if ($bind) $st->bind_param($type, ...$bind);
    $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) $rows[] = $r;
    $st->close();
}

/* ----- label helper for slider type (keeps DB col name profile_type) ----- */
function slider_type_label($v)
{
    $v = (int)$v;
    if ($v === 1) return 'Recruiter';
    if ($v === 2) return 'Job Seeker';
    if ($v === 4) return 'Refer & Earn';
    if ($v === 0) return 'Login Screen';
    return '—';
}

/* ---------- view ---------- */
ob_start(); ?>
<link rel="stylesheet" href="/adminconsole/assets/ui.css">

<div class="master-wrap">
    <div class="headbar">
        <div class="headbar-left">
            <h2 style="margin:0"><?php echo htmlspecialchars($page_title); ?></h2>
        </div>
        <div class="headbar-right"></div>
    </div>

    <?php if ($ok):  ?><div class="card" style="border-left:4px solid #10b981">
            <div><?php echo htmlspecialchars($ok); ?></div>
        </div><?php endif; ?>
    <?php if ($err): ?><div class="card" style="border-left:4px solid #ef4444">
            <div><?php echo htmlspecialchars($err); ?></div>
        </div><?php endif; ?>

    <?php if ($mode === 'list'): ?>
        <!-- LIST ONLY -->
        <div class="card">
            <div class="toolbar">
                <!-- LEFT: Search + filters + Search + View All -->
                <form method="get" class="search">
                    <input type="text" name="q" class="inp" placeholder="Search slider..." value="<?php echo htmlspecialchars($q); ?>" style="width:320px">

                    <select name="ptype" class="inp small" title="Slider Type">
                        <option value="">All Types</option>
                        <option value="1" <?php echo ($pflt === '1') ? 'selected' : ''; ?>>Recruiter</option>
                        <option value="2" <?php echo ($pflt === '2') ? 'selected' : ''; ?>>Job Seeker</option>
                        <option value="4" <?php echo ($pflt === '4') ? 'selected' : ''; ?>>Refer &amp; Earn</option>
                        <option value="0" <?php echo ($pflt === '0') ? 'selected' : ''; ?>>Login Screen</option>
                    </select>

                    <select name="sort" class="inp small" title="Sort by">
                        <?php
                        $opts = [
                            'id_desc'   => 'Newest first',
                            'id_asc'    => 'Oldest first',
                            'name_asc'  => 'Title A–Z',
                            'name_desc' => 'Title Z–A',
                        ];
                        foreach ($opts as $k => $v) {
                            $sel = ($sort === $k) ? 'selected' : '';
                            echo "<option value=\"$k\" $sel>$v</option>";
                        }
                        ?>
                    </select>

                    <button class="btn gray" type="submit">Search</button>

                    <!-- View All next to Search (resets filters + shows all) -->
                    <a class="btn gray" href="<?php echo keep_params(['q' => null, 'ptype' => null, 'sort' => null, 'all' => 1]); ?>">
                        View All (<?php echo $total; ?>)
                    </a>
                </form>

                <!-- RIGHT: Add New (alone, green) -->
                <div style="display:flex;gap:8px">
                    <?php if (has_cap('slider.add')): ?>
                        <a class="btn green" href="<?php echo keep_params(['add' => 1, 'edit' => null]); ?>">Add New</a>
                    <?php else: ?>
                        <a class="btn gray" href="javascript:void(0)" title="Permission required">Add New</a>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin:6px 0 12px;color:#9ca3af">
                Showing <strong><?php echo !$all ? count($rows) : $total; ?></strong> of <strong><?php echo $total; ?></strong>
                (sorted: <?php echo htmlspecialchars($opts[$sort] ?? 'Newest first'); ?>)
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>SR No</th>
                            <th width="220">Image</th>
                            <th>Title</th>
                            <th>Action</th>
                            <th>Slider Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?><tr>
                                <td colspan="7" style="color:#9ca3af">No records</td>
                            </tr><?php endif; ?>
                        <?php $sr = 0;
                        foreach ($rows as $r): $sr++; ?>
                            <tr>
                                <td><?php echo $sr; ?></td>
                                <td>
                                    <?php if (!empty($r['image'])): ?>
                                        <img src="<?php echo htmlspecialchars(full_img_for_display($r['image'])); ?>"
                                            alt="" style="width:200px;height:200px;object-fit:cover;border-radius:8px">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($r['title']); ?></td>
                                <td><?php echo htmlspecialchars(trim(($r['action_type'] ?? '') . ' ' . ($r['action_value'] ?? ''))); ?></td>
                                <td><?php echo slider_type_label($r['profile_type'] ?? 0); ?></td>
                                <td><span class="badge <?php echo !empty($r['status']) ? 'on' : 'off'; ?>"><?php echo !empty($r['status']) ? 'Active' : 'Inactive'; ?></span></td>
                                <td>
                                    <?php if (has_cap('slider.edit')): ?>
                                        <a class="btn gray" href="<?php echo keep_params(['edit' => (int)$r['id'], 'add' => null]); ?>">Edit</a>
                                    <?php else: ?>
                                        <a class="btn gray" href="javascript:void(0)" title="Permission required">Edit</a>
                                    <?php endif; ?>

                                    <?php if (has_cap('slider.delete')): ?>
                                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this record?');">
                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                            <input type="hidden" name="return_qs" value="<?php echo htmlspecialchars(keep_params(['edit' => null, 'add' => null])); ?>">
                                            <button class="btn red" name="delete" type="submit">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn red" disabled title="Permission required">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: /* -------- FORM ONLY (top; list hidden) -------- */ ?>
        <div class="card" id="formTop" style="max-width:820px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <h3 style="margin:0"><?php echo $edit ? 'Edit Slider' : 'Add Slider'; ?></h3>
                <a class="btn gray" href="<?php echo keep_params(['edit' => null, 'add' => null]); ?>">Back to List</a>
            </div>

            <form method="post" enctype="multipart/form-data"
                style="display:grid;grid-template-columns:1fr 1fr;gap:12px" autocomplete="off">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int)$edit['id']; ?>"><?php endif; ?>

                <!-- preserve list state so POST handlers can redirect back properly -->
                <input type="hidden" name="return_qs" value="<?php echo htmlspecialchars(keep_params(['edit' => null, 'add' => null])); ?>">

                <div style="grid-column:1/-1">
                    <label>Title*</label>
                    <input name="title" class="inp" required value="<?php echo htmlspecialchars($edit['title'] ?? ''); ?>">
                </div>

                <div style="grid-column:1/-1">
                    <label>Image (jpg/png/webp, max 5MB)</label>
                    <input type="file" name="image" class="inp" accept=".jpg,.jpeg,.png,.webp">
                    <?php if (!empty($edit['image'])): ?>
                        <div style="margin-top:6px">
                            <img src="<?php echo htmlspecialchars(full_img_for_display($edit['image'])); ?>"
                                alt="" style="width:100%;max-width:560px;height:200px;object-fit:cover;border-radius:10px;border:1px solid #2a2f3a">
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <label>Action Type (optional)</label>
                    <input name="action_type" class="inp" value="<?php echo htmlspecialchars($edit['action_type'] ?? ''); ?>" placeholder="e.g. link, job, page">
                </div>

                <div>
                    <label>Action Value (optional)</label>
                    <input name="action_value" class="inp" value="<?php echo htmlspecialchars($edit['action_value'] ?? ''); ?>" placeholder="e.g. https://..., job id">
                </div>

                <div>
                    <label>Slider Type</label>
                    <?php $p = isset($edit['profile_type']) ? (int)$edit['profile_type'] : 0; ?>
                    <select name="profile_type" class="inp">
                        <option value="1" <?php echo $p === 1 ? 'selected' : ''; ?>>Recruiter</option>
                        <option value="2" <?php echo $p === 2 ? 'selected' : ''; ?>>Job Seeker</option>
                        <option value="4" <?php echo $p === 4 ? 'selected' : ''; ?>>Refer &amp; Earn</option>
                        <option value="0" <?php echo $p === 0 ? 'selected' : ''; ?>>Login Screen</option>
                    </select>
                </div>

                <div>
                    <label>Status</label>
                    <?php $st = isset($edit['status']) ? (int)$edit['status'] : 1; ?>
                    <select name="status" class="inp">
                        <option value="1" <?php echo $st === 1 ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $st === 0 ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div style="grid-column:1/-1">
                    <?php if (($edit && has_cap('slider.edit')) || (!$edit && has_cap('slider.add'))): ?>
                        <button class="btn green" name="save" type="submit">Save</button>
                    <?php else: ?>
                        <button class="btn green" type="button" disabled>Save (Permission required)</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <script>
            // keep viewport at top in form mode
            (function() {
                function toTop() {
                    window.scrollTo({
                        top: 0,
                        behavior: 'instant'
                    });
                }
                if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
                window.addEventListener('load', function() {
                    toTop();
                    setTimeout(toTop, 50);
                    setTimeout(toTop, 150);
                });
            })();
        </script>
    <?php endif; ?>
</div>
<?php echo ob_get_clean(); ?>