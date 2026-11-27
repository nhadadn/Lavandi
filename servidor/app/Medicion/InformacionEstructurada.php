<?php

namespace App\Medicion;

use App\Models\Plaza;
use App\Models\Zona;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La información estructurada de la portada, en JSON-LD con el vocabulario de schema.org: de qué trata el negocio, la
 * lavandería a domicilio, y en qué zona opera, las plazas activas con sus zonas activas (NSV-2026-001-v8.pdf:15:ANEXO A,
 * Sección A.2; NSV-2026-001-v8.pdf:4:Cláusula I; medicion-E10).
 *
 * El nombre es APP_NAME y la dirección es la del sitio, APP_URL: nada de la marca ni del dominio se escribe aquí
 * (CLAUDE.md §5). El bloque solo lleva lo que está configurado o cargado: ningún teléfono, domicilio, correo ni
 * coordenada. Las plazas y sus zonas las carga plazas-y-zonas; aquí solo se leen, sin sus polígonos.
 *
 * Escenario: medicion-E10 · Spec: openspec/changes/medicion-de-la-landing/specs/medicion/spec.md · Tarea 2.1
 */
final class InformacionEstructurada
{
    /** El tipo de servicio, como lo dice la spec. */
    private const TIPO_DE_SERVICIO = 'Lavandería a domicilio';

    /**
     * Los datos del bloque.
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $nombre = (string) config('app.name');
        $sitio = route('portada');

        $datos = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $nombre,
            'serviceType' => self::TIPO_DE_SERVICIO,
            'url' => $sitio,
            'provider' => ['@type' => 'Organization', 'name' => $nombre, 'url' => $sitio],
        ];

        $zonaDeServicio = $this->zonaDeServicio();

        if ($zonaDeServicio !== []) {
            $datos['areaServed'] = $zonaDeServicio;
        }

        return $datos;
    }

    /**
     * El bloque como JSON, listo para ir dentro de su script: con los signos de HTML escapados, para que un nombre de
     * plaza no cierre el script.
     */
    public function json(): string
    {
        return json_encode(
            $this->datos(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Las plazas activas, en orden de nombre, cada una con sus zonas activas.
     *
     * @return list<array<string, mixed>>
     */
    private function zonaDeServicio(): array
    {
        return Plaza::query()
            ->where('activa', true)
            ->orderBy('nombre')
            ->orderBy('id_plazas')
            ->with(['zonas' => fn (HasMany $zonas) => $zonas
                ->select(['id_zonas', 'id_plazas', 'nombre'])
                ->where('activa', true)
                ->orderBy('nombre')
                ->orderBy('id_zonas')])
            ->get(['id_plazas', 'nombre'])
            ->map(function (Plaza $plaza): array {
                $area = ['@type' => 'Place', 'name' => $plaza->nombre];
                $zonas = $plaza->zonas->map(fn (Zona $zona): array => ['@type' => 'Place', 'name' => $zona->nombre])->all();

                return $zonas === [] ? $area : [...$area, 'containsPlace' => $zonas];
            })
            ->values()
            ->all();
    }
}
