<?php
require "seguridad.php";

// ✅ DESPUÉS (usuario, ventanilla y admin)
require_once "php/funciones_seguridad.php";

$rolesPermitidos = ['Usuario', 'Ventanilla', 'Administrador'];

if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], $rolesPermitidos)) {
    header("Location: acceso.php?error=no_autorizado");
    exit();
}

// Variables de permisos
$esAdmin = esAdministrador();
$esVentanilla = esVentanilla();
$esUsuario = ($_SESSION['rol'] === 'Usuario');

require_once "php/db.php";

// ✅ DESPUÉS (filtra según rol)
$sql = "SELECT t.*, tt.nombre as tipo_tramite_nombre, 
        u.nombre as creador_nombre, u.apellidos as creador_apellidos
        FROM tramites t
        LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
        LEFT JOIN usuarios u ON t.usuario_creador_id = u.id
        WHERE 1=1";

$params = [];
$types = "";

// USUARIOS solo ven sus propios trámites
if ($esUsuario && !$esAdmin && !$esVentanilla) {
    $sql .= " AND t.usuario_creador_id = ?";
    $params[] = $_SESSION['id'];
    $types .= "i";
}

/* ===== FILTRO FOLIO ===== */
if (!empty($_GET['folio'])) {
    $folioVal = $_GET['folio'];
    if (str_contains($folioVal, '/')) {
        [$fn, $fa] = explode('/', $folioVal);
        $sql .= " AND t.folio_numero = ? AND t.folio_anio = ?";
        $params[] = intval($fn); $params[] = intval($fa);
        $types .= "ii";
    } else {
        $sql .= " AND t.folio_numero LIKE ?";
        $params[] = '%'.$folioVal.'%'; $types .= "s";
    }
}

/* ===== FILTRO NOMBRE / PROPIETARIO ===== */
if (!empty($_GET['nombre'])) {
    $sql .= " AND (t.propietario LIKE ? OR t.solicitante LIKE ?)";
    $params[] = '%'.$_GET['nombre'].'%';
    $params[] = '%'.$_GET['nombre'].'%';
    $types .= "ss";
}

/* ===== FILTRO DIRECCIÓN ===== */
if (!empty($_GET['direccion'])) {
    $sql .= " AND (t.direccion LIKE ? OR t.colonia LIKE ? OR t.localidad LIKE ?)";
    $params[] = '%'.$_GET['direccion'].'%';
    $params[] = '%'.$_GET['direccion'].'%';
    $params[] = '%'.$_GET['direccion'].'%';
    $types .= "sss";
}

/* ===== FILTRO CUENTA CATASTRAL ===== */
if (!empty($_GET['catastral'])) {
    $sql .= " AND t.cuenta_catastral LIKE ?";
    $params[] = '%'.$_GET['catastral'].'%'; $types .= "s";
}

/* ===== FILTRO TRÁMITE ===== */
if (!empty($_GET['tramite'])) {
    $sql .= " AND tt.nombre LIKE ?";
    $params[] = '%' . $_GET['tramite'] . '%';
    $types .= "s";
}

/* ===== FILTRO ESTATUS ===== */
if (!empty($_GET['estatus'])) {
    $sql .= " AND t.estatus = ?";
    $params[] = $_GET['estatus'];
    $types .= "s";
}

/* ===== FILTRO SIN FOTOGRAFÍA ===== */
if (isset($_GET['sin_foto']) && $_GET['sin_foto'] !== '') {
    if ($_GET['sin_foto'] === '1') {
        $sql .= " AND (t.foto1_archivo IS NULL OR t.foto1_archivo = '') AND (t.foto2_archivo IS NULL OR t.foto2_archivo = '')";
    } else {
        $sql .= " AND (t.foto1_archivo IS NOT NULL AND t.foto1_archivo != '')";
    }
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultado = $stmt->get_result();

$anio_actual = date("Y");

$stmt = $conn->prepare("
    SELECT COALESCE(MAX(CAST(folio_numero AS UNSIGNED)),0) + 1
    FROM tramites
    WHERE folio_anio = ?
");

$stmt->bind_param("i", $anio_actual);
$stmt->execute();
$stmt->bind_result($siguiente_folio);
$stmt->fetch();
$stmt->close();

/* formato 001 */
$siguiente_folio = str_pad($siguiente_folio, 3, "0", STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sis Dit</title>

<!-- BOOTSTRAP 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<!-- LEAFLET -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
     integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
     crossorigin=""/>

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<!-- SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

<!-- CSS PROPIO -->
 <link rel="stylesheet" href="./css/style.css?v=<?= time() ?>">
<style>
/* ================= VARIABLES ================= */
:root{
    --vino:#7b0f2b;
    --vino-oscuro:#5e0b20;
    --vino-claro:#a61c3c;
    --gris-fondo:#f4f6f9;
    --verde:#2f7d6d;
    --verde-oscuro:#246356;
}

/* ================= RESET ================= */
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

html, body{
    overflow-x: hidden;
    width: 100%;
    background: var(--gris-fondo);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* ================= SIDEBAR ================= */
.sidebar{
    width: 260px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    background: linear-gradient(180deg, var(--vino), var(--vino-oscuro));
    color: white;
    padding: 20px;
    z-index: 1000;
    overflow-y: auto;
    box-shadow: 4px 0 20px rgba(0,0,0,.15);
}

.sidebar h5{
    text-align: center;
    font-weight: 700;
    margin-bottom: 30px;
    color: white;
}

.sidebar a{
    color: white;
    text-decoration: none;
    display: block;
    padding: 14px 22px;
    transition: .25s;
    font-weight: 500;
}

.sidebar a:hover{
    background: rgba(255,255,255,.12);
    padding-left: 28px;
}

.sidebar a.text-danger{
    color: #ff6b6b !important;
}

.sidebar a.text-danger:hover{
    background: rgba(255,255,255,.12);
}

/* ================= CONTENT ================= */
.content{
    margin-left: 260px;
    width: calc(100% - 260px);
    padding: 30px;
    min-height: 100vh;
    transition: all 0.3s ease;
}

/* ================= HERO - EXACTAMENTE COMO EL ORIGINAL ================= */
.hero{
    background: transparent;
    color: #7b0f2b;
    text-align: left;
    padding: 0;
    margin-bottom: 30px;
}

.hero h1{
    font-size: 28px;
    color: #7b0f2b;
    font-weight: 700;
    text-align: left;
}

.hero p{
    color: #6c757d;
    text-align: left;
    font-size: 16px;
    line-height: 1.5;
    margin-top: 10px;
}

/* ================= TRAMITE BOX ================= */
.tramite-box{
    background: white;
    padding: 25px;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(0,0,0,.08);
    margin-bottom: 30px;
    overflow-x: auto;
    width: 100%;
}

.tramite-header{
    border-bottom: 2px solid rgba(0,0,0,.05);
    margin-bottom: 20px;
    padding-bottom: 15px;
}

/* ================= MAPA ================= */
#mapa{
    width: 100%;
    height: 420px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0,0,0,.12);
    background: #e8e8e8;
}

.leaflet-container{
    height: 100% !important;
    width: 100% !important;
    z-index: 1 !important;
    border-radius: 12px;
}

.leaflet-bar a {
    background-color: white !important;
    color: #7b0f2b !important;
    font-weight: 700;
    font-size: 16px;
}

.leaflet-bar a:hover {
    background-color: #7b0f2b !important;
    color: white !important;
}

.leaflet-control-zoom {
    border: 2px solid #7b0f2b !important;
    border-radius: 8px !important;
}

/* ================= BOTONES - COLORES ORIGINALES ================= */
.btn-primary{
    background: #7b0f2b !important;
    border-color: #7b0f2b !important;
    border-radius: 10px !important;
    color: white !important;
}

.btn-primary:hover{
    background: #5e0b20 !important;
    border-color: #5e0b20 !important;
}

.btn-outline-primary{
    border-color: #7b0f2b !important;
    color: #7b0f2b !important;
}

.btn-outline-primary:hover{
    background: #7b0f2b !important;
    color: white !important;
}

.btn-success{
    background: #7b0f2b !important;
    border: none !important;
    border-radius: 10px !important;
}

.btn-success:hover{
    background: #5e0b20 !important;
}

.btn-outline-secondary{
    color: #6c757d !important;
    border-color: #6c757d !important;
    border-radius: 10px !important;
}

.btn-outline-secondary:hover{
    background: #6c757d !important;
    color: white !important;
}

/* ================= TABLAS ================= */
.table-responsive{
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    width: 100%;
}

table.dataTable thead{
    background: #7b0f2b;
    color: white;
}

table.dataTable thead th{
    background: #7b0f2b !important;
    color: white !important;
}

/* ================= DATATABLES ================= */
.dataTables_wrapper{
    width: 100% !important;
    overflow-x: hidden;
}



/* ================= FILTROS ================= */
.form-control, .form-select{
    border-radius: 10px;
    border: 1px solid #ccc;
    padding: 14px;
    height: auto;
}

.form-control:focus, .form-select:focus{
    border-color: #2f7d6d;
    outline: none;
    box-shadow: none;
}

/* ================= SELECTOR DE TRÁMITE ================= */
.tramite-selector-card{
    border-radius: 14px !important;
    transition: all 0.25s ease !important;
    border: 2px solid #dee2e6 !important;
    cursor: pointer;
}

.tramite-selector-card:hover{
    border-color: #7b0f2b !important;
    box-shadow: 0 6px 24px rgba(123,15,43,0.14) !important;
    transform: translateY(-3px) !important;
}

/* ================= ALERTAS ================= */
.alert-info{
    background: rgba(123,15,43,.12) !important;
    color: #7b0f2b !important;
    border: none !important;
}

/* ================= RESPONSIVE ================= */
@media(max-width: 992px){
    .sidebar{
        position: relative;
        width: 100%;
        height: auto;
        margin-bottom: 20px;
    }
    
    .content{
        margin-left: 0;
        width: 100%;
        padding: 20px;
    }
    
    #mapa{
        height: 350px;
    }
}

@media(max-width: 768px){
    .content{
        padding: 15px;
    }
    
    .tramite-box{
        padding: 15px;
    }
    
    #mapa{
        height: 300px;
    }
    
    .hide-mobile{
        display: none !important;
    }
}

