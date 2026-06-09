<?php

/**
 * Sincronización por web o por CLI (cron).
 *
 * - Por CLI (php public/sincronizar.php): no exige PIN. Muestra un resumen en texto.
 * - Por web: exige sesión si hay PIN y redirige a index.php con el total de nuevas.
 *
 * Para programarlo con cron, por ejemplo cada 5 minutos:
 *   * / 5 * * * * php /ruta/recordatorio-tareas/public/sincronizar.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sync.php';

if (esCli()) {
    $resultado = sincronizarCuentas();
    echo "Tareas nuevas: {$resultado['nuevas']}\n";
    foreach ($resultado['errores'] as $err) {
        echo "  [aviso] $err\n";
    }
    exit(0);
}

// Acceso web.
exigirSesionWeb();
$resultado = sincronizarCuentas();
header('Location: index.php?nuevas=' . (int) $resultado['nuevas']);
exit;
