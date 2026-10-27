'use strict';
// Vista de prueba de tipografía: ?fuente=quicksand | nunito | comfortaa (no cambia nada si no se pasa)
(() => {
  const f = new URLSearchParams(location.search).get('fuente') || 'josefin';
  const fam = { quicksand: 'Quicksand', nunito: 'Nunito', comfortaa: 'Comfortaa', josefin: 'Josefin Sans', raleway: 'Raleway', jost: 'Jost', poppins: 'Poppins', montserrat: 'Montserrat', lexend: 'Lexend' }[f];
  if (!fam) return;
  const l = document.createElement('link'); l.rel = 'stylesheet';
  l.href = 'https://fonts.googleapis.com/css2?family=' + fam.replace(/ /g, '+') + ':wght@200;300;400;500&display=swap';
  document.head.append(l);
  document.documentElement.classList.add('fuente-prueba');
  document.documentElement.style.setProperty('--fuente', "'" + fam + "', sans-serif");
  document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('.names img').forEach(i => {
    i.src = 'assets/nombres-' + f + '.png?v=4'; i.removeAttribute('height');
  }));
})();
// Música si entran directo a la invitación (sin el sobre): arranca con el primer toque y se corta al salir.
(() => {
  try { if (window.parent !== window && window.parent.document.getElementById('sobre')) return; } catch (e) {}
  const m = new Audio('assets/musica.mp3?v=1'); m.loop = true; let on = false;
  const go = () => { on = true; m.play().catch(() => {}); };
  ['pointerdown', 'keydown', 'touchstart'].forEach(ev => addEventListener(ev, go, { once: true, passive: true }));
  document.addEventListener('visibilitychange', () => { if (document.hidden) m.pause(); else if (on) m.play().catch(() => {}); });
  addEventListener('pagehide', () => m.pause());
})();
// Animaciones de carga: el encabezado entra en cascada y el resto al hacer scroll.
document.documentElement.classList.add('anim');
// Viniendo de la carta: la foto ya está (continúa el zoom) y los textos aparecen alrededor.
try { if (window.parent !== window && window.parent.document.getElementById('sobre')) document.documentElement.classList.add('from-card'); } catch (e) {}
// Cuando termina de descubrirse, sacamos la máscara (así no queda nada tapado al hacer scroll).
document.querySelector('.invitation').addEventListener('animationend', e => { if (e.animationName === 'descubrir') e.currentTarget.classList.add('revealed'); });
setTimeout(() => document.querySelector('.invitation').classList.add('revealed'), 3200);
const revealables = document.querySelectorAll('.palace-img, .venue-card, .dress-code, .attendance > *, .gifts, footer');
revealables.forEach(el => el.classList.add('reveal'));
// Lo que está más abajo espera a que termine de entrar el encabezado;
// si ya está a la vista, entra después, de a uno.
const ESPERA_ENCABEZADO = 1900;
setTimeout(() => {
  if (!('IntersectionObserver' in window)) { revealables.forEach(el => el.classList.add('visible')); return; }
  let cola = 0;
  const io = new IntersectionObserver(entries => entries.forEach(e => {
    if (!e.isIntersecting) return;
    io.unobserve(e.target);
    const el = e.target;
    setTimeout(() => el.classList.add('visible'), cola++ * 180);
    setTimeout(() => { cola = Math.max(0, cola - 1); }, 400);
  }), { rootMargin: '0px 0px -5% 0px', threshold: 0.01 });
  revealables.forEach(el => io.observe(el));
}, ESPERA_ENCABEZADO);
// Red de seguridad: si algún navegador no dispara el observer, todo aparece igual.
setTimeout(() => revealables.forEach(el => el.classList.add('visible')), 7000);
// Configuración editable.
const CONFIG = {
  ceremony: '2026-12-08T13:00:00-03:00',
  photosFrom: '2026-12-07T00:00:00-03:00', // subir fotos se habilita un día antes
  alias: 'BODACAMILAYJUAN',
};
const $ = (selector) => document.querySelector(selector);
function countdown(now = Date.now()) {
  const remaining = Math.max(0, new Date(CONFIG.ceremony).getTime() - now);
  $('#days').textContent = Math.floor(remaining / 86400000);
  $('#hours').textContent = String(Math.floor(remaining / 3600000) % 24).padStart(2, '0');
  $('#minutes').textContent = String(Math.floor(remaining / 60000) % 60).padStart(2, '0');
  $('#event-status').textContent = remaining === 0 ? '¡Llegó el día de la ceremonia!' : '';
  return remaining;
}
countdown();
const timer = setInterval(() => { if (!countdown()) clearInterval(timer); }, 1000);
// Solo se muestran las imágenes que ya existen. Sin íconos de archivos rotos.
document.querySelectorAll('[data-image]').forEach(slot => {
  const image = new Image();
  image.onload = () => { slot.style.backgroundImage = `url("${slot.dataset.image}")`; slot.classList.add('has-image'); };
  image.src = slot.dataset.image;
});
let toastTimer;
function toast(message) {
  $('#toast').textContent = message;
  $('#toast').hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { $('#toast').hidden = true; }, 4500);
}
$('#copy-alias').addEventListener('click', async () => {
  try { await navigator.clipboard.writeText(CONFIG.alias); toast('Alias copiado'); }
  catch { window.prompt('Copiá el alias:', CONFIG.alias); }
});
const dialog = $('#form-dialog');
const API = 'api/';
let currentAction;
let photoUrls = [];

