<?php
// =====================================================
// CONEXIÓN A BASE DE DATOS
// Este archivo se incluye en casi todos los demás PHP.
// También tiene algunas funciones de utilidad para
// ejecutar queries y leer la configuración del sistema.
// =====================================================

// Zona horaria de México Central
// Sin esto las fechas se guardan con desfase en servidores en UTC
date_default_timezone_set('America/Mexico_City');

// En producción cambiar a 0 para no mostrar errores al usuario
// ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');
error_reporting(E_ALL);

// Credenciales de la base de datos
$host = "localhost";
$user = "root";
$pass = "";
$db   = "sistema";

try {
    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        // No mostrar detalles del error en producción, solo loguearlo
        error_log("Error de conexión: " . $conn->connect_error);
        die("Error al conectar con la base de datos. Por favor contacte al administrador.");
    }

    // UTF-8 full (mb4 para soportar emojis si hace falta)
    $conn->set_charset("utf8mb4");

    // Zona horaria también en MySQL (zona 6 = Centro México)
    $conn->query("SET time_zone = '-06:00'");

} catch (Exception $e) {
    error_log("Excepción en conexión DB: " . $e->getMessage());
    die("Error al conectar con la base de datos.");
}

// =====================================================
// CERRAR CONEXIÓN
// Se llama automáticamente al terminar el script
// gracias al register_shutdown_function de abajo
// =====================================================
function cerrarConexion() {
    global $conn;

    if ($conn instanceof mysqli) {
        try {
            // Verificar que la conexión sigue activa antes de cerrarla
            if ($conn->connect_errno === 0 && mysqli_ping($conn)) {
                $conn->close();
            }
        } catch (Throwable $e) {
            // Ya estaba cerrada o hubo error — ignorar
        }
    }
    $conn = null;
}

// =====================================================
// EJECUTAR QUERY CON PREPARED STATEMENT
// Función de utilidad para hacer queries más seguras.
// Uso: ejecutarQuery("SELECT * FROM tabla WHERE id = ?", [$id], "i")
// =====================================================
function ejecutarQuery($sql, $params = [], $types = "") {
    global $conn;

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Error en prepare: " . $conn->error);
        return false;
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $resultado = $stmt->execute();

    if (!$resultado) {
        error_log("Error en execute: " . $stmt->error);
        return false;
    }

    return $stmt;
}

// =====================================================
// LEER CONFIGURACIÓN DEL SISTEMA
// La tabla configuracion_sistema guarda valores como
// el nombre del municipio, el director, límites, etc.
// =====================================================
function obtenerConfiguracion($clave) {
    global $conn;

    $stmt = $conn->prepare("SELECT valor, tipo FROM configuracion_sistema WHERE clave = ?");
    $stmt->bind_param("s", $clave);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($row = $resultado->fetch_assoc()) {
        // Convertir el valor al tipo correcto según lo que diga la BD
        if ($row['tipo'] === 'numero') {
            return intval($row['valor']);
        } elseif ($row['tipo'] === 'boolean') {
            return $row['valor'] === '1' || $row['valor'] === 'true';
        } elseif ($row['tipo'] === 'json') {
            return json_decode($row['valor'], true);
        }
        return $row['valor'];
    }

    return null; // Clave no encontrada
}

// Registrar cierre de conexión al terminar el script
register_shutdown_function('cerrarConexion');
