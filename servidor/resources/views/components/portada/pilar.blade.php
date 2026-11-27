{{--
    Una tarjeta de pilar o de paso: icono, título del contrato y el texto que se cargue como contenido; sin texto, su
    marcador de contenido de prueba (DD-117). Con animado, el icono flota y se queda quieto con movimiento reducido
    (landing-E06 y landing-E07).
--}}
@props(['clave', 'titulo', 'icono', 'texto' => null, 'animado' => false, 'como' => 'li', 'atributo' => 'data-pilar'])
<{{ $como }} {{ $attributes->class('tarjeta') }} {{ $atributo }}="{{ $clave }}">
    {{ $slot }}
    @if ($animado)
        <x-dynamic-component :component="'heroicon-o-'.$icono" class="icono icono--animado" aria-hidden="true" data-icono-animado />
    @else
        <x-dynamic-component :component="'heroicon-o-'.$icono" class="icono" aria-hidden="true" />
    @endif
    <h3 class="tarjeta__titulo" data-titulo>{{ $titulo }}</h3>
    @if (filled($texto))
        <p class="tarjeta__texto">{{ $texto }}</p>
    @else
        <x-portada.de-prueba>texto de «{{ $titulo }}».</x-portada.de-prueba>
    @endif
</{{ $como }}>
