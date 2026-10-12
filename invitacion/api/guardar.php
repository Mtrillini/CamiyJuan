<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
guard();

$nombre = text('nombre', 100, true);

switch ($_POST['tipo'] ?? '') {
    case 'rsvp':
        $asistencia = ($_POST['asistencia'] ?? '') === 'no' ? 'no' : 'si';
        $acompanantes = [];
        if ($asistencia === 'si') {
            $cantidad = filter_var($_POST['acompanantes'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9]]);
            if ($cantidad === false) respond(422, ['ok' => false, 'error' => 'La cantidad de acompañantes tiene que ser entre 0 y 9.']);
            $nombres = $_POST['acompanante_nombre'] ?? [];
            if (!is_array($nombres) || count($nombres) !== $cantidad) respond(422, ['ok' => false, 'error' => 'Completá los datos de todos los acompañantes.']);
            foreach (array_values($nombres) as $i => $n) {
                $n = preg_replace('/\s+/u', ' ', trim((string)$n)) ?? '';
                if ($n === '' || mb_strlen($n) > 100) respond(422, ['ok' => false, 'error' => 'Completá el nombre y apellido de todos los acompañantes.']);
                $tipo = ($_POST["acompanante_tipo_$i"] ?? '') === 'nino' ? 'nino' : 'adulto';
                $acompanantes[] = [$n, $tipo];
            }
        }
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO confirmaciones (nombre, asistencia, personas, telefono, menu, comentarios) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$nombre, $asistencia, $asistencia === 'si' ? 1 + count($acompanantes) : 0, text('telefono', 30), text('menu', 50), longText('comentarios', 1000)]);
        $id = (int)$pdo->lastInsertId();
        $insert = $pdo->prepare('INSERT INTO acompanantes (confirmacion_id, nombre, tipo) VALUES (?, ?, ?)');
        foreach ($acompanantes as [$n, $tipo]) $insert->execute([$id, $n, $tipo]);
        $pdo->commit();
        break;
    case 'song':
        db()->prepare('INSERT INTO canciones (nombre, cancion, artista) VALUES (?, ?, ?)')
            ->execute([$nombre, text('cancion', 150, true), text('artista', 150)]);
        break;
    case 'message':
        db()->prepare('INSERT INTO mensajes (nombre, mensaje) VALUES (?, ?)')
            ->execute([$nombre, longText('mensaje', 2000, true)]);
        break;
    default:
        respond(400, ['ok' => false, 'error' => 'Formulario desconocido.']);
}

respond(200, ['ok' => true]);
