{{--
    Sección 12, "Footer legal" (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2): el pie de toda página pública, la portada y
    las de textos-legales. Lo pone el diseño base, publico.layout.

    - Los enlaces al aviso de privacidad y a los términos van a las direcciones que publica textos-legales, por el nombre
      de su ruta (landing-E24). El texto de cada enlace es el título del documento (config/textos_legales.php).
    - El aviso de derechos de autor lleva el titular y el año de la configuración, landing.derechos (landing-E25). Quién
      aparece como titular es DD-120: mientras falte el titular o el año, fuera de producción va un marcador de contenido
      de prueba; en producción no va ninguno, para que las páginas de los documentos no muestren texto de prueba
      (textos-legales-E02).

    Escenarios: landing-E24 y landing-E25 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 6.1
--}}
@php
    $titular = trim((string) config('landing.derechos.titular'));
    $anio = trim((string) config('landing.derechos.anio'));
@endphp
<footer class="pie-legal" id="pie-legal" data-seccion="pie-legal">
    <div class="contenedor pie-legal__rejilla">
        <nav aria-label="Documentos legales">
            <ul class="pie-legal__enlaces">
                @foreach (['aviso-de-privacidad', 'terminos'] as $documento)
                    <li>
                        <a href="{{ route('textos-legales.'.$documento) }}">{{ config('textos_legales.documentos.'.$documento.'.titulo') }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @if ($titular !== '' && $anio !== '')
            <p class="pie-legal__derechos" data-derechos>© {{ $anio }} {{ $titular }}. Todos los derechos reservados.</p>
        @elseif (! app()->isProduction())
            <x-portada.de-prueba class="pie-legal__derechos">titular y año del aviso de derechos de autor.</x-portada.de-prueba>
        @endif
    </div>
</footer>
