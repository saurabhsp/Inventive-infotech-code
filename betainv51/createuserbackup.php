<?php
@ini_set('display_errors', '1'); @error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/initialize.php'; // $con (mysqli)
require_once __DIR__ . '/../includes/acl.php';         // ⬅️ shared ACL helper
require_login();

/* ---------- tiny helpers ---------- */
function clean($v){ return trim((string)$v); }
function keep_params(array $changes = []) {
  $qs = $_GET; foreach ($changes as $k=>$v) { if ($v===null) unset($qs[$k]); else $qs[$k]=$v; }
  $q = http_build_query($qs); return $q ? ('?'.$q) : '';
}
function col_exists(mysqli $con, string $table, string $col): bool {
  $t = mysqli_real_escape_string($con,$table);
  $c = mysqli_real_escape_string($con,$col);
  $r = mysqli_query($con,"SHOW COLUMNS FROM `$t` LIKE '$c'");
  return ($r && mysqli_num_rows($r)>0);
}
function table_exists(mysqli $con, string $table): bool {
  $t = mysqli_real_escape_string($con,$table);
  $r = mysqli_query($con,"SHOW TABLES LIKE '$t'");
  return ($r && mysqli_num_rows($r)>0);
}
function ensure_schema($con,$table){
  // ensure status exists (we keep this)
  $r = mysqli_query($con,"SHOW COLUMNS FROM `$table` LIKE 'status'");
  if(!$r || mysqli_num_rows($r)==0){ mysqli_query($con,"ALTER TABLE `$table` ADD `status` TINYINT(1) NOT NULL DEFAULT 1"); }
  // IMPORTANT: DO NOT add 'role' here anymore (we use mapping table now)
}

/**
 * Robust PRG redirect back to list. If headers already sent, falls back to JS/Meta,
 * and as the last resort forces list view in the same request.
 */
function back_to_list($msg){
  // Build query: remove form flags, add ok message, keep other filters
  $q = $_GET;
  unset($q['add'], $q['edit']);
  $q['ok'] = $msg;

  $self   = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
  $qs     = $q ? ('?'.http_build_query($q)) : '';
  $path   = $self . $qs;

  // Absolute URL for proxies / mixed setups
  $host   = $_SERVER['HTTP_HOST'] ?? '';
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $abs    = $host ? ($scheme . '://' . $host . $path) : $path;

  // Close session + clear buffers BEFORE redirect
  if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
  while (ob_get_level() > 0) { @ob_end_clean(); }

  // Primary: 303 See Other (PRG)
  if (!headers_sent()) {
    header('Cache-Control: no-store');
    header('Location: ' . $abs, true, 303);
    exit;
  }

  // Fallback A: JS redirect
  echo '<script>location.replace(' . json_encode($abs) . ');</script>';
  // Fallback B: noscript
  echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($abs, ENT_QUOTES, 'UTF-8') . '"></noscript>';

  // Fallback C: force list in this very request
  $_GET = $q;                           // mutate current request params
  $GLOBALS['__force_list__'] = true;    // flag to force list mode below
}

/* ---------- page meta ---------- */
$page_title = 'Create Users';
$TABLE      = 'jos_admin_users';
$ROLE_TABLE = 'jos_admin_roles';
$MAP_TABLE  = 'jos_admin_users_roles';
$ROLEMENU   = 'jos_admin_rolemenus';
$MENUS      = 'jos_admin_menus';

ensure_schema($con,$TABLE);

/* ---------- session / identity ---------- */
$me   = $_SESSION['admin_user'] ?? [];
$myId = (int)($me['id'] ?? 0);
if ($myId <= 0) {
  http_response_code(403);
  die('<!doctype html><meta charset="utf-8"><title>Forbidden</title><div style="font:14px system-ui;padding:24px;color:#e11d48;background:#0b1220">Access denied</div>');
}

