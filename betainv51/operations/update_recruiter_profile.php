<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

global $con;

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect_with_msg($msg)
{
    header("Location: users.php?msg=" . urlencode($msg));
    exit;
}

/* =========================
   GET IDS FROM POST OR SELF
========================= */

$user_id = (int)($_POST['user_id'] ?? 0);
$profile_id = (int)($_POST['profile_id'] ?? 0);
$profile_type_id = (int)($_POST['profile_type_id'] ?? 0);


if ($profile_type_id == 1) {
    $stmt = $con->prepare("
        SELECT u.mobile_no,
               rp.*
        FROM jos_app_users u
        JOIN jos_app_recruiter_profile rp 
            ON rp.id = u.profile_id
        WHERE u.id=?
    ");
}
// elseif($profile_type_id == 2)
// {
//     $stmt = $con->prepare("
//         SELECT u.mobile_no,
//                cp.*
//         FROM jos_app_users u
//         JOIN jos_app_candidate_profile cp 
//             ON cp.id = u.profile_id
//         WHERE u.id=?
//     ");
// }
// elseif($profile_type_id == 3)
// {
//     $stmt = $con->prepare("
//         SELECT u.mobile_no,
//                pp.*
//         FROM jos_app_users u
//         JOIN jos_app_promoter_profile pp 
//             ON pp.id = u.profile_id
//         WHERE u.id=?
//     ");
// }
else {
    die("Invalid profile type");
}

/* =========================
   UPDATE MODE
========================= */

if (isset($_POST['update_profile'])) {
    if ($user_id <= 0 || $profile_id <= 0) {
        redirect_with_msg("Invalid user");
    }

    $organization_name   = trim($_POST['organization_name']);
    $contact_person_name = trim($_POST['contact_person_name']);
    $designation         = trim($_POST['designation']);
    $email               = trim($_POST['email']);
    $website             = trim($_POST['website']);
    $industry_type       = trim($_POST['industry_type']);
    $company_size        = (int)$_POST['company_size'];
    $established_year    = (int)$_POST['established_year'];
    $address             = trim($_POST['address']);
    $district            = trim($_POST['district']);
    $city_id             = trim($_POST['city_id']);
    $locality_id         = trim($_POST['locality_id']);
    $mobile_no           = trim($_POST['mobile_no']);

    $con->begin_transaction();

    try {

        /* recruiter profile update */
        $stmt = $con->prepare("
        UPDATE jos_app_recruiter_profile SET
        organization_name=?,
        contact_person_name=?,
        designation=?,
        email=?,
        website=?,
        industry_type=?,
        company_size=?,
        established_year=?,
        address=?,
        district=?,
        city_id=?,
        locality_id=?
        WHERE id=?
        ");

        $stmt->bind_param(
            "ssssssisssssi",
            $organization_name,
            $contact_person_name,
            $designation,
            $email,
            $website,
            $industry_type,
            $company_size,
            $established_year,
            $address,
            $district,
            $city_id,
            $locality_id,
            $profile_id
        );

        $stmt->execute();

        /* users table update */
        $stmt2 = $con->prepare("
        UPDATE jos_app_users SET
        mobile_no=?,
        city_id=?,
        address=?
        WHERE id=?
        ");

        $stmt2->bind_param(
            "sssi",
            $mobile_no,
            $city_id,
            $address,
            $user_id
        );

        $stmt2->execute();

        $con->commit();

        redirect_with_msg("Profile updated successfully");
    } catch (Exception $e) {

        $con->rollback();
        redirect_with_msg("Update failed");
    }
}

/* =========================
   LOAD DATA FOR FORM
========================= */

if ($user_id <= 0) {
    die("Invalid access");
}

$stmt = $con->prepare("
SELECT u.mobile_no, rp.*
FROM jos_app_users u
JOIN jos_app_recruiter_profile rp ON rp.id=u.profile_id
WHERE u.id=?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

?>

<link rel="stylesheet" href="/adminconsole/assets/ui.css">

<style>
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px 24px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group.full {
        grid-column: span 2;
    }

    .lbl {
        margin-bottom: 6px;
        color: #9ca3af;
    }

    .form-actions {
        margin-top: 20px;
        display: flex;
        gap: 10px;
    }
</style>

<div class="master-wrap">

    <div class="headbar">
        <h2>Edit Recruiter Profile</h2>
    </div>

    <div class="card" style="max-width:900px">

        <form method="post">

            <input type="hidden" name="user_id" value="<?= $user_id ?>">
            <input type="hidden" name="profile_id" value="<?= $profile_id ?>">
            <input type="hidden" name="profile_type_id" value="<?= $profile_type_id ?>">

            <div class="form-grid">

                <div class="form-group">
                    <label class="lbl">Organization Name</label>
                    <input class="inp" name="organization_name" value="<?= h($data['organization_name']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Contact Person</label>
                    <input class="inp" name="contact_person_name" value="<?= h($data['contact_person_name']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Designation</label>
                    <input class="inp" name="designation" value="<?= h($data['designation']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Email</label>
                    <input class="inp" name="email" value="<?= h($data['email']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Mobile</label>
                    <input class="inp" name="mobile_no" value="<?= h($data['mobile_no']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Website</label>
                    <input class="inp" name="website" value="<?= h($data['website']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Industry Type</label>
                    <input class="inp" name="industry_type" value="<?= h($data['industry_type']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Company Size</label>
                    <input class="inp" name="company_size" value="<?= h($data['company_size']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Established Year</label>
                    <input class="inp" name="established_year" value="<?= h($data['established_year']) ?>">
                </div>

                <div class="form-group full">
                    <label class="lbl">Address</label>
                    <textarea class="inp" name="address"><?= h($data['address']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="lbl">District</label>
                    <input class="inp" name="district" value="<?= h($data['district']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">City</label>
                    <input class="inp" name="city_id" value="<?= h($data['city_id']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Locality</label>
                    <input class="inp" name="locality_id" value="<?= h($data['locality_id']) ?>">
                </div>

            </div>

            <div class="form-actions">
                <button class="btn primary" name="update_profile">Update Profile</button>
                <a href="users.php" class="btn secondary">Cancel</a>
            </div>

        </form>

    </div>
</div>