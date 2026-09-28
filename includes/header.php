<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'Administrator';
?>

<header class="topbar">

    <button
        type="button"
        class="menu-toggle"
        id="menuToggle"
        aria-label="Toggle menu"
    >
        ☰
    </button>

    <div class="topbar-title">
        Dashboard
    </div>

    <div class="topbar-right">

        <div class="admin-profile">

            <div class="admin-avatar">
                <?= strtoupper(substr($admin_name, 0, 1)) ?>
            </div>

            <div class="admin-info">

                <strong>
                    <?= htmlspecialchars($admin_name) ?>
                </strong>

                <span>
                    <?= htmlspecialchars($admin_role) ?>
                </span>

            </div>

        </div>

        <a href="logout.php" class="logout-btn">
            Logout
        </a>

    </div>

</header>