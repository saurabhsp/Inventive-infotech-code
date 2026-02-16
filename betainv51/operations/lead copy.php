<?php
@ini_set('display_errors', '1');
@error_reporting(E_ALL);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/initialize.php'; // $con, csrf_token(), verify_csrf()
require_login();
/* ============================================================
   lead.php — CRM Leads (UPDATED)
   FIXES ADDED NOW:
   ✅ On-boarded plan dropdown loads profile_type-wise (Employer/Jobseeker)
      - supports common plans where profile_type=0 too (shown for both)
   ✅ Same filtering for Status-Update modal
   ✅ Server-side validation also checks plan belongs to lead profile_type (or 0)
   ✅ bind_param mismatch-proof helper (keeps your debug clear)
   ✅ Flatpickr dd-mm-yyyy hh:mm AM/PM
   ✅ No ".card" usage
   ============================================================ */


/* ---------------- Tables ---------------- */
$TABLE     = 'jos_app_crm_leads';
$STATUSTBL = 'jos_app_crm_lead_statuses';
$SOURCETBL = 'jos_app_crm_lead_sources';
$PLANTBL   = 'jos_app_subscription_plans';
$HISTTBL   = 'jos_app_crm_lead_status_history';

/* ---------------- Helpers ---------------- */
function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function clean($v)
{
  return trim((string)$v);
}
function table_exists(mysqli $con, string $name): bool
{
  $name = mysqli_real_escape_string($con, $name);
  $rs = mysqli_query($con, "SHOW TABLES LIKE '$name'");
  return ($rs && mysqli_num_rows($rs) > 0);
}
function keep_params(array $changes = [])
{
  $qs = $_GET;
  foreach ($changes as $k => $v) {
    if ($v === null) unset($qs[$k]);
    else $qs[$k] = $v;
  }
  $q = http_build_query($qs);
  if ($q) return '?' . $q;
  $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? basename(__FILE__));
  return $script;
}
function fmt_date($dt)
{
  return $dt ? date('d-m-Y', strtotime($dt)) : '—';
}
function fmt_dt($dt)
{
  return $dt ? date('d-m-Y h:i A', strtotime($dt)) : '—';
}

/* dd-mm-yyyy hh:mm AM/PM => Y-m-d H:i:s */
function parse_followup_to_db($v)
{
  $v = trim((string)$v);
  if ($v === '') return null;
  $dt = DateTime::createFromFormat('d-m-Y h:i A', $v);
  if (!$dt) $dt = DateTime::createFromFormat('d-m-Y h:i a', $v);
  if (!$dt) return null;
  return $dt->format('Y-m-d H:i:s');
}

/* Safe bind helper */
function stmt_bind(mysqli_stmt $st, string $types, array $params): void
{
  if (strlen($types) !== count($params)) {
    throw new RuntimeException("bind_param mismatch: types=" . strlen($types) . " vars=" . count($params));
  }
  $refs = [];
  foreach ($params as $k => $v) $refs[$k] = &$params[$k];
  array_unshift($refs, $types);
  call_user_func_array([$st, 'bind_param'], $refs);
}

/* ---------------- Menu + ACL ---------------- */
$page_title = 'Lead Entry';
/* =========================
   Current menu (for title + ACL)
========================= */
$MENUTBL  = 'jos_admin_menus';

function current_script_paths(): array
{
  $script = $_SERVER['SCRIPT_NAME'] ?? '';
  $req    = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: $script;

  $variants = [];
  $variants[] = $script;
  $variants[] = $req;
  $variants[] = preg_replace('#^/beta#', '', $script);
  $variants[] = preg_replace('#^/beta#', '', $req);

  return array_values(array_unique(array_filter($variants)));
}

function fetch_menu_info(mysqli $con, string $MENUTBL): array
{
  $out = ['id' => 0, 'menu_name' => '', 'menu_link' => ''];
  if (!table_exists($con, $MENUTBL)) return $out;

  $paths = current_script_paths();

  foreach ($paths as $p) {
    $sql = "SELECT id, menu_name, menu_link FROM `$MENUTBL` WHERE menu_link=? LIMIT 1";
    if ($st = $con->prepare($sql)) {
      $st->bind_param('s', $p);
      $st->execute();
      if ($r = $st->get_result()->fetch_assoc()) {
        $st->close();
        return $r;
      }
      $st->close();
    }
  }
  return $out;
}

$menu = fetch_menu_info($con, $MENUTBL);
$page_title = $menu['menu_name'] ?: $page_title;
$MENU_ID    = (int)($menu['id'] ?? 0);



/* =========================
   Access control
========================= */
/* =========================
   Access control
========================= */
/**
 * user_can($action, $menu_id)
 * $action: 'view' | 'add' | 'edit' | 'delete'
 * Strategy:
 *   1) If $_SESSION['user_permissions'] exists, try by menu_id or link key.
 *   2) Else DB fallback: jos_admin_rolemenus (preferred) or jos_admin_role_menu.
 *      Expect columns: role_id, menu_id, can_view, can_add, can_edit, can_delete
 *   3) Default deny if nothing found.
 */
function user_can(string $action, int $menu_id, mysqli $con): bool {
  $action = strtolower($action);
  $valid  = ['view','add','edit','delete'];
  if (!in_array($action, $valid, true)) return false;

  // 1) Session-based
  $sess = $_SESSION ?? [];
  if (!empty($sess['user_permissions']) && is_array($sess['user_permissions'])) {
    // try by menu_id
    if ($menu_id && isset($sess['user_permissions'][$menu_id][$action])) {
      return (bool)$sess['user_permissions'][$menu_id][$action];
    }
    // optional: try by current script path key if present
    $paths = current_script_paths();
    foreach ($paths as $k) {
      if (isset($sess['user_permissions'][$k][$action])) return (bool)$sess['user_permissions'][$k][$action];
    }
  }

  // 2) DB fallback
  $me = $sess['admin_user'] ?? [];
  $role_id = (int)($me['role_id'] ?? 0);
  if ($role_id <= 0 || $menu_id <= 0) return false;

  // choose mapping table
  $MAP1 = 'jos_admin_rolemenus';
  $MAP2 = 'jos_admin_role_menu';
  $mapTable = table_exists($con, $MAP1) ? $MAP1 : (table_exists($con, $MAP2) ? $MAP2 : '');

  if ($mapTable === '') return false;

  $cols = ['view'=>'can_view','add'=>'can_add','edit'=>'can_edit','delete'=>'can_delete'];
  $col  = $cols[$action];

  $sql = "SELECT $col AS allowed FROM `$mapTable` WHERE role_id=? AND menu_id=? LIMIT 1";
  if ($st = $con->prepare($sql)) {
    $st->bind_param('ii', $role_id, $menu_id);
    $st->execute();
    $res = $st->get_result()->fetch_assoc();
    $st->close();
    return (isset($res['allowed']) && (int)$res['allowed'] === 1);
  }
  return false;
}

