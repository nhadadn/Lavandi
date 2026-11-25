{{--
    El cargador de Google Maps JavaScript que comparten los mapas del panel (ADR-0009, decisión 3). Va dentro del
    <script> de un bloque @script de Livewire. Define window.cargarLosMapasDelPanel(llave), que carga la API una sola vez
    por página, con la llave del contenedor (filament.mapas.contenedor), y responde siempre la misma promesa con
    google.maps: dos mapas en la misma página no cargan la API dos veces.
--}}
window.cargarLosMapasDelPanel ??= (llave) => window.mapasDelPanel ??= new Promise((resolver, rechazar) => {
    window.alCargarLosMapasDelPanel = () => resolver(window.google.maps);

    const script = document.createElement('script');
    script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(llave)
        + '&loading=async&callback=alCargarLosMapasDelPanel';
    script.async = true;
    script.onerror = rechazar;
    document.head.append(script);
});
