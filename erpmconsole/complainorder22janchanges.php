<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// operations/complaint_order.php
// Complaint Order – ERP style
// - Flatpickr datepickers (DD-MM-YYYY)
// - Customer autocomplete from jos_ierp_customermaster
// - Dependent Sitename / Plant Name
// - Mechanic multi-select (VirtualSelect)
// - Complaint Nature grid with modal editor
// - Frontend validation + backend validation



require_once __DIR__ . '/../includes/initialize.php';
require_once __DIR__ . '/../includes/aclhelper.php';

if (!function_exists('is_logged_in') || !is_logged_in()) {
    redirect('../login.php');
}

date_default_timezone_set('Asia/Kolkata');



/* ---------------- MODE / ID (POST-only) ---------------- */
$isAjax = (isset($_GET['ajax']) || isset($_POST['ajax'])); // autocomplete etc.
$id = 0;

// Edit should come ONLY from POST (no id in URL)
if (!$isAjax) {
    $id = (int)($_POST['id'] ?? 0);   // ✅ THIS is what you want
}

// 🔁 FIX-2: restore edit id after redirect (POST-only flow)
if (!$isAjax && $id <= 0 && !empty($_SESSION['complaint_edit_id'])) {
    $id = (int)$_SESSION['complaint_edit_id'];
    unset($_SESSION['complaint_edit_id']);
}

/* -------------------------------------------------------------
 * Config – adjust table names if needed
 * -----------------------------------------------------------*/
$TABLE_HEADER           = 'jos_ierp_complaint';
$TABLE_NATURE           = 'jos_ierp_complaint_naturelog';
$TABLE_COMPLAINT_TYPE   = 'jos_ierp_complainttype';
$TABLE_COMPLAINT_NATURE = 'jos_ierp_complaintmature';
$TABLE_CUSTOMERS        = 'jos_ierp_customermaster';

// Site & plant
// ACTUAL site table in your DB: jos_crm_siteaddress_grid (id, sitename)
$TABLE_SITES            = 'jos_crm_siteaddress_grid';
$TABLE_PLANTS           = 'jos_erp_plantname';     // plant master (id, name)


/* -------------------------------------------------------------
 * Complaint helpers
 * -----------------------------------------------------------*/

function get_next_complaint_orderno(mysqli $con): int {
    $sql = "SELECT COALESCE(MAX(orderno),0)+1 AS nxt FROM jos_ierp_complaint";
    $res = $con->query($sql);
    if (!$res) return 1;
    $row = $res->fetch_assoc();
    return (int)$row['nxt'];
}

