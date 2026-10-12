<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
guard();

const MAX_FILES = 20;
const MAX_BYTES = 10 * 1024 * 1024;
const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

$nombre = text('nombre', 100, true);
$files = $_FILES['fotos'] ?? null;
if (!$files || !is_array($files['name'])) respond(422, ['ok' => false, 'error' => 'Elegí al menos una foto.']);
$count = count($files['name']);
if ($count < 1 || $count > MAX_FILES) respond(422, ['ok' => false, 'error' => 'Podés subir hasta ' . MAX_FILES . ' fotos por vez.']);

$dir = rtrim(config()['uploads_dir'], '/');
if (!is_dir($dir) && !mkdir($dir, 0755, true)) respond(500, ['ok' => false, 'error' => 'No pudimos guardar las fotos.']);

$finfo = new finfo(FILEINFO_MIME_TYPE);
$insert = db()->prepare('INSERT INTO fotos (nombre, archivo, original, bytes) VALUES (?, ?, ?, ?)');
$saved = 0;

for ($i = 0; $i < $count; $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
    $tmp = $files['tmp_name'][$i];
    $size = (int)$files['size'][$i];
    $mime = $finfo->file($tmp);
    // Validamos el contenido real del archivo, no la extensión que manda el navegador.
    if ($size > MAX_BYTES || !isset(TYPES[$mime]) || @getimagesize($tmp) === false) continue;
    $name = bin2hex(random_bytes(16)) . '.' . TYPES[$mime];
    if (!move_uploaded_file($tmp, "$dir/$name")) continue;
    $insert->execute([$nombre, $name, mb_substr(basename((string)$files['name'][$i]), 0, 200), $size]);
    $saved++;
}

if ($saved === 0) respond(422, ['ok' => false, 'error' => 'No se pudo subir ninguna foto. Usá JPG, PNG o WebP de hasta 10 MB.']);
respond(200, ['ok' => true, 'guardadas' => $saved]);
