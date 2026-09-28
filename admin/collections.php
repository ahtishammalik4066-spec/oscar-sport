<?php

require_once "../config/database.php";
require_once "../includes/auth.php";

$message = "";
$messageType = "";

/*
|--------------------------------------------------------------------------
| DELETE COLLECTION
|--------------------------------------------------------------------------
*/

if (isset($_GET["delete"])) {

    $id = (int) $_GET["delete"];

    if ($id > 0) {

        $stmt = $pdo->prepare("DELETE FROM collections WHERE id = ?");
        $stmt->execute([$id]);

        $message = "Collection deleted successfully.";
        $messageType = "success";
    }
}

/*
|--------------------------------------------------------------------------
| ADD / UPDATE COLLECTION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = isset($_POST["id"]) ? (int) $_POST["id"] : 0;

    $name = trim($_POST["name"] ?? "");
    $slug = trim($_POST["slug"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $status = isset($_POST["status"]) ? 1 : 0;
    $sortOrder = (int) ($_POST["sort_order"] ?? 0);

    /*
    |--------------------------------------------------------------------------
    | Generate slug automatically if empty
    |--------------------------------------------------------------------------
    */

    if ($slug === "" && $name !== "") {

        $slug = strtolower($name);

        $slug = preg_replace("/[^a-z0-9]+/i", "-", $slug);
        $slug = trim($slug, "-");
    }

    if ($name === "") {

        $message = "Collection name is required.";
        $messageType = "danger";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            if ($id > 0) {

                $stmt = $pdo->prepare("
                    UPDATE collections
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
                    $description !== "" ? $description : null,
                    $status,
                    $sortOrder,
                    $id
                ]);

                $message = "Collection updated successfully.";
                $messageType = "success";

            }

            /*
            |--------------------------------------------------------------------------
            | ADD
            |--------------------------------------------------------------------------
            */

            else {

                $stmt = $pdo->prepare("
                    INSERT INTO collections
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
                    $description !== "" ? $description : null,
                    $status,
                    $sortOrder
                ]);

                $message = "Collection added successfully.";
                $messageType = "success";
            }

        } catch (PDOException $e) {

            if ($e->getCode() == 23000) {

                $message = "This slug already exists. Please use a different slug.";
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
| EDIT COLLECTION
|--------------------------------------------------------------------------
*/

$editCollection = null;

if (isset($_GET["edit"])) {

    $editId = (int) $_GET["edit"];

    if ($editId > 0) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM collections
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$editId]);

        $editCollection = $stmt->fetch();
    }
}

