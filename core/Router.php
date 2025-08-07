<?php

require_once __DIR__ . '/Response.php';

class Router {
    public static function dispatch($uri) {
        $path = parse_url($uri, PHP_URL_PATH);
        if (strpos($path, '/api/v1/') !== 0) {
            $endpoint = __DIR__ . '/../api/v1/' . basename($path) . '.php';
            if (file_exists($endpoint)) {
                require $endpoint;
                exit;
            }
        }
        ErrorManager::throw('ENDPOINT_NOT_FOUND', 404);
    }
}
