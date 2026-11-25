{{--
    El diseño de las páginas de desarrollo del servidor: mínimo, sin dependencias y sin la marca. El nombre del producto
    sale de la configuración. No se indexan.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('titulo') · {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; line-height: 1.5; margin: 0; padding: 16px; }
        main { max-width: 28rem; margin: 0 auto; }
        .aviso { padding: 8px; border: 1px dashed currentColor; }
        .error { color: #b00020; }
        dt { font-weight: bold; }
        dd { margin: 0 0 8px; overflow-wrap: anywhere; }
        button { display: block; width: 100%; box-sizing: border-box; font: inherit; padding: 12px; margin: 8px 0; }
    </style>
</head>
<body>
    <main>
        <h1>@yield('titulo')</h1>
        @yield('contenido')
    </main>
</body>
</html>
