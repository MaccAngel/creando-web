<?php
/**
 * Configuración mínima usada por los tests.
 *
 * tests/bootstrap.php define RECORDATORIO_CONFIG apuntando a este archivo,
 * de modo que config() lo cargue en lugar de config.php. La app_key es una
 * clave válida (32 bytes en base64) pero "de juguete": solo para los tests.
 */

return [
    'db' => [
        'host'    => '127.0.0.1',
        'nombre'  => 'recordatorio_tareas_test',
        'usuario' => 'root',
        'clave'   => '',
    ],
    'base_url' => 'http://localhost:8000',
    'google' => [
        'client_id'     => 'test',
        'client_secret' => 'test',
    ],
    'microsoft' => [
        'client_id'     => 'test',
        'client_secret' => 'test',
        'tenant'        => 'common',
    ],
    // 32 bytes (todo ceros) en base64. Solo para tests.
    'app_key' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'pin_hash' => '',
    'zona_horaria' => 'Europe/Madrid',
    'aviso_dias' => 2,
    'auto_sync_min' => 5,
    'agenda' => [
        'activa'       => false,
        'hora'         => '09:00',
        'duracion_min' => 30,
    ],
    'filtros' => [
        'remitentes_bloqueados' => ['noreply@'],
        'palabras_bloqueadas'   => ['oferta'],
    ],
];
