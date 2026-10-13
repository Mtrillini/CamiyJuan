<?php
// Manda a Google Drive las fotos que quedaron sin subir (drive_id NULL).
// Abrir: https://camiyjuan.com/invitacion/api/reenviar-drive.php?clave=TU_CLAVE_DE_DRIVE
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/drive.php';
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');
set_time_limit(300);

$c = config();
if (empty($c['drive_secret']) || !hash_equals((string)$c['drive_secret'], (string)($_GET['clave'] ?? ''))) {
    http_response_code(403);
    exit("Clave incorrecta.\n");
}

$dir = rtrim($c['uploads_dir'], '/');
$fotos = db()->query('SELECT id, nombre, archivo FROM fotos WHERE drive_id IS NULL ORDER BY id LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);
if (!$fotos) exit("No hay fotos pendientes. Todo está en Drive.\n");

$update = db()->prepare('UPDATE fotos SET drive_id = ? WHERE id = ?');
$finfo = new finfo(FILEINFO_MIME_TYPE);
foreach ($fotos as $f) {
    $path = "$dir/" . basename($f['archivo']);
    if (!is_file($path)) { echo "✗ {$f['archivo']}: no está en uploads/\n"; continue; }
    $id = sendToDrive($path, $f['archivo'], (string)$finfo->file($path), $f['nombre']);
    if ($id === null) { echo "✗ {$f['archivo']}: falló el envío\n"; continue; }
    $update->execute([$id, $f['id']]);
    echo "✓ {$f['archivo']} ({$f['nombre']})\n";
}
echo "\nListo. Si quedan más, recargá la página.\n";
