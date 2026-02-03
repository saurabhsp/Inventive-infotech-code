<?php
/* ============================================================
 * operations/service_quotation.php
 * Complaint / Service Quotation (Core ERP) — POST-only
 * ============================================================
 * - NO list / print / export
 * - Edit + Prefill-from-complaint = POST only (no ?id=, no ?lid=)
 * - Grid + Terms via rows_json / terms_json (kept)
 * - CSRF + ACL + Transactions (kept)
 * - qtype → title → subject/descr via jos_crm_servicequotation_config (AJAX POST)
 * - Financial year + billno rule (NEW only)
 * - Totals block computed client-side, saved safely (col_exists guarded)
 * ============================================================ */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/initialize.php';
require_once __DIR__ . '/../includes/aclhelper.php';

if (!function_exists('is_logged_in') || !is_logged_in()) {
    redirect('../login.php');
}
date_default_timezone_set('Asia/Kolkata');

/* ============================================================
 * TABLES (FROZEN)
 * ============================================================ */
$TABLE_Q_HEADER   = 'jos_ierp_complaint_quotation';
$TABLE_Q_GRID     = 'jos_ierp_complaint_quotationgrid';     // ✅ FINAL (NO fallback)
$TABLE_Q_TERMS    = 'jos_ierp_complaint_quotationterms';

$TABLE_CUSTOMERS  = 'jos_ierp_customermaster';
$TABLE_PRODUCTS   = 'jos_crm_mproducts';                    // id,name,modelcode,hsncode,cgst
$TABLE_FY         = 'jos_ierp_mfinancialyear';
$TABLE_QTYPE      = 'jos_crm_servicequotationtype';
$TABLE_CONFIG     = 'jos_crm_servicequotation_config';
$TABLE_COMPLAINT  = 'jos_ierp_complaint';

$TABLE_TERMTYPES  = 'jos_servicequotation_terms_conditions_type';
$TABLE_TERMS_M    = 'jos_erp_servicetermsconditions';

/* ============================================================
 * HELPERS
 * ============================================================ */
function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

function set_flash($k,$m){ $_SESSION[$k]=$m; }
function get_flash($k){ if(!empty($_SESSION[$k])){ $m=$_SESSION[$k]; unset($_SESSION[$k]); return $m; } return ''; }

function parse_dmy_date($val) {
    $val = trim((string)$val);
    if ($val === '') return null;
    $dt = DateTime::createFromFormat('d-m-Y', $val);
    if (!$dt) $dt = DateTime::createFromFormat('Y-m-d', $val);
    return $dt ? $dt->format('Y-m-d') : null;
}
function fmt_dmy($ymd) {
    if (!$ymd || $ymd === '0000-00-00') return '';
    $dt = DateTime::createFromFormat('Y-m-d', substr((string)$ymd,0,10));
    return $dt ? $dt->format('d-m-Y') : '';
}

/* CSRF */
function csrf_token() {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['_csrf'];
}
function verify_csrf() {
    $t = $_POST['_csrf'] ?? '';
    return $t !== '' && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $t);
}

/* schema helpers */
function col_exists(mysqli $con, string $table, string $col): bool {
    $db = $con->query("SELECT DATABASE() d")->fetch_assoc()['d'] ?? '';
    if ($db === '') return false;
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
    $st = $con->prepare($sql);
    $st->bind_param('sss', $db, $table, $col);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_row();
    $st->close();
    return $ok;
}
function first_existing_col(mysqli $con, string $table, array $candidates): ?string {
    foreach ($candidates as $c) {
        if (col_exists($con, $table, $c)) return $c;
    }
    return null;
}
function table_exists(mysqli $con, string $table): bool {
    $db = $con->query("SELECT DATABASE() d")->fetch_assoc()['d'] ?? '';
    if ($db === '') return false;
    $st = $con->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1");
    $st->bind_param('ss', $db, $table);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_row();
    $st->close();
    return $ok;
}

/* ============================================================
 * MENU META + ACL (ERP Console)
 * ============================================================ */
$aclMeta = erp_get_menu_meta_and_acl($con);

$menuMetaTitle  = $aclMeta['title']      ?? 'Complaint Quotation';
$menuMetaRemark = $aclMeta['remark']     ?? '';
$canView        = $aclMeta['can_view']   ?? false;
$canAdd         = $aclMeta['can_add']    ?? false;
$canEdit        = $aclMeta['can_edit']   ?? false;
$canDelete      = $aclMeta['can_delete'] ?? false;

$userObj       = current_user() ?? [];
$currentUserId = isset($userObj['id']) ? (int)$userObj['id'] : 0;

/* Access guard */
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
    require_once __DIR__ . '/../includes/ui_autoshell.php';exit;
}

/* sanity */
if (!table_exists($con, $TABLE_Q_HEADER) || !table_exists($con, $TABLE_Q_GRID) || !table_exists($con, $TABLE_Q_TERMS)) {
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
            <div class="alert danger">
                Required tables not found. Please verify:
                <br>- <?php echo h($TABLE_Q_HEADER); ?>
                <br>- <?php echo h($TABLE_Q_GRID); ?>
                <br>- <?php echo h($TABLE_Q_TERMS); ?>
            </div>
        </div>
    </div>
    <?php
    $CONTENT = ob_get_clean();
    require_once __DIR__ . '/../includes/ui_autoshell.php';
    exit;
}

/* ============================================================
 * POST-ONLY AJAX ENDPOINTS (no ?ajax=)
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    if (ob_get_length()) { @ob_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    $ajax = (string)$_POST['ajax'];

    /* customer lookup */
    if ($ajax === 'customer_lookup') {
        $term = trim((string)($_POST['term'] ?? ''));
        $out = [];
        if ($term !== '') {
            $like = '%'.$term.'%';
            $sql = "SELECT id, name
                    FROM {$TABLE_CUSTOMERS}
                    WHERE name LIKE ?
                    ORDER BY name ASC
                    LIMIT 20";
            $st = $con->prepare($sql);
            $st->bind_param('s', $like);
            $st->execute();
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) {
                $out[] = [
                    'id'    => (int)$r['id'],
                    'label' => (string)$r['name'],
                    'name'  => (string)$r['name'],
                ];
            }
            $st->close();
        }
        echo json_encode($out);
        exit;
    }
/* product lookup */
if ($ajax === 'product_lookup') {
    $term = trim((string)($_POST['term'] ?? ''));
    $rows = [];
    if ($term !== '') {
        $like = '%'.$term.'%';
        $sql = "SELECT p.id,
                       TRIM(CONCAT(COALESCE(p.name,''),' ',COALESCE(p.modelcode,''))) AS label,
                       COALESCE(p.hsncode,'') AS hsncode,
                       COALESCE(p.cgst,0) AS gstslab_id,
                       COALESCE(gs.name,'0') AS gst_percent
                FROM {$TABLE_PRODUCTS} p
                LEFT JOIN jos_crm_gstslab gs ON gs.id = p.cgst
                WHERE COALESCE(p.name,'') LIKE ? OR COALESCE(p.modelcode,'') LIKE ?
                ORDER BY p.name ASC
                LIMIT 30";
        $st = $con->prepare($sql);
        $st->bind_param('ss', $like, $like);
        $st->execute();
        $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) {
            $gstTotal = (float)($r['gst_percent'] ?? 0); // ✅ total GST percent (like 18)
            $rows[] = [
                'id'         => (int)$r['id'],
                'label'      => (string)$r['label'],
                'hsncode'    => (string)($r['hsncode'] ?? ''),
                'gstslab_id' => (int)($r['gstslab_id'] ?? 0),
                'gst'        => $gstTotal,               // ✅ total GST (18 => 9+9)
                'cgst'       => $gstTotal / 2,
                'sgst'       => $gstTotal / 2,
            ];
        }
        $st->close();
    }
    echo json_encode($rows);
    exit;
}

    /* term master by type */
    if ($ajax === 'term_master') {
        $typeId = (int)($_POST['type'] ?? 0);
        $resp = ['ok'=>0];
        if ($typeId > 0) {
            $sql = "SELECT title, description
                    FROM {$TABLE_TERMS_M}
                    WHERE type = ?
                    ORDER BY id DESC
                    LIMIT 1";
            $st = $con->prepare($sql);
            $st->bind_param('i', $typeId);
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            if ($r) {
                $resp = [
                    'ok' => 1,
                    'type' => $typeId,
                    'title' => (string)($r['title'] ?? ''),
                    'description' => (string)($r['description'] ?? '')
                ];
            }
        }
        echo json_encode($resp);
        exit;
    }

   
  
 /* --------------------------------
 * (1) AJAX: service_title
 * - Return: id + name
 * - JS will set <option value="id">name</option>
 * -------------------------------- */
if ($ajax === 'service_title') {
    $qtype = (int)($_POST['qtype'] ?? 0);
    $out = [];

    if ($qtype > 0) {
        $sql = "
            SELECT id, name
            FROM jos_servicequotation_subtitle
            WHERE type = ?
            ORDER BY name ASC
        ";
        $st = $con->prepare($sql);
        $st->bind_param('i', $qtype);
        $st->execute();
        $rs = $st->get_result();

        while ($r = $rs->fetch_assoc()) {
            $out[] = [
                'id'   => (int)$r['id'],
                'name' => (string)$r['name']
            ];
        }
        $st->close();
    }

    echo json_encode($out);
    exit;
}

/* --------------------------------
 * (2) AJAX: service_subject
 * - NOW uses title_id (INT) because config.title stores subtitle id
 * -------------------------------- */
if ($ajax === 'service_subject') {

    $qtype    = (int)($_POST['qtype'] ?? 0);
    $title_id = (int)($_POST['title_id'] ?? 0);

    $resp = ['ok'=>0,'subject'=>'','description'=>''];

    if ($qtype > 0 && $title_id > 0) {

        $sql = "
            SELECT subject, description
            FROM {$TABLE_CONFIG}
            WHERE qtype = ?
              AND title = ?     /* ✅ title is ID stored in DB */
            LIMIT 1
        ";

        $st = $con->prepare($sql);
        $st->bind_param('ii', $qtype, $title_id);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();

        if ($r) {
            $resp = [
                'ok' => 1,
                'subject' => (string)($r['subject'] ?? ''),
                'description' => (string)($r['description'] ?? '')
            ];
        }
    }

    echo json_encode($resp);
    exit;
}

    // ✅ If ajax is not matched
    echo json_encode(['ok'=>0,'msg'=>'Unknown ajax']);
    exit;
} // ✅ CLOSE ajax wrapper here (VERY IMPORTANT)


/* ============================================================
 * LOAD MASTER TERM TYPES FOR UI
 * ============================================================ */
$termTypes = [];
if (table_exists($con, $TABLE_TERMTYPES)) {
    $hasRank = col_exists($con, $TABLE_TERMTYPES, 'rank');
    $selectCols = $hasRank ? "id, `rank`, `type`" : "id, `type`";
    $orderBy = $hasRank ? "`rank` ASC, id ASC" : "id ASC";
    $rsTT = $con->query("SELECT {$selectCols} FROM {$TABLE_TERMTYPES} ORDER BY {$orderBy}");
    while ($rsTT && ($r = $rsTT->fetch_assoc())) $termTypes[] = $r;
}

/* ============================================================
 * QUOTATION TYPE OPTIONS
 * ============================================================ */
$qtypeOptions = [];

