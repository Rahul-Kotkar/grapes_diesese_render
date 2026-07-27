<?php
/**
 * adddata.php  (v4 — fast response to hardware)
 * IoT Sensor Data Ingestion Endpoint
 *
 * Flow:
 *  1. Validate & sanitize input
 *  2. INSERT sensor row into DB
 *  3. Try ML API with a SHORT timeout (5s) — just one attempt
 *  4. If ML responds → store dsi + risk_level in same row ✅
 *  5. If ML is sleeping → row stays NULL, backfill will retry later
 *  6. Respond 201 to hardware immediately (never > ~6s total)
 *
 * WHY short timeout: ESP32 HTTPClient times out at ~10s by default.
 * A long retry loop here blocks the hardware. The backfill workflow
 * handles NULL rows asynchronously.
 *
 * Usage:
 *   adddata.php?key=GPRFarm&temp=20&rh=90&sunlight=6&user_id=2&rainfall=2&leafw=3.2
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ── Helper ────────────────────────────────────────────────────────────────────
function sendResponse(bool $success, string $message, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

// ── Method check ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Only GET requests are accepted.', 405);
}

// ── Required params ───────────────────────────────────────────────────────────
$requiredParams = [
    'key'      => 'API key',
    'temp'     => 'Temperature',
    'rh'       => 'Humidity (RH)',
    'sunlight' => 'Sunlight',
    'user_id'  => 'User ID',
    'rainfall' => 'Rainfall',
    'leafw'    => 'Leaf Wetness',
];

$missing = [];
foreach ($requiredParams as $param => $label) {
    if (!isset($_GET[$param]) || strlen(trim($_GET[$param])) === 0) {
        $missing[] = $label;
    }
}
if (!empty($missing)) {
    sendResponse(false, 'Missing parameters: ' . implode(', ', $missing) . '.', 400);
}

// ── Sanitize ──────────────────────────────────────────────────────────────────
$apiKey      = trim($_GET['key']);
$temperature = (float) $_GET['temp'];
$humidity    = (float) $_GET['rh'];
$sunlight    = (float) $_GET['sunlight'];
$userId      = (int)   $_GET['user_id'];
$rainfall    = (float) $_GET['rainfall'];
$leafWetness = (float) $_GET['leafw'];

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!hash_equals(API_KEY, $apiKey)) {
    sendResponse(false, 'Invalid API key.', 401);
}

// ── Range validation ──────────────────────────────────────────────────────────
if ($temperature < -50 || $temperature > 100) {
    sendResponse(false, 'Temperature value out of acceptable range (−50 to 100 °C).', 400);
}
if ($humidity < 0 || $humidity > 100) {
    sendResponse(false, 'Humidity value out of acceptable range (0 to 100 %).', 400);
}
if ($userId <= 0) {
    sendResponse(false, 'user_id must be a positive integer.', 400);
}

// ── DB insert ─────────────────────────────────────────────────────────────────
$conn = getDBConnection();

$sql  = "INSERT INTO sensor_data
             (api_key, user_id, temperature, humidity, sunlight, rainfall, leaf_wetness)
         VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log('[SensorAPI] Prepare failed: ' . $conn->error);
    sendResponse(false, 'Server error. Please try again later.', 500);
}

$stmt->bind_param('siddddd', $apiKey, $userId, $temperature, $humidity, $sunlight, $rainfall, $leafWetness);

if (!$stmt->execute()) {
    error_log('[SensorAPI] Execute failed: ' . $stmt->error);
    $stmt->close();
    $conn->close();
    sendResponse(false, 'Failed to store data. Please try again later.', 500);
}

$insertId = $conn->insert_id;
$stmt->close();

// ── ML prediction — ONE quick attempt (5s timeout) ───────────────────────────
// We keep this SHORT so the hardware never waits more than ~6 seconds total.
// If Render is asleep this will timeout — that is expected and acceptable.
// The backfill.php (triggered by GitHub Actions every 10 min) handles NULL rows.
$mlResult = callMLApi($temperature, $humidity, $sunlight, $rainfall, $leafWetness, 5);

if (!empty($mlResult) && isset($mlResult['dsi'], $mlResult['risk_level'])) {
    // ✅ ML was awake — store prediction immediately
    $dsi       = (float)  $mlResult['dsi'];
    $riskLevel = (string) $mlResult['risk_level'];

    $upd = $conn->prepare("UPDATE sensor_data SET dsi = ?, risk_level = ? WHERE id = ?");
    if ($upd) {
        $upd->bind_param('dsi', $dsi, $riskLevel, $insertId);
        $upd->execute();
        $upd->close();
    }
    
    // ── Notify if high risk ───────────────────────────────────────────────────────
    if (strtolower($riskLevel) === 'high') {
        $sensorData = [
            'temperature'  => $temperature,
            'humidity'     => $humidity,
            'leaf_wetness' => $leafWetness,
            'dsi'          => $dsi
        ];
        sendHighRiskNotification($userId, $sensorData);
    }
}
// ❌ ML is sleeping — row stays NULL, backfill picks it up within 10 minutes

$conn->close();

// ── Respond to hardware ───────────────────────────────────────────────────────
// Always 201 regardless of ML result — the sensor data is safely stored.
sendResponse(true, 'Data stored successfully.', 201);
