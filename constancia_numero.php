<?php
/**
 * CONSTANCIA DE NÚMERO OFICIAL - Formato para impresión
 */

// Iniciar sesión SIN usar seguridad.php (evita el problema de cookie_samesite en PHP antiguo)
ini_set('session.cookie_httponly', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar sesión manualmente
if (!isset($_SESSION['id']) || !isset($_SESSION['usuario'])) {
    header("Location: acceso.php");
    exit();
}

require "php/db.php";
require "php/funciones_seguridad.php";

// Solo verificador, ventanilla o administrador
if (!esVerificador() && !esAdministrador() && !esVentanilla()) {
    header("Location: acceso.php");
    exit;
}

// Obtener folio
$folio = isset($_GET['folio']) ? $_GET['folio'] : '';
if (!preg_match('/^(\d{1,4})\/(\d{4})$/', $folio, $m)) {
    die("Folio invalido");
}
$folio_numero = (int)$m[1];
$folio_anio   = (int)$m[2];

// Consultar trámite
$stmt = $conn->prepare("
    SELECT t.*, tt.nombre AS tipo_tramite_nombre
    FROM tramites t
    LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
    WHERE t.folio_numero = ? AND t.folio_anio = ?
    LIMIT 1
");
$stmt->bind_param("ii", $folio_numero, $folio_anio);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) { die("Tramite no encontrado"); }
$t = $result->fetch_assoc();

if (empty($t['numero_asignado'])) {
    // Redirigir a la página anterior con un mensaje de error
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'DashVer.php';
    header("Location: $referer?error=sin_numero_asignado");
    exit();
}

// ── FOLIO DE SALIDA ──────────────────────────────────────────────────────────
// El folio de salida es independiente al de ingreso.
// Se asigna la primera vez que se abre la constancia y queda guardado.
$anio_salida = date('Y');
if (empty($t['folio_salida_numero'])) {
    // Calcular el siguiente número de salida para este año
    $stmtSal = $conn->prepare(
        "SELECT COALESCE(MAX(folio_salida_numero), 0) + 1 AS siguiente
         FROM tramites
         WHERE folio_salida_anio = ? AND folio_salida_numero IS NOT NULL"
    );
    $stmtSal->bind_param("i", $anio_salida);
    $stmtSal->execute();
    $rowSal = $stmtSal->get_result()->fetch_assoc();
    $stmtSal->close();
    $nuevo_salida = (int)$rowSal['siguiente'];

    // Guardar en la base de datos
    $stmtUpd = $conn->prepare(
        "UPDATE tramites SET folio_salida_numero = ?, folio_salida_anio = ? WHERE id = ?"
    );
    $stmtUpd->bind_param("iii", $nuevo_salida, $anio_salida, $t['id']);
    $stmtUpd->execute();
    $stmtUpd->close();

    $t['folio_salida_numero'] = $nuevo_salida;
    $t['folio_salida_anio']   = $anio_salida;
}
$folio_salida_display = "" . str_pad($t['folio_salida_numero'], 3, '0', STR_PAD_LEFT) . "/" . $t['folio_salida_anio'];
$folio_ingreso_display = "ING." . str_pad($folio_numero, 3, '0', STR_PAD_LEFT) . "/" . $folio_anio;
// ────────────────────────────────────────────────────────────────────────────

// Configuración del sistema
$config = [];
$resConfig = $conn->query("SELECT clave, valor FROM configuracion_sistema");
while ($row = $resConfig->fetch_assoc()) { $config[$row['clave']] = $row['valor']; }

// Reglamentos editables (con fallback si aún no existen en BD)
$reglamentos = [
    1 => !empty($config['constancia_reglamento_1'])
        ? $config['constancia_reglamento_1']
        : 'En inmuebles construidos deberán colocarse en el exterior, al frente de la construcción junto al acceso principal;',
    2 => !empty($config['constancia_reglamento_2'])
        ? $config['constancia_reglamento_2']
        : 'Los números oficiales en ningún caso deberán ser pintados sobre muros, bloques, columnas y/o en elementos de fácil destrucción;',
    3 => !empty($config['constancia_reglamento_3'])
        ? $config['constancia_reglamento_3']
        : 'Deberán además ser de tipo de fuente legible y permitir una fácil lectura a un mínimo de veinte metros;',
    4 => !empty($config['constancia_reglamento_4'])
        ? $config['constancia_reglamento_4']
        : 'Las placas de numeración deberán colocarse en una altura mínima de dos metros con cincuenta centímetros a partir del nivel de la banqueta.',
];

// Datos con compatibilidad PHP 5+
// Siempre usar la fecha de hoy al imprimir la constancia
$fecha_constancia = date('Y-m-d');
$tipo_asignacion  = isset($t['tipo_asignacion'])  ? $t['tipo_asignacion']  : 'ASIGNACION';
$croquis_archivo  = isset($t['croquis_archivo'])  ? $t['croquis_archivo']  : '';
$croquis_path = $croquis_archivo && strpos($croquis_archivo, '.') === 0 ? $croquis_archivo : ('uploads/' . $croquis_archivo);

// Formatear fecha
$meses = ['','ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
$fp = explode('-', $fecha_constancia);
$fecha_formateada = (int)$fp[2] . ' DE ' . $meses[(int)$fp[1]] . ' DE ' . $fp[0];

// Tipo asignacion
$tipo = strtoupper($tipo_asignacion);
$es_asignacion    = (strpos($tipo, 'ASIGNACION')    !== false);
$es_rectificacion = (strpos($tipo, 'RECTIFICACION') !== false);
$es_reposicion    = (strpos($tipo, 'REPOSICION')    !== false);

// Link de volver según rol
if (esAdministrador())  $back = 'DashAdmin.php';
elseif (esVentanilla()) $back = 'DashVentanilla.php';
else                     $back = 'DashVer.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sis Dit - <?= $folio ?></title>
    <style>
        @page { 
            size: letter; 
            margin: 1.5cm 1.8cm;
        }
        
        * { 
            margin:0; 
            padding:0; 
            box-sizing:border-box; 
        }
        
        body { 
            font-family:Arial,sans-serif; 
            font-size:11pt; 
            color:#000; 
            background:#fff; 
            line-height:1.3; 
        }
        
        .container { 
            max-width:21cm; 
            margin:0 auto; 
            padding:0; 
            page-break-after: always;
            page-break-inside: avoid;
        }

        .header { 
            display:flex; 
            justify-content:space-between; 
            align-items:flex-start; 
            margin-bottom:8px; 
            border-bottom:3px solid #7b0f2b; 
            padding-bottom:8px; 
        }
        
        .header-left { 
            display:flex; 
            align-items:center; 
            gap:10px; 
        }
        
        .header-left img { 
            height:65px; 
        }
        
        .titulo-dep { 
            font-size:13pt; 
            font-weight:bold; 
            color:#7b0f2b; 
            line-height:1.2; 
        }
        
        .subtitulo { 
            font-size:9pt; 
            color:#666; 
        }
        
        .header-right img { 
            height:65px; 
        }

        .folio-line { 
            text-align:right; 
            font-size:12pt; 
            margin:10px 0; 
            font-weight:bold; 
        }
        
        .folio-line span { 
            border-bottom:1px solid #000; 
            padding:0 20px; 
        }
        
        .titulo-principal { 
            text-align:center; 
            font-size:16pt; 
            font-weight:bold; 
            color:#7b0f2b; 
            margin:15px 0 10px; 
            letter-spacing:2px; 
        }

        .tabla-datos { 
            width:100%; 
            border-collapse:collapse; 
            margin-bottom:10px; 
        }
        
        .tabla-datos td,
        .tabla-datos th { 
            border:1px solid #7b0f2b; 
            padding:5px 7px; 
            font-size:10pt; 
        }
        
        .tabla-datos .header-row td { 
            background:#f5e6e9; 
            font-weight:bold; 
            text-align:center; 
            font-size:9pt; 
            color:#7b0f2b; 
        }
        
        .tabla-datos .label { 
            background:#f9f9f9; 
            font-weight:bold; 
            width:170px; 
            color:#333; 
        }
        
        .tabla-datos .valor { 
            text-transform:uppercase; 
        }
        
        .numero-grande {
            font-size: 200pt;
            font-weight:bold;
            text-align:center;
            color:#7b0f2b;
            width: 280px;
            height: 50px;
            background: rgba(123,15,43,0.05);
            border-radius: 8px;
        }
        
        .referencia-anterior { 
            font-size:9pt; 
            text-align:center; 
            color:#666; 
        }
        
        .checkbox { 
            display:inline-block; 
            width:12px; 
            height:12px; 
            border:2px solid #7b0f2b; 
            margin-right:4px; 
            vertical-align:middle; 
        }
        
        .checkbox.checked { 
            background:#7b0f2b; 
            position:relative; 
        }
        
        .checkbox.checked::after { 
            content:'✓'; 
            color:white; 
            font-size:9px; 
            position:absolute; 
            top:-2px; 
            left:1px; 
        }

        .seccion-nombre { 
            border:2px solid #7b0f2b; 
            margin:10px 0; 
            display:flex; 
        }
        
        .seccion-nombre .label-nombre { 
            background:#f5e6e9; 
            padding:10px 12px; 
            font-weight:bold; 
            color:#7b0f2b; 
            width:190px; 
            display:flex; 
            align-items:center; 
            border-right:2px solid #7b0f2b; 
        }
        
        .seccion-nombre .valor-nombre { 
            padding:10px 12px; 
            margin:auto;
            flex:1; 
            font-weight:bold; 
            font-size:15pt; 
            text-transform:uppercase; 
        }

        .seccion-fecha { 
            border:2px solid #7b0f2b; 
            margin:10px 0; 
        }
        
        .seccion-fecha .label-fecha { 
            background:#f5e6e9; 
            padding:6px 12px; 
            font-weight:bold; 
            color:#7b0f2b; 
            border-bottom:2px solid #7b0f2b; 
        }
        
        .seccion-fecha .valor-fecha { 
            padding:8px 12px; 
            text-align:center; 
            font-weight:bold; 
            font-size:11pt;
        }
        .valor-fecha {
            text-transform: uppercase;
        }

        .notas { 
            border:1px solid #999; 
            padding:8px; 
            margin:12px 0; 
            font-size:8pt; 
            background:#fafafa; 
        }
        
        .notas-titulo { 
            font-weight:bold; 
            margin-bottom:4px; 
            color:#7b0f2b; 
        }
        
        .notas p { 
            margin:2px 0; 
            text-align:justify; 
        }
        
        .notas .destacado { 
            font-weight:bold; 
            font-style:italic; 
        }
        
        .atentamente { 
            text-align:center; 
            margin:15px 0 10px; 
            font-weight:bold; 
            font-size:11pt; 
            letter-spacing:3px; 
        }

        .firma { 
            text-align:center; 
            margin:20px 0 10px; 
        }
        
        .firma .linea { 
            width:320px; 
            border-bottom:1px solid #000; 
            margin:0 auto 8px;
        }
        
        .firma .nombre-director { 
            font-weight:bold; 
            font-size:10pt; 
        }
        
        .firma .cargo-director { 
            font-size:9pt; 
        }
        
        .firma .municipio { 
            font-size:9pt; 
            margin-top:2px; 
        }
        .municipio {
            text-transform: uppercase;
        }
        
        .ccp { 
            margin-top:20px; 
            font-size:9pt; 
            font-style:italic; 
        }

        /* ── Página 2: Croquis ── */
        .pagina-croquis { 
            page-break-before: always; 
            page-break-after: auto;
            page-break-inside: avoid;
            padding: 0; 
        }
        
        .croquis-titulo { 
            text-align:center; 
            font-size:13pt; 
            font-weight:bold; 
            color:#7b0f2b; 
            margin:5px 0 3px; 
        }
        
        .croquis-subtitulo { 
            text-align:center; 
            font-size:9pt; 
            color:#555; 
            margin-bottom:8px; 
        }
        
        .croquis-info { 
            display:flex; 
            justify-content:space-between; 
            font-size:15pt; 
            margin-bottom:5px; 
            border-bottom:1px solid #7b0f2b; 
            padding-bottom:5px; 
        }
        
        .croquis-marco {
            border:2px solid #7b0f2b;
            border-radius:4px;
            min-height:400px;
            max-height:450px;
            display:flex;
            align-items:center;
            justify-content:center;
            overflow:hidden;
        }

        .croquis-marco img {
            width:100%;
            max-height:450px;
            object-fit:contain;
        }
        
        .croquis-firma { 
            margin-top:10px; 
            text-align:center; 
        }
        
        .croquis-firma .linea { 
            width:260px; 
            border-bottom:1px solid #000; 
            margin:0 auto 6px; 
        }

        /* ── Página 2: Reverso (texto reglamentario) ── */
        .pagina-reverso {
            page-break-before: always;
            page-break-after: always;
            page-break-inside: avoid;
            padding: 20px 30px;
            font-family: Arial, sans-serif;
        }

        .reverso-titulo {
            font-size: 11pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 18px;
            text-transform: uppercase;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
        }

        .reverso-lista {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .reverso-lista li {
            font-size: 10pt;
            margin-bottom: 14px;
            line-height: 1.6;
            text-align: justify;
        }

        .reverso-lista li::before {
            font-weight: bold;
        }

        /* ── Botones imprimir/volver (no se imprimen) ── */
        .botones-accion {
            position: fixed;
            top: 10px;
            right: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 1000;
        }
        
        .btn-imp {
            padding: 10px 20px;
            background: #7b0f2b;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        
        .btn-imp:hover { background: #5a0b20; }
        
        .btn-back {
            padding: 8px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        
        .btn-back:hover { background: #565e64; color: white; }

        /* ── Panel de croquis — esquina inferior izquierda, colapsable ── */
        .panel-croquis {
            position: fixed;
            bottom: 10px;
            left: 10px;
            background: white;
            border: 2px solid #7b0f2b;
            border-radius: 10px;
            width: 250px;
            box-shadow: 0 4px 20px rgba(0,0,0,.2);
            z-index: 1000;
            font-family: Arial, sans-serif;
            font-size: 12px;
            overflow: hidden;
        }
        
        .panel-croquis .panel-header {
            background: #7b0f2b;
            color: white;
            padding: 8px 12px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: 700;
            user-select: none;
        }
        
        .panel-croquis .panel-header:hover { background: #5a0b20; }
        .panel-croquis .panel-body { padding: 10px 12px; }
        .panel-croquis input[type=file] { width:100%; font-size:11px; margin-bottom:5px; }
        .panel-croquis .btn-sub { background:#7b0f2b; color:white; border:none; border-radius:5px; padding:6px 10px; cursor:pointer; font-size:11px; width:100%; margin-top:3px; }
        .panel-croquis .btn-sub:hover { background:#5a0b20; }
        .panel-croquis .preview-mini { width:100%; max-height:80px; object-fit:contain; border:1px solid #ddd; border-radius:4px; margin-bottom:5px; }
        .panel-croquis .msg { font-size:11px; margin-top:4px; font-weight:600; }
        .panel-croquis .msg.ok   { color:#198754; }
        .panel-croquis .msg.err  { color:#dc3545; }
        .panel-croquis .msg.info { color:#555; }

        @media print {
            .botones-accion, .panel-croquis { 
                display: none !important; 
            }
            
            html, body { 
                height: auto; 
                overflow: visible; 
                margin: 0;
                padding: 0;
                width: 100%;
            }
            
            body { 
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important; 
            }
            
            /* Página 1: constancia */
            .container {
                page-break-after: always;
                page-break-inside: avoid;
                padding: 0;
                margin: 0;
                max-width: 100%;
            }
            
            /* Página 2: croquis */
            .pagina-croquis {
                page-break-before: always;
                page-break-after: auto;
                page-break-inside: avoid;
                padding: 0;
                margin: 0;
            }
            
            /* Eliminar márgenes extras que puedan causar desborde */
            .pagina-reverso { padding: 15px 20px; }
            .reverso-titulo { font-size: 10pt; }
            .reverso-lista li { font-size: 9pt; margin-bottom: 10px; }
            .header, .tabla-datos, .seccion-nombre, .seccion-fecha, .notas, .firma {
                margin-bottom: 4px;
            }

            /* Comprimir elementos de página 1 para dar espacio a la firma */
            .titulo-principal { margin: 8px 0 6px !important; font-size: 14pt !important; }
            .tabla-datos td, .tabla-datos th { padding: 3px 6px !important; font-size: 9pt !important; }
            .seccion-nombre .valor-nombre { font-size: 15pt !important; padding: 6px 10px !important; }
            .seccion-nombre .label-nombre { padding: 6px 10px !important; }
            .seccion-fecha .valor-fecha { padding: 5px 10px !important; font-size: 10pt !important; }
            .notas { padding: 5px 7px !important; margin: 6px 0 !important; }
            .notas p { margin: 1px 0 !important; font-size: 7.5pt !important; }
            .atentamente { margin: 28px 0 4px !important; font-size: 10pt !important; }

            /* Espacio de firma: suficiente para firmar pero sin desbordar */
            .firma { margin: 0 0 4px !important; }
            .firma .linea { height: 45px !important; }

            /* CCP al fondo de la página 1 */
            .ccp {
                position: fixed;
                bottom: 1.5cm;
                left: 1.8cm;
                font-size: 8pt !important;
                margin: 0 !important;
            }

            /* Página 2: croquis — no se ve afectado por los cambios de arriba */
            .croquis-marco {
                min-height: 380px !important;
                max-height: 900px !important;
            }
            /* Ocultar URL y fecha que agrega el navegador */
            @page {
                size: letter;
                margin: 1.5cm 1.8cm;
            }
            
            /* Para Chrome, Edge, Safari */
            @page {
                @top-center {
                    content: "" !important;
                }
                @bottom-center {
                    content: "" !important;
                }
            }
            
            /* Para Firefox */
            #print-url, .print-url, [class*="print"] {
                display: none !important;
            }
            
            /* Ocultar encabezados y pies de página generados por el navegador */
            html, body {
                height: auto;
                overflow: visible;
                margin: 0;
                padding: 0;
                width: 100%;
            }
            
            /* Fuerza a que no se muestren URLs */
            a[href]:after {
                content: none !important;
            }
            
            a:after, a:before {
                content: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Botones fijos -->
<div class="botones-accion">
    <button class="btn-imp" onclick="window.print()">🖨️ Imprimir</button>
    <a href="<?= $back ?>" class="btn-back">← Volver</a>
</div>

<!-- ══ PÁGINA 1 ══ -->
<div class="container">
    <div class="header">
        <div class="header-left">
            <img src="logos/LogoDepFondo.png" alt="Logo"> 
            <div>
                <div class="titulo-dep">Dirección de Planeación<br>y Desarrollo Urbano</div>
                <div class="subtitulo">Rincón de Romos</div>
            </div>
        </div>
        <div class="header-right"><img src="logos/logoPresi.png" alt="Logo"></div>
    </div>

    <div class="folio-line">FOLIO: <span><?= htmlspecialchars($folio_salida_display) ?></span></div>
    <h1 class="titulo-principal">CONSTANCIA DE NUMERO OFICIAL</h1>

    <table class="tabla-datos" cellspacing="0" cellpadding="0">
        <tr class="header-row">        
            <td style="background:#f5e6e9; font-weight:bold; text-align:center; color:#7b0f2b; font-size:9pt;" colspan="2">TIPO DE ASIGNACIÓN</td>
            <td><span class="checkbox <?= $es_asignacion ? 'checked' : '' ?>"></span> ASIGNACIÓN</td>
            <td colspan="1"><span class="checkbox <?= $es_rectificacion ? 'checked' : '' ?>"></span> RECTIFICACIÓN</td>
            <td colspan="1"><span class="checkbox <?= $es_reposicion ? 'checked' : '' ?>"></span> REPOSICIÓN</td>
        </tr>
        <tr>
            <td colspan="4" class="label" style="vertical-align:middle;">SE ASIGNA EL NÚMERO</td>
            <td class="numero-grande" style="font-size: 25px;" rowspan="2"><?= htmlspecialchars($t['numero_asignado']) ?></td>
        </tr>
        <tr>
            <td colspan="4" class="referencia-anterior">
                REFERENCIA ANTERIOR: <?= !empty($t['referencia_anterior']) ? htmlspecialchars($t['referencia_anterior']) : '—' ?>
            </td>
        </tr>
        <tr><td class="label">CALLE:</td><td colspan=4" class="valor"><?= htmlspecialchars($t['direccion']) ?></td></tr>
        <tr ">
            <td class="label">ENTRE CALLES:</td>
            <td style="text-align:center;" colspan="3"><strong><?= htmlspecialchars(isset($t['entre_calle1']) ? $t['entre_calle1'] : '—') ?></strong></td>
            <td style="text-align:center;" colspan="2"><strong><?= htmlspecialchars(isset($t['entre_calle2']) ? $t['entre_calle2'] : '—') ?></strong></td>
        </tr>
        <tr>
            <td class="label">UBICACION:</td>
            <td style="text-align:center;">MANZANA</td>
            <td style="text-align:center;"><strong><?= htmlspecialchars(isset($t['manzana']) ? $t['manzana'] : '—') ?></strong></td>
            <td style="text-align:center;">LOTE</td>
            <td style="text-align:center;"><strong><?= htmlspecialchars(isset($t['lote']) ? $t['lote'] : '—') ?></strong></td>
        </tr>
        <tr><td class="label">COLONIA Y/O FRACCIONAMIENTO</td><td colspan="4" class="valor"><?= htmlspecialchars(isset($t['colonia']) ? $t['colonia'] : $t['localidad']) ?></td></tr>
        <tr><td class="label">POBLADO Y/O DELEGACIÓN</td><td colspan="4" class="valor"><?= htmlspecialchars(isset($config['municipio_nombre']) ? $config['municipio_nombre'] : 'RINCON DE ROMOS') ?>, AGS.</td></tr>
        <tr><td class="label">CODIGO POSTAL</td><td colspan="4" class="valor"><?= htmlspecialchars(isset($t['cp']) ? $t['cp'] : '20400') ?></td></tr>
        <tr><td class="label">CUENTA CATASTRAL No.</td><td colspan="4" class="valor"><?= htmlspecialchars($t['cuenta_catastral']) ?></td></tr>
    </table>

    <div class="seccion-nombre">
        <div class="label-nombre">SE EXTIENDE A NOMBRE DE</div>
        <div class="valor-nombre" style="text-align: center;"><?= htmlspecialchars($t['propietario']) ?></div>
    </div>

    <div class="seccion-fecha">
        <div class="label-fecha">LUGAR Y FECHA DE EXPEDICIÓN:</div>
        <div class="valor-fecha">
            <?= $fecha_formateada ?><br>
            <?= strtoupper(isset($config['municipio_nombre']) ? $config['municipio_nombre'] : 'RINCON DE ROMOS') ?>, AGS.
        </div>
    </div>

    <div class="notas">
        <div class="notas-titulo">NOTAS:</div>
        <p>1.- EL <span class="destacado">NUMERO OFICIAL</span> DEBERÁ COLOCARSE EN <span class="destacado">PARTE VISIBLE</span> DEL FRENTE DEL PREDIO, Y DEBERÁ DE SER CLARAMENTE LEGIBLE, A UN MÍNIMO DE 15 MTS DE DISTANCIA.</p>
        <p>2.- ESTE DOCUMENTO <span class="destacado">NO CONSTITUYE APEO O DESLINDE AL RESPECTO DEL INMUEBLE, NI ACREDITA LA PROPIEDAD O POSESIÓN DEL MISMO.</span></p>
        <p>3.- SE FUNDAMENTA LA CONSTANCIA EN EL <span class="destacado">ARTÍCULO 34 FRACC 11 INCISO I DEL BANDO DE POLICÍA Y GOBIERNO DE RINCON DE ROMOS, AGS.</span></p>
    </div>

    <div class="atentamente">A T E N T A M E N T E:</div>
    <div class="firma">
        <div class="linea"></div>
        <div class="nombre-director"><?= htmlspecialchars(isset($config['director_nombre']) ? $config['director_nombre'] : 'DIRECTOR DE PLANEACIÓN Y DESARROLLO URBANO') ?></div>
        <div class="cargo-director"><?= htmlspecialchars(isset($config['director_cargo']) ? $config['director_cargo'] : 'DIRECTOR DE PLANEACIÓN Y DESARROLLO URBANO') ?>.</div>
        <div class="municipio">DEL MUNICIPIO DE <?= strtoupper(isset($config['municipio_nombre']) ? $config['municipio_nombre'] : 'RINCON DE ROMOS') ?>, AGS.</div>
    </div>
    <div class="ccp"><em>C.C.P. ARCHIVO.</em></div>
</div>

<!-- ══ PÁGINA 2: CROQUIS + REGLAMENTO ══ -->
<div class="pagina-croquis">
    <div class="header">
        <div class="header-left">
            <img src="logos/LogoDepFondo.png" alt="Logo"> 
            <div>
                <div class="titulo-dep">Dirección de Planeación<br>y Desarrollo Urbano</div>
                <div class="subtitulo">Rincón de Romos</div>
            </div>
        </div>
        <div class="header-right"><img src="logos/logoPresi.png" alt="Logo"></div>
    </div>
    <div class="croquis-info" style="margin-bottom:3px;padding-bottom:3px;">
        <span><strong>FOLIO:</strong> <?= htmlspecialchars($folio_salida_display) ?></span>
        <span><strong>PROPIETARIO:</strong> <?= htmlspecialchars($t['propietario']) ?></span>
        <span><strong>CALLE:</strong> <?= htmlspecialchars($t['direccion']) ?> #<?= htmlspecialchars($t['numero_asignado']) ?></span>
    </div>
    <div class="croquis-titulo" style="margin:3px 0 2px;">CROQUIS DE UBICACIÓN DEL PREDIO</div>
    <div class="croquis-subtitulo" style="margin-bottom:5px;">El croquis es de carácter ilustrativo y no constituye deslinde ni apeo del inmueble.</div>
    <div class="croquis-marco" id="croquis-marco" style="min-height:400px;max-height:450px;">
        <?php if (!empty($croquis_archivo) && file_exists($croquis_path)): ?>
            <img src="<?= htmlspecialchars($croquis_path) ?>" id="img-croquis" alt="Croquis" style="max-height:450px;">
        <?php else: ?>
            <div id="croquis-placeholder" style="text-align:center;color:#aaa;padding:20px;">
                <div style="font-size:2rem;margin-bottom:5px;">🗺️</div>
                <p><strong>Sin croquis</strong></p>
                <p style="font-size:8pt;">Usa el panel lateral para cargar la imagen.</p>
            </div>
            <img id="img-croquis" src="" alt="Croquis" style="display:none;width:100%;max-height:450px;object-fit:contain;">
        <?php endif; ?>
    </div>
    <!-- Texto reglamentario al reverso -->
    <div style="margin-top:14px;border-top:2px solid #000;padding-top:10px;">
        <div style="font-size:10pt;font-weight:bold;text-align:center;margin-bottom:10px;text-transform:uppercase;">
            La instalación de Número Oficial deberá apegarse a lo siguiente:
        </div>
        <ol style="list-style:upper-roman;padding-left:28px;margin:0;">
            <li style="font-size:9pt;margin-bottom:8px;line-height:1.5;text-align:justify;"><?= htmlspecialchars($reglamentos[1]) ?></li>
            <li style="font-size:9pt;margin-bottom:8px;line-height:1.5;text-align:justify;"><?= htmlspecialchars($reglamentos[2]) ?></li>
            <li style="font-size:9pt;margin-bottom:8px;line-height:1.5;text-align:justify;"><?= htmlspecialchars($reglamentos[3]) ?></li>
            <li style="font-size:9pt;margin-bottom:0;line-height:1.5;text-align:justify;"><?= htmlspecialchars($reglamentos[4]) ?></li>
        </ol>
    </div>
</div>

<!-- ══ PANEL CROQUIS (no se imprime) ══ -->
<div class="panel-croquis">
    <div class="panel-header" onclick="togglePanel()" id="panel-header-btn">
        <span>🗺️ Croquis del Predio</span>
        <span id="panel-toggle-ico">▲</span>
    </div>
    <div class="panel-body" id="panel-body">
        <?php if (!empty($croquis_archivo) && file_exists($croquis_path)): ?>
            <img src="<?= htmlspecialchars($croquis_path) ?>" class="preview-mini" id="mini-prev">
            <p class="msg ok">✅ Croquis cargado. Puedes reemplazarlo.</p>
        <?php else: ?>
            <img src="" class="preview-mini" id="mini-prev" style="display:none;">
            <p class="msg info" id="msg-sin-croquis" style="margin-bottom:5px;">⚠️ Sin croquis. Selecciona una imagen.</p>
        <?php endif; ?>

        <input type="file" id="inp-croquis" accept="image/jpeg,image/png,image/webp"
               onchange="prevCroquis(this)">
        <button class="btn-sub" id="btn-subir" onclick="subirCroquis()" style="display:none;">
            ⬆️ Guardar croquis
        </button>
        <div class="msg" id="msg-croquis"></div>
    </div>
</div>

<script>
var folioActual = '<?= addslashes($folio) ?>';

// Panel colapsable
var _panelAbierto = true;
function togglePanel() {
    var body = document.getElementById('panel-body');
    var ico  = document.getElementById('panel-toggle-ico');
    if (_panelAbierto) {
        body.style.display = 'none';
        ico.textContent = '▼';
    } else {
        body.style.display = 'block';
        ico.textContent = '▲';
    }
    _panelAbierto = !_panelAbierto;
}

function prevCroquis(input) {
    var file = input.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        // Miniatura en panel
        var mini = document.getElementById('mini-prev');
        mini.src = e.target.result;
        mini.style.display = 'block';
        // Imagen en página 2
        var img = document.getElementById('img-croquis');
        var ph  = document.getElementById('croquis-placeholder');
        img.src = e.target.result;
        img.style.display = 'block';
        img.style.maxHeight = '900px';
        if (ph) ph.style.display = 'none';
        // Mostrar botón guardar
        document.getElementById('btn-subir').style.display = 'block';
        var msgSin = document.getElementById('msg-sin-croquis');
        if (msgSin) msgSin.style.display = 'none';
        document.getElementById('msg-croquis').textContent = '⚠️ Haz clic en "Guardar croquis" para guardarlo.';
        document.getElementById('msg-croquis').className = 'msg info';
    };
    reader.readAsDataURL(file);
}

function subirCroquis() {
    var input = document.getElementById('inp-croquis');
    var msg   = document.getElementById('msg-croquis');
    var btnS  = document.getElementById('btn-subir');
    if (!input.files || !input.files[0]) {
        msg.textContent = '⚠️ Selecciona una imagen primero.';
        msg.className = 'msg err'; return;
    }
    msg.textContent = 'Guardando...'; msg.className = 'msg info';
    btnS.disabled = true;
    var fd = new FormData();
    fd.append('folio', folioActual);
    fd.append('croquis', input.files[0]);
    fetch('php/guardar_croquis.php', { method:'POST', body:fd, credentials:'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(data){
        btnS.disabled = false;
        if (data.success) {
            msg.textContent = '✅ Croquis guardado. Listo para imprimir.';
            msg.className = 'msg ok';
            btnS.style.display = 'none';
        } else {
            msg.textContent = '❌ ' + data.message;
            msg.className = 'msg err';
        }
    })
    .catch(function(){
        btnS.disabled = false;
        msg.textContent = '❌ Error de conexión.'; msg.className = 'msg err';
    });
}

<?php if (isset($_GET['autoprint'])): ?>
window.onload = function() { setTimeout(function(){ window.print(); }, 600); };
<?php endif; ?>
</script>
</body>
</html>