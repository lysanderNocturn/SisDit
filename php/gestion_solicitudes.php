<?php
// =====================================================
// GESTIÓN DE SOLICITUDES DE REGISTRO
// Solo accesible por el Administrador.
// Aprueba o rechaza solicitudes pendientes y genera links de notificación
// =====================================================
/**
 * GESTION DE SOLICITUDES DE REGISTRO
 * Aprueba o rechaza solicitudes de Ventanilla/Verificador
 * Envia notificacion por WhatsApp y correo al aprobar
 */
error_reporting(0);
ini_set('display_errors', 0);
if (ob_get_length()) ob_clean();

ini_set('session.cookie_httponly', 1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once "db.php";
require_once "funciones_seguridad.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    echo json_encode(array('success'=>false,'message'=>'Sesion expirada'));
    exit;
}
if (!esAdministrador()) {
    echo json_encode(array('success'=>false,'message'=>'Sin permisos'));
    exit;
}

$accion    = isset($_POST['accion'])    ? trim($_POST['accion'])    : '';
$sol_id    = isset($_POST['sol_id'])    ? (int)$_POST['sol_id']    : 0;
$motivo    = isset($_POST['motivo'])    ? trim($_POST['motivo'])    : '';

if (!in_array($accion, array('aprobar','rechazar')) || $sol_id <= 0) {
    echo json_encode(array('success'=>false,'message'=>'Datos invalidos'));
    exit;
}

try {
    // Obtener solicitud
    $stmt = $conn->prepare("SELECT * FROM solicitudes_registro WHERE id = ? AND estado = 'Pendiente' LIMIT 1");
    $stmt->bind_param("i", $sol_id);
    $stmt->execute();
    $sol = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sol) {
        echo json_encode(array('success'=>false,'message'=>'Solicitud no encontrada o ya procesada'));
        exit;
    }

    $admin_id = (int)$_SESSION['id'];

    if ($accion === 'aprobar') {
        // Verificar que el correo no exista ya en usuarios
        $chk = $conn->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $chk->bind_param("s", $sol['correo']);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $chk->close();
            throw new Exception("El correo ya existe en el sistema");
        }
        $chk->close();
        $conn->begin_transaction();
        // Crear usuario activo
        $ins = $conn->prepare("INSERT INTO usuarios (nombre, apellidos, correo, password, rol, activo) VALUES (?, ?, ?, ?, ?, 1)");
        $ins->bind_param("sssss", $sol['nombre'], $sol['apellidos'], $sol['correo'], $sol['password_hash'], $sol['rol']);
        if (!$ins->execute()) throw new Exception("Error al crear usuario: " . $conn->error);
        $nuevo_id = $ins->insert_id;
        $ins->close();
        // Actualizar solicitud
        $upd = $conn->prepare("UPDATE solicitudes_registro SET estado='Aprobado', fecha_resolucion=NOW(), resuelto_por=? WHERE id=?");
        $upd->bind_param("ii", $admin_id, $sol_id);
        $upd->execute();
        $upd->close();
        // Log
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $det = "Solicitud aprobada: " . $sol['nombre'] . " " . $sol['apellidos'] . " / Rol: " . $sol['rol'];
        $log = $conn->prepare("INSERT INTO logs_actividad (usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent) VALUES (?, 'Aprobo solicitud registro', 'solicitudes_registro', ?, ?, ?, ?)");
        $log->bind_param("iisss", $admin_id, $sol_id, $det, $ip, $ua);
        $log->execute();
        $log->close();

        $conn->commit();

        // Generar links de notificacion
        $nombre_completo = $sol['nombre'] . ' ' . $sol['apellidos'];
        $primer_nombre   = explode(' ', $sol['nombre'])[0];
        $msg = "Hola " . $primer_nombre . ", tu solicitud para acceder al Sistema Georreferenciado fue APROBADA.\n\n" .
               "Tus datos de acceso son:\n" .
               "Correo: " . $sol['correo'] . "\n" .
               "Contrasena: la que registraste en tu solicitud\n" .
               "Rol: " . $sol['rol'] . "\n\n" .
               "Ingresa al portal SisDit con tu correo y contraseña.\n\n" .
               "-- Direccion de Planeacion y D.U.";

        $asunto = "Tu acceso al Sistema Georreferenciado fue aprobado";
        $tel    = preg_replace('/\D/', '', isset($sol['telefono']) ? $sol['telefono'] : '');
        $wa_link = $tel ? "https://wa.me/52" . $tel . "?text=" . rawurlencode($msg) : null;
        $gm_link = !empty($sol['correo'])
            ? "mailto:" . $sol['correo'] . "?subject=" . rawurlencode($asunto) . "&body=" . rawurlencode($msg)
            : null;

        echo json_encode(array(
            'success'    => true,
            'message'    => 'Solicitud aprobada. Usuario creado correctamente.',
            'accion'     => 'aprobado',
            'nombre'     => $nombre_completo,
            'correo'     => $sol['correo'],
            'telefono'   => $sol['telefono'],
            'wa_link'    => $wa_link,
            'gm_link'    => $gm_link,
            'mensaje'    => $msg
        ));

    } else {
        // RECHAZAR
        $upd = $conn->prepare("UPDATE solicitudes_registro SET estado='Rechazado', motivo_rechazo=?, fecha_resolucion=NOW(), resuelto_por=? WHERE id=?");
        $upd->bind_param("sii", $motivo, $admin_id, $sol_id);
        $upd->execute();
        $upd->close();

        $ip  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $ua  = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $det = "Solicitud rechazada: " . $sol['nombre'] . " " . $sol['apellidos'] . " | Motivo: " . $motivo;
        $log = $conn->prepare("INSERT INTO logs_actividad (usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent) VALUES (?, 'Rechazo solicitud registro', 'solicitudes_registro', ?, ?, ?, ?)");
        $log->bind_param("iisss", $admin_id, $sol_id, $det, $ip, $ua);
        $log->execute();
        $log->close();

        // Notificacion de rechazo
        $primer_nombre = explode(' ', $sol['nombre'])[0];
        $msg_rec = "Hola " . $primer_nombre . ", lamentamos informarte que tu solicitud para el rol de " . $sol['rol'] .
                   " en el Sistema Georreferenciado fue RECHAZADA." .
                   ($motivo ? "\nMotivo: " . $motivo : "") .
                   "\n\nSi tienes dudas, contacta directamente a la Direccion de Planeacion y D.U.";
        $asunto_rec = "Solicitud de acceso rechazada - Sistema Georreferenciado";
        $tel    = preg_replace('/\D/', '', isset($sol['telefono']) ? $sol['telefono'] : '');
        $wa_link = $tel ? "https://wa.me/52" . $tel . "?text=" . rawurlencode($msg_rec) : null;
        $gm_link = !empty($sol['correo'])
            ? "mailto:" . $sol['correo'] . "?subject=" . rawurlencode($asunto_rec) . "&body=" . rawurlencode($msg_rec)
            : null;

        echo json_encode(array(
            'success'  => true,
            'message'  => 'Solicitud rechazada.',
            'accion'   => 'rechazado',
            'nombre'   => $sol['nombre'] . ' ' . $sol['apellidos'],
            'correo'   => $sol['correo'],
            'telefono' => $sol['telefono'],
            'wa_link'  => $wa_link,
            'gm_link'  => $gm_link,
            'mensaje'  => $msg_rec
        ));
    }

} catch (Exception $e) {
    if ($conn->in_transaction) $conn->rollback();
    error_log("gestion_solicitudes: " . $e->getMessage());
    echo json_encode(array('success'=>false,'message'=>$e->getMessage()));
}
