<?php
// =====================================================
// REGISTRO DE NUEVO USUARIO
// Procesa el formulario de registro de acceso.php
// Crea una solicitud pendiente que el admin debe aprobar
// =====================================================

require "db.php";
require "funciones_seguridad.php";
error_reporting(0);
ini_set('display_errors', 0);
session_start();

if (!validarCSRF()) {
    header("Location: ../acceso.php?error=" . urlencode("Token de seguridad invalido"));
    exit;
}

if (empty($_POST['nombre']) || empty($_POST['apellidos']) || empty($_POST['correo']) ||
    empty($_POST['password']) || empty($_POST['rol'])) {
    header("Location: ../acceso.php?error=" . urlencode("Completa todos los campos obligatorios"));
    exit;
}

try {
    $nombre    = limpiarInput($_POST['nombre']);
    $apellidos = limpiarInput($_POST['apellidos']);
    $correo    = limpiarInput($_POST['correo']);
    $telefono  = isset($_POST['telefono']) ? preg_replace('/\D/', '', $_POST['telefono']) : '';
    $password  = $_POST['password'];
    $rol       = limpiarInput($_POST['rol']);

    if (!validarEmail($correo))
        throw new Exception("El correo electronico no es valido");
    if (!soloLetras($nombre) || !soloLetras($apellidos))
        throw new Exception("Nombre y apellidos solo deben contener letras");
    // Validación de contraseña
    if (strlen($password) != 8)
        throw new Exception("La contraseña debe tener exactamente 8 caracteres");
    if (!preg_match('/[A-Z]/', $password))
        throw new Exception("La contraseña debe contener al menos una letra mayúscula");
    if (!preg_match('/[a-z]/', $password))
        throw new Exception("La contraseña debe contener al menos una letra minúscula");
    if (!preg_match('/[0-9]/', $password))
        throw new Exception("La contraseña debe contener al menos un número");
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password))
        throw new Exception("La contraseña debe contener al menos un símbolo (!@#$%^&*)");
    if (!in_array($rol, array('Usuario','Ventanilla','Verificador')))
        throw new Exception("Rol no valido");
    // Verificar correo duplicado en usuarios activos
    $chk = $conn->prepare("SELECT id FROM usuarios WHERE correo = ?");
    $chk->bind_param("s", $correo);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0)
        throw new Exception("El correo ya esta registrado en el sistema");
    $chk->close();

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // TODOS LOS ROLES: solicitud pendiente de aprobación del administrador
    $chkSol = $conn->prepare("SELECT id, estado FROM solicitudes_registro WHERE correo = ?");
    $chkSol->bind_param("s", $correo);
    $chkSol->execute();
    $resSol = $chkSol->get_result()->fetch_assoc();
    $chkSol->close();

    if ($resSol) {
        if ($resSol['estado'] === 'Pendiente')
            throw new Exception("Ya tienes una solicitud pendiente con ese correo. Espera la revisión del administrador.");
        if ($resSol['estado'] === 'Rechazado')
            throw new Exception("Tu solicitud fue rechazada. Contacta directamente al administrador.");
    }

    $stmt = $conn->prepare("INSERT INTO solicitudes_registro (nombre, apellidos, correo, password_hash, telefono, rol, estado) VALUES (?, ?, ?, ?, ?, ?, 'Pendiente')");
    $stmt->bind_param("ssssss", $nombre, $apellidos, $correo, $passwordHash, $telefono, $rol);
    if (!$stmt->execute()) throw new Exception("Error al enviar la solicitud. Intenta nuevamente");
    $solId = $conn->insert_id;
    $stmt->close();

        // No registramos log para solicitudes pendientes para evitar errores de clave foránea
    // El log se registrará cuando el administrador apruebe la solicitud y cree el usuario
    
    header("Location: ../acceso.php?ok=solicitud_enviada");

} catch (Exception $e) {
    error_log("Error registro.php: " . $e->getMessage());
    header("Location: ../acceso.php?error=" . urlencode($e->getMessage()));
    exit;
}
