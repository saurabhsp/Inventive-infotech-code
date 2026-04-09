<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// if (!empty($_SESSION['user']['profile_type_id']) && $_SESSION['user']['profile_type_id'] == 3) {
//     header("Location: promoter_dashboard.php");
//     exit();
// }

$logged_in = !empty($_SESSION['user_id']);

$username = $_SESSION['username']
    ?? ($_SESSION['user']['name'] ?? 'User');
$initials = strtoupper(substr($username, 0, 1));

require_once __DIR__ . '/db_config.php';

$user = $_SESSION['user'] ?? null;
$userid = $_SESSION['user_id'] ?? 0;

$notification_count = $_SESSION['notification_count'] ?? 10;
$city_name = '';

if ($userid > 0) {
    $stmt = $con->prepare("SELECT city_id FROM jos_app_users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $city_name = $row['city_id'] ?? '';
    }
}

// ✅ fallback logic
$city_name = !empty($city_name) ? $city_name : ($_SESSION['city_name'] ?? 'Select City');

$plan_name = $user['plan_name'] ?? '';
$valid_to = $user['valid_to'] ?? '';
?>

<header>
    <div class="container nav-wrapper">

        <!-- Logo -->


        <div class="brand" style="display:flex; align-items:center; gap:15px;">

            <a href="<?= $logged_in ? '/promoter_dashboard.php' : '/index.php' ?>">
                <img src="/assets/pacific_iconnect.png" width="200" alt="Logo">
            </a>

            <!-- LOCATION -->
            <?php if ($logged_in): ?>
                <div class="location-pin" onclick="openLocationModal()">
                    <i class="fas fa-map-marker-alt" style="color:red;"></i>
                    <span id="headerCity"><?= htmlspecialchars($city_name) ?></span>
                </div>
            <?php endif; ?>

        </div>

        <!-- MOBILE MENU BUTTON -->
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Navigation -->
        <nav class="nav-menu" id="mobileMenu">

            <!-- HOME -->
            <a href="<?= $logged_in ? 'promoter_dashboard.php' : '/' ?>" class="nav-item">
                <i class="fa-solid fa-house"></i> Home
            </a>

            <?php if ($logged_in): ?>

                <a href="/refer_and_earn.php" class="nav-item">Refer n Earn</a>
                <a href="/wallet.php" class="nav-item">My Wallet</a>

                <!-- MOBILE LOGOUT -->
                <a href="/logout.php" class="nav-item mobile-only">Logout</a>


            <?php endif; ?>

        </nav>

        <!-- Right Side (Desktop Only) -->
        <div class="nav-right">

            <?php if ($logged_in): ?>

                <a href="/promoter_notifications.php" class="nav-action-icon" title="Notifications">
                    <i class="far fa-bell"></i>

                    <?php //if ($notification_count > 0): ?>
                        <span class="noti-badge"><?= $notification_count ?></span>
                        <?php //endif; ?>
                </a>

                <div class="profile-dropdown-wrap">

                    <div class="user-profile">

                        <div class="user-avatar"><?= $initials ?></div>

                        <span class="user-name">
                            <?= htmlspecialchars($username) ?>
                            <i class="fas fa-chevron-down" style="font-size:0.75rem;"></i>
                        </span>

                    </div>

                    <div class="dropdown-menu">

                        <a href="/promoter_profile.php" class="dropdown-item">
                            <i class="far fa-user-circle"></i> My Profile
                        </a>

                        <a href="/logout.php" class="dropdown-item text-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>

                    </div>

                </div>


            <?php endif; ?>

        </div>

    </div>

</header>
<!-- Location Modal -->

<div class="modal-overlay" id="locationModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Your Current Location</h3>
        </div>

        <div class="input-group">
            <label class="input-label">Where are you currently staying?</label>
            <input type="text" id="headerCityInput" class="modal-input" autocomplete="off">

            <!-- hidden fields -->
            <input type="hidden" id="headerStateInput">
            <input type="hidden" id="headerCountryInput">

            <!-- suggestion box -->
            <div id="headerCitySuggestions" class="suggestion-box"></div>
        </div>

        <div class="input-group">
            <label class="input-label">Select Locality</label>

            <input type="text"
                id="headerLocalityInput"
                class="modal-input"
                autocomplete="off"
                placeholder="Enter locality">

            <div id="headerLocalitySuggestions" class="suggestion-box"></div>
        </div>

        <div class="input-group">
            <label class="input-label">Pin Code</label>
            <input type="text" id="headerPincodeInput" class="modal-input" placeholder="Enter Pin Code">
        </div>


        <div class="modal-btn-row">
            <button class="btn-cancel" onclick="closeLocationModal()">Cancel</button>
            <button class="btn-save" onclick="updateLocation()">Update</button>
        </div>
    </div>
</div>
</div>

<script>
    document.getElementById("mobileMenuBtn").onclick = function() {
        document.getElementById("mobileMenu").classList.toggle("show");
    };
