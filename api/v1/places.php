<?php

require_once __DIR__ . '/../../core/Response.php';

Response::success([
    'places' => ['istanbul', 'bursa']
]);

/*
$user = AuthMiddleware::check(); // Token kontrolü yapılır

Response::success([
    'places' => ['istanbul', 'bursa'],
    'user_id' => $user['uid']
]);
*/