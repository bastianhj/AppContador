<?php
// guardar_medicion.php
// Recibe el respaldo automático de una medición completa (meta + eventos, en JSON)
// y la guarda en mediciones_json/{tripId}.json. Si el tripId ya existe, lo
// sobrescribe (upsert), de modo que cada medición en curso se va actualizando
// en el mismo archivo a medida que se registran subidas, bajadas u observaciones.
//
// IMPORTANTE: esta clave debe coincidir con SERVER_KEY en index.html,
// y con CLAVE_SECRETA en guardar_csv.php y listar_mediciones.php.
define('CLAVE_SECRETA', 'cambia-esta-clave');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$clave = isset($_POST['clave']) ? $_POST['clave'] : '';
if (!hash_equals(CLAVE_SECRETA, $clave)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Clave incorrecta']);
    exit;
}

$tripId = isset($_POST['tripId']) ? $_POST['tripId'] : '';
$tripId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $tripId);
if ($tripId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta tripId']);
    exit;
}

$data = isset($_POST['data']) ? $_POST['data'] : '';
if ($data === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Sin contenido para guardar']);
    exit;
}

json_decode($data);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

$dir = __DIR__ . '/mediciones_json/';
if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo crear la carpeta mediciones_json/']);
        exit;
    }
}

$path = $dir . $tripId . '.json';

// Usar flock para escritura atómica: evita que dos backups simultáneos
// del mismo trip corrompan el archivo.
$fh = fopen($path, 'c');  // 'c': crea si no existe, no trunca al abrir
if ($fh === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo abrir el archivo para escritura']);
    exit;
}

if (!flock($fh, LOCK_EX)) {
    fclose($fh);
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'No se pudo obtener el lock de escritura']);
    exit;
}

ftruncate($fh, 0);
rewind($fh);
$bytes = fwrite($fh, $data);
fflush($fh);
flock($fh, LOCK_UN);
fclose($fh);

if ($bytes === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo escribir el archivo (revisa permisos de mediciones_json/)']);
    exit;
}

echo json_encode(['ok' => true, 'tripId' => $tripId, 'bytes' => $bytes]);
