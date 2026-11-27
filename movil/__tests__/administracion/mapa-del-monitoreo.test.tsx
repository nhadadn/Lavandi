// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "Monitoreo de los
// pedidos activos en la app": administracion-app-E30 (el mapa de cada teléfono). Tarea 10.2 de
// openspec/changes/administracion-en-la-app/tasks.md.
//
// El contrato pide los mapas de Google en Android y los de Apple en iPhone (NSV-2026-001-v8.pdf:19:ANEXO A, Sección
// A.3, Servicios externos). Cómo, lo decide ADR-0009 (decisión 1) y lo aplica a la administración ADR-0022 (decisión
// 13): react-native-maps sin provider, que da cada uno el suyo. Sin la llave de Android en la compilación, el SDK de
// Google cierra la app al crear el mapa: la pantalla no lo monta, lo dice y la lista sigue (src/cercanas/mapa.ts).
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado de la
// administración (__tests__/apoyo/administracion.ts) y el monitoreo del contrato de MonitoreoController
// (__tests__/apoyo/administracion/monitoreo.ts). El mapa es la simulación de __mocks__/react-native-maps.tsx: que en
// iPhone se vea el de Apple y en Android el de Google se prueba en un teléfono real.
import { readFileSync } from 'node:fs';
import path from 'node:path';

import Constants from 'expo-constants';
import { screen, within } from 'expo-router/testing-library';

import { hayMapaNativo } from '../../src/cercanas/mapa';
import {
  abrirLaAppConLaAdministracion,
  PLATAFORMAS,
  usarElServidorDeLaAdministracion,
  type Plataforma,
} from '../apoyo/administracion';
import { CONFIRMADO, EN_PROCESO, RUTA_DEL_MONITOREO, usarElMonitoreo } from '../apoyo/administracion/monitoreo';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaAdministracion('administracion-mapa-del-monitoreo');
usarElMonitoreo(servidor);

const RAIZ = path.resolve(__dirname, '..', '..');

// Lo que app.config.ts le dice a la app sobre la compilación: si trae la llave de Google Maps para Android. En Jest,
// Constants.expoConfig es un objeto vacío que se puede completar.
const configuracionDeLaApp = Constants.expoConfig as { extra?: Record<string, unknown> };
function compilacionConLlave(trae: boolean) {
  configuracionDeLaApp.extra = { mapaDeGoogleEnAndroid: trae };
}

afterEach(() => {
  delete configuracionDeLaApp.extra;
});

async function abrirElMonitoreo(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_MONITOREO);
  await screen.findByTestId(`pedido-${EN_PROCESO.id_publico}`);
}

describe('administracion-app-E30: el mapa de cada teléfono', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E30 — (%s) con pedidos activos, el mapa se monta sin provider, así que cada teléfono usa el suyo: Apple en iPhone y Google en Android',
    async (plataforma) => {
      compilacionConLlave(true);

      await abrirElMonitoreo(plataforma);

      const mapa = screen.getByTestId('mapa');
      expect(mapa.props).not.toHaveProperty('provider');
      expect(within(mapa).getAllByTestId(/^marcador-/)).toHaveLength(3);
      expect(screen.queryByTestId('mapa-no-disponible')).not.toBeOnTheScreen();
    },
  );

  test('administracion-app-E30 — en iPhone el mapa no depende de la llave de Google', async () => {
    compilacionConLlave(false);

    await abrirElMonitoreo('ios');

    expect(screen.getByTestId('mapa')).toBeOnTheScreen();
    expect(screen.queryByTestId('mapa-no-disponible')).not.toBeOnTheScreen();
  });

  test('administracion-app-E30 — en una compilación de Android sin la llave, el mapa no se monta, la pantalla dice que no está disponible y la lista de pedidos sigue', async () => {
    compilacionConLlave(false);

    await abrirElMonitoreo('android');

    expect(hayMapaNativo()).toBe(false);
    expect(screen.queryByTestId('mapa')).not.toBeOnTheScreen();
    expect(screen.queryAllByTestId(/^marcador-/)).toEqual([]);
    expect(screen.getByTestId('mapa-no-disponible')).toHaveTextContent(
      'El mapa no está disponible en esta versión de la app.',
    );
    // Sin mapa, la leyenda de sus letras no tiene a qué referirse (imagen 26).
    expect(screen.queryByText('R: recolección · E: entrega en otra dirección')).not.toBeOnTheScreen();
    // La lista sigue, con los mismos pedidos y cuántos hay.
    expect(screen.getByText('2 pedidos activos · todas las plazas · se pone al día cada 30 s')).toBeOnTheScreen();
    expect(screen.getByRole('header', { name: 'Pedidos activos' })).toBeOnTheScreen();
    expect(screen.getByTestId(`pedido-${EN_PROCESO.id_publico}`)).toBeOnTheScreen();
    expect(screen.getByTestId(`pedido-${CONFIRMADO.id_publico}`)).toBeOnTheScreen();
  });

  test('administracion-app-E30 — el código del monitoreo no elige proveedor del mapa', () => {
    const fijaProveedor = /\bprovider\s*[=:{]|PROVIDER_GOOGLE|PROVIDER_DEFAULT/;
    const archivos = [
      path.join(RAIZ, 'app', 'administracion', 'monitoreo.tsx'),
      path.join(RAIZ, 'src', 'administracion', 'monitoreo', 'MapaDelMonitoreo.tsx'),
    ];
    expect(archivos.filter((archivo) => fijaProveedor.test(readFileSync(archivo, 'utf8')))).toEqual([]);
  });
});
