<?php

require_once "../config/database.php";
require_once "../includes/auth.php";

$message = "";
$messageType = "";

/*
|--------------------------------------------------------------------------
| DELETE CATEGORY
|--------------------------------------------------------------------------
*/

if (isset($_GET['delete'])) {

    $id = (int) $_GET['delete'];

    if ($id > 0) {

        try {

            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);

            $message = "Category deleted successfully.";
            $messageType = "success";

        } catch (PDOException $e) {

            $message = "Unable to delete category.";
            $messageType = "danger";
        }
    }
}

/*
|--------------------------------------------------------------------------
| ADD / UPDATE CATEGORY
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    $name = trim($_POST['name'] ?? "");
    $slug = trim($_POST['slug'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $status = isset($_POST['status']) ? 1 : 0;

    if ($name === "") {

        $message = "Category name is required.";
        $messageType = "danger";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Generate slug automatically if empty
        |--------------------------------------------------------------------------
        */

        if ($slug === "") {

            $slug = strtolower($name);

            $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

            $slug = trim($slug, '-');
        }

        try {

            if ($id > 0) {

                /*
                |--------------------------------------------------------------------------
                | UPDATE
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    UPDATE categories
                    SET
                        name = ?,
                        slug = ?,
                        description = ?,
                        status = ?,
                        sort_order = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $name,
                    $slug,
                    $description,
                    $status,
                    $sortOrder,
                    $id
                ]);

                $message = "Category updated successfully.";
                $messageType = "success";

            } else {

                /*
                |--------------------------------------------------------------------------
                | INSERT
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    INSERT INTO categories
                    (
                        name,
                        slug,
                        description,
                        status,
                        sort_order
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $name,
                    $slug,
                    $description,
                    $status,
                    $sortOrder
                ]);

                $message = "Category added successfully.";
                $messageType = "success";
            }

        } catch (PDOException $e) {

            if ($e->getCode() == 23000) {

                $message = "This category slug already exists.";
                $messageType = "danger";

            } else {

                $message = "Something went wrong. Please try again.";
                $messageType = "danger";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| EDIT CATEGORY
|--------------------------------------------------------------------------
*/

$editCategory = null;

if (isset($_GET['edit'])) {

    $id = (int) $_GET['edit'];

    if ($id > 0) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM categories
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        $editCategory = $stmt->fetch();
    }
}

/*
|--------------------------------------------------------------------------
| FETCH CATEGORIES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT *
    FROM categories
    ORDER BY sort_order ASC, id DESC
");

$categories = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Categories | Sports CMS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="assets/css/admin.css"
    >

    <style>


.main-content {
    margin-left: 260px;
    min-height: 100vh;
    width: calc(100% - 260px);
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        width: 100%;
    }
}





        .page-content {
            padding: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }

        .page-header p {
            margin: 5px 0 0;
            color: #777;
        }

        .category-form-card,
        .category-table-card {
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .category-form-card h4,
        .category-table-card h4 {
            font-weight: 700;
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
        }

        .form-control,
        .form-select {
            min-height: 46px;
            border-radius: 10px;
        }

        textarea.form-control {
            min-height: 110px;
        }

        .btn-primary {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
        }

        .btn-secondary {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
        }

        .table {
            vertical-align: middle;
        }

        .category-name {
            font-weight: 700;
        }

        .category-slug {
            font-size: 13px;
            color: #777;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-buttons {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        .action-buttons a {
            text-decoration: none;
        }

        @media (max-width: 768px) {

            .page-content {
                padding: 20px 15px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .category-form-card,
            .category-table-card {
                padding: 18px;
            }

            .table-responsive {
                overflow-x: auto;
            }

        }

    </style>

</head>

<body>

<?php include "../includes/sidebar.php"; ?>

<div class="main-area">

    <?php include "../includes/header.php"; ?>

    <main class="page-content">

        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>

                <h1>Categories</h1>

                <p>
                    Manage product categories for your WB Sports store.
                </p>

            </div>

        </div>


        <!-- MESSAGE -->

        <?php if ($message !== ""): ?>

            <div class="alert alert-<?= htmlspecialchars($messageType) ?>">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <!-- ADD / EDIT FORM -->

        <div class="category-form-card">

            <h4>
                <?= $editCategory ? "Edit Category" : "Add New Category" ?>
            </h4>

            <form method="POST">

                <?php if ($editCategory): ?>

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $editCategory['id'] ?>"
                    >

                <?php endif; ?>


                <div class="row g-4">

                    <!-- NAME -->

                    <div class="col-md-6">

                        <label class="form-label">
                            Category Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            placeholder="e.g. Cricket Gloves"
                            value="<?= htmlspecialchars($editCategory['name'] ?? '') ?>"
                            required
                        >

                    </div>


                    <!-- SLUG -->

                    <div class="col-md-6">

                        <label class="form-label">
                            Slug
                        </label>

                        <input
                            type="text"
                            name="slug"
                            class="form-control"
                            placeholder="e.g. cricket-gloves"
                            value="<?= htmlspecialchars($editCategory['slug'] ?? '') ?>"
                        >

                        <small class="text-muted">
                            Leave empty to generate automatically.
                        </small>

                    </div>


                    <!-- DESCRIPTION -->

                    <div class="col-12">

                        <label class="form-label">
                            Description
                        </label>

                        <textarea
                            name="description"
                            class="form-control"
                            placeholder="Short category description..."
                        ><?= htmlspecialchars($editCategory['description'] ?? '') ?></textarea>

                    </div>


                    <!-- SORT ORDER -->

                    <div class="col-md-4">

                        <label class="form-label">
                            Sort Order
                        </label>

                        <input
                            type="number"
                            name="sort_order"
                            class="form-control"
                            value="<?= htmlspecialchars($editCategory['sort_order'] ?? '0') ?>"
                        >

                    </div>


                    <!-- STATUS -->

                    <div class="col-md-4">

                        <label class="form-label">
                            Status
                        </label>

                        <select
                            name="status"
                            class="form-select"
                        >

                            <option
                                value="1"
                                <?= (!$editCategory || $editCategory['status'] == 1) ? 'selected' : '' ?>
                            >
                                Active
                            </option>

                            <option
                                value="0"
                                <?= ($editCategory && $editCategory['status'] == 0) ? 'selected' : '' ?>
                            >
                                Inactive
                            </option>

                        </select>

                    </div>


                    <!-- BUTTONS -->

                    <div class="col-md-4 d-flex align-items-end gap-2">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <?= $editCategory ? "Update Category" : "Add Category" ?>
                        </button>

                        <?php if ($editCategory): ?>

                            <a
                                href="categories.php"
                                class="btn btn-secondary"
                            >
                                Cancel
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            </form>

        </div>


        <!-- CATEGORY LIST -->

        <div class="category-table-card">

            <h4>All Categories</h4>

            <?php if (count($categories) > 0): ?>

                <div class="table-responsive">

                    <table class="table table-hover">

                        <thead>

                            <tr>

                                <th>ID</th>

                                <th>Category</th>

                                <th>Slug</th>

                                <th>Status</th>

                                <th>Order</th>

                                <th>Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($categories as $category): ?>

                            <tr>

                                <td>
                                    #<?= (int) $category['id'] ?>
                                </td>

                                <td>

                                    <div class="category-name">
                                        <?= htmlspecialchars($category['name']) ?>
                                    </div>

                                    <?php if (!empty($category['description'])): ?>

                                        <small class="text-muted">
                                            <?= htmlspecialchars($category['description']) ?>
                                        </small>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <span class="category-slug">
                                        <?= htmlspecialchars($category['slug']) ?>
                                    </span>

                                </td>

                                <td>

                                    <?php if ($category['status'] == 1): ?>

                                        <span class="status-badge status-active">
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="status-badge status-inactive">
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= (int) $category['sort_order'] ?>
                                </td>

                                <td>

                                    <div class="action-buttons">

                                        <a
                                            href="categories.php?edit=<?= (int) $category['id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Edit
                                        </a>

                                        <a
                                            href="categories.php?delete=<?= (int) $category['id'] ?>"
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Are you sure you want to delete this category?');"
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

                <div class="text-center py-5">

                    <h5>No categories found</h5>

                    <p class="text-muted mb-0">
                        Add your first WB Sports category using the form above.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>


<script src="assets/js/admin.js"></script>

</body>

</html>