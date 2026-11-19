// Panel demo: misma vista que el panel real, pero todo en el navegador con datos ficticios.
'use strict';
const D = () => window.DEMO.datos;
const app = document.getElementById('app');
const e = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const fecha = d => d ? new Date(d).toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
const base = new URL('../?i=', location.href).href;
const WA_DEF = '¡Hola {nombre}! Les compartimos nuestra invitación: {link}';
let tab = 'invitados', editar = null;

// Entrar: cualquier contraseña (es una demo).
const login = document.getElementById('login');
const entrar = () => { login.hidden = true; app.hidden = false; render(); };
login.addEventListener('submit', ev => { ev.preventDefault(); try { sessionStorage.setItem('demo-panel', '1'); } catch {} entrar(); });
try { if (sessionStorage.getItem('demo-panel')) entrar(); } catch {}
// Si confirman desde la invitación en otra pestaña, el panel se actualiza solo.
addEventListener('storage', () => { window.DEMO.datos = JSON.parse(localStorage.getItem('demo-invitacion-v1')) || window.DEMO.datos; if (!app.hidden) render(); });

function totales() {
  const d = D(), t = { si: 0, no: 0, personas: 0, adultos: 0, ninos: 0, pend: 0 };
  for (const p of d.personas) {
    if (p.asiste === 'si') { t.personas++; p.tipo === 'nino' ? t.ninos++ : t.adultos++; }
    else if (p.asiste === 'no') t.no++; else t.pend++;
  }
  for (const g of d.grupos) if (g.respondido) t.si++;
  for (const c of d.confirmaciones) {
    if (c.asistencia !== 'si') { t.no++; continue; }
    t.si++; t.personas += c.personas; t.adultos++;
    for (const a of d.acompanantes.filter(a => a.confirmacion_id === c.id)) a.tipo === 'nino' ? t.ninos++ : t.adultos++;
  }
  return t;
}
const pers = gid => D().personas.filter(p => p.grupo_id === gid);
const acomp = cid => D().acompanantes.filter(a => a.confirmacion_id === cid);

function render() {
  const d = D(), t = totales();
  const tabs = { invitados: `Invitados (${d.grupos.length})`, lista: 'Lista de asistencia', confirmados: 'Confirmaciones sueltas', menus: 'Menús', canciones: `Canciones (${d.canciones.length})`, mensajes: `Mensajes (${d.mensajes.length})`, fotos: `Fotos (${d.fotos.length})` };
  app.innerHTML = `
  <div class="demo-aviso"><span>Estás viendo una <b>demo</b> con datos ficticios. Lo que cargues queda solo en este dispositivo.</span>
    <span><a class="btn" href="../" target="_blank">Ver la invitación</a> <button class="btn" style="background:none;color:#fff;border:1px solid #fff9" data-act="reset">Restablecer demo</button></span></div>
  <header>
    <h1>Sofía &amp; Martín · Panel</h1>
    <div><a class="btn" href="#" data-tab="fotos">📸 Ver fotos (${d.fotos.length})</a> <button class="btn ghost" data-csv="invitados">Descargar Excel</button> <button class="btn ghost" data-act="salir">Salir</button></div>
  </header>
  <section class="stats">
    <div class="stat"><b>${t.personas}</b><span>Personas</span></div>
    <div class="stat"><b>${t.adultos}</b><span>Adultos</span></div>
    <div class="stat"><b>${t.ninos}</b><span>Menores</span></div>
    <div class="stat"><b>${t.si}</b><span>Confirmaciones</span></div>
    <div class="stat"><b>${t.no}</b><span>No asisten</span></div>
    <div class="stat"><b>${t.pend}</b><span>Sin responder</span></div>
  </section>
  <nav class="tabs">${Object.entries(tabs).map(([k, v]) => `<a href="#" data-tab="${k}" class="${tab === k ? 'on' : ''}">${e(v)}</a>`).join('')}</nav>
  ${vistas[tab]()}`;
  const wa = document.getElementById('wa-msg');
  if (wa) { try { wa.value = localStorage.getItem('demo-wa') || WA_DEF; } catch { wa.value = WA_DEF; }
    wa.addEventListener('input', () => { try { localStorage.setItem('demo-wa', wa.value); document.getElementById('wa-ok').textContent = 'Guardado en este dispositivo'; } catch {} }); }
}

