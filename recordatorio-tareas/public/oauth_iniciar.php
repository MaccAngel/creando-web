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
