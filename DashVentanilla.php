<?php
require "seguridad.php";
require_once "php/funciones_seguridad.php";

if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['Ventanilla', 'Administrador'])) {
    header("Location: acceso.php?error=no_autorizado");
    exit();
}

require_once "php/db.php";

// ── Estadísticas ──
$stats = $conn->query("
    SELECT
        SUM(CASE WHEN estatus = 'En corrección' THEN 1 ELSE 0 END) AS correccion,
        SUM(CASE WHEN estatus = 'En revisión' THEN 1 ELSE 0 END) AS revision,
        COUNT(*) AS total
    FROM tramites
")->fetch_assoc();
$total_correccion = $stats['correccion'];
$total_revision = $stats['revision'];
$total_todos = $stats['total'];

// ── Reporte (igual que DashAdmin) ──
$anio_filtro = isset($_GET['anio_reporte']) ? (int)$_GET['anio_reporte'] : (int)date('Y');
$anios_res = $conn->query("SELECT DISTINCT folio_anio FROM tramites ORDER BY folio_anio DESC");
$anios_disponibles = [];
while ($ar = $anios_res->fetch_assoc()) $anios_disponibles[] = $ar['folio_anio'];
if (empty($anios_disponibles)) $anios_disponibles[] = (int)date('Y');

$totals = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM tramites WHERE folio_anio=$anio_filtro) AS gran_total,
        (SELECT COUNT(*) FROM tramites) AS total_global
")->fetch_assoc();
$gran_total = (int)$totals['gran_total'];
$total_global = (int)$totals['total_global'];

