<?php
// Debug version of register.php with detailed logging
ob_start();

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

function sendJsonResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if ($json === false) {
        $json = json_encode([
            'success' => false,
            'message' => 'JSON encoding failed',
            'json_error' => json_last_error_msg()
        ], JSON_UNESCAPED_UNICODE);
    }
    echo $json;
    ob_end_flush();
    exit;
}

try {
    session_start();
} catch (Exception $e) {
    error_log("Session error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendJsonResponse(['success' => true], 200);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!file_exists(__DIR__ . '/../config/database.php')) {
    sendJsonResponse(['success' => false, 'message' => 'Database config not found'], 500);
}

require_once __DIR__ . '/../config/database.php';

try {
    $rawInput = file_get_contents('php://input');
    
    if ($rawInput === false || empty($rawInput)) {
        sendJsonResponse(['success' => false, 'message' => 'No input data'], 400);
    }

    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Invalid JSON',
            'json_error' => json_last_error_msg(),
            'raw_input' => substr($rawInput, 0, 100)
        ], 400);
    }

    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirmPassword'] ?? '';

    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        sendJsonResponse(['success' => false, 'message' => 'Missing required fields'], 400);
    }

    if (strlen($username) < 3) {
        sendJsonResponse(['success' => false, 'message' => 'Username too short'], 400);
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid username format'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid email'], 400);
    }

    if (strlen($password) < 8) {
        sendJsonResponse(['success' => false, 'message' => 'Password too short'], 400);
    }

    if ($password !== $confirmPassword) {
        sendJsonResponse(['success' => false, 'message' => 'Passwords do not match'], 400);
    }

    $pdo = getDBConnection();
    
    if (!$pdo) {
        sendJsonResponse(['success' => false, 'message' => 'Database connection failed'], 500);
    }

    // Check if username exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        sendJsonResponse(['success' => false, 'message' => 'Username already exists'], 409);
    }

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendJsonResponse(['success' => false, 'message' => 'Email already exists'], 409);
    }

    // Hash and insert
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    if ($passwordHash === false) {
        sendJsonResponse(['success' => false, 'message' => 'Password hashing failed'], 500);
    }

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
    $result = $stmt->execute([$username, $email, $passwordHash]);

    if (!$result) {
        $errorInfo = $stmt->errorInfo();
        sendJsonResponse([
            'success' => false,
            'message' => 'Insert failed',
            'error' => $errorInfo[2] ?? 'Unknown error'
        ], 500);
    }

    $userId = $pdo->lastInsertId();
    
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_username'] = $username;
    $_SESSION['logged_in'] = true;

    sendJsonResponse([
        'success' => true,
        'message' => 'Registration successful',
        'user' => [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
        ],
    ], 200);

} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ], 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Registration failed',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
} catch (Error $e) {
    error_log("Fatal Error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Fatal error',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
}
?>

