<?php
// =====================================================
// OBTENER DATOS DE UN TRÁMITE (AJAX)
// Devuelve JSON con los datos de un trámite por su folio.
// Usado por los modales de detalle en los dashboards
// =====================================================
/**
 * Obtener datos actualizados de un trámite
 * Ruta: php/obtener_datos_tramite.php
 */

require_once "db.php";
require_once "funciones_seguridad.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

if (!isset($_GET['folio'])) {
    echo json_encode(['success' => false, 'message' => 'Folio no proporcionado']);
    exit;
}

$folio = $_GET['folio'];

// Validar formato folio
if (!preg_match('/^(\d+)\/(\d{4})$/', $folio, $matches)) {
    echo json_encode(['success' => false, 'message' => 'Formato de folio inválido']);
    exit;
}

$folio_numero = (int) $matches[1];
$folio_anio = (int) $matches[2];

// Obtener datos actualizados del trámite
$sql = "SELECT 
            t.id, t.folio_numero, t.folio_anio, t.estatus,
            t.propietario, t.direccion, t.localidad, t.telefono, t.correo,
            t.ine_archivo, t.escrituras_archivo, t.titulo_archivo,
            t.predial_archivo, t.formato_constancia,
            t.foto1_archivo, t.foto2_archivo, t.croquis_archivo,
            t.comentario_sin_doc, t.numero_asignado, t.tipo_asignacion,
            t.referencia_anterior, t.entre_calles, t.cuenta_catastral,
            t.manzana, t.lote, t.fecha_constancia,
            t.tipo_tramite_id, tt.nombre AS tipo_tramite_nombre
        FROM tramites t
        LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
        WHERE t.folio_numero = ? AND t.folio_anio = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $folio_numero, $folio_anio);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Trámite no encontrado']);
    exit;
}

$tramite = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'tramite' => $tramite
]);