// Link personalizado: camiyjuan.com/?i=TOKEN → la confirmación muestra las personas que cargaron los novios.
const TOKEN = new URLSearchParams(location.search).get('i');
let invitacion = null;
if (TOKEN) fetch(API + 'grupo.php?t=' + encodeURIComponent(TOKEN))
  .then(r => r.ok ? r.json() : null).then(d => {
    if (!d || !d.ok) return;
    invitacion = d;
  }).catch(() => {});

const MENU_OPC = ['Común', 'Vegetariano', 'Vegano', 'Sin TACC', 'Otra'];
const esc = t => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
function camposGrupo() {
  const filas = invitacion.personas.map(p => `
    <fieldset class="persona" data-id="${p.id}">
      <legend>${esc(p.nombre)}${p.tipo === 'nino' ? ' <small>(menor)</small>' : ''}</legend>
      <div class="segmented">
        <label><input type="radio" name="asiste_${p.id}" value="si" ${p.asiste !== 'no' ? 'checked' : ''}> Asiste</label>
        <label><input type="radio" name="asiste_${p.id}" value="no" ${p.asiste === 'no' ? 'checked' : ''}> No asiste</label>
      </div>
      <label class="menu-row">Menú
        <select name="menu_${p.id}">${MENU_OPC.map(m => `<option value="${m === 'Común' ? '' : m}" ${(p.menu || '') === (m === 'Común' ? '' : m) ? 'selected' : ''}>${m}</option>`).join('')}</select>
      </label>
    </fieldset>`).join('');
  return filas + `
    <label>Teléfono (opcional)<input name="telefono" type="tel" maxlength="30" value="${esc(invitacion.grupo.telefono || '')}"></label>
    <label>Alergias o comentarios<textarea name="comentarios" maxlength="1000">${esc(invitacion.grupo.comentarios || '')}</textarea></label>
    <input type="hidden" name="token" value="${esc(TOKEN)}">`;
}
function activarGrupo() {
  document.querySelectorAll('.persona').forEach(f => {
    const upd = () => { const si = f.querySelector('input[value=si]').checked; f.querySelector('.menu-row').hidden = !si; f.classList.toggle('no-asiste', !si); };
    f.querySelectorAll('input[type=radio]').forEach(r => r.addEventListener('change', upd)); upd();
  });
}

