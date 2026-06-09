<?php
/**
 * Arranque de los tests.
 *
 * - Apunta config() a la configuración de pruebas (fixtures/config.test.php).
 * - Carga el autoload de Composer.
 */

declare(strict_types=1);

putenv('RECORDATORIO_CONFIG=' . __DIR__ . '/fixtures/config.test.php');

require __DIR__ . '/../vendor/autoload.php';
