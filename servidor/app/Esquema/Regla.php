<?php

namespace App\Esquema;

/**
 * Las reglas comunes del modelo de datos que revisa la prueba de esquema (ADR-0016;
 * openspec/changes/modelo-de-datos/specs/modelo-de-datos/spec.md).
 */
enum Regla: string
{
    /** Tablas y columnas en minúsculas y en snake_case (modelo-E04). */
    case Minusculas = 'minusculas';

    /** La llave primaria es id_<tabla> (modelo-E01). */
    case LlavePrimaria = 'llave-primaria';

    /** Todo identificador empieza con id_ y ninguno termina en _id (modelo-E02). */
    case Identificador = 'identificador';

    /** La llave foránea lleva el nombre de la llave a la que apunta (modelo-E03). */
    case NombreDeForanea = 'nombre-de-foranea';

    /** Toda tabla de operación lleva id_plazas (modelo-E05). */
    case Plaza = 'plaza';

    /** id_plazas es no nula, salvo en la lista de plaza opcional (modelo-E05). */
    case PlazaNoNula = 'plaza-no-nula';

    /** id_plazas tiene su propia llave foránea a plazas, también en la lista de plaza opcional (plazas-E09). */
    case PlazaForanea = 'plaza-foranea';

    /** La llave foránea hacia una tabla que lleva plaza es compuesta con la plaza (modelo-E06). */
    case PlazaCompuesta = 'plaza-compuesta';

    /** El dinero (monto, monto_*, precio, precio_*, envio) va en numeric(12,2) (modelo-E07). */
    case Dinero = 'dinero';

    /** Los porcentajes (porcentaje, *_porcentaje) van en numeric(5,2) (modelo-E07). */
    case Porcentaje = 'porcentaje';

    /** Las cantidades (cantidad_*) van en numeric(8,2) (modelo-E07). */
    case Cantidad = 'cantidad';

    /** Ninguna columna es real ni double precision (modelo-E07). */
    case SinFlotantes = 'sin-flotantes';

    /** Los instantes (*_en, *_at, vigente_*, ventana_*) van en timestamptz (modelo-E08). */
    case Instante = 'instante';

    /** Las horas del día de un horario (hora_*) van en time (modelo-E08). */
    case HoraDelDia = 'hora-del-dia';

    /** Los puntos y polígonos van en geography con SRID 4326 (requisito de tipos; modelo-E07 y modelo-E08). */
    case Mapa = 'mapa';

    /** Toda tabla que no es de enlace lleva created_at y updated_at en timestamptz (modelo-E17). */
    case FechasDeAltaYCambio = 'fechas-de-alta-y-cambio';

    /** Toda llave foránea se declara con ON DELETE RESTRICT (modelo-E09). */
    case ForaneaRestrict = 'foranea-restrict';

    /** Toda llave foránea tiene un índice que empieza con sus columnas (modelo-E10). */
    case ForaneaConIndice = 'foranea-con-indice';

    /** Ninguna columna guarda el número, el código de seguridad o el vencimiento de una tarjeta (modelo-E13). */
    case DatosDeTarjeta = 'datos-de-tarjeta';

    /** Ninguna tabla ni columna lleva el nombre de un proveedor de pagos (modelo-E14). */
    case ProveedorDePagos = 'proveedor-de-pagos';

    /** Cada modelo de Eloquent declara su $table, y la tabla existe en la base (modelo-E16). */
    case ModeloTabla = 'modelo-tabla';

    /** Cada modelo de Eloquent declara su $primaryKey, igual a la llave primaria de la base (modelo-E16). */
    case ModeloLlavePrimaria = 'modelo-llave-primaria';
}
