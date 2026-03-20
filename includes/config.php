<?php
/**
 * Database Configuration
 * Configure your MySQL database connection here
 */

// Database Connection Details
define('DB_HOST', 'localhost');        // Database host
define('DB_USER', 'root');             // Database username
define('DB_PASS', '');                 // Database password
define('DB_NAME', 'inventory_db');     // Database name
define('DB_CHARSET', 'utf8mb4');       // Character set

// API Configuration
define('API_RESPONSE_TYPE', 'json');   // Response type for API
define('DEBUG_MODE', true);            // Set to false in production

// Timezone
date_default_timezone_set('UTC');

// CORS Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * Create Database Connection
 */
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($mysqli->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $mysqli->connect_error,
        'error_code' => $mysqli->connect_errno
    ]));
}

// Set charset to UTF-8
$mysqli->set_charset(DB_CHARSET);

// Handle database errors in debug mode
if (DEBUG_MODE) {
    $mysqli->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES'");
}

// Set timeout for connections
$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

?>
