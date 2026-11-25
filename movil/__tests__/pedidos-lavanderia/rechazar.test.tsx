// Spec: openspec/changes/pedidos-de-la-lavanderia/specs/pedidos-lavanderia/spec.md, pedidos-lavanderia-E12 y
// pedidos-lavanderia-E13 (tarea 2.4), con la respuesta de la app al 409 de pedidos-lavanderia-E08. El rechazo exige
// motivo (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #2: "Aceptar o rechazar con motivo"; componente 4 del
// design system): "Rechazar" abre la pantalla del rechazo, con el pedido y el motivo obligatorio, como la imagen
// b02-rechazo de la Propuesta 04. Sin motivo, la app no deja confirmar; los errores del servidor (422) van en el campo;
// al rechazar (POST /api/lavanderia/pedidos/{id_publico}/rechazar con { motivo }), el pedido sale de la lista.
//
// Qué pasa después con el pedido, el cobro y lo que ve el usuario espera DD-016, DD-012 y DD-103
// (pedidos-lavanderia-E14). Se abre la app real con la sesión de una cuenta de socio lavandería, en iPhone y en
// Android, con el servidor simulado (__tests__/apoyo/lavanderia.ts).
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
  usarElServidorDeLaLavanderia,
  type Plataforma,
} from '../apoyo/lavanderia';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaLavanderia('lavanderia-rechazar');

const RUTA_DEL_RECHAZO = `${RUTA_DE_LOS_PEDIDOS}/rechazo/${NUEVO.id_publico}`;
const RECHAZAR_NUEVO = `/lavanderia/pedidos/${NUEVO.id_publico}/rechazar`;
const MOTIVO = 'No tenemos cupo esa mañana.';

/** Abre la lista, toca "Rechazar" en el pedido nuevo y espera la pantalla del rechazo. */
async function abrirElRechazo(plataforma: Plataforma) {
  servidor().poner('confirmado', NUEVO);
  servidor().poner('aceptado', OTRO);
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  const tarjeta = within(await screen.findByTestId(`pedido-${NUEVO.id_publico}`));
  await fireEvent.press(tarjeta.getByRole('button', { name: 'Rechazar' }));
  await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_RECHAZO));
  await screen.findByRole('header', { name: 'Rechazar pedido' });
}

async function escribirElMotivo(motivo: string) {
  await fireEvent.changeText(screen.getByLabelText('Motivo del rechazo'), motivo);
}

