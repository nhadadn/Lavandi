// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "La administración
// inicia sesión en la app": administracion-app-E04 (cierra su sesión) y administracion-app-E05 (la sesión vence o se
// revoca), con registro-E13 de specs/registro/spec.md. Control de acceso: la sesión se guarda de forma segura y sin pedir
// la contraseña cada vez (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3), con medidas de seguridad razonables
// (NSV-2026-001-v8.pdf:10:Cláusula X). El token de la administración vence y se revoca (ADR-0022, decisiones 2 y 4); con
// un 401 la app borra la sesión y vuelve al inicio de sesión (ADR-0018, decisión 1).
//
// El inicio del módulo no consulta nada: el 401 de una ruta de /administracion se prueba con el cliente del módulo
// (src/administracion/api.ts) en una pantalla de prueba dentro del proveedor de sesión de la app, junto al inicio de
// sesión real. Cada pantalla de las funciones lo prueba además en la suya. Ahí mismo, lo demás que el cliente da a las
// pantallas (el 409, el 404 y el 422; design.md, sección 3) y la confirmación de sus acciones
// (src/administracion/confirmar.ts).
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado de la
// administración (__tests__/apoyo/administracion.ts).
import { act, fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';
import { useState } from 'react';
import { Alert, Pressable, Text, View, type AlertButton } from 'react-native';

import InicioDeSesion from '../../app/inicio-de-sesion';
import { useApiDeLaAdministracion, type ResultadoDeLaAdministracion } from '../../src/administracion/api';
import { confirmar } from '../../src/administracion/confirmar';
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

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaAdministracion('administracion-sesion');

let dialogo: jest.SpyInstance;

beforeEach(() => {
  // Alert.alert puede ser ya una simulación de react-native que guarda las llamadas de todo el archivo.
  dialogo = jest.spyOn(Alert, 'alert').mockImplementation(() => {});
  dialogo.mockClear();
});

/** Las peticiones para revocar un token: "DELETE /sesion Bearer <token>". */
function revocaciones(): string[] {
  return servidor()
    .peticiones()
    .filter(({ metodo, ruta }) => metodo === 'DELETE' && ruta === '/sesion')
    .map(({ autorizacion }) => `DELETE /sesion ${autorizacion}`);
}

async function esperarElInicioDelModulo() {
  await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_INICIO));
  expect(await screen.findByRole('header', { name: 'Administración' })).toBeOnTheScreen();
}

/** Los botones del diálogo de confirmación que se abrió, y su título y su texto. */
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

describe('administracion-app-E04: la administración cierra su sesión', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E04 y registro-E13 — (%s) al confirmar, la app pide DELETE /sesion con su token, borra la sesión del teléfono y abre la entrada',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma);
      await esperarElInicioDelModulo();

      await fireEvent.press(screen.getByRole('button', { name: 'Cerrar sesión' }));
      const { titulo, botones } = ultimoDialogo();
      expect(titulo).toBe('¿Cerrar sesión?');
      await tocar(botones, 'Cerrar sesión');

      await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
      expect(revocaciones()).toEqual([`DELETE /sesion Bearer ${TOKEN_DE_LA_ADMINISTRACION}`]);
      await expect(leerToken()).resolves.toBeNull();
      await expect(leerRol()).resolves.toBeNull();
    },
  );

  test.each(PLATAFORMAS)('administracion-app-E04 — (%s) si no lo confirma, sigue dentro', async (plataforma) => {
    await abrirLaAppConLaAdministracion(plataforma);
    await esperarElInicioDelModulo();

    await fireEvent.press(screen.getByRole('button', { name: 'Cerrar sesión' }));
    await tocar(ultimoDialogo().botones, 'Cancelar');

    expect(screen).toHavePathname(RUTA_DEL_INICIO);
    expect(screen.getByRole('header', { name: 'Administración' })).toBeOnTheScreen();
    expect(revocaciones()).toEqual([]);
    await expect(leerToken()).resolves.toBe(TOKEN_DE_LA_ADMINISTRACION);
  });
});

// Una pantalla de prueba del módulo, con el cliente de la administración: dice el estado y el token de la sesión;
// "Consultar" pide la ruta de prueba y muestra lo que entregó el cliente; "Consultar dos veces" pide dos rutas a la vez;
// "Consultar sin esperar" pide la ruta de prueba y deja la pantalla libre mientras llega la respuesta, que la prueba
// espera en consultaEnCurso; "Abrir otra sesión" abre la de otro token, como al volver a entrar; "Confirmar" pide
// confirmar una acción de prueba y, si se confirma, lo dice.
const RUTA_DE_PRUEBA = '/administracion/prueba-del-cliente';
const OTRO_TOKEN = 'token-de-otra-sesion-de-la-administracion';
let consultaEnCurso: Promise<void> = Promise.resolve();

