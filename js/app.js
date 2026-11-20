// ============================================================================
// Sistema de Control de Préstamos de Equipo Tecnológico
// Avance 2: Interactividad de navegación, filtros y selección de equipos
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    // 1. Menú responsivo en dispositivos móviles
    const btnMenu = document.getElementById('btnMenuMovil');
    const navEnlaces = document.getElementById('navEnlaces');

    if (btnMenu && navEnlaces) {
        btnMenu.addEventListener('click', () => {
            navEnlaces.classList.toggle('abierto');
        });
    }

    // 2. Filtrado en tiempo real para tablas HTML
    const inputsFiltro = document.querySelectorAll('[data-tabla-filtro]');
    inputsFiltro.forEach((input) => {
        input.addEventListener('input', () => {
            const idTabla = input.getAttribute('data-tabla-filtro');
            filtrarTabla(idTabla);
        });
    });

    const selectsFiltro = document.querySelectorAll('[data-tabla-estado]');
    selectsFiltro.forEach((select) => {
        select.addEventListener('change', () => {
            const idTabla = select.getAttribute('data-tabla-estado');
            filtrarTabla(idTabla);
        });
    });

    // 3. Desplegable para seleccionar un equipo existente y cargar sus datos en el formulario
    const selectorEquipo = document.getElementById('selector_equipo_existente');
    const formEquipo = document.getElementById('formEquipo');
    if (selectorEquipo && formEquipo) {
        selectorEquipo.addEventListener('change', () => {
            const opcion = selectorEquipo.options[selectorEquipo.selectedIndex];
            if (!opcion || !opcion.value) {
                formEquipo.reset();
                limpiarSeleccionEquipo();
                return;
            }
            cargarEquipoEnFormulario(opcion.dataset);
        });
    }

    // 4. Botones "Editar" de la tabla de equipos también seleccionan el equipo en el formulario
    const botonesEditarEquipo = document.querySelectorAll('[data-seleccionar-equipo]');
    botonesEditarEquipo.forEach((btn) => {
        btn.addEventListener('click', () => {
            const idEquipo = btn.getAttribute('data-seleccionar-equipo');
            if (selectorEquipo) {
                selectorEquipo.value = idEquipo;
                selectorEquipo.dispatchEvent(new Event('change'));
                formEquipo.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // 5. Prevención de envío real en formularios estáticos del Avance 2
    const formulariosDemo = document.querySelectorAll('form[data-demo-form]');
    formulariosDemo.forEach((form) => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            alert('Formulario validado correctamente. La conexión con MariaDB se integrará en el siguiente avance.');
        });
    });
});

function cargarEquipoEnFormulario(datos) {
    document.getElementById('id_equipo_edit').value = datos.id || '';
    document.getElementById('codigo_inventario').value = datos.inventario || '';
    document.getElementById('numero_serie').value = datos.serie || '';
    document.getElementById('id_categoria').value = datos.categoria || '';
    document.getElementById('descripcion').value = datos.descripcion || '';
    document.getElementById('marca').value = datos.marca || '';
    document.getElementById('modelo').value = datos.modelo || '';
    document.getElementById('condicion_fisica').value = datos.condicion || 'Óptima';

    const selectEstado = document.getElementById('estado');
    const notaEstado = document.getElementById('notaEstadoPrestado');

    // Si el equipo está actualmente prestado, bloquear cambio de estado manual
    // para obligar a registrar su devolución desde el módulo de Préstamos.
    if (datos.estado === 'prestado') {
        selectEstado.disabled = true;
        if (notaEstado) notaEstado.classList.add('visible');
    } else {
        selectEstado.disabled = false;
        selectEstado.value = datos.estado || 'disponible';
        if (notaEstado) notaEstado.classList.remove('visible');
    }
}

function limpiarSeleccionEquipo() {
    const selectorEquipo = document.getElementById('selector_equipo_existente');
    const idHidden = document.getElementById('id_equipo_edit');
    const selectEstado = document.getElementById('estado');
    const notaEstado = document.getElementById('notaEstadoPrestado');

    if (selectorEquipo) selectorEquipo.value = '';
    if (idHidden) idHidden.value = '';
    if (selectEstado) {
        selectEstado.disabled = false;
        selectEstado.value = 'disponible';
    }
    if (notaEstado) notaEstado.classList.remove('visible');
}

function filtrarTabla(idTabla) {
    const tabla = document.getElementById(idTabla);
    if (!tabla) return;

    const inputTexto = document.querySelector(`[data-tabla-filtro="${idTabla}"]`);
    const selectEstado = document.querySelector(`[data-tabla-estado="${idTabla}"]`);

    const texto = inputTexto ? inputTexto.value.toLowerCase().trim() : '';
    const estado = selectEstado ? selectEstado.value.toLowerCase().trim() : '';

    const filas = tabla.querySelectorAll('tbody tr:not(.fila-vacia)');
    filas.forEach((fila) => {
        const contenidoFila = fila.textContent.toLowerCase();
        const estadoFila = (fila.getAttribute('data-estado') || '').toLowerCase();

        const coincideTexto = texto === '' || contenidoFila.includes(texto);
        const coincideEstado = estado === '' || estadoFila === estado;

        fila.style.display = (coincideTexto && coincideEstado) ? '' : 'none';
    });
}