/* Gate "view" before doing anything else 
if (!user_can('view', $MENU_ID, $con)) {
  http_response_code(403);
?>
  <!doctype html>
  <meta charset="utf-8">
  <link rel="stylesheet" href="/adminconsole/assets/ui.css">
  <div class="card" style="max-width:680px;margin:24px auto">
    <h3 style="margin-top:0"><?= h($page_title) ?></h3>
    <div class="badge off" style="margin:8px 0">You are not authorized to view this content.</div>
  </div>
<?php
  exit;
} */


/* ---------------- Flash redirect ---------------- */
function flash_redirect(string $msg = 'Saved')
{
  $qs = $_GET;
  unset($qs['add'], $qs['edit']);
  $qs['ok'] = $msg;
  header('Location: ?' . http_build_query($qs));
  exit;
}

/* ---------------- Load masters ---------------- */
$statuses = []; // id => ['name'=>, 'code'=>]
if (table_exists($con, $STATUSTBL)) {
  $rs = mysqli_query($con, "SELECT id,status_name,status_code FROM `$STATUSTBL` WHERE is_active=1 ORDER BY sort_order ASC, status_name ASC");
  while ($rs && $r = mysqli_fetch_assoc($rs)) {
    $statuses[(int)$r['id']] = ['name' => $r['status_name'], 'code' => $r['status_code']];
  }
}

$sources = []; // id => name
if (table_exists($con, $SOURCETBL)) {
  $rs = mysqli_query($con, "SELECT id,source_name FROM `$SOURCETBL` WHERE is_active=1 ORDER BY sort_order ASC, source_name ASC");
  while ($rs && $r = mysqli_fetch_assoc($rs)) {
    $sources[(int)$r['id']] = $r['source_name'];
  }
}

/* Plans: keep profile_type for filtering (0 = common) */
$plans = []; // id(string) => ['name'=>..., 'ptype'=>int]
$plansByType = [0 => [], 1 => [], 2 => []]; // for JS
if (table_exists($con, $PLANTBL)) {
  $rs = mysqli_query($con, "SELECT id,profile_type,plan_name FROM `$PLANTBL` WHERE plan_status=1 ORDER BY profile_type ASC, plan_name ASC");
  while ($rs && $r = mysqli_fetch_assoc($rs)) {
    $pid = (string)$r['id'];
    $ptype = (int)$r['profile_type'];
    $pname = (string)$r['plan_name'];
    $plans[$pid] = ['name' => $pname, 'ptype' => $ptype];

    // keep only types we need for dropdown: common(0), employer(1), jobseeker(2)
    if ($ptype === 0 || $ptype === 1 || $ptype === 2) {
      $plansByType[$ptype][] = ['id' => $pid, 'name' => $pname, 'ptype' => $ptype];
    }
  }
}

/* admin users for assignment */
$ADMINUSERS = table_exists($con, 'jos_admin_users') ? 'jos_admin_users' : (table_exists($con, 'jos_admin') ? 'jos_admin' : '');
$adminUsers = []; // id=>name
if ($ADMINUSERS) {
  $cols = [];
  $cr = mysqli_query($con, "SHOW COLUMNS FROM `$ADMINUSERS`");
  while ($cr && $c = mysqli_fetch_assoc($cr)) $cols[] = $c['Field'];
  $nameCol = in_array('name', $cols, true) ? 'name' : (in_array('username', $cols, true) ? 'username' : 'id');
  $statusCol = in_array('status', $cols, true) ? 'status' : (in_array('is_active', $cols, true) ? 'is_active' : '');
  $sql = "SELECT id, `$nameCol` AS nm FROM `$ADMINUSERS`" . ($statusCol ? (" WHERE `$statusCol`=1") : '') . " ORDER BY `$nameCol` ASC";
  $rs = mysqli_query($con, $sql);
  while ($rs && $r = mysqli_fetch_assoc($rs)) $adminUsers[(int)$r['id']] = $r['nm'];
}

$me = $_SESSION['admin_user'] ?? [];
$MY_ID = (int)($me['id'] ?? 0);

/* ---------------- Plan allowed check ---------------- */
function plan_allowed_for_profile(array $plans, ?string $plan_id, int $profile_type): bool
{
  if (!$plan_id) return false;
  if (!isset($plans[$plan_id])) return false;
  $ptype = (int)($plans[$plan_id]['ptype'] ?? -999);
  // allow exact match OR common (0)
  return ($ptype === 0 || $ptype === $profile_type);
}

