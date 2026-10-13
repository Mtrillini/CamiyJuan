<?php
declare(strict_types=1);

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
