<?php
@ini_set('display_errors', '1'); @error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_login(); // also provides $con (mysqli)

global $con;

/* =========================
   Config / Tables
========================= */
$TABLE    = 'jos_crm_skills';
$JOBTABLE = 'jos_crm_jobpost';
$MENUTBL  = 'jos_admin_menus';

/* =========================
   Tiny utils
========================= */
function clean($v){ return trim((string)$v); }
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/**
 * keep_params(array $changes = [])
 * - Builds a query string from current $_GET with optional changes.
 * - If the resulting query string is non-empty returns "?a=b&..."
 * - If empty, returns the current script path (so href is never empty).
 */
function keep_params(array $changes = []) {
  // Start with current GET params (avoid modifying $_GET)
  $qs = $_GET;
  foreach ($changes as $k => $v) {
    if ($v === null) {
      unset($qs[$k]);
    } else {
      $qs[$k] = $v;
    }
  }

  // Build query string
  $q = http_build_query($qs);
  if ($q) {
    return '?'.$q;
  }

  // Fallback: return the script path so href is never empty.
  // Use SCRIPT_NAME which gives a predictable path like "/adminconsole/masters/skills.php"
  $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? basename(__FILE__));
  return $script;
}

function table_exists(mysqli $con, string $name): bool {
  $name = mysqli_real_escape_string($con, $name);
  $rs = mysqli_query($con, "SHOW TABLES LIKE '$name'");
  return ($rs && mysqli_num_rows($rs) > 0);
}
function col_exists(mysqli $con, string $table, string $col): bool {
  $t = mysqli_real_escape_string($con, $table);
  $c = mysqli_real_escape_string($con, $col);
  $r = mysqli_query($con, "SHOW COLUMNS FROM `$t` LIKE '$c'");
  return ($r && mysqli_num_rows($r) > 0);
}

/* =========================
   Ensure schema (kept)
========================= */
function ensure_schema($con,$table){
  if (!col_exists($con,$table,'status')) {
    mysqli_query($con,"ALTER TABLE `$table` ADD `status` TINYINT(1) NOT NULL DEFAULT 1");
  }
  if (!col_exists($con,$table,'position')) {
    mysqli_query($con,"ALTER TABLE `$table` ADD `position` INT NOT NULL DEFAULT 0");
  } else {
    mysqli_query($con,"ALTER TABLE `$table` MODIFY `position` INT NOT NULL DEFAULT 0");
  }
}
ensure_schema($con,$TABLE);

/* =========================
   Current menu (for title + ACL)
========================= */
function current_script_paths(): array {
  // Absolute script path (e.g., /beta/adminconsole/masters/skills.php or /adminconsole/masters/skills.php)
  $script = $_SERVER['SCRIPT_NAME'] ?? '';
  $req    = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: $script;

  $variants = [];
  $variants[] = $script;
  $variants[] = $req;

  // add variants without a possible '/beta' prefix (do NOT force beta logic; just tolerate both)
  $variants[] = preg_replace('#^/beta#','', $script);
  $variants[] = preg_replace('#^/beta#','', $req);

  // unique, keep non-empty
  $variants = array_values(array_unique(array_filter($variants, function($p){ return (string)$p !== ''; })));
  return $variants;
}

function fetch_menu_info(mysqli $con, string $MENUTBL): array {
  $out = ['id'=>0, 'menu_name'=>'', 'menu_link'=>''];
  if (!table_exists($con, $MENUTBL)) return $out;

  $paths = current_script_paths();

  // Try exact match first, then ending-with match
  foreach ($paths as $p) {
    $p_esc = mysqli_real_escape_string($con, $p);
    $sql = "SELECT id, menu_name, menu_link FROM `$MENUTBL` WHERE menu_link=? LIMIT 1";
    if ($st = $con->prepare($sql)) {
      $st->bind_param('s', $p);
      $st->execute();
      if ($r = $st->get_result()->fetch_assoc()) { $st->close(); return $r; }
      $st->close();
    }
  }
  // fallback: path suffix match (handles full URLs stored, or base paths)
  foreach ($paths as $p) {
    $like = '%'.mysqli_real_escape_string($con, $p);
    $sql = "SELECT id, menu_name, menu_link FROM `$MENUTBL` WHERE menu_link LIKE ? ORDER BY LENGTH(menu_link) DESC LIMIT 1";
    if ($st = $con->prepare($sql)) {
      $st->bind_param('s', $like);
      $st->execute();
      if ($r = $st->get_result()->fetch_assoc()) { $st->close(); return $r; }
      $st->close();
    }
  }
  return $out;
}

