<?php

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../core/Auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ErrorManager::throw('METHOD_NOT_ALLOWED', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? null;

if (!$email || !$password) {
    ErrorManager::throw('INVALID_INPUT', 400);
}

// Kullanıcı var mı?
$existing = DB::execute(SQL::userExistsByEmail(), [$email]);
if (is_array($existing) && count($existing) > 0) {
    ErrorManager::throw('USER_EXISTS', 409);
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Kullanıcıyı ekle (name ve google_id null olarak eklenir)
$success = DB::execute(SQL::insertUser(), [null, $email, $password_hash, null]);

if (!$success) {
    ErrorManager::throw('REGISTER_FAILED', 500);
}

// Yeni eklenen kullanıcı ID'sini al
$newUser = DB::execute("SELECT id FROM users WHERE email = ?", [$email]);
$userId = $newUser[0]['id'] ?? null;

var_dump($newUser, $email, $password);

if (!$userId) {
    ErrorManager::throw('REGISTER_FAILED', 501);
}

// Token üret
$token = Auth::generateToken($userId);

Response::success([
    'message' => 'User registered successfully',
    'token' => $token
]);