const tagsPersona = p => p.asiste === 'si'
  ? `<span class="tag">Asiste</span><span class="tag">Menú ${p.menu_tipo === 'infantil' ? 'infantil' : 'adulto'}</span>${p.menu ? `<span class="tag">${e(p.menu)}</span>` : ''}`
  : p.asiste === 'no' ? '<span class="tag no">No asiste</span>' : '';
const menor = p => p.tipo === 'nino' ? ' <span class="tag nino">Menor</span>' : '';

const vistas = {
  invitados() {
    const g = editar ? D().grupos.find(x => x.id === editar) : null;
    const filas = (g ? pers(g.id) : [{ nombre: '', tipo: 'adulto' }]).map(fila).join('');
    return `
    <h2>${g ? 'Editar invitación' : 'Nueva invitación'}</h2>
    <form class="card" id="form-grupo">
      <label>Nombre de la invitación (como lo va a ver el invitado)<input name="nombre" required maxlength="120" placeholder="Ej: Familia Gómez" value="${e(g?.nombre)}"></label>
      <div class="label">Personas</div><div id="filas">${filas}</div>
      <button type="button" class="btn ghost" data-act="fila">+ Agregar persona</button>
      <button class="btn" type="submit">${g ? 'Guardar cambios' : 'Agregar invitación'}</button>
      ${g ? '<button type="button" class="btn ghost" data-act="cancelar">Cancelar</button>' : ''}
    </form>
    <h2>Mensaje para WhatsApp</h2>
    <div class="card"><label>Escriban su mensaje. <b>{nombre}</b> se reemplaza por el nombre de la invitación y <b>{link}</b> por su link.
      <textarea id="wa-msg" rows="3"></textarea></label><span class="muted" id="wa-ok"></span></div>
    <h2>Invitaciones</h2>
    <input type="search" placeholder="Buscar por nombre…" data-filtro>
    ${D().grupos.map(g => { const link = base + g.token; return `
      <div class="card" data-buscar="${e((g.nombre + ' ' + pers(g.id).map(p => p.nombre).join(' ')).toLowerCase())}">
        <div class="row"><strong>${e(g.nombre)} ${g.respondido ? '<span class="tag">Respondió</span>' : '<span class="tag nino">Sin responder</span>'}</strong>
          <span class="muted">${fecha(g.respondido)}</span></div>
        <ul>${pers(g.id).map(p => `<li>${e(p.nombre)}${menor(p)} ${tagsPersona(p)}</li>`).join('')}</ul>
        ${g.telefono || g.comentarios ? `<div class="muted">${g.telefono ? 'Tel: ' + e(g.telefono) + ' ' : ''}${g.comentarios ? '· “' + e(g.comentarios) + '”' : ''}</div>` : ''}
        <div class="acciones">
          <input class="link" readonly value="${e(link)}">
          <button class="btn" type="button" data-copiar="${e(link)}">Copiar link</button>
          <a class="btn ghost" href="${e(link)}" target="_blank">Abrir</a>
          <a class="btn ghost wa" target="_blank" rel="noopener" data-nombre="${e(g.nombre)}" data-link="${e(link)}" href="#">WhatsApp</a>
          <button class="btn ghost" data-editar="${g.id}">Editar</button>
          <button class="btn ghost" data-borrar="${g.id}">Borrar</button>
        </div></div>`; }).join('') || '<p class="muted">Todavía no cargaron invitaciones.</p>'}`;
  },
  lista() {
    const si = [], no = [], pend = [];
    for (const g of D().grupos) {
      const ps = pers(g.id), van = ps.filter(p => p.asiste === 'si');
      if (!g.respondido) pend.push([g.nombre, ps, []]);
      else if (van.length) si.push([g.nombre, van, ps.filter(p => p.asiste === 'no')]);
      else no.push([g.nombre, ps, []]);
    }
    for (const c of D().confirmaciones) {
      const ps = [{ nombre: c.nombre, tipo: 'adulto' }, ...acomp(c.id)];
      (c.asistencia === 'si' ? si : no).push([c.nombre + ' (confirmación suelta)', ps, []]);
    }
    const bloque = (titulo, l, vacio) => `
      <h2>${titulo} <span class="tag">${l.length} invitaciones · ${l.reduce((n, x) => n + x[1].length, 0)} personas</span></h2>
      ${l.length ? `<div class="card"><ul class="lista">${l.map(([n, ps, nov]) => `<li><strong>${e(n)}</strong>: ${ps.map(p => e(p.nombre) + menor(p)).join(', ')}${nov.length ? `<span class="muted"> · no van: ${e(nov.map(p => p.nombre).join(', '))}</span>` : ''}</li>`).join('')}</ul></div>` : `<p class="muted">${vacio}</p>`}`;
    return bloque('Confirmaron', si, 'Todavía nadie confirmó.') + bloque('Sin responder', pend, 'Todos respondieron.') + bloque('No asisten', no, 'Nadie avisó que no va.');
  },
  confirmados() {
    const c = D().confirmaciones;
    return `<h2>Confirmaciones sin link personal</h2>${c.length ? '' : '<p class="muted">Todavía no hay confirmaciones.</p>'}
      ${c.slice().reverse().map(c => `<div class="card"><div class="row"><strong>${e(c.nombre)} ${c.asistencia === 'si' ? `<span class="tag">${c.personas} persona${c.personas == 1 ? '' : 's'}</span>` : '<span class="tag no">No asiste</span>'}</strong><span class="muted">${fecha(c.creado)}</span></div>
        ${c.menu || c.telefono ? `<div class="muted">${c.menu ? 'Menú: ' + e(c.menu) : ''}${c.menu && c.telefono ? ' · ' : ''}${c.telefono ? 'Tel: ' + e(c.telefono) : ''}</div>` : ''}
        ${acomp(c.id).length ? `<ul>${acomp(c.id).map(a => `<li>${e(a.nombre)} <span class="tag ${a.tipo === 'nino' ? 'nino' : ''}">${a.tipo === 'nino' ? 'Menor' : 'Adulto'}</span>${a.restriccion ? ` <span class="tag">${e(a.restriccion)}</span>` : ''}</li>`).join('')}</ul>` : ''}</div>`).join('')}`;
  },
  menus() {
    const inf = [], m = {};
    for (const p of D().personas) if (p.asiste === 'si') { if (p.menu_tipo === 'infantil') inf.push(p.nombre); if (p.menu) (m[p.menu] ??= []).push(p.nombre); }
    for (const c of D().confirmaciones) if (c.asistencia === 'si') {
      if (c.menu) (m[c.menu] ??= []).push(c.nombre);
      for (const a of acomp(c.id)) if (a.restriccion) (m[a.restriccion] ??= []).push(`${a.nombre} (de ${c.nombre})`);
    }
    const card = (t, l) => `<div class="card"><div class="row"><strong>${e(t)}</strong><span class="tag">${l.length}</span></div>${l.length ? `<ul>${l.map(x => `<li>${e(x)}</li>`).join('')}</ul>` : '<p class="muted">Nadie lo pidió por ahora.</p>'}</div>`;
    return `<h2>Menú infantil</h2>${card('Menú infantil', inf)}<h2>Menús especiales</h2>${Object.keys(m).length ? Object.entries(m).map(([k, v]) => card(k, v)).join('') : '<p class="muted">Nadie pidió menú especial por ahora.</p>'}`;
  },
  canciones() {
    return `<h2>Canciones sugeridas <button class="btn ghost" data-csv="canciones">Descargar Excel</button></h2>
      ${D().canciones.map(s => `<div class="card row"><div><strong>${e(s.cancion)}</strong>${s.artista ? ' — ' + e(s.artista) : ''}
        <div class="muted">Sugerida por ${e(s.nombre)} · <a target="_blank" rel="noopener" href="https://open.spotify.com/search/${encodeURIComponent(s.cancion + ' ' + s.artista)}">Buscar en Spotify</a></div></div>
        <span class="muted">${fecha(s.creado)}</span></div>`).join('') || '<p class="muted">Todavía no hay canciones.</p>'}`;
  },
  mensajes() {
    return `<h2>Mensajes <button class="btn ghost" data-csv="mensajes">Descargar Excel</button></h2>
      ${D().mensajes.map(m => `<div class="card"><div class="row"><strong>${e(m.nombre)}</strong><span class="muted">${fecha(m.creado)}</span></div><blockquote>${e(m.mensaje)}</blockquote></div>`).join('') || '<p class="muted">Todavía no hay mensajes.</p>'}`;
  },
  fotos() {
    const f = D().fotos;
    return `<h2>Fotos de los invitados</h2>
      <p class="muted">En la versión real las fotos se guardan en una carpeta de Google Drive de los novios. Probá subir una desde la invitación (botón "Subir fotos").</p>
      ${f.length ? `<div class="fotos">${f.map(x => `<figure>${x.thumb ? `<img src="${x.thumb}" alt="">` : '<img alt="">'}<figcaption>${e(x.nombre)} · ${fecha(x.creado)}</figcaption></figure>`).join('')}</div>` : '<p class="muted">Todavía no subieron fotos.</p>'}`;
  },
};

