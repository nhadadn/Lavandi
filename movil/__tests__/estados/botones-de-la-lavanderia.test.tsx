// Spec: openspec/changes/estados-del-pedido/specs/estados/spec.md, estados-E06 (tarea 5.1). La tarjeta de pedido de
// socio lavandería (componente 4 del design system) ofrece solo el botón del paso siguiente: el que da la API en
// "siguiente", con su texto, de los cuatro del contrato, Recolectado, En proceso, Listo y En proceso de entrega
// (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #3). Nunca se ven botones de saltos ni de regresos. Al
// tocarlo, la app manda el paso (POST /api/lavanderia/pedidos/{id_publico}/estado con { estado }) y la tarjeta pasa al
// estado nuevo; mientras manda, el botón se bloquea. Si el servidor dice que ese no es el paso siguiente (409,
// estados-E05 y E09), la app muestra su mensaje y vuelve a consultar la lista.
//
// Entregado no es un botón de la lavandería mientras DD-022 siga abierta (estados-E13): desde En proceso de entrega no
// hay botón. Se abre la app real con la sesión de una cuenta de socio lavandería, en iPhone y en Android, con el
// servidor simulado (__tests__/apoyo/lavanderia.ts), que mueve el pedido como el real (ADR-0021 y su adenda).
import { fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

import { MENSAJES } from '../../src/acceso/mensajes';
import {
  abrirLaAppConSesion,
  NUEVO,
  pedidoEn,
  PLATAFORMAS,
  respuesta,
  RUTA_DE_LOS_PEDIDOS,
  usarElServidorDeLaLavanderia,
  type CodigoOperativo,
  type Plataforma,
} from '../apoyo/lavanderia';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaLavanderia('estados-botones');

const ESTADO_DE_NUEVO = `/lavanderia/pedidos/${NUEVO.id_publico}/estado`;

function tarjeta() {
  return within(screen.getByTestId(`pedido-${NUEVO.id_publico}`));
}

/** Los nombres de los botones de la tarjeta, en orden. */
function botonesDeLaTarjeta(): string[] {
  return tarjeta()
    .queryAllByRole('button')
    .map((boton) => within(boton).queryAllByText(/.+/).map((texto) => texto.props.children).join(''));
}

function estadoEnLaTarjeta() {
  return within(tarjeta().getByTestId('estado-del-pedido'));
}

async function abrirElPedidoEn(codigo: CodigoOperativo, plataforma: Plataforma = 'android') {
  servidor().poner(codigo, NUEVO);
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  await screen.findByTestId(`pedido-${NUEVO.id_publico}`);
}

describe('estados-E06: la app de la lavandería ofrece solo el paso siguiente', () => {
  test.each(PLATAFORMAS)(
    'estados-E06 — (%s) con el pedido aceptado en En proceso, el único botón que puede tocar es "Listo"',
    async (plataforma) => {
      await abrirElPedidoEn('en_proceso', plataforma);

      expect(estadoEnLaTarjeta().getByText('En proceso')).toBeOnTheScreen();
      expect(botonesDeLaTarjeta()).toEqual(['Listo']);
      expect(tarjeta().getByRole('button', { name: 'Listo' })).toBeEnabled();
    },
  );

  test.each([
    ['aceptado', 'Aceptado', ['Recolectado']],
    ['recolectado', 'Recolectado', ['En proceso']],
    ['en_proceso', 'En proceso', ['Listo']],
    ['listo', 'Listo', ['En proceso de entrega']],
    // Entregado espera DD-022 (estados-E13): la lavandería no tiene botón que tocar.
    ['en_entrega', 'En proceso de entrega', []],
    // Sin aceptar no hay botones de estado: solo la respuesta (estados-E09).
    ['confirmado', 'Por aceptar', ['Rechazar', 'Aceptar']],
  ] as const)('estados-E06 — en "%s" la tarjeta dice "%s" y ofrece %j', async (codigo, nombre, botones) => {
    await abrirElPedidoEn(codigo);

    expect(estadoEnLaTarjeta().getByText(nombre)).toBeOnTheScreen();
    expect(botonesDeLaTarjeta()).toEqual(botones);
  });

  test('estados-E06 — el botón lleva el texto que da la API, no uno de la app', async () => {
    servidor().interceptar = ({ metodo, ruta }) => {
      if (metodo !== 'GET' || ruta !== '/lavanderia/pedidos') {
        return undefined;
      }
      const pedido = pedidoEn('en_proceso', NUEVO);
      return respuesta(200, { pedidos: [{ ...pedido, siguiente: { estado: 'listo', boton: 'Texto del servidor' } }] });
    };

    await abrirElPedidoEn('en_proceso');

    expect(botonesDeLaTarjeta()).toEqual(['Texto del servidor']);
  });

  test.each(PLATAFORMAS)(
    'estados-E06 — (%s) al tocarlo, la app manda el paso siguiente y la tarjeta pasa al estado nuevo, con su siguiente botón',
    async (plataforma) => {
      await abrirElPedidoEn('en_proceso', plataforma);

      await fireEvent.press(tarjeta().getByRole('button', { name: 'Listo' }));

      await waitFor(() => expect(estadoEnLaTarjeta().getByText('Listo')).toBeOnTheScreen());
      expect(servidor().acciones()).toEqual([{ ruta: ESTADO_DE_NUEVO, cuerpo: { estado: 'listo' } }]);
      expect(servidor().estadoDe(NUEVO)).toBe('listo');
      expect(botonesDeLaTarjeta()).toEqual(['En proceso de entrega']);
    },
  );

  test('estados-E06 — de aceptado a En proceso de entrega, siempre con un solo botón, el del paso siguiente', async () => {
    await abrirElPedidoEn('aceptado', 'ios');

    for (const [boton, nombre] of [
      ['Recolectado', 'Recolectado'],
      ['En proceso', 'En proceso'],
      ['Listo', 'Listo'],
      ['En proceso de entrega', 'En proceso de entrega'],
    ] as const) {
      expect(botonesDeLaTarjeta()).toEqual([boton]);
      await fireEvent.press(tarjeta().getByRole('button', { name: boton }));
      await waitFor(() => expect(estadoEnLaTarjeta().getByText(nombre)).toBeOnTheScreen());
    }

    expect(botonesDeLaTarjeta()).toEqual([]);
    expect(servidor().acciones().map(({ cuerpo }) => cuerpo)).toEqual([
      { estado: 'recolectado' },
      { estado: 'en_proceso' },
      { estado: 'listo' },
      { estado: 'en_entrega' },
    ]);
  });

  test.each(PLATAFORMAS)(
    'estados-E06 — (%s) mientras manda, el botón se bloquea: un doble toque manda una sola vez',
    async (plataforma) => {
      await abrirElPedidoEn('en_proceso', plataforma);
      let soltar: () => void = () => {};
      servidor().interceptar = ({ metodo, ruta }) => {
        if (metodo !== 'POST' || ruta !== ESTADO_DE_NUEVO) {
          return undefined;
        }
        return new Promise((resolver) => {
          soltar = () => {
            servidor().poner('listo', NUEVO);
            resolver(respuesta(200, { pedido: pedidoEn('listo', NUEVO) }));
          };
        });
      };

      await fireEvent.press(tarjeta().getByRole('button', { name: 'Listo' }));
      await fireEvent.press(tarjeta().getByRole('button', { name: 'Listo' }));

      expect(servidor().acciones()).toHaveLength(1);
      expect(tarjeta().getByRole('button', { name: 'Listo' })).toBeDisabled();

      soltar();

      await waitFor(() => expect(estadoEnLaTarjeta().getByText('Listo')).toBeOnTheScreen());
      expect(servidor().acciones()).toHaveLength(1);
    },
  );
});

describe('estados-E05 y estados-E09 (en la app): el servidor dice que ese no es el paso siguiente', () => {
  test.each(PLATAFORMAS)(
    'estados-E05 — (%s) si el pedido ya avanzó desde otro teléfono, muestra el mensaje y vuelve a consultar la lista',
    async (plataforma) => {
      await abrirElPedidoEn('en_proceso', plataforma);
      // Mientras tanto, otro teléfono de la lavandería marcó Listo.
      servidor().poner('listo', NUEVO);

      await fireEvent.press(tarjeta().getByRole('button', { name: 'Listo' }));

      expect(await screen.findByText('Ese no es el paso siguiente del pedido.')).toBeOnTheScreen();
      await waitFor(() => expect(servidor().consultasDeLaLista()).toBe(2));
      await waitFor(() => expect(botonesDeLaTarjeta()).toEqual(['En proceso de entrega']));
      expect(estadoEnLaTarjeta().getByText('Listo')).toBeOnTheScreen();
      expect(servidor().estadoDe(NUEVO)).toBe('listo');
    },
  );

  // Sin escenario propio: sin conexión, los mensajes generales de la app; el pedido y su botón siguen igual.
  test('estados-E06 — sin conexión lo dice con el mensaje general y el botón sigue', async () => {
    await abrirElPedidoEn('recolectado', 'ios');
    servidor().interceptar = ({ metodo }) => {
      if (metodo === 'POST') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await fireEvent.press(tarjeta().getByRole('button', { name: 'En proceso' }));

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(tarjeta().getByRole('button', { name: 'En proceso' })).toBeEnabled();
    expect(servidor().estadoDe(NUEVO)).toBe('recolectado');
  });
});
