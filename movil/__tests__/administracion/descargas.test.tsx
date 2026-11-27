// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "Descarga de los
// reportes en CSV y en Excel desde la app": administracion-app-E56 (pedir la descarga de un reporte), E57 (mientras se
// genera y cuando está lista), E59 (las descargas de la cuenta), E60 (el rango de la descarga) y la parte vigente de E63
// (la app no ofrece "Descargar" en los reportes que esperan respuesta); del requisito "Indicadores del A.5 en la app",
// E53 (un indicador que espera una respuesta no ofrece su descarga); y del requisito "La administración inicia sesión en
// la app", E03 (la entrada a Descargas desde el inicio del módulo) y E05 (la sesión vence en esta pantalla). Tareas 20.1
// y 20.3. El contrato: "Descarga de reportes en formato CSV y Excel para cualquier
// rango de fechas" (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5) y "Reportes exportables generados correctamente en
// formato CSV o Excel" (NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4). Cómo se construye: ADR-0022, decisión 15.
//
// La pantalla Descargas se prueba en la app real, con las rutas de movil/app/ (ADR-0017). La pieza que pide la descarga
// (src/administracion/descargas/PedirDescarga.tsx) se prueba de dos modos:
// - sola, en una pantalla de prueba con su "Descargar", dentro del proveedor de sesión de la app, con el inicio de sesión
//   real a un lado: lo que hace la pieza con cualquier reporte;
// - en la app real, desde las tres pantallas que la ponen (tarea 20.1): Indicadores, un reporte por indicador con cifra;
//   Ingresos; y Conciliación, después de conciliar. Cada una con su rango por omisión (E56, E57 y E60), y sin "Descargar"
//   en lo que espera una respuesta (E53 y la parte vigente de E63: F-I06, F-I10 y las comisiones del periodo).
// Todo en iPhone y en Android, con el servidor simulado de la administración (__tests__/apoyo/administracion.ts), sus
// descargas (__tests__/apoyo/administracion/descargas.ts), sus indicadores (indicadores.ts) y sus reportes financieros
// (reportes-financieros.ts). Guardar el archivo en el teléfono (E58) está en guardar-archivo.test.tsx.
import { Directory } from 'expo-file-system';
import { router } from 'expo-router';
import { act, fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import { useState } from 'react';
import {
  Alert,
  AppState,
  Text,
  View,
  type AlertButton,
  type AppStateStatus,
  type NativeEventSubscription,
} from 'react-native';

import InicioDeSesion from '../../app/inicio-de-sesion';
import { MENSAJES } from '../../src/acceso/mensajes';
import type { CodigoDelReporte, Granularidad } from '../../src/administracion/descargas/api';
import { PedirDescarga } from '../../src/administracion/descargas/PedirDescarga';
import { Boton } from '../../src/piezas/Boton';
import { leerRol, leerToken } from '../../src/sesion/almacen';
import { ProveedorDeSesion, useSesion } from '../../src/sesion/ProveedorDeSesion';
import {
  abrirLaAppConLaAdministracion,
  PLATAFORMAS,
  prepararElTelefonoConLaAdministracion,
  respuesta,
  RUTA_DEL_INICIO,
  RUTAS_DE_LOS_CONTEOS,
  TOKEN_DE_LA_ADMINISTRACION,
  usarElServidorDeLaAdministracion,
  type Plataforma,
} from '../apoyo/administracion';
import {
  bajarConElServidor,
  carpetaElegida,
  descarga,
  DescargasDelServidor,
  MENSAJE_DE_LA_GRANULARIDAD,
  MENSAJE_DEL_RANGO_AL_REVES,
  MENSAJE_DEL_REPORTE,
  NO_EXISTE_LA_DESCARGA,
} from '../apoyo/administracion/descargas';
import {
  consultasDeLosIndicadores,
  RANGO_DE_OCTUBRE_POR_SEMANA,
  RANGO_DEL_MES_EN_CURSO,
  usarLosIndicadores,
} from '../apoyo/administracion/indicadores';
import {
  conciliacionesPedidas,
  consultasDeLosIngresos,
  INGRESOS_DE_MARZO_POR_SEMANA,
  INGRESOS_DEL_MES_EN_CURSO,
  usarLosReportesFinancieros,
} from '../apoyo/administracion/reportes-financieros';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const RUTA_DE_LAS_DESCARGAS = '/administracion/descargas';

// ADR-0022, decisión 15: cada 5 segundos mientras se genera.
const CINCO_SEGUNDOS = 5_000;

const servidor = usarElServidorDeLaAdministracion('administracion-descargas');
// Las tres pantallas que ponen la pieza piden sus cifras a estos manejadores; las demás pruebas no los tocan.
usarLosIndicadores(servidor);
usarLosReportesFinancieros(servidor);
let descargas: DescargasDelServidor;

// Los oyentes de AppState que registró la app, para mandarla al fondo y traerla al frente.
let oyentesDeLaApp: ((estado: AppStateStatus) => void)[] = [];

beforeEach(() => {
  descargas = new DescargasDelServidor(servidor());
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

async function mandarLaAppA(estado: AppStateStatus) {
  await act(async () => {
    for (const oyente of [...oyentesDeLaApp]) {
      oyente(estado);
    }
  });
}

/** Avanza el reloj falso y deja que lleguen las respuestas. */
async function pasan(milisegundos: number) {
  await act(() => jest.advanceTimersByTimeAsync(milisegundos));
}

/** Un instante de hoy, o de hace unos días, a esa hora del teléfono, como lo manda la API: en UTC. */
function instante(hora: number, minuto: number, diasAntes = 0): string {
  const ahora = new Date();
  return new Date(ahora.getFullYear(), ahora.getMonth(), ahora.getDate() - diasAntes, hora, minuto).toISOString();
}

// La pantalla de prueba de la pieza: su "Descargar" la abre con el reporte, el rango y la granularidad de la pantalla, y
// "Cancelar" la cierra, como lo harán indicadores, ingresos y conciliación. "Otra pantalla" lleva a otra ruta, para
// probar que la espera deja de consultar con la pantalla oculta.
const RUTA_DE_PRUEBA = '/administracion/prueba-de-la-descarga';
const OTRA_RUTA = '/administracion/otra-pantalla';

interface DeLaPantalla {
  reporte: CodigoDelReporte;
  desde: string;
  hasta: string;
  granularidad?: Granularidad;
}

const DE_LOS_INDICADORES: DeLaPantalla = {
  reporte: 'pedidos-totales',
  desde: '2026-10-26',
  hasta: '2026-11-29',
  granularidad: 'semana',
};

const DE_LA_CONCILIACION: DeLaPantalla = {
  reporte: 'conciliacion-con-la-pasarela',
  desde: '2026-11-01',
  hasta: '2026-11-15',
};

let deLaPantalla: DeLaPantalla = DE_LOS_INDICADORES;

function PantallaDePrueba() {
  const { estado } = useSesion();
  const [abierta, setAbierta] = useState(false);
  return (
    <View>
      <Text testID="estado-de-la-sesion">{estado}</Text>
      {abierta ? (
        <PedirDescarga {...deLaPantalla} alCancelar={() => setAbierta(false)} />
      ) : (
        <Boton variante="secundario" onPress={() => setAbierta(true)}>
          Descargar
        </Boton>
      )}
      <Boton variante="secundario" onPress={() => router.push(OTRA_RUTA)}>
        Otra pantalla
      </Boton>
    </View>
  );
}

function OtraPantalla() {
  return <Text>Otra pantalla del módulo</Text>;
}

/** Abre la pantalla de prueba con la sesión de la administración ya leída y toca su "Descargar". */
async function abrirLaPieza(plataforma: Plataforma, pantalla: DeLaPantalla = DE_LOS_INDICADORES) {
  deLaPantalla = pantalla;
  await prepararElTelefonoConLaAdministracion(plataforma);
  await renderRouter(
    {
      'administracion/prueba-de-la-descarga': PantallaDePrueba,
      'administracion/otra-pantalla': OtraPantalla,
      'inicio-de-sesion': InicioDeSesion,
    },
    { initialUrl: RUTA_DE_PRUEBA, wrapper: ProveedorDeSesion },
  );
  await waitFor(() => expect(screen.getByTestId('estado-de-la-sesion')).toHaveTextContent('con-sesion'));
  await fireEvent.press(screen.getByRole('button', { name: 'Descargar' }));
  expect(await screen.findByText('Descargar en CSV y en Excel')).toBeOnTheScreen();
}

function pieza() {
  return within(screen.getByTestId('pedir-descarga'));
}

async function escribir(campo: 'Desde' | 'Hasta', texto: string) {
  await fireEvent.changeText(pieza().getByLabelText(campo), texto);
}

async function pedirLaDescarga() {
  await fireEvent.press(pieza().getByRole('button', { name: 'Pedir la descarga' }));
}

/** La única descarga que creó el servidor. */
function laCreada() {
  expect(descargas.descargas).toHaveLength(1);
  return descargas.descargas[0];
}

describe('administracion-app-E03: la entrada a Descargas', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E03 — (%s) "Descargas" del inicio abre su pantalla dentro de la app, y "Regresar" vuelve al inicio',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma);
      expect(await screen.findByRole('header', { name: 'Administración' })).toBeOnTheScreen();

      await fireEvent.press(screen.getByRole('link', { name: 'Descargas' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LAS_DESCARGAS));
      expect(await screen.findByRole('header', { name: 'Descargas' })).toBeOnTheScreen();
      expect(await screen.findByText('Tus descargas de los últimos 7 días.')).toBeOnTheScreen();
      // Antes, el inicio pidió sus dos conteos (administracion-app-E03).
      expect(servidor().peticiones().map(({ metodo, ruta }) => `${metodo} ${ruta}`)).toEqual([
        'GET /cuenta',
        `GET ${RUTAS_DE_LOS_CONTEOS.repartidores}`,
        `GET ${RUTAS_DE_LOS_CONTEOS.monitoreo}`,
        `GET ${RUTA_DE_LAS_DESCARGAS}`,
      ]);

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_INICIO));
    },
  );
});

