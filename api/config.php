<?php
/**
 * config.php
 * Database configuration for IoT Sensor Monitoring API
 * Hosted on InfinityFree (MySQL via mysqli)
 *
 * IMPORTANT: Keep this file outside public_html if possible,
 * or protect it with .htaccess to prevent direct browser access.
 */

// ─────────────────────────────────────────────────────────────────────────────
// TIMEZONE — Set to IST (India Standard Time, UTC+5:30)
// This ensures all PHP date() calls and MySQL NOW() store in IST.
// ─────────────────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Kolkata');

// ─────────────────────────────────────────────────────────────────────────────
// DATABASE CREDENTIALS
// Reads from Environment Variables (set in Render Dashboard) or defaults.
// ─────────────────────────────────────────────────────────────────────────────

define('DB_HOST', getenv('DB_HOST') ?: 'sql104.infinityfree.com');
define('DB_NAME', getenv('DB_NAME') ?: 'if0_42485304_sensordb');
define('DB_USER', getenv('DB_USER') ?: 'if0_42485304');
define('DB_PASS', getenv('DB_PASS') ?: 'grapesdesese123');
define('DB_PORT', getenv('DB_PORT') ? (int)getenv('DB_PORT') : 3306);
define('DB_CHARSET', 'utf8mb4');

// ─────────────────────────────────────────────────────────────────────────────
// API SETTINGS
// ─────────────────────────────────────────────────────────────────────────────

define('API_KEY', getenv('API_KEY') ?: 'GPRFarm');
define('BACKFILL_SECRET', getenv('BACKFILL_SECRET') ?: 'gpr_backfill_secret_change_me');


/**
 * Creates and returns a MySQLi database connection.
 * Uses persistent error handling; exits with JSON on failure.
 *
 * @return mysqli  Active database connection object
 */
function getDBConnection(): mysqli
{
    // Suppress default mysqli warnings; we handle errors manually
    mysqli_report(MYSQLI_REPORT_OFF);

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    // Check connection
    if ($conn->connect_error) {
        // Return 500 Internal Server Error — never expose raw error to client
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed. Please try again later.'
        ]);
        exit();
    }

    // Set character encoding to prevent encoding issues
    $conn->set_charset(DB_CHARSET);

    // Set MySQL session timezone to IST so NOW() and timestamps match India time
    $conn->query("SET time_zone = '+05:30'");

    return $conn;
}
