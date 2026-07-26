<?php
/**
 * logs.php  —  All Sensor Logs (paginated & interactive)
 */
require_once 'auth_check.php';
require_once '../api/config.php';

$pageTitle = 'Sensor Telemetry Logs';
$conn = getDBConnection();

// ── Detect MySQL server's UTC offset so we can display all times in IST ──────
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

function pageUrl2(int $p, int $uid, string $q, string $by): string {
    $qs = http_build_query(array_filter(['page' => $p, 'user_id' => $uid ?: null, 'q' => $q, 'search_by' => $by]));
    return 'logs.php?' . $qs;
}

function riskClass(string $risk): string {
    return match(strtolower($risk)) {
        'low'    => 'risk-fill-low',
        'medium' => 'risk-fill-medium',
        'high'   => 'risk-fill-high',
        default  => 'risk-fill-na',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs — Farm Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        @media print { 
            .sidebar, .topbar, .toolbar, .table-footer, .page-header { display: none !important; } 
            .main { margin-left: 0 !important; }
            .content { padding: 0 !important; }
            .card-table-container { border: none !important; box-shadow: none !important; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'topbar.php'; ?>
        <div class="content">

            <div class="page-header">
                <div class="page-title-wrap">
                    <h1 class="page-title">Sensor Telemetry & Disease Risk Logs<?= $filterUserId ? ' (User #' . $filterUserId . ')' : '' ?></h1>
                    <p class="page-subtitle">Real-time IoT sensor readings with calculated Ridge ML severity predictions</p>
                </div>
                <div style="display:flex;gap:10px;">
                    <button class="btn btn-secondary" onclick="window.location.reload()" title="Refresh Logs">
                        <i class="fa-solid fa-rotate"></i> Refresh
                    </button>
                    <button class="btn btn-print" onclick="window.print()" id="btn-print-all">
                        <i class="fa-solid fa-print"></i> Print Report
                    </button>
                </div>
            </div>

            <!-- Card Table Container -->
            <div class="card-table-container">
                <!-- Toolbar -->
                <div class="toolbar">
                    <div class="toolbar-left">
                        <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                            <?php if ($filterUserId): ?>
                            <input type="hidden" name="user_id" value="<?= $filterUserId ?>">
                            <?php endif; ?>
                            <select name="search_by" class="search-select" id="search-by-select">
                                <option value="risk_level"   <?= $searchField==='risk_level'   ? 'selected' : '' ?>>Filter by Risk Level</option>
                                <option value="temperature"  <?= $searchField==='temperature'  ? 'selected' : '' ?>>Temperature</option>
                                <option value="humidity"     <?= $searchField==='humidity'     ? 'selected' : '' ?>>RH (Humidity)</option>
                                <option value="sunlight"     <?= $searchField==='sunlight'     ? 'selected' : '' ?>>Sunlight</option>
                                <option value="rainfall"     <?= $searchField==='rainfall'     ? 'selected' : '' ?>>Rainfall</option>
                                <option value="leaf_wetness" <?= $searchField==='leaf_wetness' ? 'selected' : '' ?>>Leaf Wetness</option>
                            </select>

                            <div class="search-box-wrap">
                                <i class="fa-solid fa-magnifying-glass search-box-icon"></i>
                                <input type="text" name="q" class="search-input" id="search-input"
                                       value="<?= htmlspecialchars($searchTerm) ?>"
                                       placeholder="Type to search logs...">
                            </div>
                        </form>
                    </div>

                    <div class="toolbar-right">
                        <?php if ($filterUserId): ?>
                        <a href="logs.php" class="btn btn-secondary" style="font-size:12px;">← View All Users Logs</a>
                        <?php endif; ?>
                        <button class="btn btn-secondary" onclick="exportLogsCSV()" id="btn-export-csv">
                            <i class="fa-solid fa-file-csv"></i> Export CSV
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="data-table" id="logs-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Temperature</th>
                                <th>Humidity</th>
                                <th>Sunlight</th>
                                <th>Rainfall</th>
                                <th>Leaf Wetness</th>
                                <th style="min-width:200px;">Disease Severity (DSI)</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px;">No sensor telemetry records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): 
                                $dsiVal = $r['dsi'] !== null ? round((float)$r['dsi'], 2) : null;
                                $riskLevel = $r['risk_level'] ?? 'N/A';
                                $fillClass = riskClass($riskLevel);
                                $percentage = $dsiVal !== null ? min(100, max(0, $dsiVal)) : 0;
                            ?>
                            <tr>
                                <td>
                                    <span style="font-weight:600;font-size:12.5px;color:var(--text-main);">
                                        <i class="fa-regular fa-clock" style="color:var(--text-light);margin-right:4px;"></i>
                                        <?= date('d M Y, H:i:s', strtotime($r['created_at']) + $toIST) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="sensor-cell sensor-temp">
                                        <i class="fa-solid fa-temperature-half"></i> <?= htmlspecialchars($r['temperature']) ?>°C
                                    </span>
                                </td>
                                <td>
                                    <span class="sensor-cell sensor-rh">
                                        <i class="fa-solid fa-droplet"></i> <?= htmlspecialchars($r['humidity']) ?>%
                                    </span>
                                </td>
                                <td>
                                    <span class="sensor-cell sensor-sun">
                                        <i class="fa-solid fa-sun"></i> <?= htmlspecialchars($r['sunlight']) ?> hrs
                                    </span>
                                </td>
                                <td>
                                    <span class="sensor-cell sensor-rain">
                                        <i class="fa-solid fa-cloud-showers-heavy"></i> <?= htmlspecialchars($r['rainfall']) ?> mm
                                    </span>
                                </td>
                                <td>
                                    <span class="sensor-cell sensor-leaf">
                                        <i class="fa-solid fa-leaf"></i> <?= htmlspecialchars($r['leaf_wetness']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($dsiVal !== null): ?>
                                    <div class="risk-progress-wrap" title="DSI: <?= number_format((float)$r['dsi'], 4) ?> | Risk: <?= htmlspecialchars($riskLevel) ?>">
                                        <div class="risk-progress-header">
                                            <span style="font-weight:700;color:var(--text-main);"><?= htmlspecialchars($riskLevel) ?> Risk</span>
                                            <span style="color:var(--text-muted);"><?= $dsiVal ?>%</span>
                                        </div>
                                        <div class="risk-bar-container">
                                            <div class="risk-bar-fill <?= $fillClass ?>" style="width: <?= $percentage ?>%;"></div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <span class="badge badge-inactive">N/A (Pending)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Pagination -->
                <div class="table-footer">
                    <span class="total-records">Total Telemetry Records: <strong><?= number_format($totalRecords) ?></strong></span>
                    <div class="pagination" id="logs-pagination">
                        <?php if ($totalPages > 1): ?>
                        <?php
                            $prevPage = max(1, $currentPage - 1);
                            $nextPage = min($totalPages, $currentPage + 1);
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
                </div>

            </div>

        </div>
    </div>
</div>

<script>
function exportLogsCSV() {
    const table = document.getElementById('logs-table');
    let csv = [];
    for (let row of table.rows) {
        let cols = Array.from(row.cells).map(c => '"' + c.innerText.trim().replace(/"/g, '""') + '"');
        csv.push(cols.join(','));
    }
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sensor_telemetry_logs.csv';
    a.click();
}
</script>
</body>
</html>
