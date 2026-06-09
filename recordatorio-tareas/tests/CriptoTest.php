<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/cripto.php';

/**
 * Tests del cifrado/descifrado de tokens (lib/cripto.php).
 */
final class CriptoTest extends TestCase
{
    public function testCifrarYDescifrarDevuelveElOriginal(): void
    {
        $original = 'ya29.un-token-de-acceso-secreto';
        $cifrado  = cifrar($original);

        $this->assertNotSame($original, $cifrado, 'El texto cifrado no debe ser igual al original.');
        $this->assertSame($original, descifrar($cifrado), 'Al descifrar se recupera el original.');
    }

    public function testCadaCifradoUsaUnNonceDistinto(): void
    {
        // El mismo texto cifrado dos veces produce salidas distintas (nonce aleatorio).
        $a = cifrar('mismo-texto');
        $b = cifrar('mismo-texto');

        $this->assertNotSame($a, $b, 'Dos cifrados del mismo texto deben diferir.');
        $this->assertSame('mismo-texto', descifrar($a));
        $this->assertSame('mismo-texto', descifrar($b));
    }

    public function testCadenaVaciaEsReversible(): void
    {
        $this->assertSame('', descifrar(cifrar('')));
    }

    public function testDescifrarDatosAlteradosLanzaExcepcion(): void
    {
        $cifrado = cifrar('contenido');
        // Corromper el último carácter del base64.
        $alterado = substr($cifrado, 0, -2) . (($cifrado[-2] === 'A') ? 'B' : 'A') . $cifrado[-1];

        $this->expectException(\RuntimeException::class);
        descifrar($alterado);
    }

    public function testDescifrarBasuraLanzaExcepcion(): void
    {
        $this->expectException(\RuntimeException::class);
        descifrar('esto-no-es-un-token-valido');
    }
}