if (table_exists($con, 'jos_crm_servicequotationtype')) {
    $rs = $con->query("
        SELECT id, qtype 
        FROM jos_crm_servicequotationtype
        ORDER BY qtype ASC
    ");

    while ($rs && ($r = $rs->fetch_assoc())) {
        $qtypeOptions[] = [
            'id'    => (int)$r['id'],
            'label' => (string)$r['qtype']
        ];
    }
}

/* ============================================================
 *GST SLABS OPTIONS
 * ============================================================ */

$gstSlabs = [];
if (table_exists($con, 'jos_crm_gstslab')) {
    $rs = $con->query("SELECT id, name FROM jos_crm_gstslab ORDER BY CAST(name AS DECIMAL(10,2)) ASC");
    while ($rs && ($r = $rs->fetch_assoc())) {
        $gstSlabs[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];
    }
}


/* ============================================================
 * COMPANY (SUB-COMPANY) OPTIONS
 * ============================================================ */
$companyOptions = [];

if (table_exists($con, 'jos_ierp_subcompany')) {
    $rs = $con->query("
        SELECT id, name
        FROM jos_ierp_subcompany
        ORDER BY name ASC
    ");
    while ($rs && ($r = $rs->fetch_assoc())) {
        $companyOptions[] = [
            'id'   => (int)$r['id'],
            'name' => (string)$r['name']
        ];
    }
}




/* ============================================================
 * POST-ONLY NAVIGATION MODEL
 * ============================================================ */
$mode = 'new';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = (string)($_POST['mode'] ?? 'new');
    if (!in_array($mode, ['new','edit','prefill_from_complaint','save'], true)) $mode = 'new';
}
$editId = (int)($_POST['edit_id'] ?? 0);
$prefillComplaintId = (int)($_POST['prefill_complaint_id'] ?? 0);

// ✅ complaint id can come as complaint_id OR complaintid (from list button)
$complaint_id = (int)($_POST['complaint_id'] ?? ($_POST['complaintid'] ?? 0));

// If user clicked "Generate Quotation" from complaint list (POST-only),
// force prefill mode using that complaint id.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $complaint_id > 0 && $mode === 'new' && $prefillComplaintId <= 0) {
    $mode = 'prefill_from_complaint';
    $prefillComplaintId = $complaint_id;
}


/* ============================================================
 * DEFAULT HEADER
 * ============================================================ */
$editingHeader = [
    'id' => 0,
    'sysdate' => '',
    'yrid' => 0,
    'billno' => '',
    'date' => date('d-m-Y'),
    'companyname' => '',
    'qtype' => 0,
    'title' => '',
    'kindattn' => '',
    'refe' => '',
    'subject' => '',
    'descr' => '',
    'gsttype' => 0,
    'customer' => '',
    'custid' => 0,
    'complaint_id' => 0,
    'remark' => '',

    /* totals defaults */
    'subtotal'=>0, 'disctype'=>0, 'discount'=>0, 'discountamt'=>0,
    'gst'=>0, 'nettotal'=>0, 'total2'=>0,
    'tcs'=>0, 'tcsamt'=>0, 'total3'=>0,
    'packing_per'=>0, 'packing_amt'=>0, 'packing'=>0, 'packing_total'=>0,
    'trans_per'=>0, 'trans_amt'=>0, 'trans'=>0, 'trans_total'=>0,
    'transfromdate'=>'', 'transtodate'=>'', 'total4'=>0,
    'insurance_per'=>0, 'insurance_amt'=>0, 'insurance'=>0, 'insurance_total'=>0,
    'commission_per'=>0, 'commission_amt'=>0, 'commission'=>0, 'commission_total'=>0,
    'total5'=>0, 'total'=>0
];

$editingGrid  = [];
$editingTerms = [];
$loadedFromMode = $mode;

/* ============================================================
 * EDIT LOAD (POST only)
 * ============================================================ */
if ($mode === 'edit' && $editId > 0) {
    if (!$canEdit) {
        set_flash('q_error', 'No permission to edit.');
        $mode = 'new';
    } else {
        $st = $con->prepare("SELECT * FROM {$TABLE_Q_HEADER} WHERE id=? LIMIT 1");
        $st->bind_param('i', $editId);
        $st->execute();
        $hdr = $st->get_result()->fetch_assoc();
        $st->close();


       if ($hdr) {
    $editingHeader = array_merge($editingHeader, $hdr);
    
    // ✅ normalize complaint id for UI (header column is complaintid in DB)
if (empty($editingHeader['complaint_id']) && !empty($editingHeader['complaintid'])) {
    $editingHeader['complaint_id'] = (int)$editingHeader['complaintid'];
}

    if (isset($editingHeader['date'])) {
        $editingHeader['date'] = fmt_dmy($editingHeader['date']);
    }

    // ✅ FIX: Resolve customer name AFTER header data is loaded
    if (!empty($editingHeader['custid'])) {
        $stc = $con->prepare("SELECT name FROM {$TABLE_CUSTOMERS} WHERE id=? LIMIT 1");
        $stc->bind_param('i', $editingHeader['custid']);
        $stc->execute();
        $row = $stc->get_result()->fetch_row();
        $stc->close();

        $editingHeader['customer'] = $row[0] ?? '';
    }



            // grid rows + join product label for UI
            $seqExists = col_exists($con, $TABLE_Q_GRID, 'sequence');
            $order = $seqExists ? "ORDER BY sequence ASC, id ASC" : "ORDER BY id ASC";

            $sql = "SELECT g.*,
                           TRIM(CONCAT(COALESCE(p.name,''),' ',COALESCE(p.modelcode,''))) AS product_label
                    FROM {$TABLE_Q_GRID} g
                    LEFT JOIN {$TABLE_PRODUCTS} p ON p.id = g.propid
                    WHERE g.qutid=?
                    {$order}";
            $stg = $con->prepare($sql);
            $stg->bind_param('i', $editId);
            $stg->execute();
            $rs = $stg->get_result();
            while ($rs && ($r = $rs->fetch_assoc())) $editingGrid[] = $r;
            $stg->close();

            $stt = $con->prepare("SELECT * FROM {$TABLE_Q_TERMS} WHERE qutid=? ORDER BY id ASC");
            $stt->bind_param('i', $editId);
            $stt->execute();
            $rs = $stt->get_result();
            while ($rs && ($r = $rs->fetch_assoc())) $editingTerms[] = $r;
            $stt->close();

            $editingHeader['id'] = (int)$editId;
        } else {
            set_flash('q_error', 'Record not found.');
            $mode = 'new';
        }
    }
}

/* ============================================================
 * PREFILL FROM COMPLAINT (POST only)
 * ============================================================ */
if ($mode === 'prefill_from_complaint' && $prefillComplaintId > 0) {
    if (!$canAdd) {
        set_flash('q_error', 'No permission to add.');
        $mode = 'new';
    } else {
        $st = $con->prepare("SELECT * FROM {$TABLE_COMPLAINT} WHERE id=? LIMIT 1");
        $st->bind_param('i', $prefillComplaintId);
        $st->execute();
        $comp = $st->get_result()->fetch_assoc();
        $st->close();

        if ($comp) {
            $editingHeader['complaint_id'] = $prefillComplaintId;
            $editingHeader['custid'] = (int)($comp['custid'] ?? 0);

            if ($editingHeader['custid'] > 0) {
                $stc = $con->prepare("SELECT name FROM {$TABLE_CUSTOMERS} WHERE id=? LIMIT 1");
                $stc->bind_param('i', $editingHeader['custid']);
                $stc->execute();
                $cn = $stc->get_result()->fetch_row();
                $stc->close();
                $editingHeader['customer'] = (string)($cn[0] ?? '');
            }
        } else {
            set_flash('q_error', 'Complaint not found for prefill.');
            $mode = 'new';
        }
    }
}

/* ============================================================
 * SAVE (POST only)
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quotation_form']) && $_POST['quotation_form'] === '1') {

    if (!verify_csrf()) {
        set_flash('q_error', 'Invalid token. Please refresh and try again.');
        header('Location: service_quotation.php');
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $isNew = ($id === 0);

    if ($isNew && !$canAdd) {
        set_flash('q_error', 'No permission to add.');
        header('Location: service_quotation.php');
        exit;
    }
    if (!$isNew && !$canEdit) {
        set_flash('q_error', 'No permission to edit.');
        header('Location: service_quotation.php');
        exit;
    }

    /* main fields */
    $q_date = parse_dmy_date($_POST['q_date'] ?? '');
    $customer = trim((string)($_POST['customer'] ?? ''));
    $custid   = (int)($_POST['custid'] ?? 0);
   $complaint_id = (int)($_POST['complaint_id'] ?? ($_POST['complaintid'] ?? 0));
    $remark = trim((string)($_POST['remark'] ?? ''));

    /* blueprint header fields */
    $companyname = trim((string)($_POST['companyname'] ?? ''));
    $qtype = (int)($_POST['qtype'] ?? 0);
   $title = (int)($_POST['title'] ?? 0);  
    $kindattn = trim((string)($_POST['kindattn'] ?? ''));
    $refe = (string)($_POST['refe'] ?? '');
    $subject = (string)($_POST['subject'] ?? '');
    $descr = (string)($_POST['descr'] ?? '');
    $gsttype = 0;

    /* totals */
    $totals = [
        'subtotal' => (float)($_POST['subtotal'] ?? 0),
        'disctype' => (int)($_POST['disctype'] ?? 0),
        'discount' => (float)($_POST['discount'] ?? 0),
        'discountamt' => (float)($_POST['discountamt'] ?? 0),
        'gst' => (float)($_POST['gst'] ?? 0),
        'nettotal' => (float)($_POST['nettotal'] ?? 0),
        'total2' => (float)($_POST['total2'] ?? 0),
        'tcs' => (float)($_POST['tcs'] ?? 0),
        'tcsamt' => (float)($_POST['tcsamt'] ?? 0),
        'total3' => (float)($_POST['total3'] ?? 0),
        'packing_per' => (float)($_POST['packing_per'] ?? 0),
        'packing_amt' => (float)($_POST['packing_amt'] ?? 0),
        'packing' => (float)($_POST['packing'] ?? 0),
        'packing_total' => (float)($_POST['packing_total'] ?? 0),
        'trans_per' => (float)($_POST['trans_per'] ?? 0),
        'trans_amt' => (float)($_POST['trans_amt'] ?? 0),
        'trans' => (float)($_POST['trans'] ?? 0),
        'trans_total' => (float)($_POST['trans_total'] ?? 0),
        'transfromdate' => (string)($_POST['transfromdate'] ?? ''),
        'transtodate' => (string)($_POST['transtodate'] ?? ''),
        'total4' => (float)($_POST['total4'] ?? 0),
        'insurance_per' => (float)($_POST['insurance_per'] ?? 0),
        'insurance_amt' => (float)($_POST['insurance_amt'] ?? 0),
        'insurance' => (float)($_POST['insurance'] ?? 0),
        'insurance_total' => (float)($_POST['insurance_total'] ?? 0),
        'commission_per' => (float)($_POST['commission_per'] ?? 0),
        'commission_amt' => (float)($_POST['commission_amt'] ?? 0),
        'commission' => (float)($_POST['commission'] ?? 0),
        'commission_total' => (float)($_POST['commission_total'] ?? 0),
        'total5' => (float)($_POST['total5'] ?? 0),
        'total' => (float)($_POST['total'] ?? 0),
    ];

    /* JSON payloads */
    $rowsJson = trim((string)($_POST['rows_json'] ?? '[]'));
    $termsJson= trim((string)($_POST['terms_json'] ?? '[]'));

    $rows = json_decode($rowsJson, true);
    if (!is_array($rows)) $rows = [];

    $terms = json_decode($termsJson, true);
    if (!is_array($terms)) $terms = [];

    /* validations */
    $errors = [];
    if (!$q_date) $errors[] = 'Quotation Date is required.';
    if ($customer === '' && $custid <= 0) $errors[] = 'Customer is required.';
    if ($companyname === '') $errors[] = 'Sub Company Name is required.';
    if (count($rows) < 1) $errors[] = 'Add at least one product in grid.';

    $hasItem = false;
    foreach ($rows as $r) {
        $pid = (int)($r['propid'] ?? 0);
        $desc= trim((string)($r['description'] ?? ''));
        $rate= (float)($r['rate'] ?? 0);
        $qty = (float)($r['qty'] ?? 0);
        if ($pid > 0 || $desc !== '' || $rate > 0 || $qty > 0) { $hasItem = true; break; }
    }
    if (!$hasItem) $errors[] = 'Add at least one valid product row.';

    if (!empty($errors)) {
        set_flash('q_error', implode(' ', $errors));
        header('Location: service_quotation.php');
        exit;
    }

    /* financial year + billno (NEW only) */
  /* financial year + billno (NEW only) — company-wise */
