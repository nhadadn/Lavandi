// Spec: openspec/changes/administracion/specs/administracion/spec.md, requisito "La administración en la app",
// administracion-E25 (tarea 6.1), y openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md, registro-E33.
// La Cláusula I pone el panel de administración en la "Aplicación única para iPhone y Android"
// (NSV-2026-001-v8.pdf:3:Cláusula I) y el A.4.3 pide los cuatro módulos accesibles (NSV-2026-001-v8.pdf:21:ANEXO A,
// Sección A.4.3).
//
// Decisión 56 de acta-2026-11-20-modelo-y-pasarela.md (concilio del 2026-11-24, por delegación de Arthur, C-245), que
// complementa la 31 (C-140): una cuenta de administración que inicia sesión en la app no recibe token, el 403 de
// POST /api/sesion (servidor/app/Http/Controllers/Api/SesionController.php), y la app no guarda nada. Le muestra el aviso
// con "Abrir el panel", que abre la dirección del panel de la configuración (EXPO_PUBLIC_PANEL_URL) en la vista de
// navegador del sistema dentro de la app, con openBrowserAsync de expo-web-browser, como el checkout del pago (adenda del
// 2026-11-22 de ADR-0010, decisión 32). Sin la dirección, el aviso sale sin el botón; sin el aviso, tampoco hay botón.
//
// Con Google, el servidor todavía le da token a la cuenta de administración: POST /api/sesion/google no le responde
// 403 hasta la tarea 6.2 (openspec/changes/administracion/tasks.md). Las pruebas de administracion-E25 con Google
// simulan el 403 sin token que pide esa tarea, en el inicio de sesión y en el registro. La que simula el token que hoy
// da el servidor es de registro-E33: la app no lo guarda y pide revocarlo, y el aviso trae el botón igual. Esa revocación
// recibe 403, como en el servidor real (servidor/routes/api.php:58-60), y el token queda vivo allá hasta la 6.2.
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado
// (__tests__/apoyo/lavanderia.ts) y la simulación de __mocks__/expo-web-browser.ts. La vista real se prueba en un
// teléfono.
import { userEvent } from '@testing-library/react-native';
import { fireEvent, screen } from 'expo-router/testing-library';
import * as WebBrowser from 'expo-web-browser';
import { NitroModules } from 'react-native-nitro-modules';

import { MENSAJES } from '../../src/acceso/mensajes';
import { leerRol, leerToken } from '../../src/sesion/almacen';
import {
  abrirLaAppSinSesion,
  CUENTAS,
  PLATAFORMAS,
  respuesta,
  TOKEN,
  usarElServidorDeLaLavanderia,
} from '../apoyo/lavanderia';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaLavanderia('abrir-el-panel');

// La simulación de __mocks__/expo-web-browser.ts, la misma instancia que importa la app.
const navegador = WebBrowser as unknown as typeof import('../../__mocks__/expo-web-browser');

// eslint-disable-next-line @typescript-eslint/no-require-imports -- la simulación trae su propia utilidad de limpieza
const { reiniciarObjetosHibridos } = require('react-native-nitro-modules') as {
  reiniciarObjetosHibridos: () => void;
};
const google = NitroModules.createHybridObject('NitroGoogleSignin') as unknown as Record<string, jest.Mock>;

// Valores de prueba que no pueden estar en el código: si llegan al navegador o a Google, salieron de la configuración.
const PANEL = 'https://panel.prueba.invalid/admin';
const CLIENTE_WEB = 'cliente-web-de-prueba.apps.googleusercontent.com';

// Lo que responde POST /api/sesion a una cuenta de administración con su contraseña correcta: 403, sin token
// (registro-E33). Es también lo que la tarea 6.2 pide a POST /api/sesion/google.
const SIN_TOKEN = respuesta(403, {
  message: 'Esta cuenta no tiene acceso a la app. Las cuentas de administración entran por el panel.',
});

// Lo que responde POST /api/sesion a una contraseña equivocada (registro-E08).
const CREDENCIALES_NO_VALIDAS = respuesta(422, {
  message: 'Las credenciales no son válidas.',
  errors: { correo: ['Las credenciales no son válidas.'] },
});

