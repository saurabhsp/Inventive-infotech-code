<?php
@ini_set('display_errors','1'); @error_reporting(E_ALL);

require_once __DIR__ . '/../includes/initialize.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

if (!isset($con) || !$con) { die('DB connection not initialized'); }

if (!defined('DOMAIN_URL')) { define('DOMAIN_URL', '/'); }

/* ---------------- ACL: View-only guard ----------------
   Inserted here as requested — uses jos_admin_menus.menu_link for matching.
   - Allows ?menu_id= override for testing.
   - Normalizes request path and attempts to find matching menu.
   - Checks jos_admin_rolemenus.can_view for the current user via jos_admin_users_roles.
   - If can_view != 1 => returns 403 Access Denied (styled with /adminconsole/assets/ui.css).
-------------------------------------------------------*/

/* helper used below (already defined later in file too, but safe to declare/use here) */
function _get_int_for_acl($k, $d=0){ return isset($_GET[$k]) ? (int)$_GET[$k] : $d; }

$__acl_menu_id = _get_int_for_acl('menu_id', 0);

if ($__acl_menu_id <= 0) {
  // derive normalized request path (no query string)
  $req_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
  // normalize: remove trailing slash (but keep root '/')
  if ($req_path !== '/' ) { $req_path = rtrim($req_path, '/'); }
  // try to find menu by exact menu_link match or menu_link starting with path (to tolerate query parts)
  $sqlM = "SELECT id, menu_link FROM jos_admin_menus WHERE menu_link = ? OR menu_link LIKE CONCAT(?, '?%') LIMIT 1";
  $stm = $con->prepare($sqlM);
  if ($stm) {
    $stm->bind_param("ss", $req_path, $req_path);
    $stm->execute();
    $resM = $stm->get_result();
    if ($rowM = $resM->fetch_assoc()) {
      $__acl_menu_id = (int)$rowM['id'];
    }
    $stm->close();
  }
}

