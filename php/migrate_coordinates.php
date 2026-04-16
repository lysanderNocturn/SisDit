<?php
require_once "db.php";

header('Content-Type: text/plain; charset=utf-8');

// Función para convertir UTM a lat/lng (zona 13)
function utmToLatLng($easting, $northing, $zone = 13) {
    $a = 6378137.0;
    $f = 1/298.257223563;
    $k0 = 0.9996;
    $e = sqrt(2*$f - $f*$f);
    $e1sq = $e*$e / (1 - $e*$e);

    $x = $easting - 500000;
    $y = $northing;
    if ($y < 0) $y += 10000000;

    $M = $y / $k0;
    $mu = $M / ($a * (1 - $e*$e/4 - 3*$e*$e*$e*$e/64 - 5*$e*$e*$e*$e*$e*$e/256));

    $phi1 = $mu + (3*$e1sq/2 - 27*$e1sq*$e1sq*$e1sq/32) * sin(2*$mu) + (21*$e1sq*$e1sq/16 - 55*$e1sq*$e1sq*$e1sq*$e1sq/32) * sin(4*$mu) + (151*$e1sq*$e1sq*$e1sq/96) * sin(6*$mu);

    $N1 = $a / sqrt(1 - $e*$e * sin($phi1)*sin($phi1));
    $T1 = tan($phi1)*tan($phi1);
    $C1 = $e1sq * cos($phi1)*cos($phi1);
    $R1 = $a * (1 - $e*$e) / pow(1 - $e*$e * sin($phi1)*sin($phi1), 1.5);
    $D = $x / ($N1 * $k0);

    $lat = $phi1 - ($N1 * tan($phi1) / $R1) * ($D*$D/2 - (5 + 3*$T1 + 10*$C1 - 4*$C1*$C1 - 9*$e1sq) * $D*$D*$D*$D/24 + (61 + 90*$T1 + 298*$C1 + 45*$T1*$T1 - 252*$e1sq - 3*$C1*$C1) * $D*$D*$D*$D*$D*$D/720);
    $lon = ($zone * 6 - 183) * pi()/180 + ( $D - (1 + 2*$T1 + $C1) * $D*$D*$D/6 + (5 - 2*$C1 + 28*$T1 - 3*$C1*$C1 + 8*$e1sq + 24*$T1*$T1) * $D*$D*$D*$D*$D/120 ) / cos($phi1);

    return [rad2deg($lat), rad2deg($lon)];
}

try {
    // Obtener todos los trámites con coordenadas
    $sql = "SELECT id, lat, lng FROM tramites WHERE lat IS NOT NULL AND lng IS NOT NULL";
    $result = $conn->query($sql);
    
    $updated = 0;
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $utmX = $row['lat'];
        $utmY = $row['lng'];
        
        // Verificar si parecen UTM (valores grandes)
        if ($utmX > 1000 && $utmY > 1000000) {
            $coords = utmToLatLng($utmX, $utmY);
            $newLat = $coords[0];
            $newLng = $coords[1];
            
            $updateSql = "UPDATE tramites SET lat = ?, lng = ? WHERE id = ?";
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param("ddi", $newLat, $newLng, $id);
            $stmt->execute();
            $stmt->close();
            
            $updated++;
            echo "Updated tramite $id: UTM ($utmX, $utmY) -> LatLng ($newLat, $newLng)\n";
        } else {
            echo "Skipped tramite $id: already in degrees ($utmX, $utmY)\n";
        }
    }
    
    echo "\nMigration completed. Updated $updated records.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>