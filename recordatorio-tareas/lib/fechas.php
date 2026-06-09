<?php
/**
 * Utilidades de fechas para los recordatorios de vencimiento.
 *
 * Se extrae aquí (en lugar de dejarlo en index.php) para poder reutilizarlo
 * y, sobre todo, poder cubrirlo con tests unitarios sin renderizar HTML.
 */

declare(strict_types=1);

/**
 * Calcula el estado de vencimiento de una tarea con fecha límite.
 *
 * @param string|null $fecha     Fecha límite 'Y-m-d' o null.
 * @param DateTime    $hoy       Día de referencia (normalmente "today").
 * @param int         $avisoDias Días de antelación para "Vence pronto".
 * @return array{0:string,1:string}|null  [clase, etiqueta] o null si no aplica.
 */
function estadoVencimiento(?string $fecha, DateTime $hoy, int $avisoDias): ?array
{
    if (!$fecha) {
        return null;
    }

    $limite = new DateTime($fecha);
    // Diferencia en días con signo (negativo = pasada).
    $dias = (int) $hoy->diff($limite)->format('%r%a');

    if ($dias < 0) {
        return ['vencida', 'Vencida'];
    }
    if ($dias === 0) {
        return ['hoy', 'Vence hoy'];
    }
    if ($dias <= $avisoDias) {
        return ['pronto', 'Vence pronto'];
    }

    return null;
}
