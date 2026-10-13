<?php
// Diagnóstico de la conexión con Google Drive.
// Abrir: https://camiyjuan.com/invitacion/api/probar-drive.php?clave=TU_CLAVE_DE_DRIVE
// (la misma clave que pusiste en drive_secret). Sube un archivo de prueba a la carpeta.
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$file = __DIR__ . '/config.php';
if (!is_file($file)) exit("FALTA api/config.php\n");
$c = require $file;
if (empty($c['drive_secret']) || !hash_equals((string)$c['drive_secret'], (string)($_GET['clave'] ?? ''))) {
    http_response_code(403);
    exit("Clave incorrecta. Usá ?clave= con el mismo valor de drive_secret.\n");
}

echo "PHP " . PHP_VERSION . "\n";
echo "curl: " . (function_exists('curl_init') ? 'sí' : 'NO (pedile al hosting que active la extensión curl)') . "\n";
$url = (string)($c['drive_script_url'] ?? '');
echo "drive_script_url: " . ($url === '' ? 'VACÍA' : $url) . "\n";
if (!str_ends_with($url, '/exec')) echo "  ⚠ La URL tiene que terminar en /exec\n";
if ($url === '' || !function_exists('curl_init')) exit;

// PNG de 1x1 píxel como prueba.
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['secret' => $c['drive_secret'], 'name' => 'prueba-servidor.png', 'mimeType' => 'image/png', 'author' => 'diagnóstico', 'data' => base64_encode($png)]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 60,
]);
$body = curl_exec($ch);
echo "\nHTTP: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
if ($body === false) echo "Error de curl: " . curl_error($ch) . "\n";
echo "Respuesta de Google:\n" . substr((string)$body, 0, 1500) . "\n";
