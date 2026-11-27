<?php
require_once 'config/auth.php';
require_once 'config/conexion.php';

$mensaje = '';
$tipoMensaje = '';

// Actualizar automáticamente préstamos activos cuya fecha prevista ya venció
$pdo->exec("UPDATE prestamos 
            SET estado_prestamo = 'vencido' 
            WHERE estado_prestamo = 'activo' 
              AND fecha_prevista_devolucion < NOW()");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirAdmin();
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'crear_prestamo') {
            $idEquipo      = (int)($_POST['id_equipo'] ?? 0);
            $idSolicitante = (int)($_POST['id_solicitante'] ?? 0);
            $fechaPrevista = trim($_POST['fecha_prevista_devolucion'] ?? '');

            if ($idEquipo <= 0 || $idSolicitante <= 0 || $fechaPrevista === '') {
                throw new Exception('Selecciona equipo, solicitante y fecha prevista de devolución.');
            }

            // Convertir formato HTML datetime-local (YYYY-MM-DDTHH:MM) a formato MariaDB
            $fechaPrevistaSql = str_replace('T', ' ', $fechaPrevista);
            if (strlen($fechaPrevistaSql) === 16) {
                $fechaPrevistaSql .= ':00';
            }

            // Iniciar transacción atómica
            $pdo->beginTransaction();

            // Bloquear fila del equipo (FOR UPDATE) para evitar préstamos duplicados simultáneos
            $stmtEq = $pdo->prepare("SELECT estado, codigo_inventario FROM equipos WHERE id_equipo = :id FOR UPDATE");
            $stmtEq->execute([':id' => $idEquipo]);
            $equipo = $stmtEq->fetch();

            if (!$equipo) {
                throw new Exception('El equipo seleccionado no existe.');
            }
            if ($equipo['estado'] !== 'disponible') {
                throw new Exception("No se puede prestar el equipo ({$equipo['codigo_inventario']}) porque su estado actual es: {$equipo['estado']}.");
            }

            // 1. Insertar registro del préstamo
            $stmtIns = $pdo->prepare("INSERT INTO prestamos 
                (id_equipo, id_solicitante, id_usuario_entrega, fecha_prevista_devolucion, estado_prestamo)
                VALUES (:eq, :sol, :usr, :prevista, 'activo')");
            $stmtIns->execute([
                ':eq'       => $idEquipo,
                ':sol'      => $idSolicitante,
                ':usr'      => (int)$_SESSION['id_usuario'],
                ':prevista' => $fechaPrevistaSql
            ]);

            // 2. Cambiar estado del equipo a 'prestado'
            $stmtUpd = $pdo->prepare("UPDATE equipos SET estado = 'prestado' WHERE id_equipo = :id");
            $stmtUpd->execute([':id' => $idEquipo]);

            $pdo->commit();
            $mensaje = 'Préstamo registrado correctamente y equipo marcado como prestado.';
            $tipoMensaje = 'exito';

        } elseif ($accion === 'registrar_devolucion') {
            $idPrestamo            = (int)($_POST['id_prestamo'] ?? 0);
            $condicionDevolucion   = trim($_POST['condicion_devolucion'] ?? '');
            $requiereMantenimiento = isset($_POST['requiere_mantenimiento']) ? 1 : 0;

            if ($idPrestamo <= 0 || $condicionDevolucion === '') {
                throw new Exception('Selecciona un préstamo activo e indica la condición de retorno.');
            }

            // Iniciar transacción atómica
            $pdo->beginTransaction();

            // Bloquear préstamo activo
            $stmtPr = $pdo->prepare("SELECT id_equipo, estado_prestamo FROM prestamos WHERE id_prestamo = :id FOR UPDATE");
            $stmtPr->execute([':id' => $idPrestamo]);
            $prestamo = $stmtPr->fetch();

            if (!$prestamo) {
                throw new Exception('El préstamo seleccionado no existe.');
            }
            if ($prestamo['estado_prestamo'] === 'devuelto') {
                throw new Exception('Este préstamo ya fue cerrado anteriormente.');
            }

            // 1. Cerrar el préstamo conservando historial
            $stmtCierre = $pdo->prepare("UPDATE prestamos
                SET id_usuario_recibe = :recibe,
                    fecha_devolucion_real = NOW(),
                    estado_prestamo = 'devuelto',
                    condicion_devolucion = :cond,
                    requiere_mantenimiento = :mant
                WHERE id_prestamo = :id");
            $stmtCierre->execute([
                ':recibe' => (int)$_SESSION['id_usuario'],
                ':cond'   => $condicionDevolucion,
                ':mant'   => $requiereMantenimiento,
                ':id'     => $idPrestamo
            ]);

            // 2. Actualizar estado y condición física del equipo
            $nuevoEstadoEquipo = ($requiereMantenimiento === 1) ? 'mantenimiento' : 'disponible';
            $stmtEqUpd = $pdo->prepare("UPDATE equipos 
                SET estado = :estado, condicion_fisica = :cond 
                WHERE id_equipo = :id_eq");
            $stmtEqUpd->execute([
                ':estado' => $nuevoEstadoEquipo,
                ':cond'   => $condicionDevolucion,
                ':id_eq'  => (int)$prestamo['id_equipo']
            ]);

            $pdo->commit();
            $mensaje = ($requiereMantenimiento === 1)
                ? 'Devolución registrada. El equipo fue enviado a Mantenimiento.'
                : 'Devolución registrada correctamente. El equipo vuelve a estar Disponible.';
            $tipoMensaje = 'exito';
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = 'Error en base de datos (operación revertida): ' . $e->getMessage();
        $tipoMensaje = 'error';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = $e->getMessage();
        $tipoMensaje = 'error';
    }
}

// 1. Equipos disponibles para nuevo préstamo
$equiposDisponibles = $pdo->query("
    SELECT id_equipo, codigo_inventario, descripcion, marca, modelo
    FROM equipos
    WHERE estado = 'disponible'
    ORDER BY codigo_inventario ASC
")->fetchAll();

// 2. Solicitantes registrados
$solicitantes = $pdo->query("
    SELECT s.id_solicitante, s.numero_control, s.nombre_completo, d.nombre AS departamento
    FROM solicitantes s
    INNER JOIN departamentos d ON s.id_departamento = d.id_departamento
    ORDER BY s.nombre_completo ASC
")->fetchAll();

// 3. Préstamos activos o vencidos pendientes de devolución (JOIN)
$prestamosActivos = $pdo->query("
    SELECT p.id_prestamo, p.fecha_prestamo, p.fecha_prevista_devolucion, p.estado_prestamo,
           e.codigo_inventario, e.descripcion AS equipo,
           s.numero_control, s.nombre_completo AS solicitante
    FROM prestamos p
    INNER JOIN equipos e ON p.id_equipo = e.id_equipo
    INNER JOIN solicitantes s ON p.id_solicitante = s.id_solicitante
    WHERE p.estado_prestamo IN ('activo', 'vencido')
    ORDER BY p.fecha_prevista_devolucion ASC
")->fetchAll();

// 4. Historial completo de préstamos y devoluciones (JOIN múltiple)
$historial = $pdo->query("
    SELECT p.id_prestamo, p.fecha_prestamo, p.fecha_prevista_devolucion, p.fecha_devolucion_real,
           p.estado_prestamo, p.condicion_devolucion,
           e.codigo_inventario, e.descripcion AS equipo, e.marca, e.modelo,
           s.numero_control, s.nombre_completo AS solicitante,
           u_ent.nombre_completo AS usuario_entrega,
           u_rec.nombre_completo AS usuario_recibe
    FROM prestamos p
    INNER JOIN equipos e ON p.id_equipo = e.id_equipo
    INNER JOIN solicitantes s ON p.id_solicitante = s.id_solicitante
    INNER JOIN usuarios u_ent ON p.id_usuario_entrega = u_ent.id_usuario
    LEFT JOIN usuarios u_rec ON p.id_usuario_recibe = u_rec.id_usuario
    ORDER BY p.id_prestamo DESC
")->fetchAll();

$filtroInicial = trim($_GET['buscar'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préstamos y Devoluciones | Control de Préstamos TI</title>
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
            <li><a href="prestamos.php" class="activo">Préstamos y Devoluciones</a></li>
            <li><a href="equipos.php">Equipos</a></li>
            <li><a href="solicitantes.php">Solicitantes</a></li>
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
            <h2>Registro de Préstamos y Devoluciones</h2>
            <p>Control de entregas, recepciones y verificación de condiciones de retorno.</p>
        </div>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="panel" style="padding:0.85rem 1.2rem; border-left:5px solid <?= $tipoMensaje === 'exito' ? '#15803d' : '#dc2626' ?>; background:<?= $tipoMensaje === 'exito' ? '#dcfce7' : '#fee2e2' ?>; color:<?= $tipoMensaje === 'exito' ? '#166534' : '#991b1b' ?>;">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if (esAdmin()): ?>
    <div class="grid-dos-columnas">
        <!-- Formulario 1: Nuevo Préstamo -->
        <section class="panel">
            <div class="panel-titulo">Registrar Nuevo Préstamo</div>
            <form method="POST" action="prestamos.php">
                <input type="hidden" name="accion" value="crear_prestamo">
                <div class="form-grid">
                    <div class="campo ancho-completo">
                        <label for="id_equipo">Equipo Disponible *</label>
                        <select id="id_equipo" name="id_equipo" required>
                            <option value="">-- Seleccione un equipo disponible --</option>
                            <?php foreach ($equiposDisponibles as $eq): ?>
                                <option value="<?= (int)$eq['id_equipo'] ?>">
                                    <?= htmlspecialchars($eq['codigo_inventario'] . ' - ' . $eq['descripcion'] . ' (' . $eq['marca'] . ' ' . $eq['modelo'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo ancho-completo">
                        <label for="id_solicitante">Solicitante *</label>
                        <select id="id_solicitante" name="id_solicitante" required>
                            <option value="">-- Seleccione solicitante registrado --</option>
                            <?php foreach ($solicitantes as $sol): ?>
                                <option value="<?= (int)$sol['id_solicitante'] ?>">
                                    <?= htmlspecialchars($sol['numero_control'] . ' - ' . $sol['nombre_completo'] . ' (' . $sol['departamento'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="fecha_prevista_devolucion">Fecha Prevista de Devolución *</label>
                        <input type="datetime-local" id="fecha_prevista_devolucion" name="fecha_prevista_devolucion" required>
                    </div>
                    <div class="campo">
                        <label for="usuario_entrega">Encargado que Entrega</label>
                        <input type="text" id="usuario_entrega" value="<?= htmlspecialchars($_SESSION['nombre']) ?>" readonly>
                    </div>
                </div>
                <div class="acciones-form">
                    <button type="submit" class="btn btn-primario">Confirmar Préstamo</button>
                    <button type="reset" class="btn btn-secundario">Limpiar</button>
                </div>
            </form>
        </section>

        <!-- Formulario 2: Registrar Devolución -->
        <section class="panel">
            <div class="panel-titulo">Registrar Devolución de Equipo</div>
            <form method="POST" action="prestamos.php">
                <input type="hidden" name="accion" value="registrar_devolucion">
                <div class="form-grid">
                    <div class="campo ancho-completo">
                        <label for="id_prestamo_dev">Préstamo Activo a Cerrar *</label>
                        <select id="id_prestamo_dev" name="id_prestamo" required>
                            <option value="">-- Seleccione préstamo activo --</option>
                            <?php foreach ($prestamosActivos as $pa): ?>
                                <option value="<?= (int)$pa['id_prestamo'] ?>">
                                    <?= htmlspecialchars('#' . $pa['id_prestamo'] . ' | ' . $pa['codigo_inventario'] . ' - ' . $pa['equipo'] . ' → ' . $pa['solicitante']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo ancho-completo">
                        <label for="condicion_devolucion">Condición en que Regresa el Equipo *</label>
                        <input type="text" id="condicion_devolucion" name="condicion_devolucion" placeholder="Ej. Sin daños físicos, incluye cargador" required>
                    </div>
                    <div class="campo ancho-completo">
                        <label class="campo-check">
                            <input type="checkbox" id="requiere_mantenimiento" name="requiere_mantenimiento" value="1">
                            <span>El equipo presenta falla y debe enviarse a <strong>Mantenimiento</strong></span>
                        </label>
                    </div>
                </div>
                <div class="acciones-form">
                    <button type="submit" class="btn btn-primario">Registrar Devolución</button>
                </div>
            </form>
        </section>
    </div>
    <?php endif; ?>

    <!-- Historial completo de movimientos -->
    <section class="panel">
        <div class="panel-titulo">Historial General de Préstamos y Devoluciones</div>
        <div class="barra-filtros">
            <input type="text" id="inputBuscarHistorial" value="<?= htmlspecialchars($filtroInicial) ?>" placeholder="Buscar historial por persona, código de inventario o equipo..." data-tabla-filtro="tablaHistorialPrestamos">
            <select data-tabla-estado="tablaHistorialPrestamos">
                <option value="">Todos los estados</option>
                <option value="activo">Activo</option>
                <option value="vencido">Vencido</option>
                <option value="devuelto">Devuelto</option>
            </select>
        </div>
        <div class="tabla-contenedor">
            <table id="tablaHistorialPrestamos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cód. Inventario</th>
                        <th>Equipo</th>
                        <th>Solicitante</th>
                        <th>Salida</th>
                        <th>Prevista</th>
                        <th>Devolución Real</th>
                        <th>Condición Retorno</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historial)): ?>
                        <tr class="fila-vacia">
                            <td colspan="9">No hay movimientos de préstamos registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historial as $h): ?>
                            <tr data-estado="<?= htmlspecialchars($h['estado_prestamo']) ?>">
                                <td><?= (int)$h['id_prestamo'] ?></td>
                                <td><?= htmlspecialchars($h['codigo_inventario']) ?></td>
                                <td><?= htmlspecialchars($h['equipo'] . ' (' . $h['marca'] . ')') ?></td>
                                <td><?= htmlspecialchars($h['numero_control'] . ' - ' . $h['solicitante']) ?></td>
                                <td><?= htmlspecialchars($h['fecha_prestamo']) ?></td>
                                <td><?= htmlspecialchars($h['fecha_prevista_devolucion']) ?></td>
                                <td><?= htmlspecialchars($h['fecha_devolucion_real'] ?? 'Pendiente') ?></td>
                                <td><?= htmlspecialchars($h['condicion_devolucion'] ?? '-') ?></td>
                                <td>
                                    <span class="badge badge-<?= htmlspecialchars($h['estado_prestamo']) ?>">
                                        <?= htmlspecialchars(ucfirst($h['estado_prestamo'])) ?>
                                    </span>
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
<?php if ($filtroInicial !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    filtrarTabla('tablaHistorialPrestamos');
});
</script>
<?php endif; ?>
</body>
</html>