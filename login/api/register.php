<?php
// Start output buffering IMMEDIATELY to catch any output
ob_start();

// Disable all error display to prevent output before JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Configure session cookie to be session-only (no expires)
ini_set('session.cookie_lifetime', 0);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// Set error handler to prevent fatal errors from outputting
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return true; // Don't execute PHP internal error handler
});

// Set exception handler
set_exception_handler(function($exception) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống không mong đợi',
        'error' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
});

// Set headers first
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Function to send JSON response and exit
function sendJsonResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    ob_clean(); // Clear any output
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    ob_end_flush();
    exit;
}

// Wrap everything in try-catch to ensure JSON response
try {
    session_start();
} catch (Exception $e) {
    error_log("Session start error: " . $e->getMessage());
    // Continue anyway, session might work later
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendJsonResponse(['success' => true], 200);
}

// Check if database.php exists
if (!file_exists(__DIR__ . '/../config/database.php')) {
    error_log("Database config file not found: " . __DIR__ . '/../config/database.php');
    sendJsonResponse([
        'success' => false, 
        'message' => 'Cấu hình database không tìm thấy'
    ], 500);
}

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse([
        'success' => false, 
        'message' => 'Method not allowed'
    ], 405);
}

// Main registration logic - wrapped in try-catch
try {
    $rawInput = file_get_contents('php://input');
    if ($rawInput === false || empty($rawInput)) {
        sendJsonResponse([
            'success' => false, 
            'message' => 'Dữ liệu đầu vào không hợp lệ'
        ], 400);
    }

    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        sendJsonResponse([
            'success' => false, 
            'message' => 'Dữ liệu JSON không hợp lệ'
        ], 400);
    }
    
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirmPassword'] ?? '';

    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        sendJsonResponse([
            'success' => false, 
            'message' => 'Tên đăng nhập, Email và mật khẩu không được để trống'
        ], 400);
    }

    // Validate username
    if (strlen($username) < 3) {
        sendJsonResponse([
            'success' => false, 
            'message' => 'Tên đăng nhập phải có ít nhất 3 ký tự'
        ], 400);
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        sendJsonResponse([
            'success' => false, 
            'message' => 'Tên đăng nhập chỉ được chứa chữ cái, số và dấu gạch dưới'
        ], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse([
            'success' => false, 
            'message' => 'Email không hợp lệ'
        ], 400);
    }

    if (strlen($password) < 8) {
        sendJsonResponse([
            'success' => false, 
            'message' => 'Mật khẩu phải có ít nhất 8 ký tự'
        ], 400);
    }

    if ($password !== $confirmPassword) {
        sendJsonResponse([
            'success' => false, 
            'message' => 'Mật khẩu xác nhận không khớp'
        ], 400);
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        sendJsonResponse([
            'success' => false, 
            'message' => 'Lỗi kết nối database'
        ], 500);
    }

// Check if users table exists, if not create it
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        // Create users table
        $createUsersSQL = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createUsersSQL);
    }
} catch (PDOException $e) {
    error_log("Error checking/creating users table: " . $e->getMessage());
    sendJsonResponse([
        'success' => false, 
        'message' => 'Lỗi khi kiểm tra/tạo bảng users',
        'error' => $e->getMessage()
    ], 500);
}

// Registration logic
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        sendJsonResponse([
            'success' => false, 
            'message' => 'Tên đăng nhập này đã được sử dụng'
        ], 409);
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendJsonResponse([
            'success' => false, 
            'message' => 'Email này đã được sử dụng'
        ], 409);
    }

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
    $stmt->execute([$username, $email, $passwordHash]);

    // Auto login
    $userId = $pdo->lastInsertId();
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_username'] = $username;
    $_SESSION['logged_in'] = true;

    sendJsonResponse([
        'success' => true,
        'message' => 'Đăng ký thành công',
        'user' => [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
        ],
    ], 200);
    
} catch (PDOException $e) {
    error_log("Register PDO error: " . $e->getMessage());
    error_log("PDO error info: " . print_r(isset($pdo) && $pdo ? $pdo->errorInfo() : [], true));
    sendJsonResponse([
        'success' => false, 
        'message' => 'Đã có lỗi xảy ra khi đăng ký',
        'error' => $e->getMessage(), // Temporary for debugging
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
} catch (Exception $e) {
    error_log("Register general error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJsonResponse([
        'success' => false, 
        'message' => 'Đã có lỗi xảy ra khi đăng ký',
        'error' => $e->getMessage(), // Temporary for debugging
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
} catch (Error $e) {
    // Catch PHP 7+ errors (fatal errors) - must be before Throwable
    error_log("Register fatal error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJsonResponse([
        'success' => false, 
        'message' => 'Đã có lỗi xảy ra khi đăng ký',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
} catch (Throwable $e) {
    // Catch any other throwable (must be last)
    error_log("Register throwable error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJsonResponse([
        'success' => false, 
        'message' => 'Đã có lỗi xảy ra khi đăng ký',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
}