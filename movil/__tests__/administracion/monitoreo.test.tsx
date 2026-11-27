// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "Monitoreo de los
// pedidos activos en la app": administracion-app-E28 (los pedidos activos en la lista y en el mapa) y E29 (el monitoreo
// se pone al día solo), con E03 (cada entrada abre su pantalla dentro de la app) y E05 (el 401 en cada pantalla). Tarea
// 10.1 de openspec/changes/administracion-en-la-app/tasks.md. El mapa de cada teléfono (E30) está en
// mapa-del-monitoreo.test.tsx; el pedido cancelado (E31) espera DD-016. Al final, sin escenario propio, lo que la
// pantalla hace como la imagen 05 de la Propuesta 06 y como el mapa del panel.
//
// El Módulo D #4 pide la "Vista en tiempo real de todos los pedidos activos con mapa" (NSV-2026-001-v8.pdf:19:ANEXO A,
// Sección A.3). Arthur decidió que la administración la tiene en la app (C-247); cómo, lo decide ADR-0022 (decisiones 9,
// 12 y 13): la misma respuesta para la lista y el mapa, la consulta cada 30 s de ADR-0007 y el mapa sin provider. Las
// reglas de qué pedido está activo y qué marcadores lleva son las del panel (administracion-E06, E07, E09 y E10): la app
// pinta lo que da el servidor.
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado de la
// administración (__tests__/apoyo/administracion.ts) y el monitoreo del contrato de MonitoreoController
// (__tests__/apoyo/administracion/monitoreo.ts). El mapa es la simulación de __mocks__/react-native-maps.tsx, con la ref
// que lee el encuadre; el ciclo de 30 s corre con los temporizadores falsos de Jest.
import Constants from 'expo-constants';
import { router } from 'expo-router';
import { act, fireEvent, screen, waitFor, within } from 'expo-router/testing-library';
import { AppState, type AppStateStatus, type NativeEventSubscription } from 'react-native';
import * as Mapas from 'react-native-maps';

import { MENSAJES } from '../../src/acceso/mensajes';
import { leerToken } from '../../src/sesion/almacen';
import {
  abrirLaAppConLaAdministracion,
  PLATAFORMAS,
  RUTA_DEL_INICIO,
  usarElServidorDeLaAdministracion,
  type Plataforma,
} from '../apoyo/administracion';
import {
  CONFIRMADO,
  consultasDelMonitoreo,
  EN_PROCESO,
  ENTREGADO,
  RECOLECTADO,
  RUTA_DEL_MONITOREO,
  usarElMonitoreo,
  type PedidoGuardado,
} from '../apoyo/administracion/monitoreo';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

// ADR-0007: cada 30 segundos.
const TREINTA_SEGUNDOS = 30_000;

const servidor = usarElServidorDeLaAdministracion('administracion-monitoreo');
const monitoreo = usarElMonitoreo(servidor);

// La simulación del mapa, la misma instancia que importa la app.
const simulacion = Mapas as unknown as typeof import('../../__mocks__/react-native-maps');

// Lo que app.config.ts le dice a la app sobre la compilación: aquí, con la llave del mapa de Android. Sin ella, en
// mapa-del-monitoreo.test.tsx.
const configuracionDeLaApp = Constants.expoConfig as { extra?: Record<string, unknown> };

// Los oyentes de AppState que registró la app, para mandarla al fondo y traerla al frente.
let oyentesDeLaApp: ((estado: AppStateStatus) => void)[] = [];

beforeEach(() => {
  simulacion.__reiniciar();
  configuracionDeLaApp.extra = { mapaDeGoogleEnAndroid: true };
  oyentesDeLaApp = [];
  jest.spyOn(AppState, 'addEventListener').mockImplementation((_tipo, oyente) => {
    oyentesDeLaApp.push(oyente as (estado: AppStateStatus) => void);
    return {
      remove: () => {
        oyentesDeLaApp = oyentesDeLaApp.filter((otro) => otro !== oyente);
      },
    } as NativeEventSubscription;
  });
});