const VARIABLES = ['EXPO_PUBLIC_PANEL_URL', 'EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID'] as const;
const originales = Object.fromEntries(VARIABLES.map((nombre) => [nombre, process.env[nombre]]));

beforeEach(() => {
  navegador.__reiniciar();
  reiniciarObjetosHibridos();
  process.env.EXPO_PUBLIC_PANEL_URL = PANEL;
  delete process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID;
  servidor().cuenta = CUENTAS.administracion;
  servidor().interceptar = ({ metodo, ruta }) => (metodo === 'POST' && ruta === '/sesion' ? SIN_TOKEN : undefined);
});

afterEach(() => {
  for (const nombre of VARIABLES) {
    const original = originales[nombre];
    if (original === undefined) {
      delete process.env[nombre];
    } else {
      process.env[nombre] = original;
    }
  }
});

async function iniciarSesion() {
  const persona = userEvent.setup();
  await persona.type(await screen.findByLabelText('Correo electrónico'), 'panel@ejemplo.com');
  await persona.type(screen.getByLabelText('Contraseña'), 'secreta123');
  await persona.press(screen.getByRole('button', { name: 'Iniciar sesión' }));
}

/** Con el cliente de Google de prueba en la configuración, la persona elige su cuenta y Google da su idToken. */
function googleDaSuIdToken() {
  process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID = CLIENTE_WEB;
  google.checkPlayServices.mockResolvedValue(undefined);
  google.signIn.mockResolvedValue({
    type: 'success',
    data: {
      user: { id: '1', email: 'panel@ejemplo.com', name: null, givenName: null, familyName: null, photo: null },
      scopes: [],
      idToken: 'id-token-de-google',
      serverAuthCode: null,
    },
  });
}

/** Las peticiones para revocar un token: "DELETE /sesion Bearer <token>". */
function revocaciones(): string[] {
  return servidor()
    .peticiones()
    .filter(({ metodo, ruta }) => metodo === 'DELETE' && ruta === '/sesion')
    .map(({ autorizacion }) => `DELETE /sesion ${autorizacion}`);
}

