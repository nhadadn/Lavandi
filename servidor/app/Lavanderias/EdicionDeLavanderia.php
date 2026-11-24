<?php

namespace App\Lavanderias;

use App\Models\Lavanderia;
use App\Models\Municipio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * La edición de la información de una lavandería: nombre, dirección, foto, servicios con su precio y ventanas, con las
 * mismas validaciones del registro (App\Lavanderias\ReglasDeLaLavanderia). La usan la app del socio
 * (alta-lavanderias-E18; NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #6) y el panel de administración
 * (alta-lavanderias-E13; :19:Módulo D #1).
 *
 * No sabe quién la llama: recibe la lavandería ya autorizada (App\Autorizacion\Autorizacion). Cambia solo lo que viene;
 * los servicios y las ventanas, si vienen, son la lista completa y reemplazan a las anteriores.
 *
 * - La dirección nueva se geocodifica una vez; si es la misma que ya tiene, no se consulta (ADR-0009). Fuera de las
 *   zonas, o en una zona de otra plaza, no se guarda (DireccionNoUbicable).
 * - La foto nueva reemplaza a la principal; el archivo anterior se borra cuando la transacción queda guardada. Si algo
 *   falla, se borra el nuevo y no cambia nada.
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
     * @param  array<string, mixed>  $datos  Lo validado que cambia, sin la foto.
     *
     * @throws DireccionNoUbicable
     */
    public function editar(Lavanderia $lavanderia, array $datos, ?UploadedFile $foto = null): Lavanderia
    {
        $direccion = isset($datos['direccion']) ? $this->direccionNueva($lavanderia, $datos['direccion']) : null;

        $guardadas = [];
        $anterior = null;

        try {
            DB::transaction(function () use ($lavanderia, $datos, $foto, $direccion, &$guardadas, &$anterior): void {
                Lavanderia::query()->whereKey($lavanderia->getKey())->lockForUpdate()->first();

                $lavanderia->fill(array_filter([
                    'nombre' => $datos['nombre'] ?? null,
                    ...($direccion ?? []),
                ], fn (mixed $valor): bool => $valor !== null))->save();

                if (isset($datos['servicios'])) {
                    $this->serviciosYVentanas->guardarServicios($lavanderia, $datos['servicios']);
                }

                if (isset($datos['ventanas'])) {
                    $this->serviciosYVentanas->guardarVentanas($lavanderia, $datos['ventanas']);
                }

                if ($foto !== null) {
                    $anterior = $this->fotos->reemplazarPrincipal($lavanderia, $foto, $guardadas);
                }
            });
        } catch (Throwable $error) {
            $this->fotos->borrar($guardadas);

            throw $error;
        }

        $this->fotos->borrar(array_filter([$anterior]));

        return $lavanderia;
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
