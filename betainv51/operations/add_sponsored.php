<?php
@ini_set('display_errors', '1');
@error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_login();

global $con;
$page_title = "Add Sponsorship";

$mode = isset($_GET['add_new']) ? 'form' : 'list';
$view_mode = $_GET['mode'] ?? '';
$sponsored_id = isset($_GET['sponsored_id']) ? (int)$_GET['sponsored_id'] : 0;
// database tables
$SPONSERSHIP = 'jos_app_sponsorship';
$REGION_SPONSERSHIP = 'jos_app_sponsorship_region';
$IMG_SPONSERSHIP = 'jos_app_sponsorship_images';


//----------------------Helpers ------------------------

/* ---------- permission wrapper ---------- */
function has_cap($cap)
{
    if (function_exists('current_user_can')) return (bool) current_user_can($cap);
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        if (!empty($_SESSION['user']['caps']) && is_array($_SESSION['user']['caps'])) {
            if (in_array($cap, $_SESSION['user']['caps'], true)) return true;
        }
        if (!empty($_SESSION['user']['permissions']) && is_array($_SESSION['user']['permissions'])) {
            if (in_array($cap, $_SESSION['user']['permissions'], true)) return true;
        }
        if (!empty($_SESSION['user']['permissions_map']) && is_array($_SESSION['user']['permissions_map'])) {
            if (!empty($_SESSION['user']['permissions_map'][$cap])) return true;
        }
    }
    return true; // permissive fallback (change to false to deny by default)
}

/* ---------- auto title from jos_admin_menus ---------- */
try {
    $script_name = basename($_SERVER['PHP_SELF']);
    $sqls = [
        "SELECT menu_name FROM jos_admin_menus WHERE menu_link = ? LIMIT 1",
        "SELECT menu_name FROM jos_admin_menus WHERE url = ? LIMIT 1",
        "SELECT menu_name FROM jos_admin_menus WHERE menu_link LIKE CONCAT('%', ?, '%') LIMIT 1",
        "SELECT menu_name FROM jos_admin_menus WHERE url LIKE CONCAT('%', ?, '%') LIMIT 1"
    ];
    foreach ($sqls as $s) {
        if ($st = @$con->prepare($s)) {
            $st->bind_param('s', $script_name);
            $st->execute();
            $res = $st->get_result();
            if ($row = $res->fetch_assoc()) {
                if (!empty($row['menu_name'])) {
                    $page_title = $row['menu_name'];
                }
            }
            $st->close();

            if ($page_title !== 'Add Sponsorship') break;
        }
    }
} catch (Exception $e) {
    // silent fallback
}



/* ---------- upload paths ---------- */
$UPLOAD_DIR_FS = realpath(__DIR__ . '/../../webservices/uploads/sponsorship');

if (!$UPLOAD_DIR_FS) {
    $UPLOAD_DIR_FS = __DIR__ . '/../../webservices/uploads/sponsorship';
}
$UPLOAD_URL_REL = 'uploads/sponsorship/';


function full_img_for_display($dbPath)
{
    if (!$dbPath) return '';
    if (defined('DOMAIN_URL') && DOMAIN_URL) {
        $base = rtrim(DOMAIN_URL, '/') . '/';
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = $scheme . '://' . $host . '/';
    }
    if (preg_match('~^https?://~i', $dbPath)) return $dbPath;
    $p = ltrim($dbPath, '/');
    if (stripos($p, 'webservices/') === 0) return $base . $p;
    if (stripos($p, 'uploads/slider/') === 0) return $base . 'webservices/' . $p;
    return $base . 'webservices/' . $p;
}





/* logged user */
$me  = $_SESSION['admin_user'] ?? [];
$uid = (int)($me['id'] ?? 0);

/* save form */
$msg = "";
if (isset($_GET['ok'])) {
    $msg = "Sponsorship added successfully!";
}

$sponsorship_id = 0;

