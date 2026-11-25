<?php

namespace App\Validacion;

use App\Models\Usuario;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El correo no tiene cuenta, sin distinguir mayúsculas y contando las cuentas borradas, como el único de la base sobre
 * lower(correo) (registro-E02). La usan los dos registros que crean una cuenta: el del usuario (registro-E01) y el del
 * repartidor (repartidor-E02); y la edición del repartidor en el panel (repartidor-E08), que no choca con su propia
 * cuenta.
 */
final class CorreoSinCuenta implements ValidationRule
{
    public const MENSAJE = 'Ese correo ya tiene una cuenta.';

    /**
     * @param  Usuario|null  $salvo  La cuenta que se edita: su propio correo no cuenta como ocupado.
     */
    public function __construct(private readonly ?Usuario $salvo = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $ocupado = Usuario::withTrashed()
            ->conCorreo($value)
            ->when($this->salvo !== null, fn ($consulta) => $consulta->whereKeyNot($this->salvo->getKey()))
            ->exists();

        if ($ocupado) {
            $fail(self::MENSAJE);
        }
    }
}
