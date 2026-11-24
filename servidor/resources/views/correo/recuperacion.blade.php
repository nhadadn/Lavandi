{{-- El correo de recuperación de la contraseña (registro-E22). El nombre del producto sale de la configuración. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crea tu contraseña nueva de {{ $producto }}</title>
</head>
<body>
    <p>Hola:</p>
    <p>Recibimos una petición para crear una contraseña nueva para tu cuenta de {{ $producto }}.</p>
    <p><a href="{{ $enlace }}">Crear mi contraseña nueva</a></p>
    <p>El enlace vence en {{ $minutos }} minutos y sirve una sola vez.</p>
    <p>Si no pediste el cambio, ignora este correo: tu contraseña sigue igual.</p>
    <p>Si el enlace no abre, copia esta dirección en tu navegador:<br>{{ $enlace }}</p>
</body>
</html>
