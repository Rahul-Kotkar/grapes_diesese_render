<?php
/**
 * Test SMTP Email Delivery
 * Run this in your browser: https://your-domain.onrender.com/api/test_email.php
 */
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$sendgridApiKey = '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';

if ($sendgridApiKey === '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') {
    die("<h3 style='color:red;'>Please replace PUT_YOUR_SENDGRID_API_KEY_HERE with your real API key in api_helper.php and test_email.php!</h3>");
}

$url = 'https://api.sendgrid.com/v3/mail/send';
$data = [
    "personalizations" => [
        [
            "to" => [ ["email" => 'rahul.temp66@gmail.com'] ]
        ]
    ],
    "from" => ["email" => 'rahul.temp66@gmail.com', "name" => "SmartAgri Test"],
    "subject" => 'Test Email from SmartAgri via SendGrid',
    "content" => [
        ["type" => "text/plain", "value" => 'If you receive this, SendGrid HTTP API is working perfectly and bypassing the firewall!']
    ]
];

echo "<h3>Attempting to send email via SendGrid API...</h3>";

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
    CURLOPT_SSL_VERIFYPEER => false,
    CURLINFO_HEADER_OUT => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo "<h3 style='color:red;'>cURL Error:</h3><b>$err</b>";
} else if ($httpCode >= 200 && $httpCode < 300) {
    echo "<h3 style='color:green;'>Success! SendGrid accepted the email. Check your inbox!</h3>";
} else {
    echo "<h3 style='color:red;'>SendGrid API Error (HTTP $httpCode):</h3>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    echo "<p>If you see a 403 Forbidden, it means your API key is invalid or your Sender Identity isn't verified in SendGrid yet.</p>";
}
