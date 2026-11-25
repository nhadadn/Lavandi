<?php

namespace App\Avisos;

use App\Models\Pedido;
use App\Models\PedidoDireccion;
use App\Models\PedidoServicio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El correo de confirmación del pedido, con el folio y el resumen: la lavandería, el servicio, la fecha con su ventana y
 * las direcciones de recogida y de entrega (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #11; :30:ANEXO B,
 * Sección B.5; confirmacion-E10).
 *
 * Va siempre en cola (ShouldQueue): si el servicio de correo falla, el trabajo se reintenta con espera creciente y el
 * pedido sigue confirmado (confirmacion-E11). Después del último intento queda en failed_jobs, de donde se reintenta a
 * mano con queue:retry. Resend responde 429 al pasar de 100 correos al día (ADR-0014).
 *
 * El nombre del producto sale de la configuración (APP_NAME), nunca de la plantilla; el remitente, de MAIL_FROM_ADDRESS.
 * Las vistas son propias, en español, sin la plantilla de Laravel, como el correo de recuperación. Lo que se muestra es
 * la copia del pedido, en la hora de su plaza.
 */
class CorreoDeConfirmacion extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** Los intentos de envío antes de dejarlo en failed_jobs. */
    public int $tries = 5;

    /**
     * La espera, en segundos, antes de cada reintento.
     *
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly Pedido $pedido) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name').": tu pedido {$this->pedido->folio} está confirmado",
        );
    }

    public function content(): Content
    {
        $pedido = $this->pedido->loadMissing(['plaza', 'lavanderia', 'servicios.tipo', 'direcciones']);
        $zona = $pedido->plaza->zona_horaria;
        $inicio = $pedido->ventana_inicio->setTimezone($zona)->locale('es');
        $fin = $pedido->ventana_fin->setTimezone($zona);
        $direcciones = $pedido->direcciones->keyBy('tipo');

        return new Content(
            view: 'correo.confirmacion',
            text: 'correo.confirmacion-texto',
            with: [
                'producto' => (string) config('app.name'),
                'folio' => $pedido->folio,
                'lavanderia' => $pedido->lavanderia->nombre,
                'servicios' => $pedido->servicios
                    ->map(fn (PedidoServicio $servicio): string => $servicio->tipo->nombre)
                    ->implode(', '),
                'fecha' => $inicio->translatedFormat('l j \d\e F \d\e Y'),
                'ventana' => $inicio->format('H:i').' a '.$fin->format('H:i'),
                'recogida' => self::direccion($direcciones[PedidoDireccion::RECOLECCION]),
                'entrega' => self::direccion($direcciones[PedidoDireccion::ENTREGA]),
            ],
        );
    }

    /**
     * La dirección copiada en una línea: "Casa: Calle 123, int. 4, Colonia, C.P. 34000, Municipio".
     */
    private static function direccion(PedidoDireccion $copia): string
    {
        $partes = array_filter([
            trim(implode(' ', array_filter([$copia->calle, $copia->numero_exterior]))),
            $copia->numero_interior === null ? null : "int. {$copia->numero_interior}",
            $copia->colonia,
            $copia->codigo_postal === null ? null : "C.P. {$copia->codigo_postal}",
            $copia->municipio,
        ], fn (?string $parte): bool => $parte !== null && $parte !== '');

        $linea = implode(', ', $partes);

        return $copia->alias === null ? $linea : "{$copia->alias}: {$linea}";
    }
}
