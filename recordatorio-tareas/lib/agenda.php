<?php
/**
 * Agenda automática: crea/actualiza eventos de calendario por tarea.
 *
 * - crearEventoTarea($tareaId)       Crea el evento de una tarea y guarda id/enlace.
 * - actualizarEventoTarea($tareaId)  Actualiza el evento (PATCH) o lo crea si no existe.
 *
 * La cuenta usada es la de la tarea; si la tarea es manual, la primera cuenta
 * conectada. Si no hay cuentas o la agenda está desactivada, no hace nada.
 *
 * Todo error se propaga al llamador, que debe envolverlo en try/catch para
 * que la tarea se conserve aunque falle la creación del evento.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/correo.php';   // tokenValido(), httpPeticion()
require_once __DIR__ . '/proveedores.php';

/** ¿Está activada la agenda en config? */
function agendaActiva(): bool
{
    return (bool) (config()['agenda']['activa'] ?? false);
}

/** Devuelve la cuenta asociada a una tarea, o la primera conectada si es manual. */
function cuentaParaTarea(array $tarea): ?array
{
    if (!empty($tarea['cuenta_id'])) {
        $st = db()->prepare('SELECT * FROM cuentas WHERE id = ?');
        $st->execute([$tarea['cuenta_id']]);
        $c = $st->fetch();
        if ($c) {
            return $c;
        }
    }
    // Tarea manual (o cuenta borrada): usar la primera cuenta conectada.
    $c = db()->query('SELECT * FROM cuentas ORDER BY id ASC LIMIT 1')->fetch();
    return $c ?: null;
}

/**
 * Calcula inicio y fin del evento (RFC3339) para una tarea.
 *
 * @return array{inicio:string, fin:string, zona:string}
 */
function franjaEvento(array $tarea): array
{
    $cfg  = config();
    $zona = $cfg['zona_horaria'] ?? 'Europe/Madrid';
    $hora = $cfg['agenda']['hora'] ?? '09:00';
    $dur  = (int) ($cfg['agenda']['duracion_min'] ?? 30);

    $fecha = $tarea['fecha_limite'] ?: date('Y-m-d');
    $tz = new DateTimeZone($zona);

    $inicio = new DateTime("$fecha $hora", $tz);
    $fin = (clone $inicio)->modify("+$dur minutes");

    return [
        'inicio' => $inicio->format('Y-m-d\TH:i:s'),
        'fin'    => $fin->format('Y-m-d\TH:i:s'),
        'zona'   => $zona,
    ];
}

/** Lee una tarea por id. */
function obtenerTarea(int $tareaId): ?array
{
    $st = db()->prepare('SELECT * FROM tareas WHERE id = ?');
    $st->execute([$tareaId]);
    return $st->fetch() ?: null;
}

/**
 * Crea el evento de calendario para una tarea y guarda evento_id/evento_enlace.
 */
function crearEventoTarea(int $tareaId): void
{
    if (!agendaActiva()) {
        return;
    }
    $tarea = obtenerTarea($tareaId);
    if (!$tarea) {
        return;
    }
    $cuenta = cuentaParaTarea($tarea);
    if (!$cuenta) {
        return; // sin cuentas conectadas no hay dónde crear el evento
    }

    $token = tokenValido($cuenta);
    [$id, $enlace] = $cuenta['proveedor'] === 'google'
        ? crearEventoGoogle($token, $tarea)
        : crearEventoMicrosoft($token, $tarea);

    $st = db()->prepare('UPDATE tareas SET evento_id = ?, evento_enlace = ? WHERE id = ?');
    $st->execute([$id, $enlace, $tareaId]);
}

/**
 * Actualiza el evento de una tarea. Si no existía, lo crea.
 */
