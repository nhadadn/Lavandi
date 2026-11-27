<?php

namespace App\Plazas;

use App\Models\Plaza;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;

/**
 * Abre una plaza cargando datos: su ficha y sus zonas, desde un archivo GeoJSON, sin migraciones de esquema ni código
 * nuevo (plazas-E08; NSV-2026-001-v8.pdf:4:Cláusula I y NSV-2026-001-v8.pdf:37:ANEXO C, Sección C.3). Lo usan el
 * comando plazas:cargar y el sembrado de las plazas de prueba.
 *
 * El archivo es un FeatureCollection de GeoJSON (RFC 7946). La ficha va en el miembro "plaza" y cada zona es un
 * Feature con su nombre, su estado y un Polygon o un MultiPolygon en longitud y latitud (SRID 4326):
 *
 *     {
 *       "type": "FeatureCollection",
 *       "plaza": {"nombre": "Plaza de prueba 1", "zona_horaria": "America/Mexico_City", "activa": true},
 *       "features": [
 *         {
 *           "type": "Feature",
 *           "properties": {"nombre": "Zona de prueba 1 Centro", "activa": true},
 *           "geometry": {"type": "Polygon", "coordinates": [[[-120, 10], [-119, 10], [-119, 11], [-120, 10]]]}
 *         }
 *       ]
 *     }
 *
 * Cada polígono debe ser válido para PostGIS (ST_IsValid): un anillo que no cierra o que se cruza se rechaza, porque
 * la columna lo guardaría sin quejarse y la resolución de un punto dejaría de ser confiable. Y dos zonas no se
 * enciman: ni dos del archivo, ni una del archivo con una guardada, de esta plaza o de otra; tocarse en el borde sí
 * se vale (plazas-E12; DD-134, punto 1).
 *
 * Volver a cargar el mismo archivo actualiza; no duplica: la plaza se reconoce por su nombre y la zona por su plaza y
 * su nombre, los únicos de la base (design.md, decisión 3). Una zona de la plaza que ya no viene en el archivo queda
 * guardada, pero no activa: no se borra, porque las filas de operación que vengan pueden apuntar a ella. Si una carga
 * posterior la vuelve a traer, es la misma fila, con el estado que diga el archivo (plazas-E14; DD-134, punto 3).
 * La carga es todo o nada: si algo falla, no queda ni la plaza ni ninguna de sus zonas.
 */
final class AperturaDePlaza
{
    /**
     * @throws ArchivoDePlazaNoValido si el archivo no existe o no es JSON.
     * @throws ValidationException si la ficha, una zona o un polígono no son válidos, o si dos zonas se enciman.
     * @throws QueryException si la base rechaza un dato.
     */
    public function cargarArchivo(string $ruta): Plaza
    {
        if (! is_file($ruta)) {
            throw new ArchivoDePlazaNoValido("No existe el archivo {$ruta}.");
        }

        try {
            $datos = json_decode((string) file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new ArchivoDePlazaNoValido("El archivo {$ruta} no es JSON: {$error->getMessage()}.", previous: $error);
        }

        if (! is_array($datos)) {
            throw new ArchivoDePlazaNoValido("El archivo {$ruta} no es un objeto GeoJSON.");
        }

        return $this->cargar($datos);
    }

    /**
     * @param  array<mixed>  $datos  El GeoJSON ya leído.
     */
    public function cargar(array $datos): Plaza
    {
        $this->validar($datos);

        return DB::transaction(function () use ($datos): Plaza {
            // La zona horaria la valida el modelo al guardar (plazas-E02).
            $plaza = Plaza::updateOrCreate(
                ['nombre' => $datos['plaza']['nombre']],
                [
                    'zona_horaria' => $datos['plaza']['zona_horaria'] ?? null,
                    'activa' => (bool) $datos['plaza']['activa'],
                ],
            );

            $delArchivo = [];

            foreach ($datos['features'] as $zona) {
                $delArchivo[] = $plaza->zonas()->updateOrCreate(
                    ['nombre' => $zona['properties']['nombre']],
                    [
                        'poligono' => $zona['geometry'],
                        'activa' => (bool) $zona['properties']['activa'],
                    ],
                )->getKey();
            }

            // La que ya no viene deja de dar servicio, sin borrarse (plazas-E14).
            $plaza->zonas()
                ->where('activa', true)
                ->whereNotIn('nombre', array_column(array_column($datos['features'], 'properties'), 'nombre'))
                ->update(['activa' => false]);

            // Ya guardadas, para comparar cada zona del archivo con lo que de verdad quedó en la base. Si alguna se
            // encima, la excepción deshace la transacción entera.
            $this->rechazarZonasEncimadas($delArchivo);

            return $plaza;
        });
    }

    /**
     * Dos zonas no se enciman (plazas-E12; DD-134, punto 1): cada zona del archivo se compara con las del mismo archivo
     * y con todas las guardadas, de cualquier plaza y activas o no, porque una zona que no está activa puede volver a
     * activarse. La zona que se recarga ya es la misma fila, así que no se compara con lo que era.
     *
     * "Comparten área" quiere decir que sus interiores se cruzan: la matriz DE-9IM de ST_Relate con el patrón
     * 2********, el cruce de los interiores con dimensión 2. Tocarse en el borde o en una esquina no cuenta. Se compara
     * en el plano de longitud y latitud, como ST_IsValid; dos zonas con el mismo borde, vértice por vértice, se leen
     * igual en el plano y en la esfera.
     *
     * @param  list<int>  $delArchivo  Las llaves de las zonas del archivo, ya guardadas.
     *
     * @throws ValidationException con un mensaje por cada par de zonas encimadas, que nombra a las dos.
     */
    private function rechazarZonasEncimadas(array $delArchivo): void
    {
        if ($delArchivo === []) {
            return;
        }

        $encimadas = DB::table('zonas as zona')
            ->join('zonas as otra', 'otra.id_zonas', '<>', 'zona.id_zonas')
            ->join('plazas', 'plazas.id_plazas', '=', 'otra.id_plazas')
            ->whereIn('zona.id_zonas', $delArchivo)
            // Un par del mismo archivo se revisa una sola vez.
            ->where(fn ($otra) => $otra
                ->whereNotIn('otra.id_zonas', $delArchivo)
                ->orWhereColumn('otra.id_zonas', '>', 'zona.id_zonas'))
            // Primero las cajas, que es barato; luego los interiores.
            ->whereRaw('zona.poligono::geometry && otra.poligono::geometry')
            ->whereRaw("ST_Relate(zona.poligono::geometry, otra.poligono::geometry, '2********')")
            ->orderBy('zona.id_zonas')
            ->orderBy('otra.id_zonas')
            ->get(['zona.nombre as zona', 'otra.id_zonas as id_otra', 'otra.nombre as otra', 'plazas.nombre as plaza']);

        if ($encimadas->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'features' => $encimadas->map(fn (object $par): string => in_array((int) $par->id_otra, $delArchivo, true)
                ? "Las zonas {$par->zona} y {$par->otra} del archivo comparten área."
                : "La zona {$par->zona} comparte área con la zona {$par->otra} de la plaza {$par->plaza}, ya guardada.",
            )->all(),
        ]);
    }

