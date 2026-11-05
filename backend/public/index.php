<?php
declare(strict_types=1);

// =========================================
// MODO DEPURACIÓN (ACTIVAR SOLO PARA DIAGNOSTICAR) (CORREGIDO)
// =========================================
// Usar comillas simples para que sea un string, no un int
//ini_set('display_errors', '1');
//ini_set('display_startup_errors', '1');
//error_reporting(E_ALL);

// =========================================
// CONFIGURACIÓN DE ZONA HORARIA
// =========================================
// Establece la zona horaria para toda la aplicación PHP
date_default_timezone_set('America/Mexico_City');

// =========================
// AUTOLOAD
// =========================
require_once __DIR__ . '/../src/autoload.php';

// =========================
// IMPORTS
// =========================
use App\Core\Database;
use App\Core\Router;
use App\Utils\Response;
use App\Utils\Env;

// =========================
// INICIO DE SESIÓN
// =========================

// =========================
// HEADERS
// =========================
// Se omite este header debido a que el frontend y el backend estan en el
// mismo dominio.
//header("Access-Control-Allow-Origin: *");
// En desarrollo local:
//header("Access-Control-Allow-Origin: http://localhost");
// En producción
// header("Access-Control-Allow-Origin: https://XXXX.com");
//20261104
// En producción, deshabilitar completamente PHP en headers
//\App\Utils\
if (!Env::getBool('APP_DEBUG', false)) {
    header_remove('X-Powered-By');
}
// Seguridad: Previene que el navegador interprete archivos como otro MIME type
header('X-Content-Type-Options: nosniff');

// Seguridad: Previene clickjacking
header('X-Frame-Options: DENY');

// Seguridad: Activa el filtro XSS del navegador (legacy, pero útil)
header('X-XSS-Protection: 1; mode=block');

// Seguridad: Controla cuánta información se comparte en el Referer
header('Referrer-Policy: strict-origin-when-cross-origin');

// Seguridad: Controla qué features puede usar el navegador
// si la app necesita alguna de estas funciones usa 
// geolocation=(self) : permite geolocalización
// microphone=(self)  : para grabar audio
// camera=(self)      : para escanear códigos o tomar fotos
header('Permissions-Policy: geolocation=(self), microphone=(), camera=()');

// =========================
// DATABASE
// =========================
try {
    $database = Database::getInstance();
} catch (\Exception $e) {
    //envia la respuesta de error
    Response::error(
        $e->getMessage(),
        500
    );

    exit;
}

// =========================
// ROUTING
// =========================
$route = $_GET['route'] ?? '';

Router::dispatch($route, $database);