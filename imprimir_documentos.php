<?php
/**
 * IMPRESIÓN DE DOCUMENTOS DEL TRÁMITE
 * Permite a la secretaria (Ventanilla) seleccionar qué documentos imprimir
 */
ini_set('session.cookie_httponly', 1);
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['id']) || !isset($_SESSION['usuario'])) {
    header("Location: acceso.php"); exit();
}

require_once "php/db.php";
require_once "php/funciones_seguridad.php";

if (!esVentanilla() && !esAdministrador() && !esVerificador()) {
    header("Location: acceso.php"); exit();
}

if (empty($_GET['folio'])) {
    header("Location: DashVentanilla.php"); exit();
}

$folio_raw = trim($_GET['folio']);
$partes    = explode('/', $folio_raw);
if (count($partes) !== 2) { header("Location: DashVentanilla.php"); exit(); }

$folio_numero = intval($partes[0]);
$folio_anio   = intval($partes[1]);

$stmt = $conn->prepare("
    SELECT t.*, tt.nombre AS tipo_tramite_nombre
    FROM tramites t
    LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
    WHERE t.folio_numero = ? AND t.folio_anio = ?
    LIMIT 1
");
$stmt->bind_param("ii", $folio_numero, $folio_anio);
$stmt->execute();
$t = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$t) { header("Location: DashVentanilla.php?error=tramite_no_encontrado"); exit(); }

$config = [];
$res = $conn->query("SELECT clave, valor FROM configuracion_sistema");
while ($row = $res->fetch_assoc()) $config[$row['clave']] = $row['valor'];

// Documentos disponibles
$docs = [];
if (!empty($t['ine_archivo']))          $docs['ine']        = ['label'=>'INE / Identificación',       'archivo'=>$t['ine_archivo']];
if (!empty($t['escrituras_archivo']))   $docs['escritura']  = ['label'=>'Escritura / Título',          'archivo'=>$t['escrituras_archivo']];
elseif (!empty($t['titulo_archivo']))   $docs['escritura']  = ['label'=>'Escritura / Título',          'archivo'=>$t['titulo_archivo']];
if (!empty($t['predial_archivo']))      $docs['predial']    = ['label'=>'Boleta Predial',              'archivo'=>$t['predial_archivo']];
if (!empty($t['formato_constancia']))   $docs['formato']    = ['label'=>'Formato de Constancia',       'archivo'=>$t['formato_constancia']];
if (!empty($t['foto1_archivo']))        $docs['foto1']      = ['label'=>'Fotografía 1 del Inmueble',   'archivo'=>$t['foto1_archivo']];
if (!empty($t['foto2_archivo']))        $docs['foto2']      = ['label'=>'Fotografía 2 del Inmueble',   'archivo'=>$t['foto2_archivo']];

$tiene_constancia = !empty($t['numero_asignado']);
$folio_display    = "ING." . str_pad($folio_numero, 3,'0',STR_PAD_LEFT) . "/" . $folio_anio;

