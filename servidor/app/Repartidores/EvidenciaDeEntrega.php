<?php

namespace App\Repartidores;

use App\Fotos\AlmacenDeFotos;
use App\Models\EstadoPedido;
use App\Models\Evidencia;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Pedidos\CambioDeEstado;
use App\Pedidos\CambioDeEstadoNoPermitido;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * El repartidor marca Entregado con su foto de evidencia (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Almacenamiento de
 * imágenes, "las evidencias de entrega"; ADR-0006; repartidor-E23).
 *
 * La foto va al almacenamiento privado de fotos del entorno, en la carpeta del pedido (evidencias/<id_publico>/), y en la
 * base solo su ruta, en evidencias, con quién la tomó y cuándo. No tiene URL pública: ninguna respuesta la trae, y el
 * almacenamiento solo sirve un archivo con URL firmada. El cambio a Entregado y la evidencia van en una transacción: si
 * Entregado no es el paso siguiente (App\Pedidos\CambioDeEstado), no queda ni la fila ni el archivo.
 *
 * Es opcional y solo al entregar: si es obligatoria, si también se toma al recolectar y si es foto o firma espera BR-29 y
 * DD-110 (repartidor-E24). Quién puede verla, y desde dónde, no lo fija la spec.
 */
final class EvidenciaDeEntrega
{
    public function __construct(
        private readonly AlmacenDeFotos $almacen,
        private readonly CambioDeEstado $cambio,
    ) {}

    /**
     * @throws CambioDeEstadoNoPermitido si Entregado no es el paso siguiente, o si no aceptó el pedido
     */
    public function entregar(Pedido $pedido, Repartidor $repartidor, UploadedFile $foto): Pedido
    {
        $ruta = $this->almacen->guardar($foto, 'evidencias/'.$pedido->id_publico);

        try {
            return DB::transaction(function () use ($pedido, $repartidor, $ruta): Pedido {
                $entregado = $this->cambio->marcarComoRepartidor($pedido, $repartidor, EstadoPedido::ENTREGADO);

                $entregado->evidencias()->create([
                    'tipo' => Evidencia::ENTREGA,
                    'ruta' => $ruta,
                    'id_usuarios' => $repartidor->id_usuarios,
                    // En UTC, como toda fecha que se guarda.
                    'tomada_en' => CarbonImmutable::now()->utc(),
                ]);

                return $entregado;
            });
        } catch (Throwable $error) {
            $this->almacen->borrar([$ruta]);

            throw $error;
        }
    }
}
