<?php
/**
 * FICHA DE INGRESO DE TRAMITE
 * Genera una ficha imprimible al estilo oficial del municipio
 */
// Iniciar sesión SIN usar seguridad.php (evita problema de cookie_samesite en nueva pestaña)
ini_set('session.cookie_httponly', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar sesión manualmente
if (!isset($_SESSION['id']) || !isset($_SESSION['usuario'])) {
    header("Location: acceso.php");
    exit();
}

require_once "php/db.php";
require_once "php/funciones_seguridad.php";

// Verificar que se recibio el folio
if (empty($_GET['folio'])) {
    header("Location: Dash.php");
    exit;
}

// Parsear folio (formato: 001/2026)
$folio_raw = $_GET['folio'];
$partes = explode('/', $folio_raw);
if (count($partes) !== 2) {
    header("Location: Dash.php?error=folio_invalido");
    exit;
}

$folio_numero = intval($partes[0]);
$folio_anio = intval($partes[1]);

// Consultar tramite con tipo
$sql = "SELECT t.*, tt.nombre as tipo_tramite_nombre, tt.codigo as tipo_tramite_codigo,
        u.nombre as creador_nombre, u.apellidos as creador_apellidos
        FROM tramites t
        LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
        LEFT JOIN usuarios u ON t.usuario_creador_id = u.id
        WHERE t.folio_numero = ? AND t.folio_anio = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $folio_numero, $folio_anio);
$stmt->execute();
$result = $stmt->get_result();
$tramite = $result->fetch_assoc();
$stmt->close();

if (!$tramite) {
    header("Location: Dash.php?error=tramite_no_encontrado");
    exit;
}

// Obtener config del municipio
$config = [];
$config_result = $conn->query("SELECT clave, valor FROM configuracion_sistema");
if ($config_result) {
    while ($row = $config_result->fetch_assoc()) {
        $config[$row['clave']] = $row['valor'];
    }
}

$municipio = $config['municipio_nombre'] ?? 'Rincon de Romos';
$director = $config['director_nombre'] ?? '';
$director_cargo = $config['director_cargo'] ?? '';

// Formatear fechas
$fecha_ingreso = $tramite['fecha_ingreso'];
$fecha_entrega = $tramite['fecha_entrega'];

$dia_ing = date('d', strtotime($fecha_ingreso));
$mes_ing = date('m', strtotime($fecha_ingreso));
$anio_ing = date('y', strtotime($fecha_ingreso));

$dia_ent = date('d', strtotime($fecha_entrega));
$mes_ent = date('m', strtotime($fecha_entrega));
$anio_ent = date('y', strtotime($fecha_entrega));

