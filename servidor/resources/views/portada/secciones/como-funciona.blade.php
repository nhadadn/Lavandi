{{--
    Sección 2, "¿Cómo funciona?": paso a paso visual, Pide → Recogemos → Lavamos → Entregamos (NSV-2026-001-v8.pdf:15:
    ANEXO A, Sección A.2). Los nombres de los pasos son del contrato; el texto de cada uno, de EL CLIENTE (DD-117).

    Escenario: landing-E05 · Tarea 2.2
--}}
@php
    $pasos = [
        ['clave' => 'pide', 'titulo' => 'Pide', 'icono' => 'device-phone-mobile'],
        ['clave' => 'recogemos', 'titulo' => 'Recogemos', 'icono' => 'truck'],
        ['clave' => 'lavamos', 'titulo' => 'Lavamos', 'icono' => 'sparkles'],
        ['clave' => 'entregamos', 'titulo' => 'Entregamos', 'icono' => 'home'],
    ];
@endphp
<section class="seccion" id="como-funciona" data-seccion="como-funciona" aria-labelledby="titulo-como-funciona">
    <div class="contenedor">
        <h2 class="seccion__titulo" id="titulo-como-funciona">¿Cómo funciona?</h2>

        <ol class="rejilla rejilla--cuatro" data-pasos>
            @foreach ($pasos as $paso)
                <x-portada.pilar
                    atributo="data-paso"
                    :clave="$paso['clave']"
                    :titulo="$paso['titulo']"
                    :icono="$paso['icono']"
                    :texto="config('landing.como_funciona.'.$paso['clave'])"
                >
                    <span class="paso__numero" aria-hidden="true">{{ $loop->iteration }}</span>
                </x-portada.pilar>
            @endforeach
        </ol>
    </div>
</section>
