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

// Load existing row for edit
$existing = ['username' => '', 'status' => 0];
if ($isEdit) {
    $st = $conn->prepare("SELECT username, status FROM farm_users WHERE id = ?");
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
    $status   = (int)($_POST['status'] ?? 0);

    if ($username === '') {
        $error = 'Username is required.';
    } elseif (strlen($username) > 100) {
        $error = 'Username must be 100 characters or fewer.';
    } else {
        if ($isEdit) {
            $upd = $conn->prepare("UPDATE farm_users SET username = ?, status = ? WHERE id = ?");
            $upd->bind_param('sii', $username, $status, $editId);
            $upd->execute();
            $upd->close();
            $success = 'User updated successfully.';
            $existing = ['username' => $username, 'status' => $status];
        } else {
            $ins = $conn->prepare("INSERT INTO farm_users (username, status) VALUES (?, ?)");
            $ins->bind_param('si', $username, $status);
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
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <div class="topbar"></div>
        <div class="content">
            <h1 class="page-title"><?= $isEdit ? 'Edit User' : 'Add New User' ?></h1>

            <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div class="alert" style="background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:10px 14px;border-radius:5px;margin-bottom:16px;"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="form-card">
                <form method="POST" id="user-form">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required maxlength="100"
                               value="<?= htmlspecialchars($existing['username']) ?>"
                               placeholder="e.g. Farm1">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="0" <?= (int)$existing['status'] === 0 ? 'selected' : '' ?>>0 — Active</option>
                            <option value="1" <?= (int)$existing['status'] === 1 ? 'selected' : '' ?>>1 — Inactive</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-save" id="btn-save-user">
                            <?= $isEdit ? 'Update User' : 'Add User' ?>
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
