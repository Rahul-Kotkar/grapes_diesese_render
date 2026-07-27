<?php
/**
 * backfill.php  —  ML Prediction Backfill Endpoint
 *
 * Called by the GitHub Actions scheduled workflow every 10 minutes.
 * Finds rows in sensor_data where dsi IS NULL (ML prediction was missed)
 * and fills them in by calling the Render ML API.
 *
 * Protected by a secret key — only GitHub Actions (or manual curl) can trigger it.
 *
 * Usage:
 *   GET /grapesml/api/backfill.php?secret=YOUR_BACKFILL_SECRET
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api_helper.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ── Secret key auth ───────────────────────────────────────────────────────────
$incoming = trim($_GET['secret'] ?? '');
if ($incoming === '' || !hash_equals(BACKFILL_SECRET, $incoming)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

// ── Allow longer execution (InfinityFree may cap this, but we ask nicely) ─────
// With 3 rows × 25s timeout = 75s worst case. First cold-start row may fail;
// the next GitHub Actions run (10 min later) will pick it up.
@set_time_limit(120);

// ── Fetch pending rows (NULL dsi, oldest first) ───────────────────────────────
$conn = getDBConnection();

$stmt = $conn->prepare(
    "SELECT id, user_id, temperature, humidity, sunlight, rainfall, leaf_wetness
     FROM sensor_data
     WHERE dsi IS NULL
     ORDER BY created_at ASC
     LIMIT 3"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed.']);
    exit();
}

$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Nothing to do ─────────────────────────────────────────────────────────────
if (empty($rows)) {
    $conn->close();
    echo json_encode([
        'success'   => true,
        'message'   => 'No pending rows — all predictions are up to date.',
        'processed' => 0,
        'failed'    => 0,
        'rows'      => [],
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
    exit();
}

// ── Process each pending row ──────────────────────────────────────────────────
$processed = 0;
$failed    = 0;
$results   = [];

foreach ($rows as $row) {
    // Use a 25-second timeout to give Render extra time to cold-start.
    // The first row in a batch typically wakes Render up; subsequent rows
    // come back in 1–2 seconds.
    $mlResult = callMLApi(
        (float) $row['temperature'],
        (float) $row['humidity'],
        (float) $row['sunlight'],
        (float) $row['rainfall'],
        (float) $row['leaf_wetness'],
        25   // $timeout — longer than adddata.php's 20s
    );

    if (!empty($mlResult) && isset($mlResult['dsi'], $mlResult['risk_level'])) {
        $dsi       = (float)  $mlResult['dsi'];
        $riskLevel = (string) $mlResult['risk_level'];

        $upd = $conn->prepare(
            "UPDATE sensor_data SET dsi = ?, risk_level = ? WHERE id = ?"
        );
        if ($upd) {
            $upd->bind_param('dsi', $dsi, $riskLevel, $row['id']);
            $upd->execute();
            $upd->close();
        }

        // ── Notify if high risk ───────────────────────────────────────────────────────
        if (strtolower($riskLevel) === 'high') {
            $sensorData = [
                'temperature'  => (float) $row['temperature'],
                'humidity'     => (float) $row['humidity'],
                'leaf_wetness' => (float) $row['leaf_wetness'],
                'dsi'          => $dsi
            ];
            sendHighRiskNotification((int)$row['user_id'], $sensorData);
        }

        $results[] = [
            'id'         => (int) $row['id'],
            'status'     => 'ok',
            'dsi'        => $dsi,
            'risk_level' => $riskLevel,
        ];
        $processed++;

    } else {
        // ML API did not respond — Render still cold-starting.
        // This row stays NULL and will be retried on the next scheduled run.
        $results[] = [
            'id'     => (int) $row['id'],
            'status' => 'failed — ML API did not respond (will retry next run)',
        ];
        $failed++;

        // Stop processing further rows in this batch.
        // If Render is cold, waiting for more rows is pointless.
        break;
    }
}

$conn->close();

// ── Return summary ────────────────────────────────────────────────────────────
http_response_code(200);
echo json_encode([
    'success'   => true,
    'processed' => $processed,
    'failed'    => $failed,
    'rows'      => $results,
    'timestamp' => date('Y-m-d H:i:s'),
], JSON_PRETTY_PRINT);
