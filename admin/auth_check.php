<?php
/**
 * auth_check.php
 * Enforces authentication & role-based permissions (Admin vs Farmer).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}

/**
 * Checks if current logged-in session has System Admin privileges.
 */
function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? 'farmer') === 'admin';
}

/**
 * Enforces admin-only page access. Redirects farmers to dashboard.
 */
function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit();
    }
}

