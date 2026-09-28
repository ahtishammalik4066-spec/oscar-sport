<?php
require_once "../config/database.php";
require_once "../includes/auth.php";

$message = "";
$messageType = "";

/*
|--------------------------------------------------------------------------
| Stock Adjustment
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["adjust_stock"])) {

    $productId = (int)($_POST["product_id"] ?? 0);
    $variantId = (int)($_POST["variant_id"] ?? 0);
    $adjustment = (int)($_POST["adjustment"] ?? 0);
    $reason = trim($_POST["reason"] ?? "");

    if ($productId <= 0) {
        $message = "Invalid product.";
        $messageType = "danger";
    } elseif ($adjustment === 0) {
        $message = "Please enter a stock adjustment.";
        $messageType = "danger";
    } else {

        try {
            $pdo->beginTransaction();

            if ($variantId > 0) {

                $stmt = $pdo->prepare("
                    SELECT stock
                    FROM product_variants
                    WHERE id = ? AND product_id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$variantId, $productId]);
                $variant = $stmt->fetch();

                if (!$variant) {
                    throw new Exception("Variant not found.");
                }

                $newStock = (int)$variant["stock"] + $adjustment;

                if ($newStock < 0) {
                    throw new Exception("Stock cannot be less than zero.");
                }

                $stmt = $pdo->prepare("
                    UPDATE product_variants
                    SET stock = ?
                    WHERE id = ? AND product_id = ?
                ");
                $stmt->execute([$newStock, $variantId, $productId]);

            } else {

                $stmt = $pdo->prepare("
                    SELECT stock_quantity
                    FROM products
                    WHERE id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();

                if (!$product) {
                    throw new Exception("Product not found.");
                }

                $newStock = (int)$product["stock_quantity"] + $adjustment;

                if ($newStock < 0) {
                    throw new Exception("Stock cannot be less than zero.");
                }

                $stmt = $pdo->prepare("
                    UPDATE products
                    SET stock_quantity = ?
                    WHERE id = ?
                ");
                $stmt->execute([$newStock, $productId]);
            }

            /*
             * If this installation later gets a stock history table,
             * $reason can be stored there. For now the actual stock
             * quantity is updated directly.
             */

            $pdo->commit();

            $message = "Stock updated successfully.";
            $messageType = "success";

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = $e->getMessage();
            $messageType = "danger";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim($_GET["search"] ?? "");
$status = $_GET["status"] ?? "all";
$type = $_GET["type"] ?? "all";

/*
|--------------------------------------------------------------------------
| Stock Threshold
|--------------------------------------------------------------------------
*/
$lowStockLimit = 5;

/*
|--------------------------------------------------------------------------
| Products + Variants
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        p.id AS product_id,
        p.name AS product_name,
        p.sku AS product_sku,
        p.stock_quantity AS product_stock,
        p.status AS product_status,

        c.name AS category_name,

        pv.id AS variant_id,
        pv.variant_name,
        pv.sku AS variant_sku,
        pv.stock AS variant_stock,
        pv.size,
        pv.color,
        pv.hand,
        pv.type AS variant_type,
        pv.status AS variant_status,

        pi.image AS primary_image

    FROM products p

    LEFT JOIN categories c
        ON c.id = p.category_id

    LEFT JOIN product_variants pv
        ON pv.product_id = p.id

    LEFT JOIN product_images pi
        ON pi.product_id = p.id
        AND pi.is_primary = 1
";

$where = [];
$params = [];

if ($search !== "") {
    $where[] = "(
        p.name LIKE ?
        OR p.sku LIKE ?
        OR pv.variant_name LIKE ?
        OR pv.sku LIKE ?
        OR c.name LIKE ?
    )";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($status === "in_stock") {
    $where[] = "(
        CASE
            WHEN pv.id IS NOT NULL THEN pv.stock
            ELSE p.stock_quantity
        END
    ) > ?";
    $params[] = $lowStockLimit;

} elseif ($status === "low_stock") {
    $where[] = "(
        CASE
            WHEN pv.id IS NOT NULL THEN pv.stock
            ELSE p.stock_quantity
        END
    ) BETWEEN 1 AND ?";
    $params[] = $lowStockLimit;

} elseif ($status === "out_of_stock") {
    $where[] = "(
        CASE
            WHEN pv.id IS NOT NULL THEN pv.stock
            ELSE p.stock_quantity
        END
    ) <= 0";
}

if ($type === "products") {
    $where[] = "pv.id IS NULL";
}

if ($type === "variants") {
    $where[] = "pv.id IS NOT NULL";
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= "
    ORDER BY
        p.id DESC,
        pv.id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inventoryItems = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Inventory Statistics
|--------------------------------------------------------------------------
*/
$statsStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_products,
        COALESCE(SUM(stock_quantity), 0) AS total_product_stock
    FROM products
