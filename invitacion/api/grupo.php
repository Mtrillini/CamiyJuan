<?php
// Devuelve la invitación (grupo y personas) para el link personalizado ?i=TOKEN.
declare(strict_types=1);
require __DIR__ . '/db.php';
$t = (string)($_GET['t'] ?? '');
if (!preg_match('/^[A-Za-z0-9]{10}$/', $t)) respond(404, ['ok' => false]);
$g = db()->prepare('SELECT id, nombre, telefono, comentarios, respondido FROM grupos WHERE token = ?');
$g->execute([$t]);
$grupo = $g->fetch(PDO::FETCH_ASSOC);
if (!$grupo) respond(404, ['ok' => false]);
$p = db()->prepare('SELECT id, nombre, tipo, asiste, menu FROM personas WHERE grupo_id = ? ORDER BY id');
$p->execute([$grupo['id']]);
unset($grupo['id']);
respond(200, ['ok' => true, 'grupo' => $grupo, 'personas' => $p->fetchAll(PDO::FETCH_ASSOC)]);
