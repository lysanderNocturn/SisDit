<?php
// =====================================================
// ASIGNAR CROQUIS A TRÁMITE
// Asocia un archivo de croquis ya subido al trámite indicado
// =====================================================
/**
 * ASIGNAR CROQUIS DE TRÁMITE ANTERIOR AL ACTUAL
 * Reutiliza el archivo de croquis ya existente en uploads/
 * sin duplicarlo físicamente.
 */
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once "db.php";
require_once "funciones_seguridad.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesion expirada']);
    exit;
}
if (!esVerificador() && !esAdministrador() && !esVentanilla()) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

$folio_destino  = isset($_POST['folio_destino'])  ? trim($_POST['folio_destino'])  : '';
$croquis_archivo = isset($_POST['croquis_archivo']) ? trim($_POST['croquis_archivo']) : '';

// Validar folio destino (formato NNN/AAAA)
if (!preg_match('/^(\d{1,4})\/(\d{4})$/', $folio_destino, $m)) {
    echo json_encode(['success' => false, 'message' => 'Folio destino invalido']);
    exit;
}
$folio_numero = (int)$m[1];
$folio_anio   = (int)$m[2];

// Validar que el archivo existe
if (empty($croquis_archivo)) {
    echo json_encode(['success' => false, 'message' => 'Archivo de croquis no especificado']);
    exit;
}

$ruta = "../" . $croquis_archivo;
if (!file_exists($ruta)) {
    echo json_encode(['success' => false, 'message' => 'El archivo del croquis no existe']);
    exit;
}

// Actualizar el trámite destino con el mismo nombre de archivo
$stmt = $conn->prepare("UPDATE tramites SET croquis_archivo = ? WHERE folio_numero = ? AND folio_anio = ?");
$stmt->bind_param("sii", $croquis_archivo, $folio_numero, $folio_anio);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar en BD']);
    exit;
}
$stmt->close();

// Log
$uid = (int)$_SESSION['id'];
$ip  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$det = "Croquis anterior asignado al folio: $folio_destino | Archivo: $croquis_archivo";
$log = $conn->prepare("INSERT INTO logs_actividad (usuario_id, accion, tabla_afectada, detalles, ip_address) VALUES (?, 'Asignar croquis anterior', 'tramites', ?, ?)");
$log->bind_param("iss", $uid, $det, $ip);
$log->execute();
$log->close();

echo json_encode([
    'success'  => true,
    'message'  => 'Croquis asignado correctamente',
    'archivo'  => $croquis_archivo
]);
