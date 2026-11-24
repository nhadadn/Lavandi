// Spec: openspec/changes/historial-de-la-lavanderia/specs/historial-lavanderia/spec.md, historial-lavanderia-E01
// (tarea 2.1). El historial de pedidos del Módulo B (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #4): la
// lavandería ve sus pedidos completados, los que llegaron a Entregado, cada uno con su folio y la fecha en que se
// entregó, en la fecha de la plaza que da el servidor; el que sigue en proceso no aparece. Se abre desde la pantalla de
// pedidos. El monto y la calificación recibida no aparecen: esperan DD-013, DD-020, DD-012 (historial-lavanderia-E03) y
// DD-052 (historial-lavanderia-E04).
//
// Se abre la app real con la sesión de una cuenta de socio lavandería, en iPhone y en Android, con el servidor simulado
// con el contrato de GET /api/lavanderia/historial (__tests__/apoyo/lavanderia.ts; design.md del cambio, "Contrato de la
// API"). Que la API no entregue pedidos de otra lavandería ni de otra plaza (historial-lavanderia-E02) lo prueba el
// servidor: la app muestra los que le da.
import { fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

import { MENSAJES } from '../../src/acceso/mensajes';
import {
  abrirLaAppConSesion,
  NUEVO,
  OTRO,
  PLATAFORMAS,
  RUTA_DE_LOS_PEDIDOS,
  TERCERO,
  TOKEN,
  usarElServidorDeLaLavanderia,
  type Plataforma,
} from '../apoyo/lavanderia';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaLavanderia('historial-lavanderia');

const RUTA_DEL_HISTORIAL = '/lavanderia/historial';

/**
 * Dos pedidos entregados y uno que sigue en proceso. NUEVO se entregó a las 19:30 del jueves 3 de diciembre en la plaza:
 * en UTC ya era el viernes 4, y su fecha es el jueves 3.
 */
function dosEntregadosYUnoEnProceso() {
  servidor().entregar(NUEVO, { entregado_en: '2026-12-04T01:30:00Z', fecha: '2026-12-03' });
  servidor().entregar(OTRO, { entregado_en: '2026-12-02T17:00:00Z', fecha: '2026-12-02' });
  servidor().poner('en_proceso', TERCERO);
}

/** Abre los pedidos de la lavandería y, desde ahí, su historial. */
async function abrirElHistorial(plataforma: Plataforma) {
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  await screen.findByRole('header', { name: 'Pedidos activos' });
  await fireEvent.press(screen.getByRole('link', { name: 'Historial de pedidos' }));
  await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_HISTORIAL));
  await screen.findByRole('header', { name: 'Historial de pedidos' });
}

function consultasDelHistorial() {
  return servidor()
    .peticiones()
    .filter(({ metodo, ruta }) => metodo === 'GET' && ruta === '/lavanderia/historial');
}

