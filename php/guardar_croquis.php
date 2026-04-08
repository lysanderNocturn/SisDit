<?php
// =====================================================
// GUARDAR IMAGEN DE CROQUIS (AJAX)
// Recibe la imagen del croquis desde el modal del verificador
// y la guarda en uploads/ asociándola al trámite
// =====================================================
/**
 * GUARDAR CROQUIS DE CONSTANCIA
 * Recibe la imagen del croquis y la guarda en la BD
 */
error_reporting(0);
ini_set('display_errors', 0);
if (ob_get_length()) ob_clean();
if (session_status() === PHP_SESSION_NONE) session_start();

require_once "db.php";
require_once "funciones_seguridad.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    echo json_encode(array('success'=>false,'message'=>'Sesion expirada'));
    exit;
}
if (!esVerificador() && !esAdministrador() && !esVentanilla()) {
    echo json_encode(array('success'=>false,'message'=>'Sin permisos'));
    exit;
}

$folio = isset($_POST['folio']) ? trim($_POST['folio']) : '';
if (!preg_match('/^(\d{1,4})\/(\d{4})$/', $folio, $m)) {
    echo json_encode(array('success'=>false,'message'=>'Folio invalido'));
    exit;
}
$folio_numero = (int)$m[1];
$folio_anio   = (int)$m[2];

// Verificar que venga archivo
if (!isset($_FILES['croquis']) || $_FILES['croquis']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(array('success'=>false,'message'=>'No se recibio imagen'));
    exit;
}

$ext = strtolower(pathinfo($_FILES['croquis']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, array('jpg','jpeg','png','webp'))) {
    echo json_encode(array('success'=>false,'message'=>'Solo se permiten imagenes JPG, PNG o WEBP'));
    exit;
}
if ($_FILES['croquis']['size'] > 10485760) { // 10MB
    echo json_encode(array('success'=>false,'message'=>'La imagen no debe superar 10MB'));
    exit;
}

$carpeta = "../uploads/";
if (!is_dir($carpeta)) mkdir($carpeta, 0755, true);

$nombre = 'croquis_' . uniqid() . '_' . time() . '.' . $ext;
if (!move_uploaded_file($_FILES['croquis']['tmp_name'], $carpeta . $nombre)) {
    echo json_encode(array('success'=>false,'message'=>'Error al guardar la imagen'));
    exit;
}

// Actualizar en BD
$stmt = $conn->prepare("UPDATE tramites SET croquis_archivo=? WHERE folio_numero=? AND folio_anio=?");
$stmt->bind_param("sii", $nombre, $folio_numero, $folio_anio);
if (!$stmt->execute()) {
    echo json_encode(array('success'=>false,'message'=>'Error al guardar en BD'));
    exit;
}
$stmt->close();

// Log
$uid = (int)$_SESSION['id'];
$ip  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$det = "Croquis cargado para folio: $folio | Archivo: $nombre";
$log = $conn->prepare("INSERT INTO logs_actividad (usuario_id, accion, tabla_afectada, detalles, ip_address) VALUES (?, 'Croquis constancia', 'tramites', ?, ?)");
$log->bind_param("iss", $uid, $det, $ip);
$log->execute();
$log->close();

echo json_encode(array(
    'success'  => true,
    'message'  => 'Croquis guardado correctamente',
    'archivo'  => $nombre,
    'url'      => '../uploads/' . $nombre
));