function actualizarEventoTarea(int $tareaId): void
{
    if (!agendaActiva()) {
        return;
    }
    $tarea = obtenerTarea($tareaId);
    if (!$tarea) {
        return;
    }
    if (empty($tarea['evento_id'])) {
        crearEventoTarea($tareaId);
        return;
    }
    $cuenta = cuentaParaTarea($tarea);
    if (!$cuenta) {
        return;
    }

    $token = tokenValido($cuenta);
    if ($cuenta['proveedor'] === 'google') {
        actualizarEventoGoogle($token, $tarea);
    } else {
        actualizarEventoMicrosoft($token, $tarea);
    }
}

// ---------------------------------------------------------------------------
// Implementación Google Calendar
// ---------------------------------------------------------------------------

/** @return array{0:string,1:?string} [id, htmlLink] */
function crearEventoGoogle(string $token, array $tarea): array
{
    $f = franjaEvento($tarea);
    $cuerpo = json_encode([
        'summary'     => $tarea['titulo'],
        'description' => $tarea['notas'] ?? '',
        'start'       => ['dateTime' => $f['inicio'], 'timeZone' => $f['zona']],
        'end'         => ['dateTime' => $f['fin'],    'timeZone' => $f['zona']],
    ]);

    $r = httpPeticion(
        'POST',
        'https://www.googleapis.com/calendar/v3/calendars/primary/events',
        ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        $cuerpo
    );
    $datos = json_decode($r['cuerpo'], true) ?? [];
    if ($r['codigo'] < 200 || $r['codigo'] >= 300) {
        throw new RuntimeException('Google Calendar: ' . $r['cuerpo']);
    }
    return [$datos['id'] ?? '', $datos['htmlLink'] ?? null];
}

function actualizarEventoGoogle(string $token, array $tarea): void
{
    $f = franjaEvento($tarea);
    $cuerpo = json_encode([
        'summary'     => $tarea['titulo'],
        'description' => $tarea['notas'] ?? '',
        'start'       => ['dateTime' => $f['inicio'], 'timeZone' => $f['zona']],
        'end'         => ['dateTime' => $f['fin'],    'timeZone' => $f['zona']],
    ]);

    $id = rawurlencode($tarea['evento_id']);
    httpPeticion(
        'PATCH',
        "https://www.googleapis.com/calendar/v3/calendars/primary/events/$id",
        ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        $cuerpo
    );
}

// ---------------------------------------------------------------------------
// Implementación Microsoft Graph (Outlook Calendar)
// ---------------------------------------------------------------------------

/** @return array{0:string,1:?string} [id, webLink] */
function crearEventoMicrosoft(string $token, array $tarea): array
{
    $f = franjaEvento($tarea);
    $cuerpo = json_encode([
        'subject' => $tarea['titulo'],
        'body'    => ['contentType' => 'text', 'content' => $tarea['notas'] ?? ''],
        'start'   => ['dateTime' => $f['inicio'], 'timeZone' => $f['zona']],
        'end'     => ['dateTime' => $f['fin'],    'timeZone' => $f['zona']],
    ]);

    $r = httpPeticion(
        'POST',
        'https://graph.microsoft.com/v1.0/me/events',
        ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        $cuerpo
    );
    $datos = json_decode($r['cuerpo'], true) ?? [];
    if ($r['codigo'] < 200 || $r['codigo'] >= 300) {
        throw new RuntimeException('Microsoft Calendar: ' . $r['cuerpo']);
    }
    return [$datos['id'] ?? '', $datos['webLink'] ?? null];
}

function actualizarEventoMicrosoft(string $token, array $tarea): void
{
    $f = franjaEvento($tarea);
    $cuerpo = json_encode([
        'subject' => $tarea['titulo'],
        'body'    => ['contentType' => 'text', 'content' => $tarea['notas'] ?? ''],
        'start'   => ['dateTime' => $f['inicio'], 'timeZone' => $f['zona']],
        'end'     => ['dateTime' => $f['fin'],    'timeZone' => $f['zona']],
    ]);

    $id = rawurlencode($tarea['evento_id']);
    httpPeticion(
        'PATCH',
        "https://graph.microsoft.com/v1.0/me/events/$id",
        ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        $cuerpo
    );
}
