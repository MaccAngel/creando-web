<?php
/**
 * Pantalla de inicio de sesión con PIN.
 *
 * Solo es relevante si config['pin_hash'] no está vacío. Si no hay PIN,
 * redirige directamente a index.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';

iniciarSesion();

// Cierre de sesión (enlace "Salir").
if (isset($_GET['salir'])) {
    cerrarSesion();
    header('Location: login.php');
    exit;
}

// Sin PIN configurado: no hace falta login.
if (!hayPin()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = $_POST['pin'] ?? '';
    if (comprobarPin((string) $pin)) {
        autenticar();
        header('Location: index.php');
        exit;
    }
    $error = 'PIN incorrecto.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso · Recordatorio de Tareas</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="pagina-login">
    <main class="login">
        <h1>Recordatorio de Tareas</h1>
        <p>Introduce tu PIN para continuar.</p>

        <?php if ($error !== ''): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="password" name="pin" inputmode="numeric"
                   placeholder="PIN" autofocus required>
            <button type="submit" class="btn btn-primario">Entrar</button>
        </form>
    </main>
</body>
</html>
