<?php
if (!isset($con)) die("Database not initialized.");

date_default_timezone_set('Asia/Kolkata');

/* ---------------- LOGGED IN USER ---------------- */
$me = function_exists('current_user') ? current_user() : [];

$logged_admin_id   = (int)($me['id'] ?? 0);
$logged_admin_name = htmlspecialchars($me['name'] ?? '', ENT_QUOTES, 'UTF-8');

$logged_role_id = (int)($me['role_id'] ?? 0);
$logged_role_name = '';

if ($logged_role_id > 0) {
    $stmt = $con->prepare("SELECT name FROM jos_admin_roles WHERE id = ?");
    $stmt->bind_param("i", $logged_role_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $logged_role_name = $row['name'];
    }
    $stmt->close();
}

if ($logged_admin_id <= 0) {
    die("Invalid user session.");
}

/* ---------------- ADMIN REFERRAL LINK + JOINING DATE ---------------- */
$referral_code = '';
$base_link = '';
$short_url = '';
$admin_created_at = '';

$stmt = $con->prepare("SELECT myreferral_code, created_at FROM jos_admin_users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $logged_admin_id);
$stmt->execute();
$res = $stmt->get_result();

if ($r = $res->fetch_assoc()) {
    $referral_code    = trim((string)($r['myreferral_code'] ?? ''));
    $admin_created_at = $r['created_at'] ?? '';
}
$stmt->close();

if ($referral_code !== '') {
    $base_link = "https://play.google.com/store/apps/details?id=com.invent.pacificConnect&referrer=" . urlencode($referral_code);

    if (function_exists('shortenUrl')) {
        $tmp = shortenUrl($base_link);
        if (!empty($tmp)) {
            $short_url = $tmp;
        }
    }

    if ($short_url === '') {
        $short_url = $base_link;
    }
}

$joiningFormatted = '';
$daysSinceJoining = 0;

if (!empty($admin_created_at)) {
    $joiningDate = new DateTime($admin_created_at);
    $todayDate   = new DateTime();
    $interval = $joiningDate->diff($todayDate);
    $daysSinceJoining = $interval->days;
    $joiningFormatted = $joiningDate->format('l, d F Y');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>
        <?php if (!empty($logged_role_name)) : ?>
            <?= htmlspecialchars($logged_role_name) ?> - Dashboard
        <?php else: ?>
            Dashboard
        <?php endif; ?>
    </title>

    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --border: #334155;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Segoe UI, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        .wrapper {
            max-width: 1200px;
            margin: auto;
            padding: 30px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        h1 {
            margin: 0 0 6px 0;
            font-size: 28px;
        }

        .muted {
            color: var(--muted);
            font-size: 14px;
        }

        .todayline {
            margin-top: 8px;
            font-size: 13px;
            color: var(--muted);
        }

        .section-label {
            margin: 10px 0 18px;
            font-weight: 700;
            font-size: 20px;
        }

        .button-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
        }

        .action-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: 0.2s ease;
        }

        .action-card:hover {
            transform: translateY(-3px);
        }

        .action-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #fff;
        }

        .action-desc {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.5;
            min-height: 40px;
            margin-bottom: 16px;
        }

        .action-btn {
            display: inline-block;
            width: 100%;
            text-align: center;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .action-btn:hover {
            background: var(--primary-hover);
        }

        .mini-referral-box {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 999;
        }

        .mini-copy-btn {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            border: none;
            border-radius: 30px;
            padding: 12px 18px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(0,0,0,0.3);
            transition: all 0.2s ease;
        }

        .mini-copy-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(0,0,0,0.4);
        }

        .mini-copy-btn:active {
            transform: scale(0.97);
        }
    </style>
</head>
<body>

<div class="wrapper">

    <?php if (!empty($short_url) || !empty($base_link)): ?>
        <div class="mini-referral-box">
            <button type="button" class="mini-copy-btn" onclick="copyReferralLink()">
                🔗 Copy My Referral Link
            </button>
        </div>
    <?php endif; ?>

    <div class="topbar">
        <div>
            <h1>
                <?php if (!empty($logged_role_name)) : ?>
                    <?= htmlspecialchars($logged_role_name) ?>
                <?php else: ?>
                    Dashboard
                <?php endif; ?>
            </h1>

            <div class="muted">Welcome, <?= $logged_admin_name ?></div>

            <?php if (!empty($joiningFormatted)): ?>
                <div class="todayline">
                    Joining Date : <?= $joiningFormatted ?>
                    (<?= $daysSinceJoining ?> <?= $daysSinceJoining == 1 ? 'day' : 'days' ?>)
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="section-label">Quick Actions</div>

    <div class="button-grid">

        <div class="action-card">
            <div class="action-title">Assign Account Operator</div>
            <div class="action-desc">Open account operator assignment page.</div>
            <a class="action-btn" href="/adminconsole/operations/assign_ac_manager.php">Open</a>
        </div>

        <div class="action-card">
            <div class="action-title">OTP Report</div>
            <div class="action-desc">Open OTP report page.</div>
            <a class="action-btn" href="/adminconsole/operations/otplist.php">Open</a>
        </div>

        <div class="action-card">
            <div class="action-title">All Employer List</div>
            <div class="action-desc">Open all employer list page.</div>
            <a class="action-btn" href="/adminconsole/operations/recuiter_list.php">Open</a>
        </div>

        <div class="action-card">
            <div class="action-title">Update User</div>
            <div class="action-desc">Open user update page.</div>
            <a class="action-btn" href="/adminconsole/operations/user_update.php">Open</a>
        </div>

        <div class="action-card">
            <div class="action-title">Lead Entry</div>
            <div class="action-desc">Open lead entry form.</div>
            <a class="action-btn" href="/adminconsole/operations/lead.php">Open</a>
        </div>

        <div class="action-card">
            <div class="action-title">Lead List</div>
            <div class="action-desc">Open leads listing page.</div>
            <a class="action-btn" href="/adminconsole/operations/lead_list.php">Open</a>
        </div>

        <div class="action-card">
            <div class="action-title">Recruiter KYC Report</div>
            <div class="action-desc">Open recruiter KYC report page.</div>
            <a class="action-btn" href="/adminconsole/operations/recruiter_kyc_report.php">Open</a>
        </div>

        <div class="action-card">
            <div class="action-title">Subscription Invoice List</div>
            <div class="action-desc">Open subscription invoice list page.</div>
            <a class="action-btn" href="/adminconsole/operations/subscription_invoicelist.php">Open</a>
        </div>

    </div>
</div>

<script>
    const referralLink = <?= json_encode($short_url ?: $base_link) ?>;

    function copyReferralLink() {
        if (!referralLink) return;

        navigator.clipboard.writeText(referralLink).then(() => {
            alert('Referral link copied ✅');
        }).catch(() => {
            alert('Copy failed ❌');
        });
    }
</script>

</body>
</html>