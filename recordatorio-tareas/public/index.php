<?php
/**
 * UI principal: lista de tareas, cuentas conectadas y avisos de vencimiento.
 *
 * El renderizado de la lista es server-side (PHP); las acciones (crear, editar,
 * completar, borrar, vincular, sincronizar) las gestiona app.js contra api.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/fechas.php';

exigirSesionWeb();

$cfg  = config();
$csrf = tokenCsrf();
$pdo  = db();

$cuentas = $pdo->query('SELECT * FROM cuentas ORDER BY proveedor, email')->fetchAll();
$tareas  = $pdo->query(
    'SELECT t.*, c.email AS cuenta_email, c.proveedor AS cuenta_proveedor
       FROM tareas t
       LEFT JOIN cuentas c ON c.id = t.cuenta_id
      ORDER BY t.completada ASC,
               FIELD(t.prioridad, "alta", "media", "baja"),
               (t.fecha_limite IS NULL), t.fecha_limite ASC,
               t.creada_en DESC'
)->fetchAll();

$avisoDias = (int) ($cfg['aviso_dias'] ?? 2);
$hoy = new DateTime('today');

/** Escapar HTML. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// estadoVencimiento() vive en lib/fechas.php (reutilizable y testeable).

// Contadores para el aviso superior.
$totalVencidas = 0;
$totalHoy = 0;
foreach ($tareas as $t) {
    if ($t['completada']) {
        continue;
    }
    $est = estadoVencimiento($t['fecha_limite'], $hoy, $avisoDias);
    if ($est && $est[0] === 'vencida') {
        $totalVencidas++;
    } elseif ($est && $est[0] === 'hoy') {
        $totalHoy++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio de Tareas</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body
    data-csrf="<?= e($csrf) ?>"
    data-auto-sync="<?= (int) ($cfg['auto_sync_min'] ?? 0) ?>">

    <header class="cabecera">
        <h1>📋 Recordatorio de Tareas</h1>
        <nav class="acciones">
            <button id="btn-nueva" class="btn btn-primario">＋ Nueva tarea</button>
            <button id="btn-sincronizar" class="btn">🔄 Sincronizar</button>
            <a class="btn" href="dashboard.php">📊 Panel</a>
            <a class="btn" href="filtros.php">⚙️ Filtros</a>
            <a class="btn" href="oauth_iniciar.php?p=google">＋ Gmail</a>
            <a class="btn" href="oauth_iniciar.php?p=microsoft">＋ Outlook</a>
            <?php if (hayPin()): ?>
                <a class="btn" href="login.php?salir=1">Salir</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="contenido">

        <!-- Aviso de vencimientos -->
        <?php if ($totalVencidas > 0 || $totalHoy > 0): ?>
            <p class="aviso aviso-vencimiento">
                <?php if ($totalVencidas > 0): ?>
                    <strong><?= $totalVencidas ?></strong> vencida(s)
                <?php endif; ?>
                <?php if ($totalVencidas > 0 && $totalHoy > 0): ?> · <?php endif; ?>
                <?php if ($totalHoy > 0): ?>
                    <strong><?= $totalHoy ?></strong> vence(n) hoy
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <!-- Cuentas conectadas -->
        <section class="tarjeta cuentas">
            <h2>Cuentas conectadas</h2>
            <?php if (!$cuentas): ?>
                <p class="vacio">Aún no hay cuentas. Conecta tu Gmail u Outlook desde la cabecera.</p>
            <?php else: ?>
                <ul class="lista-cuentas">
                    <?php foreach ($cuentas as $c): ?>
                        <li>
                            <span class="proveedor proveedor-<?= e($c['proveedor']) ?>">
                                <?= $c['proveedor'] === 'google' ? 'Gmail' : 'Outlook' ?>
                            </span>
                            <?= e($c['email']) ?>
                            <?php if ($c['requiere_reconexion']): ?>
                                <a class="reconectar"
                                   href="oauth_iniciar.php?p=<?= e($c['proveedor']) ?>">
                                   ⚠ Reconectar
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Lista de tareas -->
        <section class="tarjeta">
            <h2>Tareas</h2>
            <?php if (!$tareas): ?>
                <p class="vacio">No tienes tareas todavía. Crea una nueva o sincroniza tus correos.</p>
            <?php else: ?>
                <ul class="lista-tareas">
                    <?php foreach ($tareas as $t): ?>
                        <?php $est = estadoVencimiento($t['fecha_limite'], $hoy, $avisoDias); ?>
                        <li class="tarea <?= $t['completada'] ? 'completada' : '' ?>"
                            data-id="<?= (int) $t['id'] ?>">

                            <input type="checkbox" class="check-completar"
                                   <?= $t['completada'] ? 'checked' : '' ?>>

                            <div class="tarea-cuerpo">
                                <div class="tarea-titulo">
                                    <span class="prioridad prioridad-<?= e($t['prioridad']) ?>"
                                          title="Prioridad <?= e($t['prioridad']) ?>"></span>
                                    <span class="texto"><?= e($t['titulo']) ?></span>

                                    <?php if ($est && !$t['completada']): ?>
                                        <span class="badge badge-<?= e($est[0]) ?>"><?= e($est[1]) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($t['notas'])): ?>
                                    <p class="tarea-notas"><?= nl2br(e($t['notas'])) ?></p>
                                <?php endif; ?>

                                <div class="tarea-meta">
                                    <?php if (!empty($t['fecha_limite'])): ?>
                                        <span class="meta">📅 <?= e($t['fecha_limite']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($t['email_enlace'])): ?>
                                        <a class="meta enlace" target="_blank"
                                           href="<?= e($t['email_enlace']) ?>"
                                           title="<?= e($t['email_remitente']) ?>">✉️ correo</a>
                                    <?php endif; ?>
                                    <?php if (!empty($t['evento_enlace'])): ?>
                                        <a class="meta enlace" target="_blank"
                                           href="<?= e($t['evento_enlace']) ?>">📅 agenda</a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="tarea-acciones">
                                <button class="btn-icono btn-vincular" title="Vincular correo">🔗</button>
                                <button class="btn-icono btn-editar" title="Editar"
                                        data-titulo="<?= e($t['titulo']) ?>"
                                        data-notas="<?= e($t['notas']) ?>"
                                        data-prioridad="<?= e($t['prioridad']) ?>"
                                        data-fecha="<?= e($t['fecha_limite']) ?>">✏️</button>
                                <button class="btn-icono btn-borrar" title="Borrar">🗑️</button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>

    <!-- Modal: crear / editar tarea -->
    <div id="modal-tarea" class="modal oculto">
        <div class="modal-caja">
            <h3 id="modal-tarea-titulo">Nueva tarea</h3>
            <form id="form-tarea">
                <input type="hidden" name="id" value="">
                <label>Título
                    <input type="text" name="titulo" required maxlength="255">
                </label>
                <label>Notas
                    <textarea name="notas" rows="3"></textarea>
                </label>
                <div class="dos-columnas">
                    <label>Prioridad
                        <select name="prioridad">
                            <option value="baja">Baja</option>
                            <option value="media" selected>Media</option>
                            <option value="alta">Alta</option>
                        </select>
                    </label>
                    <label>Fecha límite
                        <input type="date" name="fecha_limite">
                    </label>
                </div>
                <div class="modal-acciones">
                    <button type="button" class="btn cerrar-modal">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: vincular correo -->
    <div id="modal-vincular" class="modal oculto">
        <div class="modal-caja">
            <h3>Vincular correo a la tarea</h3>
            <p class="ayuda">Selecciona un correo destacado para asociarlo.</p>
            <div id="lista-correos" class="lista-correos">
                <p class="vacio">Cargando correos…</p>
            </div>
            <div class="modal-acciones">
                <button type="button" class="btn cerrar-modal">Cerrar</button>
            </div>
        </div>
    </div>

    <script src="assets/app.js"></script>
</body>
</html>
