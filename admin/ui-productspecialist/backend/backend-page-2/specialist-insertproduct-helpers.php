<?php
// specialist-insertproduct-helpers.php

function convertToWebp(array $file, string $destDir): string|false
{
    $tmp  = $file['tmp_name'];
    $mime = mime_content_type($tmp);

    $src = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($tmp),
        'image/png'  => imagecreatefrompng($tmp),
        'image/gif'  => imagecreatefromgif($tmp),
        'image/webp' => imagecreatefromwebp($tmp),
        default      => false,
    };

    if (!$src) return false;

    $filename = uniqid('product_', true) . '.webp';
    $destPath = rtrim($destDir, '/') . '/' . $filename;

    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

    imagewebp($src, $destPath, 85);
    imagedestroy($src);

    return $filename;
}

function loadCategories(mysqli $conn): array
{
    $categories = [];
    $result     = $conn->query("SELECT id, name FROM noblecategory ORDER BY name ASC");
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    return $categories;
}