$menu = fetch_menu_info($con, $MENUTBL);
$page_title = $menu['menu_name'] ?: 'Skills Master';
$MENU_ID    = (int)($menu['id'] ?? 0);

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

/* Gate "view" before doing anything else */
if (!user_can('view', $MENU_ID, $con)) {
  http_response_code(403);
  ?>
  <!doctype html>
  <meta charset="utf-8">
  <link rel="stylesheet" href="/adminconsole/assets/ui.css">
  <div class="card" style="max-width:680px;margin:24px auto">
    <h3 style="margin-top:0"><?=h($page_title)?></h3>
    <div class="badge off" style="margin:8px 0">You are not authorized to view this content.</div>
  </div>
  <?php
  exit;
}

/* =========================
   Flash redirect helper
========================= */
function flash_redirect(string $msg='Saved'){
  $qs = $_GET; unset($qs['add'], $qs['edit']);
  $qs['ok'] = $msg;
  header('Location: ?'.http_build_query($qs));
  exit;
}

/* =========================
   POST (with ACL)
========================= */
$err=''; $ok='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  if (!verify_csrf($_POST['csrf'] ?? null)) {
    $err='Invalid request.';
  } else {
    /* Save (insert/update) */
    if (isset($_POST['save'])) {
      $id       = (int)($_POST['id'] ?? 0);
      $title    = trim($_POST['title'] ?? '');
      $position = (int)($_POST['position'] ?? 0);
      $status   = (int)($_POST['status'] ?? 1);

      // ACL check: add vs edit
      if ($id > 0 && !user_can('edit', $MENU_ID, $con)) {
        $err = 'You are not authorized to edit.';
      } elseif ($id === 0 && !user_can('add', $MENU_ID, $con)) {
        $err = 'You are not authorized to add.';
      } elseif ($title==='') {
        $err='Title is required.';
      } elseif ($position<=0) {
        $err='Please select a Job Position from the suggestions.';
      } else {
        if ($id>0){
          $st=$con->prepare("UPDATE $TABLE SET title=?, position=?, status=? WHERE id=?");
          $st->bind_param('siii',$title,$position,$status,$id);
          if ($st->execute()) { $st->close(); flash_redirect('Updated successfully'); }
          $err='Update failed';
          $st->close();
        } else {
          $st=$con->prepare("INSERT INTO $TABLE (title, position, status) VALUES (?,?,?)");
          $st->bind_param('sii',$title,$position,$status);
          if ($st->execute()) { $st->close(); flash_redirect('Saved successfully'); }
          $err='Insert failed';
          $st->close();
        }
      }
    }

    /* Delete */
    if (isset($_POST['delete'])) {
      if (!user_can('delete', $MENU_ID, $con)) {
        $err = 'You are not authorized to delete.';
      } else {
        $id=(int)$_POST['id'];
        $st=$con->prepare("DELETE FROM $TABLE WHERE id=?");
        $st->bind_param('i',$id);
        if ($st->execute()) { $st->close(); flash_redirect('Deleted successfully'); }
        $err='Delete failed';
        $st->close();
      }
    }
  }
}

/* =========================
   Mode (respect ACL for add/edit)
========================= */
$mode = (isset($_GET['edit']) || isset($_GET['add'])) ? 'form' : 'list';

/* If trying to add/edit without permission, show 403-like card (but keep page chrome) */
if ($mode==='form') {
  if (isset($_GET['add']) && !user_can('add', $MENU_ID, $con)) { $mode='denied_form'; $err='You are not authorized to add.'; }
  if (isset($_GET['edit']) && !user_can('edit', $MENU_ID, $con)) { $mode='denied_form'; $err='You are not authorized to edit.'; }
}

