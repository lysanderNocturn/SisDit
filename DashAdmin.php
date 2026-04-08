<?php
require "seguridad.php";
require_once "php/funciones_seguridad.php";

// Solo administradores pueden acceder
if(!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador'){
    header("Location: acceso.php?error=no_autorizado");
    exit();
}

require_once "php/db.php";

// Obtener estadísticas generales
$stats_tramites = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estatus = 'En revisión' THEN 1 ELSE 0 END) as en_revision,
    SUM(CASE WHEN estatus = 'Aprobado' THEN 1 ELSE 0 END) as aprobados,
    SUM(CASE WHEN estatus = 'Rechazado' THEN 1 ELSE 0 END) as rechazados
    FROM tramites")->fetch_assoc();

$stats_usuarios = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
    SUM(CASE WHEN rol = 'Administrador' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN rol = 'Verificador' THEN 1 ELSE 0 END) as verificadores,
    SUM(CASE WHEN rol = 'Ventanilla' THEN 1 ELSE 0 END) as ventanillas,
    SUM(CASE WHEN rol = 'Usuario' THEN 1 ELSE 0 END) as usuarios
    FROM usuarios")->fetch_assoc();

// Obtener lista de usuarios
$usuarios_query = $conn->query("SELECT * FROM usuarios ORDER BY fecha_registro DESC");

