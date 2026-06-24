<?php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

header('Content-Type: application/json');

$placename = trim($_POST['placename'] ?? '');
$latitude  = trim($_POST['latitude']  ?? '');
$longitude = trim($_POST['longitude'] ?? '');

if (!$placename || !is_numeric($latitude) || !is_numeric($longitude)) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid input.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO noblewarehousebase (placename, latitude, longitude) VALUES (?, ?, ?)");
$stmt->bind_param("sdd", $placename, $latitude, $longitude);

if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'id' => $conn->insert_id]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Database error.']);
}
$stmt->close();