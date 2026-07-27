<?php
/**
 * Test SMTP Email Delivery
 * Run this in your browser: https://your-domain.onrender.com/api/test_email.php
 */
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    die("Composer autoload not found. Build may have failed.");
}

if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    die("PHPMailer is not installed.");
}

$mail = new PHPMailer(true);
try {
    // Enable verbose debug output
    $mail->SMTPDebug = 2; // 2 = client and server messages
    $mail->Debugoutput = 'html';
    
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'rahul.temp66@gmail.com';
    $mail->Password   = 'rahul9002'; // If this is a regular password, it will fail
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('rahul.temp66@gmail.com', 'SmartAgri Test');
    // Send it to the same address for testing
    $mail->addAddress('rahul.temp66@gmail.com');

    $mail->isHTML(false);
    $mail->Subject = 'Test Email from SmartAgri';
    $mail->Body    = 'If you receive this, SMTP is working perfectly!';

    echo "<h3>Attempting to send email...</h3>";
    $mail->send();
    echo "<h3 style='color:green;'>Success! Email sent.</h3>";
} catch (Exception $e) {
    echo "<h3 style='color:red;'>Failed to send email. Error:</h3>";
    echo "<b>" . $mail->ErrorInfo . "</b>";
}
