<?php
// job_seeker_signup.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name       = trim($_POST['full_name'] ?? '');
    $gender          = trim($_POST['gender'] ?? '');
    $birth_date      = trim($_POST['birth_date'] ?? '');
    $city            = trim($_POST['city'] ?? '');
    $city_place_id   = trim($_POST['city_place_id'] ?? '');
    $city_lat        = trim($_POST['city_lat'] ?? '');
    $city_lng        = trim($_POST['city_lng'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $password        = trim($_POST['password'] ?? '');

    echo "<pre>";
    print_r($_POST); // For testing
    echo "</pre>";
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Pacific iConnect - Sign Up</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: Arial;
            background: #f4f6fb;
        }

        .container {
            width: 450px;
            margin: 50px auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #3b3b98;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-top: 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        .gender-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .gender-group label {
            flex: 1;
            text-align: center;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            cursor: pointer;
        }

        button {
            width: 100%;
            padding: 14px;
            background: #4c3db2;
            color: white;
            border: none;
            border-radius: 6px;
            margin-top: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #372c88;
        }
    </style>
</head>

<body>

    <div class="container">
        <h2>Create Profile</h2>

        <form method="POST">

            <input type="text" name="full_name" placeholder="Full Name" required>

            <div class="gender-group">
                <label>
                    <input type="radio" name="gender" value="Male" required> Male
                </label>
                <label>
                    <input type="radio" name="gender" value="Female" required> Female
                </label>
            </div>

            <input type="date" name="birth_date" required>

            <!-- City with Google Autocomplete -->
            <input type="text" id="city" name="city" placeholder="Enter City" autocomplete="off" required>

            <!-- Hidden fields -->
            <input type="hidden" id="city_place_id" name="city_place_id">
            <input type="hidden" id="city_lat" name="city_lat">
            <input type="hidden" id="city_lng" name="city_lng">

            <input type="text" name="address" placeholder="Address" required>

            <input type="password" name="password" placeholder="Password" required>

            <button type="submit">Sign Up</button>

        </form>
    </div>

    <!-- Google Places Autocomplete -->
    <script>
        let cityAutocomplete;

        function initLocationAutocomplete() {
            const input = document.getElementById("city");

            cityAutocomplete = new google.maps.places.Autocomplete(input, {
                types: ["(cities)"],
                componentRestrictions: {
                    country: "in"
                }, // restrict to India
                fields: ["place_id", "geometry", "name", "formatted_address"]
            });

            cityAutocomplete.addListener("place_changed", function() {

                const place = cityAutocomplete.getPlace();

                if (!place.place_id) {
                    document.getElementById("city_place_id").value = "";
                    document.getElementById("city_lat").value = "";
                    document.getElementById("city_lng").value = "";
                    return;
                }

                document.getElementById("city_place_id").value = place.place_id;
                document.getElementById("city_lat").value = place.geometry.location.lat();
                document.getElementById("city_lng").value = place.geometry.location.lng();
            });
        }

        /* Force user to select from dropdown */
        document.querySelector("form").addEventListener("submit", function(e) {
            const city = document.getElementById("city").value.trim();
            const placeId = document.getElementById("city_place_id").value;

            if (city !== "" && placeId === "") {
                e.preventDefault();
                alert("Please select a city from suggestions.");
                document.getElementById("city").focus();
            }
        });
    </script>

    <script
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCokcdTmQxRaopu75ourz-nNmZNie1wQkY&libraries=places&callback=initLocationAutocomplete"
        async defer></script>

</body>

</html>