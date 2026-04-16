<?php
$geojsonPath = __DIR__ . '/Geojson/TRAMITES_reprojected.geojson';
if (file_exists($geojsonPath)) {
    $geojson = json_decode(file_get_contents($geojsonPath), true);
    if ($geojson && isset($geojson['features'])) {
        foreach ($geojson['features'] as &$feature) {
            if (isset($feature['properties']['tramites'])) {
                unset($feature['properties']['tramites']);
            }
        }
    }
    file_put_contents($geojsonPath, json_encode($geojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Limpieza completada.";
} else {
    echo "Archivo no encontrado.";
}
?>