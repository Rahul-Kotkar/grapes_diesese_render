<?php
/**
 * logs.php  —  All Sensor Logs (paginated)
 */
require_once 'auth_check.php';
require_once '../api/config.php';

$conn = getDBConnection();

// ── Detect MySQL server's UTC offset so we can display all times in IST ──────
// TIME_TO_SEC(TIMEDIFF(NOW(), UTC_TIMESTAMP())) gives server offset in seconds.
// IST = UTC+5:30 = 19800 seconds. Correction = IST_offset - server_offset.
$tzRow = $conn->query("SELECT TIME_TO_SEC(TIMEDIFF(NOW(), UTC_TIMESTAMP())) AS off")->fetch_assoc();
$serverOffsetSec = (int)($tzRow['off'] ?? 0);
$toIST = 19800 - $serverOffsetSec;   // seconds to ADD to server time to get IST

$filterUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// ── Pagination ────────────────────────────────────────────────────────────────
$perPage     = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

// ── Search ────────────────────────────────────────────────────────────────────
$searchField = in_array($_GET['search_by'] ?? '', ['temperature','humidity','sunlight','rainfall','leaf_wetness','risk_level']) ? $_GET['search_by'] : 'risk_level';
$searchTerm  = trim($_GET['q'] ?? '');

// Build WHERE
$conditions = [];
$params     = [];
$types      = '';

