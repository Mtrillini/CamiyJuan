<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
guard();

$nombre = text('nombre', 100, true);

switch ($_POST['tipo'] ?? '') {
    case 'rsvp':
        $asistencia = ($_POST['asistencia'] ?? '') === 'no' ? 'no' : 'si';
        $personas = filter_var($_POST['personas'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
        if ($personas === false) respond(422, ['ok' => false, 'error' => 'La cantidad de personas tiene que ser entre 1 y 10.']);
        db()->prepare('INSERT INTO confirmaciones (nombre, asistencia, personas, telefono, menu, comentarios) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$nombre, $asistencia, $personas, text('telefono', 30), text('menu', 50), longText('comentarios', 1000)]);
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
