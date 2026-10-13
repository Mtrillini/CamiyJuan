<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/drive.php';
guard();

// Recibe UNA foto por envío, la guarda en uploads/ (copia de respaldo)
// y la manda a la carpeta de Google Drive a través del Apps Script.
const MAX_BYTES = 10 * 1024 * 1024;
const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heif'];

// Se habilita el día anterior al evento (hora Argentina).
if (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')) < new DateTimeImmutable('2026-12-07 00:00', new DateTimeZone('America/Argentina/Buenos_Aires'))) {
    respond(403, ['ok' => false, 'error' => 'Podés subir fotos desde el 7 de diciembre.']);
}

$nombre = text('nombre', 100, true);
$file = $_FILES['foto'] ?? null;
if (!$file || is_array($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
    respond(422, ['ok' => false, 'error' => 'No llegó la foto. Probá de nuevo.']);
}

$tmp = $file['tmp_name'];
$size = (int)$file['size'];
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
// Validamos el contenido real del archivo, no la extensión que manda el navegador.
// HEIC/HEIF (iPhone) no lo lee getimagesize, así que alcanza con el tipo detectado.
$isImage = isset(TYPES[$mime]) && (str_starts_with($mime, 'image/hei') || @getimagesize($tmp) !== false);
if ($size > MAX_BYTES || !$isImage) {
    respond(422, ['ok' => false, 'error' => 'La foto tiene que ser una imagen de hasta 10 MB.']);
}

$dir = rtrim(config()['uploads_dir'], '/');
if (!is_dir($dir) && !mkdir($dir, 0755, true)) respond(500, ['ok' => false, 'error' => 'No pudimos guardar la foto.']);
$name = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . TYPES[$mime];
if (!move_uploaded_file($tmp, "$dir/$name")) respond(500, ['ok' => false, 'error' => 'No pudimos guardar la foto.']);

$driveId = sendToDrive("$dir/$name", $name, $mime, $nombre);

db()->prepare('INSERT INTO fotos (nombre, archivo, original, bytes, drive_id) VALUES (?, ?, ?, ?, ?)')
    ->execute([$nombre, $name, mb_substr(basename((string)$file['name']), 0, 200), $size, $driveId]);

respond(200, ['ok' => true]);
