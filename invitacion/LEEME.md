# Invitación Camila & Juan — base 1

Abrí index.html para probarla. Para subirla a tu hosting, copiá index.html, styles.css, app.js y la carpeta assets al mismo directorio público.

## Imágenes que armaremos después
- assets/fondo.png: papel marfil con hojas en relieve, sin texto. Se repite verticalmente.
- assets/pareja.jpg: una foto horizontal de los novios.
- assets/palacio.png: palacio con bordes suaves, idealmente fondo transparente.
- assets/caligrafia.woff2: tipografía caligráfica con licencia web. Sin este archivo se usa una cursiva disponible en el dispositivo; no se verá idéntica a la referencia.

El fondo CSS, las tarjetas y los espacios de imágenes permiten trabajar antes de tener los recursos definitivos. El relieve botánico y el palacio de la referencia se completan al sumar esas imágenes.

## Interacciones
- Cuenta regresiva: 8 de diciembre de 2026, 13:00, hora Argentina. Se configura en app.js.
- Ubicación: abre una búsqueda del lugar en Google Maps.
- Alias: lo copia al portapapeles (requiere HTTPS o localhost; si no, muestra el texto para copiar). Verificá el alias con los novios antes de publicar.
- Asistencia, canción y mensaje: formularios de demostración que guardan un borrador únicamente en localStorage del navegador. NO envían datos ni notifican a los novios.
- Fotos: vista previa local; NO se suben. No se almacenan al cerrar el diálogo.

Para conectar servicios reales, editá CONFIG.links en app.js con enlaces HTTPS a tus formularios o al álbum. Si querés guardar todo en tu propio hosting, el siguiente paso es conectar los formularios a endpoints PHP y una base de datos. El servidor debe validar los datos y los archivos.

No hay dependencias, instalación, servidor de desarrollo ni QR falso. El QR se agrega cuando tengamos el enlace real del álbum. No publiques los formularios locales como si fueran confirmaciones definitivas.

## Archivos
- index.html: textos, secciones y SVG de los íconos.
- styles.css: paleta, tipografías, tarjetas, responsive y diálogos.
- app.js: configuración e interacciones.
