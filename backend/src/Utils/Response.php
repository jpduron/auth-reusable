<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * =====================================================
 * RESPONSE
 * =====================================================
 * 
 * Clase utilitaria para estandarizar las respuestas HTTP
 * de la API en formato JSON.
 * 
 * Su objetivo es centralizar:
 * 
 * - http_response_code()
 * - headers JSON
 * - json_encode()
 * - estructura uniforme de respuesta
 * 
 * y evitar duplicación de código en Controllers.
 * 
 * =====================================================
 * ESTRUCTURA DE RESPUESTA
 * =====================================================
 * 
 * RESPUESTA EXITOSA:
 * 
 * {
 *   "mensaje": "OK",
 *   "data": {...}
 * }
 * 
 * RESPUESTA DE ERROR:
 * 
 * {
 *   "mensaje": "Error descriptivo"
 * }
 * 
 * =====================================================
 * MÉTODOS DISPONIBLES
 * =====================================================
 * 
 * success()
 * 
 * Envía respuestas exitosas.
 * 
 * Parámetros:
 * - data     → información a retornar
 * - mensaje  → mensaje descriptivo
 * - code     → código HTTP
 * 
 * Ejemplo:
 * 
 * Response::success(
 *     $usuarios,
 *     "Usuarios obtenidos",
 *     200
 * );
 * 
 * -----------------------------------------------------
 * 
 * error()
 * 
 * Envía respuestas de error.
 * 
 * Parámetros:
 * - mensaje → descripción del error
 * - code    → código HTTP
 * 
 * Ejemplo:
 * 
 * Response::error(
 *     "Usuario no encontrado",
 *     404
 * );
 * 
 * =====================================================
 * RESPONSABILIDAD
 * =====================================================
 * 
 * Esta clase:
 * 
 * ✔ define respuestas JSON
 * ✔ define códigos HTTP
 * ✔ centraliza salida HTTP
 * ✔ mantiene consistencia de respuesta
 * 
 * =====================================================
 * ESTA CLASE NO
 * =====================================================
 * 
 * ✘ valida datos
 * ✘ procesa lógica de negocio
 * ✘ interactúa con base de datos
 * ✘ controla autenticación
 * 
 * =====================================================
 * USO RECOMENDADO
 * =====================================================
 * 
 * Utilizar principalmente desde Controllers.
 * 
 * Controllers:
 *   → interpretan request
 *   → llaman Services
 *   → retornan Response
 * 
 * =====================================================
 */


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