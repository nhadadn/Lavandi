// Spec: openspec/changes/agenda-de-recoleccion/specs/agenda/spec.md, agenda-E04 y agenda-E08 (tarea 2.1). El segundo
// paso del pedido nuevo es el selector de fecha y hora, componente 2 del design system
// (vault/design-system/componentes.md): los días que se pueden elegir, de primer_dia a ultimo_dia, como botones; las
// ventanas de una hora del día elegido, en dos columnas; la elegida, con una palomita además del color; y el día sin
// servicio, con un mensaje en lugar de la rejilla que deja pasar al día siguiente. "Continuar" guarda la fecha y la
// ventana en el pedido en curso, sin cambiarlas, y pasa a la dirección (3 de 4), que todavía es provisional
// (direcciones, tarea 2.1). Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el
// servidor simulado con el contrato de GET /api/lavanderias/{id_publico}/ventanas?fecha=AAAA-MM-DD
// (servidor/app/Http/Controllers/Api/VentanasDeLavanderiaController.php).
//
// No entran el cupo por ventana (agenda-E05, DD-099), la primera ventana propuesta (agenda-E10, DD-078) ni la ventana
// de entrega (no-objetivo del cambio; DD-099).
import path from 'node:path';

import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { MENSAJES } from '../../src/acceso/mensajes';
import * as fechas from '../../src/agenda/fechas';
import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import {
  elegirServicio,
  elegirVentana,
  empezarPedido,
  leerPedidoEnCurso,
  vaciarPedidoEnCurso,
  type LavanderiaDelPedido,
} from '../../src/pedido/pedidoEnCurso';
import type { ServicioDeLavanderia } from '../../src/perfil/api';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/agenda-selector';
const TOKEN = 'token-agenda-selector';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };
const ID = '01jd00000000000000000000a1';
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

// La lavandería del pedido en curso, que dejó la selección de servicio (perfil-lavanderia-E12).
const ESTANDAR: ServicioDeLavanderia = {
  codigo: 'estandar',
  nombre: 'Lavado estándar',
  precio: '45.00',
  unidad: { codigo: 'kg', nombre: 'kilo' },
};
const LAVANDERIA: LavanderiaDelPedido = {
  id_publico: ID,
  nombre: 'Lavandería de prueba agenda',
  servicios: [ESTANDAR],
};

// La agenda de prueba: hoy en la plaza es el jueves 3 de diciembre de 2026 y se ve hasta el lunes 7. Una zona de prueba
// con un desfase distinto del de México: la app muestra la hora de la plaza que da el servidor y no la convierte.
const ZONA = 'Asia/Tokyo';
const DESFASE = '+09:00';
const PRIMER_DIA = '2026-12-03';
const ULTIMO_DIA = '2026-12-07';

function dos(numero: number): string {
  return String(numero).padStart(2, '0');
}

function ventana(fecha: string, hora: number) {
  return {
    inicio: `${fecha}T${dos(hora)}:00:00${DESFASE}`,
    fin: `${fecha}T${dos(hora + 1)}:00:00${DESFASE}`,
    fecha,
    hora_inicio: `${dos(hora)}:00`,
    hora_fin: `${dos(hora + 1)}:00`,
  };
}

type Dia = { dia_sin_servicio: boolean; ventanas: ReturnType<typeof ventana>[] };

// El jueves, de 9:00 a 13:00; el viernes, de 9:00 a 11:00; el sábado tiene horario pero ya no le quedan ventanas; el
// domingo la lavandería no tiene horario (agenda-E04); el lunes, de 9:00 a 12:00.
const AGENDA: Record<string, Dia> = {
  '2026-12-03': { dia_sin_servicio: false, ventanas: [9, 10, 11, 12].map((hora) => ventana('2026-12-03', hora)) },
  '2026-12-04': { dia_sin_servicio: false, ventanas: [9, 10].map((hora) => ventana('2026-12-04', hora)) },
  '2026-12-05': { dia_sin_servicio: false, ventanas: [] },
  '2026-12-06': { dia_sin_servicio: true, ventanas: [] },
  '2026-12-07': { dia_sin_servicio: false, ventanas: [9, 10, 11].map((hora) => ventana('2026-12-07', hora)) },
};

