<?php
/**
 * Panel (dashboard) con estadísticas de las tareas.
 *
 * Renderizado en servidor (como index.php). Muestra tarjetas de resumen,
 * un anillo de progreso, desgloses por prioridad / fuente / cuenta y los
 * próximos vencimientos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/fechas.php';
require_once __DIR__ . '/../lib/estadisticas.php';

exigirSesionWeb();

$cfg = config();
$pdo = db();
$avisoDias = (int) ($cfg['aviso_dias'] ?? 2);
$hoy = new DateTime('today');

// Datos para los agregados.
$tareas = $pdo->query(
    'SELECT id, titulo, completada, prioridad, fuente, fecha_limite, cuenta_id
       FROM tareas'
)->fetchAll();

$stats = resumirTareas($tareas, $hoy, $avisoDias);

// Desglose por cuenta (requiere unir con la tabla cuentas).
$porCuenta = $pdo->query(
    'SELECT c.email, c.proveedor, COUNT(t.id) AS total
       FROM cuentas c
       LEFT JOIN tareas t ON t.cuenta_id = c.id
      GROUP BY c.id, c.email, c.proveedor
      ORDER BY total DESC'
)->fetchAll();

/** Escapar HTML. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Porcentaje seguro (evita división por cero) para las barras. */
function pct(int $parte, int $total): float
{
    return $total > 0 ? round($parte / $total * 100, 1) : 0.0;
}

// Máximo de tareas en una cuenta (para escalar las barras por cuenta).
$maxCuenta = 0;
foreach ($porCuenta as $c) {
    $maxCuenta = max($maxCuenta, (int) $c['total']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel · Recordatorio de Tareas</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="cabecera">
        <h1>📊 Panel</h1>
        <nav class="acciones">
            <a class="btn" href="index.php">← Tareas</a>
            <a class="btn" href="filtros.php">⚙️ Filtros</a>
        </nav>
    </header>

    <main class="contenido">

        <!-- Tarjetas de resumen -->
        <section class="kpis">
            <div class="kpi">
                <span class="kpi-num"><?= $stats['total'] ?></span>
                <span class="kpi-lbl">Tareas totales</span>
            </div>
            <div class="kpi">
                <span class="kpi-num"><?= $stats['pendientes'] ?></span>
                <span class="kpi-lbl">Pendientes</span>
            </div>
            <div class="kpi kpi-ok">
                <span class="kpi-num"><?= $stats['completadas'] ?></span>
                <span class="kpi-lbl">Completadas</span>
            </div>
            <div class="kpi <?= $stats['vencidas'] > 0 ? 'kpi-alerta' : '' ?>">
                <span class="kpi-num"><?= $stats['vencidas'] ?></span>
                <span class="kpi-lbl">Vencidas</span>
            </div>
            <div class="kpi <?= $stats['hoy'] > 0 ? 'kpi-aviso' : '' ?>">
                <span class="kpi-num"><?= $stats['hoy'] ?></span>
                <span class="kpi-lbl">Vencen hoy</span>
            </div>
            <div class="kpi">
                <span class="kpi-num"><?= $stats['pronto'] ?></span>
                <span class="kpi-lbl">Vencen pronto</span>
            </div>
        </section>

        <div class="rejilla">

            <!-- Progreso global (anillo) -->
            <section class="tarjeta">
                <h2>Progreso</h2>
                <div class="progreso">
                    <div class="anillo"
                         style="--pct: <?= $stats['porcentaje'] ?>"
                         role="img"
                         aria-label="<?= $stats['porcentaje'] ?>% completado">
                        <span class="anillo-num"><?= $stats['porcentaje'] ?>%</span>
                    </div>
                    <p class="ayuda">
                        <?= $stats['completadas'] ?> de <?= $stats['total'] ?> tareas completadas.
                    </p>
                </div>
            </section>

            <!-- Pendientes por prioridad -->
            <section class="tarjeta">
                <h2>Pendientes por prioridad</h2>
                <?php if ($stats['pendientes'] === 0): ?>
                    <p class="vacio">No hay tareas pendientes. 🎉</p>
                <?php else: ?>
                    <?php foreach (['alta', 'media', 'baja'] as $prio): ?>
                        <?php $n = $stats['prioridad'][$prio]; ?>
                        <div class="barra-fila">
                            <span class="barra-etq">
                                <span class="prioridad prioridad-<?= $prio ?>"></span>
                                <?= ucfirst($prio) ?>
                            </span>
                            <div class="barra">
                                <div class="barra-relleno barra-<?= $prio ?>"
                                     style="width: <?= pct($n, $stats['pendientes']) ?>%"></div>
                            </div>
                            <span class="barra-num"><?= $n ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <!-- Origen de las tareas -->
            <section class="tarjeta">
                <h2>Origen de las tareas</h2>
                <?php $tot = $stats['total']; ?>
                <div class="barra-doble">
                    <div class="seg seg-manual"
                         style="width: <?= pct($stats['fuente']['manual'], $tot) ?>%"
                         title="Manuales"></div>
                    <div class="seg seg-email"
                         style="width: <?= pct($stats['fuente']['email'], $tot) ?>%"
                         title="De correo"></div>
                </div>
                <ul class="leyenda">
                    <li><span class="punto seg-manual"></span>
                        Manuales: <strong><?= $stats['fuente']['manual'] ?></strong></li>
                    <li><span class="punto seg-email"></span>
                        De correo: <strong><?= $stats['fuente']['email'] ?></strong></li>
                </ul>
            </section>

            <!-- Tareas por cuenta -->
            <section class="tarjeta">
                <h2>Tareas por cuenta</h2>
                <?php if (!$porCuenta): ?>
                    <p class="vacio">No hay cuentas conectadas.</p>
                <?php else: ?>
                    <?php foreach ($porCuenta as $c): ?>
                        <div class="barra-fila">
                            <span class="barra-etq">
                                <span class="proveedor proveedor-<?= e($c['proveedor']) ?>">
                                    <?= $c['proveedor'] === 'google' ? 'Gmail' : 'Outlook' ?>
                                </span>
                                <span class="cuenta-email"><?= e($c['email']) ?></span>
                            </span>
                            <div class="barra">
                                <div class="barra-relleno barra-cuenta"
                                     style="width: <?= pct((int) $c['total'], $maxCuenta) ?>%"></div>
                            </div>
                            <span class="barra-num"><?= (int) $c['total'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

        </div>

        <!-- Próximos vencimientos -->
        <section class="tarjeta">
            <h2>Próximos vencimientos</h2>
            <?php
            // Mostrar las próximas 6 tareas pendientes con fecha.
            $proximas = array_slice($stats['proximas'], 0, 6);
?>
            <?php if (!$proximas): ?>
                <p class="vacio">No hay tareas pendientes con fecha límite.</p>
            <?php else: ?>
                <ul class="lista-proximas">
                    <?php foreach ($proximas as $t): ?>
                        <?php $est = estadoVencimiento($t['fecha_limite'], $hoy, $avisoDias); ?>
                        <li>
                            <span class="prioridad prioridad-<?= e($t['prioridad']) ?>"></span>
                            <span class="proxima-titulo"><?= e($t['titulo']) ?></span>
                            <span class="proxima-fecha">📅 <?= e($t['fecha_limite']) ?></span>
                            <?php if ($est): ?>
                                <span class="badge badge-<?= e($est[0]) ?>"><?= e($est[1]) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </main>
</body>
</html>