const forms = {
  rsvp: {
    title: 'Confirmá tu asistencia',
    intro: 'Te pedimos que confirmes antes del 24 de noviembre.',
    submit: 'Confirmar',
    done: '¡Gracias! Recibimos tu confirmación.',
    fields: `
      <label>Nombre y apellido<input name="nombre" autocomplete="name" maxlength="100" required></label>
      <fieldset class="choice"><legend>¿Vas a asistir?</legend>
        <label><input type="radio" name="asistencia" value="si" checked> Sí, ahí estaré</label>
        <label><input type="radio" name="asistencia" value="no"> No puedo asistir</label>
      </fieldset>
      <div id="companions-block">
        <label>¿Cuántos acompañantes vienen con vos?<input name="acompanantes" id="companions-count" type="number" min="0" max="9" value="0" inputmode="numeric" required></label>
        <div id="companions"></div>
      </div>
      <label>Teléfono (opcional)<input name="telefono" type="tel" autocomplete="tel" maxlength="30"></label>
      <label>Menú especial
        <select name="menu"><option value="">Ninguno</option><option>Vegetariano</option><option>Vegano</option><option>Sin TACC</option><option>Otro (lo aclaro abajo)</option></select></label>
      <label>Alergias o comentarios<textarea name="comentarios" maxlength="1000"></textarea></label>`
  },
  song: {
    title: 'Sugerí una canción',
    intro: '¿Qué canción no puede faltar en la pista?',
    submit: 'Sugerir',
    done: '¡Gracias! Anotamos tu canción.',
    fields: `
      <label>Tu nombre<input name="nombre" autocomplete="name" maxlength="100" required></label>
      <label>Canción<input name="cancion" maxlength="150" required></label>
      <label>Artista<input name="artista" maxlength="150"></label>`
  },
  rsvp_grupo: {
    title: 'Confirmá tu asistencia',
    intro: 'Marcá quiénes asisten y su menú. Confirmá antes del 24 de noviembre.',
    submit: 'Confirmar',
    done: '¡Gracias! Recibimos tu confirmación.',
    get fields() { return camposGrupo(); }
  },
  message: {
    title: 'Dejanos un mensaje',
    intro: 'Unas palabras para Cami y Juan.',
    submit: 'Enviar mensaje',
    done: '¡Gracias por tu mensaje!',
    fields: `
      <label>Tu nombre<input name="nombre" autocomplete="name" maxlength="100" required></label>
      <label>Mensaje<textarea name="mensaje" maxlength="2000" required></textarea></label>`
  },
  photos: {
    title: 'Compartí tus fotos',
    intro: 'Sacá una foto o elegí varias de tu galería. Se guardan en el álbum de Cami y Juan.',
    submit: 'Subir fotos',
    done: '¡Gracias! Tus fotos se subieron.',
    fields: `
      <label>Tu nombre<input name="nombre" autocomplete="name" maxlength="100" required></label>
      <div class="photo-pickers">
        <label class="photo-pick"><svg><use href="#camera"/></svg>Sacar foto<input type="file" id="camera-input" accept="image/*" capture="environment"></label>
        <label class="photo-pick"><svg><use href="#image"/></svg>Elegir de la galería<input type="file" id="gallery-input" accept="image/*" multiple></label>
      </div>
      <div id="photo-preview"></div>`
  }
};

