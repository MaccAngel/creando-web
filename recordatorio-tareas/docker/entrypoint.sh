#!/bin/sh
# Punto de entrada del contenedor de la aplicación.
#
# 1. Genera una APP_KEY temporal si no se ha definido una.
# 2. Espera a que la base de datos acepte conexiones.
# 3. Arranca el servidor embebido de PHP.
set -e

# 1. Clave de cifrado (efímera si no se pasa por entorno).
if [ -z "$APP_KEY" ]; then
    APP_KEY="$(php -r 'echo base64_encode(random_bytes(32));')"
    export APP_KEY
    echo "[entrypoint] APP_KEY generada para esta sesión."
fi

# 2. Esperar a la base de datos (hasta ~60s).
echo "[entrypoint] Esperando a la base de datos en ${DB_HOST:-db}..."
intentos=0
until php -r '
    $h = getenv("DB_HOST") ?: "db";
    $u = getenv("DB_USER") ?: "root";
    $p = getenv("DB_PASS") ?: "root";
    try { new PDO("mysql:host=$h", $u, $p); exit(0); }
    catch (Throwable $e) { exit(1); }
'; do
    intentos=$((intentos + 1))
    if [ "$intentos" -ge 30 ]; then
        echo "[entrypoint] La base de datos no respondió a tiempo." >&2
        exit 1
    fi
    sleep 2
done
echo "[entrypoint] Base de datos lista."

# 3. Arrancar el servidor web.
echo "[entrypoint] Arrancando en http://0.0.0.0:8000"
exec php -S 0.0.0.0:8000 -t public
