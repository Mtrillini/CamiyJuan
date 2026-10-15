<?php
// Imagen de la vista previa de WhatsApp: la que suben los novios desde el panel, o la de la carta por defecto.
$subida = __DIR__ . '/../uploads/vista-previa.jpg';
$file = is_file($subida) ? $subida : __DIR__ . '/../../entrada/vista-previa.jpg';
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=3600');
header('Content-Length: ' . filesize($file));
readfile($file);
