// Spec: openspec/changes/perfil-de-lavanderia/specs/perfil-lavanderia/spec.md, perfil-lavanderia-E11, E12 y E13
// (tarea 4.1). Desde el perfil, "Pedir aquí" abre la selección de servicio: solo se eligen los tipos que ofrece esa
// lavandería; la lavandería y el tipo quedan en el pedido en curso, en el teléfono, y la app pasa al paso de fecha y
// hora; sin tipo no se continúa. Un solo tipo por pedido mientras DD-098 siga abierta (design.md, Open Questions). Se
// abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado.
//
// El paso de fecha y hora es de agenda-de-recoleccion y se prueba en __tests__/agenda/selector.test.tsx (agenda-E04 y
// E08). Aquí solo se comprueba que la app llega a él con el pedido en curso guardado; el servidor falso le contesta un
// día sin ventanas.
import path from 'node:path';

import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { leerPedidoEnCurso, vaciarPedidoEnCurso } from '../../src/pedido/pedidoEnCurso';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/perfil-servicio';
const TOKEN = 'token-perfil-servicio';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };
const ID = '01jd00000000000000000000s1';
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

// Una lavandería de prueba que ofrece lavado estándar y lavado con planchado, y no lavado en seco.
const ESTANDAR = { codigo: 'estandar', nombre: 'Lavado estándar', precio: '45.00', unidad: { codigo: 'kg', nombre: 'kilo' } };
const PLANCHADO = {
  codigo: 'planchado',
  nombre: 'Lavado con planchado',
  precio: '60.00',
  unidad: { codigo: 'kg', nombre: 'kilo' },
};
const PERFIL = {
  id_publico: ID,
  nombre: 'Lavandería de prueba servicio',
  descripcion: null,
  fotos: [],
  servicios: [ESTANDAR, PLANCHADO],
  calificacion_promedio: null,
  numero_de_calificaciones: 0,
  resenas: [],
};

// La simulación de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

function servidor() {
  fetchFalso.mockImplementation(async (url: string) => {
    const ruta = url.slice(BASE.length);
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === `/lavanderias/${ID}`) {
      return respuesta(200, { lavanderia: PERFIL });
    }
    // Las ventanas que pide el paso de fecha y hora (agenda-de-recoleccion): el día pedido, sin ventanas.
    if (ruta.startsWith(`/lavanderias/${ID}/ventanas?fecha=`)) {
      const fecha = ruta.split('fecha=')[1];
      return respuesta(200, {
        fecha,
        zona_horaria: 'America/Mexico_City',
        primer_dia: fecha,
        ultimo_dia: fecha,
        dia_sin_servicio: false,
        ventanas: [],
      });
    }
    throw new Error(`El servidor falso no esperaba ${ruta}`);
  });
}

async function conSesion(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
}

// La persona con sesión abre el perfil de la lavandería de prueba y toca el acceso para pedir.
async function abrirLaSeleccion(plataforma: Plataforma) {
  await conSesion(plataforma);
  await renderRouter(APP, { initialUrl: `/lavanderias/${ID}` });
  await fireEvent.press(await screen.findByRole('button', { name: 'Pedir aquí' }));
  await waitFor(() => expect(screen).toHavePathname('/pedido/servicio'));
  await screen.findByRole('header', { name: '¿Qué servicio necesitas?' });
}

