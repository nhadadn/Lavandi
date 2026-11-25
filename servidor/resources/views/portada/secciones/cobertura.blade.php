{{--
    Sección 7, "Cobertura": mapa interactivo con las zonas de servicio activas del territorio inicial
    (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2; NSV-2026-001-v8.pdf:4:Cláusula I). Se dibuja con polígonos propios,
    en SVG, sin teselas ni servicios de mapas de pago (ADR-0009, decisión 5): App\Landing\ZonasDeLaCobertura lee las
    zonas activas de las plazas activas y App\Landing\MapaDeCobertura las proyecta. Acercar, alejar y el nombre de la
    zona que se toca o se señala los agrega resources/js/portada.js; sin JavaScript, cada zona dice su nombre con su
    title.

    La sección se construye aunque el formulario en papel del 2026-11-05 la marque "No": quitarla es cambio de alcance
    (proposal.md).

    Escenarios: landing-E12, landing-E13 y landing-E14 · Tareas 3.1 y 3.2
--}}
@php
    $mapa = app(\App\Landing\MapaDeCobertura::class)->dibujar(app(\App\Landing\ZonasDeLaCobertura::class)->activas());
@endphp
<section class="seccion seccion--superficie" id="cobertura" data-seccion="cobertura" aria-labelledby="titulo-cobertura">
    <div class="contenedor">
        <h2 class="seccion__titulo" id="titulo-cobertura">Cobertura</h2>

        @if ($mapa['zonas'] === [])
            <p class="aviso" data-sin-zonas>Todavía no hay zonas de servicio activas.</p>
        @else
            <div class="mapa" data-mapa>
                <svg
                    class="mapa__lienzo"
                    viewBox="0 0 {{ $mapa['ancho'] }} {{ $mapa['alto'] }}"
                    role="group"
                    aria-label="Mapa de las zonas de servicio activas"
                    data-mapa-lienzo
                >
                    @foreach ($mapa['zonas'] as $zona)
                        <path
                            class="mapa__zona"
                            d="{{ $zona['trazo'] }}"
                            fill-rule="evenodd"
                            tabindex="0"
                            role="img"
                            aria-label="{{ $zona['nombre'] }}, {{ $zona['plaza'] }}"
                            data-zona="{{ $zona['nombre'] }}"
                        ><title>{{ $zona['nombre'] }}</title></path>
                    @endforeach
                </svg>

                <div class="mapa__controles">
                    <button class="mapa__boton" type="button" aria-label="Acercar" data-mapa-acercar>
                        <x-heroicon-o-plus class="boton__icono" aria-hidden="true" />
                    </button>
                    <button class="mapa__boton" type="button" aria-label="Alejar" data-mapa-alejar>
                        <x-heroicon-o-minus class="boton__icono" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <p class="mapa__nombre" aria-live="polite" data-mapa-nombre></p>
        @endif
    </div>
</section>
