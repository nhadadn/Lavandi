<?php

/*
|--------------------------------------------------------------------------
| El territorio del contrato
|--------------------------------------------------------------------------
|
| Los estados y los municipios que carga el comando direcciones:cargar-catalogo desde el archivo de Correos de México
| (openspec/changes/direcciones/design.md, "La carga del catálogo"; C-238).
|
| NSV-2026-001-v8.pdf:4:Cláusula I: la totalidad del Estado de Durango y los municipios de Torreón y Matamoros,
| Coahuila. Cambiarlo es Orden de Cambio (NSV-2026-001-v8.pdf:8:Cláusula IV).
|
| Las claves son las del archivo: c_estado y c_mnpio. Se verificaron contra los archivos de Durango y de Coahuila de
| Zaragoza descargados el 2026-11-21 (grado E). El comando compara cada nombre con el del archivo, sin acentos ni
| mayúsculas, y no carga nada si no coinciden: una clave equivocada no carga otro municipio en silencio.
|
| Las claves van entre comillas: '05' y '035' llevan el cero a la izquierda.
|
*/

return [

    'estados' => [
        '10' => ['nombre' => 'Durango', 'municipios' => '*'],
        '05' => ['nombre' => 'Coahuila de Zaragoza', 'municipios' => ['035' => 'Torreón', '017' => 'Matamoros']],
    ],

];
