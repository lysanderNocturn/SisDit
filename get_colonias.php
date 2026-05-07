<?php
require_once "php/db.php";

$cp = $_GET['cp'] ?? '';

if (empty($cp)) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT c.nombre
    FROM colonias c
    JOIN codigos_postales cp ON c.cp_id = cp.id
    WHERE cp.cp = ? AND c.activo = 1
    ORDER BY c.nombre
");
$stmt->bind_param("s", $cp);
$stmt->execute();
$result = $stmt->get_result();

$colonias = [];
while ($row = $result->fetch_assoc()) {
    $colonias[] = $row['nombre'];
}

echo json_encode($colonias);
$stmt->close();
?>