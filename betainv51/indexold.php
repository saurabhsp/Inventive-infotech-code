<?php
/* ============================================================
   Dashboard (index.php) — Role-driven, table-configurable
   ============================================================ */

// -------- DEV ERROR VISIBILITY (disable display on production) --------
@ini_set('display_errors','1');
@ini_set('display_startup_errors','1');
@error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// -------- Auth bootstrap --------
require_once __DIR__ . '/includes/auth.php';
require_login();

$u = current_user(); // expects ['id','name', maybe role info]
date_default_timezone_set('Asia/Kolkata');

/* ============================================================
   Safe helpers
   ============================================================ */
function table_exists(mysqli $con, string $table): bool {
  $t = $con->real_escape_string($table);
  $r = $con->query("SHOW TABLES LIKE '$t'");
  return ($r && $r->num_rows > 0);
}
function col_exists(mysqli $con, string $table, string $col): bool {
  $t = $con->real_escape_string($table);
  $c = $con->real_escape_string($col);
  $r = $con->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return ($r && $r->num_rows > 0);
}
function qcount(mysqli $con, string $sql): int {
  $r = @$con->query($sql);
  if ($r && ($row = $r->fetch_row())) return (int)$row[0];
  return 0;
}

/* ============================================================
   Build STATS first (prevents undefined/TypeError)
   ============================================================ */
$stats = [
  'job_seekers'        => 0,
  'recruiters'         => 0,
  'promoters'          => 0,
  'premium_jobs'       => 0,
  'standard_jobs'      => 0,
  'applications_today' => 0,
  'total_applications' => 0,
  'subscriptions'      => 0,
  'revenue'            => 0.0,
];

if (isset($con) && $con instanceof mysqli) {
  // Users by profile_type (1=recruiter, 2=job seeker, 3=promoter)
  if (table_exists($con,'jos_app_users') && col_exists($con,'jos_app_users','profile_type_id')) {
    $r = $con->query("SELECT profile_type_id, COUNT(*) cnt FROM jos_app_users GROUP BY profile_type_id");
    if ($r) while ($row = $r->fetch_assoc()) {
      $pt = (int)$row['profile_type_id'];
      if ($pt === 1) $stats['recruiters'] = (int)$row['cnt'];
      if ($pt === 2) $stats['job_seekers'] = (int)$row['cnt'];
      if ($pt === 3) $stats['promoters']  = (int)$row['cnt'];
    }
  }

  // Premium jobs (walk-ins)
  if (table_exists($con,'jos_app_walkininterviews')) {
    $has_status = col_exists($con,'jos_app_walkininterviews','job_status_id');
    $where = $has_status ? "WHERE COALESCE(job_status_id,0) NOT IN (5,6)" : "";
    $stats['premium_jobs'] = qcount($con, "SELECT COUNT(*) FROM jos_app_walkininterviews $where");
  }

  // Standard jobs (vacancies)
  if (table_exists($con,'jos_app_jobvacancies')) {
    $conds = [];
    if (col_exists($con,'jos_app_jobvacancies','validity_apply') && col_exists($con,'jos_app_jobvacancies','valid_till_date')) {
      $conds[] = "(COALESCE(validity_apply,0)=0 OR (COALESCE(validity_apply,0)=1 AND valid_till_date >= CURDATE()))";
    }
    if (col_exists($con,'jos_app_jobvacancies','job_status_id')) {
      $conds[] = "COALESCE(job_status_id,0) NOT IN (5,6)";
    }
    $where = $conds ? ("WHERE ".implode(" AND ", $conds)) : "";
    $stats['standard_jobs'] = qcount($con, "SELECT COUNT(*) FROM jos_app_jobvacancies $where");
  }

  // Applications
  if (table_exists($con,'jos_app_applications')) {
    $dateCol = null;
    if (col_exists($con,'jos_app_applications','application_date')) $dateCol = "DATE(application_date)";
    elseif (col_exists($con,'jos_app_applications','created_at'))    $dateCol = "DATE(created_at)";
    if ($dateCol) {
      $today = date('Y-m-d');
      $stats['applications_today'] = qcount($con, "SELECT COUNT(*) FROM jos_app_applications WHERE $dateCol = '$today'");
    }
    $stats['total_applications'] = qcount($con, "SELECT COUNT(*) FROM jos_app_applications");
  }

  // Subscriptions + revenue
  if (table_exists($con,'jos_app_usersubscriptionlog')) {
    $conds = [];
    if (col_exists($con,'jos_app_usersubscriptionlog','end_date'))       $conds[] = "end_date >= CURDATE()";
    if (col_exists($con,'jos_app_usersubscriptionlog','payment_status')) $conds[] = "payment_status='success'";
    $where = $conds ? ("WHERE ".implode(" AND ", $conds)) : "";
    $r = $con->query("SELECT COUNT(*) c, COALESCE(SUM(amount_paid),0) s FROM jos_app_usersubscriptionlog $where");
    if ($r && ($row = $r->fetch_assoc())) {
      $stats['subscriptions'] = (int)$row['c'];
      $stats['revenue']       = (float)$row['s'];
    }
  }
}

