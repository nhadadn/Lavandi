{{--
    El aviso de un mapa del panel sin la llave de Google Maps JavaScript (App\Filament\MapaDelPanel::llave()): el mapa
    no se monta, y $respaldo dice dónde se ve lo mismo sin el mapa.
--}}
<p role="status" style="padding: 1rem; border: 1px dashed var(--gray-300); border-radius: 0.5rem; margin-bottom: 1rem;">
    El mapa no está disponible porque falta la llave de Google Maps del panel. {{ $respaldo }}
</p>
