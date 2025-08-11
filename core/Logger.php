<?php

class Logger {
    private static function getLogFile() {
        $logDir = __DIR__ . '/../logs/';
        $filename = 'app_' . date('Y-m-d_H') . '.log'; // Örn: app_2024-06-11_15.log
        return $logDir . $filename;
    }

    public static function info($message) {
        self::writeLog('INFO', $message);
    }

    public static function error($message) {
        self::writeLog('ERROR', $message);
    }

    private static function writeLog($level, $message) {
        // Milisaniye ile tarih
        $microtime = microtime(true);
        $microseconds = sprintf("%03d", ($microtime - floor($microtime)) * 1000);
        $date = date('Y-m-d H:i:s', (int)$microtime) . '.' . $microseconds;

        // IP adresi
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI/UNKNOWN';

        // Format
        $formatted = "[$date] [$ip] [$level] $message" . PHP_EOL;

        // Log dosyasını saatlik olarak al
        $logFile = self::getLogFile();

        // Log yaz
        file_put_contents($logFile, $formatted, FILE_APPEND | LOCK_EX);
    }
}
