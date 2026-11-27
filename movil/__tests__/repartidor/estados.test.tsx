// Spec: openspec/changes/repartidor/specs/repartidor/spec.md, repartidor-E19 y repartidor-E20 (tarea 2.4). El
// repartidor mueve el pedido que aceptó con los botones Recolectado → En tránsito → Entregado, en ese orden
// (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #3; componente 5 del design system). La tarjeta ofrece un solo
// botón, el del paso siguiente que da el servidor en "siguiente", y lo manda con POST
// /api/repartidor/pedidos/{id_publico}/estado; nunca uno de un salto ni de un regreso, y ninguno antes de aceptar. Entre
// Recolectado y Listo le toca a la lavandería (Módulo B #3): ahí el repartidor no tiene botón. Si ese ya no es el paso
// siguiente (409), el pedido no cambia: la app muestra el mensaje del servidor y vuelve a consultar.
//
// Cómo ve el usuario estos estados espera DD-022 y DD-140 (repartidor-E22). Se abre la app real con la sesión de una
// cuenta de repartidor activo, en iPhone y en Android, con el servidor simulado (__tests__/apoyo/repartidor.ts) y los
// temporizadores falsos de Jest, para que la consulta cada 30 segundos traiga lo que marca la lavandería.
import { act, fireEvent, screen, waitFor, within } from 'expo-router/testing-library';
import { AppState, type NativeEventSubscription } from 'react-native';

import { MENSAJES } from '../../src/acceso/mensajes';
import {
  abrirLaAppConSesion,
  pedidoDelRepartidor,
  PLATAFORMAS,
  PRIMERO,
  respuesta,
  RUTA_DE_LOS_PEDIDOS,
  SEGUNDO,
  usarElServidorDelRepartidor,
  type DatosDelPedido,
  type Plataforma,
} from '../apoyo/repartidor';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

// ADR-0007: cada 30 segundos.
const TREINTA_SEGUNDOS = 30_000;

const servidor = usarElServidorDelRepartidor('modulo-c-estados');

const ESTADO_DEL_PRIMERO = `/repartidor/pedidos/${PRIMERO.id_publico}/estado`;

beforeEach(() => {
  jest.useFakeTimers();
  jest.spyOn(AppState, 'addEventListener').mockImplementation(
    () => ({ remove: () => {} }) as NativeEventSubscription,
  );
});

afterEach(() => {
  jest.useRealTimers();
});

/** Avanza el reloj falso y deja que lleguen las respuestas. */
async function pasan(milisegundos: number) {
  await act(() => jest.advanceTimersByTimeAsync(milisegundos));
}

function tarjeta(pedido: DatosDelPedido) {
  return within(screen.getByTestId(`pedido-${pedido.id_publico}`));
}

function estadoDe(pedido: DatosDelPedido) {
  return within(tarjeta(pedido).getByTestId('estado-del-pedido'));
}

/** Los botones que ofrece la tarjeta, por su nombre. */
function botones(pedido: DatosDelPedido): string[] {
  return tarjeta(pedido)
    .queryAllByRole('button')
    .map((boton) => boton.props.accessibilityLabel ?? within(boton).queryAllByText(/.+/)[0]?.props.children);
}

async function abrirLosPedidos(plataforma: Plataforma) {
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  await screen.findByTestId(`pedido-${PRIMERO.id_publico}`);
}

async function tocar(pedido: DatosDelPedido, boton: string) {
  await fireEvent.press(tarjeta(pedido).getByRole('button', { name: boton }));
}

/**
 * Marca Entregado: el botón de la tarjeta abre la entrega, que deja tomar la foto de evidencia (repartidor-E23, tarea
 * 2.5); aquí se marca sin foto, que el servidor acepta mientras BR-29 y DD-110 no digan otra cosa (repartidor-E24).
 */
