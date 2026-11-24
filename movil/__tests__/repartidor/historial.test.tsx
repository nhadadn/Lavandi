// Spec: openspec/changes/repartidor/specs/repartidor/spec.md, repartidor-E25 (tarea 2.6). El historial de entregas del
// repartidor (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #4): se abre desde sus pedidos asignados y muestra,
// como las da el servidor (GET /api/repartidor/historial), sus entregas completadas, las que él marcó Entregado, de la
// más reciente a la más antigua, cada una con su folio y la fecha en que la entregó, en la fecha de la plaza. El servidor
// no le da la que sigue en curso ni la de otro repartidor (repartidor-E25, en el servidor).
//
// Sin monto ni calificación recibida: esperan DD-019, DD-013 y DD-052 (repartidor-E26 y E27). Se abre la app real con la
// sesión de una cuenta de repartidor activo, en iPhone y en Android, con el servidor simulado
// (__tests__/apoyo/repartidor.ts).
import { fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

import { MENSAJES } from '../../src/acceso/mensajes';
import {
  abrirLaAppConSesion,
  MENSAJES_SIN_ACCESO,
  PLATAFORMAS,
  PRIMERO,
  RUTA_DE_LA_ESPERA,
  RUTA_DE_LOS_PEDIDOS,
  RUTA_DEL_HISTORIAL,
  SEGUNDO,
  usarElServidorDelRepartidor,
  type Plataforma,
} from '../apoyo/repartidor';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDelRepartidor('modulo-c-historial');

/** Sus dos entregas completadas, como las da el servidor: la del 4 de diciembre, la más reciente, primero. */
const ENTREGAS = [
  { id_publico: SEGUNDO.id_publico, folio: SEGUNDO.folio, entregado_en: '2026-12-04T23:10:00Z', fecha: '2026-12-04' },
  // Las 19:30 del jueves en la plaza: en UTC ya es viernes, y se muestra el jueves, como lo da el servidor.
  { id_publico: PRIMERO.id_publico, folio: PRIMERO.folio, entregado_en: '2026-12-04T01:30:00Z', fecha: '2026-12-03' },
];

async function abrirElHistorial(plataforma: Plataforma) {
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  await fireEvent.press(await screen.findByRole('link', { name: 'Historial de entregas' }));
  await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_HISTORIAL));
  await screen.findByRole('header', { name: 'Historial de entregas' });
}

describe('repartidor-E25: el repartidor ve sus entregas completadas', () => {
  test.each(PLATAFORMAS)(
    'repartidor-E25 — (%s) desde sus pedidos abre su historial y ve sus dos entregas, cada una con su folio y la fecha en que la entregó',
    async (plataforma) => {
      servidor().historial = ENTREGAS;

      await abrirElHistorial(plataforma);

      const segundo = within(await screen.findByTestId(`entregado-${SEGUNDO.id_publico}`));
      expect(segundo.getByText('Pedido 3HT8W2QZ')).toBeOnTheScreen();
      expect(segundo.getByText('Entregado el viernes 4 de diciembre')).toBeOnTheScreen();
      const primero = within(screen.getByTestId(`entregado-${PRIMERO.id_publico}`));
      expect(primero.getByText('Pedido 7KQ2M9XA')).toBeOnTheScreen();
      expect(primero.getByText('Entregado el jueves 3 de diciembre')).toBeOnTheScreen();
      // En el orden del servidor, de la más reciente a la más antigua.
      const folios = screen.getAllByText(/^Pedido /).map((texto) => texto.props.children);
      expect(folios).toEqual(['Pedido 3HT8W2QZ', 'Pedido 7KQ2M9XA']);
    },
  );

  // Las que no son suyas o siguen en curso no llegan: la app muestra lo que da el servidor, sin pedirlas aparte.
  test('repartidor-E25 — la que sigue en curso no aparece en su historial, aunque esté en su lista de pedidos', async () => {
    servidor().poner('en_entrega', PRIMERO);
    servidor().historial = [ENTREGAS[0]];

    await abrirElHistorial('android');

    expect(await screen.findByTestId(`entregado-${SEGUNDO.id_publico}`)).toBeOnTheScreen();
    expect(screen.queryByTestId(`entregado-${PRIMERO.id_publico}`)).not.toBeOnTheScreen();
    expect(screen.queryByText('Pedido 7KQ2M9XA')).not.toBeOnTheScreen();
    const pedidas = servidor().peticiones().filter(({ ruta }) => ruta === '/repartidor/historial');
    expect(pedidas.map(({ metodo }) => metodo)).toEqual(['GET']);
  });

  test('repartidor-E25 — cada entrega se lee completa, en un solo anuncio', async () => {
    servidor().historial = ENTREGAS;

    await abrirElHistorial('ios');

    expect(await screen.findByLabelText('Pedido 7KQ2M9XA, entregado el jueves 3 de diciembre')).toBeOnTheScreen();
  });

  // Sin escenario propio: el historial vacío, con un mensaje neutro.
  test('repartidor-E25 — sin entregas completadas, lo dice', async () => {
    await abrirElHistorial('android');

    expect(await screen.findByText('Todavía no tienes entregas completadas.')).toBeOnTheScreen();
  });

  // Sin escenario propio: si no llega, los mensajes generales de la app.
  test('repartidor-E25 — si el historial no llega, lo dice con el mensaje general de la app', async () => {
    servidor().interceptar = ({ ruta }) => {
      if (ruta === '/repartidor/historial') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await abrirElHistorial('ios');

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
  });

  test('repartidor-E25 — "Regresar" vuelve a sus pedidos', async () => {
    await abrirElHistorial('android');

    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
  });

  // repartidor-E06 en la app: si lo suspendieron, el historial responde 403 y la app lo lleva a la espera.
  test('repartidor-E25 — si ya no opera, el historial lo lleva a la espera', async () => {
    await abrirLaAppConSesion('ios', RUTA_DE_LOS_PEDIDOS);
    await screen.findByRole('link', { name: 'Historial de entregas' });
    servidor().estado = 'suspendido';

    await fireEvent.press(screen.getByRole('link', { name: 'Historial de entregas' }));

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));
    expect(await screen.findByText(MENSAJES_SIN_ACCESO.suspendido)).toBeOnTheScreen();
  });
});
