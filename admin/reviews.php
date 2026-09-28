<?php

require_once "../config/database.php";
require_once "../includes/auth.php";

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getRatingStars($rating)
{
    $rating = (int)$rating;

    $stars = '';

    for ($i = 1; $i <= 5; $i++) {
        $stars .= $i <= $rating ? '★' : '☆';
    }

    return $stars;
}


/*
|--------------------------------------------------------------------------
| Delete Review
|--------------------------------------------------------------------------
*/

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {

    $reviewId = (int)$_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->bind_param("i", $reviewId);
    $stmt->execute();
    $stmt->close();

    header("Location: reviews.php?success=deleted");
    exit;
}


/*
|--------------------------------------------------------------------------
| Update Review Status
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    $reviewId = (int)($_POST['review_id'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';

    $allowedStatuses = ['Pending', 'Approved', 'Rejected'];

    if (
        $reviewId > 0 &&
        in_array($status, $allowedStatuses, true)
    ) {

        $stmt = $conn->prepare("
            UPDATE reviews
            SET status = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "si",
            $status,
            $reviewId
        );

        $stmt->execute();
        $stmt->close();
    }

    header("Location: reviews.php?success=status");
    exit;
}


/*
|--------------------------------------------------------------------------
| Update Featured
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_featured'])) {

    $reviewId = (int)($_POST['review_id'] ?? 0);
    $isFeatured = (int)($_POST['is_featured'] ?? 0);

    if ($reviewId > 0) {

        $stmt = $conn->prepare("
            UPDATE reviews
            SET is_featured = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ii",
            $isFeatured,
            $reviewId
        );

        $stmt->execute();
        $stmt->close();
    }

    header("Location: reviews.php?success=featured");
    exit;
}


/*
|--------------------------------------------------------------------------
| Update Admin Reply
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_reply'])) {

    $reviewId = (int)($_POST['review_id'] ?? 0);
    $adminReply = trim($_POST['admin_reply'] ?? '');

    if ($reviewId > 0) {

        $stmt = $conn->prepare("
            UPDATE reviews
            SET admin_reply = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "si",
            $adminReply,
            $reviewId
        );

        $stmt->execute();
        $stmt->close();
    }

    header("Location: reviews.php?success=reply");
    exit;
}


/*
|--------------------------------------------------------------------------
| AJAX Review Details
|--------------------------------------------------------------------------
*/

