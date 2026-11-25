<?php

/*
|--------------------------------------------------------------------------
| Excepciones a las reglas del modelo de datos
|--------------------------------------------------------------------------
|
| La lista cerrada de la decisión 2 de openspec/changes/modelo-de-datos/design.md (ADR-0016). La prueba de
| esquema (App\Esquema\RevisorDeEsquema) la lee de aquí y de ningún otro lugar.
|
| Agregar una tabla a cualquiera de estas listas es una decisión visible en el diff: se revisa contra
| vault/arquitectura/modelo-de-datos.md antes de fusionar. Una tabla nueva que no esté en ninguna lista cumple
| todas las reglas, incluida la plaza no nula con su llave foránea a plazas (plazas-E09).
|
*/

return [

    /*
     * Del framework y de las extensiones: conservan su nombre y quedan fuera de toda regla.
     */
    'del_framework' => [
        // Laravel y Sanctum.
        'migrations',
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
        'sessions',
        'personal_access_tokens',
        'password_reset_tokens',
        // PostGIS. Sus esquemas topology y tiger no se revisan: la prueba solo lee public.
        'spatial_ref_sys',
        // La exportación y la importación de Filament, y sus avisos. Sus llaves hacia el usuario se adaptan a
        // usuarios.id_usuarios, porque la tabla users de Laravel no se crea (grado E, sin verificar en Filament 5).
        'exports',
        'imports',
        'failed_import_rows',
        'notifications',
    ],

    /*
     * Sin plaza, por diseño: los catálogos, las plazas, las cuentas y lo de la pasarela. Los del territorio
     * (entidades_federativas, municipios y colonias) no son de una plaza, y una plaza nueva no los cambia (direcciones,
     * design.md, "La dirección asistida"). Los testimonios y los logos de prensa de la landing tampoco: la portada es una
     * para todo el sitio (landing, design.md, "Tablas y migración").
     */
    'sin_plaza' => [
        'roles',
        'tipos_servicio',
        'unidades_medida',
        'estados_pedido',
        'entidades_federativas',
        'municipios',
        'colonias',
        'plazas',
        'usuarios',
        'rol_usuario',
        'cuentas_externas',
        'dispositivos',
        'actividad_usuarios',
        'eventos_pasarela',
        'metodos_pago_guardados',
        // Los pagos que acreditó la pasarela, para conciliar: la cuenta de la pasarela es una para todas las plazas, y
        // la plaza de un movimiento es la del pedido con el que cuadra; uno sin pedido es justo una diferencia (segunda
        // adenda del 2026-11-22 de ADR-0010, decisión 7; administracion-E20 y E21).
        'movimientos_pasarela',
        'testimonios',
        // Los logos de la sección 10 de la portada, como los testimonios: una portada para todo el sitio (landing,
        // tarea 4.0b).
        'logos_prensa',
    ],

    /*
     * Plaza opcional: id_plazas puede quedar vacía, por la razón escrita en el modelo.
     */
    'plaza_opcional' => [
        'avisos_masivos',   // Vacía: el aviso es para todas las plazas.
        'comisiones',       // Vacía, con la lavandería, en la comisión global: una para todas las plazas (administracion-E11).
        'reportes_soporte', // Vacía si la cuenta no tiene de dónde tomarla: el reporte se guarda igual (perfil-usuario-E17).
        // Vacía si estado es pendiente, o si es baja con aprobado_en vacía: el registro que EL CLIENTE no autorizó
        // (repartidor-E31). Con su CHECK.
        'repartidores',
        'direcciones',      // Vacía si el punto no cae en ninguna zona.
    ],

    /*
     * Tablas de enlace, sin llave propia: no llevan id_<tabla>, created_at ni updated_at, ni modelo de Eloquent
     * (decisión 7).
     */
    'de_enlace' => [
        'rol_usuario',
        'actividad_usuarios',
    ],

];
