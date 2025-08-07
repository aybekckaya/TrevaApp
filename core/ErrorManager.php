<?php

require_once __DIR__ . '/Response.php';

class ErrorManager {
    private static $errors = [
        'USER_EXISTS' => ['code' => 1001, 'message' => 'User already exists.'],
        'INVALID_INPUT' => ['code' => 1002, 'message' => 'Missing required input.'],
        'DB_ERROR' => ['code' => 1003, 'message' => 'Database error.'],
        'METHOD_NOT_ALLOWED' => ['code' => 1004, 'message' => 'Only POST method allowed'],
        'REGISTER_FAILED' => ['code' => 1005, 'message' => 'User registration failed.'],
        'ENDPOINT_NOT_FOUND' => ['code' => 1006, 'message' => 'API endpoint not found.'],
        'INVALID_TOKEN' => ['code' => 1007, 'message' => 'Invalid API token.'],
        'AUTH_HEADER_MISSING' => ['code' => 1008, 'message' => 'Authorization header is missing.'],
        'USER_NOT_EXISTS' => ['code' => 1009, 'message' => 'User does not exist.']
    ];

    public static function throw($key, $httpStatusCode = 400) {
        $error = self::$errors[$key];
        Response::error($error['message'], $error['code'], $httpStatusCode);
    }
}
