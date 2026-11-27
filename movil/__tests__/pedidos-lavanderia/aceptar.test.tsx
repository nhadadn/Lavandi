// Spec: openspec/changes/pedidos-de-la-lavanderia/specs/pedidos-lavanderia/spec.md, pedidos-lavanderia-E07 (tarea 2.3),
// con la respuesta de la app al 409 de pedidos-lavanderia-E08. La lavandería acepta desde la tarjeta un pedido que
// espera su respuesta (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #2: "Aceptar o rechazar con motivo"): la
// app lo manda al servidor (POST /api/lavanderia/pedidos/{id_publico}/aceptar) y la tarjeta se ve aceptada, ya sin
// Aceptar ni Rechazar. Si el pedido ya fue respondido (409), la app muestra el mensaje del servidor y vuelve a
// consultar la lista. Mientras manda, la tarjeta se bloquea: un doble toque no manda dos veces.
//
// Se abre la app real con la sesión de una cuenta de socio lavandería, en iPhone y en Android, con el servidor simulado
// (__tests__/apoyo/lavanderia.ts), que acepta como el real: solo desde "Por aceptar".
import { fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

import { MENSAJES } from '../../src/acceso/mensajes';
import {
  abrirLaAppConSesion,
  NUEVO,
  OTRO,
  pedidoEn,
  PLATAFORMAS,
  respuesta,
  RUTA_DE_LOS_PEDIDOS,
  TERCERO,
  usarElServidorDeLaLavanderia,
  type DatosDelPedido,
  type Plataforma,
} from '../apoyo/lavanderia';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaLavanderia('lavanderia-aceptar');

const ACEPTAR_NUEVO = `/lavanderia/pedidos/${NUEVO.id_publico}/aceptar`;

function tarjeta(pedido: DatosDelPedido) {
  return within(screen.getByTestId(`pedido-${pedido.id_publico}`));
}

/** La insignia del estado en la tarjeta del pedido. */
function estadoEnLaTarjeta(pedido: DatosDelPedido) {
  return within(tarjeta(pedido).getByTestId('estado-del-pedido'));
}

async function abrirLosPedidos(plataforma: Plataforma) {
  servidor().poner('confirmado', NUEVO);
  servidor().poner('aceptado', OTRO);
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  await screen.findByTestId(`pedido-${NUEVO.id_publico}`);
  expect(servidor().consultasDeLaLista()).toBe(1);
}

async function tocarAceptar(pedido: DatosDelPedido = NUEVO) {
  await fireEvent.press(tarjeta(pedido).getByRole('button', { name: 'Aceptar' }));
}

describe('pedidos-lavanderia-E07: la lavandería acepta un pedido', () => {
  test.each(PLATAFORMAS)(
    'pedidos-lavanderia-E07 — (%s) al tocar Aceptar, la app lo manda y la tarjeta se ve aceptada, ya sin Aceptar ni Rechazar',
    async (plataforma) => {
      await abrirLosPedidos(plataforma);

      await tocarAceptar();

      await waitFor(() => expect(estadoEnLaTarjeta(NUEVO).getByText('Aceptado')).toBeOnTheScreen());
      expect(servidor().acciones()).toEqual([{ ruta: ACEPTAR_NUEVO, cuerpo: undefined }]);
      expect(servidor().estadoDe(NUEVO)).toBe('aceptado');
      // Sigue en la lista, como aceptado.
      expect(tarjeta(NUEVO).queryByRole('button', { name: 'Aceptar' })).not.toBeOnTheScreen();
      expect(tarjeta(NUEVO).queryByRole('button', { name: 'Rechazar' })).not.toBeOnTheScreen();
      expect(estadoEnLaTarjeta(OTRO).getByText('Aceptado')).toBeOnTheScreen();
    },
  );

  test('pedidos-lavanderia-E07 — la tarjeta se ve aceptada con la respuesta del servidor, sin esperar otra consulta', async () => {
    await abrirLosPedidos('android');
    // La consulta que sigue a la respuesta no llega: la tarjeta se pone al día con el pedido que devolvió el servidor.
    servidor().interceptar = ({ metodo, ruta }) => {
      if (metodo === 'GET' && ruta === '/lavanderia/pedidos') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await tocarAceptar();

    await waitFor(() => expect(estadoEnLaTarjeta(NUEVO).getByText('Aceptado')).toBeOnTheScreen());
    expect(tarjeta(NUEVO).queryByRole('button', { name: 'Aceptar' })).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'pedidos-lavanderia-E07 — (%s) mientras manda, la tarjeta se bloquea: un doble toque manda una sola vez',
    async (plataforma) => {
      await abrirLosPedidos(plataforma);
      let soltar: () => void = () => {};
      servidor().interceptar = ({ metodo, ruta }) => {
        if (metodo !== 'POST' || ruta !== ACEPTAR_NUEVO) {
          return undefined;
        }
        return new Promise((resolver) => {
          soltar = () => {
            servidor().poner('aceptado', NUEVO);
            resolver(respuesta(200, { pedido: pedidoEn('aceptado', NUEVO) }));
          };
        });
      };

      await tocarAceptar();
      await tocarAceptar();

      expect(servidor().acciones()).toHaveLength(1);
      expect(tarjeta(NUEVO).getByRole('button', { name: 'Aceptar' })).toBeDisabled();
      expect(tarjeta(NUEVO).getByRole('button', { name: 'Rechazar' })).toBeDisabled();

      soltar();

      await waitFor(() => expect(estadoEnLaTarjeta(NUEVO).getByText('Aceptado')).toBeOnTheScreen());
      expect(servidor().acciones()).toHaveLength(1);
    },
  );

  // Sin escenario propio: si la respuesta no llega, los mensajes generales de la app; el pedido sigue por responder.
  test('pedidos-lavanderia-E07 — sin conexión lo dice con el mensaje general y la tarjeta sigue ofreciendo Aceptar', async () => {
    await abrirLosPedidos('ios');
    servidor().interceptar = ({ metodo }) => {
      if (metodo === 'POST') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await tocarAceptar();

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(tarjeta(NUEVO).getByRole('button', { name: 'Aceptar' })).toBeEnabled();
    expect(servidor().estadoDe(NUEVO)).toBe('confirmado');
  });
});

describe('pedidos-lavanderia-E08 (en la app): el pedido ya fue respondido', () => {
  test.each(PLATAFORMAS)(
    'pedidos-lavanderia-E08 — (%s) si otro teléfono ya lo aceptó, muestra el mensaje del servidor y vuelve a consultar la lista',
    async (plataforma) => {
      await abrirLosPedidos(plataforma);
      // Mientras tanto, el pedido se aceptó desde otro teléfono de la lavandería.
      servidor().poner('aceptado', NUEVO);

      await tocarAceptar();

      expect(await screen.findByText('El pedido ya fue respondido.')).toBeOnTheScreen();
      await waitFor(() => expect(servidor().consultasDeLaLista()).toBe(2));
      await waitFor(() => expect(tarjeta(NUEVO).queryByRole('button', { name: 'Aceptar' })).not.toBeOnTheScreen());
      expect(estadoEnLaTarjeta(NUEVO).getByText('Aceptado')).toBeOnTheScreen();
    },
  );

  test('pedidos-lavanderia-E08 — si ya se rechazó, con la nueva consulta el pedido sale de la lista', async () => {
    await abrirLosPedidos('ios');
    servidor().poner('rechazado', NUEVO);

    await tocarAceptar();

    expect(await screen.findByText('El pedido ya fue respondido.')).toBeOnTheScreen();
    await waitFor(() => expect(screen.queryByTestId(`pedido-${NUEVO.id_publico}`)).not.toBeOnTheScreen());
    expect(screen.getByTestId(`pedido-${OTRO.id_publico}`)).toBeOnTheScreen();
  });

  test('pedidos-lavanderia-E08 — el mensaje se va al mandar la siguiente respuesta', async () => {
    await abrirLosPedidos('android');
    servidor().poner('aceptado', NUEVO);
    // Con la consulta que sigue al 409 llega otro pedido por responder.
    servidor().poner('confirmado', TERCERO);
    await tocarAceptar();
    expect(await screen.findByText('El pedido ya fue respondido.')).toBeOnTheScreen();
    await screen.findByTestId(`pedido-${TERCERO.id_publico}`);

    await tocarAceptar(TERCERO);

    await waitFor(() => expect(estadoEnLaTarjeta(TERCERO).getByText('Aceptado')).toBeOnTheScreen());
    expect(screen.queryByText('El pedido ya fue respondido.')).not.toBeOnTheScreen();
  });
});