// La respuesta del servidor para una fecha; fuera de primer_dia y ultimo_dia, 200 sin ventanas.
function diaDeLaAgenda(fecha: string, ultimoDia = ULTIMO_DIA) {
  const dia = AGENDA[fecha] && fecha <= ultimoDia ? AGENDA[fecha] : { dia_sin_servicio: false, ventanas: [] };
  return { fecha, zona_horaria: ZONA, primer_dia: PRIMER_DIA, ultimo_dia: ultimoDia, ...dia };
}

// La simulación de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

const RUTA_DE_LAS_VENTANAS = `/lavanderias/${ID}/ventanas`;

// El servidor falso: reconoce el token y contesta las ventanas de la lavandería de prueba, solo con la sesión.
function servidor({
  ultimoDia = ULTIMO_DIA,
  ventanas,
}: { ultimoDia?: string; ventanas?: (fecha: string) => Promise<unknown> } = {}) {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const [ruta, consulta = ''] = url.slice(BASE.length).split('?');
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === RUTA_DE_LAS_VENTANAS) {
      const cabeceras = (opciones.headers ?? {}) as Record<string, string>;
      if (cabeceras.Authorization !== `Bearer ${TOKEN}`) {
        return respuesta(401, { message: 'Unauthenticated.' });
      }
      const fecha = decodeURIComponent(/(?:^|&)fecha=([^&]*)/.exec(consulta)?.[1] ?? '');
      return ventanas ? ventanas(fecha) : respuesta(200, diaDeLaAgenda(fecha, ultimoDia));
    }
    throw new Error(`El servidor falso no esperaba ${ruta}`);
  });
}

/** Las fechas que la app pidió al servidor, en orden. */
function fechasPedidas(): string[] {
  return fetchFalso.mock.calls
    .map(([url]: [string]) => url.slice(BASE.length))
    .filter((ruta: string) => ruta.startsWith(`${RUTA_DE_LAS_VENTANAS}?`))
    .map((ruta: string) => decodeURIComponent(ruta.split('fecha=')[1] ?? ''));
}

async function conSesion(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
}

// La persona con sesión llega a fecha y hora con la lavandería y el tipo de servicio en el pedido en curso, como la
// deja la selección de servicio (perfil-lavanderia-E12). El teléfono dice que hoy es el primer día de la agenda de la
// plaza.
async function abrirLaAgenda(plataforma: Plataforma, { hoyEnElTelefono = PRIMER_DIA } = {}) {
  jest.spyOn(fechas, 'fechaDelTelefono').mockReturnValue(hoyEnElTelefono);
  await conSesion(plataforma);
  empezarPedido(LAVANDERIA);
  elegirServicio('estandar');
  await renderRouter(APP, { initialUrl: '/pedido/fecha-y-hora' });
  await screen.findByRole('header', { name: '¿Cuándo pasamos por tu ropa?' });
}

// Espera a que el día elegido muestre sus ventanas.
async function ventanasALaVista() {
  return within(await screen.findByTestId('ventanas')).getAllByRole('radio');
}

beforeEach(() => {
  almacen.__vaciar();
  vaciarPedidoEnCurso();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  // El inicio de sesión lleva el nombre de la app en su logotipo.
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba agenda-selector';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  servidor();
});

afterEach(() => {
  for (const [variable, original] of [
    ['EXPO_PUBLIC_API_URL', direccionOriginal],
    ['EXPO_PUBLIC_NOMBRE_APP', nombreOriginal],
  ] as const) {
    if (original === undefined) {
      delete process.env[variable];
    } else {
      process.env[variable] = original;
    }
  }
  global.fetch = fetchOriginal;
  jest.restoreAllMocks();
});

