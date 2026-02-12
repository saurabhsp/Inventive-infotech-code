<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/initialize.php';
require_once __DIR__ . '/../includes/aclhelper.php';

if (!function_exists('is_logged_in') || !is_logged_in()) {
    redirect('../login.php');
}
date_default_timezone_set('Asia/Kolkata');

/* ---------------- Helpers ---------------- */
function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function parse_dmy_to_ymd($val){
    $val = trim((string)$val);
    if ($val === '') return '';
    $dt = DateTime::createFromFormat('d-m-Y', $val);
    if (!$dt) $dt = DateTime::createFromFormat('Y-m-d', $val);
    return $dt ? $dt->format('Y-m-d') : '';
}

/* ---------------- Config ---------------- */
$TABLE_HEADER           = 'jos_ierp_complaint';
$TABLE_CUSTOMERS        = 'jos_ierp_customermaster';
$TABLE_SITES            = 'jos_crm_siteaddress_grid';
$TABLE_PLANTS           = 'jos_erp_plantname';
$TABLE_PROCESS_TYPE     = 'jos_complaint_process_type';
$TABLE_COMPLAINT_STATUS = 'jos_ierp_complaintstatus';
$TABLE_MECH_STATUS      = 'jos_comech_status';

/* ---------------- MENU META + ACL ---------------- */
$aclMeta = erp_get_menu_meta_and_acl($con);

$menuMetaTitle  = $aclMeta['title']      ?? 'Complaint Report';
$menuMetaRemark = $aclMeta['remark']     ?? 'Filter and view complaints';
$canView        = $aclMeta['can_view']   ?? false;
$canEdit        = $aclMeta['can_edit']   ?? false;

$userObj = current_user() ?? [];
if (!$canView) {
    $user        = $userObj;
    $pageTitle   = $menuMetaTitle;
    $systemTitle = 'ERP Console';
    $systemCode  = 'AGCM';
    $userName    = $user['name'] ?? 'User';
    $userLoginId = $user['login_id'] ?? ($user['email'] ?? ($user['mobile_no'] ?? ''));

    ob_start(); ?>
    <div class="master-wrap">
        <div class="headbar">
            <div>
                <h1 class="page-title"><?php echo h($menuMetaTitle); ?></h1>
                <div class="page-subtitle"><?php echo h($menuMetaRemark); ?></div>
            </div>
        </div>
        <div class="card" style="margin-top:20px; padding:24px; border-radius:16px; background:#fff;">
            <div class="alert danger">You do not have permission to view this page.</div>
        </div>
    </div>
    <?php
    $CONTENT = ob_get_clean();
    require_once __DIR__ . '/../includes/ui_autoshell.php';
    exit;
}

/* ---------------- Masters for filters ---------------- */
$processTypes = [];
$resPT = $con->query("SELECT id, name FROM {$TABLE_PROCESS_TYPE} ORDER BY name ASC");
if ($resPT) { while($r=$resPT->fetch_assoc()) $processTypes[]=$r; $resPT->free(); }

$complaintStatusOptions = [];
$resCS = $con->query("SELECT id, name FROM {$TABLE_COMPLAINT_STATUS} ORDER BY id ASC");
if ($resCS) { while($r=$resCS->fetch_assoc()) $complaintStatusOptions[]=$r; $resCS->free(); }

$mechanicStatusOptions = [];
$resMS = $con->query("SELECT id, name FROM {$TABLE_MECH_STATUS} WHERE status=1 ORDER BY order_by ASC");
if ($resMS) { while($r=$resMS->fetch_assoc()) $mechanicStatusOptions[]=$r; $resMS->free(); }

$mechanics = [];
$resMech = $con->query(
    "SELECT DISTINCT a.id, a.name
       FROM jos_users a
       JOIN jos_user_usergroup_map b ON a.id = b.user_id
      WHERE b.group_id = 213
   ORDER BY a.name ASC"
);
if ($resMech) { while($r=$resMech->fetch_assoc()) $mechanics[]=$r; $resMech->free(); }

