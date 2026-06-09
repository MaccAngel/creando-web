<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/estadisticas.php';

/**
 * Tests del agregador de estadísticas del panel (resumirTareas).
 *
 * Función pura: recibe filas de tareas y un día de referencia.
 */
final class EstadisticasTest extends TestCase
{
    private \DateTime $hoy;

    protected function setUp(): void
    {
        $this->hoy = new \DateTime('2026-06-09');
    }

    /** Conjunto de tareas de ejemplo reutilizable. */
    private function tareas(): array
    {
        return [
            // pendiente, alta, vencida (ayer), de correo
            ['completada' => 0, 'prioridad' => 'alta',  'fuente' => 'email',  'fecha_limite' => '2026-06-08', 'titulo' => 'A'],
            // pendiente, media, vence hoy, manual
            ['completada' => 0, 'prioridad' => 'media', 'fuente' => 'manual', 'fecha_limite' => '2026-06-09', 'titulo' => 'B'],
            // pendiente, baja, vence pronto (mañana), manual
            ['completada' => 0, 'prioridad' => 'baja',  'fuente' => 'manual', 'fecha_limite' => '2026-06-10', 'titulo' => 'C'],
            // pendiente, alta, sin fecha, manual
            ['completada' => 0, 'prioridad' => 'alta',  'fuente' => 'manual', 'fecha_limite' => null,         'titulo' => 'D'],
            // completada (no cuenta como pendiente), de correo
            ['completada' => 1, 'prioridad' => 'media', 'fuente' => 'email',  'fecha_limite' => '2026-06-01', 'titulo' => 'E'],
        ];
    }

    public function testConteosBasicos(): void
    {
        $s = resumirTareas($this->tareas(), $this->hoy, 2);

        $this->assertSame(5, $s['total']);
        $this->assertSame(1, $s['completadas']);
        $this->assertSame(4, $s['pendientes']);
        $this->assertSame(20, $s['porcentaje']); // 1 de 5
    }

    public function testEstadosDeVencimiento(): void
    {
        $s = resumirTareas($this->tareas(), $this->hoy, 2);

        $this->assertSame(1, $s['vencidas']);
        $this->assertSame(1, $s['hoy']);
        $this->assertSame(1, $s['pronto']);
    }

    public function testPrioridadSoloCuentaPendientes(): void
    {
        $s = resumirTareas($this->tareas(), $this->hoy, 2);

        // 2 altas pendientes, 1 media pendiente, 1 baja pendiente.
        $this->assertSame(2, $s['prioridad']['alta']);
        $this->assertSame(1, $s['prioridad']['media']);
        $this->assertSame(1, $s['prioridad']['baja']);
    }

    public function testFuenteCuentaTodas(): void
    {
        $s = resumirTareas($this->tareas(), $this->hoy, 2);

        // 3 manuales + 2 de correo (incluida la completada).
        $this->assertSame(3, $s['fuente']['manual']);
        $this->assertSame(2, $s['fuente']['email']);
    }

    public function testProximasOrdenadasYSoloPendientesConFecha(): void
    {
        $s = resumirTareas($this->tareas(), $this->hoy, 2);

        // Solo A, B, C (pendientes con fecha); ordenadas ascendentemente.
        $this->assertCount(3, $s['proximas']);
        $fechas = array_column($s['proximas'], 'fecha_limite');
        $this->assertSame(['2026-06-08', '2026-06-09', '2026-06-10'], $fechas);
    }

    public function testListaVaciaNoRompe(): void
    {
        $s = resumirTareas([], $this->hoy, 2);

        $this->assertSame(0, $s['total']);
        $this->assertSame(0, $s['porcentaje']);
        $this->assertSame([], $s['proximas']);
    }
}
