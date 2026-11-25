{{--
    La página web de la recuperación de la contraseña (ADR-0018, decisión 4). Es mínima a propósito: sin la marca
    escrita, sin dependencias y sin CSS de marca, que llega con los tokens de la landing. El nombre del producto sale
    de la configuración.
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
        label, input, button { display: block; width: 100%; box-sizing: border-box; }
        input, button { font: inherit; padding: 8px; margin: 4px 0 16px; }
    </style>
</head>
<body>
    <main>
        <h1>@yield('titulo')</h1>
        @yield('contenido')
    </main>
</body>
</html>
