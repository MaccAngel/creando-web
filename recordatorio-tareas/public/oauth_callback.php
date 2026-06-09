<?php
/**
 * Callback del flujo OAuth.
 *
 * Valida el `state`, intercambia el code por tokens, obtiene el email de la
 * cuenta y guarda/actualiza la fila en `cuentas` con los tokens cifrados.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/cripto.php';
require_once __DIR__ . '/../lib/correo.php';
require_once __DIR__ . '/../lib/proveedores.php';

exigirSesionWeb();
iniciarSesion();

$p = $_GET['p'] ?? '';
if (!in_array($p, ['google', 'microsoft'], true)) {
    http_response_code(400);
    exit('Proveedor no válido.');
}

// El proveedor puede devolver un error (p.ej. el usuario canceló).
if (isset($_GET['error'])) {
    exit('Autorización cancelada o con error: ' . htmlspecialchars($_GET['error']));
}

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';

// Validación de state (CSRF).
if ($code === '' || $state === '' || !hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
    http_response_code(400);
    exit('Parámetro state no válido. Reinicia la conexión.');
}

$proveedor = proveedorPara($p);

try {
    // Intercambiar code por tokens.
    $token = $proveedor->getAccessToken('authorization_code', ['code' => $code]);

    // Obtener el email de la cuenta.
    if ($p === 'google') {
        $duenyo = $proveedor->getResourceOwner($token);
        $email = $duenyo->getEmail();
    } else {
        $me = apiGet($token->getToken(), 'https://graph.microsoft.com/v1.0/me');
        $email = $me['mail'] ?? $me['userPrincipalName'] ?? '';
    }

    if ($email === '') {
        throw new RuntimeException('No se pudo obtener el email de la cuenta.');
    }

    // Cifrar tokens.
    $accessCifrado  = cifrar($token->getToken());
    $refresh        = $token->getRefreshToken();
    $refreshCifrado = $refresh ? cifrar($refresh) : null;
    $expira         = $token->getExpires() ? date('Y-m-d H:i:s', $token->getExpires()) : null;

    // Guardar/actualizar la cuenta.
    $st = db()->prepare(
        'INSERT INTO cuentas (proveedor, email, access_token, refresh_token, expira_en, requiere_reconexion)
         VALUES (?, ?, ?, ?, ?, 0)
         ON DUPLICATE KEY UPDATE
            access_token = VALUES(access_token),
            refresh_token = COALESCE(VALUES(refresh_token), refresh_token),
            expira_en = VALUES(expira_en),
            requiere_reconexion = 0'
    );
    $st->execute([$p, $email, $accessCifrado, $refreshCifrado, $expira]);

    unset($_SESSION['oauth_state'], $_SESSION['oauth_proveedor']);

    header('Location: index.php?conectado=' . urlencode($email));
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    exit('Error al conectar la cuenta: ' . htmlspecialchars($e->getMessage()));
}
