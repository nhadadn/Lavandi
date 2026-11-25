{{--
    Sección 8, "Testimonios": 3 a 5 tarjetas de opiniones de usuarios beta, con marcador de posición en el lanzamiento
    (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2). Los testimonios se editan en el panel (ADR-0011); qué tarjetas se
    muestran lo decide App\Landing\TestimoniosDeLaPortada. El texto del marcador lo decide EL CLIENTE (DD-117).

    Escenarios: landing-E15 y landing-E16 · Tarea 4.1
--}}
@php
    $tarjetas = app(\App\Landing\TestimoniosDeLaPortada::class)->tarjetas();
    $marcador = config('landing.testimonios.marcador');
@endphp
<section class="seccion" id="testimonios" data-seccion="testimonios" aria-labelledby="titulo-testimonios">
    <div class="contenedor">
        <h2 class="seccion__titulo" id="titulo-testimonios">Testimonios</h2>

        <ul class="rejilla">
            @foreach ($tarjetas as $testimonio)
                @if ($testimonio !== null)
                    <li class="tarjeta" data-testimonio>
                        <figure class="testimonio">
                            <blockquote class="testimonio__texto" data-texto>{{ $testimonio->texto }}</blockquote>
                            <figcaption class="testimonio__autor" data-autor>{{ $testimonio->autor }}</figcaption>
                        </figure>
                    </li>
                @else
                    <li class="tarjeta" data-testimonio data-marcador>
                        @if (filled($marcador))
                            <p class="tarjeta__texto">{{ $marcador }}</p>
                        @else
                            <x-portada.de-prueba>marcador de posición de un testimonio.</x-portada.de-prueba>
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
</section>
