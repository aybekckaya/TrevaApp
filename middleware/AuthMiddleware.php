<?php 

class AuthMiddleware {
    public static function check() {
        $headers = apache_request_headers();
        if (!isset($headers['Authorization'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Authorization header missing']);
            exit;
        }

        $token = str_replace('Bearer ', '', $headers['Authorization']);
        $user = Auth::validateToken($token);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }

        return $user; // örn. ['uid' => 5]
    }
}