describe('agenda-E04: día sin servicio', () => {
  test.each(PLATAFORMAS)(
    'agenda-E04 — (%s) al elegir el domingo, sin horario, el servidor no da ventanas y la app dice que ese día la lavandería no da servicio, sin rejilla, y deja pasar al lunes',
    async (plataforma) => {
      await abrirLaAgenda(plataforma);
      await ventanasALaVista();

      await fireEvent.press(screen.getByRole('button', { name: 'domingo 6 de diciembre' }));

      expect(await screen.findByText('Este día la lavandería no da servicio.')).toBeOnTheScreen();
      expect(fechasPedidas()).toContain('2026-12-06');
      expect(screen.getByRole('button', { name: 'domingo 6 de diciembre' })).toBeSelected();
      expect(screen.getByText('Domingo 6 de diciembre')).toBeOnTheScreen();
      expect(screen.queryByTestId('ventanas')).not.toBeOnTheScreen();
      expect(screen.queryAllByRole('radio')).toHaveLength(0);

      await fireEvent.press(screen.getByRole('button', { name: 'Ver el siguiente día' }));

      expect(await ventanasALaVista()).toHaveLength(3);
      expect(fechasPedidas().at(-1)).toBe('2026-12-07');
      expect(screen.getByRole('button', { name: 'lunes 7 de diciembre' })).toBeSelected();
      expect(screen.getByText('Lunes 7 de diciembre')).toBeOnTheScreen();
      expect(screen.queryByText('Este día la lavandería no da servicio.')).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'agenda-E04 — (%s) los días que se pueden elegir van de primer_dia a ultimo_dia, y la agenda abre en el primero, hoy en la plaza',
    async (plataforma) => {
      await abrirLaAgenda(plataforma);
      await ventanasALaVista();

      const dias = within(screen.getByTestId('dias')).getAllByRole('button');
      expect(dias).toHaveLength(5);
      for (const nombre of [
        'jueves 3 de diciembre',
        'viernes 4 de diciembre',
        'sábado 5 de diciembre',
        'domingo 6 de diciembre',
        'lunes 7 de diciembre',
      ]) {
        expect(within(screen.getByTestId('dias')).getByRole('button', { name: nombre })).toBeOnTheScreen();
      }
      expect(screen.getByRole('button', { name: 'jueves 3 de diciembre' })).toBeSelected();
      expect(screen.getByRole('button', { name: 'viernes 4 de diciembre' })).not.toBeSelected();
      expect(screen.getByText('Hoy, jueves 3 de diciembre')).toBeOnTheScreen();
      expect(fechasPedidas()).toEqual([PRIMER_DIA]);
    },
  );

  test('agenda-E04 — la consulta lleva la sesión y la fecha del día elegido', async () => {
    await abrirLaAgenda('ios');
    await ventanasALaVista();

    expect(fetchFalso).toHaveBeenCalledWith(
      `${BASE}${RUTA_DE_LAS_VENTANAS}?fecha=${PRIMER_DIA}`,
      expect.objectContaining({
        method: 'GET',
        headers: expect.objectContaining({ Authorization: `Bearer ${TOKEN}` }),
      }),
    );
  });

  test('agenda-E04 — si la fecha del teléfono no es un día de la agenda de la plaza, la agenda abre en primer_dia', async () => {
    await abrirLaAgenda('android', { hoyEnElTelefono: '2026-12-02' });

    expect(await ventanasALaVista()).toHaveLength(4);
    expect(fechasPedidas()).toEqual(['2026-12-02', PRIMER_DIA]);
    expect(screen.getByRole('button', { name: 'jueves 3 de diciembre' })).toBeSelected();
    expect(within(screen.getByTestId('dias')).getAllByRole('button')).toHaveLength(5);
  });

  test('agenda-E04 — un día con horario que ya no tiene ventanas lo dice con un mensaje, no con una rejilla vacía', async () => {
    await abrirLaAgenda('ios');
    await ventanasALaVista();

    await fireEvent.press(screen.getByRole('button', { name: 'sábado 5 de diciembre' }));

    expect(await screen.findByText('Este día ya no quedan ventanas.')).toBeOnTheScreen();
    expect(screen.queryByText('Este día la lavandería no da servicio.')).not.toBeOnTheScreen();
    expect(screen.queryByTestId('ventanas')).not.toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('button', { name: 'Ver el siguiente día' }));

    expect(await screen.findByText('Este día la lavandería no da servicio.')).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'domingo 6 de diciembre' })).toBeSelected();
  });

  test('agenda-E04 — si el día sin servicio es el último de la agenda, no ofrece un día siguiente que no se puede elegir', async () => {
    servidor({ ultimoDia: '2026-12-06' });
    await abrirLaAgenda('ios');
    await ventanasALaVista();

    await fireEvent.press(screen.getByRole('button', { name: 'domingo 6 de diciembre' }));

    expect(await screen.findByText('Este día la lavandería no da servicio.')).toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'Ver el siguiente día' })).not.toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'lunes 7 de diciembre' })).not.toBeOnTheScreen();
  });
});

