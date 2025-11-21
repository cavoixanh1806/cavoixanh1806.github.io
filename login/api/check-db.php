<?php
// Quick database check script
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot connect to database',
            'check' => 'connection_failed'
        ]);
        exit;
    }
    
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $usersTableExists = $stmt->rowCount() > 0;
    
    // Check other tables
    $tables = ['users', 'notes', 'note_replies', 'anniversaries'];
    $tableStatus = [];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $tableStatus[$table] = $stmt->rowCount() > 0;
    }
    
    if (!$usersTableExists) {
        // Try to create users table
        try {
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
            $usersTableExists = true;
            $tableStatus['users'] = true;
            $created = true;
        } catch (PDOException $e) {
            $created = false;
            $createError = $e->getMessage();
        }
    } else {
        $created = false;
        $createError = null;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Database check completed',
        'connected' => true,
        'tables' => $tableStatus,
        'users_table_exists' => $usersTableExists,
        'users_table_created' => $created ?? false,
        'create_error' => $createError ?? null
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error checking database',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
?>