beforeEach(() => {
  almacen.__vaciar();
  vaciarPedidoEnCurso();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  // El inicio de sesión lleva el nombre de la app en su logotipo.
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba perfil-servicio';
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

describe('perfil-lavanderia-E11: solo se eligen los tipos que ofrece', () => {
  test.each(PLATAFORMAS)(
    'perfil-lavanderia-E11 — (%s) al tocar el acceso para pedir se puede elegir lavado estándar o lavado con planchado, y no lavado en seco',
    async (plataforma) => {
      await abrirLaSeleccion(plataforma);

      expect(screen.getByText('Pedido nuevo · 1 de 4')).toBeOnTheScreen();
      expect(screen.getByText(PERFIL.nombre)).toBeOnTheScreen();
      const opciones = screen.getByTestId('opciones-de-servicio');
      const estandar = within(opciones).getByRole('radio', { name: 'Lavado estándar, $45.00 por kilo' });
      const planchado = within(opciones).getByRole('radio', { name: 'Lavado con planchado, $60.00 por kilo' });
      expect(estandar).not.toBeChecked();
      expect(planchado).not.toBeChecked();
      expect(within(opciones).getAllByRole('radio')).toHaveLength(2);
      expect(screen.queryByText(/Lavado en seco/)).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)('perfil-lavanderia-E11 — (%s) se elige un solo tipo a la vez (DD-098)', async (plataforma) => {
    await abrirLaSeleccion(plataforma);

    await fireEvent.press(screen.getByRole('radio', { name: /^Lavado estándar/ }));
    expect(screen.getByRole('radio', { name: /^Lavado estándar/ })).toBeChecked();

    await fireEvent.press(screen.getByRole('radio', { name: /^Lavado con planchado/ }));

    expect(screen.getByRole('radio', { name: /^Lavado con planchado/ })).toBeChecked();
    expect(screen.getByRole('radio', { name: /^Lavado estándar/ })).not.toBeChecked();
  });
});

describe('perfil-lavanderia-E12: la elección queda en el pedido en curso', () => {
  test.each(PLATAFORMAS)(
    'perfil-lavanderia-E12 — (%s) al elegir lavado estándar y continuar, el pedido en curso guarda esa lavandería y ese tipo, y la app pasa a fecha y hora',
    async (plataforma) => {
      await abrirLaSeleccion(plataforma);

      await fireEvent.press(screen.getByRole('radio', { name: /^Lavado estándar/ }));
      await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Continuar' }));

      await waitFor(() => expect(screen).toHavePathname('/pedido/fecha-y-hora'));
      expect(leerPedidoEnCurso()).toEqual({
        lavanderia: { id_publico: ID, nombre: PERFIL.nombre, servicios: PERFIL.servicios },
        servicio: 'estandar',
        ventana: null,
      });
      expect(await screen.findByText('Pedido nuevo · 2 de 4')).toBeOnTheScreen();
    },
  );

  test('perfil-lavanderia-E12 — el pedido en curso no viaja al servidor: al continuar solo se piden las ventanas de la lavandería, sin el pedido', async () => {
    await abrirLaSeleccion('ios');
    const antes = fetchFalso.mock.calls.length;

    await fireEvent.press(screen.getByRole('radio', { name: /^Lavado con planchado/ }));
    await fireEvent.press(screen.getByRole('button', { name: 'Continuar' }));

    await waitFor(() => expect(screen).toHavePathname('/pedido/fecha-y-hora'));
    expect(leerPedidoEnCurso()?.servicio).toBe('planchado');
    // Lo único que sale es la consulta de las ventanas del paso de fecha y hora (agenda-de-recoleccion): un GET sin cuerpo.
    const despues = fetchFalso.mock.calls.slice(antes) as [string, RequestInit][];
    for (const [url, opciones] of despues) {
      expect(url.startsWith(`${BASE}/lavanderias/${ID}/ventanas?fecha=`)).toBe(true);
      expect(opciones.method).toBe('GET');
      expect(opciones.body).toBeUndefined();
    }
  });

  test('perfil-lavanderia-E12 — al regresar de fecha y hora, el tipo elegido sigue marcado', async () => {
    await abrirLaSeleccion('ios');
    await fireEvent.press(screen.getByRole('radio', { name: /^Lavado con planchado/ }));
    await fireEvent.press(screen.getByRole('button', { name: 'Continuar' }));
    await waitFor(() => expect(screen).toHavePathname('/pedido/fecha-y-hora'));

    await fireEvent.press(await screen.findByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname('/pedido/servicio'));
    expect(await screen.findByRole('radio', { name: /^Lavado con planchado/ })).toBeChecked();
  });
});

describe('perfil-lavanderia-E13: sin tipo elegido no se continúa', () => {
  test.each(PLATAFORMAS)(
    'perfil-lavanderia-E13 — (%s) sin ningún tipo elegido, "Continuar" no avanza y dice que falta elegir un tipo de servicio',
    async (plataforma) => {
      await abrirLaSeleccion(plataforma);
      expect(screen.queryByRole('alert')).not.toBeOnTheScreen();

      await fireEvent.press(screen.getByRole('button', { name: 'Continuar' }));

      expect(await screen.findByRole('alert')).toHaveTextContent('Elige un tipo de servicio para continuar.');
      expect(screen).toHavePathname('/pedido/servicio');
      expect(leerPedidoEnCurso()?.servicio).toBeNull();
    },
  );

  test('perfil-lavanderia-E13 — al elegir un tipo, el aviso se va y ya se continúa', async () => {
    await abrirLaSeleccion('ios');
    await fireEvent.press(screen.getByRole('button', { name: 'Continuar' }));
    await screen.findByRole('alert');

    await fireEvent.press(screen.getByRole('radio', { name: /^Lavado estándar/ }));

    expect(screen.queryByRole('alert')).not.toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Continuar' }));
    await waitFor(() => expect(screen).toHavePathname('/pedido/fecha-y-hora'));
  });
});

describe('perfil-lavanderia-E11 a E13: la selección sin una lavandería elegida', () => {
  test('sin lavandería en el pedido en curso, la selección lleva a la pantalla principal para elegir una', async () => {
    await conSesion('ios');

    await renderRouter(APP, { initialUrl: '/pedido/servicio' });

    await waitFor(() => expect(screen).toHavePathname('/inicio'));
  });

  test.each(['/pedido/servicio', '/pedido/fecha-y-hora'])(
    'registro-E11 — sin sesión, abrir %s lleva al inicio de sesión',
    async (ruta) => {
      await comprobarInstalacion();
      await marcarBienvenidaVista();

      await renderRouter(APP, { initialUrl: ruta });

      await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    },
  );
});
