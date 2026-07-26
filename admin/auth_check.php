<?php
/**
 * auth_check.php
 * Include at the top of every admin page to enforce login.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}
