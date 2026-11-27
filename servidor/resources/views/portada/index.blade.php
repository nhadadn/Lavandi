{{--
    La portada de la landing: las 12 secciones del A.2, en su orden (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2). La 12,
    el pie legal, la pone el diseño base en toda página pública (publico.pie-legal; landing-E24).
    Cada una se marca con data-seccion; los títulos son los nombres que les da el contrato. Todo texto de EL CLIENTE
    sale de config/landing.php o del panel, nunca de la vista (DD-117).

    Escenarios: landing-E01 y landing-E02 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 1.1
--}}
@extends('publico.layout')

{{-- La información estructurada del negocio y su zona de servicio, solo en la portada (medicion-E10). --}}
@push('cabecera')
    <script type="application/ld+json">{!! app(\App\Medicion\InformacionEstructurada::class)->json() !!}</script>
@endpush

@section('contenido')
    @include('portada.secciones.encabezado')

    <main id="contenido" tabindex="-1">
        @include('portada.secciones.como-funciona')
        @include('portada.secciones.beneficios-usuarios')
        @include('portada.secciones.descarga')
        @include('portada.secciones.lavanderias')
        @include('portada.secciones.beneficios-socios')
        @include('portada.secciones.cobertura')
        @include('portada.secciones.testimonios')
        @include('portada.secciones.preguntas-frecuentes')
        @include('portada.secciones.prensa')
        @include('portada.secciones.contacto')
    </main>
@endsection
