{{--
    Sección 3, "Beneficios para usuarios": tres pilares, comodidad, precio y confianza, con iconos animados
    (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2). Los títulos son del contrato; el texto de cada pilar, de EL CLIENTE
    (DD-117). El de precio no publica cifras mientras DD-010 siga abierta. Con movimiento reducido, los iconos se quedan
    quietos (componentes.md).

    Escenarios: landing-E06 y landing-E07 · Tarea 2.2
--}}
@php
    $pilares = [
        ['clave' => 'comodidad', 'titulo' => 'Comodidad', 'icono' => 'clock'],
        ['clave' => 'precio', 'titulo' => 'Precio', 'icono' => 'banknotes'],
        ['clave' => 'confianza', 'titulo' => 'Confianza', 'icono' => 'shield-check'],
    ];
@endphp
<section class="seccion seccion--superficie" id="beneficios-usuarios" data-seccion="beneficios-usuarios" aria-labelledby="titulo-beneficios-usuarios">
    <div class="contenedor">
        <h2 class="seccion__titulo" id="titulo-beneficios-usuarios">Beneficios para usuarios</h2>

        <ul class="rejilla">
            @foreach ($pilares as $pilar)
                <x-portada.pilar
                    animado
                    :clave="$pilar['clave']"
                    :titulo="$pilar['titulo']"
                    :icono="$pilar['icono']"
                    :texto="config('landing.beneficios_usuarios.'.$pilar['clave'])"
                />
            @endforeach
        </ul>
    </div>
</section>
