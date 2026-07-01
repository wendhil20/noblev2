<?php
header('Content-Type: application/json');
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

$products = $conn->query("SELECT id, name FROM nobleproduct ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// ── Sizes per product ─────────────────────────────────────────────────────
$sizesByProduct = [];
$sizeRes = $conn->query("
    SELECT c.product_id, v.sizename
    FROM nobleproductvariant v
    INNER JOIN nobleproductcolor c ON c.id = v.color_id
    WHERE v.sizename != ''
    GROUP BY c.product_id, v.sizename
    ORDER BY v.sizename ASC
");
while ($row = $sizeRes->fetch_assoc()) {
    $sizesByProduct[$row['product_id']][] = $row['sizename'];
}

// ── Colors per product ────────────────────────────────────────────────────
$colorsByProduct = [];
$colorRes = $conn->query("
    SELECT id, product_id, colorname
    FROM nobleproductcolor
    ORDER BY colorname ASC
");
while ($row = $colorRes->fetch_assoc()) {
    $colorsByProduct[$row['product_id']][] = [
        'id'        => (int) $row['id'],
        'colorname' => $row['colorname'],
    ];
}

$sql = "
    SELECT pr.id, pr.product_id, p.name AS product_name,
           pr.color_id, c.colorname,
           pr.sizename, pr.discount_percent,
           pr.start_date, pr.end_date,
           CASE
             WHEN NOW() < pr.start_date THEN 'upcoming'
             WHEN NOW() > pr.end_date THEN 'expired'
             ELSE 'active'
           END AS status
    FROM nobleproductpromo pr
    INNER JOIN nobleproduct p ON p.id = pr.product_id
    LEFT JOIN nobleproductcolor c ON c.id = pr.color_id
    ORDER BY pr.start_date DESC
";
$rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$promos = array_map(function ($r) {
    return [
        'id'                => (int) $r['id'],
        'product_id'        => (int) $r['product_id'],
        'product_name'      => $r['product_name'],
        'color_id'          => $r['color_id'] !== null ? (int) $r['color_id'] : null,
        'colorname'         => $r['colorname'], // null = all colors
        'sizename'          => $r['sizename'],  // null = all sizes
        'discount_percent'  => rtrim(rtrim($r['discount_percent'], '0'), '.'),
        'start_date'        => date('M d, Y g:i A', strtotime($r['start_date'])),
        'end_date'          => date('M d, Y g:i A', strtotime($r['end_date'])),
        'start_date_raw'    => date('Y-m-d\TH:i', strtotime($r['start_date'])),
        'end_date_raw'      => date('Y-m-d\TH:i', strtotime($r['end_date'])),
        'status'            => $r['status'],
    ];
}, $rows);

echo json_encode([
    'products'          => $products,
    'promos'            => $promos,
    'sizes_by_product'  => $sizesByProduct,
    'colors_by_product' => $colorsByProduct,
]);