// Un bloque de nombre + adulto/niño por cada acompañante. Conserva lo ya escrito.
function setupCompanions() {
  const count = $('#companions-count');
  const list = $('#companions');
  const render = () => {
    const n = Math.max(0, Math.min(9, parseInt(count.value, 10) || 0));
    while (list.children.length > n) list.lastElementChild.remove();
    for (let i = list.children.length; i < n; i++) {
      const row = document.createElement('fieldset');
      row.className = 'companion';
      row.innerHTML = `<legend>Acompañante ${i + 1}</legend>
        <label>Nombre y apellido<input name="acompanante_nombre[]" maxlength="100" required></label>
        <div class="segmented" role="radiogroup" aria-label="Edad del acompañante ${i + 1}">
          <label><input type="radio" name="acompanante_tipo_${i}" value="adulto" checked> Adulto</label>
          <label><input type="radio" name="acompanante_tipo_${i}" value="nino"> Menor</label>
        </div>
        <label>Restricción alimentaria
          <select name="acompanante_menu[]"><option value="">Ninguna</option><option>Vegetariano</option><option>Vegano</option><option>Sin TACC</option><option>Otra</option></select></label>`;
      list.append(row);
    }
  };
  const toggle = () => {
    const going = $('#guest-form').elements.asistencia.value === 'si';
    $('#companions-block').hidden = !going;
    $('#companions-block').querySelectorAll('input').forEach(input => { input.disabled = !going; });
  };
  count.addEventListener('input', render);
  $('#guest-form').querySelectorAll('[name=asistencia]').forEach(r => r.addEventListener('change', toggle));
  render(); toggle();
}

function showError(text) { $('#form-error').textContent = text; $('#form-error').hidden = !text; }

document.querySelectorAll('[data-action]').forEach(button => button.addEventListener('click', () => {
  currentAction = button.dataset.action;
  if (currentAction === 'rsvp' && invitacion) currentAction = 'rsvp_grupo';
  const form = forms[currentAction];
  $('#dialog-title').textContent = form.title;
  $('#dialog-intro').textContent = form.intro;
  $('#form-fields').innerHTML = form.fields;
  $('#form-submit').textContent = form.submit;
  $('#form-submit').disabled = false;
  showError('');
  try { const nombre = localStorage.getItem('boda-cj-nombre'); if (nombre) $('#guest-form').elements.nombre.value = nombre; } catch {}
  if (currentAction === 'photos') {
    selectedPhotos = [];
    $('#camera-input').addEventListener('change', addPhotos);
    $('#gallery-input').addEventListener('change', addPhotos);
  }
  if (currentAction === 'rsvp') setupCompanions();
  if (currentAction === 'rsvp_grupo') activarGrupo();
  dialog.showModal();
}));

const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
const MAX_PHOTOS = 20;
let selectedPhotos = [];

// Suma fotos de la cámara o la galería a la selección y muestra la vista previa.
function addPhotos(event) {
  const files = Array.from(event.target.files);
  event.target.value = '';
  const bad = files.find(f => !PHOTO_TYPES.includes(f.type) || f.size > 10 * 1024 * 1024);
  if (bad) { showError('Cada foto tiene que ser una imagen de hasta 10 MB.'); return; }
  if (selectedPhotos.length + files.length > MAX_PHOTOS) { showError(`Podés subir hasta ${MAX_PHOTOS} fotos por vez.`); return; }
  showError('');
  files.forEach(file => {
    selectedPhotos.push(file);
    const image = document.createElement('img');
    const url = URL.createObjectURL(file); photoUrls.push(url);
    image.src = url; image.alt = '';
    $('#photo-preview').append(image);
  });
  $('#form-submit').textContent = `Subir ${selectedPhotos.length} foto${selectedPhotos.length === 1 ? '' : 's'}`;
}

$('.close-dialog').addEventListener('click', () => dialog.close());
dialog.addEventListener('click', event => { if (event.target === dialog) {
  const r = dialog.getBoundingClientRect();
  if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close();
}});

async function post(endpoint, body) {
  const response = await fetch(API + endpoint, { method: 'POST', body });
  const result = await response.json().catch(() => ({}));
  if (!response.ok || !result.ok) throw new Error(result.error || 'No pudimos guardar. Probá de nuevo en un rato.');
  return result;
}

