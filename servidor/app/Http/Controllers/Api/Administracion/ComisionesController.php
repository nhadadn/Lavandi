<?php

namespace App\Http\Controllers\Api\Administracion;

use App\Autorizacion\Autorizacion;
use App\Comisiones\PorcentajesDeComision;
use App\Http\Controllers\Controller;
use App\Models\Lavanderia;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * F-D05, la configuración de comisiones en la API de la administración: el porcentaje global y el propio de cada
 * lavandería, de todas las plazas (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #5, "Porcentaje de comisión por
 * socio o global."; openspec/changes/administracion-en-la-app/, tarea 11.1; administracion-app-E32 a E34). El mismo
 * contrato resume design.md, sección 4, F-D05.
 *
 * No valida ni calcula nada por su cuenta: llama a la misma clase de dominio que las páginas ComisionGlobal y
 * ComisionesPorLavanderia del panel, App\Comisiones\PorcentajesDeComision, que valida con sus reglas() y sus mensajes()
 * (administracion-E13) y dice cuál le aplica a cada lavandería; y las lavanderías salen de
 * Autorizacion::lavanderiasDelPanel() (ADR-0022, decisión 9; administracion-app-E11). Ningún porcentaje viene de
 * fábrica: los captura EL CLIENTE.
 *
 * Todas las rutas llevan sesión y pasan por el componente de autorización con el rol de administración y la habilidad
 * de su token (routes/api.php, el grupo de /api/administracion):
 *
 *     Authorization: Bearer <token de administración>     (auth:sanctum y autorizar:administracion)
 *
 *     401  { "message": "Unauthenticated." }              sin sesión, o con el token vencido o revocado
 *     403  { "message": "Esta cuenta no tiene acceso." }  una cuenta que no es de administración, o un token sin la
 *                                                        habilidad administracion (administracion-app-E06 y E07)
 *
 * Las comisiones, como salen en todas las respuestas:
 *
 *     {
 *       "global": {                                      el porcentaje global vigente, uno para todas las plazas
 *         "porcentaje": "12.50",                         de 0 a 100, con dos decimales
 *         "vigente_desde": "2026-12-01T12:00:00Z"        desde cuándo rige, en UTC: el global no es de ninguna plaza
 *       } | null,                                        null mientras no se guarde ninguno: sin ningún número
 *       "lavanderias": [                                 todas, de todas las plazas y en cualquier estado, por nombre
 *         {
 *           "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",  el ULID de la lavandería; la API nunca usa otro
 *           "nombre": "Lavandería Ejemplo",
 *           "plaza": { "nombre": "Plaza Ejemplo" },
 *           "propio": "7.25" | null,                     su porcentaje propio vigente; null si no tiene
 *           "aplica": {                                  el que le aplica: el propio si tiene; si no, el global
 *             "porcentaje": "7.25",
 *             "origen": "propio" | "global"
 *           } | null                                     null si no tiene propio y no hay global
 *         }
 *       ]
 *     }
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/administracion/comisiones                     El global y el de cada lavandería (administracion-app-E32)
 *
 *     200  { ...las comisiones... }
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     PUT /api/administracion/comisiones/global              Fija el porcentaje global (administracion-app-E33, con la
 *                                                            regla de administracion-E11)
 *
 *       porcentaje     obligatorio: de 0 a 100, con a lo más dos decimales, como "12.5" o 12.5. Va en el cuerpo: uno
 *                      en la consulta (?porcentaje=) no cuenta y responde 422 con "Escribe el porcentaje."
 *
 *     Le aplica a toda lavandería sin porcentaje propio, de cualquier plaza. El vigente se cierra en este instante y
 *     queda como historial.
 *
 *     200  { ...las comisiones, ya con el global nuevo... }
 *     403  Como arriba, también si el componente no deja a la cuenta fijar el global
 *          (Autorizacion::puedeFijarLaComisionGlobal()).
 *     422  { "message": "El porcentaje va de 0 a 100.", "errors": { "porcentaje": ["El porcentaje va de 0 a 100."] } }
 *          El porcentaje falta o no vale, con el mensaje de las comisiones; el vigente no cambia (administracion-app-E34).
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     PUT /api/administracion/comisiones/lavanderias/{id_publico}     Fija el porcentaje propio de la lavandería
 *                                                                     (administracion-app-E33, con la regla de
 *                                                                     administracion-E12)
 *
 *       porcentaje     obligatorio y en el cuerpo, como en el global
 *
 *     Le aplica el suyo y no el global. El propio vigente se cierra en este instante y queda como historial.
 *
 *     200  { ...las comisiones, ya con el propio nuevo... }
 *     404  { "message": "No existe la lavandería." }
 *          No existe, está borrada o el valor no es ULID: responden igual y sin ningún dato. La búsqueda va antes de
 *          validar.
 *     422  Como en el global.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     DELETE /api/administracion/comisiones/lavanderias/{id_publico}  Quita el porcentaje propio de la lavandería
 *                                                                     (administracion-app-E33, con la regla de
 *                                                                     administracion-E12)
 *
 *     Le vuelve a aplicar el global; el propio queda como historial. Sin propio vigente, no cambia nada.
 *
 *     200  { ...las comisiones, ya sin ese propio... }
 *     404  Como al fijarlo.
 *
 * La comisión del repartidor espera DD-019 (administracion-app-E35) y la de cada pedido, DD-020 y DD-013
 * (administracion-app-E36): no tienen ruta.
 */
class ComisionesController extends Controller
{
    public function __construct(
        private readonly Autorizacion $autorizacion,
        private readonly PorcentajesDeComision $porcentajes,
    ) {}

    public function mostrar(Request $peticion): JsonResponse
    {
        return $this->comisiones(self::cuenta($peticion));
    }

    public function fijarGlobal(Request $peticion): JsonResponse
    {
        $cuenta = self::cuenta($peticion);

        abort_unless($this->autorizacion->puedeFijarLaComisionGlobal($cuenta), 403, 'Esta cuenta no tiene acceso.');

        $this->porcentajes->fijarGlobal(self::porcentaje($peticion));

        return $this->comisiones($cuenta);
    }

    /**
     * La lavandería se busca antes de validar: una que no existe responde 404 aunque el porcentaje no valga.
     */
    public function fijarPropio(Request $peticion, string $lavanderia): JsonResponse
    {
        $cuenta = self::cuenta($peticion);

        $this->porcentajes->fijarPropio($this->delPanel($cuenta, $lavanderia), self::porcentaje($peticion));

        return $this->comisiones($cuenta);
    }

    public function quitarPropio(Request $peticion, string $lavanderia): JsonResponse
    {
        $cuenta = self::cuenta($peticion);

        $this->porcentajes->quitarPropio($this->delPanel($cuenta, $lavanderia));

        return $this->comisiones($cuenta);
    }

    /**
     * Las comisiones como salen en todas las respuestas: el global vigente y, por cada lavandería del panel, su propio y
     * el que le aplica, como las páginas del panel.
     */
    private function comisiones(Usuario $cuenta): JsonResponse
    {
        $global = $this->porcentajes->globalVigente();

        $lavanderias = $this->autorizacion->lavanderiasDelPanel($cuenta)
            ->with('plaza')
            ->orderBy('nombre')
            ->orderBy('id_lavanderias')
            ->get()
            ->map(function (Lavanderia $lavanderia): array {
                // Una sola vez por lavandería: queAplica() da el propio vigente si lo tiene, y si no, el global.
                $aplica = $this->porcentajes->queAplica($lavanderia);
                // De dónde sale, como lo dice el panel: el propio lleva su lavandería; el global, ninguna.
                $esPropio = $aplica?->id_lavanderias !== null;

                return [
                    'id_publico' => $lavanderia->id_publico,
                    'nombre' => $lavanderia->nombre,
                    'plaza' => ['nombre' => $lavanderia->plaza->nombre],
                    'propio' => $esPropio ? $aplica->porcentaje : null,
                    'aplica' => $aplica === null ? null : [
                        'porcentaje' => $aplica->porcentaje,
                        'origen' => $esPropio ? 'propio' : 'global',
                    ],
                ];
            });

        return response()->json([
            'global' => $global === null ? null : [
                'porcentaje' => $global->porcentaje,
                'vigente_desde' => $global->vigente_desde->utc()->toIso8601ZuluString(),
            ],
            'lavanderias' => $lavanderias,
        ]);
    }

    /**
     * La lavandería del panel con ese ULID. Una que no existe, borrada o con un valor que no es ULID responde el mismo
     * 404, sin ningún dato (ADR-0019, decisión 3; administracion-app-E12).
     */
    private function delPanel(Usuario $cuenta, string $lavanderia): Lavanderia
    {
        $encontrada = Str::isUlid($lavanderia)
            ? $this->autorizacion->lavanderiasDelPanel($cuenta)
                // HasUlids guarda el ULID en minúsculas.
                ->where('id_publico', Str::lower($lavanderia))
                ->first()
            : null;

        abort_if($encontrada === null, 404, 'No existe la lavandería.');

        return $encontrada;
    }

    /**
     * El porcentaje, solo del cuerpo (JSON o formulario): uno que llega en la consulta no cuenta, para que no termine
     * en la URL ni en las bitácoras.
     */
    private static function porcentaje(Request $peticion): mixed
    {
        return $peticion->post('porcentaje');
    }

    private static function cuenta(Request $peticion): Usuario
    {
        /** @var Usuario */
        return $peticion->user();
    }
}
