<?php

require_once "../config/database.php";
require_once "../includes/auth.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value)
{
    return 'Rs. ' . number_format((float)$value, 2);
}

/*
|--------------------------------------------------------------------------
| AJAX - Return / Exchange Details
|--------------------------------------------------------------------------
*/

if (isset($_GET['return_details'])) {

    header('Content-Type: application/json; charset=utf-8');

    $returnId = (int)$_GET['return_details'];

    if ($returnId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid return ID.'
        ]);
        exit;
    }

    try {

        $stmt = $pdo->prepare("
            SELECT *
            FROM returns_exchanges
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$returnId]);

        $return = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$return) {
            echo json_encode([
                'success' => false,
                'message' => 'Return / Exchange request not found.'
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'return' => $return
        ]);

    } catch (PDOException $e) {

        echo json_encode([
            'success' => false,
            'message' => 'Unable to load request details.'
        ]);
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| UPDATE STATUS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_return'])) {

    $returnId = (int)($_POST['return_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $refundStatus = trim($_POST['refund_status'] ?? '');

    $allowedStatuses = [
        'Pending',
        'Approved',
        'Rejected',
        'Received',
        'Completed',
        'Cancelled'
    ];

    $allowedRefundStatuses = [
        'Not Applicable',
        'Pending',
        'Processing',
        'Refunded'
    ];

    if (
        $returnId > 0 &&
        in_array($status, $allowedStatuses, true) &&
        in_array($refundStatus, $allowedRefundStatuses, true)
    ) {

        try {

            $stmt = $pdo->prepare("
                UPDATE returns_exchanges
                SET
                    status = ?,
                    refund_status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $stmt->execute([
                $status,
                $refundStatus,
                $returnId
            ]);

            $_SESSION['return_success'] = 'Return / Exchange status updated successfully.';

        } catch (PDOException $e) {

            $_SESSION['return_error'] = 'Unable to update request.';
        }
    }

    header("Location: returns.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| DELETE REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_return'])) {

    $returnId = (int)($_POST['return_id'] ?? 0);

    if ($returnId > 0) {

        try {

            $stmt = $pdo->prepare("
                DELETE FROM returns_exchanges
                WHERE id = ?
            ");

            $stmt->execute([$returnId]);

            $_SESSION['return_success'] = 'Request deleted successfully.';

        } catch (PDOException $e) {

            $_SESSION['return_error'] = 'Unable to delete request.';
        }
    }

    header("Location: returns.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

$successMessage = $_SESSION['return_success'] ?? '';
$errorMessage = $_SESSION['return_error'] ?? '';

unset($_SESSION['return_success'], $_SESSION['return_error']);


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');


/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

$totalRequests = 0;
$pendingRequests = 0;
$approvedRequests = 0;
$completedRequests = 0;
$totalRefundAmount = 0;

try {

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM returns_exchanges
    ");

    $totalRequests = (int)$stmt->fetchColumn();


    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM returns_exchanges
        WHERE status = 'Pending'
    ");

    $pendingRequests = (int)$stmt->fetchColumn();


    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM returns_exchanges
        WHERE status = 'Approved'
    ");

    $approvedRequests = (int)$stmt->fetchColumn();


    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM returns_exchanges
        WHERE status = 'Completed'
    ");

    $completedRequests = (int)$stmt->fetchColumn();


    $stmt = $pdo->query("
        SELECT COALESCE(SUM(refund_amount), 0)
        FROM returns_exchanges
        WHERE refund_status = 'Refunded'
    ");

    $totalRefundAmount = (float)$stmt->fetchColumn();

} catch (PDOException $e) {

    $totalRequests = 0;
}


/*
|--------------------------------------------------------------------------
| MAIN QUERY
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

if ($search !== '') {

    $where[] = "
        (
            r.customer_name LIKE ?
            OR r.customer_email LIKE ?
            OR r.customer_phone LIKE ?
            OR r.product_name LIKE ?
            OR CAST(r.order_id AS CHAR) LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($typeFilter !== '') {

    $where[] = "r.request_type = ?";
    $params[] = $typeFilter;
}

if ($statusFilter !== '') {

    $where[] = "r.status = ?";
    $params[] = $statusFilter;
}

$whereSql = '';

if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}


$returns = [];

try {

    $sql = "
        SELECT
            r.*
        FROM returns_exchanges r
        $whereSql
        ORDER BY r.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $returns = [];
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

    <title>Returns & Exchanges | OSCAR CMS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="assets/css/admin.css"
    >

    <style>

        .returns-page {
            width: 100%;
        }

        .page-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-heading h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #111827;
        }

        .page-heading p {
            margin: 5px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
        }

        .stat-label {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .stat-value {
            color: #111827;
            font-size: 24px;
            font-weight: 700;
        }

        .filter-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 20px;
        }

        .returns-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .returns-card-header {
            padding: 18px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .returns-card-header h5 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .returns-table {
            width: 100%;
            min-width: 1150px;
            border-collapse: collapse;
        }

        .returns-table th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: 14px 16px;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        .returns-table td {
            padding: 15px 16px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            color: #374151;
            font-size: 14px;
        }

        .returns-table tr:last-child td {
            border-bottom: 0;
        }

        .order-number {
            font-weight: 700;
            color: #111827;
        }

        .customer-name {
            font-weight: 600;
            color: #111827;
        }

        .customer-contact {
            color: #6b7280;
            font-size: 12px;
            margin-top: 3px;
        }

        .product-name {
            font-weight: 600;
            color: #111827;
        }

        .variant-text {
            color: #6b7280;
            font-size: 12px;
            margin-top: 3px;
        }

        .type-badge,
        .status-badge,
        .refund-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .type-return {
            background: #fff7ed;
            color: #c2410c;
        }

        .type-exchange {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-rejected {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status-received {
            background: #e0e7ff;
            color: #4338ca;
        }

        .status-completed {
            background: #dcfce7;
            color: #166534;
        }

        .status-cancelled {
            background: #f3f4f6;
            color: #4b5563;
        }

        .refund-not {
            background: #f3f4f6;
            color: #4b5563;
        }

        .refund-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .refund-processing {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .refund-refunded {
            background: #dcfce7;
            color: #166534;
        }

        .action-buttons {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        .btn-view {
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #111827;
            padding: 7px 11px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
        }

        .btn-view:hover {
            background: #f9fafb;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-state h5 {
            color: #111827;
            margin-bottom: 7px;
        }

        .detail-box {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px;
            height: 100%;
        }

        .detail-box-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 10px;
        }

        .detail-main {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
        }

        .detail-muted {
            color: #6b7280;
            font-size: 13px;
            margin-top: 4px;
        }

        .detail-section {
            margin-top: 20px;
        }

        .detail-section-title {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 10px;
        }

        .detail-note {
            background: #f9fafb;
            border-radius: 8px;
            padding: 12px;
            color: #4b5563;
            font-size: 13px;
        }

        @media (max-width: 1200px) {

            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {

            .page-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .page-heading h1 {
                font-size: 24px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-card {
                padding: 14px;
            }
        }

        @media (max-width: 480px) {

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 16px;
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

            <div class="returns-page">

                <div class="page-heading">

                    <div>
                        <h1>Returns & Exchanges</h1>

                        <p>
                            Manage customer return and exchange requests.
                        </p>
                    </div>

                </div>


                <?php if ($successMessage): ?>

                    <div class="alert alert-success">
                        <?= e($successMessage) ?>
                    </div>

                <?php endif; ?>


                <?php if ($errorMessage): ?>

                    <div class="alert alert-danger">
                        <?= e($errorMessage) ?>
                    </div>

                <?php endif; ?>


                <!-- STATS -->

                <div class="stats-grid">

                    <div class="stat-card">
                        <div class="stat-label">
                            Total Requests
                        </div>

                        <div class="stat-value">
                            <?= number_format($totalRequests) ?>
                        </div>
                    </div>


                    <div class="stat-card">
                        <div class="stat-label">
                            Pending
                        </div>

                        <div class="stat-value">
                            <?= number_format($pendingRequests) ?>
                        </div>
                    </div>


                    <div class="stat-card">
                        <div class="stat-label">
                            Approved
                        </div>

                        <div class="stat-value">
                            <?= number_format($approvedRequests) ?>
                        </div>
                    </div>


                    <div class="stat-card">
                        <div class="stat-label">
                            Completed
                        </div>

                        <div class="stat-value">
                            <?= number_format($completedRequests) ?>
                        </div>
                    </div>


                    <div class="stat-card">
                        <div class="stat-label">
                            Refunded Amount
                        </div>

                        <div class="stat-value">
                            <?= money($totalRefundAmount) ?>
                        </div>
                    </div>

                </div>


                <!-- FILTERS -->

                <div class="filter-card">

                    <form
                        method="GET"
                        class="row g-3 align-items-end"
                    >

                        <div class="col-lg-5">

                            <label class="form-label">
                                Search
                            </label>

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Customer, order, phone or product..."
                                value="<?= e($search) ?>"
                            >

                        </div>


                        <div class="col-lg-3">

                            <label class="form-label">
                                Type
                            </label>

                            <select
                                name="type"
                                class="form-select"
                            >

                                <option value="">
                                    All Types
                                </option>

                                <option
                                    value="Return"
                                    <?= $typeFilter === 'Return' ? 'selected' : '' ?>
                                >
                                    Return
                                </option>

                                <option
                                    value="Exchange"
                                    <?= $typeFilter === 'Exchange' ? 'selected' : '' ?>
                                >
                                    Exchange
                                </option>

                            </select>

                        </div>


                        <div class="col-lg-3">

                            <label class="form-label">
                                Status
                            </label>

                            <select
                                name="status"
                                class="form-select"
                            >

                                <option value="">
                                    All Statuses
                                </option>

                                <?php
                                $statuses = [
                                    'Pending',
                                    'Approved',
                                    'Rejected',
                                    'Received',
                                    'Completed',
                                    'Cancelled'
                                ];
                                ?>

                                <?php foreach ($statuses as $status): ?>

                                    <option
                                        value="<?= e($status) ?>"
                                        <?= $statusFilter === $status ? 'selected' : '' ?>
                                    >
                                        <?= e($status) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="col-lg-1">

                            <button
                                type="submit"
                                class="btn btn-dark w-100"
                            >
                                Filter
                            </button>

                        </div>

                    </form>

                </div>


                <!-- TABLE -->

                <div class="returns-card">

                    <div class="returns-card-header">

                        <h5>
                            Return / Exchange Requests
                        </h5>

                        <span class="text-muted small">
                            <?= count($returns) ?> result(s)
                        </span>

                    </div>


                    <div class="table-wrapper">

                        <?php if (!empty($returns)): ?>

                            <table class="returns-table">

                                <thead>

                                    <tr>

                                        <th>
                                            Request
                                        </th>

                                        <th>
                                            Order
                                        </th>

                                        <th>
                                            Customer
                                        </th>

                                        <th>
                                            Product
                                        </th>

                                        <th>
                                            Type
                                        </th>

                                        <th>
                                            Refund
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                        <th>
                                            Date
                                        </th>

                                        <th>
                                            Action
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                <?php foreach ($returns as $return): ?>

                                    <?php

                                    $typeClass = strtolower($return['request_type']) === 'exchange'
                                        ? 'type-exchange'
                                        : 'type-return';

                                    $statusClass = strtolower(str_replace(' ', '-', $return['status']));

                                    $refundClass = strtolower(str_replace(' ', '-', $return['refund_status']));

                                    ?>

                                    <tr>

                                        <td>

                                            <div class="order-number">
                                                #<?= (int)$return['id'] ?>
                                            </div>

                                            <div class="customer-contact">
                                                Qty: <?= (int)$return['quantity'] ?>
                                            </div>

                                        </td>


                                        <td>

                                            <div class="order-number">
                                                #<?= (int)$return['order_id'] ?>
                                            </div>

                                        </td>


                                        <td>

                                            <div class="customer-name">
                                                <?= e($return['customer_name']) ?>
                                            </div>

                                            <?php if (!empty($return['customer_phone'])): ?>

                                                <div class="customer-contact">
                                                    <?= e($return['customer_phone']) ?>
                                                </div>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <div class="product-name">
                                                <?= e($return['product_name']) ?>
                                            </div>

                                            <?php if (!empty($return['variant_name'])): ?>

                                                <div class="variant-text">
                                                    <?= e($return['variant_name']) ?>
                                                </div>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <span class="type-badge <?= e($typeClass) ?>">
                                                <?= e($return['request_type']) ?>
                                            </span>

                                        </td>


                                        <td>

                                            <?php if ((float)$return['refund_amount'] > 0): ?>

                                                <strong>
                                                    <?= money($return['refund_amount']) ?>
                                                </strong>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    —
                                                </span>

                                            <?php endif; ?>

                                            <div class="mt-1">

                                                <span class="refund-badge refund-<?= e($refundClass) ?>">
                                                    <?= e($return['refund_status']) ?>
                                                </span>

                                            </div>

                                        </td>


                                        <td>

                                            <span class="status-badge status-<?= e($statusClass) ?>">
                                                <?= e($return['status']) ?>
                                            </span>

                                        </td>


                                        <td>

                                            <?= !empty($return['created_at'])
                                                ? date('d M Y', strtotime($return['created_at']))
                                                : '—'
                                            ?>

                                            <div class="customer-contact">

                                                <?= !empty($return['created_at'])
                                                    ? date('h:i A', strtotime($return['created_at']))
                                                    : ''
                                                ?>

                                            </div>

                                        </td>


                                        <td>

                                            <div class="action-buttons">

                                                <button
                                                    type="button"
                                                    class="btn-view view-return-btn"
                                                    data-id="<?= (int)$return['id'] ?>"
                                                >
                                                    View Details
                                                </button>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        <?php else: ?>

                            <div class="empty-state">

                                <h5>
                                    No Return / Exchange Requests
                                </h5>

                                <p>
                                    There are currently no requests matching your filters.
                                </p>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </main>

        <?php include "../includes/footer.php"; ?>

    </div>

</div>


<!-- DETAILS MODAL -->

<div
    class="modal fade"
    id="returnDetailsModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <div>

                    <h5 class="modal-title mb-1">
                        Return / Exchange Details
                    </h5>

                    <div
                        id="modalRequestNumber"
                        class="text-muted small"
                    ></div>

                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body">

                <div
                    id="detailsLoading"
                    class="text-center py-5"
                >

                    <div
                        class="spinner-border"
                        role="status"
                    ></div>

                    <div class="mt-3 text-muted">
                        Loading request details...
                    </div>

                </div>


                <div
                    id="detailsContent"
                    style="display:none;"
                >

                    <div class="row g-3">

                        <div class="col-md-4">

                            <div class="detail-box">

                                <div class="detail-box-title">
                                    Customer
                                </div>

                                <div
                                    id="detailCustomer"
                                    class="detail-main"
                                ></div>

                                <div
                                    id="detailEmail"
                                    class="detail-muted"
                                ></div>

                                <div
                                    id="detailPhone"
                                    class="detail-muted"
                                ></div>

                            </div>

                        </div>


                        <div class="col-md-4">

                            <div class="detail-box">

                                <div class="detail-box-title">
                                    Product
                                </div>

                                <div
                                    id="detailProduct"
                                    class="detail-main"
                                ></div>

                                <div
                                    id="detailVariant"
                                    class="detail-muted"
                                ></div>

                                <div
                                    id="detailQuantity"
                                    class="detail-muted"
                                ></div>

                            </div>

                        </div>


                        <div class="col-md-4">

                            <div class="detail-box">

                                <div class="detail-box-title">
                                    Request
                                </div>

                                <div
                                    id="detailType"
                                    class="detail-main"
                                ></div>

                                <div
                                    id="detailStatus"
                                    class="mt-2"
                                ></div>

                                <div
                                    id="detailRefundStatus"
                                    class="mt-2"
                                ></div>

                            </div>

                        </div>

                    </div>


                    <div class="detail-section">

                        <div class="detail-section-title">
                            Request Information
                        </div>

                        <div class="row g-3">

                            <div class="col-md-6">

                                <div class="detail-box">

                                    <div class="detail-box-title">
                                        Order
                                    </div>

                                    <div
                                        id="detailOrder"
                                        class="detail-main"
                                    ></div>

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="detail-box">

                                    <div class="detail-box-title">
                                        Refund Amount
                                    </div>

                                    <div
                                        id="detailRefundAmount"
                                        class="detail-main"
                                    ></div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <div
                        id="exchangeSection"
                        class="detail-section"
                        style="display:none;"
                    >

                        <div class="detail-section-title">
                            Exchange Information
                        </div>

                        <div class="detail-box">

                            <div
                                id="detailExchangeProduct"
                                class="detail-main"
                            ></div>

                            <div
                                id="detailExchangeVariant"
                                class="detail-muted"
                            ></div>

                        </div>

                    </div>


                    <div class="detail-section">

                        <div class="detail-section-title">
                            Return Reason
                        </div>

                        <div
                            id="detailReason"
                            class="detail-note"
                        ></div>

                    </div>


                    <div class="detail-section">

                        <div class="detail-section-title">
                            Customer Note
                        </div>

                        <div
                            id="detailCustomerNote"
                            class="detail-note"
                        ></div>

                    </div>


                    <div class="detail-section">

                        <div class="detail-section-title">
                            Admin Note
                        </div>

                        <div
                            id="detailAdminNote"
                            class="detail-note"
                        ></div>

                    </div>


                    <div class="detail-section">

                        <div class="detail-section-title">
                            Update Request
                        </div>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="update_return"
                                value="1"
                            >

                            <input
                                type="hidden"
                                name="return_id"
                                id="updateReturnId"
                                value=""
                            >

                            <div class="row g-3">

                                <div class="col-md-6">

                                    <label class="form-label">
                                        Request Status
                                    </label>

                                    <select
                                        name="status"
                                        id="updateStatus"
                                        class="form-select"
                                    >

                                        <option value="Pending">
                                            Pending
                                        </option>

                                        <option value="Approved">
                                            Approved
                                        </option>

                                        <option value="Rejected">
                                            Rejected
                                        </option>

                                        <option value="Received">
                                            Received
                                        </option>

                                        <option value="Completed">
                                            Completed
                                        </option>

                                        <option value="Cancelled">
                                            Cancelled
                                        </option>

                                    </select>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        Refund Status
                                    </label>

                                    <select
                                        name="refund_status"
                                        id="updateRefundStatus"
                                        class="form-select"
                                    >

                                        <option value="Not Applicable">
                                            Not Applicable
                                        </option>

                                        <option value="Pending">
                                            Pending
                                        </option>

                                        <option value="Processing">
                                            Processing
                                        </option>

                                        <option value="Refunded">
                                            Refunded
                                        </option>

                                    </select>

                                </div>

                            </div>


                            <div class="mt-3 text-end">

                                <button
                                    type="submit"
                                    class="btn btn-dark"
                                >
                                    Update Request
                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/admin.js"></script>


<script>

document.addEventListener("DOMContentLoaded", function () {

    const modalElement = document.getElementById("returnDetailsModal");

    const modal = new bootstrap.Modal(modalElement);

    const buttons = document.querySelectorAll(".view-return-btn");

    function escapeHtml(value) {

        if (value === null || value === undefined || value === "") {
            return "—";
        }

        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }


    function money(value) {

        const amount = parseFloat(value || 0);

        return "Rs. " + amount.toLocaleString("en-PK", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }


    function statusClass(status) {

        return String(status || "")
            .toLowerCase()
            .replace(/\s+/g, "-");
    }


    buttons.forEach(function (button) {

        button.addEventListener("click", async function () {

            const id = this.dataset.id;

            document.getElementById("detailsLoading").style.display = "block";
            document.getElementById("detailsContent").style.display = "none";

            modal.show();

            try {

                const response = await fetch(
                    "returns.php?return_details=" + encodeURIComponent(id),
                    {
                        headers: {
                            "X-Requested-With": "XMLHttpRequest"
                        }
                    }
                );

                const data = await response.json();

                if (!data.success) {

                    alert(data.message || "Unable to load details.");
                    modal.hide();

                    return;
                }

                const r = data.return;


                document.getElementById("modalRequestNumber").textContent =
                    "#" + r.id + " • Order #" + r.order_id;


                document.getElementById("detailCustomer").textContent =
                    r.customer_name || "—";

                document.getElementById("detailEmail").textContent =
                    r.customer_email || "—";

                document.getElementById("detailPhone").textContent =
                    r.customer_phone || "—";


                document.getElementById("detailProduct").textContent =
                    r.product_name || "—";

                document.getElementById("detailVariant").textContent =
                    r.variant_name
                        ? "Variant: " + r.variant_name
                        : "No variant";


                document.getElementById("detailQuantity").textContent =
                    "Quantity: " + (r.quantity || 0);


                document.getElementById("detailType").textContent =
                    r.request_type || "—";


                document.getElementById("detailStatus").innerHTML =
                    '<span class="status-badge status-' +
                    statusClass(r.status) +
                    '">' +
                    escapeHtml(r.status) +
                    '</span>';


                document.getElementById("detailRefundStatus").innerHTML =
                    '<span class="refund-badge refund-' +
                    statusClass(r.refund_status) +
                    '">' +
                    escapeHtml(r.refund_status) +
                    '</span>';


                document.getElementById("detailOrder").textContent =
                    "#" + r.order_id;


                document.getElementById("detailRefundAmount").textContent =
                    money(r.refund_amount);


                document.getElementById("detailReason").textContent =
                    r.reason || "No reason provided.";


                document.getElementById("detailCustomerNote").textContent =
                    r.customer_note || "No customer note.";


                document.getElementById("detailAdminNote").textContent =
                    r.admin_note || "No admin note.";


                document.getElementById("updateReturnId").value =
                    r.id;


                document.getElementById("updateStatus").value =
                    r.status;


                document.getElementById("updateRefundStatus").value =
                    r.refund_status;


                if (
                    r.request_type === "Exchange" &&
                    (
                        r.exchange_product_id ||
                        r.exchange_variant_name
                    )
                ) {

                    document.getElementById("exchangeSection").style.display =
                        "block";

                    document.getElementById("detailExchangeProduct").textContent =
                        r.exchange_product_id
                            ? "Exchange Product ID: " + r.exchange_product_id
                            : "Exchange product not selected.";

                    document.getElementById("detailExchangeVariant").textContent =
                        r.exchange_variant_name
                            ? "Variant: " + r.exchange_variant_name
                            : "No exchange variant.";

                } else {

                    document.getElementById("exchangeSection").style.display =
                        "none";
                }


                document.getElementById("detailsLoading").style.display =
                    "none";

                document.getElementById("detailsContent").style.display =
                    "block";

            } catch (error) {

                console.error(error);

                alert("Something went wrong while loading details.");

                modal.hide();
            }

        });

    });

});

</script>

</body>
</html>