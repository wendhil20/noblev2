<?php
// save-map.php

include ROOT_PATH . '/network/connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$input   = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    INSERT INTO nobleuserinformation
        (user_id, contact_number, age, address, city, barangay, postalcode, latitude, longitude)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        contact_number = VALUES(contact_number),
        age            = VALUES(age),
        address        = VALUES(address),
        city           = VALUES(city),
        barangay       = VALUES(barangay),
        postalcode     = VALUES(postalcode),
        latitude       = VALUES(latitude),
        longitude      = VALUES(longitude),
        updated_at     = NOW()
");

$contact_number = $input['contact_number'] ?: null;
$age            = $input['age']            ?: null;
$address        = $input['address']        ?: null;
$city           = $input['city']           ?: null;
$barangay       = $input['barangay']       ?: null;
$postalcode     = $input['postalcode']     ?: null;
$latitude       = $input['latitude']       ?: null;
$longitude      = $input['longitude']      ?: null;

// i ss s s s s dd
$stmt->bind_param('issssssdd',
    $user_id,
    $contact_number,
    $age,
    $address,
    $city,
    $barangay,
    $postalcode,
    $latitude,
    $longitude
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}

$stmt->close();