if (isset($_POST['save'])) {

    $sponsor_name = trim($_POST['sponsor_name']);
    $address      = trim($_POST['address']);
    $contact_no   = trim($_POST['contact_no']);
    $valid_from   = $_POST['valid_from'];
    $valid_to     = $_POST['valid_to'];
    $amount       = $_POST['amount'];
    $status       = $_POST['status'];

    $stmt = $con->prepare("INSERT INTO $SPONSERSHIP
        (sponsor_name,address,contact_no,valid_from,valid_to,amount,status,created_by)
        VALUES (?,?,?,?,?,?,?,?)");

    $stmt->bind_param(
        "sssssdii",
        $sponsor_name,
        $address,
        $contact_no,
        $valid_from,
        $valid_to,
        $amount,
        $status,
        $uid
    );

    if ($stmt->execute()) {
        $sponsorship_id = $stmt->insert_id;
    } else {
        $msg = "Error saving record";
    }
    $stmt->close();
}


// Location 
if ($sponsorship_id > 0 && !empty($_POST['location'])) {

    foreach ($_POST['location'] as $loc) {

        $country  = $loc['country'];
        $state    = $loc['state'];
        $district = $loc['district'];
        $city     = $loc['city'];
        $locality = $loc['locality'];

        $stmt2 = $con->prepare("INSERT INTO $REGION_SPONSERSHIP
        (sponsorship_id,country,state,district,city,locality)
        VALUES (?,?,?,?,?,?)");

        $stmt2->bind_param(
            "isssss",
            $sponsorship_id,
            $country,
            $state,
            $district,
            $city,
            $locality
        );

        $stmt2->execute();
        $stmt2->close();
    }
}

// Save Images
if ($sponsorship_id > 0 && !empty($_FILES['images']['name'][0])) {


    foreach ($_FILES['images']['name'] as $key => $name) {

        $tmp = $_FILES['images']['tmp_name'][$key];
        $order = $_POST['image'][$key]['order_no'];
        $status = $_POST['image'][$key]['status'];

        $size = $_FILES['images']['size'][$key];
        $type = $_FILES['images']['type'][$key];

        /* size validation */
        if ($size > 2 * 1024 * 1024) {
            echo "Image must be under 2MB<br>";
            continue;
        }

        /* type validation */
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

        if (!in_array($type, $allowed)) {
            echo "Invalid image type<br>";
            continue;
        }












        $filename = time() . '_' . $key . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $name);

        $target = $UPLOAD_DIR_FS . '/' . $filename;

        if (move_uploaded_file($tmp, $target)) {

            $stmt3 = $con->prepare("
            INSERT INTO $IMG_SPONSERSHIP
            (sponsorship_id,image_path,order_no,status)
            VALUES (?,?,?,?)");

            $img_path = $UPLOAD_URL_REL . $filename;

            $stmt3->bind_param("isii", $sponsorship_id, $img_path, $order, $status);
            $stmt3->execute();
            $stmt3->close();
        } else {

            echo "UPLOAD FAILED<br>";
            echo "TMP: " . $tmp . "<br>";
            echo "TARGET: " . $target . "<br>";
            print_r(error_get_last());
        }
    }
}

if ($sponsorship_id > 0) {
    header("Location: add_sponsored.php?ok=1");
    exit;
}





$filter_name   = $_GET['sponsor_name'] ?? '';
$filter_status = $_GET['status'] ?? '';
$from_date     = $_GET['from_date'] ?? '';
$to_date       = $_GET['to_date'] ?? '';

$where = "WHERE 1=1";
$params = [];
$types  = "";

/* Sponsor Name Filter */
if ($filter_name != '') {
    $where .= " AND s.sponsor_name LIKE ?";
    $params[] = "%$filter_name%";
    $types .= "s";
}

/* Status Filter */
if ($filter_status !== '') {
    $where .= " AND s.status = ?";
    $params[] = $filter_status;
    $types .= "i";
}

/* Date Filter */
if ($from_date != '' && $to_date != '') {
    $where .= " AND DATE(s.created_at) BETWEEN ? AND ?";
    $params[] = $from_date;
    $params[] = $to_date;
    $types .= "ss";
}

/* Final Query */
$sql = "
SELECT 
s.id,
s.sponsor_name,
s.amount,
s.valid_from,
s.valid_to,
s.status,
s.created_by,
s.created_at,
u.name AS created_by_name
FROM $SPONSERSHIP s
LEFT JOIN jos_admin_users u ON u.id = s.created_by
$where
ORDER BY s.id DESC
";

$stmt = $con->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$list = $stmt->get_result();






//Region data
$regions = [];
$images = [];


if ($view_mode == 'region' && $sponsored_id > 0) {

    $stmt = $con->prepare("
        SELECT country,state,district,city,locality
        FROM $REGION_SPONSERSHIP
        WHERE sponsorship_id = ?
    ");
    $stmt->bind_param("i", $sponsored_id);
    $stmt->execute();
    $regions = $stmt->get_result();
}

if ($view_mode == 'images' && $sponsored_id > 0) {

    $stmt = $con->prepare("
        SELECT image_path,order_no,status
        FROM $IMG_SPONSERSHIP
        WHERE sponsorship_id = ?
        ORDER BY order_no ASC
    ");
    $stmt->bind_param("i", $sponsored_id);
    $stmt->execute();
    $images = $stmt->get_result();
}

























?>

<link rel="stylesheet" href="/adminconsole/assets/ui.css">

<style>
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 16px;
        padding: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-group label {
        font-weight: 600;
        font-size: 13px;
    }

    .form-group input,
    .form-group textarea,
    .form-group select {
        padding: 10px;
        border-radius: 8px;
        border: 1px solid #ccc;
    }

    /* .btn.primary {
        background: #2563eb;
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 999px;
        font-weight: 600;
    }

    .btn.primary:hover {
        opacity: .9;
    } */

    /* modal css */
    .modal {
        display: none;
        /* keep hidden by default */
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, .6);
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .modal-content {
        position: relative;
        overflow: visible;
    }

    .modal.active {
        display: flex;
        /* show modal */
    }

    .modal-content {
        background: #0f172a;
        padding: 20px;
        border-radius: 10px;
        width: 500px;
        max-width: 90%;
        position: relative;
        z-index: 10000;
    }


    .pac-container {
        border-radius: 8px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        font-size: 14px;
        z-index: 999999 !important;
    }


    .pac-item {
        padding: 10px;
        cursor: pointer;
    }

    .pac-item:hover {
        background: #f1f5ff;
    }

    /* Big Center Save Button */
    .save-sponsorship-wrap {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 30px 0;
    }

    .save-sponsorship-btn {
        font-size: 18px;
        padding: 14px 40px;
        border-radius: 10px;
        font-weight: 700;
        /* min-width: 260px; */
    }




    /* MAIN FORM ONLY - 3 inputs per row */
    .main-form-grid {
        grid-template-columns: repeat(3, 1fr);
        max-width: 1100px;
    }

    /* Smaller input height */
    .main-form-grid input,
    .main-form-grid select,
    .main-form-grid textarea {
        padding: 8px;
        font-size: 13px;
    }

    /* Address full width */
    .main-form-grid textarea {
        grid-column: span 3;
    }

    /* Responsive for small screens */
    @media (max-width: 900px) {
        .main-form-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 500px) {
        .main-form-grid {
            grid-template-columns: 1fr;
        }
    }

    /* limit page content width */
    .master-wrap {
        width: 100%;
        max-width: 100%;
        margin: 0;
        padding: 0 20px;
    }


    /* table container */
    .master-wrap {
        width: 100%;
        max-width: 100%;
        margin: 0;
        padding: 0 20px;
    }

    /* //filter hide show */
    .hide {
        display: none !important;
    }

    /* FILTER PANEL */
    .toolbar {
        padding: 16px;
        border-radius: 10px;
        margin-bottom: 15px;
    }

    /* row layout */
    .toolbar .row {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        align-items: end;
    }

    /* each filter group */
    .toolbar .group {
        display: flex;
        flex-direction: column;
        gap: 5px;
        min-width: 180px;
    }

    /* label */
    .toolbar label {
        font-size: 13px;
        font-weight: 600;
        color: #cbd5e1;
    }

    /* input + select */
    .toolbar input,
    .toolbar select {
        padding: 8px 10px;
        border-radius: 6px;
        border: 1px solid #334155;
        background: #020617;
        color: #fff;
        min-width: 160px;
    }

    /* date inputs */
    .toolbar input[type="date"] {
        min-width: 150px;
    }

    /* filter buttons container */
    .toolbar .actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    /* make form-group the anchor */
    .form-group {
        position: relative;
    }



    /* suggestion dropdown */
    .suggestion-box {
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;

        max-height: 200px;
        overflow-y: auto;

        background: #020617;
        border-radius: 8px;

        z-index: 999999;

        margin-top: 6px;

        box-shadow: 0 10px 25px rgba(0, 0, 0, .45);
    }

    /* suggestion item */
    .suggestion-item {
        padding: 10px 12px;
        cursor: pointer;
        font-size: 13px;
        color: #e2e8f0;
    }

    /* hover */
    .suggestion-item:hover {
        background: #697fed;
        color: #fff;
    }

    .modal-content {
        overflow: visible;
    }

    .suggestion-box {
        animation: fadeIn .15s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<div class="master-wrap">
    <div class="headbar">
        <div class="headbar-left">
            <h2 style="margin:0"><?php echo htmlspecialchars($page_title); ?></h2>
        </div>
        <div class="headbar-right"></div>
    </div>

    <div class="card" style="margin-top:20px">



        <?php if ($msg): ?>

            <div style="display:flex;justify-content:space-between;align-items:center;margin:10px;">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>









        <!-- RIGHT: Add New (alone, green) -->
        <?php if ($mode == 'list' && $view_mode == ''): ?>

            <!-- Show/Hide Filter Button -->




            <div id="filterPanel" class="card toolbar">
                <form method="GET">

                    <div class="row">

                        <div class="group">
                            <label>Sponsor Name</label>
                            <input type="text"
                                name="sponsor_name"
                                placeholder="Sponsor Name"
                                value="<?= htmlspecialchars($_GET['sponsor_name'] ?? '') ?>">
                        </div>

                        <div class="group">
                            <label>Status</label>
                            <select name="status">
                                <option value="">All Status</option>
                                <option value="1" <?= (($_GET['status'] ?? '') === '1') ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= (($_GET['status'] ?? '') === '0') ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="group">
                            <label>From</label>
                            <input
                                class="inp datepicker"
                                type="text"
                                name="from_date"
                                id="from_date_filter"
                                value="<?= htmlspecialchars($from_date ?? '') ?>"
                                placeholder="DD-MM-YYYY">
                        </div>


                        <div class="group">
                            <label>To</label>
                            <input
                                class="inp datepicker"
                                type="text"
                                name="to_date"
                                id="to_date_filter"
                                value="<?= htmlspecialchars($to_date ?? '') ?>"
                                placeholder="DD-MM-YYYY">
                        </div>

                        <div class="actions">
                            <button type="submit" class=" btn green">Filter</button>
                            <a href="add_sponsored.php" class="btn secondary">Reset</a>
                        </div>

                    </div>

                </form>

            </div>
















            <div style="display:flex;justify-content:space-between;align-items:center;margin:10px;">
                <div style="display:flex;gap:10px;align-items:center;">
                    <button type="button"
                        id="toggleFilterBtn"
                        class="btn secondary"
                        onclick="toggleFilterBox(); return false;">
                        Hide Filters
                    </button>
                </div>
                <a href="?add_new=1" class=" btn green">+ Add Sponsorship</a>
            </div>
        <?php endif; ?>







        <?php if ($mode == 'form'): ?>

            <div style="margin-bottom:15px">
                <a href="add_sponsored.php" class="btn gray">← Back to List</a>
            </div>


            <form method="post" enctype="multipart/form-data" id="sponsorshipForm">
                <div class="form-grid main-form-grid">

                    <div class="form-group">
                        <label>Sponsor Name</label>
                        <input type="text" name="sponsor_name" required>
                    </div>

                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_no" maxlength="10">
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" step="0.01" name="amount" required>
                    </div>

                    <div class="form-group">
                        <label>Valid From</label>
                        <input type="text" placeholder="DD-MM-YYYY" name="valid_from" id="valid_from" required>
                    </div>



                    <div class="form-group">
                        <label>Duration (Months)</label>
                        <select name="months" id="months">
                            <option value="">Select Months</option>

                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> Month<?= $i > 1 ? 's' : '' ?></option>
                            <?php endfor; ?>

                        </select>
                    </div>

                    <div class="form-group">
                        <label>Valid To</label>
                        <input type="text" placeholder="DD-MM-YYYY" name="valid_to" id="valid_to" readonly required>
                    </div>



                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>

                    <div class="form-group" style="grid-column:1/-1">
                        <label>Address</label>
                        <textarea name="address" rows="3"></textarea>
                    </div>
                </div>

                <div style="padding:0 20px 20px 20px; margin-top:8px;">
                    <button type="button" id="openLocationModal" class=" btn green">
                        + Add Location
                    </button>

                </div>



                <!-- Location Modal -->

                <div style="padding:0 20px 20px 20px; display:none;" id="locationTableWrap">
                    <table border="1" width="100%" id="locationTable" style="margin-top:8px;">
                        <thead>
                            <tr>
                                <th>Country</th>
                                <th>State</th>
                                <th>District</th>
                                <th>City</th>
                                <th>Locality</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody></tbody>

                    </table>
                </div>
                <div id="locationInputs"></div>
                <!-- Location Modal END -->


                <div style="padding:0 20px 20px 20px; margin-top:8px;">
                    <button type="button" id="openImageModal" class=" btn green">
                        + Add Image
                    </button>
                </div>
                <!-- Image Modal -->
                <div style="padding:0 20px 20px 20px; display:none;" id="imageTableWrap">
                    <table border="1" width="100%" id="imageTable">
                        <thead>
                            <tr>
                                <th>Preview</th>
                                <th>Order</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody></tbody>

                    </table>

                </div>

                <div id="imageInputs"></div>
                <!-- Image Modal END-->


                <div class="save-sponsorship-wrap">
                    <button type="submit" name="save" class="btn green save-sponsorship-btn">
                        Save Sponsorship
                    </button>
                </div>

            </form>
        <?php endif; ?>

























        <!-- Region Section -->

        <?php if ($view_mode == 'region'):
            $srnum = 1; ?>

            <div class="table-wrap" style="margin: 8px;">

                <h3>Region List</h3>

                <a href="add_sponsored.php" class="btn gray">← Back</a>

                <table border="1" width="100%" style="margin-top: 50px;">
                    <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>Country</th>
                            <th>State</th>
                            <th>District</th>
                            <th>City</th>
                            <th>Locality</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($r = $regions->fetch_assoc()): ?>

                            <tr>
                                <td><?= $srnum++ ?></td>
                                <td><?= htmlspecialchars($r['country']) ?></td>
                                <td><?= htmlspecialchars($r['state']) ?></td>
                                <td><?= htmlspecialchars($r['district']) ?></td>
                                <td><?= htmlspecialchars($r['city']) ?></td>
                                <td><?= htmlspecialchars($r['locality']) ?></td>
                            </tr>

                        <?php endwhile; ?>

                    </tbody>
                </table>

            </div>

        <?php endif; ?>











        <!-- Image section  -->
        <?php if ($view_mode == 'images'): $srnum = 1; ?>

            <div class="table-wrap">

                <h3>Image List</h3>

                <a href="add_sponsored.php" class="btn gray">← Back</a>

                <table border="1" width="100%" style="margin-top: 50px;">
                    <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>Preview</th>
                            <th>Order</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($img = $images->fetch_assoc()): ?>

                            <tr>
                                <td><?= $srnum++ ?></td>
                                <td>
                                    <img src="<?= htmlspecialchars(full_img_for_display($img['image_path'])) ?>"
                                        style="width:80px;height:80px;object-fit:cover">
                                </td>

                                <td><?= $img['order_no'] ?></td>

                                <td><?= $img['status'] ? 'Active' : 'Inactive' ?></td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>
                </table>

            </div>

        <?php endif; ?>
























        <!-- //MAIN LIST  code -->

        <?php if ($mode == 'list' && $view_mode == ''): ?>
            <div class="table-wrap">
                <table style="margin-top:10px;">
                    <thead>
                        <tr>
                            <th>Sr No</th>
                            <th>Sponsor Name</th>
                            <th>Amount</th>
                            <th>Valid From</th>
                            <th>Valid To</th>
                            <th>Status</th>
                            <th>Created By / Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php
                        $sr = 1;

                        while ($row = $list->fetch_assoc()) { ?>

                            <tr>

                                <td><?= $sr++ ?></td>

                                <td><?= htmlspecialchars($row['sponsor_name']) ?></td>

                                <td><?= $row['amount'] ?></td>

                                <td><?= date('d-m-Y', strtotime($row['valid_from'])) ?></td>
                                <td><?= date('d-m-Y', strtotime($row['valid_to'])) ?></td>

                                <!-- <td>
                                    <?php if (!empty($row['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars(full_img_for_display($row['image_path'])); ?>"
                                            alt="" style="width:50px;height:50x;object-fit:cover;border-radius:8px">
                                    <?php endif; ?>
                                </td> -->

                                <td><?= $row['status'] ? 'Active' : 'Inactive' ?></td>

                                <td>
                                    <?= htmlspecialchars($row['created_by_name'] ?? '-') ?><br>
                                    <small><?= !empty($row['created_at']) ? date('d-m-Y H:i', strtotime($row['created_at'])) : '' ?></small>
                                </td>

                                <td>

                                    <a class="btn secondary"
                                        href="add_sponsored.php?mode=region&sponsored_id=<?= $row['id'] ?>">
                                        View Region
                                    </a>

                                    <a style="margin:2px;" class="btn secondary"
                                        href="add_sponsored.php?mode=images&sponsored_id=<?= $row['id'] ?>">
                                        View Images
                                    </a>

                                </td>
                            </tr>

                        <?php } ?>

                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </div>
</div>


<!-- //modal start -->
<div id="locationModal" class="modal">

    <div class="modal-content">

        <h3>Add Location</h3>

        <div class="form-grid">

            <div class="form-group">
                <label>Country</label>
                <input type="text" id="loc_country">
                <div id="countrySuggestions" class="suggestion-box"></div>

            </div>

            <div class="form-group">
                <label>State</label>
                <input type="text" id="loc_state">
                <div id="stateSuggestions" class="suggestion-box"></div>

            </div>

            <div class="form-group">
                <label>District</label>
                <input type="text" id="loc_district">
                <div id="districtSuggestions" class="suggestion-box"></div>

            </div>

            <!-- City -->
            <div class="form-group"> <label>City</label>

                <input
                    type="text"
                    id="loc_city"
                    name="city"
                    placeholder="Enter City"
                    autocomplete="off"
                    required>
                <div id="citySuggestions" class="suggestion-box"></div>
            </div>

            <!-- 
            <input type="hidden" id="city_place_id" name="city_place_id">
            <input type="hidden" id="city_lat" name="city_lat">
            <input type="hidden" id="city_lng" name="city_lng"> -->

            <div class="form-group">
                <label>Locality</label>
                <input type="text" id="loc_locality">
                <div id="localitySuggestions" class="suggestion-box"></div>
            </div>

        </div>

        <div style="margin-top:15px">

            <button type="button" id="saveLocation" class=" btn green">
                Save Location
            </button>

            <button class="btn secondary" type="button" id="closeLocationModal">
                Cancel
            </button>

        </div>

    </div>

</div>
<!-- //location modal end -->







<!-- IMAGE MODAL START -->
<div id="imageModal" class="modal">

    <div class="modal-content">

        <h3>Add Image</h3>

        <div class="form-grid">

            <div class="form-group">
                <label>Upload Image</label>
                <input type="file" id="img_file" accept="image/*">
            </div>

            <div class="form-group">
                <label>Order No</label>
                <input type="number" id="img_order" value="1">
            </div>

            <div class="form-group">
                <label>Status</label>
                <select id="img_status">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

        </div>

        <div style="margin-top:15px">

            <button type="button" id="saveImage" class=" btn green">
                Save Image
            </button>

            <button class="btn secondary" type="button" id="closeImageModal">
                Cancel
            </button>

        </div>

    </div>

</div>
<!-- IMAGE MODAL END -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="/adminconsole/assets/ui.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    $('#openFormBtn').click(function() {

        $('#sponsorshipForm').slideToggle();

    });




















    /* ---------------- GLOBAL STORAGE ---------------- */
    let service;

    let selectedCountry = "";
    let selectedState = "";
    let selectedDistrict = "";
    let selectedCity = "";
    let placeService;

    function initLocationAutocomplete() {

        service = new google.maps.places.AutocompleteService();
        placeService = new google.maps.places.PlacesService(document.createElement('div'));



        setupAutocomplete("loc_country", "country");
        setupAutocomplete("loc_state", "state");
        setupAutocomplete("loc_district", "district");
        setupAutocomplete("loc_city", "city");
        setupAutocomplete("loc_locality", "locality");


    }


    function setupAutocomplete(inputId, type) {

        const input = document.getElementById(inputId);

        input.addEventListener("keyup", function() {

            let query = input.value;

            if (query.length < 2) return;

            service.getPlacePredictions({
                input: query,
                componentRestrictions: {
                    country: "in"
                }
            }, function(predictions, status) {

                if (!predictions) return;

                let filtered = [];


                predictions.forEach(function(p) {




                    if (
                        p.types.includes("establishment") ||
                        p.types.includes("point_of_interest")
                    ) {
                        return;
                    }

                    let text = p.description;
                    let queryLower = query.toLowerCase();
                    let descLower = text.toLowerCase();

                    if (!descLower.startsWith(queryLower)) {
                        return;
                    }
                    /* country */
                    if (type === "country") {

                        if (p.types.includes("country")) {
                            filtered.push(p);
                        }

                    }

                    /* state */
                    /* state */
                    else if (type === "state") {

                        if (
                            p.types.includes("administrative_area_level_1") &&
                            text.includes(selectedCountry)
                        ) {
                            filtered.push(p);
                        }

                    }

                    /* district */
                    else if (type === "district") {

                        if (
                            p.types.includes("administrative_area_level_3") ||
                            p.types.includes("locality")
                        ) {

                            if (text.toLowerCase().includes(selectedState.toLowerCase())) {
                                filtered.push(p);
                            }

                        }

                    }

                    /* city */
                    else if (type === "city") {

                        if (p.types.includes("locality")) {

                            if (text.toLowerCase().includes(selectedState.toLowerCase())) {
                                filtered.push(p);
                            }

                        }

                    }

                    /* locality */
                    else if (type === "locality") {

                        if (
                            p.types.includes("sublocality") ||
                            p.types.includes("sublocality_level_1") ||
                            p.types.includes("neighborhood") ||
                            p.types.includes("locality") ||
                            p.types.includes("premise")
                        ) {

                            if (
                                selectedCity &&
                                text.toLowerCase().includes(selectedCity.toLowerCase())
                            ) {

                                if (text.toLowerCase().includes(query.toLowerCase())) {
                                    filtered.push(p);
                                }

                            }
                        }

                    }

                });

                showSuggestions(filtered, inputId, type);

            });

        });

    }

    function checkCityDistrict(prediction, callback) {

        placeService.getDetails({
                placeId: prediction.place_id,
                fields: ["address_components", "name"]
            },
            function(place, status) {

                if (status !== "OK") {
                    callback(false);
                    return;
                }

                let districtFound = "";

                place.address_components.forEach(function(c) {

                    if (c.types.includes("administrative_area_level_3")) {
                        districtFound = c.long_name;
                    }

                });

                if (districtFound.toLowerCase() === selectedDistrict.toLowerCase()) {
                    callback(true);
                } else {
                    callback(false);
                }

            });

    }



    function showSuggestions(list, inputId, type) {


        let box = document.getElementById(type + "Suggestions");
        box.innerHTML = "";

        if (list.length === 0) {
            box.style.display = "none";
            return;
        }

        box.style.display = "block";

        list.forEach(function(item) {

            let div = document.createElement("div");
            div.className = "suggestion-item";
            div.innerHTML = item.description;

            div.onclick = function() {

                let value = item.description;

                /* SPLIT ADDRESS PARTS */
                let parts = value.split(",");

                /* CLEAN VALUES */

                if (type === "country") {
                    value = parts[0].trim();
                    selectedCountry = value;
                } else if (type === "state") {
                    value = parts[0].trim();
                    selectedState = value;
                } else if (type === "district") {
                    value = parts[0].trim();
                    selectedDistrict = value;
                } else if (type === "city") {
                    value = parts[0].trim();
                    selectedCity = value;
                }

                /* LOCALITY SPECIAL LOGIC */
                else if (type === "locality") {

                    let cleaned = [];

                    for (let i = 0; i < parts.length; i++) {

                        let p = parts[i].trim();

                        if (p === selectedCity) break;

                        cleaned.push(p);

                    }

                    value = cleaned.join(", ");

                }

                /* SET INPUT VALUE */

                document.getElementById(inputId).value = value;

                box.innerHTML = "";

            };

            box.appendChild(div);

        });

    }





















    $(document).ready(function() {

        let validFromPicker;
        let validToPicker;

        /* Calculate Valid To */

        function calculateDate() {

            let validFrom = $('#valid_from').val();
            let months = $('#months').val();

            if (validFrom && months) {
                let date = new Date(validFrom);

                date.setMonth(date.getMonth() + parseInt(months));

                validToPicker.setDate(date, true); // updates UI + value
            }
        }


        /* Flatpickr - Valid From */

        validFromPicker = flatpickr("#valid_from", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d-m-Y",
            onChange: function() {
                calculateDate();
            }
        });


        /* Flatpickr - Valid To */

        validToPicker = flatpickr("#valid_to", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d-m-Y",
            clickOpens: false, // prevents opening picker
            allowInput: false // prevents typing
        });


        /* Month Change */

        $('#months').on('change', function() {
            calculateDate();
        });

    });

    //modal js of location multiple
    let locationIndex = 0;
    let editingLocationRow = null;
    /* Open modal */
    $('#openLocationModal').click(function() {
        $('#locationModal').addClass('active');
    });
    /* Close modal */
    $('#closeLocationModal').click(function() {
        editingLocationRow = null;
        $('#locationModal').removeClass('active');
    });
    /* Save location */
    $('#saveLocation').click(function() {
        let country = $('#loc_country').val();
        let state = $('#loc_state').val();
        let district = $('#loc_district').val();
        let city = $('#loc_city').val();
        let locality = $('#loc_locality').val();

        if (city == "") {
            alert("Please select a city from dropdown");
            return;
        }

        let row = `<tr>
<td>${country}</td>
<td>${state}</td>
<td>${district}</td>
<td>${city}</td>
<td>${locality}</td>
<td>
<button type="button" class="editRow btn primary">Edit</button>
<button type="button" class="removeRow btn red">Remove</button>
</td>
</tr>`;

        $('#locationTableWrap').show();

        if (editingLocationRow) {
            editingLocationRow.replaceWith(row);
            editingLocationRow = null;
        } else {
            $('#locationTable tbody').append(row);
        }

        $('#locationInputs').append(`
        <input type="hidden" name="location[${locationIndex}][country]" value="${country}">
        <input type="hidden" name="location[${locationIndex}][state]" value="${state}">
        <input type="hidden" name="location[${locationIndex}][district]" value="${district}">
        <input type="hidden" name="location[${locationIndex}][city]" value="${city}">
        <input type="hidden" name="location[${locationIndex}][locality]" value="${locality}">
        `);

        locationIndex++;

        $('#loc_city,#loc_locality').val('');

        $('#locationModal').removeClass('active');

    });


    /* remove row */

    $(document).on('click', '.removeRow', function() {

        $(this).closest('tr').remove();

        if ($('#locationTable tbody tr').length == 0) {
            $('#locationTableWrap').hide();
        }

    });
    $(document).on('click', '.editRow', function() {

        editingLocationRow = $(this).closest('tr');

        let country = editingLocationRow.find('td:eq(0)').text();
        let state = editingLocationRow.find('td:eq(1)').text();
        let city = editingLocationRow.find('td:eq(3)').text();
        let locality = editingLocationRow.find('td:eq(4)').text();

        $('#loc_country').val(country);
        $('#loc_state').val(state);
        $('#loc_city').val(city);
        $('#loc_locality').val(locality);

        $('#locationModal').addClass('active');

    });


    $(window).click(function(e) {
        if ($(e.target).is('#locationModal')) {
            $('#locationModal').removeClass('active');
        }
    });
    $(document).keydown(function(e) {
        if (e.key === "Escape") {
            editingLocationRow = null;
            $('#locationModal').removeClass('active');
        }
    });

    //Image modal code
    let imageIndex = 0;
    let editingImageRow = null;

    /* open modal */

    $('#openImageModal').click(function() {
        $('#imageModal').addClass('active');
    });

    /* close modal */

    $('#closeImageModal').click(function() {
        editingImageRow = null;
        $('#imageModal').removeClass('active');
    });

    /* save image */
    $('#saveImage').click(function() {

        let fileInput = $('#img_file')[0];
        let file = fileInput.files[0];
        let order = $('#img_order').val();
        let status = $('#img_status').val();


        if (!file) {
            alert("Please select image");
            return;
        }

        /* size validation (2MB) */
        if (file.size > 2 * 1024 * 1024) {
            alert("Image must be under 2 MB");
            $('#img_file').val('');
            return;
        }

        /* type validation */
        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

        if (!allowed.includes(file.type)) {
            alert("Only JPG, PNG, WEBP images allowed");
            $('#img_file').val('');
            return;
        }

        let reader = new FileReader();

        reader.onload = function(e) {

            let preview = e.target.result;

            let row = `<tr>
            <td><img src="${preview}" width="80"></td>
            <td>${order}</td>
            <td>${status==1?'Active':'Inactive'}</td>
            <td>
            <button type="button" class="editImage btn primary">Edit</button>
            <button type="button" class="removeImage btn red">Remove</button>
            </td>
        </tr>`;

            $('#imageTableWrap').show();
            if (editingImageRow) {
                editingImageRow.replaceWith(row);
                editingImageRow = null;
            } else {
                $('#imageTable tbody').append(row);
            }
            // create hidden file input for form submission
            let newInput = document.createElement("input");
            newInput.type = "file";
            newInput.name = "images[]";
            newInput.style.display = "none";
            let dt = new DataTransfer();
            dt.items.add(file);
            newInput.files = dt.files;
            document.getElementById("imageInputs").appendChild(newInput);

            // order + status
            $('#imageInputs').append(`
            <input type="hidden" name="image[${imageIndex}][order_no]" value="${order}">
            <input type="hidden" name="image[${imageIndex}][status]" value="${status}">
        `);

            imageIndex++;
        };

        reader.readAsDataURL(file);

        $('#img_file').val('');
        $('#img_order').val('');

        $('#imageModal').removeClass('active');
    });

    /* remove */

    $(document).on('click', '.removeImage', function() {

        let rowIndex = $(this).closest('tr').index();

        /* remove hidden inputs */
        $('#imageInputs input').eq(rowIndex * 3).remove();
        $('#imageInputs input').eq(rowIndex * 3).remove();
        $('#imageInputs input').eq(rowIndex * 3).remove();

        $(this).closest('tr').remove();

        if ($('#imageTable tbody tr').length == 0) {
            $('#imageTableWrap').hide();
        }

    });
    $(document).on('click', '.editImage', function() {

        editingImageRow = $(this).closest('tr');

        let rowIndex = editingImageRow.index();

        let order = editingImageRow.find('td:eq(1)').text();
        let status = editingImageRow.find('td:eq(2)').text().trim() === 'Active' ? 1 : 0;

        $('#img_order').val(order);
        $('#img_status').val(status);

        /* REMOVE OLD HIDDEN INPUTS */
        $('#imageInputs input').eq(rowIndex * 3).remove();
        $('#imageInputs input').eq(rowIndex * 3).remove();
        $('#imageInputs input').eq(rowIndex * 3).remove();

        $('#imageModal').addClass('active');

    });
    $(window).click(function(e) {
        if ($(e.target).is('#imageModal')) {
            $('#imageModal').removeClass('active');
        }
    });

    $(document).keydown(function(e) {
        if (e.key === "Escape") {
            editingImageRow = null;
            $('#imageModal').removeClass('active');
        }
    });

    // Filter Hide Show
    function toggleFilterBox() {

        var box = document.getElementById('filterPanel');
        var btn = document.getElementById('toggleFilterBtn');

        if (box.classList.contains('hide')) {

            box.classList.remove('hide');
            btn.innerText = 'Hide Filters';

        } else {

            box.classList.add('hide');
            btn.innerText = 'Show Filters';

        }

    }


    document.addEventListener("DOMContentLoaded", function() {
        flatpickr(".datepicker", {
            altInput: true,
            altFormat: "d-m-Y",
            dateFormat: "Y-m-d",
            allowInput: false
        });
    });

    /* Form Validation */
    $('#sponsorshipForm').on('submit', function(e) {

        let locationCount = $('#locationTable tbody tr').length;
        let imageCount = $('#imageTable tbody tr').length;

        if (locationCount === 0) {
            alert("Please add at least one Location.");
            e.preventDefault();
            return false;
        }

        if (imageCount === 0) {
            alert("Please add at least one Image.");
            e.preventDefault();
            return false;
        }

    });
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCokcdTmQxRaopu75ourz-nNmZNie1wQkY&libraries=places&callback=initLocationAutocomplete"
    async defer></script>