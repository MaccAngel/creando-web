<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/estadisticas.php';

/**
 * Tests de la serie de tendencia (serieTendencia), función pura.
 */
final class TendenciaTest extends TestCase
{
    private \DateTime $hoy;

    protected function setUp(): void
    {
        $this->hoy = new \DateTime('2026-06-09');
    }

    public function testDevuelveExactamenteNDias(): void
    {
        $serie = serieTendencia([], $this->hoy, 14);
        $this->assertCount(14, $serie);
    }

    public function testRellenaConCeroLosDiasSinTareas(): void
    {
        $serie = serieTendencia([], $this->hoy, 3);
        $this->assertSame([0, 0, 0], array_column($serie, 'total'));
    }

    public function testOrdenCronologicoTerminaHoy(): void
    {
        $serie = serieTendencia([], $this->hoy, 3);
        $fechas = array_column($serie, 'fecha');
        $this->assertSame(['2026-06-07', '2026-06-08', '2026-06-09'], $fechas);
    }

    public function testColocaLosConteosEnSuDia(): void
    {
        $conteos = ['2026-06-08' => 5, '2026-06-09' => 2];
        $serie = serieTendencia($conteos, $this->hoy, 3);

        $this->assertSame(0, $serie[0]['total']); // 07
        $this->assertSame(5, $serie[1]['total']); // 08
        $this->assertSame(2, $serie[2]['total']); // 09
    }

    public function testIgnoraDiasFueraDeRango(): void
    {
        // Un conteo de hace un mes no debe aparecer en una ventana de 3 días.
        $conteos = ['2026-05-01' => 9];
        $serie = serieTendencia($conteos, $this->hoy, 3);
        $this->assertSame([0, 0, 0], array_column($serie, 'total'));
    }

    public function testEtiquetaEnFormatoDiaMes(): void
    {
        $serie = serieTendencia([], $this->hoy, 1);
        $this->assertSame('09/06', $serie[0]['etiqueta']);
    }
}
