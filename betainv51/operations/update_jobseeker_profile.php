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
} else {
    die("Invalid profile type");
}


/* =========================
   GET SKILLS FROM API
========================= */

$skills_list = [];

$position_post = json_encode([
    "position" => 9   // you can change dynamic later
]);

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://pacificconnect2.0.inv51.in/webservices/getMskill_list.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $position_post,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
    ],
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($curl);


if (!curl_errno($curl)) {
    $result = json_decode($response, true);

    if (!empty($result['status']) && $result['status'] == "success") {
        $skills_list = $result['data'];
    }
}

curl_close($curl);
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

    $job_position_ids = $_POST['job_position'] ?? [];
    $job_position_ids = array_map('intval', $job_position_ids);
    $job_position_ids_str = implode(',', $job_position_ids);

    // print_r( $job_position_ids_str );
    // exit;

    /* users table fields */
    $mobile_no = trim($_POST['mobile_no'] ?? '');
    // $latitude  = trim($_POST['latitude'] ?? '');
    // $longitude = trim($_POST['longitude'] ?? '');

    $con->begin_transaction();

    try {

        /* =========================
           UPDATE candidate profile
        ========================== */

        $stmt = $con->prepare("
        UPDATE jos_app_candidate_profile SET
            candidate_name=?,
            email=?,
            gender_id=?,
            birthdate=?,
            address=?,
            district=?,
            city_id=?,
            locality_id=?,
            skills=?,
            exp_description=?,
            experience_type=?,
            experience_period=?,
            job_position_ids=?
        WHERE id=?
        ");

        $stmt->bind_param(
            "ssissssssssisi",
            $candidate_name,
            $email,
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
            $job_position_ids_str,
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


/* ====================2. Get Job Positions========================== */

$job_positions = [];

$post_user = json_encode([
    "user_id" => $user_id
]);

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://pacificconnect2.0.inv51.in/webservices/getPosition.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post_user
]);

$response = curl_exec($curl);
curl_close($curl);

if ($response) {

    $result = json_decode($response, true);

    if ($result['status'] == "success") {

        $job_positions = $result['data']['position'];
    }
}

/* ====================4. Experience List========================== */

$experience_list = [];

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://pacificconnect.inv51.in/webservices/getExperience_list.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
]);

$response = curl_exec($curl);
curl_close($curl);

if ($response) {

    $result = json_decode($response, true);

    if ($result['status'] == "success") {

        $experience_list = $result['data'];
    }
}
/* ====================4. Experience TYPE========================== */

$experience_type = [];

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://pacificconnect.inv51.in/webservices/getExptype.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
]);

$expresponse = curl_exec($curl);
curl_close($curl);