</style>

<script>
history.pushState(null, null, location.href);
window.onpopstate = function () {
    history.go(1);
};
</script>

</head>

<body>

<!-- NAVBAR MÓVIL -->
<nav class="navbar navbar-dark d-lg-none" style="background-color: #7b0f2b !important;">
    <div class="container-fluid">
        <span class="navbar-brand">Sistema Georreferenciado</span>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuMovil">
            <span class="navbar-toggler-icon"></span>
        </button>
    </div>
    <div class="collapse navbar-collapse" id="menuMovil">
        <ul class="navbar-nav p-3">
            <li class="nav-item"><a class="nav-link" href="#inicio"><i class="bi bi-house me-2"></i> Inicio</a></li>
            <li class="nav-item"><a class="nav-link" href="#tramite"><i class="bi bi-pencil-square me-2"></i> Ingreso de Trámite</a></li>
            <li class="nav-item"><a class="nav-link" href="#mapaa"><i class="bi bi-map me-2"></i> Mapa</a></li>
            <li class="nav-item"><a class="nav-link" href="#seguimiento"><i class="bi bi-search me-2"></i> Seguimiento</a></li>
            <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador'): ?>
            <li class="nav-item"><a class="nav-link" href="DashAdmin.php"><i class="bi bi-shield-lock me-2"></i> Panel de administración</a></li>
            <?php endif; ?>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión</a></li>
        </ul>
    </div>
</nav>

<!-- SIDEBAR -->
<div class="sidebar position-fixed d-none d-lg-flex flex-column p-3">
    <h5 class="text-white text-center mb-4">Menú</h5>
    <a class="nav-link text-white" href="#inicio"><i class="bi bi-house me-2"></i> Inicio</a>
    <a class="nav-link text-white" href="#tramite"><i class="bi bi-pencil-square me-2"></i> Ingreso de Trámite</a>
    <a class="nav-link text-white" href="#mapaa"><i class="bi bi-map me-2"></i> Mapa</a>
    <a class="nav-link text-white" href="#seguimiento"><i class="bi bi-search me-2"></i> Seguimiento</a>
    <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador'): ?>
    <a class="nav-link text-white border-top mt-2 pt-2" href="DashAdmin.php"><i class="bi bi-shield-lock me-2"></i> Panel de administración</a>
    <?php endif; ?>
    <a class="nav-link text-danger mt-auto" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión</a>
</div>

