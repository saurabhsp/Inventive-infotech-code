<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/initialize.php';
require_once __DIR__ . '/../includes/aclhelper.php';

if (!is_logged_in()) redirect('../login.php');

$con = $con ?? null;
if (!$con instanceof mysqli) die('DB missing');

$quotation_id = (int)($_POST['quotation_id'] ?? 0);
if ($quotation_id <= 0) die('Invalid Quotation ID');

/* ==========================
   FETCH HEADER
========================== */
$sql = "
    SELECT
        g.salesdate,
        g.saleno,
        g.customer,
        g.address
    FROM jos_erp_sale_order g
    WHERE g.id = ?
    LIMIT 1
";



$st = $con->prepare($sql);
$st->bind_param("i", $quotation_id);
$st->execute();
$hdr = $st->get_result()->fetch_assoc();
$st->close();

if (!$hdr) die('Record not found');

/* ==========================
   FETCH ITEMS
========================== */

$sql = "
    SELECT
        g.id,
        g.saleid,
        g.propid,
        g.qty,
        g.uom,
        p.name AS product_name
    FROM jos_erp_saleorder_grid g

    LEFT JOIN jos_crm_mproducts p
           ON p.id = g.propid

    WHERE g.saleid = ?
    ORDER BY g.id ASC
";




$st = $con->prepare($sql);
$st->bind_param("i", $quotation_id);
$st->execute();
$rs = $st->get_result();

$items = [];
while ($r = $rs->fetch_assoc()) $items[] = $r;
$st->close();

/* ==========================
   HELPERS
========================== */
function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function dmy($d)
{
    if (!$d || $d === '0000-00-00') return '';
    return date('d-m-Y', strtotime($d));
}
function fy_from_date($date)
{
    if (!$date) return '';
    $y = (int)date('Y', strtotime($date));
    $m = (int)date('m', strtotime($date));
    return ($m >= 4)
        ? substr($y, 2, 2) . '-' . substr($y + 1, 2, 2)
        : substr($y - 1, 2, 2) . '-' . substr($y, 2, 2);
}
?>


<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Sale Order Print</title>

    <style>
        @media print {
            body {
                margin: 0;
            }
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
        }

        /* OUTER BORDER */
        .print-wrapper {
            border: 2px solid #000;
            padding: 10px;
            margin: 10px;
        }

        /* HEADER */
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

        /* LOGO */
        .company-logo {
            width: 80px;
        }

        /* COMMON TABLE */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .table th,
        .table td {
            border: 1px solid #000;
            padding: 6px;
        }

        .table th {
            background: #f3f3f3;
            font-weight: bold;
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .sign-box {
            height: 80px;
        }

        /* Buttons */
        .btn {
            padding: 6px 14px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            color: #fff;
        }

        .btn-print {
            background-color: #dc3545;
            /* danger red */
        }

        .btn-back {
            background-color: #28a745;
            /* green */
        }

        .btn:hover {
            opacity: 0.9;
        }

        /* Hide buttons on print */
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>


    <div class="no-print" style="margin:10px;">
        <button class="btn btn-print" onclick="window.print()">Print</button>
        <button class="btn btn-back" onclick="window.location.href='/reports/saleorderlist.php'">Back</button>
    </div>



    <div class="print-wrapper">

        <!-- ================= HEADER ================= -->
        <?php include __DIR__ . '/../includes/print_header.php'; ?>


        <!-- ================= TITLE ================= -->
        <table class="table">
            <tr>
                <th style="font-size:14px">Sale Order</th>
            </tr>
        </table>

        <!-- ================= DETAILS ================= -->
        <!-- ================= STOCK TRANSFER INFO ================= -->
        <table style="width:100%; border-collapse:collapse; margin-top:8px;">
            <tr>
                <!-- LEFT -->
                <td width="50%" style="vertical-align:top; padding:6px;">
                    <div><strong>Date :</strong> <?= dmy($hdr['salesdate']) ?></div>
                    <div>
                        <strong>Sale No. :</strong>
                        <?= fy_from_date($hdr['salesdate']) ?>/<?= h($hdr['saleno']) ?>
                    </div>
                </td>

                <!-- RIGHT -->
                <td width="50%" style="vertical-align:top; padding:6px;">
                    <div><strong>Customer :</strong> <?= h($hdr['customer']) ?></div>
                    <div><strong>Address :</strong> <?= h($hdr['address']) ?></div>
                </td>
            </tr>
        </table>




        <!-- ================= ITEM TABLE ================= -->
        <table class="table">
            <tr>
                <th width="5%">Sr No</th>
                <th width="35%">Product Name</th>
                <th width="8%">QTY</th>
            </tr>

            <?php
            $sr = 1;
            $totalQty = 0;

            foreach ($items as $it):
                $totalQty += (float)$it['qty'];
            ?>
                <tr>
                    <td class="text-center"><?= $sr++ ?></td>

                    <td class="text-center">
                        <?= h($it['product_name']) ?>
                    </td>

                    <td class="text-center"><?= h($it['qty']) ?></td>

                </tr>
            <?php endforeach; ?>

            <tr>
                <th colspan="2" class="text-right">Total</th>
                <th class="text-right"><?= $totalQty ?></th>
            </tr>
        </table>






    </div>

</body>

</html>