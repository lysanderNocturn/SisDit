<?php
// =====================================================
// OBTENER ESTADÍSTICAS (AJAX)
// Devuelve JSON con conteos de trámites por estatus.
// Usado por las tarjetas de estadísticas del dashboard admin
// =====================================================
/**
 * Obtener estadísticas actualizadas
 * Ruta: php/obtener_estadisticas.php
 */

require_once "db.php";
require_once "funciones_seguridad.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

// Obtener estadísticas
$total_tramites = $conn->query("SELECT COUNT(*) as total FROM tramites")->fetch_assoc()['total'];
$en_revision = $conn->query("SELECT COUNT(*) as total FROM tramites WHERE estatus = 'En revisión'")->fetch_assoc()['total'];
$aprobados_hoy = $conn->query("SELECT COUNT(*) as total FROM tramites WHERE estatus = 'Aprobado' AND DATE(created_at) = CURDATE()")->fetch_assoc()['total'];
$sin_fotografias = $conn->query("SELECT COUNT(*) as total FROM tramites WHERE estatus = 'En revisión' AND (foto1_archivo IS NULL OR foto1_archivo = '')")->fetch_assoc()['total'];

echo json_encode([
    'success' => true,
    'total_tramites' => $total_tramites,
    'en_revision' => $en_revision,
    'aprobados_hoy' => $aprobados_hoy,
    'sin_fotografias' => $sin_fotografias
]);