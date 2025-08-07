<?php

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../core/Auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ErrorManager::throw('METHOD_NOT_ALLOWED', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$email      = trim($input['email'] ?? '');
$password   = $input['password'] ?? null;
$google_id  = $input['google_id'] ?? null;
$name       = trim($input['name'] ?? '');

if (!$password && !$google_id) {
    ErrorManager::throw('INVALID_INPUT', 400); // Giriş yöntemi belirtilmemiş
}

// Giriş email ile yapılıyorsa
if ($password) {
    if (!$email) {
        ErrorManager::throw('INVALID_INPUT', 400); // Email zorunlu
    }

    $user = DB::execute(SQL::getUserByEmail(), [$email]);
    if (!$user || count($user) === 0) {
        ErrorManager::throw('USER_NOT_EXISTS', 401); // Email tanınmıyor
    }

    $user = $user[0];

    if (!password_verify($password, $user['password'])) {
        ErrorManager::throw('INVALID_INPUT', 401); // Şifre yanlış
    }

    if (!empty($user['google_id'])) {
        ErrorManager::throw('INVALID_INPUT', 401); // Google ile kayıtlıysa email+şifreyle girilemez
    }

    // Token üret
    $token = Auth::generateToken($user['id']);
    Response::success([
        'message' => 'Login successful',
        'token' => $token
    ]);
}

// Giriş Google ID ile yapılıyorsa
if ($google_id) {
    $user = DB::execute("SELECT * FROM users WHERE google_id = ?", [$google_id]);

    // Kullanıcı daha önce kayıt olmamışsa → yeni kullanıcı oluştur
    if (!$user || count($user) === 0) {
        DB::execute(SQL::insertUser(), [
            $name,
            null,
            null,
            $google_id
        ]);
        $user = DB::execute("SELECT * FROM users WHERE google_id = ?", [$google_id]);
    }

    $user = $user[0];

    if (!empty($user['email'])) {
        ErrorManager::throw('INVALID_INPUT', 401); // Email ile kayıtlıysa Google ile girilemez
    }

    // Token üret
    $token = Auth::generateToken($user['id']);
    Response::success([
        'message' => 'Login successful',
        'token' => $token
    ]);
}