if (isset($_GET['review_details']) && is_numeric($_GET['review_details'])) {

    $reviewId = (int)$_GET['review_details'];

    $stmt = $conn->prepare("
        SELECT
            r.*,
            p.name AS product_name
        FROM reviews r
        LEFT JOIN products p
            ON r.product_id = p.id
        WHERE r.id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $reviewId);
    $stmt->execute();

    $result = $stmt->get_result();
    $review = $result->fetch_assoc();

    $stmt->close();

    if (!$review) {
        http_response_code(404);

        echo '<div style="padding:30px;text-align:center;">Review not found.</div>';
        exit;
    }

    ?>

    <div class="review-details">

        <div class="review-detail-header">

            <div>
                <h4>
                    <?= e($review['product_name'] ?? 'Unknown Product') ?>
                </h4>

                <div class="review-stars-large">
                    <?= getRatingStars($review['rating']) ?>
                </div>
            </div>

            <span class="review-status-badge status-<?= strtolower(e($review['status'])) ?>">
                <?= e($review['status']) ?>
            </span>

        </div>


        <div class="review-detail-grid">

            <div class="review-detail-item">
                <span>Customer</span>
                <strong>
                    <?= e($review['customer_name']) ?>
                </strong>
            </div>

            <div class="review-detail-item">
                <span>Email</span>
                <strong>
                    <?= e($review['customer_email'] ?: '—') ?>
                </strong>
            </div>

            <div class="review-detail-item">
                <span>Rating</span>
                <strong>
                    <?= (int)$review['rating'] ?> / 5
                </strong>
            </div>

            <div class="review-detail-item">
                <span>Featured</span>
                <strong>
                    <?= $review['is_featured'] ? 'Yes' : 'No' ?>
                </strong>
            </div>

            <div class="review-detail-item">
                <span>Created</span>
                <strong>
                    <?= e(date('M d, Y h:i A', strtotime($review['created_at']))) ?>
                </strong>
            </div>

            <div class="review-detail-item">
                <span>Updated</span>
                <strong>
                    <?= e(date('M d, Y h:i A', strtotime($review['updated_at']))) ?>
                </strong>
            </div>

        </div>


        <div class="review-detail-section">

            <h5>Customer Review</h5>

            <div class="review-text-box">
                <?= nl2br(e($review['review_text'])) ?>
            </div>

        </div>


        <div class="review-detail-section">

            <h5>Admin Reply</h5>

            <div class="review-text-box admin-reply-box">

                <?php if (!empty($review['admin_reply'])): ?>

                    <?= nl2br(e($review['admin_reply'])) ?>

                <?php else: ?>

                    <span class="empty-review-text">
                        No admin reply yet.
                    </span>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <?php

    exit;
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalReviews = 0;
$pendingReviews = 0;
$approvedReviews = 0;
$rejectedReviews = 0;
$featuredReviews = 0;
$averageRating = 0;

$result = $conn->query("
    SELECT
        COUNT(*) AS total_reviews,
        SUM(status = 'Pending') AS pending_reviews,
        SUM(status = 'Approved') AS approved_reviews,
        SUM(status = 'Rejected') AS rejected_reviews,
        SUM(is_featured = 1) AS featured_reviews,
        COALESCE(AVG(rating), 0) AS average_rating
    FROM reviews
");

if ($result) {

    $stats = $result->fetch_assoc();

    $totalReviews = (int)($stats['total_reviews'] ?? 0);
    $pendingReviews = (int)($stats['pending_reviews'] ?? 0);
    $approvedReviews = (int)($stats['approved_reviews'] ?? 0);
    $rejectedReviews = (int)($stats['rejected_reviews'] ?? 0);
    $featuredReviews = (int)($stats['featured_reviews'] ?? 0);
    $averageRating = (float)($stats['average_rating'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$ratingFilter = $_GET['rating'] ?? '';

$where = [];
$params = [];
$types = '';


if ($search !== '') {

    $where[] = "
        (
            r.customer_name LIKE ?
            OR r.customer_email LIKE ?
            OR r.review_text LIKE ?
            OR p.name LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'ssss';
}


if (
    in_array(
        $statusFilter,
        ['Pending', 'Approved', 'Rejected'],
        true
    )
) {

    $where[] = "r.status = ?";

    $params[] = $statusFilter;

    $types .= 's';
}


if (
    $ratingFilter !== '' &&
    is_numeric($ratingFilter) &&
    (int)$ratingFilter >= 1 &&
    (int)$ratingFilter <= 5
) {

    $where[] = "r.rating = ?";

    $params[] = (int)$ratingFilter;

    $types .= 'i';
}


$whereSql = '';

if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}


/*
|--------------------------------------------------------------------------
| Reviews Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        r.*,
        p.name AS product_name
    FROM reviews r
    LEFT JOIN products p
        ON r.product_id = p.id
    $whereSql
    ORDER BY r.created_at DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$reviewsResult = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Reviews & Ratings | OSCAR CMS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="assets/css/admin.css"
    >

    <style>

        /* =====================================================
           REVIEWS PAGE
        ===================================================== */

        .reviews-page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 25px;
        }

        .reviews-page-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }

        .reviews-page-header p {
            margin: 7px 0 0;
            color: #6b7280;
            font-size: 14px;
        }


        /* =====================================================
           STAT CARDS
        ===================================================== */

        .review-stat-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .review-stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px;
            min-width: 0;
        }

        .review-stat-label {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .review-stat-value {
            color: #111827;
            font-size: 25px;
            font-weight: 700;
            line-height: 1.2;
        }

        .review-stat-stars {
            color: #d99a16;
            letter-spacing: 2px;
            font-size: 16px;
            margin-top: 4px;
        }


        /* =====================================================
           FILTER CARD
        ===================================================== */

        .reviews-filter-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 20px;
        }

        .reviews-filter-form {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) 180px 150px auto;
            gap: 12px;
            align-items: end;
        }

        .review-filter-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #4b5563;
        }

        .review-filter-group input,
        .review-filter-group select {
            width: 100%;
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0 12px;
            background: #fff;
            color: #111827;
            outline: none;
        }

        .review-filter-group input:focus,
        .review-filter-group select:focus {
            border-color: #111827;
        }

        .review-filter-actions {
            display: flex;
            gap: 8px;
        }

        .review-filter-btn {
            height: 42px;
            padding: 0 18px;
            border: 0;
            border-radius: 8px;
            background: #111827;
            color: #fff;
            font-weight: 600;
        }

        .review-clear-btn {
            height: 42px;
            padding: 0 18px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }


        /* =====================================================
           TABLE
        ===================================================== */

        .reviews-table-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
        }

        .reviews-table-header {
            padding: 18px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .reviews-table-header h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
        }

        .reviews-count {
            color: #6b7280;
            font-size: 13px;
        }

        .reviews-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .reviews-table {
            width: 100%;
            min-width: 1100px;
            border-collapse: collapse;
        }

        .reviews-table th {
            padding: 13px 16px;
            background: #f9fafb;
            color: #6b7280;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            white-space: nowrap;
            border-bottom: 1px solid #e5e7eb;
        }

        .reviews-table td {
            padding: 15px 16px;
            border-bottom: 1px solid #f0f1f3;
            vertical-align: middle;
            font-size: 13px;
            color: #374151;
        }

        .reviews-table tbody tr:hover {
            background: #fafafa;
        }

        .review-product {
            max-width: 190px;
            font-weight: 600;
            color: #111827;
        }

        .review-customer-name {
            font-weight: 600;
            color: #111827;
        }

        .review-customer-email {
            color: #6b7280;
            font-size: 12px;
            margin-top: 3px;
        }

        .review-text-preview {
            max-width: 240px;
            color: #6b7280;
            line-height: 1.5;
        }

        .review-stars {
            color: #d99a16;
            white-space: nowrap;
            letter-spacing: 1px;
        }

        .review-rating-number {
            color: #6b7280;
            margin-left: 4px;
            font-size: 12px;
        }


        /* =====================================================
           STATUS
        ===================================================== */

        .review-status-badge {
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

        .status-approved {
            background: #ecfdf5;
            color: #047857;
        }

        .status-rejected {
            background: #fef2f2;
            color: #b91c1c;
        }


        /* =====================================================
           FEATURED
        ===================================================== */

        .featured-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
        }

        .featured-badge.active {
            color: #b7791f;
        }


        /* =====================================================
           ACTIONS
        ===================================================== */

        .review-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .review-action-btn {
            width: 34px;
            height: 34px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #374151;
            text-decoration: none;
            cursor: pointer;
        }

        .review-action-btn:hover {
            background: #f9fafb;
        }

        .review-action-danger:hover {
            color: #dc2626;
            border-color: #fecaca;
            background: #fef2f2;
        }

        .review-action-primary:hover {
            color: #2563eb;
            border-color: #bfdbfe;
            background: #eff6ff;
        }


        /* =====================================================
           MODAL
        ===================================================== */

        .review-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, .55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 9999;
        }

        .review-modal-overlay.show {
            display: flex;
        }

        .review-modal {
            width: min(720px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 25px 70px rgba(0, 0, 0, .2);
        }

        .review-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 18px 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .review-modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }

        .review-modal-close {
            width: 34px;
            height: 34px;
            border: 0;
            background: #f3f4f6;
            border-radius: 8px;
            cursor: pointer;
            font-size: 20px;
            color: #374151;
        }

        .review-modal-body {
            padding: 20px;
        }

        .review-details {
            width: 100%;
        }

        .review-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 22px;
        }

        .review-detail-header h4 {
            margin: 0 0 7px;
            font-size: 19px;
            color: #111827;
        }

        .review-stars-large {
            color: #d99a16;
            font-size: 21px;
            letter-spacing: 2px;
        }

        .review-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 22px;
        }

        .review-detail-item {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 13px;
            background: #fafafa;
        }

        .review-detail-item span {
            display: block;
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .review-detail-item strong {
            display: block;
            color: #111827;
            font-size: 13px;
            word-break: break-word;
        }

        .review-detail-section {
            margin-top: 20px;
        }

        .review-detail-section h5 {
            margin: 0 0 8px;
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }

        .review-text-box {
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fafafa;
            color: #4b5563;
            line-height: 1.7;
            font-size: 13px;
        }

        .admin-reply-box {
            background: #f8fafc;
        }

        .empty-review-text {
            color: #9ca3af;
            font-style: italic;
        }


        /* =====================================================
           EMPTY STATE
        ===================================================== */

        .reviews-empty {
            text-align: center;
            padding: 55px 20px;
            color: #6b7280;
        }

        .reviews-empty-icon {
            font-size: 38px;
            margin-bottom: 10px;
        }

        .reviews-empty h4 {
            margin: 0 0 5px;
            color: #111827;
            font-size: 17px;
        }

        .reviews-empty p {
            margin: 0;
            font-size: 13px;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1200px) {

            .review-stat-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .reviews-filter-form {
                grid-template-columns: 1fr 1fr;
            }

            .review-filter-actions {
                grid-column: 1 / -1;
            }
        }


        @media (max-width: 768px) {

            .reviews-page-header {
                flex-direction: column;
            }

            .reviews-page-header h1 {
                font-size: 23px;
            }

            .review-stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .reviews-filter-form {
                grid-template-columns: 1fr;
            }

            .review-filter-actions {
                grid-column: auto;
            }

            .review-filter-btn,
            .review-clear-btn {
                flex: 1;
            }

            .review-detail-grid {
                grid-template-columns: 1fr;
            }

            .review-detail-header {
                flex-direction: column;
            }
        }


        @media (max-width: 480px) {

            .review-stat-grid {
                grid-template-columns: 1fr;
            }

            .reviews-filter-card {
                padding: 14px;
            }

            .reviews-table-header {
                padding: 15px;
            }

            .review-modal-overlay {
                padding: 10px;
            }

            .review-modal-body {
                padding: 15px;
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


            <!-- =================================================
                 PAGE HEADER
            ================================================== -->

            <div class="reviews-page-header">

                <div>

                    <h1>Reviews & Ratings</h1>

                    <p>
                        Manage customer reviews, ratings and feedback.
                    </p>

                </div>

            </div>


            <!-- =================================================
                 STATISTICS
            ================================================== -->

            <div class="review-stat-grid">

                <div class="review-stat-card">

                    <div class="review-stat-label">
                        Total Reviews
                    </div>

                    <div class="review-stat-value">
                        <?= number_format($totalReviews) ?>
                    </div>

                </div>


                <div class="review-stat-card">

                    <div class="review-stat-label">
                        Pending
                    </div>

                    <div class="review-stat-value">
                        <?= number_format($pendingReviews) ?>
                    </div>

                </div>


                <div class="review-stat-card">

                    <div class="review-stat-label">
                        Approved
                    </div>

                    <div class="review-stat-value">
                        <?= number_format($approvedReviews) ?>
                    </div>

                </div>


                <div class="review-stat-card">

                    <div class="review-stat-label">
                        Average Rating
                    </div>

                    <div class="review-stat-value">
                        <?= number_format($averageRating, 1) ?>
                    </div>

                    <div class="review-stat-stars">
                        <?= getRatingStars(round($averageRating)) ?>
                    </div>

                </div>


                <div class="review-stat-card">

                    <div class="review-stat-label">
                        Featured Reviews
                    </div>

                    <div class="review-stat-value">
                        <?= number_format($featuredReviews) ?>
                    </div>

                </div>

            </div>


            <!-- =================================================
                 FILTERS
            ================================================== -->

            <div class="reviews-filter-card">

                <form
                    method="GET"
                    class="reviews-filter-form"
                >

                    <div class="review-filter-group">

                        <label>
                            Search
                        </label>

                        <input
                            type="text"
                            name="search"
                            value="<?= e($search) ?>"
                            placeholder="Search customer, product or review..."
                        >

                    </div>


                    <div class="review-filter-group">

                        <label>
                            Status
                        </label>

                        <select name="status">

                            <option value="">
                                All Statuses
                            </option>

                            <option
                                value="Pending"
                                <?= $statusFilter === 'Pending' ? 'selected' : '' ?>
                            >
                                Pending
                            </option>

                            <option
                                value="Approved"
                                <?= $statusFilter === 'Approved' ? 'selected' : '' ?>
                            >
                                Approved
                            </option>

                            <option
                                value="Rejected"
                                <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>
                            >
                                Rejected
                            </option>

                        </select>

                    </div>


                    <div class="review-filter-group">

                        <label>
                            Rating
                        </label>

                        <select name="rating">

                            <option value="">
                                All Ratings
                            </option>

                            <?php for ($i = 5; $i >= 1; $i++): ?>

                                <option
                                    value="<?= $i ?>"
                                    <?= (string)$ratingFilter === (string)$i ? 'selected' : '' ?>
                                >
                                    <?= $i ?> Star<?= $i > 1 ? 's' : '' ?>
                                </option>

                            <?php endfor; ?>

                        </select>

                    </div>


                    <div class="review-filter-actions">

                        <button
                            type="submit"
                            class="review-filter-btn"
                        >
                            Filter
                        </button>

                        <a
                            href="reviews.php"
                            class="review-clear-btn"
                        >
                            Clear
                        </a>

                    </div>

                </form>

            </div>


            <!-- =================================================
                 REVIEWS TABLE
            ================================================== -->

            <div class="reviews-table-card">

                <div class="reviews-table-header">

                    <h3>
                        Customer Reviews
                    </h3>

                    <span class="reviews-count">

                        <?php

                        $visibleReviews = $reviewsResult->num_rows;

                        echo number_format($visibleReviews);

                        ?>

                        review<?= $visibleReviews == 1 ? '' : 's' ?>

                    </span>

                </div>


                <div class="reviews-table-wrapper">

                    <?php if ($reviewsResult->num_rows > 0): ?>

                        <table class="reviews-table">

                            <thead>

                            <tr>

                                <th>
                                    Product
                                </th>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Rating
                                </th>

                                <th>
                                    Review
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Featured
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

                            <?php while ($review = $reviewsResult->fetch_assoc()): ?>

                                <tr>

                                    <!-- Product -->

                                    <td>

                                        <div class="review-product">

                                            <?= e($review['product_name'] ?? 'Unknown Product') ?>

                                        </div>

                                    </td>


                                    <!-- Customer -->

                                    <td>

                                        <div class="review-customer-name">

                                            <?= e($review['customer_name']) ?>

                                        </div>

                                        <?php if (!empty($review['customer_email'])): ?>

                                            <div class="review-customer-email">

                                                <?= e($review['customer_email']) ?>

                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Rating -->

                                    <td>

                                        <span class="review-stars">

                                            <?= getRatingStars($review['rating']) ?>

                                        </span>

                                        <span class="review-rating-number">

                                            <?= (int)$review['rating'] ?>/5

                                        </span>

                                    </td>


                                    <!-- Review -->

                                    <td>

                                        <div class="review-text-preview">

                                            <?php

                                            $reviewText = trim($review['review_text']);

                                            if (mb_strlen($reviewText) > 90) {

                                                echo e(
                                                    mb_substr($reviewText, 0, 90)
                                                    . '...'
                                                );

                                            } else {

                                                echo e($reviewText);

                                            }

                                            ?>

                                        </div>

                                    </td>


                                    <!-- Status -->

                                    <td>

                                        <form
                                            method="POST"
                                            style="margin:0;"
                                        >

                                            <input
                                                type="hidden"
                                                name="review_id"
                                                value="<?= (int)$review['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="update_status"
                                                value="1"
                                            >

                                            <select
                                                name="status"
                                                onchange="this.form.submit()"
                                                class="form-select form-select-sm"
                                                style="width:120px;"
                                            >

                                                <option
                                                    value="Pending"
                                                    <?= $review['status'] === 'Pending' ? 'selected' : '' ?>
                                                >
                                                    Pending
                                                </option>

                                                <option
                                                    value="Approved"
                                                    <?= $review['status'] === 'Approved' ? 'selected' : '' ?>
                                                >
                                                    Approved
                                                </option>

                                                <option
                                                    value="Rejected"
                                                    <?= $review['status'] === 'Rejected' ? 'selected' : '' ?>
                                                >
                                                    Rejected
                                                </option>

                                            </select>

                                        </form>

                                    </td>


                                    <!-- Featured -->

                                    <td>

                                        <form
                                            method="POST"
                                            style="margin:0;"
                                        >

                                            <input
                                                type="hidden"
                                                name="review_id"
                                                value="<?= (int)$review['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="update_featured"
                                                value="1"
                                            >

                                            <input
                                                type="hidden"
                                                name="is_featured"
                                                value="<?= $review['is_featured'] ? 0 : 1 ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="featured-badge <?= $review['is_featured'] ? 'active' : '' ?>"
                                                style="border:0;background:none;cursor:pointer;"
                                                title="Toggle Featured"
                                            >

                                                <?= $review['is_featured'] ? '★ Featured' : '☆ Feature' ?>

                                            </button>

                                        </form>

                                    </td>


                                    <!-- Date -->

                                    <td>

                                        <?= e(
                                            date(
                                                'M d, Y',
                                                strtotime($review['created_at'])
                                            )
                                        ) ?>

                                    </td>


                                    <!-- Actions -->

                                    <td>

                                        <div class="review-actions">

                                            <button
                                                type="button"
                                                class="review-action-btn review-action-primary"
                                                title="View Review"
                                                onclick="openReviewDetails(<?= (int)$review['id'] ?>)"
                                            >
                                                👁
                                            </button>


                                            <button
                                                type="button"
                                                class="review-action-btn"
                                                title="Reply"
                                                onclick="openReplyModal(
                                                    <?= (int)$review['id'] ?>,
                                                    <?= htmlspecialchars(json_encode($review['customer_name']), ENT_QUOTES, 'UTF-8') ?>,
                                                    <?= htmlspecialchars(json_encode($review['admin_reply'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                )"
                                            >
                                                💬
                                            </button>


                                            <a
                                                href="reviews.php?delete=<?= (int)$review['id'] ?>"
                                                class="review-action-btn review-action-danger"
                                                title="Delete"
                                                onclick="return confirm('Are you sure you want to delete this review?');"
                                            >
                                                🗑
                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                            </tbody>

                        </table>

                    <?php else: ?>

                        <div class="reviews-empty">

                            <div class="reviews-empty-icon">
                                ⭐
                            </div>

                            <h4>
                                No Reviews Found
                            </h4>

                            <p>
                                There are no reviews matching your current filters.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


        </main>


        <?php include "../includes/footer.php"; ?>

    </div>

</div>


<!-- =========================================================
     VIEW REVIEW MODAL
========================================================== -->

<div
    class="review-modal-overlay"
    id="reviewDetailsModal"
>

    <div class="review-modal">

        <div class="review-modal-header">

            <h3>
                Review Details
            </h3>

            <button
                type="button"
                class="review-modal-close"
                onclick="closeReviewDetails()"
            >
                ×
            </button>

        </div>


        <div
            class="review-modal-body"
            id="reviewDetailsContent"
        >

            <div style="text-align:center;padding:30px;color:#6b7280;">
                Loading...
            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     ADMIN REPLY MODAL
========================================================== -->

<div
    class="review-modal-overlay"
    id="replyModal"
>

    <div class="review-modal">

        <div class="review-modal-header">

            <h3>
                Admin Reply
            </h3>

            <button
                type="button"
                class="review-modal-close"
                onclick="closeReplyModal()"
            >
                ×
            </button>

        </div>


        <div class="review-modal-body">

            <form
                method="POST"
                action="reviews.php"
            >

                <input
                    type="hidden"
                    name="update_reply"
                    value="1"
                >

                <input
                    type="hidden"
                    name="review_id"
                    id="replyReviewId"
                    value=""
                >


                <div style="margin-bottom:15px;">

                    <label
                        style="
                            display:block;
                            font-size:13px;
                            font-weight:600;
                            color:#374151;
                            margin-bottom:7px;
                        "
                    >
                        Customer
                    </label>

                    <input
                        type="text"
                        id="replyCustomerName"
                        readonly
                        style="
                            width:100%;
                            height:42px;
                            border:1px solid #e5e7eb;
                            border-radius:8px;
                            padding:0 12px;
                            background:#f9fafb;
                            color:#374151;
                        "
                    >

                </div>


                <div style="margin-bottom:15px;">

                    <label
                        style="
                            display:block;
                            font-size:13px;
                            font-weight:600;
                            color:#374151;
                            margin-bottom:7px;
                        "
                    >
                        Admin Reply
                    </label>

                    <textarea
                        name="admin_reply"
                        id="adminReply"
                        rows="6"
                        placeholder="Write your reply to the customer..."
                        style="
                            width:100%;
                            border:1px solid #d1d5db;
                            border-radius:8px;
                            padding:12px;
                            resize:vertical;
                            outline:none;
                        "
                    ></textarea>

                </div>


                <div
                    style="
                        display:flex;
                        justify-content:flex-end;
                        gap:8px;
                    "
                >

                    <button
                        type="button"
                        class="review-clear-btn"
                        onclick="closeReplyModal()"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="review-filter-btn"
                    >
                        Save Reply
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

    /*
    |--------------------------------------------------------------------------
    | View Review Details
    |--------------------------------------------------------------------------
    */

    function openReviewDetails(reviewId) {

        const modal = document.getElementById('reviewDetailsModal');

        const content = document.getElementById('reviewDetailsContent');

        modal.classList.add('show');

        content.innerHTML = `
            <div style="text-align:center;padding:30px;color:#6b7280;">
                Loading...
            </div>
        `;

        fetch(
            'reviews.php?review_details=' + encodeURIComponent(reviewId),
            {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }
        )
        .then(response => {

            if (!response.ok) {
                throw new Error('Unable to load review.');
            }

            return response.text();

        })
        .then(html => {

            content.innerHTML = html;

        })
        .catch(error => {

            content.innerHTML = `
                <div style="
                    padding:30px;
                    text-align:center;
                    color:#dc2626;
                ">
                    Unable to load review details.
                </div>
            `;

        });
    }


    /*
    |--------------------------------------------------------------------------
    | Close Review Details
    |--------------------------------------------------------------------------
    */

    function closeReviewDetails() {

        document
            .getElementById('reviewDetailsModal')
            .classList.remove('show');

    }


    /*
    |--------------------------------------------------------------------------
    | Open Reply Modal
    |--------------------------------------------------------------------------
    */

    function openReplyModal(reviewId, customerName, existingReply) {

        document.getElementById('replyReviewId').value = reviewId;

        document.getElementById('replyCustomerName').value = customerName || '';

        document.getElementById('adminReply').value = existingReply || '';

        document
            .getElementById('replyModal')
            .classList.add('show');
    }


    /*
    |--------------------------------------------------------------------------
    | Close Reply Modal
    |--------------------------------------------------------------------------
    */

    function closeReplyModal() {

        document
            .getElementById('replyModal')
            .classList.remove('show');

    }


    /*
    |--------------------------------------------------------------------------
    | Close Modal When Clicking Overlay
    |--------------------------------------------------------------------------
    */

    document
        .getElementById('reviewDetailsModal')
        .addEventListener('click', function(event) {

            if (event.target === this) {
                closeReviewDetails();
            }

        });


    document
        .getElementById('replyModal')
        .addEventListener('click', function(event) {

            if (event.target === this) {
                closeReplyModal();
            }

        });


    /*
    |--------------------------------------------------------------------------
    | ESC Key
    |--------------------------------------------------------------------------
    */

    document.addEventListener('keydown', function(event) {

        if (event.key === 'Escape') {

            closeReviewDetails();

            closeReplyModal();

        }

    });

</script>


</body>

</html>

<?php

$stmt->close();

?>