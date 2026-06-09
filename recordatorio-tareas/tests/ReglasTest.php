<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/sync.php';

/**
 * Tests de la lógica de reglas de prioridad (prioridadSegunReglas).
 *
 * Versión pura: recibe las reglas como array, sin acceder a la BD.
 */
final class ReglasTest extends TestCase
{
    private array $reglas = [
        ['contiene' => 'factura', 'prioridad' => 'alta'],
        ['contiene' => 'reunión', 'prioridad' => 'media'],
        ['contiene' => 'boletín', 'prioridad' => 'baja'],
    ];

    public function testAsignaPrioridadAltaPorCoincidencia(): void
    {
        $p = prioridadSegunReglas('Factura del mes', 'cobros@web.com', $this->reglas);
        $this->assertSame('alta', $p);
    }

    public function testCoincideEnElRemitente(): void
    {
        $p = prioridadSegunReglas('Hola', 'reunión-equipo@web.com', $this->reglas);
        $this->assertSame('media', $p);
    }

    public function testEsInsensibleAMayusculas(): void
    {
        $p = prioridadSegunReglas('BOLETÍN semanal', 'info@web.com', $this->reglas);
        $this->assertSame('baja', $p);
    }

    public function testDevuelveMediaSiNoHayCoincidencia(): void
    {
        $p = prioridadSegunReglas('Cualquier cosa', 'x@web.com', $this->reglas);
        $this->assertSame('media', $p);
    }

    public function testGanaLaPrimeraReglaQueCoincide(): void
    {
        // El texto contiene "factura" y "reunión"; debe ganar la primera.
        $p = prioridadSegunReglas('Factura y reunión', 'x@web.com', $this->reglas);
        $this->assertSame('alta', $p);
    }

    public function testSinReglasDevuelveMedia(): void
    {
        $this->assertSame('media', prioridadSegunReglas('Factura', 'x@web.com', []));
    }
}
