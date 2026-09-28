<?php

require_once "../config/database.php";
require_once "../includes/auth.php";

$message = "";
$messageType = "";

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function orderStatusClass($status)
{
    switch ($status) {
        case "Pending":
            return "status-pending";

        case "Processing":
            return "status-processing";

        case "Shipped":
            return "status-shipped";

        case "Delivered":
            return "status-delivered";

        case "Cancelled":
            return "status-cancelled";

        default:
            return "status-pending";
    }
}

function paymentStatusClass($status)
{
    switch ($status) {
        case "Paid":
            return "payment-paid";

        case "Failed":
            return "payment-failed";

        case "Refunded":
            return "payment-refunded";

        default:
            return "payment-pending";
    }
}

function orderImagePath($image)
{
    if (!$image) {
        return "";
    }

    return "../" . ltrim($image, "/");
}


/*
|--------------------------------------------------------------------------
| Order Status Update
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    $orderId = (int)($_POST["order_id"] ?? 0);
    $newStatus = trim($_POST["order_status"] ?? "");

    $allowedStatuses = [
        "Pending",
        "Processing",
        "Shipped",
        "Delivered",
        "Cancelled"
    ];

    if ($orderId <= 0) {

        $message = "Invalid order.";
        $messageType = "danger";

    } elseif (!in_array($newStatus, $allowedStatuses, true)) {

        $message = "Invalid order status.";
        $messageType = "danger";

    } else {

        try {

            $pdo->beginTransaction();

            $statusStmt = $pdo->prepare("
                SELECT order_status
                FROM orders
                WHERE id = ?
                LIMIT 1
            ");

            $statusStmt->execute([$orderId]);

            $order = $statusStmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new Exception("Order not found.");
            }

            $oldStatus = $order["order_status"];

            $updateStmt = $pdo->prepare("
                UPDATE orders
                SET order_status = ?
                WHERE id = ?
            ");

            $updateStmt->execute([
                $newStatus,
                $orderId
            ]);

            if ($oldStatus !== $newStatus) {

                $historyStmt = $pdo->prepare("
                    INSERT INTO order_status_history
                    (
                        order_id,
                        old_status,
                        new_status
                    )
                    VALUES (?, ?, ?)
                ");

                $historyStmt->execute([
                    $orderId,
                    $oldStatus,
                    $newStatus
                ]);
            }

            $pdo->commit();

            $message = "Order status updated successfully.";
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
$statusFilter = trim($_GET["status"] ?? "");


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalOrders = (int)$pdo->query("
    SELECT COUNT(*)
    FROM orders
")->fetchColumn();

$pendingOrders = (int)$pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE order_status = 'Pending'
")->fetchColumn();

$processingOrders = (int)$pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE order_status = 'Processing'
")->fetchColumn();

$deliveredOrders = (int)$pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE order_status = 'Delivered'
")->fetchColumn();

$totalRevenue = (float)$pdo->query("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM orders
    WHERE order_status != 'Cancelled'
")->fetchColumn();


/*
|--------------------------------------------------------------------------
| Orders Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        o.id,
        o.order_number,
        o.customer_name,
        o.customer_email,
        o.customer_phone,
        o.shipping_address,
        o.shipping_city,
        o.shipping_postal_code,
        o.subtotal,
        o.shipping_fee,
        o.discount,
        o.total_amount,
        o.payment_method,
        o.payment_status,
        o.order_status,
        o.notes,
        o.created_at,
        o.updated_at,

        COUNT(oi.id) AS item_count

    FROM orders o

    LEFT JOIN order_items oi
        ON oi.order_id = o.id

    WHERE 1=1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= "
        AND (
            o.order_number LIKE ?
            OR o.customer_name LIKE ?
            OR o.customer_email LIKE ?
            OR o.customer_phone LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($statusFilter !== "") {

    $allowedStatuses = [
        "Pending",
        "Processing",
        "Shipped",
        "Delivered",
        "Cancelled"
    ];

    if (in_array($statusFilter, $allowedStatuses, true)) {

        $sql .= "
            AND o.order_status = ?
        ";

        $params[] = $statusFilter;
    }
}


/*
|--------------------------------------------------------------------------
| Group + Sort
|--------------------------------------------------------------------------
*/

$sql .= "
    GROUP BY
        o.id,
        o.order_number,
        o.customer_name,
        o.customer_email,
        o.customer_phone,
        o.shipping_address,
        o.shipping_city,
        o.shipping_postal_code,
        o.subtotal,
        o.shipping_fee,
        o.discount,
        o.total_amount,
        o.payment_method,
        o.payment_status,
        o.order_status,
        o.notes,
        o.created_at,
        o.updated_at

    ORDER BY o.id DESC
";


