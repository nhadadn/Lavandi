// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "Indicadores del
// A.5 en la app": administracion-app-E45 (una pestaña por categoría, con su rango) y E51 (los indicadores se ponen al día
// solos), con E03 (la entrada Indicadores del inicio abre su pantalla dentro de la app) y E05 (el 401 en la pantalla).
// Tarea 18.4 de openspec/changes/administracion-en-la-app/tasks.md. Lo que trae cada pestaña está en
// indicadores-por-categoria.test.tsx, y el mapa de calor, en mapa-de-calor.test.tsx.
//
// El A.5 pide un "Panel de indicadores integrado en la sección de administración" (NSV-2026-001-v8.pdf:22:ANEXO A,
// Sección A.5) que "muestra en tiempo real los indicadores" (NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4), dentro de
// la app (NSV-2026-001-v8.pdf:3:Cláusula I, entregable 4). Cómo, lo decide ADR-0022 (decisiones 9 y 14): una pestaña por
// categoría con Pestanas, el rango y la granularidad arriba, la categoría visible consultada con el intervalo de la
// respuesta (adenda del 2026-11-22 de ADR-0007). El rango ajustado, su descripción y el intervalo los da el servidor
// (App\Indicadores\Rango y Refresco). La pantalla es la de las imágenes 41 y 42 de la Propuesta 6 (C-248).
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado de la
// administración (__tests__/apoyo/administracion.ts) y los indicadores del contrato de IndicadoresController
// (__tests__/apoyo/administracion/indicadores.ts). Los temporizadores falsos de Jest corren en todo el archivo, con el
// reloj del teléfono en el hoy del servidor simulado: así se cuentan los intervalos y los atajos del mes dan días fijos.
import { act, fireEvent, screen, waitFor, within } from 'expo-router/testing-library';
import { AppState, type AppStateStatus, type NativeEventSubscription } from 'react-native';

import { MENSAJES } from '../../src/acceso/mensajes';
import { leerToken } from '../../src/sesion/almacen';
import {
  abrirLaAppConLaAdministracion,
  PLATAFORMAS,
  RUTA_DEL_INICIO,
  usarElServidorDeLaAdministracion,
  type Plataforma,
} from '../apoyo/administracion';
import {
  categoriasConsultadas,
  consultasDeLosIndicadores,
  HOY,
  OCTUBRE_POR_SEMANA,
  RANGO_DE_OCTUBRE_POR_SEMANA,
  RANGO_DEL_MES_EN_CURSO,
  RUTA_DE_LOS_INDICADORES,
  usarLosIndicadores,
} from '../apoyo/administracion/indicadores';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

// indicadores.refresco por omisión (adenda del 2026-11-22 de ADR-0007; indicadores-E22).
const TREINTA_SEGUNDOS = 30_000;

const servidor = usarElServidorDeLaAdministracion('administracion-indicadores');
const indicadores = usarLosIndicadores(servidor);

const RUTA_DE_LA_PANTALLA = '/administracion/indicadores';

// Los oyentes de AppState que registró la app, para mandarla al fondo y traerla al frente.
let oyentesDeLaApp: ((estado: AppStateStatus) => void)[] = [];

beforeEach(() => {
  jest.useFakeTimers({ now: HOY });
  oyentesDeLaApp = [];
  jest.spyOn(AppState, 'addEventListener').mockImplementation((_tipo, oyente) => {
    oyentesDeLaApp.push(oyente as (estado: AppStateStatus) => void);
    return {
      remove: () => {
        oyentesDeLaApp = oyentesDeLaApp.filter((otro) => otro !== oyente);
      },
    } as NativeEventSubscription;
  });
});

afterEach(() => {
  jest.useRealTimers();
});

async function abrirLosIndicadores(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LA_PANTALLA);
  await screen.findByText(RANGO_DEL_MES_EN_CURSO.descripcion);
  // La pestaña de Operaciones ya trae sus cifras.
  await screen.findByTestId('indicador-pedidos-totales');
}

/** Las pestañas de las categorías, la primera fila de pestañas de la pantalla. */
function pestanasDeLasCategorias() {
  return within(screen.getAllByTestId('pestanas')[0]);
}

async function elegirLaPestana(nombre: string) {
  await fireEvent.press(pestanasDeLasCategorias().getByRole('tab', { name: nombre }));
}

/** La hoja "Rango y granularidad", abierta con "Cambiar". */
async function abrirLaHoja() {
  await fireEvent.press(screen.getByRole('link', { name: 'Cambiar' }));
  return within(await screen.findByTestId('hoja-del-rango'));
}

