<?php

namespace Tests\Feature\Autorizacion\Soporte;

use App\Cobro\Desarrollo\CobroSimulado;
use App\Models\Direccion;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\Plaza;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Database\Factories\RepartidorFactory;

/**
 * El sembrado de dos plazas de autorizacion-por-rol (design.md, decisión 4): en cada plaza, dos lavanderías, dos
 * repartidores y dos usuarios, unidos en cuatro cadenas. Cada cadena es un usuario con su dirección guardada, una
 * lavandería con su cuenta de socio y un repartidor activo, con dos pedidos del usuario a esa lavandería: uno en curso,
 * aceptado por la lavandería y por el repartidor, y uno entregado. Las cadenas no se cruzan: lo de una cadena no le
 * corresponde a nadie de las otras. Hay además una cuenta de administración. Se usa en el barrido contra fugas
 * (autorizacion-E14) y en la consulta de la administración (autorizacion-E13).
 *
 * Las cadenas se llaman UnoA, UnoB (plaza P1) y DosA, DosB (plaza P2). Cada dato que identifica a alguien lleva la marca
 * de su cadena, en ASCII, para buscarlo en las respuestas: BarridoUsuarioUnoA, BarridoCalleUnoA, BarridoLavanderiaUnoA,
 * BarridoRepartidorUnoA, barrido.usuario.unoa@ejemplo.com y los demás.
 *
 * Se usa junto con AgendaDePrueba, EstadosDePrueba, LavanderiasDePrueba, PedidosDeLaLavanderiaDePrueba, PedidosDePrueba
 * y PlazasDePrueba. Todo es ficticio: las plazas, en el océano Pacífico (PlazasDePrueba).
 */
trait DosPlazasDePrueba
{
    /** Las cadenas del sembrado, por plaza. */
    protected const CADENAS = ['UnoA' => 'Uno', 'UnoB' => 'Uno', 'DosA' => 'Dos', 'DosB' => 'Dos'];

    protected Plaza $plazaUno;

    protected Plaza $plazaDos;

    protected Usuario $administracion;

    /**
     * @var array<string, array{plaza: Plaza, usuario: Usuario, direccion: Direccion, lavanderia: Lavanderia, repartidor: Repartidor, enCurso: Pedido, entregado: Pedido}>
     */
    protected array $cadenas = [];

