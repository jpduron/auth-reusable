-- ==========================================
-- SCHEMA DE BASE DE DATOS - AUTH REUSABLE
-- PHP 8.3 Backend Template
-- ==========================================
-- Orden de creación respeta las dependencias de Foreign Keys
-- ==========================================

-- ==========================================
-- 1. TABLAS BASE (Sin dependencias)
-- ==========================================

CREATE TABLE `estados` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL COMMENT 'Ej: ACTIVO, INACTIVO, BLOQUEADO, PENDIENTE',
  `orden` tinyint NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL COMMENT 'ADMIN, USER',
  `es_sistema` tinyint DEFAULT '0' COMMENT '1 = Rol del sistema (no eliminable), 0 = Rol de usuario',
  `orden` tinyint NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 2. TABLAS CON DEPENDENCIAS
-- ==========================================

CREATE TABLE `permisos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `rol_id` int unsigned NOT NULL COMMENT 'ID del rol al que se asigna el permiso',
  `recurso` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: usuarios, areas, reportes',
  `accion` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: crear, leer, actualizar, eliminar',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_permiso` (`rol_id`,`recurso`,`accion`),
  CONSTRAINT `fk_permisos_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuarios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre_completo` varchar(150) NOT NULL COMMENT 'Nombre completo en MAYÚSCULAS',
  `clave_hash` varchar(255) NOT NULL,
  `correo` varchar(100) NOT NULL COMMENT 'Login del sistema y correo institucional',
  `rol_id` int unsigned DEFAULT NULL,
  `estado_id` int unsigned NOT NULL,
  `es_su` tinyint DEFAULT '0' COMMENT '1 = Super Usuario (acceso total)',
  `creado_por` int unsigned DEFAULT NULL,
  `creado_el` datetime DEFAULT CURRENT_TIMESTAMP,
  `eliminado_por` int unsigned DEFAULT NULL,
  `eliminado_el` datetime DEFAULT NULL,
  `ultima_sesion_el` datetime DEFAULT NULL,
  `ip_registro` varchar(45) DEFAULT NULL,
  `api_token` varchar(64) DEFAULT NULL COMMENT 'Token Bearer para la API',
  `token_verificacion` varchar(64) DEFAULT NULL COMMENT 'Token para validar correo al registrar',
  `verificado_el` datetime DEFAULT NULL COMMENT 'Fecha en que el usuario verificó su correo',
  `token_recuperacion` varchar(64) DEFAULT NULL COMMENT 'Token para recuperar contraseña',
  `token_recuperacion_expira` datetime DEFAULT NULL COMMENT 'Fecha de expiración del token de recuperación',
  `api_token_expira` datetime DEFAULT NULL COMMENT 'Fecha de expiración del token de sesión',
  `intentos_fallidos` int DEFAULT '0' COMMENT 'Contador de logins fallidos',
  `bloqueado_hasta` datetime DEFAULT NULL COMMENT 'Fecha hasta la que está bloqueado',
  PRIMARY KEY (`id`),
  UNIQUE KEY `correo` (`correo`),
  KEY `fk_usuarios_roles_id` (`rol_id`),
  KEY `fk_usuarios_estados_id` (`estado_id`),
  KEY `fk_usuarios_usuarios_id` (`creado_por`),
  KEY `fk_usuarios_usuarios_eliminado` (`eliminado_por`),
  KEY `idx_api_token` (`api_token`),
  KEY `idx_token_verificacion` (`token_verificacion`),
  KEY `idx_token_recuperacion` (`token_recuperacion`),
  CONSTRAINT `fk_usuarios_estados_id` FOREIGN KEY (`estado_id`) REFERENCES `estados` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_usuarios_roles_id` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_usuarios_usuarios_creado` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_usuarios_usuarios_eliminado` FOREIGN KEY (`eliminado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_intentos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int unsigned DEFAULT NULL,
  `correo` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `exitoso` tinyint(1) NOT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_correo_fecha` (`correo`,`fecha`),
  KEY `idx_ip_fecha` (`ip`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 3. DATOS SEMILLA (Seed Data)
-- ==========================================

-- Estados del sistema
INSERT INTO `estados` (`id`, `nombre`, `orden`) VALUES
(1, 'ACTIVO', 1),
(2, 'INACTIVO', 2),
(3, 'BLOQUEADO', 3),
(4, 'PENDIENTE', 4);

-- Roles del sistema
INSERT INTO `roles` (`id`, `nombre`, `es_sistema`, `orden`) VALUES
(1, 'ADMIN', 1, 1),
(2, 'USER', 1, 2);

-- Permisos por defecto para el rol ADMIN
INSERT INTO `permisos` (`rol_id`, `recurso`, `accion`) VALUES
(1, 'usuarios', 'leer'),
(1, 'usuarios', 'crear'),
(1, 'usuarios', 'actualizar'),
(1, 'usuarios', 'eliminar');

-- ==========================================
-- 4. USUARIO ADMINISTRADOR INICIAL (OPCIONAL)
-- ==========================================
-- Descomenta y ajusta si quieres crear un admin inicial
-- La contraseña es 'Admin123!' (hash generado con password_hash)

/*
INSERT INTO `usuarios` (
    `nombre_completo`, 
    `clave_hash`, 
    `correo`, 
    `estado_id`, 
    `rol_id`, 
    `es_su`, 
    `creado_el`
) VALUES (
    'ADMINISTRADOR DEL SISTEMA',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin@tudominio.com',
    1,
    1,
    1,
    NOW()
);
*/