if ($filterUserId > 0) {
    $conditions[] = 'user_id = ?';
    $params[]     = $filterUserId;
    $types       .= 'i';
}
if ($searchTerm !== '') {
    $conditions[] = "`$searchField` LIKE ?";
    $params[]     = '%' . $searchTerm . '%';
    $types       .= 's';
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Total count
$totalRecords = 0;
$cntStmt = $conn->prepare("SELECT COUNT(*) FROM sensor_data $where");
if ($cntStmt) {
    if ($types) $cntStmt->bind_param($types, ...$params);
    $cntStmt->execute();
    $totalRecords = (int)$cntStmt->get_result()->fetch_row()[0];
    $cntStmt->close();
}

$totalPages  = max(1, (int)ceil($totalRecords / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset      = ($currentPage - 1) * $perPage;

// Fetch rows
$rows    = [];
$fetchSt = $conn->prepare(
    "SELECT id, created_at,
            temperature, humidity, sunlight, rainfall, leaf_wetness, dsi, risk_level
     FROM sensor_data
     $where
     ORDER BY created_at DESC
     LIMIT ? OFFSET ?"
);
if ($fetchSt) {
    $allParams = array_merge($params, [$perPage, $offset]);
    $allTypes  = $types . 'ii';
    $fetchSt->bind_param($allTypes, ...$allParams);
    $fetchSt->execute();
    $rows = $fetchSt->get_result()->fetch_all(MYSQLI_ASSOC);
    $fetchSt->close();
}
$conn->close();

// ── Helpers ───────────────────────────────────────────────────────────────────
function pageUrl2(int $p, int $uid, string $q, string $by): string {
    $qs = http_build_query(array_filter(['page' => $p, 'user_id' => $uid ?: null, 'q' => $q, 'search_by' => $by]));
    return 'logs.php?' . $qs;
}

function riskClass(string $risk): string {
    return match(strtolower($risk)) {
        'low'    => 'risk-low',
        'medium' => 'risk-medium',
        'high'   => 'risk-high',
        default  => 'risk-na',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs — Farm Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <style>
        @media print { .sidebar, .topbar, .toolbar, .pagination, .total-records { display: none; } .main { margin-left: 0; } }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <div class="topbar"></div>
        <div class="content">
            <h1 class="page-title">Logs<?= $filterUserId ? ' — User #' . $filterUserId : '' ?></h1>

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="toolbar-left">
                    <button class="btn btn-print" onclick="window.print()" id="btn-print-all">&#128438; Print All</button>
                    <?php if ($filterUserId): ?>
                    <a href="logs.php" class="btn" style="background:#eee;color:#333;border-radius:20px;padding:7px 14px;font-size:12px;">← All Logs</a>
                    <?php endif; ?>
                </div>
                <div class="toolbar-right">
                    <form method="GET" style="display:flex;gap:8px;align-items:center;">
                        <?php if ($filterUserId): ?>
                        <input type="hidden" name="user_id" value="<?= $filterUserId ?>">
                        <?php endif; ?>
                        <select name="search_by" class="search-select" id="search-by-select">
                            <option value="risk_level"   <?= $searchField==='risk_level'   ? 'selected' : '' ?>>Search by...</option>
                            <option value="temperature"  <?= $searchField==='temperature'  ? 'selected' : '' ?>>Temperature</option>
                            <option value="humidity"     <?= $searchField==='humidity'     ? 'selected' : '' ?>>RH</option>
                            <option value="sunlight"     <?= $searchField==='sunlight'     ? 'selected' : '' ?>>Sunlight</option>
                            <option value="rainfall"     <?= $searchField==='rainfall'     ? 'selected' : '' ?>>Rainfall</option>
                            <option value="leaf_wetness" <?= $searchField==='leaf_wetness' ? 'selected' : '' ?>>Leaf Wetness</option>
                            <option value="risk_level"   <?= $searchField==='risk_level'   ? 'selected' : '' ?>>Risk Level</option>
                        </select>
                        <input type="text" name="q" class="search-input" id="search-input"
                               value="<?= htmlspecialchars($searchTerm) ?>"
                               placeholder="Begin Typing To Search data...">
                    </form>
                </div>
            </div>

            <!-- Table -->
            <table class="data-table" id="logs-table">
                <thead>
                    <tr>
                        <th>Date #</th>
                        <th>Temp #</th>
                        <th>RH #</th>
                        <th>Sunlight #</th>
                        <th>Rainfall #</th>
                        <th>Leaf Wet #</th>
                        <th>Risk #</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" style="text-align:center;color:#888;padding:24px;">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= date('d-m-Y H:i:s', strtotime($r['created_at']) + $toIST) ?></td>
                        <td><?= htmlspecialchars($r['temperature']) ?></td>
                        <td><?= htmlspecialchars($r['humidity']) ?></td>
                        <td><?= htmlspecialchars($r['sunlight']) ?></td>
                        <td><?= htmlspecialchars($r['rainfall']) ?></td>
                        <td><?= htmlspecialchars($r['leaf_wetness']) ?></td>
                        <td>
                            <?php if ($r['dsi'] !== null): ?>
                            <span class="risk-badge <?= riskClass($r['risk_level'] ?? '') ?>"
                                  title="<?= htmlspecialchars($r['risk_level'] ?? '') ?>">
                                <?= number_format((float)$r['dsi'], 6) ?>
                            </span>
                            <?php else: ?>
                            <span class="risk-badge risk-na">N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination" id="logs-pagination">
                <?php if ($totalPages > 1): ?>
                <?php
                    $prevPage = max(1, $currentPage - 1);
                    $nextPage = min($totalPages, $currentPage + 1);
                    // Show window of pages around current
                    $startP = max(1, $currentPage - 2);
                    $endP   = min($totalPages, $currentPage + 2);
                ?>
                <a href="<?= pageUrl2(1, $filterUserId, $searchTerm, $searchField) ?>" id="page-first">First</a>
                <?php if ($startP > 1): ?><span>…</span><?php endif; ?>
                <?php for ($p = $startP; $p <= $endP; $p++): ?>
                <a href="<?= pageUrl2($p, $filterUserId, $searchTerm, $searchField) ?>"
                   class="<?= $p === $currentPage ? 'current' : '' ?>"
                   id="page-<?= $p ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($endP < $totalPages): ?><span>…</span><?php endif; ?>
                <a href="<?= pageUrl2($nextPage, $filterUserId, $searchTerm, $searchField) ?>" id="page-next">Next</a>
                <a href="<?= pageUrl2($totalPages, $filterUserId, $searchTerm, $searchField) ?>" id="page-last">Last</a>
                <?php endif; ?>
            </div>
            <p class="total-records">Total Records: <?= number_format($totalRecords) ?></p>
        </div>
    </div>
</div>
</body>
</html>
