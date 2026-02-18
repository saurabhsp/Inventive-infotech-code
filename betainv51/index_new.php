<?php
session_start();

@ini_set('display_errors','1');
@ini_set('display_startup_errors','1');
@error_reporting(E_ALL);


require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/initialize.php';
require_login();

global $con;
date_default_timezone_set('Asia/Kolkata');

/* ---------------- Role ---------------- */
$session = $_SESSION['admin_user'];
$role_id = $session['role_id'] ?? 0;
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Dashboard</title>

    <link rel="stylesheet" href="/adminconsole/assets/ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

  <style>
    /* keep existing theme; enforce WHITE titles */
    .dash-title, .card h2, .card h3, .card-title { color:#fff !important; }
    .dash-sub { color: rgba(255,255,255,0.75) !important; }

    /* responsive grid */
    .grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap:18px; }
    .grid-5 { display:grid; grid-template-columns: repeat(5, 1fr); gap:14px; }
    @media (max-width: 1200px){ .grid-5 { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 900px){ .grid-2 { grid-template-columns: 1fr; } }

    .mini-card{
      background: rgba(0,0,0,0.22);
      border:1px solid rgba(255,255,255,0.06);
      border-radius:16px;
      padding:14px 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,.25);
    }
    .mini-card .label{ color: rgba(255,255,255,.72); font-size: 12px; margin-bottom:8px; }
    .mini-card .value{ color:#fff; font-size: 26px; font-weight: 900; letter-spacing: .2px; }

    .panel{
      background: rgba(0,0,0,0.18);
      border:1px solid rgba(255,255,255,0.06);
      border-radius:18px;
      padding:18px 18px;
      box-shadow: 0 10px 40px rgba(0,0,0,.28);
    }

    .filter-bar{
      display:flex; gap:10px; align-items:flex-end; justify-content:flex-end; flex-wrap:wrap;
      margin-bottom: 14px;
    }
    .filter-bar label{ color:rgba(255,255,255,.75); font-size:12px; display:block; margin-bottom:6px; }
    .filter-bar input{
      height:38px; border-radius:10px; border:1px solid rgba(255,255,255,0.12);
      background: rgba(0,0,0,0.22); color:#fff; padding:0 10px; min-width:160px;
    }
    .btn{
      height:38px; border-radius:10px; border:1px solid rgba(255,255,255,0.10);
      padding:0 14px; cursor:pointer; font-weight:700;
      background: rgba(255,255,255,0.10); color:#fff;
    }
    .btn.primary{ background:#2563eb; border-color:#2563eb; }
    .btn:hover{ filter:brightness(1.05); }

    .section-title{ margin:0 0 6px; font-size:16px; font-weight:900; color:#fff !important; }
    .section-sub{ margin:0 0 14px; font-size:12px; color:rgba(255,255,255,.7) !important; }
  </style>
</head>

<body>

        <!-- Role Based Dashboard -->
        <div>
            <?php

            switch ($role_id) {

                case 1:
                    include __DIR__ . '/dashboard/superadmin_dashboard.php';
                    break;

                case 2:
                    include __DIR__ . '/dashboard/admin_dashboard.php';
                    break;

                case 3:
                    include __DIR__ . '/dashboard/outreach_executive_dashboard.php';
                    break;

                case 4:
                    include __DIR__ . '/dashboard/recruiter_admin_dashboard.php';
                    break;

                case 10:
                    include __DIR__ . '/dashboard/director_dashboard.php';
                    break;

                case 11:
                    include __DIR__ . '/dashboard/receptionist_cum_app_admin_dashboard.php';
                    break;

                case 12:
                    include __DIR__ . '/dashboard/operations_manager_dashboard.php';
                    break;

                case 13:
                    include __DIR__ . '/dashboard/relationship_manager_dashboard.php';
                    break;

                case 14:
                    include __DIR__ . '/dashboard/digital_marketing_executive_dashboard.php';
                    break;

                case 15:
                    include __DIR__ . '/dashboard/accounts_assistant_dashboard.php';
                    break;

                case 16:
                    include __DIR__ . '/dashboard/business_development_manager_dashboard.php';
                    break;

                default:
                    echo "<div style='color:red;'>No dashboard assigned for this role.</div>";
            }
            ?>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        flatpickr("#from", {
            dateFormat: "d-m-Y",
            allowInput: true
        });
        flatpickr("#to", {
            dateFormat: "d-m-Y",
            allowInput: true
        });
    </script>

</body>

</html>