<?php

namespace App\Lavanderias;

use App\Models\Lavanderia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Las lavanderías cercanas a un punto: las que ve el usuario (Lavanderia::visiblesParaElUsuario()), cada una con su
 * distancia en línea recta desde el punto, calculada en la base con PostGIS sobre lavanderias.ubicacion, y con el
 * promedio y el número de sus calificaciones (openspec/changes/lavanderias-cercanas/design.md, decisiones 2, 3 y 4;
 * ADR-0004; ADR-0009).
 *
 * No llama a ningún servicio externo de rutas ni de mapas (cercanas-E06), y el punto de la persona no se guarda ni se
 * registra (decisión 6): viaja solo en la consulta.
 *
 * Los filtros se combinan (cercanas-E18):
 * - la distancia máxima, en metros, contra la distancia que se responde: medida sobre el esferoide, como ST_Distance de
 *   geography, y redondeada al metro, así que una lavandería a 3 km pasa el filtro de 3,000 m (cercanas-E16);
 * - la calificación mínima, contra el promedio de las calificaciones que tienen id_lavanderias (cercanas-E17). Una
 *   lavandería sin calificaciones no tiene promedio y no pasa este filtro; si debe pasarlo es pregunta de DD-083.
 *
 * Pendiente de DD-083 (tarea 1.4): el límite de cercanía (cercanas-E08), el resto de "activa" (E09), la disponibilidad
 * (E19) y el orden por defecto (E20). Mientras tanto no hay límite y el orden es de la más cercana a la más lejana, con
 * el identificador público como desempate, solo para que la respuesta sea estable.
 */
final class ConsultaDeCercanas
{
    /**
     * @param  int|null  $distanciaMaximaMetros  Sin filtro si es null.
     * @param  float|null  $calificacionMinima  De 1 a 5; sin filtro si es null.
     * @return Collection<int, LavanderiaCercana>
     */
    public function buscar(
        float $latitud,
        float $longitud,
        ?int $distanciaMaximaMetros = null,
        ?float $calificacionMinima = null,
    ): Collection {
        $punto = Lavanderia::punto($latitud, $longitud)->getValue(DB::connection()->getQueryGrammar());

        $calificaciones = DB::table('calificaciones')
            ->select('id_lavanderias')
            ->selectRaw('avg(estrellas) as promedio')
            ->selectRaw('count(*) as numero')
            ->whereNotNull('id_lavanderias')
            ->groupBy('id_lavanderias');

        $lavanderias = Lavanderia::query()
            ->visiblesParaElUsuario()
            ->leftJoinSub($calificaciones, 'resumen', 'resumen.id_lavanderias', '=', 'lavanderias.id_lavanderias')
            ->select('lavanderias.id_publico', 'lavanderias.nombre')
            ->selectRaw('ST_Y(lavanderias.ubicacion::geometry) as latitud')
            ->selectRaw('ST_X(lavanderias.ubicacion::geometry) as longitud')
            ->selectRaw("round(ST_Distance(lavanderias.ubicacion, {$punto}))::integer as distancia_metros")
            ->selectRaw('resumen.promedio as calificacion_promedio')
            ->selectRaw('coalesce(resumen.numero, 0) as numero_de_calificaciones')
            // ST_DWithin usa el índice GiST de la ubicación. El metro de más deja pasar lo que se redondea hacia abajo;
            // la comparación exacta es la de afuera, sobre la distancia redondeada.
            ->when($distanciaMaximaMetros !== null, fn (Builder $consulta) => $consulta->whereRaw(
                "ST_DWithin(lavanderias.ubicacion, {$punto}, ?)",
                [$distanciaMaximaMetros + 1],
            ))
            ->when($calificacionMinima !== null, fn (Builder $consulta) => $consulta->where(
                'resumen.promedio',
                '>=',
                $calificacionMinima,
            ));

        return DB::query()
            ->fromSub($lavanderias->toBase(), 'cercanas')
            ->when($distanciaMaximaMetros !== null, fn ($consulta) => $consulta->where(
                'distancia_metros',
                '<=',
                $distanciaMaximaMetros,
            ))
            ->orderBy('distancia_metros')
            ->orderBy('id_publico')
            ->get()
            ->map(fn (object $fila): LavanderiaCercana => new LavanderiaCercana(
                idPublico: $fila->id_publico,
                nombre: $fila->nombre,
                latitud: (float) $fila->latitud,
                longitud: (float) $fila->longitud,
                distanciaMetros: (int) $fila->distancia_metros,
                calificacionPromedio: $fila->calificacion_promedio === null
                    ? null
                    : round((float) $fila->calificacion_promedio, 2),
                numeroDeCalificaciones: (int) $fila->numero_de_calificaciones,
            ));
    }
}
