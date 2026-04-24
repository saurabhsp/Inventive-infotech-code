<?php
/**
 * UI Auto Shell (Dynamic Sidebar) — STRICT ACL
 * - No fallback, no static items.
 * - Renders menus from jos_admin_menus filtered by jos_admin_rolemenus (role_id + can_view=1).
 * - Role resolved from current_user(); if missing, tries jos_admin_users_roles → jos_admin_roles.
 *
 * REQUIREMENTS:
 *   - auth.php should: require this file, then call pacific_enable_autoshell();
 *   - $con (mysqli) available globally after auth bootstrap.
 */

if (!function_exists('pacific_enable_autoshell')) {

function pacific_enable_autoshell() {
  if (defined('PACIFIC_NO_SHELL') && PACIFIC_NO_SHELL) return;
  static $once=false; if ($once) return; $once=true;
  if (!ob_get_level()) ob_start();

  register_shutdown_function(function () {
    $CONTENT = ob_get_clean();

    // If not HTML, passthrough
    foreach (headers_list() as $h) {
      $hl = strtolower($h);
      if (strpos($hl,'content-disposition: attachment')===0) { echo $CONTENT; return; }
      if (strpos($hl,'content-type:')===0 && strpos($hl,'text/html')===false) { echo $CONTENT; return; }
    }

    // ---------- helpers ----------
    $con = $GLOBALS['con'] ?? null;

    $user = null;
    if (function_exists('current_user')) {
      $user = current_user();
    } else {
      // minimal session-based fallback for identity only (NOT for menus)
      $user = [
        'id'        => (int)($_SESSION['admin_user']['id'] ?? 0),
        'name'      => (string)($_SESSION['admin_user']['name'] ?? ($_SESSION['username'] ?? 'Administrator')),
        'role_id'   => (int)($_SESSION['admin_user']['role_id'] ?? 0),
        'role_name' => (string)($_SESSION['admin_user']['role'] ?? ''),
      ];
    }

    $title    = $GLOBALS['page_title'] ?? 'Dashboard';
    // ---- Force userName to come from DB ----
    $userId = (int)($user['id'] ?? 0);
    $userName = 'Administrator';

    if ($userId > 0 && isset($GLOBALS['con']) && $GLOBALS['con'] instanceof mysqli) {
      $stmt = $GLOBALS['con']->prepare("SELECT name FROM jos_admin_users WHERE id = ? LIMIT 1");
      $stmt->bind_param("i", $userId);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($res && $row = $res->fetch_assoc()) {
        $userName = $row['name'];
      }
      $stmt->close();
    }

    $iconBase = '/adminconsole/assets/menuicons/'; // local icons base

    // ---- tiny DB helpers ----
    $table_exists = function(mysqli $con, string $t): bool {
      $t = mysqli_real_escape_string($con, $t);
      $r = mysqli_query($con, "SHOW TABLES LIKE '$t'");
      return ($r && mysqli_num_rows($r) > 0);
    };
    $col_exists = function(mysqli $con, string $t, string $c): bool {
      $t = mysqli_real_escape_string($con, $t);
      $c = mysqli_real_escape_string($con, $c);
      $r = mysqli_query($con, "SHOW COLUMNS FROM `$t` LIKE '$c'");
      return ($r && mysqli_num_rows($r) > 0);
    };

    // ---- resolve role if missing ----
    $resolve_role = function(mysqli $con, int $user_id) use ($table_exists, $col_exists): ?array {
      if ($user_id <= 0) return null;
      if (!$table_exists($con, 'jos_admin_users_roles') || !$table_exists($con, 'jos_admin_roles')) return null;
      if (!$col_exists($con, 'jos_admin_users_roles', 'user_id') || !$col_exists($con, 'jos_admin_users_roles', 'role_id')) return null;
      if (!$col_exists($con, 'jos_admin_roles', 'id') || !$col_exists($con, 'jos_admin_roles', 'name')) return null;

      $has_status  = $col_exists($con, 'jos_admin_roles', 'status');
      $has_orderby = $col_exists($con, 'jos_admin_roles', 'orderby');

      $sql = "
        SELECT r.id AS role_id, r.name AS role_name
        FROM jos_admin_users_roles ur
        JOIN jos_admin_roles r ON r.id = ur.role_id
        WHERE ur.user_id = ?
      ";
      if ($has_status)  $sql .= " AND r.status = 1";
      if ($has_orderby) $sql .= " ORDER BY r.orderby ASC, r.id ASC";
      else              $sql .= " ORDER BY r.id ASC";
      $sql .= " LIMIT 1";

      $st = $con->prepare($sql);
      $st->bind_param('i', $user_id);
      $st->execute();
      $res = $st->get_result();
      $row = $res->fetch_assoc();
      $st->close();
      return $row ?: null;
    };

    // ---- STRICT ACL fetcher (MAIN MENU ONLY) ----
    $fetch_sections_strict = function(mysqli $con, array $user) use ($table_exists, $col_exists): array {
      $role_id = (int)($user['role_id'] ?? 0);
      if ($role_id <= 0) {
        $maybe = ($user['id'] ?? 0) ? ($GLOBALS['__role_cache'] ?? null) : null;
        if (!$maybe && isset($user['id'])) {
          $maybe = ($GLOBALS['__role_cache'] = (function() use ($con, $user, $table_exists, $col_exists) {
            $has_tbl_ur = $table_exists($con, 'jos_admin_users_roles');
            $has_tbl_r  = $table_exists($con, 'jos_admin_roles');
            if (!$has_tbl_ur || !$has_tbl_r) return null;
            $st = $con->prepare("
              SELECT r.id AS role_id, r.name AS role_name
              FROM jos_admin_users_roles ur
              JOIN jos_admin_roles r ON r.id = ur.role_id
              WHERE ur.user_id = ?
              ORDER BY r.id ASC
              LIMIT 1
            ");
            $uid = (int)$user['id'];
            $st->bind_param('i', $uid);
            $st->execute(); $res = $st->get_result(); $row = $res->fetch_assoc(); $st->close();
            return $row ?: null;
          })());
        }
        if (!empty($maybe['role_id'])) $role_id = (int)$maybe['role_id'];
      }
      if ($role_id <= 0) return [];

      // Ensure ACL tables/columns exist
      if (!$table_exists($con, 'jos_admin_rolemenus')) return [];
      foreach (['menu_id','role_id','can_view'] as $c) if (!$col_exists($con, 'jos_admin_rolemenus', $c)) return [];

      if (!$table_exists($con, 'jos_admin_menus')) return [];
      foreach (['id','menu_name','menu_link','icon','parent_id','orderby','status'] as $c) if (!$col_exists($con, 'jos_admin_menus', $c)) return [];
      $has_section = $col_exists($con, 'jos_admin_menus', 'section');

      // *** CHANGED: restrict to MAIN items only (parent_id = 0) ***
      $sql = "
        SELECT 
          m.id, m.menu_name, m.menu_link, m.icon, m.parent_id, m.orderby,
          " . ($has_section
                ? "COALESCE(m.section, 'Main')"
                : "'Main'"
              ) . " AS section
        FROM jos_admin_menus m
        INNER JOIN jos_admin_rolemenus rm
                ON rm.menu_id = m.id
               AND rm.can_view = 1
        WHERE m.status = 1
          AND rm.role_id = ?
          AND m.parent_id = 0
        ORDER BY m.orderby ASC, m.menu_name ASC
      ";
      $st = $con->prepare($sql);
      $st->bind_param('i', $role_id);
      $st->execute();
      $res  = $st->get_result();

      // Group by section (no children/submenus)
      $sections = [];
      while ($r = $res->fetch_assoc()) {
        $sec = $r['section'] ?: 'Main';
        $sections[$sec][] = $r;
      }
      $st->close();
      return $sections;
    };

    // ---- Build sections (strict) ----
    $sections = [];
    if ($con instanceof mysqli) {
      $sections = $fetch_sections_strict($con, $user ?? []);
    }

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title)?> • Admin Console</title>
<style>
  .content .logout-top{display:none !important}
  :root{--bg:#0f172a;--panel:#111827;--text:#e5e7eb;--muted:#9ca3af;--brand:#60a5fa}
  *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font:500 15px/1.45 system-ui,Segoe UI,Roboto,Arial}

  /* --- Topbar --- */
  .topbar{position:sticky;top:0;z-index:50;display:flex;align-items:center;gap:12px;
          padding:10px 14px;background:#1e293b;border-bottom:1px solid rgba(255,255,255,.06)}
  .topbar .grow{flex:1}
  .hamb{border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.06);border-radius:10px;padding:8px 10px;cursor:pointer}
  .logout-top{background:#ef4444;color:#fff;text-decoration:none;padding:8px 14px;border-radius:6px;font-weight:600}

  /* --- Layout (desktop default: OPEN) --- */
  .layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
  .sidebar{background:var(--panel);padding:18px;border-right:1px solid rgba(255,255,255,.08);transition:width .25s}
  .sidebar .logo{display:flex;align-items:center;gap:10px;margin-bottom:16px}
  .sidebar .logo img{height:32px}
  .brand-text{font-weight:700}
  .nav-title{color:var(--muted);font-size:12px;margin:12px 2px 6px}
  .nav a{display:flex;gap:10px;align-items:center;color:var(--text);text-decoration:none;padding:8px 10px;border-radius:8px}
  .nav a:hover{background:rgba(255,255,255,.06)}
  .nav .ico{width:20px;height:20px;object-fit:contain}
  .label{display:inline}
  .content{padding:16px}

  /* Desktop collapse */
  body.sidebar-collapsed .layout{grid-template-columns:70px 1fr}
  body.sidebar-collapsed .sidebar{width:70px;overflow:hidden}
  body.sidebar-collapsed .brand-text,
  body.sidebar-collapsed .label{display:none}

  /* --- Mobile drawer --- */
  @media (max-width: 768px){
    .layout{grid-template-columns:1fr}
    .sidebar{position:fixed;inset:0 auto 0 0;width:80%;max-width:320px;transform:translateX(-100%);z-index:60;box-shadow:20px 0 40px rgba(0,0,0,.45)}
    body.sidebar-open .sidebar{transform:translateX(0)}
    .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:55}
    body.sidebar-open .overlay{display:block}
  }
</style>
</head>
<style id="cards-grid-css">
  /* --- Dashboard/Masters cards --- */
  .grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr))}
  .card{background:#1e293b;border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:16px;box-shadow:0 8px 30px rgba(0,0,0,.18)}
  .card h2{margin:0 0 6px 0;font-size:28px}
  .card .muted{color:#9ca3af;font-size:13px}
  .btn{display:inline-flex;align-items:center;gap:8px;background:#1e293b;color:#fff;text-decoration:none;border:1px solid rgba(255,255,255,.14);padding:8px 12px;border-radius:10px}
  .btn.primary{background:linear-gradient(90deg,#60a5fa,#22d3ee);border-color:transparent}
  .masters-list .card{padding:18px}
  .masters-list .card h3{margin:0 0 6px}
  .masters-list .card p{margin:0 0 10px;color:#9ca3af}
  @media(max-width:768px){ .grid{gap:12px} .card{padding:14px} }
  .nav .ico {
  filter: brightness(0) invert(1);
}
</style>
<body class="sidebar-collapsed">
  <div class="topbar">
    <button id="menuToggle" class="hamb" aria-label="Toggle menu">☰</button>
    <div class="grow"></div>
    <span>Hello, <?=htmlspecialchars($userName)?></span>
    <a href="/adminconsole/logout.php" class="logout-top">Logout</a>
  </div>

  <div class="layout">
    <aside class="sidebar">
      <div class="logo">
       <a href="/adminconsole/index.php" ><img src="/adminconsole/uploads/logo.png" alt="Admin Console"></a>
        <span class="brand-text"></span>
      </div>

      <?php if (!empty($sections)): ?>
        <?php foreach ($sections as $sec => $roots): ?>
          <div class="nav-title"><?= htmlspecialchars($sec) ?></div>
          <nav class="nav">
            <?php foreach ($roots as $r): ?>
              <a href="<?= htmlspecialchars($r['menu_link']) ?>">
                <?php if (!empty($r['icon'])): ?>
                  <img class="ico" src="<?= htmlspecialchars($iconBase . $r['icon']) ?>" alt="">
                <?php else: ?>
                  <span class="ico">🏷️</span>
                <?php endif; ?>
                <span class="label"><?= htmlspecialchars($r['menu_name']) ?></span>
              </a>
              <!-- Submenu intentionally not rendered -->
            <?php endforeach; ?>
          </nav>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="nav-title">Main</div>
        <nav class="nav">
          <span class="label" style="color:#9ca3af">No menus available for your role.</span>
        </nav>
      <?php endif; ?>

      <div class="nav-title">Account</div>
      <nav class="nav">
<a href="/adminconsole/logout.php">
  <img 
    src="/adminconsole/assets/menuicons/logout.png" 
    style="width:20px; height:23px; object-fit:contain;" 
    alt=""
  >
  <span class="label">Logout</span>
</a>      </nav>
    </aside>

    <main class="content">
      <?=$CONTENT?>
    </main>
  </div>

  <div id="overlay" class="overlay"></div>
  <script>
    // Sidebar toggle (desktop collapse / mobile drawer)
    const btn=document.getElementById('menuToggle'), ov=document.getElementById('overlay');
    function toggle(){
      if(window.innerWidth>768){
        document.body.classList.toggle('sidebar-collapsed');
      }else{
        document.body.classList.toggle('sidebar-open');
      }
    }
    btn && btn.addEventListener('click', toggle);
    ov  && ov.addEventListener('click', ()=>document.body.classList.remove('sidebar-open'));

    // Auto-highlight active link
    (function(){
      var p = location.pathname.replace(/\/$/,"");
      document.querySelectorAll(".nav a").forEach(function(a){
        var h = a.getAttribute("href"); if(!h) return;
        var u = document.createElement("a"); u.href = h;
        var hp = u.pathname.replace(/\/$/,"");
        if (p===hp || p.indexOf(hp+"/")===0){ a.classList.add("active"); }
      });
    })();
  </script>
</body>
</html>
<?php
  });
}}
