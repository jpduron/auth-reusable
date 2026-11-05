<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Database;
use App\Utils\Response;
use App\Utils\Request;
use App\Utils\Token;

class Router {
    public static function dispatch(string $route, Database $database): void {
        $route = trim($route);
        
        // 1. Define public routes (No token required)
        $publicRoutes = [
            'login', 
            'register', 
            'verify-email',
            'forgot-password',
            'reset-password'
        ];

        // 2. Middleware: Check token for protected routes
        if (!in_array($route, $publicRoutes)) {
            Token::requireAuth(); 
        }

        // 3. Route matching
        match ($route) { 
            // public routes
            'login'            => self::handleAuth($database, 'login'),
            'register'         => self::handleAuth($database, 'register'),
            'verify-email'     => self::handleAuth($database, 'verifyEmail'),
            'forgot-password'  => self::handleAuth($database, 'forgotPassword'),
            'reset-password'   => self::handleAuth($database, 'resetPassword'),


            // protected routes
            'logout'           => self::handleAuth($database, 'logout'),
            'me'               => self::handleMe($database), 
            
            default => Response::error("Ruta no encontrada", 404)
        };
    }

    // Helper for Login / Logout
    private static function handleAuth(Database $database, string $action): void 
    {
        // Determinar el método HTTP requerido
        $method = match ($action) {
            'login', 'register', 'forgotPassword', 'resetPassword', 'logout' => 'POST',
            'verify-email' => 'GET',
            default => 'GET'
        };
        
        Request::requireMethod($method);

        $pdo = $database->getConnection();
        $model = new \App\Models\User($pdo); 
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
}