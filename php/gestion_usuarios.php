<?php
// =====================================================
// GESTIÓN DE USUARIOS
// Solo accesible por el Administrador.
// CRUD completo: crear, editar, activar/desactivar y eliminar usuarios
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
if (!validarCSRF()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit();
}

$accion = $_POST['accion'] ?? '';

switch($accion) {
    case 'crear':
        crearUsuario();
        break;
    case 'editar':
        editarUsuario();
        break;
    case 'toggle_estado':
        toggleEstadoUsuario();
        break;
    case 'eliminar':
        eliminarUsuario();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

function crearUsuario() {
    global $conn;
    
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? 'Usuario';
    
    // Validaciones
    if(empty($nombre) || empty($apellidos) || empty($correo) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
        return;
    }
    
    if(strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres']);
        return;
    }
    
    if(!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Correo electrónico no válido']);
        return;
    }
    
    $roles_validos = ['Usuario', 'Ventanilla', 'Verificador', 'Administrador'];
    if(!in_array($rol, $roles_validos)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Rol no válido']);
        return;
    }
    
    // Verificar si el correo ya existe
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $stmt->store_result();
    
    if($stmt->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está registrado']);
        return;
    }
    $stmt->close();
    
    // Crear usuario
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, apellidos, correo, password, rol, activo) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->bind_param("sssss", $nombre, $apellidos, $correo, $password_hash, $rol);
    
    if($stmt->execute()) {
        $nuevo_id = $stmt->insert_id;
        
        // Registrar en logs
        registrarLog($conn, $_SESSION['id'], 'Creó usuario', 'usuarios', $nuevo_id, "Usuario: $nombre $apellidos ($rol)");
        
        echo json_encode(['success' => true, 'message' => 'Usuario creado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear el usuario']);
    }
    
    $stmt->close();
}

function editarUsuario() {
    global $conn;
    
    $id = intval($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? 'Usuario';
    
    // Validaciones
    if(empty($id) || empty($nombre) || empty($apellidos) || empty($correo)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
        return;
    }
    
    if(!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Correo electrónico no válido']);
        return;
    }
    
    $roles_validos = ['Usuario', 'Ventanilla', 'Verificador', 'Administrador'];
    if(!in_array($rol, $roles_validos)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Rol no válido']);
        return;
    }
    
    // Verificar si el correo ya existe en otro usuario
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE correo = ? AND id != ?");
    $stmt->bind_param("si", $correo, $id);
    $stmt->execute();
    $stmt->store_result();
    
    if($stmt->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está en uso por otro usuario']);
        return;
    }
    $stmt->close();
    
    // Actualizar usuario
    if(!empty($password)) {
        if(strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres']);
            return;
        }
        
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, apellidos = ?, correo = ?, password = ?, rol = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $nombre, $apellidos, $correo, $password_hash, $rol, $id);
    } else {
        $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, apellidos = ?, correo = ?, rol = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $nombre, $apellidos, $correo, $rol, $id);
    }
    
    if($stmt->execute()) {
        // Registrar en logs
        registrarLog($conn, $_SESSION['id'], 'Actualizó usuario', 'usuarios', $id, "Usuario: $nombre $apellidos ($rol)");
        
        echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el usuario']);
    }
    
    $stmt->close();
}

function toggleEstadoUsuario() {
    global $conn;
    
    $id = intval($_POST['id'] ?? 0);
    $nuevo_estado = intval($_POST['estado'] ?? 0);
    
    if(empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de usuario no válido']);
        return;
    }
    
    // No permitir desactivar el propio usuario
    if($id == $_SESSION['id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No puedes desactivar tu propia cuenta']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
    $stmt->bind_param("ii", $nuevo_estado, $id);
    
    if($stmt->execute()) {
        $accion_texto = $nuevo_estado ? 'Activó usuario' : 'Desactivó usuario';
        registrarLog($conn, $_SESSION['id'], $accion_texto, 'usuarios', $id, "ID: $id");
        
        echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado']);
    }
    
    $stmt->close();
}

function eliminarUsuario() {
    global $conn;
    
    $id = intval($_POST['id'] ?? 0);
    
    if(empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de usuario no válido']);
        return;
    }
    
    // No permitir eliminar el propio usuario
    if($id == $_SESSION['id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No puedes eliminar tu propia cuenta']);
        return;
    }
    
    // Verificar si el usuario tiene trámites asociados
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tramites WHERE usuario_creador_id = ? OR aprobado_por = ?");
    $stmt->bind_param("ii", $id, $id);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    
    if($resultado['total'] > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar el usuario porque tiene trámites asociados. Considera desactivarlo en su lugar.']);
        return;
    }
    $stmt->close();
    
    // Eliminar usuario
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if($stmt->execute()) {
        registrarLog($conn, $_SESSION['id'], 'Eliminó usuario', 'usuarios', $id, "ID: $id");
        
        echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el usuario']);
    }
    
    $stmt->close();
}

// registrarLog ya esta definida en funciones_seguridad.php
