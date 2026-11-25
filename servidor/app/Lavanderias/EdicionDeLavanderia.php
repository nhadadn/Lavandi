<?php

namespace App\Lavanderias;

use App\Models\Lavanderia;
use App\Models\Municipio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * La edición de la información de una lavandería: nombre, dirección, foto, servicios con su precio y ventanas, con las
 * mismas validaciones del registro (App\Lavanderias\ReglasDeLaLavanderia), y la descripción y las fotos de su perfil
 * (alta-lavanderias-E26 a E31). La usan la app del socio (alta-lavanderias-E18; NSV-2026-001-v8.pdf:17:ANEXO A, Sección
 * A.3, Módulo B #6) y el panel de administración (alta-lavanderias-E13 y E31; :19:Módulo D #1).
 *
 * No sabe quién la llama: recibe la lavandería ya autorizada (App\Autorizacion\Autorizacion). Cambia solo lo que viene;
 * los servicios, las ventanas y las fotos, si vienen, son la lista completa y reemplazan a las anteriores.
 *
 * - La dirección nueva se geocodifica una vez; si es la misma que ya tiene, no se consulta (ADR-0009). Fuera de las
 *   zonas, o en una zona de otra plaza, no se guarda (DireccionNoUbicable).
 * - La descripción vacía deja el perfil sin descripción.
 * - La foto nueva reemplaza a la principal. La lista de fotos reemplaza a todas: las que no vienen se quitan, y el orden
 *   es el de la lista (FotosDeLaLavanderia::reemplazarTodas()). La del panel se rechaza si las fotos cambiaron desde
 *   que el panel las leyó. Los archivos que se reemplazan o se quitan se borran cuando la transacción queda guardada.
 *   Si algo falla, se borran los nuevos y no cambia nada.
 */
final class EdicionDeLavanderia
{
    /** Las columnas de la dirección que se comparan para saber si cambió. */
    private const SENAS = ['calle', 'numero', 'colonia', 'codigo_postal'];

    public function __construct(
        private readonly UbicacionDeLaLavanderia $ubicacion,
        private readonly ServiciosYVentanas $serviciosYVentanas,
        private readonly FotosDeLaLavanderia $fotos,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  Lo validado que cambia, sin la foto principal sola. Las fotos nuevas de la
     *                                       lista vienen en ella, como archivos.
     * @param  list<string>|null  $fotosLeidas  Las rutas de las fotos, en su orden, sobre las que se contaron los lugares
     *                                          de la lista: si al guardar ya no son esas, la lista se rechaza. La pasa el
     *                                          panel, que lee las rutas (alta-lavanderias-E31); la app no las conoce.
     *
     * @throws DireccionNoUbicable
     */
    public function editar(Lavanderia $lavanderia, array $datos, ?UploadedFile $foto = null, ?array $fotosLeidas = null): Lavanderia
    {
        $direccion = isset($datos['direccion']) ? $this->direccionNueva($lavanderia, $datos['direccion']) : null;

        $guardadas = [];
        $aBorrar = [];

        try {
            DB::transaction(function () use ($lavanderia, $datos, $foto, $fotosLeidas, $direccion, &$guardadas, &$aBorrar): void {
                Lavanderia::query()->whereKey($lavanderia->getKey())->lockForUpdate()->first();

                $lavanderia->fill(array_filter([
                    'nombre' => $datos['nombre'] ?? null,
                    ...($direccion ?? []),
                ], fn (mixed $valor): bool => $valor !== null));

                if (array_key_exists('descripcion', $datos)) {
                    $lavanderia->descripcion = self::descripcion($datos['descripcion']);
                }

                $lavanderia->save();

                if (isset($datos['servicios'])) {
                    $this->serviciosYVentanas->guardarServicios($lavanderia, $datos['servicios']);
                }

                if (isset($datos['ventanas'])) {
                    $this->serviciosYVentanas->guardarVentanas($lavanderia, $datos['ventanas']);
                }

                if ($foto !== null) {
                    $aBorrar = array_filter([$this->fotos->reemplazarPrincipal($lavanderia, $foto, $guardadas)]);
                }

                if (isset($datos['fotos'])) {
                    $aBorrar = $this->fotos->reemplazarTodas($lavanderia, $datos['fotos'], $guardadas, $fotosLeidas);
                }
            });
        } catch (Throwable $error) {
            $this->fotos->borrar($guardadas);

            throw $error;
        }

        $this->fotos->borrar(array_values($aBorrar));

        return $lavanderia;
    }

    /**
     * La descripción como se guarda: sin los espacios de las orillas, y vacía, sin descripción (alta-lavanderias-E26).
     */
    private static function descripcion(mixed $descripcion): ?string
    {
        $texto = trim((string) $descripcion);

        return $texto === '' ? null : $texto;
    }

    /**
     * Las columnas de la dirección nueva, ya ubicada; las mismas señas que ya tiene, sin consultar la geocodificación.
     *
     * @param  array{calle: string, numero: string, colonia: string, codigo_postal: string, municipio: string}  $direccion
     * @return array<string, mixed>
     *
     * @throws DireccionNoUbicable
     */
    private function direccionNueva(Lavanderia $lavanderia, array $direccion): array
    {
        $municipio = Municipio::query()->with('entidadFederativa')->where('codigo', $direccion['municipio'])->sole();

        $igual = $municipio->getKey() === $lavanderia->id_municipios
            && array_intersect_key($direccion, array_flip(self::SENAS)) == $lavanderia->only(self::SENAS);

        if ($igual) {
            return [];
        }

        $ubicada = $this->ubicacion->ubicar($direccion, $municipio);

        if ($ubicada->plaza->getKey() !== $lavanderia->id_plazas) {
            throw new DireccionNoUbicable(DireccionNoUbicable::EN_OTRA_PLAZA);
        }

        return $ubicada->columnas($direccion);
    }
}
