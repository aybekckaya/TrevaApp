<?php 

class AuthMiddleware {
    public static function check() {
        // Headerları al
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
        } else {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$key] = $value;
                }
            }
        }

        if (!isset($headers['Authorization'])) {
            ErrorManager::throw('AUTH_HEADER_MISSING', 401);
        }

        $token = str_replace('Bearer ', '', $headers['Authorization']);
        $user = Auth::validateToken($token);
        if (!$user) {
            ErrorManager::throw('INVALID_TOKEN', 401);
        }

        return $user; // örn. ['uid' => 5]
    }
}
