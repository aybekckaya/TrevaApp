<?php

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../core/ErrorManager.php';

header('Content-Type: application/json');

// Sadece local IP'lerden erişime izin ver
$clientIp = $_SERVER['REMOTE_ADDR'];
$allowed = ['127.0.0.1', '::1'];


// Sadece POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ErrorManager::throw('METHOD_NOT_ALLOWED', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$query  = $input['query'] ?? null;
$params = $input['params'] ?? [];

if (!$query) {
    ErrorManager::throw('INVALID_INPUT', 400);
}


// Sorguyu çalıştır
try {
    $result = DB::execute($query, $params);
    Response::success(['result' => $result]);
} catch (Exception $e) {
    Response::error('Execution failed: ' . $e->getMessage(), 1003, 500);
}