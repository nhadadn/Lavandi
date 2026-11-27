<?php

namespace App\Http\Controllers\Api\Administracion;

use App\Autorizacion\Autorizacion;
use App\Http\Controllers\Controller;
use App\Http\Resources\Administracion\UsuarioDeLaAdministracionResource;
use App\Identidad\BloqueoDeCuenta;
use App\Models\Usuario;
use App\Pedidos\HistorialDelUsuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * La gestión de usuarios de la administración, en la app (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #3,
 * "Consulta de usuarios activos, historial y bloqueo"; openspec/changes/administracion-en-la-app/design.md, sección 4,
 * F-D03): la lista de las cuentas de usuario, la ficha de cada una con su historial de pedidos, y el bloqueo y su
 * levantamiento.
 *
 * No valida ni calcula por su cuenta: llama a las mismas clases que el panel (ADR-0022, decisión 9). Las cuentas salen de
 * Autorizacion::usuariosDelPanel(); el historial, de App\Pedidos\HistorialDelUsuario; quién bloquea a quién, de
 * Autorizacion::puedeBloquear(); y el bloqueo y su levantamiento, de App\Identidad\BloqueoDeCuenta. La búsqueda, el orden
 * y las páginas de la lista son de la API, como el panel tiene los suyos.
 *
 * No hay rutas para dar de alta, editar ni borrar usuarios: el Módulo D #3 dice consulta, historial y bloqueo. El filtro
 * de usuarios activos espera DD-111 (administracion-app-E26), y lo que pasa con un pedido activo al bloquear, DD-016
 * (administracion-app-E27).
 *
 * Contrato para la app. Todas las rutas llevan el token de administración y pasan por el componente de autorización:
 *
 *     Authorization: Bearer <token de administración>     (auth:sanctum y autorizar:administracion)
 *
 *     401  { "message": "Unauthenticated." }              sin sesión, o con el token vencido o revocado
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no es de administración, o su token no lleva la
 *                                                         habilidad administracion (administracion-app-E06 y E07)
 *     404  { "message": "No existe el usuario." }         la cuenta no existe, no es de usuario (la de un socio o la de
 *                                                         la administración) o el valor no es un ULID: todas responden
 *                                                         igual y sin ningún dato (administracion-app-E12; ADR-0019)
 *
 * La cuenta, como sale en la lista:
 *
 *     {
 *       "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",   el ULID de la cuenta
 *       "correo": "persona@ejemplo.com",
 *       "nombre": "Ana" | null,                       null si todavía no lo escribe
 *       "estado": "activo" | "bloqueado"
 *     }
 *
 * Y en su ficha, con su teléfono y su historial:
 *
 *     {
 *       "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",
 *       "correo": "persona@ejemplo.com",
 *       "nombre": "Ana" | null,
 *       "telefono": "6181234567" | null,
 *       "estado": "activo" | "bloqueado",
 *       "historial": [                                sus pedidos confirmados, del más reciente al más antiguo; vacía
 *         {                                           si no tiene. Uno que espera su pago no va (C-188)
 *           "folio": "7KQ2M9XA",
 *           "fecha": "2026-12-03",                    el día de la recolección, en la hora de la plaza
 *           "lavanderia": "Lavandería del centro",    su nombre
 *           "estado": "En proceso",                   el estado operativo, como lo nombra la administración (ADR-0021)
 *           "monto": "1180.50" | null                 en pesos: el final si ya se pesó y si no el estimado; null si no
 *         }                                           tiene ninguno, como hoy (DD-012, DD-010)
 *       ]
 *     }
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/administracion/usuarios                     La lista de las cuentas de usuario (administracion-app-E24)
 *
 *     ?buscar=ana     opcional: el texto se busca dentro del correo o del nombre, sin distinguir mayúsculas y sin los
 *                     espacios de las orillas; un % o un _ se buscan como letras. Sin texto, o si no es texto (una
 *                     lista, o bytes que no son UTF-8 válido), la lista completa
 *     ?pagina=2       opcional: la página, de 1 en adelante; si no es un número, la primera
 *
 *     200  {
 *            "usuarios": [ { ...la cuenta... } ],      por correo; vacía si no hay o si la página ya no tiene cuentas
 *            "pagina": {
 *              "actual": 2,
 *              "ultima": 3,                          la última página con cuentas; 1 si no hay ninguna
 *              "por_pagina": 25,                     cuántas cuentas trae cada página
 *              "total": 61                           cuántas cuentas hay con esa búsqueda, en todas las páginas
 *            }
 *          }
 *          Las cuentas con el rol de usuario, cualquiera que sea la plaza de sus pedidos. Las de los socios se gestionan
 *          con su lavandería y su repartidor (Módulo D #1 y #2), y las de la administración no se listan.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/administracion/usuarios/{id_publico}        La ficha de la cuenta, con su historial
 *                                                          (administracion-app-E24, con la regla de administracion-E01)
 *
 *     200  { "usuario": { ...la cuenta, con su teléfono y su historial... } }
 *     404  Como en todas las rutas.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     POST /api/administracion/usuarios/{id_publico}/bloqueo     Bloquea la cuenta (administracion-app-E25, con la regla
 *                                                                de administracion-E03)
 *
 *     Sin cuerpo.
 *
 *     200  { "usuario": { ...su ficha, con "estado" en "bloqueado"... } }
 *          Se guarda la hora del bloqueo y se cierran todas sus sesiones: la siguiente petición de la app con cualquiera
 *          de ellas responde 401, y mientras siga bloqueada no entra con su correo ni con Google. Sus pedidos no cambian
 *          (DD-016). Una cuenta ya bloqueada se queda como está, con su hora de bloqueo.
 *     404  Como en todas las rutas. Nada cambia.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     DELETE /api/administracion/usuarios/{id_publico}/bloqueo   Levanta el bloqueo (administracion-app-E25, con la
 *                                                                regla de administracion-E04)
 *
 *     200  { "usuario": { ...su ficha, con "estado" en "activo"... } }
 *          Vuelve a entrar con su contraseña o con Google. Una cuenta activa se queda activa.
 *     404  Como en todas las rutas. Nada cambia.
 */
class UsuariosController extends Controller
{
    /** Cuántas cuentas trae cada página de la lista. */
    public const POR_PAGINA = 25;

    public function __construct(
        private readonly Autorizacion $autorizacion,
        private readonly HistorialDelUsuario $historial,
    ) {}

    public function lista(Request $peticion): JsonResponse
    {
        $consulta = $this->autorizacion->usuariosDelPanel($this->cuenta($peticion));
        $buscar = $peticion->query('buscar');

        // Un texto que no es UTF-8 válido no llega a la base: PostgreSQL lo rechaza en el LIKE y la ruta respondería 500.
        if (is_string($buscar) && mb_check_encoding($buscar, 'UTF-8') && trim($buscar) !== '') {
            self::buscar($consulta, trim($buscar));
        }

        $pagina = $consulta
            ->orderBy('usuarios.correo')
            ->orderBy('usuarios.id_usuarios')
            ->paginate(self::POR_PAGINA, pageName: 'pagina');

        return response()->json([
            'usuarios' => $pagina->getCollection()
                ->map(fn (Usuario $usuario): UsuarioDeLaAdministracionResource => UsuarioDeLaAdministracionResource::deLaLista($usuario))
                ->all(),
            'pagina' => [
                'actual' => $pagina->currentPage(),
                'ultima' => $pagina->lastPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    public function mostrar(Request $peticion, string $usuario): JsonResponse
    {
        $cuenta = $this->cuenta($peticion);

        return $this->ficha($cuenta, $this->usuarioVisible($cuenta, $usuario));
    }

    public function bloquear(Request $peticion, BloqueoDeCuenta $bloqueo, string $usuario): JsonResponse
    {
        $cuenta = $this->cuenta($peticion);
        $encontrado = $this->usuarioQueBloquea($cuenta, $usuario);

        $bloqueo->bloquear($encontrado);

        return $this->ficha($cuenta, $encontrado);
    }

    public function levantarBloqueo(Request $peticion, BloqueoDeCuenta $bloqueo, string $usuario): JsonResponse
    {
        $cuenta = $this->cuenta($peticion);
        $encontrado = $this->usuarioQueBloquea($cuenta, $usuario);

        $bloqueo->levantar($encontrado);

        return $this->ficha($cuenta, $encontrado);
    }

    private function cuenta(Request $peticion): Usuario
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();

        return $cuenta;
    }

    /**
     * La búsqueda de la lista: el texto dentro del correo o del nombre, sin distinguir mayúsculas. Los comodines de LIKE
     * se escapan, así que un % o un _ se buscan como letras.
     *
     * @param  Builder<Usuario>  $consulta
     */
    private static function buscar(Builder $consulta, string $texto): void
    {
        $patron = '%'.addcslashes($texto, '\\%_').'%';

        $consulta->where(fn (Builder $donde): Builder => $donde
            ->whereLike('usuarios.correo', $patron)
            ->orWhereLike('usuarios.nombre', $patron));
    }

    /**
     * Una cuenta de usuario por su identificador público. Quién ve cuáles lo dice el componente de autorización: una que
     * no es de usuario, una que no existe y un valor que no es ULID responden el mismo 404, sin ningún dato
     * (administracion-app-E12; ADR-0019, decisión 3).
     */
    private function usuarioVisible(Usuario $cuenta, string $usuario): Usuario
    {
        $encontrado = Str::isUlid($usuario)
            ? $this->autorizacion->usuariosDelPanel($cuenta)
                // HasUlids guarda el ULID en minúsculas.
                ->where('usuarios.id_publico', Str::lower($usuario))
                ->first()
            : null;

        abort_if($encontrado === null, 404, 'No existe el usuario.');

        return $encontrado;
    }

    /**
     * La cuenta de usuario que la administración bloquea o desbloquea: la que ve, si el componente de autorización dice
     * que puede bloquearla (administracion-E03 y E04), como el botón del panel.
     */
    private function usuarioQueBloquea(Usuario $cuenta, string $usuario): Usuario
    {
        $encontrado = $this->usuarioVisible($cuenta, $usuario);

        // Replica el authorize() de AccionesDeBloqueo. Hoy puedeBloquear() consulta lo mismo que usuariosDelPanel(), así
        // que este 403 no se alcanza: si algún día se separan, la regla nueva necesita su prueba.
        abort_unless($this->autorizacion->puedeBloquear($cuenta, $encontrado), 403, 'Esta cuenta no tiene acceso.');

        return $encontrado;
    }

    /**
     * La ficha de la cuenta, con su historial de la clase de dominio que usa también la ficha del panel.
     */
    private function ficha(Usuario $cuenta, Usuario $usuario): JsonResponse
    {
        return response()->json([
            'usuario' => UsuarioDeLaAdministracionResource::deLaFicha($usuario, $this->historial->de($cuenta, $usuario)),
        ]);
    }
}
