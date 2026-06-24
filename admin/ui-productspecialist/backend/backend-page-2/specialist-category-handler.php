<?php
// specialist-category-handler.php

header('Content-Type: application/json');

$action = $_POST['category_action'] ?? '';

// ── Add ────────────────────────────────────────────────────────────────────
if ($action === 'add') {
    $name = trim($_POST['cat_name'] ?? '');

    if (empty($name)) {
        echo json_encode(['ok' => false, 'msg' => 'Category name is required.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT IGNORE INTO noblecategory (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        echo json_encode(['ok' => false, 'msg' => 'Category already exists.']);
    } else {
        $newId = $conn->insert_id;
        echo json_encode(['ok' => true, 'id' => $newId, 'name' => $name]);
    }

    $stmt->close();
    exit;
}

// ── Update ─────────────────────────────────────────────────────────────────
if ($action === 'update') {
    $id   = intval($_POST['cat_id']   ?? 0);
    $name = trim($_POST['cat_name']   ?? '');

    if (!$id || empty($name)) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid data.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE noblecategory SET name = ? WHERE id = ?");
    $stmt->bind_param("si", $name, $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true, 'id' => $id, 'name' => $name]);
    exit;
}

// ── Delete ─────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $id = intval($_POST['cat_id'] ?? 0);

    if (!$id) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid ID.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM noblecategory WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true]);
    exit;
}

// ── Unknown action ─────────────────────────────────────────────────────────
echo json_encode(['ok' => false, 'msg' => 'Unknown action.']);
exit;