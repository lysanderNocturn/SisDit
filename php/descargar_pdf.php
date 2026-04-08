<?php
// =====================================================
// DESCARGAR PDF DE DOCUMENTO
// Sirve el archivo PDF como descarga forzada (Content-Disposition: attachment)
// =====================================================
/**
 * DESCARGA DE PDFs
 * Archivo: php/descargar_pdf.php
 * Descripción: Endpoint para descargar PDFs de trámites
 */

require_once "funciones_pdf.php";
require_once "funciones_seguridad.php";

session_start();

// Verificar autenticación
if (!isset($_SESSION['id'])) {
    die("No autenticado");
}

// Obtener ID del trámite
$tramite_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($tramite_id <= 0) {
    die("ID de trámite inválido");
}

// Verificar que el usuario tiene acceso al trámite
$sql = "SELECT t.id, t.usuario_creador_id, t.estatus 
        FROM tramites t 
        WHERE t.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tramite_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    die("Trámite no encontrado");
}

$tramite = $resultado->fetch_assoc();

// Verificar permisos:
// - El creador puede ver su trámite
// - Verificadores y administradores pueden ver todos
$puede_ver = (
    $tramite['usuario_creador_id'] == $_SESSION['id'] ||
    esVerificador() ||
    esAdministrador()
);

if (!$puede_ver) {
    die("No tiene permisos para ver este trámite");
}

// Solo se puede descargar PDF si está aprobado (o si es administrador)
if ($tramite['estatus'] !== 'Aprobado' && !esAdministrador()) {
    die("El trámite debe estar aprobado para descargar el PDF");
}

// Registrar descarga en logs
registrarLog($conn, $_SESSION['id'], 'Descargó PDF', 'tramites', $tramite_id);

// Generar y descargar PDF
descargarPDF($tramite_id);
?>
