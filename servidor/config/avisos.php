<?php

/*
|--------------------------------------------------------------------------
| Avisos al celular
|--------------------------------------------------------------------------
|
| Los avisos salen por Firebase Cloud Messaging directo, el servicio gratuito de Google que nombra el contrato
| (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3; ADR-0008), con la API HTTP v1 (openspec/changes/avisos/design.md).
|
*/

return [

    'fcm' => [
        /*
         * La ruta al archivo JSON de la cuenta de servicio del proyecto de Firebase del entorno, fuera del repositorio
         * (ADR-0012). Mientras esté vacía (DD-072), ningún aviso sale: cada intento queda en la bitácora y el pedido
         * sigue (avisos-E02).
         */
        'credenciales' => env('FCM_CREDENCIALES'),

        // Cuánto se espera a Google, en segundos, antes de dar el intento por fallido.
        'segundos_de_espera' => 10,
    ],

    /*
     * La espera, en segundos, antes de cada reintento de un aviso que no salió porque FCM no respondió. Con cuatro
     * esperas son cinco intentos; después del último, el envío queda en failed_jobs.
     */
    'esperas' => [60, 300, 900, 3600],

    /*
     * Los momentos de aviso del pedido: la única lista (ADR-0008, "se programa el mecanismo y la lista se llena
     * después"), por el código del estado que los dispara (estados_pedido.codigo; ADR-0021).
     *
     * Son los cuatro que nombra el contrato: "Confirmación, recolección, listo para entrega y entregado"
     * (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #17). El quinto del criterio A.4.3 se agrega aquí cuando
     * se defina (DD-011, avisos-E13), sin tocar los otros cuatro.
     *
     * Los textos los redacta EL PRESTADOR con la guía de tono de voz, su entregable (NSV-2026-001-v8.pdf:14:ANEXO A,
     * Sección A.1; vault/marca/entregables/guia-de-tono-de-voz.html, Ejemplos por público), y Nadir los aprueba antes
     * de enviarlos a EL CLIENTE (DD-106, abierta para la aprobación). Mientras no los apruebe, son propuesta. :folio se
     * cambia por el folio del pedido. Ni la marca ni el dominio se escriben aquí.
     */
    'momentos' => [
        'confirmado' => [
            'titulo' => 'Pedido confirmado',
            'texto' => 'Listo, tu pedido :folio está confirmado.',
        ],
        'recolectado' => [
            'titulo' => 'Pedido recolectado',
            'texto' => 'Ya recogimos la ropa de tu pedido :folio. Te avisamos cuando esté lista.',
        ],
        'listo' => [
            'titulo' => 'Pedido listo para entrega',
            'texto' => 'La ropa de tu pedido :folio ya está lista para entregártela.',
        ],
        'entregado' => [
            'titulo' => 'Pedido entregado',
            'texto' => 'Tu ropa ya está de vuelta. Tu pedido :folio fue entregado.',
        ],
    ],

];
