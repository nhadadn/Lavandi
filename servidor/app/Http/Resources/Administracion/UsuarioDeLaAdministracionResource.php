<?php

namespace App\Http\Resources\Administracion;

use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una cuenta de usuario como la ve la administración en la app (administracion-app-E24): en la lista, su identificador
 * público, su correo, su nombre y su estado; en la ficha, además su teléfono y su historial de pedidos, cada uno con su
 * folio, su fecha, su lavandería, su estado y su monto. La forma exacta está en
 * App\Http\Controllers\Api\Administracion\UsuariosController.
 *
 * Las filas del historial son las de App\Pedidos\HistorialDelUsuario, la clase que usa también la ficha del panel
 * (administracion-app-E11): aquí solo se escriben como las lee la app, la fecha AAAA-MM-DD, como el historial del
 * usuario (App\Http\Resources\PedidoDeLaListaResource), y el monto tal cual.
 *
 * Nunca la contraseña, la llave interna ni la ruta de su foto (ADR-0016, regla 5).
 *
 * @mixin Usuario
 */
class UsuarioDeLaAdministracionResource extends JsonResource
{
    /**
     * Sin historial, la cuenta va como en la lista; con él, como en su ficha.
     *
     * @param  list<array{folio: string, fecha: CarbonImmutable, lavanderia: string, estado: string, monto: string|null}>|null  $historial
     */
    public function __construct(Usuario $usuario, private readonly ?array $historial = null)
    {
        parent::__construct($usuario);
    }

    /**
     * La cuenta como va en la lista.
     */
    public static function deLaLista(Usuario $usuario): self
    {
        return new self($usuario, null);
    }

    /**
     * La cuenta como va en su ficha, con las filas de su historial (App\Pedidos\HistorialDelUsuario::de()).
     *
     * @param  list<array{folio: string, fecha: CarbonImmutable, lavanderia: string, estado: string, monto: string|null}>  $historial
     */
    public static function deLaFicha(Usuario $usuario, array $historial): self
    {
        return new self($usuario, $historial);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->historial === null) {
            return [
                'id_publico' => $this->id_publico,
                'correo' => $this->correo,
                'nombre' => $this->nombre,
                'estado' => $this->estado,
            ];
        }

        return [
            'id_publico' => $this->id_publico,
            'correo' => $this->correo,
            'nombre' => $this->nombre,
            'telefono' => $this->telefono,
            'estado' => $this->estado,
            'historial' => array_map(fn (array $fila): array => [
                ...$fila,
                'fecha' => $fila['fecha']->toDateString(),
            ], $this->historial),
        ];
    }
}
