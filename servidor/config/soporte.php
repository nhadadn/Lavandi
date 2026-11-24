<?php

/*
|--------------------------------------------------------------------------
| Soporte de la app
|--------------------------------------------------------------------------
|
| NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #16, "Chat de WhatsApp Business y formulario de reporte";
| openspec/changes/perfil-y-soporte/; decisión 27 de acta-2026-11-20-modelo-y-pasarela.
|
*/

return [

    /*
     * El número de WhatsApp Business de EL CLIENTE para el chat de soporte, en formato internacional (país y número).
     * Vive aquí y no en la app para que pueda llegar o cambiar sin publicar otra versión. La app lo lee de
     * GET /api/soporte, que lo entrega solo con sus dígitos (App\Soporte\NumeroDeSoporte).
     *
     * El número real es DD-082, de EL CLIENTE: mientras no llegue, va vacío, y la app muestra el chat como no
     * disponible (perfil-usuario-E10). Nunca se escribe en el código.
     */
    'whatsapp' => env('SOPORTE_WHATSAPP'),

];