// Obtener trámites aprobados
$tramites_aprobados = $conn->query("
    SELECT t.*, tt.nombre AS tipo_tramite_nombre,
           u.nombre AS solicitante_nombre, u.apellidos AS solicitante_apellidos,
           CONCAT(LPAD(t.folio_numero,3,'0'),'/',t.folio_anio) AS folio
    FROM tramites t
    LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
    LEFT JOIN usuarios u ON t.usuario_creador_id = u.id
    WHERE t.estatus = 'Aprobado'
    ORDER BY t.fecha_aprobacion DESC
");

// Obtener logs recientes
$logs_query = $conn->query("SELECT l.*, u.nombre, u.apellidos 
    FROM logs_actividad l 
    LEFT JOIN usuarios u ON l.usuario_id = u.id 
    ORDER BY l.fecha DESC 
    LIMIT 50");

// Solicitudes de registro pendientes
$solicitudes_query = $conn->query("SELECT * FROM solicitudes_registro ORDER BY FIELD(estado,'Pendiente','Aprobado','Rechazado'), fecha_solicitud DESC");
$total_pendientes  = $conn->query("SELECT COUNT(*) as c FROM solicitudes_registro WHERE estado='Pendiente'")->fetch_assoc()['c'];

// ── REPORTE: trámites por mes/año y tipo ──
$anio_filtro = isset($_GET['anio_reporte']) ? (int)$_GET['anio_reporte'] : (int)date('Y');

// Años disponibles en la BD
$anios_res = $conn->query("SELECT DISTINCT folio_anio FROM tramites ORDER BY folio_anio DESC");
$anios_disponibles = [];
while ($a = $anios_res->fetch_assoc()) $anios_disponibles[] = $a['folio_anio'];
if (empty($anios_disponibles)) $anios_disponibles[] = date('Y');

// Totales por mes (todos los tipos) para el año seleccionado
$reporte_mes = $conn->query("
    SELECT
        MONTH(fecha_ingreso) AS mes,
        COUNT(*) AS total,
        SUM(CASE WHEN estatus = 'Aprobado' THEN 1 ELSE 0 END) AS aprobados,
        SUM(CASE WHEN estatus = 'En revisión' THEN 1 ELSE 0 END) AS en_revision,
        SUM(CASE WHEN estatus = 'En corrección' THEN 1 ELSE 0 END) AS en_correccion,
        SUM(CASE WHEN estatus = 'Rechazado' THEN 1 ELSE 0 END) AS rechazados
    FROM tramites
    WHERE folio_anio = $anio_filtro
    GROUP BY MONTH(fecha_ingreso)
    ORDER BY MONTH(fecha_ingreso)
");
$datos_mes = [];
while ($r = $reporte_mes->fetch_assoc()) $datos_mes[(int)$r['mes']] = $r;

// Totales por tipo de trámite para el año seleccionado
$reporte_tipo = $conn->query("
    SELECT tt.nombre AS tipo,
           COUNT(*) AS total,
           SUM(CASE WHEN t.estatus = 'Aprobado' THEN 1 ELSE 0 END) AS aprobados,
           SUM(CASE WHEN t.estatus = 'Rechazado' THEN 1 ELSE 0 END) AS rechazados
    FROM tramites t
    LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
    WHERE t.folio_anio = $anio_filtro
    GROUP BY tt.nombre
    ORDER BY total DESC
");
$datos_tipo = [];
while ($r = $reporte_tipo->fetch_assoc()) $datos_tipo[] = $r;

// Gran total del año
$gran_total = $conn->query("SELECT COUNT(*) as c FROM tramites WHERE folio_anio = $anio_filtro")->fetch_assoc()['c'];
$total_global = $conn->query("SELECT COUNT(*) as c FROM tramites")->fetch_assoc()['c'];


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

<!-- CSS PROPIO -->
<link rel="stylesheet" href="./css/style.css?v=1">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Font Awesome 6 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

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
</style>
</head>

<body>

<!-- NAVBAR MÓVIL -->
<nav class="navbar navbar-dark bg-dark d-lg-none">
    <div class="container-fluid">
        <span class="navbar-brand">Panel Administrador</span>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuMovil">
            <span class="navbar-toggler-icon"></span>
        </button>
    </div>

    <div class="collapse navbar-collapse" id="menuMovil">
        <ul class="navbar-nav p-3">
            <li class="nav-item"><a class="nav-link" href="#inicio">Inicio</a></li>
            <li class="nav-item"><a class="nav-link" href="#estadisticas">Estadísticas</a></li>
            <li class="nav-item"><a class="nav-link" href="#usuarios">Gestión de Usuarios</a></li>
            <li class="nav-item"><a class="nav-link" href="#logs">Logs de Actividad</a></li>
            <li class="nav-item">
                <a class="nav-link" href="#solicitudes">
                    <i class="bi bi-person-check me-1"></i> Solicitudes
                    <?php if($total_pendientes > 0): ?><span class="badge bg-warning text-dark"><?= $total_pendientes ?></span><?php endif; ?>
                </a>
            </li>
            <li class="nav-item"><a class="nav-link" href="#reporte"><i class="bi bi-bar-chart-line me-1"></i> Reporte</a></li>
            <li class="nav-item"><a class="nav-link" href="#tramites-aprobados"><i class="bi bi-printer"></i> Constancias</a></li>
            <li class="nav-item"><a class="nav-link" href="Dash.php">Ver Trámites</a></li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Cerrar sesión</a></li>
        </ul>
    </div>
</nav>

<!-- SIDEBAR -->
<div class="sidebar position-fixed d-none d-lg-flex flex-column p-3">
    <h5 class="text-white text-center mb-4">Menú Admin</h5>
    <a class="nav-link text-white" href="#inicio">Inicio</a>
    <a class="nav-link text-white" href="#estadisticas">Estadísticas</a>
    <a class="nav-link text-white" href="#usuarios">Usuarios</a>
    <a class="nav-link text-white" href="#logs">Logs</a>
    <a class="nav-link text-white" href="#solicitudes">
        <i class="bi bi-person-check me-1"></i> Solicitudes
        <?php if($total_pendientes > 0): ?>
        <span class="badge bg-warning text-dark ms-1"><?= $total_pendientes ?></span>
        <?php endif; ?>
    </a>
    <a class="nav-link text-white" href="#reporte"><i class="bi bi-bar-chart-line me-1"></i> Reporte</a>
    <a class="nav-link text-white" href="#tramites-aprobados"><i class="bi bi-printer"></i> Constancias</a>
    <a class="nav-link text-white border-top mt-2 pt-2" href="Dash.php">Ver Trámites</a>
    <a class="nav-link text-danger mt-auto" href="logout.php">Cerrar sesión</a>
</div>

<!-- CONTENIDO -->
<div class="content">

<!-- ENCABEZADO -->
<section class="hero" id="inicio">
    <h1><i class="bi bi-shield-check"></i> Panel de Administrador</h1>
    <p>Bienvenido <?php echo $_SESSION['usuario'] ?? ''; ?>. Desde aquí puedes gestionar usuarios, configurar el sistema y monitorear la actividad.</p>
</section>

<!-- ESTADÍSTICAS -->
<section id="estadisticas" class="mb-4">
    <h4 class="text-primary mb-3"><i class="bi bi-graph-up"></i> Estadísticas del Sistema</h4>
    
    <div class="row g-3 mb-4">
        <!-- Tarjeta Trámites -->
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-folder-check text-primary fs-1"></i>
                    <h3 class="mt-2"><?= $stats_tramites['total'] ?></h3>
                    <p class="text-muted mb-0">Total Trámites</p>
                </div>
            </div>
        </div>
        
        <!-- Tarjeta En Revisión -->
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-hourglass-split text-warning fs-1"></i>
                    <h3 class="mt-2"><?= $stats_tramites['en_revision'] ?></h3>
                    <p class="text-muted mb-0">En Revisión</p>
                </div>
            </div>
        </div>
        
        <!-- Tarjeta Aprobados -->
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success fs-1"></i>
                    <h3 class="mt-2"><?= $stats_tramites['aprobados'] ?></h3>
                    <p class="text-muted mb-0">Aprobados</p>
                </div>
            </div>
        </div>
        
        <!-- Tarjeta Usuarios -->
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-people text-info fs-1"></i>
                    <h3 class="mt-2"><?= $stats_usuarios['activos'] ?></h3>
                    <p class="text-muted mb-0">Usuarios Activos</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficas -->
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Distribución de Trámites</h5>
                    <canvas id="chartTramites"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Usuarios por Rol</h5>
                    <canvas id="chartUsuarios"></canvas>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ================================================ -->
<!-- SOLICITUDES DE REGISTRO                         -->
<!-- ================================================ -->
<section id="solicitudes" class="tramite-box mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-primary m-0">
            <i class="bi bi-person-check me-2"></i>Solicitudes de Registro
        </h4>
        <?php if($total_pendientes > 0): ?>
        <span class="badge bg-warning text-dark fs-6">
            <i class="bi bi-clock me-1"></i><?= $total_pendientes ?> pendiente(s)
        </span>
        <?php else: ?>
        <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>Sin pendientes</span>
        <?php endif; ?>
    </div>
    <p class="text-muted small mb-3">
        <i class="bi bi-info-circle me-1"></i>
        Los usuarios que soliciten registro como <strong>Usuario</strong>, <strong>Ventanilla</strong> o <strong>Verificador</strong>
        aparecen aquí. Al aprobar, se crea su cuenta y puedes notificarles sus credenciales.
    </p>

    <?php if(!$solicitudes_query || $solicitudes_query->num_rows === 0): ?>
    <div class="text-center text-muted py-4">
        <i class="bi bi-inbox fs-2 d-block mb-2"></i>No hay solicitudes de registro.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table id="tablaSolicitudes" class="table table-bordered table-hover align-middle">
            <thead style="background:#7b0f2b;color:white;">
                <tr>
                    <th style="background:#7b0f2b;color:#fff;">#</th>
                    <th style="background:#7b0f2b;color:#fff;">Nombre</th>
                    <th style="background:#7b0f2b;color:#fff;">Correo</th>
                    <th style="background:#7b0f2b;color:#fff;">Teléfono</th>
                    <th style="background:#7b0f2b;color:#fff;">Rol</th>
                    <th style="background:#7b0f2b;color:#fff;">Fecha</th>
                    <th style="background:#7b0f2b;color:#fff;">Estado</th>
                    <th style="background:#7b0f2b;color:#fff;text-align:center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php while($sol = $solicitudes_query->fetch_assoc()): ?>
            <?php
                $badgeSol = 'bg-secondary';
                if ($sol['estado'] === 'Pendiente')  $badgeSol = 'bg-warning text-dark';
                if ($sol['estado'] === 'Aprobado')   $badgeSol = 'bg-success';
                if ($sol['estado'] === 'Rechazado')  $badgeSol = 'bg-danger';
            ?>
            <tr>
                <td><?= $sol['id'] ?></td>
                <td><?= htmlspecialchars($sol['nombre'].' '.$sol['apellidos']) ?></td>
                <td><?= htmlspecialchars($sol['correo']) ?></td>
                <td><?= htmlspecialchars($sol['telefono'] ? $sol['telefono'] : '—') ?></td>
                <td>
                    <span class="badge <?= 
                        $sol['rol']==='Verificador' ? 'bg-warning text-dark' : 
                        ($sol['rol']==='Ventanilla' ? 'bg-info text-dark' : 
                        ($sol['rol']==='Usuario' ? 'bg-secondary' : 'bg-secondary')) ?>">
                        <?= $sol['rol'] ?>
                    </span>
                </td>
                <td><?= date('d/m/Y H:i', strtotime($sol['fecha_solicitud'])) ?></td>
                <td><span class="badge <?= $badgeSol ?>"><?= $sol['estado'] ?></span></td>
                <td class="text-center">
                <?php if($sol['estado'] === 'Pendiente'): ?>
                    <button class="btn btn-sm btn-success btn-aprobar-sol"
                        data-id="<?= $sol['id'] ?>"
                        data-nombre="<?= htmlspecialchars($sol['nombre'].' '.$sol['apellidos']) ?>"
                        data-rol="<?= $sol['rol'] ?>">
                        <i class="bi bi-check-circle me-1"></i>Aprobar
                    </button>
                    <button class="btn btn-sm btn-danger btn-rechazar-sol"
                        data-id="<?= $sol['id'] ?>"
                        data-nombre="<?= htmlspecialchars($sol['nombre'].' '.$sol['apellidos']) ?>">
                        <i class="bi bi-x-circle me-1"></i>Rechazar
                    </button>
                <?php elseif($sol['estado'] === 'Rechazado' && $sol['motivo_rechazo']): ?>
                    <small class="text-muted fst-italic">
                        Motivo: <?= htmlspecialchars($sol['motivo_rechazo']) ?>
                    </small>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<!-- MODAL NOTIFICACIÓN SOLICITUD -->
<div class="modal fade" id="notifSolModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header" style="background:#7b0f2b;color:white;">
                <h5 class="modal-title"><i class="bi bi-bell me-2"></i>Notificar al Solicitante</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2" id="notif-sol-desc"></p>
                <div id="notif-sol-preview" class="alert alert-light border mb-3" style="display:none;">
                    <p class="mb-1 fw-bold small text-secondary"><i class="bi bi-chat-quote me-1"></i>Mensaje a enviar:</p>
                    <p id="notif-sol-texto" class="mb-0 small" style="white-space:pre-line;"></p>
                </div>
                <a id="notif-sol-wa" href="#" target="_blank" rel="noopener"
                   class="d-flex align-items-center gap-3 p-3 rounded border mb-2 text-decoration-none text-dark"
                   style="border-color:#25D366!important;background:rgba(37,211,102,.06);">
                    <span style="font-size:2rem;">💬</span>
                    <div>
                        <div class="fw-bold" style="color:#25D366;">Enviar por WhatsApp</div>
                        <div class="text-muted notif-sol-sub small">Abre WhatsApp con el mensaje listo</div>
                    </div>
                </a>
                <a id="notif-sol-gm" href="#" target="_blank" rel="noopener"
                   class="d-flex align-items-center gap-3 p-3 rounded border mb-2 text-decoration-none text-dark"
                   style="border-color:#EA4335!important;background:rgba(234,67,53,.06);">
                    <span style="font-size:2rem;">📧</span>
                    <div>
                        <div class="fw-bold" style="color:#EA4335;">Enviar por Correo</div>
                        <div class="text-muted notif-sol-sub small">Abre tu cliente de correo listo</div>
                    </div>
                </a>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btnCerrarNotifSol">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ================================================ -->
<!-- REPORTE DE TRÁMITES                             -->
<!-- ================================================ -->
<section id="reporte" class="tramite-box mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="text-primary m-0"><i class="bi bi-bar-chart-line me-2"></i>Reporte de Trámites Realizados</h4>
    <!-- Selector de año -->
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

  <!-- Tarjetas resumen del año -->
  <?php
  $tot_año    = $gran_total;
  $apr_año    = 0; $rev_año = 0; $rec_año = 0; $cor_año = 0;
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

  <!-- Gráfica de barras mensual -->
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
      <canvas id="chartReporteMes" style="max-height:280px;"></canvas>
    </div>
  </div>

  <!-- Tabla por mes -->
  <h6 class="fw-bold text-secondary mb-2"><i class="bi bi-calendar3 me-1"></i>Trámites por Mes — <?= $anio_filtro ?></h6>
  <?php
  $meses_nombre = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
                   7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
  $sum_total=0; $sum_apr=0; $sum_rev=0; $sum_cor=0; $sum_rec=0;
  ?>
  <div class="table-responsive mb-4">
    <table class="table table-bordered table-hover align-middle" id="tablaReporteMes">
      <thead style="background:#7b0f2b;color:white;">
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
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
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

  <!-- Tabla por tipo de trámite -->
  <?php if (!empty($datos_tipo)): ?>
  <h6 class="fw-bold text-secondary mb-2"><i class="bi bi-list-task me-1"></i>Trámites por Tipo — <?= $anio_filtro ?></h6>
  <div class="table-responsive mb-3">
    <table class="table table-bordered table-hover align-middle" id="tablaReporteTipo">
      <thead style="background:#7b0f2b;color:white;">
        <tr>
          <th style="background:#7b0f2b;color:#fff;">Tipo de Trámite</th>
          <th style="background:#7b0f2b;color:#fff;text-align:center;">Total</th>
          <th style="background:#1a6e35;color:#fff;text-align:center;">Aprobados</th>
          <th style="background:#842029;color:#fff;text-align:center;">Rechazados</th>
          <th style="background:#7b0f2b;color:#fff;text-align:center;">% del año</th>
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

  <!-- Botón imprimir reporte -->
  <div class="text-end">
    <button onclick="imprimirReporte()" class="btn btn-outline-primary">
      <i class="bi bi-printer me-1"></i>Imprimir Reporte
    </button>
  </div>
</section>

<!-- TRÁMITES APROBADOS - CONSTANCIAS -->
<section id="tramites-aprobados" class="tramite-box mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-success m-0"><i class="bi bi-check-circle-fill"></i> Trámites Aprobados — Imprimir Constancia</h4>
        <span class="badge bg-success fs-6"><?= $tramites_aprobados->num_rows ?> aprobados</span>
    </div>
    <p class="text-muted small mb-3"><i class="bi bi-info-circle"></i> Haz clic en <strong>Imprimir</strong> para abrir la constancia lista para firmar y entregar al solicitante.</p>

    <div class="table-responsive">
        <table id="tablaAprobados" class="table table-bordered table-hover">
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
                <?php while($tr = $tramites_aprobados->fetch_assoc()): ?>
                <?php
                    $folio_sal_a = !empty($tr['folio_salida_numero'])
                        ? str_pad($tr['folio_salida_numero'], 3, '0', STR_PAD_LEFT) . '/' . $tr['folio_salida_anio']
                        : '';
                ?>
                <tr>
                    <td><span class="badge bg-success"><?= htmlspecialchars($tr['folio']) ?></span></td>
                    <td>
                        <?php if($folio_sal_a): ?>
                            <span class="badge bg-primary"><?= htmlspecialchars($folio_sal_a) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($tr['tipo_tramite_nombre'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($tr['propietario']) ?></td>
                    <td><?= htmlspecialchars($tr['solicitante']) ?></td>
                    <td><?= htmlspecialchars($tr['direccion']) ?></td>
                    <td>
                        <?php if(!empty($tr['numero_asignado'])): ?>
                            <span class="badge bg-primary fs-6"><?= htmlspecialchars($tr['numero_asignado']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $tr['fecha_aprobacion']
                            ? date('d/m/Y H:i', strtotime($tr['fecha_aprobacion']))
                            : '—' ?>
                    </td>
                    <td class="text-center">
                        <?php
                            $folio_url = urlencode($tr['folio']);
                        ?>
                        <a href="constancia_numero.php?folio=<?= $folio_url ?>"
                           target="_self"
                           class="btn btn-sm btn-success"
                           title="Abrir constancia para imprimir">
                            <i class="bi bi-printer-fill"></i> Imprimir
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- GESTIÓN DE USUARIOS -->
<section id="usuarios" class="tramite-box mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-primary m-0"><i class="bi bi-people"></i> Gestión de Usuarios</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
            <i class="bi bi-plus-circle"></i> Nuevo Usuario
        </button>
    </div>

    <div class="table-responsive">
        <table id="tablaUsuarios" class="table table-bordered table-hover">
            <thead style="background: #7b0f2b !important; color: white !important;">
                 <tr>
                    <th style="background: #7b0f2b !important; color: white !important;">ID</th>
                    <th style="background: #7b0f2b !important; color: white !important;">Nombre</th>
                    <th style="background: #7b0f2b !important; color: white !important;">Correo</th>
                    <th style="background: #7b0f2b !important; color: white !important;">Rol</th>
                    <th style="background: #7b0f2b !important; color: white !important;">Estado</th>
                    <th style="background: #7b0f2b !important; color: white !important;">Último Acceso</th>
                    <th style="background: #7b0f2b !important; color: white !important;">Acciones</th>
                 </tr>
            </thead>
            <tbody>
                <?php while($usuario = $usuarios_query->fetch_assoc()): ?>
                <tr>
                    <td><?= $usuario['id'] ?></td>
                    <td><?= htmlspecialchars($usuario['nombre'].' '.$usuario['apellidos']) ?></td>
                    <td><?= htmlspecialchars($usuario['correo']) ?></td>
                    <td>
                        <span class="badge bg-<?= match($usuario['rol']) {
                            'Administrador' => 'danger',
                            'Verificador' => 'warning',
                            'Ventanilla' => 'info',
                            'Usuario' => 'secondary',
                            default => 'secondary'
                        } ?>">
                            <?= $usuario['rol'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-<?= $usuario['activo'] ? 'success' : 'secondary' ?>">
                            <?= $usuario['activo'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>
                    <td><?= $usuario['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca' ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" 
                                onclick="editarUsuario(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['nombre']) ?>', '<?= htmlspecialchars($usuario['apellidos']) ?>', '<?= htmlspecialchars($usuario['correo']) ?>', '<?= $usuario['rol'] ?>', <?= $usuario['activo'] ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if($usuario['id'] != $_SESSION['id']): ?>
                        <button class="btn btn-sm btn-outline-<?= $usuario['activo'] ? 'warning' : 'success' ?>" 
                                onclick="toggleEstadoUsuario(<?= $usuario['id'] ?>, <?= $usuario['activo'] ?>)">
                            <i class="bi bi-<?= $usuario['activo'] ? 'x-circle' : 'check-circle' ?>"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- LOGS DE ACTIVIDAD -->
<section id="logs" class="tramite-box mb-4">
    <h4 class="text-primary mb-3"><i class="bi bi-clock-history"></i> Registro de Actividad</h4>
    
    <div class="table-responsive">
        <table id="tablaLogs" class="table table-sm table-bordered">
            <thead style="background: #7b0f2b !important; color: white !important;">
                 <tr>
                    <th style="background: #7b0f2b !important; color: white !important;">Fecha</th>
                    <th style="background: #7b0f2b !important; color: white !important;">Usuario</th>
                    <th style="background: #7b0f2b !important; color: white !important;">Acción</th>
                    <th style="background: #7b0f2b !important; color: white !important;">Detalles</th>
                    <th style="background: #7b0f2b !important; color: white !important;">IP</th>
                 </tr>
            </thead>
            <tbody>
                <?php while($log = $logs_query->fetch_assoc()): ?>
                <tr>
                    <td><?= date('d/m/Y H:i:s', strtotime($log['fecha'])) ?></td>
                    <td><?= htmlspecialchars($log['nombre'].' '.$log['apellidos']) ?></td>
                    <td>
                        <span class="badge bg-<?= match($log['accion']) {
                            'Login exitoso' => 'success',
                            'Logout' => 'secondary',
                            'Auto-registro' => 'info',
                            default => 'primary'
                        } ?>">
                            <?= htmlspecialchars($log['accion']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($log['detalles'] ?? '-') ?></td>
                    <td><small><?= htmlspecialchars($log['ip_address']) ?></small></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>

</div>

<!-- MODAL NUEVO USUARIO -->
<div class="modal fade" id="modalNuevoUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> Nuevo Usuario</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNuevoUsuario">
                <?php $csrf = generarCSRF(); ?>
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Apellidos</label>
                        <input type="text" name="apellidos" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo Electrónico</label>
                        <input type="email" name="correo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select name="rol" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <option value="Usuario">Usuario</option>
                            <option value="Ventanilla">Ventanilla</option>
                            <option value="Verificador">Verificador</option>
                            <option value="Administrador">Administrador</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR USUARIO -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar Usuario</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarUsuario">
                <?php $csrf = generarCSRF(); ?>
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Apellidos</label>
                        <input type="text" name="apellidos" id="edit_apellidos" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo Electrónico</label>
                        <input type="email" name="correo" id="edit_correo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select name="rol" id="edit_rol" class="form-select" required>
                            <option value="Usuario">Usuario</option>
                            <option value="Ventanilla">Ventanilla</option>
                            <option value="Verificador">Verificador</option>
                            <option value="Administrador">Administrador</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nueva Contraseña (dejar vacío para mantener)</label>
                        <div class="password-container" style="position: relative;">
                            <input type="password" name="password" id="edit_password" class="form-control" minlength="8" style="padding-right: 45px;">
                            <button type="button" class="toggle-password" onclick="togglePassword('edit_password')" 
                                    style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); 
                                        background: transparent; border: none; cursor: pointer; padding: 0; width: 30px; height: 30px;
                                        display: flex; align-items: center; justify-content: center; font-size: 18px; color: #7b0f2b; border-radius: 50%;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Gráfica de Trámites
const ctxTramites = document.getElementById('chartTramites').getContext('2d');
new Chart(ctxTramites, {
    type: 'doughnut',
    data: {
        labels: ['En Revisión', 'Aprobados', 'Rechazados'],
        datasets: [{
            data: [<?= $stats_tramites['en_revision'] ?>, <?= $stats_tramites['aprobados'] ?>, <?= $stats_tramites['rechazados'] ?>],
            backgroundColor: ['#ffc107', '#28a745', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Gráfica de Usuarios
const ctxUsuarios = document.getElementById('chartUsuarios').getContext('2d');
new Chart(ctxUsuarios, {
    type: 'bar',
    data: {
        labels: ['Admins', 'Verificadores', 'Ventanillas', 'Usuarios'],
        datasets: [{
            label: 'Cantidad',
            data: [<?= $stats_usuarios['admins'] ?>, <?= $stats_usuarios['verificadores'] ?>, <?= $stats_usuarios['ventanillas'] ?>, <?= $stats_usuarios['usuarios'] ?>],
            backgroundColor: ['#dc3545', '#ffc107', '#17a2b8', '#6c757d']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>

<script src="js/admin.js"></script>
<script>
$(document).ready(function() {
    $('#tablaAprobados').DataTable({
        language: { 
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-MX.json',
            emptyTable: '<i class="bi bi-inbox fs-3 d-block mb-2"></i> No hay trámites aprobados aún.'
        },
        order: [[7, 'desc']],
        pageLength: 10,
        columnDefs: [{ orderable: false, targets: 8 }]
    });
    $('#tablaSolicitudes').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-MX.json' },
        order: [[6, 'asc'], [5, 'desc']],
        pageLength: 10,
        columnDefs: [{ orderable: false, targets: 7 }]
    });
});
</script>

<script>
// ── CSRF token para solicitudes ──
var csrfToken = '<?php echo isset($_SESSION["csrf_token"]) ? $_SESSION["csrf_token"] : ""; ?>';

// ── APROBAR solicitud ──
document.querySelectorAll('.btn-aprobar-sol').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id     = btn.dataset.id;
        var nombre = btn.dataset.nombre;
        var rol    = btn.dataset.rol;

        Swal.fire({
            title: '¿Aprobar solicitud?',
            html: 'Se creará la cuenta de <strong>' + nombre + '</strong> con rol <strong>' + rol + '</strong>.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, aprobar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });

            var fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('accion', 'aprobar');
            fd.append('sol_id', id);

            fetch('php/gestion_solicitudes.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    Swal.close();
                    _abrirNotifSol(data);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                }
            })
            .catch(function(e) {
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: e.message });
            });
        });
    });
});

// ── RECHAZAR solicitud ──
document.querySelectorAll('.btn-rechazar-sol').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id     = btn.dataset.id;
        var nombre = btn.dataset.nombre;

        Swal.fire({
            title: 'Rechazar solicitud de ' + nombre,
            html: '<label class="form-label">Motivo del rechazo (opcional):</label>' +
                  '<textarea id="motivoRechazo" class="swal2-textarea" placeholder="Ej: Documentación incompleta..."></textarea>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Rechazar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            preConfirm: function() {
                return document.getElementById('motivoRechazo').value;
            }
        }).then(function(result) {
            if (!result.isConfirmed) return;

            var motivo = result.value || '';
            Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });

            var fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('accion', 'rechazar');
            fd.append('sol_id', id);
            fd.append('motivo', motivo);

            fetch('php/gestion_solicitudes.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    Swal.close();
                    _abrirNotifSol(data);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                }
            })
            .catch(function(e) {
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: e.message });
            });
        });
    });
});