describe('agenda-E08: el usuario elige una ventana', () => {
  test.each(PLATAFORMAS)(
    'agenda-E08 — (%s) al tocar 10:00–11:00 queda elegida con una palomita además del color, y al continuar la fecha y la ventana quedan en el pedido en curso y la app pasa a la dirección',
    async (plataforma) => {
      await abrirLaAgenda(plataforma);
      const ventanas = await ventanasALaVista();
      expect(ventanas).toHaveLength(4);
      for (const disponible of ventanas) {
        expect(disponible).not.toBeChecked();
      }

      await fireEvent.press(screen.getByRole('radio', { name: '10:00 – 11:00' }));

      const elegida = screen.getByRole('radio', { name: '10:00 – 11:00' });
      expect(elegida).toBeChecked();
      expect(within(elegida).getByTestId('palomita', { includeHiddenElements: true })).toHaveTextContent('✓');
      const otra = screen.getByRole('radio', { name: '09:00 – 10:00' });
      expect(otra).not.toBeChecked();
      expect(within(otra).queryByTestId('palomita', { includeHiddenElements: true })).not.toBeOnTheScreen();
      expect(screen.getByText('Hoy, jueves 3 de diciembre · 10:00 – 11:00')).toBeOnTheScreen();

      await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Continuar' }));

      await waitFor(() => expect(screen).toHavePathname('/pedido/direccion'));
      expect(await screen.findByText('Pedido nuevo · 3 de 4')).toBeOnTheScreen();
      expect(leerPedidoEnCurso()).toEqual({
        lavanderia: LAVANDERIA,
        servicio: 'estandar',
        ventana: {
          inicio: `2026-12-03T10:00:00${DESFASE}`,
          fin: `2026-12-03T11:00:00${DESFASE}`,
          fecha: '2026-12-03',
          hora_inicio: '10:00',
          hora_fin: '11:00',
        },
      });
    },
  );

  test.each(PLATAFORMAS)(
    'agenda-E08 — (%s) mientras no hay ventana elegida, el botón dice "Elige una ventana" y no deja continuar',
    async (plataforma) => {
      await abrirLaAgenda(plataforma);
      await ventanasALaVista();

      const boton = within(screen.getByTestId('acciones')).getByRole('button', { name: 'Elige una ventana' });
      expect(boton).toBeDisabled();
      expect(screen.queryByRole('button', { name: 'Continuar' })).not.toBeOnTheScreen();

      await fireEvent.press(boton);

      expect(screen).toHavePathname('/pedido/fecha-y-hora');
      expect(leerPedidoEnCurso()?.ventana).toBeNull();
    },
  );

  test('agenda-E08 — se elige una sola ventana a la vez', async () => {
    await abrirLaAgenda('ios');
    await ventanasALaVista();

    await fireEvent.press(screen.getByRole('radio', { name: '10:00 – 11:00' }));
    await fireEvent.press(screen.getByRole('radio', { name: '12:00 – 13:00' }));

    expect(screen.getByRole('radio', { name: '12:00 – 13:00' })).toBeChecked();
    expect(screen.getByRole('radio', { name: '10:00 – 11:00' })).not.toBeChecked();
    const palomitas = within(screen.getByTestId('ventanas')).getAllByTestId('palomita', { includeHiddenElements: true });
    expect(palomitas).toHaveLength(1);
  });

  test('agenda-E08 — la hora es la de la plaza: la app muestra hora_inicio y hora_fin tal cual, en dos columnas, y guarda inicio y fin sin cambiarlos', async () => {
    await abrirLaAgenda('android');
    await ventanasALaVista();

    // Dos ventanas por fila.
    const filas = within(screen.getByTestId('ventanas')).getAllByTestId('fila-de-ventanas');
    expect(filas).toHaveLength(2);
    expect(within(filas[0]).getAllByRole('radio')).toHaveLength(2);
    expect(within(filas[1]).getAllByRole('radio')).toHaveLength(2);
    // La hora de la plaza, con su cero, aunque el teléfono esté en otra zona horaria.
    expect(screen.getByText('09:00 – 10:00')).toBeOnTheScreen();
    expect(screen.getByText('12:00 – 13:00')).toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('radio', { name: '09:00 – 10:00' }));
    await fireEvent.press(screen.getByRole('button', { name: 'Continuar' }));

    await waitFor(() => expect(screen).toHavePathname('/pedido/direccion'));
    expect(leerPedidoEnCurso()?.ventana).toEqual(AGENDA[PRIMER_DIA].ventanas[0]);
  });

  test('agenda-E08 — la ventana elegida se conserva al ver otro día, y la elección dice su propia fecha', async () => {
    await abrirLaAgenda('ios');
    await ventanasALaVista();
    await fireEvent.press(screen.getByRole('radio', { name: '11:00 – 12:00' }));

    await fireEvent.press(screen.getByRole('button', { name: 'viernes 4 de diciembre' }));
    expect(await ventanasALaVista()).toHaveLength(2);

    // El viernes también tiene 09:00 – 10:00 y 10:00 – 11:00, pero ninguna es la elegida.
    for (const ventanaDelViernes of within(screen.getByTestId('ventanas')).getAllByRole('radio')) {
      expect(ventanaDelViernes).not.toBeChecked();
    }
    expect(screen.getByText('Hoy, jueves 3 de diciembre · 11:00 – 12:00')).toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('button', { name: 'jueves 3 de diciembre' }));
    await ventanasALaVista();
    expect(screen.getByRole('radio', { name: '11:00 – 12:00' })).toBeChecked();
  });

  test('agenda-E08 — al regresar de la dirección, la ventana elegida sigue marcada', async () => {
    await abrirLaAgenda('ios');
    await ventanasALaVista();
    await fireEvent.press(screen.getByRole('radio', { name: '10:00 – 11:00' }));
    await fireEvent.press(screen.getByRole('button', { name: 'Continuar' }));
    await waitFor(() => expect(screen).toHavePathname('/pedido/direccion'));

    await fireEvent.press(await screen.findByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname('/pedido/fecha-y-hora'));
    expect(await screen.findByRole('radio', { name: '10:00 – 11:00' })).toBeChecked();
    expect(screen.getByRole('button', { name: 'Continuar' })).toBeEnabled();
  });

  test('agenda-E08 — al volver a abrir fecha y hora, la ventana que ya estaba en el pedido en curso aparece elegida en su día', async () => {
    jest.spyOn(fechas, 'fechaDelTelefono').mockReturnValue(PRIMER_DIA);
    await conSesion('ios');
    empezarPedido(LAVANDERIA);
    elegirServicio('estandar');
    elegirVentana(AGENDA['2026-12-04'].ventanas[1]);

    await renderRouter(APP, { initialUrl: '/pedido/fecha-y-hora' });

    expect(await screen.findByRole('radio', { name: '10:00 – 11:00' })).toBeChecked();
    expect(screen.getByRole('button', { name: 'viernes 4 de diciembre' })).toBeSelected();
    expect(fechasPedidas()).toEqual(['2026-12-04']);
    expect(screen.getByRole('button', { name: 'Continuar' })).toBeEnabled();
  });
});