    /**
     * Las dos plazas, con su agenda y su zona, y las cuatro cadenas. El reloj queda a las 09:30 del jueves de prueba.
     */
    protected function sembrarDosPlazas(): void
    {
        // La plaza P1 es la de prepararPedido(), con su zona en (-120, 10); la P2, otra con su zona en (-118, 10).
        $this->prepararPedido();
        $this->plazaUno = $this->plaza;
        $this->plazaUno->update(['nombre' => 'BarridoPlazaUno']);
        $this->plazaDos = $this->plazaConAgenda('BarridoPlazaDos', anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $this->zonaDePrueba($this->plazaDos, 'BarridoZonaDos', $this->cuadrado(-118.0, 10.0));

        $numero = 0;
        foreach (self::CADENAS as $marca => $plaza) {
            $this->cadenas[$marca] = $plaza === 'Uno'
                ? $this->cadena($marca, $this->plazaUno, -119.5, ++$numero)
                : $this->cadena($marca, $this->plazaDos, -117.5, ++$numero);
        }

        $this->administracion = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create([
            'correo' => 'barrido.administracion@ejemplo.com',
        ]);
    }

    /**
     * Los datos que identifican a la cadena, cada uno con su etiqueta, para buscarlos en una respuesta. Sin lo público:
     * con $conLoPublico en false, sin los datos de la lavandería que el perfil público enseña al usuario (Módulo A #5).
     *
     * @return array<string, string>
     */
    protected function datosDeLaCadena(string $marca, bool $conLoPublico = true): array
    {
        $cadena = $this->cadenas[$marca];
        $datos = [
            $cadena['usuario']->correo => "el correo del usuario {$marca}",
            "BarridoUsuario{$marca}" => "el nombre del usuario {$marca}",
            $cadena['usuario']->telefono => "el teléfono del usuario {$marca}",
            $cadena['direccion']->id_publico => "la dirección del usuario {$marca}",
            "BarridoCalle{$marca}" => "la calle del usuario {$marca}",
            "BarridoReferencias{$marca}" => "las referencias del usuario {$marca}",
            $cadena['lavanderia']->administrador->correo => "el correo del socio de la lavandería {$marca}",
            $cadena['repartidor']->id_publico => "el repartidor {$marca}",
            $cadena['repartidor']->cuenta->correo => "el correo del repartidor {$marca}",
            "BarridoRepartidor{$marca}" => "el nombre del repartidor {$marca}",
            $cadena['repartidor']->cuenta->telefono => "el teléfono del repartidor {$marca}",
        ];

        foreach (['enCurso' => 'en curso', 'entregado' => 'entregado'] as $cual => $como) {
            $datos[$cadena[$cual]->id_publico] = "el pedido {$como} {$marca}";
            $datos[$cadena[$cual]->folio] = "el folio del pedido {$como} {$marca}";
            $datos[$cadena[$cual]->cobro->id_publico] = "el cobro del pedido {$como} {$marca}";
        }

        if ($conLoPublico) {
            $datos[$cadena['lavanderia']->id_publico] = "la lavandería {$marca}";
            $datos["BarridoLavanderia{$marca}"] = "el nombre de la lavandería {$marca}";
            $datos[$cadena['lavanderia']->telefono] = "el teléfono de la lavandería {$marca}";
        }

        return $datos;
    }

    /**
     * Una cadena de la plaza: el usuario, su dirección, la lavandería, el repartidor y sus dos pedidos.
     *
     * @return array{plaza: Plaza, usuario: Usuario, direccion: Direccion, lavanderia: Lavanderia, repartidor: Repartidor, enCurso: Pedido, entregado: Pedido}
     */
    private function cadena(string $marca, Plaza $plaza, float $longitud, int $numero): array
    {
        $minuscula = strtolower($marca);

        $usuario = $this->cuentaDeUsuario();
        $usuario->forceFill([
            'correo' => "barrido.usuario.{$minuscula}@ejemplo.com",
            'nombre' => "BarridoUsuario{$marca}",
            'telefono' => "618100000{$numero}",
        ])->save();

        $direccion = $this->direccionDe($usuario, [
            'id_plazas' => $plaza->getKey(),
            'ubicacion' => ['latitud' => 10.5, 'longitud' => $longitud],
            'alias' => "BarridoCasa{$marca}",
            'es_favorita' => true,
            'calle' => "BarridoCalle{$marca}",
            'referencias' => "BarridoReferencias{$marca}",
        ]);

        $lavanderia = $this->otraLavanderia($plaza);
        $lavanderia->update(['nombre' => "BarridoLavanderia{$marca}", 'telefono' => "618200000{$numero}"]);
        $lavanderia->administrador->forceFill(['correo' => "barrido.lavanderia.{$minuscula}@ejemplo.com"])->save();

        $repartidor = Repartidor::factory()->en($plaza)->deLaCuenta(RepartidorFactory::cuentaDeRepartidor([
            'correo' => "barrido.repartidor.{$minuscula}@ejemplo.com",
            'nombre' => "BarridoRepartidor{$marca}",
            'telefono' => "618300000{$numero}",
        ]))->create();

        $enCurso = $this->pedidoDeLaCadena($usuario, $lavanderia, $direccion, '11:00');
        $this->aceptarComoLaLavanderia($enCurso);
        $this->repartidorQueLoAcepta($enCurso, $repartidor);

        $entregado = $this->pedidoDeLaCadena($usuario, $lavanderia, $direccion, '12:00');
        $this->aceptarComoLaLavanderia($entregado);
        $this->repartidorQueLoAcepta($entregado, $repartidor);
        $this->llevarHastaEntregado($entregado);

        return [
            'plaza' => $plaza,
            'usuario' => $usuario->fresh(),
            'direccion' => $direccion,
            'lavanderia' => $lavanderia->fresh(),
            'repartidor' => $repartidor->fresh(),
            'enCurso' => $enCurso->fresh(),
            'entregado' => $entregado->fresh(),
        ];
    }

    /**
     * Un pedido del usuario a la lavandería, recogido en su dirección a esa hora del jueves de prueba y confirmado por su
     * cobro, pagado con la pasarela simulada (pago-E18).
     */
    private function pedidoDeLaCadena(Usuario $usuario, Lavanderia $lavanderia, Direccion $direccion, string $hora): Pedido
    {
        $inicio = CarbonImmutable::parse(self::JUEVES_DE_PRUEBA.' '.$hora, $lavanderia->plaza->zona_horaria);

        $id = $this->aceptarResumen($usuario, $this->resumen($direccion, datos: [
            'lavanderia' => $lavanderia->id_publico,
            'ventana' => ['inicio' => $inicio->toIso8601String(), 'fin' => $inicio->addHour()->toIso8601String()],
        ]))->assertCreated()->json('pedido.id_publico');

        return app(CobroSimulado::class)->pagar(Pedido::query()->where('id_publico', $id)->sole())->fresh();
    }
}
