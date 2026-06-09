<?php
/**
 * Sincronización reutilizable y reglas de prioridad.
 *
 * - sincronizarCuentas()                 Recorre todas las cuentas e importa correos nuevos.
 * - prioridadPorReglas($asunto,$remit)   Calcula prioridad según la tabla `reglas`.
 *
 * La sincronización es incremental: para cada cuenta obtiene los IDs ya
 * importados y los pasa como "omitir" a leerCorreos(), de modo que no se
 * relee ni reprocesa nada ya guardado. Cada correo nuevo genera una tarea
 * (INSERT IGNORE, respaldado por el índice único cuenta_id+email_message_id)
 * y su evento de agenda.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/correo.php';
require_once __DIR__ . '/agenda.php';

/**
 * Devuelve la prioridad asignada por la primera regla que coincida,
 * o 'media' por defecto.
 */
function prioridadPorReglas(string $asunto, string $remitente): string
{
    $texto = mb_strtolower($asunto . ' ' . $remitente);

    try {
        $reglas = db()->query('SELECT contiene, prioridad FROM reglas')->fetchAll();
    } catch (\Throwable $e) {
        return 'media'; // tabla inexistente: prioridad por defecto
    }

    foreach ($reglas as $r) {
        $aguja = mb_strtolower($r['contiene']);
        if ($aguja !== '' && str_contains($texto, $aguja)) {
            return $r['prioridad'];
        }
    }

    return 'media';
}

/**
 * Sincroniza todas las cuentas: importa correos nuevos como tareas y
 * crea sus eventos de agenda.
 *
 * @return array{nuevas:int, errores:string[]}
 */
function sincronizarCuentas(): array
{
    $pdo = db();
    $nuevas = 0;
    $errores = [];

    $filtros = filtrosActivos();
    $cuentas = $pdo->query('SELECT * FROM cuentas WHERE requiere_reconexion = 0')->fetchAll();

    foreach ($cuentas as $cuenta) {
        try {
            // IDs ya importados de esta cuenta (sincronización incremental).
            $st = $pdo->prepare(
                'SELECT email_message_id FROM tareas
                  WHERE cuenta_id = ? AND email_message_id IS NOT NULL'
            );
            $st->execute([$cuenta['id']]);
            $omitir = $st->fetchAll(PDO::FETCH_COLUMN);

            $correos = leerCorreos($cuenta, $omitir);

            foreach ($correos as $correo) {
                if (esNoDeseado($correo, $filtros)) {
                    continue;
                }

                $prioridad = prioridadPorReglas($correo['asunto'], $correo['remitente']);
                $titulo = $correo['asunto'] !== '' ? $correo['asunto'] : '(sin asunto)';

                // INSERT IGNORE: el índice único evita duplicar el mismo correo.
                $ins = $pdo->prepare(
                    'INSERT IGNORE INTO tareas
                        (titulo, prioridad, fuente, cuenta_id, email_message_id,
                         email_asunto, email_remitente, email_enlace)
                     VALUES (?, ?, "email", ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $titulo,
                    $prioridad,
                    $cuenta['id'],
                    $correo['id'],
                    $correo['asunto'],
                    $correo['remitente'],
                    $correo['enlace'],
                ]);

                // Si realmente se insertó (no era duplicado), crear el evento.
                if ($ins->rowCount() > 0) {
                    $nuevas++;
                    $tareaId = (int) $pdo->lastInsertId();
                    try {
                        crearEventoTarea($tareaId);
                    } catch (\Throwable $e) {
                        // La tarea se conserva aunque falle el evento.
                        $errores[] = "Agenda (cuenta {$cuenta['email']}): " . $e->getMessage();
                    }
                }
            }
        } catch (\Throwable $e) {
            $errores[] = "Cuenta {$cuenta['email']}: " . $e->getMessage();
        }
    }

    return ['nuevas' => $nuevas, 'errores' => $errores];
}
