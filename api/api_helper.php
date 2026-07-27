<?php
/**
 * api_helper.php
 * Wrapper for the Grapes Disease ML prediction API.
 * Shared by adddata.php (on sensor insert) and the admin panel.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer autoloader (if available)
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

/**
 * Calls the ML prediction model directly (local Python CLI first, then HTTP fallback).
 *
 * @param float $temp      Temperature (°C)
 * @param float $rh        Relative Humidity (%)
 * @param float $sunlight  Sunlight hours
 * @param float $rainfall  Rainfall (mm)
 * @param float $leafw     Leaf Wetness index
 * @return array           ['dsi'=>float, 'risk_level'=>string, ...] or []
 */
function callMLApi(float $temp, float $rh, float $sunlight, float $rainfall, float $leafw, int $timeout = 20): array
{
    // ── STEP 1: Try Local Python Inference (Instant 10ms execution, zero sleeping API) ──
    $predictScript = dirname(__DIR__) . '/model/predict.py';
    if (file_exists($predictScript)) {
        // Escaped CLI arguments: temp, rh, sunlight, rainfall, leafw
        $cmd = sprintf(
            'python3 %s %s %s %s %s %s 2>&1',
            escapeshellarg($predictScript),
            escapeshellarg((string)$temp),
            escapeshellarg((string)$rh),
            escapeshellarg((string)$sunlight),
            escapeshellarg((string)$rainfall),
            escapeshellarg((string)$leafw)
        );

        $output = @shell_exec($cmd);
        if (!empty($output)) {
            $decoded = json_decode(trim($output), true);
            if (is_array($decoded) && isset($decoded['dsi'], $decoded['risk_level'])) {
                return $decoded;
            }
        }
    }

    // ── STEP 2: Fallback to Remote HTTP ML API ──────────────────────────────────────────
    $payload = json_encode([
        'Leafwetness' => $leafw,
        'Rain-level'  => $rainfall,
        'RH'          => $rh,
        'Temp'        => $temp,
        'Sunlight'    => $sunlight,
    ]);

    if (!function_exists('curl_init')) {
        return [];
    }

    $ch = curl_init('https://grapes-render-ml.onrender.com/api/predict');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) {
        return [];
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Sends a notification if the risk level is High.
 * 
 * @param int $userId        The ID of the user to notify
 * @param array $sensorData  Array containing temperature, humidity, leaf_wetness, dsi, risk_level
 */
function sendHighRiskNotification(int $userId, array $sensorData): void
{
    // Try to get user email
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT username, email FROM farm_users WHERE id = ?");
    if (!$stmt) return;
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user || empty($user['email'])) {
        return; // No email to send to
    }
    
    $to = $user['email'];
    $subject = "High Risk Disease Alert for " . $user['username'];
    
    $message = "Alert! High disease risk detected by your sensor.\n\n";
    $message .= "Details:\n";
    $message .= "- Temperature: " . $sensorData['temperature'] . " °C\n";
    $message .= "- Humidity: " . $sensorData['humidity'] . " %\n";
    $message .= "- Leaf Wetness: " . $sensorData['leaf_wetness'] . "\n";
    $message .= "- Disease Severity Index (DSI): " . $sensorData['dsi'] . "\n\n";
    $message .= "Please check your dashboard for more information.\n";
    
    // SendGrid API Configuration
    $sendgridApiKey = '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'; // Replace this with your actual SendGrid API Key
    
    // Fallback to mail() if no API key is provided yet
    if ($sendgridApiKey === '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') {
        $headers = "From: no-reply@smartagri.com\r\n";
        $headers .= "Reply-To: no-reply@smartagri.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        @mail($to, $subject, $message, $headers);
        return;
    }

    $url = 'https://api.sendgrid.com/v3/mail/send';
    $data = [
        "personalizations" => [
            [
                "to" => [ ["email" => $to] ]
            ]
        ],
        // You MUST verify this sender email in your SendGrid account!
        "from" => ["email" => 'rahul.temp66@gmail.com', "name" => "SmartAgri Alerts"],
        "subject" => $subject,
        "content" => [
            ["type" => "text/plain", "value" => $message]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $sendgridApiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false // Render containers sometimes have outdated ca-certs
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        error_log("SendGrid Error: $err");
    }
}
