<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Database;
use App\Utils\Response;
use App\Utils\Request;
use App\Utils\Token;

class Router {
    public static function dispatch(string $route, Database $database): void {
        $route = trim($route, '/');
        $segments = $route === '' ? [''] : explode('/', $route);
        
        $resource = $segments[0];       // Ej: 'usuarios', 'login', 'me'
        $id = $segments[1] ?? null;     // Ej: '5' (si existe)
        $httpMethod = $_SERVER['REQUEST_METHOD'];

        // =========================================
        // 1. RUTAS PÚBLICAS (No requieren token)
        // =========================================
        $publicRoutes = [
            'login', 
            'register', 
            'verify-email',
            'forgot-password',
            'reset-password'
        ];
        
        if (!in_array($resource, $publicRoutes)) {
            Token::requireAuth();
        }

        // =========================================
        // 2. ENRUTAMIENTO PRINCIPAL
        // =========================================
        match ($resource) {
            // --- AUTENTICACIÓN ---
            'login'           => self::handleAuth($database, 'login'),
            'register'        => self::handleAuth($database, 'register'),
            'verify-email'    => self::handleAuth($database, 'verifyEmail'),
            'forgot-password' => self::handleAuth($database, 'forgotPassword'),
            'reset-password'  => self::handleAuth($database, 'resetPassword'),
            'logout'          => self::handleAuth($database, 'logout'),
            'me'              => self::handleMe($database),
            
            // --- USUARIOS (CRUD) ---
            'usuarios'        => self::handleUsuarios($database, $id, $httpMethod),
            
            default => Response::error("Ruta no encontrada", 404)
        };
    }

    // Helper for Login / Logout
    private static function handleAuth(Database $database, string $action): void {
        // Determinar el método HTTP requerido
        $method = match ($action) {
            'login', 'register', 'forgotPassword', 'resetPassword', 'logout' => 'POST',
            'verify-email' => 'GET',
            default => 'GET'
        };
        
        Request::requireMethod($method);

        $pdo = $database->getConnection();
        $model = new \App\Models\Usuario($pdo); 
        $service = new \App\Services\AuthService($model);
        $controller = new \App\Controllers\AuthController($service);

        if (method_exists($controller, $action)) {
            $controller->$action();
        } else {
            Response::error("Método no encontrado en el controlador", 500);
        }
    }

    // HELPER: Specifically for getting current user data via Token
    private static function handleMe(Database $database): void {
        Request::requireMethod('GET'); // Getting profile data is always GET

        // Don't even need the Model/Service for this simple check, 
        // because Token::getUserFromToken() already queries the database
        $user = Token::getUserFromToken();

        if ($user) {
            // Remove sensitive data before sending to frontend
            unset($user['clave_hash']);
            unset($user['intentos_fallidos']);
            unset($user['bloqueado_hasta']);
            Response::success($user, 'Usuario autenticado', 200);
        } else {
            Response::error('Token inválido o expirado', 401);
        }
    }

    /**
     * Maneja todas las operaciones CRUD de usuarios
     * Combina el recurso + método HTTP para decidir la acción
     */
    private static function handleUsuarios(Database $database, ?string $id, string $httpMethod): void {
        // 1. Verificar permisos: Solo ADMIN o Super Usuario
        Token::requirePermission('usuarios', 'acceso');

        // 2. Dependency Injection Chain
        $pdo = $database->getConnection();
        $model = new \App\Models\Usuario($pdo);
        $service = new \App\Services\UsuarioService($model);
        $controller = new \App\Controllers\UsuarioController($service);

        // 3. Routing interno basado en método HTTP y presencia de ID
        match (true) {
            // GET /usuarios → Listar todos
            ($httpMethod === 'GET' && $id === null) => function() use ($database, $controller) {
                Token::requirePermission('usuarios', 'leer');
                $controller->listar();
            },
            
            // GET /usuarios/5 → Obtener uno
            ($httpMethod === 'GET' && $id !== null) => function() use ($database, $controller) {
                Token::requirePermission('usuarios', 'leer');
                $controller->obtener((int) $id);
            },
            
            // POST /usuarios → Crear nuevo
            ($httpMethod === 'POST' && $id === null) => function() use ($database, $controller) {
                Token::requirePermission('usuarios', 'crear');
                $controller->crear();
            },
            
            // PUT /usuarios/5 → Actualizar
            ($httpMethod === 'PUT' && $id !== null) => function() use ($database, $controller) {
                Token::requirePermission('usuarios', 'actualizar');
                $controller->actualizar((int) $id);
            },

            // DELETE /usuarios/5 → Soft delete
            ($httpMethod === 'DELETE' && $id !== null) => function() use ($database, $controller) {
                Token::requirePermission('usuarios', 'eliminar');
                $controller->eliminar((int) $id);
            },
            
            default => Response::error("Método no permitido para esta ruta", 405)
        };
    }
}