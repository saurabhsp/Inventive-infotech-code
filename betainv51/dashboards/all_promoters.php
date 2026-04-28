<?php
@ini_set('display_errors', '1');
@error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/initialize.php'; // $con, csrf_token(), verify_csrf()
require_login();

$mode = 'list';

/* ---------- helpers ---------- */
function clean($v)
{
    return trim((string)$v);
}
function keep_params(array $changes = [])
{
    $qs = $_GET;
    foreach ($changes as $k => $v) {
        if ($v === null) unset($qs[$k]);
        else $qs[$k] = $v;
    }
    $q = http_build_query($qs);
    return $q ? ('?' . $q) : '';
}
function col_exists(mysqli $con, $table, $col)
{
    $c = mysqli_real_escape_string($con, $col);
    $t = mysqli_real_escape_string($con, $table);
    $r = mysqli_query($con, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($r && mysqli_num_rows($r) > 0);
}
function ensure_schema(mysqli $con, $table)
{
    if (!col_exists($con, $table, 'status')) {
        @mysqli_query($con, "ALTER TABLE `$table` ADD `status` TINYINT(1) NOT NULL DEFAULT 1");
    }
    if (!col_exists($con, $table, 'orderby')) {
        @mysqli_query($con, "ALTER TABLE `$table` ADD `orderby` INT NOT NULL DEFAULT 0");
    }
    // Ensure location columns exist (if missing)
    $adds = [
        'country'  => "ADD `country` VARCHAR(120) NULL",
        'state'    => "ADD `state` VARCHAR(120) NULL",
        'district' => "ADD `district` VARCHAR(120) NULL",
        // city_id exists already
    ];
    foreach ($adds as $col => $sql) {
        if (!col_exists($con, $table, $col)) {
            @mysqli_query($con, "ALTER TABLE `$table` $sql");
        }
    }
}
function back_to_list($msg)
{
    $q = $_GET;
    unset($q['add'], $q['edit']);
    $q['ok'] = $msg;
    $self = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
    $path = $self . ($q ? ('?' . http_build_query($q)) : '');
    if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        header('Cache-Control: no-store');
        header('Location: ' . $path, true, 303);
        exit;
    }
    echo '<script>location.replace(' . json_encode($path) . ');</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    $_GET = $q;
    $GLOBALS['__force_list__'] = true;
}

function generateRandomCode($length = 4)
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle(str_repeat($chars, $length)), 0, $length);
}
function build_my_referral_code($user_id)
{
    $secretKey = "MySecretKey123";
    $hashPart  = strtoupper(substr(md5($secretKey . $user_id), 0, 8));
    $randomPart = generateRandomCode(4);
    return "REFPAC-$hashPart-$randomPart";
}

/* ---------- config ---------- */
$page_title = 'Promoters List';
$TABLE      = 'jos_app_promoter_profile';
$USER_TABLE = 'jos_app_users';
$PT_TABLE   = 'jos_app_profile_types';
$SUB_LOG    = 'jos_app_usersubscriptionlog';
$PLAN_TABLE = 'jos_app_subscription_plans';
$PROMOTER_DEFAULT_PLAN_ID = 10;

ensure_schema($con, $TABLE);

/* ---------- permissions ---------- */
function _session_permissions()
{
    if (!empty($_SESSION['admin_permissions']) && is_array($_SESSION['admin_permissions'])) return $_SESSION['admin_permissions'];
    return null;
}
function user_can($cap)
{
    $perms = _session_permissions();
    if ($perms === null) return true;
    return in_array($cap, $perms, true);
}
$can_view   = user_can('promoter.view');
$can_add    = user_can('promoter.add');
$can_edit   = user_can('promoter.edit');
$can_delete = user_can('promoter.delete');