$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Fetch Order Details Data
|--------------------------------------------------------------------------
|
| We load all order details once and keep them grouped by order ID.
| This allows the details modal to work without another request.
|
|--------------------------------------------------------------------------
*/

$orderDetails = [];

if (!empty($orders)) {

    $orderIds = array_column($orders, "id");

    $placeholders = implode(
        ",",
        array_fill(0, count($orderIds), "?")
    );

    $detailsSql = "
        SELECT
            oi.id,
            oi.order_id,
            oi.product_id,
            oi.variant_id,
            oi.product_name,
            oi.sku,
            oi.variant_details,
            oi.quantity,
            oi.price,
            oi.subtotal,

            p.name AS current_product_name,

            pv.variant_name,
            pv.size,
            pv.color,
            pv.hand,
            pv.type AS variant_type,

            pi.image AS product_image

        FROM order_items oi

        LEFT JOIN products p
            ON p.id = oi.product_id

        LEFT JOIN product_variants pv
            ON pv.id = oi.variant_id

        LEFT JOIN product_images pi
            ON pi.product_id = oi.product_id
            AND pi.is_primary = 1

        WHERE oi.order_id IN ($placeholders)

        ORDER BY oi.id ASC
    ";

    $detailsStmt = $pdo->prepare($detailsSql);
    $detailsStmt->execute($orderIds);

    $items = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {

        $orderId = (int)$item["order_id"];

        if (!isset($orderDetails[$orderId])) {
            $orderDetails[$orderId] = [];
        }

        $orderDetails[$orderId][] = $item;
    }
}


/*
|--------------------------------------------------------------------------
| Status History
|--------------------------------------------------------------------------
*/

$orderHistory = [];