// If still not found, we can't map to a menu — in that case, deny by default for safety.
// (You can pass menu_id=X for testing pages not present in jos_admin_menus.)
if ($__acl_menu_id <= 0) {
  http_response_code(403);
  ?>
  <!doctype html>
  <html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>403 Access denied</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/adminconsole/assets/ui.css">
    <style>
      body{background:#0b0f19;color:#e5e7eb}
      .master-wrap{max-width:960px;margin:40px auto;padding:20px}
      .card{padding:28px;border-radius:12px;background:#071025}
      .title{font-size:20px;margin-bottom:8px}
      .muted{color:#9ca3af}
    </style>
  </head>
  <body>
  <div class="master-wrap">
    <div class="card">
      <div class="title">403 — Access denied</div>
      <div class="muted">You do not have permission to view this page.</div>
      <div style="margin-top:14px">
        <a class="btn secondary" href="/">← Back to dashboard</a>
      </div>
    </div>
  </div>
  </body>
  </html>
  <?php
  exit;
}

// Check role-menu permission: ensure current user has can_view = 1 for this menu
$uid = (int)(current_user()['id'] ?? 0);
$can_view_ok = false;

if ($uid > 0) {
  $sqlR = "
    SELECT rm.can_view
    FROM jos_admin_rolemenus rm
    JOIN jos_admin_users_roles ur ON ur.role_id = rm.role_id
    WHERE ur.user_id = ? AND rm.menu_id = ?
    LIMIT 1
  ";
  $stR = $con->prepare($sqlR);
  if ($stR) {
    $stR->bind_param("ii", $uid, $__acl_menu_id);
    $stR->execute();
    $resR = $stR->get_result();
    if ($rowR = $resR->fetch_assoc()) {
      $can_view_ok = ((int)$rowR['can_view'] === 1);
    }
    $stR->close();
  }
}

if (!$can_view_ok) {
  http_response_code(403);
  ?>
  <!doctype html>
  <html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>403 Access denied</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/adminconsole/assets/ui.css">
    <style>
      body{background:#0b0f19;color:#e5e7eb}
      .master-wrap{max-width:960px;margin:40px auto;padding:20px}
      .card{padding:28px;border-radius:12px;background:#071025}
      .title{font-size:20px;margin-bottom:8px}
      .muted{color:#9ca3af}
    </style>
  </head>
  <body>
  <div class="master-wrap">
    <div class="card">
      <div class="title">403 — Access denied</div>
      <div class="muted">You do not have permission to view this page.</div>
      <div style="margin-top:14px">
        <a class="btn secondary" href="/">← Back to dashboard</a>
      </div>
    </div>
  </div>
  </body>
  </html>
  <?php
  exit;
}

/* ---------------- Helpers ---------------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function get_int($key,$default=0){ return isset($_GET[$key]) ? (int)$_GET[$key] : $default; }
function get_str($key,$default=''){ return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default; }

/* SAFER date parser -> always returns Y-m-d or null; NO warnings */
function dfmt_in($dateStr){
  $dateStr = trim((string)$dateStr);
  if($dateStr==='') return null;
  $fmts = ['Y-m-d','d-m-Y','d/m/Y','m/d/Y','Y/m/d','d.m.Y','d m Y'];
  foreach($fmts as $f){
    $dt = DateTime::createFromFormat($f, $dateStr);
    $err = DateTime::getLastErrors();
    if ($err === false) { $err = ['warning_count'=>0,'error_count'=>0]; }   // <-- fix for your warning
    if($dt && ($err['warning_count']==0) && ($err['error_count']==0)){
      return $dt->format('Y-m-d');
    }
  }
  // final fallback via strtotime
  $t = strtotime($dateStr);
  return $t ? date('Y-m-d',$t) : null;
}

/* Output formatters (UI) */
function fmt_d($ts){               // dd/mm/yy
  $t = strtotime((string)$ts); if(!$t) return '';
  return date('d/m/y',$t);
}
function fmt_dt($ts){              // dd/mm/yy hh:mm AM/PM
  $t = strtotime((string)$ts); if(!$t) return '';
  return date('d/m/y g:i A',$t);
}

/* Keep params */
function keep_params(array $changes=[]){
  $qs = $_GET;
  foreach($changes as $k=>$v){ if($v===null){unset($qs[$k]);} else {$qs[$k]=$v;} }
  $q = http_build_query($qs); return $q?('?'.$q):'';
}

/* ---------------- Inputs / Filters ---------------- */
$mode   = get_str('mode','list'); // list | details
$df_in  = get_str('date_from','');
$dt_in  = get_str('date_to','');

$date_to   = dfmt_in($dt_in) ?: date('Y-m-d');
$date_from = dfmt_in($df_in) ?: date('Y-m-d', strtotime('-90 days', strtotime($date_to)));

$profile_type_id = get_int('profile_type_id', 0); // 0=All
$q        = get_str('q','');
$sort     = get_str('sort','earn_desc'); // earn_desc|earn_asc|tx_desc|tx_asc|name_asc|name_desc
$page     = max(1, get_int('page',1));
$per_page = min(100, max(10, get_int('per_page',20)));
$offset   = ($page-1)*$per_page;
$user_id  = get_int('user_id', 0);

/* ---------------- SQL Snippets ---------------- */
$displayNameSQL = "
  CASE u.profile_type_id
    WHEN 1 THEN COALESCE(rp.organization_name, CONCAT('Recruiter #', u.id))
    WHEN 2 THEN COALESCE(cp.candidate_name,   CONCAT('Candidate #', u.id))
    WHEN 3 THEN COALESCE(pp.name,             CONCAT('Promoter #', u.id))
    ELSE CONCAT('User #', u.id)
  END
";

$walletSub = "
  SELECT user_id,
         SUM(CASE WHEN transaction_type=1 AND status=1 THEN amount
                  WHEN transaction_type=2 AND status=1 THEN -amount
                  ELSE 0 END) AS wallet_balance
  FROM jos_app_wallet_transaction_log
  GROUP BY user_id
";

/* ---------------- Page Render ---------------- */
ob_start();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Cashback Report</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="/adminconsole/assets/ui.css">
  <style>
    .grid{display:grid; gap:12px;}
    .grid.cols-4{grid-template-columns: repeat(4, minmax(0,1fr));}
    .grid.cols-3{grid-template-columns: repeat(3, minmax(0,1fr));}
    .stat{padding:14px; border-radius:12px; background:#111827; color:#e5e7eb; box-shadow: 0 1px 0 #111,inset 0 1px 0 #222;}
    .stat h4{margin:0 0 4px; font-size:12px; color:#9ca3af; font-weight:600;}
    .stat div{font-size:18px; font-weight:700;}
    .table thead th{position:sticky; top:0; background:#0b0f19; z-index:2;}
    .badge.pt1{background:#0e7490;}
    .mono{font-variant-numeric: tabular-nums;}
    .toolbar .row{display:flex; gap:8px; flex-wrap:wrap;}
    .toolbar .row > *{flex:1 1 auto;}
    .toolbar .row .grow{flex:2 1 320px;}
  </style>
</head>
<body>
<div class="master-wrap">

  <div class="headbar">
    <div class="title">Cashback Report</div>
    <div class="actions">
      <?php if($mode==='details'): ?>
        <a class="btn secondary" href="<?=h(keep_params(['mode'=>'list','user_id'=>null,'page'=>1]))?>">← Back to Report</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <form method="get" class="toolbar">
      <input type="hidden" name="mode" value="<?=h($mode)?>">
      <?php if($mode==='details'): ?>
        <input type="hidden" name="user_id" value="<?=h($user_id)?>">
      <?php endif; ?>

      <div class="row">
        <div><label class="lbl">From</label>
          <input class="inp" type="date" name="date_from" value="<?=h($date_from)?>">
        </div>
        <div><label class="lbl">To</label>
          <input class="inp" type="date" name="date_to" value="<?=h($date_to)?>">
        </div>
        <div>
          <label class="lbl">Profile Type</label>
          <select class="inp" name="profile_type_id">
            <option value="0"  <?= $profile_type_id===0?'selected':''; ?>>All</option>
            <option value="1"  <?= $profile_type_id===1?'selected':''; ?>>Recruiter</option>
            <option value="2"  <?= $profile_type_id===2?'selected':''; ?>>Candidate</option>
            <option value="3"  <?= $profile_type_id===3?'selected':''; ?>>Promoter</option>
          </select>
        </div>
        <?php if($mode==='list'): ?>
          <div class="grow">
            <label class="lbl">Search (name / mobile)</label>
            <input class="inp" type="text" name="q" placeholder="Search…" value="<?=h($q)?>">
          </div>
        <?php endif; ?>
      </div>

      <?php if($mode==='list'): ?>
      <div class="row">
        <div>
          <label class="lbl">Sort By</label>
          <select class="inp" name="sort">
            <option value="earn_desc"  <?=$sort==='earn_desc'?'selected':''?>>High Earner ↓</option>
            <option value="earn_asc"   <?=$sort==='earn_asc'?'selected':''?>>Low Earner ↑</option>
            <option value="tx_desc"    <?=$sort==='tx_desc'?'selected':''?>>Tx Count ↓</option>
            <option value="tx_asc"     <?=$sort==='tx_asc'?'selected':''?>>Tx Count ↑</option>
            <option value="name_asc"   <?=$sort==='name_asc'?'selected':''?>>Name A–Z</option>
            <option value="name_desc"  <?=$sort==='name_desc'?'selected':''?>>Name Z–A</option>
          </select>
        </div>
        <div>
          <label class="lbl">Per Page</label>
          <input class="inp" type="number" min="10" max="100" name="per_page" value="<?=h($per_page)?>">
        </div>
        <div style="align-self:flex-end">
          <button class="btn primary" type="submit">Apply</button>
          <a class="btn secondary" href="<?=h(strtok($_SERVER['REQUEST_URI'],'?'))?>">Reset</a>
        </div>
      </div>
      <?php else: ?>
      <div class="row" style="justify-content:flex-end">
        <div style="align-self:flex-end">
          <button class="btn primary" type="submit">Apply</button>
          <a class="btn secondary" href="<?=h(keep_params(['date_from'=>null,'date_to'=>null,'page'=>1]))?>">Reset Dates</a>
        </div>
      </div>
      <?php endif; ?>
    </form>
  </div>

<?php
/* ===================== MODE: DETAILS ===================== */
if ($mode==='details' && $user_id>0):

  // Summaries
  $sqlW = "SELECT SUM(CASE WHEN transaction_type=1 AND status=1 THEN amount
                           WHEN transaction_type=2 AND status=1 THEN -amount
                           ELSE 0 END) AS bal
           FROM jos_app_wallet_transaction_log WHERE user_id=?";
  $stW = $con->prepare($sqlW); $stW->bind_param("i",$user_id); $stW->execute();
  $resW = $stW->get_result(); $wallet_balance = (float)($resW->fetch_assoc()['bal'] ?? 0);

  $sqlAll = "SELECT SUM(amount) AS total FROM jos_app_cashback_transactions WHERE user_id=?";
  $stA = $con->prepare($sqlAll); $stA->bind_param("i",$user_id); $stA->execute();
  $resA = $stA->get_result(); $total_cashback_earned = (float)($resA->fetch_assoc()['total'] ?? 0);

  $sqlR = "SELECT SUM(amount) AS total FROM jos_app_cashback_transactions WHERE user_id=? AND DATE(created_at) BETWEEN ? AND ?";
  $stR = $con->prepare($sqlR); $stR->bind_param("iss",$user_id,$date_from,$date_to); $stR->execute();
  $resR = $stR->get_result(); $range_cashback = (float)($resR->fetch_assoc()['total'] ?? 0);

  $userHdr = ['display_name'=>'','mobile_no'=>'','profile_type_id'=>null];
  $sqlU = "
    SELECT u.mobile_no, u.profile_type_id, $displayNameSQL AS display_name
    FROM jos_app_users u
    LEFT JOIN jos_app_recruiter_profile rp ON (u.profile_type_id=1 AND rp.userid=u.id)
    LEFT JOIN jos_app_candidate_profile  cp ON (u.profile_type_id=2 AND cp.userid=u.id)
    LEFT JOIN jos_app_promoter_profile   pp ON (u.profile_type_id=3 AND pp.userid=u.id)
    WHERE u.id=? LIMIT 1
  ";
  $stU = $con->prepare($sqlU); $stU->bind_param("i",$user_id); $stU->execute();
  $resU = $stU->get_result(); if($rowU=$resU->fetch_assoc()){ $userHdr=$rowU; }

  $where = " WHERE t.user_id=? AND DATE(t.created_at) BETWEEN ? AND ? ";
  $sqlC = "SELECT COUNT(*) AS cnt FROM jos_app_cashback_transactions t $where";
  $stC = $con->prepare($sqlC); $stC->bind_param("iss",$user_id,$date_from,$date_to); $stC->execute();
  $resC = $stC->get_result(); $total_rows = (int)($resC->fetch_assoc()['cnt'] ?? 0);

  $sqlL = "
    SELECT t.id, t.referral_userid, t.plan, t.amount, t.cashbacktype, t.remark, t.created_at,
           r.mobile_no AS referral_mobile, r.profile_type_id AS referral_profile_type_id
    FROM jos_app_cashback_transactions t
    LEFT JOIN jos_app_users r ON r.id=t.referral_userid
    $where
    ORDER BY t.created_at DESC, t.id DESC
    LIMIT ?, ?
  ";
  $stL = $con->prepare($sqlL);
  $stL->bind_param("issii",$user_id,$date_from,$date_to,$offset,$per_page);
  $stL->execute(); $resL = $stL->get_result();

  $total_pages = max(1, (int)ceil($total_rows/$per_page));
  ?>
  <div class="grid cols-3" style="margin-top:12px">
    <div class="stat">
      <h4>User</h4>
      <div><?=h($userHdr['display_name'] ?: ('User #'.$user_id))?> <span class="badge"><?=h($userHdr['mobile_no'])?></span></div>
    </div>
    <div class="stat">
      <h4>Wallet Balance</h4>
      <div class="mono">₹ <?=number_format($wallet_balance,2)?></div>
    </div>
    <div class="stat">
      <h4>Total Cashback (Overall)</h4>
      <div class="mono">₹ <?=number_format($total_cashback_earned,2)?></div>
    </div>
  </div>
  <div class="grid cols-3" style="margin-top:12px">
    <div class="stat">
      <h4>Date Range</h4>
      <div><?=fmt_d($date_from)?> → <?=fmt_d($date_to)?></div>
    </div>
    <div class="stat">
      <h4>Range Cashback</h4>
      <div class="mono">₹ <?=number_format($range_cashback,2)?></div>
    </div>
    <div class="stat">
      <h4>Profile Type</h4>
      <div>
        <?php $pt = (int)($userHdr['profile_type_id'] ?? 0);
          echo $pt===1?'Recruiter':($pt===2?'Candidate':($pt===3?'Promoter':'-')); ?>
      </div>
    </div>
  </div>

  <div class="card table-wrap" style="margin-top:14px">
    <div class="table-title">Cashback History</div>
    <table class="table">
      <thead>
        <tr>
          <th style="width:70px">SR No</th>
          <th style="width:160px">Date/Time</th>
          <th style="width:120px">Amount (₹)</th>
          <th style="width:110px">Type</th>
          <th>Remark</th>
          <th style="width:210px">Referral (ID / Mobile / Profile)</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($resL->num_rows===0): ?>
          <tr><td colspan="6" style="text-align:center;color:#9ca3af">No cashback records in this range.</td></tr>
        <?php else:
          $sr = $offset+1;
          while($r = $resL->fetch_assoc()):
            $rtype = (int)$r['cashbacktype'];
            $rpt   = (int)($r['referral_profile_type_id'] ?? 0);
        ?>
          <tr>
            <td><?=$sr++?></td>
            <td class="mono"><?=fmt_dt($r['created_at'])?></td>
            <td class="mono">₹ <?=number_format((float)$r['amount'],2)?></td>
            <td><span class="badge pt1"><?= $rtype===1?'Signup/First':'Other' ?></span></td>
            <td><?=h($r['remark'])?></td>
            <td class="mono">
              <?php
                $rid = (int)$r['referral_userid'];
                $rm  = h((string)($r['referral_mobile'] ?? ''));
                $rp  = $rpt===1?'Recruiter':($rpt===2?'Candidate':($rpt===3?'Promoter':'-'));
                echo $rid>0?("#$rid / $rm / $rp"):'-';
              ?>
            </td>
          </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>

    <?php if($total_pages>1): ?>
    <div class="pager" style="padding:10px; display:flex; gap:8px; justify-content:flex-end">
      <?php if($page>1): ?>
        <a class="btn secondary" href="<?=h(keep_params(['page'=>$page-1]))?>">Prev</a>
      <?php endif; ?>
      <span class="badge">Page <?=$page?> / <?=$total_pages?></span>
      <?php if($page<$total_pages): ?>
        <a class="btn secondary" href="<?=h(keep_params(['page'=>$page+1]))?>">Next</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

<?php
/* ===================== MODE: LIST ===================== */
else:

  $where = " WHERE DATE(t.created_at) BETWEEN ? AND ? ";
  $params = [$date_from, $date_to];
  $types  = "ss";

  if ($profile_type_id>0){
    $where .= " AND u.profile_type_id = ? ";
    $params[] = $profile_type_id; $types .= "i";
  }
  if ($q!==''){
  $where .= " AND (
      u.mobile_no COLLATE utf8mb4_0900_ai_ci LIKE ?
      OR (
        $displayNameSQL COLLATE utf8mb4_0900_ai_ci
      ) LIKE ?
    ) ";

  $like = "%".$q."%";
  $params[] = $like;
  $params[] = $like;
  $types .= "ss";
}


  switch ($sort) {
    case 'earn_asc':  $order = " ORDER BY total_cashback ASC, tx_count ASC "; break;
    case 'tx_desc':   $order = " ORDER BY tx_count DESC, total_cashback DESC "; break;
    case 'tx_asc':    $order = " ORDER BY tx_count ASC, total_cashback DESC "; break;
    case 'name_asc':  $order = " ORDER BY display_name ASC "; break;
    case 'name_desc': $order = " ORDER BY display_name DESC "; break;
    case 'earn_desc':
    default:          $order = " ORDER BY total_cashback DESC, tx_count DESC ";
  }

  $countSQL = "
    SELECT COUNT(*) AS cnt FROM (
      SELECT t.user_id
      FROM jos_app_cashback_transactions t
      JOIN jos_app_users u ON u.id=t.user_id
      LEFT JOIN jos_app_recruiter_profile rp ON (u.profile_type_id=1 AND rp.userid=u.id)
      LEFT JOIN jos_app_candidate_profile  cp ON (u.profile_type_id=2 AND cp.userid=u.id)
      LEFT JOIN jos_app_promoter_profile   pp ON (u.profile_type_id=3 AND pp.userid=u.id)
      $where
      GROUP BY t.user_id
    ) x
  ";
  $stCount = $con->prepare($countSQL);
  $stCount->bind_param($types, ...$params);
  $stCount->execute(); $resCount = $stCount->get_result();
  $total_rows = (int)($resCount->fetch_assoc()['cnt'] ?? 0);
  $total_pages = max(1, (int)ceil($total_rows/$per_page));

  $sql = "
    SELECT 
      u.id AS user_id,
      u.mobile_no,
      u.profile_type_id,
      $displayNameSQL AS display_name,
      IFNULL(w.wallet_balance,0) AS wallet_balance,
      SUM(t.amount) AS total_cashback,
      COUNT(t.id)  AS tx_count,
      MIN(t.created_at) AS first_tx,
      MAX(t.created_at) AS last_tx
    FROM jos_app_cashback_transactions t
    JOIN jos_app_users u ON u.id=t.user_id
    LEFT JOIN jos_app_recruiter_profile rp ON (u.profile_type_id=1 AND rp.userid=u.id)
    LEFT JOIN jos_app_candidate_profile  cp ON (u.profile_type_id=2 AND cp.userid=u.id)
    LEFT JOIN jos_app_promoter_profile   pp ON (u.profile_type_id=3 AND pp.userid=u.id)
    LEFT JOIN ($walletSub) w ON w.user_id=u.id
    $where
    GROUP BY u.id
    $order
    LIMIT ?, ?
  ";
  $params2 = $params; $types2 = $types . "ii";
  $params2[] = $offset; $params2[] = $per_page;

  $st = $con->prepare($sql);
  $st->bind_param($types2, ...$params2);
  $st->execute(); $res = $st->get_result();

  $page_total_cashback = 0.0;
  ?>

  <div class="card table-wrap" style="margin-top:12px">
    <div class="table-title">
      Date-wise Cashback Earners
      <span class="badge">Range: <?=fmt_d($date_from)?> → <?=fmt_d($date_to)?></span>
      <?php if($profile_type_id>0): ?>
        <span class="badge">
          <?php echo $profile_type_id===1?'Recruiter':($profile_type_id===2?'Candidate':($profile_type_id===3?'Promoter':'All')); ?>
        </span>
      <?php endif; ?>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th style="width:70px">SR No</th>
          <th>User</th>
          <th style="width:120px">Profile</th>
          <th style="width:150px">Mobile</th>
          <th style="width:150px">Wallet (₹)</th>
          <th style="width:160px">Total Cashback (₹)</th>
          <th style="width:110px">Tx Count</th>
          <th style="width:160px">First Tx</th>
          <th style="width:160px">Last Tx</th>
          <th style="width:130px">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php
      if ($res->num_rows===0): ?>
        <tr><td colspan="10" style="text-align:center;color:#9ca3af">No users found for the selected range.</td></tr>
      <?php
      else:
        $sr = $offset+1;
        while($r=$res->fetch_assoc()):
          $page_total_cashback += (float)$r['total_cashback'];
      ?>
        <tr>
          <td><?=$sr++?></td>
          <td>
            <div style="font-weight:600"><?=h($r['display_name'])?></div>
            <div class="mono" style="color:#9ca3af">#<?= (int)$r['user_id'] ?></div>
          </td>
          <td>
            <span class="badge">
              <?php
              $pt=(int)$r['profile_type_id'];
              echo $pt===1?'Recruiter':($pt===2?'Candidate':($pt===3?'Promoter':'-'));
              ?>
            </span>
          </td>
          <td class="mono"><?=h($r['mobile_no'])?></td>
          <td class="mono">₹ <?=number_format((float)$r['wallet_balance'],2)?></td>
          <td class="mono">₹ <?=number_format((float)$r['total_cashback'],2)?></td>
          <td class="mono"><?= (int)$r['tx_count'] ?></td>
          <td class="mono"><?=fmt_dt($r['first_tx'])?></td>
          <td class="mono"><?=fmt_dt($r['last_tx'])?></td>
          <td>
            <a class="btn secondary"
               href="<?=h(keep_params(['mode'=>'details','user_id'=>(int)$r['user_id'],'page'=>1]))?>">
               View Details
            </a>
          </td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>

      <?php if($res->num_rows>0): ?>
      <tfoot>
        <tr>
          <th colspan="5" style="text-align:right">Page Total:</th>
          <th class="mono">₹ <?=number_format($page_total_cashback,2)?></th>
          <th colspan="4"></th>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>

    <?php if($total_pages>1): ?>
    <div class="pager" style="padding:10px; display:flex; gap:8px; justify-content:flex-end">
      <?php if($page>1): ?>
        <a class="btn secondary" href="<?=h(keep_params(['page'=>$page-1]))?>">Prev</a>
      <?php endif; ?>
      <span class="badge">Page <?=$page?> / <?=$total_pages?></span>
      <?php if($page<$total_pages): ?>
        <a class="btn secondary" href="<?=h(keep_params(['page'=>$page+1]))?>">Next</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
<?php endif; // end modes ?>

</div><!-- /master-wrap -->
</body>
</html>
<?php
echo ob_get_clean();
