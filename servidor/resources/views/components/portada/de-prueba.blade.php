{{--
    Un marcador de contenido de prueba, marcado como tal: el lugar de un texto o una imagen que entrega EL CLIENTE
    (NSV-2026-001-v8.pdf:6:Cláusula III, inciso B; DD-117). Mientras quede uno en la portada, A42-1 no se acredita
    (openspec/changes/landing/proposal.md).
--}}
@props(['como' => 'p'])
<{{ $como }} {{ $attributes->class('de-prueba') }} data-de-prueba>Contenido de prueba: {{ $slot }}</{{ $como }}>
