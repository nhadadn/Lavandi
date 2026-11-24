{{--
    Sección 1, "Hero / Encabezado": logo, tagline, botón principal de descarga e imagen con el mockup de la app
    (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2). El nombre y el logo salen de la configuración (APP_NAME y
    landing.logo); el tagline y la imagen son de EL CLIENTE (DD-117). El botón lleva a "Descarga la app", porque los
    enlaces de las tiendas no existen hasta publicar (design.md, lectura 1).

    Escenario: landing-E04 · Tarea 2.1
--}}
@php
    $tagline = config('landing.encabezado.tagline');
    $imagen = config('landing.encabezado.imagen');
@endphp
<header class="encabezado" id="encabezado" data-seccion="encabezado">
    <div class="contenedor encabezado__rejilla">
        <div>
            <h1 class="encabezado__marca">
                <img class="encabezado__logo" src="{{ asset(config('landing.logo')) }}" alt="" width="64" height="64" data-logo>
                <span>{{ config('app.name') }}</span>
            </h1>

            @if (filled($tagline))
                <p class="encabezado__tagline" data-tagline>{{ $tagline }}</p>
            @else
                <x-portada.de-prueba class="encabezado__tagline">tagline.</x-portada.de-prueba>
            @endif

            <a class="boton boton--principal" href="#descarga" data-boton-principal>
                <x-heroicon-o-device-phone-mobile class="boton__icono" aria-hidden="true" />
                Descarga la app
            </a>
        </div>

        @if (filled($imagen))
            {{-- La imagen más grande al abrir la portada en un teléfono: se pide primero (medicion-E11). --}}
            <img class="encabezado__imagen" src="{{ asset($imagen) }}" alt="{{ config('landing.encabezado.imagen_texto') }}" fetchpriority="high" decoding="async" data-imagen-encabezado>
        @else
            <x-portada.de-prueba como="div" class="imagen-de-prueba">imagen con el mockup de la app.</x-portada.de-prueba>
        @endif
    </div>
</header>
