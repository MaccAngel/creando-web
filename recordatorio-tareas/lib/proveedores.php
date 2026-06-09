<?php

/**
 * Configuración global y fábricas de proveedores OAuth.
 *
 * - config()              Devuelve el array de config.php (cacheado).
 * - proveedorGoogle()     Crea el proveedor OAuth de Google.
 * - proveedorMicrosoft()  Crea el proveedor OAuth de Microsoft (Azure v2.0).
 * - proveedorPara()       Devuelve el proveedor correcto según 'google'/'microsoft'.
 * - scopesPara()          Devuelve los scopes a solicitar por proveedor.
 * - urlCallback()         Construye la redirect URI del callback.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use League\OAuth2\Client\Provider\Google;
use TheNetworg\OAuth2\Client\Provider\Azure;

/**
 * Lee y cachea la configuración de config.php.
 *
 * @return array
 */
function config(): array
{
    static $config = null;

    if ($config === null) {
        // Permite usar una configuración alternativa (p. ej. en los tests)
        // definiendo la variable de entorno RECORDATORIO_CONFIG.
        $ruta = getenv('RECORDATORIO_CONFIG') ?: __DIR__ . '/../config.php';
        if (!is_file($ruta)) {
            throw new RuntimeException(
                'No existe config.php. Copia config.example.php a config.php y rellénalo.'
            );
        }
        $config = require $ruta;

        // Aplicar zona horaria global cuanto antes.
        if (!empty($config['zona_horaria'])) {
            date_default_timezone_set($config['zona_horaria']);
        }
    }

    return $config;
}

/**
 * Construye la redirect URI del callback para un proveedor.
 */
function urlCallback(string $proveedor): string
{
    $cfg = config();
    return rtrim($cfg['base_url'], '/') . '/oauth_callback.php?p=' . urlencode($proveedor);
}

/**
 * Scopes solicitados por proveedor.
 *
 * @return string[]
 */
function scopesPara(string $proveedor): array
{
    if ($proveedor === 'google') {
        return [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/calendar.events',
        ];
    }

    // Microsoft (Graph).
    return [
        'openid',
        'profile',
        'offline_access',
        'User.Read',
        'Mail.Read',
        'Calendars.ReadWrite',
    ];
}

/**
 * Crea el proveedor OAuth de Google.
 */
function proveedorGoogle(): Google
{
    $cfg = config();
    return new Google([
        'clientId'     => $cfg['google']['client_id'],
        'clientSecret' => $cfg['google']['client_secret'],
        'redirectUri'  => urlCallback('google'),
        'accessType'   => 'offline', // necesario para recibir refresh_token
    ]);
}

/**
 * Crea el proveedor OAuth de Microsoft usando el endpoint v2.0.
 */
function proveedorMicrosoft(): Azure
{
    $cfg = config();
    $azure = new Azure([
        'clientId'                => $cfg['microsoft']['client_id'],
        'clientSecret'            => $cfg['microsoft']['client_secret'],
        'redirectUri'             => urlCallback('microsoft'),
        'tenant'                  => $cfg['microsoft']['tenant'] ?? 'common',
        'defaultEndPointVersion'  => Azure::ENDPOINT_VERSION_2_0,
    ]);

    // Apuntar a la API de Microsoft Graph.
    $azure->defaultEndPointVersion = Azure::ENDPOINT_VERSION_2_0;
    $azure->urlAPI = 'https://graph.microsoft.com/';

    return $azure;
}

/**
 * Devuelve la instancia de proveedor correcta según el nombre.
 *
 * @return Google|Azure
 */
function proveedorPara(string $proveedor)
{
    return $proveedor === 'google' ? proveedorGoogle() : proveedorMicrosoft();
}
