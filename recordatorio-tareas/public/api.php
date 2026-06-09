<?php
/**
 * API JSON para el frontend.
 *
 * Todas las acciones llegan por POST con cuerpo JSON y la cabecera X-CSRF.
 * Excepción: la sincronización automática puede invocarse por GET
 * (?accion=sincronizar) desde app.js, ya que es idempotente.
 *
 * Acciones:
 *   crear        { titulo, notas?, prioridad?, fecha_limite? }
 *   editar       { id, titulo, notas?, prioridad?, fecha_limite? }
 *   completar    { id }                  (alterna completada)
 *   borrar       { id }
 *   correos      -                       (lista correos destacados de todas las cuentas)
 *   vincular     { id, cuenta_id, email_message_id, email_asunto, email_remitente, email_enlace }
 *   sincronizar  -                       (importa correos nuevos)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/correo.php';
require_once __DIR__ . '/../lib/agenda.php';
require_once __DIR__ . '/../lib/sync.php';

exigirSesionApi();

/** Responde JSON y termina. */
function responder(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($datos);
    exit;
}

// La acción puede venir por GET (sincronización auto) o en el JSON del POST.
$accion = $_GET['accion'] ?? '';
$cuerpo = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $cuerpo = json_decode($raw, true) ?? [];
    $accion = $cuerpo['accion'] ?? $accion;

    // Validar CSRF en cualquier POST.
    if (!validarCsrf($_SERVER['HTTP_X_CSRF'] ?? null)) {
        responder(['ok' => false, 'error' => 'CSRF no válido'], 403);
    }
}

$pdo = db();

try {
    switch ($accion) {

        // -------------------------------------------------------------
        case 'crear':
            $titulo = trim($cuerpo['titulo'] ?? '');
            if ($titulo === '') {
                responder(['ok' => false, 'error' => 'El título es obligatorio'], 422);
            }
            $st = $pdo->prepare(
                'INSERT INTO tareas (titulo, notas, prioridad, fecha_limite, fuente)
                 VALUES (?, ?, ?, ?, "manual")'
            );
            $st->execute([
                $titulo,
                trim($cuerpo['notas'] ?? '') ?: null,
                $cuerpo['prioridad'] ?? 'media',
                ($cuerpo['fecha_limite'] ?? '') ?: null,
            ]);
            $id = (int) $pdo->lastInsertId();

            // Crear evento de agenda (sin romper la creación si falla).
            try {
                crearEventoTarea($id);
            } catch (\Throwable $e) {
                // Silencioso: la tarea ya está creada.
            }
            responder(['ok' => true, 'id' => $id]);
            break;

        // -------------------------------------------------------------
        case 'editar':
            $id = (int) ($cuerpo['id'] ?? 0);
            $titulo = trim($cuerpo['titulo'] ?? '');
            if ($id <= 0 || $titulo === '') {
                responder(['ok' => false, 'error' => 'Datos incompletos'], 422);
            }
            $st = $pdo->prepare(
                'UPDATE tareas SET titulo = ?, notas = ?, prioridad = ?, fecha_limite = ?
                  WHERE id = ?'
            );
            $st->execute([
                $titulo,
                trim($cuerpo['notas'] ?? '') ?: null,
                $cuerpo['prioridad'] ?? 'media',
                ($cuerpo['fecha_limite'] ?? '') ?: null,
                $id,
            ]);

            // Actualizar (o crear) el evento de agenda asociado.
            try {
                actualizarEventoTarea($id);
            } catch (\Throwable $e) {
                // La edición se conserva aunque falle la agenda.
            }
            responder(['ok' => true]);
            break;

        // -------------------------------------------------------------
        case 'completar':
            $id = (int) ($cuerpo['id'] ?? 0);
            $st = $pdo->prepare('UPDATE tareas SET completada = 1 - completada WHERE id = ?');
            $st->execute([$id]);
            responder(['ok' => true]);
            break;

        // -------------------------------------------------------------
        case 'borrar':
            $id = (int) ($cuerpo['id'] ?? 0);
            $st = $pdo->prepare('DELETE FROM tareas WHERE id = ?');
            $st->execute([$id]);
            responder(['ok' => true]);
            break;

        // -------------------------------------------------------------
        // Lista correos destacados de todas las cuentas (para vincular).
        case 'correos':
            $cuentas = $pdo->query('SELECT * FROM cuentas WHERE requiere_reconexion = 0')->fetchAll();
            $filtros = filtrosActivos();
            $salida = [];
            foreach ($cuentas as $cuenta) {
                try {
                    foreach (leerCorreos($cuenta) as $correo) {
                        if (esNoDeseado($correo, $filtros)) {
                            continue;
                        }
                        $correo['cuenta_id'] = (int) $cuenta['id'];
                        $correo['cuenta_email'] = $cuenta['email'];
                        $salida[] = $correo;
                    }
                } catch (\Throwable $e) {
                    // Ignorar cuentas con problemas; el resto sigue funcionando.
                }
            }
            responder(['ok' => true, 'correos' => $salida]);
            break;

        // -------------------------------------------------------------
        // Asocia un correo a una tarea existente.
        case 'vincular':
            $id = (int) ($cuerpo['id'] ?? 0);
            if ($id <= 0) {
                responder(['ok' => false, 'error' => 'Tarea no válida'], 422);
            }
            $st = $pdo->prepare(
                'UPDATE tareas
                    SET cuenta_id = ?, email_message_id = ?, email_asunto = ?,
                        email_remitente = ?, email_enlace = ?, fuente = "email"
                  WHERE id = ?'
            );
            $st->execute([
                (int) ($cuerpo['cuenta_id'] ?? 0) ?: null,
                $cuerpo['email_message_id'] ?? null,
                $cuerpo['email_asunto'] ?? null,
                $cuerpo['email_remitente'] ?? null,
                $cuerpo['email_enlace'] ?? null,
                $id,
            ]);
            responder(['ok' => true]);
            break;

        // -------------------------------------------------------------
        case 'sincronizar':
            $resultado = sincronizarCuentas();
            responder([
                'ok'      => true,
                'nuevas'  => $resultado['nuevas'],
                'errores' => $resultado['errores'],
            ]);
            break;

        // -------------------------------------------------------------
        default:
            responder(['ok' => false, 'error' => 'Acción desconocida'], 404);
    }
} catch (\Throwable $e) {
    responder(['ok' => false, 'error' => $e->getMessage()], 500);
}