/** Avanza el reloj falso y deja que lleguen las respuestas. */
async function pasan(milisegundos: number) {
  await act(() => jest.advanceTimersByTimeAsync(milisegundos));
}

async function mandarLaAppA(estado: AppStateStatus) {
  await act(async () => {
    for (const oyente of [...oyentesDeLaApp]) {
      oyente(estado);
    }
  });
}

/** Lo que dice cada fila de la tabla de los pedidos totales, como la lee el lector de pantalla. */
function filasDeLosPedidosTotales(): string[] {
  return within(screen.getByTestId('indicador-pedidos-totales-comparados'))
    .getAllByTestId(/^fila-/)
    .map((fila) => fila.props.accessibilityLabel as string);
}

describe('administracion-app-E03: la entrada Indicadores abre su pantalla dentro de la app', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E03 — (%s) "Indicadores" del inicio abre la pantalla de los indicadores y "Regresar" vuelve al inicio',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_INICIO);
      await screen.findByRole('header', { name: 'Administración' });

      await fireEvent.press(screen.getByRole('link', { name: 'Indicadores' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_PANTALLA));
      expect(await screen.findByRole('header', { name: 'Indicadores' })).toBeOnTheScreen();
      expect(await screen.findByText(RANGO_DEL_MES_EN_CURSO.descripcion)).toBeOnTheScreen();
      expect(categoriasConsultadas(servidor())).toEqual(['operaciones']);

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_INICIO));
      expect(await screen.findByRole('header', { name: 'Administración' })).toBeOnTheScreen();
    },
  );
});

