<?php

namespace App\Direcciones;

use App\Models\Direccion;
use App\Models\Municipio;
use App\Models\Usuario;
use App\Plazas\ResolucionDePunto;

/**
 * Guarda la dirección de una cuenta con su punto, su municipio del catálogo y su plaza
 * (openspec/changes/direcciones/design.md, decisiones 1 a 3, 7 y 10; vault/arquitectura/modelo-de-datos.md).
 *
 * - Una dirección escrita se geocodifica una sola vez, aquí, al guardarla (direcciones-E01). Si el servicio no la
 *   encuentra, no se guarda ni se le inventa un punto (direcciones-E03).
 * - Una dirección marcada en el mapa se guarda con el punto del pin, sin consultar el servicio (direcciones-E04).
 * - El municipio es el del catálogo que eligió la persona y se guarda como llave; su estado sale de él
 *   (direcciones-E17). En la del mapa es opcional.
 * - La plaza es la de la zona activa que contiene el punto, según App\Plazas\ResolucionDePunto; vacía si el punto no
 *   cae en ninguna. Una dirección así existe, pero no se acepta para un pedido (direcciones-E11).
 * - Una favorita lleva su nombre (direcciones-E06); una que no lo es no entra a la lista de favoritas (direcciones-E08).
 */
final class AltaDeDireccion
{
    /** Lo que se guarda tal como lo escribió la persona: las señas, el nombre y si es favorita. */
    private const CAMPOS = [
        'alias',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'codigo_postal',
        'referencias',
        'es_favorita',
    ];

    public function __construct(
        private readonly Geocodificador $geocodificador,
        private readonly ResolucionDePunto $resolucion,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  Las señas validadas.
     * @param  Municipio  $municipio  El municipio del catálogo, con su estado.
     *
     * @throws DireccionNoEncontrada
     * @throws GeocodificacionNoDisponible
     */
    public function guardarEscrita(Usuario $usuario, array $datos, Municipio $municipio): Direccion
    {
        $punto = $this->geocodificador->geocodificar(self::textoParaGeocodificar($datos, $municipio), $municipio);

        if ($punto === null) {
            throw new DireccionNoEncontrada;
        }

        return $this->guardar($usuario, $datos, $punto, $municipio);
    }

    /**
     * @param  array<string, mixed>  $datos  Las señas validadas.
     * @param  Municipio|null  $municipio  El municipio del catálogo, si la persona lo eligió.
     */
    public function guardarDelMapa(Usuario $usuario, array $datos, Punto $pin, ?Municipio $municipio): Direccion
    {
        return $this->guardar($usuario, $datos, $pin, $municipio);
    }

    /**
     * El texto que se manda a geocodificar: "Calle 123, Colonia, 00000 Municipio, Estado", con el municipio y el estado
     * del catálogo. Sin el estado, "Matamoros" podría caer en Matamoros, Tamaulipas, fuera del territorio (design.md,
     * decisión 10). El número interior y las referencias no ayudan a ubicar el punto y no van.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function textoParaGeocodificar(array $datos, Municipio $municipio): string
    {
        $partes = [
            self::unir([$datos['calle'] ?? null, $datos['numero_exterior'] ?? null]),
            self::unir([$datos['colonia'] ?? null]),
            self::unir([$datos['codigo_postal'] ?? null, $municipio->nombre]),
            self::unir([$municipio->entidadFederativa?->nombre]),
        ];

        return implode(', ', array_filter($partes, fn (string $parte): bool => $parte !== ''));
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function guardar(Usuario $usuario, array $datos, Punto $punto, ?Municipio $municipio): Direccion
    {
        $enPlaza = $this->resolucion->resolver($punto->latitud, $punto->longitud);

        $direccion = Direccion::create([
            ...array_intersect_key($datos, array_flip(self::CAMPOS)),
            'id_usuarios' => $usuario->getKey(),
            'id_plazas' => $enPlaza?->plaza->getKey(),
            'id_municipios' => $municipio?->getKey(),
            'ubicacion' => ['latitud' => $punto->latitud, 'longitud' => $punto->longitud],
        ]);

        return Direccion::query()
            ->conCoordenadas()
            ->with('municipio.entidadFederativa')
            ->findOrFail($direccion->getKey());
    }

    /**
     * @param  list<mixed>  $piezas
     */
    private static function unir(array $piezas): string
    {
        return implode(' ', array_filter(
            array_map(fn (mixed $pieza): string => trim((string) $pieza), $piezas),
            fn (string $pieza): bool => $pieza !== '',
        ));
    }
}
