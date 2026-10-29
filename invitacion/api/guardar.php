<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
guard();

const MENUS = ['Menú infantil', 'Vegetariano', 'Vegano', 'Sin TACC', 'Otra'];  // vacío = menú adulto

$nombre = ($_POST['tipo'] ?? '') === 'rsvp_grupo' ? '' : text('nombre', 100, true);

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
                // Sin restricción se guarda NULL.
                $menu = trim((string)(($_POST['acompanante_menu'] ?? [])[$i] ?? ''));
                $menu = in_array($menu, MENUS, true) ? $menu : null;
                $acompanantes[] = [$n, $tipo, $menu];
            }
        }
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO confirmaciones (nombre, asistencia, personas, telefono, menu, comentarios) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$nombre, $asistencia, $asistencia === 'si' ? 1 + count($acompanantes) : 0, text('telefono', 30), text('menu', 50), longText('comentarios', 1000)]);
        $id = (int)$pdo->lastInsertId();
        $insert = $pdo->prepare('INSERT INTO acompanantes (confirmacion_id, nombre, tipo, restriccion) VALUES (?, ?, ?, ?)');
        foreach ($acompanantes as [$n, $tipo, $menu]) $insert->execute([$id, $n, $tipo, $menu]);
        $pdo->commit();
        break;
    case 'rsvp_grupo':
        // Confirmación desde un link personalizado: una respuesta por persona cargada por los novios.
        $t = (string)($_POST['token'] ?? '');
        $g = db()->prepare('SELECT id FROM grupos WHERE token = ?');
        $g->execute([$t]);
        $gid = $g->fetchColumn();
        if (!$gid) respond(404, ['ok' => false, 'error' => 'No encontramos tu invitación.']);
        $ids = db()->prepare('SELECT id FROM personas WHERE grupo_id = ?');
        $ids->execute([$gid]);
        $pdo = db();
        $pdo->beginTransaction();
        $upd = $pdo->prepare('UPDATE personas SET asiste = ?, menu = ? WHERE id = ? AND grupo_id = ?');
        foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $pid) {
            $asiste = ($_POST["asiste_$pid"] ?? '') === 'si' ? 'si' : (($_POST["asiste_$pid"] ?? '') === 'no' ? 'no' : null);
            if ($asiste === null) { $pdo->rollBack(); respond(422, ['ok' => false, 'error' => 'Indicá si asiste cada persona.']); }
            $menu = trim((string)($_POST["menu_$pid"] ?? ''));
            $menu = ($asiste === 'si' && in_array($menu, MENUS, true)) ? $menu : null;
            $upd->execute([$asiste, $menu, $pid, $gid]);
        }
        $pdo->prepare('UPDATE grupos SET telefono = ?, comentarios = ?, respondido = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([text('telefono', 30), longText('comentarios', 1000), $gid]);
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