describe('administracion-app-E45: una pestaña por categoría, con su rango', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E45 — (%s) sin elegir rango ve las cinco pestañas del A.5 y arriba el rango que da el servidor, el mes en curso hasta hoy, por día',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);

      expect(
        pestanasDeLasCategorias()
          .getAllByRole('tab')
          .map((pestana) => within(pestana).getByText(/.+/).props.children),
      ).toEqual(['Operaciones', 'Usuarios', 'Socios', 'Financiero', 'Geográfico']);
      expect(pestanasDeLasCategorias().getByRole('tab', { name: 'Operaciones' })).toBeSelected();
      // El rango arriba, plegado en su tarjeta, con el texto del servidor y cada cuánto se ponen al día las cifras.
      const tarjeta = within(screen.getByTestId('tarjeta-del-rango'));
      expect(tarjeta.getByText('Del 01/11/2026 al 04/11/2026, por día')).toBeOnTheScreen();
      expect(tarjeta.getByText('Las cifras se actualizan solas cada 30 segundos.')).toBeOnTheScreen();
      expect(tarjeta.getByRole('link', { name: 'Cambiar' })).toBeOnTheScreen();
      // Sin rango elegido la app no manda filtros: el rango es el del servidor (Rango::deLosFiltros()).
      expect(consultasDeLosIndicadores(servidor())).toEqual([{ categoria: 'operaciones', filtros: {} }]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E45 — (%s) la hoja del rango: vale para las cinco, las dos fechas con el rango en uso, "Este mes", "Mes anterior" y Día, Semana o Mes',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);

      const hoja = await abrirLaHoja();

      expect(hoja.getByRole('header', { name: 'Rango y granularidad' })).toBeOnTheScreen();
      expect(hoja.getByText('Vale para las cinco categorías.')).toBeOnTheScreen();
      expect(hoja.getByLabelText('Desde').props.value).toBe('01/11/2026');
      expect(hoja.getByLabelText('Hasta').props.value).toBe('04/11/2026');
      expect(hoja.getByRole('link', { name: 'Este mes' })).toBeOnTheScreen();
      expect(hoja.getByRole('link', { name: 'Mes anterior' })).toBeOnTheScreen();
      expect(hoja.getByText('Ver por')).toBeOnTheScreen();
      expect(hoja.getAllByRole('tab').map((pestana) => within(pestana).getByText(/.+/).props.children)).toEqual([
        'Día',
        'Semana',
        'Mes',
      ]);
      expect(hoja.getByRole('tab', { name: 'Día' })).toBeSelected();
      expect(hoja.getByRole('button', { name: 'Aplicar' })).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E45 — (%s) "Mes anterior" y Semana: la app pide los días de octubre por semana y la tarjeta muestra el rango ajustado que trae la respuesta',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);

      const hoja = await abrirLaHoja();
      await fireEvent.press(hoja.getByRole('link', { name: 'Mes anterior' }));
      expect(hoja.getByLabelText('Desde').props.value).toBe('01/10/2026');
      expect(hoja.getByLabelText('Hasta').props.value).toBe('31/10/2026');
      await fireEvent.press(hoja.getByRole('tab', { name: 'Semana' }));
      expect(
        hoja.getByText('Por semana, el rango se ajusta a semanas completas, de lunes a domingo.'),
      ).toBeOnTheScreen();
      await fireEvent.press(hoja.getByRole('button', { name: 'Aplicar' }));

      expect(await screen.findByText(RANGO_DE_OCTUBRE_POR_SEMANA.descripcion)).toBeOnTheScreen();
      expect(screen.queryByTestId('hoja-del-rango')).not.toBeOnTheScreen();
      expect(consultasDeLosIndicadores(servidor())).toEqual([
        { categoria: 'operaciones', filtros: {} },
        { categoria: 'operaciones', filtros: OCTUBRE_POR_SEMANA },
      ]);
      // Las cifras son las del rango nuevo: una fila por semana.
      expect(filasDeLosPedidosTotales()[0]).toBe('Semana del 28/09/2026. Pedidos: 3. Anterior: 2');

      // Al volver a abrir la hoja trae lo que se aplicó y el rango que ajustó el servidor.
      const otraVez = await abrirLaHoja();
      expect(otraVez.getByLabelText('Desde').props.value).toBe('01/10/2026');
      expect(otraVez.getByLabelText('Hasta').props.value).toBe('31/10/2026');
      expect(otraVez.getByRole('tab', { name: 'Semana' })).toBeSelected();
      expect(
        otraVez.getByText(
          'Por semana, el rango se ajusta a semanas completas, de lunes a domingo: del 28/09/2026 al 01/11/2026.',
        ),
      ).toBeOnTheScreen();
    },
  );

  test('administracion-app-E45 — "Este mes" pone del día 1 a hoy, y las fechas también se escriben a mano', async () => {
    await abrirLosIndicadores('android');

    const hoja = await abrirLaHoja();
    await fireEvent.press(hoja.getByRole('link', { name: 'Mes anterior' }));
    await fireEvent.press(hoja.getByRole('link', { name: 'Este mes' }));
    expect(hoja.getByLabelText('Desde').props.value).toBe('01/11/2026');
    expect(hoja.getByLabelText('Hasta').props.value).toBe('04/11/2026');

    // Escritas con el teclado de números: la app pone las diagonales.
    await fireEvent.changeText(hoja.getByLabelText('Desde'), '01102026');
    await fireEvent.changeText(hoja.getByLabelText('Hasta'), '31102026');
    await fireEvent.press(hoja.getByRole('tab', { name: 'Semana' }));
    await fireEvent.press(hoja.getByRole('button', { name: 'Aplicar' }));

    expect(await screen.findByText(RANGO_DE_OCTUBRE_POR_SEMANA.descripcion)).toBeOnTheScreen();
    expect(consultasDeLosIndicadores(servidor()).at(-1)).toEqual({
      categoria: 'operaciones',
      filtros: OCTUBRE_POR_SEMANA,
    });
  });

  test('administracion-app-E45 — "Mes anterior" en enero da el diciembre del año anterior', async () => {
    jest.setSystemTime(new Date(2027, 0, 15, 9, 0));
    await abrirLosIndicadores('ios');

    const hoja = await abrirLaHoja();
    await fireEvent.press(hoja.getByRole('link', { name: 'Mes anterior' }));

    expect(hoja.getByLabelText('Desde').props.value).toBe('01/12/2026');
    expect(hoja.getByLabelText('Hasta').props.value).toBe('31/12/2026');
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E45 — (%s) el rango vale para todas las pestañas: al cambiar de pestaña se pide la nueva con el mismo rango',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);
      const hoja = await abrirLaHoja();
      await fireEvent.press(hoja.getByRole('link', { name: 'Mes anterior' }));
      await fireEvent.press(hoja.getByRole('tab', { name: 'Semana' }));
      await fireEvent.press(hoja.getByRole('button', { name: 'Aplicar' }));
      await screen.findByText(RANGO_DE_OCTUBRE_POR_SEMANA.descripcion);

      for (const [pestana, categoria, filtros] of [
        ['Usuarios', 'usuarios', OCTUBRE_POR_SEMANA],
        ['Socios', 'socios', OCTUBRE_POR_SEMANA],
        ['Financiero', 'financiero', OCTUBRE_POR_SEMANA],
        ['Geográfico', 'geografico', { ...OCTUBRE_POR_SEMANA, agrupacion: 'colonia' }],
      ] as const) {
        await elegirLaPestana(pestana);
        expect(pestanasDeLasCategorias().getByRole('tab', { name: pestana })).toBeSelected();
        await waitFor(() =>
          expect(consultasDeLosIndicadores(servidor()).at(-1)).toEqual({ categoria, filtros }),
        );
        expect(screen.getByText(RANGO_DE_OCTUBRE_POR_SEMANA.descripcion)).toBeOnTheScreen();
      }
    },
  );

  test('administracion-app-E45 — "Cerrar" deja el rango como estaba y no pide nada', async () => {
    await abrirLosIndicadores('ios');

    const hoja = await abrirLaHoja();
    await fireEvent.press(hoja.getByRole('link', { name: 'Mes anterior' }));
    await fireEvent.press(hoja.getByRole('button', { name: 'Cerrar' }));

    expect(screen.queryByTestId('hoja-del-rango')).not.toBeOnTheScreen();
    expect(screen.getByText(RANGO_DEL_MES_EN_CURSO.descripcion)).toBeOnTheScreen();
    expect(consultasDeLosIndicadores(servidor())).toEqual([{ categoria: 'operaciones', filtros: {} }]);
  });
});

