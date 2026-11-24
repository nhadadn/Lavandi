{{--
    La página pública de un documento legal de EL CLIENTE: el aviso de privacidad o los términos y condiciones
    (NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7). Usa el diseño base de las páginas públicas, como la portada.

    El texto vigente va como texto, tal como se cargó: escapado y con sus saltos de línea y sus espacios
    (white-space: pre-wrap), sin interpretar ninguna marca (textos-legales-E01). Sin el documento, en producción la página
    dice que todavía no está disponible y no lleva ningún texto de ejemplo (textos-legales-E02); fuera de producción, un
    marcador de contenido de prueba, como la portada.

    Escenarios: textos-legales-E01 y textos-legales-E02 · Spec: openspec/changes/textos-legales/specs/textos-legales/spec.md
    · Tarea 1.1
--}}
@extends('publico.layout')

@section('titulo', $titulo.' · '.config('app.name'))

@section('contenido')
    <header class="encabezado-legal">
        <div class="contenedor">
            <a class="encabezado-legal__marca" href="{{ route('portada') }}">
                <img class="encabezado-legal__logo" src="{{ asset(config('landing.logo')) }}" alt="" width="40" height="40">
                <span>{{ config('app.name') }}</span>
            </a>
        </div>
    </header>

    <main id="contenido" tabindex="-1" class="seccion">
        <article class="contenedor documento-legal">
            <h1 class="documento-legal__titulo">{{ $titulo }}</h1>

            @if ($texto !== null)
                <div class="documento-legal__texto" data-texto-legal>{{ $texto }}</div>
            @elseif ($enProduccion)
                <p class="documento-legal__no-disponible" data-texto-no-disponible>Este documento todavía no está disponible.</p>
            @else
                <x-portada.de-prueba>el texto de este documento lo entrega el cliente y todavía no está cargado.</x-portada.de-prueba>
            @endif
        </article>
    </main>
@endsection
