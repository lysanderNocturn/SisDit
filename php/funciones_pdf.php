<?php
// =====================================================
// GENERACIÓN DE PDFs CON mPDF
// Funciones para generar los documentos oficiales en PDF.
// Requiere composer require mpdf/mpdf antes de usar
// =====================================================
/**
 * FUNCIONES PARA GENERAR PDFs
 * Archivo: php/funciones_pdf.php
 * Descripción: Generación de documentos PDF de trámites
 * 
 * IMPORTANTE: Instalar mPDF primero con:
 * composer require mpdf/mpdf
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'db.php';

/**
 * Generar PDF de Constancia de Número Oficial
 */
function generarPDFNumeroOficial($tramite_id) {
    global $conn;
    
    // Obtener datos del trámite
    $sql = "SELECT t.*, tt.nombre as tipo_tramite_nombre, 
                   u.nombre as creador_nombre, u.apellidos as creador_apellidos
            FROM tramites t
            INNER JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
            INNER JOIN usuarios u ON t.usuario_creador_id = u.id
            WHERE t.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $tramite_id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 0) {
        throw new Exception("Trámite no encontrado");
    }
    
    $tramite = $resultado->fetch_assoc();
    $stmt->close();
    
    // Obtener configuración
    $municipio = obtenerConfiguracion('municipio_nombre');
    $director = obtenerConfiguracion('director_nombre');
    $director_cargo = obtenerConfiguracion('director_cargo');
    
    // Crear instancia de mPDF
    $mpdf = new \Mpdf\Mpdf([
        'format' => 'Letter',
        'margin_left' => 20,
        'margin_right' => 20,
        'margin_top' => 25,
        'margin_bottom' => 25,
        'margin_header' => 10,
        'margin_footer' => 10
    ]);
    
    // Construir HTML del documento
    $html = construirHTMLNumeroOficial($tramite, $municipio, $director, $director_cargo);
    
    // Escribir HTML
    $mpdf->WriteHTML($html);
    
    // Generar nombre de archivo
    $folio = str_pad($tramite['folio_numero'], 3, '0', STR_PAD_LEFT) . '-' . $tramite['folio_anio'];
    $nombreArchivo = "constancia_numero_oficial_{$folio}.pdf";
    
    // Guardar PDF
    $rutaPDF = __DIR__ . "/../uploads/pdfs/";
    if (!is_dir($rutaPDF)) {
        mkdir($rutaPDF, 0755, true);
    }
    
    $mpdf->Output($rutaPDF . $nombreArchivo, \Mpdf\Output\Destination::FILE);
    
    return $nombreArchivo;
}

/**
 * Construir HTML para Constancia de Número Oficial
 */
