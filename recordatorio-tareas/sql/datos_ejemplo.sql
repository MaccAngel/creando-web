-- Datos de ejemplo (seeds) para probar la interfaz sin conectar el correo.
--
-- Carga después del esquema:
--   mysql -u root -p recordatorio_tareas < sql/datos_ejemplo.sql
--
-- Las fechas son relativas a HOY para que se vean los badges de vencimiento
-- (Vencida / Vence hoy / Vence pronto). No incluye cuentas: las tareas son
-- manuales (cuenta_id = NULL).

USE `recordatorio_tareas`;

-- Empezamos limpio para que el seed sea reproducible.
DELETE FROM `tareas`;
DELETE FROM `reglas`;
DELETE FROM `filtros`;
ALTER TABLE `tareas`  AUTO_INCREMENT = 1;
ALTER TABLE `reglas`  AUTO_INCREMENT = 1;
ALTER TABLE `filtros` AUTO_INCREMENT = 1;

-- --------------------------------------------------------------------
-- Filtros de correo no deseado de ejemplo
-- --------------------------------------------------------------------
INSERT INTO `filtros` (`tipo`, `valor`) VALUES
    ('remitente', 'noreply@'),
    ('remitente', 'newsletter@'),
    ('palabra',   'promoción'),
    ('palabra',   'descuento');

-- --------------------------------------------------------------------
-- Reglas de prioridad de ejemplo
-- --------------------------------------------------------------------
INSERT INTO `reglas` (`contiene`, `prioridad`) VALUES
    ('factura',     'alta'),
    ('urgente',     'alta'),
    ('reunión',     'media'),
    ('boletín',     'baja');

-- --------------------------------------------------------------------
-- Tareas de ejemplo (manuales)
-- --------------------------------------------------------------------
INSERT INTO `tareas`
    (`titulo`, `notas`, `prioridad`, `fecha_limite`, `completada`, `fuente`)
VALUES
    -- Vencida (ayer)
    ('Pagar factura de la luz',
     'Domiciliación pendiente de confirmar.',
     'alta', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 0, 'manual'),

    -- Vence hoy
    ('Llamar al dentista',
     'Pedir cita para revisión.',
     'media', CURDATE(), 0, 'manual'),

    -- Vence pronto (mañana)
    ('Preparar presentación del proyecto',
     NULL,
     'alta', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 0, 'manual'),

    -- Sin urgencia (dentro de una semana)
    ('Comprar regalo de cumpleaños',
     'Mirar opciones por internet.',
     'baja', DATE_ADD(CURDATE(), INTERVAL 7 DAY), 0, 'manual'),

    -- Sin fecha límite
    ('Revisar copia de seguridad del portátil',
     'Comprobar que el respaldo automático funciona.',
     'media', NULL, 0, 'manual'),

    -- Ya completada
    ('Enviar informe mensual',
     'Entregado al equipo.',
     'media', DATE_SUB(CURDATE(), INTERVAL 3 DAY), 1, 'manual');

-- --------------------------------------------------------------------
-- Ejemplo de tarea "importada de correo" (sin cuenta real asociada).
-- Muestra el enlace ✉️ en la interfaz.
-- --------------------------------------------------------------------
INSERT INTO `tareas`
    (`titulo`, `prioridad`, `fecha_limite`, `fuente`,
     `email_asunto`, `email_remitente`, `email_enlace`)
VALUES
    ('Factura pendiente de Hosting',
     'alta', CURDATE(), 'email',
     'Factura pendiente de Hosting',
     'facturacion@miproveedor.com',
     'https://mail.google.com/mail/u/0/#all/EJEMPLO');
