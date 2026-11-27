// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "Indicadores del
// A.5 en la app": administracion-app-E50 (el mapa de calor por colonia o por zona). Tarea 18.6 de
// openspec/changes/administracion-en-la-app/tasks.md. La pantalla y las demás pestañas están en indicadores.test.tsx y en
// indicadores-por-categoria.test.tsx.
//
// El A.5 pide un "Mapa interactivo de calor" de los pedidos por colonia o zona (NSV-2026-001-v8.pdf:22:ANEXO A, Sección
// A.5), con los mapas de cada plataforma (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Servicios externos). Cómo, lo
// decide ADR-0022 (decisión 13): react-native-maps sin provider (ADR-0009, decisión 1), con los agregados y los colores
// que da el servidor (PedidosPorColonia, PedidosPorZona e Intensidad), sin la capa de calor del proveedor (ADR-0009,
// decisión 3); sin la llave de Android, el mapa no se monta y la pantalla lo dice, como en src/cercanas/mapa.ts. La
// pestaña es la de las imágenes 49, 50 y 69 de la Propuesta 6 (C-248).
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado de la
// administración y los indicadores del contrato de IndicadoresController (__tests__/apoyo/administracion/indicadores.ts).
// En Jest no hay mapa nativo: la simulación de react-native-maps (__mocks__/react-native-maps.tsx) dibuja el mapa, sus
// círculos, sus polígonos y sus marcadores como vistas con sus propiedades. Que cada plataforma muestre el suyo, el de
// Apple en iPhone y el de Google en Android, se prueba en un teléfono real (ADR-0009).
import Constants from 'expo-constants';
import { act, fireEvent, screen, waitFor, within } from 'expo-router/testing-library';
import * as Mapas from 'react-native-maps';

import {
  abrirLaAppConLaAdministracion,
  PLATAFORMAS,
  usarElServidorDeLaAdministracion,
  type Plataforma,
} from '../apoyo/administracion';
import {
  consultasDeLosIndicadores,
  HOY,
  POR_ZONA,
  RANGO_DEL_MES_EN_CURSO,
  usarLosIndicadores,
} from '../apoyo/administracion/indicadores';

// La simulación, la misma instancia que importa la app.
const simulacion = Mapas as unknown as typeof import('../../__mocks__/react-native-maps');

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaAdministracion('administracion-mapa-de-calor');
const indicadores = usarLosIndicadores(servidor);

const RUTA_DE_LA_PANTALLA = '/administracion/indicadores';

// indicadores.refresco por omisión (adenda del 2026-11-22 de ADR-0007).
const TREINTA_SEGUNDOS = 30_000;

// Lo que app.config.ts le dice a la app sobre la compilación: si trae la llave de Google Maps para Android. En Jest,
// Constants.expoConfig es un objeto vacío que se puede completar.
const configuracionDeLaApp = Constants.expoConfig as { extra?: Record<string, unknown> };

function compilacionConLlave(trae: boolean) {
  configuracionDeLaApp.extra = { mapaDeGoogleEnAndroid: trae };
}

beforeEach(() => {
  jest.useFakeTimers({ now: HOY });
  simulacion.__reiniciar();
  // Por omisión, la compilación de Android trae la llave; la prueba sin ella la quita.
  compilacionConLlave(true);
});

afterEach(() => {
  jest.useRealTimers();
  delete configuracionDeLaApp.extra;
});

/** Abre los indicadores y la pestaña Geográfico, por colonia, como al entrar. */
async function abrirElMapaDeCalor(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LA_PANTALLA);
  await screen.findByText(RANGO_DEL_MES_EN_CURSO.descripcion);
  await screen.findByTestId('indicador-pedidos-totales');
  await fireEvent.press(within(screen.getAllByTestId('pestanas')[0]).getByRole('tab', { name: 'Geográfico' }));
  await screen.findByRole('header', { name: 'Pedidos por colonia' });
}

/** "Ver el mapa por": la segunda fila de pestañas de la pantalla. */
function agrupaciones() {
  return within(screen.getAllByTestId('pestanas')[1]);
}

async function verPorZona() {
  await fireEvent.press(agrupaciones().getByRole('tab', { name: 'Zona de servicio' }));
  await screen.findByRole('header', { name: 'Pedidos por zona' });
}

