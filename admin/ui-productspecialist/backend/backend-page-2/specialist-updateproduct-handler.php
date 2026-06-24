<?php
// specialist-updateproduct-handler.php

$uploadDir = ROOT_PATH . '/uploads/';

$conn->begin_transaction();


try {
    // ── 1. Product info ────────────────────────────────────────────────────
    $name = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['product_description'] ?? '');
    $category = trim($_POST['product_category'] ?? '');
    $unit = trim($_POST['product_unit'] ?? '');

    if (empty($name) || empty($category)) {
        throw new Exception('Product name and category are required.');
    }

    $newProductImage = null;
    if (!empty($_FILES['product_image']['tmp_name'])) {
        $converted = convertToWebp($_FILES['product_image'], $uploadDir);
        if (!$converted)
            throw new Exception('Failed to convert product image to WebP.');
        $newProductImage = $converted;
    }

    // ── Specifications (JSON) ──────────────────────────────────────────────
    $specKeys = $_POST['spec_key'] ?? [];
    $specValues = $_POST['spec_value'] ?? [];
    $specsArr = [];
    foreach ($specKeys as $i => $key) {
        $key = trim($key);
        $val = trim($specValues[$i] ?? '');
        if ($key !== '')
            $specsArr[$key] = $val;
    }
    $specificationsJson = !empty($specsArr) ? json_encode($specsArr, JSON_UNESCAPED_UNICODE) : null;

    // ── Gallery (merge existing - deleted + new uploads) ───────────────────
    $existingGallery = [];
    $rawGallery = $conn->query("SELECT gallery FROM nobleproduct WHERE id = $productId")->fetch_assoc();
    if (!empty($rawGallery['gallery'])) {
        $existingGallery = json_decode($rawGallery['gallery'], true) ?? [];
    }

    // Remove files the user marked for deletion
    $toDelete = $_POST['gallery_delete'] ?? [];
    $existingGallery = array_values(array_filter($existingGallery, fn($f) => !in_array($f, $toDelete)));

    // Upload new gallery images
    if (!empty($_FILES['gallery_images']['tmp_name'])) {
        foreach ($_FILES['gallery_images']['tmp_name'] as $gi => $tmpName) {
            if (empty($tmpName))
                continue;
            $fakeFile = [
                'tmp_name' => $tmpName,
                'type' => $_FILES['gallery_images']['type'][$gi],
            ];
            $galleryFile = convertToWebp($fakeFile, $uploadDir);
            if (!$galleryFile)
                throw new Exception('Failed to convert a gallery image to WebP.');
            $existingGallery[] = $galleryFile;
        }
    }
    $galleryJson = !empty($existingGallery) ? json_encode(array_values($existingGallery)) : null;

    // ── Update product ─────────────────────────────────────────────────────
    if ($newProductImage !== null) {
        $stmt = $conn->prepare(
            "UPDATE nobleproduct SET name=?, imageproduct=?, description=?, category=?, unit=?, specifications=?, gallery=? WHERE id=?"
        );
        $stmt->bind_param("sssssssi", $name, $newProductImage, $description, $category, $unit, $specificationsJson, $galleryJson, $productId);
    } else {
        $stmt = $conn->prepare(
            "UPDATE nobleproduct SET name=?, description=?, category=?, unit=?, specifications=?, gallery=? WHERE id=?"
        );
        $stmt->bind_param("ssssssi", $name, $description, $category, $unit, $specificationsJson, $galleryJson, $productId);
    }
    $stmt->execute();
    $stmt->close();

    // ── 2. Colors ──────────────────────────────────────────────────────────
    $postedColorIds = $_POST['color_id'] ?? [];
    $colorNames = $_POST['color_name'] ?? [];
    $colorPrices = $_POST['color_price'] ?? [];

    // Determine which existing color IDs are being kept
    $keepColorIds = [];
    foreach ($postedColorIds as $rawId) {
        if ($rawId !== '')
            $keepColorIds[] = intval($rawId);
    }

    // Delete colors (and variants) no longer in the form
    $existingColors = $conn->query(
        "SELECT id FROM nobleproductcolor WHERE product_id = $productId"
    );
    while ($ec = $existingColors->fetch_assoc()) {
        if (!in_array((int) $ec['id'], $keepColorIds)) {
            $conn->query("DELETE FROM nobleproductvariant WHERE color_id = {$ec['id']}");
            $conn->query("DELETE FROM nobleproductcolor WHERE id = {$ec['id']}");
        }
    }

    foreach ($postedColorIds as $ci => $rawId) {
        $colorName = trim($colorNames[$ci] ?? '');
        $priceColor = floatval($colorPrices[$ci] ?? 0);
        if (empty($colorName))
            continue;

        // Color image upload
        $newColorImage = null;
        if (!empty($_FILES['color_image']['tmp_name'][$ci])) {
            $fakeFile = [
                'tmp_name' => $_FILES['color_image']['tmp_name'][$ci],
                'type' => $_FILES['color_image']['type'][$ci],
            ];
            $converted = convertToWebp($fakeFile, $uploadDir);
            if (!$converted)
                throw new Exception("Failed to convert color image ($colorName) to WebP.");
            $newColorImage = $converted;
        }

        if ($rawId !== '') {
            // Update existing color
            $colorId = intval($rawId);
            if ($newColorImage !== null) {
                $stmt = $conn->prepare(
                    "UPDATE nobleproductcolor SET colorname=?, imagecolor=?, pricecolor=? WHERE id=?"
                );
                $stmt->bind_param("ssdi", $colorName, $newColorImage, $priceColor, $colorId);
            } else {
                $stmt = $conn->prepare(
                    "UPDATE nobleproductcolor SET colorname=?, pricecolor=? WHERE id=?"
                );
                $stmt->bind_param("sdi", $colorName, $priceColor, $colorId);
            }
            $stmt->execute();
            $stmt->close();
        } else {
            // Insert new color
            $imgVal = $newColorImage ?? '';
            $stmt = $conn->prepare(
                "INSERT INTO nobleproductcolor (product_id, colorname, imagecolor, pricecolor)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("issd", $productId, $colorName, $imgVal, $priceColor);
            $stmt->execute();
            $colorId = $conn->insert_id;
            $stmt->close();
        }

        // ── 3. Variants for this color ─────────────────────────────────────
        $postedVarIds = $_POST['variant_id'][$ci] ?? [];
        $sizeNames = $_POST['size_name'][$ci] ?? [];
        $sizePrices = $_POST['size_price'][$ci] ?? [];
        $discounts = $_POST['size_discount'][$ci] ?? [];
        $stocks = $_POST['size_stock'][$ci] ?? [];
        $widths = $_POST['size_width'][$ci] ?? [];
        $heights = $_POST['size_height'][$ci] ?? [];
        $lengths = $_POST['size_length'][$ci] ?? [];
        $dimUnits = $_POST['size_dimension_unit'][$ci] ?? [];
        $weights = $_POST['size_weight'][$ci] ?? [];
        $weightUnits = $_POST['size_weight_unit'][$ci] ?? [];

        // Delete variants removed by user (only relevant for existing colors)
        if ($rawId !== '') {
            $keepVarIds = [];
            foreach ($postedVarIds as $vRawId) {
                if ($vRawId !== '')
                    $keepVarIds[] = intval($vRawId);
            }
            $existingVars = $conn->query(
                "SELECT id FROM nobleproductvariant WHERE color_id = $colorId"
            );
            while ($ev = $existingVars->fetch_assoc()) {
                if (!in_array((int) $ev['id'], $keepVarIds)) {
                    $conn->query("DELETE FROM nobleproductvariant WHERE id = {$ev['id']}");
                }
            }
        }

        foreach ($sizeNames as $vi => $sizeName) {
            $sizeName = trim($sizeName);
            if (empty($sizeName))
                continue;

            $vRawId = $postedVarIds[$vi] ?? '';
            $priceSize = floatval($sizePrices[$vi] ?? 0);
            $discount = floatval($discounts[$vi] ?? 0);
            $stock = intval($stocks[$vi] ?? 0);
            $width = floatval($widths[$vi] ?? 0);
            $height = floatval($heights[$vi] ?? 0);
            $length = floatval($lengths[$vi] ?? 0);
            $dimUnit = $dimUnits[$vi] ?? 'cm';

            if ($vRawId !== '') {
                $variantId = intval($vRawId);
                $weight = floatval($weights[$vi] ?? 0);
                $weightUnit = $weightUnits[$vi] ?? 'kg';
                $stmt = $conn->prepare(
                    "UPDATE nobleproductvariant
                     SET sizename=?, pricesize=?, discountvariant=?,
                         width=?, height=?, leght=?, dimension_unit=?,
                         weight=?, weight_unit=?, stock=?
                     WHERE id=?"
                );
                $stmt->bind_param(
                    "sdddddsdsii",
                    $sizeName,
                    $priceSize,
                    $discount,
                    $width,
                    $height,
                    $length,
                    $dimUnit,
                    $weight,
                    $weightUnit,
                    $stock,
                    $variantId
                );
                $stmt->execute();
                $stmt->close();
            } else {
                $weight = floatval($weights[$vi] ?? 0);
                $weightUnit = $weightUnits[$vi] ?? 'kg';
                $stmt = $conn->prepare(
                    "INSERT INTO nobleproductvariant
                        (color_id, sizename, pricesize, discountvariant,
                         width, height, leght, dimension_unit, weight, weight_unit, stock)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    "isdddddsdsi",
                    $colorId,
                    $sizeName,
                    $priceSize,
                    $discount,
                    $width,
                    $height,
                    $length,
                    $dimUnit,
                    $weight,
                    $weightUnit,
                    $stock
                );
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    $conn->commit();
    $success = 'Product <strong>' . htmlspecialchars($name) . '</strong> updated successfully.';

} catch (Exception $e) {
    $conn->rollback();
    $error = $e->getMessage() . ' | Line: ' . $e->getLine();
}