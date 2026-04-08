<?php
// =====================================================
// REGISTRAR NUEVO TRÁMITE
// Recibe el formulario de Dash.php, valida todos los campos,
// sube los archivos adjuntos y lo inserta en BD con folio autogenerado
// =====================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

require "db.php";
require "funciones_seguridad.php";

session_start();

/* ── Seguridad ─────────────────────────────────────────── */
if (!isset($_SESSION['id'])) {
    header("Location: ../acceso.php"); exit;
}
if (!validarCSRF()) {
    header("Location: ../Dash.php?error_msg=" . urlencode("Token de seguridad inválido")); exit;
}

/* ── Validar campos obligatorios ───────────────────────── */
$obligatorios = ['propietario','direccion','localidad','tipo_tramite_id','fecha_ingreso','solicitante','telefono'];
foreach ($obligatorios as $campo) {
    if (empty(trim($_POST[$campo] ?? ''))) {
        header("Location: ../Dash.php?error_msg=" . urlencode("El campo '$campo' es obligatorio."));
        exit;
    }
}

try {
    $conn->begin_transaction();

    /* ── Texto en MAYÚSCULAS ── */
    $propietario = limpiarMayusculas($_POST['propietario']);
    $direccion   = limpiarMayusculas($_POST['direccion']);
    $localidad   = limpiarMayusculas($_POST['localidad']);
    $colonia     = !empty($_POST['colonia'])   ? limpiarMayusculas($_POST['colonia'])   : null;
    $solicitante = limpiarMayusculas($_POST['solicitante']);
    $cp          = !empty($_POST['cp'])         ? trim($_POST['cp'])                    : null;
    $superficie  = !empty($_POST['superficie']) ? trim($_POST['superficie'])             : null;

    $tipo_tramite_id = (int) $_POST['tipo_tramite_id'];

    /* ── Cuenta catastral: solo dígitos (null → trigger la asigna automáticamente) ── */
    $cuenta_catastral = null;
    if (!empty($_POST['cuenta_catastral'])) {
        $cc = preg_replace('/\D/', '', $_POST['cuenta_catastral']);
        if ($cc !== '') $cuenta_catastral = $cc;
    }

    /* ── Teléfono ── */
    $telefono = trim($_POST['telefono']);
    $telSolo  = preg_replace('/\D/', '', $telefono);
    if (strlen($telSolo) < 10) throw new Exception("Teléfono inválido (mínimo 10 dígitos).");

    /* ── Correo (opcional) ── */
    $correo = null;
    if (!empty(trim($_POST['correo'] ?? ''))) {
        $correo = trim($_POST['correo']);
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL))
            throw new Exception("Correo electrónico inválido.");
    }

    /* ── Fechas ── */
    $fecha_ingreso = trim($_POST['fecha_ingreso']);
    if (!DateTime::createFromFormat('Y-m-d', $fecha_ingreso))
        throw new Exception("Fecha de ingreso inválida.");

    // Entrega = 10 días hábiles automático
    $f = new DateTime($fecha_ingreso);
    $c = 0;
    while ($c < 10) {
        $f->modify('+1 day');
        if ($f->format('N') <= 5) $c++;
    }
    $fecha_entrega = $f->format('Y-m-d');

    /* ── Coordenadas UTM ── */
    $lat = null;
    $lng = null;
    if (!empty($_POST['lat']) && !empty($_POST['lng'])) {
        $latVal = (float) $_POST['lat'];
        $lngVal = (float) $_POST['lng'];
        if ($latVal < 100000 || $latVal > 900000) throw new Exception("Coordenada UTM X inválida.");
        if ($lngVal < 0     || $lngVal > 10000000) throw new Exception("Coordenada UTM Y inválida.");
        $lat = $latVal;
        $lng = $lngVal;
    }

    /* ── Folio siguiente ── */
    $folio_anio = (int) date("Y");
    $stmtFolio = $conn->prepare(
        "SELECT COALESCE(MAX(CAST(folio_numero AS UNSIGNED)), 0) + 1 FROM tramites WHERE folio_anio = ? FOR UPDATE"
    );
    if (!$stmtFolio) throw new Exception("Error folio: " . $conn->error);
    $stmtFolio->bind_param("i", $folio_anio);
    $stmtFolio->execute();
    $stmtFolio->bind_result($nuevoFolio);
    $stmtFolio->fetch();
    $stmtFolio->close();
    $folio_numero = (int) $nuevoFolio;  // INT para coincidir con la columna

    /* ── Archivos (todos opcionales) ── */
    $carpeta = "../uploads/";
    if (!is_dir($carpeta)) mkdir($carpeta, 0755, true);

    $archivos = [];
    $comentario_sin_doc = !empty($_POST['comentario_sin_doc'])
        ? htmlspecialchars(trim($_POST['comentario_sin_doc']), ENT_QUOTES, 'UTF-8') : null;

    $camposArchivo = ['ine', 'escritura', 'predial', 'formato_constancia'];
    foreach ($camposArchivo as $campo) {
        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) continue;
        if (empty($_FILES[$campo]['name'])) continue;

        $ext  = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
        $permitidos = ['pdf','jpg','jpeg','png'];
        if (!in_array($ext, $permitidos)) throw new Exception("Extensión no permitida: $campo ($ext)");
        if ($_FILES[$campo]['size'] > 5242880) throw new Exception("Archivo demasiado grande: $campo (máx 5MB)");

        $nombre = $campo . '_' . uniqid() . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $carpeta . $nombre))
            throw new Exception("Error al guardar archivo: $campo");
        $archivos[$campo] = $nombre;
    }

    $ine_archivo    = $archivos['ine']                ?? null;  // s 18
    $esc_archivo    = $archivos['escritura']          ?? null;  // s 19
    $pre_archivo    = $archivos['predial']            ?? null;  // s 20
    $fmt_constancia = $archivos['formato_constancia'] ?? null;  // s 21
    $datos_json     = null;                                     // s 22
    $usuario_id     = (int) $_SESSION['id'];                    // i 24

    /* ══════════════════════════════════════════════════════
       INSERT — 24 columnas, 24 tipos, 24 variables
       Tipos: i i i  s s s s s  d d  s s  s s s  s s  s s s s  s s  i
       Pos:   1 2 3  4 5 6 7 8  9 10 11 12 13 14 15 16 17 18 19 20 21 22 23 24
    ══════════════════════════════════════════════════════ */
    $sql = "
        INSERT INTO tramites (
            folio_numero,
            folio_anio,
            tipo_tramite_id,
            propietario,
            direccion,
            localidad,
            colonia,
            cp,
            lat,
            lng,
            fecha_ingreso,
            fecha_entrega,
            solicitante,
            telefono,
            correo,
            cuenta_catastral,
            superficie,
            ine_archivo,
            escrituras_archivo,
            predial_archivo,
            formato_constancia,
            datos_especificos,
            comentario_sin_doc,
            usuario_creador_id
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Error al preparar INSERT: " . $conn->error);

    /*  24 tipos exactos:
        i  i  i  s  s  s  s  s  d  d  s   s   s   s   s   s   s   s   s   s   s   s   s   i
        1  2  3  4  5  6  7  8  9  10 11  12  13  14  15  16  17  18  19  20  21  22  23  24  */
    $stmt->bind_param(
        "iiisssssddsssssssssssssi",
        $folio_numero,      // 1  i
        $folio_anio,        // 2  i
        $tipo_tramite_id,   // 3  i
        $propietario,       // 4  s
        $direccion,         // 5  s
        $localidad,         // 6  s
        $colonia,           // 7  s
        $cp,                // 8  s
        $lat,               // 9  d
        $lng,               // 10 d
        $fecha_ingreso,     // 11 s
        $fecha_entrega,     // 12 s
        $solicitante,       // 13 s
        $telefono,          // 14 s
        $correo,            // 15 s
        $cuenta_catastral,  // 16 s
        $superficie,        // 17 s
        $ine_archivo,       // 18 s
        $esc_archivo,       // 19 s
        $pre_archivo,       // 20 s
        $fmt_constancia,    // 21 s
        $datos_json,        // 22 s
        $comentario_sin_doc,// 23 s
        $usuario_id         // 24 i
    );

    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar INSERT: " . $stmt->error);
    }

    $tramite_id = $stmt->insert_id;
    $stmt->close();

    /* ── Historial ── */
    $hist = $conn->prepare("
        INSERT INTO historial_tramites (tramite_id, usuario_id, accion, estatus_nuevo, comentario)
        VALUES (?, ?, 'Creado', 'En revisión', 'Trámite creado')
    ");
    if ($hist) {
        $hist->bind_param("ii", $tramite_id, $usuario_id);
        $hist->execute();
        $hist->close();
    }

    /* ── Log ── */
    $folioStr = str_pad($folio_numero, 3, "0", STR_PAD_LEFT) . "/" . $folio_anio;
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? 'desconocido';
    $acc = $conn->prepare("
        INSERT INTO logs_actividad (usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
        VALUES (?, 'Creó trámite', 'tramites', ?, ?, ?, ?)
    ");
    if ($acc) {
        $det = "Folio: $folioStr";
        $acc->bind_param("iisss", $usuario_id, $tramite_id, $det, $ip, $ua);
        $acc->execute();
        $acc->close();
    }

    $conn->commit();

    header("Location: ../ficha.php?folio=" . str_pad($folio_numero, 3, "0", STR_PAD_LEFT) . "/$folio_anio");
    exit;

} catch (Exception $e) {
    $conn->rollback();

    // Borrar archivos subidos si los hubo
    if (!empty($archivos)) {
        foreach ($archivos as $a) {
            $ruta = ($carpeta ?? '../uploads/') . $a;
            if (file_exists($ruta)) unlink($ruta);
        }
    }

    header("Location: ../Dash.php?error_msg=" . urlencode($e->getMessage()));
    exit;
}
