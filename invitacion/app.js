'use strict';
// Configuración editable. Los enlaces null abren formularios de prueba locales.
const CONFIG = {
  ceremony: '2026-12-08T13:00:00-03:00',
  alias: 'BODACAMILAYJUAN',
  links: { rsvp: null, song: null, message: null, photos: null }
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
let currentAction;
let photoUrls = [];
const forms = {
  rsvp: {title:'Confirmá tu asistencia',fields:'<label>Nombre y apellido<input name="nombre" autocomplete="name" maxlength="100" required></label><label>¿Vas a asistir?<select name="asistencia"><option value="si">Sí, voy a asistir</option><option value="no">No puedo asistir</option></select></label><label>Menú especial o comentarios<textarea name="menu" maxlength="1000"></textarea></label>'},
  song: {title:'Sugerí una canción',fields:'<label>Tu nombre<input name="nombre" autocomplete="name" maxlength="100" required></label><label>Canción y artista<input name="cancion" maxlength="200" required></label>'},
  message: {title:'Dejanos un mensaje',fields:'<label>Tu nombre<input name="nombre" autocomplete="name" maxlength="100" required></label><label>Mensaje<textarea name="mensaje" maxlength="2000" required></textarea></label>'}
};
document.querySelectorAll('[data-action]').forEach(button => button.addEventListener('click', () => {
  currentAction = button.dataset.action;
  const link = CONFIG.links[currentAction];
  if (link) { window.open(link, '_blank', 'noopener,noreferrer'); return; }
  const isPhotos = currentAction === 'photos';
  $('#dialog-title').textContent = isPhotos ? 'Compartí tus fotos' : forms[currentAction].title;
  $('#guest-form').hidden = isPhotos;
  $('#photo-panel').hidden = !isPhotos;
  if (!isPhotos) {
    $('#form-fields').innerHTML = forms[currentAction].fields;
    try {
      const saved = JSON.parse(localStorage.getItem(`boda-cj-${currentAction}`) || 'null');
      if (saved) Object.entries(saved).forEach(([name, value]) => {
        const field = $('#guest-form').elements.namedItem(name);
        if (field) field.value = value;
      });
    } catch { /* La vista de prueba funciona aunque el almacenamiento no esté disponible. */ }
  }
  dialog.showModal();
}));
$('.close-dialog').addEventListener('click', () => dialog.close());
dialog.addEventListener('click', event => { if (event.target === dialog) {
  const r = dialog.getBoundingClientRect();
  if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close();
}});
$('#guest-form').addEventListener('submit', event => {
  event.preventDefault();
  const values = Object.fromEntries(new FormData(event.currentTarget));
  try {
    localStorage.setItem(`boda-cj-${currentAction}`, JSON.stringify(values));
    dialog.close(); toast('Borrador guardado en este dispositivo. No se envió.');
  } catch { toast('No se pudo guardar el borrador en este dispositivo.'); }
});
$('#photo-input').addEventListener('change', event => {
  photoUrls.forEach(url => URL.revokeObjectURL(url)); photoUrls = [];
  $('#photo-preview').replaceChildren();
  const files = Array.from(event.target.files);
  if (files.length > 12) toast('Se muestran las primeras 12 fotos.');
  files.slice(0,12).forEach(file => {
    if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024) {
      toast('Elegí JPG, PNG o WebP de hasta 10 MB por foto.'); return;
    }
    const image = document.createElement('img');
    const url = URL.createObjectURL(file); photoUrls.push(url);
    image.src = url; image.alt = 'Vista previa de tu foto';
    $('#photo-preview').append(image);
  });
});
dialog.addEventListener('close', () => {
  photoUrls.forEach(url => URL.revokeObjectURL(url)); photoUrls = [];
  $('#photo-preview').replaceChildren(); $('#photo-input').value = '';
});
