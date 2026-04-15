<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_config.php';
$userid = $_SESSION['user_id'] ?? 0;
$profile_type_id = (int)$_SESSION['user']['profile_type_id'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pacific iConnect | Academy & Performance</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .promoter-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* Header Section */
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 0.5rem;
        }

        .income-badge {
            background: var(--gradient);
            color: white;
            padding: 8px 20px;
            border-radius: 100px;
            font-weight: 700;
            display: inline-block;
            font-size: 1.1rem;
            box-shadow: 0 10px 20px rgba(0, 82, 212, 0.2);
        }

        .intro-text {
            max-width: 700px;
            margin: 1.5rem auto;
            color: var(--text-muted);
            font-weight: 500;
            line-height: 1.6;
        }

        /* Bento Grid Layout */
        .academy-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 1.5rem;
        }

        .step-card {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            border: 1px solid #E2E8F0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .step-card:hover {
            transform: translateY(-5px);
            border-color: var(--pacific-blue);
            box-shadow: var(--shadow);
        }

        .step-tag {
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--pacific-blue);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 0.5rem;
        }

        .step-card h3 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Content Styling */
        .info-bit {
            background: #F1F5F9;
            padding: 12px;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-top: 1rem;
            border-left: 4px solid var(--pacific-blue);
        }

        .tip-bit {
            background: #F0FDF4;
            padding: 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-top: 0.75rem;
            color: #166534;
        }

        .calc-box {
            background: #FFFBEB;
            border: 1px dashed #F59E0B;
            padding: 15px;
            border-radius: 15px;
            margin: 1rem 0;
            text-align: center;
            font-weight: 700;
        }

        /* Icon sets */
        .tool-icons {
            display: flex;
            gap: 15px;
            margin-top: 1rem;
        }

        .tool-icons i {
            width: 40px;
            height: 40px;
            background: #EEF2FF;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--pacific-blue);
        }

        .action-list {
            list-style: none;
            margin-top: 1rem;
        }

        .action-list li {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .action-list i {
            color: #10B981;
        }

        /* My Performance Section */
        .performance-section {
            margin-top: 5rem;
            padding-top: 3rem;
            border-top: 2px solid #E2E8F0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .stat-mini-card {
            background: white;
            padding: 1.5rem;
            border-radius: 20px;
            border: 1px solid #E2E8F0;
            text-align: center;
        }

        .stat-mini-card h4 {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .stat-mini-card .val {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--pacific-blue);
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.8rem;
            }

            .academy-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php
    include __DIR__ . "/../includes/preloader.php";

    if ($profile_type_id == 3) {
        include __DIR__ . "/../includes/promoter_header.php";
    } else {
        include __DIR__ . "/../includes/header.php";
    }
    ?>
    <div class="promoter-container">
        <section class="page-header">
            <div class="income-badge">Earn ₹20,000+ / Month</div>
            <h1 style="margin-top:15px;">Promoter <span>Academy</span></h1>
            <p class="intro-text">
                You don’t need thousands of followers — just regular effort and genuine connections.
                <strong>Consistency + Smart Sharing + Local Network = Real Earnings Growth.</strong>
            </p>
        </section>

        <div class="academy-grid">
            <div class="step-card">
                <span class="step-tag">Step 01</span>
                <h3><i class="fas fa-wallet"></i> Earning Model</h3>
                <p>Earn <strong>Rs.15</strong> on every Successful Registration through your referral link.</p>
                <div class="info-bit">💡 <strong>Key Idea:</strong> Small daily efforts build steady income. 30–50 daily regs create massive potential.</div>
                <div class="tip-bit">⭐ <strong>Success Tip:</strong> Focus on helping people first to build natural trust.</div>
            </div>

            <div class="step-card">
                <span class="step-tag">Step 02</span>
                <h3><i class="fas fa-bullseye"></i> Monthly Target</h3>
                <div class="calc-box">
                    45 Reg × ₹15 = ₹675/day <br>
                    ₹675 × 30 days ≈ ₹20,000/mo
                </div>
                <p>Focus on Freshers, Students, and Job Channels. Break targets into small daily goals.</p>
                <div class="tip-bit">⭐ <strong>Success Tip:</strong> Consistency matters more than bulk posting.</div>
            </div>

            <div class="step-card">
                <span class="step-tag">Step 03</span>
                <h3><i class="fas fa-photo-film"></i> Promotion Tools</h3>
                <p>Use our ready-to-use Promotion Kit. No design skills required!</p>
                <div class="tool-icons">
                    <i class="fab fa-instagram" title="Instagram Reels"></i>
                    <i class="fab fa-youtube" title="YT Shorts"></i>
                    <i class="fab fa-whatsapp" title="Templates"></i>
                    <i class="fas fa-image" title="Posters"></i>
                </div>
                <div class="info-bit">💡 <strong>Key Idea:</strong> Just share what is provided. Use short, simple captions.</div>
            </div>

            <div class="step-card">
                <span class="step-tag">Step 04</span>
                <h3><i class="fas fa-bolt"></i> Daily Action Plan</h3>
                <p>Spend just 60–90 mins daily following this routine:</p>
                <ul class="action-list">
                    <li><i class="fas fa-check-circle"></i> Share 1 Reel or Status</li>
                    <li><i class="fas fa-check-circle"></i> Post in 3 Active Groups</li>
                    <li><i class="fas fa-check-circle"></i> Reply to users quickly</li>
                    <li><i class="fas fa-check-circle"></i> DM 5-10 Jobseekers</li>
                </ul>
                <div class="tip-bit">⭐ <strong>Success Tip:</strong> Evening time (6-9 PM) gives the