/* ---------------- POST handlers ---------------- */
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $isModal = isset($_POST['status_update']);

  if (!verify_csrf($_POST['csrf'] ?? null)) {
    if ($isModal) {
      header('Content-Type: application/json');
      echo json_encode(['ok' => false, 'msg' => 'Invalid request.']);
      exit;
    }
    $err = 'Invalid request.';
  } else {

    /* ===== Quick Status Update (modal) ===== */
    if ($isModal) {
      if (!user_can('edit',$MENU_ID,$con)) {
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'msg'=>'You are not authorized to edit.']);
        exit;
      }
      $lead_id   = (int)($_POST['lead_id'] ?? 0);
      $to_status = (int)($_POST['to_status_id'] ?? 0);
      $remark    = trim($_POST['remark'] ?? '');
      $followup  = trim($_POST['followup_at'] ?? '');
      $plan_id   = trim($_POST['onboarded_plan_id'] ?? '0'); // string

      if ($lead_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'Invalid lead id.']);
        exit;
      }
      if ($to_status <= 0 || !isset($statuses[$to_status])) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'Invalid status.']);
        exit;
      }
      if ($remark === '') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'Remark is required.']);
        exit;
      }

      $st = $con->prepare("SELECT id,profile_type,status_id FROM `$TABLE` WHERE id=? LIMIT 1");
      $st->bind_param('i', $lead_id);
      $st->execute();
      $lead = $st->get_result()->fetch_assoc();
      $st->close();
      if (!$lead) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'Lead not found.']);
        exit;
      }

      $lead_pt = (int)($lead['profile_type'] ?? 1);
      $from_status = (int)$lead['status_id'];
      $to_code = $statuses[$to_status]['code'] ?? '';

      $followup_db = null;
      $plan_db = null;
      $not_contactable_flag = 0;

      if ($to_code === 'FOLLOW_UP') {
        if ($followup === '') {
          header('Content-Type: application/json');
          echo json_encode(['ok' => false, 'msg' => 'Follow-up Date/Time is required.']);
          exit;
        }
        $followup_db = parse_followup_to_db($followup);
        if (!$followup_db) {
          header('Content-Type: application/json');
          echo json_encode(['ok' => false, 'msg' => 'Invalid follow-up date/time (use dd-mm-yyyy hh:mm AM/PM).']);
          exit;
        }
      } elseif ($to_code === 'ON_BOARDED') {
        if ($plan_id === '0' || $plan_id === '') {
          header('Content-Type: application/json');
          echo json_encode(['ok' => false, 'msg' => 'Please select On-boarded Plan.']);
          exit;
        }
        $plan_id = (string)$plan_id;
        if (!plan_allowed_for_profile($plans, $plan_id, $lead_pt)) {
          header('Content-Type: application/json');
          echo json_encode(['ok' => false, 'msg' => 'Selected plan is not valid for this profile type.']);
          exit;
        }
        $plan_db = $plan_id;
      } elseif ($to_code === 'NOT_CONTACTABLE') {
        $not_contactable_flag = 1;
      }

      if ($to_code !== 'FOLLOW_UP') $followup_db = null;
      if ($to_code !== 'ON_BOARDED') $plan_db = null;
      if ($to_code !== 'NOT_CONTACTABLE') $not_contactable_flag = 0;

      $sql = "UPDATE `$TABLE`
              SET status_id=?,
                  last_status_reason=?,
                  followup_at=?,
                  onboarded_plan_id=?,
                  not_contactable_flag=?
              WHERE id=?";
      $st = $con->prepare($sql);
      stmt_bind($st, "isssii", [$to_status, $remark, $followup_db, $plan_db, $not_contactable_flag, $lead_id]);
      $ok = $st->execute();
      $st->close();

      if (!$ok) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'Update failed.']);
        exit;
      }

      if (table_exists($con, $HISTTBL)) {
        $meta = ['followup_at' => $followup_db, 'onboarded_plan_id' => $plan_db, 'mode' => 'modal'];
        $meta_json = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $changed_by = $MY_ID ?: null;

        $stH = $con->prepare("INSERT INTO `$HISTTBL` (lead_id,from_status_id,to_status_id,changed_by,reason,meta_json)
                              VALUES (?,?,?,?,?,?)");
        stmt_bind($stH, "iiiiss", [$lead_id, $from_status, $to_status, $changed_by, $remark, $meta_json]);
        $stH->execute();
        $stH->close();
      }

      header('Content-Type: application/json');
      echo json_encode(['ok' => true, 'msg' => 'Status updated']);
      exit;
    }

    /* ===== Delete ===== */
    if (isset($_POST['delete'])) {

      if (!user_can('delete', $MENU_ID, $con)) {
        $err = 'You are not authorized to delete.';
      } else {
        $id = (int)($_POST['id'] ?? 0);
        $st = $con->prepare("DELETE FROM `$TABLE` WHERE id=?");
        $st->bind_param('i', $id);
        if ($st->execute()) {
          $st->close();
          flash_redirect('Deleted successfully');
        }
        $err = 'Delete failed';
        $st->close();
      }
    }


    /* ===== Full Save (Add/Edit) ===== */
    if (isset($_POST['save'])) {

      $id = (int)($_POST['id'] ?? 0);

      $profile_type   = (int)($_POST['profile_type'] ?? 1);
      $company_name   = clean($_POST['company_name'] ?? '');
      $owner_hr_name  = clean($_POST['owner_hr_name'] ?? '');
      $sector         = clean($_POST['sector'] ?? '');
      $candidate_name = clean($_POST['candidate_name'] ?? '');

      $phone1        = clean($_POST['phone1'] ?? '');
      $phone2        = clean($_POST['phone2'] ?? '');
      $email         = clean($_POST['email'] ?? '');
      $city_location = clean($_POST['city_location'] ?? '');

      $source_id     = (int)($_POST['source_id'] ?? 0);
      $status_id     = (int)($_POST['status_id'] ?? 0);
      $assigned_to   = (int)($_POST['assigned_to'] ?? 0);

      $reason        = trim($_POST['last_status_reason'] ?? '');
      $followup_ui   = trim($_POST['followup_at'] ?? '');
      $plan_in       = trim($_POST['onboarded_plan_id'] ?? '0'); // string id

      if ($id > 0 && !user_can('edit', $MENU_ID, $con)) {
        $err = 'You are not authorized to edit.';
      } elseif ($id === 0 && !user_can('add', $MENU_ID, $con)) {
        $err = 'You are not authorized to add.';
      }
      if (!in_array($profile_type, [1, 2], true)) {
        $err = 'Invalid profile type.';
      } elseif ($profile_type === 1 && $company_name === '') {
        $err = 'Company Name is required.';
      } elseif ($profile_type === 2 && $candidate_name === '') {
        $err = 'Candidate Name is required.';
      } elseif ($phone1 === '') {
        $err = 'Contact - 1 is required.';
      } elseif ($status_id <= 0 || !isset($statuses[$status_id])) {
        $err = 'Please select a valid Status.';
      } else {

        $status_code = $statuses[$status_id]['code'] ?? '';

        $followup_db = null;
        $plan_db = null;
        $not_contactable_flag = 0;

        if ($status_code === 'FOLLOW_UP') {
          if ($followup_ui === '') $err = 'Follow-up Date/Time is required.';
          $followup_db = parse_followup_to_db($followup_ui);
          if (!$followup_db) $err = $err ?: 'Invalid follow-up date/time (use dd-mm-yyyy hh:mm AM/PM).';
          if ($reason === '') $err = $err ?: 'Follow-up remark is required.';
        } elseif ($status_code === 'ON_BOARDED') {
          if ($plan_in === '0' || $plan_in === '') $err = 'Please select On-boarded Plan.';
          $plan_id = ($plan_in !== '0' && $plan_in !== '') ? (string)$plan_in : null;
          if ($plan_id && !plan_allowed_for_profile($plans, $plan_id, $profile_type)) {
            $err = 'Selected plan is not valid for this profile type.';
          } else {
            $plan_db = $plan_id;
          }
          if ($reason === '') $err = $err ?: 'Remark is required.';
        } elseif ($status_code === 'NOT_INTERESTED') {
          if ($reason === '') $err = 'Remark is required.';
        } elseif ($status_code === 'NOT_CONTACTABLE') {
          if ($reason === '') $err = 'Remark is required.';
          $not_contactable_flag = 1;
        }

        if ($status_code !== 'FOLLOW_UP') $followup_db = null;
        if ($status_code !== 'ON_BOARDED') $plan_db = null;
        if ($status_code !== 'NOT_CONTACTABLE') $not_contactable_flag = 0;

        if ($err === '') {

          if ($profile_type === 1) {
            $candidate_name = '';
          } else {
            $company_name = '';
            $owner_hr_name = '';
            $sector = '';
          }

          $old = null;
          if ($id > 0) {
            $st = $con->prepare("SELECT assigned_to,assigned_at,prev_assigned_to,status_id FROM `$TABLE` WHERE id=?");
            $st->bind_param('i', $id);
            $st->execute();
            $old = $st->get_result()->fetch_assoc();
            $st->close();
          }

          $source_id_db   = $source_id > 0 ? $source_id : null;
          $assigned_to_db = $assigned_to > 0 ? $assigned_to : null;
          $assigned_by_db = $MY_ID > 0 ? $MY_ID : null;

          $assigned_at_db = null;
          $reassigned_at_db = null;
          $prev_assigned_to = null;

          if ($id === 0) {
            if ($assigned_to_db) $assigned_at_db = date('Y-m-d H:i:s');
          } else {
            $old_assigned = (int)($old['assigned_to'] ?? 0);
            $old_assigned_at = $old['assigned_at'] ?? null;

            $assigned_at_db = $old_assigned_at ?: ($assigned_to_db ? date('Y-m-d H:i:s') : null);

            if ((int)$assigned_to_db !== $old_assigned) {
              if ($old_assigned > 0) $prev_assigned_to = $old_assigned;
              $reassigned_at_db = date('Y-m-d H:i:s');
            }
          }

          if ($id > 0) {

            $sql = "UPDATE `$TABLE`
                    SET profile_type=?,
                        company_name=?,
                        owner_hr_name=?,
                        sector=?,
                        candidate_name=?,
                        phone1=?,
                        phone2=?,
                        email=?,
                        city_location=?,
                        source_id=?,
                        status_id=?,
                        assigned_to=?,
                        assigned_by=?,
                        assigned_at=?,
                        reassigned_at=?,
                        last_status_reason=?,
                        followup_at=?,
                        onboarded_plan_id=?,
                        not_contactable_flag=?
                    WHERE id=?";

            $st = $con->prepare($sql);

            $params = [
              $profile_type,
              $company_name,
              $owner_hr_name,
              $sector,
              $candidate_name,
              $phone1,
              $phone2,
              $email,
              $city_location,
              $source_id_db,
              $status_id,
              $assigned_to_db,
              $assigned_by_db,
              $assigned_at_db,
              $reassigned_at_db,
              $reason,
              $followup_db,
              $plan_db,
              $not_contactable_flag,
              $id
            ];

            $types = "issssssssiiiisssssii"; // ✅ 20 params
            stmt_bind($st, $types, $params);

            $ok = $st->execute();
            $st->close();

            if ($ok) {
              if ($prev_assigned_to) {
                $st2 = $con->prepare("UPDATE `$TABLE` SET prev_assigned_to=? WHERE id=?");
                $st2->bind_param('ii', $prev_assigned_to, $id);
                $st2->execute();
                $st2->close();
              }

              if (table_exists($con, $HISTTBL)) {
                $old_status = (int)($old['status_id'] ?? 0);
                if ($old_status !== $status_id) {
                  $meta = ['followup_at' => $followup_db, 'onboarded_plan_id' => $plan_db, 'mode' => 'full_edit'];
                  $meta_json = json_encode($meta, JSON_UNESCAPED_UNICODE);
                  $changed_by = $MY_ID ?: null;

                  $stH = $con->prepare("INSERT INTO `$HISTTBL` (lead_id,from_status_id,to_status_id,changed_by,reason,meta_json)
                                      VALUES (?,?,?,?,?,?)");
                  stmt_bind($stH, "iiiiss", [$id, $old_status, $status_id, $changed_by, $reason, $meta_json]);
                  $stH->execute();
                  $stH->close();
                }
              }

              flash_redirect('Updated successfully');
            } else {
              $err = 'Update failed';
            }
          } else {

            $sql = "INSERT INTO `$TABLE`
                    (profile_type,company_name,owner_hr_name,sector,candidate_name,
                     phone1,phone2,email,city_location,
                     source_id,status_id,
                     assigned_to,assigned_by,assigned_at,reassigned_at,
                     last_status_reason,followup_at,onboarded_plan_id,not_contactable_flag,
                     created_by)
                    VALUES
                    (?,?,?,?,?,
                     ?,?,?,?,
                     ?,?,
                     ?,?,?,?,
                     ?,?,?,?,
                     ?)";

            $st = $con->prepare($sql);

            $created_by_db = $MY_ID > 0 ? $MY_ID : null;

            $params = [
              $profile_type,
              $company_name,
              $owner_hr_name,
              $sector,
              $candidate_name,
              $phone1,
              $phone2,
              $email,
              $city_location,
              $source_id_db,
              $status_id,
              $assigned_to_db,
              $assigned_by_db,
              $assigned_at_db,
              $reassigned_at_db,
              $reason,
              $followup_db,
              $plan_db,
              $not_contactable_flag,
              $created_by_db
            ];

            $types = "issssssssiiiisssssii"; // ✅ 20 params
            stmt_bind($st, $types, $params);

            $ok = $st->execute();
            $newId = (int)$st->insert_id;
            $st->close();

            if ($ok) {
              if ($newId > 0 && table_exists($con, $HISTTBL)) {
                $meta = ['followup_at' => $followup_db, 'onboarded_plan_id' => $plan_db, 'mode' => 'created'];
                $meta_json = json_encode($meta, JSON_UNESCAPED_UNICODE);
                $changed_by = $MY_ID ?: null;

                $stH = $con->prepare("INSERT INTO `$HISTTBL` (lead_id,from_status_id,to_status_id,changed_by,reason,meta_json)
                                    VALUES (?,NULL,?,?,?,?)");
                stmt_bind($stH, "iiiss", [$newId, $status_id, $changed_by, $reason, $meta_json]);
                $stH->execute();
                $stH->close();
              }
              flash_redirect('Saved successfully');
            } else {
              $err = 'Insert failed';
            }
          }
        }
      }
    }
  }
}