/*
|--------------------------------------------------------------------------
| FETCH ALL COLLECTIONS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT *
    FROM collections
    ORDER BY sort_order ASC, id DESC
");

$collections = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Collections | Sports CMS</title>

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

        .collection-layout {
            display: grid;
            grid-template-columns: 360px minmax(0, 1fr);
            gap: 22px;
            align-items: start;
        }

        .collection-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .collection-card-header {
            padding: 20px 22px;
            border-bottom: 1px solid #e5e7eb;
        }

        .collection-card-header h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
        }

        .collection-card-header p {
            margin: 5px 0 0;
            color: #6b7280;
            font-size: 12px;
        }

        .collection-card-body {
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
            min-height: 105px;
            resize: vertical;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #9ca3af;
            box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.06);
        }

        .save-btn {
            width: 100%;
            min-height: 44px;
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
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 42px;
            margin-top: 9px;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            color: #374151;
            font-size: 13px;
            font-weight: 700;
            background: #fff;
        }

        .cancel-btn:hover {
            background: #f9fafb;
        }

        .collection-table-wrap {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .collection-table {
            width: 100%;
            min-width: 720px;
            border-collapse: collapse;
        }

        .collection-table th {
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

        .collection-table td {
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            font-size: 13px;
        }

        .collection-table tr:last-child td {
            border-bottom: 0;
        }

        .collection-name {
            font-weight: 800;
            color: #111827;
        }

        .collection-slug {
            color: #6b7280;
            font-size: 11px;
            margin-top: 3px;
        }

        .description-text {
            max-width: 280px;
            color: #6b7280;
            font-size: 12px;
            line-height: 1.5;
        }

        .status-badge {
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

        .sort-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 30px;
            height: 28px;
            padding: 0 8px;
            border-radius: 7px;
            background: #f3f4f6;
            color: #374151;
            font-size: 11px;
            font-weight: 800;
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
        }

        .edit-btn {
            color: #374151;
            background: #f3f4f6;
        }

        .edit-btn:hover {
            background: #e5e7eb;
        }

        .delete-btn {
            color: #dc2626;
            background: #fef2f2;
        }

        .delete-btn:hover {
            background: #fee2e2;
        }

        .empty-collections {
            padding: 55px 25px;
            text-align: center;
        }

        .empty-collections-icon {
            font-size: 30px;
            margin-bottom: 10px;
        }

        .empty-collections h4 {
            margin: 0 0 6px;
            font-size: 15px;
            font-weight: 800;
        }

        .empty-collections p {
            margin: 0;
            color: #6b7280;
            font-size: 12px;
        }

        .alert {
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 22px;
        }

        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 18px;
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

        @media (max-width: 1050px) {

            .collection-layout {
                grid-template-columns: 1fr;
            }

            .collection-form-card {
                max-width: 100%;
            }

        }

        @media (max-width: 768px) {

            .page-content {
                padding: 22px 18px;
                overflow-x: hidden;
            }

            .collection-card-body {
                padding: 18px;
            }

            .collection-card-header {
                padding: 18px;
            }

        }

        @media (max-width: 520px) {

            .page-content {
                padding: 18px 14px;
            }

            .collection-card-body {
                padding: 15px;
            }

            .collection-card-header {
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

            <div class="page-heading">

                <h1>Collections</h1>

                <p>
                    Manage product collections for the WB Sports store.
                </p>

            </div>


            <?php if ($message !== ""): ?>

                <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show">

                    <?php echo htmlspecialchars($message); ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <div class="collection-layout">


                <!-- FORM -->

                <div class="collection-card collection-form-card">

                    <div class="collection-card-header">

                        <h3>
                            <?php echo $editCollection ? "Edit Collection" : "Add Collection"; ?>
                        </h3>

                        <p>
                            Create and organize your store collections.
                        </p>

                    </div>


                    <div class="collection-card-body">

                        <form method="POST">

                            <?php if ($editCollection): ?>

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?php echo (int) $editCollection["id"]; ?>"
                                >

                            <?php endif; ?>


                            <!-- NAME -->

                            <div class="mb-3">

                                <label class="form-label">
                                    Collection Name
                                </label>

                                <input
                                    type="text"
                                    name="name"
                                    class="form-control"
                                    placeholder="e.g. New Releases"
                                    value="<?php echo htmlspecialchars($editCollection["name"] ?? ""); ?>"
                                    required
                                >

                            </div>


                            <!-- SLUG -->

                            <div class="mb-3">

                                <label class="form-label">
                                    Slug
                                </label>

                                <input
                                    type="text"
                                    name="slug"
                                    class="form-control"
                                    placeholder="e.g. new-releases"
                                    value="<?php echo htmlspecialchars($editCollection["slug"] ?? ""); ?>"
                                >

                                <small class="text-muted">
                                    Leave empty to generate automatically.
                                </small>

                            </div>


                            <!-- DESCRIPTION -->

                            <div class="mb-3">

                                <label class="form-label">
                                    Description
                                </label>

                                <textarea
                                    name="description"
                                    class="form-control"
                                    placeholder="Short description for this collection..."
                                ><?php echo htmlspecialchars($editCollection["description"] ?? ""); ?></textarea>

                            </div>


                            <!-- SORT ORDER -->

                            <div class="mb-3">

                                <label class="form-label">
                                    Sort Order
                                </label>

                                <input
                                    type="number"
                                    name="sort_order"
                                    class="form-control"
                                    min="0"
                                    value="<?php echo htmlspecialchars($editCollection["sort_order"] ?? "0"); ?>"
                                >

                            </div>


                            <!-- STATUS -->

                            <div class="checkbox-row">

                                <input
                                    type="checkbox"
                                    id="status"
                                    name="status"
                                    <?php
                                    if (!$editCollection || (int) $editCollection["status"] === 1) {
                                        echo "checked";
                                    }
                                    ?>
                                >

                                <label for="status">
                                    Active Collection
                                </label>

                            </div>


                            <button
                                type="submit"
                                class="save-btn"
                            >
                                <?php echo $editCollection ? "Update Collection" : "Add Collection"; ?>
                            </button>


                            <?php if ($editCollection): ?>

                                <a
                                    href="collections.php"
                                    class="cancel-btn"
                                >
                                    Cancel Edit
                                </a>

                            <?php endif; ?>

                        </form>

                    </div>

                </div>


                <!-- TABLE -->

                <div class="collection-card">

                    <div class="collection-card-header">

                        <h3>
                            All Collections
                        </h3>

                        <p>
                            <?php echo count($collections); ?>
                            collection<?php echo count($collections) === 1 ? "" : "s"; ?>
                            available
                        </p>

                    </div>


                    <?php if (!empty($collections)): ?>

                        <div class="collection-table-wrap">

                            <table class="collection-table">

                                <thead>

                                    <tr>

                                        <th>
                                            Collection
                                        </th>

                                        <th>
                                            Description
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                        <th>
                                            Sort
                                        </th>

                                        <th>
                                            Actions
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach ($collections as $collection): ?>

                                        <tr>

                                            <td>

                                                <div class="collection-name">

                                                    <?php
                                                    echo htmlspecialchars($collection["name"]);
                                                    ?>

                                                </div>

                                                <div class="collection-slug">

                                                    /
                                                    <?php
                                                    echo htmlspecialchars($collection["slug"]);
                                                    ?>

                                                </div>

                                            </td>


                                            <td>

                                                <div class="description-text">

                                                    <?php

                                                    if (!empty($collection["description"])) {

                                                        echo htmlspecialchars(
                                                            $collection["description"]
                                                        );

                                                    } else {

                                                        echo "No description";

                                                    }

                                                    ?>

                                                </div>

                                            </td>


                                            <td>

                                                <?php if ((int) $collection["status"] === 1): ?>

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

                                                <span class="sort-badge">

                                                    <?php
                                                    echo (int) $collection["sort_order"];
                                                    ?>

                                                </span>

                                            </td>


                                            <td>

                                                <div class="action-group">

                                                    <a
                                                        href="collections.php?edit=<?php echo (int) $collection["id"]; ?>"
                                                        class="action-btn edit-btn"
                                                    >
                                                        Edit
                                                    </a>


                                                    <a
                                                        href="collections.php?delete=<?php echo (int) $collection["id"]; ?>"
                                                        class="action-btn delete-btn"
                                                        onclick="return confirm('Are you sure you want to delete this collection?');"
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

                        <div class="empty-collections">

                            <div class="empty-collections-icon">
                                📦
                            </div>

                            <h4>
                                No Collections Yet
                            </h4>

                            <p>
                                Create your first collection using the form.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </main>


        <?php include "../includes/footer.php"; ?>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

<script src="assets/js/admin.js"></script>

</body>

</html>