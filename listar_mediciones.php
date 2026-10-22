<?php
// listar_mediciones.php
// Devuelve todas las mediciones respaldadas (mediciones_json/*.json) como un
// arreglo JSON, para que el dashboard de la app pueda mostrar y graficar
// los datos de todos los encuestadores, no solo los del celular actual.
//
// IMPORTANTE: esta clave debe coincidir con SERVER_KEY en index.html,
// y con CLAVE_SECRETA en guardar_csv.php y guardar_medicion.php.
define('CLAVE_SECRETA', 'cambia-esta-clave');

header('Content-Type: application/json; charset=utf-8');

$clave = isset($_GET['clave']) ? $_GET['clave'] : (isset($_POST['clave']) ? $_POST['clave'] : '');
if (!hash_equals(CLAVE_SECRETA, $clave)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Clave incorrecta']);
    exit;
}

$dir = __DIR__ . '/mediciones_json/';
$mediciones = [];

if (is_dir($dir)) {
    foreach (glob($dir . '*.json') as $file) {
        // Usar flock compartido para leer: evita leer un archivo a medio
        // escribir si otro proceso está guardando en ese momento.
        $fh = @fopen($file, 'r');
        if ($fh === false) continue;

        if (!flock($fh, LOCK_SH)) {
            fclose($fh);
            continue;
        }

        $content = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        if ($content === false || $content === '') continue;

        $obj = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && $obj !== null) {
            $mediciones[] = $obj;
        }
    }
}

echo json_encode(['ok' => true, 'mediciones' => $mediciones]);
