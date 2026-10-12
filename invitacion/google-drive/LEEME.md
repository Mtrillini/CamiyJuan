# Fotos de los invitados en Google Drive

1. En Google Drive creá una carpeta (por ejemplo "Fotos Boda Cami y Juan").
   Abrila y copiá su ID: lo que viene después de `/folders/` en la URL.
2. Entrá a https://script.google.com → **Nuevo proyecto**.
   Borrá lo que haya y pegá el contenido de `Code.gs`.
3. Completá `FOLDER_ID` con el ID de la carpeta y `SECRET` con una clave larga inventada.
4. **Implementar → Nueva implementación → Tipo: Aplicación web.**
   - Ejecutar como: **Yo**
   - Quién tiene acceso: **Cualquier usuario**
   Autorizá los permisos que pide (acceso a tu Drive).
5. Copiá la URL que termina en `/exec`.
6. En el servidor, en `api/config.php`, completá:
   - `drive_script_url` → la URL del paso 5
   - `drive_secret` → la misma clave del paso 3

Las fotos también quedan guardadas en `uploads/` del servidor como respaldo.
Si cambiás el código del script, hay que hacer **Implementar → Gestionar implementaciones → Editar → Nueva versión**.