function construirHTMLNumeroOficial($tramite, $municipio, $director, $director_cargo) {
    
    $folio = str_pad($tramite['folio_numero'], 3, '0', STR_PAD_LEFT) . '/' . $tramite['folio_anio'];
    $fecha = date('d \d\e F \d\e Y', strtotime($tramite['created_at']));
    
    // Datos específicos del trámite
    $numero_asignado = $tramite['datos_especificos'] ? 
                       json_decode($tramite['datos_especificos'], true)['numero_oficial'] ?? 'S/N' : 
                       'S/N';
    
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .titulo {
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            text-transform: uppercase;
        }
        .folio {
            text-align: right;
            font-weight: bold;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table td, table th {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .centrado {
            text-align: center;
        }
        .firma {
            margin-top: 80px;
            text-align: center;
        }
        .firma-linea {
            border-top: 2px solid #000;
            width: 50%;
            margin: 0 auto;
            margin-top: 60px;
        }
        .nota {
            font-size: 9pt;
            margin-top: 30px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="logos/logo_urbano.jpeg" style="width: 80px; float: left;">
        <img src="logos/logo_presi.jpeg" style="width: 80px; float: right;">
        <div style="clear: both;"></div>
        <h3>RINCÓN DE ROMOS<br>REQUISITOS PARA REALIZAR EL TRÁMITE</h3>
    </div>
    <div class="folio">FOLIO: {$folio}</div>
    <div class="titulo">CONSTANCIA DE NÚMERO OFICIAL</div>
    <table>
        <tr>
            <th colspan="3">ASIGNACIÓN</th>
            <th>RECTIFICACIÓN</th>
            <th>REPOSICIÓN</th>
        </tr>
        <tr>
            <td class="centrado">SE ASIGNA EL NÚMERO</td>
            <td class="centrado" colspan="2"><strong>{$numero_asignado}</strong></td>
            <td class="centrado">REFERENCIA ANTERIOR</td>
            <td></td>
        </tr>
    </table>
    <table>
        <tr>
            <td colspan="2"><strong>CALLE:</strong> {$tramite['direccion']}</td>
        </tr>
        <tr>
            <td><strong>ENTRE CALLES:</strong></td>
            <td><strong>NIÑOS HÉROES Y LA PEDRERA</strong></td>
        </tr>
        <tr>
            <td><strong>UBICACIÓN:</strong> {$tramite['localidad']}</td>
            <td><strong>LOTE:</strong></td>
        </tr>
        <tr>
            <td><strong>COLONIA Y/O FRACCIONAMIENTO:</strong> {$tramite['colonia']}</td>
            <td><strong>NORTE</strong></td>
        </tr>
        <tr>
            <td><strong>POBLADO:</strong> {$municipio}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2"><strong>CÓDIGO POSTAL:</strong> {$tramite['cp']}</td>
        </tr>
        <tr>
            <td colspan="2"><strong>CUENTA CATASTRAL No.:</strong> {$tramite['cuenta_catastral']}</td>
        </tr>
    </table>
    
    <table>
        <tr>
            <td colspan="2"><strong>SE EXTIENDE A NOMBRE DE:</strong> {$tramite['propietario']}</td>
        </tr>
    </table>
    
    <table>
        <tr>
            <td colspan="2">
                <strong>LUGAR Y FECHA DE EXPEDICIÓN:</strong><br>
                {$fecha}<br>
                {$municipio}, AGS.
            </td>
        </tr>
    </table>
    
    <div class="nota">
        <strong>NOTA:</strong><br>
        1.- EL NÚMERO OFICIAL DEBERÁ COLOCARSE EN PARTE VISIBLE DEL FRENTE DEL PREDIO, Y DEBERÁ SER
        LEGIBLEMENTE DE UN TAMAÑO NO MENOR A 10 CMS. DE ALTURA.<br>
        2.- ESTRICTAMENTE NO CONSTITUYE APOYO O DESLINDE AL RESPECTO DEL INMUEBLE, NI ACREDITA LA PROPIEDAD O POSESIÓN DEL MISMO.<br>
        3.- ESTRICTAMENTE LA CONSTANCIA EN EL ARTÍCULO 6-24 FRACC. II INCISO I DEL CÓDIGO DE POLICÍA Y GOBIERNO DE RINCÓN DE ROMOS, AGS.
    </div>
    
    <div class="firma">
        <strong>ATENTAMENTE:</strong>
        <div class="firma-linea"></div>
        <strong>{$director}</strong><br>
        {$director_cargo}<br>
        DEL MUNICIPIO DE {$municipio}, AGS.<br>
        C.C.P. ARCHIVO.
    </div>
</body>
</html>
HTML;
    
    return $html;
}

/**
 * Generar PDF según tipo de trámite
 */
function generarPDFTramite($tramite_id) {
    global $conn;
    
    // Obtener tipo de trámite
    $sql = "SELECT tt.codigo 
            FROM tramites t
            INNER JOIN tipos_tramite tt ON t.tipo_tramite_id = tt.id
            WHERE t.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $tramite_id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 0) {
        throw new Exception("Trámite no encontrado");
    }
    
    $tipo = $resultado->fetch_assoc()['codigo'];
    $stmt->close();
    
    // Llamar función específica según tipo
    switch ($tipo) {
        case 'NUM_OFICIAL':
            return generarPDFNumeroOficial($tramite_id);
        
        case 'CMCU':
            return generarPDFCMCU($tramite_id);
        
        case 'FUSION':
            return generarPDFFusion($tramite_id);
        
        case 'SUBDIVISION':
            return generarPDFSubdivision($tramite_id);
        
        default:
            throw new Exception("Tipo de trámite no soportado para PDF");
    }
}

/**
 * Descargar PDF de trámite
 */
function descargarPDF($tramite_id) {
    try {
        $nombreArchivo = generarPDFTramite($tramite_id);
        $rutaCompleta = __DIR__ . "/../uploads/pdfs/" . $nombreArchivo;
        
        if (!file_exists($rutaCompleta)) {
            throw new Exception("Archivo PDF no encontrado");
        }
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Content-Length: ' . filesize($rutaCompleta));
        readfile($rutaCompleta);
        exit;
        
    } catch (Exception $e) {
        error_log("Error al descargar PDF: " . $e->getMessage());
        die("Error al generar PDF: " . $e->getMessage());
    }
}

// Funciones placeholder para otros tipos (implementar después)
function generarPDFCMCU($tramite_id) {
    // TODO: Implementar
    throw new Exception("Función no implementada aún");
}

function generarPDFFusion($tramite_id) {
    // TODO: Implementar
    throw new Exception("Función no implementada aún");
}

function generarPDFSubdivision($tramite_id) {
    // TODO: Implementar
    throw new Exception("Función no implementada aún");
}
?>