describe('pedidos-lavanderia-E12: la lavandería rechaza con motivo', () => {
  test.each(PLATAFORMAS)(
    'pedidos-lavanderia-E12 — (%s) "Rechazar" abre el rechazo con el pedido; con el motivo, lo manda y el pedido sale de la lista',
    async (plataforma) => {
      await abrirElRechazo(plataforma);
      // El pedido que se rechaza, con la tarjeta del componente 4, sin sus botones.
      const pedido = within(screen.getByTestId(`pedido-${NUEVO.id_publico}`));
      expect(pedido.getByText('Pedido 7KQ2M9XA')).toBeOnTheScreen();
      expect(pedido.getByText('Recolección jueves 3 de diciembre, 11:00 – 12:00 · Lavado estándar')).toBeOnTheScreen();
      expect(pedido.queryByRole('button', { name: 'Aceptar' })).not.toBeOnTheScreen();

      await escribirElMotivo(`  ${MOTIVO} `);
      await fireEvent.press(screen.getByRole('button', { name: 'Rechazar pedido' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
      expect(servidor().acciones()).toEqual([{ ruta: RECHAZAR_NUEVO, cuerpo: { motivo: MOTIVO } }]);
      expect(servidor().estadoDe(NUEVO)).toBe('rechazado');
      expect(screen.queryByTestId(`pedido-${NUEVO.id_publico}`)).not.toBeOnTheScreen();
      expect(await screen.findByTestId(`pedido-${OTRO.id_publico}`)).toBeOnTheScreen();
    },
  );

  test('pedidos-lavanderia-E12 — el pedido sale de la lista en cuanto el servidor lo rechaza, sin esperar otra consulta', async () => {
    await abrirElRechazo('android');
    // La consulta al volver a la lista no llega: el pedido ya no se ve de todos modos.
    servidor().interceptar = ({ metodo, ruta }) => {
      if (metodo === 'GET' && ruta === '/lavanderia/pedidos') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await escribirElMotivo(MOTIVO);
    await fireEvent.press(screen.getByRole('button', { name: 'Rechazar pedido' }));

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
    expect(screen.queryByTestId(`pedido-${NUEVO.id_publico}`)).not.toBeOnTheScreen();
    expect(screen.getByTestId(`pedido-${OTRO.id_publico}`)).toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'pedidos-lavanderia-E12 — (%s) mientras manda, el botón se bloquea: un doble toque manda una sola vez',
    async (plataforma) => {
      await abrirElRechazo(plataforma);
      let soltar: () => void = () => {};
      servidor().interceptar = ({ metodo, ruta }) => {
        if (metodo !== 'POST' || ruta !== RECHAZAR_NUEVO) {
          return undefined;
        }
        return new Promise((resolver) => {
          soltar = () => {
            servidor().poner('rechazado', NUEVO);
            resolver(respuesta(200, { pedido: pedidoEn('rechazado', NUEVO) }));
          };
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
  test('pedidos-lavanderia-E12 — "Regresar" vuelve a la lista sin rechazar el pedido', async () => {
    await abrirElRechazo('ios');
    await escribirElMotivo(MOTIVO);

    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
    expect(servidor().acciones()).toEqual([]);
    expect(await screen.findByTestId(`pedido-${NUEVO.id_publico}`)).toBeOnTheScreen();
  });
});

describe('pedidos-lavanderia-E13: sin motivo no hay rechazo', () => {
  test.each(PLATAFORMAS)(
    'pedidos-lavanderia-E13 — (%s) con el motivo vacío, la app no deja confirmar el rechazo y dice qué falta',
    async (plataforma) => {
      await abrirElRechazo(plataforma);

      const boton = screen.getByRole('button', { name: 'Escribe el motivo' });
      expect(boton).toBeDisabled();
      await fireEvent.press(boton);

      expect(servidor().acciones()).toEqual([]);
      expect(screen).toHavePathname(RUTA_DEL_RECHAZO);
    },
  );

  test('pedidos-lavanderia-E13 — un motivo de puros espacios tampoco deja confirmar', async () => {
    await abrirElRechazo('android');

    await escribirElMotivo('    ');

    expect(screen.getByRole('button', { name: 'Escribe el motivo' })).toBeDisabled();
    expect(screen.queryByRole('button', { name: 'Rechazar pedido' })).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'pedidos-lavanderia-E13 — (%s) si el servidor rechaza el motivo (422), su error va junto al campo y el pedido no cambia',
    async (plataforma) => {
      await abrirElRechazo(plataforma);
      servidor().interceptar = ({ metodo, ruta }) =>
        metodo === 'POST' && ruta === RECHAZAR_NUEVO
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
      expect(servidor().estadoDe(NUEVO)).toBe('confirmado');
    },
  );
});

describe('pedidos-lavanderia-E08 (en la app): rechazar un pedido ya respondido', () => {
  test('pedidos-lavanderia-E08 — si ya se aceptó desde otro teléfono, muestra el mensaje del servidor y no lo rechaza', async () => {
    await abrirElRechazo('ios');
    servidor().poner('aceptado', NUEVO);

    await escribirElMotivo(MOTIVO);
    await fireEvent.press(screen.getByRole('button', { name: 'Rechazar pedido' }));

    expect(await screen.findByText('El pedido ya fue respondido.')).toBeOnTheScreen();
    expect(screen).toHavePathname(RUTA_DEL_RECHAZO);
    expect(servidor().estadoDe(NUEVO)).toBe('aceptado');

    // De vuelta en la lista, la consulta lo muestra como está.
    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
    const tarjeta = within(screen.getByTestId(`pedido-${NUEVO.id_publico}`));
    const estado = () => within(tarjeta.getByTestId('estado-del-pedido'));
    await waitFor(() => expect(estado().getByText('Aceptado')).toBeOnTheScreen());
  });

  // Sin escenario propio: sin conexión, los mensajes generales de la app; el pedido sigue igual.
  test('pedidos-lavanderia-E12 — sin conexión lo dice con el mensaje general y se queda en el rechazo', async () => {
    await abrirElRechazo('android');
    servidor().interceptar = ({ metodo }) => {
      if (metodo === 'POST') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await escribirElMotivo(MOTIVO);
    await fireEvent.press(screen.getByRole('button', { name: 'Rechazar pedido' }));

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(screen).toHavePathname(RUTA_DEL_RECHAZO);
    expect(screen.getByRole('button', { name: 'Rechazar pedido' })).toBeEnabled();
  });
});