/* financial year + billno (NEW only) — COMPANY WISE */
$yrid = 0;
$billno = 0;

if ($isNew) {

    // get latest financial year
    $fy = $con->query("SELECT id FROM {$TABLE_FY} ORDER BY id DESC LIMIT 1");
    $fyRow = $fy ? $fy->fetch_row() : null;
    $yrid = (int)($fyRow[0] ?? 0);

    if ($yrid > 0 && col_exists($con, $TABLE_Q_HEADER, 'billno')) {

        $companyKey = trim((string)$companyname);

        if ($companyKey !== '' && col_exists($con, $TABLE_Q_HEADER, 'companyname')) {

            // ✅ COMPANY + FY WISE BILLNO
            $stb = $con->prepare("
                SELECT MAX(billno)
                FROM {$TABLE_Q_HEADER}
                WHERE yrid = ?
                  AND companyname = ?
            ");
            $stb->bind_param('is', $yrid, $companyKey);

        } else {

            // fallback: FY-wise only
            $stb = $con->prepare("
                SELECT MAX(billno)
                FROM {$TABLE_Q_HEADER}
                WHERE yrid = ?
            ");
            $stb->bind_param('i', $yrid);
        }

        $stb->execute();
        $stb->bind_result($lastBill);
        $stb->fetch();
        $stb->close();

        $billno = ((int)$lastBill) + 1;
    }

} else {
    // EDIT MODE → keep same billno
    $yrid   = (int)($_POST['yrid'] ?? 0);
    $billno = (int)($_POST['billno'] ?? 0);
}


    $sysdateVal = date('Y-m-d H:i:s');

    $con->begin_transaction();
    try {

        /* ---------------- HEADER SAVE (col_exists guarded) ---------------- */
        $fields = [];
        $values = [];
        $types  = '';

        $cand = [
            'sysdate' => $sysdateVal,
            'yrid' => $yrid,
            'billno' => $billno,
            'gsttype' => $gsttype,

            'date' => $q_date,
            'companyname' => $companyname,
            'qtype' => $qtype,
            
            'kindattn' => $kindattn,
            'refe' => $refe,
            'subject' => $subject,
            'descr' => $descr,

            'customer' => $customer,
            'custid' => $custid,
            'complaint_id' => $complaint_id,

            'remark' => $remark
        ];
        // ✅ ONLY add title if valid OR new record
            if ($isNew || $title > 0) {
                $cand['title'] = $title;
            }

        // totals
        foreach ($totals as $k=>$v) { $cand[$k] = $v; }

        // if sysdate column is DATE not DATETIME, store date only
        if (col_exists($con, $TABLE_Q_HEADER, 'sysdate')) {
            $sysType = null;
            $db = $con->query("SELECT DATABASE() d")->fetch_assoc()['d'] ?? '';
            if ($db) {
                $stt = $con->prepare("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
                $c = 'sysdate';
                $stt->bind_param('sss', $db, $TABLE_Q_HEADER, $c);
                $stt->execute();
                $rr = $stt->get_result()->fetch_row();
                $stt->close();
                $sysType = strtolower((string)($rr[0] ?? ''));
            }
            if ($sysType === 'date') {
                $cand['sysdate'] = date('Y-m-d');
            }
        }

        foreach ($cand as $col=>$val) {
            if (!col_exists($con, $TABLE_Q_HEADER, $col)) continue;
            $fields[] = $col;
            $values[] = $val;

            if (is_int($val)) $types .= 'i';
            elseif (is_float($val)) $types .= 'd';
            else $types .= 's';
        }

        if ($isNew) {
            // include created_by if exists
            if (col_exists($con, $TABLE_Q_HEADER, 'created_by')) {
                $fields[] = 'created_by';
                $values[] = $currentUserId;
                $types .= 'i';
            }

            $colsSql = implode(',', array_map(function($c){ return "`{$c}`"; }, $fields));
            $qs = implode(',', array_fill(0, count($fields), '?'));
            $sql = "INSERT INTO {$TABLE_Q_HEADER} ({$colsSql}) VALUES ({$qs})";
            $st = $con->prepare($sql);
            if (!$st) throw new Exception("Header prepare failed: ".$con->error);

            $st->bind_param($types, ...$values);
            $st->execute();
            $qutid = (int)$con->insert_id;
            $st->close();
        } else {
            $setSql = implode(',', array_map(function($c){ return "`{$c}`=?"; }, $fields));
            $sql = "UPDATE {$TABLE_Q_HEADER} SET {$setSql} WHERE id=?";
            $st = $con->prepare($sql);
            if (!$st) throw new Exception("Header update prepare failed: ".$con->error);

            $types2 = $types . 'i';
            $vals2 = $values;
            $vals2[] = $id;

            $st->bind_param($types2, ...$vals2);
            $st->execute();
            $st->close();

            $qutid = $id;

            $del = $con->prepare("DELETE FROM {$TABLE_Q_GRID} WHERE qutid=?");
            $del->bind_param('i', $qutid);
            $del->execute();
            $del->close();

            $delt = $con->prepare("DELETE FROM {$TABLE_Q_TERMS} WHERE qutid=?");
            $delt->bind_param('i', $qutid);
            $delt->execute();
            $delt->close();
        }

        /* ---------------- GRID INSERT (delete+insert) ---------------- */
        $gridCols = [
            'qutid' => 'i',
            'yrid' => 'i',
            'date' => 's',
            'propid' => 'i',
            'hsncode' => 's',
            'description' => 's',
            'rate' => 'd',
            'qty' => 'd',
            'discount' => 'd',
            'instantdisc' => 'd',
            'totaldiscount' => 'd',
            'discountedamt' => 'd',
            'gst' => 'd',
            'gstamt' => 'd',
            'amt' => 'd',
            'status' => 'i',
            'sequence' => 'i',
        ];

        // keep only existing columns
        $useCols = [];
        $useTypes = '';
        foreach ($gridCols as $c=>$t) {
            if (col_exists($con, $TABLE_Q_GRID, $c)) {
                $useCols[] = $c;
                $useTypes .= $t;
            }
        }
        if (!in_array('qutid', $useCols, true)) throw new Exception("Grid table missing qutid column.");
        if (!in_array('propid', $useCols, true)) throw new Exception("Grid table missing propid column.");

        $colsSql = implode(',', array_map(function($c){ return "`{$c}`"; }, $useCols));
        $qs = implode(',', array_fill(0, count($useCols), '?'));
        $insSql = "INSERT INTO {$TABLE_Q_GRID} ({$colsSql}) VALUES ({$qs})";
        $ins = $con->prepare($insSql);
        if (!$ins) throw new Exception("Grid prepare failed: ".$con->error);

        foreach ($rows as $i => $r) {
            $pid = (int)($r['propid'] ?? 0);
            $hsn = trim((string)($r['hsncode'] ?? ''));
            $dsc = trim((string)($r['description'] ?? ''));  
            $rate= (float)($r['rate'] ?? 0);
            $qty = (float)($r['qty'] ?? 0);
            $disc= (float)($r['discount'] ?? 0);
            $inst= (float)($r['instantdisc'] ?? 0);
            $gst = (float)($r['gst'] ?? 0);
            $seq = (int)($r['sequence'] ?? ($i+1));

            if ($pid <= 0 && $dsc === '' && $rate == 0 && $qty == 0) continue;

            // Computation (stable)
            $gross = $rate * $qty;
            $discAmt = ($disc > 0) ? ($gross * $disc / 100) : 0;
            $totaldiscount = $discAmt + $inst;
            $discounted = max(0, $gross - $totaldiscount);
            $gstamt = ($gst > 0) ? ($discounted * $gst / 100.0) : 0.0;
            $amt = $discounted + $gstamt;

            $rowData = [
                'qutid' => $qutid,
                'yrid' => $yrid,
                'date' => $q_date,
                'propid' => $pid,
                'hsncode' => $hsn,
                'description' => $dsc,
                'rate' => $rate,
                'qty' => $qty,
                'discount' => $disc,
                'instantdisc' => $inst,
                'totaldiscount' => $totaldiscount,
                'discountedamt' => $discounted,
                'gst' => $gst,
                'gstamt' => $gstamt,
                'amt' => $amt,
                'status' => 1,
                'sequence' => $seq,
            ];

            $bindVals = [];
            foreach ($useCols as $c) {
                $bindVals[] = $rowData[$c] ?? null;
            }

            // bind dynamically
            $ins->bind_param($useTypes, ...$bindVals);
            $ins->execute();
        }
        $ins->close();

        /* ---------------- TERMS INSERT (delete+insert) ---------------- */
        // Columns as per your current implementation
        $termCols = [
            'qutid' => 'i',
            'date' => 's',
            'type' => 'i',
            'term' => 's',
            'title' => 's',
            'description' => 's',
        ];
        $useTC = [];
        $useTT = '';
        foreach ($termCols as $c=>$t) {
            if (col_exists($con, $TABLE_Q_TERMS, $c)) {
                $useTC[] = $c;
                $useTT .= $t;
            }
        }
        if (!in_array('qutid', $useTC, true)) throw new Exception("Terms table missing qutid column.");

        $tcSql = implode(',', array_map(function($c){ return "`{$c}`"; }, $useTC));
        $tq = implode(',', array_fill(0, count($useTC), '?'));
        $termInsSql = "INSERT INTO {$TABLE_Q_TERMS} ({$tcSql}) VALUES ({$tq})";
        $instT = $con->prepare($termInsSql);
        if (!$instT) throw new Exception("Terms prepare failed: ".$con->error);

        foreach ($terms as $t) {
            $typeId = (int)($t['type'] ?? 0);
            $tTitle = trim((string)($t['title'] ?? ''));
            $tDesc  = (string)($t['description'] ?? '');

            if ($typeId <= 0 || $tTitle === '') continue;

            $termVal  = $tTitle;
            $titleVal = $tTitle;

            $rowT = [
                'qutid' => $qutid,
                'date' => $q_date,
                'type' => $typeId,
                'term' => $termVal,
                'title' => $titleVal,
                'description' => $tDesc,
            ];

            $bindVals = [];
            foreach ($useTC as $c) $bindVals[] = $rowT[$c] ?? null;

            $instT->bind_param($useTT, ...$bindVals);
            $instT->execute();
        }
        $instT->close();

        $con->commit();
        set_flash('q_ok', 'Quotation saved successfully.');

        // must clear form after save
        header('Location: service_quotation.php');
        exit;

    } catch (Throwable $e) {
        $con->rollback();
        set_flash('q_error', 'Save failed: '.$e->getMessage());
        header('Location: service_quotation.php');
        exit;
    }
}

/* ============================================================
 * SHELL VARS
 * ============================================================ */
$user        = $userObj;
$pageTitle   = $menuMetaTitle;
$systemTitle = 'ERP Console';
$systemCode  = 'AGCM';
$userName    = $user['name'] ?? 'User';
$userLoginId = $user['login_id'] ?? ($user['email'] ?? ($user['mobile_no'] ?? ''));

$okMsg  = get_flash('q_ok');
$errMsg = get_flash('q_error');

/* ============================================================
 * PAGE UI
 * ============================================================ */
$complaintDisplay = '';
$cidUI = (int)($editingHeader['complaint_id'] ?? 0);

if ($cidUI > 0) {
    $stcno = $con->prepare("SELECT orderno FROM {$TABLE_COMPLAINT} WHERE id=? LIMIT 1");
    $stcno->bind_param('i', $cidUI);
    $stcno->execute();
    $rr = $stcno->get_result()->fetch_assoc();
    $stcno->close();
    $complaintDisplay = (string)($rr['orderno'] ?? '');
}

 
 
ob_start();
?>
<div class="master-wrap">
  <div class="headbar">
    <div>
      <h1 class="page-title"><?php echo h($menuMetaTitle); ?></h1>
      <div class="page-subtitle"><?php echo h($menuMetaRemark); ?></div>
    </div>
  </div>

  <?php if ($errMsg): ?><div class="alert danger"><?php echo h($errMsg); ?></div><?php endif; ?>
  <?php if ($okMsg): ?><div class="alert success"><?php echo h($okMsg); ?></div><?php endif; ?>

  <!-- POST-only navigation tools -->
  <!--div class="card" style="padding:14px 18px; margin:16px 0; background:#fff; border-radius:16px;">
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
      <form method="post" style="display:flex; gap:8px; align-items:flex-end;">
        <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
        <input type="hidden" name="mode" value="edit">
        <div class="field">
          <label>Load Quotation ID (Edit)</label>
          <input type="number" name="edit_id" class="inp" placeholder="Enter ID" style="width:180px;">
        </div>
        <button type="submit" class="btn secondary">Load</button>
      </form>

      <form method="post" style="display:flex; gap:8px; align-items:flex-end;">
        <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
        <input type="hidden" name="mode" value="prefill_from_complaint">
        <div class="field">
          <label>Prefill From Complaint ID</label>
          <input type="number" name="prefill_complaint_id" class="inp" placeholder="Complaint ID" style="width:180px;">
        </div>
        <button type="submit" class="btn secondary">Prefill</button>
      </form>

      <form method="post" style="margin-left:auto;">
        <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
        <input type="hidden" name="mode" value="new">
        <button type="submit" class="btn secondary">New</button>
      </form>
    </div>
    <div class="muted" style="margin-top:8px; font-size:12px;">Edit/Prefill is POST-only (no URL ids).</div>
  </div-->

  <div class="card" style="padding:24px 28px; margin:16px 0; background:#fff; border-radius:16px;">
    <h2 class="card-title" style="margin-bottom:18px;">Service Quotation</h2>

    <form method="post" id="quotation-form" autocomplete="off">
      <input type="hidden" name="quotation_form" value="1">
      <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">

      <!-- POST-only mode fields -->
      <input type="hidden" name="mode" value="save">
      <input type="hidden" name="edit_id" value="<?php echo (int)$editingHeader['id']; ?>">
      <input type="hidden" name="prefill_complaint_id" value="0">

      <input type="hidden" name="id" value="<?php echo (int)$editingHeader['id']; ?>">
      <input type="hidden" name="yrid" id="yrid_inp" value="<?php echo (int)($editingHeader['yrid'] ?? 0); ?>">
      <input type="hidden" name="billno" id="billno_inp" value="<?php echo h((string)($editingHeader['billno'] ?? '')); ?>">

      <input type="hidden" name="rows_json" id="rows_json" value="[]">
      <input type="hidden" name="terms_json" id="terms_json" value="[]">

      <div class="sq-grid">
        <div class="field">
          <label>Quotation Date<span class="req">*</span></label>
          <input type="text" name="q_date" class="inp datepick" value="<?php echo h($editingHeader['date']); ?>" placeholder="DD-MM-YYYY">
        </div>


      

       <div class="field">

  <label>Sub Company Name<span class="req">*</span></label>
  <select name="companyname" class="inp" required>
    <option value="">Select Sub Company</option>
    <?php foreach ($companyOptions as $co): ?>
      <option value="<?php echo h($co['name']); ?>"
        <?php echo ((string)($editingHeader['companyname'] ?? '') === $co['name']) ? 'selected' : ''; ?>>
        <?php echo h($co['name']); ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>

        <div class="field">
          <label>Customer<span class="req">*</span></label>
          <input type="text" id="customer_inp" name="customer" class="inp" value="<?php echo h((string)($editingHeader['customer'] ?? '')); ?>" placeholder="Type customer name...">
          <input type="hidden" id="custid_inp" name="custid" value="<?php echo (int)($editingHeader['custid'] ?? 0); ?>">
          <div class="muted" style="font-size:12px; margin-top:6px;">Customer will auto-pick from master.</div>
        </div>
        
 <?php if (!empty($editingHeader['complaint_id'])): ?>
    <div class="field">
      <label>Complaint No</label>
      <input type="text" class="inp" value="<?php echo h($complaintDisplay); ?>" readonly>
    </div>
  <?php endif; ?>

  <input type="hidden" name="complaint_id"
         value="<?php echo (int)($editingHeader['complaint_id'] ?? 0); ?>">

 

        <div class="field">
          <label>Quotation Type</label>
          <select name="qtype" id="qtype_inp" class="inp">
            <option value="0">Select</option>
            <?php foreach ($qtypeOptions as $qo): ?>
              <option value="<?php echo (int)$qo['id']; ?>" <?php echo ((int)($editingHeader['qtype'] ?? 0) === (int)$qo['id']) ? 'selected' : ''; ?>>
                <?php echo h($qo['label']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Title</label>
          <select name="title" id="title_inp" class="inp">
            <option value="">Select Title</option>
          </select>
          <div class="muted" style="font-size:12px; margin-top:6px;">Loaded from config by quotation type.</div>
        </div>

        <div class="field" >
          <label>Kind Attn</label>
          <input type="text" name="kindattn" class="inp" value="<?php echo h((string)($editingHeader['kindattn'] ?? '')); ?>" placeholder="Kind Attn">
        </div>
      </div>

      <div class="field" style="margin-top:12px;">
        <label>References</label>
        <textarea name="refe" class="inp" rows="2" style="resize:vertical;"><?php echo h((string)($editingHeader['refe'] ?? '')); ?></textarea>
      </div>

      <div style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; margin-top:12px;">
        <div class="field">
          <label>Subject</label>
          <textarea name="subject" id="subject_inp" class="inp" rows="3" style="resize:vertical;"><?php echo h((string)($editingHeader['subject'] ?? '')); ?></textarea>
        </div>
        <div class="field">
          <label>Description</label>
          <textarea name="descr" id="descr_inp" class="inp" rows="3" style="resize:vertical;"><?php echo h((string)($editingHeader['descr'] ?? '')); ?></textarea>
        </div>
      </div>

      <div class="field" style="margin-top:12px;">
        <label>Remark</label>
        <textarea name="remark" class="inp" rows="2" style="resize:vertical;"><?php echo h((string)($editingHeader['remark'] ?? '')); ?></textarea>
      </div>

      <!-- ITEMS -->
      <div style="margin-top:18px; border-top:1px solid #e5e7eb; padding-top:14px;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
          <strong>Quotation Items</strong>
          <button type="button" class="btn primary" onclick="openRowModal(-1)">+ Add Product</button>
        </div>

        <div class="table-wrap" style="margin-top:10px;">
          <table class="table" style="width:100%;">
            <thead style="position:sticky; top:0;">
            <tr>
  <th style="width:50px;">#</th>
  <th style="min-width:260px;">Product</th>
  <th style="width:110px;">HSN</th>
  <th>Description</th>
  <th style="width:90px;">Rate</th>
  <th style="width:80px;">Qty</th>
  <th style="width:90px;">Disc</th>
  <th style="width:90px;">Inst.Disc</th>
  <th style="width:130px;">Discounted Amt</th>
  <th style="width:90px;">GST%</th>
  <th style="width:120px;">Amt</th>
  <th style="width:140px;">Action</th>
</tr>


            </thead>
            <tbody id="grid-body"></tbody>
          </table>
        </div>

        <div class="muted" style="margin-top:6px; font-size:12px;">
          GST auto comes from product CGST% (split into CGST + SGST). You can edit GST%.
        </div>
      </div>

    <!-- charges grouped (like old UI) -->
<!-- TOTALS -->
<div class="totals-wrap" style="margin-top:18px; border-top:1px solid #e5e7eb; padding-top:14px;">
  <strong>Totals</strong>

  <!-- main totals grid (2 rows x 5 columns on desktop) -->
  <div class="totals-grid" style="margin-top:10px;">
    <div class="field">
      <label>Sub Total</label>
      <input type="text" name="subtotal" id="subtotal" class="inp" readonly tabindex="-1"
             value="<?php echo h((string)($editingHeader['subtotal'] ?? 0)); ?>">
    </div>

    <div class="field">
      <label>Discount Type</label>
      <select name="disctype" id="disctype" class="inp">
        <option value="0" <?php echo ((int)($editingHeader['disctype'] ?? 0)===0)?'selected':''; ?>>Amt</option>
        <option value="1" <?php echo ((int)($editingHeader['disctype'] ?? 0)===1)?'selected':''; ?>>Per</option>
      </select>
    </div>

    <div class="field">
      <label>Discount</label>
      <input type="number" step="0.01" name="discount" id="discount" class="inp"
             value="<?php echo h((string)($editingHeader['discount'] ?? 0)); ?>">
    </div>

    <div class="field">
  <label>Basic Total</label>
  <input type="text"
         name="basic_total"
         id="basic_total"
         class="inp"
         readonly
         value="<?php echo h((string)($editingHeader['basic_total'] ?? 0)); ?>">
</div>
<input type="hidden" name="discountamt" id="discountamt" value="0">


    <div class="field">
      <label>Total GST</label>
      <input type="text" name="gst" id="gst" class="inp" readonly
             value="<?php echo h((string)($editingHeader['gst'] ?? 0)); ?>">
    </div>

    <div class="field">
      <label>Basic Cost With GST</label>
      <input type="text" name="nettotal" id="nettotal" class="inp" readonly
             value="<?php echo h((string)($editingHeader['nettotal'] ?? $editingHeader['total2'] ?? 0)); ?>">
    </div>


  </div>

  <!-- charges (each row aligned like table, but responsive) -->
  <div class="charges-wrap" style="margin-top:14px;">
    <!-- Packing -->
    <div class="charge-row packing-row">

  <!-- LEFT TITLE -->
  <div class="charge-title">Packing &amp; Forwarding</div>

  <!-- RIGHT GRID -->
  <div class="charge-fields">

    <!-- ROW 1 -->
    <div class="mini">
      <div class="mini-lbl">Per %</div>
      <input type="number" step="0.01" name="packing_per" id="packing_per" class="inp"
             value="<?php echo h((string)($editingHeader['packing_per'] ?? 0)); ?>">
    </div>

    <div class="mini">
      <div class="mini-lbl">Amount</div>
      <input type="number" step="0.01" name="packing_amt" id="packing_amt" class="inp"
             value="<?php echo h((string)($editingHeader['packing_amt'] ?? 0)); ?>">
    </div>

    <!-- <div class="mini">
      <div class="mini-lbl">GST %</div>
      <input type="number" step="0.01" name="packing" id="packing" class="inp"
             value="<?php echo h((string)($editingHeader['packing'] ?? 0)); ?>">
    </div> -->

    <div class="mini">
  <div class="mini-lbl">GST %</div>
  <select name="packing" id="packing" class="inp">
    <option value="0">GST</option>
    <?php foreach ($gstSlabs as $gs): ?>
      <option value="<?php echo h($gs['name']); ?>"
        <?php echo ((string)($editingHeader['packing'] ?? '0') === (string)$gs['name']) ? 'selected' : ''; ?>>
        <?php echo h($gs['name']); ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>


    <div class="mini">
      <div class="mini-lbl"></div>
      <input type="text" name="packing_total" id="packing_total" class="inp" readonly
             value="<?php echo h((string)($editingHeader['packing_total'] ?? 0)); ?>">
    </div>
    <div class="mini">
      <div class="mini-lbl">Total</div>
      <input type="text" name="total2" id="total2" class="inp" readonly
             value="<?php echo h((string)($editingHeader['total2'] ?? 0)); ?>">
    </div>

    <!-- ROW 2 (aligned under row 1) -->
    <div class="mini">
      <div class="mini-lbl">TCS %</div>
      <input type="number" step="0.01" name="tcs" id="tcs" class="inp"
             value="<?php echo h((string)($editingHeader['tcs'] ?? 0)); ?>">
    </div>

    <div class="mini">
      <div class="mini-lbl">TCS Amt</div>
      <input type="text" name="tcsamt" id="tcsamt" class="inp" readonly
             value="<?php echo h((string)($editingHeader['tcsamt'] ?? 0)); ?>">
    </div>

    <!-- EMPTY CELL under GST % -->
    <!--<div class="mini"></div>-->

   <div class="mini">
      <div class="mini-lbl">Total with TCS</div>
       <input type="text" name="total3" id="total3" class="inp" readonly
             value="<?php echo h((string)($editingHeader['total3'] ?? 0)); ?>">
    </div>

  </div>
</div>


    <!-- Transport -->
    <div class="charge-row transport-row">
      <div class="charge-title">Transport / Courier</div>

      <div class="mini">
        <div class="mini-lbl">Per %</div>
        <input type="number" step="0.01" name="trans_per" id="trans_per" class="inp"
               value="<?php echo h((string)($editingHeader['trans_per'] ?? 0)); ?>">
      </div>
      <div class="mini">
        <div class="mini-lbl">Amount</div>
        <input type="number" step="0.01" name="trans_amt" id="trans_amt" class="inp"
               value="<?php echo h((string)($editingHeader['trans_amt'] ?? 0)); ?>">
      </div>
      <!--<div class="mini">-->
      <!--  <div class="mini-lbl">GST %</div>-->
      <!--  <input type="number" step="0.01" name="trans" id="trans" class="inp"-->
      <!--         value="<?php echo h((string)($editingHeader['trans'] ?? 0)); ?>">-->
      <!--</div>-->
          <div class="mini">
            <div class="mini-lbl">GST %</div>
            <select name="trans" id="trans" class="inp">
              <option value="0">GST</option>
              <?php foreach ($gstSlabs as $gs): ?>
                <option value="<?php echo h($gs['name']); ?>"
                  <?php echo ((string)($editingHeader['trans'] ?? '0') === (string)$gs['name']) ? 'selected' : ''; ?>>
                  <?php echo h($gs['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
        </div>

      <div class="mini">
        <div class="mini-lbl">Total</div>
        <input type="text" name="trans_total" id="trans_total" class="inp" readonly
               value="<?php echo h((string)($editingHeader['trans_total'] ?? 0)); ?>">
      </div>

      <div class="mini">
        <div class="mini-lbl">From</div>
        <input type="text" name="transfromdate" id="transfromdate" class="inp datepick" placeholder="DD-MM-YYYY"
               value="<?php echo h((string)($editingHeader['transfromdate'] ?? '')); ?>">
      </div>
      <div class="mini">
        <div class="mini-lbl">To</div>
        <input type="text" name="transtodate" id="transtodate" class="inp datepick" placeholder="DD-MM-YYYY"
               value="<?php echo h((string)($editingHeader['transtodate'] ?? '')); ?>">
      </div>
      <div class="mini">
        <div class="mini-lbl">Total + Transport</div>
        <input type="text" name="total4" id="total4" class="inp" readonly
               value="<?php echo h((string)($editingHeader['total4'] ?? 0)); ?>">
      </div>
    </div>

    <!-- Insurance -->
    <div class="charge-row insurance-row">
      <div class="charge-title">Insurance (on Total + Transport)</div>

      <div class="mini">
        <div class="mini-lbl">Per %</div>
        <input type="number" step="0.01" name="insurance_per" id="insurance_per" class="inp"
               value="<?php echo h((string)($editingHeader['insurance_per'] ?? 0)); ?>">
      </div>
      <div class="mini">
        <div class="mini-lbl">Amount</div>
        <input type="number" step="0.01" name="insurance_amt" id="insurance_amt" class="inp"
               value="<?php echo h((string)($editingHeader['insurance_amt'] ?? 0)); ?>">
      </div>
      <!-- <div class="mini">
        <div class="mini-lbl">GST %</div>
        <input type="number" step="0.01" name="insurance" id="insurance" class="inp"
               value="<?php echo h((string)($editingHeader['insurance'] ?? 0)); ?>">
      </div> -->
      <div class="mini">
        <div class="mini-lbl">GST %</div>
          <select name="insurance" id="insurance" class="inp">
            <option value="0">GST</option>
              <?php foreach ($gstSlabs as $gs): ?>
                <option value="<?php echo h($gs['name']); ?>"
                <?php echo ((string)($editingHeader['insurance'] ?? '0') === (string)$gs['name']) ? 'selected' : ''; ?>>
                <?php echo h($gs['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
      </div>

      <div class="mini">
       <div class="mini-lbl">Total</div>
       <input type="text" name="insurance_total" id="insurance_total" class="inp" readonly
              value="<?php echo h((string)($editingHeader['insurance_total'] ?? 0)); ?>">
      </div>
      <div class="mini">
        <div class="mini-lbl">Final Total</div>
        <input type="text" name="total5" id="total5" class="inp" readonly
               value="<?php echo h((string)($editingHeader['total5'] ?? 0)); ?>">
      </div>

    </div>

    <!-- Commission -->
    <div class="charge-row commission-row">
      <div class="charge-title">Erection &amp; Commissioning</div>

      <div class="mini">
        <div class="mini-lbl">Per %</div>
        <input type="number" step="0.01" name="commission_per" id="commission_per" class="inp"
               value="<?php echo h((string)($editingHeader['commission_per'] ?? 0)); ?>">
      </div>
      <div class="mini">
        <div class="mini-lbl">Amount</div>
        <input type="number" step="0.01" name="commission_amt" id="commission_amt" class="inp"
               value="<?php echo h((string)($editingHeader['commission_amt'] ?? 0)); ?>">
      </div>
      <!-- <div class="mini">
        <div class="mini-lbl">GST %</div>
        <input type="number" step="0.01" name="commission" id="commission" class="inp"
               value="<?php echo h((string)($editingHeader['commission'] ?? 0)); ?>">
      </div> -->
      <div class="mini">
        <div class="mini-lbl">GST %</div>
          <select name="commission" id="commission" class="inp">
            <option value="0">GST</option>
            <?php foreach ($gstSlabs as $gs): ?>
              <option value="<?php echo h($gs['name']); ?>"
                <?php echo ((string)($editingHeader['commission'] ?? '0') === (string)$gs['name']) ? 'selected' : ''; ?>>
                <?php echo h($gs['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
      </div>`

      <div class="mini">
        <div class="mini-lbl">Total</div>
        <input type="text" name="commission_total" id="commission_total" class="inp" readonly
               value="<?php echo h((string)($editingHeader['commission_total'] ?? 0)); ?>">
      </div>

      <!-- <div class="mini">-->
      <!--  <div class="mini-lbl final-grand-lbl">Final Total</div>-->
      <!--  <input type="text" name="total5" id="total5" class="inp" readonly-->
      <!--         value="<?php echo h((string)($editingHeader['total5'] ?? 0)); ?>">-->
      <!--</div> -->

      <div class="mini">
        <div class="mini-lbl final-grand-lbl">Grand Total</div>
        <input type="text" name="total" id="total" class="inp" readonly
               value="<?php echo h((string)($editingHeader['total'] ?? 0)); ?>">
      </div>
    </div>

    <div class="muted" style="font-size:12px; margin-top:8px;">
      Totals auto-calc from grid + charges. You can change charges; totals update.
    </div>
  </div>
</div>

<!-- CSS for Totals + Charges alignment -->



      <!-- TERMS -->
      <div style="margin-top:18px; border-top:1px solid #e5e7eb; padding-top:14px;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
          <strong>Terms & Conditions</strong>
          <button type="button" class="btn primary" onclick="openTermModal()">+ Add Terms</button>
        </div>

        <div id="terms-wrap" style="margin-top:10px;"></div>
      </div>

      <div style="text-align:center; margin-top:20px;">
        <button type="submit" class="btn success" style="min-width:180px;">Save</button>
        <button type="button" class="btn secondary" onclick="window.location.href='service_quotation.php';" style="margin-left:8px;">Clear</button>
      </div>
    </form>
  </div>
</div>

<!-- PRODUCT MODAL -->
<div id="row-modal" class="modal hidden">
  <div class="modal-backdrop" onclick="closeRowModal()"></div>
  <div class="modal-dialog">
    <h3 style="margin:0 0 10px 0;">Add / Edit Product</h3>

    <input type="hidden" id="rm_index" value="-1">
    <input type="hidden" id="rm_propid" value="0">

    <div class="field">
      <label>Product<span class="req">*</span></label>
      <input type="text" id="rm_product" class="inp" placeholder="Search product...">
      <div class="muted" style="font-size:12px; margin-top:6px;">Select product from suggestions.</div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-top:12px;">
      <div class="field">
        <label>HSN</label>
        <input type="text" id="rm_hsn" class="inp">
      </div>
      <div class="field">
        <label>Rate</label>
        <input type="number" step="0.01" id="rm_rate" class="inp" value="0">
      </div>
      <div class="field">
        <label>Qty</label>
        <input type="number" step="0.01" id="rm_qty" class="inp" value="1">
      </div>


      <div class="field" style="grid-column:1 / span 2;">
        <label>Description</label>
        <input type="text" id="rm_desc" class="inp">
      </div>
      <div class="field">
        <label>Disc in % </label>
        <input type="number" step="0.01" id="rm_disc" class="inp" value="0">
      </div>
      <div class="field">
        <label>Discount in Amount</label>
        <input type="number" step="0.01" id="rm_inst" class="inp" value="0">
      </div>
    </div>
    
     <div class="form-group">
    <label>Discounted Amount</label>
    <input type="text" id="rm_discounted_amt" class="inp" value="0" readonly>
    <small class="muted">Discounted Amt before GST</small>
  </div>
  
        <div class="field">
        <label>GST%</label>
     <select id="rm_gst" class="inp">
  <option value="0">Gst</option>
  <?php foreach ($gstSlabs as $gs): ?>
    <option value="<?php echo h($gs['name']); ?>"><?php echo h($gs['name']); ?></option>
  <?php endforeach; ?>
</select>

      </div>

  <div class="form-group">
    <label>Final Amount</label>
    <input type="text" id="rm_final_amt" class="inp" value="0" readonly>
    <small class="muted">Discounted Amount + GST</small>
  </div>



    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px;">
      <button type="button" class="btn secondary" onclick="closeRowModal()">Cancel</button>
      <button type="button" class="btn success" onclick="saveRowModal()">Save</button>
    </div>
  </div>
</div>

<!-- TERMS MODAL (Quill Rich Text) -->
<div id="term-modal" class="modal hidden">
  <div class="modal-backdrop" onclick="closeTermModal()"></div>
  <div class="modal-dialog">
    <h3 style="margin:0 0 10px 0;">Add Terms</h3>

    <div class="field">
      <label>Term Type<span class="req">*</span></label>
      <select id="tm-type" class="inp">
        <option value="">Select Type</option>
        <?php foreach ($termTypes as $tt): ?>
          <option value="<?php echo (int)$tt['id']; ?>"><?php echo h($tt['type']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="margin-top:10px;">
      <label>Title (auto)</label>
      <input type="text" id="tm-title" class="inp" readonly>  
    </div>

    <div class="field" style="margin-top:10px;">
      <label>Description (editable)</label>
      <div id="tm-quill" style="background:#fff;"></div>
      <div class="muted" style="font-size:12px; margin-top:6px;">
        Loaded from master by type. You can edit before saving into quotation.
      </div>
    </div>

    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px;">
      <button type="button" class="btn secondary" onclick="closeTermModal()">Cancel</button>
      <button type="button" class="btn success" onclick="saveTerm()">Save</button>
    </div>
  </div>
</div>

<style>
.req{ color:#ef4444; margin-left:2px; }
.muted{ color:#6b7280; }

/* FORM GRID */
/* FORM GRID (2 column) */
.sq-grid{
  display:grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap:16px;
  align-items:start;
}
@media (max-width:900px){ .sq-grid{ grid-template-columns:1fr; } }

@media (max-width:1100px){ .sq-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width:640px){ .sq-grid{ grid-template-columns:1fr; } }

/* MODAL FIX */
/* ===== FORCE MODAL OVERLAY (override ui.css) ===== */
.hidden { display: none !important; }

.modal{
  position: fixed !important;
  inset: 0 !important;
  z-index: 99990 !important;
  display: flex !important;
  align-items: flex-start !important;
  justify-content: center !important;
  padding: 18px !important;
  overflow: auto !important;
  -webkit-overflow-scrolling: touch;
}

.modal.hidden{ display:none !important; }

.modal-backdrop{
  position: fixed !important;
  inset: 0 !important;
  background: rgba(0,0,0,.45) !important;
  z-index: 99991 !important;
}

.modal-dialog{
  position: relative !important;
  z-index: 99992 !important;
  width: min(920px, calc(100vw - 36px)) !important;
  margin: 0 auto !important;
  background: #fff !important;
  border-radius: 16px !important;
  padding: 16px !important;
  max-height: calc(100vh - 36px) !important;
  overflow: auto !important;
}


/* modal card must not resize based on content */
#PRODUCT_MODAL_ID .modal-card,
.modal-card{
  width: min(980px, calc(100vw - 48px));
  box-sizing: border-box;
}

/* IMPORTANT for flex/grid children to not expand */
.modal-card *{
  box-sizing: border-box;
}

/* inputs must be fixed-width (not auto-sizing) */
.modal-card input,
.modal-card select,
.modal-card textarea{
  width: 100%;
  min-width: 0;
}

/* if you use flex rows for fields */
.modal-card .row,
.modal-card .grid-row{
  min-width: 0;
}

.modal-card .col,
.modal-card .field{
  min-width: 0;
}

/* Quill inside modal */
#tm-quill{ height:320px; background:#fff; }
#tm-quill .ql-editor{ min-height:280px; }


/* READONLY */
input[readonly]{ background:#eaeaea; color:#000000; cursor:not-allowed; }



/* Totals: responsive 4 → 3 → 2 → 1 columns automatically */
.totals-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
  gap:12px 14px;
  align-items:end;
}

@media (max-width:1100px){
  .totals-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
}
@media (max-width:640px){
  .totals-grid{ grid-template-columns:1fr; }
}
.charges-wrap{
  margin-top:14px;
  padding:12px 14px;
  border:1px solid #e5e7eb;
  border-radius:12px;
  background:#fafafa;
}

.charge-row{
  display:grid;
  grid-template-columns:220px 1fr;
  gap:12px;
  align-items:start;
  padding:12px 0;
  border-top:1px dashed #e5e7eb;
}
.charge-row:first-child{ border-top:none; padding-top:0; }

.charge-title{
font-size:13px;
  color:#111827;
  padding-top:10px;
}

.charge-fields{
  display:grid;
  grid-template-columns:repeat(4, minmax(160px, 1fr));
  gap:10px 12px;
}

/* Transport row has 7 minis */
.charge-row.transport .charge-fields{
  grid-template-columns:repeat(7, minmax(140px, 1fr));
}

/* Last row has 6 minis */
.charge-row.ec .charge-fields{
  grid-template-columns:repeat(6, minmax(140px, 1fr));
}

.mini-lbl{ font-size:12px; color:#6b7280; margin-bottom:4px; }

@media (max-width:1100px){
  .charge-row{ grid-template-columns:1fr; }
  .charge-title{ padding-top:0; }
  .charge-fields{ grid-template-columns:repeat(2, minmax(160px, 1fr)); }
  .charge-row.transport .charge-fields{ grid-template-columns:repeat(2, minmax(160px, 1fr)); }
  .charge-row.ec .charge-fields{ grid-template-columns:repeat(2, minmax(160px, 1fr)); }
}



  .totals-grid{
    display:grid;
    grid-template-columns:repeat(5, minmax(160px, 1fr));
    gap:10px 12px;
  }
  .totals-grid .field label{
    display:block;
    font-size:12px;
    color:#6b7280;
    margin-bottom:4px;
  }

  .charges-wrap{
    border:1px solid #e5e7eb;
    border-radius:12px;
    padding:10px 12px;
    background:#fff;
  }

  .charge-row{
    display:grid;
    grid-template-columns: 220px repeat(4, minmax(120px, 1fr));
    gap:10px 12px;
    padding:10px 0;
    border-top:1px dashed #e5e7eb;
    align-items:end;
  }
  .charge-row:first-child{ border-top:0; }

  .charge-title{
    font-weight:600;
    padding-top:6px;
  }

  .mini .mini-lbl{
    font-size:12px;
    color:#6b7280;
    margin-bottom:4px;
  }

  /* Transport needs 7 mini columns => title + 7 */
  .transport-row{
    grid-template-columns: 220px repeat(7, minmax(110px, 1fr));
  }
  /* Insurance row – force single line */
.insurance-row{
    grid-template-columns: 220px repeat(7, minmax(110px, 1fr));
}

  /* Commission row includes Final Total + Grand Total => title + 6 */
  .commission-row{
    grid-template-columns: 300px repeat(6, minmax(120px, 1fr));
  }

  /* responsive */
  @media (max-width:1200px){
    .totals-grid{ grid-template-columns:repeat(3, minmax(160px, 1fr)); }
    .charge-row{ grid-template-columns: 180px repeat(2, minmax(140px, 1fr)); }
    .transport-row{ grid-template-columns: 180px repeat(2, minmax(140px, 1fr)); }
    .insurance-row{ grid-template-columns: 180px repeat(2, minmax(140px, 1fr)); }
    .commission-row{ grid-template-columns: 180px repeat(2, minmax(140px, 1fr)); }
  }
  @media (max-width:700px){
    .totals-grid{ grid-template-columns:1fr; }
    .charge-row, .transport-row, .commission-row{ grid-template-columns:1fr; }
    .charge-title{ padding-top:0; }
  }


/* Highlight Final Total + Grand Total */
#total{
  font-size: 18px;
  font-weight: 800;
  padding: 10px 12px;
  border-width: 2px;
}
.final-grand-lbl{
  font-size: 13px;
  font-weight: 700;
  color: #111827;
}

.ui-autocomplete{
  z-index: 999999 !important;
}


/* Remove number input arrows - Chrome, Edge, Safari */
input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Remove number input arrows - Firefox */
input[type=number] {
    -moz-appearance: textfield;
}



/* ===== STOCK ISSUE SUGGEST DROPDOWN ===== */
.suggest-box{
  position:absolute;
  left:0;
  right:0;
  top:100%;
  margin-top:4px;
  background:#fff;
  border:1px solid #e5e7eb;
  border-radius:10px;
  box-shadow:0 10px 30px rgba(0,0,0,.12);
  max-height:240px;
  overflow:auto;
  z-index:99999;
}

.suggest-item{
  padding:10px 12px;
  cursor:pointer;
  border-bottom:1px solid #f1f5f9;
}

.suggest-item:last-child{
  border-bottom:none;
}

.suggest-item.active{
  background:#2a93fc;
}
#row-modal .field{
  position: relative;
}

/* ✅ ONLY Packing & Forwarding title alignment */
.packing-row .charge-title{
  align-self: flex-start;
  padding-top: 26px;   /* aligns with first input row */
}



</style>




<!-- jQuery UI -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<!-- Quill -->
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>

<script>
console.log("SQ script loaded");
console.log("openRowModal before define:", typeof openRowModal);

/* ===============================
   Complaint Quotation JS (POST-only AJAX)
   ===============================
   Grid row rules (stable):
   rowGross = rate * qty
   rowDiscount = discount + instantdisc
   rowDiscounted = rowGross - rowDiscount
   rowGSTAmt = rowDiscounted * gst% / 100
   rowAmt = rowDiscounted + rowGSTAmt

   Totals:
   subtotal = sum(rowDiscounted)
   gst = sum(rowGSTAmt)
   base = subtotal + gst
   header discount (disctype) applied on base
   charges (packing/trans/insurance/commission): percent or amount; then charge GST%
   tcs applied near end (after charges)
   final total = baseAfterDiscount + charges + tcs
================================ */

let gridRows = [];
let termRows = [];
let quill = null;

const existingGrid  = <?php echo json_encode($editingGrid ?: []); ?>;
const existingTerms = <?php echo json_encode($editingTerms ?: []); ?>;

const EXISTING_TITLE_ID = <?php echo json_encode((int)($editingHeader['title'] ?? 0)); ?>;
const EXISTING_QTYPE = <?php echo json_encode((int)($editingHeader['qtype'] ?? 0)); ?>;

const PRODUCT_MODAL_ID = 'row-modal';

function num(v){ v = parseFloat(v); return isNaN(v) ? 0 : v; }
function money(v){ return (num(v)).toFixed(2); }
function escapeHtml(str){
  return String(str ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function postAjax(payload){
  return fetch('service_quotation.php', {
    method: 'POST',
    headers: { 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
    body: new URLSearchParams(payload).toString()
  }).then(r=>r.json());
}

function calcRow(r){
  const rate = num(r.rate),
        qty  = num(r.qty),
        disc = num(r.discount),      // %
        inst = num(r.instantdisc),   // amount
        gst  = num(r.gst);

  const gross = rate * qty;
  const discAmt = gross * (disc / 100);
  const net = Math.max(0, gross - (discAmt + inst));
  const gstamt = gst > 0 ? (net * gst / 100) : 0;
  const amt = net + gstamt;

  r._gross = gross;
  r._net = net;
  r._gstamt = gstamt;
  r._amt = amt;

  return r;
}




/* ---------- GRID RENDER ---------- */
function renderGrid(){
  const tb = document.getElementById('grid-body');
  tb.innerHTML = '';

  if(!gridRows || gridRows.length === 0){
    const tr=document.createElement('tr');
    tr.innerHTML = `<td colspan="12" class="muted" style="padding:12px;">No items added yet.</td>`;
    tb.appendChild(tr);
    computeTotals();
    return;
  }

  gridRows.forEach((r, idx)=>{
    calcRow(r);
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="muted">${idx+1}</td>
      <td>${escapeHtml(r.product_label||'')}</td>
      <td>${escapeHtml(r.hsncode||'')}</td>
      <td>${escapeHtml(r.description||'')}</td>
      <td>${money(r.rate)}</td>
      <td>${money(r.qty)}</td>
      <td>${money(r.discount)}</td>
      <td>${money(r.instantdisc)}</td>
      <td><strong>${money(r._net || 0)}</strong></td>
      <td>${money(r.gst)}</td>
      <td><strong>${money(Math.round(r._amt || 0))}</strong></td>
      <td>
        <button type="button" class="btn secondary" onclick="openRowModal(${idx})">Edit</button>
        <button type="button" class="btn danger" onclick="deleteRow(${idx})">Del</button>
      </td>
    `;
    tb.appendChild(tr);
  });

  computeTotals();
}
function deleteRow(i){ gridRows.splice(i,1); renderGrid(); }

/* ---------- TERMS RENDER ---------- */
function renderTerms(){
  const wrap = document.getElementById('terms-wrap');
  wrap.innerHTML = '';

  if(!termRows || termRows.length === 0){
    wrap.innerHTML = `<div class="muted" style="font-size:12px;">No terms added yet.</div>`;
    return;
  }

  termRows.forEach((t, idx)=>{
    const div = document.createElement('div');
    div.className = 'card';
    div.style.cssText = 'padding:12px 14px; margin-bottom:8px; border-radius:12px; background:#fafafa;';
    div.innerHTML = `
      <div style="display:flex; justify-content:space-between; gap:10px;">
        <div style="flex:1;">
          <div><strong>${escapeHtml(t.title||'')}</strong> <span class="muted">[Type: ${escapeHtml(t.type||'')}]</span></div>
          <div class="muted" style="font-size:12px; margin-top:6px;">${t.description||''}</div>
        </div>
        <div>
          <button type="button" class="btn danger" onclick="deleteTerm(${idx})">Delete</button>
        </div>
      </div>
    `;
    wrap.appendChild(div);
  });
}
function deleteTerm(i){ termRows.splice(i,1); renderTerms(); }






function updateRowModalPreview(){
  const rate = n(document.getElementById('rm_rate')?.value);
  const qty  = n(document.getElementById('rm_qty')?.value);
  let disc   = n(document.getElementById('rm_disc')?.value);
  let inst   = n(document.getElementById('rm_inst')?.value);
  const gstp = n(document.getElementById('rm_gst')?.value);

  disc = Math.max(0, disc);
  inst = Math.max(0, inst);

  const gross = rate * qty;

  // ✅ CONVERT DISCOUNT % TO AMOUNT
  const discAmt = gross * (disc / 100);

  let discounted = gross - (discAmt + inst);
  if (discounted < 0) discounted = 0;

  const gstAmt = discounted * (gstp / 100);
  const finalAmt = discounted + gstAmt;

  const elDiscAmt = document.getElementById('rm_discounted_amt');
  if (elDiscAmt) elDiscAmt.value = money(discounted);

  const elFinal = document.getElementById('rm_final_amt');
  if (elFinal) elFinal.value = money(finalAmt);

  const prev = document.getElementById('rm_amt');
  if (prev) prev.textContent = money(finalAmt);
}














/* ---------- PRODUCT MODAL ---------- */
function openRowModal(index){
  // show modal first
  document.getElementById(PRODUCT_MODAL_ID).classList.remove('hidden');

  // set index
  document.getElementById('rm_index').value = (index ?? -1);

  // get row
  const r = (index >= 0) ? gridRows[index] : {
    propid:0, product_label:'', hsncode:'', description:'',
    rate:0, qty:1, discount:0, instantdisc:0, gst:0
  };

  // fill modal inputs
  document.getElementById('rm_propid').value  = r.propid || 0;
  document.getElementById('rm_product').value = r.product_label || '';
  document.getElementById('rm_hsn').value     = r.hsncode || '';
  document.getElementById('rm_desc').value    = r.description || '';
  document.getElementById('rm_rate').value    = r.rate || 0;
  document.getElementById('rm_qty').value     = r.qty || 1;
  document.getElementById('rm_disc').value    = r.discount || 0;
  document.getElementById('rm_inst').value    = r.instantdisc || 0;
  document.getElementById('rm_gst').value     = (r.gst || 0);

  //✅ IMPORTANT: bind autocomplete AFTER modal is visible + inputs are filled
  setTimeout(() => {
    if (typeof initProductAutocomplete === 'function') {
      initProductAutocomplete();
    }
    if (typeof updateRowModalPreview === 'function') {
      updateRowModalPreview();
    }
  }, 50);
}
 updateRowModalPreview();

function closeRowModal(){ document.getElementById(PRODUCT_MODAL_ID).classList.add('hidden'); }

function n(v){ v = parseFloat(v); return isNaN(v) ? 0 : v; }
function money(v){ return (Math.round((n(v) + Number.EPSILON) * 100) / 100).toFixed(2); }


function saveRowModal(){
  const idx = parseInt(document.getElementById('rm_index').value,10);
  const propid = parseInt(document.getElementById('rm_propid').value,10) || 0;

  const product_label = (document.getElementById('rm_product').value || '').trim();
  const hsncode = (document.getElementById('rm_hsn').value || '').trim();
  const description = (document.getElementById('rm_desc').value || '').trim();

  if(!product_label){ alert('Select product.'); return; }

  const row = {
  propid,
  product_label,
  hsncode,
  description,

  rate: num(document.getElementById('rm_rate').value),
  qty: num(document.getElementById('rm_qty').value),
  discount: num(document.getElementById('rm_disc').value),
  instantdisc: num(document.getElementById('rm_inst').value),
  gst: num(document.getElementById('rm_gst').value),

  // ✅ STORE MODAL VALUES
  _net: n(document.getElementById('rm_discounted_amt').value),
  _amt: n(document.getElementById('rm_final_amt').value),

  sequence: (idx >= 0 ? (idx+1) : (gridRows.length+1))
};

  if(idx >= 0) gridRows[idx] = row;
  else gridRows.push(row);

  closeRowModal();
  renderGrid();
}

/* product autocomplete (POST ajax) */
// function initProductAutocomplete(){
//   if(!window.jQuery) return;
//   const $inp = jQuery('#rm_product');
//   if ($inp.data('ui-autocomplete')) $inp.autocomplete('destroy');

//   $inp.autocomplete({
//     minLength: 2,
//     appendTo: '#' + PRODUCT_MODAL_ID + ' .modal-dialog',
//     position: { my:'left top', at:'left bottom', collision:'flip' },
//     source: function(req, resp){
//       jQuery.ajax({
//         url: 'service_quotation.php',
//         method: 'POST',
//         dataType: 'json',
//         data: { ajax:'product_lookup', term: req.term },
//         success: function(data){ resp(data || []); },
//         error: function(){ resp([]); }
//       });
//     },
//     focus: function(e, ui){ e.preventDefault(); $inp.val(ui.item.label || ''); },
//     select: function(e, ui){
//       e.preventDefault();
//       document.getElementById('rm_propid').value = ui.item.id || 0;
//       document.getElementById('rm_product').value = ui.item.label || '';
//       document.getElementById('rm_hsn').value     = ui.item.hsncode || '';
//       document.getElementById('rm_gst').value     = ui.item.gst || 0;
//       updateRowModalPreview();
//       return false;
//     }
//   });
// }

/* ---------- TERMS MODAL ---------- */
function openTermModal(){
  document.getElementById('term-modal').classList.remove('hidden');
  document.getElementById('tm-type').value = '';
  document.getElementById('tm-title').value = '';
  if(!quill){
    quill = new Quill('#tm-quill', {
      theme: 'snow',
      modules: { toolbar: [
        [{ header: [1,2,3,false] }],
        ['bold','italic','underline'],
        [{ list:'ordered' }, { list:'bullet' }],
        ['link'],
        ['clean']
      ]}
    });
  }
  quill.setContents([]);
}
function closeTermModal(){ document.getElementById('term-modal').classList.add('hidden'); }

function saveTerm(){
  const typeId = document.getElementById('tm-type').value;
  const title  = (document.getElementById('tm-title').value || '').trim();
  const descHtml = quill ? quill.root.innerHTML : '';

  if(!typeId){ alert('Select Term Type'); return; }
  if(!title){ alert('Master title not found for this type. Configure jos_erp_servicetermsconditions.'); return; }

  termRows.push({ type: typeId, title: title, description: descHtml });
  closeTermModal();
  renderTerms();
}

/* ---------- CUSTOMER AUTOCOMPLETE (POST ajax) ---------- */
function initCustomerAutocomplete(){
  if(!window.jQuery) return;

  jQuery('#customer_inp').autocomplete({
    minLength: 2,
    source: function(req, resp){
      jQuery.ajax({
        url: 'service_quotation.php',
        method: 'POST',
        dataType: 'json',
        data: { ajax:'customer_lookup', term:req.term },
        success: function(data){ resp(data || []); },
        error: function(){ resp([]); }
      });
    },
    select: function(e, ui){
      jQuery('#custid_inp').val(ui.item.id || 0);
      jQuery('#customer_inp').val(ui.item.name || ui.item.label || '');
      return false;
    },
    change: function(){
      if(!jQuery('#customer_inp').val().trim()){
        jQuery('#custid_inp').val(0);
      }
    }
  });
}

/* =========================
 * ✅ CHANGED PARTS ONLY (JS)
 * ========================= */
const rm_product = document.getElementById('rm_product');
const rm_propid  = document.getElementById('rm_propid');
const rm_hsn     = document.getElementById('rm_hsn');
const rm_gst     = document.getElementById('rm_gst');



/* ===== STOCK ISSUE PRODUCT SUGGEST (WITH KEYS) ===== */
let suggestBox=null, suggestItems=[], activeIndex=-1;

function closeSuggest(){
  if(suggestBox){ suggestBox.remove(); suggestBox=null; }
  suggestItems=[]; activeIndex=-1;
}

function renderSuggest(items){
  closeSuggest();
  const host=document.getElementById('rm_product').parentElement;

  suggestBox=document.createElement('div');
  suggestBox.className='suggest-box';

  items.forEach((it,i)=>{
    const d=document.createElement('div');
    d.className='suggest-item';
    d.textContent=it.label;
    d.onclick=()=>selectProduct(it);
    suggestBox.appendChild(d);
  });

  suggestItems=[...suggestBox.children];
  host.appendChild(suggestBox);
}

function highlight(i){
  suggestItems.forEach(x=>x.classList.remove('active'));
  if(suggestItems[i]){
    suggestItems[i].classList.add('active');
    suggestItems[i].scrollIntoView({block:'nearest'});
  }
}

function selectProduct(it){
  rm_product.value=it.label;
  rm_propid.value=it.id;
  rm_hsn.value=it.hsncode||'';
  rm_gst.value=it.gst||0;
  closeSuggest();
  updateRowModalPreview();
}

/* INPUT */
rm_product.addEventListener('input',()=>{
  const q=rm_product.value.trim();
  rm_propid.value=0;
  if(q.length<2){ closeSuggest(); return; }

  fetch('service_quotation.php',{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams({ajax:'product_lookup',term:q})
  })
  .then(r=>r.json())
  .then(d=>d?.length?renderSuggest(d):closeSuggest());
});

/* KEYBOARD */
rm_product.addEventListener('keydown',e=>{
  if(!suggestBox) return;

  if(e.key==='ArrowDown'){
    e.preventDefault();
    activeIndex=Math.min(activeIndex+1,suggestItems.length-1);
    highlight(activeIndex);
  }

  if(e.key==='ArrowUp'){
    e.preventDefault();
    activeIndex=Math.max(activeIndex-1,0);
    highlight(activeIndex);
  }

  if(e.key==='Enter' && activeIndex>=0){
    e.preventDefault();
    suggestItems[activeIndex].click();
  }

  if(e.key==='Escape') closeSuggest();
});

/* OUTSIDE CLICK */
document.addEventListener('click',e=>{
  if(!suggestBox) return;
  if(e.target===rm_product) return;
  if(suggestBox.contains(e.target)) return;
  closeSuggest();
});











/* --------------------------------
 * (A) qtype -> load titles (value = ID, text = NAME)
 * -------------------------------- */
function loadTitlesForQType(qtype, selectedTitleId){
  const sel = document.getElementById('title_inp');
  sel.innerHTML = '<option value="">Loading...</option>';

  if(!qtype || qtype <= 0){
    sel.innerHTML = '<option value="">Select Title</option>';
    return;
  }

  postAjax({ ajax:'service_title', qtype: qtype })
    .then(list => {
      let html = '<option value="">Select Title</option>';
      (list || []).forEach(r => {
        const id = String(r.id || '');
        const nm = escapeHtml(r.name || '');
        const selAttr = (selectedTitleId && String(selectedTitleId) === id) ? 'selected' : '';
        html += `<option value="${id}" ${selAttr}>${nm}</option>`;
      });
      sel.innerHTML = html;

      if(sel.value){
        loadSubjectByTitleId(sel.value);
      }
    })
    .catch(() => {
      sel.innerHTML = '<option value="">Select Title</option>';
    });
}

/* --------------------------------
 * (B) title_id -> load subject/description
 * -------------------------------- */
function loadSubjectByTitleId(titleId){
  const qtypeVal = document.getElementById('qtype_inp')?.value || 0;

  if(!qtypeVal || !titleId){
    document.getElementById('subject_inp').value = '';
    document.getElementById('descr_inp').value = '';
    return;
  }

  postAjax({
    ajax: 'service_subject',
    qtype: qtypeVal,
    title_id: titleId     // ✅ send id, not text
  })
  .then(j=>{
    if(j && j.ok){
      document.getElementById('subject_inp').value = j.subject || '';
      document.getElementById('descr_inp').value   = j.description || '';
    }
  })
  .catch(()=>{});
}

/* --------------------------------
 * (C) Title change event -> use ID
 * -------------------------------- */
const titleEl2 = document.getElementById('title_inp');
if(titleEl2){
  titleEl2.addEventListener('change', function(){
    loadSubjectByTitleId(this.value); // ✅ correct
  });
}


/* --------------------------------
 * (D) Existing value in EDIT mode:
 * - now EXISTING_TITLE_ID should be saved/loaded as ID
 * -------------------------------- */
// ✅ replace your EXISTING_TITLE const with this:
//const EXISTING_TITLE_ID = <?php echo json_encode((int)($editingHeader['title'] ?? 0)); ?>;


// ✅ and init titles like:
if(EXISTING_QTYPE && EXISTING_QTYPE > 0){
  loadTitlesForQType(EXISTING_QTYPE, EXISTING_TITLE_ID || 0);
}


/* ---------- TOTALS CALC ---------- */
function val(id){ return num(document.getElementById(id)?.value); }
// function setv(id, v){ const el=document.getElementById(id); if(el) el.value = money(v); }
function setv(id, v){
  const el = document.getElementById(id);
  if(!el) return;

  // ❌ Agar user manually type kar raha hai, overwrite mat karo
  if (document.activeElement === el) return;

  el.value = money(v);
}


function computeCharge(base, perId, amtId){
  const per = val(perId);
  const amt = val(amtId);
  // if user filled amt, prefer amt; else percent
  if (amt > 0) return amt;
  if (per > 0) return (base * per / 100);
  return 0;
}

function computeChargeTotal(chargeBase, gstPercent){
  const gst = num(gstPercent);
  const gstAmt = gst > 0 ? (chargeBase * gst / 100) : 0;
  return chargeBase + gstAmt;
}

function computeTotals(){

  /* -----------------------------
   * 1. GRID TOTALS
   * ----------------------------- */
  let subtotal = 0;
  let gstFromRows = 0;

  (gridRows || []).forEach(r => {
    calcRow(r);
    subtotal += num(r._net);     // discounted amount (before GST)
    gstFromRows += num(r._gstamt);
  });

  /* -----------------------------
   * 2. HEADER DISCOUNT
   * ----------------------------- */
  const disctype = parseInt(document.getElementById('disctype')?.value || 0, 10);
  const discountVal = val('discount');

  let discountAmt = 0;
  if (discountVal > 0) {
    discountAmt = (disctype === 1)
      ? (subtotal * discountVal / 100)   // % discount
      : discountVal;                     // flat discount

    if (discountAmt > subtotal) discountAmt = subtotal;
  }

  const basicTotal = Math.max(0, subtotal - discountAmt);

  /* -----------------------------
   * 3. GST ON BASIC TOTAL
   * ----------------------------- */
  let gstPercent = 0;
  let totalRowNet = 0;

  (gridRows || []).forEach(r => {
    totalRowNet += num(r._net);
  });

  if (totalRowNet > 0) {
    gstPercent = (gstFromRows * 100) / totalRowNet;
  }

  const basicGST = (basicTotal * gstPercent) / 100;
  const basicWithGST = basicTotal + basicGST;

  /* -----------------------------
   * 4. SET MAIN FIELDS
   * ----------------------------- */
  setv('subtotal', subtotal);
  setv('discountamt', discountAmt);
  setv('basic_total', basicTotal);
  setv('gst', basicGST);
  setv('nettotal', basicWithGST);
  // setv('total2', basicWithGST);

  /* -----------------------------
   * 5. PACKING
   * ----------------------------- */
const packingBase = computeCharge(getBasicTotal(), 'packing_per', 'packing_amt');
  const packingTotal = computeChargeTotal(packingBase, val('packing'));
  setv('packing_total', packingTotal);
  if (val('packing_per') > 0) {
  setv('packing_amt', packingBase);
}
  const total2 = basicWithGST + packingTotal ;

setv('total2', total2);



  /* -----------------------------
   * 6. TRANSPORT
   * ----------------------------- */
const transBase   = computeCharge(getBasicTotal(), 'trans_per', 'trans_amt');
  const transTotal = computeChargeTotal(transBase, val('trans'));
  setv('trans_total', transTotal);
if (val('trans_per') > 0) {
  setv('trans_amt', transBase);
}
    const total3 = total2 + transTotal;
  setv('total3', total3);
  const total4 = basicWithGST + packingTotal + transTotal;
  setv('total4', total4);

    /* -----------------------------
     * 7. INSURANCE (ON TOTAL + TRANSPORT ONLY)
     * ----------------------------- */
    const insurancePer = val('insurance_per');
    const insuranceAmtInput = val('insurance_amt');
    
    // ✅ Base is EXACTLY total4 (Total + Transport)
    let insuranceBaseAmt = 0;
    
    if (insurancePer > 0) {
      insuranceBaseAmt = (total4 * insurancePer) / 100;
if (val('insurance_per') > 0) {
  setv('insurance_amt', insuranceBaseAmt);
}    } else if (insuranceAmtInput > 0) {
      insuranceBaseAmt = insuranceAmtInput;
    }

    
    // GST on insurance
    const insuranceGST = val('insurance');
    const insuranceGSTAmt = insuranceGST > 0
      ? (insuranceBaseAmt * insuranceGST / 100)
      : 0;
    
    const insuranceTotal = insuranceBaseAmt + insuranceGSTAmt;
    setv('insurance_total', insuranceTotal);


 /* -----------------------------
 * 8. COMMISSION
 * ----------------------------- */
const commissionBase = computeCharge(
  getBasicTotal(),
  'commission_per',
  'commission_amt'
);
const commissionTotal = computeChargeTotal(
  commissionBase,
  val('commission')
);
if (val('commission_per') > 0) {
  setv('commission_amt', commissionBase);
}
setv('commission_total', commissionTotal);

/* -----------------------------
 * 9. TOTAL AFTER ALL CHARGES
 * ----------------------------- */
const total5Raw =
  total4 +            // basic + packing + transport
  insuranceTotal ;    

const total5 = total5Raw;
setv('total5', total5);

/* -----------------------------
 * 10. TCS (ONLY on Basic + Packing)
 * ----------------------------- */
const tcsPer = val('tcs');
const tcsBase =
  val('nettotal') +   // basic + GST
  val('packing_total');

const tcsAmt = (tcsPer > 0)
  ? (tcsBase * tcsPer / 100)
  : 0;

setv('tcsamt', tcsAmt);
setv('total3', tcsBase + tcsAmt);

/* -----------------------------
 * 11. GRAND TOTAL
 * ----------------------------- */
const grandTotalRaw = total5 +commissionTotal+ tcsAmt;
const grandTotal = Math.round(grandTotalRaw);
setv('total', grandTotal);
}



function numVal(id){
  const el = document.getElementById(id);
  if(!el) return 0;
  const v = (el.value ?? '').toString().replace(/,/g,'').trim();
  const n = parseFloat(v);
  return isNaN(n) ? 0 : n;
}
function setVal(id, v){
  const el = document.getElementById(id);
  if(!el) return;
  el.value = (isNaN(v) ? 0 : v).toFixed(2);
}

function togglePerAmtLock(perId, amtId, baseFn){
  const perEl = document.getElementById(perId);
  const amtEl = document.getElementById(amtId);
  if(!perEl || !amtEl) return;

  const per = parseFloat(perEl.value) || 0;
  const amt = parseFloat(amtEl.value) || 0;

  // ✅ If Per % is entered → auto amount + lock AMOUNT only
  if(per > 0){
    const base = baseFn();
    amtEl.value = ((base * per) / 100).toFixed(2);
    amtEl.readOnly = true;
    perEl.readOnly = false;   // ✅ ALWAYS editable
    return;
  }

  // ✅ If Per % is cleared → unlock amount
  amtEl.readOnly = false;
  perEl.readOnly = false;
}



function hookPerAmt(perId, amtId, baseFn){
  const perEl = document.getElementById(perId);
  const amtEl = document.getElementById(amtId);
  if(!perEl || !amtEl) return;

  const run = () => {
    togglePerAmtLock(perId, amtId, baseFn);
    // call your totals calculation if exists
    if (typeof calcTotals === 'function') calcTotals();
    else if (typeof recalcTotals === 'function') recalcTotals();
    else if (typeof recalc === 'function') recalc();
  };

  perEl.addEventListener('input', run);
  amtEl.addEventListener('input', run);

  // initial load
 setTimeout(run, 100);

}

// Base amount for % calculation
// function baseTotalForCharges(){
//   // Packing & Transport % must be on BASIC TOTAL (before GST)
//   const basic = numVal('basic_total');
//   return basic > 0 ? basic : numVal('subtotal');
// }

function baseForInsurance(){
  // Insurance is on Total + Transport (total4). If not ready, fallback.
  const t4 = numVal('total4');
  if(t4 > 0) return t4;
  const t3 = numVal('total3');
  if(t3 > 0) return t3;
  return baseTotalForCharges();
}
function getBasicTotal(){
  return numVal('basic_total');
}

function getTotal4(){
  return numVal('total4');
}


// Attach rules to all charge rows
document.addEventListener('DOMContentLoaded', function(){
  hookPerAmt('packing_per',   'packing_amt',   getBasicTotal);
hookPerAmt('trans_per',     'trans_amt',     getBasicTotal);
// hookPerAmt('insurance_per', 'insurance_amt', getTotal4);
hookPerAmt('insurance_per', 'insurance_amt', baseForInsurance);
hookPerAmt('commission_per','commission_amt',getBasicTotal);
});


/* ---------- DOM READY ---------- */

document.addEventListener('DOMContentLoaded', function(){

  // date pickers
  if(window.flatpickr){
    flatpickr('.datepick', { dateFormat:'d-m-Y', allowInput:true, disableMobile:true });
  }

  initCustomerAutocomplete();

  // live preview changes in modal
  ['rm_rate','rm_qty','rm_disc','rm_inst','rm_gst'].forEach(id=>{
    const el = document.getElementById(id);
    if(el) el.addEventListener('input', updateRowModalPreview);
    
    
   
  });

  // terms type change => load master term
  const tmType = document.getElementById('tm-type');
  if(tmType){
    tmType.addEventListener('change', function(){
      const typeId = this.value;
      document.getElementById('tm-title').value = '';
      if(quill) quill.setContents([]);
      if(!typeId) return;

      postAjax({ ajax:'term_master', type: typeId })
        .then(j=>{
          if(j && j.ok){
            document.getElementById('tm-title').value = j.title || '';
            if(quill) quill.root.innerHTML = j.description || '';
          }
        })
        .catch(()=>{});
    });
  }



  // qtype -> load titles
 

 const qtypeEl = document.getElementById('qtype_inp');
if(qtypeEl){
  qtypeEl.addEventListener('change', function(){
    loadTitlesForQType(this.value);  
  });
}


  // totals dependencies
  ['disctype','discount','tcs',
   'packing_per','packing_amt','packing',
   'trans_per','trans_amt','trans',
   'insurance_per','insurance_amt','insurance',
   'commission_per','commission_amt','commission'
  ].forEach(id=>{
    const el = document.getElementById(id);
    if(el) el.addEventListener('input', computeTotals);
    if(el) el.addEventListener('change', computeTotals);
  });

  // load existing grid (edit mode)
  gridRows = (existingGrid || []).map((r, idx)=>({
    propid: parseInt(r.propid || 0,10) || 0,
    product_label: String(r.product_label || r.label || (r.propid ? ('#'+r.propid) : '')),
    hsncode: String(r.hsncode || ''),
    description: String(r.description || ''),
    rate: num(r.rate),
    qty: num(r.qty),
    discount: num(r.discount),
    instantdisc: num(r.instantdisc),
    gst: num(r.gst),
    sequence: parseInt(r.sequence || (idx+1),10) || (idx+1)
  }));
  renderGrid();

  // load existing terms
  termRows = (existingTerms || []).map(t=>({
    type: String(t.type || ''),
    title: String(t.title || t.term || ''),
    description: String(t.description || '')
  }));
  renderTerms();

  // init titles for existing qtype
  if(EXISTING_QTYPE && EXISTING_QTYPE > 0){
    loadTitlesForQType(EXISTING_QTYPE, EXISTING_TITLE_ID);
  }

  // totals at start
  computeTotals();

  // before submit pack json + enforce at least one row
  const form = document.getElementById('quotation-form');
  if(form){
    form.addEventListener('submit', function(e){
      if(!gridRows || gridRows.length < 1){
        e.preventDefault();
        alert('Add at least one product.');
        return;
      }
      document.getElementById('rows_json').value  = JSON.stringify(gridRows);
      document.getElementById('terms_json').value = JSON.stringify(termRows);

      // ensure totals are up to date
      computeTotals();
    });
  }
});


window.openRowModal = openRowModal;
</script>

<?php
$CONTENT = ob_get_clean();
require_once __DIR__ . '/../includes/ui_autoshell.php';
exit;
