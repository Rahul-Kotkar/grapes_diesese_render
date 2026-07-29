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
    $subject = "High Risk Disease Alert for " . $user['username'] . " (DSI: " . round((float)($sensorData['dsi'] ?? 0), 1) . "%)";
    
    $plainMessage = "SmartAgri Disease Risk Alert\n\n";
    $plainMessage .= "High grape disease risk detected for farm account: " . $user['username'] . "\n\n";
    $plainMessage .= "Telemetry Details:\n";
    $plainMessage .= "- Temperature: " . $sensorData['temperature'] . " °C\n";
    $plainMessage .= "- Humidity: " . $sensorData['humidity'] . " %\n";
    $plainMessage .= "- Leaf Wetness: " . $sensorData['leaf_wetness'] . "\n";
    $plainMessage .= "- Disease Severity Index (DSI): " . round((float)($sensorData['dsi'] ?? 0), 2) . "%\n";
    $plainMessage .= "- Risk Level: " . htmlspecialchars($sensorData['risk_level'] ?? 'High') . "\n\n";
    $plainMessage .= "Please log into your farm dashboard for recommendations.\n";

    // Clean HTML email template to prevent email providers from marking as SPAM
    $htmlMessage = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; color: #111827; }
            .email-card { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
            .email-header { background: linear-gradient(135deg, #ef4444, #dc2626); padding: 24px; text-align: center; color: #ffffff; }
            .email-header h2 { margin: 0; font-size: 20px; font-weight: 700; }
            .email-header p { margin: 6px 0 0 0; font-size: 13px; opacity: 0.9; }
            .email-body { padding: 24px; }
            .data-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
            .data-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; font-size: 13.5px; }
            .data-table tr td:first-child { font-weight: 600; color: #4b5563; width: 45%; }
            .data-table tr td:last-child { color: #111827; font-weight: 500; }
            .badge-high { display: inline-block; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 12px; }
            .btn-action { display: inline-block; background: #16a34a; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 14px; margin-top: 16px; text-align: center; }
            .email-footer { background: #f9fafb; padding: 16px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
        </style>
    </head>
    <body>
        <div class="email-card">
            <div class="email-header">
                <h2>⚠️ High Disease Risk Alert</h2>
                <p>Smart Agriculture IoT Monitoring System</p>
            </div>
            <div class="email-body">
                <p style="font-size:14px;margin-top:0;">Hello <strong>' . htmlspecialchars($user['username']) . '</strong>,</p>
                <p style="font-size:13.5px;color:#4b5563;">High fungal disease risk detected based on real-time IoT sensor telemetry:</p>
                
                <table class="data-table">
                    <tr><td>Risk Level:</td><td><span class="badge-high">⚠️ ' . htmlspecialchars($sensorData['risk_level'] ?? 'High') . ' Risk</span></td></tr>
                    <tr><td>Disease Severity (DSI):</td><td><strong>' . round((float)($sensorData['dsi'] ?? 0), 2) . '% (DSI: ' . round((float)($sensorData['dsi'] ?? 0), 2) . ')</strong></td></tr>
                    <tr><td>Temperature:</td><td>' . htmlspecialchars($sensorData['temperature']) . ' °C</td></tr>
                    <tr><td>Humidity (RH):</td><td>' . htmlspecialchars($sensorData['humidity']) . ' %</td></tr>
                    <tr><td>Leaf Wetness:</td><td>' . htmlspecialchars($sensorData['leaf_wetness']) . '</td></tr>
                </table>

                <div style="text-align:center;">
                    <a href="https://grapes-diesese_render.onrender.com/admin/" class="btn-action">Open Farm Dashboard →</a>
                </div>
            </div>
            <div class="email-footer">
                This is an automated notification from your GPR Farm Monitoring Gateway.<br>
                Please do not reply to this email directly.
            </div>
        </div>
    </body>
    </html>';
    
    // SendGrid API Configuration
    $sendgridApiKey = getenv('SENDGRID_API_KEY') ?: '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';
    $senderEmail    = getenv('SENDGRID_FROM_EMAIL') ?: 'rahul.temp66@gmail.com';
    
    // Fallback to mail() if no API key is provided
    if (empty($sendgridApiKey)) {
        $headers = "From: SmartAgri Alerts <" . $senderEmail . ">\r\n";
        $headers .= "Reply-To: " . $senderEmail . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        @mail($to, $subject, $htmlMessage, $headers);
        return;
    }

    $url = 'https://api.sendgrid.com/v3/mail/send';
    $data = [
        "personalizations" => [
            [
                "to" => [ ["email" => $to, "name" => $user['username']] ]
            ]
        ],
        "from" => ["email" => $senderEmail, "name" => "SmartAgri Alerts"],
        "reply_to" => ["email" => $senderEmail, "name" => "SmartAgri Support"],
        "subject" => $subject,
        "content" => [
            ["type" => "text/plain", "value" => $plainMessage],
            ["type" => "text/html", "value" => $htmlMessage]
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
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        error_log("SendGrid Error: $err");
    }
}
