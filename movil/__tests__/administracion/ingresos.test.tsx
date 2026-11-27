// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "Reportes
// financieros en la app": administracion-app-E37 (los ingresos de un periodo), con E03 (la entrada Ingresos del inicio
// abre su pantalla dentro de la app) y E05 (el 401 en la pantalla). Tarea 14.1 de
// openspec/changes/administracion-en-la-app/tasks.md.
//
// El Módulo D #6 pide "Ingresos por periodo, comisiones y conciliación con Conekta" (NSV-2026-001-v8.pdf:19:ANEXO A,
// Sección A.3). Arthur decidió que la administración lo ve en la app (C-247); cómo, lo decide ADR-0022 (decisiones 9 y
// 11). La regla es la del panel (administracion-E17): las cifras, el rango ajustado y sus textos los da el servidor, y la
// app no calcula ninguno. La pantalla es la imagen 31 de la Propuesta 6 (C-248), con la hoja del rango de la imagen 42
// sin "Este mes" ni "Mes anterior". Los reembolsos y ajustes (administracion-app-E39; DD-012 y DD-016) y las comisiones
// del periodo (administracion-app-E40; DD-020) solo dejan su lugar, sin cifra ni descarga. No se pone al día sola.
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado de la
// administración (__tests__/apoyo/administracion.ts), los reportes del contrato de IngresosController
// (__tests__/apoyo/administracion/reportes-financieros.ts) y los temporizadores falsos de Jest en todo el archivo, como
// las pruebas de la consulta periódica (__tests__/pedidos-lavanderia/consulta-periodica.test.tsx): así el reloj se
// adelanta para ver que la pantalla no vuelve a pedir nada, y vuelve a ser el real después de que cada prueba cierra la
// app.
import { act, fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

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
  consultasDeLosIngresos,
  INGRESOS_DE_MARZO_POR_SEMANA,
  INGRESOS_DEL_MES_EN_CURSO,
  MARZO_POR_SEMANA,
  RUTA_DE_LOS_INGRESOS,
  usarLosReportesFinancieros,
} from '../apoyo/administracion/reportes-financieros';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaAdministracion('administracion-ingresos');
const reportes = usarLosReportesFinancieros(servidor);

// Más que el intervalo de cualquier consulta periódica de la app (30 s, ADR-0007; 5 s, adenda de ADR-0010).
const CINCO_MINUTOS = 5 * 60_000;

beforeEach(() => {
  jest.useFakeTimers();
});

afterEach(() => {
  jest.useRealTimers();
});

const RUTA_DE_LA_PANTALLA = '/administracion/ingresos';
const FECHA_INVALIDA = 'Escribe la fecha como DD/MM/AAAA.';

async function abrirLosIngresos(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LA_PANTALLA);
  await screen.findByText(INGRESOS_DEL_MES_EN_CURSO.rango.descripcion);
}

/** Lo que dice cada renglón de la tarjeta de las cifras del periodo, como lo lee el lector de pantalla, en su orden. */
function cifrasDelPeriodo(): string[] {
  return within(screen.getByTestId('cifras-del-periodo'))
    .getAllByTestId(/^renglon-/)
    .map((renglon) => renglon.props.accessibilityLabel as string);
}

/** Lo que dice cada fila de la tabla de los periodos, como la lee el lector de pantalla, en su orden. */
function filasDeLaTabla(): string[] {
  return within(screen.getByTestId('tabla-de-los-periodos'))
    .getAllByTestId(/^periodo-/)
    .map((fila) => fila.props.accessibilityLabel as string);
}

/** La hoja "Rango y granularidad", abierta con "Cambiar". */
async function abrirLaHoja() {
  await fireEvent.press(screen.getByRole('link', { name: 'Cambiar' }));
  return within(await screen.findByTestId('hoja-del-rango'));
}

describe('administracion-app-E03: la entrada Ingresos abre su pantalla dentro de la app', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E03 — (%s) "Ingresos" del inicio abre la pantalla de los ingresos y "Regresar" vuelve al inicio',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_INICIO);
      await screen.findByRole('header', { name: 'Administración' });

      await fireEvent.press(screen.getByRole('link', { name: 'Ingresos' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_PANTALLA));
      expect(await screen.findByRole('header', { name: 'Ingresos' })).toBeOnTheScreen();
      expect(await screen.findByText(INGRESOS_DEL_MES_EN_CURSO.rango.descripcion)).toBeOnTheScreen();

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_INICIO));
    },
  );
});

