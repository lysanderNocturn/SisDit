<?php
require "seguridad.php";
require_once "php/funciones_seguridad.php";
?>


<?php


if(!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Verificador'){
    header("Location: acceso.php");
    exit();
}


require_once "php/db.php";

// ── Configuración del sistema ──
$cfg = [];
$resCfgV = $conn->query("SELECT clave, valor FROM configuracion_sistema");
while ($rowCfgV = $resCfgV->fetch_assoc()) $cfg[$rowCfgV['clave']] = $rowCfgV['valor'];

$sql = "SELECT t.*, tt.nombre as tipo_tramite_nombre 
        FROM tramites t
        LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
        WHERE 1=1";
$params = [];
$types = "";

// consulta base para obtener trámites, con posibilidad de agregar filtros dinámicos según los parámetros GET recibidos. Se construye la consulta SQL y se preparan los parámetros para evitar inyecciones SQL.

$cfg = [];
$resCfg = $conn->query("SELECT direccion, colonia FROM tramites");
while ($rowCfg = $resCfg->fetch_assoc()) {
    $cfg['direccion'][] = $rowCfg['direccion'];
    $cfg['colonia'][] = $rowCfg['colonia'];
}


/* ===== FILTRO FOLIO ===== */
if (!empty($_GET['folio']) && str_contains($_GET['folio'], '/')) {
    [$folio_numero, $folio_anio] = explode('/', $_GET['folio']);
    $sql .= " AND t.folio_numero = ? AND t.folio_anio = ?";
    $params[] = $folio_numero;
    $params[] = $folio_anio;
    $types .= "ii";
}

