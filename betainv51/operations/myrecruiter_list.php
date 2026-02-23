<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

/* ------------------ View-only ACL guard (inserted) ------------------ */
global $con;
if (!isset($con) || !$con) {
  http_response_code(500);
  echo '<!doctype html><html><head><meta charset="utf-8"><title>Server error</title>';
  echo '<link rel="stylesheet" href="/adminconsole/assets/ui.css">';
  echo '</head><body><div class="master-wrap"><div class="card">';
  echo '<h2>Server error</h2><div class="alert danger">DB connection not initialized.</div>';
  echo '</div></div></body></html>';
  exit;
}

/* Normalize current script path for menu matching */
$script_path = $_SERVER['PHP_SELF'];
$script_basename = basename($script_path);

$menu_id_override = isset($_GET['menu_id']) ? (int)$_GET['menu_id'] : 0;
$can_view = 0;

if ($menu_id_override > 0) {
  $stmt = $con->prepare("SELECT can_view FROM jos_admin_menus WHERE id=? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('i', $menu_id_override);
    $stmt->execute();
    $stmt->bind_result($can_view);
    $stmt->fetch();
    $stmt->close();
  }
} else {
  $a = $script_path;
  $b = $script_basename;
  $stmt = $con->prepare("SELECT can_view FROM jos_admin_menus WHERE menu_link IN (?,?) LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('ss', $a, $b);
    $stmt->execute();
    $stmt->bind_result($can_view);
    $stmt->fetch();
    $stmt->close();
  }
}

/* loose LIKE match */
if ((int)$can_view !== 1) {
  $like_pattern = '%' . $script_basename;
  $stmt = $con->prepare("SELECT can_view FROM jos_admin_menus WHERE menu_link LIKE ? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('s', $like_pattern);
    $stmt->execute();
    $stmt->bind_result($can_view);
    $stmt->fetch();
    $stmt->close();
  }
}

if ((int)$can_view !== 1) {
  http_response_code(403);
  echo '<!doctype html><html><head><meta charset="utf-8"><title>403 Access denied</title>';
  echo '<link rel="stylesheet" href="/adminconsole/assets/ui.css">';
  echo '</head><body>';
  echo '<div class="master-wrap" style="padding:40px 0">';
  echo '  <div class="card" style="max-width:820px;margin:0 auto;text-align:left">';
  echo '    <h2 style="margin-top:0">403 — Access denied</h2>';
  echo '    <div class="alert danger">You do not have permission to view this page.</div>';
  echo '    <p style="color:#6b7280">If you believe this is an error, contact an administrator or use a menu testing override by adding <code>?menu_id=</code> to the URL (for admins only).</p>';
  echo '    <div style="margin-top:12px"><a class="btn secondary" href="/adminconsole/">Return to dashboard</a></div>';
  echo '  </div>';
  echo '</div>';
  echo '</body></html>';
  exit;
}

/* -------- page config -------- */
$page_title = 'Employer List';
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
if (!defined('DOMAIN_URL')) {
  define('DOMAIN_URL', '/');
}

/* -------- Logged in admin id (Account Manager) -------- */
$u = function_exists('current_user') ? current_user() : [];
$logged_admin_id = (int)($u['id'] ?? 0);

/* ============================================================
   DASHBOARD POST SUPPORT (Assigned Recruiters)
============================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $mode  = $_POST['mode'] ?? '';
  $from  = $_POST['from'] ?? '';
  $to    = $_POST['to'] ?? '';
  $range = $_POST['range'] ?? '';

  // 🔐 Security: ignore posted admin_id and use logged one only
  if ($logged_admin_id <= 0) {
    die("Unauthorized access.");
  }

  // Only apply when coming from dashboard
  if ($mode === 'employers_assigned') {

    if ($range !== 'lifetime' && !empty($from) && !empty($to)) {

      // Convert datetime to Y-m-d (for DATE comparison)
      $created_from = date('Y-m-d', strtotime($from));
      $created_to   = date('Y-m-d', strtotime($to));

      // Force override GET date filters
      $_GET['created_from'] = date('d-m-Y', strtotime($from));
      $_GET['created_to']   = date('d-m-Y', strtotime($to));
    }

    // Optional: Clear other filters when coming from dashboard
    $_GET['q'] = '';
    $_GET['plan_id'] = null;
    $_GET['page'] = 1;
  }
}


ob_start();
?>
<link rel="stylesheet" href="/adminconsole/assets/ui.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<link rel="stylesheet" href="https://unpkg.com/cropperjs@1.6.1/dist/cropper.css">
<script src="https://unpkg.com/cropperjs@1.6.1/dist/cropper.js"></script>


<style>
  .table a.ref-link {
    text-decoration: none;
    color: #3b82f6;
  }

  .table a.ref-link:hover {
    text-decoration: underline;
  }

  body {
    overflow-x: hidden;
  }

  .master-wrap .card .table-wrap {
    width: 100%;
    overflow-x: hidden;
  }

  .master-wrap .card .table-wrap .table {
    width: 100%;
    max-width: 100%;
    min-width: 0 !important;
    table-layout: fixed;
  }

  .master-wrap .card .table-wrap .table th,
  .master-wrap .card .table-wrap .table td {
    word-wrap: break-word;
    word-break: break-word;
  }

  /* Cards (same as candidate list) */
  .cards-row {
    display: flex;
    gap: 12px;
    overflow: auto;
    padding: 6px 2px 10px;
  }

  .stat-card {
    display: block;
    min-width: 190px;
    padding: 14px 14px;
    border: 1px solid #233045;
    border-radius: 14px;
    background: #0b1220;
    color: #e5e7eb;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
    text-decoration: none;
  }

  .stat-card:hover {
    border-color: #3b82f6;
  }

  .stat-card.active {
    border-color: #22c55e;
    box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.25);
  }

  .stat-title {
    color: #9ca3af;
    font-size: 12px;
    margin-bottom: 6px;
  }

  .stat-num {
    font-size: 28px;
    font-weight: 800;
    color: #fff;
    line-height: 1;
  }

  .btn.status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: .45rem .75rem;
    border-radius: .6rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
  }

  .btn.status.inreview {
    background: #f59e0b;
    color: #000;
  }

  .filter-box {
    margin-top: 18px;
    background: #0f172a;
    padding: 18px;
    border-radius: 14px;
    border: 1px solid #1e293b;
  }

  .filter-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 14px;
    margin-bottom: 14px;
  }

  .filter-row .inp {
    width: 100%;
  }

  .filter-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 8px;
  }

  @media (max-width: 1200px) {
    .filter-row {
      grid-template-columns: repeat(3, 1fr);
    }
  }

  @media (max-width: 768px) {
    .filter-row {
      grid-template-columns: repeat(1, 1fr);
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (window.flatpickr) {
      flatpickr(".js-date-ddmmyyyy", {
        dateFormat: "d-m-Y",
        allowInput: true
      });
    }




    let cropper;
    const logoInput = document.getElementById('logoInput');
    const cropModal = document.getElementById('cropModal');
    const cropImage = document.getElementById('cropImage');
    const cropSave = document.getElementById('cropSave');
    const cropCancel = document.getElementById('cropCancel');
    const croppedImageInput = document.getElementById('croppedImage');
    const logoForm = document.getElementById('logoForm');

    if (!logoInput) return;

    logoInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = function(event) {

        cropImage.src = event.target.result;
        cropModal.style.display = "flex";

        if (cropper) {
          cropper.destroy();
        }

        cropper = new Cropper(cropImage, {
          aspectRatio: 1,
          viewMode: 1,
          autoCropArea: 1
        });
      };
      reader.readAsDataURL(file);
    });

    cropSave.addEventListener('click', function() {

      if (!cropper) return;

      const canvas = cropper.getCroppedCanvas({
        width: 150,
        height: 150
      });

      croppedImageInput.value = canvas.toDataURL('image/png');

      cropper.destroy();
      cropper = null;

      cropModal.style.display = "none";

      logoForm.submit();
    });

    cropCancel.addEventListener('click', function() {

      if (cropper) {
        cropper.destroy();
        cropper = null;
      }

      cropModal.style.display = "none";
      logoInput.value = ""; // clear file selection
    });

  });