/* ---------------- Mode ---------------- */
$mode = (isset($_GET['edit']) || isset($_GET['add'])) ? 'form' : 'list';

/* If trying to add/edit without permission */
if ($mode === 'form') {
  if (isset($_GET['add']) && !user_can('add', $MENU_ID, $con)) {
    $mode = 'denied_form';
    $err  = 'You are not authorized to add.';
  }
  if (isset($_GET['edit']) && !user_can('edit', $MENU_ID, $con)) {
    $mode = 'denied_form';
    $err  = 'You are not authorized to edit.';
  }
}


// $edit = null;
// if ($mode === 'form' && isset($_GET['edit'])) {
//   $eid = (int)$_GET['edit'];
//   $st = $con->prepare("SELECT * FROM `$TABLE` WHERE id=?");
//   $st->bind_param('i', $eid);
//   $st->execute();
//   $edit = $st->get_result()->fetch_assoc();
//   $st->close();
//   if (!$edit) $mode = 'list';
// }

/* ---------------- Filters + list ---------------- */
$q = trim($_GET['q'] ?? '');
$ptype = (int)($_GET['ptype'] ?? 0);
$stFilter = (int)($_GET['status'] ?? 0);
$srcFilter = (int)($_GET['source'] ?? 0);
$assFilter = (int)($_GET['assignee'] ?? 0);