/* ---------- unified ACL (view/add/edit/delete from same table) ---------- */
$ACL = pac_menu_caps($con); // returns ['view','add','edit','delete','has_access','role_id']
if (!$ACL['view']) {
  http_response_code(403);
  die('<!doctype html><meta charset="utf-8"><title>Forbidden</title>
       <div style="font:14px system-ui;padding:24px;color:#e11d48;background:#0b1220">
         You are not authorized to view this content.
       </div>');
}

/* ---------- roles loader for form/filter ---------- */
$ROLE_OPTIONS = [];
$role_sql = "SELECT id, name FROM $ROLE_TABLE WHERE status=1 ORDER BY orderby ASC, name ASC";
$rr = mysqli_query($con,$role_sql);
if ($rr) { while($r = mysqli_fetch_assoc($rr)) { $ROLE_OPTIONS[] = $r; } }

/* ---------- POST (with server-side action guards) ---------- */
$err=''; $ok='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  if (!verify_csrf($_POST['csrf'] ?? null)) {
    $err='Invalid request.';
  } else {
    if (isset($_POST['save'])) {
      $id     = (int)($_POST['id'] ?? 0);
      $name   = clean($_POST['name'] ?? '');
      $email  = clean($_POST['email'] ?? '');
      $mobile = preg_replace('/\D+/', '', (string)($_POST['mobile'] ?? ''));
      $status = (int)($_POST['status'] ?? 1);
      $pass   = (string)($_POST['password'] ?? '');
      $cpass  = (string)($_POST['confirm_password'] ?? '');
      $role_id= (int)($_POST['role_id'] ?? 0);

      // action guard: create vs edit
      if ($id === 0 && empty($ACL['add'])) {
        http_response_code(403);
        die('<!doctype html><meta charset="utf-8"><title>Forbidden</title>
             <div style="font:14px system-ui;padding:24px;color:#e11d48;background:#0b1220">
               You are not authorized to add users.
             </div>');
      }
      if ($id > 0 && empty($ACL['edit'])) {
        http_response_code(403);
        die('<!doctype html><meta charset="utf-8"><title>Forbidden</title>
             <div style="font:14px system-ui;padding:24px;color:#e11d48;background:#0b1220">
               You are not authorized to edit users.
             </div>');
      }

      if ($name==='') $err='Name is required.';
      elseif ($email==='' && $mobile==='') $err='Enter at least Email or Mobile.';
      elseif ($email!=='' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err='Enter a valid email.';
      elseif ($mobile!=='' && strlen($mobile)<10) $err='Enter a valid mobile (min 10 digits).';
      elseif ($id===0 && $pass==='') $err='Password is required for new users.';
      elseif ($pass!=='' && $pass!==$cpass) $err='Passwords do not match.';
      elseif ($role_id<=0) $err='Please select a role.';
      else {
        // duplicate check on email/mobile
        $dup = null; $conds = []; $types=''; $vals=[];
        if ($email !== '')  { $conds[] = 'LOWER(email) = LOWER(?)'; $types.='s'; $vals[]=$email; }
        if ($mobile !== '') { $conds[] = 'mobile_no = ?';           $types.='s'; $vals[]=$mobile; }
        if (!empty($conds)) {
          $dup_sql = "SELECT id FROM $TABLE WHERE (" . implode(' OR ', $conds) . ")";
          if ($id>0) { $dup_sql .= " AND id <> ?"; $types.='i'; $vals[]=$id; }
          $dup_sql .= " LIMIT 1";
          $st = $con->prepare($dup_sql);
          $st->bind_param($types, ...$vals);
          $st->execute(); $dup = $st->get_result()->fetch_assoc(); $st->close();
        }

        if ($dup) $err='User already exists with this email or mobile.';
        else {
          if ($id>0){
            // update user
            if ($pass!==''){
              $hash = password_hash($pass, PASSWORD_DEFAULT);
              $st=$con->prepare("UPDATE $TABLE SET name=?, email=?, mobile_no=?, status=?, password_hash=? WHERE id=?");
              $st->bind_param('sssisi',$name,$email,$mobile,$status,$hash,$id);
            } else {
              $st=$con->prepare("UPDATE $TABLE SET name=?, email=?, mobile_no=?, status=? WHERE id=?");
              $st->bind_param('sssii',$name,$email,$mobile,$status,$id);
            }
            if ($st->execute()){
              $st->close();
              // upsert mapping (simple delete+insert)
              $con->query("DELETE FROM $MAP_TABLE WHERE user_id=".(int)$id);
              $st2=$con->prepare("INSERT INTO $MAP_TABLE (user_id, role_id) VALUES (?,?)");
              $st2->bind_param('ii',$id,$role_id);
              $st2->execute(); $st2->close();
              back_to_list('Updated successfully');
            } else {
              $err='Update failed'; $st->close();
            }
          } else {
            // new user
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $st=$con->prepare("INSERT INTO $TABLE (name,email,mobile_no,password_hash,status) VALUES (?,?,?,?,?)");
            $st->bind_param('ssssi',$name,$email,$mobile,$hash,$status);
            if ($st->execute()){
              $new_id = $st->insert_id; $st->close();
              $st2=$con->prepare("INSERT INTO $MAP_TABLE (user_id, role_id) VALUES (?,?)");
              $st2->bind_param('ii',$new_id,$role_id);
              $st2->execute(); $st2->close();
              back_to_list('Added successfully');
            } else {
              $err='Insert failed'; $st->close();
            }
          }
        }
      }
    }

    if (isset($_POST['delete'])) {
      if (empty($ACL['delete'])) {
        http_response_code(403);
        die('<!doctype html><meta charset="utf-8"><title>Forbidden</title>
             <div style="font:14px system-ui;padding:24px;color:#e11d48;background:#0b1220">
               You are not authorized to delete users.
             </div>');
      }

      $id=(int)$_POST['id'];
      if ($id === (int)($me['id'] ?? 0)) {
        $err = "You can't delete your own account.";
      } else {
        // delete mapping first (safe)
        $con->query("DELETE FROM $MAP_TABLE WHERE user_id=".(int)$id);
        $st=$con->prepare("DELETE FROM $TABLE WHERE id=?");
        $st->bind_param('i',$id);
        if ($st->execute()){
          $st->close();
          back_to_list('Deleted successfully');
        } else {
          $err='Delete failed'; $st->close();
        }
      }
    }
  }
}

/* ---------- mode & edit row ---------- */
$mode = (isset($_GET['edit']) || isset($_GET['add'])) ? 'form' : 'list';
if (!empty($GLOBALS['__force_list__'])) $mode = 'list';   // ⬅️ ensure fallback C shows list

// protect direct URL access to forms
if ($mode==='form') {
  if (isset($_GET['add']) && empty($ACL['add'])) {
    http_response_code(403);
    die('<!doctype html><meta charset="utf-8"><title>Forbidden</title>
         <div style="font:14px system-ui;padding:24px;color:#e11d48;background:#0b1220">
           You are not authorized to add users.
         </div>');
  }
  if (isset($_GET['edit']) && empty($ACL['edit'])) {
    http_response_code(403);
    die('<!doctype html><meta charset="utf-8"><title>Forbidden</title>
         <div style="font:14px system-ui;padding:24px;color:#e11d48;background:#0b1220">
           You are not authorized to edit users.
         </div>');
  }
}

$edit=null;
$edit_role_id = 0;
if ($mode==='form' && isset($_GET['edit'])){
  $eid=(int)$_GET['edit'];
  $sql="SELECT u.id,u.name,u.email,u.mobile_no,u.status,ur.role_id
        FROM $TABLE u
        LEFT JOIN $MAP_TABLE ur ON ur.user_id = u.id
        WHERE u.id=?";
  $st=$con->prepare($sql);
  $st->bind_param('i',$eid); $st->execute();
  if ($row=$st->get_result()->fetch_assoc()) {
    $edit = $row;
    $edit_role_id = (int)($row['role_id'] ?? 0);
  }
  $st->close();
}

/* ---------- filters ---------- */
$q   = clean($_GET['q'] ?? '');
$rfl = (int)($_GET['role_id'] ?? 0);   // filter by role_id
$all = isset($_GET['all']); $lim = $all ? 0 : 50;

$where = " WHERE 1=1 "; $bind=[]; $type='';
if ($q!==''){ $where.=" AND (u.name LIKE ? OR u.email LIKE ? OR u.mobile_no LIKE ?)"; $like="%$q%"; $bind[]=$like; $bind[]=$like; $bind[]=$like; $type.='sss'; }
if ($rfl>0){ $where.=" AND ur.role_id = ?"; $bind[]=$rfl; $type.='i'; }

/* ---------- counts / list ---------- */
$count_sql="SELECT COUNT(*) c
            FROM $TABLE u
            LEFT JOIN $MAP_TABLE ur ON ur.user_id = u.id
            LEFT JOIN $ROLE_TABLE r ON r.id = ur.role_id
            $where";
$st=$con->prepare($count_sql);
if ($bind) $st->bind_param($type, ...$bind);
$st->execute(); $total=(int)$st->get_result()->fetch_assoc()['c']; $st->close();

$list_sql="SELECT u.id,u.name,u.email,u.mobile_no,u.status,ur.role_id,r.name AS role_name
           FROM $TABLE u
           LEFT JOIN $MAP_TABLE ur ON ur.user_id = u.id
           LEFT JOIN $ROLE_TABLE r ON r.id = ur.role_id
           $where
           ORDER BY u.id DESC";
if (!$all) $list_sql.=" LIMIT $lim";
$st=$con->prepare($list_sql);
if ($bind) $st->bind_param($type, ...$bind);
$st->execute();
$rs=$st->get_result(); $rows=[];
while($r=$rs->fetch_assoc()) $rows[]=$r;
$st->close();

/* ---------- VIEW (standalone) ---------- */
ob_start(); ?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?=htmlspecialchars($page_title)?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/adminconsole/assets/ui.css">
</head>
<body>
<div class="master-wrap">
  <div class="headbar"><h2 style="margin:0"><?=htmlspecialchars($page_title)?></h2></div>

  <?php if ($mode==='list'): ?>
    <?php if (!empty($_GET['ok'])): ?>
      <div class="alert ok" style="margin:12px 0"><?=htmlspecialchars($_GET['ok'])?></div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div class="alert err" style="margin:12px 0"><?=htmlspecialchars($err)?></div>
    <?php endif; ?>

    <div class="card">
      <div class="toolbar">
        <form method="get" class="search">
          <input type="text" name="q" class="inp" placeholder="Search name/email/mobile..." value="<?=htmlspecialchars($q)?>">
          <select name="role_id" class="inp">
            <option value="0">All roles</option>
            <?php foreach($ROLE_OPTIONS as $opt): ?>
              <option value="<?=$opt['id']?>" <?=$rfl===(int)$opt['id']?'selected':''?>><?=htmlspecialchars($opt['name'])?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn gray" type="submit">Search</button>
          <?php if(!$all && $total>$lim): ?>
            <a class="btn gray" href="<?=htmlspecialchars(keep_params(['all'=>1]))?>">View All (<?=$total?>)</a>
          <?php endif; ?>
          <?php if($all): ?>
            <a class="btn gray" href="<?=htmlspecialchars(keep_params(['all'=>null]))?>">Last 50</a>
          <?php endif; ?>
        </form>

        <?php if (!empty($ACL['add'])): ?>
          <a class="btn green" href="<?=htmlspecialchars(keep_params(['add'=>1]))?>">Create User</a>
        <?php endif; ?>
      </div>

      <div style="margin:8px 0;color:#9ca3af">
        Showing <strong><?= !$all ? count($rows) : $total ?></strong> of <strong><?= $total ?></strong>
        <?= $q!=='' ? 'for “'.htmlspecialchars($q).'”' : '' ?>
        <?= !$all ? '(latest first)' : '' ?>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead><tr>
            <th>SR No</th><th>Name</th><th>Email</th><th>Mobile</th><th>Role</th><th>Status</th><th>Actions</th>
          </tr></thead>
          <tbody>
          <?php if(!$rows): ?>
            <tr><td colspan="7" style="color:#9ca3af">No records</td></tr>
          <?php endif; ?>
          <?php $sr=0; foreach($rows as $r): $sr++; ?>
            <tr>
              <td><?=$sr?></td>
              <td><?=htmlspecialchars($r['name'])?></td>
              <td><?=htmlspecialchars($r['email'])?></td>
              <td><?=htmlspecialchars($r['mobile_no'])?></td>
              <td><?=htmlspecialchars($r['role_name'] ?? '—')?></td>
              <td><span class="badge <?=$r['status']?'on':'off'?>"><?=$r['status']?'Active':'Inactive'?></span></td>
              <td>
                <?php if (!empty($ACL['edit'])): ?>
                  <a class="btn gray" href="<?=htmlspecialchars(keep_params(['edit'=>(int)$r['id']]))?>">Edit</a>
                <?php endif; ?>

                <?php if (!empty($ACL['delete']) && (int)$r['id'] !== (int)($me['id'] ?? 0)): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('Delete this user?');">
                    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
                    <input type="hidden" name="id" value="<?=$r['id']?>">
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

  <?php else: /* form mode: top, list hidden */ ?>

    <div class="card" style="margin-bottom:16px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <h3 style="margin:0"><?= $edit ? 'Edit User' : 'Create User' ?></h3>
        <?php
          $backHref = keep_params(['edit'=>null,'add'=>null]);
          if ($backHref === '?' || $backHref === '') {
            $backHref = $_SERVER['SCRIPT_NAME'] ?? '';
          }
        ?>
        <a class="btn gray" href="<?=htmlspecialchars($backHref)?>">Back to List</a>
      </div>

      <?php if($ok): ?><div class="alert ok" style="margin:8px 0"><?=htmlspecialchars($ok)?></div><?php endif; ?>
      <?php if($err): ?><div class="alert err" style="margin:8px 0"><?=htmlspecialchars($err)?></div><?php endif; ?>

      <form method="post" style="display:grid;grid-template-columns:1fr 1fr;gap:12px" autocomplete="off">
        <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
        <?php if($edit): ?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif; ?>

        <div style="grid-column:1/-1">
          <label>Full Name*</label>
          <input name="name" class="inp" required value="<?=htmlspecialchars($edit['name'] ?? '')?>">
        </div>

        <div>
          <label>Email</label>
          <input name="email" type="email" class="inp" value="<?=htmlspecialchars($edit['email'] ?? '')?>">
        </div>
        <div>
          <label>Mobile</label>
          <input name="mobile" class="inp" inputmode="numeric" pattern="[0-9]*" value="<?=htmlspecialchars($edit['mobile_no'] ?? '')?>">
        </div>

        <div>
          <label>Role*</label>
          <select name="role_id" class="inp" required>
            <option value="">Select role…</option>
            <?php foreach($ROLE_OPTIONS as $opt): ?>
              <option value="<?=$opt['id']?>" <?=($edit_role_id===(int)$opt['id'])?'selected':''?>><?=htmlspecialchars($opt['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Status</label>
          <?php $st = isset($edit['status']) ? (int)$edit['status'] : 1; ?>
          <select name="status" class="inp">
            <option value="1" <?=$st===1?'selected':''?>>Active</option>
            <option value="0" <?=$st===0?'selected':''?>>Inactive</option>
          </select>
        </div>

        <div>
          <label><?= $edit ? 'New Password (optional)' : 'Password*' ?></label>
          <input name="password" type="password" class="inp" <?= $edit? '' : 'required' ?>>
        </div>
        <div>
          <label><?= $edit ? 'Confirm New Password' : 'Confirm Password*' ?></label>
          <input name="confirm_password" type="password" class="inp" <?= $edit? '' : 'required' ?>>
        </div>

        <div style="grid-column:1/-1">
          <button class="btn green" name="save" type="submit">Save</button>
        </div>
      </form>
    </div>

  <?php endif; ?>
</div>
</body>
</html>
<?php
echo ob_get_clean();
