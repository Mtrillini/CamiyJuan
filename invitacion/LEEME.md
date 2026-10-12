# Invitación Camila & Juan

## Archivos
- `index.html`, `styles.css`, `app.js`: la invitación (paleta azul y marfil).
- `assets/`: fondos, nombres, línea ornamental y palacio.
- `api/guardar.php`: guarda confirmaciones, canciones y mensajes.
- `api/fotos.php`: recibe las fotos de los invitados y las guarda en `uploads/`.
- `api/schema.sql`: tablas de la base (MySQL / MariaDB).

## Poner en marcha (hosting con PHP 8.1+ y MySQL)
1. Crear la base y ejecutar `api/schema.sql` (por ejemplo desde phpMyAdmin).
2. Copiar `api/config.example.php` como `api/config.php` y completar los datos.
   `config.php` no se sube a GitHub.
3. Verificar que `uploads/` tenga permisos de escritura.

GitHub Pages no ejecuta PHP: los formularios solo guardan en un hosting con PHP.

## Pendiente
- Foto de los novios: `assets/pareja.jpg`.
- QR del álbum: poner el enlace en `CONFIG.albumUrl` de `app.js`.