$all = isset($_GET['all']);
$lim = $all ? 0 : 50;

$where = " WHERE 1=1 ";
$bind = [];
$types = '';

if ($ptype === 1 || $ptype === 2) {
  $where .= " AND l.profile_type=?";
  $bind[] = $ptype;
  $types .= 'i';
}
if ($stFilter > 0) {
  $where .= " AND l.status_id=?";
  $bind[] = $stFilter;
  $types .= 'i';
}
if ($srcFilter > 0) {
  $where .= " AND l.source_id=?";
  $bind[] = $srcFilter;
  $types .= 'i';
}
if ($assFilter > 0) {
  $where .= " AND l.assigned_to=?";
  $bind[] = $assFilter;
  $types .= 'i';
}

if ($q !== '') {
  $where .= " AND (
    l.company_name LIKE ? OR l.candidate_name LIKE ? OR
    l.phone1 LIKE ? OR l.phone2 LIKE ? OR
    l.email LIKE ?
  )";
  $like = "%$q%";
  $bind[] = $like;
  $bind[] = $like;
  $bind[] = $like;
  $bind[] = $like;
  $bind[] = $like;
  $types .= 'sssss';
}

/* count */
$total = 0;
$st = $con->prepare("SELECT COUNT(*) c FROM `$TABLE` l $where");
if ($bind) $st->bind_param($types, ...$bind);
$st->execute();
$total = (int)$st->get_result()->fetch_assoc()['c'];
$st->close();

/* rows */
$rows = [];
// $sql="SELECT l.*,
//             s.status_name, s.status_code,
//             src.source_name
//       FROM `$TABLE` l
//       LEFT JOIN `$STATUSTBL` s ON s.id=l.status_id
//       LEFT JOIN `$SOURCETBL` src ON src.id=l.source_id
//       $where
//       ORDER BY l.id DESC";
$sql = "SELECT l.*,
            s.status_name, s.status_code,
            src.source_name,
            p.plan_name AS onboarded_plan_name
      FROM `$TABLE` l
      LEFT JOIN `$STATUSTBL` s ON s.id=l.status_id
      LEFT JOIN `$SOURCETBL` src ON src.id=l.source_id
      LEFT JOIN `$PLANTBL` p ON p.id=l.onboarded_plan_id
      $where
      ORDER BY l.id DESC";

if (!$all) $sql .= " LIMIT $lim";

$st = $con->prepare($sql);
if ($bind) $st->bind_param($types, ...$bind);
$st->execute();
$rs = $st->get_result();
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$st->close();

/* ---------------- VIEW ---------------- */
ob_start(); ?>
<link rel="stylesheet" href="/adminconsole/assets/ui.css">

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
  .pac-panel {
    background: #0b1220;
    border: 1px solid rgba(148, 163, 184, .18);
    border-radius: 16px;
    padding: 16px;
    box-shadow: 0 14px 50px rgba(0, 0, 0, .45);
  }

  .pac-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
  }

  .pac-sub {
    color: #9ca3af;
    font-size: 12px;
    margin-top: 6px;
  }

  .pac-grid3 {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px 16px;
    align-items: end;
  }

  .pac-grid3 .full {
    grid-column: 1/-1;
  }

  .pac-grid3 .actions {
    grid-column: 1/-1;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
  }

  .pac-grid3 label {
    display: block;
    margin: 0 0 6px;
    font-weight: 600;
  }

  .pac-hint {
    color: #9ca3af;
    font-size: 12px;
    margin-top: 6px;
  }

  .hide {
    display: none !important;
  }

  @media (max-width:1100px) {
    .pac-grid3 {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width:720px) {
    .pac-grid3 {
      grid-template-columns: 1fr;
    }

    .pac-grid3 .full {
      grid-column: 1;
    }
  }

  .pac-modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .55);
    z-index: 9999;
    display: none;
    overflow: auto;
    padding: 18px;
  }

  .pac-modal .pac-panel {
    max-width: 980px;
    margin: 24px auto;
  }

  .pac-labelgrid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 12px;
  }

  .pac-label {
    border: 1px solid rgba(148, 163, 184, .18);
    border-radius: 12px;
    padding: 10px;
    background: #0f1a2e;
  }

  .pac-label .k {
    color: #9ca3af;
    font-size: 12px;
  }

  .pac-label .v {
    font-weight: 600;
    margin-top: 2px;
    color: #e5e7eb;
  }

  @media (max-width:900px) {
    .pac-labelgrid {
      grid-template-columns: 1fr;
    }
  }

  /* Flatpickr solid */
  .flatpickr-calendar {
    z-index: 100000 !important;
    background: #0b1220 !important;
    border: 1px solid rgba(148, 163, 184, .25) !important;
    box-shadow: 0 16px 55px rgba(0, 0, 0, .70) !important;
    opacity: 1 !important;
  }

  .flatpickr-months,
  .flatpickr-weekdays,
  .flatpickr-days,
  .flatpickr-time {
    background: #0b1220 !important;
    opacity: 1 !important;
  }

  .flatpickr-weekday,
  .flatpickr-day {
    color: #e5e7eb !important;
  }

  .flatpickr-day.today {
    border-color: rgba(96, 165, 250, .9) !important;
  }

  .flatpickr-day.selected,
  .flatpickr-day.startRange,
  .flatpickr-day.endRange {
    background: rgba(34, 197, 94, .85) !important;
    border-color: rgba(34, 197, 94, .85) !important;
    color: #04110a !important;
  }

  .flatpickr-day:hover {
    background: rgba(148, 163, 184, .18) !important;
  }

  .flatpickr-time input,
  .flatpickr-time .flatpickr-am-pm {
    color: #e5e7eb !important;
    background: #0b1220 !important;
  }

  .flatpickr-current-month .flatpickr-monthDropdown-months,
  .flatpickr-current-month input.cur-year {
    color: #e5e7eb !important;
  }

  .flatpickr-calendar.arrowTop:before,
  .flatpickr-calendar.arrowTop:after,
  .flatpickr-calendar.arrowBottom:before,
  .flatpickr-calendar.arrowBottom:after {
    display: none !important;
  }
