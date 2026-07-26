<?php
/**
 * users.php  —  Farm Users List
 */
require_once 'auth_check.php';
require_once '../api/config.php';

$conn = getDBConnection();

// ── Handle delete (if ever needed) ───────────────────────────────────────────
// (No delete in screenshots, kept for safety)

// ── Pagination ────────────────────────────────────────────────────────────────
$perPage     = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// ── Search ────────────────────────────────────────────────────────────────────
$searchField = in_array($_GET['search_by'] ?? '', ['id','username','status']) ? $_GET['search_by'] : 'username';
$searchTerm  = trim($_GET['q'] ?? '');

$where  = '';
$params = [];
$types  = '';
if ($searchTerm !== '') {
    $where    = "WHERE `$searchField` LIKE ?";
    $params[] = '%' . $searchTerm . '%';
    $types    = 's';
}

// Total count
$countSql  = "SELECT COUNT(*) FROM farm_users $where";
$countStmt = $conn->prepare($countSql);
if ($types && $countStmt) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt && $countStmt->execute();
$totalRecords = (int)($countStmt ? $countStmt->get_result()->fetch_row()[0] : 0);
$totalPages   = max(1, (int)ceil($totalRecords / $perPage));
$currentPage  = min($currentPage, $totalPages);
$offset       = ($currentPage - 1) * $perPage;

// Fetch page
$sql  = "SELECT id, username, status FROM farm_users $where ORDER BY id ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($types) {
    $p   = array_merge($params, [$perPage, $offset]);
    $t   = $types . 'ii';
    $stmt->bind_param($t, ...$p);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();

// ── Pagination helper ─────────────────────────────────────────────────────────
function pageUrl(int $p, string $q = '', string $by = 'username'): string {
    $qs = http_build_query(array_filter(['page' => $p, 'q' => $q, 'search_by' => $by]));
    return 'users.php?' . $qs;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users — Farm Admin Panel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <div class="topbar"></div>
        <div class="content">
            <h1 class="page-title">Users</h1>

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="toolbar-left">
                    <a href="add_user.php" class="btn btn-add" id="btn-add-user">&#43; Add New</a>
                </div>
                <div class="toolbar-right">
                    <form method="GET" style="display:flex;gap:8px;align-items:center;">
                        <select name="search_by" class="search-select" id="search-by-select">
                            <option value="username" <?= $searchField === 'username' ? 'selected' : '' ?>>Search by...</option>
                            <option value="id"       <?= $searchField === 'id'       ? 'selected' : '' ?>>ID</option>
                            <option value="username" <?= $searchField === 'username' ? 'selected' : '' ?>>Username</option>
                            <option value="status"   <?= $searchField === 'status'   ? 'selected' : '' ?>>Status</option>
                        </select>
                        <input type="text" name="q" class="search-input" id="search-input"
                               value="<?= htmlspecialchars($searchTerm) ?>"
                               placeholder="Begin Typing To Search data...">
                    </form>
                </div>
            </div>

            <!-- Table -->
            <table class="data-table" id="users-table">
                <thead>
                    <tr>
                        <th>Id #</th>
                        <th>User #</th>
                        <th>Status #</th>
                        <th>Action #</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="4" style="text-align:center;color:#888;padding:24px;">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td>
                            <a href="logs.php?user_id=<?= (int)$u['id'] ?>" class="user-link">
                                <?= htmlspecialchars($u['username']) ?>
                            </a>
                        </td>
                        <td><?= (int)$u['status'] ?></td>
                        <td>
                            <a href="logs.php?user_id=<?= (int)$u['id'] ?>" class="btn btn-green" id="view-logs-<?= (int)$u['id'] ?>">View Logs</a>
                            <a href="user_access_log.php?user_id=<?= (int)$u['id'] ?>" class="btn btn-blue" id="access-log-<?= (int)$u['id'] ?>">Users Access Log</a>
                            <a href="add_user.php?id=<?= (int)$u['id'] ?>" class="btn btn-red" id="edit-user-<?= (int)$u['id'] ?>">Edit User</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination" id="users-pagination">
                <?php if ($totalPages > 1): ?>
                <a href="<?= pageUrl(1, $searchTerm, $searchField) ?>" class="<?= $currentPage === 1 ? 'disabled' : '' ?>" id="page-first">First</a>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="<?= pageUrl($p, $searchTerm, $searchField) ?>"
                   class="<?= $p === $currentPage ? 'current' : '' ?>"
                   id="page-<?= $p ?>"><?= $p ?></a>
                <?php endfor; ?>
                <a href="<?= pageUrl($totalPages, $searchTerm, $searchField) ?>" class="<?= $currentPage === $totalPages ? 'disabled' : '' ?>" id="page-last">Last</a>
                <?php endif; ?>
            </div>
            <p class="total-records">Total Records: <?= $totalRecords ?></p>
        </div>
    </div>
</div>
</body>
</html>