describe('administracion-app-E05: la sesión vence en la pantalla Descargas', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el 401 de la lista, la app borra la sesión del teléfono y abre el inicio de sesión',
    async (plataforma) => {
      // El token vence o se revoca después de abrir la app: la lista de descargas recibe el 401.
      servidor().interceptar = ({ metodo, ruta }) =>
        metodo === 'GET' && ruta === RUTA_DE_LAS_DESCARGAS ? respuesta(401, { message: 'Unauthenticated.' }) : undefined;

      await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LAS_DESCARGAS);

      await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
      expect(await screen.findByLabelText('Contraseña')).toBeOnTheScreen();
      expect(servidor().peticiones()).toContainEqual(
        expect.objectContaining({
          metodo: 'GET',
          ruta: RUTA_DE_LAS_DESCARGAS,
          autorizacion: `Bearer ${TOKEN_DE_LA_ADMINISTRACION}`,
        }),
      );
      await expect(leerToken()).resolves.toBeNull();
      await expect(leerRol()).resolves.toBeNull();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) si la sesión vence antes de guardar, el archivo no se baja y la app abre el inicio de sesión',
    async (plataforma) => {
      descargas.descargas = [descarga({ id_publico: '01JDDESCARGALISTA000000001' })];
      const bajar = bajarConElServidor(servidor());
      const selector = jest.spyOn(Directory, 'pickDirectoryAsync').mockResolvedValue(carpetaElegida());
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LAS_DESCARGAS);
      await screen.findByTestId('descarga-01JDDESCARGALISTA000000001');

      servidor().vencerLaSesion();
      await fireEvent.press(screen.getByRole('button', { name: 'Guardar .csv' }));

      await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
      expect(bajar).toHaveBeenCalledTimes(1);
      expect(selector).not.toHaveBeenCalled();
      await expect(leerToken()).resolves.toBeNull();
    },
  );
});

describe('administracion-app-E59: las descargas de la cuenta', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E59 — (%s) muestra sus descargas vivas, de la más nueva a la más vieja, con su reporte, el rango de su archivo y cuándo se pidió en renglones separados, su estado y, si está lista, sus botones para guardar; ni la borrada ni la de otra cuenta',
    async (plataforma) => {
      descargas.descargas = [
        // Más vieja que los días de vida: ya se borró (exportacion-E12).
        descarga({ id_publico: '01JDDESCARGAVIEJA000000001', pedida_en: instante(9, 0, 8), viva: false }),
        descarga({ id_publico: '01JDDESCARGALISTA000000001', pedida_en: instante(17, 20, 1), estado: 'lista' }),
        descarga({
          id_publico: '01JDDESCARGAFALLO000000001',
          reporte: 'conciliacion-con-la-pasarela',
          desde: '2026-11-01',
          hasta: '2026-11-15',
          pedida_en: instante(10, 42),
          estado: 'no_se_pudo',
        }),
        // De otra cuenta de administración (exportacion-E11).
        descarga({ id_publico: '01JDDESCARGAAJENA000000001', pedida_en: instante(11, 0), cuenta: '01JDOTRAADMINISTRACION0001' }),
      ];

      await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LAS_DESCARGAS);

      const tarjetas = await screen.findAllByTestId(/^descarga-/);
      expect(tarjetas.map((tarjeta) => tarjeta.props.testID)).toEqual([
        'descarga-01JDDESCARGAFALLO000000001',
        'descarga-01JDDESCARGALISTA000000001',
      ]);

      const nueva = within(tarjetas[0]);
      expect(nueva.getByText('Conciliación')).toBeOnTheScreen();
      // El rango arriba y cuándo se pidió abajo, en dos renglones; la conciliación no lleva granularidad.
      expect(nueva.getAllByText(/^(Del|Pedida) /).map((renglon) => renglon.props.children)).toEqual([
        'Del 01/11/2026 al 15/11/2026',
        'Pedida hoy, 10:42',
      ]);
      expect(within(nueva.getByTestId('estado-de-la-descarga')).getByText('No se pudo generar')).toBeOnTheScreen();
      expect(nueva.queryByRole('button', { name: /^Guardar/ })).not.toBeOnTheScreen();
      expect(nueva.getByRole('button', { name: 'Pedirla otra vez' })).toBeOnTheScreen();

      const vieja = within(tarjetas[1]);
      expect(vieja.getByText('Pedidos totales')).toBeOnTheScreen();
      expect(vieja.getAllByText(/^(Del|Pedida) /).map((renglon) => renglon.props.children)).toEqual([
        'Del 26/10/2026 al 29/11/2026',
        'Pedida ayer, 17:20',
      ]);
      expect(within(vieja.getByTestId('estado-de-la-descarga')).getByText('Lista')).toBeOnTheScreen();
      expect(vieja.getByRole('button', { name: 'Guardar .csv' })).toBeOnTheScreen();
      expect(vieja.getByRole('button', { name: 'Guardar .xlsx' })).toBeOnTheScreen();

      expect(screen.queryByTestId('descarga-01JDDESCARGAVIEJA000000001')).not.toBeOnTheScreen();
      expect(screen.queryByTestId('descarga-01JDDESCARGAAJENA000000001')).not.toBeOnTheScreen();
      // Ninguna se está generando: la pantalla no consulta nada más que la lista.
      expect(servidor().peticiones().filter(({ ruta }) => ruta.startsWith(RUTA_DE_LAS_DESCARGAS))).toHaveLength(1);
    },
  );

  test('administracion-app-E59 — una descarga pedida antes de ayer dice su fecha', async () => {
    descargas.descargas = [descarga({ id_publico: '01JDDESCARGALISTA000000001', pedida_en: instante(9, 5, 3) })];
    const dia = new Date(instante(9, 5, 3));
    const fecha = [dia.getDate(), dia.getMonth() + 1].map((parte) => String(parte).padStart(2, '0')).join('/');

    await abrirLaAppConLaAdministracion('android', RUTA_DE_LAS_DESCARGAS);

    expect(await screen.findByText(`Pedida el ${fecha}/${dia.getFullYear()}, 09:05`)).toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)('administracion-app-E59 — (%s) sin descargas, dice dónde se piden', async (plataforma) => {
    await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LAS_DESCARGAS);

    expect(
      await screen.findByText(
        'Todavía no hay descargas. Pide una con «Descargar» en Indicadores, Ingresos o Conciliación.',
      ),
    ).toBeOnTheScreen();
    expect(screen.queryByTestId(/^descarga-/)).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E59 — (%s) dice los días que vive una descarga como los da el servidor, no un número de la app',
    async (plataforma) => {
      descargas.diasDeVida = 3;
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LAS_DESCARGAS);
      expect(await screen.findByText('Tus descargas de los últimos 3 días.')).toBeOnTheScreen();
      expect(screen.queryByText('Tus descargas de los últimos 7 días.')).not.toBeOnTheScreen();

      await screen.unmount();
      descargas.diasDeVida = 1;
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LAS_DESCARGAS);
      expect(await screen.findByText('Tus descargas del último día.')).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E59 — (%s) los ingresos y la conciliación llevan el nombre de su pantalla, como en la imagen 54; los indicadores, el que da el servidor',
    async (plataforma) => {
      descargas.descargas = [
        descarga({ id_publico: '01JDDESCARGAINGRESOS000001', reporte: 'ingresos-por-periodo', pedida_en: instante(10, 45) }),
        descarga({ id_publico: '01JDDESCARGATOTALES0000001', pedida_en: instante(10, 42) }),
        descarga({
          id_publico: '01JDDESCARGACONCILIA000001',
          reporte: 'conciliacion-con-la-pasarela',
          pedida_en: instante(17, 20, 1),
        }),
      ];

      await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LAS_DESCARGAS);

      const nombres = (await screen.findAllByTestId(/^descarga-/)).map(
        (tarjeta) => within(tarjeta).getAllByText(/^(Ingresos|Pedidos totales|Conciliación)/)[0].props.children,
      );
      expect(nombres).toEqual(['Ingresos', 'Pedidos totales', 'Conciliación']);
      expect(screen.queryByText('Ingresos por periodo')).not.toBeOnTheScreen();
      expect(screen.queryByText('Conciliación con la pasarela')).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E59 — (%s) si la lista no llega, lo dice con el mensaje de la app y ofrece "Intentar de nuevo", que la vuelve a pedir',
    async (plataforma) => {
      descargas.descargas = [descarga({ id_publico: '01JDDESCARGALISTA000000001' })];
      servidor().interceptar = ({ ruta }) => {
        if (ruta === RUTA_DE_LAS_DESCARGAS) {
          throw new TypeError('Network request failed');
        }
        return undefined;
      };

      await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LAS_DESCARGAS);

      expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
      expect(screen.queryByTestId(/^descarga-/)).not.toBeOnTheScreen();
      await expect(leerToken()).resolves.toBe(TOKEN_DE_LA_ADMINISTRACION);

      // Vuelve la conexión.
      servidor().interceptar = null;
      await fireEvent.press(screen.getByRole('button', { name: 'Intentar de nuevo' }));

      expect(await screen.findByTestId('descarga-01JDDESCARGALISTA000000001')).toBeOnTheScreen();
      expect(screen.queryByText(MENSAJES.sinConexion)).not.toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Intentar de nuevo' })).not.toBeOnTheScreen();
      expect(descargas.consultasDeLaLista()).toBe(2);
    },
  );
});

