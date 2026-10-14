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
    $conf = $pdo->query('SELECT * FROM confirmaciones ORDER BY creado DESC')->fetchAll();
    $acomp = [];
    foreach ($pdo->query('SELECT * FROM acompanantes ORDER BY id') as $a) $acomp[$a['confirmacion_id']][] = $a;
    $canciones = $pdo->query('SELECT * FROM canciones ORDER BY creado DESC')->fetchAll();
    $mensajes = $pdo->query('SELECT * FROM mensajes ORDER BY creado DESC')->fetchAll();

    // Totales
    $t = ['si' => 0, 'no' => 0, 'personas' => 0, 'adultos' => 0, 'ninos' => 0];
    $menus = [];
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
        foreach ($conf as $c) {
            fputcsv($out, [$c['nombre'], $c['asistencia'], $c['nombre'], 'adulto', $c['menu'], $c['telefono'], $c['comentarios'], $c['creado']], ';');
            foreach ($acomp[$c['id']] ?? [] as $a) fputcsv($out, [$c['nombre'], $c['asistencia'], $a['nombre'], $a['tipo'] === 'nino' ? 'niño/a' : 'adulto', $a['restriccion'] ?? '', '', '', $c['creado']], ';');
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
<?php else: $tab = $_GET['ver'] ?? 'confirmados'; ?>
<main>
  <header>
    <h1>Camila &amp; Juan · Panel</h1>
    <div><a class="btn ghost" href="?csv=1">Descargar Excel</a> <a class="btn ghost" href="?salir=1">Salir</a></div>
  </header>

  <section class="stats">
    <div class="stat"><b><?= $t['personas'] ?></b><span>Personas</span></div>
    <div class="stat"><b><?= $t['adultos'] ?></b><span>Adultos</span></div>
    <div class="stat"><b><?= $t['ninos'] ?></b><span>Niños</span></div>
    <div class="stat"><b><?= $t['si'] ?></b><span>Confirmaciones</span></div>
    <div class="stat"><b><?= $t['no'] ?></b><span>No asisten</span></div>
  </section>

  <nav class="tabs">
    <?php foreach (['confirmados' => 'Confirmados', 'menus' => 'Menús', 'canciones' => 'Canciones (' . count($canciones) . ')', 'mensajes' => 'Mensajes (' . count($mensajes) . ')'] as $k => $v): ?>
      <a href="?ver=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($v) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($tab === 'confirmados'): ?>
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
            <li><?= e($a['nombre']) ?> <span class="tag <?= $a['tipo'] === 'nino' ? 'nino' : '' ?>"><?= $a['tipo'] === 'nino' ? 'Niño/a' : 'Adulto' ?></span><?php if (!empty($a['restriccion'])): ?> <span class="tag"><?= e($a['restriccion']) ?></span><?php endif; ?></li>
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
function filtrar(q){q=q.toLowerCase();document.querySelectorAll('[data-buscar]').forEach(c=>c.hidden=!c.dataset.buscar.includes(q));}
</script>
<?php endif; ?>
</body>
</html>
