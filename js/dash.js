// =====================================================
// MAPA INTERACTIVO CON LEAFLET + PROYECCIÓN UTM
// Este archivo maneja todo el mapa del formulario
// de nuevo trámite en el dashboard del usuario
// =====================================================

// Registrar la proyección UTM Zona 13 Norte
// Aguascalientes cae en la zona 13, así que usamos esa
proj4.defs('EPSG:32613', '+proj=utm +zone=13 +datum=WGS84 +units=m +no_defs');

// Centro del municipio de Rincón de Romos en coordenadas decimales
const CENTRO_MUNICIPIO = [22.228, -102.322];

// Inicializar el mapa con Leaflet
const map = L.map('mapa', {
    zoomControl: true,
    scrollWheelZoom: true,
    tap: true  // Para que funcione bien en iPad/tablet
}).setView(CENTRO_MUNICIPIO, 14);

// Capa de mapa base con OpenStreetMap (gratis y funciona offline en LAN)
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> colaboradores',
    maxZoom: 19
}).addTo(map);

// ---------------------------------------------------
// FIX: El mapa de Leaflet a veces no renderiza bien
// cuando está dentro de un tab o con display:none.
// Lo forzamos a recalcular su tamaño varias veces
// hasta que seguro ya esté visible.
// ---------------------------------------------------
[100, 300, 600, 1000, 2000].forEach(ms => setTimeout(() => { map.invalidateSize(); }, ms));

// También refrescamos cuando el mapa entra en la pantalla (scroll)
const mapaSection = document.getElementById('mapaa');
if (mapaSection) {
    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) {
            map.invalidateSize();
            setTimeout(() => { map.invalidateSize(); }, 300);
        }
    }, { threshold: 0.05 });
    observer.observe(mapaSection);
}

// Y también cuando el usuario hace clic en el link del menú "Mapa"
document.querySelectorAll('a[href="#mapaa"]').forEach(link => {
    link.addEventListener('click', () => {
        setTimeout(() => { map.invalidateSize(); }, 200);
        setTimeout(() => { map.invalidateSize(); }, 500);
    });
});

// Al cambiar tamaño de ventana también refrescamos
window.addEventListener('resize', () => { map.invalidateSize(); });

// ---------------------------------------------------
// Variables del marcador y los inputs de coordenadas
// OJO: Los inputs se llaman "lat" y "lng" en el HTML
// pero en realidad guardan UTM X e Y respectivamente
// (así quedó desde el principio, no lo cambiamos para
// no romper el PHP que los lee por ese nombre)
// ---------------------------------------------------
let marker;
const utmXInput = document.getElementById('lat');  // En BD se guarda como UTM X (Este)
const utmYInput = document.getElementById('lng');  // En BD se guarda como UTM Y (Norte)

// Muestra las coordenadas en el pequeño bloque debajo del mapa
function mostrarCoordsDisplay(utmX, utmY, lat, lon) {
    const display = document.getElementById('coords-display');
    const texto = document.getElementById('coords-texto');
    if (display && texto) {
        display.classList.remove('d-none');
        texto.textContent = `UTM X: ${parseFloat(utmX).toFixed(2)} | UTM Y: ${parseFloat(utmY).toFixed(2)} | Lat: ${parseFloat(lat).toFixed(5)}, Lon: ${parseFloat(lon).toFixed(5)}`;
    }
}

// Botón "Centrar" del mapa — regresa al municipio
function centrarMapa() {
    map.setView(CENTRO_MUNICIPIO, 14);
}

// ---------------------------------------------------
// CLIC EN EL MAPA
// Cuando el usuario hace clic, tomamos las coords en
// decimal (WGS84) y las convertimos a UTM Zona 13N
// para guardarlas en los campos del formulario
// ---------------------------------------------------
map.on('click', e => {
    var lat = e.latlng.lat.toFixed(5);
    var lon = e.latlng.lng.toFixed(5);

    // Conversión decimal → UTM Zona 13N
    var utmCoords = proj4('EPSG:4326', 'EPSG:32613', [parseFloat(lon), parseFloat(lat)]);
    var utmX = utmCoords[0].toFixed(2);
    var utmY = utmCoords[1].toFixed(2);

    // Llenar los campos del formulario
    utmXInput.value = utmX;
    utmYInput.value = utmY;

    mostrarCoordsDisplay(utmX, utmY, lat, lon);

    // Mover el marcador o crearlo si no existe
    if (marker) {
        marker.setLatLng(e.latlng);
    } else {
        // Marcador personalizado con el color vino del municipio
        marker = L.marker(e.latlng, {
            icon: L.divIcon({
                className: '',
                html: '<div style="background:var(--vino,#7b0f2b);width:14px;height:14px;border-radius:50%;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.4);"></div>',
                iconSize: [14, 14],
                iconAnchor: [7, 7]
            })
        }).addTo(map);
    }
});