describe('historial-lavanderia-E01: la lavandería ve sus pedidos completados', () => {
  test.each(PLATAFORMAS)(
    'historial-lavanderia-E01 — (%s) desde sus pedidos abre el historial y ve los dos Entregados, cada uno con su folio y la fecha en que se entregó',
    async (plataforma) => {
      dosEntregadosYUnoEnProceso();

      await abrirElHistorial(plataforma);

      const nuevo = within(await screen.findByTestId(`entregado-${NUEVO.id_publico}`));
      expect(nuevo.getByText('Pedido 7KQ2M9XA')).toBeOnTheScreen();
      // La fecha de la plaza, como la da el servidor: no el viernes 4 del instante en UTC.
      expect(nuevo.getByText('Entregado el jueves 3 de diciembre')).toBeOnTheScreen();
      const otro = within(screen.getByTestId(`entregado-${OTRO.id_publico}`));
      expect(otro.getByText('Pedido 3HT8W2QZ')).toBeOnTheScreen();
      expect(otro.getByText('Entregado el miércoles 2 de diciembre')).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)('historial-lavanderia-E01 — (%s) no ve el pedido que sigue en proceso', async (plataforma) => {
    dosEntregadosYUnoEnProceso();

    await abrirElHistorial(plataforma);

    await screen.findByTestId(`entregado-${NUEVO.id_publico}`);
    expect(screen.queryByTestId(`entregado-${TERCERO.id_publico}`)).not.toBeOnTheScreen();
    expect(screen.queryByText('Pedido 9PL4X7RB')).not.toBeOnTheScreen();
  });

  test('historial-lavanderia-E01 — pide el historial con la sesión y lo muestra en el orden del servidor, de la entrega más reciente a la más antigua', async () => {
    dosEntregadosYUnoEnProceso();

    await abrirElHistorial('android');

    await screen.findByTestId(`entregado-${NUEVO.id_publico}`);
    expect(consultasDelHistorial().map(({ autorizacion }) => autorizacion)).toEqual([`Bearer ${TOKEN}`]);
    const folios = screen.getAllByText(/^Pedido /).map((texto) => texto.props.children);
    expect(folios).toEqual(['Pedido 7KQ2M9XA', 'Pedido 3HT8W2QZ']);
  });

  // Lo que todavía no hay: el monto (DD-013, DD-020 y DD-012) y la calificación recibida (DD-052).
  test('historial-lavanderia-E01 — el pedido no muestra monto ni calificación, que esperan su respuesta', async () => {
    dosEntregadosYUnoEnProceso();

    await abrirElHistorial('ios');

    const nuevo = within(await screen.findByTestId(`entregado-${NUEVO.id_publico}`));
    expect(nuevo.queryByText(/monto|\$/i)).not.toBeOnTheScreen();
    expect(nuevo.queryByText(/calificaci/i)).not.toBeOnTheScreen();
  });

  test('historial-lavanderia-E01 — el lector de pantalla lee cada pedido completo, con su folio y su fecha', async () => {
    dosEntregadosYUnoEnProceso();

    await abrirElHistorial('ios');

    expect(await screen.findByTestId(`entregado-${NUEVO.id_publico}`)).toHaveProp(
      'accessibilityLabel',
      'Pedido 7KQ2M9XA, entregado el jueves 3 de diciembre',
    );
  });

  // Sin escenario propio: el historial vacío, con un mensaje neutro, como "Mis pedidos".
  test('historial-lavanderia-E01 — sin pedidos entregados, lo dice', async () => {
    servidor().poner('en_proceso', TERCERO);

    await abrirElHistorial('android');

    expect(await screen.findByText('Todavía no tienes pedidos entregados.')).toBeOnTheScreen();
  });

  // Sin escenario propio: si el historial no llega, los mensajes generales de la app.
  test('historial-lavanderia-E01 — si el historial no llega, lo dice con el mensaje general de la app', async () => {
    servidor().interceptar = ({ ruta }) => {
      if (ruta === '/lavanderia/historial') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await abrirElHistorial('ios');

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(screen.queryByText('Todavía no tienes pedidos entregados.')).not.toBeOnTheScreen();
  });

  test('historial-lavanderia-E01 — mientras llega el historial, lo dice', async () => {
    let soltar: () => void = () => {};
    servidor().interceptar = ({ ruta }) => {
      if (ruta !== '/lavanderia/historial') {
        return undefined;
      }
      return new Promise<never>((_resolver, rechazar) => {
        soltar = () => rechazar(new TypeError('Network request failed'));
      });
    };

    await abrirElHistorial('android');

    expect(await screen.findByText('Cargando tu historial…')).toBeOnTheScreen();
    soltar();
    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
  });

  test('historial-lavanderia-E01 — "Regresar" vuelve a los pedidos', async () => {
    dosEntregadosYUnoEnProceso();
    await abrirElHistorial('android');

    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
    expect(await screen.findByRole('header', { name: 'Pedidos activos' })).toBeOnTheScreen();
  });
});
