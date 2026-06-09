<?php

/**
 * Cálculo de estadísticas para el panel (dashboard).
 *
 * resumirTareas() es una función PURA: recibe las filas de tareas ya cargadas
 * y devuelve todos los agregados. Así puede cubrirse con tests sin base de
 * datos (igual que estadoVencimiento() o prioridadSegunReglas()).
 */

declare(strict_types=1);

require_once __DIR__ . '/fechas.php';

/**
 * Calcula los agregados del panel a partir de las filas de tareas.
 *
 * @param array<int, array> $tareas    Filas con: completada, prioridad,
 *                                      fuente, fecha_limite, titulo…
 * @param DateTime          $hoy       Día de referencia.
 * @param int               $avisoDias Margen de "vence pronto".
 * @return array{
 *     total:int, completadas:int, pendientes:int, porcentaje:int,
 *     vencidas:int, hoy:int, pronto:int,
 *     prioridad:array{alta:int,media:int,baja:int},
 *     fuente:array{manual:int,email:int},
 *     proximas:array<int,array>
 * }
 */
function resumirTareas(array $tareas, DateTime $hoy, int $avisoDias): array
{
    $r = [
        'total'       => count($tareas),
        'completadas' => 0,
        'pendientes'  => 0,
        'porcentaje'  => 0,
        'vencidas'    => 0,
        'hoy'         => 0,
        'pronto'      => 0,
        'prioridad'   => ['alta' => 0, 'media' => 0, 'baja' => 0],
        'fuente'      => ['manual' => 0, 'email' => 0],
        'proximas'    => [],
    ];

    foreach ($tareas as $t) {
        // Desglose por fuente (sobre todas las tareas).
        $fuente = $t['fuente'] ?? 'manual';
        if (isset($r['fuente'][$fuente])) {
            $r['fuente'][$fuente]++;
        }

        if (!empty($t['completada'])) {
            $r['completadas']++;
            continue; // las completadas no cuentan para lo pendiente
        }

        $r['pendientes']++;

        // Prioridad (solo pendientes: lo que requiere atención).
        $prio = $t['prioridad'] ?? 'media';
        if (isset($r['prioridad'][$prio])) {
            $r['prioridad'][$prio]++;
        }

        // Estado de vencimiento (solo pendientes con fecha).
        $estado = estadoVencimiento($t['fecha_limite'] ?? null, $hoy, $avisoDias);
        if ($estado) {
            if ($estado[0] === 'vencida') {
                $r['vencidas']++;
            } elseif ($estado[0] === 'hoy') {
                $r['hoy']++;
            } elseif ($estado[0] === 'pronto') {
                $r['pronto']++;
            }
        }

        // Candidata a "próximos vencimientos".
        if (!empty($t['fecha_limite'])) {
            $r['proximas'][] = $t;
        }
    }

    // Porcentaje de completado.
    if ($r['total'] > 0) {
        $r['porcentaje'] = (int) round($r['completadas'] / $r['total'] * 100);
    }

    // Ordenar próximas por fecha límite ascendente.
    usort($r['proximas'], fn ($a, $b) => strcmp($a['fecha_limite'], $b['fecha_limite']));

    return $r;
}

/**
 * Construye una serie diaria de los últimos $dias días (PURA).
 *
 * Rellena con 0 los días sin tareas, de modo que la serie siempre tenga
 * exactamente $dias elementos en orden cronológico (del más antiguo a hoy).
 *
 * @param array<string,int> $conteos Mapa 'Y-m-d' => total (días sueltos).
 * @return array<int, array{fecha:string, etiqueta:string, total:int}>
 */
function serieTendencia(array $conteos, DateTime $hoy, int $dias): array
{
    $serie = [];
    for ($i = $dias - 1; $i >= 0; $i--) {
        $dia = (clone $hoy)->modify("-$i day");
        $clave = $dia->format('Y-m-d');
        $serie[] = [
            'fecha'    => $clave,
            'etiqueta' => $dia->format('d/m'),
            'total'    => (int) ($conteos[$clave] ?? 0),
        ];
    }
    return $serie;
}

/**
 * Tendencia de tareas creadas por día en los últimos $dias días.
 *
 * Lee de la BD (agrupando por fecha de creación) y delega el relleno de días
 * en serieTendencia(). Devuelve también el máximo para escalar las barras.
 *
 * @return array{serie:array<int,array{fecha:string,etiqueta:string,total:int}>, max:int}
 */
function tendenciaCreacion(PDO $pdo, DateTime $hoy, int $dias = 14): array
{
    $desde = (clone $hoy)->modify('-' . ($dias - 1) . ' day')->format('Y-m-d');

    $st = $pdo->prepare(
        'SELECT DATE(creada_en) AS dia, COUNT(*) AS total
           FROM tareas
          WHERE creada_en >= ?
          GROUP BY DATE(creada_en)'
    );
    $st->execute([$desde . ' 00:00:00']);

    $conteos = [];
    foreach ($st->fetchAll() as $fila) {
        $conteos[$fila['dia']] = (int) $fila['total'];
    }

    $serie = serieTendencia($conteos, $hoy, $dias);
    $max = 0;
    foreach ($serie as $d) {
        $max = max($max, $d['total']);
    }

    return ['serie' => $serie, 'max' => $max];
}
