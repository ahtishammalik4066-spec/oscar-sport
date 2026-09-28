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
| AJAX - CUSTOMER DETAILS
|--------------------------------------------------------------------------
*/

if (isset($_GET['customer_details'])) {

    header('Content-Type: application/json; charset=utf-8');

    $email = trim($_GET['customer_details']);

    if ($email === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid customer.'
        ]);
        exit;
    }

    try {

        /*
        |--------------------------------------------------------------------------
        | CUSTOMER SUMMARY
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                customer_name,
                customer_email,
                MAX(customer_phone) AS customer_phone,
                COUNT(*) AS total_orders,
                SUM(
                    CASE
                        WHEN order_status != 'Cancelled'
                        THEN total_amount
                        ELSE 0
                    END
                ) AS total_spent,
                MAX(created_at) AS last_order
            FROM orders
            WHERE customer_email = ?
            GROUP BY customer_name, customer_email
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {

            echo json_encode([
                'success' => false,
                'message' => 'Customer not found.'
            ]);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | CUSTOMER ORDERS
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                order_number,
                total_amount,
                payment_method,
                payment_status,
                order_status,
                created_at
            FROM orders
            WHERE customer_email = ?
            ORDER BY id DESC
            LIMIT 50
        ");

        $stmt->execute([$email]);

        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);


        echo json_encode([
            'success' => true,
            'customer' => $customer,
            'orders' => $orders
        ]);

    } catch (PDOException $e) {

        echo json_encode([
            'success' => false,
            'message' => 'Unable to load customer details.'
        ]);
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$statusFilter = trim($_GET['status'] ?? '');


/*
|--------------------------------------------------------------------------
| CUSTOMER STATS
|--------------------------------------------------------------------------
*/

$totalCustomers = 0;
$activeCustomers = 0;
$totalCustomerRevenue = 0;
$totalCustomerOrders = 0;

try {

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM (
            SELECT customer_email
            FROM orders
            WHERE customer_email IS NOT NULL
              AND customer_email != ''
            GROUP BY customer_email
        ) AS customers
    ");

    $totalCustomers = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | ACTIVE CUSTOMERS
    | Customers with at least one non-cancelled order
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM (
            SELECT customer_email
            FROM orders
            WHERE customer_email IS NOT NULL
              AND customer_email != ''
              AND order_status != 'Cancelled'
            GROUP BY customer_email
        ) AS customers
    ");

    $activeCustomers = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL ORDERS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM orders
        WHERE order_status != 'Cancelled'
    ");

    $totalCustomerOrders = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL REVENUE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COALESCE(SUM(total_amount), 0)
        FROM orders
        WHERE order_status != 'Cancelled'
    ");

    $totalCustomerRevenue = (float)$stmt->fetchColumn();

} catch (PDOException $e) {

    $totalCustomers = 0;
}


/*
|--------------------------------------------------------------------------
| CUSTOMER QUERY
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] = "
        (
            customer_name LIKE ?
            OR customer_email LIKE ?
            OR customer_phone LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| CUSTOMER STATUS FILTER
|--------------------------------------------------------------------------
|
| Active = at least one non-cancelled order
| Inactive = only cancelled orders
|
*/

$statusCondition = '';

if ($statusFilter === 'Active') {

    $statusCondition = "
        HAVING
            SUM(
                CASE
                    WHEN order_status != 'Cancelled'
                    THEN 1
                    ELSE 0
                END
            ) > 0
    ";

} elseif ($statusFilter === 'Inactive') {

    $statusCondition = "
        HAVING
            SUM(
                CASE
                    WHEN order_status != 'Cancelled'
                    THEN 1
                    ELSE 0
                END
            ) = 0
    ";
}


$whereSql = '';

if (!empty($where)) {

    $whereSql = 'WHERE ' . implode(' AND ', $where);
}


/*
|--------------------------------------------------------------------------
| FETCH CUSTOMERS
|--------------------------------------------------------------------------
*/

$customers = [];

try {

    $sql = "
        SELECT

            customer_name,
            customer_email,

            MAX(customer_phone) AS customer_phone,

            COUNT(*) AS total_orders,

            SUM(
                CASE
                    WHEN order_status != 'Cancelled'
                    THEN total_amount
                    ELSE 0
                END
            ) AS total_spent,

            SUM(
                CASE
                    WHEN order_status != 'Cancelled'
                    THEN 1
                    ELSE 0
                END
            ) AS valid_orders,

            MAX(created_at) AS last_order,

            MIN(created_at) AS first_order

        FROM orders

        $whereSql

        GROUP BY
            customer_name,
            customer_email

        $statusCondition

        ORDER BY
            last_order DESC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $customers = [];
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

    <title>Customers | OSCAR CMS</title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="assets/css/admin.css"
    >


    <style>

        .customers-page {
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


        /*
        |--------------------------------------------------------------------------
        | STATS
        |--------------------------------------------------------------------------
        */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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


        /*
        |--------------------------------------------------------------------------
        | FILTER
        |--------------------------------------------------------------------------
        */

        .filter-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 20px;
        }


        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */

        .customers-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }


        .customers-card-header {
            padding: 18px 20px;
            border-bottom: 1px solid #e5e7eb;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }


        .customers-card-header h5 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
        }


        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }


        .customers-table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
        }


        .customers-table th {
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


        .customers-table td {
            padding: 15px 16px;

            border-bottom: 1px solid #f0f0f0;

            vertical-align: middle;

            color: #374151;

            font-size: 14px;
        }


        .customers-table tr:last-child td {
            border-bottom: 0;
        }


        /*
        |--------------------------------------------------------------------------
        | CUSTOMER
        |--------------------------------------------------------------------------
        */

        .customer-name {
            font-weight: 700;
            color: #111827;
        }


        .customer-email {
            color: #6b7280;
            font-size: 12px;
            margin-top: 3px;
        }


        .customer-phone {
            color: #6b7280;
            font-size: 13px;
        }


        .orders-count {
            font-weight: 700;
            color: #111827;
        }


        .last-order {
            color: #374151;
        }


        .date-muted {
            color: #6b7280;
            font-size: 12px;
            margin-top: 3px;
        }


        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        .customer-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 6px 10px;

            border-radius: 999px;

            font-size: 11px;
            font-weight: 700;
        }


        .status-active {
            background: #dcfce7;
            color: #166534;
        }


        .status-inactive {
            background: #f3f4f6;
            color: #4b5563;
        }


        /*
        |--------------------------------------------------------------------------
        | BUTTON
        |--------------------------------------------------------------------------
        */

        .btn-view {
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #111827;

            padding: 7px 12px;

            border-radius: 7px;

            font-size: 12px;
            font-weight: 600;
        }


        .btn-view:hover {
            background: #f9fafb;
        }


        /*
        |--------------------------------------------------------------------------
        | MODAL
        |--------------------------------------------------------------------------
        */

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
            margin-bottom: 8px;
        }


        .detail-main {
            font-size: 17px;
            font-weight: 700;
            color: #111827;
        }


        .detail-muted {
            color: #6b7280;
            font-size: 13px;
            margin-top: 4px;
        }


        .orders-section {
            margin-top: 22px;
        }


        .orders-section-title {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 12px;
        }


        .customer-orders-table {
            width: 100%;
            border-collapse: collapse;
        }


        .customer-orders-table th {
            background: #f9fafb;
            padding: 11px;
            font-size: 11px;
            text-transform: uppercase;
            color: #6b7280;
            white-space: nowrap;
        }


        .customer-orders-table td {
            padding: 11px;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
        }


        .order-status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
        }


        .order-pending {
            background: #fef3c7;
            color: #92400e;
        }


        .order-processing {
            background: #dbeafe;
            color: #1d4ed8;
        }


        .order-shipped {
            background: #e0e7ff;
            color: #4338ca;
        }


        .order-delivered {
            background: #dcfce7;
            color: #166534;
        }


        .order-cancelled {
            background: #fee2e2;
            color: #b91c1c;
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


        @media (max-width: 1100px) {

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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


            <div class="customers-page">


                <!-- PAGE HEADING -->

                <div class="page-heading">

                    <div>

                        <h1>
                            Customers
                        </h1>

                        <p>
                            Manage customers and view their complete order history.
                        </p>

                    </div>

                </div>


                <!-- STATS -->

                <div class="stats-grid">


                    <div class="stat-card">

                        <div class="stat-label">
                            Total Customers
                        </div>

                        <div class="stat-value">
                            <?= number_format($totalCustomers) ?>
                        </div>

                    </div>


                    <div class="stat-card">

                        <div class="stat-label">
                            Active Customers
                        </div>

                        <div class="stat-value">
                            <?= number_format($activeCustomers) ?>
                        </div>

                    </div>


                    <div class="stat-card">

                        <div class="stat-label">
                            Total Orders
                        </div>

                        <div class="stat-value">
                            <?= number_format($totalCustomerOrders) ?>
                        </div>

                    </div>


                    <div class="stat-card">

                        <div class="stat-label">
                            Customer Revenue
                        </div>

                        <div class="stat-value">
                            <?= money($totalCustomerRevenue) ?>
                        </div>

                    </div>


                </div>


                <!-- FILTER -->

                <div class="filter-card">


                    <form
                        method="GET"
                        class="row g-3 align-items-end"
                    >


                        <div class="col-lg-8">

                            <label class="form-label">
                                Search Customer
                            </label>

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search by name, email or phone..."
                                value="<?= e($search) ?>"
                            >

                        </div>


                        <div class="col-lg-3">

                            <label class="form-label">
                                Customer Status
                            </label>

                            <select
                                name="status"
                                class="form-select"
                            >

                                <option value="">
                                    All Customers
                                </option>

                                <option
                                    value="Active"
                                    <?= $statusFilter === 'Active' ? 'selected' : '' ?>
                                >
                                    Active
                                </option>

                                <option
                                    value="Inactive"
                                    <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>
                                >
                                    Inactive
                                </option>

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


                <!-- CUSTOMERS TABLE -->

                <div class="customers-card">


                    <div class="customers-card-header">

                        <h5>
                            Customer List
                        </h5>

                        <span class="text-muted small">
                            <?= count($customers) ?> customer(s)
                        </span>

                    </div>


                    <div class="table-wrapper">


                        <?php if (!empty($customers)): ?>


                            <table class="customers-table">


                                <thead>

                                    <tr>

                                        <th>
                                            Customer
                                        </th>

                                        <th>
                                            Phone
                                        </th>

                                        <th>
                                            Orders
                                        </th>

                                        <th>
                                            Total Spent
                                        </th>

                                        <th>
                                            First Order
                                        </th>

                                        <th>
                                            Last Order
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                        <th>
                                            Action
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>


                                <?php foreach ($customers as $customer): ?>


                                    <?php

                                    $isActive = (int)$customer['valid_orders'] > 0;

                                    ?>


                                    <tr>


                                        <!-- CUSTOMER -->

                                        <td>

                                            <div class="customer-name">
                                                <?= e($customer['customer_name']) ?>
                                            </div>

                                            <div class="customer-email">
                                                <?= e($customer['customer_email']) ?>
                                            </div>

                                        </td>


                                        <!-- PHONE -->

                                        <td>

                                            <div class="customer-phone">

                                                <?= !empty($customer['customer_phone'])
                                                    ? e($customer['customer_phone'])
                                                    : '—'
                                                ?>

                                            </div>

                                        </td>


                                        <!-- ORDERS -->

                                        <td>

                                            <div class="orders-count">
                                                <?= number_format((int)$customer['total_orders']) ?>
                                            </div>

                                            <?php if ((int)$customer['valid_orders'] !== (int)$customer['total_orders']): ?>

                                                <div class="date-muted">

                                                    <?= number_format((int)$customer['valid_orders']) ?>
                                                    valid

                                                </div>

                                            <?php endif; ?>

                                        </td>


                                        <!-- TOTAL SPENT -->

                                        <td>

                                            <strong>
                                                <?= money($customer['total_spent']) ?>
                                            </strong>

                                        </td>


                                        <!-- FIRST ORDER -->

                                        <td>

                                            <?php if (!empty($customer['first_order'])): ?>

                                                <?= date(
                                                    'd M Y',
                                                    strtotime($customer['first_order'])
                                                ) ?>

                                            <?php else: ?>

                                                —

                                            <?php endif; ?>

                                        </td>


                                        <!-- LAST ORDER -->

                                        <td>

                                            <?php if (!empty($customer['last_order'])): ?>

                                                <div class="last-order">

                                                    <?= date(
                                                        'd M Y',
                                                        strtotime($customer['last_order'])
                                                    ) ?>

                                                </div>

                                                <div class="date-muted">

                                                    <?= date(
                                                        'h:i A',
                                                        strtotime($customer['last_order'])
                                                    ) ?>

                                                </div>

                                            <?php else: ?>

                                                —

                                            <?php endif; ?>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <?php if ($isActive): ?>

                                                <span class="customer-status status-active">
                                                    Active
                                                </span>

                                            <?php else: ?>

                                                <span class="customer-status status-inactive">
                                                    Inactive
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- ACTION -->

                                        <td>

                                            <button
                                                type="button"
                                                class="btn-view view-customer-btn"
                                                data-email="<?= e($customer['customer_email']) ?>"
                                            >
                                                View Details
                                            </button>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                                </tbody>


                            </table>


                        <?php else: ?>


                            <div class="empty-state">

                                <h5>
                                    No Customers Found
                                </h5>

                                <p>
                                    Customers will automatically appear here when orders are placed.
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


<!-- CUSTOMER DETAILS MODAL -->

<div
    class="modal fade"
    id="customerDetailsModal"
    tabindex="-1"
    aria-hidden="true"
>


    <div class="modal-dialog modal-xl modal-dialog-centered">


        <div class="modal-content">


            <div class="modal-header">


                <div>

                    <h5 class="modal-title mb-1">
                        Customer Details
                    </h5>

                    <div
                        id="customerModalEmail"
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


                <!-- LOADING -->

                <div
                    id="customerLoading"
                    class="text-center py-5"
                >

                    <div
                        class="spinner-border"
                        role="status"
                    ></div>

                    <div class="mt-3 text-muted">
                        Loading customer details...
                    </div>

                </div>


                <!-- CONTENT -->

                <div
                    id="customerContent"
                    style="display:none;"
                >


                    <div class="row g-3">


                        <div class="col-md-3">

                            <div class="detail-box">

                                <div class="detail-box-title">
                                    Customer
                                </div>

                                <div
                                    id="modalCustomerName"
                                    class="detail-main"
                                ></div>

                            </div>

                        </div>


                        <div class="col-md-3">

                            <div class="detail-box">

                                <div class="detail-box-title">
                                    Phone
                                </div>

                                <div
                                    id="modalCustomerPhone"
                                    class="detail-main"
                                ></div>

                            </div>

                        </div>


                        <div class="col-md-3">

                            <div class="detail-box">

                                <div class="detail-box-title">
                                    Total Orders
                                </div>

                                <div
                                    id="modalTotalOrders"
                                    class="detail-main"
                                ></div>

                            </div>

                        </div>


                        <div class="col-md-3">

                            <div class="detail-box">

                                <div class="detail-box-title">
                                    Total Spent
                                </div>

                                <div
                                    id="modalTotalSpent"
                                    class="detail-main"
                                ></div>

                            </div>

                        </div>


                    </div>


                    <!-- ORDER HISTORY -->

                    <div class="orders-section">


                        <div class="orders-section-title">
                            Order History
                        </div>


                        <div class="table-responsive">


                            <table class="customer-orders-table">


                                <thead>

                                    <tr>

                                        <th>
                                            Order
                                        </th>

                                        <th>
                                            Total
                                        </th>

                                        <th>
                                            Payment
                                        </th>

                                        <th>
                                            Payment Status
                                        </th>

                                        <th>
                                            Order Status
                                        </th>

                                        <th>
                                            Date
                                        </th>

                                    </tr>

                                </thead>


                                <tbody id="customerOrdersBody">
                                </tbody>


                            </table>


                        </div>


                    </div>


                </div>


            </div>


        </div>


    </div>


</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<script src="assets/js/admin.js"></script>


<script>

document.addEventListener("DOMContentLoaded", function () {


    const modalElement =
        document.getElementById("customerDetailsModal");


    const modal =
        new bootstrap.Modal(modalElement);


    const buttons =
        document.querySelectorAll(".view-customer-btn");


    function statusClass(status)
    {
        return String(status || "")
            .toLowerCase()
            .replace(/\s+/g, "-");
    }


    function escapeHtml(value)
    {

        if (
            value === null ||
            value === undefined ||
            value === ""
        ) {
            return "—";
        }


        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }


    function money(value)
    {

        const amount =
            parseFloat(value || 0);


        return "Rs. " +
            amount.toLocaleString("en-PK", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
    }


    function formatDate(value)
    {

        if (!value) {
            return "—";
        }


        const date =
            new Date(
                String(value).replace(" ", "T")
            );


        if (isNaN(date.getTime())) {
            return value;
        }


        return date.toLocaleDateString(
            "en-GB",
            {
                day: "2-digit",
                month: "short",
                year: "numeric"
            }
        );
    }


    buttons.forEach(function (button) {


        button.addEventListener(
            "click",
            async function ()
            {


                const email =
                    this.dataset.email;


                document.getElementById(
                    "customerLoading"
                ).style.display = "block";


                document.getElementById(
                    "customerContent"
                ).style.display = "none";


                modal.show();


                try {


                    const response =
                        await fetch(
                            "customers.php?customer_details=" +
                            encodeURIComponent(email),
                            {
                                headers: {
                                    "X-Requested-With":
                                        "XMLHttpRequest"
                                }
                            }
                        );


                    const data =
                        await response.json();


                    if (!data.success) {

                        alert(
                            data.message ||
                            "Unable to load customer."
                        );

                        modal.hide();

                        return;
                    }


                    const customer =
                        data.customer;


                    const orders =
                        data.orders;


                    /*
                    |--------------------------------------------------------------------------
                    | CUSTOMER INFO
                    |--------------------------------------------------------------------------
                    */

                    document.getElementById(
                        "customerModalEmail"
                    ).textContent =
                        customer.customer_email || "—";


                    document.getElementById(
                        "modalCustomerName"
                    ).textContent =
                        customer.customer_name || "—";


                    document.getElementById(
                        "modalCustomerPhone"
                    ).textContent =
                        customer.customer_phone || "—";


                    document.getElementById(
                        "modalTotalOrders"
                    ).textContent =
                        customer.total_orders || "0";


                    document.getElementById(
                        "modalTotalSpent"
                    ).textContent =
                        money(customer.total_spent);


                    /*
                    |--------------------------------------------------------------------------
                    | ORDER HISTORY
                    |--------------------------------------------------------------------------
                    */

                    const tbody =
                        document.getElementById(
                            "customerOrdersBody"
                        );


                    tbody.innerHTML = "";


                    if (!orders.length) {


                        tbody.innerHTML = `
                            <tr>
                                <td
                                    colspan="6"
                                    class="text-center text-muted py-4"
                                >
                                    No orders found.
                                </td>
                            </tr>
                        `;


                    } else {


                        orders.forEach(function (order) {


                            const status =
                                String(
                                    order.order_status || ""
                                );


                            const statusClassName =
                                statusClass(status);


                            const row = document.createElement("tr");


                            row.innerHTML = `

                                <td>
                                    <strong>
                                        ${escapeHtml(
                                            order.order_number ||
                                            "#" + order.id
                                        )}
                                    </strong>
                                </td>

                                <td>
                                    <strong>
                                        ${money(order.total_amount)}
                                    </strong>
                                </td>

                                <td>
                                    ${escapeHtml(
                                        order.payment_method
                                    )}
                                </td>

                                <td>
                                    ${escapeHtml(
                                        order.payment_status
                                    )}
                                </td>

                                <td>
                                    <span
                                        class="order-status order-${statusClassName}"
                                    >
                                        ${escapeHtml(status)}
                                    </span>
                                </td>

                                <td>
                                    ${formatDate(order.created_at)}
                                </td>

                            `;


                            tbody.appendChild(row);

                        });

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | SHOW CONTENT
                    |--------------------------------------------------------------------------
                    */

                    document.getElementById(
                        "customerLoading"
                    ).style.display = "none";


                    document.getElementById(
                        "customerContent"
                    ).style.display = "block";


                } catch (error) {


                    console.error(error);


                    alert(
                        "Something went wrong while loading customer details."
                    );


                    modal.hide();

                }

            }
        );

    });

});

</script>


</body>

</html>