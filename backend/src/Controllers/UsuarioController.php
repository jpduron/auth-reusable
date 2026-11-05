<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\UsuarioService;
use App\Utils\Request;
use App\Utils\Response;
use App\Utils\Token;

class UsuarioController 
{
    private UsuarioService $usuarioService;

    public function __construct(UsuarioService $usuarioService) 
    {
        $this->usuarioService = $usuarioService;
    }

    public function listar(): void 
    {
        // Obtener filtros de la URL (Query String)
        $filtros = [
            'estado'     => Request::getString('estado'),
            'rol'        => Request::getString('rol'),
            'buscar'     => Request::getString('buscar'),
            'orden'      => Request::getString('orden', 'nombre_completo'),
            'direccion'  => Request::getString('direccion', 'ASC'),
            'pagina'     => Request::getString('pagina', '1'),
            'por_pagina' => Request::getString('por_pagina', '20')
        ];

        // Limpiar filtros vacíos
        $filtros = array_filter($filtros, fn($val) => $val !== '' && $val !== null);

        $resultado = $this->usuarioService->listarUsuarios($filtros);
        Response::success($resultado['data'], 'Lista de usuarios obtenida', 200);
    }

    public function obtener(int $id): void 
    {
        $resultado = $this->usuarioService->obtenerUsuario($id);
        
        if ($resultado['success']) {
            Response::success($resultado['data'], 'Usuario encontrado', 200);
        } else {
            Response::error($resultado['message'], 404);
        }
    }

    public function crear(): void 
    {
        $data = Request::input();
        $currentUser = Token::getUserFromToken(); // Necesario para saber quién crea y sus permisos

        $resultado = $this->usuarioService->crearUsuario($data, $currentUser);

        if ($resultado['success']) {
            Response::success($resultado['data'], $resultado['message'], 201);
        } else {
            Response::error($resultado['message'], 400);
        }
    }

    public function actualizar(int $id): void 
    {
        $data = Request::input();
        $currentUser = Token::getUserFromToken();

        $resultado = $this->usuarioService->actualizarUsuario($id, $data, $currentUser);

        if ($resultado['success']) {
            Response::success(null, $resultado['message'], 200);
        } else {
            Response::error($resultado['message'], 400);
        }
    }

    public function eliminar(int $id): void 
    {
        $currentUser = Token::getUserFromToken();

        $resultado = $this->usuarioService->eliminarUsuario($id, $currentUser);

        if ($resultado['success']) {
            Response::success(null, $resultado['message'], 200);
        } else {
            Response::error($resultado['message'], 400); // O 403 si es por permisos
        }
    }
}