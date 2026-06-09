<?php
/**
 * Configuración de ejemplo de "Recordatorio de Tareas".
 *
 * Copia este archivo a config.php y rellena tus valores:
 *     cp config.example.php config.php
 *
 * IMPORTANTE: config.php está en .gitignore y NUNCA debe subirse al repositorio,
 * ya que contiene secretos (claves de cifrado y credenciales OAuth).
 *
 * La función config() de lib/proveedores.php lee este array.
 */

return [

    // --- Base de datos (MySQL / MariaDB) ---------------------------------
    'db' => [
        'host'    => '127.0.0.1',
        'nombre'  => 'recordatorio_tareas',
        'usuario' => 'root',
        'clave'   => '',
    ],

    // URL base de la app (sin barra final). Debe coincidir con la usada
    // al registrar las apps OAuth (redirect URI).
    'base_url' => 'http://localhost:8000',

    // --- Credenciales OAuth de Google ------------------------------------
    // Google Cloud Console -> Credenciales -> ID de cliente OAuth (Aplicación web).
    'google' => [
        'client_id'     => 'TU_GOOGLE_CLIENT_ID',
        'client_secret' => 'TU_GOOGLE_CLIENT_SECRET',
    ],

    // --- Credenciales OAuth de Microsoft (Entra ID) ----------------------
    // Microsoft Entra -> Registros de aplicaciones. Usa endpoint v2.0.
    // tenant 'common' permite cuentas personales y de organización.
    'microsoft' => [
        'client_id'     => 'TU_MICROSOFT_CLIENT_ID',
        'client_secret' => 'TU_MICROSOFT_CLIENT_SECRET',
        'tenant'        => 'common',
    ],

    // Clave de cifrado de tokens (32 bytes en base64). Genérala con:
    //   php -r "echo base64_encode(random_bytes(32));"
    'app_key' => 'GENERA_UNA_CLAVE_BASE64_DE_32_BYTES',

    // PIN opcional. Vacío = sin PIN (acceso libre en local).
    // Para activarlo, guarda aquí un hash creado con:
    //   php -r "echo password_hash('1234', PASSWORD_DEFAULT);"
    'pin_hash' => '',

    // Zona horaria para fechas y eventos de calendario.
    'zona_horaria' => 'Europe/Madrid',

    // Días de antelación para avisar de tareas que "vencen pronto".
    'aviso_dias' => 2,

    // Auto-sincronización en la web cada N minutos (0 = desactivada).
    'auto_sync_min' => 5,

    // --- Agenda / Calendario ---------------------------------------------
    'agenda' => [
        'activa'        => true,   // crear eventos automáticamente
        'hora'          => '09:00', // hora de inicio del evento
        'duracion_min'  => 30,      // duración en minutos
    ],

    // --- Filtros de correo no deseado ------------------------------------
    // Se combinan con los almacenados en la tabla `filtros`.
    'filtros' => [
        'remitentes_bloqueados' => [
            'noreply@',
            'newsletter@',
        ],
        'palabras_bloqueadas' => [
            'promoción',
            'oferta',
            'unsubscribe',
        ],
    ],
];
