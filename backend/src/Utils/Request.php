<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * =========================================================
 * REQUEST
 * =========================================================
 * Clase utilitaria para centralizar el acceso a los datos
 * de entrada HTTP de la aplicación.
 *
 * Esta clase abstrae el acceso directo a:
 *
 * - $_GET
 * - $_POST
 * - php://input
 * - $_SERVER
 *
 * Su objetivo es:
 *
 * ✔ Evitar el uso repetido de superglobales
 * ✔ Centralizar la lectura de requests
 * ✔ Estandarizar el acceso a parámetros HTTP
 * ✔ Facilitar mantenimiento y escalabilidad
 * ✔ Preparar la arquitectura para un router REST
 *
 * =========================================================
 * RESPONSABILIDADES
 * =========================================================
 *
 * Esta clase se encarga únicamente de:
 *
 * ✔ Leer datos del request
 * ✔ Parsear JSON
 * ✔ Obtener query params
 * ✔ Obtener método HTTP
 * ✔ Obtener IP del cliente
 * ✔ Normalizar tipos básicos
 *
 * =========================================================
 * NO ES RESPONSABILIDAD DE ESTA CLASE
 * =========================================================
 *
 * ✘ Validar reglas de negocio
 * ✘ Validar permisos
 * ✘ Validar integridad de datos
 * ✘ Sanitizar SQL
 * ✘ Procesar lógica de aplicación
 *
 * Las validaciones de negocio deben vivir en Services.
 *
 * =========================================================
 * MÉTODOS DISPONIBLES
 * =========================================================
 *
 * input()
 * -----------------------------------------
 * Obtiene el body de la request.
 *
 * Soporta:
 * - JSON
 * - form-data
 * - x-www-form-urlencoded
 *
 * Retorna siempre un array.
 *
 * ---------------------------------------------------------
 *
 * get()
 * -----------------------------------------
 * Obtiene un valor desde $_GET.
 *
 * ---------------------------------------------------------
 *
 * getInt()
 * -----------------------------------------
 * Obtiene un entero desde $_GET.
 *
 * Retorna:
 * - int
 * - null
 *
 * ---------------------------------------------------------
 *
 * getString()
 * -----------------------------------------
 * Obtiene un string desde $_GET.
 *
 * Aplica trim().
 *
 * ---------------------------------------------------------
 *
 * method()
 * -----------------------------------------
 * Retorna el método HTTP actual:
 *
 * - GET
 * - POST
 * - PUT
 * - PATCH
 * - DELETE
 *
 * ---------------------------------------------------------
 *
 * ip()
 * -----------------------------------------
 * Retorna la IP del cliente.
 *
 * Intenta obtener:
 * - HTTP_X_FORWARDED_FOR
 * - REMOTE_ADDR
 *
 * =========================================================
 * EJEMPLOS DE USO
 * =========================================================
 *
 * Obtener body JSON:
 *
 * $input = Request::input();
 *
 * ---------------------------------------------------------
 *
 * Obtener parámetro GET:
 *
 * $id = Request::getInt('id');
 *
 * ---------------------------------------------------------
 *
 * Obtener string:
 *
 * $usuario = Request::getString('usuario');
 *
 * ---------------------------------------------------------
 *
 * Obtener método HTTP:
 *
 * $method = Request::method();
 *
 * ---------------------------------------------------------
 *
 * Obtener IP:
 *
 * $ip = Request::ip();
 *
 * =========================================================
 * NOTAS
 * =========================================================
 *
 * Esta clase forma parte de la capa de infraestructura HTTP
 * del backend y es utilizada principalmente por Controllers.
 *
 * Mantener esta clase pequeña y enfocada ayuda a conservar
 * una arquitectura limpia y desacoplada.
 * 
 * =========================================================
 */


class Request {

    /**
     * =========================================
     * INPUT
     * =========================================
     * Lee el body de la request.
     *
     * Soporta:
     * - JSON
     * - application/x-www-form-urlencoded
     * - form-data
     *
     * Retorna siempre un array.
     */
    public static function input(): array {
        $raw = file_get_contents("php://input");

        if (!empty($raw)) {
            $data = json_decode($raw, true);

            if (
                json_last_error() === JSON_ERROR_NONE &&
                is_array($data)
            ) {
                return $data;
            }
        }

        return $_POST ?? [];
    }

    /**
     * =========================================
     * GET
     * =========================================
     * Obtiene un valor de $_GET.
     */
    public static function get(string $key, $default = null) {
        return $_GET[$key] ?? $default;
    }

    /**
     * =========================================
     * GET INT
     * =========================================
     * Obtiene un entero desde $_GET.
     * (elimado mixed, \mixed, va sin type hint en segundo parámetro y retorno).
     */
    public static function getInt(string $key, ?int $default = null): ?int {
        if (!isset($_GET[$key])) {
            return $default;
        }

        return (int) $_GET[$key];
    }

    /**
     * =========================================
     * GET STRING
     * =========================================
     * Obtiene un string desde $_GET.
     */
    public static function getString(
        string $key,
        ?string $default = null
    ): ?string {
        if (!isset($_GET[$key])) {
            return $default;
        }

        return trim((string) $_GET[$key]);
    }

    /**
     * =========================================
     * METHOD
     * =========================================
     * Retorna el método HTTP actual.
     */
    public static function method(): string {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public static function requireMethod(string $method): void {
        if (self::method() !== $method) {
            Response::error(
                'Método no permitido',
                405
            );
            exit;
        }
    }
    
    /**
     * =========================================
     * IP
     * =========================================
     * Retorna la IP del cliente.
     * 20261103 - ajustado para obligar a tomar la primera
     * IP de la lista del cliente.
     */
    public static function ip(): string {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Get the first IP in the list
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ipList[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * =========================================
     * USER AGENT
     * =========================================
     * Retorna el user agent del cliente.
     */
    public static function userAgent(): string {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}