<?php
/**
 * user_access_log.php  —  Per-user sensor access log
 */
require_once 'auth_check.php';
requireAdmin();
require_once '../api/config.php';

$userId = max(0, (int)($_GET['user_id'] ?? 0));
if ($userId === 0) {
    header('Location: users.php');
    exit();
}

$pageTitle = 'User Access Log';
$conn = getDBConnection();

// Helper to format DB timestamps (which are already in IST from SET time_zone = '+05:30')
function formatToIST(?string $dbTime, string $format = 'd-m-Y H:i:s'): string {
    if (empty($dbTime)) return 'N/A';
    return date($format, strtotime($dbTime));
}

// Get username
$uStmt = $conn->prepare("SELECT username FROM farm_users WHERE id = ?");
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$userRow = $uStmt->get_result()->fetch_assoc();
$username = $userRow ? htmlspecialchars($userRow['username']) : "User #$userId";
$uStmt->close();

// Pagination
$perPage     = 15;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

// Total
$cntStmt = $conn->prepare("SELECT COUNT(*) FROM sensor_data WHERE user_id = ?");
$cntStmt->bind_param('i', $userId);
$cntStmt->execute();
$totalRecords = (int)$cntStmt->get_result()->fetch_row()[0];
$cntStmt->close();

$totalPages  = max(1, (int)ceil($totalRecords / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset      = ($currentPage - 1) * $perPage;

// Fetch
$stmt = $conn->prepare(
    "SELECT created_at, temperature, humidity, sunlight, rainfall, leaf_wetness, dsi, risk_level
     FROM sensor_data WHERE user_id = ?
     ORDER BY created_at DESC LIMIT ? OFFSET ?"
);
$stmt->bind_param('iii', $userId, $perPage, $offset);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

function riskClassAL(string $risk): string {
    return match(strtolower($risk)) {
        'low'    => 'risk-fill-low',
        'medium' => 'risk-fill-medium',
        'high'   => 'risk-fill-high',
        default  => 'risk-fill-na',
    };
}
function palUrl(int $p, int $uid): string {
    return "user_access_log.php?user_id=$uid&page=$p";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Log — <?= $username ?></title>
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
                    <h1 class="page-title">Access Telemetry — <?= $username ?></h1>
                    <p class="page-subtitle">Historical sensor readings in IST (+05:30) for farm account #<?= $userId ?></p>
                </div>
                <a href="users.php" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Back to Users List
                </a>
            </div>

            <!-- Card Table Container -->
            <div class="card-table-container">
                <div class="toolbar">
                    <div class="toolbar-left">
                        <span style="font-size:13px;font-weight:600;color:var(--text-main);">
                            Showing Telemetry Log History for <strong><?= $username ?></strong> (IST +05:30)
                        </span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table" id="access-log-table">
                        <thead>
                            <tr>
                                <th>Timestamp (IST)</th>
                                <th>Temp</th>
                                <th>RH</th>
                                <th>Sunlight</th>
                                <th>Rainfall</th>
                                <th>Leaf Wetness</th>
                                <th style="min-width:180px;">Disease Severity (DSI)</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px;">No sensor records registered for this user account.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): 
                                $dsiVal = $r['dsi'] !== null ? round((float)$r['dsi'], 2) : null;
                                $riskLevel = $r['risk_level'] ?? 'N/A';
                                $fillClass = riskClassAL($riskLevel);
                                $percentage = $dsiVal !== null ? min(100, max(0, $dsiVal)) : 0;
                            ?>
                            <tr>
                                <td>
                                    <span style="font-weight:600;font-size:12.5px;color:var(--text-main);">
                                        <i class="fa-regular fa-clock" style="color:var(--text-light);margin-right:4px;"></i>
                                        <?= formatToIST($r['created_at']) ?>
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
                                    <div class="risk-progress-wrap">
                                        <div class="risk-progress-header">
                                            <span style="font-weight:700;"><?= htmlspecialchars($riskLevel) ?> Risk</span>
                                            <span><?= $dsiVal ?>%</span>
                                        </div>
                                        <div class="risk-bar-container">
                                            <div class="risk-bar-fill <?= $fillClass ?>" style="width: <?= $percentage ?>%;"></div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <span class="badge badge-inactive">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <span class="total-records">Total Telemetry Records: <strong><?= number_format($totalRecords) ?></strong></span>
                    <div class="pagination" id="access-log-pagination">
                        <?php if ($totalPages > 1):
                            $startP = max(1, $currentPage - 2);
                            $endP   = min($totalPages, $currentPage + 2);
                        ?>
                        <a href="<?= palUrl(1, $userId) ?>" id="page-first">First</a>
                        <?php for ($p = $startP; $p <= $endP; $p++): ?>
                        <a href="<?= palUrl($p, $userId) ?>"
                           class="<?= $p === $currentPage ? 'current' : '' ?>"
                           id="page-<?= $p ?>"><?= $p ?></a>
                        <?php endfor; ?>
                        <a href="<?= palUrl($totalPages, $userId) ?>" id="page-last">Last</a>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>
</body>
</html>