describe('administracion-app-E56: pedir la descarga de un reporte', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E56 — (%s) "Descargar" pide confirmar el rango, con el de la pantalla por omisión, y "Pedir la descarga" manda POST /administracion/descargas',
    async (plataforma) => {
      await abrirLaPieza(plataforma);

      expect(pieza().getByLabelText('Desde').props.value).toBe('26/10/2026');
      expect(pieza().getByLabelText('Hasta').props.value).toBe('29/11/2026');
      expect(pieza().getByText('Ver por')).toBeOnTheScreen();
      expect(pieza().getByRole('tab', { name: 'Semana', selected: true })).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([]);

      await pedirLaDescarga();

      expect(await screen.findByText('El archivo se está generando.')).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([
        { reporte: 'pedidos-totales', desde: '2026-10-26', hasta: '2026-11-29', granularidad: 'semana' },
      ]);
      expect(laCreada()).toMatchObject({ reporte: 'pedidos-totales', estado: 'generandose' });
      expect(pieza().getByText('Del 26/10/2026 al 29/11/2026, por semana')).toBeOnTheScreen();
      const [peticion] = servidor()
        .peticiones()
        .filter(({ metodo, ruta }) => metodo === 'POST' && ruta === RUTA_DE_LAS_DESCARGAS);
      expect(peticion.autorizacion).toBe(`Bearer ${TOKEN_DE_LA_ADMINISTRACION}`);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E56 — (%s) con otro rango y otra granularidad, manda los que eligió',
    async (plataforma) => {
      await abrirLaPieza(plataforma);

      await escribir('Desde', '01112026');
      await escribir('Hasta', '15112026');
      await fireEvent.press(pieza().getByRole('tab', { name: 'Día' }));
      expect(pieza().getByLabelText('Desde').props.value).toBe('01/11/2026');
      await pedirLaDescarga();

      expect(await screen.findByText('Del 01/11/2026 al 15/11/2026, por día')).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([
        { reporte: 'pedidos-totales', desde: '2026-11-01', hasta: '2026-11-15', granularidad: 'dia' },
      ]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E56 — (%s) la de la conciliación no pregunta la granularidad ni la manda',
    async (plataforma) => {
      await abrirLaPieza(plataforma, DE_LA_CONCILIACION);

      expect(pieza().queryByText('Ver por')).not.toBeOnTheScreen();
      expect(pieza().queryByRole('tablist')).not.toBeOnTheScreen();
      await pedirLaDescarga();

      expect(await screen.findByText('Del 01/11/2026 al 15/11/2026')).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([
        { reporte: 'conciliacion-con-la-pasarela', desde: '2026-11-01', hasta: '2026-11-15' },
      ]);
    },
  );

  test('administracion-app-E56 — un reporte que no es uno de los exportadores recibe el 422 del servidor, que la app dice, sin crear nada', async () => {
    await abrirLaPieza('android', { ...DE_LOS_INDICADORES, reporte: 'activas-frente-a-registradas' as CodigoDelReporte });

    await pedirLaDescarga();

    expect(await pieza().findByText(MENSAJE_DEL_REPORTE)).toBeOnTheScreen();
    expect(descargas.descargas).toEqual([]);
    expect(pieza().getByRole('button', { name: 'Pedir la descarga' })).toBeOnTheScreen();
    expect(pieza().queryByText('El archivo se está generando.')).not.toBeOnTheScreen();
  });

  test('administracion-app-E56 — una granularidad que el servidor rechaza va junto a "Ver por"', async () => {
    servidor().interceptar = ({ metodo, ruta }) =>
      metodo === 'POST' && ruta === RUTA_DE_LAS_DESCARGAS
        ? respuesta(422, {
            message: MENSAJE_DE_LA_GRANULARIDAD,
            errors: { granularidad: [MENSAJE_DE_LA_GRANULARIDAD] },
          })
        : undefined;
    await abrirLaPieza('ios');

    await pedirLaDescarga();

    expect(await pieza().findByText(MENSAJE_DE_LA_GRANULARIDAD)).toBeOnTheScreen();
    expect(pieza().getAllByText(MENSAJE_DE_LA_GRANULARIDAD)).toHaveLength(1);
  });

  test.each(PLATAFORMAS)('administracion-app-E56 — (%s) "Cancelar" cierra la pieza sin pedir nada', async (plataforma) => {
    await abrirLaPieza(plataforma);

    await fireEvent.press(pieza().getByRole('button', { name: 'Cancelar' }));

    expect(screen.queryByTestId('pedir-descarga')).not.toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Descargar' })).toBeOnTheScreen();
    expect(descargas.pedidas()).toEqual([]);
  });

  test('administracion-app-E56 — sin conexión, lo dice y no deja de ofrecer pedirla', async () => {
    await abrirLaPieza('android');
    servidor().interceptar = ({ metodo }) => {
      if (metodo === 'POST') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await pedirLaDescarga();

    expect(await pieza().findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(pieza().getByRole('button', { name: 'Pedir la descarga' })).toBeOnTheScreen();
  });
});

describe('administracion-app-E57: mientras se genera y cuando está lista', () => {
  /** Abre la pieza y pide la descarga con el reloj falso; el servidor la crea generándose. */
  async function pedirlaYEsperar(plataforma: Plataforma, pantalla: DeLaPantalla = DE_LOS_INDICADORES) {
    await abrirLaPieza(plataforma, pantalla);
    jest.useFakeTimers();
    await pedirLaDescarga();
    expect(await screen.findByText('El archivo se está generando.')).toBeOnTheScreen();
    return laCreada();
  }

  test.each(PLATAFORMAS)(
    'administracion-app-E57 — (%s) dice que el archivo se está generando y consulta su estado cada 5 segundos',
    async (plataforma) => {
      const creada = await pedirlaYEsperar(plataforma);
      await waitFor(() => expect(descargas.consultasDe(creada.id_publico)).toBe(1));

      expect(pieza().getByText('Se revisa cada 5 s mientras esta pantalla está abierta.')).toBeOnTheScreen();
      await pasan(CINCO_SEGUNDOS - 1);
      expect(descargas.consultasDe(creada.id_publico)).toBe(1);
      await pasan(1);
      expect(descargas.consultasDe(creada.id_publico)).toBe(2);
      await pasan(CINCO_SEGUNDOS);
      expect(descargas.consultasDe(creada.id_publico)).toBe(3);
      expect(pieza().getByText('El archivo se está generando.')).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E57 — (%s) cuando está lista ofrece "Guardar .csv" y "Guardar .xlsx" y deja de consultar',
    async (plataforma) => {
      const creada = await pedirlaYEsperar(plataforma);
      await waitFor(() => expect(descargas.consultasDe(creada.id_publico)).toBe(1));

      // La cola la dio por terminada con sus dos archivos CSV en su disco (ADR-0022, decisión 15).
      descargas.de(creada.id_publico).estado = 'lista';
      await pasan(CINCO_SEGUNDOS);

      expect(await pieza().findByRole('button', { name: 'Guardar .csv' })).toBeOnTheScreen();
      expect(pieza().getByRole('button', { name: 'Guardar .xlsx' })).toBeOnTheScreen();
      expect(pieza().queryByText('El archivo se está generando.')).not.toBeOnTheScreen();
      const consultas = descargas.consultasDe(creada.id_publico);
      await pasan(CINCO_SEGUNDOS * 4);
      expect(descargas.consultasDe(creada.id_publico)).toBe(consultas);
    },
  );

  test('administracion-app-E57 y administracion-app-E58 — lista, guarda el archivo y dice en qué carpeta quedó', async () => {
    const creada = await pedirlaYEsperar('android');
    descargas.de(creada.id_publico).estado = 'lista';
    await pasan(CINCO_SEGUNDOS);
    bajarConElServidor(servidor());
    jest.spyOn(Directory, 'pickDirectoryAsync').mockResolvedValue(carpetaElegida());

    await fireEvent.press(await pieza().findByRole('button', { name: 'Guardar .csv' }));

    expect(await pieza().findByText('Se guardó el archivo .csv en la carpeta Descargas.')).toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E57 — (%s) si no se pudo generar, lo dice y ofrece pedirla otra vez, con el mismo rango',
    async (plataforma) => {
      const creada = await pedirlaYEsperar(plataforma);
      await waitFor(() => expect(descargas.consultasDe(creada.id_publico)).toBe(1));

      // La cola la dio por terminada sin su archivo: el trabajo que lo escribe falló.
      descargas.de(creada.id_publico).estado = 'no_se_pudo';
      await pasan(CINCO_SEGUNDOS);

      expect(await pieza().findByText('No se pudo generar el archivo.')).toBeOnTheScreen();
      expect(pieza().queryByRole('button', { name: /^Guardar/ })).not.toBeOnTheScreen();
      const consultas = descargas.consultasDe(creada.id_publico);
      await pasan(CINCO_SEGUNDOS * 2);
      expect(descargas.consultasDe(creada.id_publico)).toBe(consultas);

      await fireEvent.press(pieza().getByRole('button', { name: 'Pedirla otra vez' }));

      expect(await pieza().findByText('El archivo se está generando.')).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([
        { reporte: 'pedidos-totales', desde: '2026-10-26', hasta: '2026-11-29', granularidad: 'semana' },
        { reporte: 'pedidos-totales', desde: '2026-10-26', hasta: '2026-11-29', granularidad: 'semana' },
      ]);
      const [, otra] = descargas.descargas;
      await waitFor(() => expect(descargas.consultasDe(otra.id_publico)).toBe(1));
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E57 — (%s) solo consulta con la pantalla visible y la app al frente',
    async (plataforma) => {
      const creada = await pedirlaYEsperar(plataforma);
      await waitFor(() => expect(descargas.consultasDe(creada.id_publico)).toBe(1));

      // La app al fondo: no consulta; al frente, consulta de inmediato.
      await mandarLaAppA('background');
      await pasan(CINCO_SEGUNDOS * 3);
      expect(descargas.consultasDe(creada.id_publico)).toBe(1);
      await mandarLaAppA('active');
      expect(descargas.consultasDe(creada.id_publico)).toBe(2);

      // Otra pantalla encima: deja de consultar.
      await fireEvent.press(screen.getByRole('button', { name: 'Otra pantalla' }));
      await waitFor(() => expect(screen).toHavePathname(OTRA_RUTA));
      await pasan(CINCO_SEGUNDOS * 3);
      expect(descargas.consultasDe(creada.id_publico)).toBe(2);
    },
  );

  test('administracion-app-E57 — si una consulta no llega, lo dice y la siguiente lo quita', async () => {
    const creada = await pedirlaYEsperar('ios');
    await waitFor(() => expect(descargas.consultasDe(creada.id_publico)).toBe(1));

    servidor().interceptar = ({ metodo }) => {
      if (metodo === 'GET') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };
    await pasan(CINCO_SEGUNDOS);
    expect(await pieza().findByText(MENSAJES.sinConexion)).toBeOnTheScreen();

    servidor().interceptar = null;
    descargas.de(creada.id_publico).estado = 'lista';
    await pasan(CINCO_SEGUNDOS);
    expect(await pieza().findByRole('button', { name: 'Guardar .csv' })).toBeOnTheScreen();
    expect(pieza().queryByText(MENSAJES.sinConexion)).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E57 — (%s) en la pantalla Descargas, la que se genera se revisa cada 5 s y ofrece guardar cuando está lista',
    async (plataforma) => {
      descargas.descargas = [
        descarga({
          id_publico: '01JDDESCARGAGENERA00000001',
          reporte: 'ingresos-por-periodo',
          pedida_en: instante(10, 45),
          estado: 'generandose',
        }),
      ];
      jest.useFakeTimers();
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LAS_DESCARGAS);
      const tarjeta = within(await screen.findByTestId('descarga-01JDDESCARGAGENERA00000001'));
      expect(within(tarjeta.getByTestId('estado-de-la-descarga')).getByText('Generándose')).toBeOnTheScreen();
      expect(tarjeta.getByText('Se revisa cada 5 s mientras esta pantalla está abierta.')).toBeOnTheScreen();
      await waitFor(() => expect(descargas.consultasDe('01JDDESCARGAGENERA00000001')).toBe(1));

      descargas.de('01JDDESCARGAGENERA00000001').estado = 'lista';
      await pasan(CINCO_SEGUNDOS);

      expect(await tarjeta.findByRole('button', { name: 'Guardar .csv' })).toBeOnTheScreen();
      expect(within(tarjeta.getByTestId('estado-de-la-descarga')).getByText('Lista')).toBeOnTheScreen();
      expect(tarjeta.queryByText('Se revisa cada 5 s mientras esta pantalla está abierta.')).not.toBeOnTheScreen();
      await pasan(CINCO_SEGUNDOS * 2);
      expect(descargas.consultasDe('01JDDESCARGAGENERA00000001')).toBe(2);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E56 y administracion-app-E57 — (%s) con la cola en la misma petición, la descarga llega lista: ofrece "Guardar .csv" y "Guardar .xlsx" sin consultar su estado',
    async (plataforma) => {
      descargas.estadoAlNacer = 'lista';
      await abrirLaPieza(plataforma);
      jest.useFakeTimers();

      await pedirLaDescarga();

      expect(await pieza().findByRole('button', { name: 'Guardar .csv' })).toBeOnTheScreen();
      expect(pieza().getByRole('button', { name: 'Guardar .xlsx' })).toBeOnTheScreen();
      expect(pieza().queryByText('El archivo se está generando.')).not.toBeOnTheScreen();
      await pasan(CINCO_SEGUNDOS * 3);
      expect(descargas.consultasDe(laCreada().id_publico)).toBe(0);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E57 — (%s) si la consulta responde que la descarga ya no existe, deja de consultar, ya no dice que se genera ni que se revisa, y ofrece pedirla otra vez',
    async (plataforma) => {
      const creada = await pedirlaYEsperar(plataforma);
      await waitFor(() => expect(descargas.consultasDe(creada.id_publico)).toBe(1));

      // Se borró a sus días de vida mientras se esperaba (exportacion-E12).
      descargas.de(creada.id_publico).viva = false;
      await pasan(CINCO_SEGUNDOS);

      expect(await pieza().findByText(NO_EXISTE_LA_DESCARGA)).toBeOnTheScreen();
      expect(pieza().queryByText('El archivo se está generando.')).not.toBeOnTheScreen();
      expect(pieza().queryByText('Se revisa cada 5 s mientras esta pantalla está abierta.')).not.toBeOnTheScreen();
      await pasan(CINCO_SEGUNDOS * 2);
      expect(descargas.consultasDe(creada.id_publico)).toBe(2);

      await fireEvent.press(pieza().getByRole('button', { name: 'Pedirla otra vez' }));

      expect(await pieza().findByText('El archivo se está generando.')).toBeOnTheScreen();
      expect(pieza().queryByText(NO_EXISTE_LA_DESCARGA)).not.toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([
        { reporte: 'pedidos-totales', desde: '2026-10-26', hasta: '2026-11-29', granularidad: 'semana' },
        { reporte: 'pedidos-totales', desde: '2026-10-26', hasta: '2026-11-29', granularidad: 'semana' },
      ]);
    },
  );

  test('administracion-app-E57 — en la pantalla Descargas, si la que se genera ya no existe, lo dice y deja de decir que se revisa', async () => {
    descargas.descargas = [descarga({ id_publico: '01JDDESCARGAGENERA00000001', estado: 'generandose' })];
    jest.useFakeTimers();
    await abrirLaAppConLaAdministracion('android', RUTA_DE_LAS_DESCARGAS);
    const tarjeta = within(await screen.findByTestId('descarga-01JDDESCARGAGENERA00000001'));
    await waitFor(() => expect(descargas.consultasDe('01JDDESCARGAGENERA00000001')).toBe(1));

    descargas.de('01JDDESCARGAGENERA00000001').viva = false;
    await pasan(CINCO_SEGUNDOS);

    expect(await tarjeta.findByText(NO_EXISTE_LA_DESCARGA)).toBeOnTheScreen();
    expect(tarjeta.queryByText('Se revisa cada 5 s mientras esta pantalla está abierta.')).not.toBeOnTheScreen();
    await pasan(CINCO_SEGUNDOS * 2);
    expect(descargas.consultasDe('01JDDESCARGAGENERA00000001')).toBe(2);
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E57 — (%s) en la pantalla Descargas, la de la conciliación que pasa a no se pudo lo dice y ofrece "Pedirla otra vez", que pide otra con el mismo reporte y el mismo rango; la nueva entra arriba y se revisa cada 5 s',
    async (plataforma) => {
      descargas.descargas = [
        descarga({
          id_publico: '01JDDESCARGAFALLO000000001',
          reporte: 'conciliacion-con-la-pasarela',
          desde: '2026-11-01',
          hasta: '2026-11-15',
          pedida_en: instante(17, 20, 1),
          estado: 'generandose',
        }),
      ];
      jest.useFakeTimers();
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LAS_DESCARGAS);
      const fallida = within(await screen.findByTestId('descarga-01JDDESCARGAFALLO000000001'));
      await waitFor(() => expect(descargas.consultasDe('01JDDESCARGAFALLO000000001')).toBe(1));
      expect(fallida.queryByRole('button', { name: 'Pedirla otra vez' })).not.toBeOnTheScreen();

      // La cola la dio por terminada sin su archivo: el trabajo que lo escribe falló.
      descargas.de('01JDDESCARGAFALLO000000001').estado = 'no_se_pudo';
      await pasan(CINCO_SEGUNDOS);

      expect(await within(fallida.getByTestId('estado-de-la-descarga')).findByText('No se pudo generar')).toBeOnTheScreen();
      expect(fallida.queryByText('Se revisa cada 5 s mientras esta pantalla está abierta.')).not.toBeOnTheScreen();
      await fireEvent.press(fallida.getByRole('button', { name: 'Pedirla otra vez' }));

      await waitFor(() => expect(screen.getAllByTestId(/^descarga-/)).toHaveLength(2));
      // La conciliación no lleva granularidad: se pide con el mismo rango, sin preguntar nada.
      expect(descargas.pedidas()).toEqual([
        { reporte: 'conciliacion-con-la-pasarela', desde: '2026-11-01', hasta: '2026-11-15' },
      ]);
      const [, otra] = descargas.descargas;
      expect(screen.getAllByTestId(/^descarga-/).map((tarjeta) => tarjeta.props.testID)).toEqual([
        `descarga-${otra.id_publico}`,
        'descarga-01JDDESCARGAFALLO000000001',
      ]);
      const nueva = within(screen.getByTestId(`descarga-${otra.id_publico}`));
      expect(within(nueva.getByTestId('estado-de-la-descarga')).getByText('Generándose')).toBeOnTheScreen();
      expect(nueva.getByText('Del 01/11/2026 al 15/11/2026')).toBeOnTheScreen();
      await waitFor(() => expect(descargas.consultasDe(otra.id_publico)).toBe(1));
      await pasan(CINCO_SEGUNDOS);
      expect(descargas.consultasDe(otra.id_publico)).toBe(2);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E57 — (%s) en la pantalla Descargas, "Pedirla otra vez" en la de otro reporte abre la pieza de la descarga con su rango y "Ver por", porque la descarga no trae su granularidad; la nueva se pide con lo que se elija y entra arriba',
    async (plataforma) => {
      descargas.descargas = [
        descarga({ id_publico: '01JDDESCARGAFALLO000000001', reporte: 'pedidos-totales', estado: 'no_se_pudo' }),
      ];
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LAS_DESCARGAS);
      const fallida = within(await screen.findByTestId('descarga-01JDDESCARGAFALLO000000001'));

      await fireEvent.press(fallida.getByRole('button', { name: 'Pedirla otra vez' }));

      const enLaTarjeta = within(fallida.getByTestId('pedir-descarga'));
      expect(enLaTarjeta.getByLabelText('Desde').props.value).toBe('26/10/2026');
      expect(enLaTarjeta.getByLabelText('Hasta').props.value).toBe('29/11/2026');
      // La del formulario del panel por omisión: por día.
      expect(enLaTarjeta.getByRole('tab', { name: 'Día', selected: true })).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([]);

      // "Cancelar" la cierra sin pedir nada.
      await fireEvent.press(enLaTarjeta.getByRole('button', { name: 'Cancelar' }));
      expect(fallida.queryByTestId('pedir-descarga')).not.toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([]);

      await fireEvent.press(fallida.getByRole('button', { name: 'Pedirla otra vez' }));
      const otraVez = within(fallida.getByTestId('pedir-descarga'));
      await fireEvent.press(otraVez.getByRole('tab', { name: 'Semana' }));
      await fireEvent.press(otraVez.getByRole('button', { name: 'Pedir la descarga' }));

      await waitFor(() => expect(screen.getAllByTestId(/^descarga-/)).toHaveLength(2));
      expect(descargas.pedidas()).toEqual([
        { reporte: 'pedidos-totales', desde: '2026-10-26', hasta: '2026-11-29', granularidad: 'semana' },
      ]);
      const [, nueva] = descargas.descargas;
      expect(screen.getAllByTestId(/^descarga-/).map((tarjeta) => tarjeta.props.testID)).toEqual([
        `descarga-${nueva.id_publico}`,
        'descarga-01JDDESCARGAFALLO000000001',
      ]);
      expect(screen.queryByTestId('pedir-descarga')).not.toBeOnTheScreen();
      expect(within(screen.getByTestId(`descarga-${nueva.id_publico}`)).getByText('Del 26/10/2026 al 29/11/2026')).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E57 — (%s) en la pantalla Descargas, la que no se pudo generar y llega sin su rango también ofrece "Pedirla otra vez": abre la pieza con los campos vacíos, aun en la conciliación, y la nueva se pide con el rango que se escriba',
    async (plataforma) => {
      descargas.descargas = [
        descarga({
          id_publico: '01JDDESCARGAFALLO000000001',
          reporte: 'conciliacion-con-la-pasarela',
          desde: null,
          hasta: null,
          estado: 'no_se_pudo',
        }),
      ];
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LAS_DESCARGAS);
      const fallida = within(await screen.findByTestId('descarga-01JDDESCARGAFALLO000000001'));
      expect(fallida.queryByText(/^Del /)).not.toBeOnTheScreen();

      await fireEvent.press(fallida.getByRole('button', { name: 'Pedirla otra vez' }));

      const enLaTarjeta = within(fallida.getByTestId('pedir-descarga'));
      expect(enLaTarjeta.getByLabelText('Desde').props.value).toBe('');
      expect(enLaTarjeta.getByLabelText('Hasta').props.value).toBe('');
      // La conciliación no lleva granularidad: no pregunta "Ver por".
      expect(enLaTarjeta.queryByText('Ver por')).not.toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([]);

      await fireEvent.changeText(enLaTarjeta.getByLabelText('Desde'), '01112026');
      await fireEvent.changeText(enLaTarjeta.getByLabelText('Hasta'), '15112026');
      await fireEvent.press(enLaTarjeta.getByRole('button', { name: 'Pedir la descarga' }));

      await waitFor(() => expect(screen.getAllByTestId(/^descarga-/)).toHaveLength(2));
      expect(descargas.pedidas()).toEqual([
        { reporte: 'conciliacion-con-la-pasarela', desde: '2026-11-01', hasta: '2026-11-15' },
      ]);
      const [, nueva] = descargas.descargas;
      expect(screen.getAllByTestId(/^descarga-/)[0].props.testID).toBe(`descarga-${nueva.id_publico}`);
      expect(within(screen.getByTestId(`descarga-${nueva.id_publico}`)).getByText('Del 01/11/2026 al 15/11/2026')).toBeOnTheScreen();
    },
  );
});

describe('administracion-app-E60: el rango de la descarga', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E60 — (%s) del 10 al 20 de marzo manda los dos días, incluidos; y un rango de dos años se acepta',
    async (plataforma) => {
      await abrirLaPieza(plataforma);

      await escribir('Desde', '10032026');
      await escribir('Hasta', '20032026');
      await fireEvent.press(pieza().getByRole('tab', { name: 'Día' }));
      await pedirLaDescarga();
      expect(await screen.findByText('Del 10/03/2026 al 20/03/2026, por día')).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([
        { reporte: 'pedidos-totales', desde: '2026-03-10', hasta: '2026-03-20', granularidad: 'dia' },
      ]);

      await screen.unmount();
      await abrirLaPieza(plataforma);
      await escribir('Desde', '10032024');
      await escribir('Hasta', '10032026');
      await pedirLaDescarga();
      expect(await screen.findByText('El archivo se está generando.')).toBeOnTheScreen();
      expect(descargas.pedidas()[1]).toEqual({
        reporte: 'pedidos-totales',
        desde: '2024-03-10',
        hasta: '2026-03-10',
        granularidad: 'semana',
      });
      expect(descargas.descargas).toHaveLength(2);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E60 — (%s) el rango al revés recibe el 422 del servidor, su mensaje va junto a "Hasta" y no se crea ninguna descarga',
    async (plataforma) => {
      await abrirLaPieza(plataforma);

      await escribir('Desde', '29112026');
      await escribir('Hasta', '26102026');
      await pedirLaDescarga();

      const hasta = within(await pieza().findByTestId('campo-hasta'));
      expect(await hasta.findByText(MENSAJE_DEL_RANGO_AL_REVES)).toBeOnTheScreen();
      expect(within(pieza().getByTestId('campo-desde')).queryByRole('alert')).not.toBeOnTheScreen();
      // La app no lo revisa por su cuenta: lo mandó y el servidor lo rechazó.
      expect(descargas.pedidas()).toEqual([
        { reporte: 'pedidos-totales', desde: '2026-11-29', hasta: '2026-10-26', granularidad: 'semana' },
      ]);
      expect(descargas.descargas).toEqual([]);
      expect(pieza().queryByText('El archivo se está generando.')).not.toBeOnTheScreen();
      expect(pieza().getAllByText(MENSAJE_DEL_RANGO_AL_REVES)).toHaveLength(1);

      // Con el rango corregido, se pide y el error se va.
      await escribir('Hasta', '30112026');
      await pedirLaDescarga();
      expect(await screen.findByText('El archivo se está generando.')).toBeOnTheScreen();
      // Por semana, el servidor lo ajusta a semanas completas: del lunes 23 de noviembre al domingo 6 de diciembre.
      expect(laCreada()).toMatchObject({ archivos: { csv: 'pedidos-totales-2026-11-23-al-2026-12-06.csv' } });
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E60 — (%s) por semana, la pieza dice el rango que cubre el archivo, ya ajustado por el servidor a semanas completas, no el que se escribió',
    async (plataforma) => {
      await abrirLaPieza(plataforma);

      await escribir('Desde', '10032026');
      await escribir('Hasta', '20032026');
      await pedirLaDescarga();

      expect(await screen.findByText('Del 09/03/2026 al 22/03/2026, por semana')).toBeOnTheScreen();
      expect(screen.queryByText('Del 10/03/2026 al 20/03/2026, por semana')).not.toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([
        { reporte: 'pedidos-totales', desde: '2026-03-10', hasta: '2026-03-20', granularidad: 'semana' },
      ]);
      expect(laCreada()).toMatchObject({ archivos: { csv: 'pedidos-totales-2026-03-09-al-2026-03-22.csv' } });
    },
  );

  test('administracion-app-E60 — una fecha a medias se dice junto a su campo, sin pedir nada (el formato de la fecha, como el filtro de «Mis pedidos»: design.md, sección 4, «Descargas en CSV y Excel»)', async () => {
    await abrirLaPieza('android');

    await escribir('Desde', '1003');
    await pedirLaDescarga();

    expect(
      await within(pieza().getByTestId('campo-desde')).findByText('Escribe la fecha como DD/MM/AAAA.'),
    ).toBeOnTheScreen();
    expect(descargas.pedidas()).toEqual([]);
  });
});

// --- La pieza en las pantallas que la ponen (tarea 20.1) ------------------------------------------------------------
//
// Indicadores, Ingresos y Conciliación, en la app real. Sin rango elegido, cada una usa el que da el servidor simulado:
// en Indicadores, el mes en curso hasta hoy por día (RANGO_DEL_MES_EN_CURSO); en Ingresos, el de
// INGRESOS_DEL_MES_EN_CURSO; en Conciliación, el periodo que se concilió.

const PANTALLA_DE_LOS_INDICADORES = '/administracion/indicadores';
const PANTALLA_DE_LOS_INGRESOS = '/administracion/ingresos';
const PANTALLA_DE_LA_CONCILIACION = '/administracion/conciliacion';

// Cada cuánto se ponen al día solas las cifras de Indicadores: indicadores.refresco del servidor simulado.
const TREINTA_SEGUNDOS = 30_000;

/** El rango que usó el servidor en Indicadores sin rango elegido, como lo manda la pieza por omisión. */
const DEL_MES_EN_CURSO = {
  desde: RANGO_DEL_MES_EN_CURSO.desde,
  hasta: RANGO_DEL_MES_EN_CURSO.hasta,
  granularidad: RANGO_DEL_MES_EN_CURSO.granularidad,
};

/** El rango que usó el servidor en Ingresos sin rango elegido, como lo manda la pieza por omisión. */
const DE_LOS_INGRESOS = {
  desde: INGRESOS_DEL_MES_EN_CURSO.rango.desde,
  hasta: INGRESOS_DEL_MES_EN_CURSO.rango.hasta,
  granularidad: INGRESOS_DEL_MES_EN_CURSO.rango.granularidad,
};

type Tarjeta = ReturnType<typeof within>;

/** Abre Indicadores sin rango elegido y espera las cifras de Operaciones. */
async function abrirLosIndicadores(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma, PANTALLA_DE_LOS_INDICADORES);
  await screen.findByText(RANGO_DEL_MES_EN_CURSO.descripcion);
  await screen.findByTestId('indicador-pedidos-totales');
}

/** Toca la pestaña de esa categoría, en la fila de las categorías, y espera la tarjeta de ese indicador. */
async function elegirLaCategoria(nombre: string, indicador: string): Promise<Tarjeta> {
  await fireEvent.press(within(screen.getAllByTestId('pestanas')[0]).getByRole('tab', { name: nombre }));
  return within(await screen.findByTestId(indicador));
}

/**
 * Toca el "Descargar" del encabezado de la tarjeta de un indicador: se va del encabezado y la pieza se abre en la
 * tarjeta, bajo su gráfica (imagen 51).
 */
async function tocarDescargar(tarjeta: Tarjeta, titulo: string) {
  await fireEvent.press(tarjeta.getByRole('link', { name: `Descargar ${titulo}` }));
  expect(tarjeta.getByTestId('pedir-descarga')).toBeOnTheScreen();
  expect(tarjeta.queryByText('Descargar')).not.toBeOnTheScreen();
}

/** "Pedir la descarga" en la pieza abierta en esa tarjeta: puede haber otra abierta en otra tarjeta de la pestaña. */
async function pedirEn(tarjeta: Tarjeta) {
  const enLaTarjeta = within(tarjeta.getByTestId('pedir-descarga'));
  await fireEvent.press(enLaTarjeta.getByRole('button', { name: 'Pedir la descarga' }));
}

/** Los "Descargar" de los indicadores que se ven, como los lee el lector de pantalla. */
function descargasDeLosIndicadores(): string[] {
  return screen
    .getAllByRole('link', { name: /^Descargar / })
    .map((enlace) => enlace.props.accessibilityLabel as string);
}

/** Abre Ingresos sin rango elegido. */
async function abrirLosIngresos(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma, PANTALLA_DE_LOS_INGRESOS);
  await screen.findByText(INGRESOS_DEL_MES_EN_CURSO.rango.descripcion);
}

/** Abre Conciliación, que antes de conciliar no pide nada. */
async function abrirLaConciliacion(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma, PANTALLA_DE_LA_CONCILIACION);
  await screen.findByRole('button', { name: 'Conciliar un periodo' });
}

/** Escribe el periodo, DD/MM/AAAA, toca "Conciliar un periodo", lo confirma en el diálogo y espera el reporte. */
async function conciliar(desde: string, hasta: string) {
  const dialogo = jest.spyOn(Alert, 'alert').mockImplementation(() => {});
  await fireEvent.changeText(screen.getByLabelText('Desde'), desde);
  await fireEvent.changeText(screen.getByLabelText('Hasta'), hasta);
  await fireEvent.press(screen.getByRole('button', { name: 'Conciliar un periodo' }));
  const botones = dialogo.mock.calls[dialogo.mock.calls.length - 1][2] as AlertButton[];
  await act(async () => {
    botones.find((boton) => boton.text === 'Conciliar')?.onPress?.();
  });
  await screen.findByTestId('resumen-de-la-conciliacion');
}

describe('administracion-app-E56: "Descargar" en Indicadores, Ingresos y Conciliación', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E56 — (%s) en Indicadores, "Descargar" de los pedidos totales se va del encabezado y abre la pieza en su tarjeta con el rango y la granularidad de la pantalla por omisión; "Cancelar" lo regresa y "Pedir la descarga" pide su reporte',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);
      const tarjeta = within(screen.getByTestId('indicador-pedidos-totales'));

      await tocarDescargar(tarjeta, 'Pedidos totales');
      expect(pieza().getByLabelText('Desde').props.value).toBe('01/11/2026');
      expect(pieza().getByLabelText('Hasta').props.value).toBe('04/11/2026');
      expect(pieza().getByRole('tab', { name: 'Día', selected: true })).toBeOnTheScreen();
      // La gráfica sigue arriba de la pieza.
      expect(tarjeta.getByTestId('grafica-de-barras')).toBeOnTheScreen();

      await fireEvent.press(pieza().getByRole('button', { name: 'Cancelar' }));
      expect(tarjeta.queryByTestId('pedir-descarga')).not.toBeOnTheScreen();
      expect(tarjeta.getByRole('link', { name: 'Descargar Pedidos totales' })).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([]);

      await tocarDescargar(tarjeta, 'Pedidos totales');
      await pedirLaDescarga();

      expect(await pieza().findByText('El archivo se está generando.')).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([{ reporte: 'pedidos-totales', ...DEL_MES_EN_CURSO }]);
      expect(laCreada()).toMatchObject({ reporte: 'pedidos-totales', estado: 'generandose' });
      expect(pieza().getByText('Del 01/11/2026 al 04/11/2026, por día')).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E56 — (%s) en Indicadores, cada indicador con cifra lleva su "Descargar" y pide su propio reporte: F-I01 a F-I05, F-I07 a F-I09, y F-I11 por colonia o por zona, según la agrupación elegida',
    async (plataforma) => {
      // Nacen listas: cada tarjeta ofrece guardarla sin consultar nada más.
      descargas.estadoAlNacer = 'lista';
      await abrirLosIndicadores(plataforma);
      // La tabla de F-I01 junto al anterior es el mismo reporte que sus barras: no trae otro "Descargar" (imagen 41).
      const comparados = within(screen.getByTestId('indicador-pedidos-totales-comparados'));
      expect(comparados.queryByText('Descargar')).not.toBeOnTheScreen();

      // La categoría, la tarjeta del indicador, su nombre y el reporte que pide.
      const indicadores: [string, string, string, CodigoDelReporte][] = [
        ['Operaciones', 'indicador-pedidos-totales', 'Pedidos totales', 'pedidos-totales'],
        ['Operaciones', 'indicador-pedidos-por-estado', 'Pedidos por estado', 'pedidos-por-estado'],
        ['Usuarios', 'indicador-usuarios-activos', 'Usuarios activos', 'usuarios-activos'],
        [
          'Usuarios',
          'indicador-vuelven-a-pedir',
          'Vuelven a pedir, por mes de registro',
          'usuarios-que-vuelven-a-pedir',
        ],
        ['Usuarios', 'indicador-monto-promedio', 'Monto promedio por pedido', 'monto-promedio-por-pedido'],
        [
          'Socios',
          'indicador-calificacion-por-lavanderia',
          'Calificación por lavandería',
          'calificacion-por-lavanderia',
        ],
        ['Socios', 'indicador-entregas-por-repartidor', 'Entregas por repartidor', 'entregas-por-repartidor'],
        ['Financiero', 'indicador-ingresos-brutos', 'Ingresos brutos', 'ingresos-brutos'],
        ['Geográfico', 'indicador-mapa-de-calor', 'Pedidos por colonia', 'pedidos-por-colonia'],
      ];
      for (const [categoria, indicador, titulo] of indicadores) {
        const tarjeta = await elegirLaCategoria(categoria, indicador);
        await tocarDescargar(tarjeta, titulo);
        await pedirEn(tarjeta);
        expect(await tarjeta.findByRole('button', { name: 'Guardar .csv' })).toBeOnTheScreen();
      }

      // Por zona de servicio, la misma tarjeta pide el reporte de las zonas.
      await fireEvent.press(screen.getByRole('tab', { name: 'Zona de servicio' }));
      await screen.findByRole('header', { name: 'Pedidos por zona' });
      const porZona = within(screen.getByTestId('indicador-mapa-de-calor'));
      await tocarDescargar(porZona, 'Pedidos por zona');
      await pedirEn(porZona);
      expect(await porZona.findByRole('button', { name: 'Guardar .csv' })).toBeOnTheScreen();

      expect(descargas.pedidas()).toEqual([
        ...indicadores.map(([, , , reporte]) => ({ reporte, ...DEL_MES_EN_CURSO })),
        { reporte: 'pedidos-por-zona', ...DEL_MES_EN_CURSO },
      ]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E56 — (%s) en Indicadores, con un rango elegido, la pieza ofrece el que usó el servidor, ya ajustado a semanas completas, y su granularidad',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);
      await fireEvent.press(screen.getByRole('link', { name: 'Cambiar' }));
      const hoja = within(await screen.findByTestId('hoja-del-rango'));
      await fireEvent.changeText(hoja.getByLabelText('Desde'), '01102026');
      await fireEvent.changeText(hoja.getByLabelText('Hasta'), '31102026');
      await fireEvent.press(hoja.getByRole('tab', { name: 'Semana' }));
      await fireEvent.press(hoja.getByRole('button', { name: 'Aplicar' }));
      await screen.findByText(RANGO_DE_OCTUBRE_POR_SEMANA.descripcion);

      await tocarDescargar(within(screen.getByTestId('indicador-pedidos-totales')), 'Pedidos totales');

      expect(pieza().getByLabelText('Desde').props.value).toBe('28/09/2026');
      expect(pieza().getByLabelText('Hasta').props.value).toBe('01/11/2026');
      expect(pieza().getByRole('tab', { name: 'Semana', selected: true })).toBeOnTheScreen();
      await pedirLaDescarga();
      expect(await pieza().findByText('Del 28/09/2026 al 01/11/2026, por semana')).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([
        { reporte: 'pedidos-totales', desde: '2026-09-28', hasta: '2026-11-01', granularidad: 'semana' },
      ]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E56 — (%s) en Ingresos, "Descargar" bajo la tabla abre la pieza en su lugar con el rango y la granularidad de la pantalla por omisión; "Cancelar" lo regresa y "Pedir la descarga" pide el reporte de los ingresos',
    async (plataforma) => {
      await abrirLosIngresos(plataforma);

      await fireEvent.press(screen.getByRole('button', { name: 'Descargar' }));

      expect(screen.queryByRole('button', { name: 'Descargar' })).not.toBeOnTheScreen();
      expect(within(screen.getByTestId('descarga-del-reporte')).getByTestId('pedir-descarga')).toBeOnTheScreen();
      expect(pieza().getByLabelText('Desde').props.value).toBe('01/03/2027');
      expect(pieza().getByLabelText('Hasta').props.value).toBe('05/03/2027');
      expect(pieza().getByRole('tab', { name: 'Día', selected: true })).toBeOnTheScreen();

      await fireEvent.press(pieza().getByRole('button', { name: 'Cancelar' }));
      expect(screen.queryByTestId('pedir-descarga')).not.toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([]);

      await fireEvent.press(screen.getByRole('button', { name: 'Descargar' }));
      await pedirLaDescarga();

      expect(await pieza().findByText('El archivo se está generando.')).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([{ reporte: 'ingresos-por-periodo', ...DE_LOS_INGRESOS }]);
      expect(pieza().getByText('Del 01/03/2027 al 05/03/2027, por día')).toBeOnTheScreen();
      // Las cifras siguen en la pantalla, sin pedirlas otra vez.
      expect(screen.getByTestId('tabla-de-los-periodos')).toBeOnTheScreen();
      expect(consultasDeLosIngresos(servidor())).toEqual([{}]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E56 — (%s) en Ingresos, con un rango elegido, la pieza ofrece el que usó el servidor y su granularidad',
    async (plataforma) => {
      await abrirLosIngresos(plataforma);
      await fireEvent.press(screen.getByRole('link', { name: 'Cambiar' }));
      const hoja = within(await screen.findByTestId('hoja-del-rango'));
      await fireEvent.changeText(hoja.getByLabelText('Hasta'), '31032027');
      await fireEvent.press(hoja.getByRole('tab', { name: 'Semana' }));
      await fireEvent.press(hoja.getByRole('button', { name: 'Aplicar' }));
      await screen.findByText(INGRESOS_DE_MARZO_POR_SEMANA.rango.descripcion);

      await fireEvent.press(screen.getByRole('button', { name: 'Descargar' }));

      expect(pieza().getByLabelText('Desde').props.value).toBe('01/03/2027');
      expect(pieza().getByLabelText('Hasta').props.value).toBe('04/04/2027');
      expect(pieza().getByRole('tab', { name: 'Semana', selected: true })).toBeOnTheScreen();
      await pedirLaDescarga();
      expect(await pieza().findByText('Del 01/03/2027 al 04/04/2027, por semana')).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([
        { reporte: 'ingresos-por-periodo', desde: '2027-03-01', hasta: '2027-04-04', granularidad: 'semana' },
      ]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E56 — (%s) en Conciliación, "Descargar" llega después de conciliar, junto a "Otro periodo", y pide el reporte de la conciliación con el periodo conciliado, sin granularidad y sin volver a conciliar',
    async (plataforma) => {
      await abrirLaConciliacion(plataforma);
      // Antes de conciliar no hay reporte que descargar.
      expect(screen.queryByRole('button', { name: 'Descargar' })).not.toBeOnTheScreen();

      await conciliar('01/03/2027', '31/03/2027');
      await fireEvent.press(screen.getByRole('button', { name: 'Descargar' }));

      expect(within(screen.getByTestId('descarga-del-reporte')).getByTestId('pedir-descarga')).toBeOnTheScreen();
      expect(pieza().getByLabelText('Desde').props.value).toBe('01/03/2027');
      expect(pieza().getByLabelText('Hasta').props.value).toBe('31/03/2027');
      expect(pieza().queryByText('Ver por')).not.toBeOnTheScreen();
      // "Otro periodo" se queda, debajo de la pieza.
      expect(screen.getByRole('button', { name: 'Otro periodo' })).toBeOnTheScreen();

      await fireEvent.press(pieza().getByRole('button', { name: 'Cancelar' }));
      expect(screen.queryByTestId('pedir-descarga')).not.toBeOnTheScreen();
      await fireEvent.press(screen.getByRole('button', { name: 'Descargar' }));
      await pedirLaDescarga();

      expect(await pieza().findByText('El archivo se está generando.')).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([
        { reporte: 'conciliacion-con-la-pasarela', desde: '2027-03-01', hasta: '2027-03-31' },
      ]);
      expect(pieza().getByText('Del 01/03/2027 al 31/03/2027')).toBeOnTheScreen();
      // La pasarela se leyó una vez, al conciliar: la descarga usa los movimientos ya guardados.
      expect(conciliacionesPedidas(servidor())).toHaveLength(1);
      expect(screen.getByTestId('resumen-de-la-conciliacion')).toBeOnTheScreen();
    },
  );
});

describe('administracion-app-E57: la espera y el archivo listo en Indicadores, Ingresos y Conciliación', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E57 — (%s) en Indicadores, la tarjeta dice que el archivo se está generando, consulta su estado cada 5 s y, lista, ofrece "Guardar .csv" y "Guardar .xlsx" y deja de consultar',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);
      const tarjeta = within(screen.getByTestId('indicador-pedidos-totales'));
      await tocarDescargar(tarjeta, 'Pedidos totales');
      jest.useFakeTimers();
      await pedirLaDescarga();
      expect(await pieza().findByText('El archivo se está generando.')).toBeOnTheScreen();
      const creada = laCreada();
      await waitFor(() => expect(descargas.consultasDe(creada.id_publico)).toBe(1));

      expect(pieza().getByText('Se revisa cada 5 s mientras esta pantalla está abierta.')).toBeOnTheScreen();
      await pasan(CINCO_SEGUNDOS);
      expect(descargas.consultasDe(creada.id_publico)).toBe(2);

      descargas.de(creada.id_publico).estado = 'lista';
      await pasan(CINCO_SEGUNDOS);

      expect(await tarjeta.findByRole('button', { name: 'Guardar .csv' })).toBeOnTheScreen();
      expect(tarjeta.getByRole('button', { name: 'Guardar .xlsx' })).toBeOnTheScreen();
      expect(tarjeta.queryByText('El archivo se está generando.')).not.toBeOnTheScreen();
      const consultas = descargas.consultasDe(creada.id_publico);
      await pasan(CINCO_SEGUNDOS * 3);
      expect(descargas.consultasDe(creada.id_publico)).toBe(consultas);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E56 y administracion-app-E57 — (%s) en Indicadores, cuando las cifras se ponen al día solas, la pieza sigue abierta con lo que se escribió y, ya pedida, sigue esperando su archivo',
    async (plataforma) => {
      jest.useFakeTimers();
      await abrirLosIndicadores(plataforma);
      const tarjeta = within(screen.getByTestId('indicador-pedidos-totales'));
      await tocarDescargar(tarjeta, 'Pedidos totales');
      await escribir('Desde', '02112026');

      await pasan(TREINTA_SEGUNDOS);

      expect(consultasDeLosIndicadores(servidor())).toHaveLength(2);
      expect(pieza().getByLabelText('Desde').props.value).toBe('02/11/2026');
      await pedirLaDescarga();
      expect(await tarjeta.findByText('El archivo se está generando.')).toBeOnTheScreen();
      const creada = laCreada();

      await pasan(TREINTA_SEGUNDOS);

      expect(consultasDeLosIndicadores(servidor())).toHaveLength(3);
      expect(tarjeta.getByText('El archivo se está generando.')).toBeOnTheScreen();
      expect(tarjeta.queryByRole('link', { name: 'Descargar Pedidos totales' })).not.toBeOnTheScreen();
      descargas.de(creada.id_publico).estado = 'lista';
      await pasan(CINCO_SEGUNDOS);
      expect(await tarjeta.findByRole('button', { name: 'Guardar .csv' })).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([
        { reporte: 'pedidos-totales', ...DEL_MES_EN_CURSO, desde: '2026-11-02' },
      ]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E57 — (%s) en Indicadores, con la app al fondo no consulta la descarga que se genera; al frente, sí',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);
      await tocarDescargar(within(screen.getByTestId('indicador-pedidos-totales')), 'Pedidos totales');
      jest.useFakeTimers();
      await pedirLaDescarga();
      const creada = laCreada();
      await waitFor(() => expect(descargas.consultasDe(creada.id_publico)).toBe(1));

      await mandarLaAppA('background');
      await pasan(CINCO_SEGUNDOS * 3);
      expect(descargas.consultasDe(creada.id_publico)).toBe(1);
      await mandarLaAppA('active');
      expect(descargas.consultasDe(creada.id_publico)).toBe(2);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E57 — (%s) en Ingresos, si no se pudo generar lo dice y "Pedirla otra vez" la pide con el mismo rango',
    async (plataforma) => {
      await abrirLosIngresos(plataforma);
      await fireEvent.press(screen.getByRole('button', { name: 'Descargar' }));
      jest.useFakeTimers();
      await pedirLaDescarga();
      expect(await pieza().findByText('El archivo se está generando.')).toBeOnTheScreen();
      const creada = laCreada();
      await waitFor(() => expect(descargas.consultasDe(creada.id_publico)).toBe(1));

      // La cola la dio por terminada sin su archivo: el trabajo que lo escribe falló.
      descargas.de(creada.id_publico).estado = 'no_se_pudo';
      await pasan(CINCO_SEGUNDOS);

      expect(await pieza().findByText('No se pudo generar el archivo.')).toBeOnTheScreen();
      expect(pieza().queryByRole('button', { name: /^Guardar/ })).not.toBeOnTheScreen();
      await fireEvent.press(pieza().getByRole('button', { name: 'Pedirla otra vez' }));

      expect(await pieza().findByText('El archivo se está generando.')).toBeOnTheScreen();
      const pedida = { reporte: 'ingresos-por-periodo', ...DE_LOS_INGRESOS };
      expect(descargas.pedidas()).toEqual([pedida, pedida]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E57 y administracion-app-E58 — (%s) en Conciliación, la que llega lista ofrece "Guardar .csv" y "Guardar .xlsx" sin consultar su estado, y guarda el archivo',
    async (plataforma) => {
      descargas.estadoAlNacer = 'lista';
      await abrirLaConciliacion(plataforma);
      await conciliar('01/03/2027', '31/03/2027');
      await fireEvent.press(screen.getByRole('button', { name: 'Descargar' }));
      await pedirLaDescarga();

      expect(await pieza().findByRole('button', { name: 'Guardar .csv' })).toBeOnTheScreen();
      expect(pieza().getByRole('button', { name: 'Guardar .xlsx' })).toBeOnTheScreen();
      expect(descargas.consultasDe(laCreada().id_publico)).toBe(0);

      bajarConElServidor(servidor());
      jest.spyOn(Directory, 'pickDirectoryAsync').mockResolvedValue(carpetaElegida());
      await fireEvent.press(pieza().getByRole('button', { name: 'Guardar .csv' }));

      expect(await pieza().findByText('Se guardó el archivo .csv en la carpeta Descargas.')).toBeOnTheScreen();
    },
  );
});

describe('administracion-app-E60: el rango de la descarga en Indicadores, Ingresos y Conciliación', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E60 — (%s) en Indicadores, el rango al revés recibe el 422 del servidor junto a "Hasta" y no se crea ninguna descarga',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);
      await tocarDescargar(within(screen.getByTestId('indicador-pedidos-por-estado')), 'Pedidos por estado');

      await escribir('Desde', '04112026');
      await escribir('Hasta', '01112026');
      await pedirLaDescarga();

      expect(
        await within(pieza().getByTestId('campo-hasta')).findByText(MENSAJE_DEL_RANGO_AL_REVES),
      ).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([
        { reporte: 'pedidos-por-estado', desde: '2026-11-04', hasta: '2026-11-01', granularidad: 'dia' },
      ]);
      expect(descargas.descargas).toEqual([]);
      expect(pieza().queryByText('El archivo se está generando.')).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E60 — (%s) en Ingresos, del 10 al 20 de marzo manda los dos días, incluidos',
    async (plataforma) => {
      await abrirLosIngresos(plataforma);
      await fireEvent.press(screen.getByRole('button', { name: 'Descargar' }));

      await escribir('Desde', '10032027');
      await escribir('Hasta', '20032027');
      await pedirLaDescarga();

      expect(await pieza().findByText('Del 10/03/2027 al 20/03/2027, por día')).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([
        { reporte: 'ingresos-por-periodo', desde: '2027-03-10', hasta: '2027-03-20', granularidad: 'dia' },
      ]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E60 — (%s) en Conciliación, el rango al revés recibe el 422 junto a "Hasta" sin crear nada, y uno de dos años se acepta',
    async (plataforma) => {
      await abrirLaConciliacion(plataforma);
      await conciliar('01/03/2027', '31/03/2027');
      await fireEvent.press(screen.getByRole('button', { name: 'Descargar' }));

      await escribir('Desde', '31032027');
      await escribir('Hasta', '01032027');
      await pedirLaDescarga();

      expect(
        await within(pieza().getByTestId('campo-hasta')).findByText(MENSAJE_DEL_RANGO_AL_REVES),
      ).toBeOnTheScreen();
      expect(descargas.descargas).toEqual([]);

      await escribir('Desde', '01032025');
      await escribir('Hasta', '31032027');
      await pedirLaDescarga();

      expect(await pieza().findByText('El archivo se está generando.')).toBeOnTheScreen();
      expect(descargas.pedidas()).toEqual([
        { reporte: 'conciliacion-con-la-pasarela', desde: '2027-03-31', hasta: '2027-03-01' },
        { reporte: 'conciliacion-con-la-pasarela', desde: '2025-03-01', hasta: '2027-03-31' },
      ]);
      expect(laCreada()).toMatchObject({ desde: '2025-03-01', hasta: '2027-03-31' });
    },
  );
});

describe('administracion-app-E53 y administracion-app-E63: lo que espera una respuesta no ofrece "Descargar"', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E53 y administracion-app-E63 — (%s) en Indicadores, las lavanderías activas frente a registradas (F-I06) y las comisiones (F-I10) no ofrecen "Descargar"; los indicadores con cifra de su pestaña, sí',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);

      const activas = await elegirLaCategoria('Socios', 'indicador-activas-frente-a-registradas');
      expect(activas.queryByText('Descargar')).not.toBeOnTheScreen();
      expect(activas.queryByRole('link')).not.toBeOnTheScreen();
      expect(descargasDeLosIndicadores()).toEqual([
        'Descargar Calificación por lavandería',
        'Descargar Entregas por repartidor',
      ]);

      const comisiones = await elegirLaCategoria('Financiero', 'indicador-comisiones');
      expect(comisiones.queryByText('Descargar')).not.toBeOnTheScreen();
      expect(comisiones.queryByRole('link')).not.toBeOnTheScreen();
      expect(descargasDeLosIndicadores()).toEqual(['Descargar Ingresos brutos']);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E63 — (%s) en Ingresos, las comisiones del periodo no ofrecen "Descargar": el único de la pantalla es el de los ingresos',
    async (plataforma) => {
      await abrirLosIngresos(plataforma);

      const comisiones = within(screen.getByTestId('renglon-comisiones-del-periodo'));
      expect(comisiones.getByText('Por confirmar · DD-020')).toBeOnTheScreen();
      expect(comisiones.queryByText('Descargar')).not.toBeOnTheScreen();
      expect(screen.getAllByText('Descargar')).toHaveLength(1);
      expect(within(screen.getByTestId('cifras-del-periodo')).queryByText('Descargar')).not.toBeOnTheScreen();
    },
  );
});
