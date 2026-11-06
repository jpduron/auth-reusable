<?php
declare(strict_types=1);

namespace App\Utils;

class Mailer {
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;

    public function __construct() {
        $this->apiKey = Env::get('RESEND_API_KEY', '');
        $this->fromEmail = Env::get('MAIL_FROM_ADDRESS', 'onboarding@resend.dev');
        $this->fromName = Env::get('MAIL_FROM_NAME', 'Mi Aplicación');
    }

    /**
     * Envía un correo de verificación de cuenta
     */
    public function sendVerificationEmail(string $toEmail, string $toName, string $token): bool {
        $verificationLink = Env::get('FRONTEND_URL') . "/pages/verify-email.html?token={$token}";
        
        $subject = "Verifica tu cuenta - {$this->fromName}";
        $htmlBody = $this->getVerificationTemplate($toName, $verificationLink);

        return $this->send($toEmail, $subject, $htmlBody);
    }

    /**
     * Envía un correo de recuperación de contraseña
     */
    public function sendPasswordResetEmail(string $toEmail, string $toName, string $token): bool {
        $resetLink = Env::get('FRONTEND_URL') . "/pages/reset-password.html?token={$token}";
        
        $subject = "Recuperar tu contraseña - {$this->fromName}";
        $htmlBody = $this->getResetTemplate($toName, $resetLink);

        return $this->send($toEmail, $subject, $htmlBody);
    }

    /**
     * Método privado que envía el correo usando la API de Resend vía cURL
     */
    private function send(string $to, string $subject, string $htmlBody): bool {
        if (empty($this->apiKey) || strpos($this->apiKey, 're_') !== 0) {
            error_log("ERROR MAILER: RESEND_API_KEY no está configurada correctamente en .env");
            return false;
        }

        $url = 'https://api.resend.com/emails';
        
        $payload = [
            'from'    => "{$this->fromName} <{$this->fromEmail}>",
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $htmlBody
        ];

        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ]);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Solo para desarrollo local
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        error_log("Resend API Error (HTTP {$httpCode}): {$response} | cURL Error: {$error}");
        return false;
    }

    // ==========================================
    // PLANTILLAS HTML (Para mantener el código limpio)
    // ==========================================

    private function getVerificationTemplate(string $name, string $link): string {
        return "
            <html>
            <head><meta charset='UTF-8'></head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #2c3e50;'>¡Bienvenido a {$this->fromName}!</h2>
                    <p>Hola <strong>{$name}</strong>,</p>
                    <p>Para activar tu cuenta, haz clic en el siguiente enlace:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$link}' style='background-color: #3498db; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Verificar mi cuenta</a>
                    </div>
                    <p style='color: #7f8c8d; font-size: 12px;'>Si no puedes hacer clic, copia y pega este enlace: <a href='{$link}'>{$link}</a></p>
                </div>
            </body>
            </html>
        ";
    }

    private function getResetTemplate(string $name, string $link): string {
        return "
            <html>
            <head><meta charset='UTF-8'></head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #e74c3c;'>Recuperación de Contraseña</h2>
                    <p>Hola <strong>{$name}</strong>,</p>
                    <p>Recibimos una solicitud para restablecer tu contraseña:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$link}' style='background-color: #e74c3c; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Restablecer contraseña</a>
                    </div>
                    <p style='color: #7f8c8d; font-size: 12px;'>Si no solicitaste esto, puedes ignorar este correo de forma segura.</p>
                </div>
            </body>
            </html>
        ";
    }
}