function describir(resultado: ResultadoDeLaAdministracion<unknown>): string {
  return JSON.stringify(resultado);
}

function PruebaDelCliente() {
  const { estado, token, iniciar } = useSesion();
  const pedir = useApiDeLaAdministracion();
  const [resultado, setResultado] = useState('');
  const [confirmada, setConfirmada] = useState(false);
  return (
    <View>
      <Text testID="estado-de-la-sesion">{estado}</Text>
      <Text testID="token-de-la-sesion">{token}</Text>
      <Pressable role="button" onPress={async () => setResultado(describir(await pedir('prueba')))}>
        <Text>Consultar</Text>
      </Pressable>
      <Pressable
        role="button"
        onPress={async () =>
          setResultado(JSON.stringify(await Promise.all([pedir('prueba'), pedir('prueba/otra')])))
        }
      >
        <Text>Consultar dos veces</Text>
      </Pressable>
      <Pressable
        role="button"
        onPress={() => {
          consultaEnCurso = pedir('prueba').then((entregado) => setResultado(describir(entregado)));
        }}
      >
        <Text>Consultar sin esperar</Text>
      </Pressable>
      <Pressable role="button" onPress={() => iniciar(OTRO_TOKEN, 'administracion')}>
        <Text>Abrir otra sesión</Text>
      </Pressable>
      <Pressable
        role="button"
        onPress={async () =>
          setResultado(describir(await pedir('prueba/accion', { metodo: 'POST', cuerpo: { motivo: 'de prueba' } })))
        }
      >
        <Text>Mandar</Text>
      </Pressable>
      <Pressable
        role="button"
        onPress={() =>
          confirmar({
            titulo: '¿Hacer la acción de prueba?',
            texto: 'Lo que pasa al hacerla.',
            accion: 'Hacerla',
            alConfirmar: () => setConfirmada(true),
          })
        }
      >
        <Text>Confirmar</Text>
      </Pressable>
      <Text testID="resultado">{resultado}</Text>
      {confirmada ? <Text>Acción confirmada</Text> : null}
    </View>
  );
}

/** Abre la pantalla de prueba con la sesión de la administración ya leída, con el inicio de sesión real a un lado. */
async function abrirLaPantallaDePrueba(plataforma: Plataforma = 'android') {
  await prepararElTelefonoConLaAdministracion(plataforma);
  await renderRouter(
    { 'administracion/prueba-del-cliente': PruebaDelCliente, 'inicio-de-sesion': InicioDeSesion },
    { initialUrl: RUTA_DE_PRUEBA, wrapper: ProveedorDeSesion },
  );
  await waitFor(() => expect(screen.getByTestId('estado-de-la-sesion')).toHaveTextContent('con-sesion'));
  expect(screen).toHavePathname(RUTA_DE_PRUEBA);
  servidor().fetch.mockClear();
}

/** Lo que entregó el cliente a la pantalla de prueba. */
async function loQueEntrego(): Promise<unknown> {
  await waitFor(() => expect(screen.getByTestId('resultado').props.children).not.toBe(''));
  return JSON.parse(screen.getByTestId('resultado').props.children as string);
}

