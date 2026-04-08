// =====================================================
// RESPONSIVE.JS — Utilidades globales del sistema
// Este archivo se carga en todos los dashboards.
// Maneja el menú responsive, validaciones, toasts,
// y un montón de helpers que usamos en todos lados.
// =====================================================

// =====================================================
// SIDEBAR EN MÓVIL
// En pantallas chicas el menú lateral se oculta y
// aparece un botón hamburguesa para abrirlo
// =====================================================
document.addEventListener('DOMContentLoaded', function() {

    const sidebar = document.querySelector('.sidebar');

    if (sidebar) {
        // Overlay oscuro detrás del menú cuando está abierto en móvil
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);

        // Botón de tres rayitas (hamburguesa) — lo creamos dinámicamente
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'sidebar-toggle';
        toggleBtn.innerHTML = '<span></span><span></span><span></span>';
        toggleBtn.setAttribute('aria-label', 'Toggle menu');
        document.body.appendChild(toggleBtn);

        // Abrir/cerrar el menú
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            // Bloquear scroll del body cuando el menú está abierto
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        }

        toggleBtn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar); // Cerrar al tocar el overlay

        // En móvil, cerrar el menú automáticamente al navegar a otra sección
        sidebar.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    toggleSidebar();
                }
            });
        });

        // Si el usuario agranda la ventana, ocultar el overlay (edge case)
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 992) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }, 250);
        });
    }
});

// =====================================================
// TABLAS RESPONSIVE
// Las tablas que no son DataTables las envolvemos
// en un div con scroll horizontal para móvil
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    const tables = document.querySelectorAll('table:not(.dataTable)');

    tables.forEach(table => {
        // Solo envolver si no tiene ya el wrapper
        if (!table.parentElement.classList.contains('table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });
});

// =====================================================
// BOTONES EN MÓVIL
// En pantallas muy chicas, los botones en grupos
// se hacen full-width para que sean más fáciles de tocar
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    function makeButtonsResponsive() {
        if (window.innerWidth <= 768) {
            document.querySelectorAll('.btn-group, .d-flex').forEach(group => {
                const buttons = group.querySelectorAll('.btn');
                if (buttons.length > 1) {
                    buttons.forEach(btn => btn.classList.add('btn-block-mobile'));
                }
            });
        }
    }

    makeButtonsResponsive();
    window.addEventListener('resize', makeButtonsResponsive);
});

// =====================================================
// TOOLTIPS EN MÓVIL
// En celular no existe el hover, así que los tooltips
// los hacemos que se activen con clic
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 768) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            el.setAttribute('data-bs-trigger', 'click');
        });
    }
});

// =====================================================
// CONFIRMACIONES GLOBALES
// Una función reutilizable para mostrar "¿estás seguro?"
// antes de ejecutar acciones destructivas
// =====================================================
window.confirmarAccion = function(mensaje, callback) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: mensaje,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#7b0f2b', // Color vino del municipio
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true // Cancelar a la izquierda, confirmar a la derecha
    }).then((result) => {
        if (result.isConfirmed && callback) {
            callback();
        }
    });
};

// =====================================================
// VALIDACIÓN DE FORMULARIOS
// Los formularios con clase .needs-validation muestran
// errores automáticamente con Bootstrap + SweetAlert2
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    Array.from(document.querySelectorAll('.needs-validation')).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();

                Swal.fire({
                    icon: 'error',
                    title: 'Error de validación',
                    text: 'Por favor completa todos los campos requeridos correctamente.',
                    confirmButtonColor: '#7b0f2b'
                });
            }

            form.classList.add('was-validated');
        }, false);
    });
});

// =====================================================
// LOADER GLOBAL
// Para mostrar un spinner mientras carga algo pesado
// Llamar mostrarLoader() al inicio, ocultarLoader() al terminar
// =====================================================
window.mostrarLoader = function() {
    Swal.fire({
        title: 'Cargando...',
        html: 'Por favor espera',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => Swal.showLoading()
    });
};

window.ocultarLoader = function() {
    Swal.close();
};

// =====================================================
// INPUTS DE ARCHIVO
// Mostrar el nombre del archivo seleccionado en el label
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function() {
            const fileName = this.files[0] ? this.files[0].name : 'Ningún archivo seleccionado';
            const label = this.nextElementSibling;

            if (label && label.classList.contains('custom-file-label')) {
                label.textContent = fileName;
            }
        });
    });
});

// =====================================================
// TOAST NOTIFICATIONS
// Notificaciones pequeñas en la esquina superior
// (para confirmaciones rápidas sin interrumpir el flujo)
// =====================================================
window.mostrarToast = function(mensaje, tipo = 'success') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    Toast.fire({ icon: tipo, title: mensaje });
};

// =====================================================
// AUTO-OCULTAR ALERTAS
// Las alertas normales de Bootstrap desaparecen solas
// después de 5 segundos (excepto las marcadas como permanentes)
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.alert:not(.alert-permanent)').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});

// =====================================================
// COPIAR AL PORTAPAPELES
// Para copiar folios, datos de contacto, etc.
// =====================================================
window.copiarAlPortapapeles = function(texto) {
    navigator.clipboard.writeText(texto)
        .then(() => mostrarToast('Copiado al portapapeles', 'success'))
        .catch(() => mostrarToast('Error al copiar', 'error'));
};

// =====================================================
// SCROLL SUAVE
// Los links internos (#sección) hacen scroll suave
// en lugar del salto brusco por default
// =====================================================
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href !== '#' && href !== '#!') {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });
});

// =====================================================
// PREVENIR DOBLE SUBMIT
// Si el usuario hace doble clic en "Guardar", el segundo
// clic se ignora para no mandar el formulario dos veces
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(form => {
        let isSubmitting = false;

        form.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }

            isSubmitting = true;

            // Cambiar el botón a estado de "cargando"
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';

                // Por si el formulario falla, restauramos el botón a los 5 segundos
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    isSubmitting = false;
                }, 5000);
            }
        });
    });
});

console.log('✅ Scripts responsive cargados correctamente');
