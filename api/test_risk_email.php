<?php
/**
 * Test Risk Email Notification and View User Emails
 * Run this in your browser: https://your-domain.onrender.com/api/test_risk_email.php
 */
require_once 'config.php';
require_once 'api_helper.php';

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = getDBConnection();

// Handle testing a specific user
if (isset($_GET['test_user_id'])) {
    $userId = (int)$_GET['test_user_id'];
    echo "<h2>Testing sendHighRiskNotification for User ID: $userId</h2>";
    
    $sensorData = [
        'temperature'  => 28.5,
        'humidity'     => 92.0,
        'leaf_wetness' => 4.5,
        'dsi'          => 0.75
    ];
    
    // We will capture any outputs/errors
    // Temporarily redefine/run the send logic with output to screen
    $stmt = $conn->prepare("SELECT username, email FROM farm_users WHERE id = ?");
    if (!$stmt) {
        die("<p style='color:red;'>DB Prepare failed: " . $conn->error . "</p>");
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        echo "<p style='color:red;'>User not found in database.</p>";
    } else if (empty($user['email'])) {
        echo "<p style='color:red;'>User '<strong>" . htmlspecialchars($user['username']) . "</strong>' does not have an email set in the database!</p>";
    } else {
        echo "<p>User: <strong>" . htmlspecialchars($user['username']) . "</strong>, Email: <strong>" . htmlspecialchars($user['email']) . "</strong></p>";
        
        // Let's call the helper function directly
        echo "<p>Calling sendHighRiskNotification...</p>";
        
        // We will call the function, but since it doesn't return value or log output to screen, we will also run a debug copy of the cURL request here:
        $to = $user['email'];
        $subject = "High Risk Disease Alert for " . $user['username'];
        $message = "Alert! High disease risk detected by your sensor.\n\n";
        $message .= "Details:\n";
        $message .= "- Temperature: " . $sensorData['temperature'] . " °C\n";
        $message .= "- Humidity: " . $sensorData['humidity'] . " %\n";
        $message .= "- Leaf Wetness: " . $sensorData['leaf_wetness'] . "\n";
        $message .= "- Disease Severity Index (DSI): " . $sensorData['dsi'] . "\n\n";
        $message .= "Please check your dashboard for more information.\n";
        
        $sendgridApiKey = getenv('SENDGRID_API_KEY') ?: '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';
        
        if (empty($sendgridApiKey) || $sendgridApiKey === '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') {
            echo "<p style='color:orange;'>Using PHP mail() fallback because SendGrid API key is not configured/changed.</p>";
            $headers = "From: no-reply@smartagri.com\r\n";
            $headers .= "Reply-To: no-reply@smartagri.com\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            $sent = @mail($to, $subject, $message, $headers);
            echo $sent ? "<p style='color:green;'>PHP mail() sent successfully.</p>" : "<p style='color:red;'>PHP mail() failed.</p>";
        } else {
            echo "<p>Using SendGrid API Key: <code>" . substr($sendgridApiKey, 0, 10) . "...</code></p>";
            $url = 'https://api.sendgrid.com/v3/mail/send';
            $data = [
                "personalizations" => [
                    [
                        "to" => [ ["email" => $to] ]
                    ]
                ],
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
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            
            if ($err) {
                echo "<p style='color:red;'>cURL Error: $err</p>";
            } else if ($httpCode >= 200 && $httpCode < 300) {
                echo "<p style='color:green;'>Success! SendGrid accepted the high-risk alert email. Check your inbox!</p>";
            } else {
                echo "<p style='color:red;'>SendGrid API Error (HTTP $httpCode):</p>";
                echo "<pre>" . htmlspecialchars($response) . "</pre>";
            }
        }
    }
    echo "<hr><a href='test_risk_email.php'>Back to User List</a>";
    exit();
}

// Fetch all users to display
$result = $conn->query("SELECT id, username, email, status FROM farm_users ORDER BY id ASC");
$users = [];
if ($result) {
    $users = $result->fetch_all(MYSQLI_ASSOC);
}
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Risk Email Notifications</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; line-height: 1.6; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f4f4f4; }
        tr:hover { background-color: #f9f9f9; }
        .btn { display: inline-block; padding: 6px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .btn:hover { background: #0056b3; }
        .btn-test { background: #28a745; }
        .btn-test:hover { background: #218838; }
        .warning { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Farm Users & Risk Email Notification Test</h1>
    <p>This utility helps you verify if your users have valid emails and lets you send a test high-risk disease notification email to them.</p>
    
    <table>
        <thead>
            <tr>
                <th>User ID</th>
                <th>Username</th>
                <th>Email Address</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="5">No users found in database.</td></tr>
            <?php else: ?>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td>
                            <?php if (empty($u['email'])): ?>
                                <span class="warning">No email set</span>
                            <?php else: ?>
                                <?= htmlspecialchars($u['email']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $u['status'] == 0 ? 'Active' : 'Inactive' ?></td>
                        <td>
                            <?php if (!empty($u['email'])): ?>
                                <a href="test_risk_email.php?test_user_id=<?= (int)$u['id'] ?>" class="btn btn-test">Send Test Risk Email</a>
                            <?php else: ?>
                                <span style="color:#666;">Set email to test</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
