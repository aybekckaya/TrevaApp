<?php

echo json_encode([
    'places' => ['istanbul', 'bursa']
]);

/*
$user = AuthMiddleware::check(); // Token kontrolü yapılır

echo json_encode([
    'places' => ['istanbul', 'bursa'],
    'user_id' => $user['uid']
]);

*/