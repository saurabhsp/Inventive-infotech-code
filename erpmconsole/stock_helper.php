<?php
/* ============================================================
 * CORE PHP STOCK HELPERS (NO CLASS, NO STATIC)
 * ============================================================ */

function get_actual_stock(
    mysqli $con,
    int $product_id,
    int $from_gid,
    int $yrid,
    ?string $batch = null
): float {

    $grn     = get_doc_stock($con, '2,3', $product_id, $from_gid, $yrid, $batch);
    $issue   = get_doc_stock($con, '5', $product_id, $from_gid, $yrid, $batch);
    $accept  = get_doc_stock($con, '6', $product_id, $from_gid, $yrid, $batch);
    $sale    = get_doc_stock($con, '12', $product_id, $from_gid, $yrid, $batch);
    $sret    = get_doc_stock($con, '14', $product_id, $from_gid, $yrid, $batch);
    $pret    = get_doc_stock($con, '15', $product_id, $from_gid, $yrid, $batch);
    $damage  = get_doc_stock($con, '13', $product_id, $from_gid, $yrid, $batch);
    $gatepassOut  = get_doc_stock($con, '27', $product_id, $from_gid, $yrid, $batch);
    $gatepassIn  = get_doc_stock($con, '28', $product_id, $from_gid, $yrid, $batch);
    $delChalan  = get_doc_stock($con, '29', $product_id, $from_gid, $yrid, $batch);

    $stock = ($grn + $accept + $sret)
        - ($issue + $sale + $pret + $damage  + $gatepassOut + $gatepassIn + $delChalan);
    return max(0, $stock);
}

function get_doc_stock(
    mysqli $con,
    int $doc,
    int $product_id,
    int $gid,
    int $yrid,
    ?string $batch
): float {

    $where = " AND b.propid=? AND b.gid=? AND b.yrid=? AND b.status!=2 ";
    $types = "iii";
    $vals  = [$product_id, $gid, $yrid];

    if ($batch) {
        $where .= " AND b.batch=? ";
        $types .= "s";
        $vals[] = $batch;
    }

    switch ($doc) {

        case '2,3':
            $sql = "SELECT COALESCE(SUM(qty),0)
                    FROM jos_ierp_grn_grid b
                    WHERE b.doc IN (2,3) $where";
            break;

        case '5':
        case '6':
            $sql = "SELECT COALESCE(SUM(qty),0)
                    FROM jos_ierp_stkrequest_grid b
                    WHERE b.doc IN ($doc) $where";
            break;

        case '12':
            $sql = "SELECT COALESCE(SUM(qty),0)
                    FROM jos_ierp_sale_grid b
                    WHERE b.doc=12 $where";
            break;

        case '14':
            $sql = "SELECT COALESCE(SUM(qty),0)
                    FROM jos_ierp_salesret_grid b
                    WHERE b.doc=14 $where";
            break;

        case '15':
            $sql = "SELECT COALESCE(SUM(qty),0)
                    FROM jos_ierp_purchaseret_grid b
                    WHERE b.doc=15 $where";
            break;

        case '13':
            $sql = "SELECT COALESCE(SUM(qty),0)
                    FROM jos_ierp_damage_grid b
                    WHERE b.doc=13 AND b.repair=0 $where";
            break;
        case '27':
            $sql = "SELECT COALESCE(SUM(qty),0)
            FROM jos_ierp_gatepass_grid b
            WHERE b.doc=27 $where";
            break;

        case '28':
            $sql = "SELECT COALESCE(SUM(qty),0)
            FROM jos_ierp_gatepass_grid b
            WHERE b.doc=28 $where";
            break;

        case '29':
            $sql = "SELECT COALESCE(SUM(qty),0)
            FROM jos_ierp_deliverychallan_grid b
            WHERE b.doc=29 $where";
            break;


        default:
            return 0;
    }

    $st = $con->prepare($sql);
    $st->bind_param($types, ...$vals);
    $st->execute();
    $st->bind_result($qty);
    $st->fetch();
    $st->close();

    return (float)$qty;
}
