<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

global $con;
if (!isset($con) || !$con) die("DB connection not initialized.");

$page_title = 'Wallet Payout Request Report';
ob_start();

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function get_str($k, $d = '')
{
    return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d;
}
function get_int($k, $d = 0)
{
    return isset($_GET[$k]) ? (int)$_GET[$k] : $d;
}

$status = isset($_GET['status']) ? trim($_GET['status']) : '';
if (!isset($_GET['status'])) {
    $status = '2'; // default only first time
}
$q = get_str('q', '');
$created_from = get_str('created_from', '');
$created_to = get_str('created_to', '');
$page = max(1, get_int('page', 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$where = [];
$types = '';
$params = [];

if ($status !== '' && $status !== null && in_array($status, ['0', '1', '2'], true)) {
    $where[] = "w.payu_status = ?";
    $types .= "i";
    $params[] = (int)$status;
}

if ($q !== '') {
    $where[] = "(
        w.user_id LIKE CONCAT('%',?,'%')
        OR w.wallet_txn_id LIKE CONCAT('%',?,'%')
        OR w.beneficiary_id LIKE CONCAT('%',?,'%')
        OR w.merchant_ref_id LIKE CONCAT('%',?,'%')
        OR u.mobile_no LIKE CONCAT('%',?,'%')
        OR rp.organization_name LIKE CONCAT('%',?,'%')
        OR rp.contact_person_name LIKE CONCAT('%',?,'%')
        OR cp.candidate_name LIKE CONCAT('%',?,'%')
        OR pp.name LIKE CONCAT('%',?,'%')
    )";
    $types .= "sssssssss";
    $params = array_merge($params, array_fill(0, 9, $q));
}

if ($created_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $created_from)) {
    $where[] = "DATE(w.created_at) >= ?";
    $types .= "s";
    $params[] = $created_from;
}

if ($created_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $created_to)) {
    $where[] = "DATE(w.created_at) <= ?";
    $types .= "s";
    $params[] = $created_to;
}

$sql_where = $where ? " WHERE " . implode(" AND ", $where) : "";

$sql_base = "
FROM jos_app_wallet_payout_transfer w
LEFT JOIN jos_app_users u ON u.id = w.user_id
LEFT JOIN jos_app_user_payout_accounts pa ON pa.id = w.beneficiary_id

LEFT JOIN jos_app_recruiter_profile rp ON u.profile_type_id = 1 AND rp.id = u.profile_id
LEFT JOIN jos_app_candidate_profile cp ON u.profile_type_id = 2 AND cp.id = u.profile_id
LEFT JOIN jos_app_promoter_profile pp ON u.profile_type_id = 3 AND pp.id = u.profile_id
";

$count_sql = "SELECT COUNT(*) " . $sql_base . $sql_where;
$stmt = $con->prepare($count_sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$pages = max(1, ceil($total / $per_page));

$sql = "
SELECT 
    w.id,
    w.user_id,
    pa.name AS beneficiary_name,
    pa.account_no,
    pa.ifsc,
    pa.vpa,
    pa.ac_type,
    w.wallet_txn_id,
    w.beneficiary_id,
    w.merchant_ref_id,
    w.amount,
    w.purpose,
    w.payment_type,
    w.pid,
    w.payout_merchant_id,
    w.payu_status,
    w.payu_msg,
    w.payu_payout_id,
    w.utr,
    w.created_at,
    w.updated_at,
    u.mobile_no,
    u.profile_type_id,
    COALESCE(
        NULLIF(rp.organization_name,''),
        NULLIF(rp.contact_person_name,''),
        NULLIF(cp.candidate_name,''),
        NULLIF(pp.name,''),
        u.mobile_no
    ) AS user_name
" . $sql_base . $sql_where . "
ORDER BY w.id DESC
LIMIT $per_page OFFSET $offset
";

$stmt = $con->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$sum_sql = "
SELECT
    SUM(CASE WHEN payu_status = 0 THEN 1 ELSE 0 END) AS failed_count,
    SUM(CASE WHEN payu_status = 1 THEN 1 ELSE 0 END) AS success_count,
    SUM(CASE WHEN payu_status = 2 THEN 1 ELSE 0 END) AS process_count,
    SUM(CASE WHEN payu_status = 0 THEN amount ELSE 0 END) AS failed_amount,
    SUM(CASE WHEN payu_status = 1 THEN amount ELSE 0 END) AS success_amount,
    SUM(CASE WHEN payu_status = 2 THEN amount ELSE 0 END) AS process_amount
FROM jos_app_wallet_payout_transfer
";
$sum_res = $con->query($sum_sql);
$summary = $sum_res ? $sum_res->fetch_assoc() : [];



//status update

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $utr = trim($_POST['utr']);
    $status = (int)$_POST['status'];

    $stmt = $con->prepare("
        UPDATE jos_app_wallet_payout_transfer 
        SET utr = ?, payu_status = ?, updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->bind_param("sii", $utr, $status, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}
?>

<link rel="stylesheet" href="/adminconsole/assets/ui.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
    .table-wrap {
        width: 100%;
        overflow-x: auto
    }

    .table {
        min-width: 1400px
    }

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        margin-bottom: 14px
    }

    .stat-box {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 14px;
        background: #fff
    }

    .stat-box b {
        font-size: 22px;
        display: block;
        margin-top: 4px
    }

    .badge.success {
        background: #dcfce7;
        color: #166534
    }

    .badge.danger {
        background: #fee2e2;
        color: #991b1b
    }

    .badge.warn {
        background: #fef3c7;
        color: #92400e
    }

    .badge {
        display: inline-block;
        white-space: nowrap;
        /* 🔥 prevent line break */
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
    }

    .topcards {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin: 10px 0 14px;
    }

    .scard {
        min-width: 160px;
        flex: 1;
        max-width: 220px;
        background: #0f1a2e;
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 14px;
        padding: 12px;
        cursor: pointer;
        transition: transform .08s ease;
    }

    .scard:hover {
        transform: translateY(-1px);
    }

    .scard .k {
        color: #9ca3af;
        font-size: 12px;
    }

    .scard .v {
        font-size: 22px;
        font-weight: 800;
        margin-top: 6px;
        color: #e5e7eb;
    }

    .scard.active {
        outline: 2px solid rgba(34, 197, 94, .65);
    }

    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 999;
        align-items: center;
        justify-content: center;
    }

    .modal-box {
        background: #0f1a2e;
        padding: 20px;
        border-radius: 12px;
        /* width: 320px; */
        color: #fff;
    }

    .modal-box input,
    .modal-box select {
        width: 100%;
        margin-top: 6px;
        margin-bottom: 10px;
        padding: 8px;
        border-radius: 6px;
        border: 1px solid #334155;
        background: #020617;
        color: #fff;
    }
