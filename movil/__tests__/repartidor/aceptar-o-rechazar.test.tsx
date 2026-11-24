// Spec: openspec/changes/repartidor/specs/repartidor/spec.md, repartidor-E15, repartidor-E16 y repartidor-E17 (tarea
// 2.3). El repartidor acepta o rechaza con motivo cada pedido que tiene asignado (NSV-2026-001-v8.pdf:18:ANEXO A, Sección
// A.3, Módulo C #2: "Aceptar o rechazar con motivo"; componente 5 del design system): el pedido que espera su respuesta
// ofrece Aceptar y Rechazar, con la nota de que el rechazo pide el motivo. Al aceptarlo (POST
// /api/repartidor/pedidos/{id_publico}/aceptar) se le habilita el botón de su paso siguiente; "Rechazar" abre el rechazo,
// con el pedido y el motivo obligatorio, como la imagen c02-rechazo de la Propuesta 04, y al rechazarlo (POST …/rechazar
// con { motivo }) el pedido deja de estar asignado a él y sale de su lista.
//
// Qué pasa después con el pedido rechazado espera DD-014 (repartidor-E18). Se abre la app real con la sesión de una
// cuenta de repartidor activo, en iPhone y en Android, con el servidor simulado (__tests__/apoyo/repartidor.ts).
import { fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

import { MENSAJES } from '../../src/acceso/mensajes';
import {
  abrirLaAppConSesion,
  AHORA,
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

const servidor = usarElServidorDelRepartidor('modulo-c-respuesta');

const RUTA_DEL_RECHAZO = `${RUTA_DE_LOS_PEDIDOS}/rechazo/${PRIMERO.id_publico}`;
const ACEPTAR_PRIMERO = `/repartidor/pedidos/${PRIMERO.id_publico}/aceptar`;
const RECHAZAR_PRIMERO = `/repartidor/pedidos/${PRIMERO.id_publico}/rechazar`;
const MOTIVO = 'Mi moto está en el taller.';

function tarjeta(pedido: DatosDelPedido) {
  return within(screen.getByTestId(`pedido-${pedido.id_publico}`));
}

async function abrirLosPedidos(plataforma: Plataforma) {
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  await screen.findByTestId(`pedido-${PRIMERO.id_publico}`);
}

async function aceptarElPrimero() {
  await fireEvent.press(tarjeta(PRIMERO).getByRole('button', { name: 'Aceptar' }));
}

describe('repartidor-E15: el repartidor acepta un pedido', () => {
  test.each(PLATAFORMAS)(
    'repartidor-E15 — (%s) al aceptarlo, la app manda la aceptación, el pedido queda aceptado por él y se le habilita el botón de su paso siguiente',
    async (plataforma) => {
      // La lavandería ya aceptó el pedido: el paso siguiente del repartidor es Recolectado.
      servidor().ofrecer(PRIMERO, 'aceptado');
      await abrirLosPedidos(plataforma);
      expect(tarjeta(PRIMERO).queryByRole('button', { name: 'Recolectado' })).not.toBeOnTheScreen();

      await aceptarElPrimero();

      const boton = await waitFor(() => tarjeta(PRIMERO).getByRole('button', { name: 'Recolectado' }));
      expect(boton).toBeEnabled();
      expect(tarjeta(PRIMERO).queryByRole('button', { name: 'Aceptar' })).not.toBeOnTheScreen();
      expect(tarjeta(PRIMERO).queryByRole('button', { name: 'Rechazar' })).not.toBeOnTheScreen();
      expect(tarjeta(PRIMERO).getByText('Aceptaste este pedido.')).toBeOnTheScreen();
      expect(servidor().acciones()).toEqual([{ ruta: ACEPTAR_PRIMERO, cuerpo: undefined, multipart: false }]);
      expect(servidor().ofertaDe(PRIMERO)).toBe('aceptado');
    },
  );

  test.each(PLATAFORMAS)(
    'repartidor-E15 — (%s) solo el pedido que espera su respuesta ofrece Aceptar y Rechazar, con la nota del rechazo',
    async (plataforma) => {
      servidor().ofrecer(PRIMERO);
      servidor().poner('recolectado', SEGUNDO);

      await abrirLosPedidos(plataforma);

      expect(tarjeta(PRIMERO).getByRole('button', { name: 'Aceptar' })).toBeOnTheScreen();
      expect(tarjeta(PRIMERO).getByRole('button', { name: 'Rechazar' })).toBeOnTheScreen();
      expect(tarjeta(PRIMERO).getByText('Si rechazas, se te pedirá el motivo.')).toBeOnTheScreen();

      expect(tarjeta(SEGUNDO).queryByRole('button', { name: 'Aceptar' })).not.toBeOnTheScreen();
      expect(tarjeta(SEGUNDO).queryByRole('button', { name: 'Rechazar' })).not.toBeOnTheScreen();
      expect(tarjeta(SEGUNDO).queryByText('Si rechazas, se te pedirá el motivo.')).not.toBeOnTheScreen();
      expect(tarjeta(SEGUNDO).getByText('Aceptaste este pedido.')).toBeOnTheScreen();
    },
  );

  // La oferta puede llegar antes de que la lavandería responda el pedido (design.md, "Aceptar o rechazar"): se acepta,
  // y su botón aparece cuando la lavandería lo acepte.
  test('repartidor-E15 — si la lavandería todavía no acepta el pedido, lo acepta y su botón espera a la lavandería', async () => {
    servidor().ofrecer(PRIMERO, 'confirmado');
    await abrirLosPedidos('android');

    await aceptarElPrimero();

    expect(await waitFor(() => tarjeta(PRIMERO).getByText('Aceptaste este pedido.'))).toBeOnTheScreen();
    expect(tarjeta(PRIMERO).queryAllByRole('button')).toEqual([]);
    expect(servidor().ofertaDe(PRIMERO)).toBe('aceptado');
  });

  test.each(PLATAFORMAS)(
    'repartidor-E15 — (%s) mientras manda, la tarjeta se bloquea: un doble toque acepta una sola vez',
    async (plataforma) => {
      servidor().ofrecer(PRIMERO);
      await abrirLosPedidos(plataforma);
      let soltar: () => void = () => {};
      servidor().interceptar = ({ metodo, ruta }) => {
        if (metodo !== 'POST' || ruta !== ACEPTAR_PRIMERO) {
          return undefined;
        }
        return new Promise((resolver) => {
          soltar = () => {
            servidor().poner('aceptado', PRIMERO, 'aceptado');
            servidor().interceptar = null;
            resolver(respuesta(200, { pedido: pedidoDelRepartidor('aceptado', 'aceptado', PRIMERO) }));
          };
        });
      };

      await aceptarElPrimero();
      await aceptarElPrimero();

      expect(servidor().acciones()).toHaveLength(1);
      expect(tarjeta(PRIMERO).getByRole('button', { name: 'Aceptar' })).toBeDisabled();
      expect(tarjeta(PRIMERO).getByRole('button', { name: 'Rechazar' })).toBeDisabled();

      soltar();

      expect(await waitFor(() => tarjeta(PRIMERO).getByRole('button', { name: 'Recolectado' }))).toBeOnTheScreen();
      expect(servidor().acciones()).toHaveLength(1);
    },
  );

  // Sin escenario propio: la oferta ya se respondió desde otro teléfono (409). El mensaje del servidor va arriba de la
  // lista y la lista se vuelve a consultar, para verlo como está.
  test('repartidor-E15 — si la oferta ya fue respondida, muestra el mensaje del servidor y lo muestra como está', async () => {
    servidor().ofrecer(PRIMERO);
    await abrirLosPedidos('ios');
    servidor().poner('aceptado', PRIMERO, 'aceptado');

    await aceptarElPrimero();

    expect(await screen.findByText('La oferta ya fue respondida.')).toBeOnTheScreen();
    await waitFor(() => expect(tarjeta(PRIMERO).queryByRole('button', { name: 'Aceptar' })).not.toBeOnTheScreen());
    expect(tarjeta(PRIMERO).getByRole('button', { name: 'Recolectado' })).toBeOnTheScreen();
  });

  // Sin escenario propio: sin conexión, los mensajes generales de la app, y el pedido sigue igual.
  test('repartidor-E15 — sin conexión, lo dice con el mensaje general y el pedido sigue por responder', async () => {
    servidor().ofrecer(PRIMERO);
    await abrirLosPedidos('android');
    servidor().interceptar = ({ metodo }) => {
      if (metodo === 'POST') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await aceptarElPrimero();

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(tarjeta(PRIMERO).getByRole('button', { name: 'Aceptar' })).toBeEnabled();
    expect(servidor().ofertaDe(PRIMERO)).toBe('ofrecido');
  });
});

/** Abre la lista, toca "Rechazar" en el primero y espera la pantalla del rechazo. */
async function abrirElRechazo(plataforma: Plataforma) {
  servidor().ofrecer(PRIMERO);
  servidor().poner('recolectado', SEGUNDO);
  await abrirLosPedidos(plataforma);
  await fireEvent.press(tarjeta(PRIMERO).getByRole('button', { name: 'Rechazar' }));
  await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_RECHAZO));
  await screen.findByRole('header', { name: 'Rechazar pedido' });
}

async function escribirElMotivo(motivo: string) {
  await fireEvent.changeText(screen.getByLabelText('Motivo del rechazo'), motivo);
}

describe('repartidor-E16: el repartidor rechaza un pedido con motivo', () => {
  test.each(PLATAFORMAS)(
    'repartidor-E16 — (%s) "Rechazar" abre el rechazo con el pedido; con el motivo, lo manda y el pedido deja de estar asignado a él',
    async (plataforma) => {
      await abrirElRechazo(plataforma);
      // El pedido que se rechaza, con la tarjeta del componente 5, sin sus botones.
      expect(tarjeta(PRIMERO).getByText('Pedido 7KQ2M9XA')).toBeOnTheScreen();
      expect(tarjeta(PRIMERO).queryByRole('button', { name: 'Aceptar' })).not.toBeOnTheScreen();

      await escribirElMotivo(`  ${MOTIVO} `);
      await fireEvent.press(screen.getByRole('button', { name: 'Rechazar pedido' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
      expect(servidor().acciones()).toEqual([{ ruta: RECHAZAR_PRIMERO, cuerpo: { motivo: MOTIVO }, multipart: false }]);
      // El rechazo queda registrado con su motivo y su hora (repartidor-E16, en el servidor).
      expect(servidor().ofertaDe(PRIMERO)).toBe('rechazado');
      expect(servidor().motivoDe(PRIMERO)).toBe(MOTIVO);
      expect(screen.queryByTestId(`pedido-${PRIMERO.id_publico}`)).not.toBeOnTheScreen();
      expect(await screen.findByTestId(`pedido-${SEGUNDO.id_publico}`)).toBeOnTheScreen();
    },
  );

  test('repartidor-E16 — el pedido sale de la lista en cuanto el servidor registra el rechazo, sin esperar otra consulta', async () => {
    await abrirElRechazo('android');
    servidor().interceptar = ({ metodo, ruta }) => {
      if (metodo === 'GET' && ruta === '/repartidor/pedidos') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await escribirElMotivo(MOTIVO);
    await fireEvent.press(screen.getByRole('button', { name: 'Rechazar pedido' }));

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
    expect(screen.queryByTestId(`pedido-${PRIMERO.id_publico}`)).not.toBeOnTheScreen();
    expect(screen.getByTestId(`pedido-${SEGUNDO.id_publico}`)).toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'repartidor-E16 — (%s) mientras manda, el botón se bloquea: un doble toque rechaza una sola vez',
    async (plataforma) => {
      await abrirElRechazo(plataforma);
      let soltar: () => void = () => {};
      servidor().interceptar = ({ metodo, ruta }) => {
        if (metodo !== 'POST' || ruta !== RECHAZAR_PRIMERO) {
          return undefined;
        }
        return new Promise((resolver) => {
          soltar = () =>
            resolver(
              respuesta(200, {
                asignacion: { pedido: PRIMERO.id_publico, estado: 'rechazado', motivo: MOTIVO, respondido_en: AHORA },
              }),
            );
        });
      };
      await escribirElMotivo(MOTIVO);

      await fireEvent.press(screen.getByRole('button', { name: 'Rechazar pedido' }));
      await fireEvent.press(screen.getByRole('button', { name: 'Rechazar pedido' }));

      expect(servidor().acciones()).toHaveLength(1);
      expect(screen.getByRole('button', { name: 'Rechazar pedido' })).toBeDisabled();

      soltar();

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
      expect(servidor().acciones()).toHaveLength(1);
    },
  );

  // Sin escenario propio: "Regresar" vuelve a la lista sin rechazar nada.
  test('repartidor-E16 — "Regresar" vuelve a la lista sin rechazar el pedido', async () => {
    await abrirElRechazo('ios');
    await escribirElMotivo(MOTIVO);

    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
    expect(servidor().acciones()).toEqual([]);
    expect(await screen.findByTestId(`pedido-${PRIMERO.id_publico}`)).toBeOnTheScreen();
  });
});

describe('repartidor-E17: un rechazo sin motivo no se acepta', () => {
  test.each(PLATAFORMAS)(
    'repartidor-E17 — (%s) con el motivo vacío, la app no deja confirmar el rechazo y dice qué falta',
    async (plataforma) => {
      await abrirElRechazo(plataforma);

      const boton = screen.getByRole('button', { name: 'Escribe el motivo' });
      expect(boton).toBeDisabled();
      await fireEvent.press(boton);

      expect(servidor().acciones()).toEqual([]);
      expect(screen).toHavePathname(RUTA_DEL_RECHAZO);
      expect(servidor().ofertaDe(PRIMERO)).toBe('ofrecido');
    },
  );

  test('repartidor-E17 — un motivo de puros espacios tampoco deja confirmar', async () => {
    await abrirElRechazo('android');

    await escribirElMotivo('    ');

    expect(screen.getByRole('button', { name: 'Escribe el motivo' })).toBeDisabled();
    expect(screen.queryByRole('button', { name: 'Rechazar pedido' })).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'repartidor-E17 — (%s) si el servidor rechaza el motivo (422), su error va junto al campo y el pedido sigue asignado a él',
    async (plataforma) => {
      await abrirElRechazo(plataforma);
      servidor().interceptar = ({ metodo, ruta }) =>
        metodo === 'POST' && ruta === RECHAZAR_PRIMERO
          ? respuesta(422, {
              message: 'El motivo no puede pasar de 500 caracteres.',
              errors: { motivo: ['El motivo no puede pasar de 500 caracteres.'] },
            })
          : undefined;

      await escribirElMotivo('x'.repeat(501));
      await fireEvent.press(screen.getByRole('button', { name: 'Rechazar pedido' }));

      expect(await within(screen.getByTestId('campo-motivo')).findByRole('alert')).toHaveTextContent(
        'El motivo no puede pasar de 500 caracteres.',
      );
      expect(screen).toHavePathname(RUTA_DEL_RECHAZO);
      expect(servidor().ofertaDe(PRIMERO)).toBe('ofrecido');
    },
  );

  // Sin escenario propio: ya la aceptó desde otro teléfono (409).
  test('repartidor-E17 — si ya la aceptó, muestra el mensaje del servidor y no la rechaza', async () => {
    await abrirElRechazo('ios');
    servidor().poner('aceptado', PRIMERO, 'aceptado');

    await escribirElMotivo(MOTIVO);
    await fireEvent.press(screen.getByRole('button', { name: 'Rechazar pedido' }));

    expect(await screen.findByText('La oferta ya fue respondida.')).toBeOnTheScreen();
    expect(screen).toHavePathname(RUTA_DEL_RECHAZO);
    expect(servidor().ofertaDe(PRIMERO)).toBe('aceptado');
  });
});
