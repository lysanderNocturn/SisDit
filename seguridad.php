<?php
// =====================================================
// SEGURIDAD DE SESIÓN
// Este archivo se incluye al inicio de TODOS los
// dashboards internos (Dash.php, DashAdmin.php, etc.)
// Verifica que haya sesión válida, que no haya expirado
// y que la IP no haya cambiado (posible robo de sesión)
// =====================================================

// Cookies de sesión solo accesibles por HTTP (no por JS)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Headers para evitar que el navegador cachee páginas privadas
// Sin esto, el botón "Atrás" podría mostrar páginas después de logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Headers de seguridad adicionales
header("X-Content-Type-Options: nosniff");   // Evitar MIME sniffing
header("X-Frame-Options: SAMEORIGIN");       // Evitar clickjacking
header("X-XSS-Protection: 1; mode=block");  // Protección XSS básica

// Si no hay sesión activa, destruir todo y mandar al login
if (!isset($_SESSION['id']) || !isset($_SESSION['usuario'])) {
    session_unset();
    session_destroy();
    header("Location: acceso.php");
    exit();
}

// =====================================================
// REGENERAR ID DE SESIÓN PERIÓDICAMENTE
// Cada 5 minutos cambiamos el ID de sesión para
// dificultar ataques de fijación de sesión
// =====================================================
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// =====================================================
// TIMEOUT POR INACTIVIDAD
// Si el usuario lleva 30 minutos sin hacer nada,
// la sesión expira y lo mandamos al login
// =====================================================
$timeout_duration = 1800; // 30 minutos en segundos

if (isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: acceso.php?timeout=1");
    exit();
}

// Actualizar timestamp de última actividad en cada request
$_SESSION['last_activity'] = time();

// =====================================================
// VALIDACIÓN DE IP
// Si la IP del cliente cambia durante la sesión,
// podría ser un robo de cookie — cerramos la sesión
// (comentar esta parte si causa problemas con proxies
// o VPNs que rotan IPs frecuentemente)
// =====================================================
if (isset($_SESSION['ip_address'])) {
    if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        session_unset();
        session_destroy();
        header("Location: acceso.php?error=security");
        exit();
    }
} else {
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
}

// =====================================================
// FUNCIÓN: VERIFICAR ROL
// Para restringir secciones según el rol del usuario.
// Uso: verificarRol(['Administrador', 'Verificador'])
// =====================================================
function verificarRol($rolesPermitidos = []) {
    if (empty($rolesPermitidos)) return true;
    if (!isset($_SESSION['rol'])) return false;
    return in_array($_SESSION['rol'], $rolesPermitidos);
}

// Generar token CSRF si no existe en la sesión actual
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