describe('agenda-E04: los días del selector se cuentan en el calendario de la plaza', () => {
  // Los días llegan como AAAA-MM-DD y se cuentan sin la zona horaria del teléfono (src/agenda/fechas.ts).
  test('agenda-E04 — de primer_dia a ultimo_dia, cruzando el fin de mes y el de año', () => {
    expect(fechas.diasEntre('2026-11-29', '2026-12-02')).toEqual([
      '2026-11-29',
      '2026-11-30',
      '2026-12-01',
      '2026-12-02',
    ]);
    expect(fechas.diasEntre('2026-12-30', '2027-01-02')).toEqual([
      '2026-12-30',
      '2026-12-31',
      '2027-01-01',
      '2027-01-02',
    ]);
    expect(fechas.diasEntre(PRIMER_DIA, PRIMER_DIA)).toEqual([PRIMER_DIA]);
  });

  test('agenda-E04 — el día de la semana sale de la fecha, no de la hora del teléfono', () => {
    expect(fechas.diaDeLaSemana('2026-12-06')).toBe(0);
    expect(fechas.diaDeLaSemana(PRIMER_DIA)).toBe(4);
    expect(fechas.diaDeLaSemana('2027-01-01')).toBe(5);
  });

  test('agenda-E04 — hoy en el teléfono va como AAAA-MM-DD, con sus ceros', () => {
    expect(fechas.fechaDelTelefono(new Date(2026, 0, 5, 23, 30))).toBe('2026-01-05');
  });
});

