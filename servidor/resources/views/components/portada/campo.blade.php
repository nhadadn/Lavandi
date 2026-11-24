{{--
    Un campo de un formulario de la portada, con su etiqueta, obligatorio, y su error junto a él, dicho con texto
    (componentes.md: el color nunca solo). El nombre va dentro del formulario, formulario[campo], para que los dos
    formularios de la portada no se confundan sus valores ni sus errores (landing-E21).
--}}
@props(['formulario', 'nombre', 'etiqueta', 'tipo' => 'text', 'autocompletar' => null, 'multilinea' => false])
@php
    $clave = $formulario.'.'.$nombre;
    $id = 'campo-'.$formulario.'-'.$nombre;
    $error = $errors->first($clave);
@endphp
<div class="campo">
    <label class="campo__etiqueta" for="{{ $id }}">{{ $etiqueta }}</label>
    @if ($multilinea)
        <textarea
            class="campo__control"
            id="{{ $id }}"
            name="{{ $formulario }}[{{ $nombre }}]"
            rows="4"
            required
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        >{{ old($clave) }}</textarea>
    @else
        <input
            class="campo__control"
            id="{{ $id }}"
            name="{{ $formulario }}[{{ $nombre }}]"
            type="{{ $tipo }}"
            value="{{ old($clave) }}"
            @if ($autocompletar) autocomplete="{{ $autocompletar }}" @endif
            required
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        >
    @endif
    @if ($error)
        <p class="campo__error" id="{{ $id }}-error" data-error-de="{{ $nombre }}">{{ $error }}</p>
    @endif
</div>
