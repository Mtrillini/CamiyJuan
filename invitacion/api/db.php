<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function config(): array {
    static $config;
    if ($config === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) respond(500, ['ok' => false, 'error' => 'Falta configurar la base de datos.']);
        $config = require $file;
    }
    return $config;
}

function db(): PDO {
    static $pdo;
    if ($pdo === null) {
        $c = config();
        try {
            $pdo = new PDO(
                "mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4",
                $c['db_user'], $c['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
            );
        } catch (PDOException $e) {
            error_log('DB: ' . $e->getMessage());
            respond(500, ['ok' => false, 'error' => 'No pudimos guardar. Probá de nuevo en un rato.']);
        }
    }
    return $pdo;
}

// Columna menu_tipo (adulto / infantil): se agrega sola si la base es anterior.
function asegurarMenuTipo(PDO $pdo): void {
    try { $pdo->query('SELECT menu_tipo FROM personas LIMIT 1'); }
    catch (PDOException $e) { $pdo->exec("ALTER TABLE personas ADD COLUMN menu_tipo VARCHAR(10) NULL"); }
}

// Solo POST, y el campo trampa vacío (los bots lo completan).
function guard(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['ok' => false, 'error' => 'Método no permitido.']);
    if (!empty($_POST['website'])) respond(200, ['ok' => true]);
}

function text(string $name, int $max, bool $required = false): string {
    $value = trim((string)($_POST[$name] ?? ''));
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    if ($required && $value === '') respond(422, ['ok' => false, 'error' => 'Completá todos los campos obligatorios.']);
    if (mb_strlen($value) > $max) respond(422, ['ok' => false, 'error' => 'Uno de los campos es demasiado largo.']);
    return $value;
}

function longText(string $name, int $max, bool $required = false): string {
    $value = trim((string)($_POST[$name] ?? ''));
    if ($required && $value === '') respond(422, ['ok' => false, 'error' => 'Completá todos los campos obligatorios.']);
    if (mb_strlen($value) > $max) respond(422, ['ok' => false, 'error' => 'El texto es demasiado largo.']);
    return $value;
}