</style>

<script>
  // Plans for JS filtering (common type=0 + specific type=1/2)
  window.PACIFIC_PLANS = <?= json_encode($plansByType, JSON_UNESCAPED_UNICODE) ?>;

  function buildPlanOptions(profileType, selectedId) {
    const common = (window.PACIFIC_PLANS && window.PACIFIC_PLANS[0]) ? window.PACIFIC_PLANS[0] : [];
    const typed = (window.PACIFIC_PLANS && window.PACIFIC_PLANS[profileType]) ? window.PACIFIC_PLANS[profileType] : [];
    const list = [...common, ...typed];

    let html = '<option value="0">— Select Plan —</option>';
    for (const p of list) {
      const sel = (String(p.id) === String(selectedId)) ? ' selected' : '';
      html += '<option value="' + String(p.id).replace(/"/g, '&quot;') + '"' + sel + '>' + String(p.name) + '</option>';
    }
    return html;
  }
</script>

<h2 style="margin:8px 0 12px"><?= h($page_title) ?></h2>

<?php if ($mode === 'denied_form'): ?>

  <div class="pac-panel" style="max-width:760px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <h3 style="margin:0">Access denied</h3>
      <a class="btn gray" href="<?= h(keep_params(['edit' => null, 'add' => null])) ?>">Back to List</a>
    </div>

    <?php if ($err): ?>
      <div class="badge off" style="margin:8px 0"><?= h($err) ?></div>
    <?php endif; ?>

    <p style="color:#9ca3af;margin:8px 0">
      You don't have permission to perform this action.
    </p>
  </div>

<?php elseif ($mode === 'list'): ?>

  <?php if (isset($_GET['ok'])): ?><div class="alert ok"><?= h($_GET['ok']) ?></div><?php endif; ?>

  <div class="pac-panel">
    <div class="pac-head">
      <div>
        <h3 style="margin:0">Leads</h3>
        <div class="pac-sub">Dates shown as dd-mm-yyyy and time as AM/PM</div>
      </div>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <?php if (user_can('add', $MENU_ID, $con)): ?>
          <a class="btn green" href="<?= h(keep_params(['add' => 1])) ?>">Add New Lead</a>
        <?php endif; ?>
      </div>
    </div>

    <form method="get" class="search" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0 12px">
      <input type="text" name="q" class="inp" placeholder="Search company/candidate/phone/email" value="<?= h($q) ?>" style="min-width:240px">

      <select name="ptype" class="inp">
        <option value="0">All Types</option>
        <option value="1" <?= $ptype === 1 ? 'selected' : '' ?>>Employer</option>
        <option value="2" <?= $ptype === 2 ? 'selected' : '' ?>>Jobseeker</option>
      </select>

      <select name="status" class="inp">
        <option value="0">All Status</option>
        <?php foreach ($statuses as $sid => $s): ?>
          <option value="<?= $sid ?>" <?= $stFilter === $sid ? 'selected' : '' ?>><?= h($s['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="source" class="inp">
        <option value="0">All Sources</option>
        <?php foreach ($sources as $sid => $nm): ?>
          <option value="<?= $sid ?>" <?= $srcFilter === $sid ? 'selected' : '' ?>><?= h($nm) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="assignee" class="inp">
        <option value="0">All Assignees</option>
        <?php foreach ($adminUsers as $uid => $nm): ?>
          <option value="<?= $uid ?>" <?= $assFilter === $uid ? 'selected' : '' ?>><?= h($nm) ?></option>
        <?php endforeach; ?>
      </select>

      <button class="btn gray" type="submit">Search</button>

      <?php if (!$all && $total > $lim): ?>
        <a class="btn gray" href="<?= h(keep_params(['all' => 1])) ?>">View All (<?= $total ?>)</a>
      <?php endif; ?>
      <?php if ($all): ?>
        <a class="btn gray" href="<?= h(keep_params(['all' => null])) ?>">Last 50</a>
      <?php endif; ?>
    </form>

    <div style="margin:6px 0 10px;color:#9ca3af">
      Showing <strong><?= !$all ? count($rows) : $total ?></strong> of <strong><?= $total ?></strong>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>SR</th>
            <th>Type</th>
            <th>Company / Candidate</th>
            <th>Phone</th>
            <th>City</th>
            <th>Source</th>
            <th>Status</th>
            <th>On-boarded Plan</th>
            <th>Assigned</th>
            <th>Updated</th>
            <th style="min-width:260px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr>
              <td colspan="10" style="color:#9ca3af">No records</td>
            </tr>
          <?php endif; ?>

          <?php $sr = 0;
          foreach ($rows as $r): $sr++; ?>
            <?php
            $pt = (int)($r['profile_type'] ?? 1);
            $typeLabel = ($pt === 1) ? 'Employer' : 'Jobseeker';
            $name = ($pt === 1) ? ($r['company_name'] ?: '—') : ($r['candidate_name'] ?: '—');

            $ass = (int)($r['assigned_to'] ?? 0);
            $assName = $ass > 0 ? ($adminUsers[$ass] ?? ('#' . $ass)) : '—';

            $payload = [
              'id' => (int)$r['id'],
              'profile_type' => $pt,
              'company_name' => $r['company_name'] ?? '',
              'candidate_name' => $r['candidate_name'] ?? '',
              'phone1' => $r['phone1'] ?? '',
              'phone2' => $r['phone2'] ?? '',
              'email' => $r['email'] ?? '',
              'city_location' => $r['city_location'] ?? '',
              'source_name' => $r['source_name'] ?? '',
              'status_id' => (int)($r['status_id'] ?? 0),
              'status_name' => $r['status_name'] ?? '',
              'assigned_to_name' => $assName,
              'updated_at_view' => fmt_dt($r['updated_at'] ?? null),
              'followup_at_view' => fmt_dt($r['followup_at'] ?? null),
              'onboarded_plan_name' => ($r['onboarded_plan_name'] ?? ''),
              'onboarded_plan_id' => (string)($r['onboarded_plan_id'] ?? '0'),

            ];
            ?>
            <tr>
              <td><?= $sr ?></td>
              <td><?= h($typeLabel) ?></td>
              <td><?= h($name) ?></td>
              <td><?= h($r['phone1'] ?: '—') ?></td>
              <td><?= h($r['city_location'] ?: '—') ?></td>
              <td><?= h($r['source_name'] ?? '—') ?></td>
              <td><span class="badge on"><?= h($r['status_name'] ?? '—') ?></span></td>
              <td><?= h($r['onboarded_plan_name'] ?? '—') ?></td>
              <td><?= h($assName) ?></td>
              <td><?= h(fmt_dt($r['updated_at'] ?? null)) ?></td>

              <td>
                <button class="btn gray" type="button"
                  onclick='openStatusModal(<?= (int)$r["id"] ?>, <?= json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                  Update Status
                </button>

                <?php if (user_can('edit', $MENU_ID, $con)): ?>
                  <a class="btn gray" href="<?= h(keep_params(['edit' => (int)$r['id']])) ?>">Edit</a>
                <?php endif; ?>


                <?php if (user_can('delete', $MENU_ID, $con)): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('Delete this lead?');">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= h($r['id']) ?>">
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

  <!-- Status Update Modal -->
  <div id="statusModal" class="pac-modal">
    <div class="pac-panel">
      <div class="pac-head">
        <h3 style="margin:0">Update Lead Status</h3>
        <button class="btn gray" type="button" onclick="closeStatusModal()">Close</button>
      </div>

      <div id="leadLabels" class="pac-labelgrid"></div>

      <hr style="opacity:.18;margin:14px 0">

      <form id="statusForm" method="post" class="pac-grid3">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="status_update" value="1">
        <input type="hidden" name="lead_id" id="m_lead_id" value="0">

        <div>
          <label>Status*</label>
          <select class="inp" name="to_status_id" id="m_status_id" required onchange="syncModalFields()">
            <?php foreach ($statuses as $sid => $s): ?>
              <option value="<?= $sid ?>" data-code="<?= h($s['code']) ?>"><?= h($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="m_followup_box" class="hide">
          <label>Follow-up Date/Time*</label>
          <input type="text" class="inp" name="followup_at" id="m_followup_at" placeholder="dd-mm-yyyy hh:mm AM/PM">
        </div>

        <div id="m_plan_box" class="hide">
          <label>On-boarded Plan*</label>
          <select class="inp" name="onboarded_plan_id" id="m_plan_id">
            <option value="0">— Select Plan —</option>
          </select>
        </div>

        <div class="full">
          <label>Remark*</label>
          <textarea class="inp" rows="3" name="remark" id="m_remark"
            placeholder="Remark will be saved in history each time you update status."></textarea>
        </div>

        <div class="actions">
          <button class="btn green" type="submit">Update</button>
          <span id="m_msg" style="color:#9ca3af"></span>
        </div>
      </form>
    </div>
  </div>

  <script>
    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, (m) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      } [m]));
    }

    let _modalFP = null;
    let _modalProfileType = 1;

    function openStatusModal(id, row) {
      document.getElementById('m_lead_id').value = id;

      const pt = parseInt(row.profile_type || '1', 10);
      _modalProfileType = pt;
      const name = (pt === 1) ? (row.company_name || '—') : (row.candidate_name || '—');

      const labels = [
        ['Type', pt === 1 ? 'Employer' : 'Jobseeker'],
        ['Name', name],
        ['Phone 1', row.phone1 || '—'],
        ['Phone 2', row.phone2 || '—'],
        ['Email', row.email || '—'],
        ['City', row.city_location || '—'],
        ['Source', row.source_name || '—'],
        ['Current Status', row.status_name || '—'],
        ['On-boarded Plan', row.onboarded_plan_name || '—'],
        ['Follow-up', row.followup_at_view || '—'],
        ['Assigned To', row.assigned_to_name || '—'],
        ['Updated', row.updated_at_view || '—'],
      ];

      const wrap = document.getElementById('leadLabels');
      wrap.innerHTML = labels.map(x => `
        <div class="pac-label">
          <div class="k">${escapeHtml(x[0])}</div>
          <div class="v">${escapeHtml(x[1])}</div>
        </div>
      `).join('');

      // status
      if (row.status_id) document.getElementById('m_status_id').value = row.status_id;

      // reset fields
      document.getElementById('m_followup_at').value = '';
      document.getElementById('m_remark').value = '';
      document.getElementById('m_msg').textContent = '';

      // build plan list by profile type
      document.getElementById('m_plan_id').innerHTML = buildPlanOptions(pt, row.onboarded_plan_id || "0");


      document.getElementById('statusModal').style.display = 'block';
      syncModalFields();

      if (window.flatpickr) {
        try {
          if (_modalFP) _modalFP.destroy();
        } catch (e) {}
        _modalFP = flatpickr(document.getElementById('m_followup_at'), {
          enableTime: true,
          time_24hr: false,
          dateFormat: "d-m-Y h:i K",
          allowInput: true,
          appendTo: document.getElementById('statusModal')
        });
      }
    }

    function closeStatusModal() {
      document.getElementById('statusModal').style.display = 'none';
    }

    function syncModalFields() {
      const sel = document.getElementById('m_status_id');
      const opt = sel.options[sel.selectedIndex];
      const code = opt ? (opt.getAttribute('data-code') || '') : '';
      document.getElementById('m_followup_box').classList.toggle('hide', code !== 'FOLLOW_UP');
      document.getElementById('m_plan_box').classList.toggle('hide', code !== 'ON_BOARDED');

      // keep plan list in sync with lead type (if user keeps modal open and switches status)
      if (code === 'ON_BOARDED') {
        const planSel = document.getElementById('m_plan_id');
        const cur = planSel.value || "0";
        planSel.innerHTML = buildPlanOptions(_modalProfileType, cur);
      }
    }

    document.getElementById('statusForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const msg = document.getElementById('m_msg');
      msg.style.color = '#9ca3af';
      msg.textContent = 'Saving...';

      const fd = new FormData(this);
      const res = await fetch(location.href, {
        method: 'POST',
        body: fd
      });
      const data = await res.json().catch(() => ({
        ok: false,
        msg: 'Invalid server response'
      }));

      if (!data.ok) {
        msg.textContent = data.msg || 'Failed';
        msg.style.color = '#fca5a5';
        return;
      }
      msg.textContent = data.msg || 'Updated';
      msg.style.color = '#86efac';
      setTimeout(() => location.reload(), 300);
    });
  </script>