describe('agenda-E04 y E08: la pantalla sin lo que necesita', () => {
  // Sin escenario propio: si las ventanas no llegan, la pantalla usa los mensajes generales de la app, como la búsqueda
  // de cercanas y el perfil (src/cercanas/textos.ts), y no deja continuar.
  test.each([
    ['sin conexión', MENSAJES.sinConexion, () => Promise.reject(new TypeError('Network request failed'))],
    [
      'la agenda de la plaza no está configurada (503)',
      MENSAJES.servidor,
      async () => respuesta(503, { message: 'La agenda de la plaza no está configurada.' }),
    ],
    [
      'la lavandería ya no está activa (404)',
      MENSAJES.servidor,
      async () => respuesta(404, { message: 'No existe la lavandería.' }),
    ],
    [
      'una respuesta fuera del contrato',
      MENSAJES.servidor,
      async () => respuesta(200, { fecha: PRIMER_DIA, ventanas: [{ hora_inicio: '09:00' }] }),
    ],
  ])('si las ventanas no llegan (%s), lo dice y no deja continuar', async (_caso, mensaje, contestar) => {
    servidor({ ventanas: contestar });

    await abrirLaAgenda('ios');

    expect(await screen.findByText(mensaje)).toBeOnTheScreen();
    expect(screen.queryAllByRole('radio')).toHaveLength(0);
    expect(screen.getByRole('button', { name: 'Elige una ventana' })).toBeDisabled();
  });

  test('si un día no llega, se puede volver a tocar para pedirlo otra vez', async () => {
    let fallar = false;
    servidor({
      ventanas: async (fecha) =>
        fallar ? Promise.reject(new TypeError('Network request failed')) : respuesta(200, diaDeLaAgenda(fecha)),
    });
    await abrirLaAgenda('ios');
    await ventanasALaVista();

    fallar = true;
    await fireEvent.press(screen.getByRole('button', { name: 'viernes 4 de diciembre' }));
    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();

    fallar = false;
    await fireEvent.press(screen.getByRole('button', { name: 'viernes 4 de diciembre' }));

    expect(await ventanasALaVista()).toHaveLength(2);
    expect(screen.queryByText(MENSAJES.sinConexion)).not.toBeOnTheScreen();
  });

  test('sin pedido en curso, fecha y hora lleva a la pantalla principal, donde se elige la lavandería', async () => {
    await conSesion('ios');

    await renderRouter(APP, { initialUrl: '/pedido/fecha-y-hora' });

    await waitFor(() => expect(screen).toHavePathname('/inicio'));
  });

  test('con la lavandería y sin el tipo de servicio, fecha y hora lleva a la selección de servicio', async () => {
    await conSesion('ios');
    empezarPedido(LAVANDERIA);

    await renderRouter(APP, { initialUrl: '/pedido/fecha-y-hora' });

    await waitFor(() => expect(screen).toHavePathname('/pedido/servicio'));
  });

  test('registro-E11 — sin sesión, abrir /pedido/direccion lleva al inicio de sesión', async () => {
    await comprobarInstalacion();
    await marcarBienvenidaVista();

    await renderRouter(APP, { initialUrl: '/pedido/direccion' });

    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
  });
});
