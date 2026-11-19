<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Modelo genérico para manejar tablas de catálogo (roles, estados, etc.)
 * Sigue el principio DRY: un modelo para todas las tablas simples de catálogo.
 */
class Catalogo {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Obtiene todos los registros de una tabla de catálogo
     * @param string $tabla Nombre de la tabla (ej: 'roles', 'estados')
     * @param string $ordenCampo Campo por el cual ordenar (por defecto 'orden')
     */
    public function obtenerTodos(string $tabla, string $ordenCampo = 'orden'): array {
        // Lista blanca de tablas permitidas (seguridad anti-inyección)
        $tablasPermitidas = ['roles', 'estados'];
        
        if (!in_array($tabla, $tablasPermitidas)) {
            return [];
        }

        // Validar campo de orden (solo 'orden' o 'nombre' por seguridad)
        $ordenPermitidos = ['orden', 'nombre', 'id'];
        if (!in_array($ordenCampo, $ordenPermitidos)) {
            $ordenCampo = 'orden';
        }

        $stmt = $this->db->prepare("SELECT * FROM {$tabla} ORDER BY {$ordenCampo} ASC");
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}