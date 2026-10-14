<?php
// Copiá este archivo como config.php (en el servidor) y completá los datos de la base.
// config.php NO se sube a GitHub.
return [
    'db_host' => 'localhost',
    'db_name' => 'NOMBRE_BASE',
    'db_user' => 'USUARIO',
    'db_pass' => 'CONTRASEÑA',
    // Carpeta donde se guardan las fotos de los invitados (fuera de api/).
    'uploads_dir' => __DIR__ . '/../uploads',
    // Google Drive (ver google-drive/LEEME.md). Si queda vacío, las fotos se guardan solo en uploads/.
    'drive_script_url' => '', // URL de la implementación del Apps Script (termina en /exec)
    'drive_secret' => '',     // la misma clave que pusiste en el Apps Script
    // Contraseña del panel de los novios (invitacion/admin)
    'admin_password' => '',
];
