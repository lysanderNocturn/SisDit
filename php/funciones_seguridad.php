<?php
// =====================================================
// FUNCIONES DE SEGURIDAD Y VALIDACIÓN
// Aquí están todas las funciones que se usan en
// múltiples archivos para validar, limpiar y
// sanitizar datos antes de meterlos a la BD
// =====================================================

// =====================================================
// LIMPIAR INPUTS
// Siempre pasar los datos del usuario por aquí antes
// de usarlos — quita espacios, barras y escapa HTML
// =====================================================
function limpiarInput($data) {
    if (is_array($data)) {
        return array_map('limpiarInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// =====================================================
// VALIDACIONES DE FORMATO
// =====================================================

// Email con el filtro nativo de PHP
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Teléfono a 10 dígitos (México)
function validarTelefono($telefono) {
    $telefono = preg_replace('/[^0-9]/', '', $telefono);
    return strlen($telefono) >= 10 && strlen($telefono) <= 15;
}

// Coordenadas UTM — las columnas se llaman lat/lng en BD pero guardan UTM X/Y
// Rango válido para México zona 13N
function validarCoordenadas($lat, $lng) {
    $lat = floatval($lat); // UTM X (Este)
    $lng = floatval($lng); // UTM Y (Norte)

    if ($lat < 100000 || $lat > 900000) {
        return ['valido' => false, 'mensaje' => 'Coordenada X (UTM) inválida'];
    }
    if ($lng < 0 || $lng > 10000000) {
        return ['valido' => false, 'mensaje' => 'Coordenada Y (UTM) inválida'];
    }

    return ['valido' => true, 'lat' => $lat, 'lng' => $lng];
}

// Folio en formato número/año — ej: 001/2025
function validarFormatoFolio($folio) {
    if (!preg_match('/^(\d{1,3})\/(\d{4})$/', $folio, $matches)) {
        return ['valido' => false, 'mensaje' => 'Formato de folio inválido. Debe ser: número/año'];
    }
    return [
        'valido'       => true,
        'folio_numero' => intval($matches[1]),
        'folio_anio'   => intval($matches[2])
    ];
}

// =====================================================
// VALIDACIÓN DE ARCHIVOS SUBIDOS
// Verifica extensión, tamaño Y tipo MIME real del archivo
// (no solo el nombre — alguien podría renombrar un .php a .jpg)
// =====================================================
function validarArchivo($archivo, $tiposPermitidos = ['pdf', 'jpg', 'jpeg', 'png']) {
    if (!isset($archivo) || $archivo['error'] !== UPLOAD_ERR_OK) {
        return ['valido' => false, 'mensaje' => 'Error al subir el archivo'];
    }

    // Máximo 5MB
    $maxSize = 5242880;
    if ($archivo['size'] > $maxSize) {
        return ['valido' => false, 'mensaje' => 'El archivo es demasiado grande. Máximo 5MB'];
    }

    // Verificar extensión
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $tiposPermitidos)) {
        return ['valido' => false, 'mensaje' => 'Tipo de archivo no permitido. Solo: ' . implode(', ', $tiposPermitidos)];
    }

    // Verificar tipo MIME real del archivo (más confiable que solo la extensión)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);

    $mimePermitidos = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($mimeType, $mimePermitidos)) {
        return ['valido' => false, 'mensaje' => 'El contenido del archivo no corresponde a un formato permitido'];
    }

    // Si es imagen, verificar que realmente sea una imagen válida
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        $imageInfo = @getimagesize($archivo['tmp_name']);
        if ($imageInfo === false) {
            return ['valido' => false, 'mensaje' => 'El archivo no es una imagen válida'];
        }
    }

    return ['valido' => true, 'extension' => $extension, 'mime' => $mimeType];
}

// Nombre de archivo único para evitar colisiones y caracteres problemáticos
function generarNombreUnico($prefijo, $extension) {
    return $prefijo . '_' . uniqid() . '_' . time() . '.' . $extension;
}

// =====================================================
// CREAR CARPETA SEGURA
// Crea la carpeta con permisos correctos y agrega
// un .htaccess para que PHP no ejecute archivos ahí
// =====================================================
function crearCarpetaSegura($ruta) {
    if (!is_dir($ruta)) {
        mkdir($ruta, 0755, true);

        // El .htaccess evita que un archivo malicioso subido se ejecute como PHP
        $htaccess  = "Options -Indexes\n";
        $htaccess .= "AddType application/octet-stream .php .phtml .php3 .php4 .php5\n";
        $htaccess .= "php_flag engine off";

        file_put_contents($ruta . '.htaccess', $htaccess);
    }
    return true;
}

