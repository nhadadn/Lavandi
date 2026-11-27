{{-- El correo de confirmación del pedido (confirmacion-E10). El nombre del producto sale de la configuración. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $producto }}: tu pedido {{ $folio }} está confirmado</title>
</head>
<body>
    <p>Hola:</p>
    <p>Tu pedido de {{ $producto }} está confirmado.</p>
    <p>Folio: <strong>{{ $folio }}</strong></p>
    <ul>
        <li>Lavandería: {{ $lavanderia }}</li>
        <li>Servicio: {{ $servicios }}</li>
        <li>Recolección: {{ $fecha }}, de {{ $ventana }}</li>
        <li>Recogida: {{ $recogida }}</li>
        <li>Entrega: {{ $entrega }}</li>
    </ul>
</body>
</html>