/* =========================
   Edit row
========================= */
$edit=null;
if ($mode==='form' && isset($_GET['edit'])){
  $eid=(int)$_GET['edit'];
  $st=$con->prepare("SELECT id,title,position,status FROM $TABLE WHERE id=?");
  $st->bind_param('i',$eid); $st->execute();
  $edit=$st->get_result()->fetch_assoc();
  $st->close();
}

/* =========================
   Filters
========================= */
$q = trim($_GET['q'] ?? '');
$jobFilter = (int)($_GET['job'] ?? 0);
$all = isset($_GET['all']);
$lim = $all ? 0 : 50;

$where = " WHERE 1=1 ";
$bind=[]; $type='';
if ($q!==''){ $where.=" AND s.title LIKE ?"; $bind[]="%$q%"; $type.='s'; }
if ($jobFilter>0){ $where.=" AND s.position=?"; $bind[]=$jobFilter; $type.='i'; }

/* =========================
   Counts / list
========================= */
$st=$con->prepare("SELECT COUNT(*) c FROM $TABLE s $where");
if ($bind) $st->bind_param($type, ...$bind);
$st->execute(); $total=(int)$st->get_result()->fetch_assoc()['c']; $st->close();

$sql="SELECT s.id,s.title,s.status,s.position,
             j.name AS job_name,
             (SELECT COUNT(*) FROM $TABLE ss WHERE ss.position=s.position) AS skills_count
      FROM $TABLE s
      LEFT JOIN $JOBTABLE j ON j.id=s.position
      $where
      ORDER BY s.id DESC";
if (!$all) $sql.=" LIMIT $lim";
$st=$con->prepare($sql);
if ($bind) $st->bind_param($type, ...$bind);
$st->execute();
$rs=$st->get_result(); $rows=[];
while($r=$rs->fetch_assoc()) $rows[]=$r;
$st->close();

/* =========================
   Job list for autocomplete
========================= */
$jrows=[]; $jr=mysqli_query($con,"SELECT id,name FROM $JOBTABLE WHERE status=1 ORDER BY name ASC");
while($jr && $r=mysqli_fetch_assoc($jr)) $jrows[]=$r;

/* =========================
   View
========================= */
ob_start(); ?>
<link rel="stylesheet" href="/adminconsole/assets/ui.css">

<h2 style="margin:8px 0 12px"><?=h($page_title)?></h2>

<?php if ($mode==='denied_form'): ?>
  <div class="card" style="max-width:760px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <h3 style="margin:0">Access denied</h3>
      <!-- Back to List works for both add & edit -->
      <a class="btn gray" href="<?=h(keep_params(['edit'=>null,'add'=>null]))?>">Back to List</a>
    </div>
    <?php if($err): ?><div class="badge off" style="margin:8px 0"><?=h($err)?></div><?php endif; ?>
    <p style="color:#9ca3af;margin:8px 0">You don't have permission to perform this action.</p>
  </div>

