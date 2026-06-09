<?php
/**
 * Seguridad transversal: PIN opcional, sesión y CSRF.
 *
 * - iniciarSesion()    Arranca la sesión PHP (si no es CLI).
 * - hayPin()           ¿Hay PIN configurado?
 * - estaAutenticado()  ¿La sesión está autenticada (o no hace falta PIN)?
 * - exigirSesionWeb()  Redirige a login.php si hace falta PIN y no hay sesión.
 * - exigirSesionApi()  Responde 401 JSON si hace falta PIN y no hay sesión.
 * - tokenCsrf()        Devuelve (creando si hace falta) el token CSRF de sesión.
 * - validarCsrf()      Compara un token recibido con el de sesión.
 * - esCli()            ¿Estamos en línea de comandos?
 */

declare(strict_types=1);

require_once __DIR__ . '/proveedores.php';

/** ¿Ejecución por línea de comandos (cron)? */
function esCli(): bool
{
    return PHP_SAPI === 'cli';
}

/** Arranca la sesión salvo en CLI. */
function iniciarSesion(): void
{
    if (!esCli() && session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/** ¿Hay un PIN configurado? */
function hayPin(): bool
{
    return !empty(config()['pin_hash']);
}

/** ¿El usuario está autenticado? Si no hay PIN, siempre true. */
function estaAutenticado(): bool
{
    if (!hayPin()) {
        return true;
    }
    iniciarSesion();
    return !empty($_SESSION['autenticado']);
}

/** Marca la sesión como autenticada (tras validar el PIN). */
function autenticar(): void
{
    iniciarSesion();
    $_SESSION['autenticado'] = true;
}

/** Cierra la sesión. */
function cerrarSesion(): void
{
    iniciarSesion();
    $_SESSION = [];
    session_destroy();
}

/**
 * Verifica el PIN introducido contra el hash de config.
 */
function comprobarPin(string $pin): bool
{
    $hash = config()['pin_hash'] ?? '';
    return $hash !== '' && password_verify($pin, $hash);
}

/**
 * Páginas web: si hace falta PIN y no hay sesión, redirige al login.
 */
function exigirSesionWeb(): void
{
    if (esCli() || estaAutenticado()) {
        return;
    }
    header('Location: login.php');
    exit;
}

/**
 * API: si hace falta PIN y no hay sesión, responde 401 JSON y corta.
 */
function exigirSesionApi(): void
{
    if (estaAutenticado()) {
        return;
    }
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

/**
 * Devuelve el token CSRF de la sesión, creándolo la primera vez.
 */
function tokenCsrf(): string
{
    iniciarSesion();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Compara de forma segura un token recibido con el de sesión.
 */
function validarCsrf(?string $token): bool
{
    iniciarSesion();
    return !empty($_SESSION['csrf'])
        && is_string($token)
        && hash_equals($_SESSION['csrf'], $token);
}
