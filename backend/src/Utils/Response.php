<?php
declare(strict_types=1);

namespace App\Utils;

class Response {
    public static function success(
        ?array $data     = null,
        string $mensaje = 'OK',
        int $code       = 200
    ): void {
        self::send(
            [
                'mensaje' => $mensaje,
                'data'    => $data
            ],
            $code
        );
    }

    public static function error(
        string $mensaje,
        int $code = 500
    ): void {
        self::send(
            [
                'mensaje' => $mensaje
            ],
            $code
        );
    }

    private static function send(
        array $payload,
        int $code
    ): void {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);

        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
        );
        exit; // importante
    }
}