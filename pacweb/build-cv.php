<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/session.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['is_logged_in'])) {
    header("Location: login.php");
    exit();
}
$profile_type_id = (int)$_SESSION['user']['profile_type_id'];

require_once 'includes/db_config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Build CV - Pacific iConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
<link rel="stylesheet" href="/style.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Forces the page to be exactly one screen high and prevents scrolling */
body {
    background-color: #f4f6f9;
}

        /* Mock Header - Replace with your actual header include */
       

        .header-logo {
            color: #312e81; /* Pacific Blue */
            font-weight: 800;
            font-size: 1.4rem;
            line-height: 1;
            text-transform: uppercase;
        }

        .header-logo span {
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .nav-links {
            display: flex;
            gap: 25px;
            list-style: none;
        }

        .nav-links li a {
            text-decoration: none;
            color: #555;
            font-weight: 600;
            font-size: 0.95rem;
        }

        /* Main Content Centered */
        main {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .simple-message-card {
            background: white;
            max-width: 500px;
            width: 100%;
            padding: 50px 40px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
            text-align: center;
        }

        .icon-wrapper {
            background: #eef2ff;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px;
            color: #312e81;
            font-size: 2.5rem;
        }

        h1 {
            color: #312e81;
            font-size: 1.8rem;
            margin-bottom: 15px;
            font-weight: 700;
        }

        p {
            color: #666;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 35px;
        }

        .play-store-btn {
            display: inline-block;
        }

        .play-store-btn img {
            height: 55px;
            transition: transform 0.2s ease;
        }

        .play-store-btn:hover img {
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
<?php include "includes/preloader.php"; ?>
    <?php
    if ($profile_type_id == 3) {
        include "includes/promoter_header.php";
    } else {
        include "includes/header.php";
    }
    ?>
    

    <main>
        <div class="simple-message-card">
            <div class="icon-wrapper">
                <i class="fa-solid fa-mobile-screen"></i>
            </div>
            <h1>App Exclusive Feature</h1>
            <p>To give you the best resume-building experience, the <strong>Build CV</strong> tool is currently only available on the Pacific iConnect Android app. Download it now to get started!</p>
            
            <a href="YOUR_APP_LINK_HERE" class="play-store-btn" target="_blank">
                <img src="https://upload.wikimedia.org/wikipedia/commons/7/78/Google_Play_Store_badge_EN.svg" alt="Get it on Google Play">
            </a>
        </div>
    </main>

</body>
</html>