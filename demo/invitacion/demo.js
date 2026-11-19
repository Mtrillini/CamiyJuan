// Demo: reemplaza el servidor (api/*.php) por datos ficticios en el navegador.
(() => {
  const real = window.fetch.bind(window);
  const ok = body => new Response(JSON.stringify(body), { status: 200, headers: { 'Content-Type': 'application/json' } });
  const error = (status, msg) => new Response(JSON.stringify({ ok: false, error: msg }), { status, headers: { 'Content-Type': 'application/json' } });
  const ahora = () => new Date().toISOString();
  const MENUS = ['Vegetariano', 'Vegano', 'Sin TACC', 'Otra'];

  window.fetch = async (url, opts = {}) => {
    const u = String(url);
    if (!u.startsWith('api/')) return real(url, opts);
    await new Promise(r => setTimeout(r, 450));          // simula la red
    const D = window.DEMO.datos;
    if (u.startsWith('api/grupo.php')) {
      const t = new URL(u, location.href).searchParams.get('t');
      const g = D.grupos.find(x => x.token === t);
      if (!g) return error(404, 'No encontrada');
      return ok({ ok: true, grupo: { nombre: g.nombre, telefono: g.telefono, comentarios: g.comentarios, respondido: g.respondido },
        personas: D.personas.filter(p => p.grupo_id === g.id).map(({ id, nombre, tipo, asiste, menu, menu_tipo }) => ({ id, nombre, tipo, asiste, menu, menu_tipo })) });
    }
    const f = opts.body;
    const v = k => String(f.get(k) ?? '').trim();
    if (u === 'api/fotos.php') {
      const foto = f.get('foto');
      let thumb = '';
      try { thumb = await miniatura(foto); } catch {}
      D.fotos.unshift({ nombre: v('nombre'), creado: ahora(), thumb });
      window.DEMO.guardar(); return ok({ ok: true });
    }
    switch (v('tipo')) {
      case 'rsvp_grupo': {
        const g = D.grupos.find(x => x.token === v('token'));
        if (!g) return error(404, 'No encontramos tu invitación.');
        for (const p of D.personas.filter(p => p.grupo_id === g.id)) {
          const a = v('asiste_' + p.id);
          if (a !== 'si' && a !== 'no') return error(422, 'Indicá si asiste cada persona.');
          p.asiste = a;
          p.menu = a === 'si' && MENUS.includes(v('menu_' + p.id)) ? v('menu_' + p.id) : null;
          p.menu_tipo = a === 'si' ? (v('menu_tipo_' + p.id) === 'infantil' ? 'infantil' : 'adulto') : null;
        }
        g.telefono = v('telefono'); g.comentarios = v('comentarios'); g.respondido = ahora();
        break;
      }
      case 'rsvp': {
        const si = v('asistencia') !== 'no';
        const nombres = si ? f.getAll('acompanante_nombre[]').map(String) : [];
        const menus = f.getAll('acompanante_menu[]').map(String);
        const id = (D.confirmaciones.at(-1)?.id || 0) + 1;
        D.confirmaciones.push({ id, nombre: v('nombre'), asistencia: si ? 'si' : 'no', personas: si ? 1 + nombres.length : 0, telefono: v('telefono'), menu: v('menu'), comentarios: v('comentarios'), creado: ahora() });
        nombres.forEach((n, i) => D.acompanantes.push({ id: D.acompanantes.length + 1, confirmacion_id: id, nombre: n.trim(), tipo: v('acompanante_tipo_' + i) === 'nino' ? 'nino' : 'adulto', restriccion: MENUS.includes(menus[i]) ? menus[i] : null }));
        break;
      }
      case 'song': D.canciones.unshift({ nombre: v('nombre'), cancion: v('cancion'), artista: v('artista'), creado: ahora() }); break;
      case 'message': D.mensajes.unshift({ nombre: v('nombre'), mensaje: v('mensaje'), creado: ahora() }); break;
      default: return error(400, 'Formulario desconocido.');
    }
    window.DEMO.guardar();
    return ok({ ok: true });
  };

  // Miniatura chica de la foto para mostrarla en el panel demo.
  function miniatura(file) {
    return new Promise((res, rej) => {
      const img = new Image(), url = URL.createObjectURL(file);
      img.onload = () => { const c = document.createElement('canvas'), s = 160 / Math.max(img.width, img.height);
        c.width = img.width * s; c.height = img.height * s; c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url); res(c.toDataURL('image/jpeg', .7)); };
      img.onerror = rej; img.src = url;
    });
  }
})();
