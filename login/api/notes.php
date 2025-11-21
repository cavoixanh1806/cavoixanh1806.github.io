<?php
// Configure session cookie to be session-only (no expires)
ini_set('session.cookie_lifetime', 0);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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

try {
    if ($method === 'GET') {
        // GET: Fetch note and replies for a given date
        $noteDate = $_GET['date'] ?? date('Y-m-d');
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $noteDate)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Định dạng ngày không hợp lệ']);
            exit;
        }
        
        // Get note for the date
        $stmt = $pdo->prepare("SELECT id, user_id, note_date, content, created_at, updated_at FROM notes WHERE user_id = ? AND note_date = ?");
        $stmt->execute([$userId, $noteDate]);
        $note = $stmt->fetch();
        
        $replies = [];
        if ($note) {
            // Get replies for the note
            $stmt = $pdo->prepare("
                SELECT nr.id, nr.note_id, nr.user_id, nr.content, nr.created_at, u.username 
                FROM note_replies nr 
                JOIN users u ON nr.user_id = u.id 
                WHERE nr.note_id = ? 
                ORDER BY nr.created_at DESC
            ");
            $stmt->execute([$note['id']]);
            $replies = $stmt->fetchAll();
        }
        
        echo json_encode([
            'success' => true,
            'note' => $note,
            'replies' => $replies
        ]);
        
    } else if ($method === 'POST' && isset($_GET['reply'])) {
        // POST with ?reply: Add a reply to a note
        $input = json_decode(file_get_contents('php://input'), true);
        $noteId = intval($input['note_id'] ?? 0);
        $content = trim($input['content'] ?? '');
        
        if ($noteId <= 0 || empty($content)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Note ID và nội dung không được để trống']);
            exit;
        }
        
        // Verify note exists and belongs to user
        $stmt = $pdo->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
        $stmt->execute([$noteId, $userId]);
        $note = $stmt->fetch();
        
        if (!$note) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Note không tồn tại']);
            exit;
        }
        
        // Insert reply
        $stmt = $pdo->prepare("INSERT INTO note_replies (note_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$noteId, $userId, $content]);
        
        $replyId = $pdo->lastInsertId();
        
        // Get the created reply with username
        $stmt = $pdo->prepare("
            SELECT nr.id, nr.note_id, nr.user_id, nr.content, nr.created_at, u.username 
            FROM note_replies nr 
            JOIN users u ON nr.user_id = u.id 
            WHERE nr.id = ?
        ");
        $stmt->execute([$replyId]);
        $reply = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'Thêm reply thành công',
            'reply' => $reply
        ]);
        
    } else if ($method === 'POST' || $method === 'PUT') {
        // POST/PUT: Create or update a note for a date
        $input = json_decode(file_get_contents('php://input'), true);
        $noteDate = $input['date'] ?? date('Y-m-d');
        $content = trim($input['content'] ?? '');
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $noteDate)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Định dạng ngày không hợp lệ']);
            exit;
        }
        
        if (empty($content)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nội dung note không được để trống']);
            exit;
        }
        
        // Check if note exists for this date
        $stmt = $pdo->prepare("SELECT id FROM notes WHERE user_id = ? AND note_date = ?");
        $stmt->execute([$userId, $noteDate]);
        $existingNote = $stmt->fetch();
        
        if ($existingNote) {
            // Update existing note
            $stmt = $pdo->prepare("UPDATE notes SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
            $stmt->execute([$content, $existingNote['id'], $userId]);
            $noteId = $existingNote['id'];
            $message = 'Cập nhật note thành công';
        } else {
            // Create new note
            $stmt = $pdo->prepare("INSERT INTO notes (user_id, note_date, content) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $noteDate, $content]);
            $noteId = $pdo->lastInsertId();
            $message = 'Tạo note thành công';
        }
        
        // Get the note
        $stmt = $pdo->prepare("SELECT id, user_id, note_date, content, created_at, updated_at FROM notes WHERE id = ?");
        $stmt->execute([$noteId]);
        $note = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'note' => $note
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (PDOException $e) {
    error_log("Notes API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Đã có lỗi xảy ra']);
}
?>

