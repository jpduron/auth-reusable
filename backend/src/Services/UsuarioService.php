<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario;
use App\Utils\Token;

class UsuarioService {
    private Usuario $usuarioModel;

    public function __construct(Usuario $usuarioModel) {
        $this->usuarioModel = $usuarioModel;
    }

    /**
     * 1. LISTAR USUARIOS
     */
    public function listarUsuarios(array $filtros): array {
        $usuarios = $this->usuarioModel->listar($filtros);
        $total = $this->usuarioModel->contarTotal($filtros);
        
        $porPagina = (int) ($filtros['por_pagina'] ?? 20);
        $pagina = (int) ($filtros['pagina'] ?? 1);
        $totalPaginas = (int) ceil($total / $porPagina);

        return [
            'success' => true,
            'data' => [
                'usuarios' => $usuarios,
                'paginacion' => [
                    'total' => $total,
                    'por_pagina' => $porPagina,
                    'pagina_actual' => $pagina,
                    'total_paginas' => $totalPaginas
                ]
            ]
        ];
    }

    /**
     * 2. OBTENER UN USUARIO POR ID
     */
    public function obtenerUsuario(int $id): array {
        $usuario = $this->usuarioModel->findById($id);

        if (!$usuario) {
            return ['success' => false, 'message' => 'Usuario no encontrado o ya fue eliminado.'];
        }

        return [
            'success' => true,
            'data' => $usuario
        ];
    }

    /**
     * 3. CREAR UN NUEVO USUARIO (Desde el panel de admin)
     */
    public function crearUsuario(array $data, array $currentUser): array {
        // Validaciones básicas
        if (empty($data['nombre_completo']) || empty($data['correo']) || empty($data['password'])) {
            return ['success' => false, 'message' => 'Nombre, correo y contraseña son obligatorios.'];
        }

        if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'El formato del correo electrónico no es válido.'];
        }

        if (strlen($data['password']) < 8) {
            return ['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.'];
        }

        // Verificar si el correo ya existe
        if ($this->usuarioModel->correoExiste($data['correo'])) {
            return ['success' => false, 'message' => 'Este correo electrónico ya está registrado.'];
        }

        // REGLA DE NEGOCIO: Solo un Super Usuario puede crear otro Super Usuario
        $esSu = 0;
        if (!empty($data['es_su'])) {
            if (!$currentUser['es_su']) {
                return ['success' => false, 'message' => 'Solo un Super Usuario puede asignar el rol de Super Usuario.'];
            }
            $esSu = 1;
        }

        // Preparar datos para el modelo
        $datosParaCrear = [
            'nombre_completo' => trim($data['nombre_completo']),
            'correo'          => trim(strtolower($data['correo'])),
            'clave_hash'      => password_hash($data['password'], PASSWORD_DEFAULT),
            'estado_id'       => (int) ($data['estado_id'] ?? 1), // ACTIVO por defecto
            'rol_id'          => (int) ($data['rol_id'] ?? 2),    // USER por defecto
            'es_su'           => $esSu,
            'creado_por'      => $currentUser['id']
        ];

        $nuevoId = $this->usuarioModel->crear($datosParaCrear);

        if (!$nuevoId) {
            return ['success' => false, 'message' => 'Error al crear el usuario en la base de datos.'];
        }

        return [
            'success' => true,
            'message' => 'Usuario creado exitosamente.',
            'data' => ['id' => $nuevoId]
        ];
    }

    /**
     * 4. ACTUALIZAR UN USUARIO
     */
    public function actualizarUsuario(int $id, array $data, array $currentUser): array 
    {
        // REGLA DE NEGOCIO: Un usuario no puede modificar su propio rol o estado de Super Usuario
        if ($id === $currentUser['id']) {
            if (isset($data['rol_id']) || isset($data['es_su'])) {
                return ['success' => false, 'message' => 'No puedes modificar tu propio rol o nivel de acceso.'];
            }
        }

        // REGLA DE NEGOCIO: Solo un Super Usuario puede cambiar el rol o es_su de OTROS usuarios
        if ((isset($data['rol_id']) || isset($data['es_su'])) && !$currentUser['es_su']) {
            return ['success' => false, 'message' => 'Solo un Super Usuario puede modificar roles o asignar Super Usuario.'];
        }

        // Si se intenta cambiar el correo, verificar que no exista
        if (isset($data['correo'])) {
            $data['correo'] = trim(strtolower($data['correo']));
            if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'El formato del correo electrónico no es válido.'];
            }
            if ($this->usuarioModel->correoExiste($data['correo'], $id)) {
                return ['success' => false, 'message' => 'Este correo electrónico ya está en uso por otro usuario.'];
            }
        }

        if (isset($data['nombre_completo'])) {
            $data['nombre_completo'] = trim($data['nombre_completo']);
        }

        // Ejecutar actualización (el modelo solo acepta campos permitidos)
        $actualizado = $this->usuarioModel->actualizar($id, $data);

        if (!$actualizado) {
            return ['success' => false, 'message' => 'No se realizaron cambios o el usuario no existe.'];
        }

        return [
            'success' => true,
            'message' => 'Usuario actualizado exitosamente.'
        ];
    }

    /**
     * 5. ELIMINAR UN USUARIO (Soft Delete)
     */
    public function eliminarUsuario(int $id, array $currentUser): array {
        // REGLA DE NEGOCIO: No puedes eliminarte a ti mismo
        if ($id === $currentUser['id']) {
            return ['success' => false, 'message' => 'No puedes eliminar tu propia cuenta.'];
        }

        // Necesitamos saber el rol del usuario a eliminar para aplicar reglas
        $usuarioAEliminar = $this->usuarioModel->findById($id);

        if (!$usuarioAEliminar) {
            return ['success' => false, 'message' => 'Usuario no encontrado o ya fue eliminado.'];
        }

        // REGLA DE NEGOCIO: No se puede eliminar a un Super Usuario
        if (!empty($usuarioAEliminar['es_su'])) {
            return ['success' => false, 'message' => 'No está permitido eliminar a un Super Usuario.'];
        }

        // REGLA DE NEGOCIO: Un Admin normal no puede eliminar a otro Admin
        if ($usuarioAEliminar['rol_id'] == 1 && !$currentUser['es_su']) {
            return ['success' => false, 'message' => 'Solo un Super Usuario puede eliminar a un Administrador.'];
        }

        // Ejecutar Soft Delete
        $eliminado = $this->usuarioModel->softDelete($id, $currentUser['id']);

        if (!$eliminado) {
            return ['success' => false, 'message' => 'Error al eliminar el usuario.'];
        }

        return [
            'success' => true,
            'message' => 'Usuario eliminado (desactivado) exitosamente.'
        ];
    }
}