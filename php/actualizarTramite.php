<?php
// =====================================================
// ACTUALIZAR ESTATUS DE TRÁMITE
// Llamado via fetch() POST desde js/verificar.js
// Permite cambiar estatus, subir fotos y guardar datos de constancia
// =====================================================
/**
 * ACTUALIZACIÓN DE TRÁMITES — v3
 * Ruta: php/actualizarTramite.php
 * Llamado vía fetch() POST desde js/verificar.js
 */

// Suprimir warnings para que nunca contaminen la respuesta JSON
error_reporting(0);
ini_set('display_errors', 0);

// Limpiar cualquier output previo
if (ob_get_length()) ob_clean();

// ── Iniciar sesión ──
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";
require_once "funciones_seguridad.php";

header('Content-Type: application/json; charset=utf-8');

// ── Autenticación ──────────────────────────────────────────
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión expirada. Recarga la página.']);
    exit;
}

if (!esVerificador() && !esAdministrador() && !esVentanilla()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos para esta acción.']);
    exit;
}

// ── CSRF ──────────────────────────────────────────────────
$csrfEnviado  = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
$csrfSesion   = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
if (empty($csrfEnviado) || empty($csrfSesion) || !hash_equals($csrfSesion, $csrfEnviado)) {
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido. Recarga la página e intenta de nuevo.']);
    exit;
}

// ── Datos del POST ─────────────────────────────────────────
$folio               = trim(isset($_POST['folio']) ? $_POST['folio'] : '');
$estatus             = trim(isset($_POST['estatus']) ? $_POST['estatus'] : '');
$observaciones       = trim(isset($_POST['observaciones']) ? $_POST['observaciones'] : '');
$verificador_nombre  = mb_strtoupper(trim(isset($_POST['verificador_nombre']) ? $_POST['verificador_nombre'] : ''), 'UTF-8');
$solo_constancia     = isset($_POST['solo_constancia']) && $_POST['solo_constancia'] == '1';

// Campos de Constancia de Numero Oficial
$numero_asignado     = trim(isset($_POST['numero_asignado']) ? $_POST['numero_asignado'] : '');
$tipo_asignacion     = trim(isset($_POST['tipo_asignacion']) ? $_POST['tipo_asignacion'] : 'Asignacion');
$referencia_anterior = trim(isset($_POST['referencia_anterior']) ? $_POST['referencia_anterior'] : '');
$entre_calles        = mb_strtoupper(trim(isset($_POST['entre_calles']) ? $_POST['entre_calles'] : ''), 'UTF-8');
$cuenta_catastral_c  = trim(isset($_POST['cuenta_catastral_constancia']) ? $_POST['cuenta_catastral_constancia'] : '');
$manzana             = trim(isset($_POST['manzana']) ? $_POST['manzana'] : '');
$lote                = trim(isset($_POST['lote']) ? $_POST['lote'] : '');
$fecha_constancia    = trim(isset($_POST['fecha_constancia']) ? $_POST['fecha_constancia'] : date('Y-m-d'));

// Si es solo constancia, solo necesitamos el folio y los datos de constancia
if ($solo_constancia) {
    if (empty($folio)) {
        echo json_encode(['success' => false, 'message' => 'Falta el folio del tramite.']);
        exit;
    }
    if (empty($numero_asignado)) {
        echo json_encode(['success' => false, 'message' => 'El numero asignado es obligatorio.']);
        exit;
    }
} else {
    if (empty($folio) || empty($estatus)) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos: folio y estatus son obligatorios.']);
        exit;
    }
}

// ── Validar formato folio (ej. 001/2026) ──────────────────
if (!preg_match('/^(\d{1,4})\/(\d{4})$/', $folio, $m)) {
    echo json_encode(['success' => false, 'message' => 'Formato de folio inválido.']);
    exit;
}
$folio_numero = (int) $m[1];
$folio_anio   = (int) $m[2];

// ── Estatus permitidos (solo validar si no es solo_constancia) ──
if (!$solo_constancia) {
    $estatusPermitidos = ['En revisión', 'Aprobado por Verificador', 'Aprobado', 'Rechazado', 'En corrección'];
    if (!in_array($estatus, $estatusPermitidos, true)) {
        echo json_encode(['success' => false, 'message' => 'Estatus no valido: ' . htmlspecialchars($estatus)]);
        exit;
    }
}

