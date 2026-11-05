# 🔐 Auth Reusable Backend

Plantilla de backend reutilizable, construida con **PHP 8.3 puro**, sin frameworks ni Composer. Diseñada con arquitectura limpia (MVC/Service), segura y lista para APIs REST o PWAs.

## 🚀 Características Principales
- **Autenticación Moderna**: Login/Logout con Tokens Bearer (sin sesiones PHP).
- **Seguridad Robusta**: 
  - Expiración de tokens (configurable).
  - Anti brute-force (bloqueo temporal tras 5 intentos).
  - Soft Deletes y auditoría de intentos de login.
  - Headers HTTP de seguridad (XSS, Clickjacking, MIME sniffing).
- **Arquitectura Limpia**: Separación estricta de Modelos, Servicios y Controladores.
- **Autorización (RBAC)**: Sistema de roles y permisos granular (Admin, User, Super Usuario).
- **Envío de Correos**: Integración nativa con API de Resend vía cURL (sin dependencias).

## 📂 Estructura del Proyecto
```text
├── backend/
│   ├── public/         # Punto de entrada (index.php) y .htaccess
│   └── src/
│       ├── Core/       # Router, Database, Autoloader
│       ├── Controllers/# Lógica HTTP
│       ├── Models/     # Consultas a BD (PDO)
│       ├── Services/   # Lógica de negocio y validaciones
│       └── Utils/      # Helpers (Token, Request, Response, Mailer, Env)
├── database/
│   └── schema.sql      # Estructura completa de la base de datos
└── .env.example        # Plantilla de variables de entorno