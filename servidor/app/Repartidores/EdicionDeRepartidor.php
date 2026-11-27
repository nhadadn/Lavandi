<?php

namespace App\Repartidores;

use App\Http\Requests\RegistroDeRepartidorRequest;
use App\Identidad\FotoDePerfil;
use App\Models\Repartidor;
use App\Validacion\CorreoSinCuenta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * La edición del repartidor por la administración (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #2,
 * "edición"; repartidor-E08): su nombre, su teléfono, su correo, su foto y su tipo de vehículo, los cinco datos de su
 * registro. Los cuatro primeros y la foto viven en su cuenta; el tipo de vehículo, en repartidores. La usan el panel
 * (App\Filament\Resources\Repartidores\Pages\EditRepartidor) y la API de la administración
 * (PATCH /api/administracion/repartidores/{repartidor}; administracion-app-E23), con la misma validación (ADR-0022,
 * decisión 9).
 *
 * Valida solo los datos que llegan, con las reglas del registro (App\Http\Requests\RegistroDeRepartidorRequest): los
 * textos sin los espacios de las orillas y el vacío como nulo, y el correo sin chocar con el de otra cuenta, salvo el
 * suyo propio (App\Validacion\CorreoSinCuenta). Si algo no pasa, no cambia nada. Cambia solo lo que viene. La contraseña
 * no se toca. La foto nueva reemplaza a la anterior, cuyo archivo se borra, y va después de guardar lo demás: si lo demás
 * falla, la foto anterior sigue (App\Identidad\FotoDePerfil). El repartidor ve el cambio en su perfil
 * (GET /api/repartidor/perfil).
 */
final class EdicionDeRepartidor
{
    /** Los datos del registro que se editan, además de la foto. */
    public const CAMPOS = ['nombre', 'telefono', 'correo', 'tipo_vehiculo'];

    /** Los datos que viven en la cuenta del repartidor. */
    private const DE_LA_CUENTA = ['nombre', 'telefono', 'correo'];

    public function __construct(private readonly FotoDePerfil $fotos) {}

    /**
     * @param  array<string, mixed>  $datos  Los que llegan, de nombre, telefono, correo, tipo_vehiculo y foto, la nueva;
     *                                       cualquier otro se ignora.
     *
     * @throws ValidationException Con el error de cada campo bajo su nombre, sin cambiar nada.
     */
    public function editar(Repartidor $repartidor, array $datos): void
    {
        $validados = $this->validar($repartidor, $datos);
        $foto = $validados['foto'] ?? null;

        DB::transaction(function () use ($repartidor, $validados): void {
            $cuenta = $repartidor->cuenta()->sole();
            $cuenta->forceFill(array_intersect_key($validados, array_flip(self::DE_LA_CUENTA)))->save();

            if (array_key_exists('tipo_vehiculo', $validados)) {
                $repartidor->forceFill(['tipo_vehiculo' => $validados['tipo_vehiculo']])->save();
            }
        });

        if ($foto instanceof UploadedFile) {
            $this->fotos->poner($repartidor->cuenta()->sole(), $foto);
        }

        $repartidor->unsetRelation('cuenta');
    }

    /**
     * Los datos que llegan, ya validados: los textos recortados y el vacío como nulo; las reglas del registro, solo de
     * los campos que llegan; y el correo sin cuenta, salvo la propia.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validar(Repartidor $repartidor, array $datos): array
    {
        $aValidar = array_map(
            fn (mixed $valor): mixed => is_string($valor) && trim($valor) === '' ? null : (is_string($valor) ? trim($valor) : $valor),
            Arr::only($datos, [...self::CAMPOS, 'foto']),
        );

        $reglas = Arr::only(RegistroDeRepartidorRequest::reglasDeLosDatos(), array_keys($aValidar));
        $reglas['correo'][] = new CorreoSinCuenta(salvo: $repartidor->cuenta);

        return Validator::make($aValidar, $reglas, RegistroDeRepartidorRequest::mensajesDeLosDatos())->validate();
    }
}
