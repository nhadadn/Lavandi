{{--
    El mapa del monitoreo de pedidos (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #4; administracion-E09 y
    administracion-E10). Lo arma App\Filament\Pages\MonitoreoDePedidos con los marcadores de
    App\Pedidos\MarcadoresDelMonitoreo; aquí solo se dibujan.

    - Con la llave de Google Maps JavaScript del panel (App\Filament\MapaDelPanel; ADR-0009, decisión 3), el mapa se
      crea una sola vez en el contenedor que Livewire no toca (filament.mapas.contenedor, con wire:ignore), con la API
      que carga filament.mapas.cargador, como el mapa de calor de los indicadores.
    - Los marcadores van en el atributo data-mapa-datos, que se actualiza con el refresco de 30 segundos de la tabla
      (administracion-E07). El script los vuelve a poner sobre el mismo mapa, sin crear otro (presupuesto-de-mapas.md).
      El mapa se encuadra la primera vez que hay pedidos; después, el refresco no mueve lo que la administración ve, y
      si tenía abierto un marcador que sigue en el mapa, lo deja abierto con sus datos nuevos.
    - Cada marcador lleva su letra, R en la recolección y E en la entrega en otra dirección, y al elegirlo dice el
      folio, el estado, si es la recolección o la entrega y la plaza, como texto: nunca como HTML.
    - Sin la llave, el mapa no se monta y el aviso dice por qué (filament.mapas.sin-llave); los pedidos se ven en la
      tabla.
--}}
<x-filament::section
    heading="Mapa de los pedidos activos"
    description="Cada pedido activo en su dirección de recolección y, si se entrega en otra dirección, también en la de entrega. Elige un marcador para ver su folio y su estado."
>
    <div hidden data-mapa-datos="{{ json_encode($datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) }}"></div>

    @if ($llave === null)
        @include('filament.mapas.sin-llave', ['respaldo' => 'Los pedidos están en la tabla.'])
    @else
        @include('filament.mapas.contenedor', ['llave' => $llave, 'etiqueta' => 'Mapa de los pedidos activos'])

        <p style="font-size: 0.75rem;">
            R: recolección (y la entrega, si es en la misma dirección) · E: entrega en otra dirección
        </p>
    @endif
</x-filament::section>

@if ($llave !== null)
@script
<script>
    @include('filament.mapas.cargador')

    const raiz = $wire.$el;
    const contenedor = raiz.querySelector('[data-mapa]');

    if (contenedor) {
        window.cargarLosMapasDelPanel(contenedor.dataset.llave).then((maps) => {
            const LETRAS = { recoleccion: 'R', entrega: 'E' };
            const mapa = new maps.Map(contenedor, {
                center: { lat: 0, lng: 0 },
                zoom: 2,
                mapTypeControl: false,
                streetViewControl: false,
            });
            const ventana = new maps.InfoWindow();
            let marcadores = new Map();
            let abierto = null;
            let dibujados = null;
            let encuadrado = false;

            ventana.addListener('closeclick', () => { abierto = null; });

            const clave = (marcador) => marcador.pedido + ':' + marcador.tipo;

            // El folio, el estado, si es la recolección o la entrega, y la plaza, como texto: nunca como HTML.
            const rotulo = (marcador) => {
                const caja = document.createElement('div');
                const folio = document.createElement('strong');
                const estado = document.createElement('div');
                const lugar = document.createElement('div');
                folio.textContent = marcador.folio;
                estado.textContent = marcador.estado;
                lugar.textContent = marcador.etiqueta + ' · ' + marcador.plaza;
                caja.append(folio, estado, lugar);

                return caja;
            };

            const abrir = (marcador, pin) => {
                abierto = clave(marcador);
                ventana.setContent(rotulo(marcador));
                ventana.open({ map: mapa, anchor: pin });
            };

            const dibujar = () => {
                const fuente = raiz.querySelector('[data-mapa-datos]');
                const texto = fuente ? fuente.dataset.mapaDatos : '{}';

                if (texto === dibujados) {
                    return;
                }

                dibujados = texto;
                marcadores.forEach((pin) => pin.setMap(null));
                marcadores = new Map();

                const datos = JSON.parse(texto || '{}');
                const limites = new maps.LatLngBounds();

                for (const marcador of datos.marcadores ?? []) {
                    const posicion = { lat: marcador.latitud, lng: marcador.longitud };
                    const pin = new maps.Marker({
                        map: mapa,
                        position: posicion,
                        label: { text: LETRAS[marcador.tipo] ?? '', color: '#ffffff', fontWeight: '600' },
                        title: marcador.folio + ' · ' + marcador.estado + ' · ' + marcador.etiqueta,
                    });
                    pin.addListener('click', () => abrir(marcador, pin));
                    marcadores.set(clave(marcador), pin);
                    limites.extend(posicion);

                    // El marcador que la administración tenía abierto sigue abierto, con sus datos nuevos.
                    if (abierto === clave(marcador)) {
                        abrir(marcador, pin);
                    }
                }

                if (abierto !== null && ! marcadores.has(abierto)) {
                    ventana.close();
                    abierto = null;
                }

                if (! encuadrado && marcadores.size > 0) {
                    encuadrado = true;

                    if (marcadores.size === 1) {
                        mapa.setCenter(limites.getCenter());
                        mapa.setZoom(15);
                    } else {
                        mapa.fitBounds(limites);
                    }
                }
            };

            dibujar();

            // El refresco cambia los datos: los marcadores se vuelven a poner sobre el mismo mapa.
            new MutationObserver(dibujar).observe(raiz, {
                subtree: true,
                childList: true,
                attributes: true,
                attributeFilter: ['data-mapa-datos'],
            });
        }).catch(() => {
            contenedor.textContent = 'No se pudo cargar el mapa de Google. Los pedidos están en la tabla.';
        });
    }
</script>
@endscript
@endif
