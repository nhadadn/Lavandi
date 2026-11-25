{{--
    Sección 5, "Lavanderías partner": invitación a lavanderías a unirse y formulario de interés (NSV-2026-001-v8.pdf:15:
    ANEXO A, Sección A.2). El texto de la invitación es de EL CLIENTE (DD-117). Qué campos lleva el formulario, a dónde
    llega y qué aviso de datos personales lo acompaña es DD-119: mientras tanto pide lo mínimo para responder
    (design.md, lectura 5), y el aviso es un marcador de contenido de prueba.

    Escenarios: landing-E10 y landing-E21 · Tareas 2.4 y 5.1
--}}
@php
    $invitacion = config('landing.lavanderias.invitacion');
@endphp
<section class="seccion seccion--superficie" id="lavanderias" data-seccion="lavanderias" aria-labelledby="titulo-lavanderias">
    <div class="contenedor">
        <h2 class="seccion__titulo" id="titulo-lavanderias">Lavanderías partner</h2>

        @if (filled($invitacion))
            <p class="seccion__entrada" data-invitacion>{{ $invitacion }}</p>
        @else
            <x-portada.de-prueba class="seccion__entrada" data-invitacion-de-prueba>invitación a las lavanderías a unirse.</x-portada.de-prueba>
        @endif

        <form class="formulario" method="post" action="{{ route('portada.interes') }}" data-formulario="interes" novalidate>
            @csrf
            <h3 class="formulario__titulo">Formulario de interés</h3>
            <x-portada.envio-sin-destino formulario="interes" />

            <x-portada.campo formulario="interes" nombre="nombre" etiqueta="Tu nombre" autocompletar="name" />
            <x-portada.campo formulario="interes" nombre="lavanderia" etiqueta="Nombre de la lavandería" autocompletar="organization" />
            <x-portada.campo formulario="interes" nombre="correo" etiqueta="Correo electrónico" tipo="email" autocompletar="email" />

            <x-portada.de-prueba>aviso de datos personales del formulario.</x-portada.de-prueba>

            <button class="boton boton--principal" type="submit">Enviar</button>
        </form>
    </div>
</section>
