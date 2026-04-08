<?php
// =====================================================
// BUSCAR CUENTA CATASTRAL (AJAX)
// Busca predios por su número de cuenta catastral para autocompletar
// =====================================================
require_once "db.php";

header('Content-Type: application/json');

if(isset($_GET['cuenta'])){

    $cuenta = trim($_GET['cuenta']);

    $stmt = $conn->prepare("SELECT utm_x, utm_y FROM tramites WHERE cuenta_catastral = ?");
    $stmt->bind_param("s", $cuenta);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0){
        echo json_encode($result->fetch_assoc());
    } else {
        echo json_encode(null);
    }

    $stmt->close();
    $conn->close();
}
?>