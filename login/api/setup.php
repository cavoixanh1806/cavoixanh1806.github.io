<?php
// Database setup script
// This script creates all required tables if they don't exist
// Run this file once to set up the database

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: text/html; charset=utf-8');

// Check if database.php exists
if (!file_exists(__DIR__ . '/../config/database.php')) {
    die("Error: Database config file not found at " . __DIR__ . '/../config/database.php');
}

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        die("Error: Could not connect to database. Please check your database credentials in config/database.php");
    }
    
    echo "<h1>Database Setup</h1>";
    echo "<p>Setting up database tables...</p>";
    
    // Read and execute schema.sql
    $schemaFile = __DIR__ . '/../config/schema.sql';
    
    if (!file_exists($schemaFile)) {
        die("Error: Schema file not found at $schemaFile");
    }
    
    $sql = file_get_contents($schemaFile);
    
    if ($sql === false) {
        die("Error: Could not read schema file");
    }
    
    // Split SQL by semicolons and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt) && !preg_match('/^\/\*/', $stmt);
        }
    );
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        // Skip empty or comment-only statements
        if (empty($statement) || preg_match('/^--/', $statement)) {
            continue;
        }
        
        // Remove comments
        $statement = preg_replace('/--.*$/m', '', $statement);
        $statement = preg_replace('/\/\*.*?\*\//s', '', $statement);
        $statement = trim($statement);
        
        if (empty($statement)) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $successCount++;
            
            // Extract table name from CREATE TABLE statement
            if (preg_match('/CREATE TABLE (?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                $tableName = $matches[1];
                echo "<p style='color: green;'>✓ Table '$tableName' created/verified successfully</p>";
            } else {
                echo "<p style='color: green;'>✓ SQL statement executed successfully</p>";
            }
        } catch (PDOException $e) {
            $errorCount++;
            echo "<p style='color: red;'>✗ Error executing SQL: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p style='color: gray; font-size: 12px;'>SQL: " . htmlspecialchars(substr($statement, 0, 100)) . "...</p>";
        }
    }
    
    echo "<hr>";
    echo "<h2>Setup Complete</h2>";
    echo "<p>Successfully executed: $successCount statements</p>";
    if ($errorCount > 0) {
        echo "<p style='color: orange;'>Errors encountered: $errorCount (some tables may already exist, which is OK)</p>";
    } else {
        echo "<p style='color: green;'>All tables created successfully!</p>";
    }
    
    // Verify tables exist
    echo "<h3>Verifying tables:</h3>";
    $requiredTables = ['users', 'notes', 'note_replies', 'anniversaries'];
    
    foreach ($requiredTables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✓ Table '$table' exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Table '$table' does NOT exist</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Error checking table '$table': " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    echo "<hr>";
    echo "<p><strong>Note:</strong> For security, please delete this setup.php file after running it successfully.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Fatal error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>