try {
    $conn->begin_transaction();

    // ── Obtener trámite actual ─────────────────────────────
    $stmtGet = $conn->prepare("
        SELECT t.id, t.estatus, t.foto1_archivo, t.foto2_archivo,
               t.ine_archivo, t.titulo_archivo, t.predial_archivo,
               t.escrituras_archivo, t.formato_constancia,
               t.telefono, t.correo, t.solicitante, t.propietario,
               tt.nombre AS tipo_tramite_nombre
        FROM tramites t
        LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
        WHERE t.folio_numero = ? AND t.folio_anio = ?
        LIMIT 1
    ");
    if (!$stmtGet) throw new Exception("Error BD: " . $conn->error);
    $stmtGet->bind_param("ii", $folio_numero, $folio_anio);
    $stmtGet->execute();
    $res = $stmtGet->get_result();

    if ($res->num_rows === 0) {
        throw new Exception("Trámite no encontrado: $folio");
    }
    $tramite          = $res->fetch_assoc();
    $tramite_id       = (int) $tramite['id'];
    $estatus_anterior = $tramite['estatus'];
    $stmtGet->close();

    // ── MODO SOLO CONSTANCIA ───────────────────────────────
    if ($solo_constancia) {
        $uid = (int) $_SESSION['id'];

        $sql = "UPDATE tramites SET 
                numero_asignado     = ?,
                tipo_asignacion     = ?,
                referencia_anterior = ?,
                entre_calles        = ?,
                cuenta_catastral    = ?,
                manzana             = ?,
                lote                = ?,
                fecha_constancia    = ?
                WHERE folio_numero = ? AND folio_anio = ?";

        $stmtUp = $conn->prepare($sql);
        if (!$stmtUp) throw new Exception("Error prepare UPDATE: " . $conn->error);

        $stmtUp->bind_param("ssssssssii",
            $numero_asignado,
            $tipo_asignacion,
            $referencia_anterior,
            $entre_calles,
            $cuenta_catastral_c,
            $manzana,
            $lote,
            $fecha_constancia,
            $folio_numero,
            $folio_anio
        );

        if (!$stmtUp->execute()) throw new Exception("Error UPDATE: " . $stmtUp->error);
        $stmtUp->close();

        // Log
        $ip  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'desconocida';
        $ua  = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'desconocido';
        $det = "Folio: $folio | Datos constancia actualizados | Numero: $numero_asignado";
        $stmtL = $conn->prepare("
            INSERT INTO logs_actividad (usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
            VALUES (?, 'Actualizo datos constancia', 'tramites', ?, ?, ?, ?)
        ");
        if ($stmtL) {
            $stmtL->bind_param("iisss", $uid, $tramite_id, $det, $ip, $ua);
            $stmtL->execute();
            $stmtL->close();
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Datos de constancia guardados correctamente',
            'folio'   => $folio
        ]);
        exit;
    }

    // ── MODO NORMAL: Fotografias (opcionales) ──────────────
    $carpeta = "../uploads/";
    if (!is_dir($carpeta)) mkdir($carpeta, 0755, true);

    $foto1_archivo = $tramite['foto1_archivo'];
    $foto2_archivo = $tramite['foto2_archivo'];

    foreach (['foto1' => &$foto1_archivo, 'foto2' => &$foto2_archivo] as $fKey => &$fVar) {
        if (!isset($_FILES[$fKey]) || $_FILES[$fKey]['error'] !== UPLOAD_ERR_OK) continue;
        if (empty($_FILES[$fKey]['name'])) continue;

        $ext = strtolower(pathinfo($_FILES[$fKey]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) continue;
        if ($_FILES[$fKey]['size'] > 5242880) continue;

        $n = $fKey . '_' . uniqid() . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES[$fKey]['tmp_name'], $carpeta . $n)) {
            $fVar = $n;
        }
    }
    unset($fVar);

    // ── Documentos adicionales (ventanilla puede reemplazar) ──
    $ine_archivo                = $tramite['ine_archivo'];
    $titulo_archivo             = $tramite['titulo_archivo'];
    $predial_archivo            = $tramite['predial_archivo'];
    $escrituras_archivo         = $tramite['escrituras_archivo'];
    $formato_constancia_archivo = $tramite['formato_constancia'];

    $docMap = [
        'ine'                => ['campo' => &$ine_archivo,                'prefijo' => 'ine'],
        'escritura'          => ['campo' => &$escrituras_archivo,         'prefijo' => 'escritura'],
        'predial'            => ['campo' => &$predial_archivo,            'prefijo' => 'predial'],
        'formato_constancia' => ['campo' => &$formato_constancia_archivo, 'prefijo' => 'formato'],
    ];

    foreach ($docMap as $inputName => &$info) {
        if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) continue;
        if (empty($_FILES[$inputName]['name'])) continue;

        $ext = strtolower(pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) continue;
        if ($_FILES[$inputName]['size'] > 10485760) continue;

        $n = $info['prefijo'] . '_' . uniqid() . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES[$inputName]['tmp_name'], $carpeta . $n)) {
            $info['campo'] = $n;
        }
    }
    unset($info);

    // ── Sanitizar observaciones ────────────────────────────
    $observaciones      = htmlspecialchars($observaciones, ENT_QUOTES, 'UTF-8');
    $verificador_nombre = htmlspecialchars($verificador_nombre, ENT_QUOTES, 'UTF-8');
    $uid                = (int) $_SESSION['id'];

    // ── UPDATE tramite ─────────────────────────────────────
    $sql = "UPDATE tramites SET 
            estatus            = ?,
            observaciones      = ?,
            foto1_archivo      = ?,
            foto2_archivo      = ?,
            ine_archivo        = ?,
            titulo_archivo     = ?,
            predial_archivo    = ?,
            escrituras_archivo = ?,
            formato_constancia = ?,
            aprobado_por       = ?,
            verificador_nombre = ?,
            fecha_aprobacion   = NOW()";

    $params = [
        $estatus, $observaciones, $foto1_archivo, $foto2_archivo,
        $ine_archivo, $titulo_archivo, $predial_archivo,
        $escrituras_archivo, $formato_constancia_archivo,
        $uid, $verificador_nombre
    ];
    $types = "sssssssssis";

    // 🔥 CORREGIDO: Solo cuando el Director firma (estatus Aprobado) se guardan los datos de constancia
    if ($estatus === 'Aprobado' && !empty($numero_asignado)) {
        $sql .= ",
            numero_asignado     = ?,
            tipo_asignacion     = ?,
            referencia_anterior = ?,
            entre_calles        = ?,
            cuenta_catastral    = ?,
            manzana             = ?,
            lote                = ?,
            fecha_constancia    = ?";

        $params[] = $numero_asignado;
        $params[] = $tipo_asignacion;
        $params[] = $referencia_anterior ?: null;
        $params[] = $entre_calles;
        $params[] = $cuenta_catastral_c ?: null;
        $params[] = $manzana ?: null;
        $params[] = $lote ?: null;
        $params[] = $fecha_constancia;
        $types   .= "ssssssss";
    }

    $sql .= " WHERE folio_numero = ? AND folio_anio = ?";
    $params[] = $folio_numero;
    $params[] = $folio_anio;
    $types   .= "ii";

    $stmtUp = $conn->prepare($sql);
    if (!$stmtUp) throw new Exception("Error prepare UPDATE: " . $conn->error);

    $stmtUp->bind_param($types, ...$params);
    if (!$stmtUp->execute()) throw new Exception("Error UPDATE: " . $stmtUp->error);
    $stmtUp->close();

    // 🔥 ASIGNAR FOLIO DE SALIDA solo cuando el Director firma (estatus Aprobado)
    if ($estatus === 'Aprobado') {
        $anio_actual = date('Y');
        
        $stmtSalida = $conn->prepare("CALL asignar_folio_salida(?, ?)");
        if (!$stmtSalida) {
            throw new Exception("Error prepare CALL asignar_folio_salida: " . $conn->error);
        }
        
        $stmtSalida->bind_param("ii", $tramite_id, $anio_actual);
        $stmtSalida->execute();
        
        $result = $stmtSalida->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $folio_asignado = $row['folio_salida_asignado'];
            $det = "Folio: $folio | $estatus_anterior → $estatus | Verificador: $verificador_nombre | Folio salida: " . str_pad($folio_asignado, 3, '0', STR_PAD_LEFT) . "/$anio_actual";
        }
        $stmtSalida->close();
        
        // Limpiar resultados pendientes
        while ($conn->next_result()) {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        }
    }

    // ── Historial ──────────────────────────────────────────
    $accionMap = [
        'En revisión'              => 'Modificado',
        'Aprobado por Verificador' => 'Aprobado por Verificador',
        'Aprobado'                 => 'Aprobado',
        'Rechazado'                => 'Rechazado',
        'En corrección'            => 'En corrección',
    ];
    $accionHist = isset($accionMap[$estatus]) ? $accionMap[$estatus] : 'Modificado';

    $stmtH = $conn->prepare("
        INSERT INTO historial_tramites
          (tramite_id, usuario_id, accion, estatus_anterior, estatus_nuevo, comentario)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    if ($stmtH) {
        $stmtH->bind_param("iissss", $tramite_id, $uid, $accionHist, $estatus_anterior, $estatus, $observaciones);
        $stmtH->execute();
        $stmtH->close();
    }

    // ── Comentario si hay observaciones ───────────────────
    if (!empty($observaciones)) {
        $stmtC = $conn->prepare("
            INSERT INTO comentarios_tramites (tramite_id, usuario_id, comentario, es_interno)
            VALUES (?, ?, ?, 0)
        ");
        if ($stmtC) {
            $stmtC->bind_param("iis", $tramite_id, $uid, $observaciones);
            $stmtC->execute();
            $stmtC->close();
        }
    }

    // ── Log ───────────────────────────────────────────────
    $ip  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'desconocida';
    $ua  = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'desconocido';
    // Si ya se actualizó $det en la asignación de folio, no sobrescribir
    if (!isset($folio_asignado)) {
        $det = "Folio: $folio | $estatus_anterior → $estatus | Verificador: $verificador_nombre";
    }
    $stmtL = $conn->prepare("
        INSERT INTO logs_actividad (usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
        VALUES (?, 'Actualizó trámite', 'tramites', ?, ?, ?, ?)
    ");
    if ($stmtL) {
        $stmtL->bind_param("iisss", $uid, $tramite_id, $det, $ip, $ua);
        $stmtL->execute();
        $stmtL->close();
    }

    $conn->commit();

    // ── Generar links de notificación ─────────────────────
    $nombre       = $tramite['solicitante'] ?: $tramite['propietario'];
    $primerNombre = explode(' ', $nombre)[0];
    $tipoTramite  = isset($tramite['tipo_tramite_nombre']) ? $tramite['tipo_tramite_nombre'] : 'trámite';

    $msgs = [
        'En revisión'              => "Hola $primerNombre, su trámite *$folio* ($tipoTramite) está EN REVISIÓN. Le informaremos novedades. — Dirección de Planeación y D.U.",
        'Aprobado por Verificador' => "Hola $primerNombre, su trámite *$folio* ($tipoTramite) fue APROBADO por el verificador y está pendiente de firma del Director. — Dirección de Planeación y D.U.",
        'Aprobado'                 => "¡Hola $primerNombre! Su trámite *$folio* ($tipoTramite) fue APROBADO y firmado. Puede pasar a recogerlo con esta papeleta. — Dirección de Planeación y D.U.",
        'Rechazado'                => "Hola $primerNombre, lamentamos informarle que su trámite *$folio* ($tipoTramite) fue RECHAZADO." . (!empty($observaciones) ? " Motivo: $observaciones" : " Comuníquese con nosotros.") . " — Dirección de Planeación y D.U.",
        'En corrección'            => (function () use ($primerNombre, $folio, $tipoTramite, $observaciones) {
            $msg = "Hola $primerNombre, su trámite *$folio* ($tipoTramite) requiere CORRECCIÓN para continuar con el proceso.\n";
            if (!empty($observaciones)) {
                $partes = explode(' | ', $observaciones);
                foreach ($partes as $parte) {
                    if (strpos($parte, 'Documentos/requisitos: ') === 0) {
                        $docs = explode(', ', str_replace('Documentos/requisitos: ', '', $parte));
                        $msg .= "\nDocumentos/requisitos pendientes:\n";
                        foreach ($docs as $doc) {
                            $msg .= "• $doc\n";
                        }
                    } else {
                        $msg .= "\nIndicación adicional: $parte\n";
                    }
                }
            }
            $msg .= "\nFavor de presentarse con los documentos indicados en las oficinas de la Dirección de Planeación y Desarrollo Urbano.\n— Dirección de Planeación y D.U.";
            return $msg;
        })(),
    ];

    $asuntos = [
        'En revisión'              => "Trámite $folio en Revisión",
        'Aprobado por Verificador' => "Trámite $folio — Aprobado por Verificador",
        'Aprobado'                 => "¡Trámite $folio Aprobado!",
        'Rechazado'                => "Trámite $folio Rechazado",
        'En corrección'            => "Trámite $folio — Corrección requerida",
    ];

    $msg    = isset($msgs[$estatus])    ? $msgs[$estatus]    : "Actualización de trámite $folio.";
    $asunto = isset($asuntos[$estatus]) ? $asuntos[$estatus] : "Actualización Trámite $folio";

    $tel    = preg_replace('/\D/', '', isset($tramite['telefono']) ? $tramite['telefono'] : '');
    $waLink = $tel
              ? "https://wa.me/52{$tel}?text=" . rawurlencode($msg)
              : null;
    $gmLink = !empty($tramite['correo'])
              ? "mailto:{$tramite['correo']}?subject=" . rawurlencode($asunto) . "&body=" . rawurlencode($msg)
              : null;

    echo json_encode([
        'success'      => true,
        'message'      => 'Trámite actualizado correctamente',
        'estatus'      => $estatus,
        'folio'        => $folio,
        'foto1'        => $foto1_archivo,
        'foto2'        => $foto2_archivo,
        'notificacion' => [
            'nombre'   => $nombre,
            'telefono' => isset($tramite['telefono']) ? $tramite['telefono'] : '',
            'correo'   => isset($tramite['correo'])   ? $tramite['correo']   : '',
            'wa_link'  => $waLink,
            'gm_link'  => $gmLink,
            'mensaje'  => $msg,
        ],
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("[actualizarTramite] " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}