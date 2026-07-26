<?php
/**
 * index.php  —  Admin Login
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in → go to dashboard
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user']      = 'admin';
        header('Location: dashboard.php');
        exit();
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — GPR Farm Admin Panel</title>
    <meta name="description" content="Admin login for the Grape Disease Monitoring System.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-card">
        <div class="login-brand">
            <div class="login-logo">
                <i class="fa-solid fa-leaf"></i>
            </div>
            <h1 class="login-title">Farm Admin Panel</h1>
            <p class="login-subtitle">Grape Disease Monitoring & AI Prediction System</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="login-form">
            <div class="form-group">
                <label for="username"><i class="fa-regular fa-user" style="margin-right:4px;"></i> Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username"
                       required autocomplete="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password"><i class="fa-solid fa-lock" style="margin-right:4px;"></i> Password</label>
                <input type="password" id="password" name="password"
                       placeholder="••••••••" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login-submit" id="login-btn">
                Sign In to Dashboard <i class="fa-solid fa-arrow-right" style="margin-left:6px;"></i>
            </button>
        </form>
    </div>
</body>
</html>
