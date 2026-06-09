<?php
/**
 * Configuración de PHP CS Fixer (formateo automático del código).
 *
 *   composer cs       Muestra los cambios que haría (no modifica nada).
 *   composer cs-fix   Aplica el formateo.
 *
 * Basado en PSR-12 con unos pocos extras razonables. Se aplica a lib/, tests/
 * y a los archivos PHP de public/ (incluidas las vistas que mezclan HTML).
 */

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/lib', __DIR__ . '/tests', __DIR__ . '/public'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        // Importaciones ordenadas y sin usos sobrantes.
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        // Arrays con sintaxis corta y comas finales en multilínea.
        'array_syntax' => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => true,
        // Espacios limpios.
        'no_trailing_whitespace' => true,
        'single_quote' => true,
        'blank_line_after_namespace' => true,
    ])
    ->setFinder($finder);