    /**
     * La forma del archivo y, si la forma está bien, que cada polígono sea válido para PostGIS.
     *
     * @param  array<mixed>  $datos
     */
    private function validar(array $datos): void
    {
        $estado = 'necesita su estado: activa, true o false.';

        $validador = Validator::make($datos, [
            'type' => ['required', 'in:FeatureCollection'],
            'plaza' => ['required', 'array'],
            'plaza.nombre' => ['required', 'string'],
            'plaza.activa' => ['required', 'boolean'],
            'features' => ['present', 'array'],
            'features.*.type' => ['required', 'in:Feature'],
            'features.*.properties.nombre' => ['required', 'string', 'distinct'],
            'features.*.properties.activa' => ['required', 'boolean'],
            'features.*.geometry.type' => ['required', 'in:Polygon,MultiPolygon'],
            'features.*.geometry.coordinates' => ['required', 'array'],
        ], [
            'type.*' => 'El archivo debe ser un FeatureCollection de GeoJSON.',
            'plaza.required' => 'Falta la ficha de la plaza, el miembro "plaza".',
            'plaza.array' => 'La ficha de la plaza debe ser un objeto.',
            'plaza.nombre.*' => 'La plaza necesita su nombre.',
            'plaza.activa.*' => "La plaza {$estado}",
            'features.*' => 'Las zonas van en "features", como una lista.',
            'features.*.type.*' => 'Cada zona debe ser un Feature de GeoJSON.',
            'features.*.properties.nombre.distinct' => 'Dos zonas de la misma plaza no pueden llamarse igual.',
            'features.*.properties.nombre.*' => 'Cada zona necesita su nombre.',
            'features.*.properties.activa.*' => "Cada zona {$estado}",
            'features.*.geometry.type.*' => 'Cada zona debe ser un Polygon o un MultiPolygon.',
            'features.*.geometry.coordinates.*' => 'Cada zona necesita las coordenadas de su polígono.',
        ]);

        $validador->after(function ($validador) use ($datos): void {
            if ($validador->errors()->isNotEmpty()) {
                return;
            }

            foreach ($datos['features'] as $posicion => $zona) {
                $razon = $this->razonDePoligonoNoValido($zona['geometry']);

                if ($razon !== null) {
                    $validador->errors()->add(
                        "features.{$posicion}.geometry",
                        "El polígono de la zona {$zona['properties']['nombre']} no es válido: {$razon}.",
                    );
                }
            }
        });

        $validador->validate();
    }

    /**
     * Por qué PostGIS no acepta el polígono como válido, o null si lo es. ST_GeomFromGeoJSON acepta, y la columna
     * guarda, un anillo que no cierra o que se cruza; ST_IsValid los detecta.
     *
     * @param  array<mixed>  $geometria
     */
    private function razonDePoligonoNoValido(array $geometria): ?string
    {
        try {
            // Una transacción anidada, un savepoint: si PostGIS no puede leer el GeoJSON, la transacción de afuera
            // sigue viva.
            $revision = DB::transaction(fn () => DB::selectOne(
                'select ST_IsValid(g) as valido, ST_IsValidReason(g) as razon
                   from (select ST_GeomFromGeoJSON(?) as g) as geometria',
                [json_encode($geometria, JSON_THROW_ON_ERROR)],
            ));
        } catch (QueryException $error) {
            return $error->getPrevious()?->getMessage() ?? $error->getMessage();
        }

        return $revision->valido ? null : $revision->razon;
    }
}
