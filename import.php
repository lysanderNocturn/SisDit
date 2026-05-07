<?php
require "seguridad.php";
require_once "php/db.php";

if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['Administrador'])) {
    header("Location: acceso.php?error=no_autorizado");
    exit();
}

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo'])) {
    $file = $_FILES['archivo']['tmp_name'];
    $tipo = $_POST['tipo_import'];

    if (($handle = fopen($file, "r")) !== FALSE) {
        $row = 0;
        $imported = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row++;
            if ($row == 1) continue; // Skip header
            // Convert encoding to UTF-8 (assuming input is ISO-8859-1)
            $data = array_map('utf8_encode', $data);

            if ($tipo == 'cp') {
                // Importar códigos postales: cp, descripcion
                $cp = trim($data[0]);
                $desc = isset($data[1]) ? trim($data[1]) : '';

                $stmt = $conn->prepare("INSERT INTO codigos_postales (cp, descripcion) VALUES (?, ?) ON DUPLICATE KEY UPDATE descripcion=VALUES(descripcion)");
                $stmt->bind_param("ss", $cp, $desc);
                $stmt->execute();
                $stmt->close();
            } elseif ($tipo == 'colonias') {
                // Importar colonias: cp, colonia, tipo_asentamiento
                $cp = trim($data[0]);
                $colonia = trim($data[1]);
                $tipo_asentamiento = isset($data[2]) ? trim($data[2]) : NULL;

                // Obtener cp_id
                $stmt_cp = $conn->prepare("SELECT id FROM codigos_postales WHERE cp = ?");
                $stmt_cp->bind_param("s", $cp);
                $stmt_cp->execute();
                $result_cp = $stmt_cp->get_result();
                if ($result_cp->num_rows > 0) {
                    $cp_id = $result_cp->fetch_assoc()['id'];
                    $stmt = $conn->prepare("INSERT INTO colonias (nombre, cp_id, tipo_asentamiento) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nombre=nombre, tipo_asentamiento=VALUES(tipo_asentamiento)");
                    $stmt->bind_param("sis", $colonia, $cp_id, $tipo_asentamiento);
                    if ($stmt->execute()) {
                        $imported++;
                    } else {
                        // Log error if needed, but for now continue
                    }
                    $stmt->close();
                }
                $stmt_cp->close();
            } elseif ($tipo == 'calles') {
                // Importar calles: calle
                $calle = trim($data[0]);

                $stmt = $conn->prepare("INSERT INTO calles (nombre) VALUES (?) ON DUPLICATE KEY UPDATE nombre=nombre");
                $stmt->bind_param("s", $calle);
                $stmt->execute();
                $stmt->close();
            }
        }
        fclose($handle);
        $mensaje = "Importación completada. Filas leídas: " . ($row - 1) . ", importadas exitosamente: $imported.";
    } else {
        $mensaje = "Error al abrir el archivo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Datos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1>Importar Datos desde CSV</h1>
    <?php if ($mensaje): ?>
        <div class="alert alert-info"><?php echo $mensaje; ?></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="tipo_import" class="form-label">Tipo de importación</label>
            <select name="tipo_import" id="tipo_import" class="form-select" required>
                <option value="">Seleccionar...</option>
                <option value="cp">Códigos Postales (columnas: CP, Descripción)</option>
                <option value="colonias">Colonias (columnas: CP, Colonia, Tipo Asentamiento)</option>
                <option value="calles">Calles (columna: Calle)</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="archivo" class="form-label">Archivo CSV</label>
            <input type="file" name="archivo" id="archivo" class="form-control" accept=".csv" required>
        </div>
        <button type="submit" class="btn btn-primary">Importar</button>
    </form>
    <a href="DashAdmin.php" class="btn btn-secondary mt-3">Volver</a>
</div>
</body>
</html>