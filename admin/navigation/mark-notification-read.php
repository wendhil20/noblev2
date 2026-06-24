<?php
// mark-notification-read.php

include ROOT_PATH . '/network/connect.php';

header('Content-Type: application/json');

$id  = intval($_POST['id'] ?? 0);
$all = $_POST['all'] ?? false;
$role = $_SESSION['role'] ?? '';

if ($all) {
    $s = $conn->prepare("UPDATE noblenotification SET is_read=1 WHERE for_role=?");
    $s->bind_param("s", $role);
} else {
    $s = $conn->prepare("UPDATE noblenotification SET is_read=1 WHERE id=?");
    $s->bind_param("i", $id);
}
$s->execute();
echo json_encode(['success' => true]);