<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/fechas.php';

/**
 * Tests del cálculo de estado de vencimiento (estadoVencimiento).
 */
final class FechasTest extends TestCase
{
    private \DateTime $hoy;

    protected function setUp(): void
    {
        // Día de referencia fijo para que los tests sean deterministas.
        $this->hoy = new \DateTime('2026-06-09');
    }

    public function testSinFechaDevuelveNull(): void
    {
        $this->assertNull(estadoVencimiento(null, $this->hoy, 2));
    }

    public function testFechaPasadaEsVencida(): void
    {
        $r = estadoVencimiento('2026-06-08', $this->hoy, 2);
        $this->assertSame(['vencida', 'Vencida'], $r);
    }

    public function testFechaDeHoy(): void
    {
        $r = estadoVencimiento('2026-06-09', $this->hoy, 2);
        $this->assertSame(['hoy', 'Vence hoy'], $r);
    }

    public function testDentroDelMargenEsPronto(): void
    {
        // Mañana, con aviso_dias = 2.
        $r = estadoVencimiento('2026-06-10', $this->hoy, 2);
        $this->assertSame(['pronto', 'Vence pronto'], $r);

        // Justo en el límite (2 días).
        $r2 = estadoVencimiento('2026-06-11', $this->hoy, 2);
        $this->assertSame(['pronto', 'Vence pronto'], $r2);
    }

    public function testFueraDelMargenDevuelveNull(): void
    {
        // 3 días, con aviso_dias = 2.
        $this->assertNull(estadoVencimiento('2026-06-12', $this->hoy, 2));
    }
}
