<?php
/**
 * index.php  —  Smart Agriculture Portal Authentication (Admin & Farmer)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../api/config.php';

// Already logged in → go to dashboard
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // 1. System Administrator Login
    if (strtolower($username) === 'ridge@grapes.com' && $password === 'ridge$123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['user_role']       = 'admin';
        $_SESSION['admin_user']      = 'Administrator';
        $_SESSION['user_id']         = 1;
        header('Location: dashboard.php');
        exit();
    } else {
        // 2. Farmer Account Login (Check farm_users database table)
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, username, status FROM farm_users WHERE LOWER(username) = LOWER(?) LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $userRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($userRow) {
                if ((int)$userRow['status'] === 1) {
                    $error = 'Account is inactive. Please contact the System Admin.';
                } else {
                    // Accepts standard passwords for farm user accounts (e.g. farm123, password123, or farm name)
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['user_role']       = 'farmer';
                    $_SESSION['admin_user']      = $userRow['username'];
                    $_SESSION['user_id']         = (int)$userRow['id'];
                    $conn->close();
                    header('Location: dashboard.php');
                    exit();
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Database connection error. Please try again.';
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Smart Agriculture Portal</title>
    <meta name="description" content="Portal login for Admin and Farm accounts.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-card">
        <div class="login-brand">
            <div class="login-logo">
                <i class="fa-solid fa-leaf"></i>
            </div>
            <h1 class="login-title">Farm Smart Portal</h1>
            <p class="login-subtitle">Sign in with Admin or Farmer credentials</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="login-form">
            <div class="form-group">
                <label for="username"><i class="fa-regular fa-user" style="margin-right:4px;"></i> Username / Email</label>
                <input type="text" id="username" name="username"
                       required autocomplete="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password"><i class="fa-solid fa-lock" style="margin-right:4px;"></i> Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login-submit" id="login-btn">
                Sign In to Portal <i class="fa-solid fa-arrow-right" style="margin-left:6px;"></i>
            </button>
        </form>
    </div>
</body>
</html>
