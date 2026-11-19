<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CatalogoService;
use App\Utils\Response;
use App\Utils\Request;

class CatalogoController {
    private CatalogoService $catalogoService;

    public function __construct(CatalogoService $catalogoService) {
        $this->catalogoService = $catalogoService;
    }

    /**
     * GET /catalogos - Devuelve todos los catálogos
     */
    public function listarTodos(): void {
        $resultado = $this->catalogoService->obtenerTodosLosCatalogos();
        Response::success($resultado['data'], 'Catálogos obtenidos', 200);
    }

    /**
     * GET /catalogos/:nombre - Devuelve un catálogo específico
     */
    public function obtener(string $nombre): void {
        $resultado = $this->catalogoService->obtenerCatalogo($nombre);
        
        if ($resultado['success']) {
            Response::success($resultado['data'], 'Catálogo obtenido', 200);
        } else {
            Response::error($resultado['message'], 404);
        }
    }
}