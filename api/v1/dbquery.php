<?php

require_once __DIR__ . '/../../autoload.php';

header('Content-Type: application/json');

// Sadece local IP'lerden erişime izin ver
$clientIp = $_SERVER['REMOTE_ADDR'];
$allowed = ['127.0.0.1', '::1'];

// var_dump($clientIp); // Debugging line to check the client IP
// if (!in_array($clientIp, $allowed)) {
    
//     http_response_code(403);
//     echo json_encode(['error' => 'Access denied']);
//     exit;
// }

// Sadece POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST method allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$query  = $input['query'] ?? null;
$params = $input['params'] ?? [];

if (!$query) {
     
    http_response_code(400);
    echo json_encode(['error' => 'Missing SQL query']);
    exit;
}

// Güvenlik: sadece belirli SQL türlerine izin ver (isteğe göre artırılabilir)
// $allowed_starts = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', ''];
// $starts_ok = false;
// foreach ($allowed_starts as $type) {
//     if (stripos(trim($query), $type) === 0) {
//         $starts_ok = true;
//         break;
//     }
// }

// if (!$starts_ok) {
    
//     http_response_code(400);
//     echo json_encode(['error' => 'Query type not allowed']);
//     exit;
// }

// Sorguyu çalıştır
try {
    $result = DB::execute($query, $params);
    echo json_encode(['result' => $result]);
} catch (Exception $e) {
    
    http_response_code(500);
    echo json_encode(['error' => 'Execution failed', 'details' => $e->getMessage()]);
}