<!-- CONTENIDO -->
<div class="content">

    <!-- HERO - EXACTAMENTE COMO EL ORIGINAL -->
    <section class="hero" id="inicio">
        <h1>Bienvenido <?php echo $_SESSION['usuario']; ?></h1>
        <p>
            Plataforma digital para la gestión, captura y consulta de trámites
            georreferenciados del municipio. Desde aquí podrás acceder a los
            formatos oficiales y al registro de información territorial.
        </p>
    </section>
    <br><br>

    <!-- ================================================ -->
    <!-- PASO 1: SELECTOR DE TRÁMITE -->
    <!-- ================================================ -->
    <section id="tramite" class="tramite-box mb-4">

        <!-- PASO 1: SELECCIÓN DEL TRÁMITE -->
        <div id="paso1-seleccion">
            <div class="tramite-header d-flex justify-content-between align-items-center pb-3 mb-3 border-bottom">
                <h4 class="m-0" style="color:#7b0f2b;">
                    <i class="bi bi-list-task me-2"></i>
                    ¿Qué trámite desea realizar?
                </h4>
                <span class="badge bg-secondary">Paso 1 de 2</span>
            </div>

            <p class="text-muted mb-4">Seleccione el tipo de trámite para ver los requisitos necesarios y continuar con el registro.</p>

            <div class="row g-3 mb-4">
                <?php
                $tipos_prev = $conn->prepare("SELECT id, codigo, nombre, descripcion FROM tipos_tramite WHERE activo = 1 ORDER BY nombre");
                $tipos_prev->execute();
                $tipos_prev_result = $tipos_prev->get_result();
                $iconos = [
                    'NUM_OFICIAL'  => ['icon' => 'bi-123', 'color' => '#0d6efd'],
                    'CMCU'         => ['icon' => 'bi-building-check', 'color' => '#198754'],
                    'FUSION'       => ['icon' => 'bi-union', 'color' => '#fd7e14'],
                    'SUBDIVISION'  => ['icon' => 'bi-subtract', 'color' => '#6610f2'],
                    'INFORME_CU'   => ['icon' => 'bi-file-earmark-bar-graph', 'color' => '#0dcaf0'],
                ];
                $requisitosInfo = [
                    1 => ['docs' => ['INE o Pasaporte', 'Boleta Predial Vigente', 'Título de Propiedad o Escritura'], 'nota' => ''],
                    2 => ['docs' => ['INE o Pasaporte', 'Boleta Predial Vigente', 'Título de Propiedad o Escritura', 'Formato de Constancia'], 'nota' => 'Predios >10,000m² requieren levantamiento topográfico catastral.'],
                    3 => ['docs' => ['INE o Pasaporte', 'Boleta Predial Vigente', 'Título de Propiedad o Escritura'], 'nota' => ''],
                    4 => ['docs' => ['INE o Pasaporte', 'Boleta Predial Vigente', 'Título de Propiedad o Escritura'], 'nota' => ''],
                    5 => ['docs' => ['INE o Pasaporte', 'Cuenta Catastral del Predio'], 'nota' => ''],
                ];
                $tiposPrev = [];
                while ($tp = $tipos_prev_result->fetch_assoc()) {
                    $tiposPrev[] = $tp;
                }
                $tipos_prev->close();
                foreach ($tiposPrev as $tp):
                    $ic = $iconos[$tp['codigo']] ?? ['icon' => 'bi-file-earmark', 'color' => '#6c757d'];
                    $reqs = $requisitosInfo[$tp['id']] ?? ['docs' => [], 'nota' => ''];
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-2 tramite-selector-card" 
                         style="cursor:pointer; transition:all 0.25s; border-color:#dee2e6;"
                         onclick="seleccionarTramite(<?= $tp['id'] ?>, '<?= htmlspecialchars(addslashes($tp['nombre'])) ?>')">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div style="width:46px;height:46px;border-radius:12px;background:<?= $ic['color'] ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="bi <?= $ic['icon'] ?>" style="font-size:1.4rem;color:<?= $ic['color'] ?>;"></i>
                                </div>
                                <div>
                                    <strong style="font-size:0.92rem;color:#212529;"><?= htmlspecialchars($tp['nombre']) ?></strong>
                                    <div class="text-muted" style="font-size:0.75rem;">10 días hábiles</div>
                                </div>
                            </div>
                            <?php if (!empty($reqs['docs'])): ?>
                            <ul class="list-unstyled mb-1" style="font-size:0.78rem;color:#555;">
                                <?php foreach($reqs['docs'] as $doc): ?>
                                <li><i class="bi bi-check2 text-success me-1"></i><?= $doc ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                            <?php if (!empty($reqs['nota'])): ?>
                            <div class="alert alert-warning py-1 px-2 mb-0 mt-1" style="font-size:0.72rem;">
                                <i class="bi bi-exclamation-triangle me-1"></i><?= $reqs['nota'] ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent border-top-0 text-end py-2 px-3">
                            <span style="font-size:0.8rem;color:<?= $ic['color'] ?>;font-weight:600;">
                                Seleccionar <i class="bi bi-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ALERTA CON WHATSAPP Y CORREO -->
            <div class="alert alert-secondary" style="font-size:0.83rem;">
                <i class="bi bi-info-circle me-2"></i>
                Todos los requisitos deberán entregarse <strong>en copia</strong>. Si el trámite será realizado por un tercero, deberá presentar una <strong>carta poder</strong>.
                <br><br>
                <i class="bi bi-telephone me-1"></i> WhatsApp: <strong>449 807 78 99</strong> &nbsp;|&nbsp;
                <i class="bi bi-envelope me-1"></i> <strong>dir.planeacionydu@gmail.com</strong>
            </div>
        </div>

        <!-- PASO 2: FORMULARIO DE REGISTRO -->
        <div id="paso2-formulario" style="display:none;">
            <div class="tramite-header d-flex justify-content-between align-items-center pb-3 mb-3 border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="volverSeleccion()">
                        <i class="bi bi-arrow-left me-1"></i>Regresar
                    </button>
                    <h4 class="m-0" style="color:#7b0f2b;">
                        <i class="bi bi-pencil-square me-2"></i>
                        Registro de Trámite: <span id="titulo-tramite-paso2"></span>
                    </h4>
                </div>
                <span class="badge bg-success">Paso 2 de 2</span>
            </div>

            <!-- Requisitos recordatorio (se llena con JS) -->
            <div class="alert alert-success mb-4" id="recordatorio-requisitos" style="font-size:0.85rem;">
                <h6 class="alert-heading"><i class="bi bi-clipboard-check me-2"></i>Documentos que debe tener listos:</h6>
                <ul id="lista-req-recordatorio" class="mb-0 mt-2"></ul>
            </div>

            <!-- FORMULARIO -->
            <form action="php/tramite.php" method="POST" enctype="multipart/form-data">
                <?php $csrf = generarCSRF(); ?>
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="tipo_tramite_id" id="tipo_tramite_id_hidden">

                <div class="tramite-header d-flex justify-content-between align-items-center pb-2">
                    <h4 class="m-0" style="color: #7b0f2b;">Ingreso de Trámite</h4>
                    <div class="folio d-flex align-items-center gap-1">
                        <label class="form-label m-0">Folio:</label>
                        <span><?= $siguiente_folio ?></span>
                        <span>/<?= $anio_actual ?></span>
                        <input type="hidden" name="folio_numero" value="<?= $siguiente_folio ?>">
                        <input type="hidden" name="folio_anio" value="<?= $anio_actual ?>">
                    </div>
                </div>

                <?php if (!empty($_GET['error_msg'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars(urldecode($_GET['error_msg'])) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Nombre del Propietario <span class="text-danger">*</span></label>
                    <input type="text" class="form-control solo-letras" name="propietario" required
                        placeholder="SOLO LETRAS MAYÚSCULAS"
                        pattern="[A-ZÁÉÍÓÚÜÑ\s]+"
                        title="Solo letras mayúsculas, sin símbolos ni caracteres especiales"
                        oninput="this.value = this.value.toUpperCase().replace(/[^A-ZÁÉÍÓÚÜÑ\s]/g, '')"
                        style="text-transform: uppercase;">
                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Solo letras mayúsculas, sin números ni símbolos.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Dirección <span class="text-danger">*</span></label>
                    <input type="text" class="form-control mayusculas" name="direccion" required placeholder="CALLE, NÚMERO, COLONIA">
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Localidad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control mayusculas" name="localidad" required placeholder="EJ: AGUASCALIENTES">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Colonia</label>
                        <input type="text" class="form-control mayusculas" name="colonia" placeholder="EJ: CENTRO">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Código Postal</label>
                        <input type="text" class="form-control" name="cp" 
                            maxlength="5" 
                            pattern="[0-9]{5}"
                            inputmode="numeric"
                            placeholder="Ej: 20400"
                            title="5 dígitos numéricos"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 5)">
                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i>5 dígitos numéricos</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Cuenta Catastral
                          <span class="badge bg-success ms-1" style="font-size:.65rem;">Auto</span>
                        </label>
                        <input type="text" class="form-control" name="cuenta_catastral" id="cuenta_catastral"
                               inputmode="numeric"
                               placeholder="Automática si se deja vacía"
                               oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                        <small class="text-muted">
                          <i class="bi bi-info-circle me-1"></i>Solo números.
                          Si se deja vacía se asigna automáticamente.
                        </small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Superficie</label>
                        <input type="text" class="form-control" name="superficie" placeholder="Ej: 200 m2">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">UTM X (Este)</label>
                        <input type="text" class="form-control" name="lat" id="lat" placeholder="Ej: 284500.00">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">UTM Y (Norte)</label>
                        <input type="text" class="form-control" name="lng" id="lng" placeholder="Ej: 2460500.00">
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Tip:</strong> Haz clic en el mapa abajo para seleccionar la ubicación automáticamente. Las coordenadas se mostrarán en formato UTM (Zona 13N).
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Tipo de Trámite</label>
                    <input type="hidden" name="tipo_tramite_id" id="tipo_tramite_id">
                    <div class="form-control bg-light fw-semibold" style="color:#198754;">
                        <i class="bi bi-check-circle-fill me-2 text-success"></i>
                        <span id="label-tipo-tramite-form">—</span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Fecha de Ingreso <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="fecha_ingreso" id="fechaIngreso">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fecha de Entrega <span class="badge bg-success ms-1" style="font-size:.7rem;">10 días hábiles automático</span></label>
                        <input type="date" class="form-control bg-light" name="fecha_entrega" id="fechaEntrega" readonly
                               style="background:#f8f9fa;cursor:not-allowed;">
                        <small class="text-muted"><i class="bi bi-calendar-check me-1"></i>Se calcula automáticamente: 10 días hábiles desde ingreso</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nombre del Solicitante <span class="text-danger">*</span></label>
                    <input type="text" name="solicitante" class="form-control solo-letras" required
                        placeholder="SOLO LETRAS MAYÚSCULAS"
                        pattern="[A-ZÁÉÍÓÚÜÑ\s]+"
                        title="Solo letras mayúsculas y espacios (sin números ni símbolos)"
                        oninput="this.value = this.value.toUpperCase().replace(/[^A-ZÁÉÍÓÚÜÑ\s]/g, '')"
                        style="text-transform: uppercase;">
                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Solo letras mayúsculas, sin números ni símbolos.</small>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Teléfono <span class="text-danger">*</span></label>
                        <input type="text" name="telefono" class="form-control" 
                            placeholder="Ej: 4491234567" 
                            required 
                            maxlength="10"
                            pattern="[0-9]{10}"
                            inputmode="numeric"
                            title="10 dígitos numéricos"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i>10 dígitos (ej: 4491234567)</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Correo Electrónico <span class="text-muted" style="font-size:.75rem;">(opcional)</span></label>
                        <input type="email" name="correo" class="form-control" 
                            placeholder="ejemplo@correo.com"
                            oninput="this.value = this.value.toLowerCase()"
                            style="text-transform: lowercase;">
                    </div>
                </div>

                <!-- SECCION DE DOCUMENTOS REQUERIDOS -->
                <section id="seccion-documentos" class="tramite-box mb-4" style="display: none;">
                    <h5 class="mb-1" style="color: #7b0f2b;">
                        <i class="bi bi-file-earmark-check me-2"></i>
                        Documentos: <span id="titulo-tramite-seleccionado"></span>
                    </h5>
                    <p class="text-muted mb-3" style="font-size:.83rem;">
                        <i class="bi bi-info-circle me-1"></i>
                        La carga de archivos es <strong>opcional</strong>. Si no adjunta algún documento, justifique el motivo en el campo de comentarios.
                    </p>
                    
                    <div class="alert alert-light border mb-4">
                        <h6 class="alert-heading"><i class="bi bi-list-check me-2"></i>Documentación recomendada:</h6>
                        <ul id="lista-requisitos" class="list-group list-group-flush mb-0"></ul>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-4" id="grupo-ine" style="display: none;">
                            <label class="form-label fw-bold"><i class="bi bi-person-vcard me-1"></i>INE o Pasaporte <span class="badge bg-secondary" style="font-size:.65rem;">Opcional</span></label>
                            <input type="file" class="form-control" name="ine" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">Formatos: PDF, JPG, PNG (Max. 5MB)</small>
                        </div>
                        <div class="col-md-4 mb-4" id="grupo-predial" style="display: none;">
                            <label class="form-label fw-bold"><i class="bi bi-receipt me-1"></i>Boleta Predial Vigente <span class="badge bg-secondary" style="font-size:.65rem;">Opcional</span></label>
                            <input type="file" class="form-control" name="predial" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">Formatos: PDF, JPG, PNG (Max. 5MB)</small>
                        </div>
                        <div class="col-md-4 mb-4" id="grupo-escritura" style="display: none;">
                            <label class="form-label fw-bold"><i class="bi bi-file-earmark-text me-1"></i>Escritura / Título de Propiedad <span class="badge bg-secondary" style="font-size:.65rem;">Opcional</span></label>
                            <input type="file" class="form-control" name="escritura" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">Formatos: PDF, JPG, PNG (Max. 5MB)</small>
                        </div>
                        <div class="col-md-4 mb-4" id="grupo-formato_constancia" style="display: none;">
                            <label class="form-label fw-bold"><i class="bi bi-file-earmark-ruled me-1"></i>Formato de Constancia <span class="badge bg-secondary" style="font-size:.65rem;">Opcional</span></label>
                            <input type="file" class="form-control" name="formato_constancia" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">Formatos: PDF, JPG, PNG (Max. 5MB)</small>
                        </div>
                    </div>

                    <!-- Comentario justificación de documentos faltantes -->
                    <div class="mt-2 p-3 border rounded" style="background:#fffbf0;">
                        <label class="form-label fw-bold text-warning-emphasis">
                            <i class="bi bi-chat-left-text me-1"></i>Comentarios / Justificación de documentos faltantes
                        </label>
                        <textarea name="comentario_sin_doc" class="form-control" rows="3"
                            placeholder="Ej: El solicitante no presenta INE porque tramita con carta poder. Escrituras pendientes de notaría..."></textarea>
                        <small class="text-muted">Escriba aquí el motivo por el cual no se adjuntan todos los documentos requeridos.</small>
                    </div>
                </section>

                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>NOTA:</strong> Para recoger su trámite, deberá presentar esta papeleta original.
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-save me-2"></i>Guardar Trámite
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- MAPA -->
    <section id="mapaa" class="tramite-box mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h4 class="m-0" style="color: #7b0f2b;">
                <i class="bi bi-map me-2"></i>Mapa Georreferenciado
            </h4>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="centrarMapa()">
                    <i class="bi bi-geo-alt me-1"></i>Centrar municipio
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="map.invalidateSize()">
                    <i class="bi bi-arrows-fullscreen me-1"></i>Ajustar mapa
                </button>
            </div>
        </div>
        
        <div class="alert alert-info py-2 mb-3" style="font-size:0.85rem;">
            <i class="bi bi-cursor me-2"></i>
            <strong>Tip:</strong> Haz clic en el mapa para capturar las coordenadas UTM del predio. También puedes escribir la cuenta catastral y el mapa se actualizará automáticamente.
        </div>
        
        <div id="coords-display" class="mb-2 px-2 py-1 rounded border bg-light d-none" style="font-size:0.82rem;font-family:monospace;">
            <i class="bi bi-pin-map me-1 text-danger"></i>
            <span id="coords-texto">Sin ubicación seleccionada</span>
        </div>
        
        <div style="position:relative;width:100%;height:420px;overflow:hidden;border-radius:12px;background:#ddd;">
            <div id="mapa" style="position:absolute;top:0;left:0;width:100%;height:100%;"></div>
        </div>
        
        <div class="row mt-2 text-muted" style="font-size:0.75rem;">
            <div class="col-6"><i class="bi bi-layers me-1"></i>OpenStreetMap © colaboradores</div>
            <div class="col-6 text-end"><i class="bi bi-grid me-1"></i>Coordenadas: UTM Zona 13N</div>
        </div>
    </section>

    <!-- SEGUIMIENTO -->
    <section id="seguimiento" class="tramite-box mb-4">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h4 class="m-0" style="color: #7b0f2b;">
                <i class="bi bi-search me-2"></i>Seguimiento de Trámites
            </h4>
            <span class="badge bg-secondary fs-6"><?= $resultado ? $resultado->num_rows : 0 ?> trámite(s)</span>
        </div>

        <!-- FILTROS MEJORADOS -->
        <form method="GET" class="mb-4">
            <div class="row g-3 align-items-end mb-3">
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold"><i class="bi bi-hash me-1"></i>Folio</label>
                    <input type="text" name="folio" class="form-control" placeholder="001/2026" value="<?= htmlspecialchars($_GET['folio'] ?? '') ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold"><i class="bi bi-person me-1"></i>Nombre / Propietario</label>
                    <input type="text" name="nombre" class="form-control" placeholder="Buscar por nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold"><i class="bi bi-geo-alt me-1"></i>Dirección</label>
                    <input type="text" name="direccion" class="form-control" placeholder="Calle o colonia" value="<?= htmlspecialchars($_GET['direccion'] ?? '') ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold"><i class="bi bi-123 me-1"></i>Cuenta Catastral</label>
                    <input type="text" name="catastral" class="form-control" placeholder="Ej: 2026000001" inputmode="numeric"
                           oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                           value="<?= htmlspecialchars($_GET['catastral'] ?? '') ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold"><i class="bi bi-file-text me-1"></i>Tipo de Trámite</label>
                    <input type="text" name="tramite" class="form-control" placeholder="Ej: Constancia" value="<?= htmlspecialchars($_GET['tramite'] ?? '') ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold"><i class="bi bi-flag me-1"></i>Estatus</label>
                    <select name="estatus" class="form-select">
                        <option value="">Todos los estatus</option>
                        <option value="En revisión" <?= (($_GET['estatus'] ?? '') === 'En revisión') ? 'selected' : '' ?>>En revisión</option>
                        <option value="Aprobado por Verificador" <?= (($_GET['estatus'] ?? '') === 'Aprobado por Verificador') ? 'selected' : '' ?>>Aprobado por Verificador</option>
                        <option value="Aprobado" <?= (($_GET['estatus'] ?? '') === 'Aprobado') ? 'selected' : '' ?>>Aprobado</option>
                        <option value="Rechazado" <?= (($_GET['estatus'] ?? '') === 'Rechazado') ? 'selected' : '' ?>>Rechazado</option>
                        <option value="En corrección" <?= (($_GET['estatus'] ?? '') === 'En corrección') ? 'selected' : '' ?>>En corrección</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold"><i class="bi bi-camera me-1"></i>Filtro Fotografía</label>
                    <select name="sin_foto" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" <?= (($_GET['sin_foto'] ?? '') === '1') ? 'selected' : '' ?>>Sin fotografía</option>
                        <option value="0" <?= (($_GET['sin_foto'] ?? '') === '0') ? 'selected' : '' ?>>Con fotografía</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 d-flex gap-2 align-items-end">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-search me-1"></i>Buscar
                    </button>
                    <a href="Dash.php#seguimiento" class="btn btn-outline-secondary" title="Limpiar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </div>
        </form>

        <div class="table-responsive rounded" style="border:1px solid #dee2e6;">
            <table id="tablaTramites" class="table table-hover table-striped mb-0" style="font-size:0.88rem;">
                <thead style="background:var(--vino);color:white;position:sticky;top:0;">
                    <tr>
                        <th style="white-space:nowrap;padding:12px 10px;">Folio</th>
                        <th style="padding:12px 10px;">Propietario</th>
                        <th class="hide-mobile" style="padding:12px 10px;">Trámite</th>
                        <th class="hide-mobile" style="white-space:nowrap;padding:12px 10px;">F. Ingreso</th>
                        <th style="text-align:center;padding:12px 10px;">Estatus</th>
                        <th style="text-align:center;padding:12px 10px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado && $resultado->num_rows > 0): ?>
                        <?php while ($t = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td><?= $t['folio_numero'] ?>/<?= $t['folio_anio'] ?></td>
                            <td><?= htmlspecialchars($t['propietario']) ?></td>
                            <td><?= htmlspecialchars($t['tipo_tramite_nombre'] ?? 'Sin tipo') ?></td>
                            <td><?= date('d/m/Y', strtotime($t['fecha_ingreso'])) ?></td>
                            <td class="text-center">
                                <?php
                                    $badge = match ($t['estatus']) {
                                        'En revisión' => 'bg-warning text-dark',
                                        'Aprobado' => 'bg-success',
                                        'Rechazado' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };
                                ?>
                                <span class="badge <?= $badge ?>"><?= $t['estatus'] ?></span>
                            </td>
                            <td class="text-center">
                                <button 
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#detalleTramite"
                                    data-folio="<?= $t['folio_numero'].'/'.$t['folio_anio'] ?>"
                                    data-estatus="<?= htmlspecialchars($t['estatus']) ?>"
                                    data-observaciones="<?= htmlspecialchars($t['observaciones'] ?? '') ?>"
                                    data-propietario="<?= htmlspecialchars($t['propietario']) ?>"
                                    data-direccion="<?= htmlspecialchars($t['direccion']) ?>"
                                    data-localidad="<?= htmlspecialchars($t['localidad']) ?>"
                                    data-tramites="<?= htmlspecialchars($t['tipo_tramite_nombre'] ?? 'Sin tipo') ?>"
                                    data-tramite-id="<?= $t['tipo_tramite_id'] ?? '' ?>"
                                    data-fecha="<?= date('d/m/Y', strtotime($t['fecha_ingreso'])) ?>"
                                    data-ine="<?= $t['ine_archivo'] ?? '' ?>"
                                    data-escritura="<?= $t['escrituras_archivo'] ?? $t['titulo_archivo'] ?? '' ?>"
                                    data-predial="<?= $t['predial_archivo'] ?? '' ?>"
                                    data-formato-constancia="<?= $t['formato_constancia'] ?? '' ?>"
                                    data-comentario-sin-doc="<?= htmlspecialchars($t['comentario_sin_doc'] ?? '') ?>"
                                >
                                    Ver detalles
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">No se encontraron trámites</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<!-- MODAL DETALLE TRÁMITE -->
<div class="modal fade" id="detalleTramite" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-folder-check me-2"></i> Detalle del Trámite</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Folio:</strong> <span id="m_folio"></span></p>
                        <p><strong>Propietario:</strong> <span id="m_propietario"></span></p>
                        <p><strong>Dirección:</strong> <span id="m_direccion"></span></p>
                        <p><strong>Localidad:</strong> <span id="m_localidad"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Trámites:</strong> <span id="m_tramites"></span></p>
                        <p><strong>Fecha ingreso:</strong> <span id="m_fecha"></span></p>
                        <p><strong>Estatus:</strong> <span id="m_estatus" class="badge"></span></p>
                    </div>
                </div>
                <hr>
                <div class="mb-3">
                    <h6 class="text-primary">Observaciones</h6>
                    <p id="m_observaciones" class="border rounded p-2 bg-light"></p>
                </div>
                <div>
                    <h6 class="text-primary">Documentos Cargados</h6>
                    <div id="modal-sin-documentos" class="text-muted mb-2" style="display:none;">
                        <i class="bi bi-info-circle me-1"></i> No hay documentos cargados para este tramite.
                    </div>
                    <div class="list-group mb-3">
                        <a id="doc_ine" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" target="_blank" style="display:none;">
                            <span><i class="bi bi-file-earmark-person me-2"></i> INE o Pasaporte</span>
                            <span class="badge bg-primary">Ver</span>
                        </a>
                        <a id="doc_escritura" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" target="_blank" style="display:none;">
                            <span><i class="bi bi-file-earmark-text me-2"></i> Escritura Publica / Titulo</span>
                            <span class="badge bg-primary">Ver</span>
                        </a>
                        <a id="doc_predial" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" target="_blank" style="display:none;">
                            <span><i class="bi bi-file-earmark-text me-2"></i> Boleta Predial</span>
                            <span class="badge bg-primary">Ver</span>
                        </a>
                        <a id="doc_formato_constancia" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" target="_blank" style="display:none;">
                            <span><i class="bi bi-file-earmark-ruled me-2"></i> Formato de Constancia</span>
                            <span class="badge bg-primary">Ver</span>
                        </a>
                    </div>
                    
                    <!-- Seccion de documentos faltantes -->
                    <div id="seccion-docs-faltantes" style="display:none;">
                        <h6 class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Documentos Faltantes</h6>
                        <div class="list-group mb-2">
                            <div id="doc_faltante_ine" class="list-group-item list-group-item-warning" style="display:none;">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-x-circle text-danger me-2"></i>
                                    <span>INE o Pasaporte</span>
                                </div>
                            </div>
                            <div id="doc_faltante_escritura" class="list-group-item list-group-item-warning" style="display:none;">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-x-circle text-danger me-2"></i>
                                    <span>Escritura Publica / Titulo</span>
                                </div>
                            </div>
                            <div id="doc_faltante_predial" class="list-group-item list-group-item-warning" style="display:none;">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-x-circle text-danger me-2"></i>
                                    <span>Boleta Predial</span>
                                </div>
                            </div>
                            <div id="doc_faltante_formato" class="list-group-item list-group-item-warning" style="display:none;">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-x-circle text-danger me-2"></i>
                                    <span>Formato de Constancia</span>
                                </div>
                            </div>
                        </div>
                        <div id="comentario-docs-faltantes" class="alert alert-warning" style="display:none;">
                            <strong><i class="bi bi-chat-left-text me-1"></i>Motivo:</strong>
                            <p id="texto-comentario-faltantes" class="mb-0 mt-1"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a id="btn_imprimir_ficha" href="#" target="_self" class="btn btn-outline-primary">
                    <i class="bi bi-printer me-1"></i> Imprimir Ficha
                </a>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.7.5/proj4.js"></script>

<script>
// ==========================================
// CONFIGURACIÓN DATATABLES - DESACTIVADA PARA EVITAR ERROR
// ==========================================
// DataTables desactivado porque causa error de columnas.
// La tabla se muestra correctamente con Bootstrap.

// Si en el futuro quieres reactivarlo, descomenta el código de abajo
// y asegúrate de que el número de columnas coincida exactamente.

/*
$(document).ready(function() {
    setTimeout(function() {
        // Verificar que la tabla existe y tiene datos reales
        var $tabla = $('#tablaTramites');
        var hayDatosReales = false;
        
        if ($tabla.length && $tabla.find('tbody tr').length > 0) {
            // Verificar que no sea solo el mensaje de "No se encontraron trámites"
            $tabla.find('tbody tr').each(function() {
                if (!$(this).text().includes('No se encontraron trámites')) {
                    hayDatosReales = true;
                }
            });
        }
        
        if ($tabla.length && hayDatosReales) {
            $('#tablaTramites').DataTable({
                "pageLength": 10,
                "lengthChange": true,
                "lengthMenu": [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Todos"]],
                "ordering": true,
                "responsive": true,
                "columnDefs": [
                    { "orderable": false, "targets": -1 }
                ],
                "language": {
                    "paginate": {
                        "previous": "Anterior",
                        "next": "Siguiente",
                        "first": "Primero",
                        "last": "Último"
                    },
                    "info": "Mostrando _START_ a _END_ de _TOTAL_ trámites",
                    "infoEmpty": "No hay trámites para mostrar",
                    "infoFiltered": "(filtrado de _MAX_ trámites totales)",
                    "zeroRecords": "No se encontraron resultados",
                    "search": "Buscar:",
                    "lengthMenu": "Mostrar _MENU_ trámites",
                    "loadingRecords": "Cargando...",
                    "processing": "Procesando..."
                },
                "dom": '<"row mb-3"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row mt-3"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
            });
        }
    }, 100);
});
*/
</script>

<script>
// ==========================================
// CONFIGURACIÓN MAPA
// ==========================================
proj4.defs('EPSG:32613', '+proj=utm +zone=13 +datum=WGS84 +units=m +no_defs');
const CENTRO_MUNICIPIO = [22.228, -102.322];
const map = L.map('mapa', { zoomControl: true, scrollWheelZoom: true, tap: true }).setView(CENTRO_MUNICIPIO, 14);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap colaboradores'
}).addTo(map);

setTimeout(() => { map.invalidateSize(); }, 200);
setTimeout(() => { map.invalidateSize(); }, 500);
setTimeout(() => { map.invalidateSize(); }, 1000);

let marker;
const utmXInput = document.getElementById('lat');
const utmYInput = document.getElementById('lng');

function centrarMapa() {
    map.setView(CENTRO_MUNICIPIO, 14);
}

map.on('click', e => {
    var lat = e.latlng.lat.toFixed(5);
    var lon = e.latlng.lng.toFixed(5);
    var utmCoords = proj4('EPSG:4326', 'EPSG:32613', [parseFloat(lon), parseFloat(lat)]);
    var utmX = utmCoords[0].toFixed(2);
    var utmY = utmCoords[1].toFixed(2);
    
    utmXInput.value = utmX;
    utmYInput.value = utmY;
    
    document.getElementById('coords-display').classList.remove('d-none');
    document.getElementById('coords-texto').textContent = `UTM X: ${utmX} | UTM Y: ${utmY} | Lat: ${lat}, Lon: ${lon}`;
    
    if (marker) {
        marker.setLatLng(e.latlng);
    } else {
        marker = L.marker(e.latlng).addTo(map);
    }
});

// ==========================================
// AUTO MAYÚSCULAS en campos con clase .mayusculas
// ==========================================
document.querySelectorAll('.mayusculas').forEach(input => {
    input.addEventListener('input', function() {
        const pos = this.selectionStart;
        this.value = this.value.toUpperCase().replace(/[^A-ZÁÉÍÓÚÜÑ0-9\s\.\,\#\/\-]/gi, '');
        try { this.setSelectionRange(pos, pos); } catch(e) {}
    });
    input.addEventListener('blur', function() {
        this.value = this.value.toUpperCase().trim();
    });
});

// ==========================================
// FECHAS — entrega AUTOMÁTICA 10 días hábiles
// ==========================================
const hoy = new Date();
const yyyy = hoy.getFullYear();
const mm = String(hoy.getMonth() + 1).padStart(2, '0');
const dd = String(hoy.getDate()).padStart(2, '0');
const fechaHoy = `${yyyy}-${mm}-${dd}`;

document.getElementById('fechaIngreso').value = fechaHoy;

function calcularDiasHabiles(fechaStr, dias) {
    const fecha = new Date(fechaStr + 'T00:00:00');
    let count = 0;
    while (count < dias) {
        fecha.setDate(fecha.getDate() + 1);
        const dow = fecha.getDay(); // 0=dom, 6=sab
        if (dow !== 0 && dow !== 6) count++;
    }
    return fecha.toISOString().split('T')[0];
}

function actualizarFechaEntrega() {
    const ing = document.getElementById('fechaIngreso').value;
    if (ing) {
        document.getElementById('fechaEntrega').value = calcularDiasHabiles(ing, 10);
    }
}

actualizarFechaEntrega();
document.getElementById('fechaIngreso').addEventListener('change', actualizarFechaEntrega);

// ==========================================
// REQUISITOS POR TRÁMITE
// ==========================================
const requisitosPorTramite = {
    1: { titulo: "Constancia de Número Oficial", documentos: ['ine', 'escritura', 'predial'], nota: '' },
    2: { titulo: "Constancia de Compatibilidad Urbanística", documentos: ['ine', 'escritura', 'predial', 'formato_constancia'], nota: 'Predios menores a 10,000m²: plano catastral. Predios mayores: levantamiento topográfico catastral.' },
    3: { titulo: "Fusión de Predios", documentos: ['ine', 'escritura', 'predial'], nota: '' },
    4: { titulo: "Subdivisión de Predio", documentos: ['ine', 'escritura', 'predial'], nota: '' },
    5: { titulo: "Informe de Compatibilidad Urbanística", documentos: ['ine'], nota: 'Requiere cuenta catastral del predio.' }
};

const labelsDocumentos = {
    'ine': 'INE o Pasaporte',
    'escritura': 'Escritura Pública / Título de Propiedad',
    'predial': 'Boleta Predial Vigente',
    'formato_constancia': 'Formato de Constancia CMCU 2025'
};

function seleccionarTramite(id, nombre) {
    document.getElementById('tipo_tramite_id_hidden').value = id;
    document.getElementById('tipo_tramite_id').value = id;
    document.getElementById('label-tipo-tramite-form').textContent = nombre;
    document.getElementById('titulo-tramite-paso2').textContent = nombre;

    const tramite = requisitosPorTramite[id];
    if (tramite) {
        const lista = document.getElementById('lista-req-recordatorio');
        let html = tramite.documentos.map(d => `<div><i class="bi bi-check2-circle me-2 text-success"></i>${labelsDocumentos[d] || d}</div>`).join('');
        if (tramite.nota) {
            html += `<div class="mt-1 text-warning"><i class="bi bi-exclamation-triangle me-2"></i>${tramite.nota}</div>`;
        }
        html += `<div class="mt-1 text-muted" style="font-size:0.82rem;"><i class="bi bi-info-circle me-2"></i>Todos los requisitos en <strong>copia</strong>. Si es tercero: <strong>carta poder</strong>.</div>`;
        lista.innerHTML = html;
        actualizarRequisitos(id);
    }

    document.getElementById('paso1-seleccion').style.display = 'none';
    document.getElementById('paso2-formulario').style.display = 'block';
    document.getElementById('tramite').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function volverSeleccion() {
    document.getElementById('paso2-formulario').style.display = 'none';
    document.getElementById('paso1-seleccion').style.display = 'block';
    document.getElementById('tramite').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function actualizarRequisitos(tramiteId) {
    const seccionDocumentos = document.getElementById('seccion-documentos');
    const listaRequisitos = document.getElementById('lista-requisitos');

    resetearCampos();

    if (!tramiteId || !requisitosPorTramite[tramiteId]) {
        seccionDocumentos.style.display = 'none';
        return;
    }

    const tramite = requisitosPorTramite[tramiteId];
    seccionDocumentos.style.display = 'block';
    document.getElementById('titulo-tramite-seleccionado').textContent = tramite.titulo;

    listaRequisitos.innerHTML = tramite.documentos.map(doc => `
        <li class="list-group-item d-flex align-items-center">
            <i class="bi bi-check-circle text-success me-2"></i>
            ${labelsDocumentos[doc] || doc}
        </li>`).join('');

    tramite.documentos.forEach(doc => {
        const campo = document.getElementById(`grupo-${doc}`);
        if (campo) {
            campo.style.display = 'block';
        }
    });
}

function resetearCampos() {
    document.querySelectorAll('[id^="grupo-"]').forEach(grupo => {
        grupo.style.display = 'none';
        const input = grupo.querySelector('input');
        if (input) input.removeAttribute('required');
    });
    const seccionDocumentos = document.getElementById('seccion-documentos');
    if (seccionDocumentos) seccionDocumentos.style.display = 'none';
}

// ==========================================
// BUSCAR CUENTA CATASTRAL
// ==========================================
let timeoutBusqueda;
document.getElementById("cuenta_catastral")?.addEventListener("input", function(){
    clearTimeout(timeoutBusqueda);
    let cuenta = this.value.trim();
    
    timeoutBusqueda = setTimeout(() => {
        if(cuenta.length < 3) return;
        
        fetch("php/buscar_catastral.php?cuenta=" + encodeURIComponent(cuenta))
        .then(response => response.json())
        .then(data => {
            if(data && data.utm_x && data.utm_y){
                let utmX = parseFloat(data.utm_x);
                let utmY = parseFloat(data.utm_y);
                
                document.getElementById("lat").value = utmX.toFixed(2);
                document.getElementById("lng").value = utmY.toFixed(2);
                
                let latlng = proj4("EPSG:32613","EPSG:4326",[utmX, utmY]);
                let lat = latlng[1];
                let lng = latlng[0];
                let punto = L.latLng(lat, lng);
                
                if (typeof map !== 'undefined') {
                    map.setView(punto, 18);
                    if(typeof marker !== 'undefined' && marker){
                        map.removeLayer(marker);
                    }
                    marker = L.marker(punto).addTo(map);
                }
            }
        })
        .catch(error => console.error("Error:", error));
    }, 600);
});

// ==========================================
// MODAL DETALLE TRÁMITE
// ==========================================
function initDash() {
    document.querySelectorAll('[data-bs-target="#detalleTramite"]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('m_folio').textContent = btn.dataset.folio;
            document.getElementById('m_propietario').textContent = btn.dataset.propietario;
            document.getElementById('m_direccion').textContent = btn.dataset.direccion;
            document.getElementById('m_localidad').textContent = btn.dataset.localidad;
            document.getElementById('m_tramites').textContent = btn.dataset.tramites;
            document.getElementById('m_fecha').textContent = btn.dataset.fecha;
            document.getElementById('m_observaciones').textContent = btn.dataset.observaciones || 'Sin observaciones';

            const estatusSpan = document.getElementById('m_estatus');
            estatusSpan.textContent = btn.dataset.estatus;
            estatusSpan.className = 'badge';
            if (btn.dataset.estatus === 'En revisión') estatusSpan.classList.add('bg-warning','text-dark');
            else if (btn.dataset.estatus === 'Aprobado') estatusSpan.classList.add('bg-success');
            else if (btn.dataset.estatus === 'Rechazado') estatusSpan.classList.add('bg-danger');
            else estatusSpan.classList.add('bg-secondary');

            const docs = {
                ine: document.getElementById('doc_ine'),
                escritura: document.getElementById('doc_escritura'),
                predial: document.getElementById('doc_predial'),
                formato_constancia: document.getElementById('doc_formato_constancia')
            };
            const docsFaltantes = {
                ine: document.getElementById('doc_faltante_ine'),
                escritura: document.getElementById('doc_faltante_escritura'),
                predial: document.getElementById('doc_faltante_predial'),
                formato: document.getElementById('doc_faltante_formato')
            };
            const sinDocumentos = document.getElementById('modal-sin-documentos');
            const seccionFaltantes = document.getElementById('seccion-docs-faltantes');
            const comentarioFaltantes = document.getElementById('comentario-docs-faltantes');
            const textoComentario = document.getElementById('texto-comentario-faltantes');

            Object.values(docs).forEach(doc => { if (doc) doc.style.display = 'none'; });
            Object.values(docsFaltantes).forEach(doc => { if (doc) doc.style.display = 'none'; });
            if (sinDocumentos) sinDocumentos.style.display = 'none';
            if (seccionFaltantes) seccionFaltantes.style.display = 'none';
            if (comentarioFaltantes) comentarioFaltantes.style.display = 'none';

            let hayDocumentos = false;
            let hayFaltantes = false;

            const tramiteId = parseInt(btn.dataset.tramiteId) || 0;
            const docsRequeridos = requisitosPorTramite[tramiteId]?.documentos || ['ine', 'escritura', 'predial'];

            const ineArchivo = btn.dataset.ine || '';
            if (ineArchivo && ineArchivo.trim() !== '' && docs.ine) {
                docs.ine.style.display = 'flex';
                docs.ine.href = `uploads/${ineArchivo}`;
                hayDocumentos = true;
            } else if (docsRequeridos.includes('ine') && docsFaltantes.ine) {
                docsFaltantes.ine.style.display = 'block';
                hayFaltantes = true;
            }

            const escrituraArchivo = btn.dataset.escritura || '';
            if (escrituraArchivo && escrituraArchivo.trim() !== '' && docs.escritura) {
                docs.escritura.style.display = 'flex';
                docs.escritura.href = `uploads/${escrituraArchivo}`;
                hayDocumentos = true;
            } else if (docsRequeridos.includes('escritura') && docsFaltantes.escritura) {
                docsFaltantes.escritura.style.display = 'block';
                hayFaltantes = true;
            }

            const predialArchivo = btn.dataset.predial || '';
            if (predialArchivo && predialArchivo.trim() !== '' && docs.predial) {
                docs.predial.style.display = 'flex';
                docs.predial.href = `uploads/${predialArchivo}`;
                hayDocumentos = true;
            } else if (docsRequeridos.includes('predial') && docsFaltantes.predial) {
                docsFaltantes.predial.style.display = 'block';
                hayFaltantes = true;
            }

            const formatoArchivo = btn.dataset.formatoConstancia || '';
            if (formatoArchivo && formatoArchivo.trim() !== '' && docs.formato_constancia) {
                docs.formato_constancia.style.display = 'flex';
                docs.formato_constancia.href = `uploads/${formatoArchivo}`;
                hayDocumentos = true;
            } else if (docsRequeridos.includes('formato_constancia') && docsFaltantes.formato) {
                docsFaltantes.formato.style.display = 'block';
                hayFaltantes = true;
            }

            if (hayFaltantes && seccionFaltantes) {
                seccionFaltantes.style.display = 'block';
                const comentario = btn.dataset.comentarioSinDoc || '';
                if (comentario.trim() !== '' && comentarioFaltantes && textoComentario) {
                    comentarioFaltantes.style.display = 'block';
                    textoComentario.textContent = comentario;
                }
            }

            if (!hayDocumentos && !hayFaltantes && sinDocumentos) {
                sinDocumentos.style.display = 'block';
            }

            const folio = btn.dataset.folio;
            document.getElementById('btn_imprimir_ficha').href = `ficha.php?folio=${folio}`;
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDash);
} else {
    initDash();
}
</script>

</body>
</html>