async function marcarEntregado(pedido: DatosDelPedido) {
  await tocar(pedido, 'Entregado');
  await waitFor(() => expect(screen).toHavePathname(`${RUTA_DE_LOS_PEDIDOS}/entrega/${pedido.id_publico}`));
  await fireEvent.press(await screen.findByRole('button', { name: 'Marcar entregado' }));
  await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
}

describe('repartidor-E19: el repartidor avanza el pedido en orden', () => {
  test.each(PLATAFORMAS)(
    'repartidor-E19 — (%s) marca Recolectado, luego En tránsito y luego Entregado, y el pedido pasa por los tres en ese orden',
    async (plataforma) => {
      servidor().poner('aceptado', PRIMERO);
      await abrirLosPedidos(plataforma);
      expect(botones(PRIMERO)).toEqual(['Recolectado']);

      await tocar(PRIMERO, 'Recolectado');

      await waitFor(() => expect(estadoDe(PRIMERO).getByText('Recolectado')).toBeOnTheScreen());
      // Entre Recolectado y Listo le toca a la lavandería: el repartidor no tiene botón.
      expect(botones(PRIMERO)).toEqual([]);
      expect(servidor().estadoDe(PRIMERO)).toBe('recolectado');

      // La lavandería marca En proceso y Listo; la consulta cada 30 segundos lo trae.
      servidor().poner('listo', PRIMERO);
      await pasan(TREINTA_SEGUNDOS);
      await waitFor(() => expect(botones(PRIMERO)).toEqual(['En tránsito']));

      await tocar(PRIMERO, 'En tránsito');

      await waitFor(() => expect(estadoDe(PRIMERO).getByText('En tránsito')).toBeOnTheScreen());
      expect(botones(PRIMERO)).toEqual(['Entregado']);
      expect(servidor().estadoDe(PRIMERO)).toBe('en_entrega');

      await marcarEntregado(PRIMERO);

      await waitFor(() => expect(servidor().estadoDe(PRIMERO)).toBe('entregado'));
      // Entregado ya no está en curso: sale de su lista y va a su historial (repartidor-E25).
      await waitFor(() => expect(screen.queryByTestId(`pedido-${PRIMERO.id_publico}`)).not.toBeOnTheScreen());
      expect(servidor().acciones().map(({ ruta, cuerpo }) => ({ ruta, estado: (cuerpo as { estado: string }).estado })))
        .toEqual([
          { ruta: ESTADO_DEL_PRIMERO, estado: 'recolectado' },
          { ruta: ESTADO_DEL_PRIMERO, estado: 'en_entrega' },
          { ruta: ESTADO_DEL_PRIMERO, estado: 'entregado' },
        ]);
    },
  );

  test.each(PLATAFORMAS)(
    'repartidor-E19 — (%s) si la lavandería ya marcó En proceso de entrega, su botón siguiente es Entregado',
    async (plataforma) => {
      servidor().poner('en_entrega', PRIMERO);

      await abrirLosPedidos(plataforma);

      expect(estadoDe(PRIMERO).getByText('En tránsito')).toBeOnTheScreen();
      expect(botones(PRIMERO)).toEqual(['Entregado']);
    },
  );

  test.each(PLATAFORMAS)(
    'repartidor-E19 — (%s) mientras manda, la tarjeta se bloquea: un doble toque marca una sola vez',
    async (plataforma) => {
      servidor().poner('aceptado', PRIMERO);
      await abrirLosPedidos(plataforma);
      let soltar: () => void = () => {};
      servidor().interceptar = ({ metodo, ruta }) => {
        if (metodo !== 'POST' || ruta !== ESTADO_DEL_PRIMERO) {
          return undefined;
        }
        return new Promise((resolver) => {
          soltar = () => {
            servidor().poner('recolectado', PRIMERO);
            servidor().interceptar = null;
            resolver(respuesta(200, { pedido: pedidoDelRepartidor('recolectado', 'aceptado', PRIMERO) }));
          };
        });
      };

      await tocar(PRIMERO, 'Recolectado');
      await tocar(PRIMERO, 'Recolectado');

      expect(servidor().acciones()).toHaveLength(1);
      expect(tarjeta(PRIMERO).getByRole('button', { name: 'Recolectado' })).toBeDisabled();

      await act(async () => soltar());

      await waitFor(() => expect(estadoDe(PRIMERO).getByText('Recolectado')).toBeOnTheScreen());
      expect(servidor().acciones()).toHaveLength(1);
    },
  );
});