describe('administracion-app-E37: los ingresos de un periodo', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E37 — (%s) sin rango elegido ve el que da el servidor, el mes en curso hasta hoy por día: el total, cuántos pagos son y el ingreso de cada día, con las cifras del servidor',
    async (plataforma) => {
      await abrirLosIngresos(plataforma);

      // Sin rango elegido, la app no manda filtros: el rango es el que da el servidor (Rango::deLosFiltros()).
      expect(consultasDeLosIngresos(servidor())).toEqual([{}]);
      expect(screen.getByText('Del 01/03/2027 al 05/03/2027, por día')).toBeOnTheScreen();
      expect(cifrasDelPeriodo()).toEqual([
        'Ingresos del periodo: $150.00',
        'Pagos confirmados: 1',
        'Reembolsos y ajustes: Por confirmar · DD-012, DD-016',
        'Comisiones del periodo: Por confirmar · DD-020',
      ]);
      expect(filasDeLaTabla()).toEqual([
        'lun. 01/03/2027: $0.00',
        'mar. 02/03/2027: $0.00',
        'mié. 03/03/2027: $0.00',
        'jue. 04/03/2027: $0.00',
        'vie. 05/03/2027: $150.00',
      ]);
      // Lo mismo, a la vista: la tabla con sus dos columnas.
      const tabla = within(screen.getByTestId('tabla-de-los-periodos'));
      expect(tabla.getByText('Periodo')).toBeOnTheScreen();
      expect(tabla.getByText('Ingresos')).toBeOnTheScreen();
      expect(tabla.getByText('vie. 05/03/2027')).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E37 — (%s) con el rango elegido, marzo por semana, la app pide GET /administracion/ingresos con desde, hasta y granularidad y ve el total, los pagos y el ingreso de cada semana del rango ajustado',
    async (plataforma) => {
      await abrirLosIngresos(plataforma);

      const hoja = await abrirLaHoja();
      await fireEvent.changeText(hoja.getByLabelText('Hasta'), '31032027');
      await fireEvent.press(hoja.getByRole('tab', { name: 'Semana' }));
      await fireEvent.press(hoja.getByRole('button', { name: 'Aplicar' }));

      expect(await screen.findByText(INGRESOS_DE_MARZO_POR_SEMANA.rango.descripcion)).toBeOnTheScreen();
      expect(screen.queryByTestId('hoja-del-rango')).not.toBeOnTheScreen();
      expect(consultasDeLosIngresos(servidor())).toEqual([{}, MARZO_POR_SEMANA]);
      expect(cifrasDelPeriodo()).toEqual([
        'Ingresos del periodo: $350.00',
        'Pagos confirmados: 2',
        'Reembolsos y ajustes: Por confirmar · DD-012, DD-016',
        'Comisiones del periodo: Por confirmar · DD-020',
      ]);
      expect(filasDeLaTabla()).toEqual([
        'Semana del 01/03/2027: $150.00',
        'Semana del 08/03/2027: $0.00',
        'Semana del 15/03/2027: $0.00',
        'Semana del 22/03/2027: $200.00',
        'Semana del 29/03/2027: $0.00',
      ]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E37 — (%s) la hoja del rango: las dos fechas con las del rango en uso, "Ver por" con Día, Semana o Mes, cómo se ajusta el rango con los días que dio el servidor, sin "Este mes" ni "Mes anterior"; "Cerrar" no pide nada',
    async (plataforma) => {
      await abrirLosIngresos(plataforma);

      let hoja = await abrirLaHoja();
      expect(hoja.getByRole('header', { name: 'Rango y granularidad' })).toBeOnTheScreen();
      // Sin rango elegido, los campos traen el que usó el servidor.
      expect(hoja.getByLabelText('Desde').props.value).toBe('01/03/2027');
      expect(hoja.getByLabelText('Hasta').props.value).toBe('05/03/2027');
      expect(hoja.getByText('Ver por')).toBeOnTheScreen();
      expect(hoja.getAllByRole('tab').map((pestana) => within(pestana).getByText(/.+/).props.children)).toEqual([
        'Día',
        'Semana',
        'Mes',
      ]);
      expect(hoja.getByRole('tab', { name: 'Día' })).toBeSelected();
      // Los atajos son de los indicadores: administracion-app-E37 no los nombra.
      expect(hoja.queryByText('Este mes')).not.toBeOnTheScreen();
      expect(hoja.queryByText('Mes anterior')).not.toBeOnTheScreen();
      // Cómo se ajusta el rango; sus días, solo los que ya dio el servidor.
      await fireEvent.press(hoja.getByRole('tab', { name: 'Mes' }));
      expect(hoja.getByText('Por mes, el rango se ajusta a meses completos, del día 1 al último día.')).toBeOnTheScreen();

      await fireEvent.press(hoja.getByRole('button', { name: 'Cerrar' }));

      expect(screen.queryByTestId('hoja-del-rango')).not.toBeOnTheScreen();
      expect(consultasDeLosIngresos(servidor())).toEqual([{}]);
      expect(screen.getByText(INGRESOS_DEL_MES_EN_CURSO.rango.descripcion)).toBeOnTheScreen();

      // Con marzo por semana aplicado, la hoja vuelve con lo que se escribió y el rango ajustado que dio el servidor.
      hoja = await abrirLaHoja();
      expect(hoja.getByRole('tab', { name: 'Día' })).toBeSelected();
      await fireEvent.changeText(hoja.getByLabelText('Hasta'), '31/03/2027');
      await fireEvent.press(hoja.getByRole('tab', { name: 'Semana' }));
      expect(
        hoja.getByText('Por semana, el rango se ajusta a semanas completas, de lunes a domingo.'),
      ).toBeOnTheScreen();
      await fireEvent.press(hoja.getByRole('button', { name: 'Aplicar' }));
      await screen.findByText(INGRESOS_DE_MARZO_POR_SEMANA.rango.descripcion);

      hoja = await abrirLaHoja();
      expect(hoja.getByLabelText('Desde').props.value).toBe('01/03/2027');
      expect(hoja.getByLabelText('Hasta').props.value).toBe('31/03/2027');
      expect(hoja.getByRole('tab', { name: 'Semana' })).toBeSelected();
      expect(
        hoja.getByText(
          'Por semana, el rango se ajusta a semanas completas, de lunes a domingo: del 01/03/2027 al 04/04/2027.',
        ),
      ).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E37 — (%s) la hoja no aplica una fecha a medias o que no existe: lo dice junto a su campo y sigue abierta; un campo vacío sí se aplica y el servidor pone el suyo',
    async (plataforma) => {
      await abrirLosIngresos(plataforma);
      // Sin "desde", el servidor pone el día 1 del mes en curso: marzo de 2027, como el resto de las pruebas.
      reportes().responderIngresos({ hasta: '2027-03-31', granularidad: 'semana' }, INGRESOS_DE_MARZO_POR_SEMANA);

      const hoja = await abrirLaHoja();
      await fireEvent.press(hoja.getByRole('tab', { name: 'Semana' }));
      for (const [desde, hasta, conError] of [
        ['01/03/2027', '31/02/2027', 'hasta'],
        ['05/03/202', '31/03/2027', 'desde'],
      ] as const) {
        await fireEvent.changeText(hoja.getByLabelText('Desde'), desde);
        await fireEvent.changeText(hoja.getByLabelText('Hasta'), hasta);
        await fireEvent.press(hoja.getByRole('button', { name: 'Aplicar' }));

        expect(screen.getByTestId('hoja-del-rango')).toBeOnTheScreen();
        expect(within(hoja.getByTestId(`campo-${conError}`)).getByText(FECHA_INVALIDA)).toBeOnTheScreen();
        expect(hoja.getAllByText(FECHA_INVALIDA)).toHaveLength(1);
        expect(consultasDeLosIngresos(servidor())).toEqual([{}]);
      }

      await fireEvent.changeText(hoja.getByLabelText('Desde'), '');
      await fireEvent.press(hoja.getByRole('button', { name: 'Aplicar' }));

      expect(await screen.findByText(INGRESOS_DE_MARZO_POR_SEMANA.rango.descripcion)).toBeOnTheScreen();
      expect(screen.queryByTestId('hoja-del-rango')).not.toBeOnTheScreen();
      expect(consultasDeLosIngresos(servidor())).toEqual([{}, { hasta: '2027-03-31', granularidad: 'semana' }]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E37 — (%s) los reembolsos y ajustes y las comisiones del periodo dejan su lugar con "Por confirmar" y su DD, sin cifra ni descarga',
    async (plataforma) => {
      await abrirLosIngresos(plataforma);

      const reembolsos = within(screen.getByTestId('renglon-reembolsos-y-ajustes'));
      expect(reembolsos.getByText('Reembolsos y ajustes')).toBeOnTheScreen();
      expect(reembolsos.getByText('Por confirmar · DD-012, DD-016')).toBeOnTheScreen();
      expect(reembolsos.queryByText(/\$/)).not.toBeOnTheScreen();
      const comisiones = within(screen.getByTestId('renglon-comisiones-del-periodo'));
      expect(comisiones.getByText('Comisiones del periodo')).toBeOnTheScreen();
      expect(comisiones.getByText('Por confirmar · DD-020')).toBeOnTheScreen();
      expect(comisiones.queryByText(/\$/)).not.toBeOnTheScreen();
      // Ninguno de los dos ofrece su descarga: el único "Descargar" de la pantalla es el de los ingresos (tarea 20.1;
      // administracion-app-E63, en descargas.test.tsx).
      expect(reembolsos.queryByText('Descargar')).not.toBeOnTheScreen();
      expect(comisiones.queryByText('Descargar')).not.toBeOnTheScreen();
      expect(screen.getAllByRole('button', { name: 'Descargar' })).toHaveLength(1);
    },
  );
});

// Lo general de la app, sin escenario propio: administracion-app-E37 no habla de la conexión.
describe('los ingresos sin conexión, como el resto de la app', () => {
  test(
    'comportamiento general de la app, sin escenario propio — sin conexión, los ingresos lo dicen y "Intentar de nuevo" los vuelve a pedir',
    async () => {
      let sinConexion = true;
      servidor().interceptar = ({ ruta }) => {
        if (sinConexion && ruta.startsWith(RUTA_DE_LOS_INGRESOS)) {
          throw new TypeError('Network request failed');
        }
        return undefined;
      };
      await abrirLaAppConLaAdministracion('android', RUTA_DE_LA_PANTALLA);

      expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
      sinConexion = false;
      await fireEvent.press(screen.getByRole('button', { name: 'Intentar de nuevo' }));

      expect(await screen.findByText(INGRESOS_DEL_MES_EN_CURSO.rango.descripcion)).toBeOnTheScreen();
      expect(screen.queryByText(MENSAJES.sinConexion)).not.toBeOnTheScreen();
    },
  );
});

describe('administracion-app-E37: la pantalla no se pone al día sola', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E37 — (%s) con la pantalla abierta, el tiempo pasa y la app no vuelve a pedir los ingresos, con el rango o sin él',
    async (plataforma) => {
      await abrirLosIngresos(plataforma);

      await act(() => jest.advanceTimersByTimeAsync(CINCO_MINUTOS));
      expect(consultasDeLosIngresos(servidor())).toEqual([{}]);

      const hoja = await abrirLaHoja();
      await fireEvent.changeText(hoja.getByLabelText('Hasta'), '31/03/2027');
      await fireEvent.press(hoja.getByRole('tab', { name: 'Semana' }));
      await fireEvent.press(hoja.getByRole('button', { name: 'Aplicar' }));
      await screen.findByText(INGRESOS_DE_MARZO_POR_SEMANA.rango.descripcion);

      await act(() => jest.advanceTimersByTimeAsync(CINCO_MINUTOS));
      expect(consultasDeLosIngresos(servidor())).toEqual([{}, MARZO_POR_SEMANA]);
    },
  );
});

describe('administracion-app-E05: la sesión vence o se revoca', () => {
  async function esperarElInicioDeSesion() {
    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    expect(await screen.findByLabelText('Contraseña')).toBeOnTheScreen();
    await expect(leerToken()).resolves.toBeNull();
  }

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el token vencido, los ingresos reciben 401 y la app vuelve al inicio de sesión',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_INICIO);
      await screen.findByRole('header', { name: 'Administración' });
      servidor().vencerLaSesion();

      await fireEvent.press(screen.getByRole('link', { name: 'Ingresos' }));

      await esperarElInicioDeSesion();
      expect(servidor().peticiones()).toContainEqual(
        expect.objectContaining({ metodo: 'GET', ruta: RUTA_DE_LOS_INGRESOS }),
      );
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) si el token vence con la pantalla abierta, el rango nuevo recibe 401 y la app vuelve al inicio de sesión',
    async (plataforma) => {
      await abrirLosIngresos(plataforma);
      servidor().vencerLaSesion();

      const hoja = await abrirLaHoja();
      await fireEvent.press(hoja.getByRole('tab', { name: 'Semana' }));
      await fireEvent.press(hoja.getByRole('button', { name: 'Aplicar' }));

      await esperarElInicioDeSesion();
      expect(consultasDeLosIngresos(servidor())).toEqual([
        {},
        { desde: '2027-03-01', hasta: '2027-03-05', granularidad: 'semana' },
      ]);
    },
  );
});
