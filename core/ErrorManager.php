<?php

class ErrorManager {
    private static $errors = [
        'USER_EXISTS' => ['code' => 1001, 'message' => 'User already exists.'],
        'INVALID_INPUT' => ['code' => 1002, 'message' => 'Missing required input.'],
        'DB_ERROR' => ['code' => 1003, 'message' => 'Database error.'],
        'METHOD_NOT_ALLOWED' => ['code' => 1004, 'message' => 'Only POST method allowed'],
        'REGISTER_FAILED' => ['code' => 1005, 'message' => 'User registration failed.']
    ];

    public static function throw($key, $httpStatusCode = 400) {
        http_response_code($httpStatusCode);
        echo json_encode(self::$errors[$key]);
        exit;
    }
}
