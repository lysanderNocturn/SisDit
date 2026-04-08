<?php
// =====================================================
// ACTUALIZAR CONFIGURACIÓN DE CONSTANCIA (AJAX)
// Guarda los datos del encabezado oficial que aparece en los documentos impresos
// =====================================================
require "../seguridad.php";
require_once "funciones_seguridad.php";
require_once "db.php";

// Ventanilla, Verificador o Administrador pueden editar el formato de constancia
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['Ventanilla', 'Verificador', 'Administrador'])) {
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

// Claves permitidas para editar por este endpoint
$claves_permitidas = [
    'director_nombre',
    'constancia_reglamento_1',
    'constancia_reglamento_2',
    'constancia_reglamento_3',
    'constancia_reglamento_4',
];

$datos = $_POST['config'] ?? [];
if (empty($datos)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se recibieron datos']);
    exit();
}

$conn->begin_transaction();

try {
    foreach ($datos as $clave => $valor) {
        // Solo permitir claves autorizadas
        if (!in_array($clave, $claves_permitidas)) continue;

        $valor = trim($valor);
        // El nombre del director siempre se guarda en mayúsculas
        if ($clave === 'director_nombre') {
            $valor = mb_strtoupper($valor, 'UTF-8');
        }

        // Verificar si la clave ya existe
        $stmtCheck = $conn->prepare("SELECT id FROM configuracion_sistema WHERE clave = ?");
        $stmtCheck->bind_param("s", $clave);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        $stmtCheck->close();

        if ($resCheck->num_rows > 0) {
            $row = $resCheck->fetch_assoc();
            $id  = $row['id'];
            $stmtUpd = $conn->prepare("UPDATE configuracion_sistema SET valor = ?, updated_at = NOW() WHERE id = ?");
            $stmtUpd->bind_param("si", $valor, $id);
            $stmtUpd->execute();
            $stmtUpd->close();
        } else {
            // Insertar si no existe
            $stmtIns = $conn->prepare("INSERT INTO configuracion_sistema (clave, valor, descripcion, tipo) VALUES (?, ?, ?, 'texto')");
            $desc = 'Configuración constancia: ' . $clave;
            $stmtIns->bind_param("sss", $clave, $valor, $desc);
            $stmtIns->execute();
            $stmtIns->close();
        }
    }

    // Log de actividad
    $ip         = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $detalles   = "Actualizó formato de constancia de número oficial";
    $stmtLog = $conn->prepare("INSERT INTO logs_actividad (usuario_id, accion, detalles, ip_address, user_agent) VALUES (?, 'Actualizó config constancia', ?, ?, ?)");
    $stmtLog->bind_param("isss", $_SESSION['id'], $detalles, $ip, $user_agent);
    $stmtLog->execute();
    $stmtLog->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Configuración de constancia guardada correctamente']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
