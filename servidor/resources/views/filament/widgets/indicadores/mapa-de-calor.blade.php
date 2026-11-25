{{--
    El mapa de calor de los pedidos del rango (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría Geográfico;
    indicadores-E19 e indicadores-E20). Lo arma App\Filament\Widgets\Indicadores\MapaDeCalor con los agregados que
    calcula el servidor; aquí solo se dibujan.

    - Con la llave de Google Maps JavaScript del panel (App\Filament\MapaDelPanel; ADR-0009, decisión 3), el mapa se
      monta una sola vez en el contenedor que Livewire no toca (filament.mapas.contenedor, con wire:ignore), con la API
      que carga filament.mapas.cargador. Los datos van en el atributo data-mapa-datos, que sí se actualiza con el
      refresco y los filtros; el script vuelve a dibujar las figuras sobre el mismo mapa, sin crear otro, para no gastar
      cargas de mapa (presupuesto-de-mapas.md).
    - Sin la llave, el mapa no se monta y el aviso dice por qué (filament.mapas.sin-llave).
    - La tabla trae las mismas cifras, para leerlas sin el mapa y sin depender del color; cada figura del mapa dice su
      nombre y su número de pedidos al elegirla.
--}}
<x-filament-widgets::widget
    :attributes="
        (new \Filament\Support\View\ComponentAttributeBag)
            ->merge(['wire:poll.' . $intervalo => $intervalo ? true : null], escape: false)
            ->class(['fi-wi-mapa-de-calor'])
    "
>
    <x-filament::section heading="Mapa de calor de pedidos" :description="$descripcion">
        @isset($agrupaciones)
            <div style="max-width: 20rem; margin-bottom: 1rem;">
                <label for="mapa-de-calor-agrupacion" style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Ver por</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="mapa-de-calor-agrupacion" wire:model.live="agrupacion">
                        @foreach ($agrupaciones as $valor => $nombre)
                            <option value="{{ $valor }}">{{ $nombre }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        @endisset

        <div hidden data-mapa-datos="{{ json_encode($datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) }}"></div>

        @if ($llave === null)
            @include('filament.mapas.sin-llave', ['respaldo' => 'Las cifras están en la tabla.'])
        @else
            @include('filament.mapas.contenedor', ['llave' => $llave, 'etiqueta' => 'Mapa de calor de pedidos'])

            <div aria-hidden="true" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; margin-bottom: 1rem;">
                <span>Menos pedidos</span>
                @foreach ($rampa as $color)
                    <span style="display: inline-block; width: 1.5rem; height: 0.75rem; border-radius: 2px; background: {{ $color }};"></span>
                @endforeach
                <span>Más pedidos</span>
            </div>
        @endif

        @if ($filas === [])
            <p>No hay pedidos en el rango.</p>
        @else
            <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr>
                        @foreach ($columnas as $columna)
                            <th scope="col" style="text-align: start; padding: 0.5rem; border-bottom: 1px solid var(--gray-200);">{{ $columna }}</th>
                        @endforeach
                        <th scope="col" style="text-align: end; padding: 0.5rem; border-bottom: 1px solid var(--gray-200);">Pedidos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $fila)
                        <tr>
                            @foreach ($fila['textos'] as $texto)
                                <td style="padding: 0.5rem; border-bottom: 1px solid var(--gray-100);">{{ $texto }}</td>
                            @endforeach
                            <td style="padding: 0.5rem; border-bottom: 1px solid var(--gray-100); text-align: end; font-variant-numeric: tabular-nums;">{{ $fila['pedidos'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>

@if ($llave !== null)
@script
<script>
    @include('filament.mapas.cargador')

    const raiz = $wire.$el;
    const contenedor = raiz.querySelector('[data-mapa]');

    if (contenedor) {
        window.cargarLosMapasDelPanel(contenedor.dataset.llave).then((maps) => {
            const mapa = new maps.Map(contenedor, {
                center: { lat: 0, lng: 0 },
                zoom: 2,
                mapTypeControl: false,
                streetViewControl: false,
            });
            const ventana = new maps.InfoWindow();
            let figuras = [];
            let dibujados = null;

            // El nombre, el detalle y el número de pedidos de una figura, como texto: nunca como HTML.
            const rotulo = (figura) => {
                const caja = document.createElement('div');
                const nombre = document.createElement('strong');
                const detalle = document.createElement('div');
                const cifra = document.createElement('div');
                nombre.textContent = figura.nombre;
                detalle.textContent = figura.detalle;
                cifra.textContent = figura.pedidos === 1 ? '1 pedido' : figura.pedidos + ' pedidos';
                caja.append(nombre, detalle, cifra);

                return caja;
            };

            const abrir = (figura, posicion) => {
                ventana.setContent(rotulo(figura));
                ventana.setPosition(posicion);
                ventana.open({ map: mapa });
            };

            const dibujar = () => {
                const fuente = raiz.querySelector('[data-mapa-datos]');
                const texto = fuente ? fuente.dataset.mapaDatos : '{}';

                if (texto === dibujados) {
                    return;
                }

                dibujados = texto;
                figuras.forEach((figura) => figura.setMap(null));
                figuras = [];
                ventana.close();

                const datos = JSON.parse(texto || '{}');
                const limites = new maps.LatLngBounds();

                for (const punto of datos.puntos ?? []) {
                    const centro = { lat: punto.latitud, lng: punto.longitud };
                    const circulo = new maps.Circle({
                        map: mapa,
                        center: centro,
                        radius: 150 + 650 * punto.intensidad,
                        fillColor: punto.color,
                        fillOpacity: 0.8,
                        strokeColor: '#ffffff',
                        strokeWeight: 2,
                    });
                    circulo.addListener('click', () => abrir(punto, centro));
                    figuras.push(circulo);
                    limites.extend(centro);
                }

                for (const zona of datos.zonas ?? []) {
                    const capa = new maps.Data({ map: mapa });
                    capa.addGeoJson({ type: 'Feature', geometry: zona.geometria, properties: {} });
                    capa.setStyle({ fillColor: zona.color, fillOpacity: 0.65, strokeColor: '#ffffff', strokeWeight: 2 });
                    capa.addListener('click', (evento) => abrir(zona, evento.latLng));
                    capa.forEach((rasgo) => rasgo.getGeometry().forEachLatLng((punto) => limites.extend(punto)));
                    figuras.push(capa);
                }

                if (! limites.isEmpty()) {
                    mapa.fitBounds(limites);
                }
            };

            dibujar();

            // El refresco y los filtros cambian los datos: se vuelven a dibujar sobre el mismo mapa.
            new MutationObserver(dibujar).observe(raiz, {
                subtree: true,
                childList: true,
                attributes: true,
                attributeFilter: ['data-mapa-datos'],
            });
        }).catch(() => {
            contenedor.textContent = 'No se pudo cargar el mapa de Google. Las cifras están en la tabla.';
        });
    }
</script>
@endscript
@endif
