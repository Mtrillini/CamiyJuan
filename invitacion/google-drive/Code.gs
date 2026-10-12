// Apps Script que recibe las fotos de la invitación y las guarda en una carpeta de Drive.
// Instrucciones en google-drive/LEEME.md.

// 1) ID de la carpeta de Drive: es lo que sigue a /folders/ en la URL de la carpeta.
const FOLDER_ID = '1UFIXWS8BP8uT1RmC0wbQW-q6f3qxisXl'; // carpeta Camiyjuan
// 2) Una clave inventada, larga. La misma va en api/config.php como drive_secret.
const SECRET = 'CAMBIAR_POR_TU_CLAVE'; // inventá una clave larga y anotala

const ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

function doPost(e) {
  try {
    const body = JSON.parse(e.postData.contents);
    if (body.secret !== SECRET) return reply({ ok: false, error: 'no autorizado' });
    if (!ALLOWED.includes(body.mimeType)) return reply({ ok: false, error: 'tipo no permitido' });
    const name = String(body.name).replace(/[^\w.\-]/g, '_').slice(0, 100);
    const blob = Utilities.newBlob(Utilities.base64Decode(body.data), body.mimeType, name);
    const file = DriveApp.getFolderById(FOLDER_ID).createFile(blob);
    file.setDescription('Subida por: ' + String(body.author || '').slice(0, 100));
    return reply({ ok: true, id: file.getId() });
  } catch (err) {
    return reply({ ok: false, error: String(err) });
  }
}

function reply(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}
