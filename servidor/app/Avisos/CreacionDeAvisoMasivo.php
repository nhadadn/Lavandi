<?php

namespace App\Avisos;

use App\Models\AvisoMasivo;
use App\Models\Usuario;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * La creación de un aviso masivo (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #7; administracion-E23): qué se
 * guarda, que sale en cola después de guardarlo y las reglas de su título y su texto, con sus mensajes.
 *
 * - Se guarda con la cuenta que lo mandó, la hora y sin plaza ni segmento, porque es global. El aviso a un segmento
 *   espera DD-113 (administracion-E24).
 * - Su salida por FCM va en cola (App\Avisos\MandarAvisoMasivo), después de guardarlo: quien lo manda no espera a
 *   Google.
 * - El título y el texto son obligatorios y van en texto. El título llega a 255 caracteres, lo que cabe en su columna;
 *   el texto, a 1000, para que el mensaje quepa en lo que acepta FCM por mensaje (grado E, SIN VERIFICAR: 4096 bytes;
 *   openspec/changes/administracion/design.md, "El panel").
 * - Los dos se recortan antes de validarlos, con Str::trim, el mismo recorte del middleware TrimStrings de la API: así
 *   el panel, al que Livewire no le pasa ese middleware, guarda lo mismo que la API para los mismos datos, y los topes
 *   se cuentan sin los espacios de los extremos (administracion-app-E11).
 *
 * Salió del panel, donde vivía en la página que escribe el aviso (CreateAvisoMasivo) y en su formulario
 * (AvisoMasivoForm), para que el panel y la API de la administración creen el aviso con la misma clase (ADR-0022,
 * decisiones 9 y 16; openspec/changes/administracion-en-la-app/design.md, sección 3; administracion-app-E11 y E42). El
 * formulario del panel valida con estas reglas y pone sus errores bajo data.<campo>; la API, con un 422 por campo.
 *
 * Quién puede mandar un aviso lo decide el componente de autorización (Autorizacion::puedeMandarAvisosMasivos()), antes
 * de llamar aquí.
 */
final class CreacionDeAvisoMasivo
{
    /** El tope del título, en caracteres. */
    public const TOPE_DEL_TITULO = 255;

    /** El tope del texto, en caracteres. */
    public const TOPE_DEL_MENSAJE = 1000;

    /**
     * Las reglas del título y del texto, por campo.
     *
     * @return array{titulo: list<string>, mensaje: list<string>}
     */
    public static function reglas(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:'.self::TOPE_DEL_TITULO],
            'mensaje' => ['required', 'string', 'max:'.self::TOPE_DEL_MENSAJE],
        ];
    }

    /**
     * Los mensajes de las reglas, para pasarlos al validador. El de un aviso sin título lo fija administracion-app-E42
     * ("Escribe el título.", con el estilo de CambiarPerfilRequest: "Escribe tu nombre."), y el del texto lo sigue.
     *
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            'titulo.required' => 'Escribe el título.',
            'titulo.string' => 'El título va en texto.',
            'titulo.max' => 'El título no puede pasar de '.self::TOPE_DEL_TITULO.' caracteres.',
            'mensaje.required' => 'Escribe el texto.',
            'mensaje.string' => 'El texto va en texto.',
            'mensaje.max' => 'El texto no puede pasar de '.self::TOPE_DEL_MENSAJE.' caracteres.',
        ];
    }

    /**
     * Guarda el aviso global que manda la cuenta y lo pone en cola para salir a los teléfonos registrados. A cuántos
     * salió se sabe cuando corre la cola: hasta entonces, destinatarios queda vacío.
     *
     * @throws ValidationException con el error de cada campo; entonces no guarda nada ni pone nada en cola.
     */
    public function crear(Usuario $cuenta, mixed $titulo, mixed $mensaje): AvisoMasivo
    {
        $datos = Validator::make(
            ['titulo' => self::recortado($titulo), 'mensaje' => self::recortado($mensaje)],
            self::reglas(),
            self::mensajes(),
        )->validate();

        $aviso = AvisoMasivo::create([
            'titulo' => $datos['titulo'],
            'mensaje' => $datos['mensaje'],
            'id_plazas' => null,
            'segmento' => null,
            'id_usuarios' => $cuenta->getKey(),
            'enviado_en' => now(),
        ]);

        MandarAvisoMasivo::dispatch($aviso)->afterCommit();

        return $aviso;
    }

    /**
     * El texto sin los espacios de los extremos; lo que no es texto pasa igual, para que lo rechace su regla.
     */
    private static function recortado(mixed $valor): mixed
    {
        return is_string($valor) ? Str::trim($valor) : $valor;
    }
}