// ── Modal de notificación al solicitante ──
function _abrirNotifSol(data) {
    var accion = data.accion === 'aprobado' ? 'APROBADA ✅' : 'RECHAZADA ❌';
    document.getElementById('notif-sol-desc').innerHTML =
        'Solicitud de <strong>' + data.nombre + '</strong> ' + accion;

    if (data.mensaje) {
        document.getElementById('notif-sol-texto').textContent = data.mensaje;
        document.getElementById('notif-sol-preview').style.display = 'block';
    }

    var waEl = document.getElementById('notif-sol-wa');
    var gmEl = document.getElementById('notif-sol-gm');

    if (data.wa_link) {
        waEl.href = data.wa_link;
        waEl.style.opacity = '1'; waEl.style.pointerEvents = 'auto';
        waEl.querySelector('.notif-sol-sub').textContent = data.telefono ? 'Enviar a: ' + data.telefono : 'Abrir WhatsApp';
    } else {
        waEl.href = '#'; waEl.style.opacity = '0.35'; waEl.style.pointerEvents = 'none';
        waEl.querySelector('.notif-sol-sub').textContent = 'Sin número de teléfono registrado';
    }

    if (data.gm_link) {
        gmEl.href = data.gm_link;
        gmEl.style.opacity = '1'; gmEl.style.pointerEvents = 'auto';
        gmEl.querySelector('.notif-sol-sub').textContent = data.correo ? 'Enviar a: ' + data.correo : 'Abrir correo';
    } else {
        gmEl.href = '#'; gmEl.style.opacity = '0.35'; gmEl.style.pointerEvents = 'none';
        gmEl.querySelector('.notif-sol-sub').textContent = 'Sin correo registrado';
    }

    var modalEl = document.getElementById('notifSolModal');
    modalEl.removeAttribute('aria-hidden');
    new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false }).show();
}

