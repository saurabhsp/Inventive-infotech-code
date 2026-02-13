<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/initialize.php';
require_once __DIR__ . '/../includes/aclhelper.php';

if (!is_logged_in()) redirect('../login.php');

$con = $con ?? null;
if (!$con instanceof mysqli) die('DB missing');

$gatepass_id = (int)($_POST['gatepass_id'] ?? 0);
if ($gatepass_id <= 0) die('Invalid Gatepass ID');

/* ==========================
   FETCH HEADER
========================== */
$sql = "
    SELECT
    g.id,
    g.billno,
    g.date,
    g.sysdate,
    g.remark,
    g.tolc,
    g.fromlc,
    g.created_by,
    g.company,
    g.doc,
    u.name            AS created_by_name,
    l.location_name   AS from_location
FROM jos_ierp_gatepass g
LEFT JOIN jos_admin_users u
       ON u.id = g.created_by
LEFT JOIN jos_erp_gidlocation l
       ON l.gid = g.fromlc
WHERE g.id = ?
LIMIT 1";

$st = $con->prepare($sql);
$st->bind_param("i", $gatepass_id);
$st->execute();
$hdr = $st->get_result()->fetch_assoc();
$st->close();

if (!$hdr) die('Record not found');

/* ==========================
   FETCH ITEMS
========================== */
// $sql = "
// SELECT
//     gg.id,
//     gg.billid,
//     gg.propid,
//     gg.qty,
//     gg.sec_qty,
//     gg.thirdqty,
//     gg.sec_width,
//     gg.sec_height,
//     gg.third_width,
//     gg.third_height,
//     gg.description,
//     gg.uom,
//     um.unit AS uom_name,
//     p.name AS product_name
// FROM jos_ierp_gatepass_grid gg
// LEFT JOIN jos_crm_mproducts p 
//        ON p.id = gg.propid
// LEFT JOIN jos_ierp_munit um
//        ON um.id = gg.uom
// WHERE gg.billid = ?
// ORDER BY gg.id ASC;
// ";

$sql = "
SELECT
    gg.id,
    gg.billid,
    gg.propid,

    gg.qty,
    gg.sec_qty,
    gg.thirdqty,
    gg.description,
    gg.uom,

    p.name AS product_name,

    p.unit          AS unit_id,
    p.secondaryunit AS sec_unit_id,
    p.thirdunit     AS third_unit_id,

    p.sec_width,
    p.sec_height,
    p.third_width,
    p.third_height,

    u1.unit AS unit_name,
    u2.unit AS sec_unit_name,
    u3.unit AS third_unit_name,
    ug.unit AS uom_name

FROM jos_ierp_gatepass_grid gg

LEFT JOIN jos_crm_mproducts p
       ON p.id = gg.propid

LEFT JOIN jos_ierp_munit u1
       ON u1.id = p.unit

LEFT JOIN jos_ierp_munit u2
       ON u2.id = p.secondaryunit

LEFT JOIN jos_ierp_munit u3
       ON u3.id = p.thirdunit

LEFT JOIN jos_ierp_munit ug
       ON ug.id = gg.uom

WHERE gg.billid = ?
ORDER BY gg.id ASC
";



//LEFT JOIN jos_ierp_munit um ON um.id = gg.uom


