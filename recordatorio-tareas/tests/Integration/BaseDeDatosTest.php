<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/sync.php';     // prioridadPorReglas()
require_once __DIR__ . '/../../lib/correo.php';   // filtrosActivos()

/**
 * Tests de integración contra una base de datos MySQL/MariaDB real.
 *
 * Crean la base de datos de pruebas y cargan el esquema. Si no hay base de
 * datos disponible, todos los tests se omiten (markTestSkipped) en lugar de
 * fallar, para no romper la suite en entornos sin BD.
 *
 * Configura la conexión con las variables de entorno DB_HOST/DB_NAME/DB_USER/DB_PASS
 * (ver tests/fixtures/config.test.php).
 */
final class BaseDeDatosTest extends TestCase
{
    private static bool $disponible = false;

    public static function setUpBeforeClass(): void
    {
        $cfg = config()['db'];
        try {
            // 1. Conexión a nivel de servidor para crear la BD de pruebas.
            $servidor = new PDO(
                "mysql:host={$cfg['host']}",
                $cfg['usuario'],
                $cfg['clave'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $servidor->exec(
                "CREATE DATABASE IF NOT EXISTS `{$cfg['nombre']}`
                 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );

            // 2. Cargar el esquema (solo las tablas) en la BD de pruebas.
            self::cargarEsquema(db());
            self::$disponible = true;
        } catch (\Throwable $e) {
            self::$disponible = false;
        }
    }

    /** Carga las sentencias CREATE TABLE de sql/esquema.sql. */
    private static function cargarEsquema(PDO $pdo): void
    {
        $sql = file_get_contents(__DIR__ . '/../../sql/esquema.sql');

        foreach (explode(';', $sql) as $sentencia) {
            // Quitar líneas de comentario (--) y espacios.
            $lineas = array_filter(
                array_map('trim', explode("\n", $sentencia)),
                fn ($l) => $l !== '' && !str_starts_with($l, '--')
            );
            $limpia = trim(implode("\n", $lineas));

            // La BD ya está seleccionada por el DSN: ignorar CREATE DATABASE / USE.
            if (
                $limpia === ''
                || preg_match('/^CREATE\s+DATABASE/i', $limpia)
                || preg_match('/^USE\b/i', $limpia)
            ) {
                continue;
            }

            $pdo->exec($limpia);
        }
    }

    /** Omite el test si no hay BD, y deja las tablas limpias antes de cada uno. */
    protected function setUp(): void
    {
        if (!self::$disponible) {
            $this->markTestSkipped('No hay base de datos disponible para los tests de integración.');
        }
        $pdo = db();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['tareas', 'reglas', 'filtros', 'cuentas'] as $tabla) {
            $pdo->exec("TRUNCATE TABLE `$tabla`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    // -------------------------------------------------------------------
    // Reglas de prioridad leídas de la base de datos.
    // -------------------------------------------------------------------
    public function testPrioridadPorReglasLeeDeLaBaseDeDatos(): void
    {
        db()->exec("INSERT INTO reglas (contiene, prioridad) VALUES ('factura', 'alta')");

        $this->assertSame('alta', prioridadPorReglas('Tu factura mensual', 'cobros@x.com'));
        $this->assertSame('media', prioridadPorReglas('Hola qué tal', 'amigo@x.com'));
    }

    // -------------------------------------------------------------------
    // filtrosActivos() combina config + tabla.
    // -------------------------------------------------------------------
    public function testFiltrosActivosCombinaConfigYTabla(): void
    {
        db()->exec("INSERT INTO filtros (tipo, valor) VALUES ('palabra', 'spam-db')");

        $filtros = filtrosActivos();

        // De config.test.php:
        $this->assertContains('noreply@', $filtros['remitentes']);
        $this->assertContains('oferta', $filtros['palabras']);
        // De la tabla:
        $this->assertContains('spam-db', $filtros['palabras']);
    }

    // -------------------------------------------------------------------
    // El índice único evita importar dos veces el mismo correo.
    // -------------------------------------------------------------------
    public function testIndiceUnicoEvitaCorreosDuplicados(): void
    {
        $pdo = db();
        $cuentaId = $this->crearCuentaDummy();

        $insertar = function () use ($pdo, $cuentaId) {
            $st = $pdo->prepare(
                'INSERT IGNORE INTO tareas
                    (titulo, fuente, cuenta_id, email_message_id)
                 VALUES (?, "email", ?, ?)'
            );
            $st->execute(['Correo importado', $cuentaId, 'MSG-123']);
            return $st->rowCount();
        };

        $this->assertSame(1, $insertar(), 'La primera inserción crea la tarea.');
        $this->assertSame(0, $insertar(), 'La segunda no duplica (INSERT IGNORE).');

        $total = (int) $pdo->query('SELECT COUNT(*) FROM tareas')->fetchColumn();
        $this->assertSame(1, $total);
    }

    // -------------------------------------------------------------------
    // Ciclo de vida de una tarea: crear, alternar completada, borrar.
    // -------------------------------------------------------------------
    public function testCicloDeVidaDeUnaTarea(): void
    {
        $pdo = db();

        $pdo->prepare('INSERT INTO tareas (titulo, prioridad) VALUES (?, ?)')
            ->execute(['Tarea de prueba', 'alta']);
        $id = (int) $pdo->lastInsertId();

        // Alternar completada (como hace la API "completar").
        $pdo->prepare('UPDATE tareas SET completada = 1 - completada WHERE id = ?')->execute([$id]);
        $completada = $pdo->query("SELECT completada FROM tareas WHERE id = $id")->fetchColumn();
        $this->assertSame(1, (int) $completada);

        // Borrar.
        $pdo->prepare('DELETE FROM tareas WHERE id = ?')->execute([$id]);
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM tareas')->fetchColumn());
    }

    // -------------------------------------------------------------------
    // Al borrar una cuenta, sus tareas quedan con cuenta_id = NULL (FK SET NULL).
    // -------------------------------------------------------------------
    public function testBorrarCuentaPonsCuentaIdANull(): void
    {
        $pdo = db();
        $cuentaId = $this->crearCuentaDummy();

        $pdo->prepare('INSERT INTO tareas (titulo, fuente, cuenta_id) VALUES (?, "email", ?)')
            ->execute(['Tarea con cuenta', $cuentaId]);

        $pdo->prepare('DELETE FROM cuentas WHERE id = ?')->execute([$cuentaId]);

        $cuentaIdTarea = $pdo->query('SELECT cuenta_id FROM tareas LIMIT 1')->fetchColumn();
        $this->assertNull($cuentaIdTarea, 'La FK ON DELETE SET NULL debe anular cuenta_id.');
    }

    /** Inserta una cuenta de prueba con tokens "cifrados" de relleno. */
    private function crearCuentaDummy(): int
    {
        $pdo = db();
        $pdo->prepare(
            'INSERT INTO cuentas (proveedor, email, access_token, refresh_token)
             VALUES ("google", ?, "x", "y")'
        )->execute(['cuenta-' . uniqid() . '@test.com']);
        return (int) $pdo->lastInsertId();
    }
}
