<?php
// specialist-updateproduct-helpers.php

function loadProductById(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare(
        "SELECT p.id, p.name, p.imageproduct, p.description, p.category, p.unit,
                p.specifications, p.gallery, p.created_by, p.updated_by,
                creator.name AS created_by_name,
                editor.name  AS updated_by_name
         FROM nobleproduct p
         LEFT JOIN noblerole creator ON creator.id = p.created_by
         LEFT JOIN noblerole editor  ON editor.id  = p.updated_by
         WHERE p.id = ? LIMIT 1"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function loadProductColors(mysqli $conn, int $productId): array
{
    $colors = [];

    $stmt = $conn->prepare(
        "SELECT id, colorname, imagecolor, pricecolor
         FROM nobleproductcolor
         WHERE product_id = ?
         ORDER BY id ASC"
    );
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['variants'] = [];
        $colors[$row['id']] = $row;
    }
    $stmt->close();

    if (empty($colors)) return [];

    // Load all variants for all colors in one query
    $colorIds    = implode(',', array_keys($colors));
    $variantRows = $conn->query(
        "SELECT id, color_id, sizename, pricesize, discountvariant,
                width, height, leght, dimension_unit, weight, weight_unit, stock
         FROM nobleproductvariant
         WHERE color_id IN ($colorIds)
         ORDER BY color_id ASC, id ASC"
    );
    while ($v = $variantRows->fetch_assoc()) {
        $colors[$v['color_id']]['variants'][] = $v;
    }

    return array_values($colors);
}

/**
 * Reuse the same WebP converter from the insert helpers.
 * (Include insert helpers before this file, or duplicate the function here.)
 */
if (!function_exists('convertToWebp')) {
    function convertToWebp(array $file, string $destDir): string|false
    {
        $tmp  = $file['tmp_name'];
        $mime = mime_content_type($tmp);
        $src  = match ($mime) {
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
}

/**
 * Loads all categories ordered by name.
 */
if (!function_exists('loadCategories')) {
    function loadCategories(mysqli $conn): array
    {
        $categories = [];
        $result     = $conn->query("SELECT id, name FROM noblecategory ORDER BY name ASC");
        while ($row = $result->fetch_assoc()) $categories[] = $row;
        return $categories;
    }
}