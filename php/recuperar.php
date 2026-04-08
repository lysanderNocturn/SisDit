<?php
// =====================================================
// RECUPERACIÓN DE CONTRASEÑA
// Genera un token de recuperación y manda el link por correo
// =====================================================
/**
 * RECUPERAR CONTRASEÑA
 * Archivo: php/recuperar.php
 * Descripción: Genera token y envía enlace de recuperación por correo
 */

require 'db.php';
session_start();

if(!isset($_GET['correo'])){
    header("Location: ../acceso.php?error=campos_obligatorios");
    exit();
}

$correo = trim($_GET['correo']);

if(!filter_var($correo, FILTER_VALIDATE_EMAIL)){
    header("Location: ../acceso.php?error=email_invalido");
    exit();
}

// Buscar usuario
$stmt = $conn->prepare("SELECT id, nombre FROM usuarios WHERE correo = ? AND activo = 1");
$stmt->bind_param("s", $correo);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){
    // Por seguridad, no revelar si el correo existe o no
    // Mostrar mensaje genérico
    $_SESSION['recuperar_msg'] = "Si el correo existe en nuestro sistema, recibirás un enlace de recuperación.";
    header("Location: ../acceso.php?ok=correo_procesado");
    exit();
}

$usuario = $result->fetch_assoc();

// Generar token seguro
$token = bin2hex(random_bytes(50));
$expira = date("Y-m-d H:i:s", strtotime("+1 hour"));

// Guardar token en la base de datos
$stmt = $conn->prepare("UPDATE usuarios SET token_recuperacion=?, token_expira=? WHERE correo=?");
$stmt->bind_param("sss", $token, $expira, $correo);

if(!$stmt->execute()){
    error_log("Error al guardar token de recuperación: " . $stmt->error);
    header("Location: ../acceso.php?error=error_sistema");
    exit();
}

// Obtener la URL base del servidor
$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$path = dirname(dirname($_SERVER['REQUEST_URI'])); // Subir un nivel desde /php/
$enlace = "$protocolo://$host$path/php/reset_password.php?token=$token";

// Configurar correo
$asunto = "Recuperación de contraseña - Sistema Georreferenciado";
$mensaje = "
Hola {$usuario['nombre']},

Has solicitado recuperar tu contraseña del Sistema Georreferenciado Municipal.

Haz clic en el siguiente enlace para establecer una nueva contraseña:

$enlace

Este enlace expira en 1 hora.

Si no solicitaste este cambio, puedes ignorar este correo.

Saludos,
Sistema Georreferenciado Municipal
Rincón de Romos
";

$cabeceras = "From: noreply@sistema-geo.com\r\n";
$cabeceras .= "Reply-To: dir.planeacionydu@gmail.com\r\n";
$cabeceras .= "Content-Type: text/plain; charset=UTF-8\r\n";
$cabeceras .= "X-Mailer: PHP/" . phpversion();

// Intentar enviar correo
$correo_enviado = @mail($correo, $asunto, $mensaje, $cabeceras);

// Guardar mensaje y enlace para mostrar en desarrollo
// En producción, solo mostrar mensaje genérico
$_SESSION['recuperar_token'] = $token; // Solo para desarrollo/pruebas
$_SESSION['recuperar_enlace'] = $enlace; // Solo para desarrollo/pruebas

if($correo_enviado){
    $_SESSION['recuperar_msg'] = "Se ha enviado un enlace de recuperación a tu correo electrónico.";
    header("Location: ../acceso.php?ok=correo_enviado");
}else{
    // En desarrollo, mostrar el enlace aunque el correo no se envíe
    $_SESSION['recuperar_msg'] = "El servidor de correo no está configurado. En modo desarrollo, usa este enlace:";
    $_SESSION['recuperar_enlace_mostrar'] = $enlace;
    header("Location: ../acceso.php?ok=correo_dev");
}
exit();
