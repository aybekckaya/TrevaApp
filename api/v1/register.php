<?php

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../core/Auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ErrorManager::throw('METHOD_NOT_ALLOWED', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$name       = trim($input['name'] ?? '');
$email      = trim($input['email'] ?? '');
$password   = $input['password'] ?? null;
$google_id  = $input['google_id'] ?? null;

if (!$email || (!$password && !$google_id)) {
    ErrorManager::throw('INVALID_INPUT', 400);
}

var_dump($name, $email, $password, $google_id);
// Kullanıcı var mı?
$existing = DB::execute(SQL::userExistsByEmail(), [$email]);
if ($existing) {
    ErrorManager::throw('USER_EXISTS', 409);
}

$password_hash = $password ? password_hash($password, PASSWORD_DEFAULT) : null;

// Kullanıcıyı ekle
$success = DB::execute(SQL::insertUser(), [$name, $email, $password_hash, $google_id]);

if (!$success) {
    ErrorManager::throw('REGISTER_FAILED', 500);
}

// Yeni eklenen kullanıcı ID'sini al
$newUser = DB::execute("SELECT id FROM users WHERE email = ?", [$email]);
$userId = $newUser[0]['id'] ?? null;

if (!$userId) {
    ErrorManager::throw('REGISTER_FAILED', 500);
}

// ✅ Token üret
$token = Auth::generateToken($userId);

// 🔁 Başarılı yanıt
echo json_encode([
    'message' => 'User registered successfully',
    'token' => $token
]);
