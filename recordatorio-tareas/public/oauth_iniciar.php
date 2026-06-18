<?php

/**
 * Inicio del flujo OAuth.
 *
 * Recibe ?p=google|microsoft, construye la URL de autorización con los scopes
 * adecuados, guarda el `state` en sesión (protección CSRF) y redirige al
 * proveedor.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/proveedores.php';

exigirSesionWeb();
iniciarSesion();

$p = $_GET['p'] ?? '';
if (!in_array($p, ['google', 'microsoft'], true)) {
    http_response_code(400);
    exit('Proveedor no válido.');
}

// Aviso claro si las credenciales OAuth aún no están configuradas (evita el
// error críptico del proveedor con valores de ejemplo).
$cfg = config();
$clientId = $cfg[$p]['client_id'] ?? '';
if ($clientId === '' || str_starts_with($clientId, 'TU_')) {
    $nombre = $p === 'google' ? 'Google (Gmail)' : 'Microsoft (Outlook)';
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    exit(
        '<p style="font-family:system-ui;max-width:42rem;margin:3rem auto;line-height:1.6">'
        . '<strong>Aún no has configurado las credenciales de ' . htmlspecialchars($nombre) . '.</strong><br>'
        . 'Edita <code>config.php</code> y rellena <code>client_id</code> y <code>client_secret</code> '
        . 'en la sección <code>\'' . htmlspecialchars($p) . '\'</code>. '
        . 'Consulta los pasos en el README. '
        . 'Mientras tanto puedes usar la app con tareas manuales. '
        . '<br><br><a href="index.php">&larr; Volver</a></p>'
    );
}

$proveedor = proveedorPara($p);

// Opciones de autorización por proveedor.
$opciones = ['scope' => scopesPara($p)];
if ($p === 'google') {
    // Necesario para recibir refresh_token en Google.
    $opciones['access_type'] = 'offline';
    $opciones['prompt'] = 'consent';
}

$url = $proveedor->getAuthorizationUrl($opciones);

// Guardar state para validarlo en el callback (anti-CSRF).
$_SESSION['oauth_state'] = $proveedor->getState();
$_SESSION['oauth_proveedor'] = $p;

header('Location: ' . $url);
exit;
