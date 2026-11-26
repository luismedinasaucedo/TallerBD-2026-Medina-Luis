<?php
require_once 'config/auth.php';
require_once 'config/conexion.php';

$mensaje = '';
$tipoMensaje = ''; // 'exito' o 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirAdmin();
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'crear_categoria') {
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');

            if ($nombre === '') {
                throw new Exception('El nombre de la categoría es obligatorio.');
            }

            $stmt = $pdo->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (:nombre, :desc)");
            $stmt->execute([
                ':nombre' => $nombre,
                ':desc'   => ($descripcion !== '') ? $descripcion : null
            ]);
            $mensaje = 'Categoría registrada correctamente.';
            $tipoMensaje = 'exito';

        } elseif ($accion === 'crear_departamento') {
            $nombre = trim($_POST['nombre'] ?? '');

            if ($nombre === '') {
                throw new Exception('El nombre del departamento es obligatorio.');
            }

            $stmt = $pdo->prepare("INSERT INTO departamentos (nombre) VALUES (:nombre)");
            $stmt->execute([':nombre' => $nombre]);
            $mensaje = 'Departamento registrado correctamente.';
            $tipoMensaje = 'exito';

        } elseif ($accion === 'crear_usuario') {
            $numEmp   = trim($_POST['num_empleado'] ?? '');
            $nombre   = trim($_POST['nombre_completo'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol      = $_POST['rol'] ?? 'consulta';

            if ($numEmp === '' || $nombre === '' || $username === '' || $password === '') {
                throw new Exception('Todos los campos del usuario son obligatorios.');
            }
            if (!in_array($rol, ['administrador', 'consulta'], true)) {
                $rol = 'consulta';
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (num_empleado, nombre_completo, username, password_hash, rol, activo)
                                   VALUES (:emp, :nombre, :user, :pass, :rol, 1)");
            $stmt->execute([
                ':emp'    => $numEmp,
                ':nombre' => $nombre,
                ':user'   => $username,
                ':pass'   => $hash,
                ':rol'    => $rol
            ]);
            $mensaje = 'Usuario creado correctamente.';
            $tipoMensaje = 'exito';
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $mensaje = 'Error: ya existe un registro con ese nombre, usuario o número de empleado.';
        } else {
            $mensaje = 'Error en base de datos: ' . $e->getMessage();
        }
        $tipoMensaje = 'error';
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = 'error';
    }
}

// Consultas SELECT para poblar las tablas
$categorias = $pdo->query("SELECT id_categoria, nombre, descripcion FROM categorias ORDER BY nombre ASC")->fetchAll();
$departamentos = $pdo->query("SELECT id_departamento, nombre FROM departamentos ORDER BY nombre ASC")->fetchAll();
$usuarios = $pdo->query("SELECT id_usuario, num_empleado, nombre_completo, username, rol, activo FROM usuarios ORDER BY nombre_completo ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogos y Usuarios | Control de Préstamos TI</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<header class="navbar">
    <div class="navbar-contenedor">
        <a href="index.php" class="marca">
            <span class="marca-icono">TI</span>
            <div class="marca-texto">
                <h1>Control de Préstamos</h1>
                <span>Soporte y Servicios Tecnológicos</span>
            </div>
        </a>
        <button class="btn-menu-movil" id="btnMenuMovil" aria-label="Abrir menú">&#9776; Menú</button>
        <ul class="nav-enlaces" id="navEnlaces">
            <li><a href="index.php">Inicio y Consultas</a></li>
            <li><a href="prestamos.php">Préstamos y Devoluciones</a></li>
            <li><a href="equipos.php">Equipos</a></li>
            <li><a href="solicitantes.php">Solicitantes</a></li>
            <li><a href="catalogos.php" class="activo">Catálogos y Usuarios</a></li>
        </ul>
        <div class="nav-usuario">
            <span class="usuario-activo"><?= htmlspecialchars($_SESSION['nombre']) ?> (<?= htmlspecialchars($_SESSION['rol']) ?>)</span>
            <a href="logout.php" class="btn-cerrar-sesion">Cerrar sesión</a>
        </div>
    </div>
</header>

<main class="contenedor">
    <div class="encabezado-seccion">
        <div>
            <h2>Catálogos Base y Control de Usuarios</h2>
            <p>Administración de categorías de equipo, departamentos institucionales y cuentas de acceso.</p>
        </div>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="panel" style="padding:0.85rem 1.2rem; border-left:5px solid <?= $tipoMensaje === 'exito' ? '#15803d' : '#dc2626' ?>; background:<?= $tipoMensaje === 'exito' ? '#dcfce7' : '#fee2e2' ?>; color:<?= $tipoMensaje === 'exito' ? '#166534' : '#991b1b' ?>;">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <div class="grid-dos-columnas">
        <!-- Categorías -->
        <section class="panel">
            <div class="panel-titulo">Categorías de Equipo</div>
            <?php if (esAdmin()): ?>
            <form method="POST" action="catalogos.php" style="margin-bottom: 1rem;">
                <input type="hidden" name="accion" value="crear_categoria">
                <div class="form-grid">
                    <div class="campo">
                        <label for="cat_nombre">Nombre de Categoría *</label>
                        <input type="text" id="cat_nombre" name="nombre" placeholder="Ej. Computadoras Portátiles" required>
                    </div>
                    <div class="campo">
                        <label for="cat_desc">Descripción</label>
                        <input type="text" id="cat_desc" name="descripcion" placeholder="Breve descripción">
                    </div>
                </div>
                <div class="acciones-form">
                    <button type="submit" class="btn btn-primario btn-sm">Agregar Categoría</button>
                </div>
            </form>
            <?php endif; ?>
            <div class="tabla-contenedor">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Categoría</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categorias)): ?>
                            <tr class="fila-vacia">
                                <td colspan="3">Sin categorías registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categorias as $cat): ?>
                                <tr>
                                    <td><?= (int)$cat['id_categoria'] ?></td>
                                    <td><?= htmlspecialchars($cat['nombre']) ?></td>
                                    <td><?= htmlspecialchars($cat['descripcion'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Departamentos -->
        <section class="panel">
            <div class="panel-titulo">Departamentos Institucionales</div>
            <?php if (esAdmin()): ?>
            <form method="POST" action="catalogos.php" style="margin-bottom: 1rem;">
                <input type="hidden" name="accion" value="crear_departamento">
                <div class="form-grid">
                    <div class="campo ancho-completo">
                        <label for="dep_nombre">Nombre del Departamento *</label>
                        <input type="text" id="dep_nombre" name="nombre" placeholder="Ej. Sistemas y Computación" required>
                    </div>
                </div>
                <div class="acciones-form">
                    <button type="submit" class="btn btn-primario btn-sm">Agregar Departamento</button>
                </div>
            </form>
            <?php endif; ?>
            <div class="tabla-contenedor">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Departamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($departamentos)): ?>
                            <tr class="fila-vacia">
                                <td colspan="2">Sin departamentos registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($departamentos as $dep): ?>
                                <tr>
                                    <td><?= (int)$dep['id_departamento'] ?></td>
                                    <td><?= htmlspecialchars($dep['nombre']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Usuarios del Sistema -->
    <section class="panel">
        <div class="panel-titulo">Usuarios del Sistema (Control de Privilegios)</div>
        <?php if (esAdmin()): ?>
        <form method="POST" action="catalogos.php" style="margin-bottom: 1.2rem;">
            <input type="hidden" name="accion" value="crear_usuario">
            <div class="form-grid">
                <div class="campo">
                    <label for="usr_emp">No. Empleado *</label>
                    <input type="text" id="usr_emp" name="num_empleado" placeholder="Ej. SOP-002" required>
                </div>
                <div class="campo">
                    <label for="usr_nombre">Nombre Completo *</label>
                    <input type="text" id="usr_nombre" name="nombre_completo" placeholder="Nombre del encargado" required>
                </div>
                <div class="campo">
                    <label for="usr_user">Usuario *</label>
                    <input type="text" id="usr_user" name="username" placeholder="Nombre de usuario" required>
                </div>
                <div class="campo">
                    <label for="usr_pass">Contraseña *</label>
                    <input type="password" id="usr_pass" name="password" placeholder="••••••••" required>
                </div>
                <div class="campo">
                    <label for="usr_rol">Rol de Acceso *</label>
                    <select id="usr_rol" name="rol" required>
                        <option value="consulta">Consulta (Solo lectura)</option>
                        <option value="administrador">Administrador (Control total)</option>
                    </select>
                </div>
            </div>
            <div class="acciones-form">
                <button type="submit" class="btn btn-primario">Crear Usuario</button>
            </div>
        </form>
        <?php endif; ?>
        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>No. Empleado</th>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usr): ?>
                        <tr>
                            <td><?= htmlspecialchars($usr['num_empleado']) ?></td>
                            <td><?= htmlspecialchars($usr['nombre_completo']) ?></td>
                            <td><?= htmlspecialchars($usr['username']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($usr['rol'])) ?></td>
                            <td>
                                <span class="badge <?= (int)$usr['activo'] === 1 ? 'badge-disponible' : 'badge-baja' ?>">
                                    <?= (int)$usr['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script src="js/app.js"></script>
</body>
</html>