afterEach(() => {
  delete configuracionDeLaApp.extra;
  jest.useRealTimers();
});

async function abrirElMonitoreo(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_MONITOREO);
  await screen.findByRole('header', { name: 'Monitoreo de pedidos' });
}

/** Abre el monitoreo y espera a que llegue la primera respuesta, con la lista. */
async function abrirElMonitoreoConLaLista(plataforma: Plataforma, primero: PedidoGuardado) {
  await abrirElMonitoreo(plataforma);
  await screen.findByTestId(`pedido-${primero.id_publico}`);
}

function consultas(): number {
  return consultasDelMonitoreo(servidor());
}

/** Avanza el reloj falso y deja que lleguen las respuestas. */
async function pasan(milisegundos: number) {
  await act(() => jest.advanceTimersByTimeAsync(milisegundos));
}

async function mandarLaAppA(estado: AppStateStatus) {
  await act(async () => {
    for (const oyente of [...oyentesDeLaApp]) {
      oyente(estado);
    }
  });
}

/** El mapa avisa que ya está listo, como lo hace el nativo al terminar de crearse. */
async function elMapaEstaListo() {
  await fireEvent(screen.getByTestId('mapa'), 'mapReady');
}

/** Lo que dice cada pedido de la lista, en su orden: "Pedido <folio> · <estado> · <plaza> · <recolección>". */
function pedidosDeLaLista(): string[] {
  return screen.getAllByTestId(/^pedido-/).map((tarjeta) =>
    within(tarjeta)
      .getAllByText(/.+/)
      .map((texto) => texto.props.children as string)
      .join(' · '),
  );
}

/** Los marcadores del mapa, en su orden, con su letra, su punto y lo que lee el lector de pantalla. */
function marcadoresDelMapa() {
  return within(screen.getByTestId('mapa'))
    .getAllByTestId(/^marcador-/)
    .map((marcador) => ({
      testID: marcador.props.testID as string,
      letra: within(marcador).getByText(/^[RE]$/).props.children as string,
      punto: marcador.props.coordinate as { latitude: number; longitude: number },
      dice: marcador.props.accessibilityLabel as string,
    }));
}

function marcador(pedido: PedidoGuardado, tipo: 'recoleccion' | 'entrega') {
  return screen.getByTestId(`marcador-${pedido.id_publico}-${tipo}`);
}

/** Lo que dice el marcador tocado, arriba del mapa, renglón por renglón. */
function loQueDiceElMarcador(): string[] {
  return within(screen.getByTestId('marcador-elegido'))
    .getAllByText(/.+/)
    .map((texto) => texto.props.children as string);
}

describe('administracion-app-E03: cada entrada abre su pantalla dentro de la app', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E03 — (%s) "Monitoreo de pedidos" del inicio abre el monitoreo y "Regresar" vuelve al inicio',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_INICIO);
      await screen.findByRole('header', { name: 'Administración' });
      // El inicio pide el monitoreo para su conteo de pedidos activos (administracion-app-E03).
      await waitFor(() => expect(consultas()).toBe(1));

      await fireEvent.press(screen.getByRole('link', { name: 'Monitoreo de pedidos' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_MONITOREO));
      expect(await screen.findByRole('header', { name: 'Monitoreo de pedidos' })).toBeOnTheScreen();
      expect(await screen.findByTestId(`pedido-${EN_PROCESO.id_publico}`)).toBeOnTheScreen();
      expect(consultas()).toBe(2);

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_INICIO));
      expect(await screen.findByRole('header', { name: 'Administración' })).toBeOnTheScreen();
      // De vuelta a la vista, el inicio vuelve a pedir su conteo.
      await waitFor(() => expect(consultas()).toBe(3));
    },
  );
});

