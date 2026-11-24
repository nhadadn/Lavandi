<?php

namespace App\Identidad;

use App\Acceso\Google\IdentidadDeGoogle;
use App\Acceso\Google\IdTokenDeGoogleNoValido;
use App\Acceso\Google\VerificadorDeGoogle;
use App\Autorizacion\Autorizacion;
use App\Models\CuentaExterna;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Encuentra, crea o liga la cuenta de quien entra con Google (ADR-0005), después de verificar su idToken. El
 * verificador ya rechazó el idToken cuyo correo Google no da por verificado, haya o no cuenta con ese correo, con el
 * mismo mensaje que cualquier otro idToken no válido (registro-E17; C-228, DD-128 cerrada). Con el correo verificado:
 *
 * - la cuenta de Google ya ligada vuelve a su misma cuenta, por su (proveedor, sujeto), aunque en Google haya cambiado
 *   el correo (registro-E16);
 * - un correo sin cuenta crea la cuenta con el rol de usuario, sin contraseña, y la liga (registro-E16);
 * - un correo que ya tiene cuenta se liga a ella, y antes se cierran sus sesiones y se anula su contraseña
 *   (registro-E18; C-224, DD-091 cerrada; DD-132);
 * - una cuenta borrada no vuelve a entrar, y tampoco se crea otra con su correo, que sigue ocupado;
 * - una cuenta bloqueada desde el panel no entra, ya ligada o por su correo, y no se le liga nada ni se le cierra nada
 *   (administracion-E03): lo decide Autorizacion::puedeIniciarSesion().
 */
final class EntradaConGoogle
{
    /** El valor de cuentas_externas.proveedor para Google. */
    public const PROVEEDOR = 'google';

    public function __construct(
        private readonly VerificadorDeGoogle $verificador,
        private readonly AltaDeCuenta $alta,
        private readonly Autorizacion $autorizacion,
    ) {}

    /**
     * La cuenta de la persona, con su rol cargado.
     *
     * @throws EntradaConGoogleRechazada
     */
    public function entrar(string $idToken): Usuario
    {
        try {
            $identidad = $this->verificador->verificar($idToken);
        } catch (IdTokenDeGoogleNoValido $rechazo) {
            // El motivo queda en la bitácora; la app recibe un mensaje que no lo dice. El token nunca se escribe.
            Log::notice($rechazo->getMessage());

            throw EntradaConGoogleRechazada::noValida();
        }

        return DB::transaction(fn (): Usuario => $this->cuentaDe($identidad)->load('roles'));
    }

    private function cuentaDe(IdentidadDeGoogle $identidad): Usuario
    {
        $ligada = CuentaExterna::query()
            ->where('proveedor', self::PROVEEDOR)
            ->where('sujeto', $identidad->sujeto)
            ->first();

        if ($ligada !== null) {
            // La relación no trae las cuentas borradas.
            return $this->siPuedeEntrar($ligada->usuario ?? throw EntradaConGoogleRechazada::noValida());
        }

        $usuario = Usuario::withTrashed()->conCorreo($identidad->correo)->first();

        if ($usuario === null) {
            $usuario = $this->alta->deUsuario($identidad->correo, null);
        } elseif ($usuario->trashed()) {
            throw EntradaConGoogleRechazada::noValida();
        } else {
            // Antes de ligar: a una cuenta bloqueada no se le cierra nada ni se le liga Google.
            $this->cerrarElAccesoAnterior($this->siPuedeEntrar($usuario));
        }

        $usuario->cuentasExternas()->create([
            'proveedor' => self::PROVEEDOR,
            'sujeto' => $identidad->sujeto,
            'correo' => $identidad->correo,
        ]);

        return $usuario;
    }

    /**
     * La misma cuenta, si el componente de autorización la deja abrir sesión: una bloqueada desde el panel no entra
     * (administracion-E03).
     *
     * @throws EntradaConGoogleRechazada
     */
    private function siPuedeEntrar(Usuario $usuario): Usuario
    {
        return $this->autorizacion->puedeIniciarSesion($usuario) ? $usuario : throw EntradaConGoogleRechazada::bloqueada();
    }

    /**
     * Antes de ligar Google a la cuenta que ya existía, cierra sus sesiones y anula su contraseña (registro-E18;
     * DD-132, decisión 23 de acta-2026-11-20-modelo-y-pasarela). El registro con correo y contraseña no comprueba que
     * el correo sea de quien se registra, y Google sí lo comprobó: quien registró el correo antes no sigue dentro, ni
     * con su token ni con su contraseña. El dueño del correo puede crear otra con la recuperación (registro-E22 a E24).
     *
     * La contraseña anulada es null, como la de la cuenta que nace con Google: el inicio de sesión responde como a una
     * contraseña equivocada (registro-E08). Quien llama abre la sesión nueva después, con un token nuevo.
     */
    private function cerrarElAccesoAnterior(Usuario $usuario): void
    {
        $usuario->forceFill(['password' => null])->save();
        $usuario->tokens()->delete();
    }
}
