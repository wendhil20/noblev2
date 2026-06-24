<?php
// admin/ui-productspecialist/backend/backend-page-7/po-supplier-insert.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$supname          = trim($_POST['supname'] ?? '');
$suptype          = trim($_POST['suptype'] ?? '');
$supaddress       = trim($_POST['supaddress'] ?? '');
$countryregion    = trim($_POST['countryregion'] ?? '');
$suppersonname    = trim($_POST['suppersonname'] ?? '');
$suppersonnumber  = trim($_POST['suppersonnumber'] ?? '');
$suppersonjobtitle = trim($_POST['suppersonjobtitle'] ?? '');
$suppersonemail   = trim($_POST['suppersonemail'] ?? '');

if (empty($supname) || empty($suptype)) {
    echo json_encode(['success' => false, 'error' => 'Supplier name and type are required.']);
    exit;
}

// Handle logo upload
$logoFilename = '';
if (!empty($_FILES['suplogoimagecompany']['name'])) {
    $file     = $_FILES['suplogoimagecompany'];
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'error' => 'Invalid image format.']);
        exit;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Image must be under 5MB.']);
        exit;
    }

    $logoFilename = uniqid('sup_', true) . '.' . $ext;
    $uploadPath   = ROOT_PATH . '/uploads/' . $logoFilename;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to upload image.']);
        exit;
    }
}

$stmt = $conn->prepare("
    INSERT INTO noblecompanysupplier 
    (supname, suptype, supaddress, countryregion, suppersonname, suppersonnumber, suppersonjobtitle, suppersonemail, suplogoimagecompany)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "sssssssss",
    $supname, $suptype, $supaddress, $countryregion,
    $suppersonname, $suppersonnumber, $suppersonjobtitle, $suppersonemail,
    $logoFilename
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    // Delete uploaded file if DB insert fails
    if ($logoFilename && file_exists(ROOT_PATH . '/uploads/' . $logoFilename)) {
        unlink(ROOT_PATH . '/uploads/' . $logoFilename);
    }
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
}

$stmt->close();