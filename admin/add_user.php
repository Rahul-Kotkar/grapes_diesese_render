<?php
/**
 * add_user.php  —  Add or Edit a Farm User
 * GET  ?id=N  → edit mode
 * GET  (no id) → add mode
 */
require_once 'auth_check.php';
require_once '../api/config.php';

$conn   = getDBConnection();
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $editId > 0;
$pageTitle = $isEdit ? 'Edit User' : 'Add User';

// Load existing row for edit
$existing = ['username' => '', 'status' => 0];
if ($isEdit) {
    $st = $conn->prepare("SELECT username, email, status FROM farm_users WHERE id = ?");
    $st->bind_param('i', $editId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) {
        header('Location: users.php');
        exit();
    }
    $existing = $row;
}

$error   = '';
$success = '';

// ── Handle form submit ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $status   = (int)($_POST['status'] ?? 0);

    if ($username === '') {
        $error = 'Username is required.';
    } elseif (strlen($username) > 100) {
        $error = 'Username must be 100 characters or fewer.';
    } else {
        if ($isEdit) {
            $upd = $conn->prepare("UPDATE farm_users SET username = ?, email = ?, status = ? WHERE id = ?");
            $upd->bind_param('ssii', $username, $email, $status, $editId);
            $upd->execute();
            $upd->close();
            $success = 'User updated successfully.';
            $existing = ['username' => $username, 'email' => $email, 'status' => $status];
        } else {
            $ins = $conn->prepare("INSERT INTO farm_users (username, email, status) VALUES (?, ?, ?)");
            $ins->bind_param('ssi', $username, $email, $status);
            $ins->execute();
            $ins->close();
            // Redirect to users list after add
            header('Location: users.php');
            exit();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Edit User' : 'Add User' ?> — Farm Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'topbar.php'; ?>
        <div class="content">

            <div class="page-header">
                <div class="page-title-wrap">
                    <h1 class="page-title"><?= $isEdit ? 'Edit Farm User' : 'Create New Farm User' ?></h1>
                    <p class="page-subtitle"><?= $isEdit ? 'Update account credentials and status' : 'Add a new authorized IoT device user account' ?></p>
                </div>
                <a href="users.php" class="btn btn-secondary" id="btn-cancel-top">
                    <i class="fa-solid fa-arrow-left"></i> Back to Users List
                </a>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:20px;">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom:20px;">
                <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <div class="form-card">
                <form method="POST" id="user-form">
                    <div class="form-group">
                        <label for="username"><i class="fa-solid fa-user" style="color:var(--text-muted);margin-right:4px;"></i> Username</label>
                        <input type="text" id="username" name="username" required maxlength="100"
                               value="<?= htmlspecialchars($existing['username'] ?? '') ?>"
                               placeholder="e.g. Farm1">
                    </div>
                    <div class="form-group">
                        <label for="email"><i class="fa-solid fa-envelope" style="color:var(--text-muted);margin-right:4px;"></i> Email (Optional)</label>
                        <input type="email" id="email" name="email" maxlength="255"
                               value="<?= htmlspecialchars($existing['email'] ?? '') ?>"
                               placeholder="e.g. farm@example.com">
                    </div>
                    <div class="form-group">
                        <label for="status"><i class="fa-solid fa-toggle-on" style="color:var(--text-muted);margin-right:4px;"></i> Account Status</label>
                        <select id="status" name="status">
                            <option value="0" <?= (int)$existing['status'] === 0 ? 'selected' : '' ?>>0 — Active (Access Allowed)</option>
                            <option value="1" <?= (int)$existing['status'] === 1 ? 'selected' : '' ?>>1 — Inactive (Access Restricted)</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-save" id="btn-save-user">
                            <i class="fa-solid fa-check"></i> <?= $isEdit ? 'Update User' : 'Create User' ?>
                        </button>
                        <a href="users.php" class="btn-cancel" id="btn-cancel">Cancel</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>
</body>
</html>
