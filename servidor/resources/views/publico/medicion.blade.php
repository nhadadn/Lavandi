{{--
    El cargador del contenedor de Google Tag Manager, por el que entran Google Analytics 4 y Meta Pixel
    (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2; ADR-0011). Lo pone el diseño base de las páginas públicas en su cabecera,
    una sola vez por página. El identificador sale de la configuración del entorno (config/medicion.php); sin él, no sale
    nada y la página no carga ningún código de medición de terceros (medicion-E02).

    Los eventos van a la capa de datos del contenedor (dataLayer), con el plan de eventos de
    openspec/changes/medicion-de-la-landing/design.md; las etiquetas de GA4 y de Meta Pixel los toman de ahí:
    - gtm.js, al abrir la página: la visita (medicion-E03);
    - descarga, con la tienda: el toque en un botón de descarga, que pone resources/js/portada.js (medicion-E04);
    - formulario_enviado, con el formulario: el envío completo de uno, que pone esta vista en la portada de vuelta
      (medicion-E05). Solo lleva el nombre del formulario, nunca lo que la persona escribió.

    Escenarios: medicion-E01 a medicion-E05 y medicion-E11 · Spec: openspec/changes/medicion-de-la-landing/specs/medicion/
    spec.md · Tareas 1.1, 1.2 y 3.1
--}}
@php
    $contenedor = app(\App\Medicion\ContenedorDeEtiquetas::class)->identificador();
    $formulario = session('formulario_enviado');
@endphp
@if ($contenedor !== null)
    <script data-medicion>
        (function (ventana, documento, contenedor) {
            ventana.dataLayer = ventana.dataLayer || [];
            ventana.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
            @if (is_string($formulario) && $formulario !== '')
                ventana.dataLayer.push(@json(['event' => 'formulario_enviado', 'formulario' => $formulario]));
            @endif

            // En diferido: el contenedor se pide cuando la página ya terminó de cargar, para que la medición no retrase lo
            // que la persona ve en el teléfono (medicion-E11; ADR-0011). Los eventos de antes esperan en la capa de datos.
            function cargar() {
                var script = documento.createElement('script');
                script.async = true;
                script.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(contenedor);
                documento.head.appendChild(script);
            }

            if (documento.readyState === 'complete') {
                cargar();
            } else {
                ventana.addEventListener('load', cargar, { once: true });
            }
        })(window, document, @js($contenedor));
    </script>
@endif
