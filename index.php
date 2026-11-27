<?php
require_once 'config/auth.php';
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
            <li><a href="index.html" class="activo">Inicio y Consultas</a></li>
<li><a href="prestamos.html">Préstamos y Devoluciones</a></li>
<li><a href="equipos.php">Equipos</a></li>
<li><a href="solicitantes.php">Solicitantes</a></li>
<li><a href="catalogos.php">Catálogos y Usuarios</a></li>
        </ul>
        <div class="nav-usuario">
    <span class="usuario-activo">
        <?= htmlspecialchars($_SESSION['nombre']) ?> (<?= htmlspecialchars($_SESSION['rol']) ?>)
    </span>
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
        <div>
            <a href="prestamos.html" class="btn btn-primario">+ Nuevo Préstamo</a>
        </div>
    </div>

    <section class="grid-kpi">
        <div class="tarjeta-kpi">
            <h3>Equipos Disponibles</h3>
            <div class="numero">0</div>
            <div class="detalle">Listos para entrega inmediata</div>
        </div>
        <div class="tarjeta-kpi alerta">
            <h3>Préstamos Activos</h3>
            <div class="numero">0</div>
            <div class="detalle">Actualmente en uso institucional</div>
        </div>
        <div class="tarjeta-kpi peligro">
            <h3>Préstamos Vencidos</h3>
            <div class="numero">0</div>
            <div class="detalle">Fecha prevista de devolución superada</div>
        </div>
        <div class="tarjeta-kpi">
            <h3>En Mantenimiento</h3>
            <div class="numero">0</div>
            <div class="detalle">Retirados temporalmente por falla</div>
        </div>
    </section>

    <!-- Consulta 3 y 4: Préstamos activos y vencidos -->
    <section class="panel">
        <div class="panel-titulo">
            <span>Préstamos Activos y Vencidos (Seguimiento)</span>
            <a href="prestamos.html" class="btn btn-secundario btn-sm">Ir a Devoluciones</a>
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
                    <tr class="fila-vacia">
                        <td colspan="8">No hay préstamos activos o vencidos registrados.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Consulta 1 y 2: Equipos disponibles y estado general -->
    <section class="panel">
        <div class="panel-titulo">
            <span>Consulta Rápida de Disponibilidad de Equipos</span>
            <a href="equipos.html" class="btn btn-secundario btn-sm">Ver Inventario Completo</a>
        </div>
        <div class="barra-filtros">
            <input type="text" placeholder="Filtrar por código de inventario, descripción, marca o modelo..." data-tabla-filtro="tablaResumenEquipos">
            <select data-tabla-estado="tablaResumenEquipos">
                <option value="">Todas las situaciones</option>
                <option value="disponible">Disponibles</option>
                <option value="prestado">Prestados</option>
                <option value="mantenimiento">En Mantenimiento</option>
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
                    <tr class="fila-vacia">
                        <td colspan="6">No hay equipos registrados en el inventario.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script src="js/app.js"></script>
</body>
</html>