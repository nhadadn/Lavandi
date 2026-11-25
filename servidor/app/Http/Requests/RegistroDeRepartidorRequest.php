<?php

namespace App\Http\Requests;

use App\Fotos\ReglasDeLaFoto;
use App\Validacion\Contrasena;
use App\Validacion\CorreoSinCuenta;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Los datos del registro del repartidor: nombre, teléfono, correo, foto de perfil y tipo de vehículo
 * (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #1), y la contraseña, porque todos los roles entran con correo y
 * contraseña (ADR-0005). Los cinco son obligatorios (openspec/changes/repartidor/design.md, lectura 4): EL CLIENTE los
 * necesita completos para validar al repartidor fuera de la plataforma. La forma exacta está en
 * App\Http\Controllers\Api\RepartidorController.
 *
 * Solo se leen esos seis campos: cualquier otro, como un estado, una plaza o un rol, se ignora (repartidor-E01). El
 * tipo de vehículo es texto libre (lectura 3). Laravel quita los espacios de los lados y deja vacío lo que queda en
 * blanco, así que un dato de puros espacios se rechaza igual que uno que falta (repartidor-E02).
 *
 * El teléfono: de 10 a 15 dígitos, que pueden ir con espacios, guiones, paréntesis y un + al frente. Es una regla
 * técnica mínima para no guardar texto que no es un teléfono; el contrato no fija su formato.
 */
class RegistroDeRepartidorRequest extends FormRequest
{
    private const TELEFONO_NO_VALIDO = 'El teléfono no es válido.';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Las reglas de los cinco datos, que también usa la edición del panel (repartidor-E08), sin la contraseña.
     *
     * @return array<string, list<mixed>>
     */
    public static function reglasDeLosDatos(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['bail', 'required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]+$/', self::digitosDeTelefono(...)],
            'correo' => ['bail', 'required', 'string', 'email', 'max:255'],
            'tipo_vehiculo' => ['required', 'string', 'max:100'],
            'foto' => ReglasDeLaFoto::reglas(),
        ];
    }

    /**
     * Los mensajes de los cinco datos.
     *
     * @return array<string, string>
     */
    public static function mensajesDeLosDatos(): array
    {
        $tipos = implode(', ', ReglasDeLaFoto::tipos());

        return [
            'nombre.required' => 'Escribe tu nombre.',
            'nombre.string' => 'El nombre va en texto.',
            'nombre.max' => 'El nombre es demasiado largo.',
            'telefono.required' => 'Escribe tu teléfono.',
            'telefono.string' => self::TELEFONO_NO_VALIDO,
            'telefono.max' => self::TELEFONO_NO_VALIDO,
            'telefono.regex' => self::TELEFONO_NO_VALIDO,
            'correo.required' => 'Escribe tu correo.',
            'correo.string' => 'El correo no es válido.',
            'correo.email' => 'El correo no es válido.',
            'correo.max' => 'El correo no puede tener más de :max caracteres.',
            'tipo_vehiculo.required' => 'Escribe tu tipo de vehículo.',
            'tipo_vehiculo.string' => 'El tipo de vehículo va en texto.',
            'tipo_vehiculo.max' => 'El tipo de vehículo es demasiado largo.',
            'foto.required' => 'Toma o elige tu foto de perfil.',
            'foto.file' => "La foto debe ser una imagen: {$tipos}.",
            'foto.image' => "La foto debe ser una imagen: {$tipos}.",
            'foto.mimes' => "La foto debe ser una imagen: {$tipos}.",
            'foto.max' => 'La foto pesa más de :max KB.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = self::reglasDeLosDatos();
        $reglas['correo'][] = new CorreoSinCuenta;

        return [...$reglas, 'password' => Contrasena::reglas()];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...self::mensajesDeLosDatos(), ...Contrasena::mensajes()];
    }

    public function foto(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('foto');
    }

    /**
     * De 10 a 15 dígitos, sin contar los espacios, los guiones, los paréntesis ni el +.
     */
    private static function digitosDeTelefono(string $atributo, mixed $telefono, Closure $fallar): void
    {
        $digitos = strlen((string) preg_replace('/\D/', '', (string) $telefono));

        if ($digitos < 10 || $digitos > 15) {
            $fallar(self::TELEFONO_NO_VALIDO);
        }
    }
}
