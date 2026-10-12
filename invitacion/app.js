'use strict';
// Configuración editable.
const CONFIG = {
  ceremony: '2026-12-08T13:00:00-03:00',
  alias: 'BODACAMILAYJUAN',
  albumUrl: null // cuando tengamos el link del álbum, se muestra el QR
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
    intro: 'Subí tus fotos de la fiesta con #BodaDeCamiYJuan. JPG, PNG o WebP de hasta 10 MB cada una.',
    submit: 'Subir fotos',
    done: '¡Gracias! Tus fotos se subieron.',
    fields: `
      <label>Tu nombre<input name="nombre" autocomplete="name" maxlength="100" required></label>
      <label class="file-label">Elegir fotos<input type="file" name="fotos[]" id="photo-input" accept="image/jpeg,image/png,image/webp" multiple required></label>
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
          <label><input type="radio" name="acompanante_tipo_${i}" value="nino"> Niño/a</label>
        </div>`;
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
  const form = forms[currentAction];
  $('#dialog-title').textContent = form.title;
  $('#dialog-intro').textContent = form.intro;
  $('#form-fields').innerHTML = form.fields;
  $('#form-submit').textContent = form.submit;
  $('#form-submit').disabled = false;
  showError('');
  try { const nombre = localStorage.getItem('boda-cj-nombre'); if (nombre) $('#guest-form').elements.nombre.value = nombre; } catch {}
  if (currentAction === 'photos') $('#photo-input').addEventListener('change', previewPhotos);
  if (currentAction === 'rsvp') setupCompanions();
  dialog.showModal();
}));

function previewPhotos(event) {
  photoUrls.forEach(url => URL.revokeObjectURL(url)); photoUrls = [];
  $('#photo-preview').replaceChildren();
  const files = Array.from(event.target.files);
  const bad = files.find(f => !['image/jpeg','image/png','image/webp'].includes(f.type) || f.size > 10 * 1024 * 1024);
  if (bad) { showError('Elegí JPG, PNG o WebP de hasta 10 MB por foto.'); event.target.value = ''; return; }
  if (files.length > 20) { showError('Podés subir hasta 20 fotos por vez.'); event.target.value = ''; return; }
  showError('');
  files.forEach(file => {
    const image = document.createElement('img');
    const url = URL.createObjectURL(file); photoUrls.push(url);
    image.src = url; image.alt = '';
    $('#photo-preview').append(image);
  });
}

$('.close-dialog').addEventListener('click', () => dialog.close());
dialog.addEventListener('click', event => { if (event.target === dialog) {
  const r = dialog.getBoundingClientRect();
  if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close();
}});

$('#guest-form').addEventListener('submit', async event => {
  event.preventDefault();
  const formEl = event.currentTarget;
  if (!formEl.checkValidity()) { formEl.reportValidity(); return; }
  const data = new FormData(formEl);
  data.append('tipo', currentAction);
  const endpoint = currentAction === 'photos' ? 'fotos.php' : 'guardar.php';
  $('#form-submit').disabled = true; showError('');
  try {
    const response = await fetch(API + endpoint, { method: 'POST', body: data });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || !result.ok) throw new Error(result.error || 'No pudimos guardar. Probá de nuevo en un rato.');
    try { localStorage.setItem('boda-cj-nombre', data.get('nombre')); } catch {}
    dialog.close(); toast(forms[currentAction].done);
  } catch (error) {
    showError(error.message === 'Failed to fetch' ? 'Sin conexión. Probá de nuevo.' : error.message);
    $('#form-submit').disabled = false;
  }
});

dialog.addEventListener('close', () => {
  photoUrls.forEach(url => URL.revokeObjectURL(url)); photoUrls = [];
  $('#form-fields').replaceChildren();
});
