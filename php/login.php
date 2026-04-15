
<?php
// =====================================================
// LOGIN — Procesar inicio de sesión
// Recibe el correo y contraseña del formulario de
// acceso.php, los valida y redirige según el rol
// =====================================================

session_start();
require "db.php";
require "funciones_seguridad.php";

// Si alguien intenta abrir este archivo directamente
// sin haber enviado el formulario, lo mandamos de vuelta
if (!isset($_POST['correo']) || !isset($_POST['password'])) {
    header("Location: ../acceso.php");
    exit();
}

// Validar token CSRF para proteger contra ataques de formularios externos
if (!validarCSRF()) {
    header("Location: ../acceso.php?error=csrf");
    exit();
}

// Limpiar el correo de caracteres raros
$correo   = limpiarInput($_POST['correo']);
$password = trim($_POST['password']);

// Verificar formato de email antes de consultar la BD
if (!validarEmail($correo)) {
    header("Location: ../acceso.php?error=email_invalido");
    exit();
}

// Buscar el usuario en la BD
$sql = $conn->prepare("SELECT id, nombre, apellidos, password, rol, activo FROM usuarios WHERE correo=?");
$sql->bind_param("s", $correo);
$sql->execute();
$resultado = $sql->get_result();

if ($resultado->num_rows == 1) {
    $datos = $resultado->fetch_assoc();

    // Verificar que la cuenta esté activa (el admin puede desactivar usuarios)
    if ($datos['activo'] != 1) {
        header("Location: ../acceso.php?error=usuario_inactivo");
        exit();
    }

    // Verificar contraseña con password_verify (la contraseña está hasheada en BD)
    if (password_verify($password, $datos['password'])) {

        // Guardar datos del usuario en sesión
        $_SESSION['usuario']          = $datos['nombre'] . ' ' . $datos['apellidos'];
        $_SESSION['id']               = $datos['id'];
        $_SESSION['rol']              = $datos['rol'];
        $_SESSION['ip_address']       = $_SERVER['REMOTE_ADDR'];
        $_SESSION['last_activity']    = time();
        $_SESSION['last_regeneration'] = time();

        // Generar nuevo token CSRF para esta sesión
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Registrar fecha/hora del último acceso en BD
        $updateStmt = $conn->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
        $updateStmt->bind_param("i", $datos['id']);
        $updateStmt->execute();
        $updateStmt->close();

        // Registrar en logs de actividad
        registrarLog($conn, $datos['id'], 'Login exitoso', 'usuarios', $datos['id']);

        // Redirigir al dashboard que corresponde según el rol
        switch ($datos['rol']) {
            case 'Administrador':
                header("Location: ../DashAdmin.php");
                break;
            case 'Verificador':
                header("Location: ../DashVer.php");
                break;
            case 'Ventanilla':
                header("Location: ../DashVentanilla.php");
                break;
            case 'Usuario':
                header("Location: ../Dash.php");
                break;
            default:
                // Rol desconocido — no debería pasar, pero por si acaso
                header("Location: ../acceso.php?error=rol_invalido");
                break;
        }
        exit();

    } else {
        // Contraseña incorrecta — registramos el intento fallido
        registrarLog($conn, 0, 'Intento de login fallido', 'usuarios', null, "Email: $correo");
        header("Location: ../acceso.php?error=password_incorrecto");
        exit();
    }

} else {
    // Correo no encontrado en BD — también lo registramos
    registrarLog($conn, 0, 'Intento de login - usuario no existe', 'usuarios', null, "Email: $correo");
    header("Location: ../acceso.php?error=usuario_no_encontrado");
    exit();
}

$sql->close();
$conn->close();