// Al cerrar el modal → recargar para actualizar la tabla
document.getElementById('btnCerrarNotifSol').addEventListener('click', function() {
    location.reload();
});
</script>

<!-- Gráfica de barras: trámites por mes -->
<script>
(function() {
    var meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    var totales  = [<?php for($m=1;$m<=12;$m++) echo (isset($datos_mes[$m]) ? $datos_mes[$m]['total'] : 0).($m<12?',':''); ?>];
    var aprobados= [<?php for($m=1;$m<=12;$m++) echo (isset($datos_mes[$m]) ? $datos_mes[$m]['aprobados'] : 0).($m<12?',':''); ?>];
    var rechazados=[<?php for($m=1;$m<=12;$m++) echo (isset($datos_mes[$m]) ? $datos_mes[$m]['rechazados'] : 0).($m<12?',':''); ?>];

    var canvasEl = document.getElementById('chartReporteMes');
    if (!canvasEl) return;
    new Chart(canvasEl.getContext('2d'), {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [
                { label: 'Total',      data: totales,   backgroundColor: 'rgba(13,110,253,.7)',  borderRadius: 4 },
                { label: 'Aprobados',  data: aprobados, backgroundColor: 'rgba(25,135,84,.7)',   borderRadius: 4 },
                { label: 'Rechazados', data: rechazados,backgroundColor: 'rgba(220,53,69,.7)',   borderRadius: 4 }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'Trámites por mes — <?= $anio_filtro ?>' } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
})();

function imprimirReporte() {
    var t1 = document.getElementById('tablaReporteMes');
    var t2 = document.getElementById('tablaReporteTipo');

    if (!t1) { alert('No hay datos para imprimir.'); return; }

    var tabla1 = t1.outerHTML;
    var tabla2 = t2 ? '<h3 style="margin-top:24px;color:#7b0f2b;">Por Tipo de Trámite</h3>' + t2.outerHTML : '';

    var w = window.open('', '_blank');
    w.document.write(
        '<html><head><title>Reporte <?= $anio_filtro ?></title><style>' +
        'body{font-family:Arial,sans-serif;padding:20px;color:#222;}' +
        'h2,h3{color:#7b0f2b;margin:6px 0;}' +
        'table{width:100%;border-collapse:collapse;margin-top:10px;font-size:13px;}' +
        'th,td{border:1px solid #bbb;padding:6px 10px;text-align:center;}' +
        'td:first-child{text-align:left;}' +
        'thead th{color:white;}' +
        'tbody tr:hover{background:#f9f9f9;}' +
        'tfoot tr:first-child td{background:#e0e0e0;font-weight:bold;}' +
        'tfoot tr:last-child td{background:#d0d0d0;font-weight:bold;}' +
        '.badge{padding:2px 8px;border-radius:4px;font-size:12px;color:white;display:inline-block;}' +
        '.bg-primary{background:#0d6efd;}' +
        '.bg-success{background:#198754;}' +
        '.bg-warning{background:#ffc107;color:#000 !important;}' +
        '.bg-info{background:#0dcaf0;color:#000 !important;}' +
        '.bg-danger{background:#dc3545;}' +
        '.bg-dark{background:#212529;}' +
        '.text-dark{color:#000 !important;}' +
        '.text-muted{color:#888;}' +
        '.progress{display:inline-block;width:60px;height:10px;background:#eee;border-radius:4px;vertical-align:middle;}' +
        '.progress-bar{height:100%;background:#0d6efd;border-radius:4px;display:block;}' +
        '@media print{.no-print{display:none;}}' +
        '</style></head><body>' +
        '<div style="text-align:center;margin-bottom:20px;">' +
        '<img src="logos/logo_urbano.jpeg" style="height:55px;margin-right:16px;" onerror="this.style.display=\'none\'">' +
        '<img src="logos/logo_presi.jpeg"  style="height:55px;" onerror="this.style.display=\'none\'">' +
        '<h2>Dirección de Planeación y Desarrollo Urbano</h2>' +
        '<h3>Reporte de Trámites — <?= $anio_filtro ?></h3>' +
        '<p style="color:#666;font-size:13px;margin:4px 0;">Total histórico acumulado: <strong><?= $total_global ?> trámites</strong></p>' +
        '</div>' +
        '<h3>Trámites por Mes</h3>' +
        tabla1 + tabla2 +
        '<p style="margin-top:20px;color:#999;font-size:11px;">Generado el ' + new Date().toLocaleDateString('es-MX',{day:"2-digit",month:"long",year:"numeric"}) + '</p>' +
        '<button class="no-print" onclick="window.print()" style="margin-top:8px;padding:8px 22px;background:#7b0f2b;color:white;border:none;border-radius:5px;cursor:pointer;font-size:14px;">🖨️ Imprimir / Guardar PDF</button>' +
        '</body></html>'
    );
    w.document.close();
}
// Función para mostrar/ocultar contraseña
function togglePassword(inputId) {
    var input = document.getElementById(inputId);
    var icon = input.nextElementSibling.querySelector('i');
    
    if (!icon) {
        // Si el icono no está en el nextElementSibling, buscarlo de otra forma
        icon = document.querySelector('#' + inputId + ' + .toggle-password i');
        if (!icon) {
            var btn = input.nextElementSibling;
            if (btn && btn.classList.contains('toggle-password')) {
                icon = btn.querySelector('i');
            }
        }
    }
    
    if (input.type === "password") {
        input.type = "text";
        if (icon) icon.className = "fas fa-eye-slash";
    } else {
        input.type = "password";
        if (icon) icon.className = "fas fa-eye";
    }
}
</script>

</body>
</html>