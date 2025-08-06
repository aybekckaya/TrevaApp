<?php

class SQL {
    public static function createUsersTable() {
        return <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100),
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255),
            google_id VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        SQL;
    }

    public static function userExistsByEmail() {
        return "SELECT id FROM users WHERE email = ?";
    }

    public static function insertUser() {
        return "INSERT INTO users (name, email, password, google_id) VALUES (?, ?, ?, ?)";
    }
}





