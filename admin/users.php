<?php
/**
 * users.php  —  Farm Users Management
 */
require_once 'auth_check.php';
require_once '../api/config.php';

$pageTitle = 'Farm Users';
$conn = getDBConnection();

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
$sql  = "SELECT id, username, email, status FROM farm_users $where ORDER BY id ASC LIMIT ? OFFSET ?";
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
                    <h1 class="page-title">Farm Users Management</h1>
                    <p class="page-subtitle">Manage registered farm user accounts, status, and sensor telemetry logs</p>
                </div>
                <a href="add_user.php" class="btn btn-primary" id="btn-add-user">
                    <i class="fa-solid fa-plus"></i> Add New User
                </a>
            </div>

            <!-- Card Table Wrapper -->
            <div class="card-table-container">
                <!-- Toolbar Header -->
                <div class="toolbar">
                    <div class="toolbar-left">
                        <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                            <select name="search_by" class="search-select" id="search-by-select">
                                <option value="username" <?= $searchField === 'username' ? 'selected' : '' ?>>Search by Username</option>
                                <option value="id"       <?= $searchField === 'id'       ? 'selected' : '' ?>>Search by ID</option>
                                <option value="status"   <?= $searchField === 'status'   ? 'selected' : '' ?>>Search by Status</option>
                            </select>
                            <div class="search-box-wrap">
                                <i class="fa-solid fa-magnifying-glass search-box-icon"></i>
                                <input type="text" name="q" class="search-input" id="search-input"
                                       value="<?= htmlspecialchars($searchTerm) ?>"
                                       placeholder="Type to search users...">
                            </div>
                        </form>
                    </div>

                    <div class="toolbar-right">
                        <button class="btn btn-secondary" onclick="exportUsersCSV()" id="btn-export-csv">
                            <i class="fa-solid fa-file-csv"></i> Export CSV
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="data-table" id="users-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>User Details</th>
                                <th>Account Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:32px;">No users found matching your query.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): 
                                $statusActive = ((int)$u['status'] === 0);
                            ?>
                            <tr>
                                <td>
                                    <span style="font-weight:600;color:var(--text-muted);">#<?= (int)$u['id'] ?></span>
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <div class="profile-avatar" style="width:36px;height:36px;font-size:14px;">
                                            <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                        </div>
                                        <div style="display:flex;flex-direction:column;">
                                            <a href="logs.php?user_id=<?= (int)$u['id'] ?>" class="user-link" style="font-weight:600;font-size:14px;">
                                                <?= htmlspecialchars($u['username']) ?>
                                            </a>
                                            <?php if (!empty($u['email'])): ?>
                                                <span style="font-size:12px;color:var(--text-muted);"><i class="fa-solid fa-envelope" style="font-size:10px;"></i> <?= htmlspecialchars($u['email']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($statusActive): ?>
                                    <span class="badge badge-active">
                                        <span class="status-dot"></span> Active (0)
                                    </span>
                                    <?php else: ?>
                                    <span class="badge badge-inactive">
                                        <span class="status-dot" style="background:#ef4444;"></span> Inactive (1)
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:flex;gap:6px;justify-content:flex-end;">
                                        <a href="logs.php?user_id=<?= (int)$u['id'] ?>" class="btn btn-green" id="view-logs-<?= (int)$u['id'] ?>" title="View Sensor Logs">
                                            <i class="fa-solid fa-list-check"></i> Logs
                                        </a>
                                        <a href="user_access_log.php?user_id=<?= (int)$u['id'] ?>" class="btn btn-blue" id="access-log-<?= (int)$u['id'] ?>" title="Access Telemetry">
                                            <i class="fa-solid fa-clock-rotate-left"></i> Access
                                        </a>
                                        <button onclick="openEditModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>', <?= (int)$u['status'] ?>)" 
                                                class="btn btn-secondary" id="edit-modal-btn-<?= (int)$u['id'] ?>" title="Edit User Modal">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Pagination -->
                <div class="table-footer">
                    <span class="total-records">Total Registered Users: <strong><?= $totalRecords ?></strong></span>
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
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal for Edit User -->
<div class="modal-overlay" id="edit-user-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-user-pen" style="color:var(--primary);margin-right:8px;"></i> Edit Farm User</h3>
            <button class="modal-close-btn" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="add_user.php?id=" id="modal-form">
            <div class="modal-body">
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;padding:12px;background:var(--bg-main);border-radius:var(--radius-md);">
                    <div class="profile-avatar" id="modal-user-avatar" style="width:48px;height:48px;font-size:18px;">U</div>
                    <div style="display:flex;flex-direction:column;">
                        <span id="modal-user-title" style="font-weight:700;font-size:15px;">User Profile</span>
                        <span style="font-size:12px;color:var(--text-muted);">Modify username and access status</span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="modal-username">Username</label>
                    <input type="text" id="modal-username" name="username" required maxlength="100" placeholder="e.g. Farm1">
                </div>
                <div class="form-group">
                    <label for="modal-status">Account Status</label>
                    <select id="modal-status" name="status">
                        <option value="0">0 — Active (Access Allowed)</option>
                        <option value="1">1 — Inactive (Access Restricted)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, username, status) {
    const modal = document.getElementById('edit-user-modal');
    const form = document.getElementById('modal-form');
    const usernameInput = document.getElementById('modal-username');
    const statusSelect = document.getElementById('modal-status');
    const avatar = document.getElementById('modal-user-avatar');
    const title = document.getElementById('modal-user-title');

    form.action = 'add_user.php?id=' + id;
    usernameInput.value = username;
    statusSelect.value = status;
    avatar.textContent = username.charAt(0).toUpperCase();
    title.textContent = username;

    modal.classList.add('show');
}

function closeEditModal() {
    document.getElementById('edit-user-modal').classList.remove('show');
}

function exportUsersCSV() {
    const table = document.getElementById('users-table');
    let csv = [];
    for (let row of table.rows) {
        let cols = Array.from(row.cells).slice(0, 3).map(c => '"' + c.innerText.trim().replace(/"/g, '""') + '"');
        csv.push(cols.join(','));
    }
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'farm_users.csv';
    a.click();
}
</script>
</body>
</html>
