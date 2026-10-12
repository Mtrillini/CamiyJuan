<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
guard();

// Recibe UNA foto por envío, la guarda en uploads/ (copia de respaldo)
// y la manda a la carpeta de Google Drive a través del Apps Script.
const MAX_BYTES = 10 * 1024 * 1024;
const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heif'];

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

// Devuelve el id del archivo en Drive, o null si Drive no está configurado o falló.
// Si falla, la foto igual queda guardada en uploads/ y se puede reenviar después.
function sendToDrive(string $path, string $name, string $mime, string $autor): ?string {
    $c = config();
    if (empty($c['drive_script_url']) || empty($c['drive_secret'])) return null;
    $payload = json_encode([
        'secret' => $c['drive_secret'],
        'name' => $name,
        'mimeType' => $mime,
        'author' => $autor,
        'data' => base64_encode((string)file_get_contents($path)),
    ]);
    $ch = curl_init($c['drive_script_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true, // Apps Script responde con una redirección
        CURLOPT_TIMEOUT => 60,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $result = is_string($body) ? json_decode($body, true) : null;
    if ($status !== 200 || empty($result['ok'])) {
        error_log("Drive: fallo al subir $name (HTTP $status): " . substr((string)$body, 0, 300));
        return null;
    }
    return (string)$result['id'];
}
