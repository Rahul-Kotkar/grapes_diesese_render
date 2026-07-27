<?php
/**
 * sidebar.php
 * Reusable modern vertical sidebar navigation with Role-Based Access Control.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
$current = basename($_SERVER['PHP_SELF'], '.php');
$isUserAdmin = function_exists('isAdmin') ? isAdmin() : (($_SESSION['user_role'] ?? 'admin') === 'admin');
$userName = $_SESSION['admin_user'] ?? 'User';
$userAvatar = strtoupper(substr($userName, 0, 1));
$userRoleTitle = $isUserAdmin ? 'System Admin' : 'Farm Account';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="brand-logo">
            <div class="logo-icon">
                <i class="fa-solid fa-leaf"></i>
            </div>
            <div class="brand-text">
                <span class="brand-name">GPR Farm</span>
                <span class="brand-sub">Smart Monitoring</span>
            </div>
        </div>
        <button class="mobile-close-btn" id="sidebar-close-btn" onclick="toggleSidebar()">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-section-title">MAIN MENU</span>
            <a href="dashboard.php" class="nav-link <?= $current === 'dashboard' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line nav-icon"></i>
                <span class="nav-label">Dashboard</span>
            </a>

            <?php if ($isUserAdmin): ?>
            <!-- Admin Only Navigation -->
            <a href="users.php" class="nav-link <?= ($current === 'users' || $current === 'add_user' || $current === 'user_access_log') ? 'active' : '' ?>">
                <i class="fa-solid fa-users nav-icon"></i>
                <span class="nav-label">Farm Users</span>
            </a>
            <?php endif; ?>

            <a href="logs.php" class="nav-link <?= $current === 'logs' ? 'active' : '' ?>">
                <i class="fa-solid fa-microchip nav-icon"></i>
                <span class="nav-label">Sensor Logs</span>
            </a>
        </div>

        <div class="nav-section" style="margin-top: auto;">
            <span class="nav-section-title">SYSTEM</span>
            <?php if ($isUserAdmin): ?>
            <a href="https://grapes-diesese-render.onrender.com/api/test.html" target="_blank" class="nav-link">
                <i class="fa-solid fa-flask nav-icon"></i>
                <span class="nav-label">API Tester</span>
                <span class="nav-tag">Live</span>
            </a>
            <?php endif; ?>
            <a href="logout.php" class="nav-link nav-logout">
                <i class="fa-solid fa-arrow-right-from-bracket nav-icon"></i>
                <span class="nav-label">Logout</span>
            </a>
        </div>
    </nav>

    <div class="sidebar-user-footer">
        <div class="user-avatar"><?= htmlspecialchars($userAvatar) ?></div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($userName) ?></span>
            <span class="user-role"><span class="status-dot"></span> <?= htmlspecialchars($userRoleTitle) ?></span>
        </div>
    </div>
</aside>

