// Spec: openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md, requisito "Cada rol de la app consulta su
// cuenta y cierra su sesión" (registro-E33): "Con el rol la app sabe a qué módulo entra la cuenta. La administración
// entra por el panel". Una sola app con los módulos del usuario y de la lavandería (NSV-2026-001-v8.pdf:16:ANEXO A,
// Sección A.3), con las pantallas separadas por tipo de usuario (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Medidas
// de seguridad).
//
// Al entrar, la cuenta de usuario llega al inicio del Módulo A y la de socio lavandería a sus pedidos del Módulo B
// (pedidos-lavanderia-E03); ninguna abre las pantallas de la otra, como ya no se abren sin sesión (ADR-0018). La cuenta
// de administración no usa la app: si llegara a entrar, la app cierra su sesión y lo dice. La lavandería cierra su
// sesión como el usuario (registro-E13), con la confirmación de la app.
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado
// (__tests__/apoyo/lavanderia.ts). Cómo se registra un socio y llega a su cuenta es DD-053: aquí la cuenta ya existe.
import { userEvent } from '@testing-library/react-native';
import { act, fireEvent, screen, waitFor } from 'expo-router/testing-library';
import { Alert, type AlertButton } from 'react-native';

import { MENSAJES } from '../../src/acceso/mensajes';
import { guardarRol, leerRol, leerToken } from '../../src/sesion/almacen';
import {
  abrirLaAppConSesion,
  abrirLaAppSinSesion,
  CUENTAS,
  PLATAFORMAS,
  RUTA_DE_LOS_PEDIDOS,
  TOKEN,
  usarElServidorDeLaLavanderia,
} from '../apoyo/lavanderia';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaLavanderia('modulo-por-rol');

/** La pantalla de pedidos de la lavandería, con su título. */
async function esperarLosPedidosDeLaLavanderia() {
  await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
  expect(await screen.findByRole('header', { name: 'Pedidos activos' })).toBeOnTheScreen();
}

async function iniciarSesion() {
  const persona = userEvent.setup();
  await persona.type(await screen.findByLabelText('Correo electrónico'), 'cuenta@ejemplo.com');
  await persona.type(screen.getByLabelText('Contraseña'), 'secreta123');
  await persona.press(screen.getByRole('button', { name: 'Iniciar sesión' }));
}

/** Las peticiones para revocar un token: "DELETE /sesion Bearer <token>". */
function revocaciones(): string[] {
  return servidor()
    .peticiones()
    .filter(({ metodo, ruta }) => metodo === 'DELETE' && ruta === '/sesion')
    .map(({ autorizacion }) => `DELETE /sesion ${autorizacion}`);
}

describe('registro-E33: con el rol, cada cuenta entra a su módulo', () => {
  test.each(PLATAFORMAS)(
    'registro-E33 — (%s) al iniciar sesión, la cuenta de lavandería entra a sus pedidos y no al inicio del usuario',
    async (plataforma) => {
      await abrirLaAppSinSesion(plataforma);

      await iniciarSesion();

      await esperarLosPedidosDeLaLavanderia();
      await expect(leerToken()).resolves.toBe(TOKEN);
      await expect(leerRol()).resolves.toBe('lavanderia');
    },
  );

  test.each(PLATAFORMAS)(
    'registro-E33 — (%s) al volver a abrir la app con la sesión de una lavandería, entra a sus pedidos',
    async (plataforma) => {
      await abrirLaAppConSesion(plataforma);

      await esperarLosPedidosDeLaLavanderia();
      expect(servidor().peticiones()[0]).toMatchObject({ metodo: 'GET', ruta: '/cuenta' });
    },
  );

  test('registro-E11 y registro-E33 — sin conexión al abrir, entra al módulo del rol que guardó con la sesión', async () => {
    await guardarRol('lavanderia');
    servidor().interceptar = ({ ruta }) => {
      if (ruta === '/cuenta') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await abrirLaAppConSesion('android');

    await esperarLosPedidosDeLaLavanderia();
    await expect(leerToken()).resolves.toBe(TOKEN);
  });

  test('registro-E11 — sin conexión, una sesión guardada antes de que la app guardara el rol sigue entrando al inicio del usuario', async () => {
    servidor().interceptar = () => {
      throw new TypeError('Network request failed');
    };

    await abrirLaAppConSesion('ios');

    await waitFor(() => expect(screen).toHavePathname('/inicio'));
  });

  test.each(PLATAFORMAS)(
    'registro-E33 — (%s) la cuenta de usuario sigue entrando al inicio y no abre las pantallas de la lavandería',
    async (plataforma) => {
      servidor().cuenta = CUENTAS.usuario;

      await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);

      await waitFor(() => expect(screen).toHavePathname('/inicio'));
      expect(screen.queryByRole('header', { name: 'Pedidos activos' })).not.toBeOnTheScreen();
      expect(servidor().consultasDeLaLista()).toBe(0);
    },
  );

  test.each(PLATAFORMAS)(
    'registro-E33 — (%s) la cuenta de lavandería no abre las pantallas del usuario',
    async (plataforma) => {
      await abrirLaAppConSesion(plataforma, '/pedidos');

      await esperarLosPedidosDeLaLavanderia();
      expect(screen.queryByRole('header', { name: 'Mis pedidos' })).not.toBeOnTheScreen();
    },
  );

  test('registro-E33 — la cuenta de lavandería tampoco abre el inicio del usuario', async () => {
    await abrirLaAppConSesion('ios', '/inicio');

    await esperarLosPedidosDeLaLavanderia();
    expect(servidor().peticiones().some(({ ruta }) => ruta.startsWith('/lavanderias/cercanas'))).toBe(false);
  });
});

