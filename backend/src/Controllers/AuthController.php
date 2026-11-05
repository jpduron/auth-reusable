<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Utils\Request;
use App\Utils\Response;
use App\Utils\Token;

class AuthController {
    private AuthService $authService;

    public function __construct(AuthService $authService) {
        $this->authService = $authService;
    }

    public function login(): void {
        // 1. Get JSON input safely using your custom Request utility
        $data = Request::input();
        $correo = trim((string) ($data['correo'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        // 2. Basic validation
        if (empty($correo) || empty($password)) {
            Response::error('El correo y la contraseña son obligatorios', 400);
            return;
        }

        // 3. Delegate to Service
        $result = $this->authService->login($correo, $password);

        // 4. Return Response using your custom Response utility
        if ($result['success']) {
            Response::success($result['data'], $result['message'], 200);
        } else {
            Response::error($result['message'], 401);
        }
    }

    public function logout(): void {
        // 1. Get user from the Bearer Token (Middleware already checked this in Router)
        $user = Token::getUserFromToken();
        
        if (!$user) {
            Response::error('No autorizado', 401);
            return;
        }

        // 2. Delegate to Service
        $result = $this->authService->logout((int) $user['id']);
        Response::success(null, $result['message'], 200);
    }

    public function register(): void {
        $data = Request::input();
        
        $nombreCompleto = trim((string) ($data['nombre_completo'] ?? ''));
        $correo = trim((string) ($data['correo'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        // Validaciones
        if (empty($nombreCompleto) || empty($correo) || empty($password)) {
            Response::error('Todos los campos son obligatorios', 400);
            return;
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            Response::error('Correo electrónico inválido', 400);
            return;
        }

        if (strlen($password) < 8) {
            Response::error('La contraseña debe tener al menos 8 caracteres', 400);
            return;
        }

        $result = $this->authService->register($nombreCompleto, $correo, $password);

        if ($result['success']) {
            Response::success(null, $result['message'], 201);
        } else {
            Response::error($result['message'], 400);
        }
    }

    public function verifyEmail(): void {
        $token = Request::getString('token');

        if (empty($token)) {
            Response::error('Token de verificación requerido', 400);
            return;
        }

        $result = $this->authService->verifyEmail($token);

        if ($result['success']) {
            Response::success(null, $result['message'], 200);
        } else {
            Response::error($result['message'], 400);
        }
    }

    public function forgotPassword(): void {
        $data = Request::input();
        $correo = trim((string) ($data['correo'] ?? ''));

        if (empty($correo)) {
            Response::error('Correo electrónico requerido', 400);
            return;
        }

        $result = $this->authService->forgotPassword($correo);
        Response::success(null, $result['message'], 200);
    }

    public function resetPassword(): void {
        $data = Request::input();
        $token = (string) ($data['token'] ?? '');
        $newPassword = (string) ($data['password'] ?? '');

        if (empty($token) || empty($newPassword)) {
            Response::error('Token y nueva contraseña son requeridos', 400);
            return;
        }

        if (strlen($newPassword) < 8) {
            Response::error('La contraseña debe tener al menos 8 caracteres', 400);
            return;
        }

        $result = $this->authService->resetPassword($token, $newPassword);

        if ($result['success']) {
            Response::success(null, $result['message'], 200);
        } else {
            Response::error($result['message'], 400);
        }
    }
}