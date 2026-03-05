<?php
@ini_set('display_errors', '1');
@error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_login();

global $con;
$page_title = "Add Sponsorship";

// database tables
$SPONSERSHIP = 'jos_app_sponsorship';

/* logged user */
$me  = $_SESSION['admin_user'] ?? [];
$uid = (int)($me['id'] ?? 0);

/* save form */
$msg = "";
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
        $msg = "Sponsorship added successfully!";
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

        $stmt2 = $con->prepare("INSERT INTO jos_app_sponsorship_region
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
    }
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

    .btn.primary {
        background: #2563eb;
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 999px;
        font-weight: 600;
    }

    .btn.primary:hover {
        opacity: .9;
    }

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
</style>

<div class="master-wrap">

    <div class="card" style="margin-top:20px">

        <div class="headbar">
            <div style="font-size:16px;font-weight:700">
                Add Sponsorship
            </div>
        </div>

        <?php if ($msg): ?>
            <div style="padding:10px;color:green;font-weight:600;">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="post">

            <div class="form-grid">

                <div class="form-group">
                    <label>Sponsor Name</label>
                    <input type="text" name="sponsor_name" required>
                </div>

                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_no" maxlength="10">
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
                    <label>Amount</label>
                    <input type="number" step="0.01" name="amount" required>
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

            <div style="padding:0 20px 20px 20px">

                <button type="button" id="openLocationModal" class="btn primary">
                    + Add Location
                </button>

            </div>
            <div style="padding:0 20px 20px 20px">

                <table border="1" width="100%" id="locationTable">
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th>State</th>
                            <th>District</th>
                            <th>City</th>
                            <th>Locality</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody></tbody>

                </table>

            </div>
            <div id="locationInputs"></div>
            <div style="padding:20px">
                <button type="submit" name="save" class="btn primary">
                    Save Sponsorship
                </button>
            </div>

        </form>
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
            </div>

            <div class="form-group">
                <label>State</label>
                <input type="text" id="loc_state">
            </div>

            <div class="form-group">
                <label>District</label>
                <input type="text" id="loc_district">
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
            </div>

            <!-- Hidden fields -->
            <input type="hidden" id="city_place_id" name="city_place_id">
            <input type="hidden" id="city_lat" name="city_lat">
            <input type="hidden" id="city_lng" name="city_lng">

            <div class="form-group">
                <label>Locality</label>
                <input type="text" id="loc_locality">
            </div>

        </div>

        <div style="margin-top:15px">

            <button type="button" id="saveLocation" class="btn primary">
                Save Location
            </button>

            <button type="button" id="closeLocationModal">
                Cancel
            </button>

        </div>

    </div>

</div>
<!-- //modal end -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    let cityAutocomplete;

    function initCityAutocomplete() {

        const input = document.getElementById("loc_city");

        cityAutocomplete = new google.maps.places.Autocomplete(input, {
            types: ["(cities)"],
            componentRestrictions: {
                country: "in"
            },
            fields: ["address_components", "geometry", "name", "place_id"]
        });

        cityAutocomplete.addListener("place_changed", function() {

            const place = cityAutocomplete.getPlace();

            let city = place.name;
            let state = "";
            let country = "";

            place.address_components.forEach(function(comp) {

                if (comp.types.includes("administrative_area_level_1")) {
                    state = comp.long_name;
                }

                if (comp.types.includes("country")) {
                    country = comp.long_name;
                }

            });

            $("#loc_city").val(city);
            $("#loc_state").val(state);
            $("#loc_country").val(country);

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

    /* Open modal */

    $('#openLocationModal').click(function() {
        $('#locationModal').addClass('active');
    });
    /* Close modal */

    $('#closeLocationModal').click(function() {
        $('#locationModal').removeClass('active');
    });


    /* Save location */

    $('#saveLocation').click(function() {

        let country = $('#loc_country').val();
        let state = $('#loc_state').val();
        let city = $('#loc_city').val();
        let locality = $('#loc_locality').val();

        if (city == "") {
            alert("Please select a city from dropdown");
            return;
        }

        let row = `<tr>
        <td>${country}</td>
        <td>${state}</td>
        <td>-</td>
        <td>${city}</td>
        <td>${locality}</td>
        <td><button type="button" class="removeRow">Remove</button></td>
        </tr>`;

        $('#locationTable tbody').append(row);

        $('#locationInputs').append(`
        <input type="hidden" name="location[${locationIndex}][country]" value="${country}">
        <input type="hidden" name="location[${locationIndex}][state]" value="${state}">
        <input type="hidden" name="location[${locationIndex}][district]" value="">
        <input type="hidden" name="location[${locationIndex}][city]" value="${city}">
        <input type="hidden" name="location[${locationIndex}][locality]" value="${locality}">
        `);

        locationIndex++;

        $('#loc_city,#loc_locality').val('');

        $('#locationModal').removeClass('active');

    });


    /* remove row */

    $(document).on('click', '.removeRow', function() {
        let rowIndex = $(this).closest('tr').index();
        $(this).closest('tr').remove();
        $('#locationInputs input').slice(rowIndex * 5, rowIndex * 5 + 5).remove();
    });

    $(window).click(function(e) {
        if ($(e.target).is('#locationModal')) {
            $('#locationModal').removeClass('active');
        }
    });
    $(document).keydown(function(e) {
        if (e.key === "Escape") {
            $('#locationModal').removeClass('active');
        }
    });
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCokcdTmQxRaopu75ourz-nNmZNie1wQkY&libraries=places&callback=initCityAutocomplete"
    async defer></script>