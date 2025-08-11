<?php

class DB {
    private static $pdo;

    /** Logging options */
    private static bool $logQueries = true;          // Sorguları logla
    private static bool $logParams  = true;          // Parametreleri logla
    private static int  $slowQueryThresholdMs = 300; // Yavaş kabul edilecek süre

    /** ---------------- Core Connectors ---------------- */

    public static function connect() {
        if (!self::$pdo) {
            $dsn  = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $user = DB_USER;
            try {
                Logger::info("DB.connect start dsn=" . self::safeDsn($dsn) . " user={$user}");
                self::$pdo = new PDO(
                    $dsn,
                    $user,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
                Logger::info("DB.connect success dsn=" . self::safeDsn($dsn));
            } catch (PDOException $e) {
                Logger::error("DB.connect fail dsn=" . self::safeDsn($dsn) . " code={$e->getCode()} msg=" . $e->getMessage());
                // Yerel ortamda bağlantı kurulamazsa fallback çalışsın diye null döndürülür
                return null;
            }
        }
        return self::$pdo;
    }

    public static function connect2() {
        if (!self::$pdo) {
            $dsn  = 'mysql:host=68.183.222.202;port=3306;dbname=TrevaDB;charset=utf8mb4';
            $user = 'aybekcankaya1';
            try {
                Logger::info("DB.connect2 start dsn=" . self::safeDsn($dsn) . " user={$user}");
                self::$pdo = new PDO(
                    $dsn,
                    $user,
                    'password1234',
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
                Logger::info("DB.connect2 success dsn=" . self::safeDsn($dsn));
            } catch (PDOException $e) {
                Logger::error("DB.connect2 fail dsn=" . self::safeDsn($dsn) . " code={$e->getCode()} msg=" . $e->getMessage());
                // Üretimde echo yapmayalım, log kâfi:
                return null;
            }
        }
        return self::$pdo;
    }

    /** ---------------- Query Executor ---------------- */

    public static function execute($sql, $params = []) {
        $pdo = self::connect2() ?: self::connect();
        if (!$pdo) {
            Logger::error("DB.execute no-connection sql=" . self::shortSql($sql));
            ErrorManager::throw('DB_ERROR', 500);
        }

        $qid = self::newQid();
        $isSelect = stripos(ltrim($sql), 'SELECT') === 0;

        $paramsForLog = self::$logParams ? self::stringifyParams($params) : '[hidden]';
        if (self::$logQueries) {
            Logger::info("SQL.prepare qid={$qid} sql=" . self::shortSql($sql) . " params={$paramsForLog}");
        }

        try {
            $t0 = microtime(true);

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $elapsedMs = (int)round((microtime(true) - $t0) * 1000);

            if ($isSelect) {
                $rows = $stmt->fetchAll();
                $rowCount = is_array($rows) ? count($rows) : 0;
                if (self::$logQueries) {
                    Logger::info("SQL.select qid={$qid} ok elapsed_ms={$elapsedMs} rows={$rowCount}");
                }
                if ($elapsedMs >= self::$slowQueryThresholdMs) {
                    Logger::error("SQL.slow qid={$qid} elapsed_ms={$elapsedMs} threshold_ms=" . self::$slowQueryThresholdMs);
                }
                return $rows;
            } else {
                $affected = $stmt->rowCount();
                $lastId = null;
                // INSERT ise lastInsertId loglayalım (PDO sürücüsüne göre değişebilir)
                if (stripos(ltrim($sql), 'INSERT') === 0) {
                    try { $lastId = $pdo->lastInsertId(); } catch (Throwable $e) {}
                }
                if (self::$logQueries) {
                    Logger::info("SQL.exec qid={$qid} ok elapsed_ms={$elapsedMs} affected={$affected}" . ($lastId ? " last_id={$lastId}" : ""));
                }
                if ($elapsedMs >= self::$slowQueryThresholdMs) {
                    Logger::error("SQL.slow qid={$qid} elapsed_ms={$elapsedMs} threshold_ms=" . self::$slowQueryThresholdMs);
                }
                return true;
            }
        } catch (PDOException $e) {
            Logger::error("SQL.error qid={$qid} code={$e->getCode()} msg=" . $e->getMessage() . " sql=" . self::shortSql($sql) . " params={$paramsForLog}");
            ErrorManager::throw('DB_ERROR', 500);
        }
    }

    /** ---------------- Helpers (logging) ---------------- */

    private static function newQid(): string {
        try {
            return bin2hex(random_bytes(4)); // 8 hex char
        } catch (Throwable $e) {
            return uniqid();
        }
    }

    private static function safeDsn(string $dsn): string {
        // Şifre zaten DSN’de yok; host/db bilgisi kalsın
        return $dsn;
    }

    private static function shortSql(string $sql, int $max = 2000): string {
        $s = trim(preg_replace('/\s+/', ' ', $sql));
        return (strlen($s) > $max) ? (substr($s, 0, $max) . '…') : $s;
    }

    private static function stringifyParams($params): string {
        // Parametreleri güvenli-özet metne çevir
        $safe = [];
        if (is_array($params)) {
            foreach ($params as $k => $v) {
                $safe[$k] = self::maskValue($v);
            }
        } else {
            $safe = self::maskValue($params);
        }
        return json_encode($safe, JSON_UNESCAPED_UNICODE);
    }

    private static function maskValue($v) {
        if (is_null($v) || is_bool($v)) return $v;
        if (is_int($v) || is_float($v)) return $v;

        $s = (string)$v;

        // JWT / uzun token / base64 benzeri stringleri kısalt
        if (preg_match('/^[A-Za-z0-9\-\_.]+$/', $s) && strlen($s) > 40) {
            return substr($s, 0, 6) . '…' . substr($s, -6);
        }

        // E-posta ise kısmi maskele
        if (filter_var($s, FILTER_VALIDATE_EMAIL)) {
            [$u, $d] = explode('@', $s, 2);
            $uMasked = strlen($u) > 2 ? substr($u, 0, 2) . '***' : $u . '*';
            return $uMasked . '@' . $d;
        }

        // Çok uzun stringleri kısalt
        if (strlen($s) > 200) {
            return substr($s, 0, 100) . '…' . substr($s, -50) . " (len=" . strlen($s) . ")";
        }

        return $s;
    }
}
