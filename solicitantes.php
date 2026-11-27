<?php
require_once 'config/auth.php';
require_once 'config/conexion.php';

$mensaje = '';
$tipoMensaje = '';

// Procesar altas, ediciones y eliminaciones (solo administradores)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirAdmin();
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'guardar') {
            $idSolicitante  = trim($_POST['id_solicitante'] ?? '');
            $numeroControl  = trim($_POST['numero_control'] ?? '');
            $nombreCompleto = trim($_POST['nombre_completo'] ?? '');
            $idDepartamento = (int)($_POST['id_departamento'] ?? 0);

            if ($numeroControl === '' || $nombreCompleto === '' || $idDepartamento <= 0) {
                throw new Exception('Todos los campos marcados con * son obligatorios.');
            }

            if ($idSolicitante === '') {
                // INSERT: Nuevo solicitante
                $stmt = $pdo->prepare("INSERT INTO solicitantes (numero_control, nombre_completo, id_departamento)
                                       VALUES (:num, :nombre, :dep)");
                $stmt->execute([
                    ':num'    => $numeroControl,
                    ':nombre' => $nombreCompleto,
                    ':dep'    => $idDepartamento
                ]);
                $mensaje = 'Solicitante registrado correctamente.';
            } else {
                // UPDATE: Actualizar solicitante existente
                $stmt = $pdo->prepare("UPDATE solicitantes
                                       SET numero_control = :num,
                                           nombre_completo = :nombre,
                                           id_departamento = :dep
                                       WHERE id_solicitante = :id");
                $stmt->execute([
                    ':num'    => $numeroControl,
                    ':nombre' => $nombreCompleto,
                    ':dep'    => $idDepartamento,
                    ':id'     => (int)$idSolicitante
                ]);
                $mensaje = 'Datos del solicitante actualizados correctamente.';
            }
            $tipoMensaje = 'exito';

        } elseif ($accion === 'eliminar') {
            $idEliminar = (int)($_POST['id_solicitante'] ?? 0);
            if ($idEliminar <= 0) {
                throw new Exception('Identificador de solicitante no válido.');
            }

            $stmt = $pdo->prepare("DELETE FROM solicitantes WHERE id_solicitante = :id");
            $stmt->execute([':id' => $idEliminar]);
            $mensaje = 'Solicitante eliminado del directorio.';
            $tipoMensaje = 'exito';
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            if ($accion === 'eliminar') {
                $mensaje = 'No se puede eliminar este solicitante porque tiene historial de préstamos registrado.';
            } else {
                $mensaje = 'Ya existe otro solicitante con ese número institucional / control.';
            }
        } else {
            $mensaje = 'Error en base de datos: ' . $e->getMessage();
        }
        $tipoMensaje = 'error';
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = 'error';
    }
}

// 1. Obtener departamentos para el <select>
$departamentos = $pdo->query("SELECT id_departamento, nombre FROM departamentos ORDER BY nombre ASC")->fetchAll();

// 2. Consulta JOIN: solicitantes + departamentos + conteo de préstamos activos e históricos
$sqlSolicitantes = "
    SELECT 
        s.id_solicitante,
        s.numero_control,
        s.nombre_completo,
        s.id_departamento,
        d.nombre AS departamento,
        COUNT(p.id_prestamo) AS total_historico,
        SUM(CASE WHEN p.estado_prestamo IN ('activo', 'vencido') THEN 1 ELSE 0 END) AS prestamos_activos
    FROM solicitantes s
    INNER JOIN departamentos d ON s.id_departamento = d.id_departamento
    LEFT JOIN prestamos p ON s.id_solicitante = p.id_solicitante
    GROUP BY s.id_solicitante, s.numero_control, s.nombre_completo, s.id_departamento, d.nombre
    ORDER BY s.nombre_completo ASC
