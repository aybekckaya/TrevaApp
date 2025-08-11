<?php

class SQL {
    /* =========================
     * Schema (CREATE TABLE)
     * ========================= */
    public static function createUsersTable() {
        return <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100),
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255),
            google_id VARCHAR(255) UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        SQL;
    }

    public static function createTripsTable() {
        return <<<SQL
        CREATE TABLE IF NOT EXISTS trips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            latitude DECIMAL(9,6) NOT NULL,
            longitude DECIMAL(9,6) NOT NULL,
            user_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_trips_user_id (user_id),
            CONSTRAINT fk_trips_user
              FOREIGN KEY (user_id) REFERENCES users(id)
              ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        SQL;
    }

    public static function createMediaTable() {
        return <<<SQL
        CREATE TABLE IF NOT EXISTS media (
            id INT AUTO_INCREMENT PRIMARY KEY,
            trip_id INT NOT NULL,
            media_type VARCHAR(100) NOT NULL,
            full_name VARCHAR(1024) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_media_trip_id (trip_id),
            CONSTRAINT fk_media_trip
              FOREIGN KEY (trip_id) REFERENCES trips(id)
              ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        SQL;
    }

    /* =========================
     * Drop Tables (delete all)
     * ========================= */
    public static function dropAllTables() {
        // FK sırası önemli: media -> trips -> users
        return [
            "DROP TABLE IF EXISTS media",
            "DROP TABLE IF EXISTS trips",
            "DROP TABLE IF EXISTS users"
        ];
    }

    /* =========================
     * Users
     * ========================= */
    public static function userExistsByEmail() {
        return "SELECT id FROM users WHERE email = ?";
    }

    public static function insertUser() {
        return "INSERT INTO users (name, email, password, google_id) VALUES (?, ?, ?, ?)";
    }

    public static function getUserByEmail() {
        return "SELECT * FROM users WHERE email = ?";
    }

    public static function getUserByGoogleId() {
        return "SELECT * FROM users WHERE google_id = ?";
    }

    /* =========================
     * Trips (CRUD + helpers)
     * ========================= */
    public static function insertTrip() {
        return "INSERT INTO trips (title, description, latitude, longitude, user_id) VALUES (?, ?, ?, ?, ?)";
    }

    public static function getTripByIdForUser() {
        return "SELECT * FROM trips WHERE id = ? AND user_id = ?";
    }

    public static function countTripsByUser() {
        return "SELECT COUNT(*) AS cnt FROM trips WHERE user_id = ?";
    }

    public static function listTripsByUserPaginated() {
        return "SELECT * FROM trips WHERE user_id = ? ORDER BY created_at DESC LIMIT %d OFFSET %d";
    }

    public static function getLastTripByUser() {
        return "SELECT * FROM trips WHERE user_id = ? ORDER BY id DESC LIMIT 1";
    }

    public static function updateTripDynamic() {
        return "UPDATE trips SET %s, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
    }

    public static function deleteTripForUser() {
        return "DELETE FROM trips WHERE id = ? AND user_id = ?";
    }

    /* =========================
     * Media (CRUD-lite + helpers)
     * ========================= */
    public static function insertMedia() {
        return "INSERT INTO media (trip_id, media_type, full_name) VALUES (?, ?, ?)";
    }

    public static function listMediaByTrip() {
        return "SELECT id, media_type, full_name FROM media WHERE trip_id = ? ORDER BY id ASC";
    }

    public static function countMediaByTrip() {
        return "SELECT COUNT(*) AS c FROM media WHERE trip_id = ?";
    }

    public static function deleteMediaById() {
        return "DELETE FROM media WHERE id = ?";
    }

    public static function deleteMediaByTrip() {
        return "DELETE FROM media WHERE trip_id = ?";
    }
}