describe('administracion-app-E05: la sesión vence o se revoca', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) al abrir la app con el token vencido, GET /cuenta da 401 y se abre el inicio de sesión',
    async (plataforma) => {
      servidor().vencerLaSesion();

      await abrirLaAppConLaAdministracion(plataforma);

      await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
      expect(await screen.findByLabelText('Contraseña')).toBeOnTheScreen();
      expect(servidor().peticiones()[0]).toMatchObject({ metodo: 'GET', ruta: '/cuenta' });
      await expect(leerToken()).resolves.toBeNull();
      await expect(leerRol()).resolves.toBeNull();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el token que todavía no vence, la app entra sin pedir otra vez la contraseña',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma);

      await esperarElInicioDelModulo();
      // La cuenta, y los dos conteos del inicio (administracion-app-E03), con el mismo token: ningún 401.
      await waitFor(() =>
        expect(servidor().peticiones().map(({ metodo, ruta }) => `${metodo} ${ruta}`)).toEqual([
          'GET /cuenta',
          `GET ${RUTAS_DE_LOS_CONTEOS.repartidores}`,
          `GET ${RUTAS_DE_LOS_CONTEOS.monitoreo}`,
        ]),
      );
      expect(await screen.findByTestId('conteo-monitoreo')).toBeOnTheScreen();
      await expect(leerToken()).resolves.toBe(TOKEN_DE_LA_ADMINISTRACION);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) ante un 401 de una ruta de /administracion, el cliente del módulo borra la sesión del teléfono y abre el inicio de sesión',
    async (plataforma) => {
      await abrirLaPantallaDePrueba(plataforma);
      // El token vence con la pantalla abierta.
      servidor().vencerLaSesion();

      await fireEvent.press(screen.getByRole('button', { name: 'Consultar' }));

      await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
      expect(await screen.findByLabelText('Contraseña')).toBeOnTheScreen();
      expect(servidor().peticiones()).toContainEqual(
        expect.objectContaining({
          metodo: 'GET',
          ruta: '/administracion/prueba',
          autorizacion: `Bearer ${TOKEN_DE_LA_ADMINISTRACION}`,
        }),
      );
      await expect(leerToken()).resolves.toBeNull();
      await expect(leerRol()).resolves.toBeNull();
      // "Atrás" ya no vuelve a la pantalla del módulo.
      expect(screen.queryByRole('button', { name: 'Consultar' })).not.toBeOnTheScreen();
    },
  );

  test('administracion-app-E05 — dos consultas de la pantalla que reciben el 401 a la vez cierran la sesión una sola vez', async () => {
    await abrirLaPantallaDePrueba();
    servidor().vencerLaSesion();

    await fireEvent.press(screen.getByRole('button', { name: 'Consultar dos veces' }));

    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    expect(
      servidor()
        .peticiones()
        .filter(({ ruta }) => ruta.startsWith('/administracion'))
        .map(({ ruta }) => ruta),
    ).toEqual(['/administracion/prueba', '/administracion/prueba/otra']);
    expect(revocaciones()).toEqual([`DELETE /sesion Bearer ${TOKEN_DE_LA_ADMINISTRACION}`]);
    await expect(leerToken()).resolves.toBeNull();
  });

  test('administracion-app-E05 — un 401 que llega tarde, cuando ya se abrió otra sesión, no la cierra', async () => {
    await abrirLaPantallaDePrueba();
    // La consulta sale con el token de la sesión y su respuesta tarda.
    let contestarTarde: (contestada: unknown) => void = () => {};
    servidor().interceptar = ({ ruta }) =>
      ruta === '/administracion/prueba'
        ? new Promise((resolver) => {
            contestarTarde = resolver;
          })
        : undefined;
    await fireEvent.press(screen.getByRole('button', { name: 'Consultar sin esperar' }));
    await waitFor(() =>
      expect(servidor().peticiones()).toContainEqual(
        expect.objectContaining({ ruta: '/administracion/prueba', autorizacion: `Bearer ${TOKEN_DE_LA_ADMINISTRACION}` }),
      ),
    );

    // Mientras tanto, la persona abre otra sesión; después llega el 401 de la consulta vieja.
    await fireEvent.press(screen.getByRole('button', { name: 'Abrir otra sesión' }));
    await waitFor(() => expect(screen.getByTestId('token-de-la-sesion')).toHaveTextContent(OTRO_TOKEN));
    await act(async () => {
      contestarTarde(respuesta(401, { message: 'Unauthenticated.' }));
      await consultaEnCurso;
    });

    await expect(loQueEntrego()).resolves.toMatchObject({ ok: false, motivo: 'sin-sesion' });
    expect(screen).toHavePathname(RUTA_DE_PRUEBA);
    expect(screen.getByTestId('estado-de-la-sesion')).toHaveTextContent('con-sesion');
    await expect(leerToken()).resolves.toBe(OTRO_TOKEN);
    expect(revocaciones()).toEqual([]);
  });
});

