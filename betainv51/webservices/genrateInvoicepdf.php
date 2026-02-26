<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/initialize.php';
require_once 'dompdf/autoload.inc.php';

use Dompdf\Dompdf;

date_default_timezone_set("Asia/Kolkata");

// Get invoice number
$invoice_no = isset($_GET['invoice_no']) ? trim($_GET['invoice_no']) : '';
if (empty($invoice_no)) {
    die("Invoice number is required.");
}

// Fetch subscription data
$query = "SELECT * FROM jos_app_usersubscriptionlog WHERE invoiceno = '$invoice_no' LIMIT 1";
$result = mysqli_query($con, $query) or die("Query error: " . mysqli_error($con));
$row = mysqli_fetch_assoc($result);
if (!$row) {
    die("No invoice found with this number.");
}

// Fetch plan name
$plan_id = $row['plan_id'];
$plan_name = '';
$plan_result = mysqli_query($con, "SELECT plan_name FROM jos_app_subscription_plans WHERE id = '$plan_id'");
if ($plan_result && mysqli_num_rows($plan_result) > 0) {
    $plan = mysqli_fetch_assoc($plan_result);
    $plan_name = $plan['plan_name'];
}

// Fetch user name
$userid = $row['userid'];
$profile_type_id = $row['profile_type_id'];
$user_display_name = '';

if ($profile_type_id == 2) {
    $q = mysqli_query($con, "SELECT candidate_name FROM jos_app_candidate_profile WHERE userid = '$userid'");
    if ($q && mysqli_num_rows($q) > 0) {
        $d = mysqli_fetch_assoc($q);
        $user_display_name = $d['candidate_name'];
    }
} elseif ($profile_type_id == 1) {
    $q = mysqli_query($con, "SELECT organization_name FROM jos_app_recruiter_profile WHERE userid = '$userid'");
    if ($q && mysqli_num_rows($q) > 0) {
        $d = mysqli_fetch_assoc($q);
        $user_display_name = $d['organization_name'];
    }
}

// Date formatting
function formatDateShort($dateStr) {
    return date("d-m-y", strtotime($dateStr));
}
function formatDateLong($dateStr) {
    return date("d F Y", strtotime($dateStr));
}

$invoice_date   = formatDateShort($row['created_at']);
$validity_text  = formatDateLong($row['start_date']) . " to " . formatDateLong($row['end_date']);

// GST calculation
$total = floatval($row['amount_paid']); // inclusive
$gst_percent = 18;
$gst_amount = round(($total * $gst_percent) / (100 + $gst_percent), 2);
$base_price = round($total - $gst_amount, 2);

// Branding
$company_name    = "PACIFIC PLACEMENTS AND BUSINESS CONSULTANCY
 (OPC) PRIVATE LIMITED";
$company_logo    = "https://pacificconnect2.0.inv51.in/uploads/pacific_iconnect_small.png";
$company_address = "UG-01, Shrushti Vista,678 E Ward,2nd Lane, Shahupuri, Tal. Karveer, Dist. Kolhapur, Maharashtra 416001 India.<br>GSTIN: 27AALCP2725P1ZT";

// HTML content
$html = "
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 14px; color: #333; }
    .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .logo { width: 150px; }
    .title { font-size: 20px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
    .footer { margin-top: 40px; font-size: 12px; text-align: center; color: #888; }
</style>

<div class='invoice-box'>
    <div class='header'>
        <div><img src='$company_logo' class='logo' /></div>
        <div style='text-align:right'>
            <div class='title'>$company_name</div>
            <div>$company_address</div>
        </div>
    </div>

    <h2 style='text-align:center;'>Subscription Invoice</h2>

    <table>
        <tr><th>Invoice No</th><td>{$row['invoiceno']}</td></tr>
        <tr><th>Invoice Date</th><td>{$invoice_date}</td></tr>
        <tr><th>Name</th><td>$user_display_name</td></tr>
        <tr><th>Plan</th><td>$plan_name</td></tr>
        <tr><th>Base Price</th><td>₹ {$base_price}</td></tr>
        <tr><th>GST (18%)</th><td>₹ {$gst_amount}</td></tr>
        <tr><th>Total Paid</th><td><strong>₹ {$total}</strong></td></tr>
        <tr><th>Payment ID</th><td>{$row['payment_id']}</td></tr>
        <tr><th>Payment Status</th><td>{$row['payment_status']}</td></tr>
        <tr><th>Validity</th><td>$validity_text</td></tr>
    </table>

    <div class='footer'>
        Thank you for subscribing to our services.<br>
        This is a computer-generated invoice and does not require a signature.
    </div>
</div>
";

// Create Dompdf
$dompdf = new Dompdf();
$dompdf->set_option('isRemoteEnabled', true); // Enable external image loading
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output
$pdf_filename = "Invoice_{$row['invoiceno']}.pdf";
$dompdf->stream($pdf_filename, ["Attachment" => true]);
?>