</script>

<div class="master-wrap">
  <div class="headbar">
    <h2 style="margin:0"><?= htmlspecialchars($page_title) ?></h2>
  </div>
  <div class="card">
    <?php
    /* --------- PHP helpers ---------- */
    function h($s)
    {
      return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
    function keep_params(array $changes = [])
    {
      $qs = $_GET;
      foreach ($changes as $k => $v) {
        if ($v === null) {
          unset($qs[$k]);
        } else {
          $qs[$k] = $v;
        }
      }
      $q = http_build_query($qs);
      return $q ? ('?' . $q) : '';
    }
    function get_int($key, $default = 0)
    {
      return isset($_GET[$key]) ? (int)$_GET[$key] : $default;
    }
    function get_str($key, $default = '')
    {
      return isset($_GET[$key]) ? trim((string)$s = $_GET[$key]) : $default;
    } // keep as-is
    function parse_ddmmyyyy_to_ymd($s)
    {
      $s = trim((string)$s);
      if ($s === '') return null;
      if (!preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $s, $m)) return null;
      return $m[3] . '-' . $m[2] . '-' . $m[1];
    }

    /* ================== small stmt fetch helper (works without mysqlnd) ================== */
    function stmt_fetch_all_assoc(mysqli_stmt $stmt)
    {
      $meta = $stmt->result_metadata();
      if (!$meta) return [];
      $fields = [];
      $row = [];
      $bind = [];
      while ($f = $meta->fetch_field()) {
        $fields[] = $f->name;
        $row[$f->name] = null;
        $bind[] = &$row[$f->name];
      }
      call_user_func_array([$stmt, 'bind_result'], $bind);
      $out = [];
      while ($stmt->fetch()) {
        $copy = [];
        foreach ($fields as $f) {
          $copy[$f] = $row[$f];
        }
        $out[] = $copy;
      }
      return $out;
    }

    /* ----------------- MODE SWITCH: LIST vs PROFILE ----------------- */
    $mode = isset($_GET['mode']) ? trim((string)$_GET['mode']) : 'list';

    if ($mode === 'profile') {


      $rid = get_int('rid', 0);
      $back_url = $_SERVER['PHP_SELF'];
      /* ================= LOGO UPLOAD ================= */
      if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['upload_logo'])
        && !empty($_POST['cropped_image'])
        && $rid > 0
      ) {

        $imageData = $_POST['cropped_image'];

        // Remove base64 prefix
        $imageData = str_replace('data:image/png;base64,', '', $imageData);
        $imageData = str_replace(' ', '+', $imageData);
        $decoded = base64_decode($imageData);

        if ($decoded === false) {
          die("Invalid cropped image data.");
        }

        $newName = 'recruiter_' . $rid . '_' . time() . '.png';

        $uploadDir = __DIR__ . '/../../webservices/uploads/company_logos/';
        if (!is_dir($uploadDir)) {
          mkdir($uploadDir, 0755, true);
        }

        $targetPath = $uploadDir . $newName;

        file_put_contents($targetPath, $decoded);

        $dbPath = 'uploads/company_logos/' . $newName;

        $up = $con->prepare("UPDATE jos_app_recruiter_profile SET company_logo=? WHERE id=?");
        $up->bind_param("si", $dbPath, $rid);
        $up->execute();
        $up->close();

        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
      }
      if ($rid <= 0) {
        echo '<div class="alert danger">Invalid recruiter ID.</div>';
        echo '<div style="margin-top:10px"><a class="btn secondary" href="' . h($back_url) . '">Back to list</a></div>';
        echo '</div></div>';
        echo ob_get_clean();
        exit;
      }

      // ---- MUST belong to this account manager ----
      if ($logged_admin_id <= 0) {
        echo '<div class="alert danger">Invalid logged-in user.</div>';
        echo '</div></div>';
        echo ob_get_clean();
        exit;
      }

      $chk = $con->prepare("
        SELECT 1
        FROM jos_app_users u
        WHERE u.profile_type_id = 1
          AND u.profile_id = ?
          AND u.ac_manager_id = ?
        LIMIT 1
    ");
      $chk->bind_param("ii", $rid, $logged_admin_id);
      $chk->execute();
      $chk->store_result();
      $ok = ($chk->num_rows > 0);
      $chk->close();

      if (!$ok) {
        echo '<div class="alert warn">No records assigned.</div>';
        echo '<div style="margin-top:10px"><a class="btn secondary" href="' . h($back_url) . '">Back to list</a></div>';
        echo '</div></div>';
        echo ob_get_clean();
        exit;
      }

      /* ====== Recruiter profile ====== */
      $sql = "
        SELECT 
            r.*, 
            u.id AS userid,
            u.active_plan_id
        FROM jos_app_recruiter_profile r
        LEFT JOIN jos_app_users u ON u.profile_id = r.id AND u.profile_type_id = 1
        WHERE r.id = ?
    ";
      $stmt = $con->prepare($sql);
      $stmt->bind_param("i", $rid);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $stmt->close();

        $data['city_name'] = $data['city_id'];
        $data['locality_name'] = $data['locality_id'];

        if (!empty($data['company_logo'])) {
          $data['company_logo'] = rtrim(DOMAIN_URL, "/") . "/webservices/" . ltrim($data['company_logo'], "/");
        }

        $userid = (int)($data['userid'] ?? 0);
        $active_plan_id = (int)($data['active_plan_id'] ?? 0);

        $subscription = [
          "status" => "no_subscription",
          "valid_from" => null,
          "valid_to" => null,
          "plan_name" => null,
          "validity_months" => null,
          "is_expired" => null
        ];

        if ($userid > 0 && $active_plan_id > 0) {
          $sub_stmt = $con->prepare("
                SELECT log.start_date, log.end_date, plan.plan_name, plan.validity_months 
                FROM jos_app_usersubscriptionlog log
                LEFT JOIN jos_app_subscription_plans plan ON plan.id = log.plan_id
                WHERE log.userid = ? AND log.plan_id = ? AND log.payment_status = 'success'
                ORDER BY log.start_date DESC
                LIMIT 1
            ");
          $sub_stmt->bind_param("ii", $userid, $active_plan_id);
          $sub_stmt->execute();
          $sub_result = $sub_stmt->get_result();

          if ($sub_result && $sub_result->num_rows > 0) {
            $sub = $sub_result->fetch_assoc();
            $valid_from = !empty($sub['start_date']) ? date("d-m-Y", strtotime($sub['start_date'])) : null;
            $valid_to   = !empty($sub['end_date'])   ? date("d-m-Y", strtotime($sub['end_date']))   : null;
            $is_expired = (!empty($sub['end_date']) && $sub['end_date'] < date("Y-m-d"));

            $subscription = [
              "status" => "active",
              "valid_from" => $valid_from,
              "valid_to" => $valid_to,
              "plan_name" => $sub['plan_name'],
              "validity_months" => $sub['validity_months'],
              "is_expired" => $is_expired
            ];
          }
          $sub_stmt->close();
        }

        echo '<div style="margin-bottom:12px;"><a class="btn secondary" href="' . h($back_url) . '">← Back to Recruiter List</a></div>';

        echo '<div style="display:flex; gap:18px; align-items:flex-start;">';

        // echo '<div style="width:140px; height:140px; background:#f3f4f6; border-radius:12px; display:flex;align-items:center;justify-content:center;overflow:hidden;">';                // if (!empty($data['company_logo'])) {
        //   echo '<img src="' . h($data['company_logo']) . '" style="width:100%;height:100%;object-fit:contain;" alt="Logo">';
        // } else {
        //   echo '<span style="color:#9ca3af;font-size:12px;">No Logo</span>';
        // }

        echo '<div style="width:160px;">';

        if (!empty($data['company_logo'])) {

          echo '<div style="width:140px;height:140px;background:#f3f4f6;border-radius:12px;display:flex;align-items:center;justify-content:center;overflow:hidden;">';
          echo '<img src="' . h($data['company_logo']) . '" style="width:100%;height:100%;object-fit:contain;" alt="Logo">';
          echo '</div>';

          // Optional: Replace image button
          echo '
<form id="logoForm" method="post" style="margin-top:8px;">
    <input type="file" id="logoInput" accept="image/*" required>
    <input type="hidden" name="cropped_image" id="croppedImage">
    <input type="hidden" name="upload_logo" value="1">
</form>';
        } else {

          echo '<div style="width:140px;height:140px;background:#111827;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#9ca3af;">
            No Logo
          </div>';

          echo '
<form id="logoForm" method="post" style="margin-top:8px;">
    <input type="file" id="logoInput" accept="image/*" required>
    <input type="hidden" name="cropped_image" id="croppedImage">
    <input type="hidden" name="upload_logo" value="1">
</form>';
        }



        echo '<div id="cropModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;padding:20px;border-radius:12px;max-width:500px;width:90%;">
      <h3>Crop Logo (150 x 150)</h3>
      <div>
          <img id="cropImage" style="max-width:100%;">
      </div>
      <div style="margin-top:15px;text-align:right;">
          <button id="cropCancel" class="btn secondary">Cancel</button>
          <button id="cropSave" class="btn primary">Crop & Upload</button>
      </div>
  </div>
</div>';
        echo '<hr style="margin:16px 0;">';

        echo '</div>';




        echo '</div>';

        echo '<div style="flex:1;">';
        echo '<h3 style="margin:0 0 6px;">' . h($data['organization_name'] ?: 'N/A') . '</h3>';
        echo '<div style="margin-bottom:4px;"><strong>Contact Person:</strong> ' . h($data['contact_person_name'] ?? '') . '</div>';
        echo '<div style="margin-bottom:4px;"><strong>Designation:</strong> ' . h($data['designation'] ?? '') . '</div>';
        echo '<div style="margin-bottom:4px;"><strong>Mobile:</strong> ' . h($data['contact_no'] ?? $data['mobile_no'] ?? '') . '</div>';
        echo '<div style="margin-bottom:4px;"><strong>Email:</strong> ' . h($data['email'] ?? '') . '</div>';
        echo '<div style="margin-bottom:4px;"><strong>Alt. Email:</strong> ' . h($data['alternate_email'] ?? '') . '</div>';
        echo '</div>';

        echo '</div>';

        echo '<hr style="margin:16px 0;">';

        echo '<div style="display:flex;flex-wrap:wrap;gap:16px;">';
        echo '<div><strong>City:</strong> ' . h($data['city_name']) . '</div>';
        echo '<div><strong>Locality:</strong> ' . h($data['locality_name']) . '</div>';
        echo '<div><strong>Pincode:</strong> ' . h($data['pincode'] ?? '') . '</div>';
        echo '<div style="flex-basis:100%;"><strong>Address:</strong> ' . h($data['address'] ?? '') . '</div>';
        echo '</div>';

        echo '<hr style="margin:16px 0;">';

        echo '<h3 style="margin-top:0;">Subscription</h3>';
        $badge = '<span class="badge">No subscription</span>';
        if ($subscription['status'] === 'active') {
          $badge = $subscription['is_expired']
            ? '<span class="badge danger">Expired</span>'
            : '<span class="badge success">Active</span>';
        }
        echo '<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">';
        echo '<div><strong>Plan:</strong> ' . h($subscription['plan_name'] ?? 'No Plan') . '</div>';
        echo '<div><strong>Valid From:</strong> ' . h($subscription['valid_from'] ?? '-') . '</div>';
        echo '<div><strong>Valid To:</strong> ' . h($subscription['valid_to'] ?? '-') . '</div>';
        echo '<div>' . $badge . '</div>';
        echo '</div>';
      } else {
        if ($stmt) {
          $stmt->close();
        }
        echo '<div class="alert danger">Recruiter not found.</div>';
        echo '<div style="margin-top:10px"><a class="btn secondary" href="' . h($back_url) . '">Back to list</a></div>';
      }

      echo '</div></div>';
      echo ob_get_clean();
      exit;
    }

    /* ====================== LIST MODE BELOW ======================= */

    if ($logged_admin_id <= 0) {
      echo '<div class="alert danger">Invalid logged-in user.</div>';
      echo '</div></div>';
      echo ob_get_clean();
      exit;
    }

    /* ---- filters ---- */
    $q                   = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $city_id             = isset($_GET['city_id']) ? trim((string)$_GET['city_id']) : '';
    $status_id           = isset($_GET['status_id']) ? (int)$_GET['status_id'] : 1;
    $kyc_status_id = isset($_GET['kyc_status_id']) ? $_GET['kyc_status_id'] : '';
    $referral_code_in    = isset($_GET['referral_code']) ? trim((string)$_GET['referral_code']) : '';
    $plan_access_in      = isset($_GET['plan_access']) ? (int)$_GET['plan_access'] : 0;
    $subscription_status = isset($_GET['subscription_status']) ? strtolower(trim((string)$_GET['subscription_status'])) : '';
    $image_filter = isset($_GET['image_filter']) ? trim($_GET['image_filter']) : '';

    $created_from_raw    = isset($_GET['created_from']) ? trim((string)$_GET['created_from']) : '';
    $created_to_raw      = isset($_GET['created_to']) ? trim((string)$_GET['created_to']) : '';
    $created_from = parse_ddmmyyyy_to_ymd($created_from_raw);
    $created_to   = parse_ddmmyyyy_to_ymd($created_to_raw);

    $sort = isset($_GET['sort']) ? trim((string)$_GET['sort']) : 'newest';
    $view = isset($_GET['view']) ? trim((string)$_GET['view']) : 'last50';
    $page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $per_page = ($view === 'all') ? 1000 : 50;
    $offset = ($page - 1) * $per_page;

    /* ---- Plan card filter ---- */
    $plan_id_filter = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;

    /* ---- Recruiter plans (profile_type=1) for cards ---- */
    $subscription_plan_opts = [];
    if ($rs = mysqli_query($con, "
    SELECT id, plan_name
    FROM jos_app_subscription_plans
    WHERE profile_type = 1
    ORDER BY plan_name
")) {
      while ($r = mysqli_fetch_assoc($rs)) {
        $subscription_plan_opts[] = $r;
      }
    }

    /* ---- Quick check: any assigned? ---- */
    $chk = $con->prepare("SELECT 1 FROM jos_app_users WHERE profile_type_id=1 AND ac_manager_id=? LIMIT 1");
    $chk->bind_param("i", $logged_admin_id);
    $chk->execute();
    $chk->store_result();
    $has_assigned = ($chk->num_rows > 0);
    $chk->close();

    if (!$has_assigned) {
      echo '<div class="alert warn">No records assigned.</div>';
      echo '</div></div>';
      echo ob_get_clean();
      exit;
    }

    /* ---- build SQL ---- */
    $sql_base = "
  FROM jos_app_users u
  LEFT JOIN jos_app_recruiter_profile rp ON (u.profile_type_id=1 AND rp.id=u.profile_id)

  LEFT JOIN jos_app_users ur ON ur.id = u.referred_by
  LEFT JOIN jos_app_recruiter_profile rrp ON (ur.profile_type_id=1 AND rrp.id=ur.profile_id)
  LEFT JOIN jos_app_candidate_profile  rcp ON (ur.profile_type_id=2 AND rcp.id=ur.profile_id)
  LEFT JOIN jos_app_promoter_profile   rpp ON (ur.profile_type_id=3 AND rpp.id=ur.profile_id)

  LEFT JOIN (
    SELECT x.userid, x.plan_id, x.start_date, x.end_date
    FROM jos_app_usersubscriptionlog x
    INNER JOIN (
      SELECT userid, MAX(CONCAT(IFNULL(DATE_FORMAT(end_date,'%Y%m%d%H%i%s'),'00000000000000'), LPAD(id,10,'0'))) AS maxk
      FROM jos_app_usersubscriptionlog
      GROUP BY userid
    ) m ON m.userid=x.userid
       AND CONCAT(IFNULL(DATE_FORMAT(x.end_date,'%Y%m%d%H%i%s'),'00000000000000'), LPAD(x.id,10,'0'))=m.maxk
  ) ls ON ls.userid = u.id
  LEFT JOIN jos_app_subscription_plans sp ON sp.id = COALESCE(ls.plan_id, u.active_plan_id)
  LEFT JOIN (
    SELECT referred_by AS uid, COUNT(*) AS total_referrals
    FROM jos_app_users
    WHERE referred_by IS NOT NULL AND referred_by<>0
    GROUP BY referred_by
  ) rc ON rc.uid = u.id
";

    /* ===== KYC Latest Status Join ===== */
    $sql_base .= "
LEFT JOIN (
    SELECT l1.*
    FROM jos_app_recruiterkyclog l1
    INNER JOIN (
        SELECT recruiter_id, MAX(id) AS max_id
        FROM jos_app_recruiterkyclog
        GROUP BY recruiter_id
    ) l2 ON l1.id = l2.max_id
) kyc ON kyc.recruiter_id = rp.id

LEFT JOIN jos_app_kycstatus ks ON ks.id = kyc.status
";

    $where_common = [];
    $types_common = '';
    $params_common = [];

    $where_common[] = "u.profile_type_id = 1";
    $where_common[] = "u.ac_manager_id = ?";
    $types_common  .= "i";
    $params_common[] = $logged_admin_id;

    if ($q !== '') {
      $where_common[] = "(u.mobile_no LIKE CONCAT('%',?,'%')
          OR u.referral_code LIKE CONCAT('%',?,'%')
          OR u.myreferral_code LIKE CONCAT('%',?,'%')
          OR rp.organization_name LIKE CONCAT('%',?,'%')
          OR rp.contact_person_name LIKE CONCAT('%',?,'%'))";
      $types_common .= 'sssss';
      $params_common = array_merge($params_common, array_fill(0, 5, $q));
    }

    if ($city_id !== '') {
      $where_common[] = "u.city_id LIKE CONCAT('%',?,'%')";
      $types_common .= 's';
      $params_common[] = $city_id;
    }
    if ($status_id >= 0) {
      $where_common[] = "u.status_id=?";
      $types_common .= 'i';
      $params_common[] = $status_id;
    }
    /* ===== KYC Filter ===== */
    if ($kyc_status_id !== '') {

      if ($kyc_status_id === 'NOT_SUBMITTED') {
        $where_common[] = "kyc.id IS NULL";
      } else {
        $where_common[] = "kyc.status = ?";
        $types_common .= 'i';
        $params_common[] = (int)$kyc_status_id;
      }
    }
    if ($referral_code_in !== '') {
      $where_common[] = "u.referral_code=?";
      $types_common .= 's';
      $params_common[] = $referral_code_in;
    }
    if ($plan_access_in > 0) {
      $where_common[] = "CAST(sp.plan_access AS UNSIGNED)=?";
      $types_common .= 'i';
      $params_common[] = $plan_access_in;
    }

    if ($subscription_status === 'active') {
      $where_common[] = "(ls.end_date IS NOT NULL AND ls.end_date>=NOW())";
    } elseif ($subscription_status === 'expired') {
      $where_common[] = "(ls.end_date IS NOT NULL AND ls.end_date<NOW())";
    }

    if ($created_from) {
      $where_common[] = "DATE(u.created_at)>=?";
      $types_common .= 's';
      $params_common[] = $created_from;
    }
    if ($created_to) {
      $where_common[] = "DATE(u.created_at)<=?";
      $types_common .= 's';
      $params_common[] = $created_to;
    }
    /* ===== Image Filter ===== */
    if ($image_filter === 'available') {
      $where_common[] = "rp.company_logo IS NOT NULL AND rp.company_logo <> ''";
    } elseif ($image_filter === 'not_available') {
      $where_common[] = "(rp.company_logo IS NULL OR rp.company_logo = '')";
    }

    $sql_where_common = ' WHERE ' . implode(' AND ', $where_common);

    /* ===================== PLAN CARDS COUNTS (group by sp.id) ===================== */
    $plan_counts = [];
    $cards_total = 0;

    $sql_cards = "
  SELECT sp.id AS plan_id, COUNT(DISTINCT u.id) AS cnt
  " . $sql_base . $sql_where_common . "
  GROUP BY sp.id
