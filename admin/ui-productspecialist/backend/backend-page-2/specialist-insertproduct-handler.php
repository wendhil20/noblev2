<?php
// specialist-insertproduct-handler.php

$uploadDir = ROOT_PATH . '/uploads/';

$conn->begin_transaction();

try {
    // ── Product ────────────────────────────────────────────────────────────
    $name = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['product_description'] ?? '');
    $category = trim($_POST['product_category'] ?? '');
    $unit = trim($_POST['product_unit'] ?? '');
    $isSimpleProduct = !empty($_POST['is_simple_product']);

    if (empty($name) || empty($category)) {
        throw new Exception('Product name and category are required.');
    }

    $productImage = '';
    if (!empty($_FILES['product_image']['tmp_name'])) {
        $productImage = convertToWebp($_FILES['product_image'], $uploadDir);
        if (!$productImage) {
            throw new Exception('Failed to convert product image to WebP.');
        }
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

    // ── Gallery (JSON array of filenames) ──────────────────────────────────
    $galleryFilenames = [];
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
            $galleryFilenames[] = $galleryFile;
        }
    }
    $galleryJson = !empty($galleryFilenames) ? json_encode($galleryFilenames) : null;

    // ── Insert product ─────────────────────────────────────────────────────
    $createdBy = $_SESSION['account_id'] ?? null;

    $stmt = $conn->prepare(
        "INSERT INTO nobleproduct (name, imageproduct, description, category, unit, specifications, gallery, created_by, updated_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sssssssii", $name, $productImage, $description, $category, $unit, $specificationsJson, $galleryJson, $createdBy, $createdBy);
    $stmt->execute();
    $productId = $conn->insert_id;
    $stmt->close();

    if ($isSimpleProduct) {
        // ── Simple product: single hidden "Default" color + "Default" variant ──
        // Frontend (mainproductview.php) detects this pattern and hides the
        // color/size pickers, auto-selecting this variant instead.
        $defaultColorName = 'Default';
        $defaultImage = '';
        $defaultColorPrice = 0.0;

        $stmt = $conn->prepare(
            "INSERT INTO nobleproductcolor (product_id, colorname, imagecolor, pricecolor)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("issd", $productId, $defaultColorName, $defaultImage, $defaultColorPrice);
        $stmt->execute();
        $colorId = $conn->insert_id;
        $stmt->close();

        $priceSize = floatval($_POST['simple_price'] ?? 0);
        $discount = floatval($_POST['simple_discount'] ?? 0);
        $stock = intval($_POST['simple_stock'] ?? 0);
        $defaultSizeName = 'Default';
        $width = floatval($_POST['simple_width'] ?? 0);
        $height = floatval($_POST['simple_height'] ?? 0);
        $length = floatval($_POST['simple_length'] ?? 0);
        $dimUnit = $_POST['simple_dimension_unit'] ?? 'cm';
        $weight = floatval($_POST['simple_weight'] ?? 0);
        $weightUnit = $_POST['simple_weight_unit'] ?? 'kg';

        $stmt = $conn->prepare(
            "INSERT INTO nobleproductvariant
                (color_id, sizename, pricesize, discountvariant, width, height, leght, dimension_unit, weight, weight_unit, stock)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "isddddddssi",
            $colorId,
            $defaultSizeName,
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

    } else {
        // ── Colors ─────────────────────────────────────────────────────────
        $colorNames = $_POST['color_name'] ?? [];
        $colorPrices = $_POST['color_price'] ?? [];

        foreach ($colorNames as $ci => $colorName) {
            $colorName = trim($colorName);
            $priceColor = floatval($colorPrices[$ci] ?? 0);

            if (empty($colorName))
                continue;

            $colorImage = '';
            if (!empty($_FILES['color_image']['tmp_name'][$ci])) {
                $fakeFile = [
                    'tmp_name' => $_FILES['color_image']['tmp_name'][$ci],
                    'type' => $_FILES['color_image']['type'][$ci],
                ];
                $colorImage = convertToWebp($fakeFile, $uploadDir);
                if (!$colorImage) {
                    throw new Exception("Failed to convert color image ($colorName) to WebP.");
                }
            }

            $stmt = $conn->prepare(
                "INSERT INTO nobleproductcolor (product_id, colorname, imagecolor, pricecolor)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("issd", $productId, $colorName, $colorImage, $priceColor);
            $stmt->execute();
            $colorId = $conn->insert_id;
            $stmt->close();

            // ── Variants (sizes) per color ─────────────────────────────────────
            $sizeNames = $_POST['size_name'][$ci] ?? [];
            $sizePrices = $_POST['size_price'][$ci] ?? [];
            $discounts = $_POST['size_discount'][$ci] ?? [];
            $widths = $_POST['size_width'][$ci] ?? [];
            $heights = $_POST['size_height'][$ci] ?? [];
            $lengths = $_POST['size_length'][$ci] ?? [];
            $dimUnits = $_POST['size_dimension_unit'][$ci] ?? [];
            $weights = $_POST['size_weight'][$ci] ?? [];
            $weightUnits = $_POST['size_weight_unit'][$ci] ?? [];
            $stocks = $_POST['size_stock'][$ci] ?? [];

            foreach ($sizeNames as $si => $sizeName) {
                $sizeName = trim($sizeName);
                if (empty($sizeName))
                    continue;

                $priceSize = floatval($sizePrices[$si] ?? 0);
                $discount = floatval($discounts[$si] ?? 0);
                $width = floatval($widths[$si] ?? 0);
                $height = floatval($heights[$si] ?? 0);
                $length = floatval($lengths[$si] ?? 0);
                $dimUnit = $dimUnits[$si] ?? 'cm';
                $weight = floatval($weights[$si] ?? 0);
                $weightUnit = $weightUnits[$si] ?? 'kg';
                $stock = intval($stocks[$si] ?? 0);

                $stmt = $conn->prepare(
                    "INSERT INTO nobleproductvariant
                        (color_id, sizename, pricesize, discountvariant, width, height, leght, dimension_unit, weight, weight_unit, stock)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("isddddddssi", $colorId, $sizeName, $priceSize, $discount, $width, $height, $length, $dimUnit, $weight, $weightUnit, $stock);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    $conn->commit();
    $success = 'Product <strong>' . htmlspecialchars($name) . '</strong> has been saved successfully.';

} catch (Exception $e) {
    $conn->rollback();
    $error = $e->getMessage();
}