<?php

require_once __DIR__ . '/Response.php';

class ErrorManager {
    private static $errors = [
        // Genel
        'METHOD_NOT_ALLOWED' => ['code' => 1004, 'message' => 'Method not allowed.'],
        'ENDPOINT_NOT_FOUND' => ['code' => 1006, 'message' => 'API endpoint not found.'],
        'DB_ERROR'           => ['code' => 1003, 'message' => 'Database error.'],

        // Auth
        'AUTH_HEADER_MISSING'=> ['code' => 1008, 'message' => 'Authorization header is missing.'],
        'INVALID_TOKEN'      => ['code' => 1007, 'message' => 'Invalid API token.'],
        'UNAUTHORIZED'       => ['code' => 1010, 'message' => 'Unauthorized.'],

        // Kullanıcı
        'USER_EXISTS'        => ['code' => 1001, 'message' => 'User already exists.'],
        'USER_NOT_EXISTS'    => ['code' => 1009, 'message' => 'User does not exist.'],
        'REGISTER_FAILED'    => ['code' => 1005, 'message' => 'User registration failed.'],

        // Girdi / JSON
        'INVALID_INPUT'      => ['code' => 1002, 'message' => 'Missing or invalid input.'],
        'INVALID_JSON'       => ['code' => 1011, 'message' => 'Invalid JSON body.'],
        'NOT_FOUND'          => ['code' => 1012, 'message' => 'Resource not found.'],
        'NOTHING_TO_UPDATE'  => ['code' => 1013, 'message' => 'Nothing to update.'],

        // Trip-specific
        'CREATE_FAILED'      => ['code' => 1020, 'message' => 'Create operation failed.'],
        'UPDATE_FAILED'      => ['code' => 1021, 'message' => 'Update operation failed.'],
        'DELETE_FAILED'      => ['code' => 1022, 'message' => 'Delete operation failed.'],
        'TITLE_TOO_LONG'     => ['code' => 1023, 'message' => 'Title is too long.'],
        'INVALID_TITLE'      => ['code' => 1024, 'message' => 'Invalid title.'],
        'INVALID_LATITUDE'   => ['code' => 1025, 'message' => 'Invalid latitude.'],
        'INVALID_LONGITUDE'  => ['code' => 1026, 'message' => 'Invalid longitude.'],

        // Upload / Media
        'CONTENT_TYPE_MUST_BE_MULTIPART' => ['code' => 1030, 'message' => 'Content-Type must be multipart/form-data.'],
        'NO_FILES_PROVIDED'   => ['code' => 1031, 'message' => 'No files provided.'],
        'UPLOAD_DIR_NOT_WRITABLE' => ['code' => 1032, 'message' => 'Upload directory is not writable.'],
        'UPLOAD_FAILED'       => ['code' => 1033, 'message' => 'Upload failed.'],
        'FILE_SIZE_INVALID'   => ['code' => 1034, 'message' => 'File size is invalid or too large.'],
        'UNSUPPORTED_MEDIA_TYPE' => ['code' => 1035, 'message' => 'Unsupported media type.'],
        'INVALID_UPLOAD_STREAM'  => ['code' => 1036, 'message' => 'Invalid upload stream.'],
        'MOVE_UPLOAD_FAILED'  => ['code' => 1037, 'message' => 'Failed to move uploaded file.'],
        'MEDIA_DB_INSERT_FAILED' => ['code' => 1038, 'message' => 'Failed to insert media record.'],
        'MEDIA_NOT_FOUND'     => ['code' => 1039, 'message' => 'Media not found.'],
        'MEDIA_OWNERSHIP_VIOLATION' => ['code' => 1040, 'message' => 'You do not have permission to modify this media.']
    ];

    public static function throw($key, $httpStatusCode = 400) {
        if (!isset(self::$errors[$key])) {
            Response::error('Unknown error.', 1999, $httpStatusCode);
            return;
        }
        $error = self::$errors[$key];
        Response::error($error['message'], $error['code'], $httpStatusCode);
    }
}
