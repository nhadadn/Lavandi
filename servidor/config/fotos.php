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
| - disco: uno de config/filesystems.php, siempre privado. En producción, fotos_s3, el almacenamiento S3 privado del
|   entorno; fuera de producción, fotos_local, un disco local privado que sirve cada foto solo con URL firmada.
| - tipos: las extensiones de imagen que se aceptan, separadas por comas.
| - peso_maximo_kb: el peso máximo de una foto, en kilobytes. El valor de omisión es provisional: lo fija Arthur
|   (alta-de-lavanderias, Open Questions 1).
| - vigencia_minutos: cuánto dura la URL firmada con que la app ve la foto.
|
*/

return [

    'disco' => env('FOTOS_DISCO', env('APP_ENV') === 'production' ? 'fotos_s3' : 'fotos_local'),

    'tipos' => array_values(array_filter(array_map('trim', explode(',', (string) env('FOTOS_TIPOS', 'jpg,jpeg,png,webp'))))),

    'peso_maximo_kb' => (int) env('FOTOS_PESO_MAXIMO_KB', 5120),

    'vigencia_minutos' => (int) env('FOTOS_VIGENCIA_MINUTOS', 60),

];
