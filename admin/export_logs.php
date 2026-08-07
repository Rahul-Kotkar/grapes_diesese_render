<?php
/**
 * export_logs.php  —  Export Sensor Telemetry Logs to CSV with Date Range Options
 */
require_once 'auth_check.php';
require_once '../api/config.php';

$conn = getDBConnection();

// Role-based user filter
$filterUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!isAdmin() && !empty($_SESSION['user_id'])) {
    $filterUserId = (int)$_SESSION['user_id'];
}

if ($filterUserId > 0) {
    $cntCheck = $conn->prepare("SELECT COUNT(*) FROM sensor_data WHERE user_id = ?");
    if ($cntCheck) {
        $cntCheck->bind_param('i', $filterUserId);
        $cntCheck->execute();
        $userCnt = (int)$cntCheck->get_result()->fetch_row()[0];
        $cntCheck->close();
    }
    if ($userCnt === 0) {
        // Fallback: If requested user_id has 0 records, export all available system telemetry
        $filterUserId = 0;
    }
}

// Range filter: all, this_month, 6_months, year
$range = isset($_GET['range']) ? trim($_GET['range']) : 'all';

// Search filters
$searchField = in_array($_GET['search_by'] ?? '', ['temperature','humidity','sunlight','rainfall','leaf_wetness','risk_level']) ? $_GET['search_by'] : 'risk_level';
$searchTerm  = trim($_GET['q'] ?? '');

$conditions = [];
$params     = [];
$types      = '';

// User condition
if ($filterUserId > 0) {
    $conditions[] = 'user_id = ?';
    $params[]     = $filterUserId;
    $types       .= 'i';
}

// Search term condition
if ($searchTerm !== '') {
    $conditions[] = "`$searchField` LIKE ?";
    $params[]     = '%' . $searchTerm . '%';
    $types       .= 's';
}

// Date Range condition
switch ($range) {
    case 'this_month':
        $conditions[] = "created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')";
        break;
    case '6_months':
    case '6_moth':
        $conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        break;
    case 'year':
    case '1_year':
        $conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'all':
    default:
        // No date constraint
        break;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Query data
$sql = "SELECT id, created_at, user_id, temperature, humidity, sunlight, rainfall, leaf_wetness, dsi, risk_level
        FROM sensor_data
        $where
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

// Prepare CSV Output
$filename = "sensor_telemetry_" . $range . "_" . date('Y-m-d_H-i') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, "\xEF\xBB\xBF");

// CSV Header
fputcsv($output, [
    'Record ID',
    'Timestamp (IST)',
    'User ID',
    'Temperature (°C)',
    'Humidity (%)',
    'Sunlight (hrs)',
    'Rainfall (mm)',
    'Leaf Wetness',
    'DSI Value',
    'Risk Level'
]);

if ($stmt) {
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Stream row by row to prevent memory spikes
    while ($r = $result->fetch_assoc()) {
        $formattedTime = !empty($r['created_at']) ? date('d-m-Y H:i:s', strtotime($r['created_at'])) : 'N/A';
        $dsiVal = $r['dsi'] !== null ? sprintf("%.6f", (float)$r['dsi'] / 100) : 'N/A';

        fputcsv($output, [
            $r['id'],
            $formattedTime,
            $r['user_id'] ?? '1',
            $r['temperature'],
            $r['humidity'],
            $r['sunlight'],
            $r['rainfall'],
            $r['leaf_wetness'],
            $dsiVal,
            $r['risk_level'] ?? 'N/A'
        ]);
    }
    $stmt->close();
}
$conn->close();

fclose($output);
exit();