/* ---------------- Filters (GET) ---------------- */
$q            = trim($_GET['q'] ?? '');
$from_dmy     = trim($_GET['from'] ?? '');
$to_dmy       = trim($_GET['to'] ?? '');
$from_ymd     = parse_dmy_to_ymd($from_dmy);
$to_ymd       = parse_dmy_to_ymd($to_dmy);

$customer_id  = (int)($_GET['customer_id'] ?? 0);
$customer_nm  = trim($_GET['customer_name'] ?? '');

$sitename_id  = (int)($_GET['sitename'] ?? 0);
$plant_id     = (int)($_GET['plantname'] ?? 0);

$process_type = (int)($_GET['process_type'] ?? 0);
$cstatus      = (int)($_GET['complaint_status'] ?? 0);
$mstatus      = (int)($_GET['mechanic_status'] ?? 0);

$mech_ids     = $_GET['assign_mechanic'] ?? [];
if (!is_array($mech_ids)) $mech_ids = [];
$mech_ids     = array_values(array_filter(array_map('intval', $mech_ids), fn($v)=>$v>0));

$view_all     = (int)($_GET['view_all'] ?? 0);
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = $view_all ? 5000 : 50;
$offset       = ($page - 1) * $limit;

/* ---------------- Build SQL ---------------- */
$where = [];
$types = '';
$params = [];

if ($from_ymd !== '' && $to_ymd !== '') {
    $where[] = "c.date BETWEEN ? AND ?";
    $types  .= "ss";
    $params[] = $from_ymd;
    $params[] = $to_ymd;
} elseif ($from_ymd !== '') {
    $where[] = "c.date >= ?";
    $types  .= "s";
    $params[] = $from_ymd;
} elseif ($to_ymd !== '') {
    $where[] = "c.date <= ?";
    $types  .= "s";
    $params[] = $to_ymd;
}

if ($customer_id > 0) { $where[]="c.custid=?"; $types.="i"; $params[]=$customer_id; }
if ($sitename_id > 0) { $where[]="c.sitename=?"; $types.="i"; $params[]=$sitename_id; }
if ($plant_id > 0)    { $where[]="c.plant=?"; $types.="i"; $params[]=$plant_id; }

if ($process_type > 0){ $where[]="c.process_type=?"; $types.="i"; $params[]=$process_type; }
if ($cstatus > 0)     { $where[]="c.complaintstatus=?"; $types.="i"; $params[]=$cstatus; }
if ($mstatus > 0)     { $where[]="c.co_mech_status=?"; $types.="i"; $params[]=$mstatus; }

