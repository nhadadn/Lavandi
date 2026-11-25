{{--
    Sección 11, "Contacto & Soporte": correo, WhatsApp Business y formulario de contacto (NSV-2026-001-v8.pdf:15:ANEXO
    A, Sección A.2). El correo y el número salen de la configuración (App\Landing\MediosDeContacto); sin ellos, la
    sección muestra sus marcadores y ningún enlace vacío. El número es DD-082 y el correo, DD-117. A dónde llega el
    formulario es DD-119.

    Escenarios: landing-E21 y landing-E23 · Tareas 5.1 y 5.3
--}}
@php
    $medios = app(\App\Landing\MediosDeContacto::class);
    $correo = $medios->correo();
    $whatsapp = $medios->whatsapp();
@endphp
<section class="seccion seccion--superficie" id="contacto" data-seccion="contacto" aria-labelledby="titulo-contacto">
    <div class="contenedor">
        <h2 class="seccion__titulo" id="titulo-contacto">Contacto &amp; Soporte</h2>

        <div class="contacto__rejilla">
            <ul class="contacto__medios">
                <li>
                    @if ($correo !== null)
                        <a class="contacto__medio" href="{{ $correo['enlace'] }}" data-contacto="correo">
                            <x-heroicon-o-envelope class="boton__icono" aria-hidden="true" />
                            <span><span class="solo-lectores">Correo: </span>{{ $correo['correo'] }}</span>
                        </a>
                    @else
                        <x-portada.de-prueba data-contacto-de-prueba>correo de contacto.</x-portada.de-prueba>
                    @endif
                </li>
                <li>
                    @if ($whatsapp !== null)
                        <a class="contacto__medio" href="{{ $whatsapp }}" data-contacto="whatsapp">
                            <x-heroicon-o-chat-bubble-left-right class="boton__icono" aria-hidden="true" />
                            <span>WhatsApp Business</span>
                        </a>
                    @else
                        <x-portada.de-prueba data-contacto-de-prueba>número de WhatsApp Business.</x-portada.de-prueba>
                    @endif
                </li>
            </ul>

            <form class="formulario" method="post" action="{{ route('portada.contacto') }}" data-formulario="contacto" novalidate>
                @csrf
                <h3 class="formulario__titulo">Formulario de contacto</h3>
                <x-portada.envio-sin-destino formulario="contacto" />

                <x-portada.campo formulario="contacto" nombre="nombre" etiqueta="Tu nombre" autocompletar="name" />
                <x-portada.campo formulario="contacto" nombre="correo" etiqueta="Correo electrónico" tipo="email" autocompletar="email" />
                <x-portada.campo formulario="contacto" nombre="mensaje" etiqueta="Mensaje" multilinea />

                <x-portada.de-prueba>aviso de datos personales del formulario.</x-portada.de-prueba>

                <button class="boton boton--principal" type="submit">Enviar</button>
            </form>
        </div>
    </div>
</section>
