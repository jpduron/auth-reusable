<?php
declare(strict_types=1);

namespace App\Utils;

use App\Core\Database;

class Token 
{
    /**
     * Generate a secure random token
     */
    public static function generate(): string 
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get the Bearer token from the Authorization header
     */
    public static function getBearerToken(): ?string 
    {
        // 1. Try getallheaders()
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        // Added 'i' modifier for case-insensitive matching (Bearer vs bearer)
        if ($authHeader && preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // 2. Fallback to $_SERVER
        if (isset($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            return $matches[1];
        }

        // 3. Another common Apache fallback
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && preg_match('/Bearer\s(\S+)/i', $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Verify the token against the database and return user data
     */
    public static function getUserFromToken(): ?array {
        $token = self::getBearerToken();
        if (!$token) {
            return null;
        }

        try {
            $pdo = Database::getInstance()->getConnection();
            
            // Exact column names (clave_hash, estado_id)
            // estado_id = 1 means ACTIVO
            $stmt = $pdo->prepare("
                SELECT id, nombre_completo, correo, clave_hash, 
                estado_id, rol_id, es_su, ultima_sesion_el
                FROM usuarios 
                WHERE api_token = :token 
                AND estado_id = 1 
                AND eliminado_el IS NULL
                AND api_token_expira > NOW()  -- Token no debe estar expirado
            ");
            
            $stmt->execute([':token' => $token]);
            $user = $stmt->fetch();

            return $user ?: null;

        } catch (\Exception $e) {
            error_log("Token verification error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Middleware: Require a valid token to proceed
     */
    public static function requireAuth(): void {
        $user = self::getUserFromToken();
        if ($user === null) {
            Response::error('No autorizado. Token inválido o faltante.', 401);
        }
    }

    /**
     * Check granular permissions
     */
    public static function can(string $codigoIndicador, string $accion): bool {
        $user = self::getUserFromToken();
        if ($user === null) {
            return false;
        }

        // 1. Super User bypass
        if (!empty($user['es_su'])) {
            return true;
        }

        // 2. Check database permissions
        try {
            $pdo = Database::getInstance()->getConnection();
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM permisos_indicadores pi
                INNER JOIN acciones a ON a.id = pi.accion_id
                WHERE pi.rol_id = :rol_id 
                  AND pi.codigo_indicador = :codigo 
                  AND a.nombre = :accion
            ");
            
            $stmt->execute([
                ':rol_id' => (int) $user['rol_id'],
                ':codigo' => $codigoIndicador,
                ':accion' => $accion
            ]);
            
            return (bool) $stmt->fetchColumn();
            
        } catch (\Exception $e) {
            error_log("Permission check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Middleware: Verifica permisos basados en la tabla 'permisos'
     * @param string $recurso Ej: 'usuarios'
     * @param string $accion Ej: 'crear', 'leer', 'actualizar', 'eliminar'
     */
    public static function requirePermission(string $recurso, string $accion): void {
        $user = self::getUserFromToken();
        
        if ($user === null) {
            Response::error('No autorizado. Token inválido o faltante.', 401);
            return;
        }

        // 1. Super Usuario tiene acceso total a todo, siempre.
        if (!empty($user['es_su'])) {
            return; 
        }

        // 2. Verificar en la base de datos si el rol tiene este permiso específico
        try {
            $pdo = Database::getInstance()->getConnection();
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM permisos 
                WHERE rol_id = :rol_id 
                  AND recurso = :recurso 
                  AND accion = :accion
            ");
            
            $stmt->execute([
                ':rol_id'  => (int) $user['rol_id'],
                ':recurso' => $recurso,
                ':accion'  => $accion
            ]);
            
            $tienePermiso = (bool) $stmt->fetchColumn();

            if (!$tienePermiso) {
                Response::error("Acceso denegado. No tienes permisos para '{$accion}' en '{$recurso}'.", 403);
            }

        } catch (\Exception $e) {
            error_log("Error verificando permisos: " . $e->getMessage());
            Response::error('Error interno del servidor al verificar permisos.', 500);
        }
    }
}