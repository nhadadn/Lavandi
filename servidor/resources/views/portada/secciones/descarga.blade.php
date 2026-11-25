{{--
    Sección 4, "Descarga la app": botones de App Store y Google Play y mockup de pantalla (NSV-2026-001-v8.pdf:15:ANEXO
    A, Sección A.2). Cada botón lleva al enlace de su tienda que se configure; sin enlace, se muestra sin enlace y con
    el texto que se configure, nunca con una dirección vacía (design.md, lectura 4). Cada botón se marca con
    data-tienda, que también usa la medición (medicion-de-la-landing).

    Escenarios: landing-E08 y landing-E09 · Tarea 2.3
--}}
@php
    $botones = app(\App\Landing\BotonesDeDescarga::class)->botones();
    $imagen = config('landing.descarga.imagen');
@endphp
<section class="seccion" id="descarga" data-seccion="descarga" aria-labelledby="titulo-descarga">
    <div class="contenedor descarga__rejilla">
        <div>
            <h2 class="seccion__titulo" id="titulo-descarga">Descarga la app</h2>

            <ul class="tiendas">
                @foreach ($botones as $boton)
                    <li>
                        @if ($boton['enlace'] !== null)
                            <a class="boton boton--principal" href="{{ $boton['enlace'] }}" data-tienda="{{ $boton['clave'] }}">
                                <x-heroicon-o-device-phone-mobile class="boton__icono" aria-hidden="true" />
                                {{ $boton['nombre'] }}
                            </a>
                        @else
                            <span class="boton boton--sin-enlace" data-tienda="{{ $boton['clave'] }}">
                                <span>{{ $boton['nombre'] }}</span>
                                @if ($boton['texto_sin_enlace'] !== null)
                                    <span>{{ $boton['texto_sin_enlace'] }}</span>
                                @else
                                    <x-portada.de-prueba como="span">texto del botón sin enlace.</x-portada.de-prueba>
                                @endif
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        @if (filled($imagen))
            {{-- Más abajo en un teléfono: se pide al acercarse a ella (medicion-E11). --}}
            <img class="encabezado__imagen" src="{{ asset($imagen) }}" alt="{{ config('landing.descarga.imagen_texto') }}" loading="lazy" decoding="async" data-imagen-descarga>
        @else
            <x-portada.de-prueba como="div" class="imagen-de-prueba" data-imagen-descarga-de-prueba>mockup de pantalla de la app.</x-portada.de-prueba>
        @endif
    </div>
</section>
