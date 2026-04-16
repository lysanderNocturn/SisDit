<?php
require_once "db.php";

header('Content-Type: application/json; charset=utf-8');

// Función para convertir lat/lng a UTM (zona 13)
function latLngToUtm($lat, $lon, $zone = 13) {
    $a = 6378137.0;
    $f = 1/298.257223563;
    $k0 = 0.9996;
    $e = sqrt(2*$f - $f*$f);
    $latRad = deg2rad($lat);
    $lonRad = deg2rad($lon);
    $lon0 = deg2rad($zone * 6 - 183);

    $N = $a / sqrt(1 - $e*$e * sin($latRad)*sin($latRad));
    $T = tan($latRad)*tan($latRad);
    $C = $e*$e * cos($latRad)*cos($latRad) / (1 - $e*$e);
    $A = ($lonRad - $lon0) * cos($latRad);

    $M = $a * ((1 - $e*$e/4 - 3*$e*$e*$e*$e/64 - 5*$e*$e*$e*$e*$e*$e/256) * $latRad - (3*$e*$e/8 + 3*$e*$e*$e*$e/32 + 45*$e*$e*$e*$e*$e*$e/1024) * sin(2*$latRad) + (15*$e*$e*$e*$e/256 + 45*$e*$e*$e*$e*$e*$e/1024) * sin(4*$latRad) - (35*$e*$e*$e*$e*$e*$e/3072) * sin(6*$latRad));

    $utmE = $k0 * $N * ($A + (1 - $T + $C) * $A*$A*$A/6 + (5 - 18*$T + $T*$T + 72*$C - 58*$e*$e) * $A*$A*$A*$A*$A/120) + 500000;
    $utmN = $k0 * ($M + $N * tan($latRad) * ($A*$A/2 + (5 - $T + 9*$C + 4*$C*$C) * $A*$A*$A*$A/24 + (61 - 58*$T + $T*$T + 600*$C - 330*$e*$e) * $A*$A*$A*$A*$A*$A/720));

    if ($lat < 0) $utmN += 10000000;

    return [$utmE, $utmN];
}

try {
    $sql = "
        SELECT
            CONCAT(LPAD(t.folio_numero, 3, '0'), '/', t.folio_anio) AS FOLIO_INGR,
            t.solicitante AS NOM_SOLI,
            tt.nombre AS TIP_TRAMIT,
            t.direccion AS UBICACION,
            DATE_FORMAT(t.fecha_ingreso, '%Y-%m-%d') AS FECH_INGRE,
            DATE_FORMAT(t.fecha_entrega, '%Y-%m-%d') AS FECH_ENTRE,
            t.estatus AS ESTATUS,
            t.telefono AS CONTACTO,
            t.numero_asignado AS NUMERO,
            t.lat,
            t.lng
        FROM tramites t
        LEFT JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
        WHERE t.lat IS NOT NULL AND t.lng IS NOT NULL
        ORDER BY t.created_at DESC
    ";

    $result = $conn->query($sql);
    $features = [];

    while ($row = $result->fetch_assoc()) {
        $utm = latLngToUtm($row['lat'], $row['lng']);
        $features[] = [
            'type' => 'Feature',
            'properties' => [
                'FOLIO_INGR' => $row['FOLIO_INGR'],
                'NOM_SOLI' => $row['NOM_SOLI'] ?? 'N/A',
                'TIP_TRAMIT' => $row['TIP_TRAMIT'] ?? 'N/A',
                'UBICACION' => $row['UBICACION'] ?? 'N/A',
                'FECH_INGRE' => $row['FECH_INGRE'] ?? 'N/A',
                'FECH_ENTRE' => $row['FECH_ENTRE'] ?? 'N/A',
                'ESTATUS' => $row['ESTATUS'] ?? 'N/A',
                'X' => round($utm[0], 2),
                'Y' => round($utm[1], 2),
                'CONTACTO' => $row['CONTACTO'] ?? 'N/A',
                'NUMERO' => $row['NUMERO'] ?? 'N/A'
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float) $row['lng'], (float) $row['lat']] // [lng, lat]
            ]
        ];
    }

    $geojson = [
        'type' => 'FeatureCollection',
        'name' => 'TRAMITES_' . date('Y'),
        'features' => $features      

    ];

    echo json_encode($geojson, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>