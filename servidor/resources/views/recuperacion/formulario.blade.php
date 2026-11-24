{{-- El formulario de la contraseña nueva, al que lleva el enlace del correo (registro-E24). --}}
@extends('recuperacion.pagina')

@section('titulo', 'Crea tu contraseña nueva')

@section('contenido')
    @if ($errors->any())
        <div role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('contrasena.restablecer') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="correo" value="{{ $correo }}">

        <label for="password">Contraseña nueva</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">

        <label for="password_confirmation">Escríbela otra vez</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">

        <button type="submit">Guardar mi contraseña nueva</button>
    </form>
@endsection
