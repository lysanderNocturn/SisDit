// =====================================================
// ADMIN.JS — Panel de administración
// Todo lo del dashboard del administrador: tablas,
// gestión de usuarios, configuración y logs
// =====================================================

// =====================================================
// DATATABLES — Tablas con paginación y búsqueda
// Inicializamos todas las tablas del panel admin
// =====================================================
$(document).ready(function() {

    // Tabla de usuarios activos
    $('#tablaUsuarios').DataTable({
        order: [[0, 'desc']], // Ordenar por ID descendente (más nuevos primero)
        pageLength: 10,
        language: {
            paginate: { previous: "Anterior", next: "Siguiente", first: "Primero", last: "Último" },
            info: "Mostrando _START_ a _END_ de _TOTAL_ usuarios",
            infoEmpty: "No hay usuarios para mostrar",
            infoFiltered: "(filtrado de _MAX_ usuarios totales)",
            zeroRecords: "No se encontraron resultados",
            search: "Buscar:",
            lengthMenu: "Mostrar _MENU_ usuarios",
            loadingRecords: "Cargando...",
            processing: "Procesando..."
        }
    });

    // Tabla de logs de actividad (muestra más porque son registros históricos)
    $('#tablaLogs').DataTable({
        order: [[0, 'desc']], // Los más recientes primero
        pageLength: 25,
        language: {
            paginate: { previous: "Anterior", next: "Siguiente", first: "Primero", last: "Último" },
            info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
            infoEmpty: "No hay registros para mostrar",
            infoFiltered: "(filtrado de _MAX_ registros totales)",
            zeroRecords: "No se encontraron resultados",
            search: "Buscar:",
            lengthMenu: "Mostrar _MENU_ registros",
            loadingRecords: "Cargando...",
            processing: "Procesando..."
        }
    });
});

// =====================================================
// GESTIÓN DE USUARIOS
// Funciones para crear, editar, activar/desactivar
// y eliminar usuarios desde el panel admin
// =====================================================

// Llenar el modal de edición con los datos del usuario seleccionado
// Se llama desde el botón "Editar" en cada fila de la tabla
function editarUsuario(id, nombre, apellidos, correo, rol, activo) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nombre').value = nombre;
    document.getElementById('edit_apellidos').value = apellidos;
    document.getElementById('edit_correo').value = correo;
    document.getElementById('edit_rol').value = rol;

    const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
    modal.show();
}

// Activar o desactivar un usuario (toggle)
// Mejor desactivar que eliminar para conservar historial
function toggleEstadoUsuario(id, estadoActual) {
    const nuevoEstado = estadoActual ? 0 : 1;
    const accion = nuevoEstado ? 'activar' : 'desactivar';

    Swal.fire({
        title: '¿Estás seguro?',
        text: `¿Deseas ${accion} este usuario?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('accion', 'toggle_estado');
            formData.append('id', id);
            formData.append('estado', nuevoEstado);
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            fetch('php/gestion_usuarios.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('¡Listo!', data.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'Error al procesar la solicitud', 'error');
                    console.error('Error toggle usuario:', err);
                });
        }
    });
}

// Eliminar usuario definitivamente
// Nota: el PHP podría rechazar esto si tiene trámites asociados
function eliminarUsuario(id) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "Esta acción no se puede deshacer. Considera desactivar el usuario en su lugar.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('accion', 'eliminar');
            formData.append('id', id);
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            fetch('php/gestion_usuarios.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('¡Eliminado!', data.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'Error al eliminar el usuario', 'error');
                    console.error('Error eliminar usuario:', err);
                });
        }
    });
}

// =====================================================
// FORMULARIOS CON FETCH (sin recargar la página)
// Los formularios del panel admin usan fetch en lugar
// de submit normal para dar feedback instantáneo
// =====================================================

// Formulario de configuración del sistema
var formConfig = document.getElementById('formConfiguracion');
if (formConfig) {
    formConfig.addEventListener('submit', function(e) {
        e.preventDefault();

        Swal.fire({
            title: 'Guardando...',
            text: 'Actualizando configuración del sistema',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('php/actualizar_configuracion.php', { method: 'POST', body: new FormData(this) })
            .then(res => res.json())
            .then(data => {
                Swal.close();
                data.success
                    ? Swal.fire('¡Guardado!', data.message, 'success')
                    : Swal.fire('Error', data.message, 'error');
            })
            .catch(err => {
                Swal.close();
                Swal.fire('Error', 'Error al guardar la configuración', 'error');
                console.error('Error config:', err);
            });
    });
}

// Formulario para crear nuevo usuario
var formNuevo = document.getElementById('formNuevoUsuario');
if (formNuevo) {
    formNuevo.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('accion', 'crear'); // Indicarle al PHP qué acción ejecutar

        Swal.fire({ title: 'Creando usuario...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        fetch('php/gestion_usuarios.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                Swal.close();
                data.success
                    ? Swal.fire('¡Creado!', data.message, 'success').then(() => location.reload())
                    : Swal.fire('Error', data.message, 'error');
            })
            .catch(err => {
                Swal.close();
                Swal.fire('Error', 'Error al crear el usuario', 'error');
                console.error('Error crear usuario:', err);
            });
    });
}

// Formulario para editar usuario existente
var formEditar = document.getElementById('formEditarUsuario');
if (formEditar) {
    formEditar.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('accion', 'editar');

        Swal.fire({ title: 'Actualizando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        fetch('php/gestion_usuarios.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                Swal.close();
                data.success
                    ? Swal.fire('¡Actualizado!', data.message, 'success').then(() => location.reload())
                    : Swal.fire('Error', data.message, 'error');
            })
            .catch(err => {
                Swal.close();
                Swal.fire('Error', 'Error al actualizar el usuario', 'error');
                console.error('Error editar usuario:', err);
            });
    });
}

// =====================================================
// FUNCIONES DE LOGS
// Para exportar y limpiar el historial de actividad
// =====================================================

// Exportar logs a CSV — la lógica real pendiente de implementar
function exportarLogs() {
    Swal.fire({
        title: 'Exportando logs...',
        text: 'Preparando archivo CSV',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    // TODO: hacer la llamada AJAX real para generar y descargar el CSV
    setTimeout(() => {
        Swal.fire('Completado', 'Logs exportados correctamente', 'success');
    }, 1500);
}

// Limpiar logs con más de 90 días para no llenar la BD
function limpiarLogsAntiguos() {
    Swal.fire({
        title: '¿Eliminar logs antiguos?',
        text: "Se eliminarán los registros de actividad con más de 90 días",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6'
    }).then((result) => {
        if (result.isConfirmed) {
            // TODO: hacer la llamada AJAX real para limpiar en BD
            Swal.fire('¡Limpiado!', 'Logs antiguos eliminados correctamente', 'success')
                .then(() => location.reload());
        }
    });
}
