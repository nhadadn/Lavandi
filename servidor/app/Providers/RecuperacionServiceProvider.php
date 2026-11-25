<?php

namespace App\Providers;

use App\Models\Usuario;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * El límite de las peticiones del enlace de recuperación de la contraseña (ADR-0018, decisión 3; registro-E31).
 *
 * Cuenta por correo y por dirección, con el mismo valor y la misma clave que el límite del inicio de sesión
 * (acceso.intentos_por_minuto; registro-E30), pero con su propio contador: los intentos de entrar no gastan las
 * peticiones del enlace. La petición que pasa el límite recibe 429 y no llega al envío del correo.
 *
 * Un correo que no es texto cuenta como vacío: la validación, que corre después, responde con el error del campo.
 */
class RecuperacionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('recuperacion', fn (Request $request): Limit => Limit::perMinute(
            (int) config('acceso.intentos_por_minuto'),
        )->by(Usuario::normalizarCorreo(is_string($correo = $request->input('correo')) ? $correo : '').'|'.$request->ip())
            ->response($this->demasiadosIntentos(...)));
    }

    /**
     * @param  array<string, string>  $encabezados
     */
    private function demasiadosIntentos(Request $request, array $encabezados): JsonResponse
    {
        return response()->json(
            ['message' => 'Demasiados intentos. Espera un minuto y vuelve a intentarlo.'],
            429,
            $encabezados,
        );
    }
}
