<?php 

class AuthMiddleware {
    public static function check() {
        $headers = apache_request_headers();
        if (!isset($headers['Authorization'])) {
            ErrorMManager::throw('AUTH_HEADER_MISSING', 401);
            exit;
        }

        $token = str_replace('Bearer ', '', $headers['Authorization']);
        $user = Auth::validateToken($token);
        if (!$user) {
            ErrorManager::throw('INVALID_TOKEN', 401);
            exit;
        }

        return $user; // örn. ['uid' => 5]
    }
}
