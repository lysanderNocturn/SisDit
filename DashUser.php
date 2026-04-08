<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "seguridad.php";
require_once "php/db.php";

if (!isset($_SESSION['id'])) {
    header("Location: acceso.php");
    exit();
}

if ($_SESSION['rol'] !== 'Usuario') {
    header("Location: DashAdmin.php");
    exit();
}

$usuario_id = intval($_SESSION['id']);

$sql = "
SELECT t.*, 
       (SELECT h.estatus_nuevo 
        FROM historial_tramites h 
        WHERE h.tramite_id = t.id 
        ORDER BY h.id DESC 
        LIMIT 1) as estatus_actual
FROM tramites t
WHERE t.usuario_creador_id = ?
ORDER BY t.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

$stats = [
    'total' => 0,
    'en_revision' => 0,
    'aprobado' => 0,
    'rechazado' => 0
];

$tramites = [];

while ($row = $result->fetch_assoc()) {
    $tramites[] = $row;
    $stats['total']++;

    if ($row['estatus_actual'] == 'En revisión') $stats['en_revision']++;
    if ($row['estatus_actual'] == 'Aprobado') $stats['aprobado']++;
    if ($row['estatus_actual'] == 'Rechazado') $stats['rechazado']++;
}

$stmt->close();

// ===== MENSAJE AUTOMÁTICO WHATSAPP =====
$mensaje_whatsapp = urlencode("Hola, soy " . ($_SESSION['usuario'] ?? '') . " y necesito información sobre mi trámite.");
$numero_whatsapp = "5214491234567"; // <-- CAMBIA ESTE NÚMERO
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sis Dit</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root{
    --guindo: #6a0f2b;
    --guindo-oscuro: #4b0a1e;
    --guindo-claro: #8e1c3f;
}

body{
    background-color:#f8f4f6;
}

.navbar-guindo{
    background: linear-gradient(90deg, var(--guindo), var(--guindo-oscuro));
}

.card-guindo{
    border-left:5px solid var(--guindo);
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
    transition:0.3s;
}

.card-guindo:hover{
    transform:translateY(-3px);
}

.table thead{
    background-color: var(--guindo);
    color:white;
}

.whatsapp-float{
    position:fixed;
    bottom:25px;
    right:25px;
    background-color:#25D366;
    color:white;
    width:65px;
    height:65px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:32px;
    box-shadow:0 5px 15px rgba(0,0,0,0.3);
    z-index:1000;
    text-decoration:none;
    transition:0.3s;
}

.whatsapp-float:hover{
    transform:scale(1.1);
    background-color:#1ebe5d;
}
</style>
</head>

<body>

<nav class="navbar navbar-guindo navbar-dark">
<div class="container-fluid">
<span class="navbar-brand">
<i class="bi bi-person-circle"></i> Panel Usuario
</span>
<div>
<span class="text-white me-3">
<?= htmlspecialchars($_SESSION['usuario'] ?? '') ?>
</span>
<a href="logout.php" class="btn btn-light btn-sm">Cerrar sesión</a>
</div>
</div>
</nav>

<div class="container mt-4">

<div class="row mb-4">

<div class="col-md-3">
<div class="card card-guindo text-center">
<div class="card-body">
<h4><?= $stats['total'] ?></h4>
Total Trámites
</div>
</div>
</div>

<div class="col-md-3">
<div class="card card-guindo text-center">
<div class="card-body text-warning">
<h4><?= $stats['en_revision'] ?></h4>
En revisión
</div>
</div>
</div>

<div class="col-md-3">
<div class="card card-guindo text-center">
<div class="card-body text-success">
<h4><?= $stats['aprobado'] ?></h4>
Aprobados
</div>
</div>
</div>

<div class="col-md-3">
<div class="card card-guindo text-center">
<div class="card-body text-danger">
<h4><?= $stats['rechazado'] ?></h4>
Rechazados
</div>
</div>
</div>

</div>

<div class="card shadow-sm">
<div class="card-body">
<h5 class="mb-3">Mis Trámites</h5>

<table class="table table-bordered table-hover">
<thead>
<tr>
<th>Folio</th>
<th>Tipo</th>
<th>Fecha Ingreso</th>
<th>Estatus</th>
</tr>
</thead>
<tbody>

<?php if (!empty($tramites)): ?>
<?php foreach ($tramites as $t): 

$color = "secondary";
if ($t['estatus_actual'] == "En revisión") $color = "warning";
if ($t['estatus_actual'] == "Aprobado") $color = "success";
if ($t['estatus_actual'] == "Rechazado") $color = "danger";

?>

<tr>
<td><?= $t['folio_numero'] . "/" . $t['folio_anio'] ?></td>
<td><?= $t['tipo_tramite_id'] ?></td>
<td><?= $t['fecha_ingreso'] ?></td>
<td>
<span class="badge bg-<?= $color ?>">
<?= $t['estatus_actual'] ?? 'Sin estatus' ?>
</span>
</td>
</tr>

<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="4" class="text-center">
No tienes trámites registrados
</td>
</tr>
<?php endif; ?>

</tbody>
</table>

</div>
</div>

</div>

<!-- BOTÓN FLOTANTE WHATSAPP -->
<a href="https://wa.me/<?= $numero_whatsapp ?>?text=<?= $mensaje_whatsapp ?>"
   class="whatsapp-float"
   target="_blank">
   <i class="bi bi-whatsapp"></i>
</a>

</body>
</html>