if (!empty($orders)) {

    $orderIds = array_column($orders, "id");

    $placeholders = implode(
        ",",
        array_fill(0, count($orderIds), "?")
    );

    $historyStmt = $pdo->prepare("
        SELECT
            id,
            order_id,
            old_status,
            new_status,
            note,
            created_at
        FROM order_status_history
        WHERE order_id IN ($placeholders)
        ORDER BY id DESC
    ");

    $historyStmt->execute($orderIds);

    $historyRows = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($historyRows as $history) {

        $orderId = (int)$history["order_id"];

        if (!isset($orderHistory[$orderId])) {
            $orderHistory[$orderId] = [];
        }

        $orderHistory[$orderId][] = $history;
    }
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

    <title>Orders | OSCAR CMS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="assets/css/admin.css"
    >

    <style>

        .orders-page {
            width: 100%;
        }

        .orders-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .orders-title h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #111827;
        }

        .orders-title p {
            margin: 6px 0 0;
            color: #6b7280;
        }


        /* =====================================================
           STATISTICS
        ===================================================== */

        .orders-stat-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .orders-stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
            min-width: 0;
        }

        .orders-stat-label {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .orders-stat-value {
            color: #111827;
            font-size: 25px;
            font-weight: 700;
            line-height: 1.1;
        }


        /* =====================================================
           FILTERS
        ===================================================== */

        .orders-filters {
            background: #fff;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 22px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
        }

        .orders-filters form {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) 190px auto auto;
            gap: 10px;
            align-items: center;
        }

        .orders-filters .form-control,
        .orders-filters .form-select {
            min-height: 44px;
            border-color: #e5e7eb;
        }


        /* =====================================================
           TABLE
        ===================================================== */

        .orders-table-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
            overflow: hidden;
        }

        .orders-table-head {
            padding: 18px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .orders-table-head h5 {
            margin: 0;
            color: #111827;
            font-weight: 700;
        }

        .orders-count {
            color: #6b7280;
            font-size: 13px;
        }

        .orders-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .orders-table {
            width: 100%;
            min-width: 1200px;
            margin: 0;
            border-collapse: collapse;
        }

        .orders-table th {
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

        .orders-table td {
            padding: 14px 16px;
            color: #374151;
            border-bottom: 1px solid #f0f1f3;
            vertical-align: middle;
        }

        .orders-table tbody tr:last-child td {
            border-bottom: 0;
        }


        /* =====================================================
           ORDER INFO
        ===================================================== */

        .order-number {
            color: #111827;
            font-weight: 700;
            white-space: nowrap;
        }

        .customer-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 180px;
        }

        .customer-name {
            color: #111827;
            font-weight: 600;
        }

        .customer-info small {
            color: #6b7280;
            font-size: 12px;
        }

        .order-total {
            color: #111827;
            font-weight: 700;
            white-space: nowrap;
        }

        .order-items-count {
            color: #6b7280;
            white-space: nowrap;
        }


        /* =====================================================
           BADGES
        ===================================================== */

        .status-badge,
        .payment-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-pending {
            background: #fff7ed;
            color: #c2410c;
        }

        .status-processing {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .status-shipped {
            background: #f5f3ff;
            color: #6d28d9;
        }

        .status-delivered {
            background: #ecfdf5;
            color: #047857;
        }

        .status-cancelled {
            background: #fef2f2;
            color: #b91c1c;
        }

        .payment-paid {
            background: #ecfdf5;
            color: #047857;
        }

        .payment-pending {
            background: #fff7ed;
            color: #c2410c;
        }

        .payment-failed {
            background: #fef2f2;
            color: #b91c1c;
        }

        .payment-refunded {
            background: #f5f3ff;
            color: #6d28d9;
        }


        /* =====================================================
           ACTIONS
        ===================================================== */

        .order-actions {
            display: flex;
            align-items: center;
            gap: 7px;
            white-space: nowrap;
        }

        .details-btn {
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #111827;
            border-radius: 8px;
            padding: 7px 11px;
            font-size: 12px;
            font-weight: 600;
        }

        .details-btn:hover {
            background: #f9fafb;
        }

        .status-form {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-select {
            min-width: 125px;
            height: 36px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            background: #fff;
            padding: 0 8px;
            font-size: 12px;
        }

        .update-btn {
            height: 36px;
            border: 0;
            background: #111827;
            color: #fff;
            border-radius: 7px;
            padding: 0 10px;
            font-size: 12px;
            font-weight: 600;
        }

        .update-btn:hover {
            background: #1f2937;
        }


        /* =====================================================
           EMPTY
        ===================================================== */

        .empty-orders {
            padding: 55px 20px;
            text-align: center;
            color: #6b7280;
        }

        .empty-orders strong {
            display: block;
            color: #111827;
            font-size: 16px;
            margin-bottom: 6px;
        }


        /* =====================================================
           ORDER DETAILS MODAL
        ===================================================== */

        .order-details-modal .modal-dialog {
            max-width: 1050px;
        }

        .order-details-modal .modal-content {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
        }

        .order-details-modal .modal-header {
            padding: 18px 22px;
            border-bottom: 1px solid #e5e7eb;
        }

        .order-details-modal .modal-title {
            color: #111827;
            font-weight: 700;
        }

        .order-details-modal .modal-body {
            padding: 22px;
            background: #f9fafb;
        }

        .detail-section {
            background: #fff;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 16px;
            border: 1px solid #e5e7eb;
        }

        .detail-section:last-child {
            margin-bottom: 0;
        }

        .detail-section-title {
            color: #111827;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .customer-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .detail-label {
            display: block;
            color: #9ca3af;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 4px;
        }

        .detail-value {
            color: #111827;
            font-size: 13px;
            line-height: 1.5;
        }

        .detail-address {
            white-space: pre-line;
        }


        /* =====================================================
           ITEMS
        ===================================================== */

        .detail-items-wrap {
            overflow-x: auto;
        }

        .detail-items-table {
            width: 100%;
            min-width: 700px;
            border-collapse: collapse;
        }

        .detail-items-table th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 11px;
            text-transform: uppercase;
            padding: 11px;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        .detail-items-table td {
            padding: 12px 11px;
            border-bottom: 1px solid #f0f1f3;
            vertical-align: middle;
        }

        .detail-item-product {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 220px;
        }

        .detail-item-image {
            width: 46px;
            height: 46px;
            flex: 0 0 46px;
            border-radius: 8px;
            overflow: hidden;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 10px;
        }

        .detail-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .detail-item-name {
            color: #111827;
            font-size: 13px;
            font-weight: 600;
        }

        .detail-item-sku {
            color: #9ca3af;
            font-size: 11px;
            margin-top: 2px;
        }

        .variant-text {
            color: #6b7280;
            font-size: 12px;
            line-height: 1.5;
        }


        /* =====================================================
           TOTALS
        ===================================================== */

        .order-summary {
            margin-left: auto;
            width: 100%;
            max-width: 360px;
        }

        .summary-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 7px 0;
            color: #6b7280;
            font-size: 13px;
        }

        .summary-row.total {
            margin-top: 7px;
            padding-top: 13px;
            border-top: 1px solid #e5e7eb;
            color: #111827;
            font-size: 16px;
            font-weight: 700;
        }


        /* =====================================================
           HISTORY
        ===================================================== */

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .history-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 11px 13px;
            background: #f9fafb;
            border-radius: 9px;
        }

        .history-status {
            color: #111827;
            font-size: 12px;
            font-weight: 600;
        }

        .history-date {
            color: #9ca3af;
            font-size: 11px;
            white-space: nowrap;
        }

        .no-history {
            color: #9ca3af;
            font-size: 13px;
        }


        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 1150px) {

            .orders-stat-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .orders-filters form {
                grid-template-columns: 1fr 1fr;
            }

        }

        @media (max-width: 700px) {

            .orders-header {
                flex-direction: column;
                margin-bottom: 18px;
            }

            .orders-title h1 {
                font-size: 23px;
            }

            .orders-stat-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .orders-stat-card {
                padding: 15px;
            }

            .orders-stat-value {
                font-size: 21px;
            }

            .orders-filters {
                padding: 14px;
            }

            .orders-filters form {
                grid-template-columns: 1fr;
            }

            .orders-table-head {
                padding: 15px;
                align-items: flex-start;
                flex-direction: column;
            }

            .order-details-modal .modal-body {
                padding: 12px;
            }

            .customer-detail-grid {
                grid-template-columns: 1fr;
            }

            .detail-section {
                padding: 14px;
            }

        }

        @media (max-width: 430px) {

            .orders-stat-grid {
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

            <div class="orders-page">


                <!-- =================================================
                     HEADER
                ================================================== -->

                <div class="orders-header">

                    <div class="orders-title">

                        <h1>Orders</h1>

                        <p>
                            Manage customer orders, payments and order status.
                        </p>

                    </div>

                </div>


                <!-- =================================================
                     MESSAGE
                ================================================== -->

                <?php if ($message !== ""): ?>

                    <div
                        class="alert alert-<?php echo e($messageType); ?> alert-dismissible fade show"
                        role="alert"
                    >

                        <?php echo e($message); ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Close"
                        ></button>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     STATISTICS
                ================================================== -->

                <div class="orders-stat-grid">

                    <div class="orders-stat-card">

                        <div class="orders-stat-label">
                            Total Orders
                        </div>

                        <div class="orders-stat-value">
                            <?php echo number_format($totalOrders); ?>
                        </div>

                    </div>


                    <div class="orders-stat-card">

                        <div class="orders-stat-label">
                            Pending
                        </div>

                        <div class="orders-stat-value">
                            <?php echo number_format($pendingOrders); ?>
                        </div>

                    </div>


                    <div class="orders-stat-card">

                        <div class="orders-stat-label">
                            Processing
                        </div>

                        <div class="orders-stat-value">
                            <?php echo number_format($processingOrders); ?>
                        </div>

                    </div>


                    <div class="orders-stat-card">

                        <div class="orders-stat-label">
                            Delivered
                        </div>

                        <div class="orders-stat-value">
                            <?php echo number_format($deliveredOrders); ?>
                        </div>

                    </div>


                    <div class="orders-stat-card">

                        <div class="orders-stat-label">
                            Revenue
                        </div>

                        <div class="orders-stat-value">
                            Rs. <?php echo number_format($totalRevenue, 2); ?>
                        </div>

                    </div>

                </div>


                <!-- =================================================
                     FILTERS
                ================================================== -->

                <div class="orders-filters">

                    <form method="GET" action="orders.php">

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="Search order, customer, email..."
                            value="<?php echo e($search); ?>"
                        >


                        <select
                            name="status"
                            class="form-select"
                        >

                            <option value="">
                                All Statuses
                            </option>

                            <option
                                value="Pending"
                                <?php echo $statusFilter === "Pending" ? "selected" : ""; ?>
                            >
                                Pending
                            </option>

                            <option
                                value="Processing"
                                <?php echo $statusFilter === "Processing" ? "selected" : ""; ?>
                            >
                                Processing
                            </option>

                            <option
                                value="Shipped"
                                <?php echo $statusFilter === "Shipped" ? "selected" : ""; ?>
                            >
                                Shipped
                            </option>

                            <option
                                value="Delivered"
                                <?php echo $statusFilter === "Delivered" ? "selected" : ""; ?>
                            >
                                Delivered
                            </option>

                            <option
                                value="Cancelled"
                                <?php echo $statusFilter === "Cancelled" ? "selected" : ""; ?>
                            >
                                Cancelled
                            </option>

                        </select>


                        <button
                            type="submit"
                            class="btn btn-dark"
                        >
                            Search
                        </button>


                        <a
                            href="orders.php"
                            class="btn btn-outline-secondary"
                        >
                            Reset
                        </a>

                    </form>

                </div>


                <!-- =================================================
                     ORDERS TABLE
                ================================================== -->

                <div class="orders-table-card">


                    <div class="orders-table-head">

                        <h5>
                            All Orders
                        </h5>

                        <div class="orders-count">

                            <?php echo number_format(count($orders)); ?>

                            records

                        </div>

                    </div>


                    <div class="orders-table-wrap">

                        <table class="orders-table">

                            <thead>

                                <tr>

                                    <th>
                                        Order
                                    </th>

                                    <th>
                                        Customer
                                    </th>

                                    <th>
                                        Items
                                    </th>

                                    <th>
                                        Total
                                    </th>

                                    <th>
                                        Payment
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Actions
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php if (empty($orders)): ?>

                                <tr>

                                    <td colspan="8">

                                        <div class="empty-orders">

                                            <strong>
                                                No orders found
                                            </strong>

                                            Orders will appear here once customers place them.

                                        </div>

                                    </td>

                                </tr>


                            <?php else: ?>


                                <?php foreach ($orders as $order): ?>

                                    <?php

                                    $orderId = (int)$order["id"];

                                    $itemsForOrder =
                                        $orderDetails[$orderId] ?? [];

                                    $historyForOrder =
                                        $orderHistory[$orderId] ?? [];

                                    ?>

                                    <tr>


                                        <!-- ORDER -->

                                        <td>

                                            <div class="order-number">

                                                #
                                                <?php echo e($order["order_number"]); ?>

                                            </div>

                                        </td>


                                        <!-- CUSTOMER -->

                                        <td>

                                            <div class="customer-info">

                                                <span class="customer-name">

                                                    <?php
                                                    echo e(
                                                        $order["customer_name"]
                                                    );
                                                    ?>

                                                </span>


                                                <?php if (!empty($order["customer_email"])): ?>

                                                    <small>
                                                        <?php
                                                        echo e(
                                                            $order["customer_email"]
                                                        );
                                                        ?>
                                                    </small>

                                                <?php endif; ?>


                                                <?php if (!empty($order["customer_phone"])): ?>

                                                    <small>
                                                        <?php
                                                        echo e(
                                                            $order["customer_phone"]
                                                        );
                                                        ?>
                                                    </small>

                                                <?php endif; ?>

                                            </div>

                                        </td>


                                        <!-- ITEMS -->

                                        <td>

                                            <span class="order-items-count">

                                                <?php
                                                echo number_format(
                                                    (int)$order["item_count"]
                                                );
                                                ?>

                                                item<?php
                                                echo ((int)$order["item_count"] === 1)
                                                    ? ""
                                                    : "s";
                                                ?>

                                            </span>

                                        </td>


                                        <!-- TOTAL -->

                                        <td>

                                            <span class="order-total">

                                                Rs.
                                                <?php
                                                echo number_format(
                                                    (float)$order["total_amount"],
                                                    2
                                                );
                                                ?>

                                            </span>

                                        </td>


                                        <!-- PAYMENT -->

                                        <td>

                                            <span
                                                class="payment-badge
                                                <?php
                                                echo paymentStatusClass(
                                                    $order["payment_status"]
                                                );
                                                ?>"
                                            >

                                                <?php
                                                echo e(
                                                    $order["payment_status"]
                                                );
                                                ?>

                                            </span>

                                            <div
                                                style="
                                                    color:#6b7280;
                                                    font-size:11px;
                                                    margin-top:5px;
                                                "
                                            >

                                                <?php
                                                echo e(
                                                    $order["payment_method"]
                                                );
                                                ?>

                                            </div>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <span
                                                class="status-badge
                                                <?php
                                                echo orderStatusClass(
                                                    $order["order_status"]
                                                );
                                                ?>"
                                            >

                                                <?php
                                                echo e(
                                                    $order["order_status"]
                                                );
                                                ?>

                                            </span>

                                        </td>


                                        <!-- DATE -->

                                        <td>

                                            <div style="white-space:nowrap;">

                                                <?php
                                                echo date(
                                                    "d M Y",
                                                    strtotime(
                                                        $order["created_at"]
                                                    )
                                                );
                                                ?>

                                            </div>

                                            <div
                                                style="
                                                    color:#9ca3af;
                                                    font-size:11px;
                                                    margin-top:3px;
                                                "
                                            >

                                                <?php
                                                echo date(
                                                    "h:i A",
                                                    strtotime(
                                                        $order["created_at"]
                                                    )
                                                );
                                                ?>

                                            </div>

                                        </td>


                                        <!-- ACTIONS -->

                                        <td>

                                            <div class="order-actions">


                                                <!-- DETAILS BUTTON -->

                                                <button
                                                    type="button"
                                                    class="details-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#orderDetailsModal<?php echo $orderId; ?>"
                                                >
                                                    View Details
                                                </button>


                                                <!-- STATUS UPDATE -->

                                                <form
                                                    method="POST"
                                                    class="status-form"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="order_id"
                                                        value="<?php echo $orderId; ?>"
                                                    >


                                                    <select
                                                        name="order_status"
                                                        class="status-select"
                                                    >

                                                        <?php
                                                        $statuses = [
                                                            "Pending",
                                                            "Processing",
                                                            "Shipped",
                                                            "Delivered",
                                                            "Cancelled"
                                                        ];
                                                        ?>

                                                        <?php foreach ($statuses as $orderStatus): ?>

                                                            <option
                                                                value="<?php echo e($orderStatus); ?>"
                                                                <?php
                                                                echo $order["order_status"] === $orderStatus
                                                                    ? "selected"
                                                                    : "";
                                                                ?>
                                                            >

                                                                <?php
                                                                echo e($orderStatus);
                                                                ?>

                                                            </option>

                                                        <?php endforeach; ?>

                                                    </select>


                                                    <button
                                                        type="submit"
                                                        name="update_status"
                                                        value="1"
                                                        class="update-btn"
                                                    >
                                                        Update
                                                    </button>

                                                </form>

                                            </div>

                                        </td>

                                    </tr>


                                    <!-- =================================================
                                         ORDER DETAILS MODAL
                                    ================================================== -->

                                    <div
                                        class="modal fade order-details-modal"
                                        id="orderDetailsModal<?php echo $orderId; ?>"
                                        tabindex="-1"
                                        aria-hidden="true"
                                    >

                                        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">

                                            <div class="modal-content">


                                                <!-- MODAL HEADER -->

                                                <div class="modal-header">

                                                    <div>

                                                        <h5 class="modal-title">

                                                            Order #

                                                            <?php
                                                            echo e(
                                                                $order["order_number"]
                                                            );
                                                            ?>

                                                        </h5>

                                                        <div
                                                            style="
                                                                color:#9ca3af;
                                                                font-size:12px;
                                                                margin-top:4px;
                                                            "
                                                        >

                                                            <?php
                                                            echo date(
                                                                "d M Y, h:i A",
                                                                strtotime(
                                                                    $order["created_at"]
                                                                )
                                                            );
                                                            ?>

                                                        </div>

                                                    </div>


                                                    <button
                                                        type="button"
                                                        class="btn-close"
                                                        data-bs-dismiss="modal"
                                                        aria-label="Close"
                                                    ></button>

                                                </div>


                                                <!-- MODAL BODY -->

                                                <div class="modal-body">


                                                    <!-- CUSTOMER -->

                                                    <div class="detail-section">

                                                        <div class="detail-section-title">
                                                            Customer & Shipping
                                                        </div>


                                                        <div class="customer-detail-grid">


                                                            <div>

                                                                <span class="detail-label">
                                                                    Customer
                                                                </span>

                                                                <div class="detail-value">

                                                                    <?php
                                                                    echo e(
                                                                        $order["customer_name"]
                                                                    );
                                                                    ?>

                                                                </div>

                                                            </div>


                                                            <div>

                                                                <span class="detail-label">
                                                                    Phone
                                                                </span>

                                                                <div class="detail-value">

                                                                    <?php
                                                                    echo e(
                                                                        $order["customer_phone"] ?: "—"
                                                                    );
                                                                    ?>

                                                                </div>

                                                            </div>


                                                            <div>

                                                                <span class="detail-label">
                                                                    Email
                                                                </span>

                                                                <div class="detail-value">

                                                                    <?php
                                                                    echo e(
                                                                        $order["customer_email"] ?: "—"
                                                                    );
                                                                    ?>

                                                                </div>

                                                            </div>


                                                            <div>

                                                                <span class="detail-label">
                                                                    City
                                                                </span>

                                                                <div class="detail-value">

                                                                    <?php
                                                                    echo e(
                                                                        $order["shipping_city"] ?: "—"
                                                                    );
                                                                    ?>

                                                                </div>

                                                            </div>


                                                            <div>

                                                                <span class="detail-label">
                                                                    Postal Code
                                                                </span>

                                                                <div class="detail-value">

                                                                    <?php
                                                                    echo e(
                                                                        $order["shipping_postal_code"] ?: "—"
                                                                    );
                                                                    ?>

                                                                </div>

                                                            </div>


                                                            <div>

                                                                <span class="detail-label">
                                                                    Order Status
                                                                </span>

                                                                <div class="detail-value">

                                                                    <span
                                                                        class="status-badge
                                                                        <?php
                                                                        echo orderStatusClass(
                                                                            $order["order_status"]
                                                                        );
                                                                        ?>"
                                                                    >

                                                                        <?php
                                                                        echo e(
                                                                            $order["order_status"]
                                                                        );
                                                                        ?>

                                                                    </span>

                                                                </div>

                                                            </div>


                                                            <div style="grid-column:1 / -1;">

                                                                <span class="detail-label">
                                                                    Shipping Address
                                                                </span>

                                                                <div class="detail-value detail-address">

                                                                    <?php
                                                                    echo e(
                                                                        $order["shipping_address"]
                                                                    );
                                                                    ?>

                                                                </div>

                                                            </div>


                                                        </div>

                                                    </div>


                                                    <!-- ORDER ITEMS -->

                                                    <div class="detail-section">

                                                        <div class="detail-section-title">
                                                            Ordered Products
                                                        </div>


                                                        <div class="detail-items-wrap">

                                                            <table class="detail-items-table">

                                                                <thead>

                                                                    <tr>

                                                                        <th>
                                                                            Product
                                                                        </th>

                                                                        <th>
                                                                            Variant
                                                                        </th>

                                                                        <th>
                                                                            Qty
                                                                        </th>

                                                                        <th>
                                                                            Price
                                                                        </th>

                                                                        <th>
                                                                            Subtotal
                                                                        </th>

                                                                    </tr>

                                                                </thead>


                                                                <tbody>


                                                                <?php if (empty($itemsForOrder)): ?>

                                                                    <tr>

                                                                        <td
                                                                            colspan="5"
                                                                            style="
                                                                                text-align:center;
                                                                                color:#9ca3af;
                                                                            "
                                                                        >

                                                                            No order items found.

                                                                        </td>

                                                                    </tr>


                                                                <?php else: ?>


                                                                    <?php foreach ($itemsForOrder as $item): ?>

                                                                        <?php

                                                                        $itemImage =
                                                                            orderImagePath(
                                                                                $item["product_image"]
                                                                            );

                                                                        $displayProductName =
                                                                            $item["product_name"];

                                                                        if (
                                                                            !empty(
                                                                                $item["current_product_name"]
                                                                            )
                                                                        ) {
                                                                            $displayProductName =
                                                                                $item["current_product_name"];
                                                                        }

                                                                        $variantText =
                                                                            $item["variant_details"];

                                                                        if (
                                                                            empty(
                                                                                $variantText
                                                                            )
                                                                        ) {

                                                                            $variantParts = [];

                                                                            if (!empty($item["variant_name"])) {
                                                                                $variantParts[] =
                                                                                    $item["variant_name"];
                                                                            }

                                                                            if (!empty($item["size"])) {
                                                                                $variantParts[] =
                                                                                    "Size: " . $item["size"];
                                                                            }

                                                                            if (!empty($item["color"])) {
                                                                                $variantParts[] =
                                                                                    "Color: " . $item["color"];
                                                                            }

                                                                            if (!empty($item["hand"])) {
                                                                                $variantParts[] =
                                                                                    "Hand: " . $item["hand"];
                                                                            }

                                                                            if (!empty($item["variant_type"])) {
                                                                                $variantParts[] =
                                                                                    "Type: " . $item["variant_type"];
                                                                            }

                                                                            $variantText =
                                                                                !empty($variantParts)
                                                                                    ? implode(" • ", $variantParts)
                                                                                    : "—";
                                                                        }

                                                                        ?>


                                                                        <tr>


                                                                            <td>

                                                                                <div class="detail-item-product">

                                                                                    <div class="detail-item-image">

                                                                                        <?php if ($itemImage !== ""): ?>

                                                                                            <img
                                                                                                src="<?php echo e($itemImage); ?>"
                                                                                                alt="<?php echo e($displayProductName); ?>"
                                                                                            >

                                                                                        <?php else: ?>

                                                                                            No Image

                                                                                        <?php endif; ?>

                                                                                    </div>


                                                                                    <div>

                                                                                        <div class="detail-item-name">

                                                                                            <?php
                                                                                            echo e(
                                                                                                $displayProductName
                                                                                            );
                                                                                            ?>

                                                                                        </div>


                                                                                        <div class="detail-item-sku">

                                                                                            SKU:

                                                                                            <?php
                                                                                            echo e(
                                                                                                $item["sku"] ?: "—"
                                                                                            );
                                                                                            ?>

                                                                                        </div>

                                                                                    </div>

                                                                                </div>

                                                                            </td>


                                                                            <td>

                                                                                <div class="variant-text">

                                                                                    <?php
                                                                                    echo e(
                                                                                        $variantText
                                                                                    );
                                                                                    ?>

                                                                                </div>

                                                                            </td>


                                                                            <td>

                                                                                <?php
                                                                                echo number_format(
                                                                                    (int)$item["quantity"]
                                                                                );
                                                                                ?>

                                                                            </td>


                                                                            <td>

                                                                                Rs.

                                                                                <?php
                                                                                echo number_format(
                                                                                    (float)$item["price"],
                                                                                    2
                                                                                );
                                                                                ?>

                                                                            </td>


                                                                            <td>

                                                                                <strong>

                                                                                    Rs.

                                                                                    <?php
                                                                                    echo number_format(
                                                                                        (float)$item["subtotal"],
                                                                                        2
                                                                                    );
                                                                                    ?>

                                                                                </strong>

                                                                            </td>

                                                                        </tr>


                                                                    <?php endforeach; ?>


                                                                <?php endif; ?>


                                                                </tbody>

                                                            </table>

                                                        </div>

                                                    </div>


                                                    <!-- ORDER SUMMARY -->

                                                    <div class="detail-section">

                                                        <div class="detail-section-title">
                                                            Order Summary
                                                        </div>


                                                        <div class="order-summary">

                                                            <div class="summary-row">

                                                                <span>
                                                                    Subtotal
                                                                </span>

                                                                <span>

                                                                    Rs.

                                                                    <?php
                                                                    echo number_format(
                                                                        (float)$order["subtotal"],
                                                                        2
                                                                    );
                                                                    ?>

                                                                </span>

                                                            </div>


                                                            <div class="summary-row">

                                                                <span>
                                                                    Shipping
                                                                </span>

                                                                <span>

                                                                    Rs.

                                                                    <?php
                                                                    echo number_format(
                                                                        (float)$order["shipping_fee"],
                                                                        2
                                                                    );
                                                                    ?>

                                                                </span>

                                                            </div>


                                                            <div class="summary-row">

                                                                <span>
                                                                    Discount
                                                                </span>

                                                                <span>

                                                                    Rs.

                                                                    <?php
                                                                    echo number_format(
                                                                        (float)$order["discount"],
                                                                        2
                                                                    );
                                                                    ?>

                                                                </span>

                                                            </div>


                                                            <div class="summary-row">

                                                                <span>
                                                                    Payment
                                                                </span>

                                                                <span>

                                                                    <span
                                                                        class="payment-badge
                                                                        <?php
                                                                        echo paymentStatusClass(
                                                                            $order["payment_status"]
                                                                        );
                                                                        ?>"
                                                                    >

                                                                        <?php
                                                                        echo e(
                                                                            $order["payment_status"]
                                                                        );
                                                                        ?>

                                                                    </span>

                                                                </span>

                                                            </div>


                                                            <div class="summary-row">

                                                                <span>
                                                                    Method
                                                                </span>

                                                                <span>
                                                                    <?php
                                                                    echo e(
                                                                        $order["payment_method"]
                                                                    );
                                                                    ?>
                                                                </span>

                                                            </div>


                                                            <div class="summary-row total">

                                                                <span>
                                                                    Grand Total
                                                                </span>

                                                                <span>

                                                                    Rs.

                                                                    <?php
                                                                    echo number_format(
                                                                        (float)$order["total_amount"],
                                                                        2
                                                                    );
                                                                    ?>

                                                                </span>

                                                            </div>

                                                        </div>

                                                    </div>


                                                    <!-- STATUS HISTORY -->

                                                    <div class="detail-section">

                                                        <div class="detail-section-title">
                                                            Status History
                                                        </div>


                                                        <?php if (empty($historyForOrder)): ?>

                                                            <div class="no-history">
                                                                No status changes recorded yet.
                                                            </div>

                                                        <?php else: ?>

                                                            <div class="history-list">


                                                                <?php foreach ($historyForOrder as $history): ?>

                                                                    <div class="history-item">

                                                                        <div class="history-status">

                                                                            <?php if (!empty($history["old_status"])): ?>

                                                                                <?php
                                                                                echo e(
                                                                                    $history["old_status"]
                                                                                );
                                                                                ?>

                                                                                →

                                                                            <?php endif; ?>


                                                                            <?php
                                                                            echo e(
                                                                                $history["new_status"]
                                                                            );
                                                                            ?>

                                                                        </div>


                                                                        <div class="history-date">

                                                                            <?php
                                                                            echo date(
                                                                                "d M Y, h:i A",
                                                                                strtotime(
                                                                                    $history["created_at"]
                                                                                )
                                                                            );
                                                                            ?>

                                                                        </div>

                                                                    </div>

                                                                <?php endforeach; ?>


                                                            </div>

                                                        <?php endif; ?>

                                                    </div>


                                                    <!-- NOTES -->

                                                    <?php if (!empty($order["notes"])): ?>

                                                        <div class="detail-section">

                                                            <div class="detail-section-title">
                                                                Order Notes
                                                            </div>

                                                            <div
                                                                class="detail-value"
                                                                style="white-space:pre-line;"
                                                            >

                                                                <?php
                                                                echo e(
                                                                    $order["notes"]
                                                                );
                                                                ?>

                                                            </div>

                                                        </div>

                                                    <?php endif; ?>


                                                </div>


                                                <!-- MODAL FOOTER -->

                                                <div class="modal-footer">

                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary"
                                                        data-bs-dismiss="modal"
                                                    >
                                                        Close
                                                    </button>

                                                </div>

                                            </div>

                                        </div>

                                    </div>


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


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

<script
    src="assets/js/admin.js"
></script>


</body>

</html>