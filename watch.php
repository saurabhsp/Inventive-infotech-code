<?php
require_once __DIR__ . '/includes/session.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $company     = trim($_POST['company_name'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $person      = trim($_POST['contact_person_name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $password    = trim($_POST['password'] ?? '');
    $mobile      = $_SESSION['mobile'] ?? '';
    $latitude  = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
    $state    = $_POST['state'] ?? '';
    $district = $_POST['district'] ?? '';
    $country  = $_POST['country'] ?? '';

    if (empty($mobile)) {
        $error = "Session expired. Please login again.";
    } elseif (!$company || !$person || !$password || !$city) {
        $error = "Please fill all required fields.";
    } else {

        // ✅ API payload
        $postData = [
            "mobile_no" => $mobile,
            "password" => $password,
            "profile_type_id" => 1, // ✅ EMPLOYER
            "city_id" => $city,
            "address" => $address,
            "latitude" => $latitude,
            "longitude" => $longitude,
            "referral_code" => "",
            "fcm_token" => "",
            "profile_details" => [
                "organization_name" => $company,
                "contact_person_name" => $person,
                "designation" => $designation,
                "state" => $state,
                "district" => $district,
                "country" => $country
            ]
        ];

        $api_url = "https://pacweb.inv11.in/web_api/signup.php";



        $ch = curl_init($api_url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json"
            ],
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);



        if ($response === false) {
            $error = "Server error: " . curl_error($ch);
        } else {

            $result = json_decode($response, true);


            if (isset($result['status']) && $result['status'] === 'success') {

                $_SESSION['user_id'] = $result['data']['user_id'];

                header("Location: /index.php");
                exit;
            } else {
                $error = $result['message'] ?? "Signup failed";
            }
        }

        curl_close($ch);
    }
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Sign Up | Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">
    <style>
        :root {
            --primary: #483EA8;
            --primary-light: #eceaf9;
            --secondary: #ff6f00;
            --bg-body: #f4f6f9;
            --white: #ffffff;
            --text-dark: #1a1a1a;
            --text-grey: #555555;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-dark);
            font-size: 14px;
            height: 100vh;
            /* Fixed height for 1-screen feel */
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* --- HEADER --- */
        header {
            background: var(--white);
            height: 50px;
            flex-shrink: 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            z-index: 100;
        }

        .nav-wrapper {
            width: 100%;
            max-width: 1150px;
            margin: 0 auto;
            padding: 0 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            font-weight: 800;
            font-size: 1.1rem;
        }

        .login-link {
            font-weight: 700;
            color: var(--primary);
            font-size: 0.9rem;
            text-decoration: none;
        }

        /* --- MAIN SECTION --- */
        .signup-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
        }

        .signup-card {
            background: var(--white);
            width: 100%;
            max-width: 1000px;
            height: 100%;
            max-height: 600px;
            /* Fixed height container */
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
        }

        /* --- LEFT: VISUAL (Desktop) --- */
        .visual-side {
            position: relative;
            overflow: hidden;
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Floating Background Animation */
        .visual-bg {
            position: absolute;
            top: -10%;
            left: -10%;
            width: 120%;
            height: 120%;
            background: linear-gradient(135deg, rgba(50, 43, 122, 0.9) 0%, rgba(72, 62, 168, 0.85) 100%),
                url('https://images.unsplash.com/photo-1556761175-5973dc0f32e7?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80');
            background-size: cover;
            background-position: center;
            animation: floatBg 20s infinite alternate ease-in-out;
            z-index: 1;
        }

        .visual-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        /* Animated Text */
        .visual-content h2 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 15px;
            line-height: 1.2;
            animation: fadeInUp 1s ease-out;
        }

        .visual-content p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 30px;
            animation: fadeInUp 1.2s ease-out;
        }

        /* USP Grid */
        .usp-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            animation: fadeInUp 1.4s ease-out;
        }

        .mini-usp {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            padding: 10px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* --- RIGHT: FORM --- */
        .form-side {
            padding: 30px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
        }

        .form-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .form-header h1 {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 2px;
        }

        .form-header p {
            color: #666;
            font-size: 0.9rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .full-width {
            grid-column: span 2;
        }

        .input-label {
            display: block;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 4px;
            color: #444;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            outline: none;
            transition: 0.3s;
            background: #fff;
            height: 40px;
        }

        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(72, 62, 168, 0.1);
        }

        .btn-submit {
            width: 100%;
            background: var(--primary);
            color: white;
            padding: 12px;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 700;
            border: none;
            margin-top: 25px;
            transition: 0.3s;
            cursor: pointer;
        }

        .btn-submit:hover {
            background: #322b7a;
        }

        /* Animations */
        @keyframes floatBg {
            0% {
                transform: scale(1);
            }

            100% {
                transform: scale(1.1);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* --- MOBILE OPTIMIZATION (1 Screen) --- */
        @media (max-width: 900px) {
            body {
                height: 100vh;
                overflow: hidden;
                background: #fff;
            }

            header {
                height: 45px;
                border-bottom: 1px solid #eee;
                box-shadow: none;
            }

            .signup-section {
                padding: 0;
                height: calc(100vh - 45px);
                align-items: flex-start;
            }

            .signup-card {
                grid-template-columns: 1fr;
                box-shadow: none;
                border-radius: 0;
                max-height: 100%;
                height: 100%;
            }

            .visual-side {
                display: none;
            }

            .form-side {
                padding: 15px 20px;
                justify-content: flex-start;
            }

            .form-header {
                margin-bottom: 15px;
                text-align: left;
            }

            .form-header h1 {
                font-size: 1.5rem;
            }

            .form-header p {
                font-size: 0.85rem;
            }

            /* Tighter Grid */
            .form-grid {
                gap: 10px;
            }

            .input-label {
                font-size: 0.75rem;
                margin-bottom: 2px;
            }

            .form-input,
            .form-select {
                height: 35px;
                font-size: 0.9rem;
                padding: 5px 10px;
            }

            /* Squeeze Grid: Designation & Contact on same row for Mobile */
            .mobile-half {
                grid-column: span 1 !important;
            }

            .btn-submit {
                margin-top: 15px;
                padding: 10px;
            }
        }
    </style>
</head>

<body>

    <?php include "includes/header.php"; ?>

    <section class="signup-section">
        <div class="container">
            <div class="signup-card">

                <div class="visual-side">
                    <div class="visual-bg"></div>
                    <div class="visual-content">
                        <h2>Build Your Dream Team.</h2>
                        <p>Access India's largest database of verified candidates and hire instantly.</p>

                        <div class="usp-grid">
                            <div class="mini-usp"><i class="fas fa-file-alt"></i> 1M+ CVs</div>
                            <div class="mini-usp"><i class="fas fa-bolt"></i> Fast Hiring</div>
                            <div class="mini-usp"><i class="fas fa-robot"></i> AI Matching</div>
                            <div class="mini-usp"><i class="fas fa-check-circle"></i> Verified</div>
                        </div>
                    </div>
                </div>

                <div class="form-side">
                    <div class="form-header">
                        <h1>Recruiter Sign Up</h1>
                        <p>Start hiring in minutes.</p>
                    </div>

                    <form method="POST">
                        <div class="form-grid">

                            <div class="full-width">
                                <label class="input-label">Organization Name</label>
                                <input type="text" name="company_name" class="form-input" placeholder="Company Name">
                            </div>

                            <div class="full-width">
                                <label class="input-label">Office Address</label>
                                <input type="text" name="address" class="form-input" placeholder="Full Address">
                            </div>

                            <div class="mobile-half">
                                <label class="input-label">Contact Person</label>
                                <input type="text" name="contact_person_name" class="form-input" placeholder="Your Name">
                            </div>

                            <div class="mobile-half">
                                <label class="input-label">Designation</label>
                                <input type="text" name="designation" class="form-input" placeholder="e.g. HR">
                            </div>

                            <div class="mobile-half">
                                <label class="input-label">City</label>
                                <input type="text" id="city" name="city" class="form-input" placeholder="Enter city" autocomplete="off" required>

                                <!-- Hidden fields -->

                                <input type="hidden" name="state" id="state">
                                <input type="hidden" name="district" id="district">
                                <input type="hidden" name="country" id="country">


                            </div>

                            <div class="mobile-half">
                                <label class="input-label">Password</label>
                                <input type="password" name="password" class="form-input" placeholder="Password">
                            </div>

                        </div>

                        <input type="hidden" name="latitude" id="lat">
                        <input type="hidden" name="longitude" id="lng">

                        <script>
                            // Get current location (lat/lng)
                            document.addEventListener("DOMContentLoaded", function() {
                                navigator.geolocation.getCurrentPosition(
                                    function(position) {
                                        document.getElementById("lat").value = position.coords.latitude;
                                        document.getElementById("lng").value = position.coords.longitude;
                                    },
                                    function(error) {
                                        console.log("Location error:", error.message);
                                    }
                                );
                            });

                            let cityAutocomplete;

                            function initCityAutocomplete() {
                                const input = document.getElementById("city");

                                cityAutocomplete = new google.maps.places.Autocomplete(input, {
                                    types: ["(cities)"],
                                    componentRestrictions: {
                                        country: "in"
                                    },
                                    fields: ["address_components", "geometry", "name"]
                                });

                                cityAutocomplete.addListener("place_changed", function() {

                                    const place = cityAutocomplete.getPlace();
                                    if (!place.address_components) return;

                                    let city = "";
                                    let state = "";
                                    let district = "";
                                    let country = "";

                                    place.address_components.forEach(component => {
                                        const types = component.types;

                                        if (types.includes("locality")) {
                                            city = component.long_name;
                                        }

                                        if (types.includes("administrative_area_level_2")) {
                                            district = component.long_name;
                                        }

                                        if (types.includes("administrative_area_level_1")) {
                                            state = component.long_name;
                                        }

                                        if (types.includes("country")) {
                                            country = component.long_name;
                                        }
                                    });

                                    // Set values
                                    document.getElementById("city").value = city;
                                    document.getElementById("state").value = state;
                                    document.getElementById("district").value = district;
                                    document.getElementById("country").value = country;

                                    // Override lat/lng from Google (better accuracy)
                                    document.getElementById("lat").value = place.geometry.location.lat();
                                    document.getElementById("lng").value = place.geometry.location.lng();
                                });
                            }

                            // Form validation (force dropdown selection)
                            document.querySelector("form").addEventListener("submit", function(e) {
                                const city = document.getElementById("city").value.trim();
                                const state = document.getElementById("state").value;
                                const country = document.getElementById("country").value;

                                if (!city || !state || !country) {
                                    e.preventDefault();
                                    alert("Please select a city from suggestions.");
                                    document.getElementById("city").focus();
                                }

                            });
                        </script>

                        <button type="submit" class="btn-submit">Register</button>
                    </form>
                </div>

            </div>
        </div>
    </section>
    <?php include "includes/footer.php"; ?>
    <script
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCokcdTmQxRaopu75ourz-nNmZNie1wQkY&libraries=places&callback=initCityAutocomplete"
        async defer></script>
</body>

</html>