function get_admin_gid(mysqli $con, int $admin_id): int {
    $sql = "SELECT COALESCE(gid,0) AS gid
            FROM jos_admin_users
            WHERE id = ?
            LIMIT 1";
    $st = $con->prepare($sql);
    $st->bind_param("i", $admin_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (int)($row['gid'] ?? 0);
}

function fy_code_from_date(string $ymd): string {
    $dt = DateTime::createFromFormat('Y-m-d', $ymd);
    if (!$dt) return '';

    $y = (int)$dt->format('Y');
    $m = (int)$dt->format('n');

    $startYear = ($m >= 4) ? $y : ($y - 1);
    $endYear   = $startYear + 1;

    return substr($startYear, -2) . '-' . substr($endYear, -2); // 24-25
}

function get_finyear_id(mysqli $con, string $fy_code): int {
    if ($fy_code === '') return 0;

    $sql = "SELECT id FROM jos_ierp_mfinancialyear WHERE code = ? LIMIT 1";
    $st = $con->prepare($sql);
    $st->bind_param("s", $fy_code);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    return (int)($row['id'] ?? 0);
}


/* -------------------------------------------------------------
 * Helpers
 * -----------------------------------------------------------*/
function h($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
function set_flash($k,$m){ $_SESSION[$k]=$m; }
function get_flash($k){
    if (!empty($_SESSION[$k])) { $m=$_SESSION[$k]; unset($_SESSION[$k]); return $m; }
    return '';
}
function redirect_clean_complaint() {
    header('Location: complaint_order.php');
    exit;
}
function parse_dmy_date($val) {
    $val = trim((string)$val);
    if ($val === '') return null;
    $dt = DateTime::createFromFormat('d-m-Y', $val);
    if (!$dt) $dt = DateTime::createFromFormat('Y-m-d', $val);
    return $dt ? $dt->format('Y-m-d') : null;
}

/* -------------------------------------------------------------
 * MENU META + ACL
 * -----------------------------------------------------------*/
$aclMeta = erp_get_menu_meta_and_acl($con);

$menuMetaTitle  = $aclMeta['title']      ?? 'Complaint Order';
$menuMetaRemark = $aclMeta['remark']     ?? '';
$canView        = $aclMeta['can_view']   ?? false;
$canAdd         = $aclMeta['can_add']    ?? false;
$canEdit        = $aclMeta['can_edit']   ?? false;
$canDelete      = $aclMeta['can_delete'] ?? false;

$userObj       = current_user() ?? [];
$currentUserId = isset($userObj['id']) ? (int)$userObj['id'] : 0;

/* -------------------------------------------------------------
 * AJAX: Complaint Nature by Type
 * -----------------------------------------------------------*/
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_natures') {
    if (!$canView) { http_response_code(403); echo json_encode([]); exit; }

    $typeId = isset($_GET['type']) ? (int)$_GET['type'] : 0;
    $rows = [];

    if ($typeId > 0) {
        $stmt = $con->prepare(
            "SELECT id, name FROM {$TABLE_COMPLAINT_NATURE}
              WHERE type = ? ORDER BY name ASC"
        );
        $stmt->bind_param('i', $typeId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = ['id'=>(int)$r['id'], 'name'=>$r['name']];
        }
        $stmt->close();
    }
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit;
}

/* -------------------------------------------------------------
 * AJAX: Customer autocomplete – name + address
 * -----------------------------------------------------------*/
if (isset($_GET['ajax']) && $_GET['ajax'] === 'cust_autocomplete') {
    if (!$canView) { http_response_code(403); echo json_encode([]); exit; }

    $term = trim($_GET['term'] ?? '');
    $out = [];
    if ($term !== '') {
        $like = '%'.$term.'%';
        $sql = "SELECT id, name, address
                  FROM {$TABLE_CUSTOMERS}
                 WHERE name LIKE ?
              ORDER BY name ASC
                 LIMIT 20";
        $stmt = $con->prepare($sql);
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $out[] = [
                'id'      => (int)$r['id'],
                'label'   => $r['name'],
                'value'   => $r['name'],
                'address' => $r['address'] ?? ''
            ];
        }
        $stmt->close();
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

/* -------------------------------------------------------------
 * AJAX: Sitename list for a customer
 *  -> Uses sale_order + site master (jos_crm_siteaddress_grid)
 * -----------------------------------------------------------*/
if (isset($_GET['ajax']) && $_GET['ajax'] === 'sites') {
    if (!$canView) { http_response_code(403); echo json_encode([]); exit; }

    $custId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
    $rows = [];

    if ($custId > 0) {
        $sql = "
            SELECT DISTINCT
                s.id,
                s.sitename AS name
            FROM jos_erp_sale_order AS so
            JOIN {$TABLE_SITES} AS s
                 ON s.id = so.sitename
            WHERE so.custid = ?
            ORDER BY s.sitename ASC
        ";
        $stmt = $con->prepare($sql);
        $stmt->bind_param('i', $custId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = ['id'=>(int)$r['id'], 'name'=>$r['name']];
        }
        $stmt->close();
    }

    header('Content-Type: application/json');
    echo json_encode($rows);
    exit;
}


/* -------------------------------------------------------------
 * AJAX: Plant list for a site  – uses sale order + plant + product
 * -----------------------------------------------------------*/

 
if (isset($_GET['ajax']) && $_GET['ajax'] === 'plants') {
    if (!$canView) { http_response_code(403); echo json_encode([]); exit; }

    $siteId = isset($_GET['site_id']) ? (int)$_GET['site_id'] : 0;
    $rows   = [];

    if ($siteId > 0) {
        $sql = "
            SELECT DISTINCT
                so.plantname AS plant_id,
                TRIM(
                    CONCAT(
                        pn.name, ' - ',
                        p.name, ' ',
                        p.modelcode
                    )
                ) AS plant_full_name
            FROM jos_erp_sale_order AS so
            LEFT JOIN {$TABLE_PLANTS} AS pn
                   ON pn.id = so.plantname
            LEFT JOIN jos_crm_mproducts AS p
                   ON p.id = so.productid
            WHERE so.sitename = ?
              AND so.plantname <> 0
              AND pn.id IS NOT NULL
            ORDER BY plant_full_name ASC
        ";

        $stmt = $con->prepare($sql);
        $stmt->bind_param('i', $siteId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'id'   => (int)$r['plant_id'],
                'name' => $r['plant_full_name']
            ];
        }
        $stmt->close();
    }

    header('Content-Type: application/json');
    echo json_encode($rows);
    exit;
}

/* -------------------------------------------------------------
 * ACCESS GUARD
 * -----------------------------------------------------------*/
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

/* -------------------------------------------------------------
 * Masters (Complaint types, mechanics, etc.)
 * -----------------------------------------------------------*/
$complaintTypes = [];
$resCT = $con->query("SELECT id, name FROM {$TABLE_COMPLAINT_TYPE} ORDER BY name ASC");
while ($r = $resCT->fetch_assoc()) $complaintTypes[] = $r;
if ($resCT instanceof mysqli_result) $resCT->free();

$complaintTypesById = [];
foreach ($complaintTypes as $ct) $complaintTypesById[(int)$ct['id']] = $ct['name'];

$mechanics = [];
$resMech = $con->query(
    "SELECT DISTINCT a.id, a.name
       FROM jos_users a
       JOIN jos_user_usergroup_map b ON a.id = b.user_id
      WHERE b.group_id = 213
   ORDER BY a.name ASC"
);
while ($r = $resMech->fetch_assoc()) $mechanics[] = $r;
if ($resMech instanceof mysqli_result) $resMech->free();

$TABLE_PROCESS_TYPE = 'jos_complaint_process_type'; // adjust exact table name

$processTypes = [];
$resPT = $con->query("SELECT id, name FROM {$TABLE_PROCESS_TYPE} ORDER BY name ASC");
while ($r = $resPT->fetch_assoc()) $processTypes[] = $r;
if ($resPT instanceof mysqli_result) $resPT->free();


/* complaint status start 
*/
$TABLE_COMPLAINT_STATUS = 'jos_ierp_complaintstatus';

$complaintStatusOptions = [];
$resCS = $con->query("SELECT id, name FROM {$TABLE_COMPLAINT_STATUS} ORDER BY id ASC");
while ($r = $resCS->fetch_assoc()) $complaintStatusOptions[] = $r;
if ($resCS instanceof mysqli_result) $resCS->free();

/* complaint status end
*/


/* mech status start
*/

$TABLE_MECH_STATUS = 'jos_comech_status';

$mechanicStatusOptions = [];
$sqlMS = "
    SELECT id, name
    FROM {$TABLE_MECH_STATUS}
    WHERE status = 1
    ORDER BY order_by ASC
";
$resMS = $con->query($sqlMS);
while ($r = $resMS->fetch_assoc()) {
    $mechanicStatusOptions[] = $r;
}
if ($resMS instanceof mysqli_result) $resMS->free();



/* mech status end
*/



/* -------------------------------------------------------------
 * Load existing record (edit)
 * -----------------------------------------------------------*/
$editId = (int)$id; // ✅ POST-only edit id
$editingHeader     = null;
$editingNatureRows = [];
$hasValidationError = false;
$errorMsgLocal      = '';

if ($editId > 0) {
    $stmt = $con->prepare("SELECT * FROM {$TABLE_HEADER} WHERE id = ?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editingHeader = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($editingHeader) {
        // Convert dates to d-m-Y
        foreach (['date','statusdate','scheduledate','mechanic_allotmentdate'] as $col) {
            if (!empty($editingHeader[$col]) && $editingHeader[$col] !== '0000-00-00') {
                $dt = DateTime::createFromFormat('Y-m-d', substr($editingHeader[$col],0,10));
                if ($dt) $editingHeader[$col] = $dt->format('d-m-Y');
            }
        }

        // Map customer_id for JS and hidden field
        $editingHeader['customer_id'] = (int)($editingHeader['custid'] ?? 0);

        // Address is NOT stored in complaint table – pull from customer master
        if (!empty($editingHeader['customer_id'])) {
            $stmtA = $con->prepare("SELECT address FROM {$TABLE_CUSTOMERS} WHERE id = ?");
            $stmtA->bind_param('i', $editingHeader['customer_id']);
            $stmtA->execute();
            $resA = $stmtA->get_result()->fetch_assoc();
            $stmtA->close();
            $editingHeader['address'] = $resA['address'] ?? '';
        }

        // Load nature rows
        $stmt2 = $con->prepare(
            "SELECT n.id, n.complainttype, n.complaintnature, n.complaintdesc,
                    ct.name AS type_name, cn.name AS nature_name
               FROM {$TABLE_NATURE} n
          LEFT JOIN {$TABLE_COMPLAINT_TYPE}   ct ON ct.id = n.complainttype
          LEFT JOIN {$TABLE_COMPLAINT_NATURE} cn ON cn.id = n.complaintnature
              WHERE n.saleid = ?
           ORDER BY n.id ASC"
        );
        $stmt2->bind_param('i', $editId);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($r = $res2->fetch_assoc()) {
            $editingNatureRows[] = [
                'id'              => (int)$r['id'],
                'complainttype'   => (int)$r['complainttype'],
                'complaintnature' => (int)$r['complaintnature'],
                'complaintdesc'   => $r['complaintdesc'] ?? '',
                'type_name'       => $r['type_name']   ?? '',
                'nature_name'     => $r['nature_name'] ?? '',
            ];
        }
        $stmt2->close();
    } else {
        $editId = 0;
    }
}

/* -------------------------------------------------------------
 * HANDLE POST – SAVE
 * -----------------------------------------------------------*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complaint_form'])) {

    if (!$canAdd && !$canEdit) {
        set_flash('complaint_error', 'You do not have permission to save complaints.');
        redirect_clean_complaint();
    }

    $id = (int)($_POST['id'] ?? 0);

    /* ----------- INPUTS ----------- */
    $complaint_date  = parse_dmy_date($_POST['complaint_date'] ?? '');
    $customer_name   = trim($_POST['customer_name'] ?? '');
    $customer_id     = (int)($_POST['customer_id'] ?? 0);
    $sitename_id     = (int)($_POST['sitename'] ?? 0);
    $plant_id        = (int)($_POST['plantname'] ?? 0);
  $process_type = (int)($_POST['process_type'] ?? 0);
$complaintstatus = (int)($_POST['complaint_status'] ?? 0);

    $status_date     = parse_dmy_date($_POST['status_date'] ?? '');
    $status_desc     = trim($_POST['status_desc'] ?? '');
    $schedule_date   = parse_dmy_date($_POST['schedule_date'] ?? '');
    $mechanic_date   = parse_dmy_date($_POST['mechanic_date'] ?? '');
   $mechanic_status = (int)($_POST['mechanic_status'] ?? 0);


    $assign_mech_arr = $_POST['assign_mechanic'] ?? [];

    if (count($assign_mech_arr) === 1 && strpos($assign_mech_arr[0], ',') !== false) {
        $assign_mech_arr = explode(',', $assign_mech_arr[0]);
    }

    $assign_mech = implode(',', array_map('intval', $assign_mech_arr));


    // echo '<pre>';
    // var_dump($_POST['assign_mechanic']); 
    // var_dump($assign_mech_arr);          
    // var_dump($assign_mech);      
    // exit;


    $ctArr = $_POST['complainttype']   ?? [];
    $cnArr = $_POST['complaintnature'] ?? [];
    $cdArr = $_POST['complaintdesc']   ?? [];

    /* ----------- VALIDATION ----------- */
    $errors = [];
    if (!$complaint_date)  $errors[] = 'Complaint Date is required.';
    if ($customer_id <= 0) $errors[] = 'Valid Customer is required.';
    if ($sitename_id <= 0) $errors[] = 'Site Name is required.';
    
if ($complaintstatus <= 0) $errors[] = 'Complaint Status is required.';
if ($mechanic_status !== 0 && $mechanic_status <= 0) { /* optional */ }

    
    
    if (!$status_date) $errors[] = 'Status Date is required.';

    $hasNature = false;
    foreach ($ctArr as $i => $ct) {
        if ((int)$ct > 0 || (int)($cnArr[$i] ?? 0) > 0 || trim($cdArr[$i] ?? '') !== '') {
            $hasNature = true;
            break;
        }
    }
    if (!$hasNature) $errors[] = 'Please add at least one Complaint Nature row.';

 if (!empty($errors)) {
    // 🔁 FIX-2: preserve edit id before redirect
    if ($id > 0) {
        $_SESSION['complaint_edit_id'] = $id;
    }

    set_flash('complaint_error', implode(' ', $errors));
    redirect_clean_complaint();
}


    /* ----------- AUTO VALUES ----------- */
    $created_by = $currentUserId;
    $gid        = get_admin_gid($con, $created_by);
    $fy_code    = fy_code_from_date($complaint_date);
    $yrid       = get_finyear_id($con, $fy_code);

    if ($yrid <= 0) {
        set_flash('complaint_error', 'Financial year not configured.');
        redirect_clean_complaint();
    }

    $orderno = 0;
    if ($id === 0) {
        $orderno = get_next_complaint_orderno($con);
        
    }
/* ----------- TRANSACTION START ----------- */

$con->begin_transaction();

try {

    // normalize optional dates to NULL (DB will store NULL if allowed)
    $schedule_date = $schedule_date ?: null;
    $mechanic_date = $mechanic_date ?: null;

    $complaintId = 0;

    // For UPDATE: fetch old mechanic info so we rewrite mechanic log only if changed
    $oldAssignMech = '';
    $oldMechStatus = 0;
    $oldMechDate   = null;

    if ($id > 0) {
        $stOld = $con->prepare("SELECT assign_mechanic, co_mech_status, mechanic_allotmentdate FROM {$TABLE_HEADER} WHERE id=? LIMIT 1");
        if (!$stOld) { throw new Exception("Prepare failed (old header): " . $con->error); }

        $stOld->bind_param('i', $id);
        $stOld->execute();
        $oldRow = $stOld->get_result()->fetch_assoc();
        $stOld->close();

        if ($oldRow) {
            $oldAssignMech = (string)($oldRow['assign_mechanic'] ?? '');
            $oldMechStatus = (int)($oldRow['co_mech_status'] ?? 0);
            $oldMechDate   = (!empty($oldRow['mechanic_allotmentdate']) && $oldRow['mechanic_allotmentdate'] !== '0000-00-00')
                ? substr($oldRow['mechanic_allotmentdate'], 0, 10)
                : null;
        }

        /* -------- UPDATE HEADER -------- */
        $sql = "UPDATE {$TABLE_HEADER}
                   SET date=?, customer=?, custid=?, sitename=?, plant=?,
                       process_type=?, complaintstatus=?, statusdate=?,
                       description=?, scheduledate=?, mechanic_allotmentdate=?,
                       co_mech_status=?, assign_mechanic=?, yrid=?
                 WHERE id=?";

        $stmt = $con->prepare($sql);
        if (!$stmt) { throw new Exception("Prepare failed (update header): " . $con->error); }

        // 15 params
        $types = 'ssiiiii' . 'ssss' . 'isii';

        $stmt->bind_param(
            $types,
            $complaint_date,   // s
            $customer_name,    // s
            $customer_id,      // i
            $sitename_id,      // i
            $plant_id,         // i
            $process_type,     // i
            $complaintstatus,  // i
            $status_date,      // s
            $status_desc,      // s
            $schedule_date,    // s (NULL ok)
            $mechanic_date,    // s (NULL ok)
            $mechanic_status,  // i
            $assign_mech,      // s
            $yrid,             // i
            $id                // i
        );

        $stmt->execute();
        $stmt->close();

        $complaintId = $id;

        /* ---- DELETE OLD NATURE ROWS ---- */
        $del = $con->prepare("DELETE FROM {$TABLE_NATURE} WHERE saleid=?");
        if (!$del) { throw new Exception("Prepare failed (delete nature): " . $con->error); }

        $del->bind_param('i', $complaintId);
        $del->execute();
        $del->close();

    } else {

        /* -------- INSERT HEADER -------- */
        $sql = "INSERT INTO {$TABLE_HEADER}
                (orderno, date, customer, custid, sitename, plant,
                 process_type, complaintstatus, statusdate,
                 description, scheduledate, mechanic_allotmentdate,
                 co_mech_status, assign_mechanic,
                 yrid, gid, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $con->prepare($sql);
        if (!$stmt) { throw new Exception("Prepare failed (insert header): " . $con->error); }

        // 17 params
        $types = 'issiiiii' . 'ssss' . 'isiii';

        $stmt->bind_param(
            $types,
            $orderno,          // i
            $complaint_date,   // s
            $customer_name,    // s
            $customer_id,      // i
            $sitename_id,      // i
            $plant_id,         // i
            $process_type,     // i
            $complaintstatus,  // i
            $status_date,      // s
            $status_desc,      // s
            $schedule_date,    // s (NULL ok)
            $mechanic_date,    // s (NULL ok)
            $mechanic_status,  // i
            $assign_mech,      // s
            $yrid,             // i
            $gid,              // i
            $created_by        // i
        );

        $stmt->execute();
        $complaintId = (int)$con->insert_id;
        $stmt->close();
    }

    /* =========================================================
     * STATUS LOG — insert every save
     * ======================================================= */
    $logtime = date('H:i:s');

    $stLog = $con->prepare(
        "INSERT INTO jos_ierp_complaint_statuslog
            (cid, date, status, description, userid, logtime)
         VALUES (?,?,?,?,?,?)"
    );
    if (!$stLog) { throw new Exception("Prepare failed (status log): " . $con->error); }

    $stLog->bind_param(
        'isisis',
        $complaintId,      // i
        $status_date,      // s (Y-m-d)
        $complaintstatus,  // i
        $status_desc,      // s
        $created_by,       // i
        $logtime           // s (H:i:s)
    );
    $stLog->execute();
    $stLog->close();

    /* =========================================================
     * ASSIGN MECHANIC LOG — rewrite ONLY when mechanic info changed
     * ======================================================= */
    $newMechDate = $mechanic_date ?: null;

    $assignChanged = (trim((string)$oldAssignMech) !== trim((string)$assign_mech));
    $statusChanged = ((int)$oldMechStatus !== (int)$mechanic_status);
    $dateChanged   = ((string)($oldMechDate ?? '') !== (string)($newMechDate ?? ''));

    $shouldRewriteMechLog = ($id === 0) || $assignChanged || $statusChanged || $dateChanged;

    if ($shouldRewriteMechLog) {

        $delMech = $con->prepare("DELETE FROM jos_ierp_complaint_assignmechanic_log WHERE complaint_id = ?");
        if (!$delMech) { throw new Exception("Prepare failed (delete mech log): " . $con->error); }

        $delMech->bind_param('i', $complaintId);
        $delMech->execute();
        $delMech->close();

        if (is_array($assign_mech_arr) && count($assign_mech_arr) > 0) {

            // if mechanic_date missing, use status_date or today
            $assignDateStr = $mechanic_date ?: ($status_date ?: date('Y-m-d'));

            $insMech = $con->prepare(
                "INSERT INTO jos_ierp_complaint_assignmechanic_log
                    (complaint_id, assigndate, time, mechanic_id, status)
                 VALUES (?,?,?,?,?)"
            );
            if (!$insMech) { throw new Exception("Prepare failed (insert mech log): " . $con->error); }

            foreach ($assign_mech_arr as $mid) {
                $mid = (int)$mid;
                if ($mid <= 0) continue;

                $insMech->bind_param(
                    'issii',
                    $complaintId,     // i
                    $assignDateStr,   // s
                    $logtime,         // s
                    $mid,             // i
                    $mechanic_status  // i
                );
                $insMech->execute();
            }
            $insMech->close();
        }
    }

    /* =========================================================
     * INSERT NATURE ROWS (after delete in update)
     * ======================================================= */
    $insNature = $con->prepare(
        "INSERT INTO {$TABLE_NATURE} (saleid, complainttype, complaintnature, complaintdesc)
         VALUES (?,?,?,?)"
    );
    if (!$insNature) { throw new Exception("Prepare failed (insert nature): " . $con->error); }

    foreach ($ctArr as $i => $ct) {
        $ctId = (int)$ct;
        $cnId = (int)($cnArr[$i] ?? 0);
        $desc = trim($cdArr[$i] ?? '');

        // skip empty rows
        if ($ctId <= 0 && $cnId <= 0 && $desc === '') continue;

        // enforce required values
        if ($ctId <= 0 || $cnId <= 0) {
            throw new Exception("Complaint Nature row missing Type/Nature (row ".($i+1).").");
        }

        $insNature->bind_param('iiis', $complaintId, $ctId, $cnId, $desc);
        $insNature->execute();
    }
    $insNature->close();

    /* ----------- COMMIT ----------- */
    $con->commit();

    set_flash('complaint_flash', 'Complaint saved successfully.');
header('Location: complaint_order.php?ok=1');
exit;

} catch (Throwable $e) {
    $con->rollback();
    set_flash('complaint_error', 'Save failed: ' . $e->getMessage());
   // 🔁 FIX-2: preserve edit id on exception (POST-only)
if ($id > 0) {
    $_SESSION['complaint_edit_id'] = $id;
}
header('Location: complaint_order.php');
exit;

}

}
/* -------------------------------------------------------------
 * Shell vars + defaults
 * -----------------------------------------------------------*/
$user        = $userObj;
$pageTitle   = $menuMetaTitle;
$systemTitle = 'ERP Console';
$systemCode  = 'AGCM';
$userName    = $user['name'] ?? 'User';
$userLoginId = $user['login_id'] ?? ($user['email'] ?? ($user['mobile_no'] ?? ''));

$flashMsg = get_flash('complaint_flash');
$errorMsg = get_flash('complaint_error');
if ($errorMsgLocal!=='') $errorMsg=$errorMsgLocal;

if (!$editingHeader) {
    $today = date('d-m-Y');
    $editingHeader = [
        'id'=>0,
        'date'=>$today,
        'customer'=>'',
        'customer_id'=>0,
        'address'=>'',
        'sitename'=>0,
        'plant'=>0,
        'process_type'=>'',
        'complaintstatus'=>'',
        'statusdate'=>$today,
        'description'=>'',
        'scheduledate'=>$today,
        'mechanic_allotmentdate'=>$today,
        'co_mech_status'=>'',
        'assign_mechanic'=>'',
    ];
}

/* -------------------------------------------------------------
 * PAGE CONTENT
 * -----------------------------------------------------------*/
ob_start();
?>
<div class="master-wrap">
    <div class="headbar">
        <div>
            <h1 class="page-title"><?php echo h($menuMetaTitle); ?></h1>
            <div class="page-subtitle"><?php echo h($menuMetaRemark); ?></div>
        </div>
    </div>

    <?php if ($errorMsg): ?>
        <div class="alert danger" id="flash-band"><?php echo h($errorMsg); ?></div>
        <div class="flash-toast error" id="flash-toast"><?php echo h($errorMsg); ?></div>
    <?php elseif ($flashMsg): ?>
        <div class="alert success" id="flash-band"><?php echo h($flashMsg); ?></div>
        <div class="flash-toast" id="flash-toast"><?php echo h($flashMsg); ?></div>
    <?php endif; ?>

    <div class="card" style="padding:24px 28px; margin:16px 0; background:#fff; border-radius:16px;">

        <h2 class="card-title" style="margin-bottom:18px;">Complaint Order</h2>

        <form method="post" autocomplete="off" data-show-loader="1" id="complaint-form" data-validate="complaint">
            <input type="hidden" name="complaint_form" value="1">
            <input type="hidden" name="id" value="<?php echo (int)$editingHeader['id']; ?>">

            <!-- ROW 1: Complaint Date, Customer, Address (label) -->
            <div class="form-grid-3" style="display:grid; grid-template-columns:1fr 1.4fr 1.6fr; gap:16px;">

                <div class="field">
                    <label>Complaint Date<span class="req">*</span></label>
                    <div class="date-input-wrap">
                        <input type="text" name="complaint_date" class="inp datepick"
                               placeholder="DD-MM-YYYY"
                               value="<?php echo h($editingHeader['date']); ?>">
                    </div>
                </div>

                <div class="field">
                    <label>Customer Name<span class="req">*</span></label>
                    <input type="hidden" name="customer_id" id="customer_id"
                           value="<?php echo (int)($editingHeader['customer_id'] ?? 0); ?>">
                    <input type="text" name="customer_name" id="customer_name"
                           class="inp"
                           value="<?php echo h($editingHeader['customer']); ?>"
                           placeholder="Customer Name">
                </div>

                <div class="field">
                    <label>Address</label>
                    <input type="hidden" name="address" id="address"
                           value="<?php echo h($editingHeader['address']); ?>">
                  <textarea id="address_display" class="inp" readonly rows="2"
          style="min-height:40px; background:#f9fafb; resize:vertical;"><?php
    echo !empty($editingHeader['address']) ? h($editingHeader['address']) : '';
?></textarea>
                </div>
            </div>

            <!-- ROW 2: Sitename, Plant, Process Type, Complaint Status -->
            <div class="form-grid-4" style="display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; margin-top:16px;">

                <div class="field">
                    <label>Sitename<span class="req">*</span></label>
                    <select id="sitename" name="sitename" class="inp">
                        <option value="">Select Site Name</option>
                    </select>
                </div>

                <div class="field">
                    <label>Plant Name</label>
                    <select id="plantname" name="plantname" class="inp">
                        <option value="">Select Plant Name</option>
                    </select>
                </div>

                <div class="field">
                    <label>Process Type</label>
                    <select name="process_type" id="process_type" class="inp">
                        <option value="">Select Process Type</option>
                    <?php foreach ($processTypes as $pt): ?>
  <option value="<?php echo (int)$pt['id']; ?>"
    <?php if ((int)$editingHeader['process_type'] === (int)$pt['id']) echo 'selected'; ?>>
    <?php echo h($pt['name']); ?>
  </option>
<?php endforeach; ?>

                    </select>
                </div>

                <div class="field">
                    <label>Complaint Status<span class="req">*</span></label>
                  
<select name="complaint_status" class="inp">
    <option value="">Select Complaint Status</option>
    <?php foreach ($complaintStatusOptions as $st): ?>
        <option value="<?php echo (int)$st['id']; ?>"
            <?php if ((int)$editingHeader['complaintstatus'] === (int)$st['id']) echo 'selected'; ?>>
            <?php echo h($st['name']); ?>
        </option>
    <?php endforeach; ?>
</select>

                </div>
            </div>

            <!-- ROW 3: Status Date, Description (multi-line), Schedule Date -->
            <div class="form-grid-4" style="display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; margin-top:16px;">

                <div class="field">
                    <label>Status Date<span class="req">*</span></label>
                    <div class="date-input-wrap">
                        <input type="text" name="status_date" class="inp datepick"
                               placeholder="DD-MM-YYYY"
                               value="<?php echo h($editingHeader['statusdate']); ?>">
                    </div>
                </div>

                <div class="field" style="grid-column:2 / span 2;">
                    <label>Complaint Status Description</label>
                    <textarea name="status_desc" class="inp" rows="3"
                              style="min-height:80px; resize:vertical;"><?php echo h($editingHeader['description']); ?></textarea>
                </div>

                <div class="field">
                    <label>Schedule Date</label>
                    <div class="date-input-wrap">
                        <input type="text" name="schedule_date" class="inp datepick"
                               placeholder="DD-MM-YYYY"
                               value="<?php echo h($editingHeader['scheduledate']); ?>">
                    </div>
                </div>
            </div>

            <!-- MECHANIC BLOCK -->
<!-- MECHANIC BLOCK -->
<div id="mechanic-section"
     style="margin-top:24px; border-top:1px solid #e5e7eb; padding-top:18px; display:none;">
                <div class="form-grid-4" style="display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px;">

 <div class="field" style="grid-column:1 / span 2;">
    <label>Assign Mechanic</label>
    <select id="assign_mechanic" name="assign_mechanic[]" multiple
            class="inp" data-search="true"
            data-silent-initial-value-set="true">
        <?php
        $selArr = array_filter(explode(',', (string)$editingHeader['assign_mechanic']), 'strlen');
        $selArr = array_map('intval',$selArr);
        foreach ($mechanics as $m):
            $mid = (int)$m['id'];
            $sel = in_array($mid,$selArr,true) ? 'selected':'';
        ?>
            <option value="<?php echo $mid; ?>" <?php echo $sel; ?>>
                <?php echo h($m['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>



                    <div class="field">
                        <label>Mechanic Call Attend Date</label>
                        <div class="date-input-wrap">
                            <input type="text" name="mechanic_date" class="inp datepick"
                                   placeholder="DD-MM-YYYY"
                                   value="<?php echo h($editingHeader['mechanic_allotmentdate']); ?>">
                        </div>
                    </div>

                    <div class="field">
                        <label>Complaint Mechanic Status</label>
                      <select name="mechanic_status" class="inp">
    <option value="">Select Status</option>
    <?php foreach ($mechanicStatusOptions as $st): ?>
        <option value="<?php echo (int)$st['id']; ?>"
            <?php if ((int)$editingHeader['co_mech_status'] === (int)$st['id']) echo 'selected'; ?>>
            <?php echo h($st['name']); ?>
        </option>
    <?php endforeach; ?>
</select>

                    </div>
                </div>
            </div>

            <!-- ADD NATURE PANEL -->
            <div style="margin-top:24px;">
                <div id="nature-panel"
                     style="padding:10px 16px; background:#e5f1ff; border-radius:10px;
                            display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <button type="button" id="btn-add-nature"
                                class="btn"
                                style="padding:5px 12px; border-radius:999px; font-size:13px;
                                       background:#2563eb; color:#fff; border:none;">
                            + Add Complaint Nature
                        </button>
                        <strong style="font-size:14px;">Complaint Nature</strong>
                    </div>
                    <small class="muted">Click + to add, edit or delete complaint nature rows.</small>
                </div>

                <div id="nature-grid" style="margin-top:12px;">
                    <?php if (!empty($editingNatureRows)): ?>
                        <?php foreach ($editingNatureRows as $row):
                            $rid = mt_rand(1000,999999);
                            $ctName = $row['type_name'] ?: ($complaintTypesById[$row['complainttype']] ?? '');
                            $cnName = $row['nature_name'] ?? '';
                            ?>
                            <div class="nature-row"
                                 data-row-id="<?php echo $rid; ?>"
                                 style="border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px;
                                        margin-bottom:8px; background:#fafafa;
                                        display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                                <input type="hidden" class="nature-ct-val"   name="complainttype[]"   data-row="<?php echo $rid; ?>" value="<?php echo (int)$row['complainttype']; ?>">
                                <input type="hidden" class="nature-cn-val"   name="complaintnature[]" data-row="<?php echo $rid; ?>" value="<?php echo (int)$row['complaintnature']; ?>">
                                <input type="hidden" class="nature-desc-val" name="complaintdesc[]"   data-row="<?php echo $rid; ?>" value="<?php echo h($row['complaintdesc']); ?>">

                                <div style="flex:1 1 auto;">
                                    <div style="display:flex; flex-wrap:wrap; gap:20px; font-size:13px; margin-bottom:4px;">
                                        <div>
                                            <strong>Complaint Type:</strong>
                                            <span data-label-type="<?php echo $rid; ?>">
                                                <?php echo $ctName ? h($ctName) : '—'; ?>
                                            </span>
                                        </div>
                                        <div>
                                            <strong>Complaint Nature:</strong>
                                            <span data-label-nature="<?php echo $rid; ?>">
                                                <?php echo $cnName ? h($cnName) : '—'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div data-label-desc="<?php echo $rid; ?>"
                                         style="font-size:12px; color:#4b5563; white-space:pre-wrap;">
                                        <strong>Description:</strong>
                                        <?php echo $row['complaintdesc'] ? h($row['complaintdesc']) : '—'; ?>
                                    </div>
                                </div>

                                <div style="display:flex; flex-direction:column; gap:4px; white-space:nowrap;">
                                    <button type="button" class="btn primary"
                                            style="padding:4px 10px; font-size:11px;"
                                            onclick="openNatureModal('edit','<?php echo $rid; ?>');">Edit</button>
                                    <button type="button" class="btn danger"
                                            style="padding:4px 10px; font-size:11px;"
                                            onclick="removeNatureRow('<?php echo $rid; ?>');">Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- NO DEFAULT ROW -->
                        <div id="nature-empty" style="font-size:12px; color:#6b7280; padding:8px 4px;">
                            No complaint nature rows added yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions" style="text-align:center; margin-top:28px;">
                <button type="submit" class="btn success" style="min-width:160px;">Submit</button>
            </div>
        </form>
    </div>
</div>

<!-- NATURE MODAL -->
<div id="nature-modal" class="nature-modal hidden">
    <div class="nature-modal-backdrop" onclick="closeNatureModal()"></div>
    <div class="nature-modal-dialog">
        <h3 id="nature-modal-title" style="margin-top:0;margin-bottom:12px;font-size:16px;">Add Complaint Nature</h3>
        <input type="hidden" id="nature-modal-row-id">

        <div class="modal-two-col">
            <div class="field">
                <label>Complaint Type<span class="req">*</span></label>
                <select id="nature-modal-ctype" class="inp">
                    <option value="">Select Complaint Type</option>
                    <?php foreach ($complaintTypes as $ct): ?>
                        <option value="<?php echo (int)$ct['id']; ?>"><?php echo h($ct['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Complaint Nature<span class="req">*</span></label>
                <select id="nature-modal-cnature" class="inp">
                    <option value="">Select Complaint Nature</option>
                </select>
            </div>
        </div>

        <div class="field" style="margin-top:10px;">
            <label>Complaint Description</label>
            <textarea id="nature-modal-desc" class="inp" rows="2" style="resize:vertical;"></textarea>
        </div>

        <div style="text-align:right;margin-top:16px;">
            <button type="button" class="btn secondary" style="margin-right:6px;" onclick="closeNatureModal()">Cancel</button>
            <button type="button" class="btn success" onclick="saveNatureFromModal()">Save</button>
        </div>
    </div>
</div>

<style>
.req{ color:#ef4444; margin-left:2px; }

.flash-toast{
    position:fixed; right:24px; top:80px; z-index:9999;
    padding:10px 16px; border-radius:999px; font-size:13px;
    background:#16a34a; color:#fff; box-shadow:var(--shadow-soft);
    opacity:0; transform:translateY(-8px); pointer-events:none;
    transition:all .25s ease-out;
}
.flash-toast.error{ background:#ef4444; }
.flash-toast.show{ opacity:1; transform:translateY(0); }

.field-error .inp,
.field-error select{
    border-color:#ef4444 !important;
}
.field-error .static-error-border{
    border-color:#ef4444 !important;
}
.error-text{
    font-size:11px;
    color:#b91c1c;
    margin-top:2px;
}

.nature-modal.hidden{ display:none; }
.nature-modal{ position:fixed; inset:0; z-index:1050; }
.nature-modal-backdrop{
    position:absolute; inset:0; background:rgba(15,23,42,0.45);
}
.nature-modal-dialog{
    position:absolute; left:50%; top:50%;
    transform:translate(-50%,-50%);
    width:520px; max-width:95vw;
    background:#fff; border-radius:14px;
    padding:18px 20px; box-shadow:0 10px 40px rgba(15,23,42,0.4);
}
.modal-two-col{
    display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px;
}

/* Flatpickr icon look */
.date-input-wrap{
    position:relative;
}
.date-input-wrap .inp{
    padding-right:32px;
}
.date-input-wrap::after{
    content:'';
    position:absolute;
    right:10px; top:50%;
    width:16px; height:16px;
    transform:translateY(-50%);
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%2394a3b8' stroke-width='1.4' viewBox='0 0 24 24'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cpath d='M16 2v4M8 2v4M3 10h18'/%3E%3C/svg%3E");
    background-size:16px 16px;
    background-repeat:no-repeat;
    pointer-events:none;
}

/* Virtual Select tweaks (original) */
.vscomp-wrapper{ width:100%; }
.vscomp-toggle-button{
    border-radius:4px;
    border:1px solid #d1d5db;
    padding:4px 8px;
    min-height:34px;
    font-size:13px;
    background:#fff;
    box-shadow:none;
}
.vscomp-dropbox{
    font-size:13px;
    border-radius:4px;
    box-shadow:0 10px 25px rgba(15,23,42,0.25);
}
.vscomp-value-tag{
    background:#f3f4f6;
    border-radius:999px;
    padding:2px 8px;
    font-size:11px;
    margin:2px 4px 2px 0;
}


</style>

<!-- jQuery + jQuery UI (autocomplete only) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet"
      href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<!-- Virtual Select -->
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/virtual-select-plugin@1.0.39/dist/virtual-select.min.css">
<script src="https://cdn.jsdelivr.net/npm/virtual-select-plugin@1.0.39/dist/virtual-select.min.js"></script>

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    const ON_FIELD_SERVICE_ID = <?php echo (int)(
        array_values(
            array_filter($processTypes, fn($p) => strtolower($p['name']) === 'on field service')
        )[0]['id'] ?? 0
    ); ?>;
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-show-loader="1"]').forEach(function(f){
        f.addEventListener('submit', function(){ document.body.classList.add('is-loading'); });
    });

    var toast=document.getElementById('flash-toast');
    if(toast){
        setTimeout(function(){ toast.classList.add('show'); },100);
        setTimeout(function(){ toast.classList.remove('show'); },4000);
    }

    if(window.VirtualSelect){
       VirtualSelect.init({
      ele:'#assign_mechanic',
      multiple:true,
      search:true,
      showValueAsTags:true,
      dropboxWidth:'420px',   // modern fixed width (optional)
      zIndex:99999,
      appendToBody:true
    });
    }

    if(window.flatpickr){
        flatpickr('.datepick', {
            dateFormat: 'd-m-Y',
            allowInput: true,
            disableMobile: true
        });
    }

    var btnAdd=document.getElementById('btn-add-nature');
    if(btnAdd){ btnAdd.addEventListener('click', function(){ openNatureModal('add',''); }); }

    document.addEventListener('keydown', function(e){
        if(e.key==='Escape' || e.keyCode===27){ closeNatureModal(); }
    });

    if(window.jQuery){
        jQuery('#customer_name').autocomplete({
            minLength:2,
            source:function(request,response){
                jQuery.getJSON('complaint_order.php',
                    {ajax:'cust_autocomplete', term:request.term},
                    function(data){ response(data); });
            },
            select:function(event,ui){
                jQuery('#customer_id').val(ui.item.id);
                jQuery('#customer_name').val(ui.item.value);
                jQuery('#address').val(ui.item.address || '');
              var ad = (ui.item.address || '').toString();
var adEl = document.getElementById('address_display');
if(adEl){
  adEl.value = ad;
  adEl.scrollTop = 0;
  adEl.scrollLeft = 0;
}

                loadSites(ui.item.id, 0, 0, true);
                return false;
            },
            change:function(event,ui){
                if(!ui.item){
                    jQuery('#customer_id').val('');
                    jQuery('#address').val('');
                   var adEl = document.getElementById('address_display');
if(adEl){
  adEl.value = '';
  adEl.scrollTop = 0;
  adEl.scrollLeft = 0;
}

                    clearSitenameAndPlant();
                }
            }
        });
    }

    var initialCustId   = <?php echo (int)($editingHeader['customer_id'] ?? 0); ?>;
    var initialSiteId   = <?php echo (int)($editingHeader['sitename'] ?? 0); ?>;
    var initialPlantId  = <?php echo (int)($editingHeader['plant'] ?? 0); ?>;
    if(initialCustId){
        loadSites(initialCustId, initialSiteId, initialPlantId, false);
    }

    var sitenameSel = document.getElementById('sitename');
    if(sitenameSel){
        sitenameSel.addEventListener('change', function(){
            var siteId = this.value || '';
            loadPlants(siteId, 0);
        });
    }

    var form = document.getElementById('complaint-form');
    if(form){
        form.addEventListener('submit', function(e){
            var ok = runValidation();
            if(!ok){
                e.preventDefault();
                document.body.classList.remove('is-loading');
            }
        });
    }
});

/* -------- Dependent dropdown helpers ---------- */
function clearSitenameAndPlant(){
    var s = document.getElementById('sitename');
    var p = document.getElementById('plantname');
    if(s){ s.innerHTML = '<option value="">Select Site Name</option>'; }
    if(p){ p.innerHTML = '<option value="">Select Plant Name</option>'; }
}

function loadSites(custId, selectedSiteId, selectedPlantId, clearPlant){
    var sel = document.getElementById('sitename');
    if(!sel || !custId){ clearSitenameAndPlant(); return; }
    sel.disabled = true;
    sel.innerHTML = '<option>Loading...</option>';

    if(clearPlant){ loadPlants('', 0); }

    var xhr = new XMLHttpRequest();
    xhr.open('GET','complaint_order.php?ajax=sites&customer_id='+encodeURIComponent(custId),true);
    xhr.onreadystatechange=function(){
        if(xhr.readyState===4){
            sel.disabled = false;
            if(xhr.status===200){
                try{
                    var data = JSON.parse(xhr.responseText || '[]');
                    var html = '<option value="">Select Site Name</option>';
                    data.forEach(function(d){
                        var isSel = selectedSiteId && Number(selectedSiteId) === Number(d.id);
                        html += '<option value="'+d.id+'"'+(isSel?' selected':'')+'>'+escapeHtml(d.name)+'</option>';
                    });
                    sel.innerHTML = html;
                    if(selectedSiteId && selectedPlantId){
                        loadPlants(selectedSiteId, selectedPlantId);
                    }
                }catch(e){
                    sel.innerHTML = '<option value="">Select Site Name</option>';
                }
            }else{
                sel.innerHTML = '<option value="">Select Site Name</option>';
            }
        }
    };
    xhr.send();
}

function loadPlants(siteId, selectedPlantId){
    var sel = document.getElementById('plantname');
    if (!sel) { return; }

    if (!siteId) {
        sel.innerHTML = '<option value="">Select Plant Name</option>';
        return;
    }

    sel.disabled = true;
    sel.innerHTML = '<option>Loading...</option>';

    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'complaint_order.php?ajax=plants&site_id=' + encodeURIComponent(siteId), true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            sel.disabled = false;

            // 🔍 DEBUG: see exactly what backend returns
            console.log('PLANT AJAX RESPONSE:', xhr.responseText);

            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText || '[]');

                    if (!Array.isArray(data)) {
                        // if not array, show quick debug
                        alert('Plant JSON is not array:\n' + xhr.responseText);
                        sel.innerHTML = '<option value="">Select Plant Name</option>';
                        return;
                    }

                    var html = '<option value="">Select Plant Name</option>';
                    data.forEach(function (d) {
                        var isSel = selectedPlantId && Number(selectedPlantId) === Number(d.id);
                        html += '<option value="' + d.id + '"' + (isSel ? ' selected' : '') + '>' +
                                escapeHtml(d.name) + '</option>';
                    });
                    sel.innerHTML = html;
                } catch (e) {
                    // 🔍 if JSON.parse fails, show the raw response
                    alert('Plant JSON parse error:\n' + xhr.responseText);
                    sel.innerHTML = '<option value="">Select Plant Name</option>';
                }
            } else {
                sel.innerHTML = '<option value="">Select Plant Name</option>';
            }
        }
    };
    xhr.send();
}