if (!$can_view) {
    http_response_code(403);
    die('<!doctype html><meta charset="utf-8"><title>Forbidden</title>
       <div style="font:14px system-ui;padding:24px;color:#e11d48;background:#0b1220">
         You are not authorized to view this content.
       </div>');
}




/* ---------- filters ---------- */
$q   = clean($_GET['q'] ?? '');
$sfl = isset($_GET['status']) ? (string)$_GET['status'] : '';
$created_from = clean($_GET['created_from'] ?? '');
$created_to   = clean($_GET['created_to'] ?? '');
$city   = clean($_GET['city'] ?? '');
$all = isset($_GET['all']);
$lim = $all ? 0 : 50;


$where = " WHERE 1=1 ";
$type = '';
$bind = [];
if ($q !== '') {
    $where .= " AND (name LIKE ? OR mobile_no LIKE ? OR pan_no LIKE ? OR city_id LIKE ? OR country LIKE ? OR state LIKE ? OR district LIKE ?)";
    $like = "%$q%";
    $type .= 'sssssss';
    $bind[] = $like;
    $bind[] = $like;
    $bind[] = $like;
    $bind[] = $like;
    $bind[] = $like;
    $bind[] = $like;
    $bind[] = $like;
}
if ($sfl === '1' || $sfl === '0') {
    $where .= " AND status = ?";
    $type .= 'i';
    $bind[] = (int)$sfl;
}
if ($created_from !== '') {
    $from = date('Y-m-d', strtotime($created_from));
    $where .= " AND DATE(created_at) >= ?";
    $type .= 's';
    $bind[] = $from;
}

if ($created_to !== '') {
    $to = date('Y-m-d', strtotime($created_to));
    $where .= " AND DATE(created_at) <= ?";
    $type .= 's';
    $bind[] = $to;
}
if ($city !== '') {
    $where .= " AND city_id LIKE ?";
    $type .= 's';
    $bind[] = "%$city%";
}

/* ---------- count / list ---------- */
$count_sql = "SELECT COUNT(*) c FROM `$TABLE` $where";
$st = $con->prepare($count_sql);
if ($bind) $st->bind_param($type, ...$bind);
$st->execute();
$total = (int)$st->get_result()->fetch_assoc()['c'];
$st->close();

$list_sql = "SELECT id,name,mobile_no,pan_no,country,state,district,city_id,orderby,status,created_at
           FROM `$TABLE`
           $where
           ORDER BY orderby ASC, id DESC";
if (!$all) $list_sql .= " LIMIT $lim";

$st = $con->prepare($list_sql);
if ($bind) $st->bind_param($type, ...$bind);
$st->execute();
$rs = $st->get_result();
$rows = [];
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$st->close();

/* ---------- view ---------- */
ob_start(); ?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/adminconsole/assets/ui.css">
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
   <style>
.table-wrap {
    overflow: auto;
}

.table {
    min-width: 1100px;
}
</style>
</head>

<body>
    <div class="master-wrap">
        <div class="headbar">
            <h2 style="margin:0"><?= htmlspecialchars($page_title) ?></h2>
        </div>


        <div class="card">
            <div class="toolbar">
                <form method="get" class="search">
                    <input type="text" name="q" class="inp" placeholder="Search..." value="<?= htmlspecialchars($q) ?>">
                    <select name="status" class="inp">
                        <option value="">All status</option>
                        <option value="1" <?= $sfl === '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $sfl === '0' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <input class="inp flatpickr" type="text" name="created_from"
                        value="<?= htmlspecialchars($created_from) ?>"
                        placeholder="DD-MM-YYYY"
                        autocomplete="off">

                    <input class="inp flatpickr" type="text" name="created_to"
                        value="<?= htmlspecialchars($created_to) ?>"
                        placeholder="DD-MM-YYYY"
                        autocomplete="off">
                     <input type="text" name="city" class="inp" placeholder="Search City" value="<?= htmlspecialchars($city) ?>">
                    <button class="btn gray" type="submit">Search</button>
                    <a href="all_promoters.php" class="btn gray">Reset</a>

                    <?php if (!$all && $total > $lim): ?>
                        <a class="btn gray" href="<?= htmlspecialchars(keep_params(['all' => 1])) ?>">View All (<?= $total ?>)</a>
                    <?php endif; ?>
                    <?php if ($all): ?>
                        <a class="btn gray" href="<?= htmlspecialchars(keep_params(['all' => null])) ?>">Last 50</a>
                    <?php endif; ?>
                </form>

                <!-- <?php if ($can_add): ?>
          <a class="btn green" href="<?= htmlspecialchars(keep_params(['add' => 1])) ?>">Add Promoter</a>
        <?php endif; ?> -->
            </div>

            <div style="margin:8px 0;color:#9ca3af">
                Showing <strong><?= !$all ? count($rows) : $total ?></strong> of <strong><?= $total ?></strong>
                <?= $q !== '' ? 'for “' . htmlspecialchars($q) . '”' : '' ?>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>SR</th>
                            <th>Reg Date</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>PAN</th>
                            <th>Country</th>
                            <th>State</th>
                            <th>District</th>
                            <th>City</th>
                            <th>Order</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="11" style="color:#9ca3af">No records</td>
                            </tr>
                        <?php endif; ?>
                        <?php $sr = 0;
                        foreach ($rows as $r): $sr++; ?>
                            <tr>
                                <td><?= $sr ?></td>
                                <td><?= htmlspecialchars(date('d-m-Y', strtotime($r['created_at']))) ?></td>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td><?= htmlspecialchars($r['mobile_no']) ?></td>
                                <td><?= htmlspecialchars($r['pan_no']) ?></td>
                                <td><?= htmlspecialchars($r['country'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['state'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['district'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['city_id']) ?></td>
                                <td><?= htmlspecialchars($r['orderby']) ?></td>
                                <td><span class="badge <?= $r['status'] ? 'on' : 'off' ?>"><?= $r['status'] ? 'Active' : 'Inactive' ?></span></td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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


    </div>
</body>

</html>
<?php
echo ob_get_clean();