/* ===== FILTRO FECHA ===== */
if (!empty($_GET['fecha'])) {
    $sql .= " AND t.fecha_ingreso = ?";
    $params[] = $_GET['fecha'];
    $types .= "s";
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

/* ===== FILTRO CUENTA CATASTRAL ===== */
if (!empty($_GET['catastral'])) {
    $sql .= " AND t.cuenta_catastral LIKE ?";
    $params[] = '%'.$_GET['catastral'].'%';
    $types .= "s";
}

/* ===== FILTRO NOMBRE ===== */
if (!empty($_GET['nombre'])) {
    $sql .= " AND (t.propietario LIKE ? OR t.solicitante LIKE ?)";
    $params[] = '%'.$_GET['nombre'].'%';
    $params[] = '%'.$_GET['nombre'].'%';
    $types .= "ss";
}

/* ===== FILTRO SIN FOTOGRAFÍA ===== */
if (isset($_GET['sin_foto']) && $_GET['sin_foto'] !== '') {
    if ($_GET['sin_foto'] === '1') {
        $sql .= " AND (t.foto1_archivo IS NULL OR t.foto1_archivo = '')";
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

?>


<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sis Dit</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- BOOTSTRAP 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<!-- LEAFLET -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>

<!-- CSS PROPIO -->
<link rel="stylesheet" href="./css/style.css?v=1">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<!-- DataTables Bootstrap 5 CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<!-- DataTables Bootstrap 5 JS -->
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>


<script>
history.pushState(null, null, location.href);
window.onpopstate = function () {
    history.go(1);
};
</script>
<style>
    /* =====================================================
   DATATABLES - ALINEACIÓN PERFECTA
===================================================== */

/* Para pantallas mayores a 768px (tablet/desktop) */
@media (min-width: 769px) {
    .dataTables_wrapper .dataTables_length {
        float: left;
    }
    .dataTables_wrapper .dataTables_filter {
        float: right;
    }
}

/* Para móvil (menor o igual a 768px) */
@media (max-width: 768px) {
    /* Contenedor principal como flexbox */
    .dataTables_wrapper {
        display: flex !important;
        align-items: center !important;  /* <-- ESTO ALINEA VERTICALMENTE */
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 10px !important;
    }
    
    /* Selector de registros */
    .dataTables_wrapper .dataTables_length {
        float: none !important;
        width: auto !important;
        order: 1 !important;
    }
    
    /* Buscador */
    .dataTables_wrapper .dataTables_filter {
        float: none !important;
        width: auto !important;
        order: 2 !important;
    }
    
    /* Labels - FORZAR MISMA ALTURA */
    .dataTables_wrapper .dataTables_length label,
    .dataTables_wrapper .dataTables_filter label {
        display: flex !important;
        align-items: center !important;  /* <-- ALINEACIÓN VERTICAL */
        margin: 0 !important;
        line-height: 1 !important;  /* <-- MISMA ALTURA DE LÍNEA */
        height: 36px !important;     /* <-- MISMA ALTURA FIJA */
    }
    
    /* Texto de los labels - MISMA PROPIEDADES */
    .dataTables_wrapper .dataTables_length label span,
    .dataTables_wrapper .dataTables_length label .fw-semibold,
    .dataTables_wrapper .dataTables_filter label {
        font-size: 14px !important;
        line-height: 36px !important;  /* <-- MISMA ALTURA DE LÍNEA */
    }
    
    /* Selector pequeño */
    .dataTables_wrapper .dataTables_length select {
        width: 65px !important;
        height: 32px !important;
        margin: 0 5px !important;
        padding: 4px !important;
        border: 1px solid #ced4da !important;
        border-radius: 4px !important;
    }
    
    /* Input de búsqueda */
    .dataTables_wrapper .dataTables_filter input {
        width: 150px !important;
        height: 32px !important;
        margin-left: 5px !important;
        padding: 4px 8px !important;
        border: 1px solid #ced4da !important;
        border-radius: 4px !important;
    }
}

/* Para móvil muy pequeño */
@media (max-width: 480px) {
    .dataTables_wrapper {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        width: 100% !important;
    }
    
    .dataTables_wrapper .dataTables_length label,
    .dataTables_wrapper .dataTables_filter label {
        width: 100% !important;
        justify-content: space-between !important;
    }
    
    .dataTables_wrapper .dataTables_filter input {
        width: calc(100% - 60px) !important;
    }
}

/* Estilos para mejorar alineación del modal de constancia */
.modal .form-label { font-size: .9rem; }
.modal .form-control { height: calc(1.9em + .75rem + 2px); }
.modal .card-body .row > [class*="col-"] { display:flex; flex-direction:column; }

/* Ajuste específico: asegurar que los tres inputs principales estén alineados */
@media (min-width: 768px) {
  #modalConstancia .card-body .row.g-3 > .col-md-4 { display:flex; flex-direction:column; }
}
</style>
</head>

<body>
<!-- NAVBAR MÓVIL -->
<nav class="navbar navbar-dark bg-dark d-lg-none">
    <div class="container-fluid">
        <span class="navbar-brand">Sistema Georreferenciado</span>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuMovil">
            <span class="navbar-toggler-icon"></span>
        </button>
    </div>

    <div class="collapse navbar-collapse" id="menuMovil">
        <ul class="navbar-nav p-3">
            <li class="nav-item"><a class="nav-link" href="#inicio"><i class="bi bi-house me-2"></i> Inicio</a></li>
            <li class="nav-item"><a class="nav-link" href="#seguimiento"><i class="bi bi-search me-2"></i> Seguimiento</a></li>
            <li class="nav-item"><a class="nav-link" href="#config-constancia"><i class="bi bi-file-earmark-text me-2"></i> Formato Constancia</a></li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión</a></li>
        </ul>
    </div>
</nav>

<!-- SIDEBAR -->
<div class="sidebar position-fixed d-none d-lg-flex flex-column p-3">
    <h5 class="text-white text-center mb-4"> <i class="bi bi-clipboard-check"></i> Verificador</h5>
    <a class="nav-link text-white" href="#inicio"><i class="bi bi-house me-2"></i> Inicio</a>
    <a class="nav-link text-white" href="#seguimiento"><i class="bi bi-search me-2"></i> Seguimiento</a>
    <a class="nav-link text-white" href="#config-constancia"><i class="bi bi-file-earmark-text me-2"></i> Formato Constancia</a>
    <a class="nav-link text-danger mt-auto" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión</a>
</div>

<!-- CONTENIDO -->
<div class="content">

<!-- ENCABEZADO -->
<section class="hero" id="inicio">
    <h1><i class="bi bi-clipboard-check"></i> Panel de Verificador</h1>
    <p>Bienvenido <?php echo $_SESSION['usuario'] ?? ''; ?>. Revisa, aprueba y gestiona los trámites georreferenciados.</p>
</section>

<!-- ESTADÍSTICAS RÁPIDAS -->
<div class="row g-3 mb-4">
    <?php
    // Obtener estadísticas
    $total_tramites = $conn->query("SELECT COUNT(*) as total FROM tramites")->fetch_assoc()['total'];
    $en_revision = $conn->query("SELECT COUNT(*) as total FROM tramites WHERE estatus = 'En revisión'")->fetch_assoc()['total'];
    $aprobados_hoy = $conn->query("SELECT COUNT(*) as total FROM tramites WHERE estatus = 'Aprobado' AND DATE(created_at) = CURDATE()")->fetch_assoc()['total'];
    $pendientes = $conn->query("SELECT COUNT(*) as total FROM tramites WHERE estatus = 'En revisión' AND foto1_archivo IS NULL")->fetch_assoc()['total'];
    ?>
    
    <div class="col-md-3">
        <div class="card shadow-sm border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Trámites</h6>
                        <h3 class="mb-0"><?= $total_tramites ?></h3>
                    </div>
                    <i class="bi bi-folder-check text-primary fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card shadow-sm border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">En Revisión</h6>
                        <h3 class="mb-0"><?= $en_revision ?></h3>
                    </div>
                    <i class="bi bi-hourglass-split text-warning fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card shadow-sm border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Aprobados Hoy</h6>
                        <h3 class="mb-0"><?= $aprobados_hoy ?></h3>
                    </div>
                    <i class="bi bi-check-circle text-success fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card shadow-sm border-start border-danger border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Sin Fotografías</h6>
                        <h3 class="mb-0"><?= $pendientes ?></h3>
                    </div>
                    <i class="bi bi-camera-fill text-danger fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SEGUIMIENTO -->
<section id="seguimiento" class="tramite-box mb-4">
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="text-primary m-0"><i class="bi bi-search"></i> Seguimiento de Trámites</h4>
    
    <!-- Filtros rápidos -->
    <div class="btn-group" role="group">
        <a href="?estatus=En revisión" class="btn btn-sm btn-outline-warning">
            <i class="bi bi-hourglass-split"></i> En Revisión
        </a>
        <a href="?estatus=Aprobado" class="btn btn-sm btn-outline-success">
            <i class="bi bi-check-circle"></i> Aprobados
        </a>
        <a href="?estatus=Rechazado" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-x-circle"></i> Rechazados
        </a>
        <a href="DashVer.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-clockwise"></i> Todos
        </a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <label class="form-label">Folio</label>
        <div class="input-group">
            <form method="GET" class="input-group mb-3">
                <input type="text" name="folio" class="form-control" placeholder="001/2026" required>
                <button class="btn btn-primary" type="submit">Buscar</button>
            </form>
        </div>
    </div>
</div>




<form method="GET" class="row g-3 mb-4">

    <!-- FECHA -->
    <div class="col-md-3">
        <label class="form-label">Fecha de ingreso</label>
        <input type="date" name="fecha" class="form-control"
               value="<?= $_GET['fecha'] ?? '' ?>">
    </div>

    <!-- TRÁMITE -->
    <div class="col-md-3">
        <label class="form-label">Trámite</label>
        <select name="tramite" class="form-select">
            <option value="">Todos</option>
            <?php
            $filtro_tipos = $conn->query("SELECT nombre FROM tipos_tramite WHERE activo = 1 ORDER BY nombre");
            while ($ft = $filtro_tipos->fetch_assoc()):
            ?>
            <option value="<?= htmlspecialchars($ft['nombre']) ?>" <?= ($_GET['tramite'] ?? '') === $ft['nombre'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($ft['nombre']) ?>
            </option>
            <?php endwhile; ?>
        </select>
    </div>

    <!-- ESTATUS -->
    <div class="col-md-3">
        <label class="form-label">Estatus</label>
        <select name="estatus" class="form-select">
            <option value="">Todos</option>
            <option value="En revisión" <?= ($_GET['estatus'] ?? '') === 'En revisión' ? 'selected' : '' ?>>
                En revisión
            </option>
            <option value="Aprobado" <?= ($_GET['estatus'] ?? '') === 'Aprobado' ? 'selected' : '' ?>>
                Aprobado
            </option>
            <option value="Rechazado" <?= ($_GET['estatus'] ?? '') === 'Rechazado' ? 'selected' : '' ?>>
                Rechazado
            </option>
        </select>
    </div>

    <!-- FILTRO FOTOGRAFÍA -->
    <div class="col-md-3">
        <label class="form-label"><i class="bi bi-camera me-1"></i>Fotografías</label>
        <select name="sin_foto" class="form-select">
            <option value="">Todos</option>
            <option value="1" <?= (($_GET['sin_foto'] ?? '') === '1') ? 'selected' : '' ?>>Sin fotografía</option>
            <option value="0" <?= (($_GET['sin_foto'] ?? '') === '0') ? 'selected' : '' ?>>Con fotografía</option>
        </select>
    </div>

    <!-- BOTONES -->
    <div class="col-md-3 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-funnel"></i> Filtrar
        </button>
        <a href="DashVer.php" class="btn btn-outline-secondary">
            Limpiar
        </a>
    </div>

</form>


<div  class="table-responsive">
<table id="tablaTramites" class="table table-bordered">
<thead class="table-secondary text-center">
<tr>
<th>Folio</th>
<th>Propietario</th>
<th>Tramite</th>
<th>Fecha Ingreso</th>
<th>Estatus</th>
<th>Acciones</th>
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
                    'En revisi������������n' => 'bg-warning text-dark',
                    'Aprobado' => 'bg-success',
                    'Rechazado' => 'bg-danger',
                    default => 'bg-secondary'
                };
            ?>
            <span class="badge <?= $badge ?>">
                <?= $t['estatus'] ?>
            </span>
        </td>
        <td class="text-center">
            <div class="btn-group" role="group">
                <button 
                    class="btn btn-sm btn-outline-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#detalleTramite"
                    data-folio="<?= $t['folio_numero'].'/'.$t['folio_anio'] ?>"
                    data-estatus="<?= htmlspecialchars($t['estatus']) ?>"
                    data-observaciones="<?= htmlspecialchars($t['observaciones'] ?? '') ?>"
                    data-propietario="<?= htmlspecialchars($t['propietario']) ?>"
                    data-direccion="<?= htmlspecialchars($t['direccion']) ?>"
                    data-colonia="<?= htmlspecialchars($t['colonia'] ?? '') ?>"
                    data-localidad="<?= htmlspecialchars($t['localidad']) ?>"
                    data-tramites="<?= htmlspecialchars($t['tipo_tramite_nombre'] ?? 'Sin tipo') ?>"
                    data-fecha="<?= date('d/m/Y', strtotime($t['fecha_ingreso'])) ?>"
                    data-tramite-id="<?= $t['tipo_tramite_id'] ?>"
                    data-telefono="<?= htmlspecialchars($t['telefono'] ?? '') ?>"
                    data-correo="<?= htmlspecialchars($t['correo'] ?? '') ?>"
                    data-ine="<?= $t['ine_archivo'] ?? '' ?>"
                    data-titulo="<?= $t['escrituras_archivo'] ?? $t['titulo_archivo'] ?? '' ?>"
                    data-predial="<?= $t['predial_archivo'] ?? '' ?>"
                    data-formato-constancia="<?= $t['formato_constancia'] ?? '' ?>"
                    data-foto1="<?= $t['foto1_archivo'] ?? '' ?>"
                    data-foto2="<?= $t['foto2_archivo'] ?? '' ?>"
                    data-comentario-sin-doc="<?= htmlspecialchars($t['comentario_sin_doc'] ?? '') ?>"
                    data-numero-asignado="<?= htmlspecialchars($t['numero_asignado'] ?? '') ?>"
                    data-tipo-asignacion="<?= htmlspecialchars($t['tipo_asignacion'] ?? 'Asignacion') ?>"
                    data-referencia-anterior="<?= htmlspecialchars($t['referencia_anterior'] ?? '') ?>"
                    data-entre-calle1="<?= htmlspecialchars($t['entre_calle1'] ?? '') ?>"
                    data-entre-calle2="<?= htmlspecialchars($t['entre_calle2'] ?? '') ?>"
                    data-manzana="<?= htmlspecialchars($t['manzana'] ?? '') ?>"
                    data-lote="<?= htmlspecialchars($t['lote'] ?? '') ?>"
                    data-fecha-constancia="<?= $t['fecha_constancia'] ?? date('Y-m-d') ?>"
                    data-cuenta-catastral="<?= htmlspecialchars($t['cuenta_catastral'] ?? '') ?>"
                    data-tipo-tramite-id="<?= $t['tipo_tramite_id'] ?? '' ?>"
                >
                    Ver detalles
                </button>
                <?php if (in_array($t['estatus'], ['Aprobado por Verificador', 'Aprobado']) && $t['tipo_tramite_id'] == 1): ?>
                <button 
                    class="btn btn-sm btn-success btn-generar-constancia"

                    data-folio="<?= $t['folio_numero'].'/'.$t['folio_anio'] ?>"
                    data-propietario="<?= htmlspecialchars($t['propietario']) ?>"
                    data-direccion="<?= htmlspecialchars($t['direccion']) ?>"
                    data-colonia="<?= htmlspecialchars($t['colonia'] ?? '') ?>"
                    data-localidad="<?= htmlspecialchars($t['localidad']) ?>"
                    data-numero-asignado="<?= htmlspecialchars($t['numero_asignado'] ?? '') ?>"
                    data-tipo-asignacion="<?= htmlspecialchars($t['tipo_asignacion'] ?? 'Asignacion') ?>"
                    data-referencia-anterior="<?= htmlspecialchars($t['referencia_anterior'] ?? '') ?>"
                    data-entre-calle1="<?= htmlspecialchars($t['entre_calle1'] ?? '') ?>"
                    data-entre-calle2="<?= htmlspecialchars($t['entre_calle2'] ?? '') ?>"
                    data-manzana="<?= htmlspecialchars($t['manzana'] ?? '') ?>"
                    data-lote="<?= htmlspecialchars($t['lote'] ?? '') ?>"
                    data-fecha-constancia="<?= $t['fecha_constancia'] ?? date('Y-m-d') ?>"
                    data-cuenta-catastral="<?= htmlspecialchars($t['cuenta_catastral'] ?? '') ?>"
                    data-superficie="<?= htmlspecialchars($t['superficie'] ?? '') ?>"
                    data-croquis="<?= htmlspecialchars(isset($t['croquis_archivo']) && !empty($t['croquis_archivo']) ? (strpos($t['croquis_archivo'], '.') === 0 ? $t['croquis_archivo'] : 'uploads/' . $t['croquis_archivo']) : '') ?>"
                    title="Generar Constancia de Numero Oficial"
                >
                    <i class="bi bi-file-earmark-check"></i> Constancia
                </button>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr>
        <td colspan="6" class="text-center text-muted">
            No se encontraron trámites
        </td>
    </tr>
<?php endif; ?>
</tbody>
</table>
</div>
</section>

<!-- ================================================ -->
<!-- FORMATO DE CONSTANCIA DE NÚMERO OFICIAL         -->
<!-- ================================================ -->
<section id="config-constancia" class="tramite-box mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="m-0" style="color:#7b0f2b;">
      <i class="bi bi-file-earmark-text me-2"></i>Formato de Constancia de Número Oficial
    </h4>
  </div>
  <div class="alert alert-info py-2 mb-4" style="font-size:.85rem;">
    <i class="bi bi-info-circle-fill me-2"></i>
    Aquí puedes editar el <strong>nombre del director</strong> que aparece en la firma y los <strong>reglamentos</strong> que se imprimen al reverso del croquis en la constancia.
  </div>

  <?php $csrf_ccv = generarCSRF(); ?>
  <form id="formConfigConstanciaVer">
    <input type="hidden" name="csrf_token" value="<?= $csrf_ccv ?>">

    <!-- Nombre del Director -->
    <div class="mb-4">
      <label class="fw-bold mb-1" style="color:#7b0f2b;">
        <i class="bi bi-person-badge me-1"></i> Nombre del Director
      </label>
      <input type="text" class="form-control" name="config[director_nombre]"
             value="<?= htmlspecialchars($cfg['director_nombre'] ?? 'DIRECTOR DE PLANEACIÓN Y DESARROLLO URBANO') ?>"
             placeholder="Ej: LIC. URB. JUAN PÉREZ GÓMEZ"
             style="text-transform:uppercase;"
             oninput="this.value=this.value.toUpperCase()">
      <small class="text-muted">Aparece en la sección de firma al pie de la constancia.</small>
    </div>

    <!-- Reglamentos -->
    <div class="mb-2">
      <label class="fw-bold mb-2" style="color:#7b0f2b;">
        <i class="bi bi-list-ol me-1"></i> Reglamentos (al reverso del croquis)
      </label>
      <p class="text-muted small mb-3">Estos textos aparecen enumerados con números romanos (I–IV) debajo del croquis de ubicación.</p>
    </div>

    <?php
    $regsV = [
      1 => $cfg['constancia_reglamento_1'] ?? 'En inmuebles construidos deberán colocarse en el exterior, al frente de la construcción junto al acceso principal;',
      2 => $cfg['constancia_reglamento_2'] ?? 'Los números oficiales en ningún caso deberán ser pintados sobre muros, bloques, columnas y/o en elementos de fácil destrucción;',
      3 => $cfg['constancia_reglamento_3'] ?? 'Deberán además ser de tipo de fuente legible y permitir una fácil lectura a un mínimo de veinte metros;',
      4 => $cfg['constancia_reglamento_4'] ?? 'Las placas de numeración deberán colocarse en una altura mínima de dos metros con cincuenta centímetros a partir del nivel de la banqueta.',
    ];
    $romanosV = ['I','II','III','IV'];
    foreach ($regsV as $i => $texto): ?>
    <div class="mb-3">
      <label class="form-label fw-semibold text-secondary small">
        Inciso <?= $romanosV[$i-1] ?>
      </label>
      <textarea class="form-control" name="config[constancia_reglamento_<?= $i ?>]"
                rows="2" style="font-size:.9rem;"><?= htmlspecialchars($texto) ?></textarea>
    </div>
    <?php endforeach; ?>

    <div class="d-flex align-items-center gap-3 mt-3">
      <button type="button" class="btn btn-success px-4" onclick="guardarConfigConstanciaVer()">
        <i class="bi bi-floppy me-1"></i> Guardar cambios
      </button>
      <span id="msg-config-constancia-ver" class="fw-semibold" style="font-size:.9rem;"></span>
    </div>
  </form>
</section>

</div>

<div class="modal fade" id="detalleTramite" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content shadow">

      <!-- HEADER -->
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">
          <i class="bi bi-folder-check me-2"></i> Detalle del Trámite
        </h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <!-- BODY -->
      <div class="modal-body">
<form id="formActualizarTramite" enctype="multipart/form-data">
  <?php $csrf = generarCSRF(); ?>
  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
  <input type="hidden" name="folio" id="m_folio_hidden">
        <input type="hidden" name="tipo_tramite_id" id="m_tipo_tramite_id">
        <input type="hidden" name="numero_asignado" id="m_numero_asignado" value="">

        <!-- INFO GENERAL -->
        <div class="row mb-3">
          <div class="col-md-6">
            <p><strong>Folio:</strong> <span id="m_folio"></span></p>
            <p><strong>Propietario:</strong> <span id="m_propietario"></span></p>
            <p><strong>Dirección:</strong> <span id="m_direccion"></span></p>
            <p><strong>Colonia:</strong> <span id="m_colonia"></span></p>
            <p><strong>Localidad:</strong> <span id="m_localidad"></span></p>
            <p><strong>Teléfono:</strong> <span id="m_telefono"></span></p>
            <p><strong>Correo:</strong> <span id="m_correo"></span></p>
          </div>
          <div class="col-md-6">
    <p><strong>Trámite:</strong> <span id="m_tramites"></span></p>
    <p><strong>Fecha ingreso:</strong> <span id="m_fecha"></span></p>
    <div class="mb-2">
        <label class="fw-bold small">Nombre del Verificador <span class="text-danger">*</span></label>
        <input type="text" class="form-control form-control-sm" name="verificador_nombre" id="m_verificador_nombre"
               value="<?= htmlspecialchars(mb_strtoupper($_SESSION['usuario'] ?? '', 'UTF-8')) ?>"
               readonly style="background:#f8f9fa;font-weight:600;text-transform:uppercase;">
        <small class="text-muted"><i class="bi bi-person-check me-1"></i>Verificador en sesión activa</small>
    </div>
    <div class="mb-2">
        <label class="fw-bold small">Cambiar Estatus:</label>
        <select class="form-select form-select-sm" name="estatus" id="m_estatus">
            <option value="En revisión">🟡 En revisión</option>
            <option value="En corrección">🔵 En corrección</option>
            <option value="Aprobado por Verificador">✅ Aprobado por Verificador</option>
            <option value="Rechazado">❌ Rechazado</option>
        </select>
        <small class="text-muted d-block mt-1" id="estatus-hint"></small>
    </div>
    
</div>
        </div>
        <hr>

        <!-- OBSERVACIONES / CORRECCIÓN -->
        <div class="mb-3" id="bloque-observaciones">
          <h6 class="text-primary">Observaciones / Motivo</h6>
          <textarea class="form-control" name="observaciones" id="m_observaciones" rows="3"
                    placeholder="Escribe observaciones generales del trámite..."></textarea>
        </div>

        <!-- PANEL EN CORRECCIÓN (se muestra solo cuando se elige ese estatus) -->
        <div id="panel-correccion" style="display:none;" class="mb-3">
          <div class="card border-warning shadow-sm">
            <div class="card-header bg-warning text-dark d-flex align-items-center gap-2">
              <i class="bi bi-pencil-square fs-5"></i>
              <strong>Indicaciones de Corrección para el Ciudadano</strong>
            </div>
            <div class="card-body">

              <!-- Documentos faltantes / a corregir -->
              <p class="fw-semibold mb-2"><i class="bi bi-file-earmark-x text-danger me-1"></i>Documentos que debe traer o corregir:</p>
              <div class="row g-2 mb-3" id="checkDocs">
                <div class="col-6 col-md-4">
                  <div class="form-check">
                    <input class="form-check-input check-correccion" type="checkbox" value="INE o identificación oficial vigente" id="chk_ine">
                    <label class="form-check-label small" for="chk_ine">INE / Identificación oficial</label>
                  </div>
                </div>
                <div class="col-6 col-md-4">
                  <div class="form-check">
                    <input class="form-check-input check-correccion" type="checkbox" value="Escritura pública o título de propiedad" id="chk_escritura">
                    <label class="form-check-label small" for="chk_escritura">Escritura / Título</label>
                  </div>
                </div>
                <div class="col-6 col-md-4">
                  <div class="form-check">
                    <input class="form-check-input check-correccion" type="checkbox" value="Boleta predial actualizada" id="chk_predial">
                    <label class="form-check-label small" for="chk_predial">Boleta predial</label>
                  </div>
                </div>
                <div class="col-6 col-md-4">
                  <div class="form-check">
                    <input class="form-check-input check-correccion" type="checkbox" value="Formato de constancia debidamente llenado" id="chk_formato">
                    <label class="form-check-label small" for="chk_formato">Formato de constancia</label>
                  </div>
                </div>
                <div class="col-6 col-md-4">
                  <div class="form-check">
                    <input class="form-check-input check-correccion" type="checkbox" value="Carta poder (si tramita en nombre de otra persona)" id="chk_carta">
                    <label class="form-check-label small" for="chk_carta">Carta poder</label>
                  </div>
                </div>
                <div class="col-6 col-md-4">
                  <div class="form-check">
                    <input class="form-check-input check-correccion" type="checkbox" value="Fotografías del inmueble (frente y numeración visible)" id="chk_fotos">
                    <label class="form-check-label small" for="chk_fotos">Fotos del inmueble</label>
                  </div>
                </div>
              </div>

              <!-- Texto libre adicional -->
              <label class="fw-semibold small mb-1"><i class="bi bi-chat-left-text me-1"></i>Indicación adicional (opcional):</label>
              <textarea id="correccion_extra" class="form-control form-control-sm" rows="2"
                        placeholder="Ej: Los documentos deben ser legibles y en formato PDF o imagen clara..."></textarea>

              <!-- Preview del mensaje -->
              <div class="mt-3 p-2 rounded bg-light border" id="preview-msg-correccion" style="display:none;">
                <p class="mb-1 fw-bold text-secondary small"><i class="bi bi-eye me-1"></i>Vista previa del mensaje:</p>
                <p id="texto-preview-correccion" class="mb-0 small" style="white-space:pre-line;"></p>
              </div>
            </div>
          </div>
        </div>



        <!-- FLUJO DE TRABAJO -->
        <div class="alert alert-info py-2 mb-3" id="bloque-flujo-info" style="font-size:.84rem;">
          <i class="bi bi-info-circle-fill me-1"></i>
          <strong>Flujo para Constancia de Número Oficial:</strong>
          <ol class="mb-0 mt-1 ps-3">
            <li>Sube las <strong>fotografías del inmueble</strong> y guarda → imprime la <strong>Ficha de Fotografías</strong>.</li>
            <li>Llena los <strong>datos de la constancia</strong> (modal "Constancia") → imprime la <strong>Constancia de Número Oficial</strong>.</li>
            <li>Entrega ambos documentos a la secretaria.</li>
          </ol>
        </div>

        <!-- FOTOGRAFIAS -->
        <div class="mb-3">
          <h6 class="text-primary"><i class="bi bi-camera me-1"></i> Paso 1 — Fotografías del Inmueble</h6>
          <p class="text-muted small mb-2">Sube las fotos, guarda y luego imprime la <strong>Ficha de Fotografías</strong>.</p>
          <div class="row g-3">
             <div class="col-md-6">
               <label class="form-label fw-bold">Fotografía 1</label>
               <div id="preview_foto1_container" class="mb-2" style="display:none;">
                 <img id="preview_foto1" src="" alt="Foto 1" class="img-thumbnail" style="max-height:150px; cursor:pointer;" onclick="window.open(this.src, '_blank')">
               </div>
               <input type="file" class="form-control" name="foto1" id="input_foto1" accept="image/jpeg,image/png,image/jpg">
               <small class="text-muted">JPG o PNG, máximo 5MB. También puedes pegar con Ctrl+V</small>
             </div>
             <div class="col-md-6">
               <label class="form-label fw-bold">Fotografía 2</label>
               <div id="preview_foto2_container" class="mb-2" style="display:none;">
                 <img id="preview_foto2" src="" alt="Foto 2" class="img-thumbnail" style="max-height:150px; cursor:pointer;" onclick="window.open(this.src, '_blank')">
               </div>
               <input type="file" class="form-control" name="foto2" id="input_foto2" accept="image/jpeg,image/png,image/jpg">
               <small class="text-muted">JPG o PNG, máximo 5MB. También puedes pegar con Ctrl+V</small>
             </div>
          </div>
          <!-- Alerta: fotos necesarias antes de imprimir ficha -->
          <div id="alerta-sin-fotos" class="alert alert-warning py-2 mt-2 mb-0" style="font-size:.83rem;display:none;">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            Aún no hay fotografías guardadas. Sube al menos una foto y guarda antes de imprimir la ficha.
          </div>
          <div id="alerta-fotos-ok" class="alert alert-success py-2 mt-2 mb-0" style="font-size:.83rem;display:none;">
            <i class="bi bi-check-circle-fill me-1"></i>
            Fotografías guardadas. Ya puedes imprimir la <strong>Ficha de Fotografías</strong>.
          </div>
        </div>

        <hr>

        <!-- PASO 2: CONSTANCIA -->
        <div class="mb-1" id="bloque-paso2">
          <h6 class="text-primary"><i class="bi bi-file-earmark-check me-1"></i> Paso 2 — Constancia de Número Oficial</h6>
          <div id="alerta-paso2-pendiente" class="alert alert-warning py-2 mb-2" style="font-size:.83rem;display:none;">
            <i class="bi bi-lock-fill me-1"></i>
            Primero guarda las fotografías (Paso 1) para habilitar la constancia.
          </div>
          <div id="alerta-constancia-ok" class="alert alert-success py-2 mb-2" style="font-size:.83rem;display:none;">
            <i class="bi bi-check-circle-fill me-1"></i>
            Constancia generada. Ya puedes imprimirla.
          </div>
          <div id="bloque-btn-constancia-modal" class="mb-1" style="display:none;">
            <button type="button" class="btn btn-outline-success btn-sm" id="btn_abrir_constancia_desde_detalle">
              <i class="bi bi-file-earmark-text me-1"></i> Llenar / imprimir Constancia
            </button>
          </div>
        </div>

        <hr>

        <!-- DOCUMENTOS CARGADOS -->
        <div>
          <h6 class="text-primary">Documentos Cargados</h6>

          <div class="list-group mb-3">
            <a id="doc_ine" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" target="_blank" style="display:none;">
              <span><i class="bi bi-file-earmark-person me-2"></i> INE</span>
              <span class="badge bg-primary">Ver</span>
            </a>

            <a id="doc_titulo" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" target="_blank" style="display:none;">
              <span><i class="bi bi-file-earmark-text me-2"></i> Titulo / Escritura</span>
              <span class="badge bg-primary">Ver</span>
            </a>

            <a id="doc_predial" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" target="_blank" style="display:none;">
              <span><i class="bi bi-file-earmark-text me-2"></i> Predial</span>
              <span class="badge bg-primary">Ver</span>
            </a>
            
            <a id="doc_formato_constancia" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" target="_blank" style="display:none;">
              <span><i class="bi bi-file-earmark-ruled me-2"></i> Formato de Constancia</span>
              <span class="badge bg-primary">Ver</span>
            </a>
          </div>
          
          <div id="modal-sin-documentos" class="text-muted mb-3" style="display:none;">
            <i class="bi bi-info-circle me-1"></i> No hay documentos cargados para este tramite.
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
              <div id="doc_faltante_titulo" class="list-group-item list-group-item-warning" style="display:none;">
                <div class="d-flex align-items-center">
                  <i class="bi bi-x-circle text-danger me-2"></i>
                  <span>Titulo / Escritura Publica</span>
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

      <!-- FOOTER -->
      <div class="modal-footer flex-wrap gap-2">
        <!-- Ver todos los documentos -->
        <a id="btn_ver_documentos" href="#"  class="btn btn-outline-info" title="Ver e imprimir todos los documentos del trámite">
            <i class="bi bi-files me-1"></i> Ver Documentos
        </a>
        <!-- Paso 1: Ficha de fotografías (solo si ya hay fotos guardadas) -->
        <a id="btn_imprimir_fotos" href="#" target="_blank" class="btn btn-outline-secondary" style="display:none;" title="Imprimir ficha con fotografías del inmueble">
            <i class="bi bi-camera me-1"></i> Imprimir Ficha de Fotografías
        </a>
        <!-- Ficha de datos (siempre disponible) -->
        <a id="btn_imprimir_ficha" href="#" target="_self" class="btn btn-outline-primary" title="Imprimir ficha de datos del trámite">
            <i class="bi bi-printer me-1"></i> Imprimir Ficha
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="submit" class="btn btn-success">
            <i class="bi bi-floppy me-1"></i> Guardar y continuar
        </button>
      </div>


    </div>
  </div>
  </form>

</div>


<!-- MODAL: CONSTANCIA DE NUMERO OFICIAL -->
<div class="modal fade" id="modalConstancia" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg">
    <div class="modal-content shadow" style="max-height: 80vh; overflow-y: auto;">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">
          <i class="bi bi-file-earmark-check me-2"></i> Constancia de Número Oficial
        </h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="formConstancia">
          <?php $csrf_constancia = generarCSRF(); ?>
          <input type="hidden" name="csrf_token" value="<?= $csrf_constancia ?>">
          <input type="hidden" name="folio" id="c_folio_hidden">
          <input type="hidden" name="solo_constancia" value="1">
          
          <!-- Info del tramite -->
          <div class="alert alert-light border mb-3">
            <div class="row">
              <div class="col-md-6">
                <p class="mb-1"><strong>Folio:</strong> <span id="c_folio"></span></p>
                <p class="mb-1"><strong>Propietario:</strong> <span id="c_propietario"></span></p>
              </div>
              <div class="col-md-6">
                <p class="mb-1"><strong>Dirección:</strong> <span id="c_direccion"></span></p>
                <p class="mb-0"><strong>Localidad:</strong> <span id="c_localidad"></span></p>
              </div>
            </div>
          </div>
          
          <!-- Buscar número oficial anterior -->
          <div class="card border-info mb-3">
            <div class="card-header bg-info text-white py-2">
              <i class="bi bi-search me-2"></i>Cargar datos de número oficial anterior <small class="fw-normal">(opcional)</small>
            </div>
            <div class="card-body py-3">
              <div class="row g-2 align-items-end">
                <div class="col-md-5">
                  <label class="form-label small fw-semibold mb-1">Folio de salida anterior</label>
                  <input type="text" class="form-control form-control-sm" id="c_buscar_folio" placeholder="Ej: 001/2026">
                </div>
                <div class="col-md-5">
                  <label class="form-label small fw-semibold mb-1">O nombre del propietario</label>
                  <input type="text" class="form-control form-control-sm" id="c_buscar_propietario" placeholder="Ej: JUAN PÉREZ">
                </div>
                <div class="col-md-2">
                  <button type="button" class="btn btn-info btn-sm w-100 text-white" onclick="cargarDatosAnterioresVer()">
                    <i class="bi bi-search me-1"></i>Buscar
                  </button>
                </div>
              </div>
              <div id="c_msg_busqueda" class="small mt-2" style="display:none;"></div>
            </div>
          </div>

          <div class="card border-success">
            <div class="card-header bg-success text-white">
              <i class="bi bi-file-earmark-text me-2"></i>Datos de la Constancia
            </div>
            <div class="card-body">
              <div class="row g-3">
                <!-- Tipo de asignacion -->
                <div class="col-md-4">
                    <label class="form-label fw-bold">Tipo <span class="text-danger">*</span></label>
                    <select class="form-select" name="tipo_asignacion" id="c_tipo_asignacion" required>
                        <option value="ASIGNACION">ASIGNACIÓN</option>
                        <option value="RECTIFICACION">RECTIFICACIÓN</option>
                        <option value="REPOSICION">REPOSICIÓN</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label small mt-1">Dirección<span class="text-danger">*</span></label>
                    <input type="text" class="form-control input-mayusculas" name="direccion_constancia" id="c_direccion_constancia"
                           value="" pattern="[A-Za-z0-9\s\#\.\-]+"
                           title="Solo letras, numeros, espacios, #, puntos y guiones" required>
                    
                    <label class="form-label small mt-1">Colonia <span class="text-danger">*</span></label>
                    <input type="text" class="form-control input-mayusculas" name="colonia_constancia" id="c_colonia_constancia"
                           value="" pattern="[A-Za-z0-9\s\#\.\-]+"
                           title="Solo letras, numeros, espacios, #, puntos y guiones" required>
                              <label class="form-label fw-bold">Número Asignado <span class="text-danger">*</span></label>
                  <input type="text" class="form-control input-mayusculas" name="numero_asignado" id="c_numero_asignado" 
                         placeholder="Ej: 103" pattern="[A-Za-z0-9\s\-]+" 
                         title="Solo letras, numeros, espacios y guiones" required>
                </div>
                
                <!-- Numero asignado -->
                <div class="col-md-4">
               
                </div>
                
                <!-- Referencia anterior -->
                <div class="col-md-4">
                  <label class="form-label fw-bold">Referencia Anterior</label>
                  <input type="text" class="form-control input-mayusculas" name="referencia_anterior" id="c_referencia_anterior" 
                         placeholder="Opcional" pattern="[A-Za-z0-9\s\-]*"
                         title="Solo letras, numeros, espacios y guiones">
                  <small class="text-muted">Solo si aplica</small>
                </div>
                
                <!-- Entre calles -->
                <div class="col-md-6">
                  <label class="form-label fw-bold">Entre Calles <span class="text-danger">*</span></label>
                  <input type="text" class="form-control input-mayusculas" name="entre_calle1" id="c_entre_calle1" 
                         placeholder="Ej: NINOS HEROES Y JUAREZ" pattern="[A-Za-z0-9\s\-]+" title="Solo letras, numeros, espacios y guiones" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold invisible">Entre Calles (continuación) <span class="text-danger invisible">*</span></label>
                  <input type="text" class="form-control input-mayusculas" name="entre_calle2" id="c_entre_calle2" 
                         placeholder="Ej: INDEPENDENCIA Y HIDALGO" pattern="[A-Za-z0-9\s\-]*" title="Solo letras, numeros, espacios y guiones">
                </div>
                </div>
              </div>

              <div class="row g-3">
                <!-- Cuenta catastral (solo numeros) -->
                <div class="col-md-6">
                  <label class="form-label fw-bold">Cuenta Catastral <span class="text-danger">*</span></label>
                  <input type="text" class="form-control input-solo-numeros" name="cuenta_catastral_constancia" id="c_cuenta_catastral" 
                         placeholder="Ej: 70104010022000" pattern="[0-9]+" 
                         title="Solo numeros" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Superficie (m²) <span class="text-danger">*</span></label>
                  <input type="text" class="form-control input-solo-numeros" name="superficie_constancia" id="c_superficie_constancia" 
                         placeholder="Ej: 250" pattern="[0-9]+" title="Solo numeros">
                </div>
                
                <!-- Manzana -->
                <div class="col-md-3">
                  <label class="form-label fw-bold">Manzana</label>
                  <input type="text" class="form-control input-mayusculas" name="manzana" id="c_manzana" 
                         placeholder="Opcional" pattern="[A-Za-z0-9\s\-]*"
                         title="Solo letras, numeros, espacios y guiones">
                </div>
                
                
                <!-- Lote -->
                <div class="col-md-3">
                  <label class="form-label fw-bold">Lote</label>
                  <input type="text" class="form-control input-mayusculas" name="lote" id="c_lote"
                         placeholder="Opcional" pattern="[A-Za-z0-9\s\-]*"
                         title="Solo letras, numeros, espacios y guiones">
                </div>

                <!-- Fecha de constancia -->
                <div class="col-md-6">
                  <label class="form-label fw-bold">Fecha de Expedición <span class="text-danger">*</span></label>
                  <input type="date" class="form-control" name="fecha_constancia" id="c_fecha_constancia"
                         value="<?= date('Y-m-d') ?>" required>
                </div>
                  
              </div>
            </div>
          </div>

          <!-- ── CROQUIS ── -->
    <div class="card-header bg-secondary text-white d-flex align-items-center gap-2 mt-3">
        <i class="bi bi-map-fill"></i>
        <span class="fw-bold">Croquis del Predio</span>
        <span class="badge bg-warning text-dark ms-1">Requerido para imprimir</span>
    </div>
    <div class="card-body">
        <div id="ver_alerta_croquis" class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3" style="display:none;">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>Sin croquis. Selecciona una imagen y guárdala para poder imprimir.</span>
        </div>
        <div id="ver_ok_croquis" class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3" style="display:none;">
            <i class="bi bi-check-circle-fill"></i>
            <span>Croquis cargado correctamente.</span>
        </div>
        <div class="row g-2 align-items-start">
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Imagen del croquis (JPG/PNG, máx. 10MB, se redimensiona a 500x800 píxeles)</label>
                <input type="file" class="form-control form-control-sm" id="ver_inp_croquis"
                       accept="image/jpeg,image/png,image/webp"
                       onchange="ver_prevCroquis(this)">
                <div class="text-muted small mt-1">
                    <i class="bi bi-clipboard me-1"></i>También puedes pegar una imagen con Ctrl+V
                </div>
                <button type="button" class="btn btn-sm btn-secondary w-100 mt-2"
                        id="ver_btn_subir" onclick="ver_subirCroquis()" style="display:none;">
                    <i class="bi bi-cloud-upload me-1"></i>Guardar croquis
                </button>
                <div id="ver_msg_croquis" class="small fw-semibold mt-1"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Vista previa:</label>
                <div id="ver_preview_box" style="border:2px dashed #ccc;border-radius:6px;min-height:200px;display:flex;align-items:center;justify-content:center;background:#f8f9fa;overflow:hidden;">
                    <span id="ver_prev_ph" class="text-muted small text-center px-2">
                        <i class="bi bi-image fs-3 d-block mb-1"></i>Vista previa del croquis
                    </span>
                    <img id="ver_prev_img" src="" style="display:none;width:100%;max-height:100px;object-fit:contain;">
                </div>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-lg me-1"></i> Cerrar
        </button>
        <button type="button" id="btnSoloImprimir" class="btn btn-primary">
          <i class="bi bi-printer me-1"></i> Imprimir
        </button>
        <button type="submit" form="formConstancia" class="btn btn-success">
          <i class="bi bi-save me-1"></i> Guardar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: NOTIFICACIÓN AL CIUDADANO -->
<div class="modal fade" id="notifModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow border-0">
      <div class="modal-header" style="background:#7b0f2b;color:white;">
        <h5 class="modal-title" id="notif-modal-title">
          <i class="bi bi-bell me-2"></i>Notificar al Ciudadano
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-3" id="notif-modal-desc"></p>

        <!-- Preview del mensaje a enviar -->
        <div id="notif-msg-preview" class="alert alert-light border mb-3" style="display:none;">
          <p class="mb-1 fw-bold small text-secondary"><i class="bi bi-chat-quote me-1"></i>Mensaje que se enviará:</p>
          <p id="notif-msg-texto" class="mb-0 small" style="white-space:pre-line;"></p>
        </div>

        <p class="mb-3" style="font-size:.87rem;">
          El mensaje ya está listo. Presiona el botón del canal preferido del ciudadano para enviarlo:
        </p>
        <a id="notif-wa-link" href="#" target="_blank" rel="noopener"
           class="d-flex align-items-center gap-3 p-3 rounded border mb-2 text-decoration-none text-dark"
           style="border-color:#25D366 !important;background:rgba(37,211,102,.06);transition:.2s;">
          <span style="font-size:2rem;">💬</span>
          <div>
            <div class="fw-bold" style="color:#25D366;">Enviar por WhatsApp</div>
            <div class="text-muted notif-sub" style="font-size:.78rem;">Abre WhatsApp con el mensaje ya escrito</div>
          </div>
        </a>
        <a id="notif-gm-link" href="#" target="_blank" rel="noopener"
           class="d-flex align-items-center gap-3 p-3 rounded border mb-2 text-decoration-none text-dark"
           style="border-color:#EA4335 !important;background:rgba(234,67,53,.06);transition:.2s;">
          <span style="font-size:2rem;">📧</span>
          <div>
            <div class="fw-bold" style="color:#EA4335;">Enviar por Correo</div>
            <div class="text-muted notif-sub" style="font-size:.78rem;">Abre tu cliente de correo con el mensaje listo</div>
          </div>
        </a>
        <p class="text-muted mt-3 mb-0" style="font-size:.72rem;">
          <i class="bi bi-info-circle me-1"></i>
          Si el ciudadano no proporcionó correo o teléfono, ese botón estará deshabilitado.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar sin notificar</button>
      </div>
    </div>
  </div>
</div>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/verificar.js"></script>

<script>
// Mostrar alertas con SweetAlert2 - SOLO para errores del sistema, NO para validación de campos
document.addEventListener('DOMContentLoaded', () => {
  <?php if(isset($_GET['error']) && $_GET['error'] !== 'sin_numero_asignado'): ?>
    let errorMsg = '';
    let errorTitle = 'Error';
    
    switch('<?= $_GET['error'] ?>') {
    case 'csrf':
        errorMsg = 'Error de seguridad. Por favor intenta de nuevo.';
        errorTitle = 'Error de Seguridad';
        break;
    case 'tramite_no_encontrado':
        errorMsg = 'No se encontró el trámite especificado.';
        errorTitle = 'Trámite No Encontrado';
        break;
    case 'archivo_invalido':
        errorMsg = 'El archivo no es válido o excede el tamaño permitido.';
        errorTitle = 'Archivo No Válido';
        break;
    default:
        errorMsg = '<?= htmlspecialchars($_GET['error'] ?? '') ?>';
    }
    
    Swal.fire({
      icon: 'error',
      title: errorTitle,
      text: errorMsg,
      confirmButtonText: 'Entendido',
      confirmButtonColor: '#2f7d6d'
    });
  <?php endif; ?>

  <?php if(isset($_GET['success'])): ?>
    let successMsg = '';
    let successTitle = '¡Éxito!';
    
    switch('<?= $_GET['success'] ?>') {
      case 'tramite_actualizado':
        successMsg = 'El trámite ha sido actualizado correctamente.';
        successTitle = '¡Trámite Actualizado!';
        break;
      case 'fotografias_subidas':
        successMsg = 'Las fotografías han sido subidas correctamente.';
        successTitle = '¡Fotografías Guardadas!';
        break;
      default:
        successMsg = 'Operación completada correctamente.';
    }
    
    Swal.fire({
      icon: 'success',
      title: successTitle,
      text: successMsg,
      confirmButtonText: 'Continuar',
      confirmButtonColor: '#2f7d6d',
      timer: 3000,
      timerProgressBar: true
    });
  <?php endif; ?>
});

// Guardar configuración de constancia
function guardarConfigConstanciaVer() {
  var form = document.getElementById('formConfigConstanciaVer');
  var msg  = document.getElementById('msg-config-constancia-ver');
  var btn  = form.querySelector('button[onclick]');
  var fd   = new FormData(form);

  msg.textContent = 'Guardando...';
  msg.style.color = '#666';
  btn.disabled = true;

  fetch('php/actualizar_config_constancia.php', {
    method: 'POST',
    body: fd,
    credentials: 'same-origin'
  })
  .then(function(r){ return r.json(); })
  .then(function(data){
    btn.disabled = false;
    if (data.success) {
      msg.textContent = '✅ ' + data.message;
      msg.style.color = '#198754';
    } else {
      msg.textContent = '❌ ' + data.message;
      msg.style.color = '#dc3545';
    }
    setTimeout(function(){ msg.textContent = ''; }, 4000);
  })
  .catch(function(){
    btn.disabled = false;
    msg.textContent = '❌ Error de conexión.';
    msg.style.color = '#dc3545';
  });
}

    // Función para abrir modal de constancia
    function abrirModalConstancia(btn) {
    const folio = btn.getAttribute('data-folio');
    const modalConstancia = new bootstrap.Modal(document.getElementById('modalConstancia'));
    
    // Poblar los datos en el modal de constancia
    document.getElementById('c_folio').textContent = folio;
    document.getElementById('c_propietario').textContent = btn.getAttribute('data-propietario') || '';
    document.getElementById('c_direccion').textContent = btn.getAttribute('data-direccion') || '';
    document.getElementById('c_colonia_constancia').value = btn.getAttribute('data-colonia') || '';
    document.getElementById('c_localidad').textContent = btn.getAttribute('data-localidad') || '';
    document.getElementById('c_folio_hidden').value = folio;
    document.getElementById('c_numero_asignado').value = btn.getAttribute('data-numero-asignado') || '';
    
    // Seleccionar la opción correcta en el select
    const tipoAsignacion = btn.getAttribute('data-tipo-asignacion') || 'ASIGNACION';
    const selectTipo = document.getElementById('c_tipo_asignacion');
    if (selectTipo) {
        const tipoUpper = tipoAsignacion.toUpperCase();
        for (let i = 0; i < selectTipo.options.length; i++) {
            if (selectTipo.options[i].value === tipoUpper) {
                selectTipo.selectedIndex = i;
                break;
            }
        }
    }
    
    document.getElementById('c_referencia_anterior').value = btn.getAttribute('data-referencia-anterior') || '';
    document.getElementById('c_entre_calle1').value = btn.getAttribute('data-entre-calle1') || '';
    document.getElementById('c_entre_calle2').value = btn.getAttribute('data-entre-calle2') || '';
    document.getElementById('c_cuenta_catastral').value = btn.getAttribute('data-cuenta-catastral') || '';
    document.getElementById('c_superficie_constancia').value = btn.getAttribute('data-superficie') || '';
    document.getElementById('c_direccion_constancia').value = btn.getAttribute('data-direccion') || '';
    document.getElementById('c_manzana').value = btn.getAttribute('data-manzana') || '';
    document.getElementById('c_lote').value = btn.getAttribute('data-lote') || '';
    document.getElementById('c_fecha_constancia').value = btn.getAttribute('data-fecha-constancia') || new Date().toISOString().split('T')[0];
    
    // Mostrar croquis si existe
    const croquis = btn.getAttribute('data-croquis');
    const alertaCroquis = document.getElementById('ver_alerta_croquis');
    const okCroquis = document.getElementById('ver_ok_croquis');
    const prevImg = document.getElementById('ver_prev_img');
    const prevPh = document.getElementById('ver_prev_ph');
    
    if (croquis && croquis.trim()) {
        alertaCroquis.style.display = 'none';
        okCroquis.style.display = 'flex';
        prevImg.src = croquis;
        prevImg.style.display = 'block';
        prevPh.style.display = 'none';
    } else {
        alertaCroquis.style.display = 'flex';
        okCroquis.style.display = 'none';
        prevImg.style.display = 'none';
        prevPh.style.display = 'flex';
    }
    
    modalConstancia.show();
}

// Permitir abrir el modal de constancia desde el modal de detalle (botón "Llenar / imprimir Constancia")
document.addEventListener('DOMContentLoaded', function() {
    const btnDesdeDetalle = document.getElementById('btn_abrir_constancia_desde_detalle');
    if (btnDesdeDetalle) {
        btnDesdeDetalle.addEventListener('click', function(e) {
            // Tomar datos visibles en el modal de detalle y pasarlos al modal de constancia
            const folio = document.getElementById('m_folio').textContent.trim();
            const propietario = document.getElementById('m_propietario').textContent.trim();
            const direccion = document.getElementById('m_direccion').textContent.trim();
            const localidad = document.getElementById('m_localidad').textContent.trim();
            const tipoTramiteId = document.getElementById('m_tipo_tramite_id') ? document.getElementById('m_tipo_tramite_id').value : '';

            // Poblar campos del modal de constancia
            document.getElementById('c_folio').textContent = folio;
            document.getElementById('c_propietario').textContent = propietario;
            document.getElementById('c_direccion').textContent = direccion;
            document.getElementById('c_colonia_constancia').value = (document.getElementById('m_colonia') ? document.getElementById('m_colonia').textContent.trim() : '');
            document.getElementById('c_localidad').textContent = localidad;
            document.getElementById('c_folio_hidden').value = folio;
            document.getElementById('c_direccion_constancia').value = direccion;
            // Si por alguna razón el modal detalle no tiene colonia, intentar leer data-colonia del botón original (no es habitual)
            const posibleCol = (event && event.relatedTarget) ? (event.relatedTarget.getAttribute('data-colonia') || '') : '';
            if (!document.getElementById('c_colonia_constancia').value && posibleCol) document.getElementById('c_colonia_constancia').value = posibleCol;

            // Mostrar modal
            const modalConstancia = new bootstrap.Modal(document.getElementById('modalConstancia'));
            modalConstancia.show();
        });
    }
});

// Cargar datos de número oficial anterior en modal constancia (verificador)
function cargarDatosAnterioresVer() {
  const folio      = document.getElementById('c_buscar_folio').value.trim();
  const propietario = document.getElementById('c_buscar_propietario').value.trim();
  const msg        = document.getElementById('c_msg_busqueda');

  if (!folio && !propietario) {
    msg.style.display = 'block';
    msg.style.color   = '#dc3545';
    msg.textContent   = '⚠️ Escribe un folio de salida o el nombre del propietario.';
    return;
  }

  msg.style.display = 'block';
  msg.style.color   = '#0d6efd';
  msg.textContent   = '🔍 Buscando...';

  let url = 'php/obtener_tramite_anterior.php?incluir_constancia=true';
  if (folio) {
    url += '&folio=' + encodeURIComponent(folio) + '&buscar_por_folio_salida=true';
  } else {
    url += '&propietario=' + encodeURIComponent(propietario) + '&tipo_tramite_id=1';
  }

  fetch(url)
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        msg.style.color = '#dc3545';
        msg.textContent = '❌ ' + data.error;
        return;
      }
      const c = data.tramite.constancia || {};
      const t = data.tramite;

      // Rellenar campos de constancia con los datos anteriores
      if (c.numero_asignado)     document.getElementById('c_numero_asignado').value      = c.numero_asignado;
      if (c.tipo_asignacion) {
        const sel = document.getElementById('c_tipo_asignacion');
        for (let i = 0; i < sel.options.length; i++) {
          if (sel.options[i].value === c.tipo_asignacion.toUpperCase()) { sel.selectedIndex = i; break; }
        }
      }
      if (c.referencia_anterior) document.getElementById('c_referencia_anterior').value  = c.referencia_anterior;
      if (c.entre_calle1)        document.getElementById('c_entre_calle1').value          = c.entre_calle1;
      if (c.entre_calle2)        document.getElementById('c_entre_calle2').value          = c.entre_calle2;
      if (c.manzana)             document.getElementById('c_manzana').value               = c.manzana;
      if (c.lote)                document.getElementById('c_lote').value                  = c.lote;
      if (t.cuenta_catastral)    document.getElementById('c_cuenta_catastral').value      = t.cuenta_catastral;

      // Cargar croquis si existe en el trámite anterior
      const croquis       = (t.archivos && t.archivos.croquis_archivo) ? t.archivos.croquis_archivo : '';
      const alertaCroquis = document.getElementById('ver_alerta_croquis');
      const okCroquis     = document.getElementById('ver_ok_croquis');
      const prevImg       = document.getElementById('ver_prev_img');
      const prevPh        = document.getElementById('ver_prev_ph');
      if (croquis) {
        // Mostrar imagen en vista previa
        alertaCroquis.style.display = 'none';
        okCroquis.style.display     = 'flex';
        prevImg.src                 = croquis;
        prevImg.style.display       = 'block';
        prevPh.style.display        = 'none';

        // Persistir en BD: asignar el croquis al trámite actual
        const folioActual = document.getElementById('c_folio_hidden').value;
        const fd = new FormData();
        fd.append('folio_destino',   folioActual);
        fd.append('croquis_archivo', croquis);
        fetch('php/asignar_croquis.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(r => r.json())
          .then(res => {
            if (!res.success) console.warn('No se pudo asignar croquis:', res.message);
          })
          .catch(err => console.warn('Error asignando croquis:', err));
      }

      msg.style.color = '#198754';
      msg.textContent = '✅ Datos cargados del folio ' + (data.tramite.folio_salida || data.tramite.folio) + ' — ' + data.tramite.propietario;
    })
    .catch(() => {
      msg.style.color = '#dc3545';
      msg.textContent = '❌ Error de conexión.';
    });
}

// Limpiar campos de búsqueda al abrir el modal
document.getElementById('modalConstancia').addEventListener('show.bs.modal', function () {
  document.getElementById('c_buscar_folio').value       = '';
  document.getElementById('c_buscar_propietario').value = '';
  const msg = document.getElementById('c_msg_busqueda');
  msg.style.display = 'none';
  msg.textContent   = '';
});

// Cuando se abre el modal de detalle, cargar los datos
document.getElementById('detalleTramite').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const folio = button.getAttribute('data-folio');
    
    const btnVerDocumentos = document.getElementById('btn_ver_documentos');
    if (btnVerDocumentos && folio) {
        btnVerDocumentos.href = 'imprimir_documentos.php?folio=' + encodeURIComponent(folio);
    }
    
    const btnImprimirFicha = document.getElementById('btn_imprimir_ficha');
    if (btnImprimirFicha && folio) {
        btnImprimirFicha.href = 'ficha.php?folio=' + encodeURIComponent(folio);
    }
});

