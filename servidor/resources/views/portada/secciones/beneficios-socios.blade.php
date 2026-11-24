{{--
    Sección 6, "Beneficios para socios": tres pilares, más clientes, pagos seguros y cero gestión de cobranza
    (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2). Los títulos son del contrato; el texto de cada pilar, de EL CLIENTE
    (DD-117). El de "cero gestión de cobranza" promete que la plataforma cobra por el socio, y el modelo de liquidación
    no está decidido: no se escribe hasta que DD-013 cierre.

    Escenario: landing-E11 · Tarea 2.4
--}}
@php
    $pilares = [
        ['clave' => 'mas_clientes', 'titulo' => 'Más clientes', 'icono' => 'user-group'],
        ['clave' => 'pagos_seguros', 'titulo' => 'Pagos seguros', 'icono' => 'lock-closed'],
        ['clave' => 'cero_gestion_de_cobranza', 'titulo' => 'Cero gestión de cobranza', 'icono' => 'document-check'],
    ];
@endphp
<section class="seccion" id="beneficios-socios" data-seccion="beneficios-socios" aria-labelledby="titulo-beneficios-socios">
    <div class="contenedor">
        <h2 class="seccion__titulo" id="titulo-beneficios-socios">Beneficios para socios</h2>

        <ul class="rejilla">
            @foreach ($pilares as $pilar)
                <x-portada.pilar
                    :clave="$pilar['clave']"
                    :titulo="$pilar['titulo']"
                    :icono="$pilar['icono']"
                    :texto="config('landing.beneficios_socios.'.$pilar['clave'])"
                />
            @endforeach
        </ul>
    </div>
</section>
