<?php
require_once 'config.php';

header('Content-Type: application/json');

$conn = getDBConnection();

$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

$sql = "SELECT id, created_at, temperature, humidity, dsi 
        FROM sensor_data 
        WHERE (risk_level = 'High' OR risk_level = 'Very High') AND id > ? 
        ORDER BY id DESC LIMIT 1";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $lastId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'alert' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No new alerts']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Query failed']);
}

$conn->close();
