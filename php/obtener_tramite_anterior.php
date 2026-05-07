<?php
// =====================================================
// OBTENER TRÁMITE ANTERIOR DEL MISMO PREDIO (AJAX)
// Busca si el predio ya tuvo trámites antes para pre-llenar datos
// =====================================================
session_start();
require_once "db.php";

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['id']) || !isset($_SESSION['usuario'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$folio = isset($_GET['folio']) ? trim($_GET['folio']) : '';
$propietario = isset($_GET['propietario']) ? trim($_GET['propietario']) : '';
$tipo_tramite_id = isset($_GET['tipo_tramite_id']) ? intval($_GET['tipo_tramite_id']) : 0;
$incluir_constancia = isset($_GET['incluir_constancia']) ? $_GET['incluir_constancia'] === 'true' : false;
$buscar_por_folio_salida = isset($_GET['buscar_por_folio_salida']) ? $_GET['buscar_por_folio_salida'] === 'true' : false;

if (empty($folio) && empty($propietario)) {
    echo json_encode(['error' => 'Se requiere folio o propietario']);
    exit;
}

// Construir consulta
$sql = "SELECT 
            t.*,
            CONCAT(LPAD(t.folio_numero, 3, '0'), '/', t.folio_anio) as folio_formateado,
            CONCAT(LPAD(t.folio_salida_numero, 3, '0'), '/', t.folio_salida_anio) as folio_salida_formateado,
            tt.nombre as tipo_tramite_nombre
        FROM tramites t
        LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
        WHERE 1=1";

$params = [];
$types = "";

if (!empty($folio)) {
    $partes = explode('/', $folio);
    if (count($partes) == 2) {
        if ($buscar_por_folio_salida) {
            // Buscar por folio de salida
            $sql .= " AND t.folio_salida_numero = ? AND t.folio_salida_anio = ?";
            $params[] = intval($partes[0]);
            $params[] = intval($partes[1]);
            $types .= "ii";
        } else {
            // Buscar por folio de ingreso (original)
            $sql .= " AND t.folio_numero = ? AND t.folio_anio = ?";
            $params[] = intval($partes[0]);
            $params[] = intval($partes[1]);
            $types .= "ii";
        }
    } else {
        echo json_encode(['error' => 'Formato de folio inválido']);
        exit;
    }
} elseif (!empty($propietario) && $tipo_tramite_id > 0) {
    $sql .= " AND t.propietario LIKE ? AND t.tipo_tramite_id = ? 
              AND t.estatus IN ('Aprobado', 'Aprobado por Verificador')
              ORDER BY t.fecha_ingreso DESC LIMIT 1";
    $params[] = '%' . $propietario . '%';
    $params[] = $tipo_tramite_id;
    $types .= "si";
} else {
    echo json_encode(['error' => 'Parámetros insuficientes']);
    exit;
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$tramite = $result->fetch_assoc();
$stmt->close();

if (!$tramite) {
    echo json_encode(['error' => 'No se encontró el trámite']);
    exit;
}

// Preparar datos para devolver
$datos = [
    'success' => true,
    'tramite' => [
        // Datos generales
        'folio' => $tramite['folio_formateado'],
        'folio_salida' => $tramite['folio_salida_formateado'] ?? '',
        'propietario' => $tramite['propietario'],
        'direccion' => $tramite['direccion'],
        'localidad' => $tramite['localidad'],
        'colonia' => $tramite['colonia'],
        'cp' => $tramite['cp'],
        'calle' => $tramite['calle'],
        'entre_calle1' => $tramite['entre_calle1'],
        'entre_calle2' => $tramite['entre_calle2'],
        'cuenta_catastral' => $tramite['cuenta_catastral'],
        'superficie' => $tramite['superficie'],
        'lat' => $tramite['lat'],
        'lng' => $tramite['lng'],
        'solicitante' => $tramite['solicitante'],
        'telefono' => $tramite['telefono'],
        'correo' => $tramite['correo'],
        'observaciones' => $tramite['observaciones'],
        
        // Documentos subidos
        'archivos' => [
            'ine_archivo' => $tramite['ine_archivo'],
            'escrituras_archivo' => $tramite['escrituras_archivo'] ?: $tramite['titulo_archivo'],
            'predial_archivo' => $tramite['predial_archivo'],
            'formato_constancia' => $tramite['formato_constancia'],
            'foto1_archivo' => $tramite['foto1_archivo'],
            'foto2_archivo' => $tramite['foto2_archivo'],
            'croquis_archivo' => $tramite['croquis_archivo']
        ],
        
        // Datos de constancia (solo si se solicitan)
        'constancia' => $incluir_constancia ? [
            'numero_asignado' => $tramite['numero_asignado'],
            'tipo_asignacion' => $tramite['tipo_asignacion'],
            'referencia_anterior' => $tramite['referencia_anterior'],
            'entre_calle1' => $tramite['entre_calle1'],
            'entre_calle2' => $tramite['entre_calle2'],
            'manzana' => $tramite['manzana'],
            'lote' => $tramite['lote'],
            'fecha_constancia' => $tramite['fecha_constancia'],
            'cuenta_catastral_constancia' => $tramite['cuenta_catastral']
        ] : null
    ]
];

echo json_encode($datos);
$conn->close();
?>