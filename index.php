<?php
require_once __DIR__ . '/autoload.php';
header('Content-Type: application/json');

//var_dump($_SERVER['REQUEST_URI']); // Debugging line to check the request URI

Router::dispatch($_SERVER['REQUEST_URI']);
 