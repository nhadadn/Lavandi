<?php

/*
|--------------------------------------------------------------------------
| El aviso de privacidad y los términos y condiciones
|--------------------------------------------------------------------------
|
| NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7; openspec/changes/textos-legales/.
|
| Los dos documentos son de EL CLIENTE: aquí no se redactan ni se cambian (DD-080). Cada uno se publica en su dirección
| fija, /aviso-de-privacidad y /terminos, con el texto vigente tal como se cargó. El texto vigente no vive en el código
| ni en la base: es un archivo de texto UTF-8 en el almacenamiento privado del entorno (ADR-0006), así que una versión
| nueva entra sin modificar ni volver a desplegar el código (textos-legales-E04):
|
|     php artisan textos-legales:cargar aviso-de-privacidad /ruta/al/aviso-de-privacidad.txt
|
| - disco: uno de config/filesystems.php, siempre privado. En producción, textos_legales_s3, el almacenamiento S3 privado
|   del entorno; fuera de producción, textos_legales_local, una carpeta privada del servidor. El servidor lee el archivo
|   y lo muestra en la página: el archivo nunca se sirve directo.
| - documentos: cada uno con su título, que es el nombre que le da el B.7, y el nombre de su archivo en el disco. La
|   llave es la que recibe el comando y la que da su dirección.
|
| Sin el documento cargado, en producción la página dice que todavía no está disponible, sin ningún texto de ejemplo
| (textos-legales-E02); fuera de producción muestra un marcador de contenido de prueba, como la portada.
|
*/

return [

    'disco' => env(
        'TEXTOS_LEGALES_DISCO',
        env('APP_ENV') === 'production' ? 'textos_legales_s3' : 'textos_legales_local',
    ),

    'documentos' => [
        'aviso-de-privacidad' => [
            'titulo' => 'Aviso de privacidad',
            'archivo' => 'aviso-de-privacidad.txt',
        ],
        // Los "términos de servicio" del A.2 (NSV-2026-001-v8.pdf:15) se toman como estos mismos, salvo que EL CLIENTE
        // diga otra cosa (DD-080; design.md, pregunta 5).
        'terminos' => [
            'titulo' => 'Términos y condiciones',
            'archivo' => 'terminos.txt',
        ],
    ],

];
