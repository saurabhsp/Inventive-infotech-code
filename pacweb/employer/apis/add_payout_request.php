<?php
@ini_set('display_errors','1');
@error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/initialize.php'; // $con (mysqli)
date_default_timezone_set('Asia/Kolkata');

function send_json($arr, $code=200){
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

function table_exists(mysqli $con, string $table): bool {
  $t = mysqli_real_escape_string($con, $table);
  $r = mysqli_query($con, "SHOW TABLES LIKE '$t'");
  return ($r && mysqli_num_rows($r) > 0);
}

function get_wallet_balance_from_logs(mysqli $con, int $userId): float {
  // Assumption: transaction_type 1=Deposit(Credit), 2=Withdraw(Debit)
  // status: 1=Success only counts in balance, (2=inprocess not counted), failed ignored
  $sql = "
    SELECT
      COALESCE(SUM(CASE WHEN transaction_type=1 AND status=1 THEN amount ELSE 0 END),0) AS credit,
      COALESCE(SUM(CASE WHEN transaction_type=2 AND status=1 THEN amount ELSE 0 END),0) AS debit
    FROM jos_app_wallet_transaction_log
    WHERE user_id = ?
  ";
  $st = $con->prepare($sql);
  $st->bind_param("i",$userId);
  $st->execute();
  $res = $st->get_result()->fetch_assoc();
  $credit = (float)$res['credit'];
  $debit  = (float)$res['debit'];
  return $credit - $debit;
}

$raw = file_get_contents("php://input");
$in  = json_decode($raw, true);

if (!is_array($in)) send_json(["status"=>false,"message"=>"Invalid JSON","requestRaw"=>$raw], 400);

$user_id       = (int)($in['user_id'] ?? 0);
$amount        = (float)($in['amount'] ?? 0);
$beneficiary_id= (int)($in['beneficiary_id'] ?? 0);
$purpose       = trim((string)($in['purpose'] ?? 'Wallet Withdraw'));
$payment_type  = strtoupper(trim((string)($in['payment_type'] ?? 'IMPS')));

if ($user_id <= 0) send_json(["status"=>false,"message"=>"user_id required"], 400);
if ($amount <= 0)  send_json(["status"=>false,"message"=>"amount must be > 0"], 400);
if ($beneficiary_id <= 0) send_json(["status"=>false,"message"=>"beneficiary_id required"], 400);

$con->begin_transaction();

try {

  // --------- BALANCE CHECK ----------
  // If you have a separate balance table, you can plug it here.
  // Auto-detect a common table name; otherwise compute from logs.
  $balance = null;

  if (table_exists($con, 'jos_app_wallet')) {
    // Example table (if exists): jos_app_wallet(user_id,balance)
    $st = $con->prepare("SELECT balance FROM jos_app_wallet WHERE user_id=? LIMIT 1");
    $st->bind_param("i",$user_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if ($row) $balance = (float)$row['balance'];
  }

  if ($balance === null) {
    $balance = get_wallet_balance_from_logs($con, $user_id);
  }

  if ($balance < $amount) {
    $con->rollback();
    send_json([
      "status"=>false,
      "message"=>"Insufficient wallet balance",
      "balance"=>$balance,
      "amount"=>$amount
    ], 400);
  }

  $now = date("Y-m-d H:i:s");

  // --------- 1) INSERT WALLET DEBIT (IN PROCESS) ----------
  $transaction_type = 2; // withdraw
  $status = 2;           // in process
  $payment_mode = 'PAYU_PAYOUT';

  $remark = "Wallet withdraw request (pending)";

  $st = $con->prepare("
    INSERT INTO jos_app_wallet_transaction_log
      (transaction_type, user_id, transaction_datetime, payment_mode, remark, amount, status, ref_table, ref_id)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, NULL, NULL)
  ");
  $st->bind_param("iisssdi", $transaction_type, $user_id, $now, $payment_mode, $remark, $amount, $status);
  $st->execute();
  $wallet_txn_id = (int)$con->insert_id;

  // Optional: if balance table exists, you may want to HOLD amount by deducting now.
  // If you prefer deduct only on success, remove this block.
  if (table_exists($con, 'jos_app_wallet')) {
    $st = $con->prepare("UPDATE jos_app_wallet SET balance = balance - ? WHERE user_id=?");
    $st->bind_param("di", $amount, $user_id);
    $st->execute();
  }

  // --------- 2) INSERT PAYOUT TRANSFER ROW (PENDING) ----------
  $tmpMerchant = 'TMP_' . $wallet_txn_id . '_' . time();

  $st = $con->prepare("
    INSERT INTO jos_app_wallet_payout_transfer
      (user_id, wallet_txn_id, beneficiary_id, merchant_ref_id, amount, purpose, payment_type, payu_status, created_at)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, 2, ?)
  ");
  $st->bind_param("iiisdsss", $user_id, $wallet_txn_id, $beneficiary_id, $tmpMerchant, $amount, $purpose, $payment_type, $now);
  $st->execute();
  $payout_id = (int)$con->insert_id;

  // Final merchantRefId
  $merchantRefId = 'PAYOUT_' . $payout_id;

  $st = $con->prepare("UPDATE jos_app_wallet_payout_transfer SET merchant_ref_id=?, updated_at=? WHERE id=?");
  $st->bind_param("ssi", $merchantRefId, $now, $payout_id);
  $st->execute();

  // Link wallet row to payout row
  $ref_table = 'payu_payout';
  $st = $con->prepare("UPDATE jos_app_wallet_transaction_log SET ref_table=?, ref_id=? WHERE id=?");
  $st->bind_param("sii", $ref_table, $payout_id, $wallet_txn_id);
  $st->execute();

  $con->commit();

  send_json([
    "status" => true,
    "message" => "Withdraw request created",
    "user_id" => $user_id,
    "amount" => $amount,
    "wallet_txn_id" => $wallet_txn_id,
    "payout_id" => $payout_id,
    "merchantRefId" => $merchantRefId,
    "payment_type" => $payment_type,
    "purpose" => $purpose
  ]);

} catch (Throwable $e) {
  $con->rollback();
  send_json([
    "status"=>false,
    "message"=>"Server error",
    "error"=>$e->getMessage()
  ], 500);
}
