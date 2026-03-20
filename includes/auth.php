<?php
/**
 * Authentication Handler
 * Handles login, logout, and session management
 */

session_start();

// Set proper headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load users database
$users_file = __DIR__ . '/users.json';
if (!file_exists($users_file)) {
    createDefaultUsers();
}

// Get the action parameter
$action = isset($_GET['action']) ? sanitize_input($_GET['action']) : '';
$method = $_SERVER['REQUEST_METHOD'];

// Route to appropriate function
if ($method === 'POST' && !$action) {
    handleLogin();
} elseif ($action === 'check_session') {
    handleCheckSession();
} elseif ($action === 'logout') {
    handleLogout();
} else {
    sendErrorResponse('Invalid request');
}

/**
 * Handle login request
 */
function handleLogin() {
    // Check if username and password are provided
    if (!isset($_POST['username']) || !isset($_POST['password'])) {
        sendErrorResponse('Username and password are required');
        return;
    }
    
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password']; // Don't sanitize password as it's hashed
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        sendErrorResponse('Username and password cannot be empty');
        return;
    }
    
    // Load users
    $users_file = __DIR__ . '/users.json';
    $users_content = file_get_contents($users_file);
    $users = json_decode($users_content, true);
    
    // Find user
    $user = null;
    foreach ($users as $u) {
        if ($u['username'] === $username) {
            $user = $u;
            break;
        }
    }
    
    // Verify user and password
    if (!$user || !verifyPassword($password, $user['password'])) {
        logFailedAttempt($username);
        sendErrorResponse('Invalid username or password');
        return;
    }
    
    // Check if user is active
    if (!$user['active']) {
        sendErrorResponse('This account has been disabled');
        return;
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    
    // Create auth token
    $token = generateToken();
    $_SESSION['auth_token'] = $token;
    
    // Set remember me cookie
    if ($remember) {
        $cookie_token = generateToken();
        setcookie('remember_token', $cookie_token, time() + (86400 * 30), '/', '', false, true); // 30 days
        $_SESSION['remember_token'] = $cookie_token;
    }
    
    // Force session to save
    session_write_close();
    
    // Log successful login
    logLoginAttempt($username, true);
    
    // Send success response with token
    sendSuccessResponse([
        'message' => 'Login successful',
        'user_id' => $user['user_id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'auth_token' => $token
    ]);
}

/**
 * Handle session check
 */
function handleCheckSession() {
    $authenticated = isset($_SESSION['user_id']) && isset($_SESSION['username']);
    
    if ($authenticated) {
        sendSuccessResponse([
            'authenticated' => true,
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'] ?? 'user'
        ]);
    } else {
        http_response_code(200);
        echo json_encode([
            'success' => false,
            'data' => [
                'authenticated' => false
            ],
            'message' => 'Not authenticated'
        ]);
        exit;
    }
}

/**
 * Handle logout
 */
function handleLogout() {
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';
    
    // Destroy session
    session_destroy();
    
    // Clear remember token
    setcookie('remember_token', '', time() - 3600, '/');
    
    // Log logout
    logLoginAttempt($username, true, 'logout');
    
    sendSuccessResponse([
        'message' => 'Logged out successfully'
    ]);
}

/**
 * Verify password against hash
 */
function verifyPassword($password, $hash) {
    // Use password_verify for hashed passwords
    if (password_verify($password, $hash)) {
        return true;
    }
    
    // For demo purposes, also allow plain text comparison
    // Remove this in production!
    if ($password === $hash) {
        return true;
    }
    
    return false;
}

/**
 * Generate random token
 */
function generateToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Sanitize user input
 */
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Send success response
 */
function sendSuccessResponse($data) {
    echo json_encode([
        'success' => true,
        'data' => $data,
        'message' => isset($data['message']) ? $data['message'] : 'Request successful'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send error response
 */
function sendErrorResponse($message) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'error' => true
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Create default users if file doesn't exist
 */
function createDefaultUsers() {
    $users = [
        [
            'user_id' => 1,
            'username' => 'admin',
            'password' => 'password123', // Plain text for demo (hash in production)
            'email' => 'admin@inventory.local',
            'role' => 'admin',
            'active' => true,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'user_id' => 2,
            'username' => 'manager',
            'password' => 'manager123',
            'email' => 'manager@inventory.local',
            'role' => 'manager',
            'active' => true,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'user_id' => 3,
            'username' => 'staff',
            'password' => 'staff123',
            'email' => 'staff@inventory.local',
            'role' => 'staff',
            'active' => true,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    $users_file = __DIR__ . '/users.json';
    file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * Log failed login attempt
 */
function logFailedAttempt($username) {
    $log_file = __DIR__ . '/login_attempts.log';
    $log_entry = date('Y-m-d H:i:s') . " - Failed login attempt for user: " . htmlspecialchars($username) . " - IP: " . $_SERVER['REMOTE_ADDR'] . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Log login attempts
 */
function logLoginAttempt($username, $success = true, $type = 'login') {
    $log_file = __DIR__ . '/login_attempts.log';
    $status = $success ? 'SUCCESS' : 'FAILED';
    $log_entry = date('Y-m-d H:i:s') . " - " . strtoupper($type) . " ($status) - User: " . htmlspecialchars($username) . " - IP: " . $_SERVER['REMOTE_ADDR'] . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

?>
