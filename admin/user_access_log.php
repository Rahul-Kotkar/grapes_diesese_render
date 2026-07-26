<?php
/**
 * user_access_log.php  —  Per-user sensor access log
 * Shows all sensor_data rows for a specific farm_user.
 */
require_once 'auth_check.php';
require_once '../api/config.php';

$userId = max(0, (int)($_GET['user_id'] ?? 0));
if ($userId === 0) {
    header('Location: users.php');
    exit();
}

$conn = getDBConnection();

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
        'low'    => 'risk-low',
        'medium' => 'risk-medium',
        'high'   => 'risk-high',
        default  => 'risk-na',
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
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <div class="topbar"></div>
        <div class="content">
            <h1 class="page-title">Access Log — <?= $username ?></h1>

            <div class="toolbar">
                <div class="toolbar-left">
                    <a href="users.php" class="btn" style="background:#eee;color:#333;border-radius:20px;padding:7px 14px;font-size:12px;">← Back to Users</a>
                </div>
            </div>

            <table class="data-table" id="access-log-table">
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
                    <tr><td colspan="7" style="text-align:center;color:#888;padding:24px;">No records for this user.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= date('d-m-Y (H:i:s)', strtotime($r['created_at'])) ?></td>
                        <td><?= htmlspecialchars($r['temperature']) ?></td>
                        <td><?= htmlspecialchars($r['humidity']) ?></td>
                        <td><?= htmlspecialchars($r['sunlight']) ?></td>
                        <td><?= htmlspecialchars($r['rainfall']) ?></td>
                        <td><?= htmlspecialchars($r['leaf_wetness']) ?></td>
                        <td>
                            <?php if ($r['dsi'] !== null): ?>
                            <span class="risk-badge <?= riskClassAL($r['risk_level'] ?? '') ?>">
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
            <p class="total-records">Total Records: <?= number_format($totalRecords) ?></p>
        </div>
    </div>
</div>
</body>
</html>
