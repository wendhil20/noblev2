<?php
// trucklist-update.php
// File: admin/ui-productspecialist/backend/backend-page-4/trucklist-update.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];

$id                = (int)($data['id']             ?? 0);
$nametruck         = trim(htmlspecialchars($data['nametruck']    ?? '', ENT_QUOTES, 'UTF-8'));
$trucktype         = trim(htmlspecialchars($data['trucktype']    ?? '', ENT_QUOTES, 'UTF-8'));
$truckvariant      = trim(htmlspecialchars($data['truckvariant'] ?? '', ENT_QUOTES, 'UTF-8'));
$basefare          = round((float)($data['basefare']          ?? 0), 2);
$addperkm          = round((float)($data['addperkm']          ?? 0), 2);
$perkmrate         = round((float)($data['perkmrate']         ?? 0), 2);
$length            = round((float)($data['length']            ?? 0), 2);
$width             = round((float)($data['width']             ?? 0), 2);
$height            = round((float)($data['height']            ?? 0), 2);
$maxcubicmeter     = round((float)($data['maxcubicmeter']     ?? 0), 2);
$maxweightcapacity = round((float)($data['maxweightcapacity'] ?? 0), 2);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid truck ID.']);
    exit;
}

if (!$nametruck || !$trucktype) {
    echo json_encode(['success' => false, 'message' => 'Truck name and type are required.']);
    exit;
}

$stmt = $conn->prepare("
    UPDATE nobletrucklist SET
        nametruck         = ?,
        trucktype         = ?,
        truckvariant      = ?,
        basefare          = ?,
        addperkm          = ?,
        perkmrate         = ?,
        length            = ?,
        width             = ?,
        height            = ?,
        maxcubicmeter     = ?,
        maxweightcapacity = ?
    WHERE id = ?
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    'sssddddddddi',
    $nametruck, $trucktype, $truckvariant,
    $basefare, $addperkm, $perkmrate,
    $length, $width, $height,
    $maxcubicmeter, $maxweightcapacity,
    $id
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Truck updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
}