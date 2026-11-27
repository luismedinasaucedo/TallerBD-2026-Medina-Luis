<?php
require_once 'config/auth.php';
require_once 'config/conexion.php';

$mensaje = '';
$tipoMensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirAdmin();
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'guardar') {
            $idEquipo         = trim($_POST['id_equipo'] ?? '');
            $codigoInventario = trim($_POST['codigo_inventario'] ?? '');
            $numeroSerie      = trim($_POST['numero_serie'] ?? '');
            $idCategoria      = (int)($_POST['id_categoria'] ?? 0);
            $descripcion      = trim($_POST['descripcion'] ?? '');
            $marca            = trim($_POST['marca'] ?? '');
            $modelo           = trim($_POST['modelo'] ?? '');
            $estado           = $_POST['estado'] ?? 'disponible';
            $condicionFisica  = trim($_POST['condicion_fisica'] ?? 'Óptima');

            if ($codigoInventario === '' || $idCategoria <= 0 || $descripcion === '' || $marca === '' || $modelo === '') {
                throw new Exception('Completa todos los campos obligatorios (*).');
            }

            if (!in_array($estado, ['disponible', 'mantenimiento', 'baja'], true)) {
                $estado = 'disponible';
            }

            if ($idEquipo === '') {
                // INSERT: Registrar nuevo equipo
                $stmt = $pdo->prepare("INSERT INTO equipos 
                    (codigo_inventario, numero_serie, descripcion, marca, modelo, id_categoria, estado, condicion_fisica)
                    VALUES (:inv, :serie, :desc, :marca, :modelo, :cat, :estado, :cond)");
                $stmt->execute([
                    ':inv'    => $codigoInventario,
                    ':serie'  => ($numeroSerie !== '') ? $numeroSerie : null,
                    ':desc'   => $descripcion,
                    ':marca'  => $marca,
                    ':modelo' => $modelo,
                    ':cat'    => $idCategoria,
                    ':estado' => $estado,
                    ':cond'   => ($condicionFisica !== '') ? $condicionFisica : 'Óptima'
                ]);
                $mensaje = 'Equipo registrado correctamente en el inventario.';
            } else {
                // Verificar estado real en BD antes de actualizar
                $stmtActual = $pdo->prepare("SELECT estado FROM equipos WHERE id_equipo = :id");
                $stmtActual->execute([':id' => (int)$idEquipo]);
                $equipoActual = $stmtActual->fetch();

                if (!$equipoActual) {
                    throw new Exception('El equipo seleccionado no existe.');
                }

                // Si está prestado, no permitir cambiar su estado manualmente
                $estadoFinal = ($equipoActual['estado'] === 'prestado') ? 'prestado' : $estado;

                // UPDATE: Actualizar equipo existente
                $stmt = $pdo->prepare("UPDATE equipos
                    SET codigo_inventario = :inv,
                        numero_serie = :serie,
                        descripcion = :desc,
                        marca = :marca,
                        modelo = :modelo,
                        id_categoria = :cat,
                        estado = :estado,
                        condicion_fisica = :cond
                    WHERE id_equipo = :id");
                $stmt->execute([
                    ':inv'    => $codigoInventario,
                    ':serie'  => ($numeroSerie !== '') ? $numeroSerie : null,
                    ':desc'   => $descripcion,
                    ':marca'  => $marca,
                    ':modelo' => $modelo,
                    ':cat'    => $idCategoria,
                    ':estado' => $estadoFinal,
                    ':cond'   => ($condicionFisica !== '') ? $condicionFisica : 'Óptima',
                    ':id'     => (int)$idEquipo
                ]);
                $mensaje = 'Datos del equipo actualizados correctamente.';
            }
            $tipoMensaje = 'exito';

        } elseif ($accion === 'eliminar') {
            $idEliminar = (int)($_POST['id_equipo'] ?? 0);
            if ($idEliminar <= 0) {
                throw new Exception('Identificador de equipo no válido.');
            }

            $stmt = $pdo->prepare("DELETE FROM equipos WHERE id_equipo = :id");
            $stmt->execute([':id' => $idEliminar]);
            $mensaje = 'Equipo eliminado del inventario.';
            $tipoMensaje = 'exito';
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            if ($accion === 'eliminar') {
                $mensaje = 'No se puede eliminar este equipo porque tiene préstamos en el historial. Cámbialo a estado "Baja de inventario" para conservar sus movimientos.';
            } else {
                $mensaje = 'Ya existe un equipo con ese código de inventario o número de serie.';
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

// 1. Obtener categorías para el <select>
$categorias = $pdo->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre ASC")->fetchAll();

// 2. Consulta JOIN: listado general de equipos con el nombre de su categoría
$sqlEquipos = "
    SELECT 
        e.id_equipo,
        e.codigo_inventario,
        e.numero_serie,
        e.descripcion,
        e.marca,
        e.modelo,
        e.id_categoria,
        c.nombre AS categoria,
        e.estado,
        e.condicion_fisica
    FROM equipos e
    INNER JOIN categorias c ON e.id_categoria = c.id_categoria
    ORDER BY e.codigo_inventario ASC
";
$equipos = $pdo->query($sqlEquipos)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario de Equipos | Control de Préstamos TI</title>
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
            <li><a href="equipos.php" class="activo">Equipos</a></li>
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
            <h2>Control de Equipos Tecnológicos</h2>
            <p>Alta individual por código de inventario, actualización de datos y gestión de estado operativo.</p>
        </div>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="panel" style="padding:0.85rem 1.2rem; border-left:5px solid <?= $tipoMensaje === 'exito' ? '#15803d' : '#dc2626' ?>; background:<?= $tipoMensaje === 'exito' ? '#dcfce7' : '#fee2e2' ?>; color:<?= $tipoMensaje === 'exito' ? '#166534' : '#991b1b' ?>;">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if (esAdmin()): ?>
    <section class="panel">
        <div class="panel-titulo">Registrar / Actualizar Equipo</div>

        <!-- Desplegable conectado con js/app.js -->
        <div class="selector-equipo-box">
            <div class="campo">
                <label for="selector_equipo_existente">Seleccionar equipo existente (opcional):</label>
                <select id="selector_equipo_existente">
                    <option value="">-- Nuevo equipo (llenar campos en blanco) --</option>
                    <?php foreach ($equipos as $eq): ?>
                        <option value="<?= (int)$eq['id_equipo'] ?>"
                            data-id="<?= (int)$eq['id_equipo'] ?>"
                            data-inventario="<?= htmlspecialchars($eq['codigo_inventario']) ?>"
                            data-serie="<?= htmlspecialchars($eq['numero_serie'] ?? '') ?>"
                            data-categoria="<?= (int)$eq['id_categoria'] ?>"
                            data-descripcion="<?= htmlspecialchars($eq['descripcion']) ?>"
                            data-marca="<?= htmlspecialchars($eq['marca']) ?>"
                            data-modelo="<?= htmlspecialchars($eq['modelo']) ?>"
                            data-condicion="<?= htmlspecialchars($eq['condicion_fisica']) ?>"
                            data-estado="<?= htmlspecialchars($eq['estado']) ?>">
                            <?= htmlspecialchars($eq['codigo_inventario'] . ' - ' . $eq['descripcion'] . ' (' . $eq['marca'] . ' ' . $eq['modelo'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <form id="formEquipo" method="POST" action="equipos.php">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" id="id_equipo_edit" name="id_equipo" value="">
            <div class="form-grid">
                <div class="campo">
                    <label for="codigo_inventario">Código de Inventario (Único) *</label>
                    <input type="text" id="codigo_inventario" name="codigo_inventario" placeholder="Ej. INV-LAP-001" required>
                </div>
                <div class="campo">
                    <label for="numero_serie">Número de Serie (Opcional)</label>
                    <input type="text" id="numero_serie" name="numero_serie" placeholder="Ej. SN-9948210A">
                </div>
                <div class="campo">
                    <label for="id_categoria">Categoría *</label>
                    <select id="id_categoria" name="id_categoria" required>
                        <option value="">-- Seleccione categoría --</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= (int)$cat['id_categoria'] ?>">
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo">
                    <label for="descripcion">Descripción *</label>
                    <input type="text" id="descripcion" name="descripcion" placeholder="Ej. Laptop para laboratorio" required>
                </div>
                <div class="campo">
                    <label for="marca">Marca *</label>
                    <input type="text" id="marca" name="marca" placeholder="Ej. Dell" required>
                </div>
                <div class="campo">
                    <label for="modelo">Modelo *</label>
                    <input type="text" id="modelo" name="modelo" placeholder="Ej. Latitude 5430" required>
                </div>
                <div class="campo">
                    <label for="estado">Estado Operativo *</label>
                    <select id="estado" name="estado" required>
                        <option value="disponible">Disponible</option>
                        <option value="mantenimiento">Mantenimiento</option>
                        <option value="baja">Baja de inventario</option>
                    </select>
                    <span id="notaEstadoPrestado" class="nota-campo">
                        Este equipo está prestado; su estado solo cambia al registrar la devolución.
                    </span>
                </div>
                <div class="campo">
                    <label for="condicion_fisica">Condición Física *</label>
                    <input type="text" id="condicion_fisica" name="condicion_fisica" value="Óptima" required>
                </div>
            </div>
            <div class="acciones-form">
                <button type="submit" class="btn btn-primario">Guardar Equipo</button>
                <button type="reset" class="btn btn-secundario" onclick="limpiarSeleccionEquipo()">Limpiar / Nuevo</button>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-titulo">Listado General de Equipos Registrados</div>
        <div class="barra-filtros">
            <input type="text" placeholder="Buscar por código de inventario, serie, descripción, marca o modelo..." data-tabla-filtro="tablaEquipos">
            <select data-tabla-estado="tablaEquipos">
                <option value="">Todos los estados</option>
                <option value="disponible">Disponible</option>
                <option value="prestado">Prestado</option>
                <option value="mantenimiento">Mantenimiento</option>
                <option value="baja">Baja</option>
            </select>
        </div>
        <div class="tabla-contenedor">
            <table id="tablaEquipos">
                <thead>
                    <tr>
                        <th>Inventario</th>
                        <th>No. Serie</th>
                        <th>Descripción</th>
                        <th>Categoría</th>
                        <th>Marca / Modelo</th>
                        <th>Condición</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($equipos)): ?>
                        <tr class="fila-vacia">
                            <td colspan="8">No hay equipos registrados todavía.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($equipos as $eq): ?>
                            <tr data-estado="<?= htmlspecialchars($eq['estado']) ?>">
                                <td><?= htmlspecialchars($eq['codigo_inventario']) ?></td>
                                <td><?= htmlspecialchars($eq['numero_serie'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($eq['descripcion']) ?></td>
                                <td><?= htmlspecialchars($eq['categoria']) ?></td>
                                <td><?= htmlspecialchars($eq['marca'] . ' ' . $eq['modelo']) ?></td>
                                <td><?= htmlspecialchars($eq['condicion_fisica']) ?></td>
                                <td>
                                    <span class="badge badge-<?= htmlspecialchars($eq['estado']) ?>">
                                        <?= htmlspecialchars(ucfirst($eq['estado'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="prestamos.php?buscar=<?= urlencode($eq['codigo_inventario']) ?>" class="btn btn-secundario btn-sm">Historial</a>
                                    <?php if (esAdmin()): ?>
                                        <button type="button" class="btn btn-advertencia btn-sm" data-seleccionar-equipo="<?= (int)$eq['id_equipo'] ?>">Editar</button>
                                        <form method="POST" action="equipos.php" style="display:inline;" onsubmit="return confirm('¿Eliminar este equipo del inventario?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id_equipo" value="<?= (int)$eq['id_equipo'] ?>">
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
</body>
</html>