";
    $stmt = $con->prepare($sql_cards);
    if ($types_common !== '') {
      $stmt->bind_param($types_common, ...$params_common);
    }
    $stmt->execute();
    $rows_cards = stmt_fetch_all_assoc($stmt);
    $stmt->close();

    foreach ($rows_cards as $r) {
      $pid = (int)($r['plan_id'] ?? 0);
      $cnt = (int)($r['cnt'] ?? 0);
      if ($pid > 0) $plan_counts[$pid] = $cnt;
      $cards_total += $cnt;
    }

    /* ---- apply optional plan_id filter to list ---- */
    $where = $where_common;
    $types = $types_common;
    $params = $params_common;

    if ($plan_id_filter > 0) {
      $where[] = "sp.id = ?";
      $types  .= "i";
      $params[] = $plan_id_filter;
    }
    $sql_where = ' WHERE ' . implode(' AND ', $where);

    /* sort */
    switch ($sort) {
      case 'oldest':
        $order = ' ORDER BY u.id ASC';
        break;
      case 'name_asc':
        $order = " ORDER BY COALESCE(NULLIF(rp.organization_name,''), NULLIF(rp.contact_person_name,''), u.mobile_no) ASC";
        break;
      case 'name_desc':
        $order = " ORDER BY COALESCE(NULLIF(rp.organization_name,''), NULLIF(rp.contact_person_name,''), u.mobile_no) DESC";
        break;
      case 'city_asc':
        $order = ' ORDER BY u.city_id ASC, u.id DESC';
        break;
      case 'city_desc':
        $order = ' ORDER BY u.city_id DESC, u.id DESC';
        break;
      default:
        $order = ' ORDER BY u.id DESC';
    }

    /* total count */
    $sql_count = "SELECT COUNT(DISTINCT u.id) AS c " . $sql_base . $sql_where;
    $stmt = $con->prepare($sql_count);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = 0;
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    /* page clamp */
    if ($view !== 'all') {
      $pages = max(1, (int)ceil($total / $per_page));
      if ($page > $pages) {
        $page = $pages;
        $offset = ($page - 1) * $per_page;
      }
    } else {
      $pages = 1;
      $page = 1;
      $offset = 0;
    }

    /* main query */
    $sql = "