if ($q !== '') {
    $where[] = "(c.customer LIKE ? OR CAST(c.orderno AS CHAR) LIKE ? OR CAST(c.id AS CHAR) LIKE ?)";
    $types  .= "sss";
    $like = "%{$q}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}

if (!empty($mech_ids)) {
    // match any selected mechanic in CSV list
    $or = [];
    foreach ($mech_ids as $mid) {
        $or[] = "FIND_IN_SET(?, c.assign_mechanic)";
        $types .= "i";
        $params[] = $mid;
    }
    $where[] = "(" . implode(" OR ", $or) . ")";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

/* Count */
$countSql = "SELECT COUNT(DISTINCT c.id) AS cnt FROM {$TABLE_HEADER} c {$whereSql}";
$cnt = 0;
$stc = $con->prepare($countSql);
if ($stc) {
    if ($types !== '') $stc->bind_param($types, ...$params);
    $stc->execute();
    $cntRow = $stc->get_result()->fetch_assoc();
    $cnt = (int)($cntRow['cnt'] ?? 0);
    $stc->close();
}

/* List */
$sql = "
SELECT
    c.id,
    c.orderno,
    c.date,
    c.customer,
    c.custid,
    c.sitename,
    s.sitename AS site_name,
    c.plant,
    pn.name AS plant_name,
    c.process_type,
    pt.name AS process_type_name,
    c.complaintstatus,
    cs.name AS complaint_status_name,
    c.statusdate,
    c.description,
    c.scheduledate,
    c.mechanic_allotmentdate,
    c.co_mech_status,
    ms.name AS mech_status_name,
    c.assign_mechanic,
    GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR ', ') AS mechanic_names
FROM {$TABLE_HEADER} c
LEFT JOIN {$TABLE_SITES} s ON s.id = c.sitename
LEFT JOIN {$TABLE_PLANTS} pn ON pn.id = c.plant
LEFT JOIN {$TABLE_PROCESS_TYPE} pt ON pt.id = c.process_type
LEFT JOIN {$TABLE_COMPLAINT_STATUS} cs ON cs.id = c.complaintstatus
LEFT JOIN {$TABLE_MECH_STATUS} ms ON ms.id = c.co_mech_status
LEFT JOIN jos_users u ON FIND_IN_SET(u.id, c.assign_mechanic)
{$whereSql}
GROUP BY c.id
ORDER BY c.id DESC
" . ($view_all ? "" : " LIMIT {$limit} OFFSET {$offset}");

$rows = [];
$st = $con->prepare($sql);
if ($st) {
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $st->close();
}

/* Shell vars */
$user        = $userObj;
$pageTitle   = $menuMetaTitle;
$systemTitle = 'ERP Console';
$systemCode  = 'AGCM';
$userName    = $user['name'] ?? 'User';
$userLoginId = $user['login_id'] ?? ($user['email'] ?? ($user['mobile_no'] ?? ''));

/* Render */
ob_start();
?>
<div class="master-wrap">
    <div class="headbar no-print">
        <div>
            <h1 class="page-title"><?php echo h($menuMetaTitle); ?></h1>
            <div class="page-subtitle"><?php echo h($menuMetaRemark); ?></div>
        </div>
       
    </div>

    <div class="card no-print" style="padding:16px 18px; margin:14px 0; background:#fff; border-radius:16px;">
        <form method="get" autocomplete="off" id="filterForm">
            <div style="display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:12px; align-items:end;">
                <div class="field">
                    <label>From Date</label>
                    <input type="text" name="from" class="inp datepick" placeholder="DD-MM-YYYY" value="<?php echo h($from_dmy); ?>">
                </div>
                <div class="field">
                    <label>To Date</label>
                    <input type="text" name="to" class="inp datepick" placeholder="DD-MM-YYYY" value="<?php echo h($to_dmy); ?>">
                </div>

                <div class="field" style="grid-column:3 / span 2;">
                    <label>Customer</label>
                    <input type="hidden" name="customer_id" id="customer_id" value="<?php echo (int)$customer_id; ?>">
                    <input type="text" name="customer_name" id="customer_name" class="inp"
                           placeholder="Type customer name..."
                           value="<?php echo h($customer_nm); ?>">
                </div>

                <div class="field">
                    <label>Search</label>
                    <input type="text" name="q" class="inp" placeholder="Order no / customer" value="<?php echo h($q); ?>">
                </div>

                <div class="field">
                    <label>Process Type</label>
                    <select name="process_type" class="inp">
                        <option value="">All</option>
                        <?php foreach($processTypes as $pt): ?>
                            <option value="<?php echo (int)$pt['id']; ?>" <?php echo ((int)$process_type===(int)$pt['id'])?'selected':''; ?>>
                                <?php echo h($pt['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Complaint Status</label>
                    <select name="complaint_status" class="inp">
                        <option value="">All</option>
                        <?php foreach($complaintStatusOptions as $stt): ?>
                            <option value="<?php echo (int)$stt['id']; ?>" <?php echo ((int)$cstatus===(int)$stt['id'])?'selected':''; ?>>
                                <?php echo h($stt['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Mechanic Status</label>
                    <select name="mechanic_status" class="inp">
                        <option value="">All</option>
                        <?php foreach($mechanicStatusOptions as $stt): ?>
                            <option value="<?php echo (int)$stt['id']; ?>" <?php echo ((int)$mstatus===(int)$stt['id'])?'selected':''; ?>>
                                <?php echo h($stt['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field" style="grid-column:1 / span 3;">
                    <label>Assigned Mechanic</label>
                    <select id="assign_mechanic" name="assign_mechanic[]" multiple class="inp" data-search="true">
                        <?php foreach($mechanics as $m): $mid=(int)$m['id']; ?>
                            <option value="<?php echo $mid; ?>" <?php echo in_array($mid,$mech_ids,true)?'selected':''; ?>>
                                <?php echo h($m['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>View</label>
                    <select name="view_all" class="inp">
                        <option value="0" <?php echo $view_all? '' : 'selected'; ?>>Last 50</option>
                        <option value="1" <?php echo $view_all? 'selected' : ''; ?>>View All</option>
                    </select>
                </div>

                <div class="field">
                    <button type="submit" class="btn success" style="min-width:120px;">Filter</button>
                </div>
                <div class="field">
                    <a href="complaint_report.php" class="btn secondary" style="display:inline-block; text-align:center; min-width:120px;">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <div class="card" style="padding:14px 16px; background:#fff; border-radius:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:10px;">
            <div><strong>Total:</strong> <?php echo (int)$cnt; ?></div>
            <?php if(!$view_all): ?>
                <div class="muted">Page <?php echo (int)$page; ?></div>
            <?php endif; ?>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th style="width:70px;">SR</th>
                    <th>Order No</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Site</th>
                    <th>Plant</th>
                    <th>Process</th>
                    <th>Complaint Status</th>
                    <th>Status Date</th>
                    <th>Schedule Date</th>
                    <th>Mechanic</th>
                    <th>Mech Date</th>
                    <th>Mech Status</th>
                    
                    
                    <th class="no-print" style="width:140px;">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if(empty($rows)): ?>
                    <tr><td colspan="14" style="text-align:center; padding:18px;">No records found.</td></tr>
                <?php else:
                    $sr = $offset + 1;
                    foreach($rows as $r):
                        $d  = (!empty($r['date']) && $r['date']!=='0000-00-00') ? date('d-m-Y', strtotime($r['date'])) : '';
                        $sd = (!empty($r['statusdate']) && $r['statusdate']!=='0000-00-00') ? date('d-m-Y', strtotime($r['statusdate'])) : '';
                        $sch= (!empty($r['scheduledate']) && $r['scheduledate']!=='0000-00-00') ? date('d-m-Y', strtotime($r['scheduledate'])) : '';
                        $md = (!empty($r['mechanic_allotmentdate']) && $r['mechanic_allotmentdate']!=='0000-00-00') ? date('d-m-Y', strtotime($r['mechanic_allotmentdate'])) : '';
                        ?>
                        <tr>
                            <td><?php echo (int)$sr++; ?></td>
                            <td><?php echo (int)($r['orderno'] ?? 0); ?> <div class="muted" style="font-size:11px;"></div></td>
                            <td><?php echo h($d); ?></td>
                            <td><?php echo h($r['customer']); ?></td>
                            <td><?php echo h($r['site_name'] ?? ''); ?></td>
                            <td><?php echo h($r['plant_name'] ?? ''); ?></td>
                            <td><?php echo h($r['process_type_name'] ?? ''); ?></td>
                            <td><?php echo h($r['complaint_status_name'] ?? ''); ?></td>
                            <td><?php echo h($sd); ?></td>
                            <td><?php echo h($sch); ?></td>
                            <td><?php echo h($r['mechanic_names'] ?? ''); ?></td>
                            <td><?php echo h($md); ?></td>
                            <td><?php echo h($r['mech_status_name'] ?? ''); ?></td>
                            
                            
<td class="no-print">
  <div style="display:flex; gap:6px; flex-wrap:wrap;">

    <?php if($canEdit): ?>
      <!-- EDIT (POST-only, no id in URL) -->
      <form method="post" action="/operations/complaint_order.php" target="_blank" style="display:inline;">
        <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
        <button type="submit" class="btn primary" style="padding:4px 10px; font-size:12px;">
          Edit
        </button>
      </form>
    <?php endif; ?>

    <!-- Update Status (already POST-only) -->
    <form method="post" action="/operations/update_complaint_status.php" target="_blank" style="display:inline;">
      <input type="hidden" name="cid" value="<?php echo (int)$r['id']; ?>">
      <button type="submit" class="btn success" style="padding:4px 10px; font-size:12px;">
        Update Status
      </button>
    </form>

    <!-- ✅ NEW: Generate Quotation (POST-only, no id in URL) -->
    <form method="post" action="/operations/service_quotation.php" target="_blank" style="display:inline;">
      <input type="hidden" name="complaint_id" value="<?php echo (int)$r['id']; ?>">
      <!-- optional: also send complaintid for compatibility -->
      <input type="hidden" name="complaintid" value="<?php echo (int)$r['id']; ?>">
      <button type="submit" class="btn success" style="padding:4px 10px; font-size:12px;">
        Generate Quotation
      </button>
    </form>

  </div>
</td>
             <?php endforeach;
                endif; ?>
                </tbody>
            </table>
        </div>

        <?php if(!$view_all && $cnt > $limit): 
            $totalPages = (int)ceil($cnt / $limit);

            // keep params for paging
            $qs = $_GET;
            ?>
            <div class="no-print" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
                <div class="muted">Pages: <?php echo (int)$totalPages; ?></div>
                <div style="display:flex; gap:8px;">
                    <?php
                    $prev = max(1, $page-1);
                    $next = min($totalPages, $page+1);

                    $qs['page'] = $prev;
                    $prevUrl = 'complaint_orderlist.php?' . http_build_query($qs);

                    $qs['page'] = $next;
                    $nextUrl = 'complaint_orderlist.php?' . http_build_query($qs);
                    ?>
                    <a class="btn secondary" href="<?php echo h($prevUrl); ?>" <?php echo ($page<=1)?'style="pointer-events:none;opacity:.5"':''; ?>>Prev</a>
                    <a class="btn secondary" href="<?php echo h($nextUrl); ?>" <?php echo ($page>=$totalPages)?'style="pointer-events:none;opacity:.5"':''; ?>>Next</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
@media print{
    .no-print{ display:none !important; }
    body{ background:#fff !important; }
    .card{ box-shadow:none !important; }
}
</style>

<!-- jQuery UI for customer autocomplete -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<!-- Virtual Select -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/virtual-select-plugin@1.0.39/dist/virtual-select.min.css">
<script src="https://cdn.jsdelivr.net/npm/virtual-select-plugin@1.0.39/dist/virtual-select.min.js"></script>

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
document.addEventListener('DOMContentLoaded', function(){
    if(window.flatpickr){
        flatpickr('.datepick', { dateFormat:'d-m-Y', allowInput:true, disableMobile:true });
    }
    if(window.VirtualSelect){
        VirtualSelect.init({
            ele:'#assign_mechanic',
            multiple:true, search:true, showValueAsTags:true, dropboxWidth:'100%', zIndex:1040
        });
    }

    if(window.jQuery){
        jQuery('#customer_name').autocomplete({
            minLength:2,
            source:function(request,response){
                jQuery.getJSON('complaint_order.php', {ajax:'cust_autocomplete', term:request.term}, function(data){
                    response(data);
                });
            },
            select:function(event,ui){
                jQuery('#customer_id').val(ui.item.id);
                jQuery('#customer_name').val(ui.item.value);
                return false;
            },
            change:function(event,ui){
                if(!ui.item){
                    jQuery('#customer_id').val('');
                }
            }
        });
    }
});
</script>

<?php
$CONTENT = ob_get_clean();
require_once __DIR__ . '/../includes/ui_autoshell.php';
