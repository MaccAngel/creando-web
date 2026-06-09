-- Esquema de la base de datos de "Recordatorio de Tareas".
-- Crea la base de datos y ejecuta este archivo:
--   mysql -u root -p < sql/esquema.sql
--
-- Motor InnoDB y juego de caracteres utf8mb4 en todas las tablas.

CREATE DATABASE IF NOT EXISTS `recordatorio_tareas`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `recordatorio_tareas`;

SET NAMES utf8mb4;

-- --------------------------------------------------------------------
-- Cuentas de correo conectadas por OAuth (Google / Microsoft).
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cuentas` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `proveedor`           ENUM('google','microsoft') NOT NULL,
    `email`               VARCHAR(255) NOT NULL,
    `access_token`        TEXT NOT NULL,
    `refresh_token`       TEXT NULL,
    `expira_en`           DATETIME NULL,
    `requiere_reconexion` TINYINT(1) NOT NULL DEFAULT 0,
    `creada_en`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_proveedor_email` (`proveedor`, `email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- Tareas (manuales o importadas desde correos).
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tareas` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `titulo`           VARCHAR(255) NOT NULL,
    `notas`            TEXT NULL,
    `prioridad`        ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
    `fecha_limite`     DATE NULL,
    `completada`       TINYINT(1) NOT NULL DEFAULT 0,
    `fuente`           ENUM('manual','email') NOT NULL DEFAULT 'manual',
    `cuenta_id`        INT UNSIGNED NULL,
    `email_message_id` VARCHAR(512) NULL,
    `email_asunto`     VARCHAR(512) NULL,
    `email_remitente`  VARCHAR(512) NULL,
    `email_enlace`     VARCHAR(1024) NULL,
    `evento_id`        VARCHAR(512) NULL,
    `evento_enlace`    VARCHAR(1024) NULL,
    `creada_en`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Evita importar dos veces el mismo correo en la misma cuenta.
    UNIQUE KEY `uq_cuenta_mensaje` (`cuenta_id`, `email_message_id`),
    KEY `idx_completada` (`completada`),
    KEY `idx_fecha_limite` (`fecha_limite`),
    CONSTRAINT `fk_tareas_cuenta`
        FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- Filtros de correo no deseado (por remitente o por palabra).
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `filtros` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tipo`      ENUM('remitente','palabra') NOT NULL,
    `valor`     VARCHAR(255) NOT NULL,
    `creada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- Reglas de prioridad: si el correo contiene X -> prioridad Y.
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reglas` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `contiene`  VARCHAR(255) NOT NULL,
    `prioridad` ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
    `creada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