";
$solicitantes = $pdo->query($sqlSolicitantes)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitantes | Control de Préstamos TI</title>
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
            <li><a href="solicitantes.php" class="activo">Solicitantes</a></li>
            <li><a href="catalogos.php">Catálogos y Usuarios</a></li>
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
            <h2>Registro de Solicitantes</h2>
            <p>Personal docente, administrativo y estudiantes autorizados para solicitar equipo.</p>
        </div>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="panel" style="padding:0.85rem 1.2rem; border-left:5px solid <?= $tipoMensaje === 'exito' ? '#15803d' : '#dc2626' ?>; background:<?= $tipoMensaje === 'exito' ? '#dcfce7' : '#fee2e2' ?>; color:<?= $tipoMensaje === 'exito' ? '#166534' : '#991b1b' ?>;">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if (esAdmin()): ?>
    <section class="panel">
        <div class="panel-titulo" id="tituloFormSolicitante">Registrar / Editar Solicitante</div>
        <form method="POST" action="solicitantes.php" id="formSolicitante">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id_solicitante" id="id_solicitante_edit" value="">
            <div class="form-grid">
                <div class="campo">
                    <label for="numero_control">Número Institucional / Control *</label>
                    <input type="text" id="numero_control" name="numero_control" placeholder="Ej. 22460145 o EMP-0892" required>
                </div>
                <div class="campo">
                    <label for="nombre_completo">Nombre Completo *</label>
                    <input type="text" id="nombre_completo" name="nombre_completo" placeholder="Nombre y apellidos" required>
                </div>
                <div class="campo">
                    <label for="id_departamento">Departamento o Área *</label>
                    <select id="id_departamento" name="id_departamento" required>
                        <option value="">-- Seleccione departamento --</option>
                        <?php foreach ($departamentos as $dep): ?>
                            <option value="<?= (int)$dep['id_departamento'] ?>">
                                <?= htmlspecialchars($dep['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="acciones-form">
                <button type="submit" class="btn btn-primario">Guardar Solicitante</button>
                <button type="reset" class="btn btn-secundario" onclick="limpiarFormSolicitante()">Limpiar / Nuevo</button>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-titulo">Directorio de Solicitantes e Historial Individual</div>
        <div class="barra-filtros">
            <input type="text" placeholder="Buscar por número institucional, nombre o departamento..." data-tabla-filtro="tablaSolicitantes">
        </div>
        <div class="tabla-contenedor">
            <table id="tablaSolicitantes">
                <thead>
                    <tr>
                        <th>No. Institucional</th>
                        <th>Nombre Completo</th>
                        <th>Departamento / Área</th>
                        <th>Préstamos Activos</th>
                        <th>Total Histórico</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($solicitantes)): ?>
                        <tr class="fila-vacia">
                            <td colspan="6">No hay solicitantes registrados todavía.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($solicitantes as $sol): ?>
                            <tr>
                                <td><?= htmlspecialchars($sol['numero_control']) ?></td>
                                <td><?= htmlspecialchars($sol['nombre_completo']) ?></td>
                                <td><?= htmlspecialchars($sol['departamento']) ?></td>
                                <td><?= (int)$sol['prestamos_activos'] ?></td>
                                <td><?= (int)$sol['total_historico'] ?></td>
                                <td>
                                    <a href="prestamos.php?buscar=<?= urlencode($sol['numero_control']) ?>" class="btn btn-secundario btn-sm">Historial</a>
                                    <?php if (esAdmin()): ?>
                                        <button type="button" class="btn btn-advertencia btn-sm"
                                            onclick='cargarSolicitante(<?= json_encode([
                                                "id"     => $sol["id_solicitante"],
                                                "num"    => $sol["numero_control"],
                                                "nombre" => $sol["nombre_completo"],
                                                "dep"    => $sol["id_departamento"]
                                            ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            Editar
                                        </button>
                                        <form method="POST" action="solicitantes.php" style="display:inline;" onsubmit="return confirm('¿Eliminar a este solicitante?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id_solicitante" value="<?= (int)$sol['id_solicitante'] ?>">
                                            <button type="submit" class="btn btn-peligro btn-sm">Eliminar</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script src="js/app.js"></script>
<script>
function cargarSolicitante(datos) {
    document.getElementById('id_solicitante_edit').value = datos.id;
    document.getElementById('numero_control').value = datos.num;
    document.getElementById('nombre_completo').value = datos.nombre;
    document.getElementById('id_departamento').value = datos.dep;
    document.getElementById('formSolicitante').scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function limpiarFormSolicitante() {
    document.getElementById('id_solicitante_edit').value = '';
}
</script>
</body>
</html>