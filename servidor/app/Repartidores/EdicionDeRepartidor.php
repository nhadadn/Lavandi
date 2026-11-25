<?php

namespace App\Repartidores;

use App\Identidad\FotoDePerfil;
use App\Models\Repartidor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * La edición del repartidor desde el panel (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #2, "edición";
 * repartidor-E08): su nombre, su teléfono, su correo, su foto y su tipo de vehículo, los cinco datos de su registro. Los
 * cuatro primeros y la foto viven en su cuenta; el tipo de vehículo, en repartidores.
 *
 * Recibe los datos ya validados con las reglas del registro (App\Http\Requests\RegistroDeRepartidorRequest) y cambia
 * solo los que vienen. La contraseña no se toca. La foto nueva reemplaza a la anterior, cuyo archivo se borra, y va
 * después de guardar lo demás: si lo demás falla, la foto anterior sigue (App\Identidad\FotoDePerfil). El repartidor ve
 * el cambio en su perfil (GET /api/repartidor/perfil).
 */
final class EdicionDeRepartidor
{
    /** Los datos que viven en la cuenta del repartidor. */
    private const DE_LA_CUENTA = ['nombre', 'telefono', 'correo'];

    public function __construct(private readonly FotoDePerfil $fotos) {}

    /**
     * @param  array<string, string>  $datos  Los que cambian, de nombre, telefono, correo y tipo_vehiculo.
     */
    public function editar(Repartidor $repartidor, array $datos, ?UploadedFile $foto = null): void
    {
        DB::transaction(function () use ($repartidor, $datos): void {
            $cuenta = $repartidor->cuenta()->sole();
            $cuenta->forceFill(array_intersect_key($datos, array_flip(self::DE_LA_CUENTA)))->save();

            if (array_key_exists('tipo_vehiculo', $datos)) {
                $repartidor->forceFill(['tipo_vehiculo' => $datos['tipo_vehiculo']])->save();
            }
        });

        if ($foto !== null) {
            $this->fotos->poner($repartidor->cuenta()->sole(), $foto);
        }

        $repartidor->unsetRelation('cuenta');
    }
}