describe('registro-E33: la cuenta de administración no usa la app', () => {
  test.each(PLATAFORMAS)(
    'registro-E33 — (%s) si inicia sesión, la app no la abre, pide revocar su token y lo dice',
    async (plataforma) => {
      servidor().cuenta = CUENTAS.administracion;
      await abrirLaAppSinSesion(plataforma);

      await iniciarSesion();

      expect(await screen.findByText(MENSAJES.sinAccesoALaApp)).toBeOnTheScreen();
      expect(screen).toHavePathname('/inicio-de-sesion');
      await expect(leerToken()).resolves.toBeNull();
      expect(revocaciones()).toEqual([`DELETE /sesion Bearer ${TOKEN}`]);
    },
  );

  test('registro-E33 — si después entra con una cuenta que sí usa la app, el mensaje se va', async () => {
    servidor().cuenta = CUENTAS.administracion;
    await abrirLaAppSinSesion('android');
    await iniciarSesion();
    expect(await screen.findByText(MENSAJES.sinAccesoALaApp)).toBeOnTheScreen();

    // Ahora entra con la cuenta de la lavandería.
    servidor().cuenta = CUENTAS.lavanderia;
    await fireEvent.press(screen.getByRole('button', { name: 'Iniciar sesión' }));

    await esperarLosPedidosDeLaLavanderia();
    expect(screen.queryByText(MENSAJES.sinAccesoALaApp)).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'registro-E33 — (%s) al abrir, si el servidor dice que la cuenta guardada no tiene acceso a la app (403), la cierra y lo dice',
    async (plataforma) => {
      servidor().cuenta = CUENTAS.administracion;

      await abrirLaAppConSesion(plataforma);

      await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
      expect(await screen.findByText(MENSAJES.sinAccesoALaApp)).toBeOnTheScreen();
      await expect(leerToken()).resolves.toBeNull();
    },
  );
});

describe('registro-E13 y registro-E33: la lavandería cierra su sesión, con la confirmación de la app', () => {
  let dialogo: jest.SpyInstance;

  beforeEach(() => {
    // Alert.alert puede ser ya una simulación de react-native que guarda las llamadas de todo el archivo.
    dialogo = jest.spyOn(Alert, 'alert').mockImplementation(() => {});
    dialogo.mockClear();
  });

  /** Toca "Cerrar sesión" y devuelve los botones del diálogo de confirmación que abrió. */
  async function pedirCerrarLaSesion(): Promise<AlertButton[]> {
    await fireEvent.press(await screen.findByRole('button', { name: 'Cerrar sesión' }));
    expect(dialogo).toHaveBeenCalledTimes(1);
    expect(dialogo.mock.calls[0][0]).toBe('¿Cerrar sesión?');
    return dialogo.mock.calls[0][2] as AlertButton[];
  }

  async function tocar(botones: AlertButton[], texto: string) {
    const boton = botones.find((opcion) => opcion.text === texto);
    expect(boton).toBeDefined();
    await act(async () => {
      boton?.onPress?.();
    });
  }

  test.each(PLATAFORMAS)(
    'registro-E13 — (%s) al confirmar, pide al servidor revocar el token, lo borra del teléfono y lleva al inicio de sesión',
    async (plataforma) => {
      await abrirLaAppConSesion(plataforma);
      await esperarLosPedidosDeLaLavanderia();

      const botones = await pedirCerrarLaSesion();
      await tocar(botones, 'Cerrar sesión');

      await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
      expect(revocaciones()).toEqual([`DELETE /sesion Bearer ${TOKEN}`]);
      await expect(leerToken()).resolves.toBeNull();
      await expect(leerRol()).resolves.toBeNull();
    },
  );

  test('registro-E13 — con "Cancelar" no cierra nada', async () => {
    await abrirLaAppConSesion('ios');
    await esperarLosPedidosDeLaLavanderia();

    const botones = await pedirCerrarLaSesion();
    await tocar(botones, 'Cancelar');

    expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS);
    expect(revocaciones()).toEqual([]);
    await expect(leerToken()).resolves.toBe(TOKEN);
  });
});
