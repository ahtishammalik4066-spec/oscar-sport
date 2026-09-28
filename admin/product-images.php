<?php

require_once "../config/database.php";
require_once "../includes/auth.php";

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| DELETE IMAGE
|--------------------------------------------------------------------------
*/
if (isset($_GET["delete"]) && is_numeric($_GET["delete"])) {

    $imageId = (int) $_GET["delete"];

    $stmt = $pdo->prepare("
        SELECT product_id, image, is_primary
        FROM product_images
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$imageId]);

    $imageData = $stmt->fetch();

    if ($imageData) {

        $productId = (int) $imageData["product_id"];
        $imagePath = "../" . $imageData["image"];
        $wasPrimary = (int) $imageData["is_primary"];

        // Delete database record
        $stmt = $pdo->prepare("
            DELETE FROM product_images
            WHERE id = ?
        ");

        $stmt->execute([$imageId]);

        // Delete physical image
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }

        /*
        |--------------------------------------------------------------------------
        | If deleted image was primary, make another image primary
        |--------------------------------------------------------------------------
        */
        if ($wasPrimary === 1) {

            $stmt = $pdo->prepare("
                SELECT id
                FROM product_images
                WHERE product_id = ?
                ORDER BY sort_order ASC, id ASC
                LIMIT 1
            ");

            $stmt->execute([$productId]);

            $nextImage = $stmt->fetch();

            if ($nextImage) {

                $nextImageId = (int) $nextImage["id"];

                $stmt = $pdo->prepare("
                    UPDATE product_images
                    SET is_primary = 1
                    WHERE id = ?
                ");

                $stmt->execute([$nextImageId]);
            }
        }

        header(
            "Location: product-images.php?product=" .
            $productId .
            "&success=deleted"
        );

        exit;
    }

    header("Location: product-images.php?error=notfound");
    exit;
}


/*
|--------------------------------------------------------------------------
| SET PRIMARY IMAGE
|--------------------------------------------------------------------------
*/
if (isset($_GET["primary"]) && is_numeric($_GET["primary"])) {

    $imageId = (int) $_GET["primary"];

    $stmt = $pdo->prepare("
        SELECT product_id
        FROM product_images
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$imageId]);

    $imageData = $stmt->fetch();

    if ($imageData) {

        $productId = (int) $imageData["product_id"];

        // Remove primary from all images of this product
        $stmt = $pdo->prepare("
            UPDATE product_images
            SET is_primary = 0
            WHERE product_id = ?
        ");

        $stmt->execute([$productId]);

        // Set selected image as primary
        $stmt = $pdo->prepare("
            UPDATE product_images
            SET is_primary = 1
            WHERE id = ?
        ");

        $stmt->execute([$imageId]);

        header(
            "Location: product-images.php?product=" .
            $productId .
            "&success=primary"
        );

        exit;
    }

    header("Location: product-images.php?error=notfound");
    exit;
}


/*
|--------------------------------------------------------------------------
| UPLOAD PRODUCT IMAGES
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $productId = isset($_POST["product_id"])
        ? (int) $_POST["product_id"]
        : 0;

    $altText = trim($_POST["alt_text"] ?? "");

    $sortOrder = isset($_POST["sort_order"])
        ? (int) $_POST["sort_order"]
        : 0;

    /*
    |--------------------------------------------------------------------------
    | Validate Product
    |--------------------------------------------------------------------------
    */
    if ($productId <= 0) {

        $error = "Please select a product.";

    } else {

        $stmt = $pdo->prepare("
            SELECT id
            FROM products
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$productId]);

        $productExists = $stmt->fetch();

        if (!$productExists) {

            $error = "Selected product does not exist.";

        } elseif (
            !isset($_FILES["images"]) ||
            empty($_FILES["images"]["name"][0])
        ) {

            $error = "Please select at least one image.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Upload Directory
            |--------------------------------------------------------------------------
            */
            $uploadDirectory = "../uploads/products/";

            if (!is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0777, true);
            }

            /*
            |--------------------------------------------------------------------------
            | Check Existing Images
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS total
                FROM product_images
                WHERE product_id = ?
            ");

            $stmt->execute([$productId]);

            $countData = $stmt->fetch();

            $existingImages = (int) $countData["total"];

            $uploadedCount = 0;
            $failedCount = 0;

            /*
            |--------------------------------------------------------------------------
            | Allowed Extensions
            |--------------------------------------------------------------------------
            */
            $allowedExtensions = [
                "jpg",
                "jpeg",
                "png",
                "webp"
            ];

            /*
            |--------------------------------------------------------------------------
            | Upload Multiple Images
            |--------------------------------------------------------------------------
            */
            $totalFiles = count($_FILES["images"]["name"]);

            for ($i = 0; $i < $totalFiles; $i++) {

                if (
                    $_FILES["images"]["error"][$i]
                    !== UPLOAD_ERR_OK
                ) {
                    $failedCount++;
                    continue;
                }

                $originalName =
                    $_FILES["images"]["name"][$i];

                $tmpName =
                    $_FILES["images"]["tmp_name"][$i];

                $fileSize =
                    (int) $_FILES["images"]["size"][$i];

                /*
                |--------------------------------------------------------------------------
                | File Extension
                |--------------------------------------------------------------------------
                */
                $extension = strtolower(
                    pathinfo(
                        $originalName,
                        PATHINFO_EXTENSION
                    )
                );

                if (
                    !in_array(
                        $extension,
                        $allowedExtensions,
                        true
                    )
                ) {
                    $failedCount++;
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Maximum File Size = 5MB
                |--------------------------------------------------------------------------
                */
                if ($fileSize > 5 * 1024 * 1024) {
                    $failedCount++;
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Verify Actual Image
                |--------------------------------------------------------------------------
                */
                $imageInfo = @getimagesize($tmpName);

                if ($imageInfo === false) {
                    $failedCount++;
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Generate Unique File Name
                |--------------------------------------------------------------------------
                */
                $fileName =
                    "product-" .
                    $productId .
                    "-" .
                    uniqid() .
                    "." .
                    $extension;

                $destination =
                    $uploadDirectory . $fileName;

                /*
                |--------------------------------------------------------------------------
                | Move File
                |--------------------------------------------------------------------------
                */
                if (
                    !move_uploaded_file(
                        $tmpName,
                        $destination
                    )
                ) {
                    $failedCount++;
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Relative Database Path
                |--------------------------------------------------------------------------
                */
                $databasePath =
                    "uploads/products/" . $fileName;

                /*
                |--------------------------------------------------------------------------
                | First Image Becomes Primary
                |--------------------------------------------------------------------------
                */
                $isPrimary =
                    (
                        $existingImages === 0 &&
                        $uploadedCount === 0
                    )
                    ? 1
                    : 0;

                /*
                |--------------------------------------------------------------------------
                | Insert Database Record
                |--------------------------------------------------------------------------
                */
                $stmt = $pdo->prepare("
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

                $currentSortOrder =
                    $sortOrder + $uploadedCount;

                try {

                    $stmt->execute([
                        $productId,
                        $databasePath,
                        $altText,
                        $currentSortOrder,
                        $isPrimary
                    ]);

                    $uploadedCount++;

                } catch (PDOException $e) {

                    // If DB insert fails, remove uploaded file
                    if (file_exists($destination)) {
                        unlink($destination);
                    }

                    $failedCount++;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Upload Message
            |--------------------------------------------------------------------------
            */
            if ($uploadedCount > 0) {

                $message =
                    $uploadedCount .
                    " image(s) uploaded successfully.";

                if ($failedCount > 0) {

                    $message .=
                        " " .
                        $failedCount .
                        " image(s) could not be uploaded.";
                }

            } else {

                $error =
                    "No image was uploaded successfully.";
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Success / Error Messages From Redirect
|--------------------------------------------------------------------------
*/
if (isset($_GET["success"])) {

    if ($_GET["success"] === "deleted") {
        $message = "Image deleted successfully.";
    }

    if ($_GET["success"] === "primary") {
        $message = "Primary image updated successfully.";
    }
}

if (isset($_GET["error"])) {

    if ($_GET["error"] === "notfound") {
        $error = "Image not found.";
    }
}


/*
|--------------------------------------------------------------------------
| Load Products
|--------------------------------------------------------------------------
*/
$products = [];

$stmt = $pdo->query("
    SELECT
        id,
        name,
        sku
    FROM products
    ORDER BY name ASC
");

$products = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Selected Product
|--------------------------------------------------------------------------
*/
$selectedProductId = 0;

if (
    isset($_GET["product"]) &&
    is_numeric($_GET["product"])
) {

    $selectedProductId = (int) $_GET["product"];

} elseif (
    isset($_POST["product_id"]) &&
    is_numeric($_POST["product_id"])
) {

    $selectedProductId = (int) $_POST["product_id"];
}


/*
|--------------------------------------------------------------------------
| Selected Product Details
|--------------------------------------------------------------------------
*/
$selectedProduct = null;

if ($selectedProductId > 0) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            sku
        FROM products
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$selectedProductId]);

    $selectedProduct = $stmt->fetch();
}


/*
|--------------------------------------------------------------------------
| Load Product Images
|--------------------------------------------------------------------------
*/
$productImages = [];

if ($selectedProductId > 0) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            product_id,
            image,
            alt_text,
            sort_order,
            is_primary,
            created_at
        FROM product_images
        WHERE product_id = ?
        ORDER BY
            is_primary DESC,
            sort_order ASC,
            id ASC
    ");

    $stmt->execute([$selectedProductId]);

    $productImages = $stmt->fetchAll();
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

    <title>Product Images | OSCAR CMS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="assets/css/admin.css"
    >

    <style>

        .page-content {
            padding: 32px;
            width: 100%;
            min-width: 0;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #111827;
        }

        .page-header p {
            margin: 7px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        .image-layout {
            display: grid;
            grid-template-columns: 360px minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }

        .admin-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }

        .admin-card h5 {
            margin: 0 0 20px;
            font-size: 18px;
            font-weight: 700;
            color: #111827;
        }

        .form-group {
            margin-bottom: 17px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }

        .form-control,
        .form-select {
            width: 100%;
            min-height: 44px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 14px;
            color: #111827;
            background: #ffffff;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #111827;
            box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.08);
        }

        textarea.form-control {
            min-height: 90px;
            resize: vertical;
        }

        .upload-info {
            margin-top: 8px;
            font-size: 12px;
            color: #6b7280;
            line-height: 1.5;
        }

        .btn-upload {
            width: 100%;
            min-height: 44px;
            border: 0;
            border-radius: 8px;
            background: #111827;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .btn-upload:hover {
            background: #000000;
        }

        .product-info {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 10px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
        }

        .product-info strong {
            display: block;
            color: #111827;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .product-info span {
            color: #6b7280;
            font-size: 12px;
        }

        .images-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .image-card {
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #ffffff;
        }

        .image-preview {
            position: relative;
            width: 100%;
            height: 220px;
            background: #f3f4f6;
            overflow: hidden;
        }

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .primary-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 5px 9px;
            border-radius: 20px;
            background: #111827;
            color: #ffffff;
            font-size: 11px;
            font-weight: 600;
        }

        .image-card-body {
            padding: 15px;
        }

        .image-alt {
            margin-bottom: 8px;
            color: #374151;
            font-size: 13px;
            line-height: 1.4;
        }

        .image-sort {
            margin-bottom: 13px;
            color: #6b7280;
            font-size: 12px;
        }

        .image-actions {
            display: flex;
            gap: 8px;
        }

        .image-actions a {
            flex: 1;
            text-align: center;
            text-decoration: none;
            padding: 8px 10px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
        }

        .btn-primary-image {
            background: #111827;
            color: #ffffff;
        }

        .btn-primary-image:hover {
            background: #000000;
            color: #ffffff;
        }

        .btn-delete-image {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-delete-image:hover {
            background: #fecaca;
            color: #991b1b;
        }

        .empty-state {
            padding: 55px 25px;
            text-align: center;
            border: 1px dashed #d1d5db;
            border-radius: 12px;
            background: #fafafa;
        }

        .empty-state h5 {
            margin-bottom: 7px;
            color: #374151;
            font-size: 17px;
        }

        .empty-state p {
            margin: 0;
            color: #6b7280;
            font-size: 13px;
        }

        .alert {
            border-radius: 9px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        /*
        |--------------------------------------------------------------------------
        | Tablet
        |--------------------------------------------------------------------------
        */
        @media (max-width: 1100px) {

            .image-layout {
                grid-template-columns: 1fr;
            }

            .images-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Mobile
        |--------------------------------------------------------------------------
        */
        @media (max-width: 768px) {

            .page-content {
                padding: 22px 18px;
            }

            .page-header {
                margin-bottom: 20px;
            }

            .page-header h2 {
                font-size: 23px;
            }

            .admin-card {
                padding: 18px;
            }

            .images-grid {
                grid-template-columns: 1fr;
            }

            .image-preview {
                height: 240px;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Small Mobile
        |--------------------------------------------------------------------------
        */
        @media (max-width: 520px) {

            .page-content {
                padding: 18px 14px;
            }

            .admin-card {
                padding: 15px;
                border-radius: 11px;
            }

            .page-header h2 {
                font-size: 21px;
            }

            .page-header p {
                font-size: 13px;
            }

            .image-preview {
                height: 220px;
            }

            .image-actions {
                flex-direction: column;
            }

            .image-actions a {
                width: 100%;
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

            <div class="page-header">

                <h2>Product Images</h2>

                <p>
                    Manage product images, primary image and image order.
                </p>

            </div>


            <?php if ($message): ?>

                <div class="alert alert-success">
                    <?= htmlspecialchars($message) ?>
                </div>

            <?php endif; ?>


            <?php if ($error): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <div class="image-layout">


                <!--
                |--------------------------------------------------------------------------
                | Upload Form
                |--------------------------------------------------------------------------
                -->

                <div class="admin-card">

                    <h5>Upload Images</h5>

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                    >

                        <div class="form-group">

                            <label for="product_id">
                                Product
                            </label>

                            <select
                                name="product_id"
                                id="product_id"
                                class="form-select"
                                onchange="changeProduct(this.value)"
                                required
                            >

                                <option value="">
                                    Select Product
                                </option>

                                <?php foreach ($products as $product): ?>

                                    <option
                                        value="<?= (int) $product["id"] ?>"
                                        <?= $selectedProductId === (int) $product["id"] ? "selected" : "" ?>
                                    >

                                        <?= htmlspecialchars($product["name"]) ?>

                                        <?php if (!empty($product["sku"])): ?>
                                            — <?= htmlspecialchars($product["sku"]) ?>
                                        <?php endif; ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="form-group">

                            <label for="images">
                                Product Images
                            </label>

                            <input
                                type="file"
                                name="images[]"
                                id="images"
                                class="form-control"
                                accept=".jpg,.jpeg,.png,.webp"
                                multiple
                                required
                            >

                            <div class="upload-info">
                                JPG, JPEG, PNG or WEBP.
                                Maximum 5MB per image.
                            </div>

                        </div>


                        <div class="form-group">

                            <label for="alt_text">
                                Alt Text
                            </label>

                            <input
                                type="text"
                                name="alt_text"
                                id="alt_text"
                                class="form-control"
                                placeholder="Example: Black sports jacket"
                                value="<?= htmlspecialchars($_POST["alt_text"] ?? "") ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label for="sort_order">
                                Starting Sort Order
                            </label>

                            <input
                                type="number"
                                name="sort_order"
                                id="sort_order"
                                class="form-control"
                                value="<?= (int) ($_POST["sort_order"] ?? 0) ?>"
                                min="0"
                            >

                        </div>


                        <button
                            type="submit"
                            class="btn-upload"
                        >
                            Upload Images
                        </button>

                    </form>

                </div>


                <!--
                |--------------------------------------------------------------------------
                | Images Area
                |--------------------------------------------------------------------------
                -->

                <div class="admin-card">

                    <h5>Product Images</h5>


                    <?php if ($selectedProduct): ?>

                        <div class="product-info">

                            <strong>
                                <?= htmlspecialchars($selectedProduct["name"]) ?>
                            </strong>

                            <?php if (!empty($selectedProduct["sku"])): ?>

                                <span>
                                    SKU:
                                    <?= htmlspecialchars($selectedProduct["sku"]) ?>
                                </span>

                            <?php endif; ?>

                        </div>


                        <?php if (!empty($productImages)): ?>

                            <div class="images-grid">

                                <?php foreach ($productImages as $image): ?>

                                    <div class="image-card">

                                        <div class="image-preview">

                                            <img
                                                src="../<?= htmlspecialchars($image["image"]) ?>"
                                                alt="<?= htmlspecialchars($image["alt_text"] ?: $selectedProduct["name"]) ?>"
                                            >

                                            <?php if ((int) $image["is_primary"] === 1): ?>

                                                <span class="primary-badge">
                                                    Primary
                                                </span>

                                            <?php endif; ?>

                                        </div>


                                        <div class="image-card-body">

                                            <div class="image-alt">

                                                <strong>Alt:</strong>

                                                <?= htmlspecialchars(
                                                    $image["alt_text"] ?: "No alt text"
                                                ) ?>

                                            </div>


                                            <div class="image-sort">

                                                Sort Order:
                                                <?= (int) $image["sort_order"] ?>

                                            </div>


                                            <div class="image-actions">

                                                <?php if ((int) $image["is_primary"] !== 1): ?>

                                                    <a
                                                        href="product-images.php?primary=<?= (int) $image["id"] ?>&product=<?= (int) $selectedProductId ?>"
                                                        class="btn-primary-image"
                                                        onclick="return confirm('Set this image as primary?');"
                                                    >
                                                        Set Primary
                                                    </a>

                                                <?php else: ?>

                                                    <a
                                                        href="javascript:void(0)"
                                                        class="btn-primary-image"
                                                        style="opacity:0.6; pointer-events:none;"
                                                    >
                                                        Primary
                                                    </a>

                                                <?php endif; ?>


                                                <a
                                                    href="product-images.php?delete=<?= (int) $image["id"] ?>"
                                                    class="btn-delete-image"
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

                            <div class="empty-state">

                                <h5>
                                    No Images Yet
                                </h5>

                                <p>
                                    This product does not have any images.
                                    Use the upload form to add images.
                                </p>

                            </div>

                        <?php endif; ?>


                    <?php else: ?>

                        <div class="empty-state">

                            <h5>
                                Select a Product
                            </h5>

                            <p>
                                Select a product from the upload form
                                to view and manage its images.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </main>

        <?php include "../includes/footer.php"; ?>

    </div>

</div>


<script src="assets/js/admin.js"></script>

<script>

function changeProduct(productId) {

    if (productId) {

        window.location.href =
            "product-images.php?product=" +
            encodeURIComponent(productId);

    } else {

        window.location.href =
            "product-images.php";
    }
}

</script>

</body>

</html>