// =====================================================
// REGISTRAR ACTIVIDAD EN LOGS
// Guarda un registro de cada acción importante.
// usuario_id = 0 se convierte a NULL para no romper
// la clave foránea cuando es un usuario no autenticado
// =====================================================
function registrarLog($conn, $usuario_id, $accion, $tabla = null, $registro_id = null, $detalles = null) {
    $ip         = $_SERVER['REMOTE_ADDR']     ?? 'desconocida';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'desconocido';

    // Convertir 0 a NULL para no violar la FK con la tabla usuarios
    if ($usuario_id == 0 || $usuario_id === '0') {
        $usuario_id = null;
    }

    $sql  = "INSERT INTO logs_actividad (usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssss", $usuario_id, $accion, $tabla, $registro_id, $detalles, $ip, $user_agent);

    try {
        $stmt->execute();
    } catch (Exception $e) {
        // Si falla el log, no detenemos la ejecución — solo lo registramos en el error_log
        error_log("Error en registrarLog: " . $e->getMessage());
    }

    $stmt->close();
}

// =====================================================
// CSRF — Protección contra ataques de formularios externos
// =====================================================

// Validar que el token del formulario coincida con el de la sesión
function validarCSRF() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return $_POST['csrf_token'] === $_SESSION['csrf_token'];
}

// Generar token CSRF (se crea si no existe)
function generarCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// =====================================================
// OBTENER IP REAL DEL CLIENTE
// Considera proxies y balanceadores de carga
// =====================================================
function obtenerIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
    }
}

// Validar fecha en el formato esperado
function validarFecha($fecha, $formato = 'Y-m-d') {
    $d = DateTime::createFromFormat($formato, $fecha);
    return $d && $d->format($formato) === $fecha;
}

// =====================================================
// JERARQUÍA DE ROLES
// Cuánto "poder" tiene cada rol en el sistema
// =====================================================

// Verificar si el rol del usuario tiene suficiente nivel
function tienePermiso($rolRequerido) {
    if (!isset($_SESSION['rol'])) return false;

    $jerarquia = [
        'Usuario'       => 1,
        'Ventanilla'    => 2,
        'Verificador'   => 3,
        'Administrador' => 4,
    ];

    $rolUsuario = $_SESSION['rol'];
    if (!isset($jerarquia[$rolUsuario]) || !isset($jerarquia[$rolRequerido])) return false;

    return $jerarquia[$rolUsuario] >= $jerarquia[$rolRequerido];
}

// Shortcuts para los roles más usados
function esAdministrador() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
}

// Verificador incluye también al Administrador
function esVerificador() {
    return isset($_SESSION['rol']) &&
           ($_SESSION['rol'] === 'Verificador' || $_SESSION['rol'] === 'Administrador');
}

// Ventanilla incluye también al Administrador
function esVentanilla() {
    return isset($_SESSION['rol']) &&
           ($_SESSION['rol'] === 'Ventanilla' || $_SESSION['rol'] === 'Administrador');
}

// =====================================================
// HELPERS DE TEXTO
// =====================================================

// Escape para evitar XSS en la salida HTML
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Solo letras y espacios (para nombres propios)
function soloLetras($string) {
    return preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", $string);
}

// Solo dígitos
function soloNumeros($string) {
    return preg_match("/^[0-9]+$/", $string);
}

// Limpiar nombre de archivo para guardar en disco sin caracteres problemáticos
function limpiarNombreArchivo($filename) {
    $filename = preg_replace("/[^a-zA-Z0-9._-]/", "", $filename);
    return substr($filename, 0, 200);
}

// Verificar que un texto solo tenga mayúsculas, acentos y espacios
// (para campos como propietario, dirección, etc. que se guardan en mayúsculas)
function validarSoloMayusculas($texto) {
    return preg_match('/^[A-ZÁÉÍÓÚÜÑ\s]+$/u', strtoupper(trim($texto)));
}

// Convertir a mayúsculas y quitar caracteres no permitidos en campos de dirección
function limpiarMayusculas($texto) {
    $texto = mb_strtoupper(trim($texto), 'UTF-8');
    // Solo letras, números, espacios y caracteres comunes en direcciones
    $texto = preg_replace('/[^A-ZÁÉÍÓÚÜÑ0-9\s\.\,\#\/\-]/u', '', $texto);
    return $texto;
}

// Cuenta catastral: solo números sin letras ni guiones
function validarCuentaCatastral($cuenta) {
    return preg_match('/^[0-9]+$/', $cuenta);
}

// =====================================================
// CALCULAR FECHA DE ENTREGA
// Suma N días hábiles a la fecha de ingreso
// (de lunes a viernes, sin contar fines de semana)
// =====================================================
function calcularFechaEntrega($fechaInicio, $diasHabiles = 10) {
    $fecha = new DateTime($fechaInicio);
    $count = 0;
    while ($count < $diasHabiles) {
        $fecha->modify('+1 day');
        $diaSemana = $fecha->format('N'); // 1=lunes ... 7=domingo
        if ($diaSemana <= 5) {
            $count++;
        }
    }
    return $fecha->format('Y-m-d');
}