describe('ADR-0022, decisiones 7 y 9: lo que el cliente del módulo da a las pantallas (design.md, sección 3); administracion-app-E12 lo prueba cada pantalla en la suya', () => {
  test('ADR-0022 — la petición va a /administracion/<ruta> con el token de la sesión, y la respuesta llega con sus datos', async () => {
    servidor().agregar(({ metodo, ruta }) =>
      metodo === 'GET' && ruta === '/administracion/prueba' ? respuesta(200, { prueba: 'lista' }) : undefined,
    );
    await abrirLaPantallaDePrueba();

    await fireEvent.press(screen.getByRole('button', { name: 'Consultar' }));

    await expect(loQueEntrego()).resolves.toEqual({ ok: true, datos: { prueba: 'lista' } });
    const [, opciones] = servidor().fetch.mock.calls.find(([url]) => String(url).endsWith('/administracion/prueba')) as [
      string,
      RequestInit,
    ];
    expect(opciones.headers).toMatchObject({
      Accept: 'application/json',
      Authorization: `Bearer ${TOKEN_DE_LA_ADMINISTRACION}`,
    });
  });

  test('ADR-0022 — con un cuerpo, lo manda en JSON con su método', async () => {
    servidor().agregar(({ metodo, ruta, cuerpo }) =>
      metodo === 'POST' && ruta === '/administracion/prueba/accion' ? respuesta(200, { recibido: cuerpo }) : undefined,
    );
    await abrirLaPantallaDePrueba();

    await fireEvent.press(screen.getByRole('button', { name: 'Mandar' }));

    await expect(loQueEntrego()).resolves.toEqual({ ok: true, datos: { recibido: { motivo: 'de prueba' } } });
  });

  test('ADR-0022 — un 409 sale como conflicto, con el mensaje del servidor, y la sesión sigue', async () => {
    servidor().agregar(() => respuesta(409, { message: 'Otra sesión ya hizo esta acción.' }));
    await abrirLaPantallaDePrueba();

    await fireEvent.press(screen.getByRole('button', { name: 'Consultar' }));

    await expect(loQueEntrego()).resolves.toEqual({
      ok: false,
      motivo: 'conflicto',
      mensaje: 'Otra sesión ya hizo esta acción.',
    });
    expect(screen).toHavePathname(RUTA_DE_PRUEBA);
    await expect(leerToken()).resolves.toBe(TOKEN_DE_LA_ADMINISTRACION);
  });

  test('ADR-0022 — un 404 sale como que no existe, con el mensaje del servidor', async () => {
    servidor().agregar(() => respuesta(404, { message: 'No existe.' }));
    await abrirLaPantallaDePrueba();

    await fireEvent.press(screen.getByRole('button', { name: 'Consultar' }));

    await expect(loQueEntrego()).resolves.toEqual({ ok: false, motivo: 'no-existe', mensaje: 'No existe.' });
  });

  test('ADR-0022 — un 422 sale con el error de cada campo', async () => {
    servidor().agregar(() =>
      respuesta(422, { message: 'Escribe el motivo.', errors: { motivo: ['Escribe el motivo.'] } }),
    );
    await abrirLaPantallaDePrueba();

    await fireEvent.press(screen.getByRole('button', { name: 'Mandar' }));

    await expect(loQueEntrego()).resolves.toEqual({
      ok: false,
      motivo: 'invalido',
      mensaje: 'Escribe el motivo.',
      errores: { motivo: ['Escribe el motivo.'] },
    });
  });

  test('ADR-0022 — sin conexión, la falla lo dice y la sesión sigue', async () => {
    await abrirLaPantallaDePrueba();
    servidor().interceptar = ({ ruta }) => {
      if (ruta === '/administracion/prueba') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await fireEvent.press(screen.getByRole('button', { name: 'Consultar' }));

    await expect(loQueEntrego()).resolves.toEqual({ ok: false, motivo: 'sin-conexion' });
    await expect(leerToken()).resolves.toBe(TOKEN_DE_LA_ADMINISTRACION);
  });

  test('ADR-0022 — una acción se confirma con el diálogo del sistema: el título, el texto, "Cancelar" y la acción; con "Cancelar" no pasa nada', async () => {
    await abrirLaPantallaDePrueba();

    await fireEvent.press(screen.getByRole('button', { name: 'Confirmar' }));
    const { titulo, texto, botones } = ultimoDialogo();
    expect(titulo).toBe('¿Hacer la acción de prueba?');
    expect(texto).toBe('Lo que pasa al hacerla.');
    expect(botones.map(({ text, style }) => [text, style])).toEqual([
      ['Cancelar', 'cancel'],
      ['Hacerla', undefined],
    ]);

    await tocar(botones, 'Cancelar');
    expect(screen.queryByText('Acción confirmada')).not.toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('button', { name: 'Confirmar' }));
    await tocar(ultimoDialogo().botones, 'Hacerla');
    expect(await screen.findByText('Acción confirmada')).toBeOnTheScreen();
    // Confirmar no pide nada al servidor: lo hace la acción.
    expect(servidor().peticiones().filter(({ ruta }) => ruta.startsWith('/administracion'))).toEqual([]);
  });
});
