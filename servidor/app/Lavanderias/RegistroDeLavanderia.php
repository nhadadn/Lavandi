<?php

namespace App\Lavanderias;

use App\Autorizacion\Autorizacion;
use App\Models\Lavanderia;
use App\Models\Municipio;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * El registro de la lavandería desde la app (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #1;
 * alta-lavanderias-E01): con nombre, dirección, foto, servicios con su precio y horario ya validados
 * (App\Lavanderias\ReglasDeLaLavanderia), guarda la lavandería a nombre de la cuenta del socio, con la ubicación y la
 * plaza de su dirección, sus servicios, sus ventanas y su foto, todo o nada.
 *
 * La dirección se geocodifica antes de abrir la transacción, una sola vez. La foto se sube dentro de ella; si algo
 * falla después, el archivo se borra y no queda nada (alta-lavanderias-E02).
 *
 * El estado con que nace lo fija DD-053 (alta-lavanderias-E11 y E12). Mientras siga abierta, nace pendiente y sin
 * aprobada_en: es la lectura de design.md, Open Questions 9, porque "solo una lavandería con alta aparece al usuario".
 * Hoy una pendiente sí aparece: quién aparece lo fija DD-083 (cercanas-E09).
 */
final class RegistroDeLavanderia
{
    public const ESTADO_AL_REGISTRARSE = Lavanderia::PENDIENTE;

    public function __construct(
        private readonly Autorizacion $autorizacion,
        private readonly UbicacionDeLaLavanderia $ubicacion,
        private readonly ServiciosYVentanas $serviciosYVentanas,
        private readonly FotosDeLaLavanderia $fotos,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  El registro validado, sin la foto.
     *
     * @throws CuentaConLavanderia
     */
    public function registrar(Usuario $cuenta, array $datos, UploadedFile $foto): Lavanderia
    {
        // Antes de geocodificar, para no gastar una consulta en un registro que no va a entrar.
        $this->rechazarSiYaTieneLavanderia($cuenta);

        $municipio = Municipio::query()->with('entidadFederativa')->where('codigo', $datos['direccion']['municipio'])->sole();
        $ubicada = $this->ubicacion->ubicar($datos['direccion'], $municipio);

        $guardadas = [];

        try {
            return DB::transaction(function () use ($cuenta, $datos, $foto, $ubicada, &$guardadas): Lavanderia {
                // Dos registros a la vez de la misma cuenta: el segundo espera al primero y lo encuentra.
                Usuario::query()->whereKey($cuenta->getKey())->lockForUpdate()->first();
                $this->rechazarSiYaTieneLavanderia($cuenta);

                $lavanderia = Lavanderia::create([
                    ...$ubicada->columnas($datos['direccion']),
                    'id_usuarios' => $cuenta->getKey(),
                    'nombre' => $datos['nombre'],
                    'estado' => self::ESTADO_AL_REGISTRARSE,
                    'aprobada_en' => null,
                ]);

                $this->serviciosYVentanas->guardarServicios($lavanderia, $datos['servicios']);
                $this->serviciosYVentanas->guardarVentanas($lavanderia, $datos['ventanas']);
                $this->fotos->reemplazarPrincipal($lavanderia, $foto, $guardadas);

                return $lavanderia;
            });
        } catch (Throwable $error) {
            $this->fotos->borrar($guardadas);

            throw $error;
        }
    }

    /**
     * @throws CuentaConLavanderia
     */
    private function rechazarSiYaTieneLavanderia(Usuario $cuenta): void
    {
        if ($this->autorizacion->lavanderiasDeLaCuenta($cuenta)->exists()) {
            throw new CuentaConLavanderia;
        }
    }
}
