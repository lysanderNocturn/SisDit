<?php
require_once "php/db.php";

$result = $conn->query("SELECT nombre FROM calles WHERE activo = 1 ORDER BY nombre");
$calles = [];
while ($row = $result->fetch_assoc()) {
    $calles[] = $row['nombre'];
}

echo json_encode($calles);
?>