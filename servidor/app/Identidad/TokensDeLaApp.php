<?php

namespace App\Identidad;

use App\Autorizacion\Autorizacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * La única pieza que crea los tokens de Sanctum de la app (ADR-0005; ADR-0022, decisión 3): la usan el inicio de sesión
 * con correo y contraseña, la entrada con Google, el registro con correo y el registro del repartidor, y las pruebas que
 * necesitan un token de la app. Ninguna otra clase llama a createToken().
 *
 * Qué habilidad y qué vencimiento lleva cada token lo dice el componente de autorización
 * (Autorizacion::habilidadesDelTokenDeLaApp() y vencimientoDelTokenDeLaApp()):
 *
 * - la cuenta de administración recibe un token llamado "administracion", con esa sola habilidad y con vencimiento a
 *   los días de acceso.dias_del_token_de_administracion (administracion-app-E01). Al crearlo, se borran los tokens de
 *   esa cuenta que no llevan la habilidad, como los "*" que salieron por Google antes de ADR-0022: no abren ninguna
 *   ruta, tampoco el cierre de la sesión, así que sin este paso quedarían vivos (ADR-0022, decisión 4;
 *   administracion-app-E06);
 * - las demás cuentas reciben el token de siempre: llamado "app", con "*" y sin vencimiento.
 */
final class TokensDeLaApp
{
    /** El nombre de los tokens de la app en personal_access_tokens. */
    public const NOMBRE = 'app';

    /** El nombre del token de la administración (ADR-0022, decisión 2). */
    public const NOMBRE_DE_ADMINISTRACION = 'administracion';

    public function __construct(private readonly Autorizacion $autorizacion) {}

    /**
     * Crea el token de la sesión nueva de la cuenta. La respuesta de la app lleva su plainTextToken.
     */
    public function crear(Usuario $usuario): NewAccessToken
    {
        $habilidades = $this->autorizacion->habilidadesDelTokenDeLaApp($usuario);
        $vence = $this->autorizacion->vencimientoDelTokenDeLaApp($usuario);

        if (! in_array(Autorizacion::HABILIDAD_DE_ADMINISTRACION, $habilidades, true)) {
            return $usuario->createToken(self::NOMBRE, $habilidades, $vence);
        }

        return DB::transaction(function () use ($usuario, $habilidades, $vence): NewAccessToken {
            $sinLaHabilidad = $usuario->tokens()->get()->reject(
                fn (PersonalAccessToken $token): bool => $this->autorizacion->esTokenDeAdministracion($token),
            );

            $usuario->tokens()->whereKey($sinLaHabilidad->modelKeys())->delete();

            return $usuario->createToken(self::NOMBRE_DE_ADMINISTRACION, $habilidades, $vence);
        });
    }
}