// Interceptar el envío del formulario de actualización de trámite
document.addEventListener('DOMContentLoaded', function() {
    const formActualizar = document.getElementById('formActualizarTramite');
    if (formActualizar) {
        formActualizar.addEventListener('submit', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Guardando...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('php/actualizarTramite.php', {
                method: 'POST',
                body: new FormData(this),
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('detalleTramite'));
                    if (modal) modal.hide();
                    
                    Swal.fire({
                        icon: 'success',
                        title: '¡Actualizado!',
                        text: data.message || 'Trámite actualizado correctamente.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'No se pudo actualizar el trámite.'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo conectar con el servidor.'
                });
            });
        });
    }
    
    // Configurar botones de constancia
    const botonesConstancia = document.querySelectorAll('.btn-generar-constancia');
    botonesConstancia.forEach(btn => {
        const nuevoBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(nuevoBtn, btn);
        
        nuevoBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            abrirModalConstancia(this);
        });
    });
    
    // Configurar botón de imprimir en modal de constancia - SIN ALERTA, usa validación nativa
    const btnImprimirConstancia = document.getElementById('btnSoloImprimir');
    if (btnImprimirConstancia) {
        const nuevoBoton = btnImprimirConstancia.cloneNode(true);
        btnImprimirConstancia.parentNode.replaceChild(nuevoBoton, btnImprimirConstancia);
        
        nuevoBoton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Obtener el formulario
            const form = document.getElementById('formConstancia');
            
            // Verificar si el formulario es válido usando la validación nativa del navegador
            if (form.checkValidity()) {
                // Si es válido, redirigir a imprimir
                const folio = document.getElementById('c_folio_hidden').value;
                const url = 'constancia_numero.php?folio=' + encodeURIComponent(folio) + '&imprimir=1';
                window.location.href = url;
            } else {
                // Si no es válido, mostrar los mensajes de validación nativos del navegador
                form.reportValidity();
            }
        });
    }
});
</script>
</body>
</html>
