// Spec: openspec/changes/repartidor/specs/repartidor/spec.md, repartidor-E10 y repartidor-E11 (tarea 2.2), con la
// respuesta de la app al 403 de repartidor-E03 y E06. Los pedidos asignados del repartidor "en tiempo real"
// (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #2): cada pedido con la tarjeta de pedido de socio repartidor,
// el componente 5 del design system (vault/design-system/componentes.md; canvas/PedidoRepartidor.dc.html), con su folio,
// su ventana, su dirección de recogida y de entrega y su lavandería; la lista se pone al día con una consulta cada 30
// segundos mientras la pantalla está visible y la app al frente (ADR-0007), y ninguna con la pantalla oculta o la app al
// fondo.
//
// Se abre la app real con la sesión de una cuenta de repartidor activo, en iPhone y en Android, con el servidor simulado
// con el contrato de GET /api/repartidor/pedidos (__tests__/apoyo/repartidor.ts). Quién asigna el pedido espera DD-014
// (repartidor-E13): la oferta la siembra la prueba en el servidor, como la fábrica en las pruebas del servidor. El aviso
// con la app cerrada espera DD-054 (repartidor-E14).
import { router } from 'expo-router';
import { act, screen, waitFor, within } from 'expo-router/testing-library';
import { AppState, type AppStateStatus, type NativeEventSubscription } from 'react-native';

import { MENSAJES } from '../../src/acceso/mensajes';
import { color, radio } from '../../src/tema/theme';
import {
  abrirLaAppConSesion,
  MENSAJES_SIN_ACCESO,
  PLATAFORMAS,
  PRIMERO,
  RUTA_DE_LA_ESPERA,
  RUTA_DE_LOS_PEDIDOS,
  SEGUNDO,
  TERCERO,
  usarElServidorDelRepartidor,
  type DatosDelPedido,
  type Plataforma,
} from '../apoyo/repartidor';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

// ADR-0007: cada 30 segundos.
const TREINTA_SEGUNDOS = 30_000;

const servidor = usarElServidorDelRepartidor('modulo-c-asignados');

// La lista corre con los temporizadores falsos de Jest en todo el archivo, como la consulta de la lavandería: la
// consulta cada 30 segundos (repartidor-E11) se prueba avanzando el reloj.
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

function enLaLista(pedido: DatosDelPedido) {
  return screen.queryByTestId(`pedido-${pedido.id_publico}`);
}

