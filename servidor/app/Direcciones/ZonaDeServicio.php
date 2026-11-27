<?php

namespace App\Direcciones;

use App\Models\Direccion;
use App\Models\Lavanderia;
use App\Models\Zona;
use App\Plazas\ResolucionDePunto;

/**
 * Decide si una dirección sirve como recogida o como entrega de un pedido con una lavandería: su punto debe caer dentro
 * de una zona de servicio activa de la plaza de la lavandería (NSV-2026-001-v8.pdf:4:Cláusula I;
 * openspec/changes/direcciones/design.md, decisión 5; ADR-0004).
 *
 * La zona se comprueba en la base, con el punto contra los polígonos de las zonas, al momento de elegir: una zona que
 * se desactivó después de guardar la dirección ya no da servicio (plazas-E13). Si la rechaza, dice por qué:
 * - el punto no cae en ninguna zona activa de ninguna plaza activa (direcciones-E11);
 * - cae en una zona de otra plaza (direcciones-E12).
 *
 * El radio de servicio de la lavandería dentro de su plaza no se revisa: espera DD-083 (direcciones-E13).
 */
final class ZonaDeServicio
{
    public const FUERA_DE_LA_ZONA = 'fuera_de_la_zona_de_servicio';

    public const OTRA_PLAZA = 'fuera_de_la_plaza_de_la_lavanderia';

    public function __construct(private readonly ResolucionDePunto $resolucion) {}

    /**
     * Null si la dirección sirve para un pedido con la lavandería; si no, el motivo: FUERA_DE_LA_ZONA u OTRA_PLAZA.
     *
     * La lavandería es una que el usuario ve, así que su plaza está activa (Lavanderia::visiblesParaElUsuario()).
     */
    public function motivoDeRechazo(Direccion $direccion, Lavanderia $lavanderia): ?string
    {
        // ST_Covers sobre geography usa el índice GiST del polígono y cuenta el borde como dentro, igual que
        // App\Plazas\ResolucionDePunto.
        $dentro = Zona::query()
            ->where('id_plazas', $lavanderia->id_plazas)
            ->where('activa', true)
            ->whereRaw(
                'ST_Covers(poligono, (select ubicacion from direcciones where id_direcciones = ?))',
                [$direccion->getKey()],
            )
            ->exists();

        if ($dentro) {
            return null;
        }

        $enOtraPlaza = $this->resolucion->resolver((float) $direccion->latitud, (float) $direccion->longitud);

        return $enOtraPlaza === null ? self::FUERA_DE_LA_ZONA : self::OTRA_PLAZA;
    }
}
