<?php

require_once "../config/database.php";
require_once "../includes/auth.php";

$message = "";
$messageType = "";


/*
|--------------------------------------------------------------------------
| DELETE SUB CATEGORY
|--------------------------------------------------------------------------
*/

if (isset($_GET['delete'])) {

    $id = (int) $_GET['delete'];

    if ($id > 0) {

        try {

            $stmt = $pdo->prepare("
                DELETE FROM sub_categories
                WHERE id = ?
            ");

            $stmt->execute([$id]);

            $message = "Sub category deleted successfully.";
            $messageType = "success";

        } catch (PDOException $e) {

            $message = "Unable to delete sub category.";
            $messageType = "danger";
        }
    }
}


/*
|--------------------------------------------------------------------------
| ADD / UPDATE SUB CATEGORY
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? "");
    $slug = trim($_POST['slug'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $status = isset($_POST['status']) ? 1 : 0;


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($categoryId <= 0) {

        $message = "Please select a category.";
        $messageType = "danger";

    } elseif ($name === "") {

        $message = "Sub category name is required.";
        $messageType = "danger";

    } else {


        /*
        |--------------------------------------------------------------------------
        | GENERATE SLUG
        |--------------------------------------------------------------------------
        */

        if ($slug === "") {

            $slug = strtolower($name);

            $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

            $slug = trim($slug, '-');
        }


        try {

            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            if ($id > 0) {

                $stmt = $pdo->prepare("
                    UPDATE sub_categories
                    SET
                        category_id = ?,
                        name = ?,
                        slug = ?,
                        description = ?,
                        status = ?,
                        sort_order = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $categoryId,
                    $name,
                    $slug,
                    $description,
                    $status,
                    $sortOrder,
                    $id
                ]);

                $message = "Sub category updated successfully.";
                $messageType = "success";

            }

            /*
            |--------------------------------------------------------------------------
            | INSERT
            |--------------------------------------------------------------------------
            */

            else {

                $stmt = $pdo->prepare("
                    INSERT INTO sub_categories
                    (
                        category_id,
                        name,
                        slug,
                        description,
                        status,
                        sort_order
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $categoryId,
                    $name,
                    $slug,
                    $description,
                    $status,
                    $sortOrder
                ]);

                $message = "Sub category added successfully.";
                $messageType = "success";
            }

        } catch (PDOException $e) {

            if ($e->getCode() == 23000) {

                $message = "This sub category slug already exists.";
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
| EDIT SUB CATEGORY
|--------------------------------------------------------------------------
*/

$editSubCategory = null;

if (isset($_GET['edit'])) {

    $id = (int) $_GET['edit'];

    if ($id > 0) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM sub_categories
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        $editSubCategory = $stmt->fetch();
    }
}


/*
|--------------------------------------------------------------------------
| FETCH CATEGORIES FOR DROPDOWN
|--------------------------------------------------------------------------
*/

$categoryStmt = $pdo->query("
    SELECT id, name
    FROM categories
    ORDER BY sort_order ASC, name ASC
");

$categories = $categoryStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| FETCH SUB CATEGORIES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        sc.id,
        sc.category_id,
        sc.name,
        sc.slug,
        sc.description,
        sc.status,
        sc.sort_order,
        c.name AS category_name
    FROM sub_categories sc
    INNER JOIN categories c
        ON sc.category_id = c.id
    ORDER BY
        c.sort_order ASC,
        sc.sort_order ASC,
        sc.id DESC
");

$subCategories = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Sub Categories | Sports CMS</title>


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
            flex: 1;
            min-width: 0;
        }


        .page-header {
            margin-bottom: 25px;
        }


        .page-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
        }


        .page-header p {
            margin: 7px 0 0;
            color: #6b7280;
            font-size: 14px;
        }


        .sub-category-form-card,
        .sub-category-table-card {

            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 25px;

        }


        .sub-category-form-card h4,
        .sub-category-table-card h4 {

            margin: 0 0 20px;
            font-size: 17px;
            font-weight: 800;

        }


        .form-label {
            font-weight: 600;
            font-size: 14px;
        }


        .form-control,
        .form-select {

            min-height: 46px;
            border-radius: 10px;

        }


        textarea.form-control {
            min-height: 110px;
        }


        .form-control:focus,
        .form-select:focus {

            border-color: #9ca3af;
            box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.08);

        }


        .btn-primary {

            background: #111827;
            border-color: #111827;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;

        }


        .btn-primary:hover {

            background: #1f2937;
            border-color: #1f2937;

        }


        .btn-secondary {

            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;

        }


        .table {

            margin-bottom: 0;
            vertical-align: middle;

        }


        .table th {

            font-size: 12px;
            font-weight: 800;
            color: #6b7280;
            text-transform: uppercase;
            white-space: nowrap;

        }


        .table td {

            font-size: 14px;

        }


        .sub-category-name {

            font-weight: 700;
            color: #111827;

        }


        .parent-category {

            display: inline-block;
            padding: 5px 9px;
            background: #f3f4f6;
            border-radius: 7px;
            color: #374151;
            font-size: 12px;
            font-weight: 700;

        }


        .sub-category-slug {

            color: #6b7280;
            font-size: 13px;

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
            white-space: nowrap;

        }


        .empty-state {

            padding: 50px 20px;
            text-align: center;

        }


        .empty-state h5 {

            margin-bottom: 7px;
            font-weight: 700;

        }


        .empty-state p {

            margin: 0;
            color: #6b7280;
            font-size: 13px;

        }


        @media (max-width: 768px) {

            .page-content {

                padding: 22px 18px;
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;

            }


            .page-header h1 {

                font-size: 24px;

            }


            .sub-category-form-card,
            .sub-category-table-card {

                padding: 18px;
                width: 100%;
                max-width: 100%;
                min-width: 0;

            }


            .table-responsive {

                width: 100%;
                max-width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;

            }


            .action-buttons {

                flex-wrap: nowrap;

            }

        }


        @media (max-width: 520px) {

            .page-content {

                padding: 18px 14px;

            }


            .sub-category-form-card,
            .sub-category-table-card {

                padding: 15px;

            }


            .page-header {

                margin-bottom: 20px;

            }


            .page-header h1 {

                font-size: 22px;

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

            <h1>Sub Categories</h1>

            <p>
                Manage product sub categories under your main categories.
            </p>

        </div>


        <!-- MESSAGE -->

        <?php if ($message !== ""): ?>

            <div class="alert alert-<?= htmlspecialchars($messageType) ?>">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <!-- FORM -->

        <div class="sub-category-form-card">

            <h4>
                <?= $editSubCategory
                    ? "Edit Sub Category"
                    : "Add New Sub Category"
                ?>
            </h4>


            <?php if (count($categories) > 0): ?>

                <form method="POST">

                    <?php if ($editSubCategory): ?>

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $editSubCategory['id'] ?>"
                        >

                    <?php endif; ?>


                    <div class="row g-4">


                        <!-- CATEGORY -->

                        <div class="col-md-6">

                            <label class="form-label">
                                Main Category
                            </label>

                            <select
                                name="category_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Category
                                </option>


                                <?php foreach ($categories as $category): ?>

                                    <option
                                        value="<?= (int) $category['id'] ?>"
                                        <?= (
                                            $editSubCategory &&
                                            $editSubCategory['category_id'] == $category['id']
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >

                                        <?= htmlspecialchars($category['name']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- NAME -->

                        <div class="col-md-6">

                            <label class="form-label">
                                Sub Category Name
                            </label>

                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                placeholder="e.g. Cricket Bats"
                                value="<?= htmlspecialchars(
                                    $editSubCategory['name'] ?? ''
                                ) ?>"
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
                                placeholder="e.g. cricket-bats"
                                value="<?= htmlspecialchars(
                                    $editSubCategory['slug'] ?? ''
                                ) ?>"
                            >

                            <small class="text-muted">
                                Leave empty to generate automatically.
                            </small>

                        </div>


                        <!-- SORT ORDER -->

                        <div class="col-md-3">

                            <label class="form-label">
                                Sort Order
                            </label>

                            <input
                                type="number"
                                name="sort_order"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $editSubCategory['sort_order'] ?? '0'
                                ) ?>"
                            >

                        </div>


                        <!-- STATUS -->

                        <div class="col-md-3">

                            <label class="form-label">
                                Status
                            </label>

                            <select
                                name="status"
                                class="form-select"
                            >

                                <option
                                    value="1"
                                    <?= (
                                        !$editSubCategory ||
                                        $editSubCategory['status'] == 1
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Active
                                </option>

                                <option
                                    value="0"
                                    <?= (
                                        $editSubCategory &&
                                        $editSubCategory['status'] == 0
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Inactive
                                </option>

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
                                placeholder="Short sub category description..."
                            ><?= htmlspecialchars(
                                $editSubCategory['description'] ?? ''
                            ) ?></textarea>

                        </div>


                        <!-- BUTTONS -->

                        <div class="col-12 d-flex gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >

                                <?= $editSubCategory
                                    ? "Update Sub Category"
                                    : "Add Sub Category"
                                ?>

                            </button>


                            <?php if ($editSubCategory): ?>

                                <a
                                    href="sub-categories.php"
                                    class="btn btn-secondary"
                                >
                                    Cancel
                                </a>

                            <?php endif; ?>

                        </div>


                    </div>

                </form>

            <?php else: ?>

                <div class="alert alert-warning mb-0">

                    Please create at least one main category before adding
                    a sub category.

                </div>

            <?php endif; ?>

        </div>


        <!-- SUB CATEGORY LIST -->

        <div class="sub-category-table-card">

            <h4>All Sub Categories</h4>


            <?php if (count($subCategories) > 0): ?>

                <div class="table-responsive">

                    <table class="table table-hover">

                        <thead>

                            <tr>

                                <th>ID</th>

                                <th>Sub Category</th>

                                <th>Parent Category</th>

                                <th>Slug</th>

                                <th>Status</th>

                                <th>Order</th>

                                <th>Actions</th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($subCategories as $subCategory): ?>

                            <tr>


                                <td>

                                    #<?= (int) $subCategory['id'] ?>

                                </td>


                                <td>

                                    <div class="sub-category-name">

                                        <?= htmlspecialchars(
                                            $subCategory['name']
                                        ) ?>

                                    </div>


                                    <?php if (
                                        !empty($subCategory['description'])
                                    ): ?>

                                        <small class="text-muted">

                                            <?= htmlspecialchars(
                                                $subCategory['description']
                                            ) ?>

                                        </small>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <span class="parent-category">

                                        <?= htmlspecialchars(
                                            $subCategory['category_name']
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <span class="sub-category-slug">

                                        <?= htmlspecialchars(
                                            $subCategory['slug']
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?php if (
                                        $subCategory['status'] == 1
                                    ): ?>

                                        <span
                                            class="status-badge status-active"
                                        >
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="status-badge status-inactive"
                                        >
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= (int) $subCategory['sort_order'] ?>

                                </td>


                                <td>

                                    <div class="action-buttons">

                                        <a
                                            href="sub-categories.php?edit=<?= (int) $subCategory['id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Edit
                                        </a>


                                        <a
                                            href="sub-categories.php?delete=<?= (int) $subCategory['id'] ?>"
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Are you sure you want to delete this sub category?');"
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

                <div class="empty-state">

                    <h5>
                        No sub categories found
                    </h5>

                    <p>
                        Add your first sub category using the form above.
                    </p>

                </div>

            <?php endif; ?>


        </div>


    </main>


</div>


<script src="assets/js/admin.js"></script>


</body>

</html>