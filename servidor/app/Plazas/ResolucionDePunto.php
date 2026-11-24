<?php

namespace App\Plazas;

use App\Models\Zona;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resuelve un punto: en qué zona activa y en qué plaza activa cae una latitud y una longitud, o que no cae en ninguna
 * (openspec/changes/plazas-y-zonas/specs/plazas/spec.md, requisito "Resolver un punto"; ADR-0004).
 *
 * Una plaza que no está activa no da servicio (plazas-E06), ni una zona que no está activa, aunque su plaza sí lo esté
 * (plazas-E13). La respuesta depende solo del punto: no recibe la cuenta de quien pregunta, porque el usuario no
 * pertenece a una plaza (plazas-E07; modelo-multi-plaza.md).
 *
 * La consulta usa las funciones de PostGIS sobre zonas.poligono, sin paquete de tipos espaciales. ST_Covers sobre
 * geography usa el índice GiST del polígono y cuenta el borde como dentro de la zona.
 *
 * Dos zonas no se enciman: la carga lo rechaza (plazas-E12). Solo un punto justo en el borde que comparten dos zonas
 * cae en las dos; gana la de menor id_zonas, para que la respuesta sea estable.
 */
final class ResolucionDePunto
{
    public function resolver(float $latitud, float $longitud): ?PuntoEnPlaza
    {
        $zona = Zona::query()
            ->where('activa', true)
            ->whereHas('plaza', fn (Builder $plaza) => $plaza->where('activa', true))
            ->whereRaw(
                'ST_Covers(poligono, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)',
                [$longitud, $latitud],
            )
            ->orderBy('id_zonas')
            ->with('plaza')
            ->first();

        return $zona === null ? null : new PuntoEnPlaza($zona, $zona->plaza);
    }
}
