<?php
/**
 * raw_db.php  —  Raw Database Table Viewer (phpMyAdmin style)
 */
require_once 'auth_check.php';
require_once '../api/config.php';

$conn = getDBConnection();

// ── Handle Delete Action ──────────────────────────────────────────────────────
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $table = $_POST['table'] ?? 'sensor_data';
    $deleteId = (int)($_POST['id'] ?? 0);
    
    // Allowed tables only
    if (in_array($table, ['sensor_data', 'farm_users']) && $deleteId > 0) {
        $delStmt = $conn->prepare("DELETE FROM `$table` WHERE id = ?");
        if ($delStmt) {
            $delStmt->bind_param('i', $deleteId);
            if ($delStmt->execute()) {
                $message = "Row #$deleteId deleted from `$table` successfully.";
            }
            $delStmt->close();
        }
    }
}

// ── Selected Table & Settings ─────────────────────────────────────────────────
$currentTable = in_array($_GET['table'] ?? '', ['sensor_data', 'farm_users']) ? $_GET['table'] : 'sensor_data';
$perPage      = in_array((int)($_GET['limit'] ?? 50), [25, 50, 100, 500]) ? (int)$_GET['limit'] : 50;
$currentPage  = max(1, (int)($_GET['page'] ?? 1));
$sortCol      = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['sort'] ?? 'id');
$sortOrder    = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$searchTerm   = trim($_GET['q'] ?? '');

// Get column names for selected table
$columns = [];
$colRes = $conn->query("DESCRIBE `$currentTable`");
if ($colRes) {
    while ($c = $colRes->fetch_assoc()) {
        $columns[] = $c['Field'];
    }
}

if (!in_array($sortCol, $columns)) {
    $sortCol = 'id';
}

// ── Build Search / Filter ─────────────────────────────────────────────────────
$whereClause = '';
$params      = [];
$types       = '';

if ($searchTerm !== '') {
    $orConditions = [];
    foreach ($columns as $col) {
        $orConditions[] = "`$col` LIKE ?";
        $params[]       = '%' . $searchTerm . '%';
        $types         .= 's';
    }
    $whereClause = 'WHERE ' . implode(' OR ', $orConditions);
}

// Count total rows
$totalRecords = 0;
$cntStmt = $conn->prepare("SELECT COUNT(*) FROM `$currentTable` $whereClause");
if ($cntStmt) {
    if ($types) $cntStmt->bind_param($types, ...$params);
    $cntStmt->execute();
    $totalRecords = (int)$cntStmt->get_result()->fetch_row()[0];
    $cntStmt->close();
}

