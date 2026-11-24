// Spec: openspec/changes/repartidor/specs/repartidor/spec.md, repartidor-E28 (tarea 2.7). Las estadísticas básicas del
// repartidor (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #5): se abren desde sus pedidos asignados y muestran,
// como las da el servidor (GET /api/repartidor/estadisticas), cuántas entregas completó hoy y esta semana, contadas en la
// hora de su plaza y con la semana de lunes a domingo (design.md, lectura 5). El servidor solo cuenta las suyas.
//
// Sin ingreso total acumulado: espera DD-019 y DD-013 (repartidor-E29). Del mock c05-estadisticas tampoco entra el
// desglose por día (proposal.md, "Fuera del contrato"). Se abre la app real con la sesión de una cuenta de repartidor
// activo, en iPhone y en Android, con el servidor simulado (__tests__/apoyo/repartidor.ts).
import { fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

import { MENSAJES } from '../../src/acceso/mensajes';
import { color, grosor, radio } from '../../src/tema/theme';
import {
  abrirLaAppConSesion,
  PLATAFORMAS,
  RUTA_DE_LAS_ESTADISTICAS,
  RUTA_DE_LOS_PEDIDOS,
  usarElServidorDelRepartidor,
  type Plataforma,
} from '../apoyo/repartidor';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDelRepartidor('modulo-c-estadisticas');

async function abrirLasEstadisticas(plataforma: Plataforma) {
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  await fireEvent.press(await screen.findByRole('link', { name: 'Mis estadísticas' }));
  await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LAS_ESTADISTICAS));
  await screen.findByRole('header', { name: 'Mis estadísticas' });
}

describe('repartidor-E28: entregas del día y de la semana', () => {
  test.each(PLATAFORMAS)(
    'repartidor-E28 — (%s) con 2 entregas hoy y 5 en la semana, ve 2 del día y 5 de la semana',
    async (plataforma) => {
      // 2 hoy, 3 en días anteriores de esta semana y 1 de la semana pasada: el servidor cuenta 2 y 5.
      servidor().estadisticas = { entregas_del_dia: 2, entregas_de_la_semana: 5 };

      await abrirLasEstadisticas(plataforma);

      const delDia = within(await screen.findByTestId('entregas-del-dia'));
      expect(delDia.getByText('Entregas de hoy')).toBeOnTheScreen();
      expect(delDia.getByText('2')).toBeOnTheScreen();
      const deLaSemana = within(screen.getByTestId('entregas-de-la-semana'));
      expect(deLaSemana.getByText('Entregas de esta semana')).toBeOnTheScreen();
      expect(deLaSemana.getByText('5')).toBeOnTheScreen();
      expect(screen.getByText('La semana va de lunes a domingo.')).toBeOnTheScreen();
      expect(servidor().peticiones().filter(({ ruta }) => ruta === '/repartidor/estadisticas')).toHaveLength(1);
    },
  );

  test('repartidor-E28 — cada cifra se lee con lo que cuenta, en un solo anuncio', async () => {
    servidor().estadisticas = { entregas_del_dia: 2, entregas_de_la_semana: 5 };

    await abrirLasEstadisticas('ios');

    expect(await screen.findByLabelText('Entregas de hoy: 2')).toBeOnTheScreen();
    expect(screen.getByLabelText('Entregas de esta semana: 5')).toBeOnTheScreen();
  });

  test('repartidor-E28 — sin entregas, las dos cifras van en 0', async () => {
    await abrirLasEstadisticas('android');

    expect(await screen.findByLabelText('Entregas de hoy: 0')).toBeOnTheScreen();
    expect(screen.getByLabelText('Entregas de esta semana: 0')).toBeOnTheScreen();
  });

  test('repartidor-E28 — las cifras van en la tarjeta del design system, con números tabulares', async () => {
    servidor().estadisticas = { entregas_del_dia: 2, entregas_de_la_semana: 5 };

    await abrirLasEstadisticas('ios');

    expect(await screen.findByTestId('tarjeta-de-cifras')).toHaveStyle({
      backgroundColor: color.semantico.superficie,
      borderColor: color.semantico.borde,
      borderWidth: grosor.control,
      borderRadius: radio.grande,
    });
    expect(within(screen.getByTestId('entregas-del-dia')).getByText('2')).toHaveStyle({ fontVariant: ['tabular-nums'] });
  });

  // Sin escenario propio: si no llegan, los mensajes generales de la app.
  test('repartidor-E28 — si las estadísticas no llegan, lo dice con el mensaje general de la app', async () => {
    servidor().interceptar = ({ ruta }) => {
      if (ruta === '/repartidor/estadisticas') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await abrirLasEstadisticas('android');

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
  });

  test('repartidor-E28 — "Regresar" vuelve a sus pedidos', async () => {
    await abrirLasEstadisticas('ios');

    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
  });
});
