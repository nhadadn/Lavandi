<?php

namespace App\Providers;

use App\Avisos\AvisarAlUsuario;
use App\Avisos\MandarCorreoDeConfirmacion;
use App\Exportacion\Exportacion;
use App\Exportacion\PoliticaDeExportacion;
use App\Models\Usuario;
use App\Pedidos\EstadoDelPedidoCambiado;
use App\Pedidos\PedidoConfirmado;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // La exportación de Filament usa el modelo que liga la descarga a usuarios.id_usuarios: aquí no existe la tabla
        // users (openspec/changes/exportacion/design.md, "Tablas y migración").
        $this->app->bind(Export::class, Exportacion::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // El correo de confirmación cuelga del evento de pedido confirmado (confirmacion-E10; design.md, decisión 7).
        Event::listen(PedidoConfirmado::class, MandarCorreoDeConfirmacion::class);

        // Los avisos al teléfono del usuario cuelgan de la confirmación y de cada cambio de estado (ADR-0008;
        // openspec/changes/avisos/design.md).
        Event::listen(PedidoConfirmado::class, AvisarAlUsuario::class);
        Event::listen(EstadoDelPedidoCambiado::class, AvisarAlUsuario::class);

        // La ruta de descarga de Filament pregunta a esta Policy, que solo pasa la pregunta al componente único de
        // autorización (exportacion-E11; ADR-0019).
        Gate::policy(Exportacion::class, PoliticaDeExportacion::class);

        // La regla de la contraseña sale de la configuración cada vez que se pide (ADR-0018, decisión 2).
        Password::defaults(fn (): Password => Password::min((int) config('acceso.contrasena_minima')));

        // El límite de intentos del inicio de sesión, por correo y por dirección (ADR-0018, decisión 3;
        // registro-E30). Cuenta todo intento, abra sesión o no; al pasarlo, la contraseña ya no se revisa. Un correo
        // que no es texto cuenta como vacío: la validación, que corre después, responde con el error del campo.
        RateLimiter::for('inicio-de-sesion', fn (Request $request): Limit => Limit::perMinute(
            (int) config('acceso.intentos_por_minuto'),
        )->by(Usuario::normalizarCorreo(is_string($correo = $request->input('correo')) ? $correo : '').'|'.$request->ip())
            ->response(fn (Request $request, array $encabezados) => response()->json(
                ['message' => 'Demasiados intentos. Espera un minuto y vuelve a intentarlo.'],
                429,
                $encabezados,
            )));
    }
}
