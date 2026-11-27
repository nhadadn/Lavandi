// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "Reportes
// financieros en la app": administracion-app-E38 (conciliar un periodo), con E03 (la entrada Conciliación del inicio
// abre su pantalla dentro de la app) y E05 (el 401 en la pantalla). Tarea 14.2 de
// openspec/changes/administracion-en-la-app/tasks.md.
//
// El Módulo D #6 pide la "conciliación con Conekta" (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3). Arthur decidió que
// la administración la hace en la app (C-247); cómo, lo decide ADR-0022 (decisiones 9 y 11). La regla es la del panel
// (administracion-E20 y E21): el servidor lee la pasarela, compara y responde el reporte; la app muestra cada diferencia
// con su tipo y no decide ninguna. La regla del rango (App\Validacion\RangoDeFechas) también es del servidor: su 422 va
// junto al campo. El servidor no impone el formato de las fechas (el contrato de ConciliacionesController): la app no
// confirma un periodo con una fecha vacía, a medias o que no existe, y lo dice junto a su campo, como el filtro de "Mis
// pedidos". Las pantallas son las imágenes 32 a 35 de la Propuesta 6 (C-248), con la confirmación y los textos del
// panel de respaldo (servidor/app/Filament/Pages/Conciliacion.php). La comisión de la pasarela (administracion-app-E41;
// DD-013) solo deja su lugar, el de la imagen 33. No se pone al día sola.
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado de la
// administración (__tests__/apoyo/administracion.ts), los reportes del contrato de ConciliacionesController
// (__tests__/apoyo/administracion/reportes-financieros.ts) y los temporizadores falsos de Jest en todo el archivo, como
// las pruebas de la consulta periódica (__tests__/pedidos-lavanderia/consulta-periodica.test.tsx): así el reloj se
// adelanta para ver que la pantalla no vuelve a pedir nada, y vuelve a ser el real después de que cada prueba cierra la
// app.
import { act, fireEvent, screen, waitFor, within } from 'expo-router/testing-library';
import { Alert, type AlertButton } from 'react-native';

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
  CONCILIADO,
  conciliacionesPedidas,
  FIN_ANTERIOR,
  MARZO,
  MONTO_DISTINTO,
  MOVIMIENTO_SIN_PEDIDO,
  PEDIDO_SIN_MOVIMIENTO,
  RUTA_DE_LAS_CONCILIACIONES,
  usarLosReportesFinancieros,
  type FilaDelReporte,
} from '../apoyo/administracion/reportes-financieros';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaAdministracion('administracion-conciliacion');
const reportes = usarLosReportesFinancieros(servidor);

const RUTA_DE_LA_PANTALLA = '/administracion/conciliacion';
const INSTRUCCIONES = 'Elige un periodo y concilia para leer los pagos de la pasarela.';
const PASARELA_NO_DISPONIBLE = 'La pasarela no está disponible por ahora. Intenta conciliar otra vez más tarde.';
const FECHA_INVALIDA = 'Escribe la fecha como DD/MM/AAAA.';

type Campo = 'desde' | 'hasta';

// Las fechas que la app no manda. El servidor no impone el formato: con "05/03/202" o "11/30/2026" (el mes primero) la
// regla "date" de Laravel pasa, Rango::deLosFiltros() la toma como no dada y la conciliación corre sobre otro periodo;
// vacía o con un día que no existe, el 422 sale en inglés.
const FECHAS_QUE_NO_SE_MANDAN: readonly { caso: string; desde: string; hasta: string; conError: readonly Campo[] }[] = [
  { caso: 'los dos campos vacíos', desde: '', hasta: '', conError: ['desde', 'hasta'] },
  { caso: '"Desde" vacío', desde: '', hasta: '31/03/2027', conError: ['desde'] },
  { caso: '"Desde" a medias, 05/03/202', desde: '05/03/202', hasta: '31/03/2027', conError: ['desde'] },
  { caso: '"Hasta" el 31/11/2026, que no existe', desde: '01/11/2026', hasta: '31/11/2026', conError: ['hasta'] },
  { caso: '"Hasta" con el mes primero, 11/30/2026', desde: '01/11/2026', hasta: '11/30/2026', conError: ['hasta'] },
];

/** Las plataformas por cada fecha que no se manda. */
const CADA_FECHA_QUE_NO_SE_MANDA = PLATAFORMAS.flatMap((plataforma) =>
  FECHAS_QUE_NO_SE_MANDAN.map((fechas) => [plataforma, fechas.caso, fechas] as const),
);

