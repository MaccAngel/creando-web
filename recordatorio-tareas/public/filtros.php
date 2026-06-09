<?php
/**
 * Panel web de filtros y reglas.
 *
 * Permite añadir/borrar:
 *  - Filtros de correo no deseado (por remitente o por palabra).
 *  - Reglas de prioridad (si el correo contiene X -> prioridad Y).
 *
 * Todos los formularios usan POST con token CSRF en campo oculto.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

exigirSesionWeb();
iniciarSesion();

$pdo = db();
$aviso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('CSRF no válido.');
    }

    $accion = $_POST['accion'] ?? '';

    switch ($accion) {
        case 'filtro_add':
            $tipo  = $_POST['tipo'] ?? '';
            $valor = trim($_POST['valor'] ?? '');
            if (in_array($tipo, ['remitente', 'palabra'], true) && $valor !== '') {
                $st = $pdo->prepare('INSERT INTO filtros (tipo, valor) VALUES (?, ?)');
                $st->execute([$tipo, $valor]);
                $aviso = 'Filtro añadido.';
            }
            break;

        case 'filtro_del':
            $st = $pdo->prepare('DELETE FROM filtros WHERE id = ?');
            $st->execute([(int) ($_POST['id'] ?? 0)]);
            $aviso = 'Filtro borrado.';
            break;

        case 'regla_add':
            $contiene  = trim($_POST['contiene'] ?? '');
            $prioridad = $_POST['prioridad'] ?? 'media';
            if ($contiene !== '' && in_array($prioridad, ['baja', 'media', 'alta'], true)) {
                $st = $pdo->prepare('INSERT INTO reglas (contiene, prioridad) VALUES (?, ?)');
                $st->execute([$contiene, $prioridad]);
                $aviso = 'Regla añadida.';
            }
            break;

        case 'regla_del':
            $st = $pdo->prepare('DELETE FROM reglas WHERE id = ?');
            $st->execute([(int) ($_POST['id'] ?? 0)]);
            $aviso = 'Regla borrada.';
            break;
    }

    // Patrón Post/Redirect/Get para evitar reenvíos al recargar.
    header('Location: filtros.php?ok=' . urlencode($aviso));
    exit;
}

$filtros = $pdo->query('SELECT * FROM filtros ORDER BY tipo, valor')->fetchAll();
$reglas  = $pdo->query('SELECT * FROM reglas ORDER BY contiene')->fetchAll();
$csrf    = tokenCsrf();

/** Atajo para escapar HTML. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filtros y reglas · Recordatorio de Tareas</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="cabecera">
        <h1>Filtros y reglas</h1>
        <nav class="acciones">
            <a class="btn" href="index.php">← Volver a tareas</a>
        </nav>
    </header>

    <main class="contenido">
        <?php if (!empty($_GET['ok'])): ?>
            <p class="aviso aviso-ok"><?= e($_GET['ok']) ?></p>
        <?php endif; ?>

        <div class="rejilla">

            <!-- ---------------- Filtros ---------------- -->
            <section class="tarjeta">
                <h2>Filtros de correo no deseado</h2>
                <p class="ayuda">Los correos cuyo remitente o asunto contengan estos
                   valores se descartan al sincronizar.</p>

                <form method="post" class="formulario-linea">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="accion" value="filtro_add">
                    <select name="tipo">
                        <option value="remitente">Remitente</option>
                        <option value="palabra">Palabra</option>
                    </select>
                    <input type="text" name="valor" placeholder="p. ej. noreply@ o 'oferta'" required>
                    <button type="submit" class="btn btn-primario">Añadir</button>
                </form>

                <ul class="lista-simple">
                    <?php foreach ($filtros as $f): ?>
                        <li>
                            <span class="etiqueta"><?= e($f['tipo']) ?></span>
                            <?= e($f['valor']) ?>
                            <form method="post" class="en-linea">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="accion" value="filtro_del">
                                <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                <button type="submit" class="btn-icono" title="Borrar">🗑️</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$filtros): ?>
                        <li class="vacio">No hay filtros en la base de datos.</li>
                    <?php endif; ?>
                </ul>
            </section>

            <!-- ---------------- Reglas ---------------- -->
            <section class="tarjeta">
                <h2>Reglas de prioridad</h2>
                <p class="ayuda">Si el correo contiene el texto indicado, la tarea
                   importada tomará esa prioridad.</p>

                <form method="post" class="formulario-linea">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="accion" value="regla_add">
                    <input type="text" name="contiene" placeholder="contiene… (p. ej. 'factura')" required>
                    <select name="prioridad">
                        <option value="baja">Baja</option>
                        <option value="media" selected>Media</option>
                        <option value="alta">Alta</option>
                    </select>
                    <button type="submit" class="btn btn-primario">Añadir</button>
                </form>

                <ul class="lista-simple">
                    <?php foreach ($reglas as $r): ?>
                        <li>
                            <span class="prioridad prioridad-<?= e($r['prioridad']) ?>">
                                <?= e($r['prioridad']) ?>
                            </span>
                            contiene «<?= e($r['contiene']) ?>»
                            <form method="post" class="en-linea">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="accion" value="regla_del">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="btn-icono" title="Borrar">🗑️</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$reglas): ?>
                        <li class="vacio">No hay reglas definidas.</li>
                    <?php endif; ?>
                </ul>
            </section>

        </div>
    </main>
</body>
</html>