describe('administracion-E25: una cuenta de administración en la app abre el panel', () => {
  test.each(PLATAFORMAS)(
    'administracion-E25 y registro-E33: (%s) una cuenta de administración que inicia sesión ve el aviso y el botón, y el botón abre en expo-web-browser la dirección del panel de la configuración',
    async (plataforma) => {
      await abrirLaAppSinSesion(plataforma);
      expect(await screen.findByLabelText('Correo electrónico')).toBeOnTheScreen();
      // Sin el aviso, no hay botón aunque la configuración traiga la dirección del panel.
      expect(screen.queryByRole('button', { name: 'Abrir el panel' })).not.toBeOnTheScreen();

      await iniciarSesion();

      expect(await screen.findByText(MENSAJES.sinAccesoALaApp)).toBeOnTheScreen();
      expect(screen).toHavePathname('/inicio-de-sesion');
      // El servidor no le dio token (registro-E33): la app no guarda nada ni tiene nada que revocar.
      await expect(leerToken()).resolves.toBeNull();
      await expect(leerRol()).resolves.toBeNull();
      expect(revocaciones()).toEqual([]);
      expect(navegador.openBrowserAsync).not.toHaveBeenCalled();

      await fireEvent.press(screen.getByRole('button', { name: 'Abrir el panel' }));

      expect(navegador.openBrowserAsync).toHaveBeenCalledTimes(1);
      expect(navegador.openBrowserAsync).toHaveBeenCalledWith(PANEL);
      expect(screen).toHavePathname('/inicio-de-sesion');
    },
  );

  test('administracion-E25: sin la dirección del panel, el aviso se muestra sin el botón', async () => {
    delete process.env.EXPO_PUBLIC_PANEL_URL;
    await abrirLaAppSinSesion('android');

    await iniciarSesion();

    expect(await screen.findByText(MENSAJES.sinAccesoALaApp)).toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'Abrir el panel' })).not.toBeOnTheScreen();
    await expect(leerToken()).resolves.toBeNull();
  });

  test('administracion-E25: el botón solo va con el aviso: con la contraseña equivocada (422) no aparece', async () => {
    servidor().interceptar = ({ metodo, ruta }) =>
      metodo === 'POST' && ruta === '/sesion' ? CREDENCIALES_NO_VALIDAS : undefined;
    await abrirLaAppSinSesion('ios');

    await iniciarSesion();

    expect(await screen.findByText('Las credenciales no son válidas.')).toBeOnTheScreen();
    expect(screen.queryByText(MENSAJES.sinAccesoALaApp)).not.toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'Abrir el panel' })).not.toBeOnTheScreen();
  });

  test.each([
    ['el inicio de sesión', '/inicio-de-sesion'],
    ['el registro', '/registro'],
  ])(
    'administracion-E25: con Google, desde %s, con el 403 sin token (tarea 6.2) la app no guarda nada y el aviso trae el botón',
    async (_pantalla, ruta) => {
      googleDaSuIdToken();
      servidor().interceptar = ({ metodo, ruta: pedida }) =>
        metodo === 'POST' && pedida === '/sesion/google' ? SIN_TOKEN : undefined;
      const persona = userEvent.setup();
      await abrirLaAppSinSesion('android', ruta);
      expect(screen.queryByRole('button', { name: 'Abrir el panel' })).not.toBeOnTheScreen();

      await persona.press(await screen.findByRole('button', { name: 'Continuar con Google' }));

      expect(await screen.findByText(MENSAJES.sinAccesoALaApp)).toBeOnTheScreen();
      expect(screen).toHavePathname(ruta);
      await expect(leerToken()).resolves.toBeNull();
      await expect(leerRol()).resolves.toBeNull();
      expect(revocaciones()).toEqual([]);

      await persona.press(screen.getByRole('button', { name: 'Abrir el panel' }));

      expect(navegador.openBrowserAsync).toHaveBeenCalledTimes(1);
      expect(navegador.openBrowserAsync).toHaveBeenCalledWith(PANEL);
    },
  );
});

// Hasta la tarea 6.2, POST /api/sesion/google sí le da token a la cuenta de administración (servidor/app/Identidad/
// EntradaConGoogle.php). La app no abre esa sesión (registro-E33, ProveedorDeSesion) y le ofrece el panel igual. La
// revocación que pide recibe 403 del servidor simulado, como del real: la app no depende de que salga bien.
describe('registro-E33: con Google, la cuenta de administración que recibe token no se queda con la sesión', () => {
  test('registro-E33: con Google, si el servidor le da token, la app no lo guarda, pide revocarlo (403, sin efecto hasta la 6.2) y el aviso trae el botón', async () => {
    googleDaSuIdToken();
    servidor().interceptar = ({ metodo, ruta }) =>
      metodo === 'POST' && ruta === '/sesion/google'
        ? respuesta(200, { token: TOKEN, usuario: CUENTAS.administracion })
        : undefined;
    const persona = userEvent.setup();
    await abrirLaAppSinSesion('ios');

    await persona.press(await screen.findByRole('button', { name: 'Continuar con Google' }));

    expect(await screen.findByText(MENSAJES.sinAccesoALaApp)).toBeOnTheScreen();
    await expect(leerToken()).resolves.toBeNull();
    expect(revocaciones()).toEqual([`DELETE /sesion Bearer ${TOKEN}`]);
    // El 403 de la revocación no cambia nada en la pantalla: sigue el aviso, sin "Algo salió mal".
    expect(screen.getByText(MENSAJES.sinAccesoALaApp)).toBeOnTheScreen();
    expect(screen.queryByText(MENSAJES.servidor)).not.toBeOnTheScreen();

    await persona.press(screen.getByRole('button', { name: 'Abrir el panel' }));

    expect(navegador.openBrowserAsync).toHaveBeenCalledWith(PANEL);
  });
});