$('#guest-form').addEventListener('submit', async event => {
  event.preventDefault();
  const formEl = event.currentTarget;
  if (!formEl.checkValidity()) { formEl.reportValidity(); return; }
  const data = new FormData(formEl);
  data.append('tipo', currentAction);
  if (currentAction === 'photos' && !selectedPhotos.length) { showError('Sacá o elegí al menos una foto.'); return; }
  $('#form-submit').disabled = true; showError('');
  try {
    if (currentAction === 'photos') {
      // De a una foto por envío: más liviano para el celular y para el servidor.
      // Si se corta a mitad de camino, las que ya subieron se sacan de la lista y no se repiten.
      const total = selectedPhotos.length;
      while (selectedPhotos.length) {
        $('#form-submit').textContent = `Subiendo ${total - selectedPhotos.length + 1} de ${total}…`;
        const one = new FormData();
        one.append('nombre', data.get('nombre'));
        one.append('website', data.get('website') || '');
        one.append('foto', selectedPhotos[0]);
        await post('fotos.php', one);
        selectedPhotos.shift();
        $('#photo-preview').firstElementChild?.remove();
      }
    } else {
      await post('guardar.php', data);
    }
    try { localStorage.setItem('boda-cj-nombre', data.get('nombre')); } catch {}
    dialog.close(); toast(forms[currentAction].done);
  } catch (error) {
    showError(error.message === 'Failed to fetch' ? 'Sin conexión. Probá de nuevo.' : error.message);
    $('#form-submit').disabled = false;
    $('#form-submit').textContent = forms[currentAction].submit;
  }
});

dialog.addEventListener('close', () => {
  photoUrls.forEach(url => URL.revokeObjectURL(url)); photoUrls = [];
  $('#form-fields').replaceChildren();
});

// El QR de las mesas apunta a .../invitacion/#fotos: abre directo la ventana de fotos.
// También acepta ?fotos por si algún lector de QR pierde el #.
const fotosHabilitadas = () => Date.now() >= new Date(CONFIG.photosFrom).getTime();
// Antes de la fecha el botón queda desactivado con el aviso.
function estadoFotos() {
  const boton = document.querySelector('[data-action=photos]');
  const on = fotosHabilitadas();
  boton.disabled = !on;
  boton.textContent = on ? 'Subir fotos' : 'Próximamente';
  $('#photos-soon').hidden = on;
}
estadoFotos();
setInterval(estadoFotos, 60000);

// Antes de la fecha: se abre la misma ventana, pero con el aviso en vez del formulario.
function mostrarFotosProximamente() {
  document.querySelector('.photos-card').scrollIntoView({ block: 'center' });
  $('#dialog-title').textContent = 'Compartí tus fotos';
  $('#dialog-intro').innerHTML = '¡Gracias por entrar! 📸<br>La subida de fotos se habilita el <strong>7 de diciembre</strong>. Guardá este link y volvé ese día para compartir las fotos de la fiesta.';
  $('#form-fields').replaceChildren();
  $('#form-error').hidden = true;
  $('#guest-form').hidden = true;
  if (!dialog.open) dialog.showModal();
}
dialog.addEventListener('close', () => { $('#guest-form').hidden = false; });

function abrirFotosSiCorresponde() {
  if (location.hash !== '#fotos' && !new URLSearchParams(location.search).has('fotos')) return;
  if (!fotosHabilitadas()) { mostrarFotosProximamente(); return; }
  const boton = document.querySelector('[data-action=photos]');
  boton.closest('.mini-card').scrollIntoView({ block: 'center' });
  if (!dialog.open) boton.click();
}
abrirFotosSiCorresponde();
addEventListener('hashchange', abrirFotosSiCorresponde);
addEventListener('pageshow', e => { if (e.persisted) abrirFotosSiCorresponde(); });
// Foto de la pareja: aparece desde difuminada cuando llega al deslizar.
(() => {
  const p = document.querySelector('.pareja');
  if (!p || !('IntersectionObserver' in window)) return;
  p.classList.add('oculta');
  const io = new IntersectionObserver(es => es.forEach(e => {
    if (e.isIntersecting) { p.classList.remove('oculta'); io.disconnect(); }
  }), { threshold: .25 });
  io.observe(p);
})();