/** Lo que el lector de pantalla dice de cada fila de la tabla bajo el mapa, en orden. */
function filasDeLaTabla(): string[] {
  return within(screen.getByTestId('tabla-del-mapa'))
    .getAllByTestId(/^fila-/)
    .map((fila) => fila.props.accessibilityLabel as string);
}

/** Los textos que se ven dentro de un elemento, en orden. */
function textosDe(elemento: ReturnType<typeof screen.getByTestId>): string[] {
  return within(elemento)
    .getAllByText(/.+/)
    .map((texto) => String(texto.props.children));
}

/** Un punto del mapa, como lo recibe react-native-maps, de uno del GeoJSON: [longitud, latitud]. */
function punto([longitud, latitud]: [number, number]) {
  return { latitude: latitud, longitude: longitud };
}

describe('administracion-app-E50: el mapa de calor por colonia o por zona', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E50 — (%s) por colonia, un círculo por colonia en el centro de sus recolecciones, más grande y más intenso el de más pedidos, con el color que da el servidor',
    async (plataforma) => {
      await abrirElMapaDeCalor(plataforma);

      // Por omisión, por colonia; la app pide la agrupación con la pestaña geográfica.
      expect(screen.getByText('Ver el mapa por')).toBeOnTheScreen();
      expect(agrupaciones().getByRole('tab', { name: 'Colonia' })).toBeSelected();
      expect(consultasDeLosIndicadores(servidor()).at(-1)).toEqual({
        categoria: 'geografico',
        filtros: { agrupacion: 'colonia' },
      });

      // Sin provider: Apple en iPhone y Google en Android (ADR-0009, decisión 1).
      const mapa = screen.getByTestId('mapa');
      expect(mapa.props.provider).toBeUndefined();
      expect(mapa.props.accessibilityLabel).toBe(
        'Mapa de calor de pedidos por colonia: cada punto es una colonia de recolección; más grande y más oscuro, más pedidos',
      );
      expect(screen.queryByTestId('mapa-no-disponible')).not.toBeOnTheScreen();

      // Cinco pedidos en A, dos en B y uno en C: el radio crece con la intensidad del servidor y el color es el suyo,
      // con la transparencia del mapa del panel (0.8).
      const circulos = [0, 1, 2].map((indice) => screen.getByTestId(`circulo-${indice}`).props);
      expect(circulos.map(({ center, radius, fillColor }) => ({ center, radius, fillColor }))).toEqual([
        { center: { latitude: 24.03, longitude: -104.66 }, radius: 800, fillColor: '#104281cc' },
        { center: { latitude: 24.02, longitude: -104.64 }, radius: 410, fillColor: '#3987e5cc' },
        { center: { latitude: 24.01, longitude: -104.65 }, radius: 280, fillColor: '#6da7eccc' },
      ]);
      expect(screen.queryAllByTestId(/^zona-/)).toHaveLength(0);

      // Encima de cada círculo, la letra de su fila en la tabla: el color nunca va solo.
      expect(textosDe(screen.getByTestId('colonia-0'))).toEqual(['A']);
      expect(screen.getByTestId('colonia-0').props.accessibilityLabel).toBe('A · Colonia de prueba A, Durango: 5 pedidos');
      expect(screen.getByTestId('colonia-2').props.accessibilityLabel).toBe('C · Colonia de prueba C: 1 pedido');

      // Debajo del mapa, la tabla con las mismas cifras, en el orden del servidor.
      expect(filasDeLaTabla()).toEqual([
        'A · Colonia de prueba A, Durango. Pedidos: 5',
        'B · Colonia de prueba B, Durango. Pedidos: 2',
        'C · Colonia de prueba C. Pedidos: 1',
      ]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E50 — (%s) por zona, cada zona pintada como polígono con la geometría y el color que da el servidor, con su nombre y sus pedidos, y la tabla con su plaza',
    async (plataforma) => {
      await abrirElMapaDeCalor(plataforma);

      await verPorZona();

      expect(agrupaciones().getByRole('tab', { name: 'Zona de servicio' })).toBeSelected();
      expect(consultasDeLosIndicadores(servidor()).at(-1)).toEqual({
        categoria: 'geografico',
        filtros: { agrupacion: 'zona' },
      });
      expect(screen.getByTestId('mapa').props.provider).toBeUndefined();
      expect(screen.queryAllByTestId(/^circulo-/)).toHaveLength(0);

      // El Centro, con su hueco; el Norte, en dos partes; el Sur, sin pedidos. El color, el del servidor, con la
      // transparencia del panel (0.65).
      const centro = screen.getByTestId('zona-0-0').props;
      expect(centro.coordinates).toEqual(
        (
          [
            [-104.67, 24.02],
            [-104.64, 24.02],
            [-104.64, 24.0],
            [-104.67, 24.0],
            [-104.67, 24.02],
          ] as [number, number][]
        ).map(punto),
      );
      expect(centro.holes).toEqual([
        (
          [
            [-104.66, 24.015],
            [-104.65, 24.015],
            [-104.65, 24.005],
            [-104.66, 24.015],
          ] as [number, number][]
        ).map(punto),
      ]);
      expect(centro.fillColor).toBe('#104281a6');
      expect(screen.getByTestId('zona-1-0').props.fillColor).toBe('#6da7eca6');
      expect(screen.getByTestId('zona-1-1').props.coordinates[0]).toEqual(punto([-104.63, 24.06]));
      expect(screen.getByTestId('zona-2-0').props.fillColor).toBe('#b7d3f6a6');

      // Encima de cada zona, su nombre y su número de pedidos.
      expect(textosDe(screen.getByTestId('zona-0'))).toEqual(['Zona de prueba Durango Centro', '6']);
      expect(screen.getByTestId('zona-1').props.accessibilityLabel).toBe('Zona de prueba Durango Norte: 2 pedidos');

      expect(filasDeLaTabla()).toEqual([
        'Zona de prueba Durango Centro, Plaza de prueba Durango. Pedidos: 6',
        'Zona de prueba Durango Norte, Plaza de prueba Durango. Pedidos: 2',
        'Zona de prueba Durango Sur, Plaza de prueba Durango. Pedidos: 0',
      ]);

      // De vuelta por colonia, se pide otra vez por colonia.
      await fireEvent.press(agrupaciones().getByRole('tab', { name: 'Colonia' }));
      await screen.findByRole('header', { name: 'Pedidos por colonia' });
      expect(consultasDeLosIndicadores(servidor()).at(-1)).toEqual({
        categoria: 'geografico',
        filtros: { agrupacion: 'colonia' },
      });
      expect(screen.getAllByTestId(/^circulo-/)).toHaveLength(3);
    },
  );

  test('administracion-app-E50 — (android) sin la llave de Android, el mapa no se monta, la pantalla lo dice y la tabla sigue, por colonia y por zona', async () => {
    compilacionConLlave(false);
    await abrirElMapaDeCalor('android');

    expect(screen.queryByTestId('mapa')).not.toBeOnTheScreen();
    const aviso = within(screen.getByTestId('mapa-no-disponible'));
    expect(aviso.getByText('El mapa no está disponible en esta versión de la app.')).toBeOnTheScreen();
    expect(filasDeLaTabla()).toEqual([
      'A · Colonia de prueba A, Durango. Pedidos: 5',
      'B · Colonia de prueba B, Durango. Pedidos: 2',
      'C · Colonia de prueba C. Pedidos: 1',
    ]);

    await verPorZona();

    expect(screen.queryByTestId('mapa')).not.toBeOnTheScreen();
    expect(screen.getByTestId('mapa-no-disponible')).toBeOnTheScreen();
    expect(filasDeLaTabla()).toHaveLength(3);
  });

  test('administracion-app-E50 — (ios) en iPhone el mapa de Apple no lleva llave: se monta aunque la compilación no traiga la de Google', async () => {
    compilacionConLlave(false);
    await abrirElMapaDeCalor('ios');

    expect(screen.getByTestId('mapa')).toBeOnTheScreen();
    expect(screen.queryByTestId('mapa-no-disponible')).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E50 — (%s) el mapa se encuadra en las figuras al estar listo y al cambiar de agrupación, y la consulta periódica no lo mueve',
    async (plataforma) => {
      await abrirElMapaDeCalor(plataforma);
      const { fitToCoordinates } = simulacion.__referenciaDelMapa;
      expect(fitToCoordinates).not.toHaveBeenCalled();

      await act(async () => {
        fireEvent(screen.getByTestId('mapa'), 'mapReady');
      });

      expect(fitToCoordinates).toHaveBeenCalledTimes(1);
      expect(fitToCoordinates.mock.calls[0][0]).toEqual([
        { latitude: 24.03, longitude: -104.66 },
        { latitude: 24.02, longitude: -104.64 },
        { latitude: 24.01, longitude: -104.65 },
      ]);

      // La siguiente consulta trae las mismas colonias: el mapa se queda como está.
      const antes = consultasDeLosIndicadores(servidor()).length;
      await act(() => jest.advanceTimersByTimeAsync(TREINTA_SEGUNDOS));
      await waitFor(() => expect(consultasDeLosIndicadores(servidor())).toHaveLength(antes + 1));
      expect(fitToCoordinates).toHaveBeenCalledTimes(1);

      // Por zona, mientras llega la respuesta la pestaña dice que carga; el mapa que vuelve se encuadra al estar listo
      // en los contornos de las zonas.
      await verPorZona();
      await act(async () => {
        fireEvent(screen.getByTestId('mapa'), 'mapReady');
      });
      expect(fitToCoordinates).toHaveBeenCalledTimes(2);
      expect(fitToCoordinates.mock.calls[1][0]).toContainEqual(punto([-104.63, 24.06]));
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E50 — (%s) antes de estar listo, el mapa ya abre sobre las figuras de la respuesta, por colonia y por zona',
    async (plataforma) => {
      await abrirElMapaDeCalor(plataforma);

      // La caja de las tres colonias, de 24.01 a 24.03 de latitud y de -104.66 a -104.64 de longitud.
      expect(screen.getByTestId('mapa').props.initialRegion).toEqual({
        latitude: expect.closeTo(24.02, 6),
        longitude: expect.closeTo(-104.65, 6),
        latitudeDelta: expect.closeTo(0.02, 6),
        longitudeDelta: expect.closeTo(0.02, 6),
      });

      // La de los contornos de las tres zonas, también la del Sur, sin pedidos: de 23.97 a 24.06 y de -104.67 a
      // -104.62.
      await verPorZona();
      expect(screen.getByTestId('mapa').props.initialRegion).toEqual({
        latitude: expect.closeTo(24.015, 6),
        longitude: expect.closeTo(-104.645, 6),
        latitudeDelta: expect.closeTo(0.09, 6),
        longitudeDelta: expect.closeTo(0.05, 6),
      });
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E50 — (%s) los letreros no siguen los cambios de su vista, y el de una zona dice su nueva cifra cuando la consulta trae otra',
    async (plataforma) => {
      await abrirElMapaDeCalor(plataforma);
      expect(screen.getByTestId('colonia-0').props.tracksViewChanges).toBe(false);

      await verPorZona();
      expect(screen.getByTestId('zona-0').props.tracksViewChanges).toBe(false);
      expect(textosDe(screen.getByTestId('zona-0'))).toEqual(['Zona de prueba Durango Centro', '6']);

      // Un pedido más en el Centro: la siguiente consulta lo trae y el letrero lo dice, igual que la tabla.
      const [centro, ...lasDemas] = (POR_ZONA.mapa_de_calor as { zonas: Record<string, unknown>[] }).zonas;
      indicadores().cambiarSinFiltros(
        'geografico',
        { mapa_de_calor: { agrupacion: 'zona', zonas: [{ ...centro, pedidos: 7 }, ...lasDemas] } },
        'zona',
      );
      await act(() => jest.advanceTimersByTimeAsync(TREINTA_SEGUNDOS));

      await waitFor(() =>
        expect(textosDe(screen.getByTestId('zona-0'))).toEqual(['Zona de prueba Durango Centro', '7']),
      );
      expect(screen.getByTestId('zona-0').props.tracksViewChanges).toBe(false);
      expect(filasDeLaTabla()[0]).toBe('Zona de prueba Durango Centro, Plaza de prueba Durango. Pedidos: 7');
    },
  );

  test('administracion-app-E50 — sin pedidos en el rango, el mapa va sin figuras y en lugar de la tabla lo dice como el panel', async () => {
    indicadores().cambiarSinFiltros('geografico', { mapa_de_calor: { agrupacion: 'colonia', colonias: [] } });
    await abrirElMapaDeCalor('ios');

    expect(screen.getByTestId('mapa')).toBeOnTheScreen();
    expect(screen.queryAllByTestId(/^circulo-/)).toHaveLength(0);
    // Sin figuras no hay de dónde sacar la región: abre donde lo deje la plataforma, como el panel.
    expect(screen.getByTestId('mapa').props.initialRegion).toBeUndefined();
    expect(screen.getByText('No hay pedidos en el rango.')).toBeOnTheScreen();
    expect(screen.queryByTestId('tabla-del-mapa')).not.toBeOnTheScreen();
  });
});