async function abrirConUnPedido(plataforma: Plataforma) {
  servidor().ofrecer(PRIMERO);
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  await screen.findByTestId(`pedido-${PRIMERO.id_publico}`);
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

/** La tarjeta de un pedido en la lista. */
function tarjeta(pedido: DatosDelPedido) {
  return within(screen.getByTestId(`pedido-${pedido.id_publico}`));
}

/** Un dato de la tarjeta: su etiqueta, su valor y lo que va debajo. */
function dato(pedido: DatosDelPedido, cual: string) {
  return within(tarjeta(pedido).getByTestId(`dato-${cual}`));
}

async function abrirLosPedidos(plataforma: Plataforma) {
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  await screen.findByRole('header', { name: 'Pedidos asignados' });
  await waitFor(() => expect(servidor().consultasDeLaLista()).toBe(1));
}

describe('repartidor-E10: el repartidor ve sus pedidos asignados', () => {
  test.each(PLATAFORMAS)(
    'repartidor-E10 — (%s) ve sus dos pedidos, cada uno con su folio, su ventana, su recogida, su entrega y su lavandería',
    async (plataforma) => {
      servidor().ofrecer(PRIMERO);
      servidor().poner('listo', SEGUNDO);

      await abrirLosPedidos(plataforma);

      await screen.findByTestId(`pedido-${PRIMERO.id_publico}`);
      expect(tarjeta(PRIMERO).getByText('Pedido 7KQ2M9XA')).toBeOnTheScreen();
      // La ventana en la hora de la plaza, como la da el servidor, sin pasarla a la del teléfono.
      expect(dato(PRIMERO, 'recoleccion').getByText('Recolección')).toBeOnTheScreen();
      expect(dato(PRIMERO, 'recoleccion').getByText('Jueves 3 de diciembre · 11:00 – 12:00')).toBeOnTheScreen();
      expect(dato(PRIMERO, 'recogida').getByText('Recoger en')).toBeOnTheScreen();
      expect(dato(PRIMERO, 'recogida').getByText('Calle de prueba 123, int. 4, Colonia de prueba')).toBeOnTheScreen();
      expect(dato(PRIMERO, 'recogida').getByText('Durango · Portón verde')).toBeOnTheScreen();
      expect(dato(PRIMERO, 'entrega').getByText('Entregar en')).toBeOnTheScreen();
      expect(dato(PRIMERO, 'entrega').getByText('Avenida de prueba 900, Zona Centro')).toBeOnTheScreen();
      expect(dato(PRIMERO, 'lavanderia').getByText('Lavandería')).toBeOnTheScreen();
      expect(dato(PRIMERO, 'lavanderia').getByText('Lavandería de prueba Durango Norte')).toBeOnTheScreen();
      expect(dato(PRIMERO, 'lavanderia').getByText('Calle de la lavandería 45, Del Norte')).toBeOnTheScreen();

      expect(tarjeta(SEGUNDO).getByText('Pedido 3HT8W2QZ')).toBeOnTheScreen();
      expect(dato(SEGUNDO, 'recoleccion').getByText('Viernes 4 de diciembre · 09:00 – 10:00')).toBeOnTheScreen();
      expect(dato(SEGUNDO, 'recogida').getByText('Avenida de prueba 900, Zona Centro')).toBeOnTheScreen();
      // Sin entrega distinta, el servidor manda la misma: no se repite.
      expect(dato(SEGUNDO, 'entrega').getByText('La misma de la recogida')).toBeOnTheScreen();
      expect(dato(SEGUNDO, 'lavanderia').getByText('Lavandería de prueba Durango Norte')).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'repartidor-E10 — (%s) cada pedido lleva su estado con el nombre que le da el servidor, y "En tránsito" en lugar de En proceso de entrega',
    async (plataforma) => {
      servidor().ofrecer(PRIMERO, 'confirmado');
      servidor().poner('en_entrega', SEGUNDO);

      await abrirLosPedidos(plataforma);

      await screen.findByTestId(`pedido-${PRIMERO.id_publico}`);
      expect(within(tarjeta(PRIMERO).getByTestId('estado-del-pedido')).getByText('Confirmado')).toBeOnTheScreen();
      expect(within(tarjeta(SEGUNDO).getByTestId('estado-del-pedido')).getByText('En tránsito')).toBeOnTheScreen();
    },
  );

  test('repartidor-E10 — los pedidos van en el orden en que los da el servidor, de la recolección más próxima a la más lejana', async () => {
    servidor().poner('listo', TERCERO);
    servidor().ofrecer(PRIMERO);
    servidor().poner('recolectado', SEGUNDO);

    await abrirLosPedidos('android');

    await screen.findByTestId(`pedido-${SEGUNDO.id_publico}`);
    const folios = screen.getAllByText(/^Pedido /).map((texto) => texto.props.children);
    expect(folios).toEqual(['Pedido 9PL4X7RB', 'Pedido 7KQ2M9XA', 'Pedido 3HT8W2QZ']);
  });

  // La insignia cuadrada es la de un estado operativo del módulo (canvas/PedidoRepartidor.dc.html): el que espera su
  // respuesta lleva el color de Confirmado y En tránsito el de Recolectado, como en el canvas. El nombre va escrito.
  test('repartidor-E10 — el estado va en la insignia cuadrada del componente 5, con el color del canvas', async () => {
    servidor().ofrecer(PRIMERO);
    servidor().poner('en_entrega', SEGUNDO);

    await abrirLosPedidos('ios');

    const confirmado = within(await screen.findByTestId(`pedido-${PRIMERO.id_publico}`)).getByTestId('estado-del-pedido');
    expect(confirmado).toHaveStyle({ borderRadius: radio.chico, borderColor: color.pedido.confirmado });
    const enTransito = tarjeta(SEGUNDO).getByTestId('estado-del-pedido');
    expect(enTransito).toHaveStyle({ borderRadius: radio.chico, borderColor: color.pedido.recolectado });
    expect(within(enTransito).getByText('En tránsito')).toHaveStyle({ color: color.pedido.recolectado });
  });

  test('repartidor-E10 — la tarjeta va en la superficie, con el borde y el radio del design system', async () => {
    servidor().ofrecer(PRIMERO);

    await abrirLosPedidos('android');

    expect(await screen.findByTestId(`pedido-${PRIMERO.id_publico}`)).toHaveStyle({
      backgroundColor: color.semantico.superficie,
      borderColor: color.semantico.borde,
      borderRadius: radio.grande,
    });
  });

  // Sin escenario propio: la lista vacía con un mensaje neutro, como la de la lavandería.
  test('repartidor-E10 — sin pedidos asignados, lo dice', async () => {
    await abrirLosPedidos('ios');

    expect(await screen.findByText('No tienes pedidos asignados.')).toBeOnTheScreen();
  });

  // Sin escenario propio: si la lista no llega, los mensajes generales de la app.
  test('repartidor-E10 — si la lista no llega, lo dice con el mensaje general de la app', async () => {
    servidor().interceptar = ({ ruta }) => {
      if (ruta === '/repartidor/pedidos') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await abrirLaAppConSesion('android', RUTA_DE_LOS_PEDIDOS);

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(screen.queryByText('No tienes pedidos asignados.')).not.toBeOnTheScreen();
  });

  test('repartidor-E10 — mientras llega la lista, lo dice', async () => {
    let soltar: () => void = () => {};
    servidor().interceptar = ({ ruta }) => {
      if (ruta !== '/repartidor/pedidos') {
        return undefined;
      }
      return new Promise<never>((_resolver, rechazar) => {
        soltar = () => rechazar(new TypeError('Network request failed'));
      });
    };

    await abrirLaAppConSesion('ios', RUTA_DE_LOS_PEDIDOS);

    expect(await screen.findByText('Cargando tus pedidos…')).toBeOnTheScreen();
    soltar();
    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
  });
});

describe('repartidor-E11: un pedido recién asignado aparece solo', () => {
  test.each(PLATAFORMAS)(
    'repartidor-E11 — (%s) el pedido recién asignado aparece con la siguiente consulta, a los 30 segundos de la anterior, sin que haga nada',
    async (plataforma) => {
      await abrirConUnPedido(plataforma);

      // Se le asigna un pedido nuevo (la oferta la siembra la prueba: DD-014).
      servidor().ofrecer(SEGUNDO);
      await pasan(TREINTA_SEGUNDOS - 1);
      expect(consultas()).toBe(1);
      expect(enLaLista(SEGUNDO)).not.toBeOnTheScreen();

      await pasan(1);

      expect(consultas()).toBe(2);
      const nuevo = await screen.findByTestId(`pedido-${SEGUNDO.id_publico}`);
      expect(within(nuevo).getByText('Pedido 3HT8W2QZ')).toBeOnTheScreen();
    },
  );

  test('repartidor-E11 — sigue consultando cada 30 segundos mientras la pantalla está visible', async () => {
    await abrirConUnPedido('android');

    await pasan(TREINTA_SEGUNDOS);
    servidor().ofrecer(SEGUNDO);
    await pasan(TREINTA_SEGUNDOS);
    expect(await screen.findByTestId(`pedido-${SEGUNDO.id_publico}`)).toBeOnTheScreen();
    await pasan(TREINTA_SEGUNDOS);

    expect(consultas()).toBe(4);
  });

  // Sin escenario propio: si una consulta no llega, se queda la última lista con el mensaje general de la app.
  test('repartidor-E11 — si una consulta no llega, conserva la última lista, lo dice y la siguiente la pone al día', async () => {
    await abrirConUnPedido('ios');

    servidor().interceptar = () => {
      throw new TypeError('Network request failed');
    };
    await pasan(TREINTA_SEGUNDOS);
    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(enLaLista(PRIMERO)).toBeOnTheScreen();

    servidor().interceptar = null;
    servidor().ofrecer(SEGUNDO);
    await pasan(TREINTA_SEGUNDOS);

    expect(await screen.findByTestId(`pedido-${SEGUNDO.id_publico}`)).toBeOnTheScreen();
    expect(screen.queryByText(MENSAJES.sinConexion)).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'repartidor-E11 — (%s) al cambiar de pantalla deja de consultar, y al volver consulta y retoma el ciclo de 30 segundos',
    async (plataforma) => {
      await abrirConUnPedido(plataforma);

      await act(() => router.push('/informativa/1'));
      await waitFor(() => expect(screen).toHavePathname('/informativa/1'));
      servidor().ofrecer(SEGUNDO);
      await pasan(3 * TREINTA_SEGUNDOS);
      expect(consultas()).toBe(1);

      await act(() => router.back());
      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));

      expect(consultas()).toBe(2);
      expect(await screen.findByTestId(`pedido-${SEGUNDO.id_publico}`)).toBeOnTheScreen();
      await pasan(TREINTA_SEGUNDOS - 1);
      expect(consultas()).toBe(2);
      await pasan(1);
      expect(consultas()).toBe(3);
    },
  );

  test.each(PLATAFORMAS)(
    'repartidor-E11 — (%s) con la app al fondo deja de consultar, y al traerla al frente consulta y retoma el ciclo',
    async (plataforma) => {
      await abrirConUnPedido(plataforma);

      await mandarLaAppA('background');
      servidor().ofrecer(SEGUNDO);
      await pasan(3 * TREINTA_SEGUNDOS);
      expect(consultas()).toBe(1);

      await mandarLaAppA('active');

      expect(consultas()).toBe(2);
      expect(await screen.findByTestId(`pedido-${SEGUNDO.id_publico}`)).toBeOnTheScreen();
      await pasan(TREINTA_SEGUNDOS);
      expect(consultas()).toBe(3);
    },
  );

  // repartidor-E03 y E06 con la lista abierta: si la administración lo suspende, la siguiente consulta responde 403 con
  // su estado; la app vuelve a pedir su perfil y lo lleva a la espera, sin seguir consultando.
  test.each(PLATAFORMAS)(
    'repartidor-E03 — (%s) si lo suspenden con la lista abierta, la siguiente consulta lo lleva a la espera y deja de consultar',
    async (plataforma) => {
      await abrirConUnPedido(plataforma);

      servidor().estado = 'suspendido';
      await pasan(TREINTA_SEGUNDOS);

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));
      expect(await screen.findByText(MENSAJES_SIN_ACCESO.suspendido)).toBeOnTheScreen();
      await pasan(3 * TREINTA_SEGUNDOS);
      expect(consultas()).toBe(2);
    },
  );
});
