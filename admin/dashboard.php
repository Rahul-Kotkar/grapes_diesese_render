<?php
/**
 * dashboard.php  —  DSI Disease Risk Chart & Modern SaaS Metrics Dashboard
 */
require_once 'auth_check.php';
require_once '../api/config.php';

$pageTitle = 'Dashboard';
$conn = getDBConnection();

// Helper to format DB timestamps (which are already in IST from SET time_zone = '+05:30')
function formatToIST(?string $dbTime, string $format = 'H:i:s, d M'): string {
    if (empty($dbTime)) return 'Never';
    return date($format, strtotime($dbTime));
}

// Fetch summary metrics
$userCount = (int)($conn->query("SELECT COUNT(*) FROM farm_users")->fetch_row()[0] ?? 0);
$totalRecords = (int)($conn->query("SELECT COUNT(*) FROM sensor_data")->fetch_row()[0] ?? 0);
$todayRecords = (int)($conn->query("SELECT COUNT(*) FROM sensor_data WHERE DATE(created_at) = CURDATE()")->fetch_row()[0] ?? 0);

$avgDsiRow = $conn->query("SELECT AVG(dsi) FROM sensor_data WHERE dsi IS NOT NULL")->fetch_row();
$avgDsi = ($avgDsiRow && $avgDsiRow[0] !== null) ? round((float)$avgDsiRow[0], 2) : 'N/A';

$lastSyncRow = $conn->query("SELECT MAX(created_at) FROM sensor_data")->fetch_row();
$lastSync = formatToIST($lastSyncRow[0] ?? null, 'H:i:s, d M');

// Fetch last 30 sensor readings with prediction for chart
$sql = "SELECT created_at, temperature, humidity, sunlight, rainfall, leaf_wetness, dsi, risk_level
        FROM sensor_data
        WHERE dsi IS NOT NULL
        ORDER BY created_at DESC
        LIMIT 30";

$result = $conn->query($sql);
$rows   = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

// Fetch recent 6 sensor readings for live feed
$recentFeed = array_slice($rows, 0, 6);

$conn->close();

// Reverse so chart is chronological left → right
$chartRows = array_reverse($rows);
$labels    = [];
$dsiVals   = [];
$tempVals  = [];

foreach ($chartRows as $r) {
    $labels[]   = formatToIST($r['created_at'], 'd/m H:i');
    $dsiVals[]  = round((float)$r['dsi'], 2);
    $tempVals[] = round((float)$r['temperature'], 1);
}