<?php elseif ($mode==='list'): ?>
  <?php if(isset($_GET['ok'])): ?><div class="alert ok"><?=h($_GET['ok'])?></div><?php endif; ?>
  <div class="card">
    <div class="toolbar">
      <form method="get" class="search">
        <input type="text" name="q" class="inp" placeholder="Search by skill..." value="<?=h($q)?>">
        <input type="text" id="jobinput" class="inp" placeholder="Filter by Job Position"
               value="<?php
                 if($jobFilter>0){
                   foreach($jrows as $j){ if((int)$j['id']===$jobFilter){ echo h($j['name']); break; } }
                 }
               ?>">
        <input type="hidden" name="job" id="job" value="<?=$jobFilter?>">
        <datalist id="joblist">
          <?php foreach($jrows as $j): ?>
            <option data-id="<?=$j['id']?>" value="<?=$j['name']?>"></option>
          <?php endforeach; ?>
        </datalist>
        <button class="btn gray" type="submit">Search</button>
        <?php if(!$all && $total>$lim): ?><a class="btn gray" href="<?=h(keep_params(['all'=>1]))?>">View All (<?=$total?>)</a><?php endif; ?>
        <?php if($all): ?><a class="btn gray" href="<?=h(keep_params(['all'=>null]))?>">Last 50</a><?php endif; ?>
      </form>
      <?php if (user_can('add', $MENU_ID, $con)): ?>
        <a class="btn green" href="<?=h(keep_params(['add'=>1]))?>">Add New</a>
      <?php endif; ?>
    </div>

    <div style="margin:8px 0;color:#9ca3af">
      Showing <strong><?= !$all ? count($rows) : $total ?></strong> of <strong><?= $total ?></strong>
      <?= $q!=='' ? 'for “'.h($q).'”' : '' ?>
      <?= !$all ? '(latest first)' : '' ?>
    </div>

    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>SR No</th><th>Title</th><th>Job Position</th><th>Skills Count</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if(!$rows): ?><tr><td colspan="6" style="color:#9ca3af">No records</td></tr><?php endif; ?>
        <?php $sr=0; foreach($rows as $r): $sr++; ?>
          <tr>
            <td><?=$sr?></td>
            <td><?=h($r['title'])?></td>
            <td><?=h($r['job_name'] ?? '—')?></td>
            <td><?=$r['skills_count']?></td>
            <td><span class="badge <?=$r['status']?'on':'off'?>"><?=$r['status']?'Active':'Inactive'?></span></td>
            <td>
              <?php if (user_can('edit', $MENU_ID, $con)): ?>
                <a class="btn gray" href="<?=h(keep_params(['edit'=>(int)$r['id']]))?>">Edit</a>
              <?php endif; ?>
              <?php if (user_can('delete', $MENU_ID, $con)): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this record?');">
                  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
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

<?php else: /* form (allowed) */ ?>
  <div class="card" style="max-width:760px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <h3 style="margin:0"><?= $edit ? 'Edit Skill' : 'Add Skill' ?></h3>
      <!-- Back to List removes add/edit flags; works for both -->
      <a class="btn gray" href="<?=h(keep_params(['edit'=>null,'add'=>null]))?>">Back to List</a>
    </div>

    <?php if($err): ?><div class="badge off" style="margin:8px 0"><?=h($err)?></div><?php endif; ?>

    <form method="post" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <?php if($edit): ?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif; ?>

      <div style="grid-column:1/-1">
        <label>Title*</label>
        <input name="title" class="inp" required value="<?=h($edit['title'] ?? '')?>">
      </div>

      <div>
        <label>Job Position*</label>
        <?php
          $jid = (int)($edit['position'] ?? 0);
          $jname = '';
          foreach($jrows as $j){ if((int)$j['id']===$jid){ $jname=$j['name']; break; } }
        ?>
        <input type="text" id="jobinput2" class="inp" placeholder="Start typing..." value="<?=h($jname)?>">
        <input type="hidden" name="position" id="jobid2" value="<?=$jid?>">
        <datalist id="joblist2">
          <?php foreach($jrows as $j): ?>
            <option data-id="<?=$j['id']?>" value="<?=$j['name']?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>

      <div>
        <label>Status</label>
        <?php $st = isset($edit['status']) ? (int)$edit['status'] : 1; ?>
        <select name="status" class="inp">
          <option value="1" <?=$st===1?'selected':''?>>Active</option>
          <option value="0" <?=$st===0?'selected':''?>>Inactive</option>
        </select>
      </div>

      <div style="grid-column:1/-1">
        <button class="btn green" name="save" type="submit">Save</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<script>
(function(){
  function wire(inputId, datalistId, hiddenId){
    var inp=document.getElementById(inputId);
    if(!inp) return;
    var hid= hiddenId ? document.getElementById(hiddenId) : null;
    var dl = document.getElementById(datalistId);
    var map={};
    if(dl){
      dl.querySelectorAll('option').forEach(function(o){
        if(o.getAttribute('data-id')) map[o.value]=o.getAttribute('data-id');
      });
    }
    function sync(){ var id = map[inp.value] || 0; if(hid) hid.value = id; }
    inp.setAttribute('list', datalistId);
    inp.addEventListener('input', sync);
    sync();
  }
  wire('jobinput','joblist','job');
  wire('jobinput2','joblist2','jobid2');
})();
</script>

<?php
echo ob_get_clean();
