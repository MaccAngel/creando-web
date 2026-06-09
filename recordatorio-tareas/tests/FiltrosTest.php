<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/correo.php';

/**
 * Tests del filtrado de correo no deseado (esNoDeseado).
 *
 * Es una función pura: recibe el correo y los filtros, no toca la BD.
 */
final class FiltrosTest extends TestCase
{
    private array $filtros = [
        'remitentes' => ['noreply@', 'newsletter@'],
        'palabras'   => ['oferta', 'promoción'],
    ];

    public function testDescartaPorRemitenteBloqueado(): void
    {
        $correo = ['asunto' => 'Tu resumen semanal', 'remitente' => 'noreply@web.com'];
        $this->assertTrue(esNoDeseado($correo, $this->filtros));
    }

    public function testDescartaPorPalabraEnAsunto(): void
    {
        $correo = ['asunto' => '¡Gran OFERTA de verano!', 'remitente' => 'tienda@web.com'];
        $this->assertTrue(esNoDeseado($correo, $this->filtros), 'Debe ser insensible a mayúsculas.');
    }

    public function testDescartaPorPalabraConTilde(): void
    {
        $correo = ['asunto' => 'Promoción exclusiva', 'remitente' => 'ventas@web.com'];
        $this->assertTrue(esNoDeseado($correo, $this->filtros));
    }

    public function testNoDescartaCorreoLegitimo(): void
    {
        $correo = ['asunto' => 'Reunión del lunes', 'remitente' => 'jefe@empresa.com'];
        $this->assertFalse(esNoDeseado($correo, $this->filtros));
    }

    public function testSinFiltrosNoDescartaNada(): void
    {
        $vacios = ['remitentes' => [], 'palabras' => []];
        $correo = ['asunto' => 'oferta noreply', 'remitente' => 'noreply@web.com'];
        $this->assertFalse(esNoDeseado($correo, $vacios));
    }
}