/* ============================================================
   Role from tables (no hard-coded IDs)
   - If you add optional columns: code, dashboard_profile, dashboard_cards
   - Logic will automatically use them.
   ============================================================ */
function pacific_role_row(mysqli $con, int $user_id): ?array {
  if ($user_id <= 0) return null;
  if (!table_exists($con,'jos_admin_users_roles') || !table_exists($con,'jos_admin_roles')) return null;
  if (!col_exists($con,'jos_admin_users_roles','user_id') || !col_exists($con,'jos_admin_users_roles','role_id')) return null;

  $has_status   = col_exists($con,'jos_admin_roles','status');
  $has_orderby  = col_exists($con,'jos_admin_roles','orderby');
  $has_code     = col_exists($con,'jos_admin_roles','code');               // optional
  $has_profile  = col_exists($con,'jos_admin_roles','dashboard_profile');  // optional
  $has_cards    = col_exists($con,'jos_admin_roles','dashboard_cards');    // optional

  $cols = ['r.id AS role_id','r.name AS role_name'];
  if ($has_code)    $cols[] = 'r.code AS role_code';
  if ($has_profile) $cols[] = 'r.dashboard_profile AS dashboard_profile';
  if ($has_cards)   $cols[] = 'r.dashboard_cards AS dashboard_cards';

  $sql = "SELECT ".implode(',', $cols)."
          FROM jos_admin_users_roles ur
          JOIN jos_admin_roles r ON r.id = ur.role_id
          WHERE ur.user_id = ?";
  if ($has_status) $sql .= " AND r.status = 1";
  $sql .= " ORDER BY ".($has_orderby ? "r.orderby ASC, r.id ASC" : "r.id ASC")." LIMIT 1";

  $st = $con->prepare($sql);
  $st->bind_param('i', $user_id);
  $st->execute();
  $res = $st->get_result();
  $row = $res->fetch_assoc();
  $st->close();
  return $row ?: null;
}

function pacific_role_key_from_row(?array $r): string {
  if (!$r) return 'role-0';
  $id = (int)($r['role_id'] ?? 0);
  return 'role-'.$id; // stable, based on numeric id
}


function pacific_stat_labels(): array {
  return [
    'job_seekers'        => 'Job Seekers',
    'recruiters'         => 'Recruiters',
    'promoters'          => 'Promoters',
    'standard_jobs'      => 'Standard Jobs',
    'premium_jobs'       => 'Premium Jobs',
    'applications_today' => 'Applications Today',
    'total_applications' => 'Total Applications',
    'subscriptions'      => 'Active Subscriptions',
    'revenue'            => 'Revenue (Active Subs)',
  ];
}

/* ---- If roles.dashboard_cards (JSON) exists, use it to render tiles ----
   Example JSON:
   ["standard_jobs","premium_jobs","applications_today","total_applications","revenue"]
   or
   [{"key":"revenue","label":"Revenue ₹"}, "subscriptions", "applications_today"]
*/
function pacific_cards_from_table_config(?string $json, array $stats): ?array {
  if ($json === null || trim($json) === '') return null;
  $data = json_decode($json, true);
  if (!is_array($data)) return null;

  $labels = pacific_stat_labels();
  $cards  = [];

  foreach ($data as $item) {
    if (is_string($item)) {
      $key = $item;
      if (!array_key_exists($key, $stats)) continue;
      $val = $key === 'revenue' ? "₹".number_format((float)($stats[$key]??0),2) : ($stats[$key]??0);
      $cards[] = ['value'=>$val, 'label'=>$labels[$key] ?? ucfirst(str_replace('_',' ',$key))];
    } elseif (is_array($item) && isset($item['key'])) {
      $key = (string)$item['key'];
      if (!array_key_exists($key, $stats)) continue;
      $label = isset($item['label']) ? (string)$item['label'] : ($labels[$key] ?? ucfirst(str_replace('_',' ',$key)));
      $val = $key === 'revenue' ? "₹".number_format((float)($stats[$key]??0),2) : ($stats[$key]??0);
      $cards[] = ['value'=>$val, 'label'=>$label];
    }
  }
  return $cards ?: null;
}

