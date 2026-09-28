<?php

session_start();

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter email and password.";
    } else {

        $stmt = $pdo->prepare("
            SELECT id, name, email, password, role, status
            FROM admins
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $admin = $stmt->fetch();

        if ($admin && $admin["status"] == 1 && password_verify($password, $admin["password"])) {

            session_regenerate_id(true);

            $_SESSION["admin_id"] = $admin["id"];
            $_SESSION["admin_name"] = $admin["name"];
            $_SESSION["admin_email"] = $admin["email"];
            $_SESSION["admin_role"] = $admin["role"];

            header("Location: dashboard.php");
            exit;

        } else {
            $error = "Invalid email or password.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | Sports CMS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f4f6f8;
            font-family: Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 430px;
        }

        .login-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 38px;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.08);
        }

        .brand {
            text-align: center;
            margin-bottom: 30px;
        }

        .brand h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            color: #111827;
        }

        .brand p {
            margin: 8px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
        }

        .form-control {
            min-height: 48px;
            border-radius: 10px;
        }

        .btn-login {
            width: 100%;
            min-height: 50px;
            border: 0;
            border-radius: 10px;
            background: #111827;
            color: #ffffff;
            font-weight: 700;
        }

        .btn-login:hover {
            background: #000000;
        }

        @media (max-width: 480px) {

            .login-card {
                padding: 26px 20px;
                border-radius: 14px;
            }

            .brand h1 {
                font-size: 24px;
            }
        }

    </style>
</head>

<body>

<div class="login-wrapper">

    <div class="login-card">

        <div class="brand">
            <h1>Sports CMS</h1>
            <p>Admin Panel</p>
        </div>

        <?php if ($error): ?>

            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="mb-3">

                <label class="form-label">
                    Email Address
                </label>

                <input
                    type="email"
                    name="email"
                    class="form-control"
                    placeholder="admin@sports.com"
                    required
                >

            </div>

            <div class="mb-4">

                <label class="form-label">
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    class="form-control"
                    placeholder="Enter password"
                    required
                >

            </div>

            <button type="submit" class="btn-login">
                Sign In
            </button>

        </form>

    </div>

</div>

</body>
</html>