describe('administracion-app-E05: la sesión vence o se revoca', () => {
  async function esperarElInicioDeSesion() {
    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    expect(await screen.findByLabelText('Contraseña')).toBeOnTheScreen();
    await expect(leerToken()).resolves.toBeNull();
  }

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el token vencido, el monitoreo recibe 401 y la app vuelve al inicio de sesión',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_INICIO);
      await screen.findByRole('header', { name: 'Administración' });
      // El conteo del inicio ya llegó, con la sesión todavía viva (administracion-app-E03).
      expect(await screen.findByTestId('conteo-monitoreo')).toBeOnTheScreen();
      servidor().vencerLaSesion();

      await fireEvent.press(screen.getByRole('link', { name: 'Monitoreo de pedidos' }));

      await esperarElInicioDeSesion();
      expect(consultas()).toBe(2);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) si el token vence con el monitoreo abierto, la siguiente consulta recibe 401, la app vuelve al inicio de sesión y deja de consultar',
    async (plataforma) => {
      jest.useFakeTimers();
      await abrirElMonitoreoConLaLista(plataforma, EN_PROCESO);

      servidor().vencerLaSesion();
      await pasan(TREINTA_SEGUNDOS);

      await esperarElInicioDeSesion();
      expect(consultas()).toBe(2);
      await pasan(3 * TREINTA_SEGUNDOS);
      expect(consultas()).toBe(2);
    },
  );
});

