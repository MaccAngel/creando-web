<?php

/**
 * Conexión PDO única a MySQL/MariaDB.
 *
 * db() devuelve siempre la misma instancia PDO (patrón singleton sencillo),
 * configurada para lanzar excepciones y usar utf8mb4. Todas las consultas
 * de la app deben usar sentencias preparadas.
 */

declare(strict_types=1);

require_once __DIR__ . '/proveedores.php';

/**
 * Devuelve la conexión PDO compartida.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $cfg = config()['db'];

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            $cfg['nombre']
        );

        $pdo = new PDO($dsn, $cfg['usuario'], $cfg['clave'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}