describe('repartidor-E20: no se salta, no se retrocede y no se mueve antes de aceptar', () => {
  test.each(PLATAFORMAS)(
    'repartidor-E20 — (%s) sin aceptar no hay botón de estado: solo Aceptar y Rechazar',
    async (plataforma) => {
      servidor().ofrecer(PRIMERO, 'aceptado');

      await abrirLosPedidos(plataforma);

      expect(botones(PRIMERO)).toEqual(['Rechazar', 'Aceptar']);
    },
  );

  test('repartidor-E20 — en Recolectado, En proceso y Listo no hay botón de un salto ni de un regreso', async () => {
    servidor().poner('recolectado', PRIMERO);
    servidor().poner('en_proceso', SEGUNDO);

    await abrirLosPedidos('android');

    expect(botones(PRIMERO)).toEqual([]);
    expect(botones(SEGUNDO)).toEqual([]);
    expect(tarjeta(PRIMERO).queryByRole('button', { name: 'Entregado' })).not.toBeOnTheScreen();
    expect(tarjeta(PRIMERO).queryByRole('button', { name: 'En tránsito' })).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'repartidor-E20 — (%s) si ese ya no es el paso siguiente, muestra el mensaje del servidor, el pedido no cambia y lo vuelve a consultar',
    async (plataforma) => {
      servidor().poner('aceptado', PRIMERO);
      await abrirLosPedidos(plataforma);
      // Mientras tanto, la lavandería ya marcó Recolectado.
      servidor().poner('recolectado', PRIMERO);

      await tocar(PRIMERO, 'Recolectado');

      expect(await screen.findByText('Ese no es el paso siguiente del pedido.')).toBeOnTheScreen();
      await waitFor(() => expect(estadoDe(PRIMERO).getByText('Recolectado')).toBeOnTheScreen());
      expect(botones(PRIMERO)).toEqual([]);
      expect(servidor().estadoDe(PRIMERO)).toBe('recolectado');
      expect(servidor().acciones()).toHaveLength(1);
    },
  );

  test('repartidor-E20 — si su oferta sigue sin aceptar, muestra "Primero acepta el pedido." y vuelve a ofrecer Aceptar', async () => {
    servidor().poner('aceptado', PRIMERO);
    await abrirLosPedidos('ios');
    servidor().poner('aceptado', PRIMERO, 'ofrecido');

    await tocar(PRIMERO, 'Recolectado');

    expect(await screen.findByText('Primero acepta el pedido.')).toBeOnTheScreen();
    await waitFor(() => expect(botones(PRIMERO)).toEqual(['Rechazar', 'Aceptar']));
    expect(servidor().estadoDe(PRIMERO)).toBe('aceptado');
  });

  // Sin escenario propio: sin conexión, los mensajes generales de la app, y el pedido sigue igual.
  test('repartidor-E20 — sin conexión, lo dice con el mensaje general y el botón sigue a la mano', async () => {
    servidor().poner('aceptado', PRIMERO);
    await abrirLosPedidos('android');
    servidor().interceptar = ({ metodo }) => {
      if (metodo === 'POST') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await tocar(PRIMERO, 'Recolectado');

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(tarjeta(PRIMERO).getByRole('button', { name: 'Recolectado' })).toBeEnabled();
    expect(servidor().estadoDe(PRIMERO)).toBe('aceptado');
  });
});
