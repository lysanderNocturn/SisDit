<?php
// =====================================================
// LOGOUT — Cerrar sesión
// Limpia la sesión completa y redirige al login.
// También registra el logout en los logs de actividad.
// =====================================================

session_start();
require_once "php/db.php";
require_once "php/funciones_seguridad.php";

// Registrar el logout en los logs antes de destruir la sesión
if (isset($_SESSION['id'])) {
    registrarLog($conn, $_SESSION['id'], 'Logout', 'usuarios', $_SESSION['id']);
}

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión del navegador
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destruir la sesión en el servidor
session_destroy();

// Redirigir al login con parámetro para mostrar mensaje de "sesión cerrada"
header("Location: acceso.php?logout=1");
exit();