describe('administracion-app-E51: los indicadores se ponen al día solos', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E51 — (%s) se confirma un pedido nuevo y la siguiente consulta, a los 30 segundos, lo cuenta en los pedidos totales y en los pedidos por estado',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);
      expect(filasDeLosPedidosTotales().at(-1)).toBe('mié. 04/11/2026. Pedidos: 1. Anterior: 3');
      expect(screen.getByTestId('dona').props.accessibilityLabel).toBe(
        'Pedidos por estado. 6 pedidos. Activos: 2. Completados: 4',
      );

      indicadores().confirmarUnPedido();
      await pasan(TREINTA_SEGUNDOS - 1);
      expect(categoriasConsultadas(servidor())).toEqual(['operaciones']);
      await pasan(1);

      await waitFor(() =>
        expect(filasDeLosPedidosTotales().at(-1)).toBe('mié. 04/11/2026. Pedidos: 2. Anterior: 3'),
      );
      expect(screen.getByTestId('dona').props.accessibilityLabel).toBe(
        'Pedidos por estado. 7 pedidos. Activos: 3. Completados: 4',
      );
      expect(categoriasConsultadas(servidor())).toEqual(['operaciones', 'operaciones']);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E51 — (%s) se consulta solo la pestaña visible: al cambiar de pestaña se pide la nueva de inmediato y después solo ella',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);

      await elegirLaPestana('Usuarios');
      await screen.findByTestId('indicador-usuarios-activos');
      expect(categoriasConsultadas(servidor())).toEqual(['operaciones', 'usuarios']);

      await pasan(3 * TREINTA_SEGUNDOS);
      expect(categoriasConsultadas(servidor())).toEqual(['operaciones', 'usuarios', 'usuarios', 'usuarios', 'usuarios']);
    },
  );

  test('administracion-app-E51 — con el intervalo que trae la respuesta: con "20s" se consulta cada 20 segundos y la tarjeta lo dice', async () => {
    indicadores().refresco = '20s';
    await abrirLosIndicadores('android');

    expect(screen.getByText('Las cifras se actualizan solas cada 20 segundos.')).toBeOnTheScreen();
    // La primera sale con los 30 s de omisión; al llegar el intervalo de la respuesta, la consulta empieza de nuevo con él.
    await waitFor(() => expect(categoriasConsultadas(servidor())).toHaveLength(2));
    await pasan(20_000);
    expect(categoriasConsultadas(servidor())).toHaveLength(3);
    await pasan(10_000);
    expect(categoriasConsultadas(servidor())).toHaveLength(3);
    await pasan(10_000);
    expect(categoriasConsultadas(servidor())).toHaveLength(4);
  });

  test('administracion-app-E51 — un intervalo en milisegundos, como "1500ms", también vale', async () => {
    indicadores().refresco = '1500ms';
    await abrirLosIndicadores('ios');

    expect(screen.getByText('Las cifras se actualizan solas cada 1500 milisegundos.')).toBeOnTheScreen();
    await waitFor(() => expect(categoriasConsultadas(servidor())).toHaveLength(2));
    await pasan(1500);
    expect(categoriasConsultadas(servidor())).toHaveLength(3);
  });

  test('administracion-app-E51 — con "1s" dice lo mismo que el panel (Refresco::descripcion()), con la unidad en plural', async () => {
    indicadores().refresco = '1s';
    await abrirLosIndicadores('ios');

    expect(screen.getByText('Las cifras se actualizan solas cada 1 segundos.')).toBeOnTheScreen();
  });

  test('administracion-app-E51 — sin intervalo en la respuesta (null), la pantalla no se consulta sola: pide solo al cambiar de pestaña', async () => {
    indicadores().refresco = null;
    await abrirLosIndicadores('android');
    expect(screen.queryByText(/Las cifras se actualizan solas/)).not.toBeOnTheScreen();

    // La respuesta que dijo que no hay intervalo ya es la de la pestaña que se ve: no se pide otra vez.
    await pasan(10 * TREINTA_SEGUNDOS);
    expect(categoriasConsultadas(servidor())).toEqual(['operaciones']);

    await elegirLaPestana('Socios');
    await screen.findByTestId('indicador-calificacion-por-lavanderia');
    await pasan(10 * TREINTA_SEGUNDOS);
    expect(categoriasConsultadas(servidor())).toEqual(['operaciones', 'socios']);

    // Volver a una pestaña la pide de nuevo, aunque sea la primera.
    await elegirLaPestana('Operaciones');
    await waitFor(() => expect(categoriasConsultadas(servidor())).toEqual(['operaciones', 'socios', 'operaciones']));
    await pasan(10 * TREINTA_SEGUNDOS);
    expect(categoriasConsultadas(servidor())).toEqual(['operaciones', 'socios', 'operaciones']);
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E51 — (%s) con la app al fondo deja de consultar, y al traerla al frente consulta de inmediato',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);

      await mandarLaAppA('background');
      await pasan(5 * TREINTA_SEGUNDOS);
      expect(categoriasConsultadas(servidor())).toEqual(['operaciones']);

      await mandarLaAppA('active');
      await waitFor(() => expect(categoriasConsultadas(servidor())).toEqual(['operaciones', 'operaciones']));
      await pasan(TREINTA_SEGUNDOS);
      expect(categoriasConsultadas(servidor())).toHaveLength(3);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E51 — (%s) con la pantalla cerrada deja de consultar',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_INICIO);
      await screen.findByRole('header', { name: 'Administración' });
      await fireEvent.press(screen.getByRole('link', { name: 'Indicadores' }));
      await screen.findByTestId('indicador-pedidos-totales');

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_INICIO));
      await pasan(5 * TREINTA_SEGUNDOS);

      expect(categoriasConsultadas(servidor())).toEqual(['operaciones']);
    },
  );

  // Sin escenario propio: si la consulta no llega, los mensajes generales de la app e "Intentar de nuevo".
  test('administracion-app-E51 — sin conexión lo dice, y "Intentar de nuevo" vuelve a pedir la pestaña', async () => {
    let sinConexion = true;
    servidor().interceptar = ({ ruta }) => {
      if (sinConexion && ruta.startsWith(RUTA_DE_LOS_INDICADORES)) {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };
    await abrirLaAppConLaAdministracion('android', RUTA_DE_LA_PANTALLA);

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(screen.queryByText('Cargando los indicadores…')).not.toBeOnTheScreen();
    sinConexion = false;
    await fireEvent.press(screen.getByRole('button', { name: 'Intentar de nuevo' }));

    expect(await screen.findByText(RANGO_DEL_MES_EN_CURSO.descripcion)).toBeOnTheScreen();
    expect(await screen.findByTestId('indicador-pedidos-totales')).toBeOnTheScreen();
    expect(screen.queryByText(MENSAJES.sinConexion)).not.toBeOnTheScreen();
  });
});

describe('administracion-app-E05: la sesión vence o se revoca', () => {
  async function esperarElInicioDeSesion() {
    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    expect(await screen.findByLabelText('Contraseña')).toBeOnTheScreen();
    await expect(leerToken()).resolves.toBeNull();
  }

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el token vencido, los indicadores reciben 401 y la app vuelve al inicio de sesión',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_INICIO);
      await screen.findByRole('header', { name: 'Administración' });
      servidor().vencerLaSesion();

      await fireEvent.press(screen.getByRole('link', { name: 'Indicadores' }));

      await esperarElInicioDeSesion();
      expect(categoriasConsultadas(servidor())).toEqual(['operaciones']);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) si el token vence con la pantalla abierta, la siguiente consulta recibe 401, la app vuelve al inicio de sesión y deja de consultar',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);

      servidor().vencerLaSesion();
      await pasan(TREINTA_SEGUNDOS);

      await esperarElInicioDeSesion();
      expect(categoriasConsultadas(servidor())).toEqual(['operaciones', 'operaciones']);
      await pasan(3 * TREINTA_SEGUNDOS);
      expect(categoriasConsultadas(servidor())).toEqual(['operaciones', 'operaciones']);
    },
  );
});
