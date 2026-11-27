<?php

namespace App\Lavanderias;

use App\Models\Lavanderia;
use App\Models\Municipio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * La edición de la información de una lavandería: nombre, dirección, foto, servicios con su precio y ventanas, con las
 * mismas validaciones del registro (App\Lavanderias\ReglasDeLaLavanderia), y la descripción y las fotos de su perfil
 * (alta-lavanderias-E26 a E31). La usan la app del socio (alta-lavanderias-E18; NSV-2026-001-v8.pdf:17:ANEXO A, Sección
 * A.3, Módulo B #6), la API de la administración (administracion-app-E14; :19:Módulo D #1) y el panel web de respaldo
 * (alta-lavanderias-E13 y E31).
 *
 * No sabe quién la llama: recibe la lavandería ya autorizada (App\Autorizacion\Autorizacion). Primero valida lo que
 * cambia (validar()), la misma validación para los tres (ADR-0022, decisión 9), y luego lo guarda (editar()). Cambia
 * solo lo que viene; los servicios, las ventanas y las fotos, si vienen, son la lista completa y reemplazan a las
 * anteriores.
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
     * Lo que cambia, validado con las reglas del registro: ReglasDeLaLavanderia::para() de esos campos, con sus mensajes
     * (alta-lavanderias-E13 y E31; administracion-app-E14). Qué cambia:
     *
     * - Sin lo leído, como en la app, que manda solo lo que cambia: cada campo de la edición que llega.
     * - Con lo leído al abrir, como en el panel, que manda el formulario entero: solo los campos que son distintos de lo
     *   leído. Así una lavandería de prueba sin municipio, o con franjas largas de horario, se edita en lo demás. Las
     *   fotos leídas se nombran por su lugar (0, la primera): la lista cambia si no son esos lugares, en su orden. Se
     *   comparan idénticas, porque una foto nueva es un archivo y nunca es un lugar; lo demás, con ==, como lo devuelve
     *   el formulario.
     *
     * La lista de fotos se toma en el orden de sus lugares: en multipart, Laravel junta primero los textos y después los
     * archivos, y fotos[0] como archivo y fotos[1]=0 quedarían al revés.
     *
     * @param  array<string, mixed>  $datos  Lo que llega, en la forma de la edición de la app: los campos de
     *                                       ReglasDeLaLavanderia::CAMPOS_DE_LA_EDICION; lo demás no cuenta.
     * @param  array<string, mixed>|null  $leidos  Lo que se leyó al abrir, en esa misma forma, con las fotos por su
     *                                             lugar; null si quien llama manda solo lo que cambia.
     * @return array<string, mixed> Lo validado que cambia, con la foto principal sola si vino, para editar().
     *
     * @throws ValidationException Con el error de cada campo en su llave de la edición: nombre, direccion.calle,
     *                             servicios.0.precio, ventanas.1, fotos.2...
     */
    public function validar(array $datos, ?array $leidos = null): array
    {
        if (is_array($datos['fotos'] ?? null)) {
            ksort($datos['fotos']);
        }

        $cambian = array_values(array_filter(
            array_intersect(ReglasDeLaLavanderia::CAMPOS_DE_LA_EDICION, array_keys($datos)),
            fn (string $campo): bool => $leidos === null
                || ! array_key_exists($campo, $leidos)
                || ($campo === 'fotos' ? $datos[$campo] !== $leidos[$campo] : $datos[$campo] != $leidos[$campo]),
        ));

        return Validator::make(
            array_intersect_key($datos, array_flip($cambian)),
            ReglasDeLaLavanderia::para($cambian),
            ReglasDeLaLavanderia::mensajes(),
        )->validate();
    }

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
