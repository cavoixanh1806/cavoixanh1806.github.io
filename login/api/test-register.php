<?php
// Simple test file to check register endpoint
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Test data
$testData = [
    'username' => 'testuser' . time(),
    'email' => 'test' . time() . '@example.com',
    'password' => 'testpass123',
    'confirmPassword' => 'testpass123'
];

echo json_encode([
    'test' => true,
    'data' => $testData,
    'method' => $_SERVER['REQUEST_METHOD'],
    'has_input' => !empty(file_get_contents('php://input')),
    'server_time' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

ob_end_flush();
?>

