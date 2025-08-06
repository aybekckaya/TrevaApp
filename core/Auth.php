<?php 


class Auth {
    private static $secret = 'super_secret_key';

    public static function generateToken($userId) {
        $payload = base64_encode(json_encode([
            'uid' => $userId,
            'iat' => time(),
            'exp' => time() + 3600
        ]));
        $signature = hash_hmac('sha256', $payload, self::$secret);
        return $payload . '.' . $signature;
    }

    public static function validateToken($token) {
        list($payload, $signature) = explode('.', $token);
        $expected = hash_hmac('sha256', $payload, self::$secret);
        if ($expected !== $signature) return false;

        $data = json_decode(base64_decode($payload), true);
        if ($data['exp'] < time()) return false;

        return $data;
    }
}
