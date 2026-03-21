<?php
/**
 * Authentication Handler
 * Uses SQLite for users and session-backed login state.
 */

session_start();
require_once __DIR__ . '/runtime.php';
require_once __DIR__ . '/sqlite.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = isset($_GET['action']) ? sanitize_input($_GET['action']) : '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST' && !$action) {
    handleLogin();
} elseif ($action === 'check_session') {
    handleCheckSession();
} elseif ($action === 'logout') {
    handleLogout();
} else {
    sendErrorResponse('Invalid request');
}

function handleLogin() {
    if (!isset($_POST['username']) || !isset($_POST['password'])) {
        sendErrorResponse('Username and password are required');
        return;
    }

    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        sendErrorResponse('Username and password cannot be empty');
        return;
    }

    try {
        $pdo = getSqliteConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !verifyPassword($password, $user['password'])) {
            logFailedAttempt($username);
            sendErrorResponse('Invalid username or password');
            return;
        }

        if ((int) $user['active'] !== 1) {
            sendErrorResponse('This account has been disabled');
            return;
        }

        $_SESSION['user_id'] = (int) $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $token = generateToken();
        $_SESSION['auth_token'] = $token;

        if ($remember) {
            $cookieToken = generateToken();
            setcookie('remember_token', $cookieToken, time() + (86400 * 30), '/', '', false, true);
            $_SESSION['remember_token'] = $cookieToken;
        }

        session_write_close();
        logLoginAttempt($username, true);

        sendSuccessResponse([
            'message' => 'Login successful',
            'user_id' => (int) $user['user_id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'auth_token' => $token
        ]);
    } catch (Throwable $e) {
        sendErrorResponse('Login failed: ' . $e->getMessage());
    }
}

function handleCheckSession() {
    $authenticated = isset($_SESSION['user_id']) && isset($_SESSION['username']);

    if ($authenticated) {
        sendSuccessResponse([
            'authenticated' => true,
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'] ?? 'user'
        ]);
        return;
    }

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

function handleLogout() {
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';

    session_destroy();
    setcookie('remember_token', '', time() - 3600, '/');
    logLoginAttempt($username, true, 'logout');

    sendSuccessResponse([
        'message' => 'Logged out successfully'
    ]);
}

function verifyPassword($password, $hash) {
    if (password_verify($password, $hash)) {
        return true;
    }

    return hash_equals((string) $hash, (string) $password);
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function sendSuccessResponse($data) {
    echo json_encode([
        'success' => true,
        'data' => $data,
        'message' => isset($data['message']) ? $data['message'] : 'Request successful'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function sendErrorResponse($message) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'error' => true
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function logFailedAttempt($username) {
    $logEntry = date('Y-m-d H:i:s') . ' - Failed login attempt for user: ' . htmlspecialchars($username) . ' - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '') . PHP_EOL;
    appendRuntimeLog('login_attempts.log', $logEntry);
}

function logLoginAttempt($username, $success = true, $type = 'login') {
    $status = $success ? 'SUCCESS' : 'FAILED';
    $logEntry = date('Y-m-d H:i:s') . ' - ' . strtoupper($type) . ' (' . $status . ') - User: ' . htmlspecialchars($username) . ' - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '') . PHP_EOL;
    appendRuntimeLog('login_attempts.log', $logEntry);
}
