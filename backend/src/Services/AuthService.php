<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario;
use App\Utils\Token;
use App\Utils\Request;
use App\Utils\Mailer;

class AuthService {
    private Usuario $usuarioModel;

    // Constants for the 'estados' table to make the code readable and reusable
    private const ESTADO_ACTIVO     = 1;
    private const ESTADO_INACTIVO   = 2;
    private const ESTADO_BLOQUEADO  = 3;
    private const ESTADO_PENDIENTE  = 4;

    public function __construct(Usuario $usuarioModel) {
        $this->usuarioModel = $usuarioModel;
    }

    public function login(string $correo, string $password): array {
        $exito = false; // Por defecto asumimos fallo
        $userId = null;

        try {
            // 1. Find user
            $user = $this->usuarioModel->findByEmail($correo);
            $userId = $user['id'] ?? null;

            // 👇 Uso ?? null para evitar el warning si la columna no existe
            $bloqueadoHasta = $user['bloqueado_hasta'] ?? null;

            // Verificar si está bloqueado temporalmente
            if ($user && $user['bloqueado_hasta'] !== null) {
                $bloqueadoHasta = strtotime($user['bloqueado_hasta']);
                if (time() < $bloqueadoHasta) {
                    $minutos = ceil(($bloqueadoHasta - time()) / 60);
                    return [
                        'success' => false, 
                        'message' => "Cuenta bloqueada temporalmente. Intenta en {$minutos} minutos."
                    ];
                }
                // Si ya pasó el tiempo, resetear contador
                $this->usuarioModel->resetIntentos((int) $user['id']);
            }

            // 2. Verify credentials
            if (!$user || !password_verify($password, $user['clave_hash'])) {
                // Registrar intento fallido en la tabla de usuarios (para bloquear)
                if ($user) {
                    $this->usuarioModel->registrarIntentoFallido((int) $user['id']);
                }
                
                // Mensaje genérico (no revelar si el correo existe o no)
                return ['success' => false, 'message' => 'Correo o contraseña incorrectos.'];
            }

            // Login exitoso hasta este punto: resetear contador de intentos fallidos
            $this->usuarioModel->resetIntentos((int) $user['id']);

            // 3. Check user status (estado_id)
            $estadoId = (int) $user['estado_id'];
            
            if ($estadoId === self::ESTADO_PENDIENTE) {
                return ['success' => false, 'message' => 'Tu cuenta está pendiente de activación. Revisa tu correo.'];
            }
            if ($estadoId === self::ESTADO_BLOQUEADO) {
                return ['success' => false, 'message' => 'Tu cuenta ha sido bloqueada. Contacta al administrador.'];
            }
            if ($estadoId !== self::ESTADO_ACTIVO) {
                return ['success' => false, 'message' => 'Tu cuenta está inactiva.'];
            }

            // 4. Generate secure API token
            $newToken = Token::generate();
            $days = 1;
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$days} days")); // 1 día

            // 5. Save token to database
            $this->usuarioModel->updateApiToken((int) $user['id'], $newToken, $expiresAt);

            // ¡Marcar como exitoso justo antes de retornar!
            $exito = true;

            // 👇 NUEVO: Actualizar la fecha del último acceso
            $this->usuarioModel->updateLastLogin((int) $user['id']);

            // 6. Return success (NEVER return clave_hash to the frontend)
            return [
                'success' => true,
                'message' => 'Login exitoso',
                'data' => [
                    'token' => $newToken,
                    'user' => [
                        'id'              => (int) $user['id'],
                        'nombre_completo' => $user['nombre_completo'],
                        'correo'          => $user['correo'],
                        'rol_id'          => (int) $user['rol_id'],
                        'es_su'           => (bool) $user['es_su']
                    ]
                ]
            ];

        } finally {
            // 👇 ESTE BLOQUE SIEMPRE SE EJECUTA, haya tenido éxito o haya fallado 👇
            $this->usuarioModel->registrarIntentoLogin(
                $userId, 
                $correo, 
                Request::ip(), 
                Request::userAgent(), 
                $exito
            );
        }
    }

    public function logout(int $userId): array {
        $this->usuarioModel->clearApiToken($userId);
        return ['success' => true, 'message' => 'Sesión cerrada correctamente'];
    }

    /**
     * Registra un nuevo usuario
     */
    public function register(string $nombreCompleto, string $correo, string $password): array {
        // 1. Validar que el correo no exista
        $existingUser = $this->usuarioModel->findByEmail($correo);
        if ($existingUser) {
            return [
                'success' => false,
                'message' => 'Este correo ya está registrado'
            ];
        }

        // 2. Hashear la contraseña
        $claveHash = password_hash($password, PASSWORD_DEFAULT);

        // 3. Generar token de verificación
        $tokenVerificacion = bin2hex(random_bytes(32));

        // 4. Crear usuario pendiente
        $userId = $this->usuarioModel->createPending(
            $nombreCompleto,
            $correo,
            $claveHash,
            $tokenVerificacion
        );

        if (!$userId) {
            return [
                'success' => false,
                'message' => 'Error al crear el usuario'
            ];
        }

        // 5. Enviar correo de verificación
        $mailer = new Mailer();
        $emailSent = $mailer->sendVerificationEmail($correo, $nombreCompleto, $tokenVerificacion);

        if (!$emailSent) {
            error_log("No se pudo enviar correo de verificación a: {$correo}");
        }

        return [
            'success' => true,
            'message' => 'Registro exitoso. Revisa tu correo para verificar tu cuenta.'
        ];
    }

    /**
     * Verifica el email con el token
     */
    public function verifyEmail(string $token): array {
        $verified = $this->usuarioModel->verifyEmail($token);

        if ($verified) {
            return [
                'success' => true,
                'message' => 'Cuenta verificada exitosamente. Ya puedes iniciar sesión.'
            ];
        }

        return [
            'success' => false,
            'message' => 'Token inválido o ya utilizado'
        ];
    }

    /**
     * Solicita recuperación de contraseña
     */
    public function forgotPassword(string $correo): array {
        $user = $this->usuarioModel->findByEmail($correo);

        // Siempre retornamos éxito por seguridad (no revelar si el correo existe)
        if (!$user || $user['estado_id'] !== 1) {
            return [
                'success' => true,
                'message' => 'Si el correo está registrado, recibirás un enlace de recuperación.'
            ];
        }

        // Generar token de recuperación (válido por 1 hora)
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $saved = $this->usuarioModel->saveResetToken($correo, $token, $expiresAt);

        if ($saved) {
            $mailer = new Mailer();
            $mailer->sendPasswordResetEmail($correo, $user['nombre_completo'], $token);
        }

        return [
            'success' => true,
            'message' => 'Si el correo está registrado, recibirás un enlace de recuperación.'
        ];
    }

    /**
     * Resetea la contraseña con el token
     */
    public function resetPassword(string $token, string $newPassword): array {
        $user = $this->usuarioModel->findByResetToken($token);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Token inválido o expirado'
            ];
        }

        $claveHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $reset = $this->usuarioModel->resetPassword((int) $user['id'], $claveHash);

        if ($reset) {
            return [
                'success' => true,
                'message' => 'Contraseña actualizada exitosamente'
            ];
        }

        return [
            'success' => false,
            'message' => 'Error al actualizar la contraseña'
        ];
    }
}