</style>

<!-- MODAL -->
<div id="statusModal" class="modal">
    <div class="modal-box">
        <h3>Update Payout Status</h3>

        <form method="post" id="statusForm">
            <input type="hidden" name="id" id="modal_id">

            <label>UTR Number</label>
            <input type="text" name="utr" id="modal_utr" class="inp" required>

            <label>Status</label>
            <select name="status" id="modal_status" class="inp" required>
                <option value="2">In Process</option>
                <option value="1">Success</option>
                <option value="0">Failed</option>
            </select>

            <div style="margin-top:15px;display:flex;gap:10px;">
                <button type="submit" class="btn primary">Update</button>
                <button type="button" class="btn secondary" id="closeModal">Cancel</button>
            </div>
        </form>
    </div>
</div>
<!-- modal end -->




<div class="master-wrap">
    <div class="headbar">
        <h2 style="margin:0"><?= h($page_title) ?></h2>
    </div>

    <div class="card">

        <?php
        $success_count = (int)($summary['success_count'] ?? 0);
        $process_count = (int)($summary['process_count'] ?? 0);
        $failed_count  = (int)($summary['failed_count'] ?? 0);

        $totalAll = $success_count + $process_count + $failed_count;
        ?>

        <div class="topcards" id="statusCards">

            <div class="scard <?= ($status === '' ? 'active' : '') ?>" data-status="all">
                <div class="k">All</div>
                <div class="v"><?= $totalAll ?></div>
            </div>

            <div class="scard <?= ($status === '1' ? 'active' : '') ?>" data-status="1">
                <div class="k">Success</div>
                <div class="v"><?= $success_count ?></div>
            </div>

            <div class="scard <?= ($status === '2' ? 'active' : '') ?>" data-status="2">
                <div class="k">In Process</div>
                <div class="v"><?= $process_count ?></div>
            </div>

            <div class="scard <?= ($status === '0' ? 'active' : '') ?>" data-status="0">
                <div class="k">Failed</div>
                <div class="v"><?= $failed_count ?></div>
            </div>

        </div>

        <form method="get" class="toolbar" style="gap:10px;flex-wrap:wrap">
            <input class="inp" type="text" name="q" value="<?= h($q) ?>" placeholder="Search..." style="min-width:280px">

            <select class="inp" name="status">
                <option value="" <?= $status === '' ? 'selected' : '' ?>>Status: All</option>
                <option value="2" <?= $status === '2' ? 'selected' : '' ?>>In Process</option>
                <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Success</option>
                <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Failed</option>
            </select>

            <input class="inp flatpickr" type="text" name="created_from" value="<?= h($created_from) ?>" placeholder="From date">
            <input class="inp flatpickr" type="text" name="created_to" value="<?= h($created_to) ?>" placeholder="To date">

            <button class="btn secondary" type="submit">Apply</button>
            <a class="btn secondary" href="?status=2">Reset</a>
        </form>

        <div style="display:flex;align-items:center;gap:10px;margin:10px 0">
            <span class="badge">Total: <?= (int)$total ?></span>
            <span class="badge">Page <?= (int)$page ?> / <?= (int)$pages ?></span>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>SR</th>
                        <th>User</th>
                        <th>Mobile</th>
                        <th>Beneficiary</th>
                        <th>Merchant Ref</th>
                        <th>Amount</th>
                        <th>Purpose</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Message</th>
                        <th>PayU Payout ID</th>
                        <th>UTR</th>
                        <th>Created</th>
                        <th>Updated at</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sr = $offset + 1;
                    while ($row = $res->fetch_assoc()):
                        $st = (int)$row['payu_status'];
                        if ($st === 1) {
                            $status_badge = '<span class="badge success">Success</span>';
                        } elseif ($st === 2) {
                            $status_badge = '<span class="badge warn">In Process</span>';
                        } else {
                            $status_badge = '<span class="badge danger">Failed</span>';
                        }
                    ?>
                        <tr>
                            <td><?= $sr++ ?></td>
                            <td>
                                <b><?= h($row['user_name']) ?></b><br>
                                <!-- <small>User ID: <?= (int)$row['user_id'] ?></small> -->

                            </td>
                            <td><?= h($row['mobile_no']) ?></td>
                            <td>

                                <?php if ((int)$row['ac_type'] === 1): ?>

                                    <span style="font-size:11px;color:#22c55e;font-weight:600;">BANK ACCOUNT</span><br>
                                    <b><?= h($row['beneficiary_name']) ?></b><br>
                                    <small>
                                        A/C: <?= h($row['account_no']) ?><br>
                                        IFSC: <?= h($row['ifsc']) ?>
                                    </small>

                                <?php elseif ((int)$row['ac_type'] === 2): ?>

                                    <span style="font-size:11px;color:#ffd600;font-weight:600;">VPA (UPI)</span><br>
                                    <b><?= h($row['beneficiary_name']) ?></b><br>
                                    <small>VPA: <?= h($row['vpa']) ?></small>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>
                            <td><?= h($row['merchant_ref_id']) ?></td>
                            <td>₹<?= number_format((float)$row['amount'], 2) ?></td>
                            <td><?= h($row['purpose']) ?></td>
                            <td><?= h($row['payment_type']) ?></td>
                            <td><?= $status_badge ?></td>
                            <td><?= h($row['payu_msg']) ?></td>
                            <td><?= h($row['payu_payout_id']) ?></td>
                            <td><?= h($row['utr']) ?></td>
                            <td>
                                <?= !empty($row['created_at'])
                                    ? h(date('d M Y h:i A', strtotime($row['created_at'])))
                                    : '—' ?>
                            </td>
                            <td>
                                <?= !empty($row['updated_at'])
                                    ? h(date('d M Y h:i A', strtotime($row['updated_at'])))
                                    : '—' ?>
                            </td>
                            <td>
                                <button
                                    class="btn secondary openModalBtn"
                                    data-id="<?= (int)$row['id'] ?>"
                                    data-utr="<?= h($row['utr']) ?>"
                                    data-status="<?= (int)$row['payu_status'] ?>">
                                    Update Status
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($sr === $offset + 1): ?>
                        <tr>
                            <td colspan="15" style="text-align:center;color:#9ca3af">No payout records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
            <?php if ($page > 1): ?>
                <a class="btn secondary" href="?<?= h(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">‹ Prev</a>
            <?php endif; ?>

            <?php if ($page < $pages): ?>
                <a class="btn secondary" href="?<?= h(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Next ›</a>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
    document.querySelectorAll('#statusCards .scard').forEach(card => {
        card.addEventListener('click', function() {
            const status = this.getAttribute('data-status');

            const url = new URL(window.location.href);

            if (status === 'all') {
                url.searchParams.set('status', '');
            } else {
                url.searchParams.set('status', status);
            }
            url.searchParams.delete('page'); // reset pagination

            window.location.href = url.toString();
        });
    });




    const modal = document.getElementById('statusModal');

    document.querySelectorAll('.openModalBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('modal_id').value = this.dataset.id;
            document.getElementById('modal_utr').value = this.dataset.utr;
            document.getElementById('modal_status').value = this.dataset.status;

            modal.style.display = 'flex';
        });
    });

    document.getElementById('closeModal').onclick = () => {
        modal.style.display = 'none';
    };



    document.addEventListener("DOMContentLoaded", function() {
        flatpickr(".flatpickr", {
            altInput: true,
            altFormat: "d-m-Y",
            dateFormat: "Y-m-d",
            allowInput: false
        });
    });
</script>

<?php
$stmt->close();
echo ob_get_clean();