</script>
<script>
    let headerService;
    let headerPlaceService;

    let headerSelectedCountry = "";
    let headerSelectedState = "";
    let headerSelectedCity = "";

    function initHeaderCityAutocomplete() {

        headerService = new google.maps.places.AutocompleteService();
        headerPlaceService = new google.maps.places.PlacesService(document.createElement('div'));

        const input = document.getElementById("headerCityInput");

        input.addEventListener("keyup", function() {

            let query = input.value;

            if (query.length < 2) return;

            headerService.getPlacePredictions({
                input: query,
                componentRestrictions: {
                    country: "in"
                }
            }, function(predictions, status) {

                if (!predictions) return;

                showHeaderCitySuggestions(predictions);

            });

        });

    }

    function showHeaderLocalitySuggestions(list, query) {

        let box = document.getElementById("headerLocalitySuggestions");
        box.innerHTML = "";

        if (list.length === 0) {
            box.style.display = "none";
            return;
        }

        box.style.display = "block";

        list.forEach(function(item) {

            if (
                item.types.includes("sublocality") ||
                item.types.includes("sublocality_level_1") ||
                item.types.includes("neighborhood") ||
                item.types.includes("premise")
            ) {

                if (headerSelectedCity && item.description.toLowerCase().includes(headerSelectedCity.toLowerCase())) {

                    let div = document.createElement("div");
                    div.className = "suggestion-item";
                    div.innerHTML = item.description;

                    div.onclick = function() {

                        let parts = item.description.split(",");
                        let cleaned = [];

                        for (let i = 0; i < parts.length; i++) {

                            let p = parts[i].trim();

                            if (p === headerSelectedCity) break;

                            cleaned.push(p);
                        }

                        document.getElementById("headerLocalityInput").value = cleaned.join(", ");

                        box.innerHTML = "";

                    }

                    box.appendChild(div);

                }

            }

        });

    }

    function showHeaderCitySuggestions(list) {

        let box = document.getElementById("headerCitySuggestions");
        box.innerHTML = "";

        if (list.length === 0) {
            box.style.display = "none";
            return;
        }

        box.style.display = "block";

        list.forEach(function(item) {

            if (!item.types.includes("locality")) return;

            let div = document.createElement("div");
            div.className = "suggestion-item";
            div.innerHTML = item.description;

            div.onclick = function() {

                getHeaderPlaceDetails(item.place_id);

                box.innerHTML = "";
            }

            box.appendChild(div);

        });

    }

    function getHeaderPlaceDetails(placeId) {

        headerPlaceService.getDetails({
            placeId: placeId,
            fields: ["address_components", "name"]
        }, function(place, status) {

            if (status !== "OK") return;

            let city = "";
            let state = "";
            let country = "";


            place.address_components.forEach(function(c) {

                if (c.types.includes("locality")) {
                    city = c.long_name;
                }

                if (c.types.includes("administrative_area_level_1")) {
                    state = c.long_name;
                }

                if (c.types.includes("country")) {
                    country = c.long_name;
                }

            });

            document.getElementById("headerCityInput").value = city;
            document.getElementById("headerStateInput").value = state;
            document.getElementById("headerCountryInput").value = country;
            headerSelectedCity = city;

        });

    }

    document.getElementById("headerLocalityInput").addEventListener("keyup", function() {

        let query = this.value;

        if (query.length < 2) return;

        headerService.getPlacePredictions({
            input: query,
            componentRestrictions: {
                country: "in"
            }
        }, function(predictions, status) {

            if (!predictions) return;

            showHeaderLocalitySuggestions(predictions, query);

        });

    });


    function openLocationModal() {

        document.getElementById('locationModal').classList.add('active');

        const userid = <?php echo (int)$userid; ?>;

        fetch("/web_api/getUsercity.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    userid: userid
                })
            })
            .then(res => res.json())
            .then(data => {

                if (data.status === "success") {

                    const city = data.data.city_name ?? "";
                    const locality = data.data.locality_name ?? "";

                    document.getElementById("headerCityInput").value = city;
                    document.getElementById("headerLocalityInput").value = locality;

                    // ✅ IMPORTANT FIX
                    headerSelectedCity = city;
                } else {
                    console.log(data.message);
                }

            })
            .catch(err => {
                console.log(err);
            });

    }

    function closeLocationModal() {
        document.getElementById('locationModal').classList.remove('active');
    }

    function updateLocation() {

        const city = document.getElementById("headerCityInput").value.trim();
        const locality = document.getElementById("headerLocalityInput").value.trim();
        const state = document.getElementById("headerStateInput").value;
        const country = document.getElementById("headerCountryInput").value;
        const pincode = document.getElementById("headerPincodeInput").value;
        const userid = <?php echo (int)$userid; ?>;

        if (city === "") {
            alert("City is required");
            return;
        }

        const payload = {
            userid: userid,
            city_id: city,
            locality_id: locality,
            state: state,
            country: country,
            pincode: pincode
        };

        console.log("Sending to API:", payload);

        fetch("/web_api/updateCity.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    userid: userid,
                    city_id: city,
                    locality_id: locality,
                    state: state,
                    country: country,
                    pincode: pincode
                })
            })
            .then(res => res.json())
            .then(data => {

                if (data.status === "success") {

                    // update header instantly
                    document.getElementById("headerCity").innerText = city;

                    closeLocationModal();

                } else {
                    alert(data.message);
                }

            })
            .catch(err => {
                console.log(err);
                alert("Failed to update location");
            });

    }
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCokcdTmQxRaopu75ourz-nNmZNie1wQkY&libraries=places&callback=initHeaderCityAutocomplete" async defer></script>
<style>
    .nav-menu.show {
        display: flex;
        flex-direction: column;
        position: absolute;
        top: 70px;
        left: 0;
        width: 100%;
        background: #fff;
        padding: 20px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    }

    .location-pin {
        cursor: pointer;
        font-weight: 600;
    }

    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;

        background: rgba(0, 0, 0, 0.5);
        z-index: 2000;

        display: none;
        align-items: center;
        /* ✅ CENTER */
        justify-content: center;
        /* ✅ CENTER */

        opacity: 0;
        transition: 0.3s;
    }


    .modal-overlay.active {
        display: flex;
        opacity: 1;
    }

    /* MODAL BOX */
    .modal-content {
        background: #fff;
        width: 90%;
        max-width: 420px;
        /* ✅ SMALLER WIDTH */
        padding: 20px;

        border-radius: 12px;
        /* ✅ NORMAL BOX */

        transform: scale(0.9);
        /* nice animation */
        transition: 0.3s;
    }

    /* ANIMATION */
    .modal-overlay.active .modal-content {
        transform: scale(1);
    }

    /* INPUT STYLE */
    .modal-input {
        width: 100%;
        border: none;
        border-bottom: 1px solid #ccc;
        padding: 10px 0;
        font-size: 15px;
        outline: none;
    }

    .modal-input:focus {
        border-bottom: 2px solid #483EA8;
    }

    .input-group {
        margin-bottom: 20px;
        position: relative;
    }

    .input-label {
        font-size: 13px;
        color: #666;
        margin-bottom: 5px;
        display: block;
    }

    /* BUTTONS */
    .modal-btn-row {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .btn-cancel {
        background: #fff;
        border: 1px solid #ccc;
        padding: 10px 20px;
        border-radius: 20px;
    }

    .btn-save {
        background: #2563eb;
        color: #fff;
        border: none;
        padding: 10px 25px;
        border-radius: 20px;
    }

    /* SUGGESTION BOX */
    .suggestion-box {
        position: absolute;
        width: 100%;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        max-height: 200px;
        overflow-y: auto;
        z-index: 9999;
    }

    .suggestion-item {
        padding: 10px;
        cursor: pointer;
    }

    .suggestion-item:hover {
        background: #f1f5ff;
    }

    /* DESKTOP */
    @media (min-width:768px) {
        .modal-overlay {
            align-items: center;
        }

        .modal-content {
            border-radius: 16px;
            transform: translateY(20px);
        }
    }

    /* ===== MOBILE HEADER FIX ===== */
    @media (max-width:768px) {

        .brand {
            gap: 8px;
        }

        .brand img {
            width: 130px;
            /* थोड़ा छोटा */
        }

        .location-pin {
            max-width: 90px;
            /* limit width */
            overflow: hidden;
        }

        .location-pin span {
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            /* Pune → Pun... if long */
        }

        .location-pin i {
            font-size: 12px;
        }

    }

    /* ===== FINAL MOBILE HEADER FIX ===== */
    @media (max-width:768px) {

        .nav-wrapper {
            gap: 6px;
        }

        /* LOGO SMALL */
        .brand img {
            width: 120px;
        }

        /* LOCATION FIX */
        .location-pin {
            display: flex;
            align-items: center;
            max-width: 80px;
        }

        .location-pin span {
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .location-pin i {
            font-size: 12px;
        }

        /* RIGHT SIDE FIX */
        .nav-right {
            gap: 6px;
        }

        .user-name {
            display: none;
            /* 🔥 hide name in mobile */
        }

        .user-avatar {
            width: 28px;
            height: 28px;
            font-size: 12px;
        }

        .nav-action-icon {
            font-size: 1.1rem;
        }

    }
</style>

<!-- ===== MOBILE BOTTOM NAV ===== -->
<div class="mobile-bottom-nav">

    <?php if ($logged_in): ?>

        <a href="/promoter_dashboard.php" class="bottom-item">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>

        <a href="/refer_and_earn.php" class="bottom-item">
            <i class="fas fa-handshake"></i>
            <span>Refer</span>
        </a>

        <a href="/wallet.php" class="bottom-item">
            <i class="fas fa-wallet"></i>
            <span>Wallet</span>
        </a>

    <?php endif; ?>

</div>