<?php
$host = '68.183.222.202'; // örnek: '127.0.0.1' ya da '123.45.67.89'
$db   = 'TrevaDB';
$user = 'aybekcankaya';
$pass = '1020304050Aa!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Hataları yakala
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch tarzı
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Emülasyon kapalı
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "✅ MySQL connection successful!";
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

/*
CREATE DATABASE TrevaDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'aybekcankaya'@'%' IDENTIFIED BY '1020304050Aa!';

GRANT ALL PRIVILEGES ON TrevaDB.* TO 'aybekcankaya'@'%';

FLUSH PRIVILEGES;
*/