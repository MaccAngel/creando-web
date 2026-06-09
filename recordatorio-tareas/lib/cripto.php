<?php

/**
 * Cifrado simétrico de tokens OAuth con libsodium.
 *
 * Usamos sodium_crypto_secretbox (XSalsa20-Poly1305). El nonce se genera al
 * azar en cada cifrado y se antepone al texto cifrado; el resultado completo
 * se codifica en base64 para guardarlo cómodamente en la base de datos.
 *
 * La clave se toma de config['app_key'] (base64 de 32 bytes).
 */

declare(strict_types=1);

require_once __DIR__ . '/proveedores.php';

/**
 * Obtiene la clave binaria de 32 bytes a partir de config['app_key'].
 */
function claveCripto(): string
{
    $cfg = config();
    $clave = base64_decode($cfg['app_key'] ?? '', true);

    if ($clave === false || strlen($clave) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException(
            'app_key inválida. Debe ser base64 de 32 bytes. Genérala con: '
            . 'php -r "echo base64_encode(random_bytes(32));"'
        );
    }

    return $clave;
}

/**
 * Cifra un texto plano y devuelve base64( nonce || cifrado ).
 */
function cifrar(string $textoPlano): string
{
    $clave = claveCripto();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cifrado = sodium_crypto_secretbox($textoPlano, $nonce, $clave);

    return base64_encode($nonce . $cifrado);
}

/**
 * Descifra un valor producido por cifrar(). Lanza excepción si está corrupto.
 */
function descifrar(string $base64): string
{
    $clave = claveCripto();
    $datos = base64_decode($base64, true);

    if ($datos === false || strlen($datos) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        throw new RuntimeException('Token cifrado inválido o corrupto.');
    }

    $nonce   = substr($datos, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cifrado = substr($datos, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

    $plano = sodium_crypto_secretbox_open($cifrado, $nonce, $clave);
    if ($plano === false) {
        throw new RuntimeException('No se pudo descifrar el token (clave incorrecta o datos alterados).');
    }

    return $plano;
}
