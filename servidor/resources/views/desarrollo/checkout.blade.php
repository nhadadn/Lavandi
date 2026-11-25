{{--
    La página de checkout de desarrollo (openspec/changes/pago/design.md, "Decisiones de la implementación"). Solo existe
    en local y en las pruebas: no es una pantalla del producto ni lleva la marca. Es mínima a propósito, como la de la
    recuperación de la contraseña.
--}}
@extends('desarrollo.pagina')

@section('titulo', 'Checkout simulado')

@section('contenido')
    <p class="aviso">Página de desarrollo: ninguna pasarela real cobra aquí.</p>
    <dl>
        <dt>Monto</dt>
        <dd>${{ $cobro->monto }}</dd>
        <dt>Pedido</dt>
        <dd>{{ $cobro->pedido->id_publico }}</dd>
        <dt>Cobro</dt>
        <dd>{{ $cobro->id_publico }} ({{ $cobro->estado }})</dd>
    </dl>

    @if (in_array($cobro->estado, [\App\Models\Pago::PENDIENTE, \App\Models\Pago::FALLIDO], true))
        @if ($cobro->estado === \App\Models\Pago::FALLIDO)
            <p>El intento anterior se rechazó; este es un intento nuevo.</p>
        @endif
        @error('resultado')
            <p class="error">{{ $message }}</p>
        @enderror
        <form method="post" action="{{ route('desarrollo.pasarela.resultado', $cobro->id_pasarela_orden) }}">
            @csrf
            <button type="submit" name="resultado" value="aprobado">Aprobar</button>
            <button type="submit" name="resultado" value="rechazado">Rechazar</button>
        </form>
    @elseif ($cobro->estado === \App\Models\Pago::PAGADO)
        <p>Este cobro ya está pagado.</p>
    @else
        <p>Este cobro ya no se puede pagar.</p>
    @endif
@endsection
