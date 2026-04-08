<?php
/**
 * FICHA DE FOTOGRAFÍAS
 * Genera una ficha imprimible con las fotografías del inmueble
 * Formato similar al oficial de Planeación y Desarrollo Urbano
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

// Verificar que se recibió el folio
if (empty($_GET['folio'])) {
    header("Location: DashVer.php");
    exit;
}

// Parsear folio (formato: 001/2026)
$folio_raw = $_GET['folio'];
$partes = explode('/', $folio_raw);
if (count($partes) !== 2) {
    header("Location: DashVer.php?error=folio_invalido");
    exit;
}

$folio_numero = intval($partes[0]);
$folio_anio = intval($partes[1]);

// Consultar trámite con tipo
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
    header("Location: DashVer.php?error=tramite_no_encontrado");
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

$municipio = $config['municipio_nombre'] ?? 'Rincón de Romos';

// Datos del trámite
$inmueble = $tramite['direccion'];
$colonia = $tramite['colonia'] ?? $tramite['localidad'];
$realizo = ($_SESSION['usuario'] ?? '');
$tipo_trabajo = $tramite['tipo_tramite_nombre'] ?? 'Sin tipo';
$reporta = $realizo;
$fecha_ingreso = $tramite['fecha_ingreso'];

// Formatear fecha en español
$meses = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
$dia = date('d', strtotime($fecha_ingreso));
$mes = $meses[intval(date('m', strtotime($fecha_ingreso)))];
$anio = date('Y', strtotime($fecha_ingreso));
$fecha_formateada = "$dia DE $mes DE $anio";

$observaciones = $tramite['observaciones'] ?? '';

$foto1 = $tramite['foto1_archivo'] ?? '';
$foto2 = $tramite['foto2_archivo'] ?? '';

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sis Dit - <?= htmlspecialchars($folio_raw) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    @page {
        size: letter portrait;
        margin: 12mm;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: Arial, Helvetica, sans-serif;
        background: #e9ecef;
        color: #000;
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
    .ficha {
        width: 216mm;
        min-height: 279mm;
        margin: 0 auto 30px;
        background: #fff;
        border: 2px solid #000;
        padding: 0;
        position: relative;
    }

    /* --- TITULO PRINCIPAL --- */
    .ficha-titulo {
        border-bottom: 2px solid #000;
        padding: 10px 15px;
        text-align: center;
    }

    .ficha-titulo h1 {
        font-size: 16px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 0;
        line-height: 1.3;
    }

    /* --- CUERPO PRINCIPAL --- */
    .ficha-cuerpo {
        display: flex;
        flex-direction: column;
        height: calc(100% - 50px);
    }

    /* --- SECCIÓN SUPERIOR: INFO + FOTOS --- */
    .seccion-superior {
        display: flex;
        border-bottom: 2px solid #000;
        flex: 1;
    }

    /* --- INFO LATERAL IZQUIERDA --- */
    .info-lateral {
        width: 200px;
        border-right: 2px solid #000;
        display: flex;
        flex-direction: column;
    }

    .info-campo {
        border-bottom: 1px solid #000;
        padding: 6px 8px;
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .info-campo:last-child {
        border-bottom: none;
    }

    .info-campo .label {
        font-size: 8px;
        font-weight: 700;
        text-transform: uppercase;
        color: #000;
        margin-bottom: 2px;
    }

    .info-campo .valor {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        word-wrap: break-word;
    }

    /* --- ZONA DE FOTOS --- */
    .zona-fotos {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .foto-titulo {
        text-align: center;
        font-size: 14px;
        font-weight: 900;
        text-transform: uppercase;
        text-decoration: underline;
        padding: 8px;
        border-bottom: 1px solid #000;
        letter-spacing: 2px;
    }

    .fotos-contenedor {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .foto-espacio {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 8px;
        border-bottom: 1px solid #000;
        min-height: 250px;
        overflow: hidden;
    }

    .foto-espacio:last-child {
        border-bottom: none;
    }

    .foto-espacio img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .foto-espacio .sin-foto {
        color: #999;
        font-size: 14px;
        font-style: italic;
    }

    /* --- SECCIÓN DATOS INFERIOR --- */
    .seccion-datos {
        border-bottom: 2px solid #000;
        display: flex;
    }

    .dato-grupo {
        display: flex;
        border-right: 2px solid #000;
    }

    .dato-grupo:last-child {
        border-right: none;
        flex: 1;
    }

    .dato-label {
        font-size: 8px;
        font-weight: 700;
        text-transform: uppercase;
        padding: 6px 8px;
        white-space: nowrap;
        display: flex;
        align-items: center;
        border-right: 1px solid #000;
    }

    .dato-valor {
        font-size: 10px;
        font-weight: 600;
        padding: 6px 8px;
        display: flex;
        align-items: center;
        text-transform: uppercase;
    }

    /* --- SECCIÓN TIPO DE TRABAJO --- */
    .seccion-tipo-trabajo {
        border-bottom: 2px solid #000;
        display: flex;
    }

    .tipo-label {
        font-size: 8px;
        font-weight: 700;
        text-transform: uppercase;
        padding: 6px 8px;
        white-space: nowrap;
        display: flex;
        align-items: center;
        border-right: 1px solid #000;
    }

    .tipo-valor {
        font-size: 10px;
        font-weight: 600;
        padding: 6px 8px;
        display: flex;
        align-items: center;
        text-transform: uppercase;
        flex: 1;
    }

    /* --- SECCIÓN REPORTA --- */
    .seccion-reporta {
        border-bottom: 2px solid #000;
        display: flex;
    }

    .reporta-label {
        font-size: 8px;
        font-weight: 700;
        text-transform: uppercase;
        padding: 6px 8px;
        white-space: nowrap;
        display: flex;
        align-items: center;
        border-right: 1px solid #000;
    }

    .reporta-valor {
        font-size: 10px;
        font-weight: 600;
        padding: 6px 8px;
        display: flex;
        align-items: center;
        text-transform: uppercase;
        flex: 1;
    }

    /* --- SECCIÓN OBSERVACIONES --- */
    .seccion-observaciones {
        padding: 10px 15px;
        min-height: 80px;
    }

    .obs-titulo {
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        text-decoration: underline;
        margin-bottom: 8px;
    }

    .obs-contenido {
        font-size: 11px;
        line-height: 1.5;
        min-height: 50px;
    }

    /* ========== PRINT ========== */
    @media print {
        body {
            background: #fff;
        }

        .no-print {
            display: none !important;
        }

        .ficha {
            border: 2px solid #000;
            margin: 0;
            width: 100%;
            min-height: auto;
            page-break-inside: avoid;
        }
    }
</style>
</head>
<body>

<!-- BARRA ACCIONES -->
<div class="no-print">
    <button class="btn-print" onclick="window.print()">Imprimir Ficha</button>
    <button class="btn-back" onclick="history.back()">Regresar</button>
</div>

<!-- FICHA DE FOTOGRAFIAS -->
<div class="ficha">

    <!-- TITULO -->
    <div class="ficha-titulo">
        <h1>Planeación y Desarrollo<br>Urbano <?= htmlspecialchars($municipio) ?>, Ags.</h1>
    </div>

    <!-- CUERPO -->
    <div class="ficha-cuerpo">

        <!-- SECCION SUPERIOR: INFO + FOTOS -->
        <div class="seccion-superior">

            <!-- INFO LATERAL -->
            <div class="info-lateral">
                <div class="info-campo">
                    <span class="label">Inmueble:</span>
                    <span class="valor"><?= htmlspecialchars($inmueble) ?></span>
                </div>
                <div class="info-campo">
                    <span class="label">Col.:</span>
                    <span class="valor"><?= htmlspecialchars($colonia) ?></span>
                </div>
                <div class="info-campo">
                    <span class="label">Localidad:</span>
                    <span class="valor"><?= htmlspecialchars($tramite['localidad']) ?></span>
                </div>
                <div class="info-campo">
                    <span class="label">Realizó:</span>
                    <span class="valor"><?= htmlspecialchars($realizo) ?></span>
                </div>
            </div>

            <!-- ZONA DE FOTOS -->
            <div class="zona-fotos">
                <div class="foto-titulo">Fotografías</div>
                <div class="fotos-contenedor">
                    <div class="foto-espacio">
                        <?php if ($foto1): ?>
                            <img src="uploads/<?= htmlspecialchars($foto1) ?>" alt="Fotografía 1 del inmueble">
                        <?php else: ?>
                            <span class="sin-foto">Sin fotografía 1</span>
                        <?php endif; ?>
                    </div>
                    <div class="foto-espacio">
                        <?php if ($foto2): ?>
                            <img src="uploads/<?= htmlspecialchars($foto2) ?>" alt="Fotografía 2 del inmueble">
                        <?php else: ?>
                            <span class="sin-foto">Sin fotografía 2</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- DATOS: HOJA, DE, FECHA -->
        <div class="seccion-datos">
            <div class="dato-grupo">
                <div class="dato-label">Hoja No.:</div>
                <div class="dato-valor">01</div>
            </div>
            <div class="dato-grupo">
                <div class="dato-label">De:</div>
                <div class="dato-valor">01</div>
            </div>
            <div class="dato-grupo">
                <div class="dato-label">Fecha:</div>
                <div class="dato-valor"><?= $fecha_formateada ?></div>
            </div>
        </div>

        <!-- TIPO DE TRABAJO -->
        <div class="seccion-tipo-trabajo">
            <div class="tipo-label">Tipo de Trabajo:</div>
            <div class="tipo-valor"><?= htmlspecialchars($tipo_trabajo) ?></div>
        </div>

        <!-- REPORTA -->
        <div class="seccion-reporta">
            <div class="reporta-label">Reporta:</div>
            <div class="reporta-valor"><?= htmlspecialchars($reporta) ?></div>
        </div>

        <!-- OBSERVACIONES -->
        <div class="seccion-observaciones">
            <div class="obs-titulo">Observaciones:</div>
            <div class="obs-contenido"><?= nl2br(htmlspecialchars($observaciones)) ?></div>
        </div>

    </div>

</div>

</body>
</html>
