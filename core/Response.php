<?php

class Response
{
    /**
     * Başarılı JSON yanıt döndürür
     * @param mixed $data - Gönderilecek veri (array, string vs.)
     * @param int $httpCode - HTTP durumu (varsayılan 200)
     */
    public static function success($data = [], int $httpCode = 200)
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
        exit;
    }

    /**
     * Hatalı JSON yanıt döndürür
     * @param string $message - Hata mesajı
     * @param int $code - Uygulama içi özel hata kodu
     * @param int $httpCode - HTTP durumu (varsayılan 400)
     */
    public static function error(string $message, int $code = 1000, int $httpCode = 400)
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ]);
        exit;
    }
}