$labelsJson = json_encode($labels);
$dsiJson    = json_encode($dsiVals);
$tempJson   = json_encode($tempVals);
$count      = count($rows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Smart Agriculture Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'topbar.php'; ?>
        <div class="content">
            
            <div class="page-header">
                <div class="page-title-wrap">
                    <h1 class="page-title">Grape Farm Overview</h1>
                    <p class="page-subtitle">Real-time IoT sensor telemetry & Ridge ML disease risk predictions (+05:30 IST)</p>
                </div>
            </div>

            <!-- Stats Widgets Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Total Active Devices</span>
                        <div class="stat-card-icon icon-emerald">
                            <i class="fa-solid fa-microchip"></i>
                        </div>
                    </div>
                    <div class="stat-card-body">
                        <span class="stat-card-value">1 Device</span>
                        <span class="stat-card-desc">
                            <span class="trend-badge trend-up"><i class="fa-solid fa-signal"></i> Online</span> ESP32 Gateway
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Registered Users</span>
                        <div class="stat-card-icon icon-blue">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-card-body">
                        <span class="stat-card-value"><?= number_format($userCount) ?></span>
                        <span class="stat-card-desc">
                            <span class="trend-badge trend-neutral">Farm Admins</span> Authorized access
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Total Sensor Data</span>
                        <div class="stat-card-icon icon-purple">
                            <i class="fa-solid fa-database"></i>
                        </div>
                    </div>
                    <div class="stat-card-body">
                        <span class="stat-card-value"><?= number_format($totalRecords) ?></span>
                        <span class="stat-card-desc">
                            <span class="trend-badge trend-up"><i class="fa-solid fa-arrow-up"></i> Active</span> Database records
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Avg Disease Risk (DSI)</span>
                        <div class="stat-card-icon icon-amber">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>
                    <div class="stat-card-body">
                        <span class="stat-card-value"><?= $avgDsi ?>%</span>
                        <span class="stat-card-desc">
                            <span class="trend-badge trend-neutral">ML Severity</span> Ridge Model Output
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Today's Ingestions</span>
                        <div class="stat-card-icon icon-green">
                            <i class="fa-solid fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="stat-card-body">
                        <span class="stat-card-value"><?= number_format($todayRecords) ?></span>
                        <span class="stat-card-desc">
                            <span class="trend-badge trend-up">Recorded</span> IST Today
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Last Data Sync (IST)</span>
                        <div class="stat-card-icon icon-rose">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </div>
                    </div>
                    <div class="stat-card-body">
                        <span class="stat-card-value" style="font-size:17px;line-height:1.2;"><?= htmlspecialchars($lastSync) ?></span>
                        <span class="stat-card-desc">
                            <span class="trend-badge trend-up"><i class="fa-solid fa-circle"></i> Live</span> IST (+5:30)
                        </span>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Layout Grid -->
            <div class="dashboard-grid">
                <!-- Left: Chart Widget -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title-area">
                            <h2 class="chart-title">Disease Severity Index (DSI) & Climate Trends</h2>
                            <p class="chart-subtitle">Showing recent <?= $count ?> telemetry readings & ML predictions in IST (+5:30)</p>
                        </div>
                    </div>

                    <?php if ($count === 0): ?>
                    <div class="alert alert-warning">
                        <i class="fa-solid fa-circle-info"></i> No sensor prediction data recorded yet.
                    </div>
                    <?php else: ?>
                    <div class="chart-canvas-wrap">
                        <canvas id="dsiChart" height="280"></canvas>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right: Recent Activity Feed Widget -->
                <div class="activity-card">
                    <div class="activity-header">
                        <h2 class="chart-title">Recent Telemetry Feed</h2>
                        <a href="logs.php" style="font-size:12px;font-weight:600;color:var(--primary);">View All →</a>
                    </div>
                    <div class="activity-list">
                        <?php if (empty($recentFeed)): ?>
                        <p style="font-size:13px;color:var(--text-muted);">No recent records.</p>
                        <?php else: ?>
                            <?php foreach ($recentFeed as $feed): 
                                $risk = strtolower($feed['risk_level'] ?? 'low');
                                $badgeClass = match($risk) {
                                    'low' => 'badge-active',
                                    'medium' => 'trend-badge trend-neutral',
                                    'high' => 'trend-badge trend-down',
                                    default => 'badge-inactive'
                                };
                            ?>
                            <div class="activity-item">
                                <div class="activity-meta">
                                    <div class="activity-icon">
                                        <i class="fa-solid fa-temperature-half"></i>
                                    </div>
                                    <div class="activity-details">
                                        <span class="activity-time"><?= formatToIST($feed['created_at'], 'H:i:s, d M') ?></span>
                                        <span class="activity-sensors"><?= $feed['temperature'] ?>°C | <?= $feed['humidity'] ?>% RH | Leaf: <?= $feed['leaf_wetness'] ?></span>
                                    </div>
                                </div>
                                <span class="<?= $badgeClass ?>">
                                    <?= htmlspecialchars($feed['risk_level'] ?? 'N/A') ?> (<?= round((float)$feed['dsi'], 1) ?>%)
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
const labels   = <?= $labelsJson ?>;
const dsiVals  = <?= $dsiJson ?>;
const tempVals = <?= $tempJson ?>;

if (labels.length > 0) {
    const ctx = document.getElementById('dsiChart').getContext('2d');
    const isMobile = window.innerWidth <= 576;
    
    // Gradient fill for chart
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(22, 163, 74, 0.25)');
    gradient.addColorStop(1, 'rgba(22, 163, 74, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Disease Risk (DSI %)',
                    data: dsiVals,
                    borderColor: '#16a34a',
                    backgroundColor: gradient,
                    borderWidth: isMobile ? 2 : 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: isMobile ? 2 : 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#16a34a',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 1.5,
                    yAxisID: 'y'
                },
                {
                    label: 'Temperature (°C)',
                    data: tempVals,
                    borderColor: '#3b82f6',
                    borderWidth: 1.5,
                    borderDash: [4, 4],
                    fill: false,
                    tension: 0.3,
                    pointRadius: 0,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: isMobile ? 'bottom' : 'top',
                    align: isMobile ? 'center' : 'end',
                    labels: {
                        font: { family: 'Inter', size: isMobile ? 10.5 : 12, weight: '500' },
                        usePointStyle: true,
                        boxWidth: 6,
                        padding: isMobile ? 10 : 15
                    }
                },
                tooltip: {
                    backgroundColor: '#111827',
                    padding: isMobile ? 8 : 12,
                    titleFont: { family: 'Inter', size: 12, weight: '600' },
                    bodyFont: { family: 'Inter', size: 11 },
                    cornerRadius: 8,
                    displayColors: true
                }
            },
            scales: {
                x: {
                    grid: { color: '#f3f4f6' },
                    ticks: {
                        font: { family: 'Inter', size: isMobile ? 9.5 : 11 },
                        color: '#6b7280',
                        maxRotation: isMobile ? 0 : 45,
                        autoSkip: true,
                        maxTicksLimit: isMobile ? 6 : 12
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    grid: { color: '#f3f4f6' },
                    ticks: { font: { family: 'Inter', size: isMobile ? 9.5 : 11 }, color: '#6b7280' },
                    title: { display: !isMobile, text: 'DSI Index (%)', font: { family: 'Inter', size: 11 } }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { font: { family: 'Inter', size: isMobile ? 9.5 : 11 }, color: '#3b82f6' },
                    title: { display: !isMobile, text: 'Temp (°C)', font: { family: 'Inter', size: 11 } }
                }
            }
        }
    });
}
</script>
</body>
</html>
