// Spec: openspec/changes/historial-de-la-lavanderia/specs/historial-lavanderia/spec.md, historial-lavanderia-E05
// (tarea 2.2). Las estadísticas básicas del Módulo B (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #5): la
// lavandería ve cuántos pedidos completó en el día y en la semana, como los cuenta el servidor en la zona horaria de la
// plaza, con la semana de lunes a domingo (design.md del cambio, decisiones 2 y 3). Se abren desde la pantalla de
// pedidos. El ingreso total y la calificación promedio no aparecen: esperan DD-013, DD-020, DD-012
// (historial-lavanderia-E07) y DD-052 (historial-lavanderia-E08).
//
// Se abre la app real con la sesión de una cuenta de socio lavandería, en iPhone y en Android, con el servidor simulado
// con el contrato de GET /api/lavanderia/estadisticas (__tests__/apoyo/lavanderia.ts). Que los conteos no incluyan
// pedidos de otra lavandería (historial-lavanderia-E06) lo prueba el servidor: la app muestra los que le da.
import { fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

import { MENSAJES } from '../../src/acceso/mensajes';
import {
  abrirLaAppConSesion,
  PLATAFORMAS,
  RUTA_DE_LOS_PEDIDOS,
  TOKEN,
  usarElServidorDeLaLavanderia,
  type Plataforma,
} from '../apoyo/lavanderia';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaLavanderia('estadisticas-lavanderia');

const RUTA_DE_LAS_ESTADISTICAS = '/lavanderia/estadisticas';
// La ruta de la API, relativa a su dirección base: GET /api/lavanderia/estadisticas.
const API_DE_LAS_ESTADISTICAS = '/lavanderia/estadisticas';

/** Abre los pedidos de la lavandería y, desde ahí, sus estadísticas. */
async function abrirLasEstadisticas(plataforma: Plataforma) {
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  await screen.findByRole('header', { name: 'Pedidos activos' });
  await fireEvent.press(screen.getByRole('link', { name: 'Estadísticas' }));
  await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LAS_ESTADISTICAS));
  await screen.findByRole('header', { name: 'Estadísticas' });
}

/** La cifra de una fila de las estadísticas: "Pedidos completados hoy" o "esta semana". */
function cifra(fila: 'del-dia' | 'de-la-semana') {
  return within(screen.getByTestId(`pedidos-${fila}`));
}

describe('historial-lavanderia-E05: pedidos del día y de la semana', () => {
  test.each(PLATAFORMAS)(
    'historial-lavanderia-E05 — (%s) con 2 pedidos entregados hoy y 3 más en la semana, ve 2 del día y 5 de la semana',
    async (plataforma) => {
      // Lo que cuenta el servidor: 2 de hoy, 3 de días anteriores de esta semana y 1 de la pasada, que no cuenta.
      servidor().estadisticas = { pedidos_del_dia: 2, pedidos_de_la_semana: 5 };

      await abrirLasEstadisticas(plataforma);

      await waitFor(() => expect(cifra('del-dia').getByText('2')).toBeOnTheScreen());
      expect(cifra('del-dia').getByText('Pedidos completados hoy')).toBeOnTheScreen();
      expect(cifra('de-la-semana').getByText('5')).toBeOnTheScreen();
      expect(cifra('de-la-semana').getByText('Pedidos completados esta semana')).toBeOnTheScreen();
    },
  );

  test('historial-lavanderia-E05 — pide las estadísticas con la sesión', async () => {
    servidor().estadisticas = { pedidos_del_dia: 2, pedidos_de_la_semana: 5 };

    await abrirLasEstadisticas('ios');

    await waitFor(() => expect(cifra('del-dia').getByText('2')).toBeOnTheScreen());
    const consultas = servidor()
      .peticiones()
      .filter(({ metodo, ruta }) => metodo === 'GET' && ruta === API_DE_LAS_ESTADISTICAS);
    expect(consultas.map(({ autorizacion }) => autorizacion)).toEqual([`Bearer ${TOKEN}`]);
  });

  test('historial-lavanderia-E05 — la semana se explica: va de lunes a domingo, en la hora de la plaza', async () => {
    servidor().estadisticas = { pedidos_del_dia: 2, pedidos_de_la_semana: 5 };

    await abrirLasEstadisticas('android');

    expect(await screen.findByText('La semana va de lunes a domingo.')).toBeOnTheScreen();
  });

  test('historial-lavanderia-E05 — el lector de pantalla lee cada cifra con lo que cuenta', async () => {
    servidor().estadisticas = { pedidos_del_dia: 1, pedidos_de_la_semana: 4 };

    await abrirLasEstadisticas('ios');

    await waitFor(() =>
      expect(screen.getByTestId('pedidos-del-dia')).toHaveProp('accessibilityLabel', 'Pedidos completados hoy: 1'),
    );
    expect(screen.getByTestId('pedidos-de-la-semana')).toHaveProp(
      'accessibilityLabel',
      'Pedidos completados esta semana: 4',
    );
  });

  test('historial-lavanderia-E05 — sin pedidos entregados, los dos conteos en 0', async () => {
    await abrirLasEstadisticas('android');

    await waitFor(() => expect(cifra('del-dia').getByText('0')).toBeOnTheScreen());
    expect(cifra('de-la-semana').getByText('0')).toBeOnTheScreen();
  });

  // Lo que todavía no hay: el ingreso total (DD-013, DD-020 y DD-012) y la calificación promedio (DD-052).
  test('historial-lavanderia-E05 — no muestra ingreso ni calificación promedio, que esperan su respuesta', async () => {
    servidor().estadisticas = { pedidos_del_dia: 2, pedidos_de_la_semana: 5 };

    await abrirLasEstadisticas('ios');

    await waitFor(() => expect(cifra('del-dia').getByText('2')).toBeOnTheScreen());
    expect(screen.queryByText(/ingreso|\$/i)).not.toBeOnTheScreen();
    expect(screen.queryByText(/calificaci/i)).not.toBeOnTheScreen();
  });

  // Sin escenario propio: si no llegan, los mensajes generales de la app.
  test('historial-lavanderia-E05 — si las estadísticas no llegan, lo dice con el mensaje general y no muestra cifras', async () => {
    servidor().interceptar = ({ ruta }) => {
      if (ruta === API_DE_LAS_ESTADISTICAS) {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await abrirLasEstadisticas('android');

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(screen.queryByTestId('pedidos-del-dia')).not.toBeOnTheScreen();
  });

  test('historial-lavanderia-E05 — una respuesta fuera del contrato no se pinta: dice el mensaje general', async () => {
    servidor().interceptar = ({ ruta }) =>
      ruta === API_DE_LAS_ESTADISTICAS
        ? { ok: true, status: 200, json: async () => ({ pedidos_del_dia: 'dos' }) }
        : undefined;

    await abrirLasEstadisticas('ios');

    expect(await screen.findByText(MENSAJES.servidor)).toBeOnTheScreen();
    expect(screen.queryByTestId('pedidos-del-dia')).not.toBeOnTheScreen();
  });

  test('historial-lavanderia-E05 — "Regresar" vuelve a los pedidos', async () => {
    await abrirLasEstadisticas('ios');

    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
  });
});
