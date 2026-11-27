<?php
require_once 'config/auth.php';
require_once 'config/conexion.php';

// Sincronizar vencidos antes de calcular indicadores
$pdo->exec("UPDATE prestamos 
            SET estado_prestamo = 'vencido' 
            WHERE estado_prestamo = 'activo' 
              AND fecha_prevista_devolucion < NOW()");

// KPIs de equipos
$kpiEquipos = $pdo->query("
    SELECT 
        SUM(CASE WHEN estado = 'disponible' THEN 1 ELSE 0 END) AS disponibles,
        SUM(CASE WHEN estado = 'mantenimiento' THEN 1 ELSE 0 END) AS mantenimiento
    FROM equipos
")->fetch();

// KPIs de préstamos activos y vencidos
$kpiPrestamos = $pdo->query("
    SELECT 
        SUM(CASE WHEN estado_prestamo = 'activo' THEN 1 ELSE 0 END) AS activos,
        SUM(CASE WHEN estado_prestamo = 'vencido' THEN 1 ELSE 0 END) AS vencidos
    FROM prestamos
")->fetch();

// Consulta 3 y 4: Préstamos activos y vencidos con datos legibles (JOIN)
$prestamosSeguimiento = $pdo->query("
    SELECT p.estado_prestamo, p.fecha_prestamo, p.fecha_prevista_devolucion,
           e.codigo_inventario, e.descripcion AS equipo,
           s.nombre_completo AS solicitante,
           d.nombre AS departamento,
           u.nombre_completo AS usuario_entrega
    FROM prestamos p
    INNER JOIN equipos e ON p.id_equipo = e.id_equipo
    INNER JOIN solicitantes s ON p.id_solicitante = s.id_solicitante
    INNER JOIN departamentos d ON s.id_departamento = d.id_departamento
    INNER JOIN usuarios u ON p.id_usuario_entrega = u.id_usuario
    WHERE p.estado_prestamo IN ('activo', 'vencido')
    ORDER BY p.fecha_prevista_devolucion ASC
")->fetchAll();

// Consulta 1 y 2: Listado general de equipos y disponibilidad (JOIN)
$equiposResumen = $pdo->query("
    SELECT e.codigo_inventario, c.nombre AS categoria, e.descripcion,
           e.marca, e.modelo, e.condicion_fisica, e.estado
    FROM equipos e
    INNER JOIN categorias c ON e.id_categoria = c.id_categoria
    ORDER BY e.codigo_inventario ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio | Control de Préstamos de Equipo Tecnológico</title>
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
            <li><a href="index.php" class="activo">Inicio y Consultas</a></li>
            <li><a href="prestamos.php">Préstamos y Devoluciones</a></li>
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
            <h2>Panel General y Consultas de Seguimiento</h2>
            <p>Resumen operativo del inventario bajo resguardo, préstamos activos y vencimientos.</p>
        </div>
        <?php if (esAdmin()): ?>
        <div>
            <a href="prestamos.php" class="btn btn-primario">+ Nuevo Préstamo</a>
        </div>
        <?php endif; ?>
    </div>

    <section class="grid-kpi">
        <div class="tarjeta-kpi">
            <h3>Equipos Disponibles</h3>
            <div class="numero"><?= (int)($kpiEquipos['disponibles'] ?? 0) ?></div>
            <div class="detalle">Listos para entrega inmediata</div>
        </div>
        <div class="tarjeta-kpi alerta">
            <h3>Préstamos Activos</h3>
            <div class="numero"><?= (int)($kpiPrestamos['activos'] ?? 0) ?></div>
            <div class="detalle">Actualmente en uso institucional</div>
        </div>
        <div class="tarjeta-kpi peligro">
            <h3>Préstamos Vencidos</h3>
            <div class="numero"><?= (int)($kpiPrestamos['vencidos'] ?? 0) ?></div>
            <div class="detalle">Fecha prevista de devolución superada</div>
        </div>
        <div class="tarjeta-kpi">
            <h3>En Mantenimiento</h3>
            <div class="numero"><?= (int)($kpiEquipos['mantenimiento'] ?? 0) ?></div>
            <div class="detalle">Retirados temporalmente por falla</div>
        </div>
    </section>

    <!-- Consulta 3 y 4: Préstamos activos y vencidos -->
    <section class="panel">
        <div class="panel-titulo">
            <span>Préstamos Activos y Vencidos (Seguimiento)</span>
            <a href="prestamos.php" class="btn btn-secundario btn-sm">Ir a Devoluciones</a>
        </div>
        <div class="barra-filtros">
            <input type="text" placeholder="Buscar por equipo, inventario, solicitante o departamento..." data-tabla-filtro="tablaResumenPrestamos">
            <select data-tabla-estado="tablaResumenPrestamos">
                <option value="">Todos los estados</option>
                <option value="activo">Activos</option>
                <option value="vencido">Vencidos</option>
            </select>
        </div>
        <div class="tabla-contenedor">
            <table id="tablaResumenPrestamos">
                <thead>
                    <tr>
                        <th>Inventario</th>
                        <th>Equipo</th>
                        <th>Solicitante</th>
                        <th>Departamento</th>
                        <th>Fecha Préstamo</th>
                        <th>Devolución Prevista</th>
                        <th>Entregó</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prestamosSeguimiento)): ?>
                        <tr class="fila-vacia">
                            <td colspan="8">No hay préstamos activos o vencidos registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($prestamosSeguimiento as $ps): ?>
                            <tr data-estado="<?= htmlspecialchars($ps['estado_prestamo']) ?>">
                                <td><?= htmlspecialchars($ps['codigo_inventario']) ?></td>
                                <td><?= htmlspecialchars($ps['equipo']) ?></td>
                                <td><?= htmlspecialchars($ps['solicitante']) ?></td>
                                <td><?= htmlspecialchars($ps['departamento']) ?></td>
                                <td><?= htmlspecialchars($ps['fecha_prestamo']) ?></td>
                                <td><?= htmlspecialchars($ps['fecha_prevista_devolucion']) ?></td>
                                <td><?= htmlspecialchars($ps['usuario_entrega']) ?></td>
                                <td>
                                    <span class="badge badge-<?= htmlspecialchars($ps['estado_prestamo']) ?>">
                                        <?= htmlspecialchars(ucfirst($ps['estado_prestamo'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Consulta 1 y 2: Equipos disponibles y estado general -->
    <section class="panel">
        <div class="panel-titulo">
            <span>Consulta Rápida de Disponibilidad de Equipos</span>
            <a href="equipos.php" class="btn btn-secundario btn-sm">Ver Inventario Completo</a>
        </div>
        <div class="barra-filtros">
            <input type="text" placeholder="Filtrar por código de inventario, descripción, marca o modelo..." data-tabla-filtro="tablaResumenEquipos">
            <select data-tabla-estado="tablaResumenEquipos">
                <option value="">Todas las situaciones</option>
                <option value="disponible">Disponibles</option>
                <option value="prestado">Prestados</option>
                <option value="mantenimiento">En Mantenimiento</option>
                <option value="baja">Baja</option>
            </select>
        </div>
        <div class="tabla-contenedor">
            <table id="tablaResumenEquipos">
                <thead>
                    <tr>
                        <th>Cód. Inventario</th>
                        <th>Categoría</th>
                        <th>Descripción</th>
                        <th>Marca / Modelo</th>
                        <th>Condición Física</th>
                        <th>Situación Actual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($equiposResumen)): ?>
                        <tr class="fila-vacia">
                            <td colspan="6">No hay equipos registrados en el inventario.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($equiposResumen as $er): ?>
                            <tr data-estado="<?= htmlspecialchars($er['estado']) ?>">
                                <td><?= htmlspecialchars($er['codigo_inventario']) ?></td>
                                <td><?= htmlspecialchars($er['categoria']) ?></td>
                                <td><?= htmlspecialchars($er['descripcion']) ?></td>
                                <td><?= htmlspecialchars($er['marca'] . ' ' . $er['modelo']) ?></td>
                                <td><?= htmlspecialchars($er['condicion_fisica']) ?></td>
                                <td>
                                    <span class="badge badge-<?= htmlspecialchars($er['estado']) ?>">
                                        <?= htmlspecialchars(ucfirst($er['estado'])) ?>
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
</body>
</html>