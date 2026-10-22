<?php
// guardar_csv.php
// Recibe un CSV enviado desde la app (Contador de Pasajeros) y lo guarda
// en la carpeta "mediciones/" junto a este archivo.
//
// IMPORTANTE: cambia esta clave y usa la MISMA clave en index.html
// (constante SERVER_KEY), para que no cualquiera pueda escribir archivos
// en tu servidor a través de esta URL.
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

$content = isset($_POST['content']) ? $_POST['content'] : '';
if ($content === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Sin contenido para guardar']);
    exit;
}

// Solo el nombre de archivo, sin rutas (evita path traversal), y solo .csv
$filenameRaw = isset($_POST['filename']) ? $_POST['filename'] : ('medicion_' . date('Y-m-d_H-i-s') . '.csv');
$filename = basename($filenameRaw);
if (!preg_match('/\.csv$/i', $filename)) {
    $filename .= '.csv';
}

$dir = __DIR__ . '/mediciones/';
if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo crear la carpeta mediciones/']);
        exit;
    }
}

// Resolver colisión de nombre de forma atómica: intentar crear el archivo
// con O_CREAT|O_EXCL (falla si ya existe), y si falla, agregar sufijo único.
// Esto evita la race condition entre file_exists() + file_put_contents().
$base = pathinfo($filename, PATHINFO_FILENAME);
$path = $dir . $filename;

$fh = @fopen($path, 'x');  // 'x': falla si el archivo ya existe (O_EXCL)
if ($fh === false) {
    // El archivo ya existe; generar nombre único con microtime
    $suffix = '_' . str_replace('.', '', (string)microtime(true)) . '_' . bin2hex(random_bytes(3));
    $filename = $base . $suffix . '.csv';
    $path = $dir . $filename;
    $fh = fopen($path, 'x');
    if ($fh === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo crear el archivo CSV (revisa permisos de mediciones/)']);
        exit;
    }
}

// Escribir con lock exclusivo para máxima seguridad
flock($fh, LOCK_EX);
$bytes = fwrite($fh, $content);
fflush($fh);
flock($fh, LOCK_UN);
fclose($fh);

if ($bytes === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo escribir el archivo']);
    exit;
}

echo json_encode(['ok' => true, 'filename' => $filename, 'bytes' => $bytes]);
