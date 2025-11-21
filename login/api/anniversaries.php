<?php
// Configure session cookie to be session-only (no expires)
ini_set('session.cookie_lifetime', 0);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối database']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Helper function to calculate days since and days until next anniversary
function calculateAnniversaryStats($anniversaryDate) {
    $today = new DateTime();
    $anniversary = new DateTime($anniversaryDate);
    
    // Days since the original date
    $daysSince = $today->diff($anniversary)->days;
    if ($today < $anniversary) {
        $daysSince = 0;
    }
    
    // Calculate next anniversary (same month/day in current or next year)
    $nextAnniversary = new DateTime($anniversaryDate);
    $nextAnniversary->setDate($today->format('Y'), $nextAnniversary->format('m'), $nextAnniversary->format('d'));
    
    // If the anniversary date this year has passed, use next year
    if ($nextAnniversary < $today) {
        $nextAnniversary->modify('+1 year');
    }
    
    $daysUntil = $today->diff($nextAnniversary)->days;
    
    return [
        'days_since' => $daysSince,
        'days_until' => $daysUntil,
        'next_anniversary' => $nextAnniversary->format('Y-m-d')
    ];
}

try {
    if ($method === 'GET') {
        // GET: Fetch all anniversaries for the user
        $stmt = $pdo->prepare("SELECT id, user_id, title, date, created_at, updated_at FROM anniversaries WHERE user_id = ? ORDER BY date ASC");
        $stmt->execute([$userId]);
        $anniversaries = $stmt->fetchAll();
        
        // Calculate stats for each anniversary
        $today = new DateTime();
        $anniversariesWithStats = array_map(function($ann) use ($today) {
            $stats = calculateAnniversaryStats($ann['date']);
            return array_merge($ann, $stats);
        }, $anniversaries);
        
        echo json_encode([
            'success' => true,
            'anniversaries' => $anniversariesWithStats
        ]);
        
    } else if ($method === 'POST') {
        // POST: Create a new anniversary
        $input = json_decode(file_get_contents('php://input'), true);
        $title = trim($input['title'] ?? '');
        $date = $input['date'] ?? '';
        
        if (empty($title) || empty($date)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tiêu đề và ngày không được để trống']);
            exit;
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Định dạng ngày không hợp lệ']);
            exit;
        }
        
        // Insert anniversary
        $stmt = $pdo->prepare("INSERT INTO anniversaries (user_id, title, date) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $title, $date]);
        
        $anniversaryId = $pdo->lastInsertId();
        
        // Get the created anniversary with stats
        $stmt = $pdo->prepare("SELECT id, user_id, title, date, created_at, updated_at FROM anniversaries WHERE id = ?");
        $stmt->execute([$anniversaryId]);
        $anniversary = $stmt->fetch();
        
        $stats = calculateAnniversaryStats($anniversary['date']);
        $anniversary = array_merge($anniversary, $stats);
        
        echo json_encode([
            'success' => true,
            'message' => 'Tạo anniversary thành công',
            'anniversary' => $anniversary
        ]);
        
    } else if ($method === 'PUT') {
        // PUT: Update an existing anniversary
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $date = $input['date'] ?? '';
        
        if ($id <= 0 || empty($title) || empty($date)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID, tiêu đề và ngày không được để trống']);
            exit;
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Định dạng ngày không hợp lệ']);
            exit;
        }
        
        // Verify anniversary belongs to user
        $stmt = $pdo->prepare("SELECT id FROM anniversaries WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $existing = $stmt->fetch();
        
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Anniversary không tồn tại']);
            exit;
        }
        
        // Update anniversary
        $stmt = $pdo->prepare("UPDATE anniversaries SET title = ?, date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
        $stmt->execute([$title, $date, $id, $userId]);
        
        // Get the updated anniversary with stats
        $stmt = $pdo->prepare("SELECT id, user_id, title, date, created_at, updated_at FROM anniversaries WHERE id = ?");
        $stmt->execute([$id]);
        $anniversary = $stmt->fetch();
        
        $stats = calculateAnniversaryStats($anniversary['date']);
        $anniversary = array_merge($anniversary, $stats);
        
        echo json_encode([
            'success' => true,
            'message' => 'Cập nhật anniversary thành công',
            'anniversary' => $anniversary
        ]);
        
    } else if ($method === 'DELETE') {
        // DELETE: Delete an anniversary
        $id = intval($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
            exit;
        }
        
        // Verify anniversary belongs to user
        $stmt = $pdo->prepare("SELECT id FROM anniversaries WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $existing = $stmt->fetch();
        
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Anniversary không tồn tại']);
            exit;
        }
        
        // Delete anniversary
        $stmt = $pdo->prepare("DELETE FROM anniversaries WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Xóa anniversary thành công'
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (PDOException $e) {
    error_log("Anniversaries API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Đã có lỗi xảy ra']);
}
?>