$st = $con->prepare($sql);
$st->bind_param("i", $gatepass_id);
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
    <title>Material Gatepass Inward Print</title>

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
        <button class="btn btn-back" onclick="window.location.href='/reports/materialgatepassinward_list.php'">Back</button>
    </div>



    <div class="print-wrapper">

        <!-- ================= HEADER ================= -->
        <?php include __DIR__ . '/../includes/print_header.php'; ?>


        <!-- ================= TITLE ================= -->
        <table class="table">
            <tr>
                <th style="font-size:14px">Material Gate Pass Inward</th>
            </tr>
        </table>

        <!-- ================= DETAILS ================= -->
        <!-- ================= STOCK TRANSFER INFO ================= -->
        <table style="width:100%; border-collapse:collapse; margin-top:8px;">
            <tr>
                <!-- LEFT -->
                <td width="33%" style="vertical-align:top; padding:6px;">
                    <div><strong>Date :</strong> <?= dmy($hdr['date']) ?></div>
                    <div>
                        <strong>M.GatePass Inward No. :</strong>
                        <?= fy_from_date($hdr['date']) ?>/<?= h($hdr['billno']) ?>
                    </div>

                </td>

                <!-- CENTER -->
                <td width="34%" style="vertical-align:top; padding:6px;">
                    <div><strong>From :</strong> <?= h($hdr['from_location']) ?></div>
                    <div><strong>To :</strong> <?= h($hdr['tolc']) ?></div>
                    <div><strong> User :</strong> <?= h($hdr['created_by_name']) ?></div>

                </td>

                <!-- RIGHT -->
                <td width="33%" style="vertical-align:top; padding:6px;">
                    <div>
                        <strong>Time :</strong>
                        <?= date('H:i', strtotime($hdr['sysdate'])) ?>
                    </div>
                    <div><strong>Remark :</strong> <?= h($hdr['remark']) ?></div>  

                </td>
            </tr>
        </table>



        <!-- ================= ITEM TABLE ================= -->
        <table class="table">
            <tr>
                <th width="5%">Sr No</th>
                <th width="35%">Description</th>
                <th width="8%">QTY</th>
                <th width="8%">UOM</th>
                <th width="8%">2nd Qty</th>
                <th width="12%">2nd Measure</th>
                <th width="8%">3rd Qty</th>
                <th width="12%">3rd Measure</th>
            </tr>

            <?php
            $sr = 1;
            $totalQty = 0;
            
                

            foreach ($items as $it):
                $totalQty += (float)$it['qty'];

                $secMeasure = ($it['sec_width'] > 0 && $it['sec_height'] > 0)
                    ? $it['sec_width'] . ' x ' . $it['sec_height']
                    : '';

                $thirdMeasure = ($it['third_width'] > 0 && $it['third_height'] > 0)
                    ? $it['third_width'] . ' x ' . $it['third_height']
                    : '';
            ?>
                <tr>
                    <td class="text-center"><?= $sr++ ?></td>
                    <td>
                        <?= h($it['product_name']) ?><br>
                        <small><?= h($it['description']) ?></small>
                    </td>
                     <td class="text-center"><?= h($it['qty']) ?></td> 
                    <!--<td class="text-center"><?= h($it['qty']) ?> <?= h($it['unit_name']) ?></td>-->
                    <td class="text-center"><?= h($it['uom_name']) ?></td>
                    <td class="text-center"><?= h($it['sec_qty']) ?> <?= h($it['sec_unit_name']) ?></td>
                    <td class="text-center"> <?= h($it['thirdqty']) ?> <?= h($it['third_unit_name']) ?></td>
                    <td class="text-center"><?= h($it['thirdqty']) ?></td>
                    <td class="text-center"><?= h($thirdMeasure) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th colspan="2" class="text-center">Total</th>
                <th class="text-right"><?= $totalQty ?></th>
                <th colspan="5"></th>
            </tr>
        </table>

        <!-- ================= SIGNATURE ================= -->
        <table class="table">
            <tr>
                <td width="20%" class="sign-box text-center"><strong><br><br><br>Send By</strong></td>
                <td width="25%" class="sign-box text-center"><strong><br><br><br>Received By</strong></td>
                <td width="55%" class="sign-box text-right">
                    FOR AKASHGANGA CONSTRUCTIONAL MACHINES PVT. LTD<br><br><br>
                    <strong>Authorised By</strong>
                </td>
            </tr>
        </table>

        <!-- ================= NOTE ================= -->
        <table class="table">
            <tr>
                <td style="height:50px;"><strong>Note :</strong><?= h($hdr['remark']) ?></td>
            </tr>
        </table>

    </div>

</body>

</html>