// Más que el intervalo de cualquier consulta periódica de la app (30 s, ADR-0007; 5 s, adenda de ADR-0010).
const CINCO_MINUTOS = 5 * 60_000;

let dialogo: jest.SpyInstance;

beforeEach(() => {
  jest.useFakeTimers();
  // Alert.alert puede ser ya una simulación de react-native que guarda las llamadas de todo el archivo.
  dialogo = jest.spyOn(Alert, 'alert').mockImplementation(() => {});
  dialogo.mockClear();
});

afterEach(() => {
  jest.useRealTimers();
});

async function abrirLaConciliacion(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LA_PANTALLA);
  await screen.findByText(INSTRUCCIONES);
}

/** El título, el texto y los botones del último diálogo de confirmación. */
function ultimoDialogo(): { titulo: string; texto: string | undefined; botones: AlertButton[] } {
  const [titulo, texto, botones] = dialogo.mock.calls[dialogo.mock.calls.length - 1] as [
    string,
    string | undefined,
    AlertButton[],
  ];
  return { titulo, texto, botones };
}

async function tocar(botones: AlertButton[], texto: string) {
  const boton = botones.find((opcion) => opcion.text === texto);
  expect(boton).toBeDefined();
  await act(async () => {
    boton?.onPress?.();
  });
}

/** Escribe el periodo, como DD/MM/AAAA, y toca "Conciliar un periodo". */
async function pedirLaConciliacion(desde: string, hasta: string) {
  await fireEvent.changeText(screen.getByLabelText('Desde'), desde);
  await fireEvent.changeText(screen.getByLabelText('Hasta'), hasta);
  await fireEvent.press(screen.getByRole('button', { name: 'Conciliar un periodo' }));
}

/** Escribe el periodo, como DD/MM/AAAA, toca "Conciliar un periodo" y lo confirma. */
async function conciliar(desde: string, hasta: string) {
  await pedirLaConciliacion(desde, hasta);
  await tocar(ultimoDialogo().botones, 'Conciliar');
}

/** Lo que dice cada renglón del resumen, como lo lee el lector de pantalla, en su orden. */
function resumen(): string[] {
  return within(screen.getByTestId('resumen-de-la-conciliacion'))
    .getAllByTestId(/^renglon-/)
    .map((renglon) => renglon.props.accessibilityLabel as string);
}

/** Las filas del reporte bajo un título ("Diferencias" o "Conciliados"), por su orden en la pasarela. */
function filasDe(seccion: string): string[] {
  return within(screen.getByTestId(seccion))
    .getAllByTestId(/^fila-/)
    .map((fila) => (fila.props.testID as string).slice('fila-'.length));
}

/** Lo que muestra la tarjeta de una fila del reporte. */
function tarjetaDe(fila: FilaDelReporte) {
  return within(screen.getByTestId(`fila-${fila.orden}`));
}

describe('administracion-app-E03: la entrada Conciliación abre su pantalla dentro de la app', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E03 — (%s) "Conciliación" del inicio abre la pantalla de la conciliación, que no pide nada, y "Regresar" vuelve al inicio',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_INICIO);
      await screen.findByRole('header', { name: 'Administración' });

      await fireEvent.press(screen.getByRole('link', { name: 'Conciliación' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_PANTALLA));
      expect(await screen.findByRole('header', { name: 'Conciliación' })).toBeOnTheScreen();
      expect(screen.getByText(INSTRUCCIONES)).toBeOnTheScreen();
      // Antes de conciliar no hay nada que pedir: sin leer la pasarela, todo pago parecería un pedido sin movimiento.
      expect(servidor().peticiones().filter(({ ruta }) => ruta.startsWith(RUTA_DE_LAS_CONCILIACIONES))).toEqual([]);

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_INICIO));
    },
  );
});

