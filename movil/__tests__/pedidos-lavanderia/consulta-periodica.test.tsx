// Spec: openspec/changes/pedidos-de-la-lavanderia/specs/pedidos-lavanderia/spec.md, pedidos-lavanderia-E05 y
// pedidos-lavanderia-E06 (tarea 2.2). La lista "en tiempo real" de la lavandería (NSV-2026-001-v8.pdf:17:ANEXO A,
// Sección A.3, Módulo B #2) se pone al día con una consulta cada 30 segundos mientras la pantalla de pedidos está
// visible y la app al frente (ADR-0007), y ninguna con la pantalla oculta o la app al fondo; al volver, consulta de
// inmediato y retoma el ciclo. Es la misma forma del seguimiento del usuario (src/estados/useSeguimiento.ts).
//
// Se abre la app real con la sesión de una cuenta de socio lavandería, en iPhone y en Android, con el servidor simulado
// (__tests__/apoyo/lavanderia.ts) y los temporizadores falsos de Jest. El aviso de pedido nuevo que adelanta la
// consulta espera DD-054 (pedidos-lavanderia-E15 y E16).
import { router } from 'expo-router';
import { act, screen, waitFor, within } from 'expo-router/testing-library';
import { AppState, type AppStateStatus, type NativeEventSubscription } from 'react-native';

import { MENSAJES } from '../../src/acceso/mensajes';
import {
  abrirLaAppConSesion,
  NUEVO,
  OTRO,
  PLATAFORMAS,
  RUTA_DE_LOS_PEDIDOS,
  usarElServidorDeLaLavanderia,
  type DatosDelPedido,
  type Plataforma,
} from '../apoyo/lavanderia';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

// ADR-0007: cada 30 segundos.
const TREINTA_SEGUNDOS = 30_000;

const servidor = usarElServidorDeLaLavanderia('lavanderia-consulta');

// Los oyentes de AppState que registró la app, para mandarla al fondo y traerla al frente.
let oyentesDeLaApp: ((estado: AppStateStatus) => void)[] = [];

async function mandarLaAppA(estado: AppStateStatus) {
  await act(async () => {
    for (const oyente of [...oyentesDeLaApp]) {
      oyente(estado);
    }
  });
}

/** Avanza el reloj falso y deja que lleguen las respuestas. */
async function pasan(milisegundos: number) {
  await act(() => jest.advanceTimersByTimeAsync(milisegundos));
}

function consultas(): number {
  return servidor().consultasDeLaLista();
}

/** Si el pedido está en la lista de la pantalla. */
function enLaLista(pedido: DatosDelPedido) {
  return screen.queryByTestId(`pedido-${pedido.id_publico}`);
}

async function abrirLosPedidos(plataforma: Plataforma) {
  servidor().poner('confirmado', NUEVO);
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  await screen.findByTestId(`pedido-${NUEVO.id_publico}`);
  expect(consultas()).toBe(1);
}

beforeEach(() => {
  jest.useFakeTimers();
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
  jest.useRealTimers();
});

describe('pedidos-lavanderia-E05: un pedido nuevo aparece sin recargar', () => {
  test.each(PLATAFORMAS)(
    'pedidos-lavanderia-E05 — (%s) el pedido nuevo aparece con la siguiente consulta, a los 30 segundos de la anterior',
    async (plataforma) => {
      await abrirLosPedidos(plataforma);

      // El servidor recibe un pedido nuevo para la lavandería.
      servidor().poner('confirmado', OTRO);
      await pasan(TREINTA_SEGUNDOS - 1);
      expect(consultas()).toBe(1);
      expect(enLaLista(OTRO)).not.toBeOnTheScreen();

      await pasan(1);

      expect(consultas()).toBe(2);
      const nuevo = await screen.findByTestId(`pedido-${OTRO.id_publico}`);
      expect(within(nuevo).getByText('Pedido 3HT8W2QZ')).toBeOnTheScreen();
      expect(within(nuevo).getByRole('button', { name: 'Aceptar' })).toBeOnTheScreen();
    },
  );

  test('pedidos-lavanderia-E05 — sigue consultando cada 30 segundos mientras la pantalla está visible', async () => {
    await abrirLosPedidos('android');

    await pasan(TREINTA_SEGUNDOS);
    servidor().poner('confirmado', OTRO);
    await pasan(TREINTA_SEGUNDOS);
    expect(await screen.findByTestId(`pedido-${OTRO.id_publico}`)).toBeOnTheScreen();
    await pasan(TREINTA_SEGUNDOS);

    expect(consultas()).toBe(4);
  });

  // Sin escenario propio: si una consulta no llega, la pantalla conserva la última lista y usa el mensaje general de la
  // app; la siguiente que llega lo quita, como el seguimiento del usuario.
  test('pedidos-lavanderia-E05 — si una consulta no llega, conserva la última lista, lo dice y la siguiente la pone al día', async () => {
    await abrirLosPedidos('ios');

    servidor().interceptar = () => {
      throw new TypeError('Network request failed');
    };
    await pasan(TREINTA_SEGUNDOS);
    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(enLaLista(NUEVO)).toBeOnTheScreen();

    servidor().interceptar = null;
    servidor().poner('confirmado', OTRO);
    await pasan(TREINTA_SEGUNDOS);

    expect(await screen.findByTestId(`pedido-${OTRO.id_publico}`)).toBeOnTheScreen();
    expect(screen.queryByText(MENSAJES.sinConexion)).not.toBeOnTheScreen();
  });
});

describe('pedidos-lavanderia-E06: sin consultas con la pantalla oculta', () => {
  test.each(PLATAFORMAS)(
    'pedidos-lavanderia-E06 — (%s) al cambiar de pantalla deja de consultar, y al volver consulta y retoma el ciclo de 30 segundos',
    async (plataforma) => {
      await abrirLosPedidos(plataforma);

      await act(() => router.push('/informativa/1'));
      await waitFor(() => expect(screen).toHavePathname('/informativa/1'));
      servidor().poner('confirmado', OTRO);
      await pasan(3 * TREINTA_SEGUNDOS);
      expect(consultas()).toBe(1);

      await act(() => router.back());
      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));

      expect(consultas()).toBe(2);
      expect(await screen.findByTestId(`pedido-${OTRO.id_publico}`)).toBeOnTheScreen();

      await pasan(TREINTA_SEGUNDOS - 1);
      expect(consultas()).toBe(2);
      await pasan(1);
      expect(consultas()).toBe(3);
    },
  );

  test.each(PLATAFORMAS)(
    'pedidos-lavanderia-E06 — (%s) con la app al fondo deja de consultar, y al traerla al frente consulta y retoma el ciclo',
    async (plataforma) => {
      await abrirLosPedidos(plataforma);

      await mandarLaAppA('background');
      servidor().poner('confirmado', OTRO);
      await pasan(3 * TREINTA_SEGUNDOS);
      expect(consultas()).toBe(1);

      await mandarLaAppA('active');

      expect(consultas()).toBe(2);
      expect(await screen.findByTestId(`pedido-${OTRO.id_publico}`)).toBeOnTheScreen();

      await pasan(TREINTA_SEGUNDOS);
      expect(consultas()).toBe(3);
    },
  );

  test('pedidos-lavanderia-E06 — al cerrar la pantalla deja de consultar', async () => {
    await abrirLosPedidos('android');

    await act(() => router.replace('/informativa/1'));
    await waitFor(() => expect(screen).toHavePathname('/informativa/1'));
    await pasan(3 * TREINTA_SEGUNDOS);

    expect(consultas()).toBe(1);
  });
});