/* ---- Fallback presets by role key (no hard-coded IDs needed) ---- */
function pacific_cards_by_role_key(string $role_key, array $stats): array {
  $money = "₹".number_format((float)($stats['revenue'] ?? 0), 2);

  // role_key format: role-<id>
  $role_id = 0;
  if (preg_match('/^role-(\d+)$/', $role_key, $m)) $role_id = (int)$m[1];

  // Put your REAL role IDs here (stable)
  $ADMIN_ROLE_IDS   = [1];        // example: SuperAdmin=1
  $OPS_ROLE_IDS     = [2,3,4];    // example: Operations/Support etc.

  if (in_array($role_id, $ADMIN_ROLE_IDS, true)) {
    return [
      ['value'=>$stats['job_seekers']??0,        'label'=>'Job Seekers'],
      ['value'=>$stats['recruiters']??0,         'label'=>'Recruiters'],
      ['value'=>$stats['promoters']??0,          'label'=>'Promoters'],
      ['value'=>$stats['standard_jobs']??0,      'label'=>'Standard Jobs'],
      ['value'=>$stats['premium_jobs']??0,       'label'=>'Premium Jobs'],
      ['value'=>$stats['applications_today']??0, 'label'=>'Applications Today'],
      ['value'=>$stats['total_applications']??0, 'label'=>'Total Applications'],
      ['value'=>$stats['subscriptions']??0,      'label'=>'Active Subscriptions'],
      ['value'=>$money,                          'label'=>'Revenue (Active Subs)'],
    ];
  }

  if (in_array($role_id, $OPS_ROLE_IDS, true)) {
    return [
      ['value'=>$stats['applications_today']??0, 'label'=>'Apps Today'],
      ['value'=>$stats['total_applications']??0, 'label'=>'Total Applications'],
      ['value'=>$stats['standard_jobs']??0,      'label'=>'Open Vacancies'],
      ['value'=>$stats['premium_jobs']??0,       'label'=>'Walk-ins Live'],
    ];
  }

  return [
    ['value'=>$stats['applications_today']??0, 'label'=>'Apps Today'],
    ['value'=>$stats['total_applications']??0, 'label'=>'Total Applications'],
  ];



  // Ops / Outreach / Support
  if (in_array($role_key, ['outreach-executive','operations','support'], true)) {
    return [
      ['value'=>$stats['applications_today']??0, 'label'=>'Apps Today'],
      ['value'=>$stats['total_applications']??0, 'label'=>'Total Applications'],
      ['value'=>$stats['standard_jobs']??0,      'label'=>'Open Vacancies'],
      ['value'=>$stats['premium_jobs']??0,       'label'=>'Walk-ins Live'],
    ];
  }

  // Default minimal
  return [
    ['value'=>$stats['applications_today']??0, 'label'=>'Apps Today'],
    ['value'=>$stats['total_applications']??0, 'label'=>'Total Applications'],
  ];
}

/* ============================================================
   Build cards from role (table-driven, then fallback)
   ============================================================ */
$role_row = (isset($con) && $con instanceof mysqli) ? pacific_role_row($con, (int)($u['id'] ?? 0)) : null;
$role_key = pacific_role_key_from_row($role_row);

$cards = null;
if ($role_row && array_key_exists('dashboard_cards', $role_row)) {
  $cards = pacific_cards_from_table_config($role_row['dashboard_cards'], $stats);
}
if (!$cards) {
  $cards = pacific_cards_by_role_key($role_key, $stats);
}

/* ============================================================
   Render
   ============================================================ */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/adminconsole/assets/ui.css">
<style>
  body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:#0f172a;color:#e2e8f0;margin:0}
  .top{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:#111827;border-bottom:1px solid #1f2937}
  .wrap{padding:22px;display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px}
  .card{background:#111827;padding:18px;border-radius:16px;border:1px solid #1f2937;text-align:center}
  .card h2{margin:0;font-size:28px}
  .card span{font-size:14px;color:#9ca3af}
  .btn{display:inline-block;padding:8px 12px;background:#3b82f6;color:#fff;border-radius:10px;text-decoration:none;font-weight:600}
  .foot{padding:0 22px 22px}
  .note{max-width:960px;margin:0 22px 22px;background:#0b1220;border:1px solid #1f2937;border-radius:14px;padding:14px;color:#9ca3af}
  .note code{background:#0a0f1a;padding:2px 6px;border-radius:6px;color:#cbd5e1}
</style>
</head>
<body>
  <div class="top">
    <div><strong>Pacific Admin</strong></div>
    <div>Hello, <?= htmlspecialchars($u['name'] ?? 'User') ?></div>
  </div>

  <div class="wrap">
    <?php foreach ($cards as $c): ?>
      <div class="card">
        <h2><?= is_numeric($c['value']) ? (int)$c['value'] : htmlspecialchars((string)$c['value']) ?></h2>
        <span><?= htmlspecialchars((string)$c['label']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>



  <div class="foot">
    <a class="btn" href="/adminconsole/masters/index.php">Open Masters</a>
    <a class="btn" href="/adminconsole/operations/index.php" style="margin-left:8px">Open Operations</a>
    <a class="btn" href="/adminconsole/reports/index.php" style="margin-left:8px">Open Reports</a>
  </div>
</body>
</html>
