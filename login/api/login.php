<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Configure session cookie to be session-only (no expires)
// This ensures session cookie expires when browser closes
ini_set('session.cookie_lifetime', 0); // 0 = session-only cookie (no expires)
ini_set('session.cookie_httponly', 1); // HttpOnly flag for security
ini_set('session.use_only_cookies', 1); // Use only cookies for session

try {
    session_start();
} catch (Exception $e) {
    error_log("Session start error: " . $e->getMessage());
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check if database.php exists
if (!file_exists(__DIR__ . '/../config/database.php')) {
    error_log("Database config file not found: " . __DIR__ . '/../config/database.php');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Cấu hình database không tìm thấy']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $rawInput = file_get_contents('php://input');
    if ($rawInput === false || empty($rawInput)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dữ liệu đầu vào không hợp lệ']);
        exit;
    }

    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dữ liệu JSON không hợp lệ']);
        exit;
    }

    $usernameOrEmail = trim($input['usernameOrEmail'] ?? $input['email'] ?? '');
    $password = $input['password'] ?? '';
    $rememberMe = isset($input['rememberMe']) && $input['rememberMe'] === true;

    if (empty($usernameOrEmail) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tên đăng nhập/Email và mật khẩu không được để trống']);
        exit;
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        error_log("Database connection failed in login.php");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Lỗi kết nối database']);
        exit;
    }

    // Check if input is email or username
    $isEmail = filter_var($usernameOrEmail, FILTER_VALIDATE_EMAIL);
    
    if ($isEmail) {
        $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE email = ?");
    } else {
        $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE username = ?");
    }
    
    if (!$stmt) {
        error_log("PDO prepare failed: " . implode(", ", $pdo->errorInfo()));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Lỗi chuẩn bị câu lệnh SQL']);
        exit;
    }
    
    $stmt->execute([$usernameOrEmail]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Tên đăng nhập/Email hoặc mật khẩu không đúng']);
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Tên đăng nhập/Email hoặc mật khẩu không đúng']);
        exit;
    }

    // Clear remember_token cookie if not remember me (from previous login)
    if (!$rememberMe && isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['logged_in'] = true;

    // Set cookie if remember me
    if ($rememberMe) {
        $cookieValue = base64_encode($user['id'] . ':' . hash('sha256', $user['password_hash']));
        setcookie('remember_token', $cookieValue, time() + (30 * 24 * 60 * 60), '/', '', true, true);
    } else {
        // Explicitly clear remember_token if not remember me
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Đăng nhập thành công',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
        ],
    ]);
} catch (PDOException $e) {
    error_log("Login PDO error: " . $e->getMessage());
    error_log("PDO error info: " . print_r(isset($pdo) && $pdo ? $pdo->errorInfo() : [], true));
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Đã có lỗi xảy ra khi đăng nhập',
        'error' => $e->getMessage(), // Temporary for debugging
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Exception $e) {
    error_log("Login general error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Đã có lỗi xảy ra khi đăng nhập',
        'error' => $e->getMessage(), // Temporary for debugging
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
