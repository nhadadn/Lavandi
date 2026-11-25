{{--
    El aviso tras un envío completo mientras su formulario no tiene destino (DD-119, landing-E22 bloqueado): dice que no
    se entregó, en vez de una confirmación que no sería cierta. Lo reemplaza la confirmación de la tarea 5.2.
--}}
@props(['formulario'])
@if (session('envio_sin_destino') === $formulario)
    <p class="aviso" role="status" data-envio-sin-destino>Este formulario todavía no tiene destino: tu envío no se entregó.</p>
@endif
