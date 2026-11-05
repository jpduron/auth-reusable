<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Utils\Request;

class User {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Busca un usuario por su correo electrónico.
     * Incluye 'eliminado_el IS NULL' para respetar el Soft Delete.
     */
    public function findByEmail(string $correo): ?array {
        $stmt = $this->db->prepare("
            SELECT id, nombre_completo, correo, clave_hash, 
            estado_id, rol_id, es_su, intentos_fallidos, 
            bloqueado_hasta, ultima_sesion_el
            FROM usuarios 
            WHERE correo = ? AND eliminado_el IS NULL
        ");
        $stmt->execute([$correo]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    /**
     * Guarda el token de la API cuando el usuario hace login.
     */
    public function updateApiToken(int $id, string $token, string $expiresAt): bool {
        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET api_token = ?, api_token_expira = ? 
            WHERE id = ?
        ");
        return $stmt->execute([$token, $expiresAt, $id]);
    }

    /**
     * Borra el token cuando el usuario hace logout.
     */
    public function clearApiToken(int $id): bool {
        $stmt = $this->db->prepare("UPDATE usuarios SET api_token = NULL WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function registrarIntentoFallido(int $id): bool {
        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET intentos_fallidos = intentos_fallidos + 1,
                bloqueado_hasta = CASE 
                    WHEN intentos_fallidos + 1 >= 5 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    ELSE bloqueado_hasta
                END
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    public function resetIntentos(int $id): bool {
        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET intentos_fallidos = 0, bloqueado_hasta = NULL 
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    public function registrarIntentoLogin(?int $userId, string $correo, string $ip, string $userAgent, bool $exitoso): void {
        $stmt = $this->db->prepare("
            INSERT INTO login_intentos (usuario_id, correo, ip, user_agent, exitoso) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, 
            $correo, 
            $ip, 
            substr($userAgent, 0, 255), // Limitar longitud
            $exitoso ? 1 : 0
        ]);
    }

    /**
     * Crea un nuevo usuario pendiente de verificación
     */
    public function createPending(
        string $nombreCompleto, 
        string $correo, 
        string $claveHash, 
        string $tokenVerificacion
    ): ?int {
        $stmt = $this->db->prepare("
            INSERT INTO usuarios (
                nombre_completo, 
                correo, 
                clave_hash, 
                estado_id, 
                rol_id, 
                token_verificacion,
                ip_registro,
                creado_el
            ) VALUES (
                ?, ?, ?, 4, 2, ?, ?, NOW()
            )
        ");
        
        $result = $stmt->execute([
            strtoupper($nombreCompleto),
            $correo,
            $claveHash,
            $tokenVerificacion,
            Request::ip()
        ]);

        return $result ? (int) $this->db->lastInsertId() : null;
    }

    /**
     * Verifica el token y activa la cuenta
     */
    public function verifyEmail(string $token): bool {
        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET estado_id = 1, 
                token_verificacion = NULL,
                verificado_el = NOW()
            WHERE token_verificacion = ? AND estado_id = 4
        ");
        
        return $stmt->execute([$token]);
    }

    /**
     * Guarda el token de recuperación de contraseña
     */
    public function saveResetToken(string $correo, string $token, string $expiresAt): bool {
        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET token_recuperacion = ?, 
                token_recuperacion_expira = ?
            WHERE correo = ? AND estado_id = 1 AND eliminado_el IS NULL
        ");
        
        return $stmt->execute([$token, $expiresAt, $correo]);
    }

    /**
     * Busca usuario por token de recuperación válido
     */
    public function findByResetToken(string $token): ?array {
        $stmt = $this->db->prepare("
            SELECT id, nombre_completo, correo 
            FROM usuarios 
            WHERE token_recuperacion = ? 
              AND token_recuperacion_expira > NOW()
              AND estado_id = 1
              AND eliminado_el IS NULL
        ");
        
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    /**
     * Resetea la contraseña y limpia el token
     */
    public function resetPassword(int $userId, string $claveHash): bool {
        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET clave_hash = ?, 
                token_recuperacion = NULL,
                token_recuperacion_expira = NULL
            WHERE id = ?
        ");
        
        return $stmt->execute([$claveHash, $userId]);
    }

    /**
     * Actualiza la fecha del último inicio de sesión exitoso
     */
    public function updateLastLogin(int $id): bool 
    {
        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET ultima_sesion_el = NOW() 
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
}