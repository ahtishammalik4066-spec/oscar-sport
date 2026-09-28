<?php

require_once "../includes/auth.php";
require_once "../config/database.php";

$totalProducts = 0;
$totalCategories = 0;
$totalCustomers = 0;
$totalOrders = 0;

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $totalProducts = (int) $stmt->fetchColumn();

} catch (PDOException $e) {
    $totalProducts = 0;
}

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
    $totalCategories = (int) $stmt->fetchColumn();

} catch (PDOException $e) {
    $totalCategories = 0;
}

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM customers");
    $totalCustomers = (int) $stmt->fetchColumn();

} catch (PDOException $e) {
    $totalCustomers = 0;
}

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $totalOrders = (int) $stmt->fetchColumn();

} catch (PDOException $e) {
    $totalOrders = 0;
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

    <title>Dashboard | Sports CMS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="assets/css/admin.css"
    >

</head>

<body>

<div class="admin-layout">

    <?php require_once "../includes/sidebar.php"; ?>

    <div class="main-area">

        <?php require_once "../includes/header.php"; ?>

        <main class="dashboard-content">

            <div class="page-heading">

                <div>
                    <h1>Dashboard</h1>

                    <p>
                        Welcome back,
                        <?= htmlspecialchars($_SESSION["admin_name"]) ?>.
                        Here's what's happening with your store.
                    </p>
                </div>

            </div>

            <div class="stats-grid">

                <div class="stat-card">

                    <div class="stat-icon">
                        ◈
                    </div>

                    <div>
                        <span>Total Products</span>
                        <strong><?= $totalProducts ?></strong>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon">
                        ◉
                    </div>

                    <div>
                        <span>Categories</span>
                        <strong><?= $totalCategories ?></strong>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon">
                        ♙
                    </div>

                    <div>
                        <span>Customers</span>
                        <strong><?= $totalCustomers ?></strong>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon">
                        ▤
                    </div>

                    <div>
                        <span>Total Orders</span>
                        <strong><?= $totalOrders ?></strong>
                    </div>

                </div>

            </div>

            <div class="dashboard-grid">

                <section class="dashboard-card">

                    <div class="card-header">

                        <div>
                            <h3>Recent Orders</h3>
                            <p>Latest customer orders</p>
                        </div>

                        <a href="#">
                            View All
                        </a>

                    </div>

                    <div class="empty-state">

                        <div class="empty-icon">
                            ▤
                        </div>

                        <h4>No orders yet</h4>

                        <p>
                            Orders will appear here once customers
                            start purchasing.
                        </p>

                    </div>

                </section>

                <section class="dashboard-card">

                    <div class="card-header">

                        <div>
                            <h3>Store Overview</h3>
                            <p>Quick store information</p>
                        </div>

                    </div>

                    <div class="overview-list">

                        <div>
                            <span>Products</span>
                            <strong><?= $totalProducts ?></strong>
                        </div>

                        <div>
                            <span>Categories</span>
                            <strong><?= $totalCategories ?></strong>
                        </div>

                        <div>
                            <span>Customers</span>
                            <strong><?= $totalCustomers ?></strong>
                        </div>

                        <div>
                            <span>Orders</span>
                            <strong><?= $totalOrders ?></strong>
                        </div>

                    </div>

                </section>

            </div>

        </main>

        <?php require_once "../includes/footer.php"; ?>

    </div>

</div>

<script src="assets/js/admin.js"></script>

</body>
</html>