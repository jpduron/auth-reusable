<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Utils\Request;

class Usuario {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * ============================================================ 
     * BEGIN LOGIN METHODS
     * ============================================================
     */
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
    public function updateLastLogin(int $id): bool {
        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET ultima_sesion_el = NOW() 
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * ============================================================ 
     * END LOGIN METHODS
     * ============================================================
     */

    /**
     * ============================================================ 
     * BEGIN CRUD METHODS
     * ============================================================
     */
    // =========================================
    // MÉTODOS CRUD (Para administración)
    // =========================================

    /**
     * Lista usuarios con filtros, paginación y ordenamiento
     * Devuelve solo los campos seguros para mostrar al admin
     */
    public function listar(array $filtros = []): array 
    {
        // Campos seguros que se pueden mostrar
        $campos = 'id, nombre_completo, correo, rol_id, estado_id, es_su, 
                   creado_el, ultima_sesion_el';
        
        $condiciones = ['eliminado_el IS NULL'];
        $parametros = [];
        
        // Filtro por estado
        if (!empty($filtros['estado'])) {
            $condiciones[] = 'estado_id = ?';
            $parametros[] = (int) $filtros['estado'];
        }
        
        // Filtro por rol
        if (!empty($filtros['rol'])) {
            $condiciones[] = 'rol_id = ?';
            $parametros[] = (int) $filtros['rol'];
        }
        
        // Búsqueda por nombre o correo
        if (!empty($filtros['buscar'])) {
            $condiciones[] = '(nombre_completo LIKE ? OR correo LIKE ?)';
            $parametros[] = '%' . $filtros['buscar'] . '%';
            $parametros[] = '%' . $filtros['buscar'] . '%';
        }
        
        // Ordenamiento (validado para evitar inyección SQL)
        $ordenPermitidos = ['nombre_completo', 'correo', 'creado_el', 'ultima_sesion_el'];
        $ordenCampo = in_array($filtros['orden'] ?? '', $ordenPermitidos) 
            ? $filtros['orden'] 
            : 'nombre_completo';
        $ordenDireccion = strtoupper($filtros['direccion'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        
        // Paginación
        $pagina = max(1, (int) ($filtros['pagina'] ?? 1));
        $porPagina = min(100, max(1, (int) ($filtros['por_pagina'] ?? 20)));
        $offset = ($pagina - 1) * $porPagina;
        
        // Construir la consulta
        $sql = "SELECT {$campos} FROM usuarios 
                WHERE " . implode(' AND ', $condiciones) . "
                ORDER BY {$ordenCampo} {$ordenDireccion}
                LIMIT {$porPagina} OFFSET {$offset}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);
        
        return $stmt->fetchAll();
    }

    /**
     * Cuenta el total de usuarios que coinciden con los filtros
     * (Usado para calcular el total de páginas)
     */
    public function contarTotal(array $filtros = []): int 
    {
        $condiciones = ['eliminado_el IS NULL'];
        $parametros = [];
        
        if (!empty($filtros['estado'])) {
            $condiciones[] = 'estado_id = ?';
            $parametros[] = (int) $filtros['estado'];
        }
        if (!empty($filtros['rol'])) {
            $condiciones[] = 'rol_id = ?';
            $parametros[] = (int) $filtros['rol'];
        }
        if (!empty($filtros['buscar'])) {
            $condiciones[] = '(nombre_completo LIKE ? OR correo LIKE ?)';
            $parametros[] = '%' . $filtros['buscar'] . '%';
            $parametros[] = '%' . $filtros['buscar'] . '%';
        }
        
        $sql = "SELECT COUNT(*) FROM usuarios WHERE " . implode(' AND ', $condiciones);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca un usuario por su ID (excluye eliminados)
     */
    public function findById(int $id): ?array 
    {
        $stmt = $this->db->prepare("
            SELECT id, nombre_completo, correo, rol_id, estado_id, es_su, 
                   creado_por, creado_el, ultima_sesion_el
            FROM usuarios 
            WHERE id = ? AND eliminado_el IS NULL
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    /**
     * Verifica si un correo ya existe en la base de datos
     * $excluirId permite excluir un ID específico (útil al actualizar)
     */
    public function correoExiste(string $correo, ?int $excluirId = null): bool 
    {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE correo = ? AND eliminado_el IS NULL";
        $parametros = [$correo];
        
        if ($excluirId !== null) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Crea un nuevo usuario (desde el panel de administración)
     * A diferencia de createPending, este usuario queda ACTIVO directamente
     */
    public function crear(array $data): ?int 
    {
        $stmt = $this->db->prepare("
            INSERT INTO usuarios (
                nombre_completo, 
                correo, 
                clave_hash, 
                estado_id, 
                rol_id, 
                es_su,
                creado_por,
                ip_registro,
                creado_el
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, NOW()
            )
        ");
        
        $result = $stmt->execute([
            strtoupper($data['nombre_completo']),
            $data['correo'],
            $data['clave_hash'],
            $data['estado_id'] ?? 1,  // ACTIVO por defecto
            $data['rol_id'] ?? 2,     // USER por defecto
            $data['es_su'] ?? 0,
            $data['creado_por'],
            Request::ip()
        ]);

        return $result ? (int) $this->db->lastInsertId() : null;
    }

    /**
     * Actualiza los campos de un usuario (actualización parcial)
     * Solo actualiza los campos que vienen en el array $data
     */
    public function actualizar(int $id, array $data): bool 
    {
        if (empty($data)) {
            return false;
        }

        // Campos permitidos para actualizar (seguridad)
        $camposPermitidos = [
            'nombre_completo', 'correo', 'rol_id', 'estado_id', 'es_su'
        ];

        $setParts = [];
        $parametros = [];

        foreach ($data as $campo => $valor) {
            if (in_array($campo, $camposPermitidos)) {
                $setParts[] = "{$campo} = ?";
                
                // Convertir nombre a mayúsculas si es el campo correspondiente
                if ($campo === 'nombre_completo') {
                    $parametros[] = strtoupper($valor);
                } else {
                    $parametros[] = $valor;
                }
            }
        }

        if (empty($setParts)) {
            return false;
        }

        // Agregar el ID al final de los parámetros
        $parametros[] = $id;

        $sql = "UPDATE usuarios SET " . implode(', ', $setParts) . " WHERE id = ? AND eliminado_el IS NULL";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($parametros);
    }

    /**
     * Soft delete: marca al usuario como eliminado sin borrarlo de la BD
     */
    public function softDelete(int $id, int $eliminadoPor): bool 
    {
        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET eliminado_el = NOW(), 
                eliminado_por = ?,
                api_token = NULL,
                api_token_expira = NULL
            WHERE id = ? AND eliminado_el IS NULL
        ");
        
        return $stmt->execute([$eliminadoPor, $id]);
    }
}