SELECT
  u.id, u.mobile_no, u.profile_id, u.city_id, u.referral_code, u.myreferral_code,
  u.referred_by, u.active_plan_id, u.status_id, u.created_at,

  rp.organization_name, rp.contact_person_name, rp.designation,rp.company_logo,

  ls.plan_id AS last_plan_id, ls.start_date AS last_start_date, ls.end_date AS last_end_date,
  sp.id AS plan_id, sp.plan_name AS plan_name, CAST(sp.plan_access AS UNSIGNED) AS plan_access_num,

  IFNULL(rc.total_referrals,0) AS total_referrals,
  (SELECT COUNT(*) FROM jos_app_walkininterviews w WHERE w.recruiter_id = rp.id) AS premium_jobs_count,
  (SELECT COUNT(*) FROM jos_app_jobvacancies jv WHERE jv.recruiter_id = rp.id)    AS standard_jobs_count, 
  ks.name AS kyc_status_name,
  ks.colorcode AS kyc_color,
  kyc.status AS kyc_status_id,
  ur.mobile_no AS ref_mobile,
  COALESCE(
    NULLIF(rrp.organization_name,''),
    NULLIF(rrp.contact_person_name,''),
    NULLIF(rcp.candidate_name,''),
    NULLIF(rpp.name,''),
    ur.mobile_no
  ) AS ref_name
