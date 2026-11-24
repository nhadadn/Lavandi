{{-- El enlace venció, ya se usó o no es de esa cuenta: no se cambió nada (registro-E25). --}}
@extends('recuperacion.pagina')

@section('titulo', 'El enlace ya no sirve')

@section('contenido')
    <p>El enlace para crear tu contraseña nueva venció o ya se usó. Tu contraseña no cambió.</p>
    <p>Pide otro enlace desde la app, en «¿Olvidaste tu contraseña?».</p>
@endsection
