{{--
    Sección 9, "Preguntas frecuentes": acordeón expandible con 6 a 8 preguntas (NSV-2026-001-v8.pdf:15:ANEXO A, Sección
    A.2). Cada respuesta empieza plegada y se despliega al tocar su pregunta, también con el teclado: es <details> con
    <summary>, que el navegador hace accesible sin JavaScript. Preguntas y respuestas son de EL CLIENTE (DD-117).

    Escenario: landing-E20 · Tarea 4.3
--}}
@php
    $preguntas = app(\App\Landing\PreguntasFrecuentes::class)->todas();
@endphp
<section class="seccion seccion--superficie" id="preguntas-frecuentes" data-seccion="preguntas-frecuentes" aria-labelledby="titulo-preguntas-frecuentes">
    <div class="contenedor">
        <h2 class="seccion__titulo" id="titulo-preguntas-frecuentes">Preguntas frecuentes</h2>

        @if ($preguntas === [])
            <x-portada.de-prueba>de 6 a 8 preguntas frecuentes con su respuesta.</x-portada.de-prueba>
        @else
            <div class="acordeon">
                @foreach ($preguntas as $pregunta)
                    <details class="acordeon__pregunta" data-pregunta>
                        <summary class="acordeon__titulo">{{ $pregunta['pregunta'] }}</summary>
                        <p class="acordeon__respuesta" data-respuesta>{{ $pregunta['respuesta'] }}</p>
                    </details>
                @endforeach
            </div>
        @endif
    </div>
</section>