if ($expresponse) {

    $expresult = json_decode($expresponse, true);

    if ($expresult['status'] == "success") {

        $experience_type = $expresult['data'];
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
    .form-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        /* ✅ 3 in row */
        gap: 18px 20px;
    }

    @media (max-width: 900px) {
        .form-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 600px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
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

    .multi-select {
        position: relative;
        width: 100%;
    }

    /* ================= DARK THEME SKILLS ================= */

    .select-box {
        border: 1px solid #334155;
        padding: 10px 12px;
        border-radius: 8px;
        cursor: pointer;
        background: #0f172a;
        /* dark */
        color: #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .select-box:after {
        content: "▼";
        font-size: 12px;
        color: #94a3b8;
    }

    /* dropdown */
    .checkbox-container {
        display: none;
        position: absolute;
        background: #0f172a;
        /* dark */
        border: 1px solid #334155;
        border-radius: 10px;
        max-height: 220px;
        overflow-y: auto;
        width: 100%;
        z-index: 9999;
        margin-top: 5px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
    }

    /* items */
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px;
        cursor: pointer;
        font-size: 14px;
        color: #e2e8f0;
    }

    .checkbox-item:hover {
        background: #1e293b;
        /* hover dark */
    }

    /* checkbox color */
    .checkbox-item input {
        accent-color: #3b82f6;
    }

    /* scrollbar */
    .checkbox-container::-webkit-scrollbar {
        width: 6px;
    }

    .checkbox-container::-webkit-scrollbar-thumb {
        background: #475569;
        border-radius: 10px;
    }

    /* LOCAITION SUGGESTION BOX CSS */
    .suggestion-box {
        position: absolute;
        width: 100%;
        background: #0b1220;
        border-radius: 8px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        max-height: 200px;
        overflow-y: auto;
        z-index: 9999;
        margin-top: 5px;
    }

    .suggestion-item {
        padding: 10px;
        cursor: pointer;
    }

    .suggestion-item:hover {
        background: #334155;
    }

    /* //job position css */
    /* Dropdown container */
    .custom-dropdown {
        position: relative;
        width: 100%;
    }

    /* Box */
    .dropdown-box {
        border: 1px solid #334155;
        padding: 10px 12px;
        border-radius: 8px;
        background: #0f172a;
        color: #e2e8f0;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Arrow */
    .dropdown-box .arrow {
        font-size: 12px;
        color: #94a3b8;
    }

    /* Dropdown list */
    .dropdown-list {
        display: none;
        position: absolute;
        width: 100%;
        background: #0f172a;
        border: 1px solid #334155;
        border-radius: 10px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 9999;
        margin-top: 5px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
    }

    /* Search box */
    .dropdown-search {
        width: 100%;
        padding: 10px;
        border: none;
        border-bottom: 1px solid #334155;
        background: #020617;
        color: #e2e8f0;
        outline: none;
    }

    /* Items */
    .dropdown-item {
        padding: 10px;
        cursor: pointer;
        color: #e2e8f0;
        font-size: 14px;
    }

    /* Hover */
    .dropdown-item:hover {
        background: #1e293b;
    }

    /* Selected */
    .dropdown-item.active {
        background: #2563eb;
        color: #fff;
        font-weight: bold;
    }

    /* Scrollbar */
    .dropdown-list::-webkit-scrollbar {
        width: 6px;
    }

    .dropdown-list::-webkit-scrollbar-thumb {
        background: #475569;
        border-radius: 10px;
    }
</style>

<div class="master-wrap">

    <div class="headbar">
        <h2>Edit Candidate Profile</h2>
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

                <!-- <div class="form-group">
                    <label class="lbl">PAN No</label>
                    <input class="inp" name="pan_no" value="<?= h($data['pan_no']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Aadhar No</label>
                    <input class="inp" name="aadhar_no" value="<?= h($data['aadhar_no']) ?>">
                </div> -->

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
                    <?php
                    $birthdate_val = '';

                    if (!empty($data['birthdate']) && $data['birthdate'] != '0000-00-00') {
                        $birthdate_val = date('d-m-Y', strtotime($data['birthdate']));
                    }
                    ?>
                    <input class="inp" value="<?= h($birthdate_val) ?>" placeholder="DD-MM-YYYY" type="text" id="birthdate" name="birthdate" value="<?= h($data['birthdate']) ?>">
                </div>

                <div class="form-group full">
                    <label class="lbl">Address</label>
                    <textarea class="inp" name="address"><?= h($data['address']) ?></textarea>
                </div>
                <br>




                <!-- ✅ IMPORTANT -->
                <div class="form-group">
                    <label class="input-label">District</label>
                    <input class="inp" type="text" id="profileDistrictInput" class="modal-input" autocomplete="off"
                        value="<?= h($data['district']) ?>">

                    <input type="hidden" name="district" id="profileDistrictId"
                        value="<?= h($data['district']) ?>">

                    <div id="profileDistrictSuggestions" class="suggestion-box"></div>
                </div>


                <div class="form-group">
                    <label class="input-label">Tehsil/City</label>
                    <input class="inp" type="text" id="profileCityInput" class="modal-input" autocomplete="off"
                        value="<?= h($data['city_id']) ?>">

                    <input type="hidden" name="city_id" id="profileCityId"
                        value="<?= h($data['city_id']) ?>">


                    <div id="profileCitySuggestions" class="suggestion-box"></div>
                </div>

                <div class="form-group">
                    <label class="input-label">Area/Locality/Village</label>
                    <input class="inp" type="text" id="profileLocalityInput" class="modal-input" autocomplete="off"
                        value="<?= h($data['locality_id']) ?>">

                    <input type="hidden" name="locality_id" id="profileLocalityId"
                        value="<?= h($data['locality_id']) ?>">
                    <div id="profileLocalitySuggestions" class="suggestion-box"></div>
                </div>



                <?php $selected_positions = [];

                if (!empty($data['job_position_ids'])) {
                    $selected_positions = explode(',', $data['job_position_ids']);
                } ?>
                <div class="form-group">
                    <label class="lbl">Job Position</label>

                    <div class="multi-select">
                        <div class="select-box" onclick="toggleJobDropdown()">
                            <span id="jobSelectedText">Select Job Positions</span>
                        </div>

                        <div class="checkbox-container" id="jobDropdown">
                            <!-- 🔍 SEARCH -->
                            <input type="text"
                                id="jobSearch"
                                placeholder="🔍 Search job..."
                                onkeyup="filterJobs()"
                                class="dropdown-search">

                            <?php foreach ($job_positions as $position): ?>
                                <label class="checkbox-item"
    data-name="<?= strtolower($position['name']) ?>">
                                    <input type="checkbox"
                                        name="job_position[]"
                                        value="<?= $position['id'] ?>"
                                        <?php if (in_array($position['id'], $selected_positions)) echo 'checked'; ?>
                                        onchange="updateJobText()">

                                    <?= htmlspecialchars($position['name']) ?>
                                </label>
                            <?php endforeach; ?>

                        </div>
                    </div>
                </div>




                <div class="form-group">
                    <label class="lbl">Experience Type</label>
                    <select class="inp" name="experience_type">
                        <option value="">From</option>
                        <?php if (!empty($experience_type)) { ?>
                            <?php foreach ($experience_type as $expt) { ?>
                                <option value="<?php echo $expt['id']; ?>"
                                    <?php echo (h($data['experience_type']) == $expt['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($expt['name']); ?>
                                </option>
                            <?php } ?>
                        <?php } ?>
                    </select>
                </div>


                <!-- <?php //print_r($data);
                        //exit; 
                        ?> -->
                <div class="form-group">
                    <label class="lbl">Experience Period</label>
                    <select class="inp" name="experience_period">
                        <option value="">From</option>
                        <?php if (!empty($experience_list)) { ?>
                            <?php foreach ($experience_list as $exp) { ?>
                                <option value="<?php echo $exp['id']; ?>"
                                    <?php echo (h($data['experience_period']) == $exp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exp['name']); ?>
                                </option>
                            <?php } ?>
                        <?php } ?>
                    </select>
                </div>

                <!-- <div class="form-group">
                    <label class="lbl">Latitude</label>
                    <input class="inp" name="latitude" value="<?= h($data['latitude']) ?>">
                </div>

                <div class="form-group">
                    <label class="lbl">Longitude</label>
                    <input class="inp" name="longitude" value="<?= h($data['longitude']) ?>">
                </div> -->

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
<script>
    function toggleJobDropdown() {
        let box = document.getElementById("jobDropdown");
        box.style.display = box.style.display === "block" ? "none" : "block";
    }

    function updateJobText() {
        let checkboxes = document.querySelectorAll('input[name="job_position[]"]:checked');

        let names = [];
        checkboxes.forEach(cb => {
            names.push(cb.parentElement.innerText.trim());
        });

        document.getElementById("jobSelectedText").innerText =
            names.length > 0 ? names.join(", ") : "Select Job Positions";
    }

    // auto load selected text on edit
    document.addEventListener("DOMContentLoaded", function() {
        updateJobText();
    });

    // close dropdown outside click
    document.addEventListener("click", function(e) {
        if (!e.target.closest(".multi-select")) {
            document.getElementById("jobDropdown").style.display = "none";
        }
    });

    function filterJobs() {

    let input = document.getElementById("jobSearch").value.toLowerCase();

    let items = document.querySelectorAll("#jobDropdown .checkbox-item");

    items.forEach(item => {

        let name = item.getAttribute("data-name");

        if (name.includes(input)) {
            item.style.display = "flex";
        } else {
            item.style.display = "none";
        }

    });
}

function toggleJobDropdown() {
    let box = document.getElementById("jobDropdown");

    let isOpen = box.style.display === "block";

    box.style.display = isOpen ? "none" : "block";

    if (!isOpen) {
        setTimeout(() => {
            document.getElementById("jobSearch").focus();
        }, 100);
    }
}
</script>
<script>
    let currentIndex = -1;

    document.addEventListener("keydown", function(e) {

        let items = document.querySelectorAll("#skillsDropdown .checkbox-item");

        if (items.length === 0) return;

        // DOWN ↓
        if (e.key === "ArrowDown") {
            e.preventDefault();

            currentIndex++;

            if (currentIndex >= items.length) currentIndex = 0;

            highlightItem(items);
        }

        // UP ↑
        if (e.key === "ArrowUp") {
            e.preventDefault();

            currentIndex--;

            if (currentIndex < 0) currentIndex = items.length - 1;

            highlightItem(items);
        }

        // ENTER
        if (e.key === "Enter") {
            if (currentIndex >= 0) {

                e.preventDefault();

                let checkbox = items[currentIndex].querySelector("input");

                checkbox.checked = !checkbox.checked;

                updateSkills();
            }
        }

    });

    function highlightItem(items) {

        items.forEach(item => item.style.background = "");

        items[currentIndex].style.background = "#1e293b";

        items[currentIndex].scrollIntoView({
            block: "nearest"
        });

    }

    document.querySelector(".select-box").addEventListener("focus", () => {
        document.getElementById("skillsDropdown").style.display = "block";
    });

    flatpickr("#birthdate", {
        dateFormat: "d-m-Y", // ✅ DD-MM-YYYY
        allowInput: true
    });
</script>
<script>
    function closeAllSuggestions() {
        document.getElementById("profileCitySuggestions").style.display = "none";
        document.getElementById("profileDistrictSuggestions").style.display = "none";
        document.getElementById("profileLocalitySuggestions").style.display = "none";
    }
    document.querySelectorAll("#profileCityInput, #profileDistrictInput, #profileLocalityInput")
        .forEach(function(input) {

            input.addEventListener("blur", function() {

                // delay so click on suggestion still works
                setTimeout(function() {
                    closeAllSuggestions();
                }, 150);

            });

        });
    let profileService;
    let profilePlaceService;

    let profileSelectedCity = "";
    let profileSelectedDist = "";
    let profileSelectedState = "";
    let profileSelectedCountry = "";

    // INIT
    function initProfileCityAutocomplete() {

        profileService = new google.maps.places.AutocompleteService();
        profilePlaceService = new google.maps.places.PlacesService(document.createElement('div'));

        const input = document.getElementById("profileCityInput");

        input.addEventListener("keyup", function() {

            let query = input.value;

            if (query.length < 2) return;

            profileService.getPlacePredictions({
                input: query
            }, function(predictions, status) {

                if (!predictions) return;

                showProfileCitySuggestions(predictions);
            });
        });
    }



    // SHOW DISTRICT

    document.getElementById("profileDistrictInput").addEventListener("keyup", function() {

        let query = this.value;

        if (query.length < 2) return;

        profileService.getPlacePredictions({
                input: query,
                types: ["(cities)"], // ✅ ONLY CITY
                componentRestrictions: {
                    country: "in"
                }
            },
            function(predictions) {

                if (!predictions) return;

                showProfileDistrictSuggestions(predictions);

            });
    });


    function showProfileDistrictSuggestions(list) {
        closeAllSuggestions();
        let box = document.getElementById("profileDistrictSuggestions");
        box.innerHTML = "";

        list.forEach(function(item) {

            let div = document.createElement("div");
            div.className = "suggestion-item";
            div.innerHTML = item.description;

            div.onclick = function() {

                document.getElementById("profileDistrictInput").value = item.description;
                document.getElementById("profileDistrictId").value = item.description;


                profileSelectedDist = item.description;

                box.innerHTML = "";
            }

            box.appendChild(div);
        });

        box.style.display = "block";
    }


    // SHOW CITY
    function showProfileCitySuggestions(list) {
        closeAllSuggestions();
        let box = document.getElementById("profileCitySuggestions");
        box.innerHTML = "";
        box.style.display = "block";

        list.forEach(function(item) {

            let div = document.createElement("div");
            div.className = "suggestion-item";
            div.innerHTML = item.description;

            div.onclick = function() {

                let fullText = item.description;
                // ✅ FIX: store FULL string for search
                profileSelectedCity = fullText;

                document.getElementById("profileCityInput").value = cityOnly;
                document.getElementById("profileCityId").value = cityOnly;

                profileSelectedCity = cityOnly;

                // ✅ GET FULL DETAILS (IMPORTANT LIKE HEADER)
                profilePlaceService.getDetails({
                        placeId: item.place_id
                    },
                    function(place, status) {

                        if (status !== google.maps.places.PlacesServiceStatus.OK) return;

                        place.address_components.forEach(function(comp) {

                            if (comp.types.includes("administrative_area_level_1")) {
                                profileSelectedState = comp.long_name;
                            }

                            if (comp.types.includes("country")) {
                                profileSelectedCountry = comp.long_name;
                            }
                        });

                    }
                );

                box.innerHTML = "";
            };

            box.appendChild(div);
        });
    }

    // LOCALITY
    document.getElementById("profileLocalityInput").addEventListener("keyup", function() {

        let query = this.value;

        if (query.length < 2) return;

        // ✅ build search like header
        let searchQuery = query;

        // ✅ use full city string (important)
        if (profileSelectedCity) {
            searchQuery += ", " + profileSelectedCity;
        }

        if (profileSelectedState) {
            searchQuery += ", " + profileSelectedState;
        }

        if (profileSelectedCountry) {
            searchQuery += ", " + profileSelectedCountry;
        }

        profileService.getPlacePredictions({
            input: searchQuery
        }, function(predictions, status) {

            if (!predictions) return;

            showProfileLocalitySuggestions(predictions);
        });
    });

    // SHOW LOCALITY (FILTER BY CITY)
    function showProfileLocalitySuggestions(list) {
        closeAllSuggestions();
        let box = document.getElementById("profileLocalitySuggestions");
        box.innerHTML = "";
        box.style.display = "block";

        list.forEach(function(item) {

            let div = document.createElement("div");
            div.className = "suggestion-item";
            div.innerHTML = item.description;

            div.onclick = function() {

                let fullText = item.description;

                // cityOnly for cutting
                let cityOnly = document.getElementById("profileCityInput").value.toLowerCase();

                let parts = fullText.split(",");

                let result = [];

                for (let i = 0; i < parts.length; i++) {

                    // STOP BEFORE CITY (don't include city)
                    if (parts[i].toLowerCase().includes(cityOnly)) {
                        break;
                    }

                    result.push(parts[i].trim());
                }

                let localityOnly = result.join(", ");

                document.getElementById("profileLocalityInput").value = localityOnly;
                document.getElementById("profileLocalityId").value = localityOnly;

                box.innerHTML = "";
            };

            box.appendChild(div);
        });
    }

    if (result.length === 0) {
        localityOnly = parts[0].trim();
    }


    // INIT CALL
    document.addEventListener("DOMContentLoaded", function() {
        initProfileCityAutocomplete();
    });
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key=
    AIzaSyCokcdTmQxRaopu75ourz-nNmZNie1wQkY&libraries=places&callback=initProfileCityAutocomplete"
    async defer></script>