// Determinar URL de regreso
$back = esAdministrador() ? 'DashAdmin.php' : (esVentanilla() ? 'DashVentanilla.php' : 'DashVer.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sis Dit — <?= htmlspecialchars($folio_display) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="./css/style.css?v=1">
<style>
  body { background: #f4f6f9; }

  .doc-card {
    border: 2px solid #dee2e6;
    border-radius: 10px;
    overflow: hidden;
    transition: border-color .2s, box-shadow .2s;
    cursor: pointer;
    background: #fff;
  }
  .doc-card:hover { border-color: #7b0f2b; box-shadow: 0 4px 16px rgba(123,15,43,.12); }
  .doc-card.selected { border-color: #7b0f2b; background: #fff8fa; box-shadow: 0 4px 16px rgba(123,15,43,.18); }
  .doc-card .card-header-custom {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .doc-card.selected .card-header-custom { background: #f5e6ea; border-color: #e0b3bf; }
  .doc-card .preview-area {
    min-height: 160px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
    background: #fafafa;
  }
  .doc-card .preview-area img {
    max-width: 100%;
    max-height: 160px;
    object-fit: contain;
    border-radius: 4px;
  }
  .doc-card .preview-area .pdf-icon {
    font-size: 3rem;
    color: #dc3545;
  }
  .check-badge {
    width: 26px; height: 26px;
    border-radius: 50%;
    border: 2px solid #aaa;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: .2s;
    font-size: 14px;
    color: transparent;
    background: #fff;
  }
  .doc-card.selected .check-badge {
    border-color: #7b0f2b;
    background: #7b0f2b;
    color: #fff;
  }
  .constancia-card {
    border: 2px solid #198754;
    border-radius: 10px;
    background: #f0faf4;
    padding: 18px 20px;
    margin-bottom: 20px;
  }
  .constancia-card.bloqueada { border-color: #aaa; background: #f8f9fa; opacity: .7; }
  .btn-accion-print {
    background: #7b0f2b; color: #fff; border: none;
    padding: 12px 32px; border-radius: 8px; font-size: 1rem;
    font-weight: 700; cursor: pointer; transition: background .2s;
  }
  .btn-accion-print:hover { background: #5e0b20; }
  .btn-accion-print:disabled { background: #aaa; cursor: not-allowed; }
  .seccion-titulo {
    font-size: 1rem; font-weight: 700; color: #7b0f2b;
    border-bottom: 2px solid #e0b3bf; padding-bottom: 6px; margin-bottom: 14px;
  }

  /* Print styles */
  @media print {
    .no-print { display: none !important; }
    body { background: #fff; }
    .pagina-impresion { page-break-before: always; }
    .pagina-impresion:first-of-type { page-break-before: auto; }
    .img-doc-print { max-width: 100%; max-height: 85vh; object-fit: contain; display: block; margin: 0 auto; }
    .pdf-aviso { display: none; }
  }
  .pagina-impresion { display: none; }
  @media print {
    .pagina-impresion { display: block; }
    .contenido-seleccion { display: none; }
  }
  @media print {
  .no-print { display: none !important; }
  body { background: #fff; }
  .pagina-impresion { page-break-before: always; }
  .pagina-impresion:first-of-type { page-break-before: auto; }
  .img-doc-print { max-width: 100%; max-height: 85vh; object-fit: contain; display: block; margin: 0 auto; }
  .pdf-aviso { display: none; }
  
  /* Eliminar headers y footers predeterminados del navegador */
  @page {
    margin: 0;
    size: auto;
  }
  
  /* Ocultar URL, fecha, título de página, etc. */
  html, body {
    margin: 0;
    padding: 0;
  }
  
  /* Ocultar el título de la página que algunos navegadores añaden */
  title {
    display: none;
  }
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-dark d-lg-none" style="background:#7b0f2b;">
  <div class="container-fluid">
    <span class="navbar-brand">Documentos del Trámite</span>
  </div>
</nav>

<!-- SIDEBAR (desktop) -->
<div class="sidebar position-fixed d-none d-lg-flex flex-column p-3">
  <h5 class="text-white text-center mb-4">Menú</h5>
  <a class="nav-link text-white" href="<?= $back ?>"><i class="bi bi-arrow-left me-2"></i> Volver al Panel</a>
  <a class="nav-link text-danger mt-auto" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión</a>
</div>

<!-- CONTENIDO -->
<div class="content no-print">

  <!-- ENCABEZADO -->
  <section class="hero" style="padding: 18px 24px;">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <a href="<?= $back ?>" class="btn btn-sm btn-outline-light">
        <i class="bi bi-arrow-left me-1"></i> Volver
      </a>
      <div>
        <h4 class="mb-0"><i class="bi bi-printer me-2"></i>Documentos del Trámite</h4>
        <small class="opacity-75">
          <?= htmlspecialchars($folio_display) ?> —
          <?= htmlspecialchars($t['propietario']) ?> —
          <?= htmlspecialchars($t['tipo_tramite_nombre'] ?? 'Sin tipo') ?>
        </small>
      </div>
    </div>
  </section>

  <div class="container-fluid px-4 pb-5">

    <!-- CONSTANCIA DE NÚMERO OFICIAL (siempre arriba y aparte) -->
    <?php if ($t['tipo_tramite_id'] == 1): ?>
    <div class="constancia-card <?= $tiene_constancia ? '' : 'bloqueada' ?>">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <h5 class="mb-1" style="color: <?= $tiene_constancia ? '#198754' : '#666' ?>;">
            <i class="bi bi-file-earmark-check me-2"></i>Constancia de Número Oficial
          </h5>
          <?php if ($tiene_constancia): ?>
            <p class="mb-0 text-muted small">
              Número asignado: <strong><?= htmlspecialchars($t['numero_asignado']) ?></strong> —
              Listo para imprimir y firmar.
            </p>
          <?php else: ?>
            <p class="mb-0 text-danger small">
              <i class="bi bi-lock-fill me-1"></i>
              La constancia aún no ha sido generada por el verificador.
            </p>
          <?php endif; ?>
        </div>
        <?php if ($tiene_constancia): ?>
          <a href="constancia_numero.php?folio=<?= urlencode($folio_raw) ?>" target="_self"
             class="btn btn-success px-4">
            <i class="bi bi-printer me-2"></i>Imprimir Constancia
          </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- FICHA DE DATOS -->
    <div class="constancia-card" style="border-color:#0d6efd; background:#f0f4ff;">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <h5 class="mb-1" style="color:#0d6efd;">
            <i class="bi bi-card-text me-2"></i>Ficha de Ingreso del Trámite
          </h5>
          <p class="mb-0 text-muted small">Datos completos del solicitante y del trámite.</p>
        </div>
        <a href="ficha.php?folio=<?= urlencode($folio_raw) ?>" target="_self"
           class="btn btn-primary px-4">
          <i class="bi bi-printer me-2"></i>Imprimir Ficha
        </a>
      </div>
    </div>

    <!-- FICHA DE FOTOGRAFÍAS -->
    <?php
      $hay_fotos = !empty($t['foto1_archivo']) || !empty($t['foto2_archivo']);
    ?>
    <div class="constancia-card <?= $hay_fotos ? '' : 'bloqueada' ?>" style="border-color:#6f42c1; background:#f8f0ff;">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <h5 class="mb-1" style="color:#6f42c1;">
            <i class="bi bi-camera-fill me-2"></i>Ficha de Fotografías del Inmueble
          </h5>
          <?php if ($hay_fotos): ?>
            <p class="mb-0 text-muted small">Fotografías del inmueble tomadas por el verificador.</p>
          <?php else: ?>
            <p class="mb-0 text-danger small">
              <i class="bi bi-lock-fill me-1"></i>Aún no se han subido fotografías.
            </p>
          <?php endif; ?>
        </div>
        <?php if ($hay_fotos): ?>
          <a href="ficha_fotografias.php?folio=<?= urlencode($folio_raw) ?>" target="_self"
             class="btn px-4" style="background:#6f42c1;color:#fff;">
            <i class="bi bi-printer me-2"></i>Imprimir Ficha de Fotos
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- DOCUMENTOS ADJUNTOS -->
    <?php if (!empty($docs)): ?>
    <div class="tramite-box mt-2">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="seccion-titulo mb-0">
          <i class="bi bi-files me-2"></i>Documentos Adjuntos del Solicitante
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary" onclick="seleccionarTodos()">Seleccionar todos</button>
          <button class="btn btn-sm btn-outline-danger" onclick="deseleccionarTodos()">Limpiar</button>
          <button class="btn-accion-print btn btn-sm" id="btn-imprimir-sel" onclick="imprimirSeleccionados()" disabled>
            <i class="bi bi-printer me-1"></i> Imprimir seleccionados
          </button>
        </div>
      </div>
      <p class="text-muted small mb-3">
        <i class="bi bi-info-circle me-1"></i>
        Haz clic en cada documento para seleccionarlo. Los PDFs se abrirán en una nueva pestaña.
      </p>

      <div class="row g-3" id="grid-docs">
        <?php foreach ($docs as $key => $doc): ?>
        <?php
          $archivo  = $doc['archivo'];
          $ext      = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
          $es_pdf   = ($ext === 'pdf');
          $url_doc  = "uploads/" . htmlspecialchars($archivo);
        ?>
        <div class="col-md-4 col-lg-3">
          <div class="doc-card" id="card-<?= $key ?>" onclick="toggleDoc('<?= $key ?>', '<?= $es_pdf ? 'pdf' : 'img' ?>', '<?= addslashes($url_doc) ?>')">
            <div class="card-header-custom">
              <div class="check-badge" id="chk-<?= $key ?>">
                <i class="bi bi-check-lg"></i>
              </div>
              <span class="fw-semibold small"><?= htmlspecialchars($doc['label']) ?></span>
              <a href="<?= $url_doc ?>" target="_blank" class="btn btn-sm btn-outline-secondary ms-auto"
                 onclick="event.stopPropagation()" title="Ver en nueva pestaña">
                <i class="bi bi-eye"></i>
              </a>
            </div>
            <div class="preview-area">
              <?php if ($es_pdf): ?>
                <div class="text-center">
                  <i class="bi bi-file-earmark-pdf pdf-icon"></i>
                  <p class="small text-muted mt-2 mb-0">Archivo PDF</p>
                  <p class="small text-muted" style="font-size:.75rem;">Se abrirá en nueva pestaña</p>
                </div>
              <?php else: ?>
                <img src="<?= $url_doc ?>" alt="<?= htmlspecialchars($doc['label']) ?>"
                     onerror="this.parentElement.innerHTML='<span class=\'text-muted small\'>No se pudo cargar</span>'">
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="tramite-box mt-2">
      <p class="text-muted text-center py-3">
        <i class="bi bi-folder-x fs-2 d-block mb-2 opacity-50"></i>
        No hay documentos adjuntos en este trámite.
      </p>
    </div>
    <?php endif; ?>

  </div><!-- /container -->
</div><!-- /content -->


<!-- PÁGINAS DE IMPRESIÓN (solo visibles al imprimir) -->
<div id="zona-impresion">
  <?php foreach ($docs as $key => $doc):
    $archivo = $doc['archivo'];
    $ext     = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
    $es_pdf  = ($ext === 'pdf');
    $url_doc = "uploads/" . htmlspecialchars($archivo);
  ?>
  <?php if (!$es_pdf): ?>
    <div class="pagina-impresion" id="pag-<?= $key ?>" style="text-align:center; padding:10mm;">
      <img src="<?= $url_doc ?>" class="img-doc-print" alt="<?= htmlspecialchars($doc['label']) ?>">
    </div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Estado de selección
var seleccion = {};
var tipoDoc   = {};

<?php foreach ($docs as $key => $doc): ?>
seleccion['<?= $key ?>'] = false;
tipoDoc['<?= $key ?>'] = '<?= (strtolower(pathinfo($doc['archivo'], PATHINFO_EXTENSION)) === 'pdf') ? 'pdf' : 'img' ?>';
<?php endforeach; ?>

function toggleDoc(key, tipo, url) {
  if (tipo === 'pdf') {
    // Para PDFs, abrir directo en nueva pestaña
    window.open(url, '_blank');
    return;
  }
  seleccion[key] = !seleccion[key];
  var card = document.getElementById('card-' + key);
  var chk  = document.getElementById('chk-' + key);
  if (seleccion[key]) {
    card.classList.add('selected');
    chk.style.background = '#7b0f2b';
    chk.style.borderColor = '#7b0f2b';
    chk.style.color = '#fff';
  } else {
    card.classList.remove('selected');
    chk.style.background = '';
    chk.style.borderColor = '';
    chk.style.color = 'transparent';
  }
  actualizarBoton();
}

function actualizarBoton() {
  var haySeleccion = Object.keys(seleccion).some(function(k) {
    return seleccion[k] && tipoDoc[k] === 'img';
  });
  var btn = document.getElementById('btn-imprimir-sel');
  if (btn) {
    btn.disabled = !haySeleccion;
    btn.style.background = haySeleccion ? '#7b0f2b' : '';
  }
}

function seleccionarTodos() {
  Object.keys(seleccion).forEach(function(key) {
    if (tipoDoc[key] !== 'pdf') {
      seleccion[key] = true;
      var card = document.getElementById('card-' + key);
      var chk  = document.getElementById('chk-' + key);
      if (card) card.classList.add('selected');
      if (chk) { chk.style.background='#7b0f2b'; chk.style.borderColor='#7b0f2b'; chk.style.color='#fff'; }
    }
  });
  actualizarBoton();
}

function deseleccionarTodos() {
  Object.keys(seleccion).forEach(function(key) {
    seleccion[key] = false;
    var card = document.getElementById('card-' + key);
    var chk  = document.getElementById('chk-' + key);
    if (card) card.classList.remove('selected');
    if (chk) { chk.style.background=''; chk.style.borderColor=''; chk.style.color='transparent'; }
  });
  actualizarBoton();
}

function imprimirSeleccionados() {
  // Mostrar solo las páginas de los docs seleccionados
  Object.keys(seleccion).forEach(function(key) {
    var pag = document.getElementById('pag-' + key);
    if (pag) {
      pag.style.display = seleccion[key] ? 'block' : 'none';
    }
  });
  window.print();
  // Después de imprimir, ocultar todo de nuevo
  setTimeout(function() {
    Object.keys(seleccion).forEach(function(key) {
      var pag = document.getElementById('pag-' + key);
      if (pag) pag.style.display = 'none';
    });
  }, 500);
}
</script>
</body>
</html>