// ---------------------------------------------------
// ACTUALIZAR MARCADOR DESDE LOS INPUTS
// Si el usuario escribe las coords manualmente en los
// campos, el marcador del mapa se mueve al lugar
// ---------------------------------------------------
function actualizarMarcadorDesdeUTM() {
    var utmX = parseFloat(utmXInput.value);
    var utmY = parseFloat(utmYInput.value);

    // Validar que sean valores UTM razonables
    if (!isNaN(utmX) && !isNaN(utmY) &&
        utmX >= 100000 && utmX <= 900000 &&
        utmY >= 0 && utmY <= 10000000)
    {
        // Convertir UTM Zona 13N → decimal para que Leaflet entienda
        var decCoords = proj4('EPSG:32613', 'EPSG:4326', [utmX, utmY]);
        var lng = decCoords[0];
        var lat = decCoords[1];

        var pos = [lat, lng];
        map.setView(pos, 16);
        marker ? marker.setLatLng(pos) : marker = L.marker(pos).addTo(map);
    }
}

utmXInput.addEventListener('change', actualizarMarcadorDesdeUTM);
utmYInput.addEventListener('change', actualizarMarcadorDesdeUTM);

// =====================================================
// FECHAS DEL FORMULARIO
// La fecha de ingreso se pone hoy por defecto y la
// de entrega no puede ser anterior a la de ingreso
// =====================================================

const fechaIngreso = document.getElementById('fechaIngreso');
const fechaEntrega = document.getElementById('fechaEntrega');

// Calcular fecha de hoy en formato YYYY-MM-DD
const hoy = new Date();
const yyyy = hoy.getFullYear();
const mm = String(hoy.getMonth() + 1).padStart(2, '0');
const dd = String(hoy.getDate()).padStart(2, '0');
const fechaHoy = `${yyyy}-${mm}-${dd}`;

fechaIngreso.value = fechaHoy;
fechaEntrega.min = fechaHoy;

// Si cambian la fecha de ingreso, ajustar el mínimo de entrega
fechaIngreso.addEventListener('change', () => {
    fechaEntrega.min = fechaIngreso.value;
});

// =====================================================
// ALERTAS DE ÉXITO / ERROR DESPUÉS DE GUARDAR
// El PHP redirige con ?ok=... o ?error=... en la URL
// Aquí los leemos y mostramos el SweetAlert2
// =====================================================
document.addEventListener("DOMContentLoaded", () => {
    const params = new URLSearchParams(window.location.search);

    if (params.has("ok")) {
        const folio = params.get("folio") || "";
        Swal.fire({
            icon: "success",
            title: "Trámite guardado",
            text: folio ? `Folio asignado: ${folio}` : "La información se registró correctamente",
            confirmButtonColor: "#198754"
        });
    }

    if (params.has("error_msg")) {
        // El PHP manda el mensaje de error directo en la URL
        const errorMsg = decodeURIComponent(params.get("error_msg"));
        Swal.fire("Error", errorMsg, "error");
    }

    if (params.has("error")) {
        const errorCode = params.get("error");
        let titulo = "Error";
        let texto = errorCode;
        let icono = "error";

        // Mensajes amigables para errores comunes
        switch (errorCode) {
            case "campos_obligatorios":
                titulo = "Faltan datos";
                texto = "Completa todos los campos obligatorios";
                icono = "warning";
                break;
            case "csrf":
                titulo = "Error de seguridad";
                texto = "Token de seguridad inválido. Recarga la página e intenta de nuevo.";
                break;
            default:
                titulo = "Error";
                texto = decodeURIComponent(errorCode);
                break;
        }

        Swal.fire(titulo, texto, icono);
    }

    // Limpiar los parámetros de la URL una vez leídos
    // (para que no aparezcan si el usuario recarga la página)
    if (params.toString()) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});

// =====================================================
// TABLA DE TRÁMITES CON DATATABLES
// La tabla de historial al fondo del dashboard
// =====================================================
$(document).ready(function() {
    if ($('#tablaTramites').length) {
        const numColumnas = $('#tablaTramites thead tr th').length;

        $('#tablaTramites').DataTable({
            "pageLength": 10,
            "lengthChange": true,
            "lengthMenu": [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Todos"]],
            "ordering": true,
            "responsive": true,
            "columnDefs": [
                // La última columna (Acciones) no se puede ordenar
                { "orderable": false, "targets": numColumnas - 1 }
            ],
            "language": {
                "paginate": {
                    "previous": "Anterior",
                    "next": "Siguiente",
                    "first": "Primero",
                    "last": "Último"
                },
                "info": "Mostrando _START_ a _END_ de _TOTAL_ trámites",
                "infoEmpty": "No hay trámites para mostrar",
                "infoFiltered": "(filtrado de _MAX_ trámites totales)",
                "zeroRecords": "No se encontraron resultados",
                "search": "Buscar:",
                "lengthMenu": "Mostrar _MENU_ trámites",
                "loadingRecords": "Cargando...",
                "processing": "Procesando..."
            },
            // Layout personalizado para que quede bien con Bootstrap
            "dom": '<"row mb-3"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row mt-3"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
        });
    }
});
