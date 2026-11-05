<?php
declare(strict_types=1);

namespace App\Utils;

class Env {
    private static array $variables = [];
    private static bool $loaded     = false;

    /**
     * Carga el archivo .env desde la raíz del proyecto
     */
    public static function load(string $path = null): void {
        if (self::$loaded) {
            return;
        }

        // Ruta por defecto: 3 niveles arriba desde Utils/ (src/Utils → src → backend → raíz)
        if ($path === null) {
            $path = dirname(__DIR__, 3) . '/.env';
        }

        if (!file_exists($path)) {
            throw new \RuntimeException("Archivo .env no encontrado en: $path");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Ignorar líneas sin '='
            if (strpos($line, '=') === false) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Remover comillas si existen
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            self::$variables[$name] = $value;
            
            // También establecer como variable de entorno del sistema
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
        }

        self::$loaded = true;
    }

    /**
     * Obtiene una variable de entorno
     */
    public static function get(string $key, $default = null) {
        self::load();
        
        return self::$variables[$key] 
            ?? $_ENV[$key] 
            ?? getenv($key) 
            ?: $default;
    }

    /**
     * Obtiene una variable como entero
     */
    public static function getInt(string $key, int $default = 0): int {
        $value = self::get($key);
        return $value !== null ? (int) $value : $default;
    }

    /**
     * Obtiene una variable como boolean
     */
    public static function getBool(string $key, bool $default = false): bool {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower((string) $value), ['true', '1', 'yes', 'on'], true);
    }
}