<?php else: /* form */ ?>

  <?php
  $isEdit = (bool)$edit;
  $val = function ($k, $default = '') use ($edit) {
    if (!$edit) return $default;
    return $edit[$k] ?? $default;
  };

  $pt = (int)($val('profile_type', 1));
  $curStatus = (int)($val('status_id', (int)(array_key_first($statuses) ?: 0)));
  $curPlan = (string)($val('onboarded_plan_id', '0'));
  ?>

  <div class="pac-panel" style="max-width:980px">
    <div class="pac-head">
      <h3 style="margin:0"><?= $isEdit ? 'Edit Lead' : 'Add Lead' ?></h3>
      <a class="btn gray" href="<?= h(keep_params(['edit' => null, 'add' => null])) ?>">Back to List</a>
    </div>

    <?php if ($err): ?><div class="badge off" style="margin:10px 0"><?= h($err) ?></div><?php endif; ?>

    <form method="post" class="pac-grid3">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= h($edit['id']) ?>"><?php endif; ?>

      <div>
        <label>Profile Type*</label>
        <select name="profile_type" id="profile_type" class="inp" required>
          <option value="1" <?= $pt === 1 ? 'selected' : '' ?>>Employer (Profile 1)</option>
          <option value="2" <?= $pt === 2 ? 'selected' : '' ?>>Jobseeker (Profile 2)</option>
        </select>
      </div>

      <div>
        <label>Source</label>
        <select name="source_id" class="inp">
          <option value="0">— Select —</option>
          <?php $sv = (int)$val('source_id', 0);
          foreach ($sources as $sid => $nm): ?>
            <option value="<?= $sid ?>" <?= $sv === $sid ? 'selected' : '' ?>><?= h($nm) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label>Assign To</label>
        <select name="assigned_to" class="inp">
          <option value="0">— Not assigned —</option>
          <?php $av = (int)$val('assigned_to', 0);
          foreach ($adminUsers as $uid => $nm): ?>
            <option value="<?= $uid ?>" <?= $av === $uid ? 'selected' : '' ?>><?= h($nm) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="pac-hint"></div>
      </div>

      <!-- Employer -->
      <div id="emp_company">
        <label>Company Name*</label>
        <input name="company_name" class="inp" value="<?= h($val('company_name')) ?>" placeholder="Company name">
      </div>
      <div id="emp_owner">
        <label>Owner / HR Name</label>
        <input name="owner_hr_name" class="inp" value="<?= h($val('owner_hr_name')) ?>" placeholder="Owner / HR">
      </div>
      <div id="emp_sector">
        <label>Type of Organisation (Sector)</label>
        <input name="sector" class="inp" value="<?= h($val('sector')) ?>" placeholder="Sector">
      </div>

      <!-- Jobseeker -->
      <div id="js_candidate" style="grid-column: span 2">
        <label>Candidate Name*</label>
        <input name="candidate_name" class="inp" value="<?= h($val('candidate_name')) ?>" placeholder="Candidate name">
      </div>
      <div id="js_fill" class="hide"></div>

      <!-- Shared -->
      <div>
        <label>Contact - 1*</label>
        <input name="phone1" class="inp" required value="<?= h($val('phone1')) ?>" placeholder="Phone 1">
      </div>
      <div>
        <label>Contact - 2</label>
        <input name="phone2" class="inp" value="<?= h($val('phone2')) ?>" placeholder="Phone 2">
      </div>
      <div>
        <label>Email ID</label>
        <input name="email" class="inp" value="<?= h($val('email')) ?>" placeholder="Email">
      </div>

      <div>
        <label>City / Location</label>
        <input name="city_location" class="inp" value="<?= h($val('city_location')) ?>" placeholder="City / Location">
      </div>

      <div>
        <label>Status*</label>
        <select name="status_id" id="status_id" class="inp" required>
          <?php foreach ($statuses as $sid => $s): ?>
            <option value="<?= $sid ?>" <?= $curStatus === $sid ? 'selected' : '' ?> data-code="<?= h($s['code']) ?>"><?= h($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="box_followup" class="hide">
        <label>Follow-up Date/Time*</label>
        <input type="text" name="followup_at" id="followup_at" class="inp"
          placeholder="dd-mm-yyyy hh:mm AM/PM"
          value="<?php
                  $fv = $val('followup_at', '');
                  if ($fv) echo h(date('d-m-Y h:i A', strtotime($fv)));
                  ?>">
      </div>

      <div id="box_plan" class="hide">
        <label>On-boarded Plan*</label>
        <select name="onboarded_plan_id" id="plan_select" class="inp"></select>
        <div class="pac-hint">Plans shown as per Profile Type (and common plans).</div>
      </div>

      <div class="full">
        <label>Reason / Notes</label>
        <textarea name="last_status_reason" class="inp" rows="3"
          placeholder="(Required for Not Interested / Follow-up / Not Contactable / On-boarded)"><?= h($val('last_status_reason')) ?></textarea>
      </div>

      <div class="actions">
        <button class="btn green" name="save" type="submit">Save</button>
        <a class="btn gray" href="<?= h(keep_params(['edit' => null, 'add' => null])) ?>">Cancel</a>
      </div>
    </form>
  </div>

  <script>
    (function() {
      function byId(id) {
        return document.getElementById(id);
      }

      function setHide(el, hide) {
        if (!el) return;
        el.classList.toggle('hide', !!hide);
      }

      function rebuildPlanDropdown() {
        var pt = parseInt(byId('profile_type').value || '1', 10);
        var sel = byId('plan_select');
        if (!sel) return;
        var selected = sel.getAttribute('data-selected') || '0';
        sel.innerHTML = buildPlanOptions(pt, selected);
      }

      function toggleProfile() {
        var pt = parseInt(byId('profile_type').value || '1', 10);
        setHide(byId('emp_company'), pt !== 1);
        setHide(byId('emp_owner'), pt !== 1);
        setHide(byId('emp_sector'), pt !== 1);
        setHide(byId('js_candidate'), pt !== 2);
        setHide(byId('js_fill'), pt !== 2);

        // important: rebuild plan list when profile type changes
        rebuildPlanDropdown();
      }

      function toggleStatusExtras() {
        var sel = byId('status_id');
        var opt = sel.options[sel.selectedIndex];
        var code = (opt && opt.getAttribute('data-code')) ? opt.getAttribute('data-code') : '';
        setHide(byId('box_followup'), code !== 'FOLLOW_UP');
        setHide(byId('box_plan'), code !== 'ON_BOARDED');

        if (code === 'ON_BOARDED') rebuildPlanDropdown();
      }

      // initial selected plan from PHP
      var planSel = byId('plan_select');
      if (planSel) planSel.setAttribute('data-selected', <?= json_encode($curPlan) ?>);

      byId('profile_type').addEventListener('change', toggleProfile);
      byId('status_id').addEventListener('change', toggleStatusExtras);

      toggleProfile();
      toggleStatusExtras();

      if (window.flatpickr) {
        var el = document.getElementById('followup_at');
        if (el) {
          flatpickr(el, {
            enableTime: true,
            time_24hr: false,
            dateFormat: "d-m-Y h:i K",
            allowInput: true
          });
        }
      }
    })();
  </script>

<?php endif; ?>

<?php
echo ob_get_clean();
