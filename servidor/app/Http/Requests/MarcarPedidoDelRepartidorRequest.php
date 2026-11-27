<?php

namespace App\Http\Requests;

use App\Fotos\ReglasDeLaFoto;
use App\Models\EstadoPedido;
use App\Pedidos\CambioDeEstado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * El botón que toca el repartidor: uno de los tres de su módulo, "Recolectado → En tránsito → Entregado"
 * (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #3), por el código del estado al que lleva: recolectado,
 * en_entrega (En tránsito) o entregado. Cualquier otro valor, como confirmado, aceptado o los botones de la lavandería, no
 * es un botón del repartidor (repartidor-E20). Si es el paso siguiente del pedido lo decide App\Pedidos\CambioDeEstado.
 *
 * Con Entregado puede ir la foto de evidencia, en multipart (repartidor-E23; ADR-0006), con las reglas de toda foto
 * (App\Fotos\ReglasDeLaFoto). Es opcional y solo con Entregado: si es obligatoria o si también va al recolectar espera
 * BR-29 y DD-110 (repartidor-E24).
 */
class MarcarPedidoDelRepartidorRequest extends FormRequest
{
    private const NO_ES_UN_BOTON = 'Ese no es un botón del repartidor.';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', Rule::in(CambioDeEstado::BOTONES_DEL_REPARTIDOR)],
            'foto' => ['bail', 'prohibited_unless:estado,'.EstadoPedido::ENTREGADO, ...ReglasDeLaFoto::opcional()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $tipos = implode(', ', ReglasDeLaFoto::tipos());

        return [
            'estado.required' => self::NO_ES_UN_BOTON,
            'estado.string' => self::NO_ES_UN_BOTON,
            'estado.in' => self::NO_ES_UN_BOTON,
            'foto.prohibited_unless' => 'La foto de evidencia se toma al marcar Entregado.',
            'foto.file' => "La foto debe ser una imagen: {$tipos}.",
            'foto.image' => "La foto debe ser una imagen: {$tipos}.",
            'foto.mimes' => "La foto debe ser una imagen: {$tipos}.",
            'foto.max' => 'La foto pesa más de :max KB.',
        ];
    }

    /**
     * La foto de evidencia, si la app la mandó.
     */
    public function foto(): ?UploadedFile
    {
        $foto = $this->file('foto');

        return $foto instanceof UploadedFile ? $foto : null;
    }
}
