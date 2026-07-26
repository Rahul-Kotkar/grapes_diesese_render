<?php
/**
 * mockdata.php — Dummy Test Endpoint (NO database required)
 *
 * Use this to confirm your ESP32 can reach the server and send correct data
 * BEFORE the real database is configured.
 *
 * Test URL (same structure as the real endpoint):
 *   http://levetech.infinityfree.io/grapesml/api/mockdata?key=GPRFarm&temp=20&rh=90&sunlight=6&user_id=2&rainfall=2&leafw=3.2
 *
 * ⚠️  DELETE or RENAME this file once real testing is done.
 *      Never leave a mock endpoint open in production.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', 0);

// ── Helper ────────────────────────────────────────────────────────────────────
function respond(bool $success, string $message, array $extra = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(array_merge(
        ['success' => $success, 'message' => $message],
        $extra
    ), JSON_PRETTY_PRINT);
    exit();
}

// ── Only GET ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(false, 'Only GET requests are accepted.', [], 405);
}

// ── Required params (same as real adddata.php) ────────────────────────────────
$required = ['key', 'temp', 'rh', 'sunlight', 'user_id', 'rainfall', 'leafw'];
$missing  = [];

foreach ($required as $param) {
    if (!isset($_GET[$param]) || strlen(trim($_GET[$param])) === 0) {
        $missing[] = $param;
    }
}

if (!empty($missing)) {
    respond(false, 'Missing parameters: ' . implode(', ', $missing), [], 400);
}

// ── API key check ─────────────────────────────────────────────────────────────
if (!hash_equals('GPRFarm', trim($_GET['key']))) {
    respond(false, 'Invalid API key.', [], 401);
}

// ── All good — echo back what was received (no DB write) ─────────────────────
respond(true, 'Mock OK — data received successfully. (No DB write)', [
    'mode'      => 'MOCK / TEST — not saved to database',
    'received'  => [
        'api_key'     => trim($_GET['key']),
        'user_id'     => (int)   $_GET['user_id'],
        'temperature' => (float) $_GET['temp'],
        'humidity'    => (float) $_GET['rh'],
        'sunlight'    => (float) $_GET['sunlight'],
        'rainfall'    => (float) $_GET['rainfall'],
        'leaf_wetness'=> (float) $_GET['leafw'],
    ],
    'timestamp' => date('Y-m-d H:i:s'),
], 200);
