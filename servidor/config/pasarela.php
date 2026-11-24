<?php

/*
|--------------------------------------------------------------------------
| La pasarela de pago
|--------------------------------------------------------------------------
|
| openspec/changes/pago/ (pago-E17); ADR-0010 y ADR-0012. Cuál pasarela cobra y con qué llave sale del .env de cada
| entorno, nunca del código: en local y en prueba, la de su sandbox; en producción, la de la cuenta de EL CLIENTE
| (NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.11).
|
*/

return [

    /*
     * El código de la pasarela que cobra en este entorno, el mismo que se guarda en pagos.pasarela (App\Cobro\Pasarelas):
     * - conekta, la del contrato (ADR-0010), en todos los entornos; si cobra en su sandbox o en producción lo dice su
     *   llave;
     * - simulada, la de local y las pruebas, que no llama a ningún proveedor y que el código no usa en otro entorno.
     * Vacío o con una que no existe, el pago no está disponible: la API responde 503.
     */
    'pasarela' => env('PASARELA'),

    /*
     * Conekta (App\Cobro\PasarelaConekta). Su llave privada autentica cada petición a su API: en local y en prueba, la
     * del sandbox; en producción, la de la cuenta de EL CLIENTE (ADR-0012; NSV-2026-001-v8.pdf:34:ANEXO B, Sección
     * B.11). Sin ella, Conekta no crea órdenes. La llave pública no se usa: la tarjeta se escribe en el checkout de
     * Conekta, no en la app (adenda del 2026-11-22 de ADR-0010). Con qué se comprueba la autenticidad de sus avisos llega
     * con la tarea 3.1 de openspec/changes/pago/tasks.md.
     */
    'conekta' => [
        'llave' => env('PASARELA_CONEKTA_LLAVE'),
    ],

    /*
     * La pasarela simulada (App\Cobro\PasarelaSimulada). Su llave autentica cada petición que se le hace y firma los
     * avisos simulados; es de prueba, pero vive en el .env como cualquier llave. Sin ella, la simulada no crea órdenes.
     */
    'simulada' => [
        'llave' => env('PASARELA_SIMULADA_LLAVE'),
    ],

    /*
     * La dirección de regreso a la app desde el checkout: el esquema de la app, el mismo identificador de la app que
     * usa movil/app.config.ts, seguido de la ruta de regreso, como "<identificador>://pago". Sale del entorno, nunca
     * del código, porque lleva el identificador de la app (CLAUDE.md §5). La app la usa para abrir el checkout dentro
     * de ella y saber cuándo volvió (adenda del 2026-11-22 de ADR-0010); volver no confirma ningún pago (pago-E07).
     * Vacía, la página de checkout de desarrollo le dice a la persona que regrese a la app.
     */
    'regreso' => env('PASARELA_REGRESO'),

];
