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


// if($profile_type_id == 1)
// {
//     $stmt = $con->prepare("
//         SELECT u.mobile_no,
//                rp.*
//         FROM jos_app_users u
//         JOIN jos_app_recruiter_profile rp 
//             ON rp.id = u.profile_id
//         WHERE u.id=?
//     ");
// }
if ($profile_type_id == 2) {
    $stmt = $con->prepare("
        SELECT u.mobile_no,
               cp.*
        FROM jos_app_users u
        JOIN jos_app_candidate_profile cp 
            ON cp.userid=u.id
        WHERE u.id=?
    ");
}
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
    /* candidate profile fields */
    $candidate_name   = trim($_POST['candidate_name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $pan_no           = trim($_POST['pan_no'] ?? '');
    $aadhar_no        = trim($_POST['aadhar_no'] ?? '');
    $gender_id        = (int)($_POST['gender_id'] ?? 0);
    $birthdate        = trim($_POST['birthdate'] ?? '');
    $address          = trim($_POST['address'] ?? '');
    $district         = trim($_POST['district'] ?? '');
    $city_id          = trim($_POST['city_id'] ?? '');
    $locality_id      = trim($_POST['locality_id'] ?? '');
    $skills           = trim($_POST['skills'] ?? '');
    $exp_description  = trim($_POST['exp_description'] ?? '');
    $experience_type  = trim($_POST['experience_type'] ?? '');
    $experience_period = (int)($_POST['experience_period'] ?? 0);

    /* users table fields */
    $mobile_no = trim($_POST['mobile_no'] ?? '');
    $latitude  = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');

    $con->begin_transaction();

    try {

        /* =========================
           UPDATE candidate profile
        ========================== */

        $stmt = $con->prepare("
        UPDATE jos_app_candidate_profile SET
            candidate_name=?,
            email=?,
            pan_no=?,
            aadhar_no=?,
            gender_id=?,
            birthdate=?,
            address=?,
            district=?,
            city_id=?,
            locality_id=?,
            skills=?,
            exp_description=?,
            experience_type=?,
            experience_period=?
        WHERE id=?
        ");

        $stmt->bind_param(
            "ssssissssssssii",
            $candidate_name,
            $email,
            $pan_no,
            $aadhar_no,
            $gender_id,
            $birthdate,
            $address,
            $district,
            $city_id,
            $locality_id,
            $skills,
            $exp_description,
            $experience_type,
            $experience_period,
            $profile_id
        );

        $stmt->execute();
        $stmt->close();


        /* =========================
           UPDATE users table
        ========================== */

        $stmt2 = $con->prepare("
        UPDATE jos_app_users SET
            mobile_no=?,
            city_id=?,
            address=?,
            latitude=?,
            longitude=?
        WHERE id=?
        ");

        $stmt2->bind_param(
            "sssssi",
            $mobile_no,
            $city_id,
            $address,
            $latitude,
            $longitude,
            $user_id
        );

        $stmt2->execute();
        $stmt2->close();


        $con->commit();

        redirect_with_msg("Jobseeker profile updated successfully");
    } catch (Exception $e) {

        $con->rollback();

        redirect_with_msg("Update failed: " . $e->getMessage());
    }
}






/* =========================
   LOAD DATA FOR FORM
========================= */

if ($user_id <= 0) {
    die("Invalid access");
}

$stmt = $con->prepare("
SELECT u.mobile_no, cp.*
FROM jos_app_users u
JOIN jos_app_candidate_profile cp ON cp.userid=u.id
WHERE u.id=?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();
/* =========================
   LOAD GENDER LIST
========================= */

$gender_list = [];

$gstmt = $con->prepare("
    SELECT id, name
    FROM jos_crm_gender
    WHERE status=1
    ORDER BY name
");

$gstmt->execute();

$resg = $gstmt->get_result();

while ($rowg = $resg->fetch_assoc()) {
    $gender_list[] = $rowg;
}

$gstmt->close();

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
                    <label class="lbl">Candidate Name</label>
                    <input class="inp" name="candidate_name" value="<?= h($data['candidate_name']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Mobile</label>
                    <input class="inp" name="mobile_no" value="<?= h($data['mobile_no']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Email</label>
                    <input class="inp" name="email" value="<?= h($data['email']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">PAN No</label>
                    <input class="inp" name="pan_no" value="<?= h($data['pan_no']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Aadhar No</label>
                    <input class="inp" name="aadhar_no" value="<?= h($data['aadhar_no']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Gender</label>
                    <select class="inp" name="gender_id" required>
                        <option value="">Select Gender</option>
                        <?php foreach ($gender_list as $g): ?>
                            <option value="<?= $g['id'] ?>"
                                <?= ($data['gender_id'] == $g['id']) ? 'selected' : '' ?>>
                                <?= h($g['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="lbl">Birthdate</label>
                    <input class="inp" type="date" name="birthdate" value="<?= h($data['birthdate']) ?>">
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

                <div class="form-group full">
                    <label class="lbl">Skills</label>
                    <textarea class="inp" name="skills"><?= h($data['skills']) ?></textarea>
                </div>

                <div class="form-group full">
                    <label class="lbl">Experience Description</label>
                    <textarea class="inp" name="exp_description"><?= h($data['exp_description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="lbl">Experience Type</label>
                    <input class="inp" name="experience_type" value="<?= h($data['experience_type']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Experience Period</label>
                    <input class="inp" name="experience_period" value="<?= h($data['experience_period']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Latitude</label>
                    <input class="inp" name="latitude" value="<?= h($data['latitude']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Longitude</label>
                    <input class="inp" name="longitude" value="<?= h($data['longitude']) ?>">
                </div>

            </div>

            <div class="form-actions">
                <button class="btn primary" name="update_profile" value="1">
                    Update Profile
                </button>

                <a href="users.php" class="btn secondary">Cancel</a>
            </div>

        </form>

    </div>
</div>