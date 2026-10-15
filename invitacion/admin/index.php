<?php
// Panel para los novios: confirmaciones, menús, canciones y mensajes.
// La contraseña va en api/config.php como 'admin_password'.
declare(strict_types=1);
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => !empty($_SERVER['HTTPS'])]);
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');

$config = require __DIR__ . '/../api/config.php';
$pass = (string)($config['admin_password'] ?? '');

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (isset($_GET['salir'])) { session_destroy(); header('Location: ./'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clave'])) {
    usleep(400000); // frena intentos repetidos
    if ($pass !== '' && hash_equals($pass, (string)$_POST['clave'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: ./'); exit;
    }
    $error = $pass === '' ? 'Falta configurar admin_password en api/config.php.' : 'Contraseña incorrecta.';
}

$ok = !empty($_SESSION['admin']);
if ($ok) {
    $pdo = new PDO($config['db_dsn'] ?? "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4", $config['db_user'], $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    $csrf = $_SESSION['csrf'];
    // Alta / edición / baja de invitaciones (grupo + personas)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $acc = $_POST['accion'];
        if ($acc === 'vista') {
            // Foto para la vista previa del link en WhatsApp
            $f = $_FILES['foto'] ?? null;
            if ($f && $f['error'] === UPLOAD_ERR_OK && $f['size'] < 12 * 1024 * 1024) {
                $info = @getimagesize($f['tmp_name']);
                $dir = rtrim($config['uploads_dir'] ?? __DIR__ . '/../uploads', '/');
                if ($info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    if (function_exists('imagecreatefromstring')) {
                        // recorta a 1200x630 (formato de WhatsApp) y guarda en JPG
                        $src = imagecreatefromstring(file_get_contents($f['tmp_name']));
                        [$w, $h] = [imagesx($src), imagesy($src)];
                        $r = 1200 / 630; $cw = $w; $ch = (int)round($w / $r);
                        if ($ch > $h) { $ch = $h; $cw = (int)round($h * $r); }
                        $dst = imagecreatetruecolor(1200, 630);
                        imagecopyresampled($dst, $src, 0, 0, (int)(($w - $cw) / 2), (int)(($h - $ch) / 2), 1200, 630, $cw, $ch);
                        imagejpeg($dst, "$dir/vista-previa.jpg", 85);
                    } elseif ($info[2] === IMAGETYPE_JPEG) {
                        move_uploaded_file($f['tmp_name'], "$dir/vista-previa.jpg");
                    }
                }
            }
            header('Location: ./?ver=invitados'); exit;
        }
        if ($acc === 'vista_borrar') { @unlink(rtrim($config['uploads_dir'] ?? __DIR__ . '/../uploads', '/') . '/vista-previa.jpg'); header('Location: ./?ver=invitados'); exit; }
        if ($acc === 'borrar') {
            $pdo->prepare('DELETE FROM grupos WHERE id = ?')->execute([(int)$_POST['id']]);
        } elseif ($acc === 'guardar') {
            $nombreG = mb_substr(trim((string)$_POST['nombre']), 0, 120);
            // Filas del formulario: nombre + casillero "Menor".
            $lineas = [];
            foreach ((array)($_POST['p_nombre'] ?? []) as $i => $n) {
                $n = mb_substr(trim((string)$n), 0, 100);
                if ($n !== '') $lineas[] = [$n, !empty($_POST['p_menor'][$i]) ? 'nino' : 'adulto'];
            }
            if ($nombreG !== '' && $lineas) {
                $id = (int)($_POST['id'] ?? 0);
                $pdo->beginTransaction();
                if ($id) {
                    $pdo->prepare('UPDATE grupos SET nombre = ? WHERE id = ?')->execute([$nombreG, $id]);
                    $prev = $pdo->prepare('SELECT id, nombre FROM personas WHERE grupo_id = ?');$prev->execute([$id]);
                    $prevMap = [];foreach ($prev as $r) $prevMap[mb_strtolower($r['nombre'])] = $r['id'];
                } else {
                    do { $tok = substr(str_replace(['+','/','='], '', base64_encode(random_bytes(12))), 0, 10); } while (strlen($tok) < 10);
                    $pdo->prepare('INSERT INTO grupos (nombre, token) VALUES (?, ?)')->execute([$nombreG, $tok]);
                    $id = (int)$pdo->lastInsertId();$prevMap = [];
                }
                $quedan = [];
                foreach ($lineas as [$n, $tipo]) {
                    $k = mb_strtolower($n);
                    if (isset($prevMap[$k])) { $pdo->prepare('UPDATE personas SET tipo = ? WHERE id = ?')->execute([$tipo, $prevMap[$k]]); $quedan[] = $prevMap[$k]; }
                    else { $pdo->prepare('INSERT INTO personas (grupo_id, nombre, tipo) VALUES (?, ?, ?)')->execute([$id, $n, $tipo]); $quedan[] = (int)$pdo->lastInsertId(); }
                }
                $del = $pdo->prepare('DELETE FROM personas WHERE grupo_id = ? AND id NOT IN (' . implode(',', array_map('intval', $quedan ?: [0])) . ')');
                $del->execute([$id]);
                $pdo->commit();
            }
        }
        header('Location: ./?ver=invitados'); exit;
    }
    $grupos = $pdo->query('SELECT * FROM grupos ORDER BY nombre')->fetchAll();
    $pers = [];
    foreach ($pdo->query('SELECT * FROM personas ORDER BY id') as $r) $pers[$r['grupo_id']][] = $r;
    $base = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'camiyjuan.com') . '/?i=';
    $conf = $pdo->query('SELECT * FROM confirmaciones ORDER BY creado DESC')->fetchAll();
    $acomp = [];
    foreach ($pdo->query('SELECT * FROM acompanantes ORDER BY id') as $a) $acomp[$a['confirmacion_id']][] = $a;
    $canciones = $pdo->query('SELECT * FROM canciones ORDER BY creado DESC')->fetchAll();
    $mensajes = $pdo->query('SELECT * FROM mensajes ORDER BY creado DESC')->fetchAll();
    $nFotos = (int)$pdo->query('SELECT COUNT(*) FROM fotos')->fetchColumn();
    $drive = $config['drive_folder_url'] ?? 'https://drive.google.com/drive/folders/1UFIXWS8BP8uT1RmC0wbQW-q6f3qxisXl';

    // Totales
    $t = ['si' => 0, 'no' => 0, 'personas' => 0, 'adultos' => 0, 'ninos' => 0];
    $menus = [];
    $tg = ['pend' => 0];
    foreach ($pers as $gid => $lista) foreach ($lista as $p) {
        if ($p['asiste'] === 'si') { $t['personas']++; $p['tipo'] === 'nino' ? $t['ninos']++ : $t['adultos']++; if ($p['menu']) $menus[$p['menu']][] = $p['nombre']; }
        elseif ($p['asiste'] === 'no') $t['no']++;
        else $tg['pend']++;
    }
    foreach ($grupos as $g) if ($g['respondido']) $t['si']++;
    foreach ($conf as $c) {
        if ($c['asistencia'] !== 'si') { $t['no']++; continue; }
        $t['si']++; $t['personas'] += (int)$c['personas']; $t['adultos']++;
        if ($c['menu'] !== '') $menus[$c['menu']][] = $c['nombre'];
        foreach ($acomp[$c['id']] ?? [] as $a) {
            $a['tipo'] === 'nino' ? $t['ninos']++ : $t['adultos']++;
            if (!empty($a['restriccion'])) $menus[$a['restriccion']][] = $a['nombre'] . ' (de ' . $c['nombre'] . ')';
        }
    }

    // Exportar a Excel (CSV)
    if (isset($_GET['csv'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="invitados-camila-juan.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Invitado principal', 'Asiste', 'Nombre', 'Tipo', 'Restricción', 'Teléfono', 'Comentarios', 'Fecha'], ';');
        foreach ($grupos as $g) foreach ($pers[$g['id']] ?? [] as $p)
            fputcsv($out, [$g['nombre'], $p['asiste'] ?? 'sin responder', $p['nombre'], $p['tipo'] === 'nino' ? 'menor' : 'adulto', $p['asiste'] === 'si' ? ($p['menu'] ?: 'común') : '', $g['telefono'], $g['comentarios'], $g['respondido']], ';');
        foreach ($conf as $c) {
            fputcsv($out, [$c['nombre'], $c['asistencia'], $c['nombre'], 'adulto', $c['menu'], $c['telefono'], $c['comentarios'], $c['creado']], ';');
            foreach ($acomp[$c['id']] ?? [] as $a) fputcsv($out, [$c['nombre'], $c['asistencia'], $a['nombre'], $a['tipo'] === 'nino' ? 'menor' : 'adulto', $a['restriccion'] ?? '', '', '', $c['creado']], ';');
        }
        exit;
    }
}
$fecha = fn($d) => date('d/m H:i', strtotime((string)$d));
?>
<!doctype html>
<html lang="es-AR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Panel · Camila & Juan</title>
<style>
  :root{--ivory:#f7f1e6;--paper:#fffaf3;--brown:#644732;--soft:#8a7060;--pet:#527987;--line:#e2d5c4}
  *{box-sizing:border-box}
  body{margin:0;background:var(--ivory);color:var(--brown);font-family:Georgia,'Times New Roman',serif;line-height:1.4}
  main{max-width:960px;margin:auto;padding:24px 16px 60px}
  header{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px}
  h1{font-weight:400;font-size:28px;margin:0} h2{font-weight:400;font-size:22px;margin:30px 0 10px}
  a{color:var(--pet)}
  .btn{display:inline-block;background:var(--pet);color:#fff;border:0;border-radius:20px;padding:8px 16px;font:inherit;font-size:13px;text-decoration:none;cursor:pointer}
  .btn.ghost{background:none;color:var(--brown);border:1px solid var(--line)}
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px}
  .stat{background:var(--paper);border:1px solid var(--line);border-radius:14px;padding:14px;text-align:center}
  .stat b{display:block;font-size:30px;font-weight:400;color:var(--pet)} .stat span{font-size:12px;letter-spacing:1px;text-transform:uppercase;color:var(--soft)}
  .card{background:var(--paper);border:1px solid var(--line);border-radius:14px;padding:14px 16px;margin-bottom:10px}
  .row{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}
  .tag{display:inline-block;font-size:12px;border-radius:10px;padding:2px 8px;background:#e7eef1;color:var(--pet);margin-left:4px}
  .tag.no{background:#f3e3df;color:#9b3b2e} .tag.nino{background:#f2e9da;color:#8a6a3a}
  .muted{color:var(--soft);font-size:13px}
  ul{margin:6px 0 0;padding-left:18px}
  .tabs{display:flex;gap:6px;flex-wrap:wrap;margin:18px 0 0}
  .tabs a{padding:7px 14px;border-radius:20px;border:1px solid var(--line);text-decoration:none;color:var(--brown);font-size:14px}
  .tabs a.on{background:var(--brown);color:#fff;border-color:var(--brown)}
  input[type=search],input[type=password]{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;font:inherit;background:#fff;margin:6px 0 12px}
  .login{max-width:340px;margin:12vh auto;background:var(--paper);border:1px solid var(--line);border-radius:18px;padding:26px}
  .err{color:#9b3b2e;font-size:14px}
  .label{font-size:14px;margin:4px 0 6px}
  .fila{display:flex;gap:8px;align-items:center;margin-bottom:8px}
  .fila input[name^=p_nombre]{flex:1;margin:0}
  .fila .menor{display:flex;align-items:center;gap:4px;margin:0;font-size:13px;white-space:nowrap}
  .fila .menor input{width:auto;margin:0}
  .quitar{border:0;background:none;font-size:22px;color:var(--soft);cursor:pointer;padding:0 4px}
  form.card>.btn.ghost{margin:2px 0 12px}
  @media (max-width:480px){.stats{grid-template-columns:repeat(3,1fr);gap:6px}.stat{padding:10px 4px}.stat b{font-size:22px}.stat span{font-size:9px;letter-spacing:.5px}header h1{font-size:22px}}
  .fila .menor{display:inline-flex!important;align-items:center;gap:6px;flex:0 0 auto;margin:0!important;font-size:14px;white-space:nowrap}
  .fila .menor input[type=checkbox]{width:18px!important;height:18px;margin:0!important;padding:0;flex:0 0 auto;accent-color:#527987}
  .fila .quitar{flex:0 0 auto}
  .acciones{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;align-items:center}
  .acciones .link{flex:1 1 100%;margin:0;font-size:13px;padding:8px 10px;border:1px solid var(--line);border-radius:10px;background:#fff}
  label{display:block;font-size:14px;margin-bottom:10px}
  input:not([type=search]):not([type=password]):not(.link),textarea{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;font:inherit;background:#fff;margin-top:4px}
  blockquote{margin:6px 0 0;white-space:pre-wrap}
</style>
</head>
<body>
<?php if (!$ok): ?>
  <form class="login" method="post">
    <h1>Camila &amp; Juan</h1>
    <p class="muted">Panel de la invitación</p>
    <label>Contraseña<input type="password" name="clave" autocomplete="current-password" autofocus required></label>
    <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
    <button class="btn" type="submit">Entrar</button>
  </form>
<?php else: $tab = $_GET['ver'] ?? 'invitados'; ?>
<main>
  <header>
    <h1>Camila &amp; Juan · Panel</h1>
    <div><a class="btn" href="<?= e($drive) ?>" target="_blank" rel="noopener">📸 Ver fotos (<?= $nFotos ?>)</a> <a class="btn ghost" href="?csv=1">Descargar Excel</a> <a class="btn ghost" href="?salir=1">Salir</a></div>
  </header>

  <section class="stats">
    <div class="stat"><b><?= $t['personas'] ?></b><span>Personas</span></div>
    <div class="stat"><b><?= $t['adultos'] ?></b><span>Adultos</span></div>
    <div class="stat"><b><?= $t['ninos'] ?></b><span>Menores</span></div>
    <div class="stat"><b><?= $t['si'] ?></b><span>Confirmaciones</span></div>
    <div class="stat"><b><?= $t['no'] ?></b><span>No asisten</span></div>
    <div class="stat"><b><?= $tg['pend'] ?></b><span>Sin responder</span></div>
  </section>

  <nav class="tabs">
    <?php foreach (['invitados' => 'Invitados (' . count($grupos) . ')', 'confirmados' => 'Confirmaciones sueltas', 'menus' => 'Menús', 'canciones' => 'Canciones (' . count($canciones) . ')', 'mensajes' => 'Mensajes (' . count($mensajes) . ')'] as $k => $v): ?>
      <a href="?ver=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($v) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($tab === 'invitados'): $edit = null; foreach ($grupos as $g) if ((int)($_GET['editar'] ?? 0) === (int)$g['id']) $edit = $g; ?>
    <h2><?= $edit ? 'Editar invitación' : 'Nueva invitación' ?></h2>
    <form class="card" method="post">
      <input type="hidden" name="accion" value="guardar"><input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
      <label>Nombre de la invitación (como lo va a ver el invitado)<input name="nombre" required maxlength="120" placeholder="Ej: Familia Gómez" value="<?= e($edit['nombre'] ?? '') ?>"></label>
      <div class="label">Personas</div>
      <div id="filas">
        <?php $lista = $edit ? ($pers[$edit['id']] ?? []) : [['nombre' => '', 'tipo' => 'adulto']]; foreach ($lista as $i => $p): ?>
        <div class="fila"><input name="p_nombre[<?= $i ?>]" placeholder="Nombre y apellido" maxlength="100" value="<?= e($p['nombre']) ?>">
          <label class="menor"><input type="checkbox" name="p_menor[<?= $i ?>]" value="1" <?= $p['tipo'] === 'nino' ? 'checked' : '' ?>> Menor</label>
          <button type="button" class="quitar" onclick="this.parentNode.remove()" aria-label="Quitar">×</button></div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn ghost" onclick="agregarFila()">+ Agregar persona</button>
      <button class="btn" type="submit"><?= $edit ? 'Guardar cambios' : 'Agregar invitación' ?></button>
      <?php if ($edit): ?> <a class="btn ghost" href="?ver=invitados">Cancelar</a><?php endif; ?>
    </form>
    <h2>Imagen al compartir el link</h2>
    <div class="card">
      <p class="muted">Es la foto que aparece en WhatsApp cuando mandan el link. Se recorta a formato horizontal.</p>
      <img src="../share/imagen.php?t=<?= time() ?>" alt="" style="width:100%;max-width:420px;border-radius:10px;display:block;margin:8px 0">
      <form method="post" enctype="multipart/form-data" class="acciones">
        <input type="hidden" name="accion" value="vista"><input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required style="flex:1 1 100%">
        <button class="btn" type="submit">Usar esta foto</button>
      </form>
      <form method="post" style="margin-top:6px"><input type="hidden" name="accion" value="vista_borrar"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button class="btn ghost" type="submit">Volver a la imagen de la carta</button></form>
    </div>
    <h2>Mensaje para WhatsApp</h2>
    <div class="card">
      <label>Escriban su mensaje. <b>{nombre}</b> se reemplaza por el nombre de la invitación y <b>{link}</b> por su link.
        <textarea id="wa-msg" rows="4"></textarea></label>
      <span class="muted" id="wa-ok"></span>
    </div>
    <h2>Invitaciones</h2>
    <input type="search" placeholder="Buscar por nombre…" oninput="filtrar(this.value)">
    <?php if (!$grupos): ?><p class="muted">Todavía no cargaron invitaciones.</p><?php endif; ?>
    <?php foreach ($grupos as $g): $link = $base . $g['token'] . '&v=2';   // v= cambia la URL: WhatsApp vuelve a leer la vista previa ?>
      <div class="card" data-buscar="<?= e(mb_strtolower($g['nombre'] . ' ' . implode(' ', array_column($pers[$g['id']] ?? [], 'nombre')))) ?>">
        <div class="row"><strong><?= e($g['nombre']) ?>
          <?= $g['respondido'] ? '<span class="tag">Respondió</span>' : '<span class="tag nino">Sin responder</span>' ?></strong>
          <span class="muted"><?= $g['respondido'] ? $fecha($g['respondido']) : '' ?></span></div>
        <ul><?php foreach ($pers[$g['id']] ?? [] as $p): ?>
          <li><?= e($p['nombre']) ?><?= $p['tipo'] === 'nino' ? ' <span class="tag nino">Menor</span>' : '' ?>
            <?php if ($p['asiste'] === 'si'): ?><span class="tag">Asiste</span><span class="tag"><?= e($p['menu'] ?: 'Menú común') ?></span>
            <?php elseif ($p['asiste'] === 'no'): ?><span class="tag no">No asiste</span><?php endif; ?></li>
        <?php endforeach; ?></ul>
        <?php if ($g['telefono'] || $g['comentarios']): ?><div class="muted"><?= $g['telefono'] ? 'Tel: ' . e($g['telefono']) . ' ' : '' ?><?= $g['comentarios'] ? '· “' . e($g['comentarios']) . '”' : '' ?></div><?php endif; ?>
        <div class="acciones">
          <input class="link" readonly value="<?= e($link) ?>" onclick="this.select()">
          <button class="btn" type="button" onclick="copiar('<?= e($link) ?>', this)">Copiar link</button>
          <a class="btn ghost wa" target="_blank" rel="noopener" data-nombre="<?= e($g['nombre']) ?>" data-link="<?= e($link) ?>" href="#">WhatsApp</a>
          <a class="btn ghost" href="?ver=invitados&editar=<?= (int)$g['id'] ?>">Editar</a>
          <form method="post" style="display:inline" onsubmit="return confirm('¿Borrar la invitación de <?= e(addslashes($g['nombre'])) ?>?')">
            <input type="hidden" name="accion" value="borrar"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <button class="btn ghost" type="submit">Borrar</button></form>
        </div>
      </div>
    <?php endforeach; ?>

  <?php elseif ($tab === 'confirmados'): ?>
    <h2>Confirmados</h2>
    <input type="search" placeholder="Buscar por nombre…" oninput="filtrar(this.value)">
    <?php if (!$conf): ?><p class="muted">Todavía no hay confirmaciones.</p><?php endif; ?>
    <?php foreach ($conf as $c): ?>
      <div class="card" data-buscar="<?= e(mb_strtolower($c['nombre'] . ' ' . implode(' ', array_column($acomp[$c['id']] ?? [], 'nombre')))) ?>">
        <div class="row">
          <strong><?= e($c['nombre']) ?>
            <?php if ($c['asistencia'] === 'si'): ?><span class="tag"><?= (int)$c['personas'] ?> persona<?= $c['personas'] == 1 ? '' : 's' ?></span>
            <?php else: ?><span class="tag no">No asiste</span><?php endif; ?></strong>
          <span class="muted"><?= $fecha($c['creado']) ?></span>
        </div>
        <?php if ($c['menu'] || $c['telefono']): ?><div class="muted"><?= $c['menu'] ? 'Menú: ' . e($c['menu']) : '' ?><?= $c['menu'] && $c['telefono'] ? ' · ' : '' ?><?= $c['telefono'] ? 'Tel: ' . e($c['telefono']) : '' ?></div><?php endif; ?>
        <?php if (!empty($acomp[$c['id']])): ?>
          <ul><?php foreach ($acomp[$c['id']] as $a): ?>
            <li><?= e($a['nombre']) ?> <span class="tag <?= $a['tipo'] === 'nino' ? 'nino' : '' ?>"><?= $a['tipo'] === 'nino' ? 'Menor' : 'Adulto' ?></span><?php if (!empty($a['restriccion'])): ?> <span class="tag"><?= e($a['restriccion']) ?></span><?php endif; ?></li>
          <?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if ($c['comentarios']): ?><blockquote class="muted">“<?= e($c['comentarios']) ?>”</blockquote><?php endif; ?>
      </div>
    <?php endforeach; ?>

  <?php elseif ($tab === 'menus'): ?>
    <h2>Menús especiales</h2>
    <?php if (!$menus): ?><p class="muted">Nadie pidió menú especial por ahora.</p><?php endif; ?>
    <?php foreach ($menus as $m => $quienes): ?>
      <div class="card"><div class="row"><strong><?= e($m) ?></strong><span class="tag"><?= count($quienes) ?></span></div>
        <ul><?php foreach ($quienes as $q): ?><li><?= e($q) ?></li><?php endforeach; ?></ul></div>
    <?php endforeach; ?>

  <?php elseif ($tab === 'canciones'): ?>
    <h2>Canciones sugeridas</h2>
    <?php if (!$canciones): ?><p class="muted">Todavía no hay canciones.</p><?php endif; ?>
    <?php foreach ($canciones as $s): ?>
      <div class="card row"><div><strong><?= e($s['cancion']) ?></strong><?= $s['artista'] ? ' — ' . e($s['artista']) : '' ?>
        <div class="muted">Sugerida por <?= e($s['nombre']) ?> · <a target="_blank" rel="noopener" href="https://open.spotify.com/search/<?= rawurlencode($s['cancion'] . ' ' . $s['artista']) ?>">Buscar en Spotify</a></div></div>
        <span class="muted"><?= $fecha($s['creado']) ?></span></div>
    <?php endforeach; ?>

  <?php else: ?>
    <h2>Mensajes</h2>
    <?php if (!$mensajes): ?><p class="muted">Todavía no hay mensajes.</p><?php endif; ?>
    <?php foreach ($mensajes as $m): ?>
      <div class="card"><div class="row"><strong><?= e($m['nombre']) ?></strong><span class="muted"><?= $fecha($m['creado']) ?></span></div>
        <blockquote><?= e($m['mensaje']) ?></blockquote></div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
<script>
const WA_DEF='¡Hola {nombre}! Les compartimos nuestra invitación: {link}';
const waMsg=document.getElementById('wa-msg');
if(waMsg){try{waMsg.value=localStorage.getItem('cj-wa')||WA_DEF}catch(e){waMsg.value=WA_DEF}
  waMsg.addEventListener('input',()=>{try{localStorage.setItem('cj-wa',waMsg.value);document.getElementById('wa-ok').textContent='Guardado en este dispositivo'}catch(e){}});}
document.addEventListener('click',ev=>{const a=ev.target.closest('a.wa');if(!a)return;
  let m=(waMsg&&waMsg.value.trim())||WA_DEF;if(!m.includes('{link}'))m+=' {link}';
  a.href='https://wa.me/?text='+encodeURIComponent(m.replaceAll('{nombre}',a.dataset.nombre).replaceAll('{link}',a.dataset.link));});
let nFila=1000;
function agregarFila(){const d=document.createElement('div');d.className='fila';nFila++;
  d.innerHTML='<input name="p_nombre['+nFila+']" placeholder="Nombre y apellido" maxlength="100"><label class="menor"><input type="checkbox" name="p_menor['+nFila+']" value="1"> Menor</label><button type="button" class="quitar" onclick="this.parentNode.remove()" aria-label="Quitar">×</button>';
  document.getElementById('filas').append(d);d.querySelector('input').focus();}
function copiar(t,b){navigator.clipboard.writeText(t).then(()=>{b.textContent='¡Copiado!';setTimeout(()=>b.textContent='Copiar link',1500)})}
function filtrar(q){q=q.toLowerCase();document.querySelectorAll('[data-buscar]').forEach(c=>c.hidden=!c.dataset.buscar.includes(q));}
</script>
<?php endif; ?>
</body>
</html>
