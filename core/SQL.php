<?php

class SQL {
    /* =========================
     * Schema (CREATE TABLE)
     * ========================= */

    public static function createUsersTable() {
        return <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NULL,
            surname VARCHAR(100) NULL,
            email VARCHAR(255) NULL,
            phone VARCHAR(50) NULL,
            password VARCHAR(255) NULL,
            google_id VARCHAR(255) NULL,
            username VARCHAR(50) NULL,
            bio VARCHAR(300) NULL,
            avatar_url VARCHAR(255) NULL,
            is_private TINYINT(1) NULL DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_users_email (email),
            UNIQUE KEY uq_users_google (google_id),
            UNIQUE KEY uq_users_username (username)
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        SQL;
    }

    public static function createUserFollowTable() {
        return <<<SQL
        CREATE TABLE IF NOT EXISTS user_follow (
            follower_id INT NOT NULL,
            following_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (follower_id, following_id),
            KEY idx_following (following_id),
            CONSTRAINT fk_follow_follower  FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_follow_following FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
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
        // FK sırası: media -> trips -> user_follow -> users
        return [
            "DROP TABLE IF EXISTS media",
            "DROP TABLE IF EXISTS trips",
            "DROP TABLE IF EXISTS user_follow",
            "DROP TABLE IF EXISTS users"
        ];
    }

    /* =========================
     * Users (CRUD + helpers)
     * ========================= */

    public static function userExistsByEmail() {
        return "SELECT id FROM users WHERE email = ? AND deleted_at IS NULL";
    }

    // register.php ile uyumlu: (name, email, password, google_id)
    public static function insertUser() {
        return "INSERT INTO users (name, email, password, google_id) VALUES (?, ?, ?, ?)";
    }

    public static function getUserByEmail() {
        return "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL";
    }

    public static function getUserByGoogleId() {
        return "SELECT * FROM users WHERE google_id = ? AND deleted_at IS NULL";
    }

    public static function getUserById() {
        return "SELECT id, name, surname, email, phone, username, bio, avatar_url, is_private, created_at, updated_at, deleted_at FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1";
    }

    public static function getUserByUsername() {
        return "SELECT id FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1";
    }

    public static function updateUserDynamic() {
        // sprintf ile %s kısmına "field1 = ?, field2 = ?" basılacak
        return "UPDATE users SET %s, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND deleted_at IS NULL";
    }

    public static function softDeleteUser() {
        return "UPDATE users SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND deleted_at IS NULL";
    }

    public static function searchUsers() {
        return <<<SQL
        SELECT id, name, surname, email, phone, username, bio, avatar_url, is_private, created_at, updated_at
        FROM users
        WHERE deleted_at IS NULL
          AND (username LIKE ? OR name LIKE ? OR surname LIKE ? OR email LIKE ?)
        ORDER BY username IS NULL, username, name, surname
        LIMIT %d OFFSET %d
        SQL;
    }

    /* =========================
     * Follow (relations)
     * ========================= */

    public static function followInsert() {
        return "INSERT IGNORE INTO user_follow (follower_id, following_id) VALUES (?, ?)";
    }

    public static function followDelete() {
        return "DELETE FROM user_follow WHERE follower_id = ? AND following_id = ?";
    }

    public static function isFollowing() {
        return "SELECT 1 FROM user_follow WHERE follower_id = ? AND following_id = ? LIMIT 1";
    }

    public static function followersCount() {
        return "SELECT COUNT(*) AS c FROM user_follow WHERE following_id = ?";
    }

    public static function followingCount() {
        return "SELECT COUNT(*) AS c FROM user_follow WHERE follower_id = ?";
    }

    public static function followersList() {
        return <<<SQL
        SELECT u.id, u.username, u.name, u.surname, u.avatar_url
        FROM user_follow f
        JOIN users u ON u.id = f.follower_id
        WHERE f.following_id = ? AND u.deleted_at IS NULL
        ORDER BY f.created_at DESC
        LIMIT %d OFFSET %d
        SQL;
    }

    public static function followingList() {
        return <<<SQL
        SELECT u.id, u.username, u.name, u.surname, u.avatar_url
        FROM user_follow f
        JOIN users u ON u.id = f.following_id
        WHERE f.follower_id = ? AND u.deleted_at IS NULL
        ORDER BY f.created_at DESC
        LIMIT %d OFFSET %d
        SQL;
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
        // sprintf ile %s kısmına "field1 = ?, field2 = ?" basılacak
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
