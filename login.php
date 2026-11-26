<?php
session_start();
require_once 'config/conexion.php';

// Si ya inició sesión, mandarlo directo al panel
if (isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Ingresa tu usuario y contraseña.';
    } else {
        $stmt = $pdo->prepare("SELECT id_usuario, nombre_completo, username, password_hash, rol, activo 
                               FROM usuarios 
                               WHERE username = :user LIMIT 1");
        $stmt->execute([':user' => $username]);
        $usuario = $stmt->fetch();

        if ($usuario && (int)$usuario['activo'] === 1 && password_verify($password, $usuario['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['nombre']     = $usuario['nombre_completo'];
            $_SESSION['username']   = $usuario['username'];
            $_SESSION['rol']        = $usuario['rol'];

            header('Location: index.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos, o cuenta inactiva.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | Control de Préstamos TI</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body class="login-pagina">

    <div class="login-contenedor">
        <div class="login-cabecera">
            <span class="marca-icono">TI</span>
            <h1>Control de Préstamos</h1>
            <p>Departamento de Soporte y Servicios Tecnológicos</p>
        </div>

        <section class="panel">
            <div class="panel-titulo">Iniciar Sesión</div>
            <?php if ($error !== ''): ?>
                <p style="background:#fee2e2; color:#991b1b; padding:0.6rem 0.8rem; border-radius:6px; font-size:0.86rem; margin-bottom:1rem;">
                    <?= htmlspecialchars($error) ?>
                </p>
            <?php endif; ?>
            <form method="POST" action="login.php">
                <div class="form-grid" style="grid-template-columns: 1fr;">
                    <div class="campo">
                        <label for="login_user">Usuario</label>
                        <input type="text" id="login_user" name="username" placeholder="Nombre de usuario" required>
                    </div>
                    <div class="campo">
                        <label for="login_pass">Contraseña</label>
                        <input type="password" id="login_pass" name="password" placeholder="••••••••" required>
                    </div>
                </div>
                <div class="acciones-form">
                    <button type="submit" class="btn btn-primario" style="width:100%;">Ingresar al Sistema</button>
                </div>
            </form>
        </section>
    </div>

</body>
</html>
