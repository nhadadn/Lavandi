{{--
    Cómo pedir el borrado de la cuenta (NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3; adenda del 2026-11-24 de
    ADR-0005): la dirección web que pide Google Play además del borrado desde la app. Usa el diseño base de las páginas
    públicas, como los documentos legales, con el mismo encabezado; el título nombra la app con APP_NAME.

    - Desde la app: los pasos con las etiquetas de la pantalla "Mi perfil" (movil/src/perfil-usuario/textos.ts;
      perfil-usuario-E15). El borrado en la app es de la cuenta de usuario: la API solo lo da a ese rol.
    - Por correo o por WhatsApp: el correo de contacto y el WhatsApp de soporte de la configuración, los de "Contacto &
      Soporte" de la portada (App\Landing\MediosDeContacto). Sin uno, en producción no aparece y no se pone ninguno de
      ejemplo (textos-legales-E07); fuera de producción, un marcador de contenido de prueba en su lugar.

    Solo informa: ningún formulario, inicio de sesión ni borrado desde la web, y nada sobre qué datos se borran o se
    conservan, que es de EL CLIENTE (DD-109). No es el aviso de privacidad (RN-27).

    Escenarios: textos-legales-E06 y textos-legales-E07 · Spec: openspec/changes/textos-legales/specs/textos-legales/spec.md
    · Tarea 1.4
--}}
@extends('publico.layout')

@section('titulo', 'Eliminar tu cuenta · '.config('app.name'))

@php
    $hayContacto = $correo !== null || $whatsapp !== null;
    $conMarcadores = ! $enProduccion;
    $porCorreo = $correo !== null || $conMarcadores;
    $porWhatsApp = $whatsapp !== null || $conMarcadores;
    $tituloDelContacto = match (true) {
        $porCorreo && $porWhatsApp => 'Por correo o por WhatsApp',
        $porCorreo => 'Por correo',
        default => 'Por WhatsApp',
    };
@endphp

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
        <article class="contenedor documento-legal borrado-de-cuenta">
            <h1 class="documento-legal__titulo">Eliminar tu cuenta de {{ config('app.name') }}</h1>

            <section class="borrado-de-cuenta__seccion" data-pasos-de-la-app aria-labelledby="titulo-desde-la-app">
                <h2 class="borrado-de-cuenta__subtitulo" id="titulo-desde-la-app">Desde la app</h2>
                <p>Si usas la app para pedir tu servicio de lavandería, puedes eliminar tu cuenta desde ella:</p>
                <ol class="borrado-de-cuenta__pasos">
                    <li>Abre la app e inicia sesión.</li>
                    <li>En la pantalla principal, toca “Mi perfil”.</li>
                    <li>Al final de “Mi perfil”, toca “Eliminar mi cuenta”.</li>
                    <li>La app te pregunta “¿Eliminar tu cuenta?”. Toca “Eliminar” para confirmar.</li>
                </ol>
            </section>

            @if ($hayContacto || $conMarcadores)
                <section class="borrado-de-cuenta__seccion" data-pedir-por-contacto aria-labelledby="titulo-por-contacto">
                    <h2 class="borrado-de-cuenta__subtitulo" id="titulo-por-contacto">{{ $tituloDelContacto }}</h2>
                    <p>También puedes pedir que se elimine tu cuenta por estos medios:</p>
                    <ul class="contacto__medios">
                        @if ($correo !== null)
                            <li>
                                <a class="contacto__medio" href="{{ $correo['enlace'] }}" data-contacto="correo">
                                    <x-heroicon-o-envelope class="boton__icono" aria-hidden="true" />
                                    <span><span class="solo-lectores">Correo: </span>{{ $correo['correo'] }}</span>
                                </a>
                            </li>
                        @elseif ($conMarcadores)
                            <li><x-portada.de-prueba>correo de contacto.</x-portada.de-prueba></li>
                        @endif

                        @if ($whatsapp !== null)
                            <li>
                                <a class="contacto__medio" href="{{ $whatsapp }}" data-contacto="whatsapp">
                                    <x-heroicon-o-chat-bubble-left-right class="boton__icono" aria-hidden="true" />
                                    <span>WhatsApp</span>
                                </a>
                            </li>
                        @elseif ($conMarcadores)
                            <li><x-portada.de-prueba>número de WhatsApp de soporte.</x-portada.de-prueba></li>
                        @endif
                    </ul>
                </section>
            @endif
        </article>
    </main>
@endsection
