<?php
// =====================================================
// ACTUALIZAR CONFIGURACIÓN DEL SISTEMA (AJAX)
// Guarda los valores editados en el panel de configuración del admin
// =====================================================
require "../seguridad.php";
require_once "funciones_seguridad.php";
require_once "db.php";

// Solo administradores pueden acceder
if(!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador'){
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

// Validar CSRF
if (!isset($_POST['csrf_token']) || !validarCSRF($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit();
}

$config_data = $_POST['config'] ?? [];

if(empty($config_data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se recibieron datos de configuración']);
    exit();
}

$conn->begin_transaction();

try {
    foreach($config_data as $id => $valor) {
        $id = intval($id);
        $valor = trim($valor);
        
        $stmt = $conn->prepare("UPDATE configuracion_sistema SET valor = ? WHERE id = ?");
        $stmt->bind_param("si", $valor, $id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Registrar en logs
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $detalles = "Actualizó " . count($config_data) . " parámetros de configuración";
    
    $stmt = $conn->prepare("INSERT INTO logs_actividad (usuario_id, accion, detalles, ip_address, user_agent) VALUES (?, 'Actualizó configuración', ?, ?, ?)");
    $stmt->bind_param("isss", $_SESSION['id'], $detalles, $ip, $user_agent);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Configuración actualizada correctamente']);
    
} catch(Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar la configuración: ' . $e->getMessage()]);
}
