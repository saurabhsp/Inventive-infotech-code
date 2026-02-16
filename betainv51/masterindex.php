<?php
@ini_set('display_errors', '1');
@error_reporting(E_ALL);
require_once __DIR__ . '/../includes/auth.php';
require_login();

global $con;
$page_title = 'Masters';

/* ---------- helpers ---------- */
function keep_params(array $changes = []): string
{
    $qs = $_GET;
    foreach ($changes as $k => $v) {
        if ($v === null) unset($qs[$k]);
        else $qs[$k] = $v;
    }
    $s = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
    $q = http_build_query($qs);
    return $q ? ($s . '?' . $q) : $s;
}
function table_exists(mysqli $c, string $n): bool
{
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($n) . "'");
    return ($r && $r->num_rows > 0);
}
function norm_path($p): string
{
    $p = (string)$p;
    if ($p === '') return '/';
    $parts = @parse_url($p);
    $path = $parts['path'] ?? $p;
    $path = strtr($path, ['//' => '/']);
    if (strpos($path, '/beta/') === 0) $path = substr($path, 5);
    elseif ($path === '/beta') $path = '/';
    if ($path === '' || $path[0] !== '/') $path = '/' . $path;
    if ($path !== '/' && substr($path, -1) === '/') $path = rtrim($path, '/');
    return $path;
}
function current_role_id(mysqli $c, int $u): ?int
{
    if ($u <= 0 || !table_exists($c, 'jos_admin_roles') || !table_exists($c, 'jos_admin_users_roles')) return null;
    $st = $c->prepare("SELECT r.id FROM jos_admin_users_roles ur JOIN jos_admin_roles r ON r.id=ur.role_id AND r.status=1 WHERE ur.user_id=? LIMIT 1");
    $st->bind_param('i', $u);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    return $r ? (int)$r['id'] : null;
}
function has_menu_access_here(mysqli $c, int $rid): bool
{
    if ($rid <= 0 || !table_exists($c, 'jos_admin_menus')) return false;
    if (!table_exists($c, 'jos_admin_rolemenus')) return true;
    $paths = [norm_path($_SERVER['SCRIPT_NAME'] ?? ''), norm_path($_SERVER['REQUEST_URI'] ?? '')];
    foreach ($paths as $p) {
        $st = $c->prepare("SELECT 1 FROM jos_admin_menus m JOIN jos_admin_rolemenus rm ON rm.menu_id=m.id AND rm.role_id=? WHERE m.status=1 AND (m.menu_link=? OR m.menu_link LIKE CONCAT('%',?)) LIMIT 1");
        $st->bind_param('iss', $rid, $p, $p);
        $st->execute();
        if ($st->get_result()->num_rows > 0) {
            $st->close();
            return true;
        }
        $st->close();
    }
    return false;
}

/* ---------- enforce ---------- */
$me = $_SESSION['admin_user'] ?? [];
$uid = (int)($me['id'] ?? 0);
if ($uid <= 0) die('<div style="color:#e11d48;padding:24px">Forbidden</div>');
$rid = current_role_id($con, $uid);
if ($rid === null || !has_menu_access_here($con, $rid)) die('<div style="color:#e11d48;padding:24px">Access denied</div>');

/* ---------- fetch masters ---------- */
$rows = [];
$acl = table_exists($con, 'jos_admin_rolemenus');
$sql = "SELECT m.menu_name,m.menu_link FROM jos_admin_menus m" . ($acl ? " JOIN jos_admin_rolemenus rm ON rm.menu_id=m.id AND rm.role_id=?" : "") . " WHERE m.status=1 ORDER BY m.orderby,m.id";
if ($acl) {
    $st = $con->prepare($sql);
    $st->bind_param('i', $rid);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
} else {
    $res = $con->query($sql);
    if ($res) $rows = $res->fetch_all(MYSQLI_ASSOC);
}
function is_master($l)
{
    $p = norm_path($l);
    return (strpos($p, '/masters/') !== false && substr($p, -4) === '.php');
}
function href_master($l)
{
    $p = norm_path($l);
    if (strpos($p, '/adminconsole/') !== 0 && strpos($p, '/masters/') === 0) $p = '/adminconsole' . $p;
    return $p;
}
$masters = array_values(array_filter($rows, fn($r) => is_master($r['menu_link'])));

/* ---------- view ---------- */
?>
<link rel="stylesheet" href="/adminconsole/assets/ui.css">
<style>
    /* ---- mimic Operations UI exactly ---- */
    .master-headbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 10px;
    }

    .master-search {
        display: flex;
        gap: 8px;
        align-items: center;
        justify-content: flex-start;
    }

    .master-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 16px;
        padding: 12px;
    }

    .master-card {
        padding: 20px;
        border-radius: 14px;
        min-height: 150px;
        background-color: rgba(255, 255, 255, 0.03);
    }

    .master-title {
        font-weight: 700;
        margin-bottom: 10px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .btn.primary {
        background: #2563eb !important;
        color: #fff !important;
        border: none;
        padding: 8px 18px;
        border-radius: 999px;
        font-weight: 600;
    }

    .btn.primary:hover {
        opacity: .9;
    }

    .btn.secondary {
        padding: 8px 14px;
        border-radius: 10px;
    }

    .inp {
        min-width: 280px;
    }
</style>

<div class="master-wrap">
    <div class="card" style="margin-top:16px">
        <div class="headbar master-headbar" style="position:sticky;top:0;z-index:1">
            <div style="font-weight:700;font-size:16px;">All Masters</div>
            <form id="searchBar" class="master-search" onsubmit="return false;">
                <input id="recentFilter" class="inp" type="text" placeholder="Search pages…" autocomplete="off">
                <button type="button" id="btnSearch" class="btn primary">Search</button>
                <button type="button" id="btnReset" class="btn secondary">Reset</button>
            </form>
        </div>

        <?php if ($masters): ?>
            <div id="recentGrid" class="master-grid">
                <?php foreach ($masters as $r):
                    $title = trim($r['menu_name'] ?? 'Untitled');
                    $href = href_master($r['menu_link']); ?>
                    <div class="card master-card" data-title="<?= htmlspecialchars(strtolower($title)) ?>">
                        <div class="master-title"><?= htmlspecialchars($title) ?></div>
                        <a class="btn primary" href="<?= htmlspecialchars($href) ?>">Open</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="padding:16px">
                <div class="badge">No master menus available.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    (function() {
        var q = document.getElementById('recentFilter'),
            grid = document.getElementById('recentGrid'),
            s = document.getElementById('btnSearch'),
            r = document.getElementById('btnReset');
        if (!grid) return;
        var cards = [...grid.children];

        function filter(v) {
            v = (v || '').toLowerCase().trim();
            cards.forEach(c => c.style.display = !v || c.dataset.title.includes(v) ? '' : 'none');
        }
        s.onclick = () => filter(q.value);
        r.onclick = () => {
            q.value = '';
            filter('');
            q.focus();
        };
        q.onkeydown = e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                filter(q.value);
            }
        };
    })();
</script>