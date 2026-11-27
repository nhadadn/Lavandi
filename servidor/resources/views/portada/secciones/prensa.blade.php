{{--
    Sección 10, "Prensa / Reconocimientos": "Logos de medios o menciones, activables conforme se obtengan"
    (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2). Los logos se editan en el panel, con su interruptor de activo y su orden
    (ADR-0011): se muestran los activos, en el orden del panel, con el nombre del medio como texto alternativo. Cada imagen
    llega por la ruta pública portada.prensa.imagen, que la lee del disco privado de fotos (adenda del 2026-11-24 de
    ADR-0006). Sin logos activos, la sección sigue presente, para que las 12 secciones estén siempre (design.md, lectura
    3), con el texto de config('landing.prensa.sin_logos'), que decide EL CLIENTE (DD-117).

    Escenarios: landing-E17 y landing-E18 · Tarea 4.2
--}}
@php
    $logos = \App\Models\LogoPrensa::query()->activosEnOrden()->get();
    $sinLogos = config('landing.prensa.sin_logos');
@endphp
<section class="seccion" id="prensa" data-seccion="prensa" aria-labelledby="titulo-prensa">
    <div class="contenedor">
        <h2 class="seccion__titulo" id="titulo-prensa">Prensa / Reconocimientos</h2>

        @if ($logos->isNotEmpty())
            <ul class="prensa__logos">
                @foreach ($logos as $logo)
                    <li data-logo-prensa>
                        <img class="prensa__logo" src="{{ route('portada.prensa.imagen', $logo->archivo()) }}" alt="{{ $logo->nombre }}" loading="lazy" decoding="async">
                    </li>
                @endforeach
            </ul>
        @elseif (filled($sinLogos))
            <p class="seccion__entrada" data-sin-logos>{{ $sinLogos }}</p>
        @else
            <x-portada.de-prueba class="seccion__entrada" data-sin-logos-de-prueba>texto de la sección mientras no haya logos de medios o menciones.</x-portada.de-prueba>
        @endif
    </div>
</section>
