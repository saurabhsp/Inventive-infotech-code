<?php
require_once 'includes/session.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ===============================
   ✅ LOGIN CHECK
================================ */
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* ===============================
   ✅ DB CONNECTION
================================ */
require_once 'includes/db_config.php';

$user_id = $_SESSION['user_id'];

/* ===============================
   ✅ FETCH CITY + LOCALITY FROM DB
================================ */
$city = '';
$locality = '';

$stmt = $con->prepare("
    SELECT u.city_id, cp.locality_id
    FROM jos_app_users u
    LEFT JOIN jos_app_candidate_profile cp 
        ON u.profile_id = cp.id
    WHERE u.id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $city = trim($row['city_id'] ?? '');
        $locality = trim($row['locality_id'] ?? '');
    }
}

/* ===============================
   ✅ SAVE INTO SESSION (SYNC FIX)
================================ */
$_SESSION['user']['city_name'] = $city;
$_SESSION['user']['locality_name'] = $locality;


/* ===============================
   LOAD SLIDER DIRECT FROM DB
================================ */

$slider_list = [];
$profile_type = 3;

$sliderQuery = "SELECT id, title, image, action_type, action_value
                FROM jos_app_slider
                WHERE profile_type = ? AND status = 1
                ORDER BY id DESC";

$stmtS = $con->prepare($sliderQuery);
$stmtS->bind_param("i", $profile_type);
$stmtS->execute();
$resS = $stmtS->get_result();

while ($row = $resS->fetch_assoc()) {
    $row['image'] = !empty($row['image'])
        ? DOMAIN_URL . "webservices/" . ltrim($row['image'], '/')
        : DOMAIN_URL . "assets/img/slider-default.jpg";

    $slider_list[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pacific iConnect – Hire or Get Hired</title>


    <link rel="stylesheet" href="/style.css">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">


</head>

<body>

<?php include "includes/preloader.php"; ?>
<?php include "includes/promoter_header.php"; ?>

    <!-- ================= SLIDER ================= -->

    <?php if (!empty($slider_list)): ?>

        <div class="slider-wrapper">
            <div class="slider-scroll" id="cardSlider">

                <?php
                $themes = ['c1', 'c2', 'c3', 'c4'];
                $i = 0;

                foreach ($slider_list as $slide):

                    $theme = $themes[$i % 4];

                    /* Handle action types */
                    $url = '#';

                    if (($slide['action_type'] ?? '') === 'link') {
                        $url = $slide['action_value'];
                    } elseif (($slide['action_type'] ?? '') === 'job') {
                        $url = "/jobs/" . $slide['action_value'];
                    }

                ?>

                    <a
                        href="<?= htmlspecialchars($url); ?>"
                        class="card <?= $theme ?>"
                        style="background-image:url('<?= htmlspecialchars($slide['image'] ?? '/assets/img/slider-default.jpg'); ?>');
background-size:cover;
background-position:center;">

                        <!--<div style="display:flex;justify-content:space-between;">
<span style="font-weight:bold;">Pacific iConnect</span>
</div>
-->

                        <!--
<div style="margin-top:auto;font-size:18px;font-weight:bold;">
<?= htmlspecialchars($slide['title'] ?? ''); ?>
</div>
-->

                    </a>

                <?php
                    $i++;
                endforeach;
                ?>

            </div>

            <div class="dots">

                <?php foreach ($slider_list as $k => $v): ?>
                    <div class="dot <?= $k == 0 ? 'active' : '' ?>"></div>
                <?php endforeach; ?>

            </div>

        </div>

    <?php endif; ?>



    <script>
        const words = [
            "<?= addslashes($welcome_message ?: 'Instantly.') ?>",
            "Directly.",
            "Securely."
        ];
        let i = 0;
        const heading = document.getElementById("typewriter");

        setInterval(() => {
            if (!heading) return;
            heading.textContent = words[i];
            i = (i + 1) % words.length;
        }, 2500);
    </script>

    <script>
        const slider = document.getElementById('cardSlider');
        const dots = document.querySelectorAll('.dot');

        if (slider) {

            let scrollInterval;
            let isUserInteracting = false;

            function updateActiveDot() {

                const cardWidth = slider.querySelector('.card').offsetWidth + 20;
                const index = Math.round(slider.scrollLeft / cardWidth);

                dots.forEach((dot, i) => {
                    dot.classList.toggle('active', i === index);
                });

            }

            function startAutoSlide() {

                scrollInterval = setInterval(() => {

                    if (!isUserInteracting) {

                        const cardWidth = slider.querySelector('.card').offsetWidth + 20;
                        const maxScroll = slider.scrollWidth - slider.clientWidth;

                        if (slider.scrollLeft >= maxScroll - 10) {

                            slider.scrollTo({
                                left: 0,
                                behavior: 'smooth'
                            });

                        } else {

                            slider.scrollBy({
                                left: cardWidth,
                                behavior: 'smooth'
                            });

                        }

                    }

                }, 3000);

            }

            slider.addEventListener('touchstart', () => isUserInteracting = true);
            slider.addEventListener('mousedown', () => isUserInteracting = true);

            window.addEventListener('touchend', () => {
                setTimeout(() => isUserInteracting = false, 2000);
            });

            window.addEventListener('mouseup', () => {
                setTimeout(() => isUserInteracting = false, 2000);
            });

            slider.addEventListener('scroll', updateActiveDot);

            startAutoSlide();

        }
    </script>



</body>

</html>