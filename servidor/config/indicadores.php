<?php

/*
|--------------------------------------------------------------------------
| El panel de indicadores
|--------------------------------------------------------------------------
|
| Lo que depende de un valor que todavía no existe va aquí, como dato de configuración, y no en el código
| (openspec/changes/indicadores/design.md, Goals). Cambiar un valor es cambiar el .env, sin tocar el código.
|
*/

return [

    /*
     * Cada cuánto se refrescan solos los indicadores con la página abierta, en el formato del sondeo de Livewire: "30s"
     * o "1500ms" (NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4; indicadores-E21).
     *
     * Cada 30 s, la consulta periódica de ADR-0007: lo fija su adenda del 2026-11-22, que cierra DD-116
     * (indicadores-E22). Cada entorno lo cambia con INDICADORES_REFRESCO sin tocar el código; vacío, vale el de la
     * adenda. Un valor que no sirve, como "no", deja los widgets sin sondear: las cifras se actualizan al abrir o recargar
     * la página.
     */
    'refresco' => env('INDICADORES_REFRESCO') ?: '30s',

    /*
     * Los dos cortes del semáforo del ranking de lavanderías, en estrellas de 1 a 5 (NSV-2026-001-v8.pdf:22:ANEXO A,
     * Sección A.5, "Ranking con semáforo"; indicadores-E13). Verde desde el corte alto; amarillo desde el bajo y por
     * debajo del alto; rojo por debajo del bajo.
     *
     * Sus valores los da EL CLIENTE (indicadores-E14; DD-114). Mientras no lleguen van vacíos, y el ranking se muestra
     * sin semáforo. Unos cortes que no son números entre 1 y 5, o con el bajo arriba del alto, se toman como vacíos.
     */
    'semaforo' => [
        'corte_alto' => env('INDICADORES_SEMAFORO_CORTE_ALTO'),
        'corte_bajo' => env('INDICADORES_SEMAFORO_CORTE_BAJO'),
    ],

    /*
     * El mapa de calor por zona (indicadores-E20): cuánto se simplifica el polígono de cada zona antes de mandarlo al
     * panel, en grados, como la cobertura de la portada (config/landing.php). 0.0005 grados son unos 55 metros: no
     * cambia la forma a la escala del mapa y quita los vértices de más.
     */
    'mapa' => [
        'simplificacion' => (float) env('INDICADORES_MAPA_SIMPLIFICACION', 0.0005),
    ],

];
