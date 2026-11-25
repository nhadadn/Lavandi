<?php

/*
|--------------------------------------------------------------------------
| Fotos de las lavanderías y de perfil
|--------------------------------------------------------------------------
|
| Dónde se guardan y qué acepta el servidor (ADR-0006; ADR-0012; openspec/changes/alta-de-lavanderias/design.md,
| "La foto"). Valen para las fotos de las lavanderías y para la foto de perfil de cada cuenta (perfil-y-soporte, tarea
| 1.2). La app las comprime antes de subirlas, con sus propios parámetros (alta-lavanderias-E03); el servidor solo
| revisa el tipo y el peso (App\Fotos\ReglasDeLaFoto) y las guarda y firma en App\Fotos\AlmacenDeFotos.
|
| - disco: uno de config/filesystems.php, siempre privado. fotos_s3 es el almacenamiento S3 privado del entorno y
|   fotos_local un disco local privado que sirve cada foto solo con URL firmada. Por la adenda del 2026-11-24 de
|   ADR-0006 (acta-2026-11-20-modelo-y-pasarela.md, decisión 49), fotos_local solo es para la máquina local y las
|   pruebas automáticas, y el entorno de prueba y producción usan fotos_s3, con un bucket privado por entorno. El valor
|   por omisión de abajo, fotos_local fuera de producción, cambia cuando exista el bucket del entorno de prueba
|   (DD-070).
| - tipos: las extensiones de imagen que se aceptan, separadas por comas.
| - peso_maximo_kb: el peso máximo de una foto, en kilobytes. Ratificado por el concilio del 2026-11-24, por
|   delegación de Arthur (C-245; acta-2026-11-20-modelo-y-pasarela.md, decisión 47); se mide en el Android de Arthur
|   (C-243), y medir solo mueve el número.
| - vigencia_minutos: cuánto dura la URL firmada con que la app ve la foto.
| - maximo_por_lavanderia: cuántas fotos puede tener el perfil de una lavandería (alta-lavanderias-E30). Es un valor
|   técnico de configuración, que cambia sin tocar el código; la app lo recibe del servidor.
|
*/

return [

    'disco' => env('FOTOS_DISCO', env('APP_ENV') === 'production' ? 'fotos_s3' : 'fotos_local'),

    /*
     * Los logos de prensa de la portada usan este disco y los tipos y el peso que siguen (landing, tarea 4.2; adenda
     * del 2026-11-24 de ADR-0006, decisión 2): cambiar FOTOS_DISCO, FOTOS_TIPOS o FOTOS_PESO_MAXIMO_KB cambia también
     * lo que acepta el panel de prensa y de dónde lee su imagen la ruta pública de la portada.
     */
    'tipos' => array_values(array_filter(array_map('trim', explode(',', (string) env('FOTOS_TIPOS', 'jpg,jpeg,png,webp'))))),

    'peso_maximo_kb' => (int) env('FOTOS_PESO_MAXIMO_KB', 5120),

    'vigencia_minutos' => (int) env('FOTOS_VIGENCIA_MINUTOS', 60),

    'maximo_por_lavanderia' => (int) env('FOTOS_MAXIMO_POR_LAVANDERIA', 5),

];