$reporte_mes = $conn->query("
    SELECT MONTH(fecha_ingreso) AS mes,
           COUNT(*) AS total,
           SUM(estatus='Aprobado') AS aprobados,
           SUM(estatus='En revisión') AS en_revision,
           SUM(estatus='En corrección') AS en_correccion,
           SUM(estatus='Rechazado') AS rechazados
    FROM tramites
    WHERE folio_anio = $anio_filtro
    GROUP BY MONTH(fecha_ingreso)
    ORDER BY mes
");
$datos_mes = [];
while ($r = $reporte_mes->fetch_assoc()) $datos_mes[(int)$r['mes']] = $r;

$reporte_tipo = $conn->query("
    SELECT tt.nombre AS tipo, COUNT(*) AS total,
           SUM(t.estatus='Aprobado') AS aprobados,
           SUM(t.estatus='Rechazado') AS rechazados
    FROM tramites t
    LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
    WHERE t.folio_anio = $anio_filtro
    GROUP BY tt.nombre
    ORDER BY total DESC
");
$datos_tipo = [];
while ($r = $reporte_tipo->fetch_assoc()) $datos_tipo[] = $r;
$total_aprobados_v = $conn->query("SELECT COUNT(*) as c FROM tramites WHERE estatus = 'Aprobado por Verificador'")->fetch_assoc()['c'];

// ── Configuración del sistema (para formato de constancia) ──
$cfg = [];
$resCfg = $conn->query("SELECT clave, valor FROM configuracion_sistema");
while ($rowCfg = $resCfg->fetch_assoc()) $cfg[$rowCfg['clave']] = $rowCfg['valor'];

// ── Trámites aprobados por verificador (pendientes de firma del Director) ──
$aprobados_ver_res = $conn->query("
    SELECT t.*, tt.nombre AS tipo_tramite_nombre
    FROM tramites t
    LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
    WHERE t.estatus = 'Aprobado por Verificador'
    ORDER BY t.updated_at ASC
");


// ── Trámites firmados por el Director (ya aprobados) ──
$tramites_firmados_res = $conn->query("
    SELECT t.*, tt.nombre AS tipo_tramite_nombre,
           u.nombre AS solicitante_nombre, u.apellidos AS solicitante_apellidos,
           CONCAT(LPAD(t.folio_numero,3,'0'),'/',t.folio_anio) AS folio_formateado
    FROM tramites t
    LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
    LEFT JOIN usuarios u ON t.usuario_creador_id = u.id
    WHERE t.estatus = 'Aprobado'
    ORDER BY t.fecha_aprobacion DESC
");

// ── Folio siguiente ──
$anio_actual = date("Y");
$stmtF = $conn->prepare("SELECT COALESCE(MAX(CAST(folio_numero AS UNSIGNED)),0)+1 FROM tramites WHERE folio_anio=?");
$stmtF->bind_param("i",$anio_actual);
$stmtF->execute();
$stmtF->bind_result($siguiente_folio);
$stmtF->fetch();
$stmtF->close();
$siguiente_folio = str_pad($siguiente_folio, 3, "0", STR_PAD_LEFT);

// ── Trámites en corrección ──
$correccion_res = $conn->query("
    SELECT t.*, tt.nombre AS tipo_tramite_nombre
    FROM tramites t LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
    WHERE t.estatus = 'En corrección'
    ORDER BY t.updated_at DESC
");

// ── Todos los trámites (seguimiento) ──
$sql_seg = "SELECT t.*, tt.nombre AS tipo_tramite_nombre FROM tramites t
            LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id WHERE 1=1";
$params_seg = []; $types_seg = "";
if (!empty($_GET['folio'])) {
    if (str_contains($_GET['folio'],'/')) {
        [$fn,$fa] = explode('/',$_GET['folio']);
        $sql_seg .= " AND t.folio_numero=? AND t.folio_anio=?";
        $params_seg[]=(int)$fn; $params_seg[]=(int)$fa; $types_seg.="ii";
    } else {
        $sql_seg .= " AND t.folio_numero LIKE ?";
        $params_seg[]='%'.$_GET['folio'].'%'; $types_seg.="s";
    }
}
if (!empty($_GET['nombre'])) {
    $sql_seg .= " AND (t.propietario LIKE ? OR t.solicitante LIKE ?)";
    $params_seg[]='%'.$_GET['nombre'].'%'; $params_seg[]='%'.$_GET['nombre'].'%'; $types_seg.="ss";
}
if (!empty($_GET['estatus'])) {
    $sql_seg .= " AND t.estatus=?"; $params_seg[]=$_GET['estatus']; $types_seg.="s";
}
$sql_seg .= " ORDER BY t.created_at DESC";
$stmtSeg = $conn->prepare($sql_seg);
if (!empty($params_seg)) $stmtSeg->bind_param($types_seg,...$params_seg);
$stmtSeg->execute();
$seg_res = $stmtSeg->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sis Dit</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css"/>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
<link rel="stylesheet" href="./css/style.css?v=1">
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.7.5/proj4.js"></script>
<script>
history.pushState(null,null,location.href);
window.onpopstate=function(){history.go(1);};
</script>
<link rel="stylesheet" href="css/dashVentanilla.css">
</head>
<body>

<!-- NAVBAR MÓVIL -->
<nav class="navbar navbar-dark bg-dark d-lg-none">
  <div class="container-fluid">
    <span class="navbar-brand"><i class="bi bi-person-badge me-2"></i>Ventanilla</span>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuMovil">
      <span class="navbar-toggler-icon"></span>
    </button>
  </div>
  <div class="collapse navbar-collapse" id="menuMovil">
    <ul class="navbar-nav p-3">
      <li class="nav-item"><a class="nav-link" href="#inicio">Inicio</a></li>
      <li class="nav-item"><a class="nav-link" href="#firma-director"><i class="bi bi-pen me-1"></i>Firma Director
        <?php if($total_aprobados_v > 0): ?><span class="badge bg-warning text-dark"><?= $total_aprobados_v ?></span><?php endif; ?>
      </a></li>
      <li class="nav-item"><a class="nav-link" href="#tramite">Nuevo Trámite</a></li>
      <li class="nav-item"><a class="nav-link" href="#correccion">En Corrección <span class="badge bg-primary"><?= $total_correccion ?></span></a></li>
      <li class="nav-item"><a class="nav-link" href="#seguimiento">Seguimiento</a></li>
      <li class="nav-item"><a class="nav-link" href="#tramites-aprobados">Constancias</a></li>
      <li class="nav-item"><a class="nav-link" href="#reporte"><i class="bi bi-bar-chart-line me-1"></i> Reporte</a></li>
      <li class="nav-item"><a class="nav-link" href="#config-constancia"><i class="bi bi-file-earmark-text me-1"></i> Formato Constancia</a></li>
      <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Cerrar sesión</a></li>
    </ul>
  </div>
</nav>

<!-- SIDEBAR -->
<div class="sidebar d-none d-lg-flex">
  <h5><i class="bi bi-person-badge me-2"></i>Ventanilla</h5>
  <a href="#inicio"><i class="bi bi-house me-2"></i>Inicio</a>
  <a href="#firma-director"><i class="bi bi-pen me-2"></i>Firma Director
    <?php if($total_aprobados_v > 0): ?>
    <span class="badge bg-warning text-dark ms-1"><?= $total_aprobados_v ?></span>
    <?php endif; ?>
  </a>
  <a href="#tramite"><i class="bi bi-plus-circle me-2"></i>Nuevo Trámite</a>
  <a class="nav-link text-white" href="#mapa"><i class="bi bi-map me-2"></i> Mapa</a>
  <a href="#correccion"><i class="bi bi-pencil-square me-2"></i>En Corrección
    <?php if($total_correccion>0): ?>
    <span class="badge bg-warning text-dark ms-1"><?= $total_correccion ?></span>
    <?php endif; ?>
  </a>
  <a href="#seguimiento"><i class="bi bi-search me-2"></i>Seguimiento</a>
  <a href="#tramites-aprobados">
  <i class="bi bi-printer me-2"></i>Constancias
</a>
  <a href="#reporte"><i class="bi bi-bar-chart-line me-2"></i>Reporte</a>
  <a href="#config-constancia"><i class="bi bi-file-earmark-text me-2"></i>Formato Constancia</a>

  <a href="logout.php" class="text-danger mt-auto"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a>
</div>

<!-- CONTENIDO -->
<div class="content">

<!-- HERO -->
<section class="hero" id="inicio">
  <h1>Panel de Ventanilla</h1>
  <p>Bienvenido, <strong><?= htmlspecialchars(isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '') ?></strong>.
     Registra nuevos trámites, atiende correcciones y da seguimiento a todos los expedientes.</p>
</section>

<!-- ESTADÍSTICAS -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm border-start border-info border-4">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div><p class="text-muted mb-1 small">Pend. Firma Director</p><h3 class="mb-0 fw-bold"><?= $total_aprobados_v ?></h3></div>
        <i class="bi bi-pen text-info fs-1 opacity-40"></i>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm border-start border-primary border-4">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div><p class="text-muted mb-1 small">En Corrección</p><h3 class="mb-0 fw-bold"><?= $total_correccion ?></h3></div>
        <i class="bi bi-pencil-square text-primary fs-1 opacity-40"></i>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm border-start border-warning border-4">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div><p class="text-muted mb-1 small">En Revisión</p><h3 class="mb-0 fw-bold"><?= $total_revision ?></h3></div>
        <i class="bi bi-hourglass-split text-warning fs-1 opacity-40"></i>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm border-start border-success border-4">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div><p class="text-muted mb-1 small">Total Trámites</p><h3 class="mb-0 fw-bold"><?= $total_todos ?></h3></div>
        <i class="bi bi-folder-check text-success fs-1 opacity-40"></i>
      </div>
    </div>
  </div>
</div>

<!-- ================================================ -->
<!-- FIRMA DEL DIRECTOR / RESOLUCIÓN FINAL           -->
<!-- ================================================ -->
<section id="firma-director" class="tramite-box mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="m-0" style="color:#7b0f2b;">
      <i class="bi bi-pen me-2"></i>Aprobados por Verificador — Firma del Director
    </h4>
    <span class="badge bg-info text-dark fs-6"><?= $aprobados_ver_res->num_rows ?></span>
  </div>
  <div class="alert alert-info py-2 mb-3" style="font-size:.85rem;">
    <i class="bi bi-info-circle-fill me-2"></i>
    Estos trámites fueron revisados y aprobados por el verificador. La ventanilla registra aquí si el Director los <strong>firma (Aprobado)</strong> o los <strong>Rechaza</strong>.
  </div>

  <?php if($aprobados_ver_res->num_rows === 0): ?>
  <div class="text-center text-muted py-4">
    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
    <p>No hay trámites pendientes de firma del Director.</p>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table id="tablaFirmaDirector" class="table table-bordered table-hover align-middle">
      <thead style="background:#7b0f2b;color:white;">
        <tr>
          <th style="background:#7b0f2b;color:#fff;">Folio Ingreso</th>
          <th style="background:#7b0f2b;color:#fff;">Folio Salida</th>
          <th style="background:#7b0f2b;color:#fff;">Propietario</th>
          <th style="background:#7b0f2b;color:#fff;">Trámite</th>
          <th style="background:#7b0f2b;color:#fff;">Teléfono</th>
          <th style="background:#7b0f2b;color:#fff;">Verificador</th>
          <th style="background:#7b0f2b;color:#fff;">Fecha Aprobación</th>
          <th style="background:#7b0f2b;color:#fff;text-align:center;">Resolución</th>
        </tr>
      </thead>
      <tbody>
      <?php while($av = $aprobados_ver_res->fetch_assoc()):
        $folio_salida = '';

if (!empty($av['folio_salida_numero'])) {
    $folio_salida = str_pad($av['folio_salida_numero'], 3, '0', STR_PAD_LEFT) . '/' . $av['folio_salida_anio'];
}
        $fav       = $av['folio_numero'].'/'.$av['folio_anio'];
        $tnav      = isset($av['tipo_tramite_nombre']) ? $av['tipo_tramite_nombre'] : '—';
        $telav     = isset($av['telefono'])            ? $av['telefono']            : '—';
        $corrav    = isset($av['correo'])              ? $av['correo']              : '';
        $verif     = isset($av['verificador_nombre'])  ? $av['verificador_nombre']  : '—';
        $obsav     = isset($av['observaciones'])       ? $av['observaciones']       : '';
        $fechaApro = $av['fecha_aprobacion'] ? date('d/m/Y H:i', strtotime($av['fecha_aprobacion'])) : '—';
        $solav     = isset($av['solicitante'])         ? $av['solicitante']         : '';
        $propav    = isset($av['propietario'])         ? $av['propietario']         : '';
        // Datos constancia
        $num_asig  = isset($av['numero_asignado'])     ? $av['numero_asignado']     : '';
        $tipo_asig = isset($av['tipo_asignacion'])     ? $av['tipo_asignacion']     : 'ASIGNACION';
        $ref_ant   = isset($av['referencia_anterior']) ? $av['referencia_anterior'] : '';
        $entre_c   = isset($av['entre_calles'])        ? $av['entre_calles']        : '';
        $mzav      = isset($av['manzana'])             ? $av['manzana']             : '';
        $loteav    = isset($av['lote'])                ? $av['lote']                : '';
        $fec_const = isset($av['fecha_constancia'])    ? $av['fecha_constancia']    : date('Y-m-d');
        $cta_cat   = isset($av['cuenta_catastral'])    ? $av['cuenta_catastral']    : '';
        $dirav     = isset($av['direccion'])           ? $av['direccion']           : '';
        $locav     = isset($av['localidad'])           ? $av['localidad']           : '';
      ?>
      <tr>
        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($fav) ?></span></td>
        <td>
  <?= $folio_salida ? '<span class="badge bg-success">'.$folio_salida.'</span>' : '<span class="text-muted">—</span>' ?>
</td>
        <td><?= htmlspecialchars($propav) ?></td>
        <td><?= htmlspecialchars($tnav) ?></td>
        <td><?= htmlspecialchars($telav) ?></td>
        <td><small><?= htmlspecialchars($verif) ?></small></td>
        <td><?= htmlspecialchars($fechaApro) ?></td>
        <td class="text-center">
          <?php if((int)$av['tipo_tramite_id'] === 1): ?>
          <button class="btn btn-sm btn-outline-success btn-constancia-sec mb-1"
            data-folio="<?= htmlspecialchars($fav) ?>"
            data-propietario="<?= htmlspecialchars($propav) ?>"
            data-direccion="<?= htmlspecialchars($dirav) ?>"
            data-localidad="<?= htmlspecialchars($locav) ?>"
            data-numero-asignado="<?= htmlspecialchars($num_asig) ?>"
            data-tipo-asignacion="<?= htmlspecialchars($tipo_asig) ?>"
            data-referencia-anterior="<?= htmlspecialchars($ref_ant) ?>"
            data-entre-calles="<?= htmlspecialchars($entre_c) ?>"
            data-folio-salida-numero="<?= $av['folio_salida_numero'] ?>"
data-folio-salida-anio="<?= $av['folio_salida_anio'] ?>"
            data-manzana="<?= htmlspecialchars($mzav) ?>"
            data-lote="<?= htmlspecialchars($loteav) ?>"
            data-fecha-constancia="<?= htmlspecialchars($fec_const) ?>"
            data-cuenta-catastral="<?= htmlspecialchars($cta_cat) ?>"
            data-croquis="<?= htmlspecialchars(isset($av['croquis_archivo']) ? $av['croquis_archivo'] : '') ?>"
            data-bs-toggle="modal" data-bs-target="#modalConstanciaSec"
            title="Editar e imprimir constancia para firma">
            <i class="bi bi-file-earmark-check me-1"></i>Constancia
          </button><br>
          <?php endif; ?>
          <button class="btn btn-sm btn-success btn-firmar-director"
            data-folio="<?= htmlspecialchars($fav) ?>"
            data-folio-salida="<?= htmlspecialchars($folio_salida) ?>"
            data-propietario="<?= htmlspecialchars($propav) ?>"
            data-solicitante="<?= htmlspecialchars($solav) ?>"
            data-tramite="<?= htmlspecialchars($tnav) ?>"
            data-telefono="<?= htmlspecialchars($telav) ?>"
            data-correo="<?= htmlspecialchars($corrav) ?>"
            data-observaciones="<?= htmlspecialchars($obsav) ?>"
            data-tipo-tramite-id="<?= (int)$av['tipo_tramite_id'] ?>"
            title="Registrar firma del Director"
            data-bs-toggle="modal" data-bs-target="#modalFirmaDirector">
            <i class="bi bi-pen me-1"></i>Resolución
          </button>
        </td>
      </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<!-- MODAL: RESOLUCIÓN DEL DIRECTOR -->
<div class="modal fade" id="modalFirmaDirector" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow">
      <div class="modal-header text-white" style="background:#7b0f2b;">
        <h5 class="modal-title"><i class="bi bi-pen me-2"></i>Resolución del Director</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="formFirmaDirector" enctype="multipart/form-data">
          <?php $csrf_fd = generarCSRF(); ?>
          <input type="hidden" name="csrf_token" value="<?= $csrf_fd ?>">
          <input type="hidden" name="folio" id="fd_folio_hidden">
          <input type="hidden" name="verificador_nombre" value="VENTANILLA">
          <input type="hidden" name="tipo_tramite_id" id="fd_tipo_tramite_id">

          <!-- Info del trámite -->
          <div class="alert alert-light border mb-3 py-2">
            <p class="mb-1"><strong>Folio Ingreso:</strong> <span id="fd_folio" class="badge bg-info text-dark fs-6"></span></p>
            <p class="mb-1"><strong>Folio Salida:</strong> <span id="fd_folio_salida" class="badge bg-success fs-6">—</span></p>
            <p class="mb-1"><strong>Propietario:</strong> <span id="fd_propietario"></span></p>
            <p class="mb-1"><strong>Trámite:</strong> <span id="fd_tramite"></span></p>
            <p class="mb-0"><strong>Teléfono:</strong> <span id="fd_telefono"></span></p>
          </div>

          <!-- Selección de resolución -->
          <div class="mb-3">
            <label class="fw-bold mb-2">Resolución del Director:</label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="estatus" id="rdAprobado"
                       value="Aprobado" required>
                <label class="form-check-label fw-semibold text-success" for="rdAprobado">
                  <i class="bi bi-check-circle-fill me-1"></i>Aprobado — firmado por el Director
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="estatus" id="rdRechazado"
                       value="Rechazado">
                <label class="form-check-label fw-semibold text-danger" for="rdRechazado">
                  <i class="bi bi-x-circle-fill me-1"></i>Rechazado
                </label>
              </div>
            </div>
          </div>

          <!-- Observaciones / motivo -->
          <div class="mb-3">
            <label class="form-label fw-semibold small">
              <i class="bi bi-chat-left-text me-1"></i>Observaciones / Motivo de rechazo (opcional):
            </label>
            <textarea name="observaciones" id="fd_observaciones" class="form-control form-control-sm" rows="3"
              placeholder="Ej: El Director firmó el día de hoy / Rechazado por documentación incompleta..."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <a id="fd_btn_ficha" href="#" target="_blank" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-printer me-1"></i>Ver Ficha
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" form="formFirmaDirector" class="btn btn-primary">
          <i class="bi bi-save me-1"></i>Guardar Resolución
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: CONSTANCIA (Ventanilla) -->
<div class="modal fade" id="modalConstanciaSec" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow">

      <!-- HEADER -->
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">
          <i class="bi bi-file-earmark-check me-2"></i>
          Constancia de Número Oficial — Vista previa
        </h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <input type="hidden" id="cs_folio_hidden">

      <!-- BODY -->
      <div class="modal-body">

        <!-- INFO GENERAL -->
        <div class="alert alert-light border mb-3">
          <div class="row">
            <div class="col-md-6">
              <p><strong>Folio Ingreso:</strong> <span id="cs_folio" class="badge bg-success"></span></p>
               <p><strong>Folio Salida:</strong> 
        <span id="cs_folio_salida" class="badge bg-primary"></span>
      </p>
              <p><strong>Propietario:</strong> <span id="cs_propietario"></span></p>
            </div>
            <div class="col-md-6">
              <p><strong>Dirección:</strong> <span id="cs_direccion"></span></p>
              <p><strong>Localidad:</strong> <span id="cs_localidad"></span></p>
            </div>
          </div>
        </div>

        <!-- DATOS CONSTANCIA -->
        <div class="card border-success mb-3">
          <div class="card-header bg-success text-white">
            <i class="bi bi-file-earmark-text me-2"></i>Datos de la Constancia
          </div>
          <div class="card-body">
            <div class="row g-2">

              <div class="col-md-4">
                <strong>Tipo:</strong><br>
                <span id="cs_tipo_asignacion"></span>
              </div>

              <div class="col-md-4">
                <strong>Número Asignado:</strong><br>
                <span id="cs_numero_asignado"></span>
              </div>

              <div class="col-md-4">
                <strong>Referencia Anterior:</strong><br>
                <span id="cs_referencia_anterior"></span>
              </div>

              <div class="col-md-6">
                <strong>Entre Calles:</strong><br>
                <span id="cs_entre_calles"></span>
              </div>

              <div class="col-md-6">
                <strong>Cuenta Catastral:</strong><br>
                <span id="cs_cuenta_catastral"></span>
              </div>

              <div class="col-md-3">
                <strong>Manzana:</strong><br>
                <span id="cs_manzana"></span>
              </div>

              <div class="col-md-3">
                <strong>Lote:</strong><br>
                <span id="cs_lote"></span>
              </div>

              <div class="col-md-6">
                <strong>Fecha de Expedición:</strong><br>
                <span id="cs_fecha_constancia"></span>
              </div>

            </div>
          </div>
        </div>

        <!-- CROQUIS -->
        <div class="card border-dark">
          <div class="card-header text-white" style="background:#7b0f2b;">
            <i class="bi bi-map-fill me-2"></i>Croquis del Predio
          </div>
          <div class="card-body text-center">

            <img id="cs_preview_img"
                 src=""
                 style="max-width:100%; max-height:300px; object-fit:contain; border-radius:8px;">

            <div id="cs_no_img" class="text-muted mt-2">
              <i class="bi bi-image fs-2 d-block"></i>
              Sin croquis disponible
            </div>

          </div>
        </div>

      </div>

      <!-- FOOTER -->
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-lg me-1"></i>Cerrar
        </button>

        <button id="btnImprimirConstanciaSec" class="btn btn-success">
          <i class="bi bi-printer me-1"></i>Imprimir Constancia
        </button>
      </div>

    </div>
  </div>
</div>



<!-- ================================================ -->
<!-- NUEVO TRÁMITE                                    -->
<!-- ================================================ -->
<section id="tramite" class="tramite-box mb-4">

  <!-- PASO 1: Selección -->
  <div id="paso1-seleccion">
    <div class="d-flex justify-content-between align-items-center pb-3 mb-3 border-bottom">
      <h4 class="m-0" style="color:#7b0f2b;"><i class="bi bi-list-task me-2"></i>¿Qué trámite desea registrar?</h4>
      <span class="badge bg-secondary">Paso 1 de 2</span>
    </div>
    <p class="text-muted mb-4">Seleccione el tipo de trámite para ver los requisitos y continuar con el registro.</p>
    <div class="row g-3 mb-4">
      <?php
      $tipos_q = $conn->prepare("SELECT id, codigo, nombre, descripcion FROM tipos_tramite WHERE activo=1 ORDER BY ID");
      $tipos_q->execute();
      $tipos_r = $tipos_q->get_result();
      $iconos = [
        'NUM_OFICIAL'  => ['icon'=>'bi-123',                  'color'=>'#0d6efd'], // 1
        'CMCU'         => ['icon'=>'bi-building-check',       'color'=>'#198754'], // 2
        'FUSION'       => ['icon'=>'bi-union',                'color'=>'#fd7e14'], // 3
        'SUBDIVISION'  => ['icon'=>'bi-subtract',             'color'=>'#6610f2'], // 4
        'INFORME_CU'   => ['icon'=>'bi-file-earmark-bar-graph','color'=>'#0dcaf0'], // 5
        'USO_SUELO'    => ['icon'=>'bi-diagram-3',            'color'=>'#6f42c1'], // 6
        'LIC_CONST' => ['icon'=>'bi-building',             'color'=>'#dc3545'], // 7
        'ANUNCIOS'      => ['icon'=>'bi-megaphone',            'color'=>'#dc3545'], // 8

      ];
      $requisitosInfo = [
        1 => ['docs'=>['INE o Pasaporte','Boleta Predial Vigente','Título de Propiedad o Escritura'],'nota'=>''],
        2 => ['docs'=>['INE o Pasaporte','Boleta Predial Vigente','Título de Propiedad o Escritura','Formato de Constancia'],'nota'=>'Si es comercial se requiere contrato de arrendamiento y medidas de superficie.'],
        3 => ['docs'=>['INE o Pasaporte','Boleta Predial Vigente','Título de Propiedad o Escritura'],'nota'=>'Si es mas de 10,000 m2 se requiere levantamiento topográfico.'],
        4 => ['docs'=>['INE o Pasaporte','Boleta Predial Vigente','Título de Propiedad o Escritura'],'nota'=>''],
        5 => ['docs'=>['INE o Pasaporte','Cuenta Catastral del Predio'],'nota'=>''],
        6 => ['docs'=>['INE o Pasaporte','Boleta Predial Vigente','Título de Propiedad o Escritura'],'nota'=>''],
        7 => ['docs'=>['INE o Pasaporte','Boleta Predial Vigente','Título de Propiedad o Escritura'],'nota'=>''],
        8 => ['docs'=>['INE o Pasaporte','Boleta Predial Vigente','Contrato de Arrendamiento o Escritura'],'nota'=>'Se requiere memoria descriptiva o calculo de superficie, si es Empresa se requiere Poder Notariado y Acta Constitutiva.'],
      ];
      $tiposList = [];
      while($tp=$tipos_r->fetch_assoc()) $tiposList[]=$tp;
      $tipos_q->close();
      foreach($tiposList as $tp):
        $ic   = isset($iconos[$tp['codigo']])     ? $iconos[$tp['codigo']]     : array('icon'=>'bi-file-earmark','color'=>'#6c757d');
        $reqs = isset($requisitosInfo[$tp['id']]) ? $requisitosInfo[$tp['id']] : array('docs'=>array(),'nota'=>'');
      ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-2 tramite-selector-card"
             style="cursor:pointer;transition:all .25s;border-color:#dee2e6;"
             onclick="seleccionarTramite(<?= $tp['id'] ?>,'<?= htmlspecialchars(addslashes($tp['nombre'])) ?>')">
          <div class="card-body p-3">
            <div class="d-flex align-items-center gap-3 mb-2">
              <div style="width:46px;height:46px;border-radius:12px;background:<?= $ic['color'] ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi <?= $ic['icon'] ?>" style="font-size:1.4rem;color:<?= $ic['color'] ?>;"></i>
              </div>
              <div>
                <strong style="font-size:.92rem;"><?= htmlspecialchars($tp['nombre']) ?></strong>
                <div class="text-muted" style="font-size:.75rem;">10 días hábiles</div>
              </div>
            </div>
            <?php if(!empty($reqs['docs'])): ?>
            <ul class="list-unstyled mb-1" style="font-size:.78rem;color:#555;">
              <?php foreach($reqs['docs'] as $d): ?>
              <li><i class="bi bi-check2 text-success me-1"></i><?= $d ?></li>
              <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if(!empty($reqs['nota'])): ?>
            <div class="alert alert-warning py-1 px-2 mb-0 mt-1" style="font-size:.72rem;">
              <i class="bi bi-exclamation-triangle me-1"></i><?= $reqs['nota'] ?>
            </div>
            <?php endif; ?>
          </div>
          <div class="card-footer bg-transparent border-top-0 text-end py-2 px-3">
            <span style="font-size:.8rem;color:<?= $ic['color'] ?>;font-weight:600;">
              Seleccionar <i class="bi bi-arrow-right"></i>
            </span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="alert alert-secondary" style="font-size:.83rem;">
      <i class="bi bi-info-circle me-2"></i>
      Todos los requisitos deberán entregarse en <strong>copia</strong>. Si el trámite será realizado por un tercero, presente <strong>carta poder</strong>.
    </div>
  </div>

  <!-- PASO 2: Formulario -->
  <div id="paso2-formulario" style="display:none;">
    <div class="d-flex justify-content-between align-items-center pb-3 mb-3 border-bottom">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="volverSeleccion()">
          <i class="bi bi-arrow-left me-1"></i>Regresar
        </button>
        <h4 class="m-0" style="color:#7b0f2b;">
          <i class="bi bi-pencil-square me-2"></i>Registro: <span id="titulo-tramite-paso2"></span>
        </h4>
      </div>
      <span class="badge bg-success">Paso 2 de 2</span>
    </div>

    <div class="alert alert-success mb-4" id="recordatorio-requisitos" style="font-size:.85rem;">
      <h6 class="alert-heading"><i class="bi bi-clipboard-check me-2"></i>Documentos requeridos:</h6>
      <ul id="lista-req-recordatorio" class="mb-0 mt-2"></ul>
    </div>

    <form action="php/tramite.php" method="POST" enctype="multipart/form-data">
      <?php $csrf = generarCSRF(); ?>
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="tipo_tramite_id" id="tipo_tramite_id_hidden">

      <div class="d-flex justify-content-between align-items-center pb-2 mb-3">
        <h4 class="m-0" style="color:#7b0f2b;">Ingreso de Trámite</h4>
        <div class="d-flex align-items-center gap-1">
          <label class="form-label m-0">Folio:</label>
          <span><?= $siguiente_folio ?></span><span>/<?= $anio_actual ?></span>
          <input type="hidden" name="folio_numero" value="<?= $siguiente_folio ?>">
          <input type="hidden" name="folio_anio" value="<?= $anio_actual ?>">
        </div>
      </div>

      <?php if(!empty($_GET['error_msg'])): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <?= htmlspecialchars(urldecode($_GET['error_msg'])) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php endif; ?>

      <div class="mb-3">
          <label class="form-label">Nombre del Propietario <span class="text-danger">*</span></label>
          <div class="input-group">
              <input type="text" class="form-control" name="propietario" id="propietario_input" required placeholder="SOLO LETRAS MAYÚSCULAS"
                oninput="this.value=this.value.toUpperCase().replace(/[^A-ZÁÉÍÓÚÜÑ\s]/g,'')" style="text-transform:uppercase;">
              <button type="button" class="btn btn-outline-secondary" id="btnBuscarTramiteAnterior" title="Buscar trámite anterior del mismo propietario">
                  <i class="bi bi-search"></i> Buscar anterior
              </button>
              <button type="button" class="btn btn-outline-secondary" id="btnCargarPorFolio" title="Cargar por folio específico">
                  <i class="bi bi-file-text"></i> Por folio
              </button>
          </div>
          <small class="text-muted">Escribe el nombre y haz clic en "Buscar anterior" para cargar datos de un trámite previo (copiará datos generales).</small>
      </div>

      <!-- Campo oculto para el tipo de trámite actual -->
      <input type="hidden" id="tipo_tramite_actual" value="">
      <div class="mb-3">
        <label class="form-label">Dirección <span class="text-danger">*</span></label>
        <input type="text" class="form-control mayusculas" name="direccion" required placeholder="CALLE, NÚMERO, COLONIA">
      </div>
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label">Localidad <span class="text-danger">*</span></label>
          <input type="text" class="form-control mayusculas" name="localidad" required placeholder="EJ: RINCÓN DE ROMOS">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Colonia</label>
          <input type="text" class="form-control mayusculas" name="colonia" placeholder="EJ: CENTRO">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Código Postal</label>
          <input type="text" class="form-control" name="cp" maxlength="5" placeholder="Ej: 20400"
            oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,5)">
        </div>
      </div>
      <div class="row">
        <div class="col-md-3 mb-3">
          <label class="form-label">Cuenta Catastral <span class="badge bg-success ms-1" style="font-size:.65rem;">Auto</span></label>
          <div class="input-group">
            <input type="text" class="form-control" name="cuenta_catastral" id="cuenta_catastral"
                                    inputmode="numeric"
                                    placeholder="Ingrese cuenta para buscar"
                                    oninput="this.value=this.value.replace(/[^0-9]/g,'')">
            <button type="button" class="btn btn-primary" id="btnBuscarCuenta">
              <i class="bi bi-search"></i>
            </button>
          </div>
          <small class="text-muted">
            <i class="bi bi-info-circle me-1"></i>Solo números. Escriba y presione buscar o clic en mapa.
          </small>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Superficie</label>
          <input type="text" class="form-control" name="superficie" placeholder="Ej: 200 m2">
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">UTM X (Este)</label>
          <input type="text" class="form-control" name="lat" id="lat" placeholder="Ej: 284500.00" readonly>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">UTM Y (Norte)</label>
          <input type="text" class="form-control" name="lng" id="lng" placeholder="Ej: 2460500.00" readonly>
        </div>
      </div>
      <div class="alert alert-info"><i class="bi bi-cursor me-2"></i><strong>Tip:</strong> Haz clic en el mapa para capturar las coordenadas UTM del predio.</div>

      <div class="mb-3">
        <label class="form-label fw-bold">Tipo de Trámite</label>
        <input type="hidden" name="tipo_tramite_id" id="tipo_tramite_id">
        <div class="form-control bg-light fw-semibold" style="color:#198754;">
          <i class="bi bi-check-circle-fill me-2 text-success"></i><span id="label-tipo-tramite-form">—</span>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label">Fecha de Ingreso <span class="text-danger">*</span></label>
          <input type="date" class="form-control" name="fecha_ingreso" id="fechaIngreso">
        </div>
        <div class="col-md-6">
          <label class="form-label">Fecha de Entrega <span class="badge bg-success ms-1" style="font-size:.7rem;">10 días hábiles</span></label>
          <input type="date" class="form-control bg-light" name="fecha_entrega" id="fechaEntrega" readonly>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Nombre del Solicitante <span class="text-danger">*</span></label>
        <input type="text" name="solicitante" class="form-control" required placeholder="SOLO LETRAS MAYÚSCULAS"
          oninput="this.value=this.value.toUpperCase().replace(/[^A-ZÁÉÍÓÚÜÑ\s]/g,'')" style="text-transform:uppercase;">
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Teléfono <span class="text-danger">*</span></label>
          <input type="text" name="telefono" class="form-control" required maxlength="10" placeholder="Ej: 4491234567"
            oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Correo Electrónico <span class="text-muted" style="font-size:.75rem;">(opcional)</span></label>
          <input type="email" name="correo" class="form-control" placeholder="ejemplo@correo.com">
        </div>
      </div>

      <!-- DOCUMENTOS -->
      <section id="seccion-documentos" style="display:none;" class="tramite-box mb-4">
        <h5 class="mb-1" style="color:#7b0f2b;"><i class="bi bi-file-earmark-check me-2"></i>Documentos: <span id="titulo-tramite-seleccionado"></span></h5>
        <p class="text-muted mb-3" style="font-size:.83rem;"><i class="bi bi-info-circle me-1"></i>La carga es <strong>opcional</strong>. Si falta algún documento, justifique abajo.</p>
        <div class="alert alert-light border mb-4">
          <h6 class="alert-heading"><i class="bi bi-list-check me-2"></i>Documentación requerida:</h6>
          <ul id="lista-requisitos" class="list-group list-group-flush mb-0"></ul>
        </div>
        <div class="row">
          <div class="col-md-4 mb-4" id="grupo-ine" style="display:none;">
            <label class="form-label fw-bold"><i class="bi bi-person-vcard me-1"></i>INE o Pasaporte</label>
            <input type="file" class="form-control" name="ine" accept=".pdf,.jpg,.jpeg,.png">
            <small class="text-muted">PDF, JPG, PNG (Max. 5MB)</small>
          </div>
          <div class="col-md-4 mb-4" id="grupo-predial" style="display:none;">
            <label class="form-label fw-bold"><i class="bi bi-receipt me-1"></i>Boleta Predial</label>
            <input type="file" class="form-control" name="predial" accept=".pdf,.jpg,.jpeg,.png">
            <small class="text-muted">PDF, JPG, PNG (Max. 5MB)</small>
          </div>
          <div class="col-md-4 mb-4" id="grupo-escritura" style="display:none;">
            <label class="form-label fw-bold"><i class="bi bi-file-earmark-text me-1"></i>Escritura / Título</label>
            <input type="file" class="form-control" name="escritura" accept=".pdf,.jpg,.jpeg,.png">
            <small class="text-muted">PDF, JPG, PNG (Max. 5MB)</small>
          </div>
           <div class="col-md-4 mb-4" id="grupo-formato_constancia" style="display:none;">
             <label class="form-label fw-bold"><i class="bi bi-file-earmark-ruled me-1"></i>Formato de Constancia</label>
             <input type="file" class="form-control" name="formato_constancia" accept=".pdf,.jpg,.jpeg,.png">
             <small class="text-muted">PDF, JPG, PNG (Max. 5MB)</small>
           </div>
           <div class="col-md-4 mb-4" id="grupo-contrato_arrendamiento" style="display:none;">
             <label class="form-label fw-bold"><i class="bi bi-file-earmark-text me-1"></i>Contrato de Arrendamiento o Escritura</label>
             <input type="file" class="form-control" name="contrato_arrendamiento" accept=".pdf,.jpg,.jpeg,.png">
             <small class="text-muted">PDF, JPG, PNG (Max. 5MB)</small>
           </div>
           <div class="col-md-4 mb-4" id="grupo-memoria_descriptiva" style="display:none;">
             <label class="form-label fw-bold"><i class="bi bi-file-earmark-ruled me-1"></i>Memoria Descriptiva / Cálculo de Superficie</label>
             <input type="file" class="form-control" name="memoria_descriptiva" accept=".pdf,.jpg,.jpeg,.png">
             <small class="text-muted">PDF, JPG, PNG (Max. 5MB)</small>
           </div>
           <div class="col-md-4 mb-4" id="grupo-poder_notariado" style="display:none;">
             <label class="form-label fw-bold"><i class="bi bi-file-earmark-person me-1"></i>Poder Notariado <small class="text-muted">(opcional para empresas)</small></label>
             <input type="file" class="form-control" name="poder_notariado" accept=".pdf,.jpg,.jpeg,.png">
             <small class="text-muted">PDF, JPG, PNG (Max. 5MB)</small>
           </div>
           <div class="col-md-4 mb-4" id="grupo-acta_constitutiva" style="display:none;">
             <label class="form-label fw-bold"><i class="bi bi-building me-1"></i>Acta Constitutiva <small class="text-muted">(opcional para empresas)</small></label>
             <input type="file" class="form-control" name="acta_constitutiva" accept=".pdf,.jpg,.jpeg,.png">
             <small class="text-muted">PDF, JPG, PNG (Max. 5MB)</small>
           </div>
         </div>
        <div class="mt-2 p-3 border rounded" style="background:#fffbf0;">
          <label class="form-label fw-bold text-warning-emphasis"><i class="bi bi-chat-left-text me-1"></i>Comentarios / Justificación de documentos faltantes</label>
          <textarea name="comentario_sin_doc" class="form-control" rows="3" placeholder="Ej: El solicitante no presenta INE porque tramita con carta poder..."></textarea>
        </div>
      </section>

      <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><strong>NOTA:</strong> Para recoger su trámite, deberá presentar esta papeleta original.</div>
      <div class="text-end">
        <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-save me-2"></i>Guardar Trámite</button>
      </div>
    </form>
  </div>
</section>

<!-- MAPA -->
<section id="mapaa" class="tramite-box mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="m-0" style="color:#7b0f2b;"><i class="bi bi-map me-2"></i>Mapa Georreferenciado</h4>
    <button type="button" class="btn btn-sm btn-outline-primary" onclick="centrarMapa()">
      <i class="bi bi-geo-alt me-1"></i>Centrar municipio
    </button>
  </div>
  <div class="alert alert-info py-2" style="font-size:.85rem;">
    <i class="bi bi-cursor me-2"></i>Haz clic en el mapa para capturar las coordenadas UTM del predio automáticamente.
  </div>
  <div id="coords-display" class="mb-2 px-2 py-1 rounded border bg-light d-none" style="font-size:.82rem;font-family:monospace;">
    <i class="bi bi-pin-map me-1 text-danger"></i><span id="coords-texto">Sin ubicación seleccionada</span>
  </div>
  <div id="mapa"></div>
</section>

<!-- ================================================ -->
<!-- TRÁMITES EN CORRECCIÓN                           -->
<!-- ================================================ -->
<section id="correccion" class="tramite-box mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="m-0 text-primary"><i class="bi bi-pencil-square me-2"></i>Trámites en Corrección</h4>
    <span class="badge bg-primary fs-6"><?= $correccion_res->num_rows ?></span>
  </div>
  <div class="alert alert-warning py-2 mb-3" style="font-size:.85rem;">
    <i class="bi bi-info-circle-fill me-2"></i>
    Al guardar un trámite de corrección, el estatus regresa automáticamente a <strong>En revisión</strong> para que el verificador lo atienda.
  </div>
  <?php if($correccion_res->num_rows===0): ?>
  <div class="text-center text-muted py-4">
    <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
    <h5>¡Sin trámites en corrección!</h5>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table id="tablaCorreccion" class="table table-bordered table-hover align-middle">
      <thead style="background:var(--vino);color:white;">
        <tr>
          <th style="background:var(--vino);color:#fff;">Folio Ingreso</th>
          <th style="background:var(--vino);color:#fff;">Folio Salida</th>
          <th style="background:var(--vino);color:#fff;">Propietario</th>
          <th style="background:var(--vino);color:#fff;">Trámite</th>
          <th style="background:var(--vino);color:#fff;">Teléfono</th>
          <th style="background:var(--vino);color:#fff;">Indicaciones del Verificador</th>
          <th style="background:var(--vino);color:#fff;text-align:center;">Acción</th>
        </tr>
      </thead>
      <tbody>
      <?php while($t=$correccion_res->fetch_assoc()):
        $folio       = $t['folio_numero'].'/'.$t['folio_anio'];
        $folio_sal_c = !empty($t['folio_salida_numero']) ? str_pad($t['folio_salida_numero'],3,'0',STR_PAD_LEFT).'/'.$t['folio_salida_anio'] : '';
        $tnombre     = isset($t['tipo_tramite_nombre']) ? $t['tipo_tramite_nombre'] : '—';
        $ttelefono   = isset($t['telefono'])            ? $t['telefono']            : '—';
        $tsolicitante= isset($t['solicitante'])         ? $t['solicitante']         : '';
        $tdireccion  = isset($t['direccion'])           ? $t['direccion']           : '';
        $tlocalidad  = isset($t['localidad'])           ? $t['localidad']           : '';
        $tcorreo     = isset($t['correo'])              ? $t['correo']              : '';
        $tobs        = isset($t['observaciones'])       ? $t['observaciones']       : '';
        $tine        = isset($t['ine_archivo'])         ? $t['ine_archivo']         : '';
        $tescritura  = isset($t['escrituras_archivo'])  ? $t['escrituras_archivo']  : (isset($t['titulo_archivo']) ? $t['titulo_archivo'] : '');
        $tpredial    = isset($t['predial_archivo'])     ? $t['predial_archivo']     : '';
        $tformato    = isset($t['formato_constancia'])  ? $t['formato_constancia']  : '';
        $tcontrato   = isset($t['contrato_arrendamiento_archivo']) ? $t['contrato_arrendamiento_archivo'] : '';
        $tmemoria    = isset($t['memoria_descriptiva_archivo'])    ? $t['memoria_descriptiva_archivo']    : '';
        $tpoder      = isset($t['poder_notariado_archivo'])        ? $t['poder_notariado_archivo']        : '';
        $tacta       = isset($t['acta_constitutiva_archivo'])      ? $t['acta_constitutiva_archivo']      : '';
        $tfoto1      = isset($t['foto1_archivo'])       ? $t['foto1_archivo']       : '';
        $tfoto2      = isset($t['foto2_archivo'])       ? $t['foto2_archivo']       : '';
      ?>
        <tr>
          <td><span class="badge bg-primary"><?= htmlspecialchars($folio) ?></span></td>
          <td><?= $folio_sal_c ? '<span class="badge bg-success">'.htmlspecialchars($folio_sal_c).'</span>' : '<span class="text-muted">—</span>' ?></td>
          <td><?= htmlspecialchars($t['propietario']) ?></td>
          <td><?= htmlspecialchars($tnombre) ?></td>
          <td><?= htmlspecialchars($ttelefono) ?></td>
          <td><small class="text-muted" style="white-space:pre-line;"><?= !empty($tobs) ? nl2br(htmlspecialchars($tobs)) : '<em>Sin indicaciones</em>' ?></small></td>
          <td class="text-center">
            <button class="btn btn-sm btn-primary btn-editar-correccion"
              data-folio="<?= htmlspecialchars($folio) ?>"
              data-propietario="<?= htmlspecialchars($t['propietario']) ?>"
              data-solicitante="<?= htmlspecialchars($tsolicitante) ?>"
              data-direccion="<?= htmlspecialchars($tdireccion) ?>"
              data-localidad="<?= htmlspecialchars($tlocalidad) ?>"
              data-tramite="<?= htmlspecialchars($tnombre) ?>"
              data-fecha="<?= htmlspecialchars(date('d/m/Y',strtotime($t['fecha_ingreso']))) ?>"
              data-telefono="<?= htmlspecialchars($ttelefono) ?>"
              data-correo="<?= htmlspecialchars($tcorreo) ?>"
              data-observaciones="<?= htmlspecialchars($tobs) ?>"
              data-ine="<?= htmlspecialchars($tine) ?>"
              data-escritura="<?= htmlspecialchars($tescritura) ?>"
              data-predial="<?= htmlspecialchars($tpredial) ?>"
              data-formato="<?= htmlspecialchars($tformato) ?>"
              data-contrato="<?= htmlspecialchars($tcontrato) ?>"
              data-memoria="<?= htmlspecialchars($tmemoria) ?>"
              data-poder="<?= htmlspecialchars($tpoder) ?>"
              data-acta="<?= htmlspecialchars($tacta) ?>"
              data-foto1="<?= htmlspecialchars($tfoto1) ?>"
              data-foto2="<?= htmlspecialchars($tfoto2) ?>"
              data-tipo-tramite-id="<?= (int)$t['tipo_tramite_id'] ?>"
              data-bs-toggle="modal" data-bs-target="#modalEditar">
              <i class="bi bi-pencil me-1"></i>Actualizar
            </button>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<!-- ================================================ -->
<!-- SEGUIMIENTO DE TODOS LOS TRÁMITES                -->
<!-- ================================================ -->
<section id="seguimiento" class="tramite-box mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="m-0" style="color:#7b0f2b;"><i class="bi bi-search me-2"></i>Seguimiento de Trámites</h4>
    <span class="badge bg-secondary fs-6"><?= $seg_res->num_rows ?> trámite(s)</span>
  </div>

  <form method="GET" class="mb-4">
    <a name="seguimiento"></a>
    <div class="row g-3 align-items-end">
      <div class="col-12 col-sm-6 col-lg-3">
        <label class="form-label fw-semibold"><i class="bi bi-hash me-1"></i>Folio</label>
        <input type="text" name="folio" class="form-control" placeholder="001/2026" value="<?= htmlspecialchars(isset($_GET['folio']) ? $_GET['folio'] : '') ?>">
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <label class="form-label fw-semibold"><i class="bi bi-person me-1"></i>Nombre / Propietario</label>
        <input type="text" name="nombre" class="form-control" placeholder="Buscar por nombre" value="<?= htmlspecialchars(isset($_GET['nombre']) ? $_GET['nombre'] : '') ?>">
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <label class="form-label fw-semibold"><i class="bi bi-flag me-1"></i>Estatus</label>
        <select name="estatus" class="form-select">
          <option value="">Todos</option>
          <?php foreach(['En revisión','Aprobado por Verificador','Aprobado','Rechazado','En corrección'] as $es): 
            $sel = (isset($_GET['estatus']) && $_GET['estatus'] === $es) ? 'selected' : '';
          ?>
          <option value="<?= $es ?>" <?= $sel ?>><?= $es ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-sm-6 col-lg-3 d-flex gap-2 align-items-end">
        <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Buscar</button>
        <a href="DashVentanilla.php#seguimiento" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
      </div>
    </div>
  </form>

  <div class="table-responsive">
    <table id="tablaSeguimiento" class="table table-bordered table-hover align-middle">
      <thead style="background:var(--vino);color:white;">
        <tr>
          <th style="background:var(--vino);color:#fff;">Folio Ingreso</th>
          <th style="background:var(--vino);color:#fff;">Folio Salida</th>
          <th style="background:var(--vino);color:#fff;">Propietario</th>
          <th style="background:var(--vino);color:#fff;">Trámite</th>
          <th style="background:var(--vino);color:#fff;">Fecha</th>
          <th style="background:var(--vino);color:#fff;">Estatus</th>
          <th style="background:var(--vino);color:#fff;text-align:center;">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if($seg_res->num_rows===0): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No se encontraron trámites.</td></tr>
      <?php else: while($t=$seg_res->fetch_assoc()):
        $folio       = $t['folio_numero'].'/'.$t['folio_anio'];
        $folio_sal_s = !empty($t['folio_salida_numero']) ? str_pad($t['folio_salida_numero'],3,'0',STR_PAD_LEFT).'/'.$t['folio_salida_anio'] : '';
        $tnombre     = isset($t['tipo_tramite_nombre']) ? $t['tipo_tramite_nombre'] : '—';
        $ttelefono   = isset($t['telefono'])            ? $t['telefono']            : '';
        $tsolicitante= isset($t['solicitante'])         ? $t['solicitante']         : '';
        $tdireccion  = isset($t['direccion'])           ? $t['direccion']           : '';
        $tlocalidad  = isset($t['localidad'])           ? $t['localidad']           : '';
        $tcorreo     = isset($t['correo'])              ? $t['correo']              : '';
        $tobs        = isset($t['observaciones'])       ? $t['observaciones']       : '';
        $tine        = isset($t['ine_archivo'])         ? $t['ine_archivo']         : '';
        $tescritura  = isset($t['escrituras_archivo'])  ? $t['escrituras_archivo']  : (isset($t['titulo_archivo']) ? $t['titulo_archivo'] : '');
        $tpredial    = isset($t['predial_archivo'])     ? $t['predial_archivo']     : '';
        $tformato    = isset($t['formato_constancia'])  ? $t['formato_constancia']  : '';
        $tfoto1      = isset($t['foto1_archivo'])       ? $t['foto1_archivo']       : '';
        $tfoto2      = isset($t['foto2_archivo'])       ? $t['foto2_archivo']       : '';
        $estatus     = $t['estatus'];
        $fecha       = date('d/m/Y', strtotime($t['created_at']));
        if ($estatus === 'En revisión')              $badge = 'bg-warning text-dark';
        elseif ($estatus === 'Aprobado por Verificador') $badge = 'bg-info text-dark';
        elseif ($estatus === 'Aprobado')             $badge = 'bg-success';
        elseif ($estatus === 'Rechazado')            $badge = 'bg-danger';
        elseif ($estatus === 'En corrección')        $badge = 'bg-primary';
        else                                         $badge = 'bg-secondary';
      ?>
        <tr>
          <td><span class="badge bg-primary"><?= htmlspecialchars($folio) ?></span></td>
          <td><?= $folio_sal_s ? '<span class="badge bg-success">'.htmlspecialchars($folio_sal_s).'</span>' : '<span class="text-muted">—</span>' ?></td>
          <td><?= htmlspecialchars($t['propietario']) ?></td>
          <td><?= htmlspecialchars($tnombre) ?></td>
          <td><?= htmlspecialchars($fecha) ?></td>
          <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($estatus) ?></span></td>
          <td class="text-center">
            <a href="ficha.php?folio=<?= urlencode($folio) ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Ver ficha completa">
              <i class="bi bi-eye"></i> Ver
            </a>
          </td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</section>


<!-- ================================================ -->
<!-- TRÁMITES APROBADOS (REIMPRESIÓN)                -->
<!-- ================================================ -->
<section id="tramites-aprobados" class="tramite-box mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-success m-0"><i class="bi bi-check-circle-fill"></i> Trámites Aprobados — Imprimir Constancia</h4>
        <span class="badge bg-success fs-6"><?= $tramites_firmados_res->num_rows ?> aprobados</span>
    </div>
    <p class="text-muted small mb-3"><i class="bi bi-info-circle"></i> Haz clic en <strong>Imprimir</strong> para abrir la constancia lista para firmar y entregar al solicitante.</p>

    <div class="table-responsive">
        <table id="tablaAprobadosSec" class="table table-bordered table-hover">
            <thead style="background: #1a6e35 !important; color: white !important;">
                <tr>
                    <th style="background:#1a6e35;color:#fff;">Folio Ingreso</th>
                    <th style="background:#1a6e35;color:#fff;">Folio Salida</th>
                    <th style="background:#1a6e35;color:#fff;">Tipo de Trámite</th>
                    <th style="background:#1a6e35;color:#fff;">Propietario</th>
                    <th style="background:#1a6e35;color:#fff;">Solicitante</th>
                    <th style="background:#1a6e35;color:#fff;">Dirección</th>
                    <th style="background:#1a6e35;color:#fff;">Número Asignado</th>
                    <th style="background:#1a6e35;color:#fff;">Fecha Aprobación</th>
                    <th style="background:#1a6e35;color:#fff; text-align:center;">Constancia</th>
                </tr>
            </thead>
            <tbody>
                <?php if($tramites_firmados_res->num_rows === 0): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No hay trámites aprobados aún.
                    </td>
                </tr>
                <?php else: ?>
                <?php while($tr = $tramites_firmados_res->fetch_assoc()):
                    $folio_ing = htmlspecialchars($tr['folio_formateado']);
                    $folio_sal = !empty($tr['folio_salida_numero'])
                        ? str_pad($tr['folio_salida_numero'], 3, '0', STR_PAD_LEFT) . '/' . $tr['folio_salida_anio']
                        : '';
                    $folio_url = urlencode($tr['folio_formateado']);
                ?>
                <tr>
                    <td><span class="badge bg-success"><?php echo $folio_ing; ?></span></td>
                    <td><?php echo $folio_sal ? '<span class="badge bg-primary">' . htmlspecialchars($folio_sal) . '</span>' : '<span class="text-muted">—</span>'; ?></td>
                    <td><?php echo htmlspecialchars($tr['tipo_tramite_nombre'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($tr['propietario']); ?></td>
                    <td><?php echo htmlspecialchars(trim(($tr['solicitante_nombre'] ?? '') . ' ' . ($tr['solicitante_apellidos'] ?? ''))); ?></td>
                    <td><?php echo htmlspecialchars($tr['direccion'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($tr['numero_asignado'] ?? '—'); ?></td>
                    <td><?php echo $tr['fecha_aprobacion'] ? date('d/m/Y', strtotime($tr['fecha_aprobacion'])) : '—'; ?></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-success btn-reimprimir"
                            data-folio="<?php echo $folio_ing; ?>"
                            data-folio-salida-numero="<?php echo $tr['folio_salida_numero'] ?? ''; ?>"
                            data-folio-salida-anio="<?php echo $tr['folio_salida_anio'] ?? ''; ?>"
                            data-bs-toggle="modal" data-bs-target="#modalConstanciaSec"
                            title="Reimprimir constancia">
                            <i class="bi bi-printer me-1"></i>Imprimir
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>





<!-- ===================== SECCIÓN REPORTE ===================== -->
<section id="reporte" class="tramite-box mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="text-primary m-0"><i class="bi bi-bar-chart-line me-2"></i>Reporte de Trámites Realizados</h4>
    <form method="GET" class="d-flex align-items-center gap-2 mb-0">
      <a href="#reporte"></a>
      <label class="fw-semibold mb-0">Año:</label>
      <select name="anio_reporte" class="form-select form-select-sm" style="width:100px;" onchange="this.form.submit()">
        <?php foreach($anios_disponibles as $a): ?>
        <option value="<?= $a ?>" <?= $a == $anio_filtro ? 'selected' : '' ?>><?= $a ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php
  $tot_año = $gran_total;
  $apr_año = 0; $rev_año = 0; $rec_año = 0; $cor_año = 0;
  foreach ($datos_mes as $d) {
    $apr_año += $d['aprobados'];
    $rev_año += $d['en_revision'];
    $rec_año += $d['rechazados'];
    $cor_año += $d['en_correccion'];
  }
  ?>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm border-start border-primary border-4 h-100">
        <div class="card-body text-center py-3">
          <div class="fs-1 fw-bold text-primary"><?= $tot_año ?></div>
          <div class="text-muted small">Total <?= $anio_filtro ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm border-start border-success border-4 h-100">
        <div class="card-body text-center py-3">
          <div class="fs-1 fw-bold text-success"><?= $apr_año ?></div>
          <div class="text-muted small">Aprobados</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm border-start border-warning border-4 h-100">
        <div class="card-body text-center py-3">
          <div class="fs-1 fw-bold text-warning"><?= $rev_año ?></div>
          <div class="text-muted small">En Revisión</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm border-start border-danger border-4 h-100">
        <div class="card-body text-center py-3">
          <div class="fs-1 fw-bold text-danger"><?= $rec_año ?></div>
          <div class="text-muted small">Rechazados</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
      <canvas id="chartReporteMesSec" style="max-height:280px;"></canvas>
    </div>
  </div>

  <?php
  $meses_nombre = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
                   7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
  $sum_total=0; $sum_apr=0; $sum_rev=0; $sum_cor=0; $sum_rec=0;
  ?>
  <h6 class="fw-bold text-secondary mb-2"><i class="bi bi-calendar3 me-1"></i>Trámites por Mes — <?= $anio_filtro ?></h6>
  <div class="table-responsive mb-4">
    <table class="table table-bordered table-hover align-middle" id="tablaReporteMesSec">
      <thead>
        <tr>
          <th style="background:#7b0f2b;color:#fff;">Mes</th>
          <th style="background:#7b0f2b;color:#fff;text-align:center;">Total</th>
          <th style="background:#1a6e35;color:#fff;text-align:center;">Aprobados</th>
          <th style="background:#856404;color:#fff;text-align:center;">En Revisión</th>
          <th style="background:#0d6efd;color:#fff;text-align:center;">En Corrección</th>
          <th style="background:#842029;color:#fff;text-align:center;">Rechazados</th>
        </tr>
      </thead>
      <tbody>
        <?php for($m=1; $m<=12; $m++):
          $d = isset($datos_mes[$m]) ? $datos_mes[$m] : ['total'=>0,'aprobados'=>0,'en_revision'=>0,'en_correccion'=>0,'rechazados'=>0];
          $sum_total += $d['total'];
          $sum_apr   += $d['aprobados'];
          $sum_rev   += $d['en_revision'];
          $sum_cor   += $d['en_correccion'];
          $sum_rec   += $d['rechazados'];
        ?>
        <tr <?= $d['total'] == 0 ? 'class="text-muted"' : '' ?>>
          <td class="fw-semibold"><?= $meses_nombre[$m] ?></td>
          <td class="text-center">
            <?php if($d['total'] > 0): ?>
            <span class="badge bg-primary fs-6"><?= $d['total'] ?></span>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td class="text-center"><?= $d['aprobados'] > 0 ? '<span class="badge bg-success">'.$d['aprobados'].'</span>' : '—' ?></td>
          <td class="text-center"><?= $d['en_revision'] > 0 ? '<span class="badge bg-warning text-dark">'.$d['en_revision'].'</span>' : '—' ?></td>
          <td class="text-center"><?= $d['en_correccion'] > 0 ? '<span class="badge bg-info text-dark">'.$d['en_correccion'].'</span>' : '—' ?></td>
          <td class="text-center"><?= $d['rechazados'] > 0 ? '<span class="badge bg-danger">'.$d['rechazados'].'</span>' : '—' ?></td>
        </tr>
        <?php endfor; ?>
      </tbody>
      <tfoot>
        <tr style="background:#f0f0f0;font-weight:700;">
          <td>TOTAL <?= $anio_filtro ?></td>
          <td class="text-center"><span class="badge bg-primary fs-6"><?= $sum_total ?></span></td>
          <td class="text-center"><span class="badge bg-success"><?= $sum_apr ?></span></td>
          <td class="text-center"><span class="badge bg-warning text-dark"><?= $sum_rev ?></span></td>
          <td class="text-center"><span class="badge bg-info text-dark"><?= $sum_cor ?></span></td>
          <td class="text-center"><span class="badge bg-danger"><?= $sum_rec ?></span></td>
        </tr>
        <tr style="background:#e8e8e8;font-weight:700;">
          <td colspan="2">TOTAL HISTÓRICO (todos los años)</td>
          <td colspan="4" class="text-center"><span class="badge bg-dark fs-6"><?= $total_global ?> trámites en total</span></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <?php if (!empty($datos_tipo)): ?>
  <h6 class="fw-bold text-secondary mb-2"><i class="bi bi-list-task me-1"></i>Trámites por Tipo — <?= $anio_filtro ?></h6>
  <div class="table-responsive mb-3">
    <table class="table table-bordered table-hover align-middle" id="tablaReporteTipoSec">
      <thead>
        <tr>
          <th style="background:#7b0f2b;color:#fff;">Tipo de Trámite</th>
          <th style="background:#7b0f2b;color:#fff;text-align:center;">Total</th>
          <th style="background:#1a6e35;color:#fff;text-align:center;">Aprobados</th>
          <th style="background:#842029;color:#fff;text-align:center;">Rechazados</th>
          <th style="background:#7b0f2b;color:#fff;text-align:center;">% del año</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($datos_tipo as $dt):
          $pct = $sum_total > 0 ? round(($dt['total'] / $sum_total) * 100, 1) : 0;
        ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($dt['tipo'] ? $dt['tipo'] : 'Sin tipo') ?></td>
          <td class="text-center"><span class="badge bg-primary"><?= $dt['total'] ?></span></td>
          <td class="text-center"><span class="badge bg-success"><?= $dt['aprobados'] ?></span></td>
          <td class="text-center"><span class="badge bg-danger"><?= $dt['rechazados'] ?></span></td>
          <td class="text-center">
            <div class="d-flex align-items-center gap-2">
              <div class="progress flex-fill" style="height:14px;">
                <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
              </div>
              <small class="fw-bold"><?= $pct ?>%</small>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div class="text-end">
    <button onclick="imprimirReporteSec()" class="btn btn-outline-primary">
      <i class="bi bi-printer me-1"></i>Imprimir Reporte
    </button>
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

  <?php $csrf_cc = generarCSRF(); ?>
  <form id="formConfigConstancia">
    <input type="hidden" name="csrf_token" value="<?= $csrf_cc ?>">

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
    $regs = [
      1 => $cfg['constancia_reglamento_1'] ?? 'En inmuebles construidos deberán colocarse en el exterior, al frente de la construcción junto al acceso principal;',
      2 => $cfg['constancia_reglamento_2'] ?? 'Los números oficiales en ningún caso deberán ser pintados sobre muros, bloques, columnas y/o en elementos de fácil destrucción;',
      3 => $cfg['constancia_reglamento_3'] ?? 'Deberán además ser de tipo de fuente legible y permitir una fácil lectura a un mínimo de veinte metros;',
      4 => $cfg['constancia_reglamento_4'] ?? 'Las placas de numeración deberán colocarse en una altura mínima de dos metros con cincuenta centímetros a partir del nivel de la banqueta.',
    ];
    $romanos = ['I','II','III','IV'];
    foreach ($regs as $i => $texto): ?>
    <div class="mb-3">
      <label class="form-label fw-semibold text-secondary small">
        Inciso <?= $romanos[$i-1] ?>
      </label>
      <textarea class="form-control" name="config[constancia_reglamento_<?= $i ?>]"
                rows="2" style="font-size:.9rem;"><?= htmlspecialchars($texto) ?></textarea>
    </div>
    <?php endforeach; ?>

    <div class="d-flex align-items-center gap-3 mt-3">
      <button type="button" class="btn btn-success px-4" onclick="guardarConfigConstancia()">
        <i class="bi bi-floppy me-1"></i> Guardar cambios
      </button>
      <span id="msg-config-constancia" class="fw-semibold" style="font-size:.9rem;"></span>
    </div>
  </form>
</section>

</div><!-- /content -->

<!-- ===================== MODAL EDITAR CORRECCIÓN ===================== -->
<div class="modal fade" id="modalEditar" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content shadow">
      <div class="modal-header text-white" style="background:var(--vino);">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Actualizar Trámite en Corrección</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="formVentanilla" enctype="multipart/form-data">
          <?php $csrf2 = generarCSRF(); ?>
          <input type="hidden" name="csrf_token" value="<?= $csrf2 ?>">
          <input type="hidden" name="folio" id="sec_folio_hidden">
          <input type="hidden" name="tipo_tramite_id" id="sec_tipo_tramite_id">
          <input type="hidden" name="estatus" value="En revisión">
          <input type="hidden" name="verificador_nombre" value="VENTANILLA">

          <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-3">
            <i class="bi bi-info-circle-fill fs-5"></i>
            <span>Al guardar, el trámite regresará a <strong>En revisión</strong> para que el verificador lo revise.</span>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <p class="mb-1"><strong>Folio:</strong> <span id="sec_folio" class="badge bg-primary fs-6"></span></p>
              <p class="mb-1"><strong>Propietario:</strong> <span id="sec_propietario"></span></p>
              <p class="mb-1"><strong>Solicitante:</strong> <span id="sec_solicitante"></span></p>
              <p class="mb-1"><strong>Dirección:</strong> <span id="sec_direccion"></span></p>
            </div>
            <div class="col-md-6">
              <p class="mb-1"><strong>Trámite:</strong> <span id="sec_tramite"></span></p>
              <p class="mb-1"><strong>Fecha ingreso:</strong> <span id="sec_fecha"></span></p>
              <p class="mb-1"><strong>Teléfono:</strong> <span id="sec_telefono"></span></p>
              <p class="mb-1"><strong>Correo:</strong> <span id="sec_correo"></span></p>
            </div>
          </div>

          <div class="card border-warning mb-3">
            <div class="card-header bg-warning text-dark fw-bold">
              <i class="bi bi-exclamation-triangle me-2"></i>Indicaciones del Verificador
            </div>
            <div class="card-body">
              <p id="sec_observaciones" class="mb-0 small" style="white-space:pre-line;"></p>
            </div>
          </div>

          <div class="mb-3">
            <h6 class="fw-bold text-primary"><i class="bi bi-paperclip me-1"></i>Documentos actuales</h6>
            <div class="list-group mb-2">
              <a id="sec_doc_ine" href="#" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="display:none!important;">
                <span><i class="bi bi-file-earmark-person me-2 text-primary"></i>INE / Identificación</span>
                <span class="badge bg-primary">Ver</span>
              </a>
              <a id="sec_doc_escritura" href="#" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="display:none!important;">
                <span><i class="bi bi-file-earmark-text me-2 text-primary"></i>Escritura / Título</span>
                <span class="badge bg-primary">Ver</span>
              </a>
              <a id="sec_doc_predial" href="#" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="display:none!important;">
                <span><i class="bi bi-file-earmark-text me-2 text-primary"></i>Boleta Predial</span>
                <span class="badge bg-primary">Ver</span>
              </a>
               <a id="sec_doc_formato" href="#" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="display:none!important;">
                 <span><i class="bi bi-file-earmark-ruled me-2 text-primary"></i>Formato de Constancia</span>
                 <span class="badge bg-primary">Ver</span>
               </a>
               <a id="sec_doc_contrato_arrendamiento" href="#" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="display:none!important;">
                 <span><i class="bi bi-file-earmark-text me-2 text-primary"></i>Contrato de Arrendamiento o Escritura</span>
                 <span class="badge bg-primary">Ver</span>
               </a>
               <a id="sec_doc_memoria_descriptiva" href="#" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="display:none!important;">
                 <span><i class="bi bi-file-earmark-ruled me-2 text-primary"></i>Memoria Descriptiva / Cálculo de Superficie</span>
                 <span class="badge bg-primary">Ver</span>
               </a>
               <a id="sec_doc_poder_notariado" href="#" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="display:none!important;">
                 <span><i class="bi bi-file-earmark-person me-2 text-primary"></i>Poder Notariado <small class="text-muted">(opcional para empresas)</small></span>
                 <span class="badge bg-primary">Ver</span>
               </a>
               <a id="sec_doc_acta_constitutiva" href="#" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="display:none!important;">
                 <span><i class="bi bi-building me-2 text-primary"></i>Acta Constitutiva <small class="text-muted">(opcional para empresas)</small></span>
                 <span class="badge bg-primary">Ver</span>
               </a>
             </div>
            <p id="sec_sin_docs" class="text-muted small fst-italic" style="display:none;">Sin documentos cargados.</p>
          </div>

          <div class="card border-primary mb-3">
            <div class="card-header bg-primary text-white fw-bold">
              <i class="bi bi-upload me-2"></i>Subir / Reemplazar Documentos
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold small">INE / Identificación</label>
                  <input type="file" name="ine" class="form-control form-control-sm" accept="image/*,.pdf">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold small">Escritura / Título</label>
                  <input type="file" name="escritura" class="form-control form-control-sm" accept="image/*,.pdf">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold small">Boleta Predial</label>
                  <input type="file" name="predial" class="form-control form-control-sm" accept="image/*,.pdf">
                </div>
                 <div class="col-md-6">
                   <label class="form-label fw-semibold small">Formato de Constancia</label>
                   <input type="file" name="formato_constancia" class="form-control form-control-sm" accept="image/*,.pdf">
                 </div>
                 <div class="col-md-6">
                   <label class="form-label fw-semibold small">Contrato de Arrendamiento o Escritura</label>
                   <input type="file" name="contrato_arrendamiento" class="form-control form-control-sm" accept="image/*,.pdf">
                 </div>
                 <div class="col-md-6">
                   <label class="form-label fw-semibold small">Memoria Descriptiva / Cálculo de Superficie</label>
                   <input type="file" name="memoria_descriptiva" class="form-control form-control-sm" accept="image/*,.pdf">
                 </div>
                 <div class="col-md-6" id="sec_grupo_poder_notariado" style="display:none;">
                   <label class="form-label fw-semibold small">Poder Notariado <small class="text-muted">(opcional para empresas)</small></label>
                   <input type="file" name="poder_notariado" class="form-control form-control-sm" accept="image/*,.pdf">
                 </div>
                 <div class="col-md-6" id="sec_grupo_acta_constitutiva" style="display:none;">
                   <label class="form-label fw-semibold small">Acta Constitutiva <small class="text-muted">(opcional para empresas)</small></label>
                   <input type="file" name="acta_constitutiva" class="form-control form-control-sm" accept="image/*,.pdf">
                 </div>
               </div>
             </div>
           </div>

          <div class="card border-secondary mb-3">
            <div class="card-header bg-secondary text-white fw-bold">
              <i class="bi bi-camera me-2"></i>Fotografías del Inmueble
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold small">Fotografía 1</label>
                  <div id="sec_prev1_container" class="mb-2" style="display:none;">
                    <img id="sec_prev1" src="" class="img-thumbnail" style="max-height:120px;cursor:pointer;" onclick="window.open(this.src,'_blank')">
                  </div>
                  <input type="file" name="foto1" id="sec_input_foto1" class="form-control form-control-sm" accept="image/jpeg,image/png">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold small">Fotografía 2</label>
                  <div id="sec_prev2_container" class="mb-2" style="display:none;">
                    <img id="sec_prev2" src="" class="img-thumbnail" style="max-height:120px;cursor:pointer;" onclick="window.open(this.src,'_blank')">
                  </div>
                  <input type="file" name="foto2" id="sec_input_foto2" class="form-control form-control-sm" accept="image/jpeg,image/png">
                </div>
              </div>
            </div>
          </div>

          <div class="mb-2">
            <label class="fw-semibold small"><i class="bi bi-chat-left-text me-1"></i>Nota (opcional):</label>
            <textarea name="observaciones" id="sec_nota" class="form-control form-control-sm" rows="2"
              placeholder="Ej: Se entregaron documentos corregidos el día de hoy..."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <a id="sec_btn_ficha" href="#" target="_blank" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-printer me-1"></i>Ver Ficha
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="submit" form="formVentanilla" class="btn btn-primary">
          <i class="bi bi-save me-1"></i>Guardar y Regresar a Revisión
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL NOTIFICACIÓN -->
<div class="modal fade" id="notifModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow border-0">
      <div class="modal-header" style="background:var(--vino);color:white;">
        <h5 class="modal-title"><i class="bi bi-bell me-2"></i>Notificar al Ciudadano</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-2" id="notif-desc"></p>
        <div id="notif-preview" class="alert alert-light border mb-3" style="display:none;">
          <p class="mb-1 fw-bold small text-secondary"><i class="bi bi-chat-quote me-1"></i>Mensaje:</p>
          <p id="notif-texto" class="mb-0 small" style="white-space:pre-line;"></p>
        </div>
        <a id="notif-wa" href="#" target="_blank" rel="noopener"
           class="d-flex align-items-center gap-3 p-3 rounded border mb-2 text-decoration-none text-dark"
           style="border-color:#25D366!important;background:rgba(37,211,102,.06);">
          <span style="font-size:2rem;">💬</span>
          <div>
            <div class="fw-bold" style="color:#25D366;">Enviar por WhatsApp</div>
            <div class="text-muted notif-sub small">Abre WhatsApp con el mensaje listo</div>
          </div>
        </a>
        <a id="notif-gm" href="#" target="_blank" rel="noopener"
           class="d-flex align-items-center gap-3 p-3 rounded border mb-2 text-decoration-none text-dark"
           style="border-color:#EA4335!important;background:rgba(234,67,53,.06);">
          <span style="font-size:2rem;">📧</span>
          <div>
            <div class="fw-bold" style="color:#EA4335;">Enviar por Correo</div>
            <div class="text-muted notif-sub small">Abre tu cliente de correo listo</div>
          </div>
        </a>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar sin notificar</button>
      </div>
    </div>
  </div>
</div>
<!-- MODAL PARA CARGAR POR FOLIO EN VENTANILLA -->
<div class="modal fade" id="modalCargarPorFolio" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-file-text me-2"></i>Cargar trámite por Folio de Salida</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Folio de Salida del trámite anterior (ej: 001/2026)</label>
                <input type="text" id="folio_cargar" class="form-control" placeholder="001/2026">
                <small class="text-muted mt-1 d-block">Ingresa el folio de salida que fue asignado al trámite anterior</small>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="btnConfirmarCargarFolio">Cargar datos</button>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.7.5/proj4.js"></script>
<script src="js/dashVentanilla.js"></script>


</script>
