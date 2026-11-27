{{--
    El diseño base de las páginas públicas del sitio (ADR-0011): la portada de la landing y las de textos-legales, el
    aviso de privacidad, los términos y la página para pedir el borrado de la cuenta. Toda página que lo usa lleva el pie
    legal al final (landing-E24 y landing-E25).

    En la cabecera, lo de medicion-de-la-landing: el contenedor de etiquetas (publico.medicion; medicion-E01), y el título
    y la descripción para buscadores de la página (medicion-E07). Cada página dice cuál es con $pagina, que le pasa su
    controlador; la descripción la da App\Medicion\DescripcionParaBuscadores. Lo propio de una página en la cabecera,
    como la información estructurada de la portada (medicion-E10), entra con @push('cabecera').

    El nombre del producto sale de la configuración (APP_NAME), nunca de la vista (CLAUDE.md §5). Los estilos usan las
    variables de resources/css/tokens.css, que genera el adaptador de tokens (ADR-0013); solo modo claro (C-141).
--}}
@php
    $descripcion = app(\App\Medicion\DescripcionParaBuscadores::class)->de($pagina ?? null);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', config('app.name'))</title>
    @if ($descripcion !== null)
        <meta name="description" content="{{ $descripcion }}">
    @endif
    @include('publico.medicion')
    @vite(['resources/css/portada.css', 'resources/js/portada.js'])
    @stack('cabecera')
</head>
<body class="publico">
    <a class="saltar-al-contenido" href="#contenido">Ir al contenido</a>
    @yield('contenido')

    @include('publico.pie-legal')
</body>
</html>
