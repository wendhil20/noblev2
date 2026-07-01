<?php
// clearrecentview.php
include ROOT_PATH . '/network/connect.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Not logged in.']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM noblerecentview WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true]);