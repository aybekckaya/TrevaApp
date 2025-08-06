<?php
header('Content-Type: application/json');

// GET parametresiyle gelen "name" değişkenini al
$name = isset($_GET['name']) ? $_GET['name'] : 'Guest';

// JSON cevabı oluştur
$response = [
    'success' => true,
    'message' => "Hello, $name!",
    'timestamp' => date('Y-m-d H:i:s')
];

// JSON olarak gönder
echo json_encode($response);
