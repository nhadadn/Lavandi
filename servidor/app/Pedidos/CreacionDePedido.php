<?php

namespace App\Pedidos;

use App\Agenda\Agenda;
use App\Agenda\AgendaSinConfigurar;
use App\Direcciones\DireccionesDelPedido;
use App\Direcciones\DireccionesFueraDeServicio;
use App\Direcciones\RecogidaYEntrega;
use App\Models\Direccion;
use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\LavanderiaServicio;
use App\Models\Pedido;
use App\Models\PedidoDireccion;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crear el pedido al aceptar el resumen, la primera de las dos operaciones del pedido en la API
 * (openspec/changes/resumen-y-confirmacion/design.md, decisiones 1, 3, 4 y 5; C-188).
 *
 * El pedido nace en el estado previo al pago, sin folio y sin confirmado_en, con la plaza de su lavandería, la ventana,
 * el servicio con el precio de la lavandería en este momento y la copia de las dos direcciones, y con su primer evento,
 * todo en una transacción (confirmacion-E05). La copia es historial: no cambia si después cambian el precio o la
 * dirección guardada (confirmacion-E14).
 *
 * No sabe quién la llama: recibe la lavandería y las direcciones ya leídas como las ve la cuenta. El precio estimado,
 * el envío y la política de cancelación no entran: esperan DD-012, DD-010, DD-019, DD-025 y DD-016. El cupo por
 * ventana tampoco: espera DD-099 (agenda-E05).
 */
final class CreacionDePedido
{
    /**
     * Las señas de la dirección guardada que se copian al pedido tal como están, además de su punto y del nombre de su
     * municipio.
     */
    private const SENAS = [
        'alias',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'codigo_postal',
        'referencias',
    ];

    public function __construct(
        private readonly Agenda $agenda,
        private readonly RecogidaYEntrega $recogidaYEntrega,
    ) {}

    /**
     * Antes de crear nada revalida, con las mismas reglas con que se ofrecieron, la ventana (App\Agenda\Agenda) y las
     * direcciones (App\Direcciones\RecogidaYEntrega). Si alguna dejó de valer, lo rechaza con su motivo y no crea el
     * pedido (confirmacion-E08).
     *
     * @param  string  $servicio  El código del tipo de servicio, como lo da el perfil de la lavandería.
     * @param  array{inicio: mixed, fin: mixed}  $ventana  La ventana como la dio la agenda.
     *
     * @throws ValidationException
     * @throws AgendaSinConfigurar
     * @throws DireccionesFueraDeServicio
     * @throws EstadoPrevioAlPagoSinDefinir
     */
    public function crear(
        Usuario $usuario,
        Lavanderia $lavanderia,
        string $servicio,
        array $ventana,
        DireccionesDelPedido $direcciones,
    ): Pedido {
        $ofrecido = $this->servicioOfrecido($lavanderia, $servicio);
        // En UTC: es lo que se guarda en ventana_inicio y ventana_fin.
        $ventanaValida = $this->agenda->validar($lavanderia, $ventana['inicio'] ?? null, $ventana['fin'] ?? null);
        $this->recogidaYEntrega->validar($direcciones, $lavanderia);
        $previo = $this->estadoPrevioAlPago();

        return DB::transaction(function () use ($usuario, $lavanderia, $ofrecido, $ventanaValida, $direcciones, $previo): Pedido {
            // La plaza guardada de la dirección puede ser vieja: se vuelve a resolver antes de copiarla.
            $this->recogidaYEntrega->alinearPlaza($direcciones, $lavanderia);

            $pedido = Pedido::create([
                'id_plazas' => $lavanderia->id_plazas,
                'id_usuarios' => $usuario->getKey(),
                'id_lavanderias' => $lavanderia->getKey(),
                'id_estados_pedido' => $previo->getKey(),
                'ventana_inicio' => $ventanaValida->inicio,
                'ventana_fin' => $ventanaValida->fin,
            ]);

            $pedido->servicios()->create([
                'id_tipos_servicio' => $ofrecido->id_tipos_servicio,
                'id_unidades_medida' => $ofrecido->id_unidades_medida,
                'precio_unitario' => $ofrecido->precio,
            ]);

            $this->copiar($pedido, $direcciones->recogida, PedidoDireccion::RECOLECCION);
            $this->copiar($pedido, $direcciones->entrega, PedidoDireccion::ENTREGA);

            $pedido->eventos()->create([
                'id_estados_pedido' => $previo->getKey(),
                'id_usuarios' => $usuario->getKey(),
                // En UTC, como toda fecha que se guarda (App\Agenda\Ventana).
                'ocurrido_en' => CarbonImmutable::now()->utc(),
            ]);

            return $pedido;
        });
    }

    /**
     * El servicio activo de la lavandería con ese tipo, si el tipo también está activo, como lo muestra su perfil.
     *
     * @throws ValidationException
     */
    private function servicioOfrecido(Lavanderia $lavanderia, string $servicio): LavanderiaServicio
    {
        return $lavanderia->servicios()
            ->where('activo', true)
            ->whereHas('tipo', fn (Builder $tipo) => $tipo->where('codigo', $servicio)->where('activo', true))
            ->first()
            ?? throw ValidationException::withMessages(['servicio' => 'La lavandería no ofrece ese servicio.']);
    }

    /**
     * El estado en que nace el pedido. Tiene que ser uno que el usuario no ve: el primero que ve es Confirmado, y solo
     * llega con el pago (C-188).
     *
     * @throws EstadoPrevioAlPagoSinDefinir
     */
    private function estadoPrevioAlPago(): EstadoPedido
    {
        $codigo = config('pedidos.estado_previo_al_pago');

        $estado = is_string($codigo) && $codigo !== ''
            ? EstadoPedido::query()->where('codigo', $codigo)->where('visible_usuario', false)->first()
            : null;

        return $estado ?? throw new EstadoPrevioAlPagoSinDefinir;
    }

    /**
     * La copia de la dirección guardada en el pedido, con su punto copiado en la base, sin pasar por PHP.
     *
     * El municipio se copia como texto, con el nombre que tiene hoy en el catálogo: la copia es la foto del momento y no
     * cambia si el catálogo cambia después (ADR-0016; openspec/changes/direcciones/design.md, "pedido_direcciones no
     * cambia"). El estado no se copia.
     */
    private function copiar(Pedido $pedido, Direccion $direccion, string $tipo): void
    {
        $pedido->direcciones()->create([
            ...$direccion->only(self::SENAS),
            'municipio' => $direccion->municipio?->nombre,
            'tipo' => $tipo,
            'id_direcciones' => $direccion->getKey(),
            'ubicacion' => DB::raw(sprintf(
                '(select ubicacion from direcciones where id_direcciones = %d)',
                $direccion->getKey(),
            )),
        ]);
    }
}
