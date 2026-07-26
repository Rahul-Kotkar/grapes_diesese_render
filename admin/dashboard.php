<?php
/**
 * dashboard.php  —  DSI Disease Risk Chart
 * Shows the last 30 sensor readings (with ML prediction) as a line chart.
 */
require_once 'auth_check.php';
require_once '../api/config.php';

$conn = getDBConnection();

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
$conn->close();

// Reverse so chart is chronological left → right
$rows   = array_reverse($rows);
$labels = [];
$dsiVals = [];
foreach ($rows as $r) {
    $labels[]   = date('d/m H:i', strtotime($r['created_at']));
    $dsiVals[]  = round((float)$r['dsi'], 4);
}

$labelsJson = json_encode($labels);
$dsiJson    = json_encode($dsiVals);
$count      = count($rows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Farm Admin Panel</title>
    <meta name="description" content="Grape disease risk dashboard showing DSI trends from sensor data.">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <div class="topbar"></div>
        <div class="content">
            <h1 class="page-title">Dashboard</h1>

            <?php if ($count === 0): ?>
            <div class="alert" style="background:#fff3cd;border:1px solid #ffe08a;color:#856404;padding:12px 16px;border-radius:6px;">
                No prediction data yet. Sensor readings will appear here once the ESP32 sends data and the ML API responds.
            </div>
            <?php else: ?>
            <div class="chart-wrap">
                <canvas id="dsiChart" height="90"></canvas>
            </div>
            <p class="chart-footer">Last <?= $count ?> Records</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const labels   = <?= $labelsJson ?>;
const dsiVals  = <?= $dsiJson ?>;

if (labels.length > 0) {
    const ctx = document.getElementById('dsiChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Disease Risk (DSI)',
                data: dsiVals,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(180, 180, 180, 0.25)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 2,
                pointHoverRadius: 5,
                pointBackgroundColor: '#dc3545',
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { font: { family: 'Inter', size: 12 }, boxWidth: 28 }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ' DSI: ' + ctx.parsed.y
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: '#e8e8e8' },
                    ticks: { font: { family: 'Inter', size: 11 }, maxRotation: 45 }
                },
                y: {
                    grid: { color: '#e8e8e8' },
                    ticks: { font: { family: 'Inter', size: 11 } }
                }
            }
        }
    });
}
</script>
</body>
</html>