" . $sql_base . $sql_where . $order . ($view === 'all' ? "" : " LIMIT $per_page OFFSET $offset");

    $stmt = $con->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    ?>

    <!-- ================== PLAN CARDS UI ================== -->
    <div class="cards-row">
      <a class="stat-card <?= ($plan_id_filter == 0 ? 'active' : '') ?>"
        href="<?= h(keep_params(['plan_id' => null, 'page' => 1])) ?>">
        <div class="stat-title">Total Records</div>
        <div class="stat-num"><?= (int)$cards_total ?></div>
      </a>

      <?php foreach ($subscription_plan_opts as $p):
        $pid = (int)$p['id'];
        $cnt = (int)($plan_counts[$pid] ?? 0);
        // If you want to hide 0 count plans, uncomment:
        // if ($cnt === 0) continue;
        $isActive = ($plan_id_filter === $pid);
      ?>
        <a class="stat-card <?= $isActive ? 'active' : '' ?>"
          href="<?= h(keep_params(['plan_id' => $pid, 'page' => 1])) ?>">
          <div class="stat-title"><?= h($p['plan_name']) ?></div>
          <div class="stat-num"><?= $cnt ?></div>
        </a>
      <?php endforeach; ?>
    </div>

    <form method="get" class="filter-box">

      <input type="hidden" name="plan_id" value="<?= (int)$plan_id_filter ?>">

      <!-- ROW 1 -->
      <div class="filter-row">
        <input class="inp" type="text" name="q"
          value="<?= h($q) ?>"
          placeholder="Search name/mobile/referral/org...">

        <input class="inp" type="text" name="city_id"
          value="<?= h($city_id) ?>"
          placeholder="City Name">

        <select class="inp" name="status_id">
          <option value="1" <?= $status_id === 1 ? 'selected' : '' ?>>Active</option>
          <option value="0" <?= $status_id === 0 ? 'selected' : '' ?>>Inactive</option>
          <option value="-1" <?= $status_id === -1 ? 'selected' : '' ?>>Any Status</option>
        </select>

        <select class="inp" name="kyc_status_id">
          <option value="">All KYC Status</option>
          <?php foreach ($kycStatuses as $st): ?>
            <option value="<?= (int)$st['id'] ?>"
              <?= ($kyc_status_id !== '' && (int)$kyc_status_id === (int)$st['id']) ? 'selected' : '' ?>>
              <?= h($st['name']) ?>
            </option>
          <?php endforeach; ?>
          <option value="NOT_SUBMITTED"
            <?= ($kyc_status_id === 'NOT_SUBMITTED') ? 'selected' : '' ?>>
            Not Submitted
          </option>
        </select>

        <select class="inp" name="subscription_status">
          <option value="">Subscription: Any</option>
          <option value="active" <?= $subscription_status === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="expired" <?= $subscription_status === 'expired' ? 'selected' : '' ?>>Expired</option>
        </select>
      </div>

      <!-- ROW 2 -->
      <div class="filter-row">
        <input class="inp js-date-ddmmyyyy" type="text"
          name="created_from"
          value="<?= h($created_from_raw) ?>"
          placeholder="Reg Date From">

        <input class="inp js-date-ddmmyyyy" type="text"
          name="created_to"
          value="<?= h($created_to_raw) ?>"
          placeholder="Reg Date To">

        <input class="inp" type="text"
          name="referral_code"
          value="<?= h($referral_code_in) ?>"
          placeholder="Referral Code">

        <select class="inp" name="image_filter">
          <option value="">Image: All</option>
          <option value="available" <?= $image_filter === 'available' ? 'selected' : '' ?>>Available</option>
          <option value="not_available" <?= $image_filter === 'not_available' ? 'selected' : '' ?>>Not Available</option>
        </select>

        <select class="inp" name="sort">
          <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
          <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
          <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A–Z</option>
          <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z–A</option>
        </select>
      </div>

      <!-- ROW 3 BUTTONS -->
      <div class="filter-actions">
        <button class="btn primary" type="submit">Apply Filters</button>
        <a class="btn secondary" href="<?= h(keep_params(['view' => 'last50', 'page' => 1])) ?>">Last 50</a>
        <a class="btn secondary" href="<?= h(keep_params(['view' => 'all', 'page' => 1])) ?>">View All</a>
      </div>

    </form>



    <div style="display:flex;align-items:center;gap:12px;margin:8px 0 12px">
      <span class="badge">Total: <?= (int)$total ?></span>
      <span class="badge">Showing: <?= ($view === 'all') ? 'All' : ($res->num_rows) ?></span>
      <?php if ($plan_id_filter > 0) { ?><span class="badge">Plan Filter: #<?= (int)$plan_id_filter ?></span><?php } ?>
      <?php if ($view !== 'all') { ?>
        <div style="margin-left:auto;display:flex;gap:6px;align-items:center">
          <?php if ($page > 1) { ?><a class="btn secondary" href="<?= h(keep_params(['page' => $page - 1])) ?>">‹ Prev</a><?php } ?>
          <span class="badge">Page <?= (int)$page ?> / <?= (int)$pages ?></span>
          <?php if ($page < $pages) { ?><a class="btn secondary" href="<?= h(keep_params(['page' => $page + 1])) ?>">Next ›</a><?php } ?>
        </div>
      <?php } ?>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th style="width:60px">SR No</th>
            <th>Reg Date</th>
            <th>Name / Profile</th>
            <th>Image</th>
            <th>Contact Info</th>
            <th>Mobile</th>
            <th>Referred By</th>
            <th>Plan / Subscr.</th>
            <th>KYC Status</th>
            <th>Premium Jobs</th>
            <th>Standard Jobs</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $sr = ($view === 'all') ? 1 : ($offset + 1);
          while ($row = $res->fetch_assoc()):
            $display = $row['organization_name'] ?: $row['contact_person_name'] ?: $row['mobile_no'];

            $contact_parts = [];
            if (!empty($row['contact_person_name'])) $contact_parts[] = $row['contact_person_name'];
            if (!empty($row['designation'])) $contact_parts[] = $row['designation'];
            $contact_info = implode(' | ', $contact_parts);
            if ($contact_info === '') $contact_info = '—';

            if (!empty($row['plan_name'])) {
              $plan_label = $row['plan_name'] . ' ' . (((int)$row['plan_access_num'] == 2) ? '(Premium)' : '(Free)');
            } elseif (!empty($row['plan_id'])) {
              $plan_label = 'Plan #' . (int)$row['plan_id'] . ' ' . (((int)$row['plan_access_num'] == 2) ? '(Premium)' : '(Free)');
            } elseif (!empty($row['active_plan_id'])) {
              $plan_label = 'Plan #' . (int)$row['active_plan_id'];
            } else $plan_label = 'No plan';

            $sub_status = 'No subscription';
            $sub_status_class = 'badge';
            $tooltip_lines = [];

            if ($row['last_start_date'] || $row['last_end_date']) {
              $startTxt = $row['last_start_date'] ? date('d M Y', strtotime($row['last_start_date'])) : '—';
              $endTxt   = $row['last_end_date']   ? date('d M Y', strtotime($row['last_end_date']))   : '—';

              if ($row['last_end_date'] && strtotime($row['last_end_date']) >= time()) {
                $sub_status = 'Active';
                $sub_status_class = 'badge success';
              } else {
                $sub_status = 'Expired';
                $sub_status_class = 'badge warn';
              }

              $tooltip_lines[] = 'Plan: ' . $plan_label;
              $tooltip_lines[] = 'Start: ' . $startTxt;
              $tooltip_lines[] = 'End: ' . $endTxt;
            } else {
              $tooltip_lines[] = 'Plan: ' . $plan_label;
              $tooltip_lines[] = 'No subscription log found';
            }
            $tooltip = implode("\n", $tooltip_lines);

            $refByDisplay = '—';
            $refLinkHref = null;
            if (!empty($row['referred_by'])) {
              $refName   = trim((string)($row['ref_name'] ?? ''));
              $refMobile = trim((string)($row['ref_mobile'] ?? ''));
              if ($refName === '' && $refMobile === '') $refByDisplay = '#' . (int)$row['referred_by'];
              elseif ($refName !== '' && $refMobile !== '') $refByDisplay = h($refName) . ' (' . h($refMobile) . ')';
              else $refByDisplay = h($refName ?: $refMobile);
              $refLinkHref = keep_params(['q' => $refByDisplay, 'page' => 1]);
            }

            $status_badge = ((int)$row['status_id'] === 1)
              ? '<span class="badge success">Active</span>'
              : '<span class="badge danger">Inactive</span>';

            $recruiterProfileId = (int)$row['profile_id'];
            $premiumJobsCount   = (int)($row['premium_jobs_count'] ?? 0);
            $standardJobsCount  = (int)($row['standard_jobs_count'] ?? 0);

            // $premiumJobsUrl  = '/adminconsole/operations/premium_jobs_report.php?recruiter_id=' . $recruiterProfileId;
            // $standardJobsUrl = '/adminconsole/operations/standard_jobs_report.php?recruiter_id=' . $recruiterProfileId;

            $profileUrl = keep_params(['mode' => 'profile', 'rid' => $recruiterProfileId, 'page' => null]);
          ?>
            <tr>
              <td><?= (int)$sr++; ?></td>
              <td><?= h(date('d M Y', strtotime($row['created_at']))) ?></td>
              <td>
                <div style="font-weight:600"><?= h($display) ?></div>
                <div style="margin-top:4px"><?= $status_badge ?></div>
              </td>
              <td>
                <?php
                $image = trim((string)($row['company_logo'] ?? ''));

                if ($image !== '') {
                  $imageUrl = rtrim(DOMAIN_URL, "/") . "/webservices/" . ltrim($image, "/");
                  echo '<img src="' . h($imageUrl) . '" 
              style="width:50px;height:50px;object-fit:contain;border-radius:8px;background:#f3f4f6;">';
                } else {
                  echo '-';
                }
                ?>
              </td>
              <td><?= h($contact_info) ?></td>
              <td><?= h($row['mobile_no']) ?></td>
              <td>
                <?php if ($refLinkHref) { ?>
                  <a class="ref-link" href="<?= h($refLinkHref) ?>"><?= $refByDisplay ?></a>
                <?php } else {
                  echo $refByDisplay;
                } ?>
              </td>
              <td>
                <div><?= h($plan_label) ?></div>
                <div style="margin-top:4px;font-size:12px;">
                  <span class="<?= h($sub_status_class) ?>" title="<?= h($tooltip) ?>"><?= h($sub_status) ?></span>
                </div>
              </td>

              <!-- KYC -->
              <!-- <td>
                <?php
                $kycName  = $row['kyc_status_name'] ?? '';
                $kycId    = (int)($row['kyc_status_id'] ?? 0);
                $recruiterProfileId = (int)$row['profile_id'];

                if (!empty($kycName)) { ?>

                  <form method="post"
                    action="/adminconsole/operations/recruiter_kyc_report.php"
                    style="margin:0;">

                    <input type="hidden" name="recruiter_id" value="<?= $recruiterProfileId ?>">
                    <input type="hidden" name="status" value="<?= $kycId ?>">

                    <button type="submit"
                      class="ref-link"
                      style="background:none;border:none;padding:0;color:#3b82f6;cursor:pointer;">
                      <?= h($kycName) ?>
                    </button>
                  </form>

                <?php } else { ?>

                  <form method="post"
                    action="/adminconsole/operations/recruiter_kyc_report.php"
                    style="margin:0;">

                    <input type="hidden" name="recruiter_id" value="<?= $recruiterProfileId ?>">
                    <input type="hidden" name="status" value="NOT_SUBMITTED">

                    <button type="submit"
                      class="badge danger"
                      style="border:none;cursor:pointer;">
                      Not Submitted
                    </button>
                  </form>

                <?php } ?>
              </td> -->

              <td>
                <?php
                $kycName  = $row['kyc_status_name'] ?? '';
                $kycId    = (int)($row['kyc_status_id'] ?? 0);
                $kycColor = $row['kyc_color'] ?? '#555';
                $recruiterProfileId = (int)$row['profile_id'];

                if (!empty($kycName)) {

                  $isInReview = (strcasecmp($kycName, 'In Review') === 0);
                ?>

                  <form method="post"
                    action="/adminconsole/operations/recruiter_kyc_report.php"
                    style="margin:0;">

                    <input type="hidden" name="recruiter_id" value="<?= $recruiterProfileId ?>">
                    <input type="hidden" name="status" value="<?= $kycId ?>">

                    <button type="submit"
                      class="btn status <?= $isInReview ? 'inreview' : '' ?>"
                      style="<?= $isInReview ? '' : 'background:' . h($kycColor) . ';color:#fff;' ?>">
                      <?= h($kycName) ?>
                    </button>
                  </form>

                <?php } else { ?>

                  <form method="post"
                    action="/adminconsole/operations/recruiter_kyc_report.php"
                    style="margin:0;">

                    <input type="hidden" name="recruiter_id" value="<?= $recruiterProfileId ?>">
                    <input type="hidden" name="status" value="NOT_SUBMITTED">

                    <button type="submit"
                      class="btn"
                      style="background:#DC3545;color:#fff;">
                      Not Submitted
                    </button>
                  </form>

                <?php } ?>
              </td>


              <td>
                <?php if ($premiumJobsCount > 0) { ?>
                  <form method="post" action="/adminconsole/operations/premium_jobs_report.php" style="margin:0">
                    <input type="hidden" name="recruiter_id" value="<?= (int)$recruiterProfileId ?>">
                    <button type="submit" class="ref-link" style="background:none;border:none;padding:0;color:#3b82f6;cursor:pointer">
                      <?= $premiumJobsCount ?>
                    </button>
                  </form>
                <?php } else { ?>
                  <?= $premiumJobsCount ?>
                <?php } ?>
              </td>
              <td>
                <?php if ($standardJobsCount > 0) { ?>
                  <form method="post" action="/adminconsole/operations/standard_jobs_report.php" style="margin:0">
                    <input type="hidden" name="recruiter_id" value="<?= (int)$recruiterProfileId ?>">
                    <button type="submit" class="ref-link" style="background:none;border:none;padding:0;color:#3b82f6;cursor:pointer">
                      <?= $standardJobsCount ?>
                    </button>
                  </form>
                <?php } else { ?>
                  <?= $standardJobsCount ?>
                <?php } ?>
              </td>
              <td><a class="btn secondary" href="<?= h($profileUrl) ?>">View</a></td>
            </tr>
          <?php endwhile;
          $stmt->close(); ?>
          <?php if ($sr === (($view === 'all') ? 1 : ($offset + 1))) { ?>
            <tr>
              <td colspan="12" style="text-align:center;color:#9ca3af">No records found.</td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>

    <?php if ($view !== 'all') { ?>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
        <?php if ($page > 1) { ?><a class="btn secondary" href="<?= h(keep_params(['page' => $page - 1])) ?>">‹ Prev</a><?php } ?>
        <span class="badge">Page <?= (int)$page ?> / <?= (int)$pages ?></span>
        <?php if ($page < $pages) { ?><a class="btn secondary" href="<?= h(keep_params(['page' => $page + 1])) ?>">Next ›</a><?php } ?>
      </div>
    <?php } ?>

  </div>
</div>
<?php
echo ob_get_clean();