describe('administracion-app-E28: los pedidos activos en la lista y en el mapa', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E28 — (%s) la lista trae el Confirmado y el En proceso de dos plazas con su folio, su estado operativo, su plaza y su recolección, y no el Entregado',
    async (plataforma) => {
      await abrirElMonitoreoConLaLista(plataforma, EN_PROCESO);

      expect(screen.getByText('2 pedidos activos · todas las plazas · se pone al día cada 30 s')).toBeOnTheScreen();
      expect(screen.getByRole('header', { name: 'Pedidos activos' })).toBeOnTheScreen();
      expect(pedidosDeLaLista()).toEqual([
        'Pedido 7K2M9QXA · En proceso · Plaza de prueba Durango · Recolección: 24/11/2026, 09:00 a 10:00',
        'Pedido R6T3N8VB · Confirmado · Plaza de prueba Torreón · Recolección: 24/11/2026, 11:00 a 12:00',
      ]);
      expect(screen.queryByTestId(`pedido-${ENTREGADO.id_publico}`)).not.toBeOnTheScreen();
      expect(screen.queryByText(/M4P7R2KD/)).not.toBeOnTheScreen();
      // Una sola consulta da la lista y el mapa.
      expect(servidor().peticiones().map(({ metodo, ruta }) => `${metodo} ${ruta}`)).toEqual([
        'GET /cuenta',
        `GET ${RUTA_DEL_MONITOREO}`,
      ]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E28 — (%s) el mapa trae una R en la recolección de cada pedido y una E en la entrega que es en otra dirección, y ningún otro marcador',
    async (plataforma) => {
      await abrirElMonitoreoConLaLista(plataforma, EN_PROCESO);

      expect(marcadoresDelMapa()).toEqual([
        {
          testID: `marcador-${EN_PROCESO.id_publico}-recoleccion`,
          letra: 'R',
          punto: { latitude: EN_PROCESO.recoleccion.latitud, longitude: EN_PROCESO.recoleccion.longitud },
          dice: 'Pedido 7K2M9QXA, En proceso, Recolección, Plaza de prueba Durango',
        },
        {
          testID: `marcador-${EN_PROCESO.id_publico}-entrega`,
          letra: 'E',
          punto: { latitude: EN_PROCESO.entrega?.latitud, longitude: EN_PROCESO.entrega?.longitud },
          dice: 'Pedido 7K2M9QXA, En proceso, Entrega, Plaza de prueba Durango',
        },
        {
          testID: `marcador-${CONFIRMADO.id_publico}-recoleccion`,
          letra: 'R',
          punto: { latitude: CONFIRMADO.recoleccion.latitud, longitude: CONFIRMADO.recoleccion.longitud },
          dice: 'Pedido R6T3N8VB, Confirmado, Recolección y entrega, Plaza de prueba Torreón',
        },
      ]);
      expect(screen.getByText('R: recolección · E: entrega en otra dirección')).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E28 — (%s) al tocar un marcador dice el folio, el estado, si es la recolección o la entrega, y la plaza',
    async (plataforma) => {
      await abrirElMonitoreoConLaLista(plataforma, EN_PROCESO);
      expect(screen.queryByTestId('marcador-elegido')).not.toBeOnTheScreen();

      await fireEvent.press(marcador(EN_PROCESO, 'recoleccion'));
      expect(loQueDiceElMarcador()).toEqual(['Pedido 7K2M9QXA', 'En proceso · Recolección', 'Plaza de prueba Durango']);
      // Lo que dice va encima del mapa, pero no recibe los toques: en el teléfono, los marcadores que quedan debajo se
      // siguen tocando y esa parte del mapa se sigue arrastrando. La simulación no reparte los toques como el nativo, así
      // que se lee la propiedad.
      expect(screen.getByTestId('marcador-elegido')).toHaveProp('pointerEvents', 'none');

      await fireEvent.press(marcador(EN_PROCESO, 'entrega'));
      expect(loQueDiceElMarcador()).toEqual(['Pedido 7K2M9QXA', 'En proceso · Entrega', 'Plaza de prueba Durango']);

      await fireEvent.press(marcador(CONFIRMADO, 'recoleccion'));
      expect(loQueDiceElMarcador()).toEqual([
        'Pedido R6T3N8VB',
        'Confirmado · Recolección y entrega',
        'Plaza de prueba Torreón',
      ]);
    },
  );

  test('administracion-app-E28 — el mapa no muestra la posición de ningún repartidor: cada marcador es de un pedido de la lista', async () => {
    await abrirElMonitoreoConLaLista('android', EN_PROCESO);

    const pedidosDeLaPantalla = screen.getAllByTestId(/^pedido-/).map((tarjeta) => tarjeta.props.testID as string);
    for (const { testID } of marcadoresDelMapa()) {
      const [, pedido, tipo] = /^marcador-(.+)-(recoleccion|entrega)$/.exec(testID) ?? [];
      expect(pedidosDeLaPantalla).toContain(`pedido-${pedido}`);
      expect(['recoleccion', 'entrega']).toContain(tipo);
    }
    expect(screen.queryByText(/repartidor/i)).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E28 — (%s) sin pedidos activos, el mapa sin marcadores y "No hay pedidos activos.", como el panel',
    async (plataforma) => {
      monitoreo().poner([ENTREGADO]);

      await abrirElMonitoreo(plataforma);

      expect(await screen.findByText('No hay pedidos activos.')).toBeOnTheScreen();
      expect(screen.getByText('Todas las plazas · se pone al día cada 30 s')).toBeOnTheScreen();
      expect(screen.getByTestId('mapa')).toBeOnTheScreen();
      expect(within(screen.getByTestId('mapa')).queryAllByTestId(/^marcador-/)).toEqual([]);
      expect(screen.queryByText('R: recolección · E: entrega en otra dirección')).not.toBeOnTheScreen();
      expect(screen.queryByRole('header', { name: 'Pedidos activos' })).not.toBeOnTheScreen();
    },
  );

  test('administracion-app-E28 — con un solo pedido activo, dice "1 pedido activo"', async () => {
    monitoreo().poner([CONFIRMADO]);

    await abrirElMonitoreoConLaLista('ios', CONFIRMADO);

    expect(screen.getByText('1 pedido activo · todas las plazas · se pone al día cada 30 s')).toBeOnTheScreen();
  });
});

describe('administracion-app-E29: el monitoreo se pone al día solo', () => {
  beforeEach(() => {
    jest.useFakeTimers();
    monitoreo().poner([RECOLECTADO, EN_PROCESO]);
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E29 — (%s) un pedido que pasa de Recolectado a En proceso se ve En proceso en la lista y en el marcador en 30 segundos o menos, sin tocar nada',
    async (plataforma) => {
      await abrirElMonitoreoConLaLista(plataforma, RECOLECTADO);
      expect(within(screen.getByTestId(`pedido-${RECOLECTADO.id_publico}`)).getByText('Recolectado')).toBeOnTheScreen();
      await fireEvent.press(marcador(RECOLECTADO, 'recoleccion'));
      expect(loQueDiceElMarcador()).toContain('Recolectado · Recolección y entrega');

      // La lavandería lo pasa a En proceso.
      monitoreo().cambiar(RECOLECTADO.id_publico, 'en_proceso');
      await pasan(TREINTA_SEGUNDOS);

      expect(consultas()).toBe(2);
      const tarjeta = screen.getByTestId(`pedido-${RECOLECTADO.id_publico}`);
      expect(within(tarjeta).getByText('En proceso')).toBeOnTheScreen();
      expect(within(tarjeta).queryByText('Recolectado')).not.toBeOnTheScreen();
      expect(marcador(RECOLECTADO, 'recoleccion').props.accessibilityLabel).toBe(
        'Pedido W9C4D7PL, En proceso, Recolección y entrega, Plaza de prueba Torreón',
      );
      // El marcador tocado sigue diciendo lo suyo, con sus datos nuevos.
      expect(loQueDiceElMarcador()).toEqual([
        'Pedido W9C4D7PL',
        'En proceso · Recolección y entrega',
        'Plaza de prueba Torreón',
      ]);
    },
  );

  test('administracion-app-E29 — sigue consultando cada 30 segundos mientras la pantalla está visible', async () => {
    await abrirElMonitoreoConLaLista('android', RECOLECTADO);

    await pasan(TREINTA_SEGUNDOS - 1);
    expect(consultas()).toBe(1);
    await pasan(1);
    expect(consultas()).toBe(2);
    await pasan(2 * TREINTA_SEGUNDOS);
    expect(consultas()).toBe(4);
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E29 — (%s) con la app al fondo deja de consultar, y al traerla al frente consulta de inmediato y retoma el ciclo',
    async (plataforma) => {
      await abrirElMonitoreoConLaLista(plataforma, RECOLECTADO);

      await mandarLaAppA('background');
      monitoreo().cambiar(RECOLECTADO.id_publico, 'en_proceso');
      await pasan(3 * TREINTA_SEGUNDOS);
      expect(consultas()).toBe(1);

      await mandarLaAppA('active');

      expect(consultas()).toBe(2);
      await waitFor(() =>
        expect(within(screen.getByTestId(`pedido-${RECOLECTADO.id_publico}`)).getByText('En proceso')).toBeOnTheScreen(),
      );
      await pasan(TREINTA_SEGUNDOS);
      expect(consultas()).toBe(3);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E29 — (%s) con la pantalla cerrada deja de consultar, y al abrirla otra vez consulta de inmediato',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_INICIO);
      await screen.findByRole('header', { name: 'Administración' });
      // El inicio pide el monitoreo una vez para su conteo, sin consulta periódica (administracion-app-E03).
      await waitFor(() => expect(consultas()).toBe(1));
      await fireEvent.press(screen.getByRole('link', { name: 'Monitoreo de pedidos' }));
      await screen.findByTestId(`pedido-${RECOLECTADO.id_publico}`);
      expect(consultas()).toBe(2);

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_INICIO));
      // De vuelta a la vista, el inicio pide su conteo una vez, y nada más.
      await waitFor(() => expect(consultas()).toBe(3));
      await pasan(3 * TREINTA_SEGUNDOS);
      expect(consultas()).toBe(3);

      await act(() => router.push(RUTA_DEL_MONITOREO));

      await waitFor(() => expect(consultas()).toBe(4));
      expect(await screen.findByTestId(`pedido-${RECOLECTADO.id_publico}`)).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E29 — (%s) el mapa no se vuelve a crear en cada consulta: cambian sus marcadores, y se encuadra solo la primera vez que hay pedidos',
    async (plataforma) => {
      monitoreo().poner([]);
      await abrirElMonitoreo(plataforma);
      await screen.findByText('No hay pedidos activos.');
      const elMapa = screen.getByTestId('mapa');
      await elMapaEstaListo();
      // Sin pedidos no hay nada que encuadrar.
      expect(simulacion.__referenciaDelMapa.fitToCoordinates).not.toHaveBeenCalled();
      expect(simulacion.__referenciaDelMapa.animateToRegion).not.toHaveBeenCalled();

      // Llegan dos pedidos: se encuadran sus tres marcadores, una vez.
      monitoreo().poner([RECOLECTADO, EN_PROCESO]);
      await pasan(TREINTA_SEGUNDOS);
      await screen.findByTestId(`pedido-${RECOLECTADO.id_publico}`);

      expect(screen.getByTestId('mapa')).toBe(elMapa);
      expect(marcadoresDelMapa().map(({ testID }) => testID)).toEqual([
        `marcador-${RECOLECTADO.id_publico}-recoleccion`,
        `marcador-${EN_PROCESO.id_publico}-recoleccion`,
        `marcador-${EN_PROCESO.id_publico}-entrega`,
      ]);
      expect(simulacion.__referenciaDelMapa.fitToCoordinates).toHaveBeenCalledTimes(1);
      expect(simulacion.__referenciaDelMapa.fitToCoordinates).toHaveBeenCalledWith(
        [
          { latitude: RECOLECTADO.recoleccion.latitud, longitude: RECOLECTADO.recoleccion.longitud },
          { latitude: EN_PROCESO.recoleccion.latitud, longitude: EN_PROCESO.recoleccion.longitud },
          { latitude: EN_PROCESO.entrega?.latitud, longitude: EN_PROCESO.entrega?.longitud },
        ],
        expect.objectContaining({ animated: true }),
      );

      // Las consultas siguientes cambian los marcadores sobre el mismo mapa, sin encuadrar otra vez.
      monitoreo().poner([EN_PROCESO, CONFIRMADO]);
      await pasan(TREINTA_SEGUNDOS);
      await waitFor(() => expect(screen.queryByTestId(`pedido-${RECOLECTADO.id_publico}`)).not.toBeOnTheScreen());
      await pasan(TREINTA_SEGUNDOS);

      expect(consultas()).toBe(4);
      expect(screen.getByTestId('mapa')).toBe(elMapa);
      expect(marcadoresDelMapa().map(({ testID }) => testID)).toEqual([
        `marcador-${EN_PROCESO.id_publico}-recoleccion`,
        `marcador-${EN_PROCESO.id_publico}-entrega`,
        `marcador-${CONFIRMADO.id_publico}-recoleccion`,
      ]);
      expect(simulacion.__referenciaDelMapa.fitToCoordinates).toHaveBeenCalledTimes(1);
      expect(simulacion.__referenciaDelMapa.animateToRegion).not.toHaveBeenCalled();
    },
  );

  test('administracion-app-E29 — si la primera respuesta llega antes de que el mapa esté listo, se encuadra cuando lo está, una sola vez', async () => {
    await abrirElMonitoreoConLaLista('android', RECOLECTADO);
    expect(simulacion.__referenciaDelMapa.fitToCoordinates).not.toHaveBeenCalled();

    await elMapaEstaListo();
    await pasan(TREINTA_SEGUNDOS);

    expect(simulacion.__referenciaDelMapa.fitToCoordinates).toHaveBeenCalledTimes(1);
  });

  test('administracion-app-E29 — el marcador tocado deja de decirse cuando su pedido sale del monitoreo', async () => {
    await abrirElMonitoreoConLaLista('android', RECOLECTADO);
    await fireEvent.press(marcador(RECOLECTADO, 'recoleccion'));
    expect(screen.getByTestId('marcador-elegido')).toBeOnTheScreen();

    monitoreo().cambiar(RECOLECTADO.id_publico, 'entregado');
    await pasan(TREINTA_SEGUNDOS);

    await waitFor(() => expect(screen.queryByTestId(`pedido-${RECOLECTADO.id_publico}`)).not.toBeOnTheScreen());
    expect(screen.queryByTestId('marcador-elegido')).not.toBeOnTheScreen();
  });
});

// Lo que la pantalla hace sin un escenario propio en la spec: lo que pasa si la consulta no llega, como la imagen 05 de
// la Propuesta 06, y lo que el mapa hace como el del panel
// (servidor/resources/views/filament/pages/monitoreo-de-pedidos/mapa.blade.php). No cubren ningún escenario.
describe('sin escenario propio: como la imagen 05 de la Propuesta 06 y como el mapa del panel', () => {
  test.each(PLATAFORMAS)(
    'sin escenario (imagen 05 de la Propuesta 06) — (%s) si el monitoreo no llega, lo dice con el mensaje general y "Intentar de nuevo" lo vuelve a pedir',
    async (plataforma) => {
      servidor().interceptar = ({ ruta }) => {
        if (ruta === RUTA_DEL_MONITOREO) {
          throw new TypeError('Network request failed');
        }
        return undefined;
      };

      await abrirElMonitoreo(plataforma);

      expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
      // El mapa sigue montado, sin marcadores.
      expect(screen.getByTestId('mapa')).toBeOnTheScreen();

      servidor().interceptar = null;
      await fireEvent.press(screen.getByRole('button', { name: 'Intentar de nuevo' }));

      expect(await screen.findByTestId(`pedido-${EN_PROCESO.id_publico}`)).toBeOnTheScreen();
      expect(screen.queryByText(MENSAJES.sinConexion)).not.toBeOnTheScreen();
      expect(consultas()).toBe(2);
    },
  );

  test('sin escenario (como el mapa del panel) — con un solo marcador, se centra en él en lugar de encuadrar un solo punto', async () => {
    jest.useFakeTimers();
    monitoreo().poner([RECOLECTADO]);
    await abrirElMonitoreoConLaLista('ios', RECOLECTADO);

    await elMapaEstaListo();
    await pasan(TREINTA_SEGUNDOS);

    expect(simulacion.__referenciaDelMapa.fitToCoordinates).not.toHaveBeenCalled();
    expect(simulacion.__referenciaDelMapa.animateToRegion).toHaveBeenCalledTimes(1);
    expect(simulacion.__referenciaDelMapa.animateToRegion).toHaveBeenCalledWith(
      expect.objectContaining({
        latitude: RECOLECTADO.recoleccion.latitud,
        longitude: RECOLECTADO.recoleccion.longitud,
      }),
    );
  });

  test.each(PLATAFORMAS)(
    'sin escenario (como la ventana del marcador del panel, que se cierra) — (%s) tocar el mapa fuera de los marcadores deja de decir el marcador tocado',
    async (plataforma) => {
      await abrirElMonitoreoConLaLista(plataforma, EN_PROCESO);
      await fireEvent.press(marcador(EN_PROCESO, 'recoleccion'));
      expect(screen.getByTestId('marcador-elegido')).toBeOnTheScreen();

      // El toque de un marcador sube al mapa: cada marcador lo detiene (stopPropagation), y si aun así llega, el mapa
      // lo reconoce por su "marker-press" y no cierra nada (node_modules/react-native-maps/src/MapMarker.tsx).
      for (const elemento of within(screen.getByTestId('mapa')).getAllByTestId(/^marcador-/)) {
        expect(elemento).toHaveProp('stopPropagation', true);
      }
      await fireEvent.press(screen.getByTestId('mapa'), { nativeEvent: { action: 'marker-press' } });
      expect(loQueDiceElMarcador()).toEqual(['Pedido 7K2M9QXA', 'En proceso · Recolección', 'Plaza de prueba Durango']);

      await fireEvent.press(screen.getByTestId('mapa'), { nativeEvent: { action: 'press' } });
      expect(screen.queryByTestId('marcador-elegido')).not.toBeOnTheScreen();

      // Otro marcador lo vuelve a decir.
      await fireEvent.press(marcador(CONFIRMADO, 'recoleccion'));
      expect(loQueDiceElMarcador()).toEqual([
        'Pedido R6T3N8VB',
        'Confirmado · Recolección y entrega',
        'Plaza de prueba Torreón',
      ]);
    },
  );
});
