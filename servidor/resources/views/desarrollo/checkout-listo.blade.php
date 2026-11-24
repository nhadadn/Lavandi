{{--
    Lo que muestra la página de checkout de desarrollo después de un botón, cuando no hay dirección de regreso a la app
    en la configuración (PASARELA_REGRESO).
--}}
@extends('desarrollo.pagina')

@section('titulo', $aprobado ? 'Pago aprobado' : 'Pago rechazado')

@section('contenido')
    <p>La pasarela simulada mandó su aviso. Regresa a la app: ella consulta el cobro.</p>
@endsection