");
$productStats = $statsStmt->fetch();

$variantStatsStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_variants,
        COALESCE(SUM(stock), 0) AS total_variant_stock
    FROM product_variants
");
$variantStats = $variantStatsStmt->fetch();

$lowStockProductsStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM products
    WHERE stock_quantity BETWEEN 1 AND ?
");
$lowStockProductsStmt->execute([$lowStockLimit]);
$lowStockProducts = (int)$lowStockProductsStmt->fetchColumn();

$lowStockVariantsStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM product_variants
    WHERE stock BETWEEN 1 AND ?
");
$lowStockVariantsStmt->execute([$lowStockLimit]);
$lowStockVariants = (int)$lowStockVariantsStmt->fetchColumn();

$outOfStockProductsStmt = $pdo->query("
    SELECT COUNT(*)
    FROM products
    WHERE stock_quantity <= 0
");
$outOfStockProducts = (int)$outOfStockProductsStmt->fetchColumn();

$outOfStockVariantsStmt = $pdo->query("
    SELECT COUNT(*)
    FROM product_variants
    WHERE stock <= 0
");
$outOfStockVariants = (int)$outOfStockVariantsStmt->fetchColumn();

$totalItems =
    (int)$productStats["total_products"] +
    (int)$variantStats["total_variants"];

$totalStock =
    (int)$productStats["total_product_stock"] +
    (int)$variantStats["total_variant_stock"];

$totalLowStock =
    $lowStockProducts +
    $lowStockVariants;

$totalOutOfStock =
    $outOfStockProducts +
    $outOfStockVariants;

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function inventoryImagePath($image)
{
    if (!$image) {
        return "";
    }

    return "../" . ltrim($image, "/");
}

function inventoryStockClass($stock, $limit = 5)
{
    $stock = (int)$stock;

    if ($stock <= 0) {
        return "out";
    }

    if ($stock <= $limit) {
        return "low";
    }

    return "good";
}

function inventoryStockLabel($stock, $limit = 5)
{
    $stock = (int)$stock;

    if ($stock <= 0) {
        return "Out of Stock";
    }

    if ($stock <= $limit) {
        return "Low Stock";
    }

    return "In Stock";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Inventory | OSCAR CMS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/css/admin.css">

    <style>
        .inventory-page {
            width: 100%;
        }

        .inventory-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .inventory-title h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }

        .inventory-title p {
            margin: 6px 0 0;
            opacity: .75;
        }

        .stock-threshold {
            background: rgba(255, 207, 99, .12);
            border: 1px solid rgba(255, 207, 99, .25);
            color: #FFCF63;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            white-space: nowrap;
        }

        .inventory-stat-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .inventory-stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
            min-width: 0;
        }

        .inventory-stat-label {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .inventory-stat-value {
            color: #111827;
            font-size: 26px;
            font-weight: 700;
            line-height: 1.1;
        }

        .inventory-stat-note {
            color: #6b7280;
            font-size: 12px;
            margin-top: 8px;
        }

        .inventory-filters {
            background: #fff;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 22px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
        }

        .inventory-filters form {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) 180px 180px auto auto;
            gap: 10px;
            align-items: center;
        }

        .inventory-filters .form-control,
        .inventory-filters .form-select {
            min-height: 44px;
            border-color: #e5e7eb;
        }

        .inventory-table-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
            overflow: hidden;
        }

        .inventory-table-head {
            padding: 18px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .inventory-table-head h5 {
            margin: 0;
            color: #111827;
            font-weight: 700;
        }

        .inventory-count {
            color: #6b7280;
            font-size: 13px;
        }

        .inventory-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .inventory-table {
            width: 100%;
            min-width: 1050px;
            margin: 0;
            border-collapse: collapse;
        }

        .inventory-table th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            padding: 14px 16px;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        .inventory-table td {
            padding: 14px 16px;
            color: #374151;
            border-bottom: 1px solid #f0f1f3;
            vertical-align: middle;
        }

        .inventory-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .inventory-product {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 250px;
        }

        .inventory-image {
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            border-radius: 10px;
            overflow: hidden;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 12px;
        }

        .inventory-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .inventory-product-name {
            color: #111827;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .inventory-product-sku {
            color: #9ca3af;
            font-size: 12px;
        }

        .variant-info {
            min-width: 190px;
        }

        .variant-name {
            font-weight: 600;
            color: #111827;
            margin-bottom: 3px;
        }

        .variant-meta {
            color: #6b7280;
            font-size: 12px;
            line-height: 1.6;
        }

        .sku-text {
            font-size: 13px;
            color: #4b5563;
            font-family: monospace;
        }

        .stock-number {
            font-size: 17px;
            font-weight: 700;
            color: #111827;
        }

        .stock-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .stock-badge.good {
            background: #dcfce7;
            color: #166534;
        }

        .stock-badge.low {
            background: #fef3c7;
            color: #92400e;
        }

        .stock-badge.out {
            background: #fee2e2;
            color: #991b1b;
        }

        .stock-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .adjust-btn {
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #111827;
            border-radius: 8px;
            padding: 7px 11px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .adjust-btn:hover {
            background: #f9fafb;
        }

        .empty-inventory {
            padding: 55px 20px;
            text-align: center;
            color: #6b7280;
        }

        .empty-inventory strong {
            display: block;
            color: #111827;
            font-size: 16px;
            margin-bottom: 6px;
        }

        .modal-content {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
        }

        .modal-header {
            border-bottom: 1px solid #e5e7eb;
            padding: 18px 20px;
        }

        .modal-title {
            font-weight: 700;
            color: #111827;
        }

        .modal-body {
            padding: 20px;
        }

        .adjust-product-box {
            background: #f9fafb;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 18px;
        }

        .adjust-product-name {
            font-weight: 700;
            color: #111827;
        }

        .adjust-current {
            font-size: 13px;
            color: #6b7280;
            margin-top: 4px;
        }

        .adjust-help {
            color: #6b7280;
            font-size: 12px;
            margin-top: 6px;
        }

        @media (max-width: 1100px) {
            .inventory-stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .inventory-filters form {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 700px) {
            .inventory-header {
                flex-direction: column;
                margin-bottom: 18px;
            }

            .inventory-title h1 {
                font-size: 23px;
            }

            .stock-threshold {
                white-space: normal;
            }

            .inventory-stat-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .inventory-stat-card {
                padding: 15px;
            }

            .inventory-stat-value {
                font-size: 21px;
            }

            .inventory-filters {
                padding: 14px;
            }

            .inventory-filters form {
                grid-template-columns: 1fr;
            }

            .inventory-table-head {
                padding: 15px;
                align-items: flex-start;
                flex-direction: column;
            }

            .inventory-table th,
            .inventory-table td {
                padding: 12px;
            }
        }

        @media (max-width: 430px) {
            .inventory-stat-grid {
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

            <div class="inventory-page">

                <div class="inventory-header">

                    <div class="inventory-title">
                        <h1>Inventory</h1>
                        <p>Monitor product and variant stock from one place.</p>
                    </div>

                    <div class="stock-threshold">
                        Low stock threshold: <strong><?php echo $lowStockLimit; ?></strong> units
                    </div>

                </div>

                <?php if ($message !== ""): ?>

                    <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Close"
                        ></button>
                    </div>

                <?php endif; ?>

                <div class="inventory-stat-grid">

                    <div class="inventory-stat-card">
                        <div class="inventory-stat-label">Total Items</div>
                        <div class="inventory-stat-value">
                            <?php echo number_format($totalItems); ?>
                        </div>
                        <div class="inventory-stat-note">
                            Products + variants
                        </div>
                    </div>

                    <div class="inventory-stat-card">
                        <div class="inventory-stat-label">Total Stock</div>
                        <div class="inventory-stat-value">
                            <?php echo number_format($totalStock); ?>
                        </div>
                        <div class="inventory-stat-note">
                            Available units
                        </div>
                    </div>

                    <div class="inventory-stat-card">
                        <div class="inventory-stat-label">Low Stock</div>
                        <div class="inventory-stat-value">
                            <?php echo number_format($totalLowStock); ?>
                        </div>
                        <div class="inventory-stat-note">
                            <?php echo $lowStockLimit; ?> units or less
                        </div>
                    </div>

                    <div class="inventory-stat-card">
                        <div class="inventory-stat-label">Out of Stock</div>
                        <div class="inventory-stat-value">
                            <?php echo number_format($totalOutOfStock); ?>
                        </div>
                        <div class="inventory-stat-note">
                            Needs restocking
                        </div>
                    </div>

                </div>

                <div class="inventory-filters">

                    <form method="GET">

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="Search product, SKU, variant or category..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >

                        <select name="status" class="form-select">

                            <option value="all" <?php echo $status === "all" ? "selected" : ""; ?>>
                                All Stock
                            </option>

                            <option value="in_stock" <?php echo $status === "in_stock" ? "selected" : ""; ?>>
                                In Stock
                            </option>

                            <option value="low_stock" <?php echo $status === "low_stock" ? "selected" : ""; ?>>
                                Low Stock
                            </option>

                            <option value="out_of_stock" <?php echo $status === "out_of_stock" ? "selected" : ""; ?>>
                                Out of Stock
                            </option>

                        </select>

                        <select name="type" class="form-select">

                            <option value="all" <?php echo $type === "all" ? "selected" : ""; ?>>
                                Products + Variants
                            </option>

                            <option value="products" <?php echo $type === "products" ? "selected" : ""; ?>>
                                Products Only
                            </option>

                            <option value="variants" <?php echo $type === "variants" ? "selected" : ""; ?>>
                                Variants Only
                            </option>

                        </select>

                        <button type="submit" class="btn btn-dark">
                            Search
                        </button>

                        <a href="inventory.php" class="btn btn-outline-secondary">
                            Reset
                        </a>

                    </form>

                </div>

                <div class="inventory-table-card">

                    <div class="inventory-table-head">

                        <h5>Stock Overview</h5>

                        <div class="inventory-count">
                            <?php echo number_format(count($inventoryItems)); ?> records
                        </div>

                    </div>

                    <div class="inventory-table-wrap">

                        <table class="inventory-table">

                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Variant</th>
                                    <th>SKU</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php if (empty($inventoryItems)): ?>

                                <tr>
                                    <td colspan="7">

                                        <div class="empty-inventory">
                                            <strong>No inventory records found</strong>
                                            Try changing your search or filters.
                                        </div>

                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($inventoryItems as $item): ?>

                                    <?php
                                    $isVariant = !empty($item["variant_id"]);

                                    $stock = $isVariant
                                        ? (int)$item["variant_stock"]
                                        : (int)$item["product_stock"];

                                    $stockClass = inventoryStockClass($stock, $lowStockLimit);
                                    $stockLabel = inventoryStockLabel($stock, $lowStockLimit);

                                    $displaySku = $isVariant
                                        ? $item["variant_sku"]
                                        : $item["product_sku"];

                                    $imagePath = inventoryImagePath($item["primary_image"]);
                                    ?>

                                    <tr>

                                        <td>

                                            <div class="inventory-product">

                                                <div class="inventory-image">

                                                    <?php if ($imagePath !== ""): ?>

                                                        <img
                                                            src="<?php echo htmlspecialchars($imagePath); ?>"
                                                            alt="<?php echo htmlspecialchars($item["product_name"]); ?>"
                                                        >

                                                    <?php else: ?>

                                                        No Image

                                                    <?php endif; ?>

                                                </div>

                                                <div>

                                                    <div class="inventory-product-name">
                                                        <?php echo htmlspecialchars($item["product_name"]); ?>
                                                    </div>

                                                    <div class="inventory-product-sku">
                                                        <?php
                                                        echo $isVariant
                                                            ? "Variant inventory"
                                                            : "Base product inventory";
                                                        ?>
                                                    </div>

                                                </div>

                                            </div>

                                        </td>

                                        <td>
                                            <?php echo htmlspecialchars($item["category_name"] ?: "—"); ?>
                                        </td>

                                        <td>

                                            <?php if ($isVariant): ?>

                                                <div class="variant-info">

                                                    <div class="variant-name">
                                                        <?php echo htmlspecialchars($item["variant_name"]); ?>
                                                    </div>

                                                    <div class="variant-meta">

                                                        <?php
                                                        $variantParts = [];

                                                        if (!empty($item["size"])) {
                                                            $variantParts[] = "Size: " . $item["size"];
                                                        }

                                                        if (!empty($item["color"])) {
                                                            $variantParts[] = "Color: " . $item["color"];
                                                        }

                                                        if (!empty($item["hand"])) {
                                                            $variantParts[] = "Hand: " . $item["hand"];
                                                        }

                                                        if (!empty($item["variant_type"])) {
                                                            $variantParts[] = "Type: " . $item["variant_type"];
                                                        }

                                                        echo htmlspecialchars(
                                                            !empty($variantParts)
                                                                ? implode(" • ", $variantParts)
                                                                : "Variant"
                                                        );
                                                        ?>

                                                    </div>

                                                </div>

                                            <?php else: ?>

                                                <span class="text-muted">No variant</span>

                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <span class="sku-text">
                                                <?php echo htmlspecialchars($displaySku ?: "—"); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="stock-number">
                                                <?php echo number_format($stock); ?>
                                            </span>
                                        </td>

                                        <td>

                                            <span class="stock-badge <?php echo $stockClass; ?>">
                                                <span class="stock-dot"></span>
                                                <?php echo $stockLabel; ?>
                                            </span>

                                        </td>

                                        <td>

                                            <button
                                                type="button"
                                                class="adjust-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#adjustStockModal"
                                                data-product-id="<?php echo (int)$item["product_id"]; ?>"
                                                data-variant-id="<?php echo $isVariant ? (int)$item["variant_id"] : 0; ?>"
                                                data-product-name="<?php echo htmlspecialchars($item["product_name"], ENT_QUOTES); ?>"
                                                data-variant-name="<?php echo $isVariant ? htmlspecialchars($item["variant_name"], ENT_QUOTES) : ""; ?>"
                                                data-current-stock="<?php echo $stock; ?>"
                                            >
                                                Adjust Stock
                                            </button>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </main>

        <?php include "../includes/footer.php"; ?>

    </div>

</div>

<!-- Adjust Stock Modal -->
<div
    class="modal fade"
    id="adjustStockModal"
    tabindex="-1"
    aria-labelledby="adjustStockModalLabel"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="adjustStockModalLabel">
                    Adjust Stock
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>

            <form method="POST">

                <div class="modal-body">

                    <input type="hidden" name="adjust_stock" value="1">

                    <input type="hidden" name="product_id" id="adjustProductId">

                    <input type="hidden" name="variant_id" id="adjustVariantId">

                    <div class="adjust-product-box">

                        <div class="adjust-product-name" id="adjustProductName">
                            Product
                        </div>

                        <div class="adjust-current">
                            Current stock:
                            <strong id="adjustCurrentStock">0</strong>
                        </div>

                    </div>

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Stock Adjustment
                        </label>

                        <input
                            type="number"
                            name="adjustment"
                            class="form-control"
                            placeholder="Example: 10 or -5"
                            required
                        >

                        <div class="adjust-help">
                            Use a positive number to add stock and a negative number to remove stock.
                        </div>

                    </div>

                    <div class="mb-0">

                        <label class="form-label fw-semibold">
                            Reason <span class="text-muted">(optional)</span>
                        </label>

                        <input
                            type="text"
                            name="reason"
                            class="form-control"
                            placeholder="Example: New shipment"
                            maxlength="255"
                        >

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button type="submit" class="btn btn-dark">
                        Update Stock
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

<script src="assets/js/admin.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const modal = document.getElementById("adjustStockModal");

    if (!modal) {
        return;
    }

    modal.addEventListener("show.bs.modal", function (event) {

        const button = event.relatedTarget;

        if (!button) {
            return;
        }

        const productId =
            button.getAttribute("data-product-id") || "";

        const variantId =
            button.getAttribute("data-variant-id") || "0";

        const productName =
            button.getAttribute("data-product-name") || "Product";

        const variantName =
            button.getAttribute("data-variant-name") || "";

        const currentStock =
            button.getAttribute("data-current-stock") || "0";

        document.getElementById("adjustProductId").value = productId;
        document.getElementById("adjustVariantId").value = variantId;

        document.getElementById("adjustProductName").textContent =
            variantName
                ? productName + " — " + variantName
                : productName;

        document.getElementById("adjustCurrentStock").textContent =
            currentStock;

        const adjustmentInput =
            modal.querySelector('input[name="adjustment"]');

        if (adjustmentInput) {
            adjustmentInput.value = "";
            adjustmentInput.focus();
        }

    });

});
</script>

</body>
</html>
