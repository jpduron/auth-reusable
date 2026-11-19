<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Catalogo;

class CatalogoService {
    private Catalogo $catalogoModel;

    public function __construct(Catalogo $catalogoModel) {
        $this->catalogoModel = $catalogoModel;
    }

    /**
     * Obtiene todos los catálogos del sistema en una sola respuesta
     */
    public function obtenerTodosLosCatalogos(): array {
        return [
            'success' => true,
            'data' => [
                'roles'   => $this->catalogoModel->obtenerTodos('roles'),
                'estados' => $this->catalogoModel->obtenerTodos('estados')
                // En el futuro puedes agregar más:
                // 'municipios' => $this->catalogoModel->obtenerTodos('municipios'),
                // 'tipos_documento' => $this->catalogoModel->obtenerTodos('tipos_documento'),
            ]
        ];
    }

    /**
     * Obtiene un catálogo específico
     */
    public function obtenerCatalogo(string $nombre): array {
        $datos = $this->catalogoModel->obtenerTodos($nombre);
        
        if (empty($datos)) {
            return ['success' => false, 'message' => 'Catálogo no encontrado'];
        }

        return [
            'success' => true,
            'data' => $datos
        ];
    }
}