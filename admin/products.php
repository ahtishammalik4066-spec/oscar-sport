<?php

require_once "../config/database.php";
require_once "../includes/auth.php";

$message = "";
$messageType = "";


/*
|--------------------------------------------------------------------------
| UPLOAD DIRECTORY
|--------------------------------------------------------------------------
*/

$uploadDirectory = "../uploads/products/";
$uploadDatabasePath = "uploads/products/";


if (!is_dir($uploadDirectory)) {
    mkdir($uploadDirectory, 0777, true);
}


/*
|--------------------------------------------------------------------------
| HELPER - DELETE IMAGE FILE
|--------------------------------------------------------------------------
*/

function deleteProductImageFile($imagePath)
{
    if (empty($imagePath)) {
        return;
    }

    if (
        strpos($imagePath, "uploads/products/") !== 0
    ) {
        return;
    }

    $filePath = "../" . $imagePath;

    if (is_file($filePath)) {
        @unlink($filePath);
    }
}


/*
|--------------------------------------------------------------------------
| DELETE PRODUCT IMAGE
|--------------------------------------------------------------------------
*/

if (isset($_GET["delete_image"])) {

    $imageId = (int) $_GET["delete_image"];

    $productId = isset($_GET["product_id"])
        ? (int) $_GET["product_id"]
        : 0;

    if ($imageId > 0) {

        try {

            $stmt = $pdo->prepare("
                SELECT *
                FROM product_images
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$imageId]);

            $image = $stmt->fetch();

            if ($image) {

                $deleteStmt = $pdo->prepare("
                    DELETE FROM product_images
                    WHERE id = ?
                    AND product_id = ?
                ");

                $deleteStmt->execute([
                    $imageId,
                    (int) $image["product_id"]
                ]);


                deleteProductImageFile(
                    $image["image"]
                );


                if ((int) $image["is_primary"] === 1) {

                    $nextStmt = $pdo->prepare("
                        SELECT id
                        FROM product_images
                        WHERE product_id = ?
                        ORDER BY sort_order ASC, id ASC
                        LIMIT 1
                    ");

                    $nextStmt->execute([
                        (int) $image["product_id"]
                    ]);

                    $nextImage = $nextStmt->fetch();

                    if ($nextImage) {

                        $primaryStmt = $pdo->prepare("
                            UPDATE product_images
                            SET is_primary = 1
                            WHERE id = ?
                            AND product_id = ?
                        ");

                        $primaryStmt->execute([
                            (int) $nextImage["id"],
                            (int) $image["product_id"]
                        ]);
                    }
                }

                $message = "Product image deleted successfully.";
                $messageType = "success";

            } else {

                $message = "Image not found.";
                $messageType = "danger";
            }

        } catch (PDOException $e) {

            $message = "Unable to delete image.";
            $messageType = "danger";
        }
    }


    if ($productId > 0) {

        header(
            "Location: products.php?edit=" .
            $productId .
            "&message=" .
            urlencode($message) .
            "&type=" .
            urlencode($messageType)
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| SET PRIMARY PRODUCT IMAGE
|--------------------------------------------------------------------------
*/

if (isset($_GET["primary_image"])) {

    $imageId = (int) $_GET["primary_image"];

    $productId = isset($_GET["product_id"])
        ? (int) $_GET["product_id"]
        : 0;

    if ($imageId > 0) {

        try {

            $stmt = $pdo->prepare("
                SELECT id, product_id
                FROM product_images
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$imageId]);

            $image = $stmt->fetch();

            if ($image) {

                $actualProductId = (int) $image["product_id"];


                $resetStmt = $pdo->prepare("
                    UPDATE product_images
                    SET is_primary = 0
                    WHERE product_id = ?
                ");

                $resetStmt->execute([
                    $actualProductId
                ]);


                $primaryStmt = $pdo->prepare("
                    UPDATE product_images
                    SET is_primary = 1
                    WHERE id = ?
                    AND product_id = ?
                ");

                $primaryStmt->execute([
                    $imageId,
                    $actualProductId
                ]);


                $message = "Primary image updated successfully.";
                $messageType = "success";

                $productId = $actualProductId;

            } else {

                $message = "Image not found.";
                $messageType = "danger";
            }

        } catch (PDOException $e) {

            $message = "Unable to set primary image.";
            $messageType = "danger";
        }
    }


    if ($productId > 0) {

        header(
            "Location: products.php?edit=" .
            $productId .
            "&message=" .
            urlencode($message) .
            "&type=" .
            urlencode($messageType)
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| DELETE PRODUCT VARIANT
|--------------------------------------------------------------------------
*/

if (isset($_GET["delete_variant"])) {

    $variantId = (int) $_GET["delete_variant"];

    $productId = isset($_GET["product_id"])
        ? (int) $_GET["product_id"]
        : 0;


    if ($variantId > 0 && $productId > 0) {

        try {

            $stmt = $pdo->prepare("
                DELETE FROM product_variants
                WHERE id = ?
                AND product_id = ?
            ");

            $stmt->execute([
                $variantId,
                $productId
            ]);


            if ($stmt->rowCount() > 0) {

                $message = "Product variant deleted successfully.";
                $messageType = "success";

            } else {

                $message = "Variant not found.";
                $messageType = "danger";
            }

        } catch (PDOException $e) {

            $message = "Unable to delete product variant.";
            $messageType = "danger";
        }


        header(
            "Location: products.php?edit=" .
            $productId .
            "&message=" .
            urlencode($message) .
            "&type=" .
            urlencode($messageType)
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| ADD / UPDATE PRODUCT VARIANT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["variant_action"])
) {

    $variantAction =
        $_POST["variant_action"] ?? "add";

    $variantId =
        isset($_POST["variant_id"])
        ? (int) $_POST["variant_id"]
        : 0;

    $productId =
        isset($_POST["product_id"])
        ? (int) $_POST["product_id"]
        : 0;

    $variantName =
        trim($_POST["variant_name"] ?? "");

    $variantSku =
        trim($_POST["variant_sku"] ?? "");

    $variantPrice =
        (float) ($_POST["variant_price"] ?? 0);

    $variantSalePrice =
        ($_POST["variant_sale_price"] ?? "") !== ""
        ? (float) $_POST["variant_sale_price"]
        : null;

    $variantStock =
        (int) ($_POST["variant_stock"] ?? 0);

    $variantSize =
        trim($_POST["variant_size"] ?? "");

    $variantColor =
        trim($_POST["variant_color"] ?? "");

    $variantHand =
        trim($_POST["variant_hand"] ?? "");

    $variantType =
        trim($_POST["variant_type"] ?? "");

    $variantStatus =
        isset($_POST["variant_status"])
        ? "active"
        : "inactive";


    /*
    |--------------------------------------------------------------------------
    | VARIANT VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($productId <= 0) {

        $message = "Invalid product.";
        $messageType = "danger";

    } elseif ($variantName === "") {

        $message = "Variant name is required.";
        $messageType = "danger";

    } elseif ($variantSku === "") {

        $message = "Variant SKU is required.";
        $messageType = "danger";

    } elseif ($variantPrice < 0) {

        $message = "Variant price cannot be negative.";
        $messageType = "danger";

    } elseif (
        $variantSalePrice !== null &&
        $variantSalePrice < 0
    ) {

        $message = "Variant sale price cannot be negative.";
        $messageType = "danger";

    } elseif ($variantStock < 0) {

        $message = "Variant stock cannot be negative.";
        $messageType = "danger";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | CHECK PRODUCT EXISTS
            |--------------------------------------------------------------------------
            */

            $productCheckStmt = $pdo->prepare("
                SELECT id
                FROM products
                WHERE id = ?
                LIMIT 1
            ");

            $productCheckStmt->execute([
                $productId
            ]);

            $productExists =
                $productCheckStmt->fetch();


            if (!$productExists) {

                throw new Exception(
                    "Product not found."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE VARIANT
            |--------------------------------------------------------------------------
            */

            if (
                $variantAction === "update" &&
                $variantId > 0
            ) {

                $stmt = $pdo->prepare("
                    UPDATE product_variants
                    SET
                        variant_name = ?,
                        sku = ?,
                        price = ?,
                        sale_price = ?,
                        stock = ?,
                        size = ?,
                        color = ?,
                        hand = ?,
                        type = ?,
                        status = ?
                    WHERE id = ?
                    AND product_id = ?
                ");

                $stmt->execute([
                    $variantName,
                    $variantSku,
                    $variantPrice,
                    $variantSalePrice,
                    $variantStock,
                    $variantSize !== ""
                        ? $variantSize
                        : null,
                    $variantColor !== ""
                        ? $variantColor
                        : null,
                    $variantHand !== ""
                        ? $variantHand
                        : null,
                    $variantType !== ""
                        ? $variantType
                        : null,
                    $variantStatus,
                    $variantId,
                    $productId
                ]);


                $message =
                    "Product variant updated successfully.";

                $messageType = "success";

            }


            /*
            |--------------------------------------------------------------------------
            | ADD VARIANT
            |--------------------------------------------------------------------------
            */

            else {

                $stmt = $pdo->prepare("
                    INSERT INTO product_variants
                    (
                        product_id,
                        variant_name,
                        sku,
                        price,
                        sale_price,
                        stock,
                        size,
                        color,
                        hand,
                        type,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $productId,
                    $variantName,
                    $variantSku,
                    $variantPrice,
                    $variantSalePrice,
                    $variantStock,
                    $variantSize !== ""
                        ? $variantSize
                        : null,
                    $variantColor !== ""
                        ? $variantColor
                        : null,
                    $variantHand !== ""
                        ? $variantHand
                        : null,
                    $variantType !== ""
                        ? $variantType
                        : null,
                    $variantStatus
                ]);


                $message =
                    "Product variant added successfully.";

                $messageType = "success";
            }

        } catch (PDOException $e) {

            if ($e->getCode() == 23000) {

                $message =
                    "Variant SKU already exists.";

                $messageType = "danger";

            } else {

                $message =
                    "Unable to save product variant.";

                $messageType = "danger";
            }

        } catch (Exception $e) {

            $message =
                $e->getMessage();

            $messageType = "danger";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RETURN TO PRODUCT EDIT
    |--------------------------------------------------------------------------
    */

    if ($productId > 0) {

        header(
            "Location: products.php?edit=" .
            $productId .
            "&message=" .
            urlencode($message) .
            "&type=" .
            urlencode($messageType)
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| DELETE PRODUCT
|--------------------------------------------------------------------------
*/

if (isset($_GET["delete"])) {

    $id = (int) $_GET["delete"];

    if ($id > 0) {

        try {

            /*
            | Get all product images before deleting product.
            */
            $imageStmt = $pdo->prepare("
                SELECT image
                FROM product_images
                WHERE product_id = ?
            ");

            $imageStmt->execute([$id]);

            $productImages =
                $imageStmt->fetchAll();


            /*
            | Product variants are deleted automatically
            | through ON DELETE CASCADE.
            */
            $stmt = $pdo->prepare("
                DELETE FROM products
                WHERE id = ?
            ");

            $stmt->execute([$id]);


            /*
            | Delete physical image files.
            */
            foreach ($productImages as $productImage) {

                deleteProductImageFile(
                    $productImage["image"]
                );
            }


            $message =
                "Product deleted successfully.";

            $messageType = "success";

        } catch (PDOException $e) {

            $message =
                "Unable to delete product.";

            $messageType = "danger";
        }
    }
}


/*
|--------------------------------------------------------------------------
| ADD / UPDATE PRODUCT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    !isset($_POST["variant_action"])
) {

    $id = isset($_POST["id"])
        ? (int) $_POST["id"]
        : 0;

    $name =
        trim($_POST["name"] ?? "");

    $slug =
        trim($_POST["slug"] ?? "");

    $sku =
        trim($_POST["sku"] ?? "");


    $categoryId =
        !empty($_POST["category_id"])
        ? (int) $_POST["category_id"]
        : null;

    $subCategoryId =
        !empty($_POST["sub_category_id"])
        ? (int) $_POST["sub_category_id"]
        : null;

    $collectionId =
        !empty($_POST["collection_id"])
        ? (int) $_POST["collection_id"]
        : null;


    $description =
        trim($_POST["description"] ?? "");


    $price =
        (float) (
            $_POST["price"] ?? 0
        );


    $salePrice =
        (
            $_POST["sale_price"] ?? ""
        ) !== ""
        ? (float) $_POST["sale_price"]
        : null;


    $stockQuantity =
        (int) (
            $_POST["stock_quantity"] ?? 0
        );


    $status =
        isset($_POST["status"])
        ? 1
        : 0;


    $featured =
        isset($_POST["featured"])
        ? 1
        : 0;


    /*
    |--------------------------------------------------------------------------
    | OPTIONAL VARIANTS FROM MAIN PRODUCT FORM
    |--------------------------------------------------------------------------
    */

    $variantNames = isset($_POST["variant_name"]) && is_array($_POST["variant_name"])
        ? $_POST["variant_name"]
        : [];

    $variantSkus = isset($_POST["variant_sku"]) && is_array($_POST["variant_sku"])
        ? $_POST["variant_sku"]
        : [];

    $variantPrices = isset($_POST["variant_price"]) && is_array($_POST["variant_price"])
        ? $_POST["variant_price"]
        : [];

    $variantSalePrices = isset($_POST["variant_sale_price"]) && is_array($_POST["variant_sale_price"])
        ? $_POST["variant_sale_price"]
        : [];

    $variantStocks = isset($_POST["variant_stock"]) && is_array($_POST["variant_stock"])
        ? $_POST["variant_stock"]
        : [];

    $variantSizes = isset($_POST["variant_size"]) && is_array($_POST["variant_size"])
        ? $_POST["variant_size"]
        : [];

    $variantColors = isset($_POST["variant_color"]) && is_array($_POST["variant_color"])
        ? $_POST["variant_color"]
        : [];

    $variantHands = isset($_POST["variant_hand"]) && is_array($_POST["variant_hand"])
        ? $_POST["variant_hand"]
        : [];

    $variantTypes = isset($_POST["variant_type"]) && is_array($_POST["variant_type"])
        ? $_POST["variant_type"]
        : [];

    $variantStatuses = isset($_POST["variant_status"]) && is_array($_POST["variant_status"])
        ? $_POST["variant_status"]
        : [];

    $variantCount = count($variantNames);

    /*
    |--------------------------------------------------------------------------
    | OPTIONAL VARIANT VALIDATION
    |--------------------------------------------------------------------------
    */

    $variantValidationError = "";

    for ($i = 0; $i < $variantCount; $i++) {

        $variantNameCheck = trim($variantNames[$i] ?? "");
        $variantSkuCheck = trim($variantSkus[$i] ?? "");

        $variantPriceCheck =
            ($variantPrices[$i] ?? "") !== ""
                ? (float) $variantPrices[$i]
                : 0;

        $variantSalePriceCheck =
            ($variantSalePrices[$i] ?? "") !== ""
                ? (float) $variantSalePrices[$i]
                : null;

        $variantStockCheck =
            ($variantStocks[$i] ?? "") !== ""
                ? (int) $variantStocks[$i]
                : 0;

        $variantSizeCheck = trim($variantSizes[$i] ?? "");
        $variantColorCheck = trim($variantColors[$i] ?? "");
        $variantHandCheck = trim($variantHands[$i] ?? "");
        $variantTypeCheck = trim($variantTypes[$i] ?? "");

        $variantIsEmpty =
            $variantNameCheck === "" &&
            $variantSkuCheck === "" &&
            $variantSizeCheck === "" &&
            $variantColorCheck === "" &&
            $variantHandCheck === "" &&
            $variantTypeCheck === "" &&
            $variantPriceCheck == 0 &&
            $variantStockCheck == 0 &&
            $variantSalePriceCheck === null;

        if ($variantIsEmpty) {
            continue;
        }

        if ($variantNameCheck === "") {
            $variantValidationError =
                "Variant name is required for variant #" . ($i + 1) . ".";
            break;
        }

        if ($variantSkuCheck === "") {
            $variantValidationError =
                "Variant SKU is required for variant #" . ($i + 1) . ".";
            break;
        }

        if ($variantPriceCheck < 0) {
            $variantValidationError =
                "Variant price cannot be negative for variant #" . ($i + 1) . ".";
            break;
        }

        if (
            $variantSalePriceCheck !== null &&
            $variantSalePriceCheck < 0
        ) {
            $variantValidationError =
                "Variant sale price cannot be negative for variant #" . ($i + 1) . ".";
            break;
        }

        if ($variantStockCheck < 0) {
            $variantValidationError =
                "Variant stock cannot be negative for variant #" . ($i + 1) . ".";
            break;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | AUTO SLUG
    |--------------------------------------------------------------------------
    */

    if (
        $slug === "" &&
        $name !== ""
    ) {

        $slug =
            strtolower($name);

        $slug =
            preg_replace(
                "/[^a-z0-9]+/i",
                "-",
                $slug
            );

        $slug =
            trim($slug, "-");
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($name === "") {

        $message =
            "Product name is required.";

        $messageType = "danger";

    } elseif ($price < 0) {

        $message =
            "Price cannot be negative.";

        $messageType = "danger";

    } elseif (
        $salePrice !== null &&
        $salePrice < 0
    ) {

        $message =
            "Sale price cannot be negative.";

        $messageType = "danger";

    } elseif ($stockQuantity < 0) {

        $message =
            "Stock quantity cannot be negative.";

        $messageType = "danger";

    } elseif ($variantValidationError !== "") {

        $message =
            $variantValidationError;

        $messageType = "danger";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | START TRANSACTION
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | UPDATE PRODUCT
            |--------------------------------------------------------------------------
            */

            if ($id > 0) {

                $stmt = $pdo->prepare("
                    UPDATE products
                    SET
                        name = ?,
                        slug = ?,
                        sku = ?,
                        category_id = ?,
                        sub_category_id = ?,
                        collection_id = ?,
                        description = ?,
                        price = ?,
                        sale_price = ?,
                        stock_quantity = ?,
                        status = ?,
                        featured = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $name,
                    $slug,
                    $sku !== ""
                        ? $sku
                        : null,
                    $categoryId,
                    $subCategoryId,
                    $collectionId,
                    $description !== ""
                        ? $description
                        : null,
                    $price,
                    $salePrice,
                    $stockQuantity,
                    $status,
                    $featured,
                    $id
                ]);

                $productId = $id;

                $message =
                    "Product updated successfully.";

                $messageType = "success";
            }


            /*
            |--------------------------------------------------------------------------
            | ADD PRODUCT
            |--------------------------------------------------------------------------
            */

            else {

                $stmt = $pdo->prepare("
                    INSERT INTO products
                    (
                        name,
                        slug,
                        sku,
                        category_id,
                        sub_category_id,
                        collection_id,
                        description,
                        price,
                        sale_price,
                        stock_quantity,
                        status,
                        featured
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $name,
                    $slug,
                    $sku !== ""
                        ? $sku
                        : null,
                    $categoryId,
                    $subCategoryId,
                    $collectionId,
                    $description !== ""
                        ? $description
                        : null,
                    $price,
                    $salePrice,
                    $stockQuantity,
                    $status,
                    $featured
                ]);


                /*
                | Get newly created product ID.
                */
                $productId =
                    (int) $pdo->lastInsertId();


                $message =
                    "Product added successfully.";

                $messageType =
                    "success";
            }


            /*
            |--------------------------------------------------------------------------
            | OPTIONAL PRODUCT VARIANTS
            |--------------------------------------------------------------------------
            */

            for ($i = 0; $i < $variantCount; $i++) {

                $variantName = trim($variantNames[$i] ?? "");
                $variantSku = trim($variantSkus[$i] ?? "");

                $variantPrice =
                    ($variantPrices[$i] ?? "") !== ""
                        ? (float) $variantPrices[$i]
                        : 0;

                $variantSalePrice =
                    ($variantSalePrices[$i] ?? "") !== ""
                        ? (float) $variantSalePrices[$i]
                        : null;

                $variantStock =
                    ($variantStocks[$i] ?? "") !== ""
                        ? (int) $variantStocks[$i]
                        : 0;

                $variantSize = trim($variantSizes[$i] ?? "");
                $variantColor = trim($variantColors[$i] ?? "");
                $variantHand = trim($variantHands[$i] ?? "");
                $variantType = trim($variantTypes[$i] ?? "");

                $variantStatus =
                    ($variantStatuses[$i] ?? "active") === "inactive"
                        ? "inactive"
                        : "active";

                /*
                | Empty rows are allowed because variants are optional.
                */
                if (
                    $variantName === "" &&
                    $variantSku === "" &&
                    $variantSize === "" &&
                    $variantColor === "" &&
                    $variantHand === "" &&
                    $variantType === "" &&
                    $variantPrice == 0 &&
                    $variantStock == 0 &&
                    $variantSalePrice === null
                ) {
                    continue;
                }

                $variantStmt = $pdo->prepare("
                    INSERT INTO product_variants
                    (
                        product_id,
                        variant_name,
                        sku,
                        price,
                        sale_price,
                        stock,
                        size,
                        color,
                        hand,
                        type,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $variantStmt->execute([
                    $productId,
                    $variantName,
                    $variantSku,
                    $variantPrice,
                    $variantSalePrice,
                    $variantStock,
                    $variantSize !== ""
                        ? $variantSize
                        : null,
                    $variantColor !== ""
                        ? $variantColor
                        : null,
                    $variantHand !== ""
                        ? $variantHand
                        : null,
                    $variantType !== ""
                        ? $variantType
                        : null,
                    $variantStatus
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | PRODUCT IMAGE UPLOAD
            |--------------------------------------------------------------------------
            */

            if (
                isset($_FILES["images"]) &&
                isset($_FILES["images"]["name"]) &&
                is_array($_FILES["images"]["name"])
            ) {

                /*
                | Count existing images.
                */
                $countStmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM product_images
                    WHERE product_id = ?
                ");

                $countStmt->execute([
                    $productId
                ]);

                $existingImageCount =
                    (int) $countStmt->fetchColumn();


                /*
                | Check whether product already has primary image.
                */
                $primaryStmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM product_images
                    WHERE product_id = ?
                    AND is_primary = 1
                ");

                $primaryStmt->execute([
                    $productId
                ]);

                $hasPrimaryImage =
                    (
                        (int) $primaryStmt->fetchColumn() > 0
                    );


                $allowedExtensions = [
                    "jpg",
                    "jpeg",
                    "png",
                    "webp"
                ];

                $maxFileSize =
                    5 * 1024 * 1024;

                $uploadedImageNumber = 0;


                foreach (
                    $_FILES["images"]["name"]
                    as $index => $originalName
                ) {

                    /*
                    | Skip empty upload slots.
                    */
                    if (
                        empty($originalName) ||
                        !isset(
                            $_FILES["images"]["tmp_name"][$index]
                        )
                    ) {
                        continue;
                    }


                    $tmpName =
                        $_FILES["images"]["tmp_name"][$index];

                    $error =
                        $_FILES["images"]["error"][$index];

                    $fileSize =
                        (int) $_FILES["images"]["size"][$index];


                    /*
                    | Upload error.
                    */
                    if (
                        $error !== UPLOAD_ERR_OK
                    ) {
                        continue;
                    }


                    /*
                    | File size validation.
                    */
                    if (
                        $fileSize > $maxFileSize
                    ) {
                        continue;
                    }


                    /*
                    | Get extension.
                    */
                    $extension =
                        strtolower(
                            pathinfo(
                                $originalName,
                                PATHINFO_EXTENSION
                            )
                        );


                    /*
                    | Extension validation.
                    */
                    if (
                        !in_array(
                            $extension,
                            $allowedExtensions,
                            true
                        )
                    ) {
                        continue;
                    }


                    /*
                    | Actual image validation.
                    */
                    $imageInfo =
                        @getimagesize(
                            $tmpName
                        );

                    if (
                        $imageInfo === false
                    ) {
                        continue;
                    }


                    /*
                    | Generate unique filename.
                    */
                    $fileName =
                        "product-" .
                        $productId .
                        "-" .
                        uniqid() .
                        "." .
                        $extension;


                    $targetPath =
                        $uploadDirectory .
                        $fileName;


                    $databasePath =
                        $uploadDatabasePath .
                        $fileName;


                    /*
                    | Move image.
                    */
                    if (
                        !move_uploaded_file(
                            $tmpName,
                            $targetPath
                        )
                    ) {
                        continue;
                    }


                    /*
                    | Determine primary image.
                    */
                    $isPrimary =
                        (
                            !$hasPrimaryImage &&
                            $uploadedImageNumber === 0
                        )
                        ? 1
                        : 0;


                    /*
                    | Sort order.
                    */
                    $sortOrder =
                        $existingImageCount +
                        $uploadedImageNumber;


                    try {

                        $imageStmt = $pdo->prepare("
                            INSERT INTO product_images
                            (
                                product_id,
                                image,
                                alt_text,
                                sort_order,
                                is_primary
                            )
                            VALUES (?, ?, ?, ?, ?)
                        ");

                        $imageStmt->execute([
                            $productId,
                            $databasePath,
                            $name,
                            $sortOrder,
                            $isPrimary
                        ]);


                        if (
                            $isPrimary === 1
                        ) {
                            $hasPrimaryImage =
                                true;
                        }


                        $uploadedImageNumber++;

                    } catch (PDOException $imageException) {

                        /*
                        | If database insert fails,
                        | remove uploaded physical file.
                        */
                        if (
                            is_file($targetPath)
                        ) {
                            @unlink(
                                $targetPath
                            );
                        }

                        throw $imageException;
                    }
                }
            }


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            | After successful add/update,
            | return to clean products page.
            */
            if ($id <= 0) {

                header(
                    "Location: products.php?message=" .
                    urlencode($message) .
                    "&type=" .
                    urlencode($messageType)
                );

                exit;
            }

        } catch (PDOException $e) {

            /*
            | Rollback if transaction is active.
            */
            if (
                $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }


            if (
                $e->getCode() == 23000
            ) {

                $message =
                    "Product slug or SKU already exists.";

                $messageType =
                    "danger";

            } else {

                $message =
                    "Something went wrong. Please try again.";

                $messageType =
                    "danger";
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| MESSAGE FROM REDIRECT
|--------------------------------------------------------------------------
*/

if (
    isset($_GET["message"]) &&
    $_GET["message"] !== ""
) {

    $message =
        $_GET["message"];

    $messageType =
        $_GET["type"] ?? "success";
}


/*
|--------------------------------------------------------------------------
| EDIT PRODUCT
|--------------------------------------------------------------------------
*/

$editProduct = null;

if (isset($_GET["edit"])) {

    $editId =
        (int) $_GET["edit"];

    if ($editId > 0) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM products
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $editId
        ]);

        $editProduct =
            $stmt->fetch();
    }
}


/*
|--------------------------------------------------------------------------
| FETCH EDIT PRODUCT IMAGES
|--------------------------------------------------------------------------
*/

$editProductImages = [];

if ($editProduct) {

    $imageStmt = $pdo->prepare("
        SELECT *
        FROM product_images
        WHERE product_id = ?
        ORDER BY
            is_primary DESC,
            sort_order ASC,
            id ASC
    ");

    $imageStmt->execute([
        (int) $editProduct["id"]
    ]);

    $editProductImages =
        $imageStmt->fetchAll();
}


/*
|--------------------------------------------------------------------------
| FETCH EDIT PRODUCT VARIANTS
|--------------------------------------------------------------------------
*/

$editProductVariants = [];

if ($editProduct) {

    $variantStmt = $pdo->prepare("
        SELECT *
        FROM product_variants
        WHERE product_id = ?
        ORDER BY id DESC
    ");

    $variantStmt->execute([
        (int) $editProduct["id"]
    ]);

    $editProductVariants =
        $variantStmt->fetchAll();
}


/*
|--------------------------------------------------------------------------
| FETCH VARIANT FOR EDIT
|--------------------------------------------------------------------------
*/

$editVariant = null;

if (
    $editProduct &&
    isset($_GET["variant_edit"])
) {

    $variantEditId =
        (int) $_GET["variant_edit"];

    if ($variantEditId > 0) {

        $variantEditStmt = $pdo->prepare("
            SELECT *
            FROM product_variants
            WHERE id = ?
            AND product_id = ?
            LIMIT 1
        ");

        $variantEditStmt->execute([
            $variantEditId,
            (int) $editProduct["id"]
        ]);

        $editVariant =
            $variantEditStmt->fetch();
    }
}


/*
|--------------------------------------------------------------------------
| FETCH CATEGORIES
|--------------------------------------------------------------------------
*/

$categoriesStmt = $pdo->query("
    SELECT id, name
    FROM categories
    WHERE status = 1
    ORDER BY sort_order ASC, name ASC
");

$categories =
    $categoriesStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| FETCH SUB CATEGORIES
|--------------------------------------------------------------------------
*/

$subCategoriesStmt = $pdo->query("
    SELECT
        sc.id,
        sc.name,
        sc.category_id,
        c.name AS category_name
    FROM sub_categories sc
    LEFT JOIN categories c
        ON c.id = sc.category_id
    WHERE sc.status = 1
    ORDER BY sc.sort_order ASC, sc.name ASC
");

$subCategories =
    $subCategoriesStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| FETCH COLLECTIONS
|--------------------------------------------------------------------------
*/

$collectionsStmt = $pdo->query("
    SELECT id, name
    FROM collections
    WHERE status = 1
    ORDER BY sort_order ASC, name ASC
");

$collections =
    $collectionsStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| FETCH PRODUCTS
|--------------------------------------------------------------------------
*/

$productsStmt = $pdo->query("
    SELECT
        p.*,
        c.name AS category_name,
        sc.name AS sub_category_name,
        col.name AS collection_name
    FROM products p

    LEFT JOIN categories c
        ON c.id = p.category_id

    LEFT JOIN sub_categories sc
        ON sc.id = p.sub_category_id

    LEFT JOIN collections col
        ON col.id = p.collection_id

    ORDER BY p.id DESC
");

$products =
    $productsStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| FETCH PRODUCT IMAGE COUNTS + PRIMARY IMAGES
|--------------------------------------------------------------------------
*/

$productImageData = [];

$imageDataStmt = $pdo->query("
    SELECT
        product_id,
        COUNT(*) AS image_count,
        MAX(
            CASE
                WHEN is_primary = 1
                THEN image
                ELSE NULL
            END
        ) AS primary_image
    FROM product_images
    GROUP BY product_id
");

$imageDataRows =
    $imageDataStmt->fetchAll();

foreach (
    $imageDataRows as $imageData
) {

    $productImageData[
        (int) $imageData["product_id"]
    ] = $imageData;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Products | Sports CMS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="assets/css/admin.css"
    >

    <style>

        /*
        |--------------------------------------------------------------------------
        | SELECTED IMAGE PREVIEW
        |--------------------------------------------------------------------------
        */

        .selected-images-preview {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-top: 15px;
        }

        .selected-preview-item {
            position: relative;
            aspect-ratio: 1 / 1;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #f3f4f6;
        }

        .selected-preview-item img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        .selected-preview-number {
            position: absolute;
            top: 7px;
            left: 7px;
            padding: 4px 7px;
            border-radius: 999px;
            background: #111827;
            color: #fff;
            font-size: 9px;
            font-weight: 800;
        }


        /*
        |--------------------------------------------------------------------------
        | PRODUCT PAGE
        |--------------------------------------------------------------------------
        */

        .page-content {
            padding: 32px;
            width: 100%;
            min-width: 0;
        }

        .product-form-card,
        .product-table-card,
        .variant-management-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 22px;
        }

        .product-card-header {
            padding: 20px 22px;
            border-bottom: 1px solid #e5e7eb;
        }

        .product-card-header h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
        }

        .product-card-header p {
            margin: 5px 0 0;
            color: #6b7280;
            font-size: 12px;
        }

        .product-card-body {
            padding: 22px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 7px;
        }

        .form-control,
        .form-select {
            min-height: 43px;
            border-radius: 9px;
            border-color: #e5e7eb;
            font-size: 13px;
        }

        textarea.form-control {
            min-height: 110px;
            resize: vertical;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #9ca3af;
            box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.06);
        }

        .form-section-title {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .7px;
            color: #111827;
            margin: 6px 0 18px;
        }

        .save-btn {
            min-height: 44px;
            padding: 0 22px;
            border: 0;
            border-radius: 9px;
            background: #111827;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .save-btn:hover {
            background: #1f2937;
        }

        .cancel-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 22px;
            margin-left: 8px;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            color: #374151;
            background: #fff;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .cancel-btn:hover {
            background: #f9fafb;
        }

        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .checkbox-row input {
            width: 17px;
            height: 17px;
            cursor: pointer;
        }

        .checkbox-row label {
            margin: 0;
            font-size: 12px;
            font-weight: 700;
            color: #374151;
            cursor: pointer;
        }


        /*
        |--------------------------------------------------------------------------
        | PRODUCT IMAGE SECTION
        |--------------------------------------------------------------------------
        */

        .image-upload-box {
            padding: 18px;
            background: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 11px;
        }

        .image-upload-box .form-text {
            color: #9ca3af;
            font-size: 11px;
            margin-top: 7px;
        }

        .existing-images-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .product-image-card {
            position: relative;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 11px;
            background: #fff;
        }

        .product-image-preview {
            width: 100%;
            aspect-ratio: 1 / 1;
            background: #f3f4f6;
            overflow: hidden;
        }

        .product-image-preview img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        .primary-image-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            padding: 5px 8px;
            border-radius: 999px;
            background: #111827;
            color: #fff;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .product-image-info {
            padding: 10px;
        }

        .product-image-alt {
            font-size: 10px;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 9px;
        }

        .product-image-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .image-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 29px;
            padding: 0 8px;
            border-radius: 7px;
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
        }

        .image-primary-btn {
            background: #f3f4f6;
            color: #374151;
        }

        .image-primary-btn:hover {
            background: #e5e7eb;
            color: #111827;
        }

        .image-delete-btn {
            background: #fef2f2;
            color: #dc2626;
        }

        .image-delete-btn:hover {
            background: #fee2e2;
            color: #b91c1c;
        }

        .no-images-box {
            padding: 22px;
            text-align: center;
            border: 1px dashed #d1d5db;
            border-radius: 11px;
            background: #f9fafb;
        }

        .no-images-box strong {
            display: block;
            font-size: 12px;
            color: #374151;
            margin-bottom: 4px;
        }

        .no-images-box span {
            color: #9ca3af;
            font-size: 11px;
        }


        /*
        |--------------------------------------------------------------------------
        | PRODUCT VARIANTS
        |--------------------------------------------------------------------------
        */

        .variant-management-card {
            overflow: hidden;
        }

        .variant-management-header {
            padding: 20px 22px;
            border-bottom: 1px solid #e5e7eb;
        }

        .variant-management-header h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
            color: #111827;
        }

        .variant-management-header p {
            margin: 5px 0 0;
            color: #6b7280;
            font-size: 12px;
        }

        .variant-add-card {
            margin: 20px;
            padding: 20px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 11px;
        }

        .variant-add-title {
            font-size: 15px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 4px;
        }

        .variant-add-subtitle {
            color: #6b7280;
            font-size: 11px;
            margin-bottom: 18px;
        }

        .variant-edit-notice {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
            padding: 11px 13px;
            border-radius: 8px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            font-size: 11px;
            font-weight: 700;
        }

        .variant-edit-cancel {
            color: #9a3412;
            font-size: 11px;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }

        .variant-edit-cancel:hover {
            color: #7c2d12;
            text-decoration: underline;
        }

        .variant-list-card {
            margin: 0 20px 20px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 11px;
            background: #fff;
        }

        .variant-list-header {
            padding: 18px 20px;
            border-bottom: 1px solid #e5e7eb;
            background: #fff;
        }

        .variant-list-header h4 {
            margin: 0;
            font-size: 14px;
            font-weight: 800;
            color: #111827;
        }

        .variant-list-header p {
            margin: 4px 0 0;
            color: #6b7280;
            font-size: 11px;
        }

        .variant-table-wrap {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            background: #fff;
        }

        .variant-table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        .variant-table th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 13px 15px;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        .variant-table td {
            padding: 14px 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            font-size: 12px;
        }

        .variant-table tr:last-child td {
            border-bottom: 0;
        }

        .variant-name {
            font-size: 12px;
            font-weight: 800;
            color: #111827;
        }

        .variant-sku {
            font-size: 11px;
            color: #6b7280;
            margin-top: 3px;
        }

        .variant-options {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            max-width: 250px;
        }

        .variant-options span {
            display: inline-flex;
            align-items: center;
            padding: 4px 7px;
            border-radius: 6px;
            background: #f3f4f6;
            color: #374151;
            font-size: 9px;
            font-weight: 700;
        }

        .variant-price {
            font-size: 12px;
            font-weight: 800;
            color: #111827;
        }

        .variant-sale-price {
            margin-top: 3px;
            color: #dc2626;
            font-size: 10px;
        }

        .variant-stock {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #374151;
            font-size: 10px;
            font-weight: 800;
        }

        .variant-stock-low {
            background: #fff7ed;
            color: #c2410c;
        }

        .variant-stock-out {
            background: #fef2f2;
            color: #dc2626;
        }

        .variant-status {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
        }

        .variant-status-active {
            background: #ecfdf3;
            color: #15803d;
        }

        .variant-status-inactive {
            background: #fef2f2;
            color: #dc2626;
        }

        .variant-action-group {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .variant-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            padding: 0 9px;
            border-radius: 7px;
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }

        .variant-edit-btn {
            color: #374151;
            background: #f3f4f6;
        }

        .variant-edit-btn:hover {
            color: #111827;
            background: #e5e7eb;
        }

        .variant-delete-btn {
            color: #dc2626;
            background: #fef2f2;
        }

        .variant-delete-btn:hover {
            color: #b91c1c;
            background: #fee2e2;
        }

        .no-variants-box {
            padding: 30px 20px;
            text-align: center;
            background: #fff;
        }

        .no-variants-box strong {
            display: block;
            color: #374151;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .no-variants-box span {
            color: #9ca3af;
            font-size: 11px;
        }


        /*
        |--------------------------------------------------------------------------
        | PRODUCT TABLE
        |--------------------------------------------------------------------------
        */

        .product-table-wrap {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .product-table {
            width: 100%;
            min-width: 1100px;
            border-collapse: collapse;
        }

        .product-table th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .6px;
            padding: 14px 16px;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        .product-table td {
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            font-size: 13px;
        }

        .product-table tr:last-child td {
            border-bottom: 0;
        }

        .product-name {
            font-weight: 800;
            color: #111827;
        }

        .product-sku {
            color: #6b7280;
            font-size: 11px;
            margin-top: 3px;
        }

        .product-slug {
            color: #9ca3af;
            font-size: 10px;
            margin-top: 2px;
        }

        .category-text {
            font-size: 12px;
            font-weight: 700;
            color: #374151;
        }

        .sub-category-text {
            color: #6b7280;
            font-size: 11px;
            margin-top: 3px;
        }

        .collection-text {
            color: #6b7280;
            font-size: 11px;
        }

        .price-main {
            font-weight: 800;
            color: #111827;
        }

        .price-sale {
            color: #dc2626;
            font-size: 11px;
            margin-top: 3px;
        }

        .stock-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #374151;
            font-size: 10px;
            font-weight: 800;
        }

        .stock-low {
            background: #fff7ed;
            color: #c2410c;
        }

        .stock-out {
            background: #fef2f2;
            color: #dc2626;
        }

        .status-badge,
        .featured-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
        }

        .status-active {
            background: #ecfdf3;
            color: #15803d;
        }

        .status-inactive {
            background: #fef2f2;
            color: #dc2626;
        }

        .featured-yes {
            background: #fff7ed;
            color: #c2410c;
        }

        .featured-no {
            background: #f3f4f6;
            color: #6b7280;
        }


        /*
        |--------------------------------------------------------------------------
        | TABLE IMAGE
        |--------------------------------------------------------------------------
        */

        .table-product-image-wrap {
            width: 46px;
            height: 46px;
            border-radius: 8px;
            overflow: hidden;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            flex-shrink: 0;
        }

        .table-product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .table-product-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 17px;
        }

        .product-table-product-cell {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .image-count-text {
            color: #6b7280;
            font-size: 10px;
            margin-top: 3px;
        }

        .action-group {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            padding: 0 10px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
        }

        .edit-btn {
            color: #374151;
            background: #f3f4f6;
        }

        .edit-btn:hover {
            background: #e5e7eb;
            color: #111827;
        }

        .delete-btn {
            color: #dc2626;
            background: #fef2f2;
        }

        .delete-btn:hover {
            background: #fee2e2;
            color: #b91c1c;
        }

        .empty-products {
            padding: 55px 25px;
            text-align: center;
        }

        .empty-products-icon {
            font-size: 30px;
            margin-bottom: 10px;
        }

        .empty-products h4 {
            margin: 0 0 6px;
            font-size: 15px;
            font-weight: 800;
        }

        .empty-products p {
            margin: 0;
            color: #6b7280;
            font-size: 12px;
        }

        .alert {
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 22px;
        }


        /*
        |--------------------------------------------------------------------------
        | OPTIONAL PRODUCT VARIANT BUILDER
        |--------------------------------------------------------------------------
        */

        .product-variant-builder {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 22px;
        }

        .variant-builder-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 16px;
        }

        .variant-builder-header h4 {
            margin: 0;
            font-size: 14px;
            font-weight: 800;
            color: #111827;
        }

        .variant-builder-header p {
            margin: 5px 0 0;
            color: #6b7280;
            font-size: 11px;
            line-height: 1.5;
        }

        .variant-add-row-btn {
            min-height: 38px;
            padding: 0 14px;
            border: 0;
            border-radius: 8px;
            background: #111827;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
        }

        .variant-add-row-btn:hover {
            background: #1f2937;
        }

        .variant-builder-row {
            padding: 18px;
            margin-top: 12px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }

        .variant-row-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 16px;
        }

        .variant-row-top strong {
            color: #111827;
            font-size: 12px;
            font-weight: 800;
        }

        .variant-remove-row {
            border: 0;
            background: #fef2f2;
            color: #dc2626;
            border-radius: 7px;
            padding: 6px 10px;
            font-size: 10px;
            font-weight: 800;
            cursor: pointer;
        }

        .variant-remove-row:hover {
            background: #fee2e2;
            color: #b91c1c;
        }

        @media (max-width: 768px) {

            .product-variant-builder {
                padding: 14px;
            }

            .variant-builder-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .variant-add-row-btn {
                width: 100%;
            }

            .variant-builder-row {
                padding: 14px;
            }

        }

        @media (max-width: 520px) {

            .product-variant-builder {
                padding: 12px;
            }

            .variant-builder-row {
                padding: 12px;
            }

            .variant-row-top {
                align-items: flex-start;
            }

        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 992px) {

            .existing-images-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

        }


        @media (max-width: 768px) {

            .page-content {
                padding: 22px 18px;
                overflow-x: hidden;
            }

            .product-card-body {
                padding: 18px;
            }

            .product-card-header {
                padding: 18px;
            }

            .existing-images-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .variant-add-card {
                margin: 15px;
                padding: 16px;
            }

            .variant-list-card {
                margin: 0 15px 15px;
            }

            .variant-management-header {
                padding: 18px;
            }

            .variant-list-header {
                padding: 16px;
            }

            .variant-edit-notice {
                align-items: flex-start;
                flex-direction: column;
            }

        }


        @media (max-width: 520px) {

            .page-content {
                padding: 18px 14px;
            }

            .product-card-body {
                padding: 15px;
            }

            .product-card-header {
                padding: 16px;
            }

            .existing-images-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .product-image-info {
                padding: 8px;
            }

            .product-image-actions {
                flex-direction: column;
            }

            .image-action-btn {
                width: 100%;
            }

            .save-btn,
            .cancel-btn {
                width: 100%;
                margin-left: 0;
            }

            .cancel-btn {
                margin-top: 9px;
            }

            .variant-add-card {
                margin: 12px;
                padding: 14px;
            }

            .variant-list-card {
                margin: 0 12px 12px;
            }

        }


        @media (max-width: 360px) {

            .existing-images-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>

<div class="admin-layout">

    <?php include "../includes/sidebar.php"; ?>


    <div class="main-area">

        <?php include "../includes/header.php"; ?>


        <main class="page-content">


            <div class="page-heading">

                <h1>Products</h1>

                <p>
                    Manage WB Sports products, pricing, categories and inventory.
                </p>

            </div>


            <?php if ($message !== ""): ?>

                <div
                    class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show"
                >

                    <?php
                    echo htmlspecialchars($message);
                    ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- =========================================================
                 PRODUCT FORM
            ========================================================== -->

            <div class="product-form-card">

                <div class="product-card-header">

                    <h3>

                        <?php
                        echo $editProduct
                            ? "Edit Product"
                            : "Add Product";
                        ?>

                    </h3>

                    <p>
                        Add the basic information for your WB Sports product.
                    </p>

                </div>


                <div class="product-card-body">

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                    >

                        <?php if ($editProduct): ?>

                            <input
                                type="hidden"
                                name="id"
                                value="<?php echo (int) $editProduct["id"]; ?>"
                            >

                        <?php endif; ?>


                        <div class="form-section-title">
                            Basic Information
                        </div>


                        <div class="row g-3">


                            <!-- PRODUCT NAME -->

                            <div class="col-lg-6">

                                <label class="form-label">
                                    Product Name
                                </label>

                                <input
                                    type="text"
                                    name="name"
                                    class="form-control"
                                    placeholder="e.g. WB White Tracksuit"
                                    value="<?php echo htmlspecialchars($editProduct["name"] ?? ""); ?>"
                                    required
                                >

                            </div>


                            <!-- SKU -->

                            <div class="col-lg-3">

                                <label class="form-label">
                                    SKU
                                </label>

                                <input
                                    type="text"
                                    name="sku"
                                    class="form-control"
                                    placeholder="e.g. WB-TS-001"
                                    value="<?php echo htmlspecialchars($editProduct["sku"] ?? ""); ?>"
                                >

                            </div>


                            <!-- SLUG -->

                            <div class="col-lg-3">

                                <label class="form-label">
                                    Slug
                                </label>

                                <input
                                    type="text"
                                    name="slug"
                                    class="form-control"
                                    placeholder="white-tracksuit"
                                    value="<?php echo htmlspecialchars($editProduct["slug"] ?? ""); ?>"
                                >

                            </div>


                            <!-- CATEGORY -->

                            <div class="col-lg-4">

                                <label class="form-label">
                                    Category
                                </label>

                                <select
                                    name="category_id"
                                    class="form-select"
                                >

                                    <option value="">
                                        Select Category
                                    </option>

                                    <?php foreach ($categories as $category): ?>

                                        <option
                                            value="<?php echo (int) $category["id"]; ?>"
                                            <?php
                                            if (
                                                isset($editProduct["category_id"]) &&
                                                (int) $editProduct["category_id"] ===
                                                (int) $category["id"]
                                            ) {
                                                echo "selected";
                                            }
                                            ?>
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $category["name"]
                                            );
                                            ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- SUB CATEGORY -->

                            <div class="col-lg-4">

                                <label class="form-label">
                                    Sub Category
                                </label>

                                <select
                                    name="sub_category_id"
                                    class="form-select"
                                >

                                    <option value="">
                                        Select Sub Category
                                    </option>

                                    <?php foreach ($subCategories as $subCategory): ?>

                                        <option
                                            value="<?php echo (int) $subCategory["id"]; ?>"
                                            <?php
                                            if (
                                                isset($editProduct["sub_category_id"]) &&
                                                (int) $editProduct["sub_category_id"] ===
                                                (int) $subCategory["id"]
                                            ) {
                                                echo "selected";
                                            }
                                            ?>
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $subCategory["name"]
                                            );
                                            ?>

                                            <?php if (!empty($subCategory["category_name"])): ?>

                                                —
                                                <?php
                                                echo htmlspecialchars(
                                                    $subCategory["category_name"]
                                                );
                                                ?>

                                            <?php endif; ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- COLLECTION -->

                            <div class="col-lg-4">

                                <label class="form-label">
                                    Collection
                                </label>

                                <select
                                    name="collection_id"
                                    class="form-select"
                                >

                                    <option value="">
                                        Select Collection
                                    </option>

                                    <?php foreach ($collections as $collection): ?>

                                        <option
                                            value="<?php echo (int) $collection["id"]; ?>"
                                            <?php
                                            if (
                                                isset($editProduct["collection_id"]) &&
                                                (int) $editProduct["collection_id"] ===
                                                (int) $collection["id"]
                                            ) {
                                                echo "selected";
                                            }
                                            ?>
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $collection["name"]
                                            );
                                            ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- DESCRIPTION -->

                            <div class="col-12">

                                <label class="form-label">
                                    Description
                                </label>

                                <textarea
                                    name="description"
                                    class="form-control"
                                    placeholder="Write product description..."
                                ><?php echo htmlspecialchars($editProduct["description"] ?? ""); ?></textarea>

                            </div>

                        </div>


                        <div class="form-section-title mt-4">
                            Pricing & Inventory
                        </div>


                        <div class="row g-3">


                            <!-- PRICE -->

                            <div class="col-md-4">

                                <label class="form-label">
                                    Price
                                </label>

                                <input
                                    type="number"
                                    name="price"
                                    class="form-control"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                    value="<?php echo htmlspecialchars($editProduct["price"] ?? "0.00"); ?>"
                                    required
                                >

                            </div>


                            <!-- SALE PRICE -->

                            <div class="col-md-4">

                                <label class="form-label">
                                    Sale Price
                                </label>

                                <input
                                    type="number"
                                    name="sale_price"
                                    class="form-control"
                                    min="0"
                                    step="0.01"
                                    placeholder="Optional"
                                    value="<?php echo htmlspecialchars($editProduct["sale_price"] ?? ""); ?>"
                                >

                            </div>


                            <!-- STOCK -->

                            <div class="col-md-4">

                                <label class="form-label">
                                    Stock Quantity
                                </label>

                                <input
                                    type="number"
                                    name="stock_quantity"
                                    class="form-control"
                                    min="0"
                                    value="<?php echo htmlspecialchars($editProduct["stock_quantity"] ?? "0"); ?>"
                                >

                            </div>

                        </div>


                        <!-- =================================================
                             PRODUCT IMAGES
                        ================================================== -->

                        <div class="form-section-title mt-4">
                            Product Images
                        </div>


                        <div class="image-upload-box mb-4">

                            <label class="form-label">

                                <?php
                                echo $editProduct
                                    ? "Add More Images"
                                    : "Upload Product Images";
                                ?>

                            </label>


                            <input
                                type="file"
                                name="images[]"
                                id="productImages"
                                class="form-control"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                multiple
                            >


                            <div class="form-text">

                                Optional. You can upload multiple images.
                                JPG, JPEG, PNG and WEBP only. Maximum 5MB per image.

                            </div>


                            <div
                                id="imagePreview"
                                class="selected-images-preview"
                            ></div>

                        </div>


                        <?php if ($editProduct): ?>

                            <!-- EXISTING IMAGES -->

                            <div class="mb-4">

                                <label class="form-label">
                                    Existing Images
                                </label>


                                <?php if (!empty($editProductImages)): ?>

                                    <div class="existing-images-grid">

                                        <?php foreach ($editProductImages as $image): ?>

                                            <div class="product-image-card">

                                                <?php if ((int) $image["is_primary"] === 1): ?>

                                                    <span class="primary-image-badge">
                                                        Primary
                                                    </span>

                                                <?php endif; ?>


                                                <div class="product-image-preview">

                                                    <img
                                                        src="../<?php echo htmlspecialchars($image["image"]); ?>"
                                                        alt="<?php echo htmlspecialchars($image["alt_text"] ?: $editProduct["name"]); ?>"
                                                    >

                                                </div>


                                                <div class="product-image-info">

                                                    <div class="product-image-alt">

                                                        <?php
                                                        echo htmlspecialchars(
                                                            $image["alt_text"]
                                                            ?: "Product image"
                                                        );
                                                        ?>

                                                    </div>


                                                    <div class="product-image-actions">


                                                        <?php if ((int) $image["is_primary"] !== 1): ?>

                                                            <a
                                                                href="products.php?primary_image=<?php echo (int) $image["id"]; ?>&product_id=<?php echo (int) $editProduct["id"]; ?>"
                                                                class="image-action-btn image-primary-btn"
                                                            >
                                                                Set Primary
                                                            </a>

                                                        <?php endif; ?>


                                                        <a
                                                            href="products.php?delete_image=<?php echo (int) $image["id"]; ?>&product_id=<?php echo (int) $editProduct["id"]; ?>"
                                                            class="image-action-btn image-delete-btn"
                                                            onclick="return confirm('Are you sure you want to delete this image?');"
                                                        >
                                                            Delete
                                                        </a>

                                                    </div>

                                                </div>

                                            </div>

                                        <?php endforeach; ?>

                                    </div>

                                <?php else: ?>

                                    <div class="no-images-box">

                                        <strong>
                                            No images added yet.
                                        </strong>

                                        <span>
                                            You can upload product images using the field above.
                                        </span>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>


                        <!-- =================================================
                             OPTIONAL PRODUCT VARIANTS
                        ================================================== -->

                        <div class="form-section-title mt-4">
                            Product Variants
                        </div>

                        <div class="product-variant-builder">

                            <div class="variant-builder-header">

                                <div>
                                    <h4>
                                        Optional Variants
                                    </h4>

                                    <p>
                                        Add variants such as size, color, hand or type.
                                        Leave this section empty if the product has no variants.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="variant-add-row-btn"
                                    id="addVariantRow"
                                >
                                    + Add Variant
                                </button>

                            </div>


                            <div id="variantRows">

                                <div class="variant-builder-row">

                                    <div class="variant-row-top">

                                        <strong>
                                            Variant #1
                                        </strong>

                                        <button
                                            type="button"
                                            class="variant-remove-row"
                                        >
                                            Remove
                                        </button>

                                    </div>


                                    <div class="row g-3">

                                        <!-- VARIANT NAME -->
                                        <div class="col-lg-4 col-md-6">

                                            <label class="form-label">
                                                Variant Name
                                            </label>

                                            <input
                                                type="text"
                                                name="variant_name[]"
                                                class="form-control"
                                                placeholder="e.g. Black - Large"
                                            >

                                        </div>


                                        <!-- VARIANT SKU -->
                                        <div class="col-lg-4 col-md-6">

                                            <label class="form-label">
                                                Variant SKU
                                            </label>

                                            <input
                                                type="text"
                                                name="variant_sku[]"
                                                class="form-control"
                                                placeholder="e.g. WB-001-BLK-L"
                                            >

                                        </div>


                                        <!-- SIZE -->
                                        <div class="col-lg-4 col-md-6">

                                            <label class="form-label">
                                                Size
                                            </label>

                                            <input
                                                type="text"
                                                name="variant_size[]"
                                                class="form-control"
                                                placeholder="e.g. S, M, L, XL"
                                            >

                                        </div>


                                        <!-- COLOR -->
                                        <div class="col-lg-4 col-md-6">

                                            <label class="form-label">
                                                Color
                                            </label>

                                            <input
                                                type="text"
                                                name="variant_color[]"
                                                class="form-control"
                                                placeholder="e.g. Black"
                                            >

                                        </div>


                                        <!-- HAND -->
                                        <div class="col-lg-4 col-md-6">

                                            <label class="form-label">
                                                Hand
                                            </label>

                                            <select
                                                name="variant_hand[]"
                                                class="form-select"
                                            >
                                                <option value="">
                                                    Not Applicable
                                                </option>

                                                <option value="LH">
                                                    LH
                                                </option>

                                                <option value="RH">
                                                    RH
                                                </option>

                                            </select>

                                        </div>


                                        <!-- TYPE -->
                                        <div class="col-lg-4 col-md-6">

                                            <label class="form-label">
                                                Type
                                            </label>

                                            <select
                                                name="variant_type[]"
                                                class="form-select"
                                            >
                                                <option value="">
                                                    Not Applicable
                                                </option>

                                                <option value="Youth">
                                                    Youth
                                                </option>

                                                <option value="Regular">
                                                    Regular
                                                </option>

                                            </select>

                                        </div>


                                        <!-- PRICE -->
                                        <div class="col-lg-4 col-md-6">

                                            <label class="form-label">
                                                Price
                                            </label>

                                            <input
                                                type="number"
                                                name="variant_price[]"
                                                class="form-control"
                                                min="0"
                                                step="0.01"
                                                placeholder="0.00"
                                            >

                                        </div>


                                        <!-- SALE PRICE -->
                                        <div class="col-lg-4 col-md-6">

                                            <label class="form-label">
                                                Sale Price
                                            </label>

                                            <input
                                                type="number"
                                                name="variant_sale_price[]"
                                                class="form-control"
                                                min="0"
                                                step="0.01"
                                                placeholder="Optional"
                                            >

                                        </div>


                                        <!-- STOCK -->
                                        <div class="col-lg-4 col-md-6">

                                            <label class="form-label">
                                                Stock
                                            </label>

                                            <input
                                                type="number"
                                                name="variant_stock[]"
                                                class="form-control"
                                                min="0"
                                                value="0"
                                            >

                                        </div>


                                        <!-- STATUS -->
                                        <div class="col-12">

                                            <label class="form-label">
                                                Variant Status
                                            </label>

                                            <select
                                                name="variant_status[]"
                                                class="form-select"
                                            >
                                                <option value="active">
                                                    Active
                                                </option>

                                                <option value="inactive">
                                                    Inactive
                                                </option>

                                            </select>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <div class="form-section-title mt-4">
                            Visibility
                        </div>


                        <div class="d-flex flex-wrap gap-4 mb-4">


                            <!-- STATUS -->

                            <div class="checkbox-row">

                                <input
                                    type="checkbox"
                                    id="status"
                                    name="status"
                                    <?php
                                    if (
                                        !$editProduct ||
                                        (int) $editProduct["status"] === 1
                                    ) {
                                        echo "checked";
                                    }
                                    ?>
                                >

                                <label for="status">
                                    Active Product
                                </label>

                            </div>


                            <!-- FEATURED -->

                            <div class="checkbox-row">

                                <input
                                    type="checkbox"
                                    id="featured"
                                    name="featured"
                                    <?php
                                    if (
                                        $editProduct &&
                                        (int) $editProduct["featured"] === 1
                                    ) {
                                        echo "checked";
                                    }
                                    ?>
                                >

                                <label for="featured">
                                    Featured Product
                                </label>

                            </div>

                        </div>


                        <button
                            type="submit"
                            class="save-btn"
                        >

                            <?php
                            echo $editProduct
                                ? "Update Product"
                                : "Add Product";
                            ?>

                        </button>


                        <?php if ($editProduct): ?>

                            <a
                                href="products.php"
                                class="cancel-btn"
                            >
                                Cancel Edit
                            </a>

                        <?php endif; ?>


                    </form>

                </div>

            </div>


            <!-- =========================================================
                 PRODUCT VARIANTS
            ========================================================== -->

            <?php if ($editProduct): ?>

                <div class="variant-management-card">

                    <div class="variant-management-header">

                        <h3>
                            Product Variants
                        </h3>

                        <p>
                            Manage sizes, colors, hands, types, pricing and stock for this product.
                        </p>

                    </div>


                    <!-- =================================================
                         ADD / EDIT VARIANT FORM
                    ================================================== -->

                    <div class="variant-add-card">

                        <div class="variant-add-title">

                            <?php
                            echo $editVariant
                                ? "Edit Variant"
                                : "Add Variant";
                            ?>

                        </div>


                        <div class="variant-add-subtitle">

                            <?php
                            echo $editVariant
                                ? "Update the selected product variant."
                                : "Create a variant for this product.";
                            ?>

                        </div>


                        <?php if ($editVariant): ?>

                            <div class="variant-edit-notice">

                                <span>
                                    You are editing:
                                    <?php
                                    echo htmlspecialchars(
                                        $editVariant["variant_name"]
                                    );
                                    ?>
                                </span>


                                <a
                                    href="products.php?edit=<?php echo (int) $editProduct["id"]; ?>"
                                    class="variant-edit-cancel"
                                >
                                    Cancel Variant Edit
                                </a>

                            </div>

                        <?php endif; ?>


                        <form method="POST">

                            <input
                                type="hidden"
                                name="variant_action"
                                value="<?php echo $editVariant ? "update" : "add"; ?>"
                            >


                            <input
                                type="hidden"
                                name="product_id"
                                value="<?php echo (int) $editProduct["id"]; ?>"
                            >


                            <?php if ($editVariant): ?>

                                <input
                                    type="hidden"
                                    name="variant_id"
                                    value="<?php echo (int) $editVariant["id"]; ?>"
                                >

                            <?php endif; ?>


                            <div class="row g-3">


                                <!-- VARIANT NAME -->

                                <div class="col-lg-4 col-md-6">

                                    <label class="form-label">
                                        Variant Name
                                    </label>

                                    <input
                                        type="text"
                                        name="variant_name"
                                        class="form-control"
                                        placeholder="e.g. White - Large"
                                        value="<?php echo htmlspecialchars($editVariant["variant_name"] ?? ""); ?>"
                                        required
                                    >

                                </div>


                                <!-- VARIANT SKU -->

                                <div class="col-lg-4 col-md-6">

                                    <label class="form-label">
                                        Variant SKU
                                    </label>

                                    <input
                                        type="text"
                                        name="variant_sku"
                                        class="form-control"
                                        placeholder="e.g. WB-001-L-WH"
                                        value="<?php echo htmlspecialchars($editVariant["sku"] ?? ""); ?>"
                                        required
                                    >

                                </div>


                                <!-- SIZE -->

                                <div class="col-lg-4 col-md-6">

                                    <label class="form-label">
                                        Size
                                    </label>

                                    <input
                                        type="text"
                                        name="variant_size"
                                        class="form-control"
                                        placeholder="e.g. S, M, L, XL"
                                        value="<?php echo htmlspecialchars($editVariant["size"] ?? ""); ?>"
                                    >

                                </div>


                                <!-- COLOR -->

                                <div class="col-lg-4 col-md-6">

                                    <label class="form-label">
                                        Color
                                    </label>

                                    <input
                                        type="text"
                                        name="variant_color"
                                        class="form-control"
                                        placeholder="e.g. Black"
                                        value="<?php echo htmlspecialchars($editVariant["color"] ?? ""); ?>"
                                    >

                                </div>


                                <!-- HAND -->

                                <div class="col-lg-4 col-md-6">

                                    <label class="form-label">
                                        Hand
                                    </label>

                                    <select
                                        name="variant_hand"
                                        class="form-select"
                                    >

                                        <option value="">
                                            Not Applicable
                                        </option>

                                        <option
                                            value="LH"
                                            <?php
                                            echo (
                                                ($editVariant["hand"] ?? "") === "LH"
                                            )
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            LH
                                        </option>

                                        <option
                                            value="RH"
                                            <?php
                                            echo (
                                                ($editVariant["hand"] ?? "") === "RH"
                                            )
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            RH
                                        </option>

                                    </select>

                                </div>


                                <!-- TYPE -->

                                <div class="col-lg-4 col-md-6">

                                    <label class="form-label">
                                        Type
                                    </label>

                                    <select
                                        name="variant_type"
                                        class="form-select"
                                    >

                                        <option value="">
                                            Not Applicable
                                        </option>

                                        <option
                                            value="Youth"
                                            <?php
                                            echo (
                                                ($editVariant["type"] ?? "") === "Youth"
                                            )
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Youth
                                        </option>

                                        <option
                                            value="Regular"
                                            <?php
                                            echo (
                                                ($editVariant["type"] ?? "") === "Regular"
                                            )
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Regular
                                        </option>

                                    </select>

                                </div>


                                <!-- PRICE -->

                                <div class="col-lg-4 col-md-6">

                                    <label class="form-label">
                                        Price
                                    </label>

                                    <input
                                        type="number"
                                        name="variant_price"
                                        class="form-control"
                                        min="0"
                                        step="0.01"
                                        placeholder="0.00"
                                        value="<?php echo htmlspecialchars($editVariant["price"] ?? "0.00"); ?>"
                                        required
                                    >

                                </div>


                                <!-- SALE PRICE -->

                                <div class="col-lg-4 col-md-6">

                                    <label class="form-label">
                                        Sale Price
                                    </label>

                                    <input
                                        type="number"
                                        name="variant_sale_price"
                                        class="form-control"
                                        min="0"
                                        step="0.01"
                                        placeholder="Optional"
                                        value="<?php echo htmlspecialchars($editVariant["sale_price"] ?? ""); ?>"
                                    >

                                </div>


                                <!-- STOCK -->

                                <div class="col-lg-4 col-md-6">

                                    <label class="form-label">
                                        Stock
                                    </label>

                                    <input
                                        type="number"
                                        name="variant_stock"
                                        class="form-control"
                                        min="0"
                                        value="<?php echo htmlspecialchars($editVariant["stock"] ?? "0"); ?>"
                                    >

                                </div>


                                <!-- STATUS -->

                                <div class="col-12">

                                    <div class="checkbox-row mt-1">

                                        <input
                                            type="checkbox"
                                            id="variant_status"
                                            name="variant_status"
                                            <?php
                                            if (
                                                !$editVariant ||
                                                ($editVariant["status"] ?? "") === "active"
                                            ) {
                                                echo "checked";
                                            }
                                            ?>
                                        >

                                        <label for="variant_status">
                                            Active Variant
                                        </label>

                                    </div>

                                </div>


                                <!-- BUTTON -->

                                <div class="col-12">

                                    <button
                                        type="submit"
                                        class="save-btn"
                                    >

                                        <?php
                                        echo $editVariant
                                            ? "Update Variant"
                                            : "Add Variant";
                                        ?>

                                    </button>


                                    <?php if ($editVariant): ?>

                                        <a
                                            href="products.php?edit=<?php echo (int) $editProduct["id"]; ?>"
                                            class="cancel-btn"
                                        >
                                            Cancel
                                        </a>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </form>

                    </div>


                    <!-- =================================================
                         EXISTING VARIANTS
                    ================================================== -->

                    <div class="variant-list-card">

                        <div class="variant-list-header">

                            <h4>
                                Existing Variants
                            </h4>

                            <p>

                                <?php
                                echo count($editProductVariants);
                                ?>

                                variant<?php echo count($editProductVariants) === 1 ? "" : "s"; ?>
                                for this product.

                            </p>

                        </div>


                        <?php if (!empty($editProductVariants)): ?>

                            <div class="variant-table-wrap">

                                <table class="variant-table">

                                    <thead>

                                        <tr>

                                            <th>
                                                Variant
                                            </th>

                                            <th>
                                                Options
                                            </th>

                                            <th>
                                                Price
                                            </th>

                                            <th>
                                                Stock
                                            </th>

                                            <th>
                                                Status
                                            </th>

                                            <th>
                                                Actions
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                        <?php foreach ($editProductVariants as $variant): ?>

                                            <?php

                                            $variantStock =
                                                (int) $variant["stock"];


                                            if (
                                                $variantStock <= 0
                                            ) {

                                                $variantStockClass =
                                                    "variant-stock-out";

                                                $variantStockText =
                                                    "Out of Stock";

                                            } elseif (
                                                $variantStock <= 5
                                            ) {

                                                $variantStockClass =
                                                    "variant-stock-low";

                                                $variantStockText =
                                                    $variantStock . " Low";

                                            } else {

                                                $variantStockClass =
                                                    "";

                                                $variantStockText =
                                                    (string) $variantStock;
                                            }

                                            ?>


                                            <tr>


                                                <!-- VARIANT -->

                                                <td>

                                                    <div class="variant-name">

                                                        <?php
                                                        echo htmlspecialchars(
                                                            $variant["variant_name"]
                                                        );
                                                        ?>

                                                    </div>


                                                    <div class="variant-sku">

                                                        SKU:
                                                        <?php
                                                        echo htmlspecialchars(
                                                            $variant["sku"]
                                                        );
                                                        ?>

                                                    </div>

                                                </td>


                                                <!-- OPTIONS -->

                                                <td>

                                                    <div class="variant-options">


                                                        <?php if (!empty($variant["size"])): ?>

                                                            <span>
                                                                Size:
                                                                <?php
                                                                echo htmlspecialchars(
                                                                    $variant["size"]
                                                                );
                                                                ?>
                                                            </span>

                                                        <?php endif; ?>


                                                        <?php if (!empty($variant["color"])): ?>

                                                            <span>
                                                                Color:
                                                                <?php
                                                                echo htmlspecialchars(
                                                                    $variant["color"]
                                                                );
                                                                ?>
                                                            </span>

                                                        <?php endif; ?>


                                                        <?php if (!empty($variant["hand"])): ?>

                                                            <span>
                                                                Hand:
                                                                <?php
                                                                echo htmlspecialchars(
                                                                    $variant["hand"]
                                                                );
                                                                ?>
                                                            </span>

                                                        <?php endif; ?>


                                                        <?php if (!empty($variant["type"])): ?>

                                                            <span>
                                                                Type:
                                                                <?php
                                                                echo htmlspecialchars(
                                                                    $variant["type"]
                                                                );
                                                                ?>
                                                            </span>

                                                        <?php endif; ?>


                                                        <?php if (
                                                            empty($variant["size"]) &&
                                                            empty($variant["color"]) &&
                                                            empty($variant["hand"]) &&
                                                            empty($variant["type"])
                                                        ): ?>

                                                            <span>
                                                                No options
                                                            </span>

                                                        <?php endif; ?>

                                                    </div>

                                                </td>


                                                <!-- PRICE -->

                                                <td>

                                                    <div class="variant-price">

                                                        Rs.
                                                        <?php
                                                        echo number_format(
                                                            (float) $variant["price"],
                                                            2
                                                        );
                                                        ?>

                                                    </div>


                                                    <?php if (
                                                        $variant["sale_price"] !== null
                                                    ): ?>

                                                        <div class="variant-sale-price">

                                                            Sale:
                                                            Rs.
                                                            <?php
                                                            echo number_format(
                                                                (float) $variant["sale_price"],
                                                                2
                                                            );
                                                            ?>

                                                        </div>

                                                    <?php endif; ?>

                                                </td>


                                                <!-- STOCK -->

                                                <td>

                                                    <span
                                                        class="variant-stock <?php echo $variantStockClass; ?>"
                                                    >

                                                        <?php
                                                        echo htmlspecialchars(
                                                            $variantStockText
                                                        );
                                                        ?>

                                                    </span>

                                                </td>


                                                <!-- STATUS -->

                                                <td>

                                                    <?php if (
                                                        ($variant["status"] ?? "") === "active"
                                                    ): ?>

                                                        <span class="variant-status variant-status-active">
                                                            Active
                                                        </span>

                                                    <?php else: ?>

                                                        <span class="variant-status variant-status-inactive">
                                                            Inactive
                                                        </span>

                                                    <?php endif; ?>

                                                </td>


                                                <!-- ACTIONS -->

                                                <td>

                                                    <div class="variant-action-group">

                                                        <a
                                                            href="products.php?edit=<?php echo (int) $editProduct["id"]; ?>&variant_edit=<?php echo (int) $variant["id"]; ?>"
                                                            class="variant-action-btn variant-edit-btn"
                                                        >
                                                            Edit
                                                        </a>


                                                        <a
                                                            href="products.php?delete_variant=<?php echo (int) $variant["id"]; ?>&product_id=<?php echo (int) $editProduct["id"]; ?>"
                                                            class="variant-action-btn variant-delete-btn"
                                                            onclick="return confirm('Are you sure you want to delete this variant?');"
                                                        >
                                                            Delete
                                                        </a>

                                                    </div>

                                                </td>


                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php else: ?>

                            <div class="no-variants-box">

                                <strong>
                                    No variants added yet.
                                </strong>

                                <span>
                                    Use the form above to add the first variant for this product.
                                </span>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>


            <!-- =========================================================
                 PRODUCTS TABLE
            ========================================================== -->

            <div class="product-table-card">

                <div class="product-card-header">

                    <h3>
                        All Products
                    </h3>

                    <p>

                        <?php
                        echo count($products);
                        ?>

                        product<?php echo count($products) === 1 ? "" : "s"; ?>
                        available

                    </p>

                </div>


                <?php if (!empty($products)): ?>

                    <div class="product-table-wrap">

                        <table class="product-table">

                            <thead>

                                <tr>

                                    <th>
                                        Product
                                    </th>

                                    <th>
                                        Category
                                    </th>

                                    <th>
                                        Collection
                                    </th>

                                    <th>
                                        Price
                                    </th>

                                    <th>
                                        Stock
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Featured
                                    </th>

                                    <th>
                                        Actions
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($products as $product): ?>

                                    <?php

                                    $productId =
                                        (int) $product["id"];

                                    $imageInfo =
                                        $productImageData[$productId]
                                        ?? null;

                                    $primaryImage =
                                        $imageInfo["primary_image"]
                                        ?? null;

                                    $imageCount =
                                        (int) (
                                            $imageInfo["image_count"]
                                            ?? 0
                                        );

                                    ?>


                                    <tr>


                                        <!-- PRODUCT -->

                                        <td>

                                            <div class="product-table-product-cell">


                                                <div class="table-product-image-wrap">

                                                    <?php if (!empty($primaryImage)): ?>

                                                        <img
                                                            src="../<?php echo htmlspecialchars($primaryImage); ?>"
                                                            alt="<?php echo htmlspecialchars($product["name"]); ?>"
                                                            class="table-product-image"
                                                        >

                                                    <?php else: ?>

                                                        <div class="table-product-image-placeholder">
                                                            🛍️
                                                        </div>

                                                    <?php endif; ?>

                                                </div>


                                                <div>

                                                    <div class="product-name">

                                                        <?php
                                                        echo htmlspecialchars(
                                                            $product["name"]
                                                        );
                                                        ?>

                                                    </div>


                                                    <?php if (!empty($product["sku"])): ?>

                                                        <div class="product-sku">

                                                            SKU:
                                                            <?php
                                                            echo htmlspecialchars(
                                                                $product["sku"]
                                                            );
                                                            ?>

                                                        </div>

                                                    <?php endif; ?>


                                                    <div class="product-slug">

                                                        /
                                                        <?php
                                                        echo htmlspecialchars(
                                                            $product["slug"]
                                                        );
                                                        ?>

                                                    </div>


                                                    <?php if ($imageCount > 0): ?>

                                                        <div class="image-count-text">

                                                            <?php
                                                            echo $imageCount;
                                                            ?>

                                                            image<?php echo $imageCount === 1 ? "" : "s"; ?>

                                                        </div>

                                                    <?php endif; ?>

                                                </div>

                                            </div>

                                        </td>


                                        <!-- CATEGORY -->

                                        <td>

                                            <?php if (!empty($product["category_name"])): ?>

                                                <div class="category-text">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $product["category_name"]
                                                    );
                                                    ?>

                                                </div>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    —
                                                </span>

                                            <?php endif; ?>


                                            <?php if (!empty($product["sub_category_name"])): ?>

                                                <div class="sub-category-text">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $product["sub_category_name"]
                                                    );
                                                    ?>

                                                </div>

                                            <?php endif; ?>

                                        </td>


                                        <!-- COLLECTION -->

                                        <td>

                                            <?php if (!empty($product["collection_name"])): ?>

                                                <span class="collection-text">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $product["collection_name"]
                                                    );
                                                    ?>

                                                </span>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- PRICE -->

                                        <td>

                                            <div class="price-main">

                                                Rs.
                                                <?php
                                                echo number_format(
                                                    (float) $product["price"],
                                                    2
                                                );
                                                ?>

                                            </div>


                                            <?php if (
                                                $product["sale_price"] !== null
                                            ): ?>

                                                <div class="price-sale">

                                                    Sale:
                                                    Rs.
                                                    <?php
                                                    echo number_format(
                                                        (float) $product["sale_price"],
                                                        2
                                                    );
                                                    ?>

                                                </div>

                                            <?php endif; ?>

                                        </td>


                                        <!-- STOCK -->

                                        <td>

                                            <?php

                                            $stock =
                                                (int) $product["stock_quantity"];


                                            if ($stock <= 0) {

                                                $stockClass =
                                                    "stock-out";

                                                $stockText =
                                                    "Out of Stock";

                                            } elseif ($stock <= 5) {

                                                $stockClass =
                                                    "stock-low";

                                                $stockText =
                                                    $stock . " Low";

                                            } else {

                                                $stockClass =
                                                    "";

                                                $stockText =
                                                    $stock;
                                            }

                                            ?>


                                            <span
                                                class="stock-badge <?php echo $stockClass; ?>"
                                            >

                                                <?php
                                                echo htmlspecialchars(
                                                    $stockText
                                                );
                                                ?>

                                            </span>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <?php if (
                                                (int) $product["status"] === 1
                                            ): ?>

                                                <span class="status-badge status-active">
                                                    Active
                                                </span>

                                            <?php else: ?>

                                                <span class="status-badge status-inactive">
                                                    Inactive
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- FEATURED -->

                                        <td>

                                            <?php if (
                                                (int) $product["featured"] === 1
                                            ): ?>

                                                <span class="featured-badge featured-yes">
                                                    Featured
                                                </span>

                                            <?php else: ?>

                                                <span class="featured-badge featured-no">
                                                    No
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- ACTIONS -->

                                        <td>

                                            <div class="action-group">

                                                <a
                                                    href="products.php?edit=<?php echo $productId; ?>"
                                                    class="action-btn edit-btn"
                                                >
                                                    Edit
                                                </a>


                                                <a
                                                    href="products.php?delete=<?php echo $productId; ?>"
                                                    class="action-btn delete-btn"
                                                    onclick="return confirm('Are you sure you want to delete this product? This will also delete all product images and variants.');"
                                                >
                                                    Delete
                                                </a>

                                            </div>

                                        </td>


                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty-products">

                        <div class="empty-products-icon">
                            🛍️
                        </div>

                        <h4>
                            No Products Yet
                        </h4>

                        <p>
                            Add your first WB Sports product using the form above.
                        </p>

                    </div>

                <?php endif; ?>

            </div>


        </main>


        <?php include "../includes/footer.php"; ?>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

<script src="assets/js/admin.js"></script>


<script>

    const productImagesInput =
        document.getElementById("productImages");

    const imagePreview =
        document.getElementById("imagePreview");


    if (
        productImagesInput &&
        imagePreview
    ) {

        productImagesInput.addEventListener(
            "change",
            function () {

                imagePreview.innerHTML = "";

                const files =
                    Array.from(this.files);


                files.forEach(
                    function (file, index) {

                        if (
                            !file.type.startsWith("image/")
                        ) {
                            return;
                        }


                        const reader =
                            new FileReader();


                        reader.onload =
                            function (event) {

                                const previewItem =
                                    document.createElement(
                                        "div"
                                    );

                                previewItem.className =
                                    "selected-preview-item";


                                const image =
                                    document.createElement(
                                        "img"
                                    );

                                image.src =
                                    event.target.result;

                                image.alt =
                                    "Selected product image";


                                const number =
                                    document.createElement(
                                        "span"
                                    );

                                number.className =
                                    "selected-preview-number";

                                number.textContent =
                                    index === 0
                                        ? "Primary"
                                        : "#" + (index + 1);


                                previewItem.appendChild(
                                    image
                                );

                                previewItem.appendChild(
                                    number
                                );

                                imagePreview.appendChild(
                                    previewItem
                                );

                            };


                        reader.readAsDataURL(
                            file
                        );

                    }
                );

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | OPTIONAL PRODUCT VARIANT BUILDER
    |--------------------------------------------------------------------------
    */

    const addVariantRowButton =
        document.getElementById("addVariantRow");

    const variantRows =
        document.getElementById("variantRows");


    if (
        addVariantRowButton &&
        variantRows
    ) {

        function updateVariantNumbers() {

            const rows =
                variantRows.querySelectorAll(
                    ".variant-builder-row"
                );

            rows.forEach(function (row, index) {

                const title =
                    row.querySelector(
                        ".variant-row-top strong"
                    );

                if (title) {

                    title.textContent =
                        "Variant #" + (index + 1);

                }

            });

        }


        addVariantRowButton.addEventListener(
            "click",
            function () {

                const row =
                    document.createElement("div");

                row.className =
                    "variant-builder-row";

                row.innerHTML = `
                    <div class="variant-row-top">

                        <strong>
                            Variant
                        </strong>

                        <button
                            type="button"
                            class="variant-remove-row"
                        >
                            Remove
                        </button>

                    </div>


                    <div class="row g-3">

                        <div class="col-lg-4 col-md-6">

                            <label class="form-label">
                                Variant Name
                            </label>

                            <input
                                type="text"
                                name="variant_name[]"
                                class="form-control"
                                placeholder="e.g. Black - Large"
                            >

                        </div>


                        <div class="col-lg-4 col-md-6">

                            <label class="form-label">
                                Variant SKU
                            </label>

                            <input
                                type="text"
                                name="variant_sku[]"
                                class="form-control"
                                placeholder="e.g. WB-001-BLK-L"
                            >

                        </div>


                        <div class="col-lg-4 col-md-6">

                            <label class="form-label">
                                Size
                            </label>

                            <input
                                type="text"
                                name="variant_size[]"
                                class="form-control"
                                placeholder="e.g. S, M, L, XL"
                            >

                        </div>


                        <div class="col-lg-4 col-md-6">

                            <label class="form-label">
                                Color
                            </label>

                            <input
                                type="text"
                                name="variant_color[]"
                                class="form-control"
                                placeholder="e.g. Black"
                            >

                        </div>


                        <div class="col-lg-4 col-md-6">

                            <label class="form-label">
                                Hand
                            </label>

                            <select
                                name="variant_hand[]"
                                class="form-select"
                            >

                                <option value="">
                                    Not Applicable
                                </option>

                                <option value="LH">
                                    LH
                                </option>

                                <option value="RH">
                                    RH
                                </option>

                            </select>

                        </div>


                        <div class="col-lg-4 col-md-6">

                            <label class="form-label">
                                Type
                            </label>

                            <select
                                name="variant_type[]"
                                class="form-select"
                            >

                                <option value="">
                                    Not Applicable
                                </option>

                                <option value="Youth">
                                    Youth
                                </option>

                                <option value="Regular">
                                    Regular
                                </option>

                            </select>

                        </div>


                        <div class="col-lg-4 col-md-6">

                            <label class="form-label">
                                Price
                            </label>

                            <input
                                type="number"
                                name="variant_price[]"
                                class="form-control"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                            >

                        </div>


                        <div class="col-lg-4 col-md-6">

                            <label class="form-label">
                                Sale Price
                            </label>

                            <input
                                type="number"
                                name="variant_sale_price[]"
                                class="form-control"
                                min="0"
                                step="0.01"
                                placeholder="Optional"
                            >

                        </div>


                        <div class="col-lg-4 col-md-6">

                            <label class="form-label">
                                Stock
                            </label>

                            <input
                                type="number"
                                name="variant_stock[]"
                                class="form-control"
                                min="0"
                                value="0"
                            >

                        </div>


                        <div class="col-12">

                            <label class="form-label">
                                Variant Status
                            </label>

                            <select
                                name="variant_status[]"
                                class="form-select"
                            >

                                <option value="active">
                                    Active
                                </option>

                                <option value="inactive">
                                    Inactive
                                </option>

                            </select>

                        </div>

                    </div>
                `;

                variantRows.appendChild(row);

                updateVariantNumbers();

            }
        );


        variantRows.addEventListener(
            "click",
            function (event) {

                const removeButton =
                    event.target.closest(
                        ".variant-remove-row"
                    );

                if (!removeButton) {
                    return;
                }


                const rows =
                    variantRows.querySelectorAll(
                        ".variant-builder-row"
                    );


                if (rows.length === 1) {

                    const row = rows[0];

                    row.querySelectorAll("input")
                        .forEach(function (input) {

                            if (
                                input.type === "number" &&
                                input.name === "variant_stock[]"
                            ) {
                                input.value = "0";
                            } else {
                                input.value = "";
                            }

                        });


                    row.querySelectorAll("select")
                        .forEach(function (select) {

                            select.selectedIndex = 0;

                        });

                    return;
                }


                const row =
                    removeButton.closest(
                        ".variant-builder-row"
                    );


                if (row) {
                    row.remove();
                }


                updateVariantNumbers();

            }
        );

    }

</script>

</body>

</html>