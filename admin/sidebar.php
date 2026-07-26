<?php
/**
 * sidebar.php
 * Reusable sidebar navigation. Include inside .wrapper div.
 * Expects $activePage variable set to 'dashboard'|'users' etc.
 */
$current = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar">
    <div class="sidebar-logo">Farm Admin Panel</div>
    <nav>
        <a href="dashboard.php" class="<?= $current === 'dashboard' ? 'active' : '' ?>">
            <span class="icon">⊞</span> Dashboard
        </a>
        <a href="users.php" class="<?= ($current === 'users' || $current === 'add_user' || $current === 'user_access_log') ? 'active' : '' ?>">
            <span class="icon">👤</span> Users
        </a>
        <a href="raw_db.php" class="<?= $current === 'raw_db' ? 'active' : '' ?>">
            <span class="icon">🗄️</span> Raw DB Tables
        </a>
        <a href="logout.php">
            <span class="icon">⏻</span> Logout
        </a>
    </nav>
</aside>
