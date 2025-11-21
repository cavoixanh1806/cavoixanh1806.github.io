<?php
// Configure session cookie to be session-only (no expires)
// This ensures session cookie expires when browser closes
ini_set('session.cookie_lifetime', 0); // 0 = session-only cookie (no expires)
ini_set('session.cookie_httponly', 1); // HttpOnly flag for security
ini_set('session.use_only_cookies', 1); // Use only cookies for session

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối database']);
    exit;
}

// Check if user is logged in via session
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    // Check if remember_token cookie exists
    if (isset($_COOKIE['remember_token'])) {
        try {
            $cookieValue = base64_decode($_COOKIE['remember_token']);
            $parts = explode(':', $cookieValue);
            
            if (count($parts) === 2) {
                $userId = $parts[0];
                $tokenHash = $parts[1];
                
                // Get user from database
                $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if ($user && hash('sha256', $user['password_hash']) === $tokenHash) {
                    // Valid token, restore session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_username'] = $user['username'];
                    $_SESSION['logged_in'] = true;
                } else {
                    // Invalid token, clear cookie
                    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
                    http_response_code(401);
                    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
                    exit;
                }
            } else {
                // Invalid cookie format, clear it
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
                exit;
            }
        } catch (Exception $e) {
            error_log("Remember token error: " . $e->getMessage());
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
            exit;
        }
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
        exit;
    }
}

try {
    $stmt = $pdo->prepare("SELECT id, username, email, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Người dùng không tồn tại']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'created_at' => $user['created_at'],
        ],
    ]);
} catch (PDOException $e) {
    error_log("Get user error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Đã có lỗi xảy ra']);
}
?>