// Folio formateado
$folio_display = "ING." . str_pad($folio_numero, 3, '0', STR_PAD_LEFT) . "/" . $folio_anio;
$mensaje_whatsapp = "Hola, quiero información sobre mi trámite.";
$link_whatsapp = "https://wa.me/524498077899?text=" . urlencode($mensaje_whatsapp);
$link_whatsapp_qr = urlencode($link_whatsapp);

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sis Dit - <?= $folio_display ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    @page {
        size: landscape;
        margin: 10mm;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: Arial, Helvetica, sans-serif;
        background: #e9ecef;
        color: #222;
    }

    .no-print {
        text-align: center;
        padding: 15px;
        background: #fff;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .no-print button {
        padding: 10px 30px;
        font-size: 15px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        margin: 0 8px;
        font-weight: 600;
    }

    .btn-print {
        background: #7b0f2b;
        color: #fff;
    }

    .btn-print:hover {
        background: #5e0b20;
    }

    .btn-back {
        background: #6c757d;
        color: #fff;
    }

    .btn-back:hover {
        background: #5a6268;
    }

    /* ========== FICHA ========== */
    .ficha-container {
        width: 960px;
        margin: 0 auto 30px;
        background: #fff;
        border: 2px solid #333;
        position: relative;
        overflow: hidden;
    }

    /* --- HEADER --- */
    .ficha-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 20px 8px;
        border-bottom: 2px solid #333;
    }

    .ficha-header .logo-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ficha-header .logo-left img {
        height: 55px;
    }

    .ficha-header .logo-left .dept-text {
        font-size: 11px;
        font-weight: 700;
        line-height: 1.3;
        color: #1a472a;
    }

    .ficha-header .logo-left .dept-text span {
        display: block;
        font-size: 9px;
        font-weight: 400;
        color: #555;
    }

    .ficha-header .folio-box {
        text-align: center;
    }

    .ficha-header .folio-box .folio-label {
        font-size: 10px;
        font-weight: 700;
        color: #555;
    }

    .ficha-header .folio-box .folio-value {
        font-size: 16px;
        font-weight: 700;
        border: 2px solid #333;
        border-radius: 6px;
        padding: 3px 14px;
        display: inline-block;
        margin-top: 2px;
        letter-spacing: 1px;
    }

    .ficha-header .logo-right img {
        height: 60px;
    }

    /* --- BODY --- */
    .ficha-body {
        display: flex;
    }

    .ficha-main {
        flex: 1;
        padding: 10px 18px 14px;
    }

    .ficha-sidebar {
        width: 160px;
        border-left: 2px solid #333;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 14px;
        padding: 14px 10px;
        background: #fafafa;
    }

    .ficha-sidebar .sidebar-label {
        font-size: 10px;
        font-weight: 700;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .ficha-sidebar .qr-placeholder {
        width: 100px;
        height: 100px;
        border: 2px solid #7b0f2b;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 9px;
        color: #7b0f2b;
        text-align: center;
        font-weight: 600;
    }

    .ficha-sidebar .whatsapp-text {
        font-size: 10px;
        font-weight: 700;
        text-align: center;
    }

    /* --- TITULO --- */
    .ficha-title {
        font-size: 22px;
        font-weight: 900;
        margin-bottom: 10px;
        color: #1a1a1a;
        letter-spacing: -0.5px;
    }

    /* --- FILAS DE DATOS --- */
    .data-row {
        display: flex;
        align-items: stretch;
        margin-bottom: 6px;
    }

    .data-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        white-space: nowrap;
        padding-right: 8px;
        display: flex;
        align-items: center;
        min-width: 170px;
    }

    .data-value {
        flex: 1;
        background: #d4edfc;
        border: 1px solid #b0d4f1;
        padding: 4px 10px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        min-height: 24px;
        display: flex;
        align-items: center;
    }

    .data-row-multi {
        display: flex;
        gap: 6px;
    }

    .data-row-multi .data-value {
        flex: 1;
    }

    .sub-labels {
        display: flex;
        gap: 6px;
        margin-top: 1px;
        margin-bottom: 4px;
        padding-left: 170px;
    }

    .sub-labels span {
        flex: 1;
        font-size: 8px;
        color: #777;
        text-align: center;
        font-style: italic;
    }

    /* --- TIPO TRAMITE BOX --- */
    .tipo-tramite-section {
        margin: 10px 0;
    }

    .tipo-tramite-label {
        background: #b8e0c8;
        border: 1px solid #82c9a0;
        font-size: 9px;
        font-weight: 700;
        padding: 3px 10px;
        display: inline-block;
        margin-bottom: 4px;
    }

    .tipo-tramite-value {
        background: #b8e0c8;
        border: 1px solid #82c9a0;
        padding: 6px 14px;
        font-size: 16px;
        font-weight: 800;
        text-transform: uppercase;
    }

    /* --- FECHAS --- */
    .fechas-row {
        display: flex;
        gap: 30px;
        margin: 10px 0;
        align-items: center;
    }

    .fecha-group {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .fecha-group .fecha-label {
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
    }

    .fecha-box {
        border: 1px solid #b0d4f1;
        background: #d4edfc;
        padding: 3px 8px;
        font-size: 14px;
        font-weight: 700;
        text-align: center;
        min-width: 32px;
    }

    .fecha-sep {
        font-size: 8px;
        color: #888;
        text-align: center;
        margin-top: 1px;
    }

    .fecha-col {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    /* --- FIRMA --- */
    .firma-row {
        display: flex;
        justify-content: flex-end;
        margin-top: 2px;
        margin-bottom: 6px;
    }

    .firma-line {
        width: 220px;
        border-top: 1px solid #333;
        text-align: center;
        font-size: 9px;
        font-weight: 700;
        padding-top: 2px;
    }

    /* --- NOTA --- */
    .nota {
        font-size: 9px;
        font-weight: 800;
        color: #d00;
        margin-top: 10px;
        text-transform: uppercase;
    }

    /* --- RECIBIO --- */
    .recibio-row {
        display: flex;
        align-items: stretch;
        margin-top: 8px;
    }

    /* ========== PRINT ========== */
    @media print {
        body {
            background: #fff;
        }

        .no-print {
            display: none !important;
        }

        .ficha-container {
            border: 2px solid #333;
            margin: 0;
            width: 100%;
            page-break-inside: avoid;
        }
    }
</style>
</head>
<body>

<!-- BARRA ACCIONES -->
<div class="no-print">
    <button class="btn-print" onclick="window.print()">Imprimir Ficha</button>
    <button class="btn-back" onclick="window.location.href='<?php 
        // Redirigir según el rol del usuario
        switch($_SESSION['rol']) {
            case 'Administrador':
                echo 'DashAdmin.php';
                break;
            case 'Verificador':
                echo 'DashVer.php';
                break;
            case 'Ventanilla':
                echo 'DashVentanilla.php';
                break;
            default:
                echo 'Dash.php';
                break;
        }
    ?>'">Regresar al Panel
    </button>
</div>

<!-- FICHA -->
<div class="ficha-container">

    <!-- HEADER -->
    <div class="ficha-header">
        <div class="logo-left">
            <img src="logos/logo_urbano.jpeg" alt="Logo Direccion" onerror="this.style.display='none'">
            <div class="dept-text">
                Direccion de Planeacion<br>y Desarrollo Urbano
                <span><?= htmlspecialchars($municipio) ?></span>
            </div>
        </div>

        <div class="folio-box">
            <div class="folio-label">No. de Folio</div>
            <div class="folio-value"><?= htmlspecialchars($folio_display) ?></div>
        </div>

        <div class="logo-right">
            <img src="logos/logo_presi.jpeg" alt="Logo Municipio" onerror="this.style.display='none'">
        </div>
    </div>

    <!-- BODY -->
    <div class="ficha-body">

        <!-- MAIN CONTENT -->
        <div class="ficha-main">

            <div class="ficha-title">INGRESO DE TRAMITE</div>

            <!-- PROPIETARIO -->
            <div class="data-row">
                <div class="data-label">Nombre del Propietario:</div>
                <div class="data-value"><?= htmlspecialchars($tramite['propietario']) ?></div>
            </div>

            <!-- DIRECCION -->
            <div class="data-row">
                <div class="data-label">Direccion:</div>
                <div class="data-value"><?= htmlspecialchars($tramite['direccion']) ?></div>
            </div>
            <div class="sub-labels">
                <span>Calle</span>
                <span>Numero</span>
                <span>Colonia</span>
            </div>

            <!-- LOCALIDAD + COORDENADAS -->
            <div class="data-row">
                <div class="data-label">Localidad:</div>
                <div class="data-row-multi" style="flex:1;">
                    <div class="data-value" style="flex:2;"><?= htmlspecialchars($tramite['localidad']) ?></div>
                    <div class="data-value" style="flex:1; background:#b8e0c8; border-color:#82c9a0; font-size:11px;">
                       X <?= isset($tramite['lat']) ? number_format((float)$tramite['lat'], 2, '.', '') : '' ?>
                    </div>
                    <div class="data-value" style="flex:1; background:#b8e0c8; border-color:#82c9a0; font-size:11px;">
                        Y <?= isset($tramite['lng']) ? number_format((float)$tramite['lng'], 2, '.', '') : '' ?>
                    </div>
                </div>
            </div>
            <div class="sub-labels" style="padding-left:170px;">
                <span style="flex:2;"></span>
                <span style="flex:2;">Coordenadas</span>
            </div>

            <!-- TIPO DE TRAMITE -->
            <div class="tipo-tramite-section">
                <div class="tipo-tramite-label">Tipo de Tramite:</div>
                <div class="tipo-tramite-value">
                    <?= htmlspecialchars($tramite['tipo_tramite_nombre'] ?? 'Sin tipo') ?>
                    (<?= htmlspecialchars($tramite['folio_numero']) ?>)
                </div>
            </div>

            <!-- FECHAS -->
            <div class="fechas-row">
                <div class="fecha-group">
                    <span class="fecha-label">Fecha de Ingreso</span>
                    <div class="fecha-col">
                        <div class="fecha-box"><?= $dia_ing ?></div>
                        <div class="fecha-sep">dia</div>
                    </div>
                    <div class="fecha-col">
                        <div class="fecha-box"><?= $mes_ing ?></div>
                        <div class="fecha-sep">mes</div>
                    </div>
                    <div class="fecha-col">
                        <div class="fecha-box"><?= $anio_ing ?></div>
                        <div class="fecha-sep">ano</div>
                    </div>
                </div>

                <div class="fecha-group">
                    <span class="fecha-label">Fecha de Entrega</span>
                    <div class="fecha-col">
                        <div class="fecha-box"><?= $dia_ent ?></div>
                        <div class="fecha-sep">dia</div>
                    </div>
                    <div class="fecha-col">
                        <div class="fecha-box"><?= $mes_ent ?></div>
                        <div class="fecha-sep">mes</div>
                    </div>
                    <div class="fecha-col">
                        <div class="fecha-box"><?= $anio_ent ?></div>
                        <div class="fecha-sep">ano</div>
                    </div>
                </div>
            </div>

            <!-- SOLICITANTE -->
            <div class="data-row">
                <div class="data-label">Nombre del Solicitante:</div>
                <div class="data-value"><?= htmlspecialchars($tramite['solicitante']) ?></div>
            </div>

            <!-- TELEFONO -->
            <div class="data-row">
                <div class="data-label">Telefono:</div>
                <div class="data-value" style="max-width:220px;"><?= htmlspecialchars($tramite['telefono']) ?></div>
            </div>

            <div class="firma-row">
                <div class="firma-line">FIRMA</div>
            </div>

            <!-- CORREO -->
            <div class="data-row">
                <div class="data-label">Correo Electronico:</div>
                <div class="data-value" style="text-transform: none !important;"><?= htmlspecialchars(strtolower(trim($tramite['correo'] ?? ''))) ?></div>
            </div>

            <!-- RECIBIO -->
            <div class="recibio-row">
                <div class="data-label">Recibio:</div>
                <div class="data-value">
                    <?= htmlspecialchars(($_SESSION['usuario'] ?? '')) ?>
                </div>
            </div>

            <div class="firma-row" style="margin-top:4px;">
                <div class="firma-line">FIRMA</div>
            </div>

            <!-- NOTA -->
            <div class="nota">
                Nota: Para recoger su tramite, debera presentar esta papeleta original.
            </div>

        </div>

        <!-- SIDEBAR -->
        <div class="ficha-sidebar">
            <div class="sidebar-label">Trámites y Servicios</div>
            <div class="qr-placeholder">
                <img 
                    src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=https://tusitio.com/tramites"
                    alt="QR Trámites"
                    style="width:90px; height:90px;">
            </div>
            <div style="margin-top: 10px;">
                <div class="sidebar-label">WhatsApp</div>
                <div class="whatsapp-text">449 807 78 99</div>
            </div>
            <div class="qr-placeholder">
                <img 
                src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?= $link_whatsapp_qr ?>"
                alt="QR WhatsApp"
                style="width:90px; height:90px;">

            </div>
        </div>
    <!-- CIERRE BODY -->
    </div>

</div>

</body>
</html>