<?php

/*
|--------------------------------------------------------------------------
| La medición de la landing
|--------------------------------------------------------------------------
|
| NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2; ADR-0011; openspec/changes/medicion-de-la-landing/.
|
| Google Analytics 4 y Meta Pixel entran por un solo contenedor de Google Tag Manager; sus etiquetas se configuran en la
| cuenta de GTM, no aquí. Las cuentas de producción son de EL CLIENTE (NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.11);
| local y prueba pueden usar un contenedor de prueba de EL PRESTADOR (ADR-0012). Ningún identificador se escribe en el
| código: cada entorno pone el suyo en su .env.
|
*/

return [

    /*
     * El identificador del contenedor de Google Tag Manager de este entorno: "GTM-" seguido de letras mayúsculas y
     * números, como lo da la cuenta de GTM. Vacío, o con algo que no tiene esa forma, las páginas públicas no cargan
     * ningún código de medición de terceros (medicion-E02; App\Medicion\ContenedorDeEtiquetas).
     */
    'gtm' => env('MEDICION_GTM'),

];
