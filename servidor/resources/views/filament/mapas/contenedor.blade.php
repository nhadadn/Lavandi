{{--
    El contenedor de un mapa del panel, con la llave de Google Maps JavaScript (App\Filament\MapaDelPanel::llave()).
    Livewire no lo toca (wire:ignore): el mapa se crea una sola vez dentro de él y sobrevive al refresco y a los filtros,
    que cambian solo los datos (presupuesto-de-mapas.md). $etiqueta es su nombre para los lectores de pantalla.
--}}
<div
    wire:ignore
    data-mapa
    data-llave="{{ $llave }}"
    role="region"
    aria-label="{{ $etiqueta }}"
    style="height: 26rem; border-radius: 0.5rem; overflow: hidden; margin-bottom: 0.75rem;"
></div>