const fila = p => `<div class="fila"><input name="p_nombre" placeholder="Nombre y apellido" maxlength="100" value="${e(p.nombre)}" data-id="${p.id || ''}">
  <label class="menor"><input type="checkbox" name="p_menor" ${p.tipo === 'nino' ? 'checked' : ''}> Menor</label>
  <button type="button" class="quitar" aria-label="Quitar">×</button></div>`;

function guardarGrupo(form) {
  const d = D(), nombre = form.nombre.value.trim();
  const filas = [...form.querySelectorAll('.fila')].map(f => ({ id: +f.querySelector('[name=p_nombre]').dataset.id || 0, nombre: f.querySelector('[name=p_nombre]').value.trim(), tipo: f.querySelector('[name=p_menor]').checked ? 'nino' : 'adulto' })).filter(f => f.nombre);
  if (!nombre || !filas.length) { alert('Poné el nombre de la invitación y al menos una persona.'); return; }
  let g = editar && d.grupos.find(x => x.id === editar);
  if (!g) { g = { id: Math.max(0, ...d.grupos.map(x => x.id)) + 1, token: token(), telefono: '', comentarios: '', respondido: null }; d.grupos.push(g); }
  g.nombre = nombre;
  const quedan = [];
  for (const f of filas) {
    const p = f.id && d.personas.find(x => x.id === f.id && x.grupo_id === g.id);
    if (p) { p.nombre = f.nombre; p.tipo = f.tipo; quedan.push(p.id); }
    else { const id = ++d.seq; d.personas.push({ id, grupo_id: g.id, nombre: f.nombre, tipo: f.tipo, asiste: null, menu: null, menu_tipo: null }); quedan.push(id); }
  }
  d.personas = d.personas.filter(p => p.grupo_id !== g.id || quedan.includes(p.id));
  editar = null; window.DEMO.guardar(); render();
}
const token = () => Array.from(crypto.getRandomValues(new Uint8Array(10)), b => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'[b % 56]).join('');

function csv(nombre, cab, filas) {
  const q = v => { v = String(v ?? ''); return /[;"\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
  const txt = '﻿' + [cab, ...filas].map(f => f.map(q).join(';')).join('\n');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([txt], { type: 'text/csv;charset=utf-8' })); a.download = nombre; a.click();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}
const exportar = {
  invitados: () => {
    const f = [];
    for (const g of D().grupos) for (const p of pers(g.id)) f.push([g.nombre, p.asiste ?? 'sin responder', p.nombre, p.tipo === 'nino' ? 'menor' : 'adulto', p.asiste === 'si' ? (p.menu_tipo || 'adulto') : '', p.asiste === 'si' ? (p.menu || '') : '', g.telefono, g.comentarios, g.respondido || '']);
    for (const c of D().confirmaciones) { f.push([c.nombre, c.asistencia, c.nombre, 'adulto', '', c.menu, c.telefono, c.comentarios, c.creado]);
      for (const a of acomp(c.id)) f.push([c.nombre, c.asistencia, a.nombre, a.tipo === 'nino' ? 'menor' : 'adulto', '', a.restriccion || '', '', '', c.creado]); }
    csv('invitados-demo.csv', ['Invitado principal', 'Asiste', 'Nombre', 'Tipo', 'Menú', 'Menú especial', 'Teléfono', 'Comentarios', 'Fecha'], f);
  },
  canciones: () => csv('canciones-demo.csv', ['Canción', 'Artista', 'Sugerida por', 'Fecha'], D().canciones.map(c => [c.cancion, c.artista, c.nombre, c.creado])),
  mensajes: () => csv('mensajes-demo.csv', ['Nombre', 'Mensaje', 'Fecha'], D().mensajes.map(m => [m.nombre, m.mensaje, m.creado])),
};

app.addEventListener('click', ev => {
  const t = ev.target.closest('[data-tab],[data-act],[data-csv],[data-copiar],[data-editar],[data-borrar],a.wa,.quitar');
  if (!t) return;
  if (t.dataset.tab) { ev.preventDefault(); tab = t.dataset.tab; editar = null; render(); scrollTo(0, 0); }
  else if (t.dataset.csv) exportar[t.dataset.csv]();
  else if (t.dataset.copiar) navigator.clipboard.writeText(t.dataset.copiar).then(() => { t.textContent = '¡Copiado!'; setTimeout(() => t.textContent = 'Copiar link', 1500); });
  else if (t.dataset.editar) { editar = +t.dataset.editar; tab = 'invitados'; render(); scrollTo(0, 0); }
  else if (t.dataset.borrar) { const g = D().grupos.find(x => x.id === +t.dataset.borrar);
    if (confirm(`¿Borrar la invitación de ${g.nombre}?`)) { D().grupos = D().grupos.filter(x => x !== g); D().personas = D().personas.filter(p => p.grupo_id !== g.id); window.DEMO.guardar(); render(); } }
  else if (t.classList.contains('wa')) { let m = (document.getElementById('wa-msg')?.value.trim()) || WA_DEF; if (!m.includes('{link}')) m += ' {link}';
    t.href = 'https://wa.me/?text=' + encodeURIComponent(m.replaceAll('{nombre}', t.dataset.nombre).replaceAll('{link}', t.dataset.link)); }
  else if (t.classList.contains('quitar')) t.parentNode.remove();
  else if (t.dataset.act === 'fila') { document.getElementById('filas').insertAdjacentHTML('beforeend', fila({ nombre: '', tipo: 'adulto' })); document.querySelector('#filas .fila:last-child input').focus(); }
  else if (t.dataset.act === 'cancelar') { editar = null; render(); }
  else if (t.dataset.act === 'reset') { if (confirm('¿Volver a los datos de ejemplo? Se borra lo que cargaste.')) { window.DEMO.reiniciar(); tab = 'invitados'; editar = null; render(); } }
  else if (t.dataset.act === 'salir') { try { sessionStorage.removeItem('demo-panel'); } catch {} app.hidden = true; login.hidden = false; }
});
app.addEventListener('submit', ev => { if (ev.target.id === 'form-grupo') { ev.preventDefault(); guardarGrupo(ev.target); } });
app.addEventListener('input', ev => { if (ev.target.matches('[data-filtro]')) { const q = ev.target.value.toLowerCase(); document.querySelectorAll('[data-buscar]').forEach(c => c.hidden = !c.dataset.buscar.includes(q)); } });