/* -------- Nature modal logic ---------- */
function openNatureModal(mode,rid){
    var modal=document.getElementById('nature-modal');
    if(!modal) return;
    var title=document.getElementById('nature-modal-title');
    var rowId=document.getElementById('nature-modal-row-id');
    var ctype=document.getElementById('nature-modal-ctype');
    var cnature=document.getElementById('nature-modal-cnature');
    var desc=document.getElementById('nature-modal-desc');

    document.getElementById('nature-panel').classList.remove('field-error');
    var err = document.querySelector('#nature-panel .error-text');
    if(err) err.remove();

    rowId.value=rid||'';
    ctype.value='';
    cnature.innerHTML='<option value="">Select Complaint Nature</option>';
    desc.value='';

    if(mode==='edit' && rid){
        title.textContent='Edit Complaint Nature';
        var ctVal   = document.querySelector('.nature-ct-val[data-row="'+rid+'"]');
        var cnVal   = document.querySelector('.nature-cn-val[data-row="'+rid+'"]');
        var descVal = document.querySelector('.nature-desc-val[data-row="'+rid+'"]');
        var ctId = ctVal ? ctVal.value : '';
        var cnId = cnVal ? cnVal.value : '';
        var dsc  = descVal? descVal.value : '';
        ctype.value = ctId || '';
        desc.value  = dsc || '';
        if(ctId){ loadNatureOptionsForModal(ctId, cnId); }
    } else {
        title.textContent='Add Complaint Nature';
    }

    ctype.onchange=function(){
        cnature.innerHTML='<option value="">Select Complaint Nature</option>';
        if(this.value){ loadNatureOptionsForModal(this.value,''); }
    };

    modal.classList.remove('hidden');
}
function closeNatureModal(){
    var modal=document.getElementById('nature-modal');
    if(modal) modal.classList.add('hidden');
}
function loadNatureOptionsForModal(typeId,selectedId){
    var sel=document.getElementById('nature-modal-cnature');
    if(!sel) return;
    sel.disabled=true;
    sel.innerHTML='<option>Loading...</option>';
    var xhr=new XMLHttpRequest();
    xhr.open('GET','complaint_order.php?ajax=get_natures&type='+encodeURIComponent(typeId),true);
    xhr.onreadystatechange=function(){
        if(xhr.readyState===4){
            sel.disabled=false;
            if(xhr.status===200){
                try{
                    var data=JSON.parse(xhr.responseText);
                    var html='<option value="">Select Complaint Nature</option>';
                    data.forEach(function(d){
                        var s=(String(selectedId)===String(d.id))?' selected':'';
                        html+='<option value="'+d.id+'"'+s+'>'+escapeHtml(d.name)+'</option>';
                    });
                    sel.innerHTML=html;
                }catch(e){ sel.innerHTML='<option value="">Unable to load</option>'; }
            }else sel.innerHTML='<option value="">Unable to load</option>';
        }
    };
    xhr.send();
}
function saveNatureFromModal(){
    var rowId=document.getElementById('nature-modal-row-id').value || '';
    var ctype=document.getElementById('nature-modal-ctype');
    var cnature=document.getElementById('nature-modal-cnature');
    var desc=document.getElementById('nature-modal-desc');

    var ctId=ctype.value, cnId=cnature.value, dsc=desc.value || '';
    if(!ctId || !cnId){
        alert('Please select Complaint Type and Complaint Nature.');
        return;
    }

    var ctText=ctype.options[ctype.selectedIndex].text;
    var cnText=cnature.options[cnature.selectedIndex].text;

    var empty = document.getElementById('nature-empty');
    if(empty) empty.remove();

    if(!rowId){
        rowId=String(Date.now());
        appendNatureRow(rowId,ctId,cnId,dsc,ctText,cnText);
    }else{
        updateNatureRow(rowId,ctId,cnId,dsc,ctText,cnText);
    }
    closeNatureModal();
}
function appendNatureRow(rid,ctId,cnId,desc,ctText,cnText){
    var grid=document.getElementById('nature-grid');
    if(!grid) return;
    var html=`
    <div class="nature-row" data-row-id="${rid}"
         style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;margin-bottom:8px;background:#fafafa;display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
        <input type="hidden" class="nature-ct-val"   name="complainttype[]"   data-row="${rid}" value="${ctId}">
        <input type="hidden" class="nature-cn-val"   name="complaintnature[]" data-row="${rid}" value="${cnId}">
        <input type="hidden" class="nature-desc-val" name="complaintdesc[]"   data-row="${rid}" value="${escapeHtml(desc)}">
        <div style="flex:1 1 auto;">
            <div style="display:flex;flex-wrap:wrap;gap:20px;font-size:13px;margin-bottom:4px;">
                <div><strong>Complaint Type:</strong> <span data-label-type="${rid}">${escapeHtml(ctText)}</span></div>
                <div><strong>Complaint Nature:</strong> <span data-label-nature="${rid}">${escapeHtml(cnText)}</span></div>
            </div>
            <div data-label-desc="${rid}" style="font-size:12px;color:#4b5563;white-space:pre-wrap;">
                <strong>Description:</strong> ${desc ? escapeHtml(desc) : '—'}
            </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;white-space:nowrap;">
            <button type="button" class="btn primary" style="padding:4px 10px;font-size:11px;"
                    onclick="openNatureModal('edit','${rid}');">Edit</button>
            <button type="button" class="btn danger" style="padding:4px 10px;font-size:11px;"
                    onclick="removeNatureRow('${rid}');">Delete</button>
        </div>
    </div>`;
    grid.insertAdjacentHTML('beforeend',html);
}
function updateNatureRow(rid,ctId,cnId,desc,ctText,cnText){
    var ctVal=document.querySelector('.nature-ct-val[data-row="'+rid+'"]');
    var cnVal=document.querySelector('.nature-cn-val[data-row="'+rid+'"]');
    var descVal=document.querySelector('.nature-desc-val[data-row="'+rid+'"]');
    if(ctVal) ctVal.value=ctId;
    if(cnVal) cnVal.value=cnId;
    if(descVal) descVal.value=desc;

    var lblType=document.querySelector('[data-label-type="'+rid+'"]');
    var lblNature=document.querySelector('[data-label-nature="'+rid+'"]');
    var lblDesc=document.querySelector('[data-label-desc="'+rid+'"]');
    if(lblType) lblType.textContent=ctText;
    if(lblNature) lblNature.textContent=cnText;
    if(lblDesc) lblDesc.innerHTML='<strong>Description:</strong> '+(desc?escapeHtml(desc):'—');
}
function removeNatureRow(rid){
    var row=document.querySelector('.nature-row[data-row-id="'+rid+'"]');
    if(row) row.remove();
    var grid=document.getElementById('nature-grid');
    if(grid && !grid.querySelector('.nature-row')){
        var empty=document.createElement('div');
        empty.id='nature-empty';
        empty.style.cssText='font-size:12px;color:#6b7280;padding:8px 4px;';
        empty.textContent='No complaint nature rows added yet.';
        grid.appendChild(empty);
    }
}
function escapeHtml(str){
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

/* ------------ Client-side validation -------------- */
function clearErrors(){
    document.querySelectorAll('.field-error').forEach(function(el){ el.classList.remove('field-error'); });
    document.querySelectorAll('.error-text').forEach(function(el){ el.remove(); });
}
function addErrorForInput(input, message){
    if(!input) return;
    var field = input.closest('.field') || input;
    field.classList.add('field-error');
    var msg = document.createElement('div');
    msg.className = 'error-text';
    msg.textContent = message;
    field.appendChild(msg);
}
function runValidation(){
    clearErrors();
    var form = document.getElementById('complaint-form');
    if(!form) return true;

    var firstErrorEl = null;
    function mark(input, msg){
        addErrorForInput(input, msg);
        if(!firstErrorEl) firstErrorEl = input.closest('.field') || input;
    }

    var cd  = form.complaint_date.value.trim();
    var custName = form.customer_name.value.trim();
    var custId   = form.customer_id.value.trim();
    var siteSel  = form.sitename;
    var statusDate = form.status_date.value.trim();
    var compStatus = form.complaint_status.value.trim();

    if(!cd){ mark(form.complaint_date, 'Complaint Date is required.'); }
    if(!custName || !custId){ mark(document.getElementById('customer_name'), 'Select a valid customer.'); }
    if(!siteSel.value){ mark(siteSel, 'Site Name is required.'); }
    if(!statusDate){ mark(form.status_date, 'Status Date is required.'); }
    if(!compStatus){ mark(form.complaint_status, 'Complaint Status is required.'); }

    var natureRows = document.querySelectorAll('#nature-grid .nature-row');
    if(natureRows.length === 0){
        var panel = document.getElementById('nature-panel');
        panel.classList.add('field-error');
        var msg = document.createElement('div');
        msg.className = 'error-text';
        msg.textContent = 'Add at least one Complaint Nature row.';
        panel.appendChild(msg);
        if(!firstErrorEl) firstErrorEl = panel;
    }

    if(firstErrorEl){
        var rect = firstErrorEl.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        window.scrollTo({ top: rect.top + scrollTop - 120, behavior: 'smooth' });
        return false;
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function () {

    const processSelect = document.getElementById('process_type');
    const mechanicSection = document.getElementById('mechanic-section');

    if (!processSelect || !mechanicSection) return;

    function toggleMechanicFields() {
        const selectedVal = Number(processSelect.value || 0);

        if (selectedVal === ON_FIELD_SERVICE_ID) {
            mechanicSection.style.display = 'block';
        } else {
            mechanicSection.style.display = 'none';
            clearMechanicFields();
        }
    }

    function clearMechanicFields() {
        // Clear mechanic date
        const mechDate = document.querySelector('input[name="mechanic_date"]');
        if (mechDate) mechDate.value = '';

        // Clear mechanic status
        const mechStatus = document.querySelector('select[name="mechanic_status"]');
        if (mechStatus) mechStatus.value = '';

        // Clear VirtualSelect
        const mechSelect = document.getElementById('assign_mechanic');
        if (mechSelect && mechSelect.virtualSelect) {
            mechSelect.virtualSelect.reset();
        }
    }

    // 🔥 Run on page load (EDIT MODE FIX)
    toggleMechanicFields();

    // 🔥 Run when process type changes
    processSelect.addEventListener('change', toggleMechanicFields);
});
</script>
<?php
$CONTENT = ob_get_clean();
require_once __DIR__ . '/../includes/ui_autoshell.php';