describe('administracion-app-E38: conciliar un periodo', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E38 — (%s) "Conciliar un periodo" pide confirmar con los textos del panel; con "Cancelar" no se manda nada',
    async (plataforma) => {
      await abrirLaConciliacion(plataforma);
      expect(screen.getByLabelText('Desde').props.value).toBe('');
      expect(screen.getByLabelText('Hasta').props.value).toBe('');

      await fireEvent.changeText(screen.getByLabelText('Desde'), '01032027');
      await fireEvent.changeText(screen.getByLabelText('Hasta'), '31032027');
      await fireEvent.press(screen.getByRole('button', { name: 'Conciliar un periodo' }));

      const { titulo, texto, botones } = ultimoDialogo();
      expect(titulo).toBe('Conciliar con la pasarela');
      expect(texto).toBe(
        'Se leen de la pasarela los pagos que acreditó en el periodo, con sus dos fechas, y se comparan con los que registró la plataforma.',
      );
      expect(botones.map(({ text }) => text)).toEqual(['Cancelar', 'Conciliar']);

      await tocar(botones, 'Cancelar');

      expect(conciliacionesPedidas(servidor())).toEqual([]);
      expect(screen.getByLabelText('Desde').props.value).toBe('01/03/2027');
      expect(screen.getByRole('button', { name: 'Conciliar un periodo' })).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E38 — (%s) al confirmar, la app pide POST /administracion/conciliaciones con el periodo y muestra el resumen de cada resultado y la tabla con cada diferencia y su tipo, y después las conciliadas',
    async (plataforma) => {
      await abrirLaConciliacion(plataforma);

      await conciliar('01/03/2027', '31/03/2027');

      expect(await screen.findByText('Del 01/03/2027 al 31/03/2027')).toBeOnTheScreen();
      expect(conciliacionesPedidas(servidor())).toEqual([MARZO]);
      // Cuántas filas hay de cada resultado, los cuatro, en el orden de la imagen 33. Debajo, el lugar de la comisión
      // de la pasarela, en ámbar y sin cifra: es de la imagen 33 (C-248) y espera a administracion-app-E41 (DD-013);
      // E38 no lo nombra.
      expect(resumen()).toEqual([
        'Conciliado: 1',
        'Monto que no coincide: 1',
        'Movimiento sin pedido: 1',
        'Pedido pagado sin movimiento: 1',
        'Comisión de la pasarela: Por confirmar · DD-013',
      ]);
      // Cada diferencia con su tipo, de la más antigua a la más nueva; después, las conciliadas.
      expect(screen.getByRole('header', { name: 'Diferencias' })).toBeOnTheScreen();
      expect(filasDe('diferencias')).toEqual([
        MOVIMIENTO_SIN_PEDIDO.orden,
        PEDIDO_SIN_MOVIMIENTO.orden,
        MONTO_DISTINTO.orden,
      ]);
      expect(screen.getByRole('header', { name: 'Conciliados' })).toBeOnTheScreen();
      expect(filasDe('conciliados')).toEqual([CONCILIADO.orden]);

      const montoDistinto = tarjetaDe(MONTO_DISTINTO);
      expect(montoDistinto.getByText('Monto que no coincide')).toBeOnTheScreen();
      expect(montoDistinto.getByText('Pedido F2G7H4JS · Plaza de prueba Durango')).toBeOnTheScreen();
      expect(
        montoDistinto.getByText('Orden en la pasarela: ord_prueba_0004 · Pago del 20/03/2027 10:00'),
      ).toBeOnTheScreen();
      expect(montoDistinto.getByLabelText('En la plataforma: $150.00')).toBeOnTheScreen();
      expect(montoDistinto.getByLabelText('En la pasarela: $175.00')).toBeOnTheScreen();

      const sinPedido = tarjetaDe(MOVIMIENTO_SIN_PEDIDO);
      expect(sinPedido.getByText('Movimiento sin pedido')).toBeOnTheScreen();
      expect(sinPedido.getByText('Sin pedido')).toBeOnTheScreen();
      expect(
        sinPedido.getByText('Orden en la pasarela: orden-de-otro-sistema · Pago del 10/03/2027 10:00'),
      ).toBeOnTheScreen();
      expect(sinPedido.getByLabelText('En la plataforma: —')).toBeOnTheScreen();
      expect(sinPedido.getByLabelText('En la pasarela: $80.00')).toBeOnTheScreen();

      const sinMovimiento = tarjetaDe(PEDIDO_SIN_MOVIMIENTO);
      expect(sinMovimiento.getByText('Pedido pagado sin movimiento')).toBeOnTheScreen();
      expect(sinMovimiento.getByText('Pedido 3HT8W2QZ · Plaza de prueba Durango')).toBeOnTheScreen();
      expect(sinMovimiento.getByLabelText('En la plataforma: $120.00')).toBeOnTheScreen();
      expect(sinMovimiento.getByLabelText('En la pasarela: —')).toBeOnTheScreen();

      const conciliado = tarjetaDe(CONCILIADO);
      expect(conciliado.getByText('Conciliado')).toBeOnTheScreen();
      expect(conciliado.getByLabelText('En la plataforma: $100.00')).toBeOnTheScreen();
      expect(conciliado.getByLabelText('En la pasarela: $100.00')).toBeOnTheScreen();

      // Después de conciliar, el periodo ya no se edita: "Otro periodo" vuelve a él. A su lado, "Descargar" pide el
      // reporte (tarea 20.1; administracion-app-E56, en descargas.test.tsx).
      expect(screen.queryByLabelText('Desde')).not.toBeOnTheScreen();
      expect(screen.getByRole('button', { name: 'Otro periodo' })).toBeOnTheScreen();
      expect(screen.getByRole('button', { name: 'Descargar' })).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E38 — (%s) "Otro periodo" vuelve al periodo que se concilió; uno sin pagos ni movimientos lo dice, con el resumen en 0',
    async (plataforma) => {
      await abrirLaConciliacion(plataforma);
      await conciliar('01/03/2027', '31/03/2027');
      await screen.findByText('Del 01/03/2027 al 31/03/2027');

      await fireEvent.press(screen.getByRole('button', { name: 'Otro periodo' }));

      expect(screen.getByText(INSTRUCCIONES)).toBeOnTheScreen();
      expect(screen.getByLabelText('Desde').props.value).toBe('01/03/2027');
      expect(screen.getByLabelText('Hasta').props.value).toBe('31/03/2027');
      expect(screen.queryByTestId('resumen-de-la-conciliacion')).not.toBeOnTheScreen();

      await conciliar('01/04/2027', '30/04/2027');

      expect(await screen.findByText('Del 01/04/2027 al 30/04/2027')).toBeOnTheScreen();
      expect(conciliacionesPedidas(servidor())).toEqual([MARZO, { desde: '2027-04-01', hasta: '2027-04-30' }]);
      expect(screen.getByText('No hay pagos ni movimientos de la pasarela en el periodo.')).toBeOnTheScreen();
      expect(resumen()).toEqual([
        'Conciliado: 0',
        'Monto que no coincide: 0',
        'Movimiento sin pedido: 0',
        'Pedido pagado sin movimiento: 0',
        'Comisión de la pasarela: Por confirmar · DD-013',
      ]);
      expect(screen.queryByRole('header', { name: 'Diferencias' })).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E38 — (%s) con la fecha de fin anterior a la de inicio, el 422 del servidor va junto al campo "Hasta" y no hay reporte',
    async (plataforma) => {
      await abrirLaConciliacion(plataforma);

      await conciliar('31/03/2027', '01/03/2027');

      const hasta = within(screen.getByTestId('campo-hasta'));
      expect(await hasta.findByText(FIN_ANTERIOR)).toBeOnTheScreen();
      expect(within(screen.getByTestId('campo-desde')).queryByRole('alert')).not.toBeOnTheScreen();
      expect(conciliacionesPedidas(servidor())).toEqual([{ desde: '2027-03-31', hasta: '2027-03-01' }]);
      // Una sola vez, junto al campo.
      expect(screen.getAllByText(FIN_ANTERIOR)).toHaveLength(1);
      expect(screen.queryByTestId('resumen-de-la-conciliacion')).not.toBeOnTheScreen();
      expect(screen.getByRole('button', { name: 'Conciliar un periodo' })).toBeOnTheScreen();
    },
  );

  test.each(CADA_FECHA_QUE_NO_SE_MANDA)(
    'administracion-app-E38 — (%s) con %s, "Escribe la fecha como DD/MM/AAAA." va junto a su campo y no se confirma ni se manda nada',
    async (plataforma, _caso, { desde, hasta, conError }) => {
      await abrirLaConciliacion(plataforma);

      await pedirLaConciliacion(desde, hasta);

      expect(dialogo).not.toHaveBeenCalled();
      expect(conciliacionesPedidas(servidor())).toEqual([]);
      for (const campo of ['desde', 'hasta'] as const) {
        const junto = within(screen.getByTestId(`campo-${campo}`));
        if (conError.includes(campo)) {
          expect(junto.getByText(FECHA_INVALIDA)).toBeOnTheScreen();
        } else {
          expect(junto.queryByRole('alert')).not.toBeOnTheScreen();
        }
      }
      // Solo junto a su campo, y ningún mensaje del validador del servidor.
      expect(screen.getAllByText(FECHA_INVALIDA)).toHaveLength(conError.length);
      expect(screen.queryByText(/The (desde|hasta) field/)).not.toBeOnTheScreen();
      expect(screen.queryByTestId('resumen-de-la-conciliacion')).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E38 — (%s) al corregir la fecha, el error se va y se concilia el periodo escrito, en AAAA-MM-DD',
    async (plataforma) => {
      await abrirLaConciliacion(plataforma);
      await pedirLaConciliacion('05/03/202', '31/03/2027');
      expect(within(screen.getByTestId('campo-desde')).getByText(FECHA_INVALIDA)).toBeOnTheScreen();

      await conciliar('01/03/2027', '31/03/2027');

      expect(await screen.findByText('Del 01/03/2027 al 31/03/2027')).toBeOnTheScreen();
      expect(conciliacionesPedidas(servidor())).toEqual([MARZO]);
      expect(screen.queryByText(FECHA_INVALIDA)).not.toBeOnTheScreen();

      // De vuelta al periodo, sin el error de antes.
      await fireEvent.press(screen.getByRole('button', { name: 'Otro periodo' }));
      expect(screen.getByLabelText('Desde').props.value).toBe('01/03/2027');
      expect(screen.queryByText(FECHA_INVALIDA)).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E38 — (%s) si la pasarela no responde, dice "No se pudo leer la pasarela", sin tabla nueva, y se puede conciliar otra vez',
    async (plataforma) => {
      await abrirLaConciliacion(plataforma);
      await conciliar('01/03/2027', '31/03/2027');
      await screen.findByText('Del 01/03/2027 al 31/03/2027');
      await fireEvent.press(screen.getByRole('button', { name: 'Otro periodo' }));
      reportes().pasarelaResponde = false;

      await conciliar('01/03/2027', '31/03/2027');

      expect(await screen.findByText('No se pudo leer la pasarela')).toBeOnTheScreen();
      expect(screen.getByText(PASARELA_NO_DISPONIBLE)).toBeOnTheScreen();
      expect(screen.queryByTestId('resumen-de-la-conciliacion')).not.toBeOnTheScreen();
      expect(screen.queryByTestId('diferencias')).not.toBeOnTheScreen();
      expect(screen.queryByText('Del 01/03/2027 al 31/03/2027')).not.toBeOnTheScreen();
      expect(conciliacionesPedidas(servidor())).toEqual([MARZO, MARZO]);

      // Más tarde la pasarela responde: se concilia otra vez y el aviso se va.
      reportes().pasarelaResponde = true;
      await conciliar('01/03/2027', '31/03/2027');

      expect(await screen.findByText('Del 01/03/2027 al 31/03/2027')).toBeOnTheScreen();
      expect(screen.queryByText('No se pudo leer la pasarela')).not.toBeOnTheScreen();
    },
  );
});

// Lo general de la app, sin escenario propio: administracion-app-E38 no habla de la conexión.
describe('la conciliación sin conexión, como el resto de la app', () => {
  test(
    'comportamiento general de la app, sin escenario propio — sin conexión, la conciliación lo dice y no hay reporte',
    async () => {
      servidor().interceptar = ({ ruta }) => {
        if (ruta === RUTA_DE_LAS_CONCILIACIONES) {
          throw new TypeError('Network request failed');
        }
        return undefined;
      };
      await abrirLaConciliacion('ios');

      await conciliar('01/03/2027', '31/03/2027');

      expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
      expect(screen.queryByTestId('resumen-de-la-conciliacion')).not.toBeOnTheScreen();
    },
  );
});

describe('administracion-app-E38: la pantalla no se pone al día sola', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E38 — (%s) con el reporte a la vista, el tiempo pasa y la app no vuelve a conciliar ni a pedir nada',
    async (plataforma) => {
      await abrirLaConciliacion(plataforma);
      await conciliar('01/03/2027', '31/03/2027');
      await screen.findByText('Del 01/03/2027 al 31/03/2027');

      await act(() => jest.advanceTimersByTimeAsync(CINCO_MINUTOS));

      expect(
        servidor()
          .peticiones()
          .filter(({ ruta }) => ruta.startsWith('/administracion'))
          .map(({ metodo, ruta }) => `${metodo} ${ruta}`),
      ).toEqual([`POST ${RUTA_DE_LAS_CONCILIACIONES}`]);
    },
  );
});

describe('administracion-app-E05: la sesión vence o se revoca', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el token vencido, la conciliación recibe 401 y la app vuelve al inicio de sesión',
    async (plataforma) => {
      await abrirLaConciliacion(plataforma);
      servidor().vencerLaSesion();

      await conciliar('01/03/2027', '31/03/2027');

      await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
      expect(await screen.findByLabelText('Contraseña')).toBeOnTheScreen();
      await expect(leerToken()).resolves.toBeNull();
      expect(servidor().peticiones()).toContainEqual(
        expect.objectContaining({ metodo: 'POST', ruta: RUTA_DE_LAS_CONCILIACIONES }),
      );
    },
  );
});
