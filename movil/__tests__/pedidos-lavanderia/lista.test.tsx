// Spec: openspec/changes/pedidos-de-la-lavanderia/specs/pedidos-lavanderia/spec.md, pedidos-lavanderia-E03 (tarea 2.1).
// La pantalla de pedidos del Módulo B (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #2): cada pedido activo de
// la lavandería con la tarjeta de pedido de socio lavandería, el componente 4 del design system
// (vault/design-system/componentes.md; vault/design-system/canvas/PedidoLavanderia.dc.html), con su folio, su servicio,
// su fecha y su ventana de recolección en la hora de la plaza, y su estado con el nombre que le da la lavandería. Solo
// el pedido que espera respuesta ofrece Aceptar y Rechazar.
//
// Se abre la app real con la sesión de una cuenta de socio lavandería, en iPhone y en Android, con el servidor simulado
// con el contrato de GET /api/lavanderia/pedidos (__tests__/apoyo/lavanderia.ts). Los datos de recolección y del
// usuario esperan DD-022 y DD-014 (pedidos-lavanderia-E04): la API no los manda.
import { screen, waitFor, within } from 'expo-router/testing-library';

import { MENSAJES } from '../../src/acceso/mensajes';
import { color, radio } from '../../src/tema/theme';
import {
  abrirLaAppConSesion,
  NUEVO,
  OTRO,
  PLATAFORMAS,
  RUTA_DE_LOS_PEDIDOS,
  TERCERO,
  usarElServidorDeLaLavanderia,
  type DatosDelPedido,
  type Plataforma,
} from '../apoyo/lavanderia';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaLavanderia('lavanderia-lista');

/** La tarjeta de un pedido en la lista. */
function tarjeta(pedido: DatosDelPedido) {
  return screen.getByTestId(`pedido-${pedido.id_publico}`);
}

async function abrirLosPedidos(plataforma: Plataforma) {
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  await screen.findByRole('header', { name: 'Pedidos activos' });
  await waitFor(() => expect(servidor().consultasDeLaLista()).toBe(1));
}

describe('pedidos-lavanderia-E03: cómo se ve cada pedido en la app', () => {
  test.each(PLATAFORMAS)(
    'pedidos-lavanderia-E03 — (%s) cada pedido se ve con su folio, su estado, su servicio y su fecha y ventana de recolección',
    async (plataforma) => {
      servidor().poner('confirmado', NUEVO);
      servidor().poner('aceptado', OTRO);

      await abrirLosPedidos(plataforma);

      const nuevo = within(await screen.findByTestId(`pedido-${NUEVO.id_publico}`));
      expect(nuevo.getByText('Pedido 7KQ2M9XA')).toBeOnTheScreen();
      expect(within(nuevo.getByTestId('estado-del-pedido')).getByText('Por aceptar')).toBeOnTheScreen();
      // La ventana en la hora de la plaza, como la da el servidor, sin pasarla a la del teléfono.
      expect(nuevo.getByText('Recolección jueves 3 de diciembre, 11:00 – 12:00 · Lavado estándar')).toBeOnTheScreen();

      const aceptado = within(tarjeta(OTRO));
      expect(aceptado.getByText('Pedido 3HT8W2QZ')).toBeOnTheScreen();
      expect(within(aceptado.getByTestId('estado-del-pedido')).getByText('Aceptado')).toBeOnTheScreen();
      expect(aceptado.getByText('Recolección viernes 4 de diciembre, 09:00 – 10:00 · Planchado')).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'pedidos-lavanderia-E03 — (%s) solo el pedido que espera respuesta ofrece Aceptar y Rechazar',
    async (plataforma) => {
      servidor().poner('confirmado', NUEVO);
      servidor().poner('aceptado', OTRO);

      await abrirLosPedidos(plataforma);

      const nuevo = within(await screen.findByTestId(`pedido-${NUEVO.id_publico}`));
      expect(nuevo.getByRole('button', { name: 'Aceptar' })).toBeOnTheScreen();
      expect(nuevo.getByRole('button', { name: 'Rechazar' })).toBeOnTheScreen();
      // El componente 4 avisa que el rechazo exige motivo.
      expect(nuevo.getByText('Si rechazas, se te pedirá el motivo.')).toBeOnTheScreen();

      const aceptado = within(tarjeta(OTRO));
      expect(aceptado.queryByRole('button', { name: 'Aceptar' })).not.toBeOnTheScreen();
      expect(aceptado.queryByRole('button', { name: 'Rechazar' })).not.toBeOnTheScreen();
      expect(aceptado.queryByText('Si rechazas, se te pedirá el motivo.')).not.toBeOnTheScreen();
    },
  );

  test('pedidos-lavanderia-E03 — los pedidos van en el orden en que los da el servidor, de la recolección más próxima a la más lejana', async () => {
    servidor().poner('en_proceso', NUEVO);
    servidor().poner('confirmado', OTRO);
    servidor().poner('listo', TERCERO);

    await abrirLosPedidos('android');

    await screen.findByTestId(`pedido-${TERCERO.id_publico}`);
    const folios = screen.getAllByText(/^Pedido /).map((texto) => texto.props.children);
    expect(folios).toEqual(['Pedido 7KQ2M9XA', 'Pedido 3HT8W2QZ', 'Pedido 9PL4X7RB']);
  });

  // La insignia cuadrada es la de un estado operativo del módulo; la píldora queda para los cinco que ve el usuario
  // (canvas/PedidoLavanderia.dc.html). El color nunca va solo: el nombre va escrito.
  test('pedidos-lavanderia-E03 — el estado va en la insignia cuadrada del componente 4, con el color de "Por aceptar"', async () => {
    servidor().poner('confirmado', NUEVO);

    await abrirLosPedidos('ios');

    const insignia = within(await screen.findByTestId(`pedido-${NUEVO.id_publico}`)).getByTestId('estado-del-pedido');
    expect(insignia).toHaveStyle({ borderRadius: radio.chico, borderColor: color.estado.atencion });
    expect(within(insignia).getByText('Por aceptar')).toHaveStyle({ color: color.estado.atencion });
  });

  test('pedidos-lavanderia-E03 — la tarjeta va en la superficie, con el borde y el radio del design system', async () => {
    servidor().poner('confirmado', NUEVO);

    await abrirLosPedidos('android');

    expect(await screen.findByTestId(`pedido-${NUEVO.id_publico}`)).toHaveStyle({
      backgroundColor: color.semantico.superficie,
      borderColor: color.semantico.borde,
      borderRadius: radio.grande,
    });
  });

  // Sin escenario propio: la lista vacía con un mensaje neutro, como "Mis pedidos" (historial-E01).
  test('pedidos-lavanderia-E03 — sin pedidos activos, lo dice', async () => {
    await abrirLosPedidos('ios');

    expect(await screen.findByText('No tienes pedidos activos.')).toBeOnTheScreen();
  });

  // Sin escenario propio: si la lista no llega, los mensajes generales de la app, como el seguimiento y "Mis pedidos".
  test('pedidos-lavanderia-E03 — si la lista no llega, lo dice con el mensaje general de la app', async () => {
    servidor().interceptar = ({ ruta }) => {
      if (ruta === '/lavanderia/pedidos') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await abrirLaAppConSesion('android', RUTA_DE_LOS_PEDIDOS);

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(screen.queryByText('No tienes pedidos activos.')).not.toBeOnTheScreen();
  });

  test('pedidos-lavanderia-E03 — mientras llega la lista, lo dice', async () => {
    let soltar: () => void = () => {};
    servidor().interceptar = ({ ruta }) => {
      if (ruta !== '/lavanderia/pedidos') {
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
