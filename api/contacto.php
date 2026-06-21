<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'Método no permitido.']));
}

$nombre  = trim($_POST['nombre']  ?? '');
$email   = trim($_POST['email']   ?? '');
$mensaje = trim($_POST['mensaje'] ?? '');
$tipo    = trim($_POST['tipo_sesion'] ?? '');
$fecha   = trim($_POST['fecha']   ?? '');

if ($nombre === '' || $email === '' || $mensaje === '') {
    http_response_code(422);
    exit(json_encode(['ok' => false, 'error' => 'Nombre, email y mensaje son obligatorios.']));
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    exit(json_encode(['ok' => false, 'error' => 'Email no válido.']));
}

$dbDir = __DIR__ . '/../data';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

try {
    $pdo = new PDO('sqlite:' . $dbDir . '/fotocol.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mensajes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            email TEXT NOT NULL,
            tipo_sesion TEXT,
            fecha_sesion TEXT,
            mensaje TEXT NOT NULL,
            leido INTEGER DEFAULT 0,
            creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $stmt = $pdo->prepare('INSERT INTO mensajes (nombre, email, tipo_sesion, fecha_sesion, mensaje) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$nombre, $email, $tipo, $fecha, $mensaje]);

    exit(json_encode(['ok' => true]));
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['ok' => false, 'error' => 'Error interno. Inténtalo de nuevo.']));
}