$totalPages  = max(1, (int)ceil($totalRecords / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset      = ($currentPage - 1) * $perPage;

// Fetch raw rows
$rows = [];
$fetchSql = "SELECT * FROM `$currentTable` $whereClause ORDER BY `$sortCol` $sortOrder LIMIT ? OFFSET ?";
$fetchSt  = $conn->prepare($fetchSql);
if ($fetchSt) {
    $allParams = array_merge($params, [$perPage, $offset]);
    $allTypes  = $types . 'ii';
    $fetchSt->bind_param($allTypes, ...$allParams);
    $fetchSt->execute();
    $rows = $fetchSt->get_result()->fetch_all(MYSQLI_ASSOC);
    $fetchSt->close();
}

$conn->close();

function navUrl(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    return 'raw_db.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raw Database Viewer — phpMyAdmin Style</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .pma-header {
            background: #eef2f5;
            padding: 12px 18px;
            border-radius: 8px;
            border: 1px solid #dcdfe3;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .pma-tabs {
            display: flex;
            gap: 6px;
        }
        .pma-tab {
            padding: 8px 16px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            font-weight: 600;
            font-size: 13px;
        }
        .pma-tab.active {
            background: #007bff;
            color: #fff;
            border-color: #0056b3;
        }
        .raw-table-container {
            overflow-x: auto;
            background: #fff;
            border: 1px solid #dcdfe3;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .raw-table {
            width: 100%;
            border-collapse: collapse;
            font-family: monospace, monospace;
            font-size: 12px;
        }
        .raw-table th {
            background: #f1f4f8;
            color: #444;
            padding: 10px 12px;
            text-align: left;
            border-bottom: 2px solid #cbd5e1;
            white-space: nowrap;
        }
        .raw-table th a {
            color: #0056b3;
            text-decoration: none;
        }
        .raw-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
            color: #2d3748;
        }
        .raw-table tr:nth-child(even) {
            background: #f8fafc;
        }
        .raw-table tr:hover {
            background: #edf2f7;
        }
        .null-val {
            color: #a0aec0;
            font-style: italic;
        }
        .btn-del {
            background: #e53e3e;
            color: #fff;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
        }
        .btn-del:hover {
            background: #c53030;
        }
        .alert-msg {
            background: #c6f6d5;
            color: #22543d;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border: 1px solid #9ae6b4;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <div class="topbar"></div>
        <div class="content">
            <h1 class="page-title">🗄️ Raw Database Table Viewer (phpMyAdmin Style)</h1>

            <?php if ($message): ?>
            <div class="alert-msg"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <!-- Table Navigation Header -->
            <div class="pma-header">
                <div class="pma-tabs">
                    <a href="<?= navUrl(['table' => 'sensor_data', 'page' => 1]) ?>" class="pma-tab <?= $currentTable === 'sensor_data' ? 'active' : '' ?>">
                        📊 sensor_data
                    </a>
                    <a href="<?= navUrl(['table' => 'farm_users', 'page' => 1]) ?>" class="pma-tab <?= $currentTable === 'farm_users' ? 'active' : '' ?>">
                        👥 farm_users
                    </a>
                </div>

                <form method="GET" style="display:flex;gap:10px;align-items:center;">
                    <input type="hidden" name="table" value="<?= htmlspecialchars($currentTable) ?>">
                    <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Filter rows..." class="search-input" style="width:200px;">
                    <select name="limit" onchange="this.form.submit()" class="search-select">
                        <option value="25"  <?= $perPage===25  ? 'selected':'' ?>>25 rows</option>
                        <option value="50"  <?= $perPage===50  ? 'selected':'' ?>>50 rows</option>
                        <option value="100" <?= $perPage===100 ? 'selected':'' ?>>100 rows</option>
                        <option value="500" <?= $perPage===500 ? 'selected':'' ?>>500 rows</option>
                    </select>
                </form>
            </div>

            <!-- Raw Data Table -->
            <div class="raw-table-container">
                <table class="raw-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <?php foreach ($columns as $col): ?>
                            <th>
                                <a href="<?= navUrl(['sort' => $col, 'order' => ($sortCol === $col && $sortOrder === 'ASC') ? 'DESC' : 'ASC']) ?>">
                                    <?= htmlspecialchars($col) ?>
                                    <?= $sortCol === $col ? ($sortOrder === 'ASC' ? '▲' : '▼') : '' ?>
                                </a>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="<?= count($columns) + 1 ?>" style="text-align:center;padding:24px;color:#718096;">No rows found in table <code><?= htmlspecialchars($currentTable) ?></code>.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete row #<?= $row['id'] ?>?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="table" value="<?= htmlspecialchars($currentTable) ?>">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn-del">Delete</button>
                                </form>
                            </td>
                            <?php foreach ($columns as $col): ?>
                            <td>
                                <?php if ($row[$col] === null): ?>
                                    <span class="null-val">NULL</span>
                                <?php else: ?>
                                    <?= htmlspecialchars((string)$row[$col]) ?>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination & Records count -->
            <div style="margin-top:15px;display:flex;justify-content:space-between;align-items:center;">
                <p class="total-records">Total Rows in Database: <strong><?= number_format($totalRecords) ?></strong></p>
                
                <div class="pagination">
                    <?php if ($totalPages > 1): ?>
                        <?php for ($p = 1; $p <= min(10, $totalPages); $p++): ?>
                        <a href="<?= navUrl(['page' => $p]) ?>" class="<?= $p === $currentPage ? 'current' : '' ?>"><?= $p ?></a>
                        <?php endfor; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
