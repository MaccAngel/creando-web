<?php
/**
 * Configuración para el entorno Docker.
 *
 * El contenedor define la variable de entorno RECORDATORIO_CONFIG apuntando a
 * este archivo, de modo que config() lo use sin necesidad de crear config.php.
 *
 * Todos los valores se leen de variables de entorno (definidas en
 * docker-compose.yml), con valores por defecto razonables para desarrollo.
 */

return [

    'db' => [
        'host'    => getenv('DB_HOST')   ?: 'db',
        'nombre'  => getenv('DB_NAME')   ?: 'recordatorio_tareas',
        'usuario' => getenv('DB_USER')   ?: 'root',
        'clave'   => getenv('DB_PASS')   ?: 'root',
    ],

    'base_url' => getenv('BASE_URL') ?: 'http://localhost:8000',

    'google' => [
        'client_id'     => getenv('GOOGLE_CLIENT_ID')     ?: 'TU_GOOGLE_CLIENT_ID',
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: 'TU_GOOGLE_CLIENT_SECRET',
    ],

    'microsoft' => [
        'client_id'     => getenv('MS_CLIENT_ID')     ?: 'TU_MICROSOFT_CLIENT_ID',
        'client_secret' => getenv('MS_CLIENT_SECRET') ?: 'TU_MICROSOFT_CLIENT_SECRET',
        'tenant'        => getenv('MS_TENANT')         ?: 'common',
    ],

    // La genera el entrypoint si no se ha definido (APP_KEY).
    'app_key' => getenv('APP_KEY') ?: '',

    'pin_hash' => getenv('PIN_HASH') ?: '',

    'zona_horaria'  => getenv('ZONA_HORARIA') ?: 'Europe/Madrid',
    'aviso_dias'    => (int) (getenv('AVISO_DIAS')    ?: 2),
    'auto_sync_min' => (int) (getenv('AUTO_SYNC_MIN') ?: 5),

    'agenda' => [
        'activa'       => filter_var(getenv('AGENDA_ACTIVA') ?: 'true', FILTER_VALIDATE_BOOL),
        'hora'         => getenv('AGENDA_HORA') ?: '09:00',
        'duracion_min' => (int) (getenv('AGENDA_DURACION') ?: 30),
    ],

    'filtros' => [
        'remitentes_bloqueados' => ['noreply@', 'newsletter@'],
        'palabras_bloqueadas'   => ['promoción', 'oferta', 'unsubscribe'],
    ],
];
