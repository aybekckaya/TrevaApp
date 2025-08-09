<?php

class DB {
    private static $pdo;

    public static function connect() {
        if (!self::$pdo) {
            try {
                self::$pdo = new PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
            } catch (PDOException $e) {
                // Yerel ortamda bağlantı kurulamazsa fallback çalışsın diye boş döndürülür
                return null;
            }
        }
        return self::$pdo;
    }

    public static function execute($sql, $params = []) {
       
        // LOCAL ORTAM ALGILAMA
        $isLocal = in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']) ||
                   in_array($_SERVER['REMOTE_ADDR'], ['::1', '127.0.0.1']);

         // Eğer local ise dbquery.php'yi kullan
        if ($isLocal) {
            return self::executeRemotely($sql, $params);
        }

        // Normal PDO ile çalış
        try {
            $pdo = self::connect();
            if (!$pdo) {
                return self::executeRemotely($sql, $params);
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if (stripos($sql, 'SELECT') === 0) {
                return $stmt->fetchAll();
            }

            return true;
        } catch (PDOException $e) {
            ErrorManager::throw('DB_ERROR', 500);
        }
    }

    private static function executeRemotely($sql, $params = []) {
        $remoteUrl = 'http://68.183.222.202/my-api/api/v1/dbquery.php';

        $payload = json_encode([
            'query' => $sql,
            'params' => $params
        ]);

        $ch = curl_init($remoteUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        var_dump($sql,$params, $response, $httpCode); // Debugging output

        curl_close($ch);
        //Response::success($response);

        return $response;
        /*
        var_dump($sql,$params, $response, $httpCode); // Debugging output
        

        if (curl_errno($ch)) {
            curl_close($ch);
            ErrorManager::throw('DB_ERROR', 500);
        }

        curl_close($ch);

        if ($httpCode !== 200) {
             
            ErrorManager::throw('DB_ERROR', $httpCode);
        }

        $data = json_decode($response, true);
        return $data['result'] ?? null;
        */
    }
}



