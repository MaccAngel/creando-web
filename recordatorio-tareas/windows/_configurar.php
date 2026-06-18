<?php
/**
 * Ayudante del instalador de Windows (lo invoca instalar.bat).
 *
 * Crea o actualiza config.php a partir de config.example.php usando las
 * variables de entorno que define el .bat:
 *   RT_APPKEY  -> clave de cifrado (base64 de 32 bytes)
 *   RT_DBUSER  -> usuario de MySQL
 *   RT_DBPASS  -> contraseña de MySQL
 *
 * No sobrescribe una app_key ya válida (solo rellena el placeholder o una vacía).
 * Pensado para ejecutarse desde la raíz del proyecto.
 */

declare(strict_types=1);

$raiz    = dirname(__DIR__);
$destino = $raiz . '/config.php';
$base    = is_file($destino) ? $destino : $raiz . '/config.example.php';

$c = file_get_contents($base);
if ($c === false) {
    fwrite(STDERR, "No se pudo leer la configuración base.\n");
    exit(1);
}

$appkey = (string) getenv('RT_APPKEY');
$dbuser = (string) getenv('RT_DBUSER');
$dbpass = (string) getenv('RT_DBPASS');

// Si el .bat no pasó una clave, la generamos aquí (evita comillas frágiles
// en el script de Windows).
if ($appkey === '') {
    $appkey = base64_encode(random_bytes(32));
}

// app_key: solo se fija si aún es el placeholder o está vacía.
if ($appkey !== '' && preg_match("/'app_key'\\s*=>\\s*'(GENERA_[^']*|)'/", $c)) {
    $c = preg_replace(
        "/('app_key'\\s*=>\\s*')[^']*(')/",
        '${1}' . $appkey . '${2}',
        $c,
        1
    );
}

// Usuario y contraseña de la base de datos.
$c = preg_replace(
    "/('usuario'\\s*=>\\s*')[^']*(')/",
    '${1}' . addcslashes($dbuser, "'\\") . '${2}',
    $c,
    1
);
$c = preg_replace(
    "/('clave'\\s*=>\\s*')[^']*(')/",
    '${1}' . addcslashes($dbpass, "'\\") . '${2}',
    $c,
    1
);

if (file_put_contents($destino, $c) === false) {
    fwrite(STDERR, "No se pudo escribir config.php.\n");
    exit(1);
}

echo "config.php preparado.\n";
