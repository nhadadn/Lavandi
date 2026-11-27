// Spec: openspec/changes/repartidor/specs/repartidor/spec.md, repartidor-E01, repartidor-E02 y repartidor-E03 (tarea
// 2.1). El registro del repartidor en la app (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #1: "Registro del
// repartidor con nombre, teléfono, correo, foto de perfil y tipo de vehículo") y la espera de su alta, que "corresponde
// a EL CLIENTE": la persona llena sus datos y su contraseña (ADR-0005), toma o elige su foto, y envía; la app comprime la
// foto con los parámetros de configuración (ADR-0006) y manda el registro en multipart, sin sesión
// (POST /api/repartidor/registro). El servidor abre la sesión y deja el registro pendiente: la app muestra que espera la
// autorización. Un repartidor pendiente o suspendido ve esa espera y no sus pedidos; uno activo, su lista.
//
// Cómo llega la persona al registro desde la app es DD-053 (repartidor-E04): aquí la prueba abre su ruta directo, y
// comprueba que ni la bienvenida ni el inicio de sesión llevan a él. Se abre la app real en iPhone y en Android, con el
// servidor simulado con el "Contrato de la API" de openspec/changes/repartidor/design.md (__tests__/apoyo/repartidor.ts)
// y la cámara, la galería y la compresión simuladas (__mocks__/).
import { userEvent } from '@testing-library/react-native';
import * as ImageManipulator from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';
import { fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

import { MENSAJES } from '../../src/acceso/mensajes';
import { COMPRESION_DE_FOTOS } from '../../src/configuracion/fotos';
import { leerRol, leerToken } from '../../src/sesion/almacen';
import {
  abrirLaAppConSesion,
  abrirLaAppSinSesion,
  FALTA,
  MENSAJES_SIN_ACCESO,
  PLATAFORMAS,
  respuesta,
  RUTA_DE_LA_ESPERA,
  RUTA_DE_LOS_PEDIDOS,
  RUTA_DEL_REGISTRO,
  TOKEN,
  usarElServidorDelRepartidor,
  type Plataforma,
} from '../apoyo/repartidor';
import { contenidoDelArchivo } from '../apoyo/formulario';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDelRepartidor('modulo-c-registro');

// Las simulaciones de __mocks__/, la misma instancia que importa la app.
const camara = ImagePicker as unknown as typeof import('../../__mocks__/expo-image-picker');
const manipulador = ImageManipulator as unknown as typeof import('../../__mocks__/expo-image-manipulator');

/** Una foto de la cámara de un teléfono, horizontal, más grande que el lado máximo de la configuración. */
const FOTO_DE_LA_CAMARA = { uri: 'file:///camara/repartidor.jpg', width: 4032, height: 3024, mimeType: 'image/jpeg' };
/** Una foto vertical de la galería, en el formato de iPhone. */
const FOTO_DE_LA_GALERIA = { uri: 'file:///galeria/repartidor.heic', width: 3024, height: 4032, mimeType: 'image/heic' };

const DATOS = {
  nombre: 'Rubén Repartidor',
  telefono: '618 123 4567',
  correo: 'ruben@ejemplo.com',
  password: 'secreta123',
  tipo_vehiculo: 'Motocicleta',
};

async function abrirElRegistro(plataforma: Plataforma) {
  await abrirLaAppSinSesion(plataforma, RUTA_DEL_REGISTRO);
  await screen.findByRole('header', { name: 'Registro de repartidor' });
}

/** Escribe los datos de texto del registro. */
async function escribirLosDatos(datos: Partial<typeof DATOS> = DATOS) {
  const campos: [keyof typeof DATOS, string][] = [
    ['nombre', 'Nombre'],
    ['telefono', 'Teléfono'],
    ['correo', 'Correo electrónico'],
    ['password', 'Contraseña'],
    ['tipo_vehiculo', 'Tipo de vehículo'],
  ];
  for (const [campo, etiqueta] of campos) {
    if (datos[campo] !== undefined) {
      await fireEvent.changeText(screen.getByLabelText(etiqueta), datos[campo]);
    }
  }
}

async function tomarLaFoto() {
  camara.__fotoDeLaCamara(FOTO_DE_LA_CAMARA);
  await fireEvent.press(screen.getByRole('button', { name: 'Tomar foto' }));
  await screen.findByLabelText('Tu foto de perfil');
}

async function enviar() {
  await fireEvent.press(screen.getByRole('button', { name: 'Enviar registro' }));
}

/** El error de un campo, junto a él. */
function errorDe(campo: string) {
  return within(screen.getByTestId(`campo-${campo}`)).queryByRole('alert');
}

describe('repartidor-E01: el repartidor se registra y queda pendiente', () => {
  test.each(PLATAFORMAS)(
    'repartidor-E01 — (%s) con sus cinco datos, su contraseña y la foto de la cámara, manda el registro en multipart, sin sesión, y muestra que espera la autorización',
    async (plataforma) => {
      await abrirElRegistro(plataforma);
      await escribirLosDatos();
      await tomarLaFoto();

      await enviar();

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));
      expect(await screen.findByText(MENSAJES_SIN_ACCESO.pendiente)).toBeOnTheScreen();
      expect(servidor().registros()).toEqual([
        {
          multipart: true,
          autorizacion: null,
          cuerpo: {
            ...DATOS,
            foto: {
              nombre: 'foto.jpg',
              tipo: 'image/jpeg',
              contenido: contenidoDelArchivo(manipulador.__preparada(FOTO_DE_LA_CAMARA.uri)),
            },
          },
        },
      ]);
      // La sesión que abrió el registro queda en el teléfono, con el rol que dice su módulo (registro-E33).
      await expect(leerToken()).resolves.toBe(TOKEN);
      await expect(leerRol()).resolves.toBe('repartidor');
      // Pendiente, la app no pide sus pedidos (repartidor-E03).
      expect(servidor().consultasDeLaLista()).toBe(0);
    },
  );

  test('repartidor-E01 — la foto se comprime antes de subirla, con el lado máximo y la calidad de la configuración, en JPEG', async () => {
    await abrirElRegistro('ios');

    await tomarLaFoto();

    expect(manipulador.__preparaciones()).toEqual([
      {
        fuente: FOTO_DE_LA_CAMARA.uri,
        redimension: { width: COMPRESION_DE_FOTOS.ladoMaximoPx },
        guardado: { compress: COMPRESION_DE_FOTOS.calidad, format: 'jpeg' },
        resultado: manipulador.__preparada(FOTO_DE_LA_CAMARA.uri),
      },
    ]);
  });

  test.each(PLATAFORMAS)('repartidor-E01 — (%s) la foto también se puede elegir de la galería', async (plataforma) => {
    await abrirElRegistro(plataforma);
    await escribirLosDatos();
    camara.__fotoDeLaGaleria(FOTO_DE_LA_GALERIA);

    await fireEvent.press(screen.getByRole('button', { name: 'Elegir de la galería' }));
    await screen.findByLabelText('Tu foto de perfil');
    await enviar();

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));
    expect(servidor().registros()[0].cuerpo).toMatchObject({
      foto: {
        nombre: 'foto.jpg',
        tipo: 'image/jpeg',
        contenido: contenidoDelArchivo(manipulador.__preparada(FOTO_DE_LA_GALERIA.uri)),
      },
    });
  });

  // Sin escenario propio: sin permiso de la cámara, la galería sigue sirviendo, como en el registro de la lavandería.
  test('repartidor-E01 — sin permiso de la cámara, lo dice y la galería sigue a la mano', async () => {
    camara.__respuestaAlPedirLaCamara(camara.PermissionStatus.DENIED);
    await abrirElRegistro('android');

    await fireEvent.press(screen.getByRole('button', { name: 'Tomar foto' }));

    expect(await within(screen.getByTestId('campo-foto')).findByRole('alert')).toHaveTextContent(
      'Sin permiso de la cámara no podemos tomar la foto. Puedes elegirla de tu galería.',
    );
    expect(screen.getByRole('button', { name: 'Elegir de la galería' })).toBeEnabled();
  });

  test.each(PLATAFORMAS)(
    'repartidor-E01 — (%s) mientras manda, el botón se bloquea: un doble toque manda un solo registro',
    async (plataforma) => {
      await abrirElRegistro(plataforma);
      await escribirLosDatos();
      await tomarLaFoto();
      let soltar: () => void = () => {};
      servidor().interceptar = ({ metodo, ruta, cuerpo }) =>
        metodo === 'POST' && ruta === '/repartidor/registro'
          ? new Promise((resolver) => {
              soltar = () => resolver(servidor().registrar(cuerpo as Record<string, unknown>));
            })
          : undefined;

      await enviar();
      await enviar();

      expect(servidor().registros()).toHaveLength(1);
      expect(screen.getByRole('button', { name: 'Enviar registro' })).toBeDisabled();
      soltar();
      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));
      expect(servidor().registros()).toHaveLength(1);
    },
  );

  // Sin escenario propio: "Regresar" lleva al inicio de sesión, la entrada sin sesión de la app (bienvenida-E04).
  test('repartidor-E01 — "Regresar" lleva al inicio de sesión sin mandar nada', async () => {
    await abrirElRegistro('ios');

    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    expect(servidor().registros()).toEqual([]);
  });
});

describe('repartidor-E02: un registro incompleto se rechaza', () => {
  test.each(PLATAFORMAS)(
    'repartidor-E02 — (%s) sin ningún dato, cada error del servidor va junto a su campo y la app no abre ninguna sesión',
    async (plataforma) => {
      await abrirElRegistro(plataforma);

      await enviar();

      for (const campo of ['nombre', 'telefono', 'correo', 'password', 'foto', 'tipo_vehiculo']) {
        expect(await waitFor(() => errorDe(campo))).toHaveTextContent(FALTA[campo]);
      }
      expect(screen).toHavePathname(RUTA_DEL_REGISTRO);
      await expect(leerToken()).resolves.toBeNull();
    },
  );

  test('repartidor-E02 — sin la foto, el error va junto a la foto y lo escrito se queda', async () => {
    await abrirElRegistro('android');
    await escribirLosDatos();

    await enviar();

    expect(await waitFor(() => errorDe('foto'))).toHaveTextContent('Toma o elige tu foto de perfil.');
    expect(errorDe('nombre')).not.toBeOnTheScreen();
    expect(screen.getByLabelText('Nombre')).toHaveDisplayValue(DATOS.nombre);
    expect(screen.getByLabelText('Tipo de vehículo')).toHaveDisplayValue(DATOS.tipo_vehiculo);
    await expect(leerToken()).resolves.toBeNull();
  });

  test('repartidor-E02 — el error de un dato que no vale, como el correo con cuenta, va junto a su campo', async () => {
    await abrirElRegistro('ios');
    await escribirLosDatos();
    await tomarLaFoto();
    servidor().interceptar = ({ ruta }) =>
      ruta === '/repartidor/registro'
        ? respuesta(422, {
            message: 'Ese correo ya tiene una cuenta.',
            errors: { correo: ['Ese correo ya tiene una cuenta.'] },
          })
        : undefined;

    await enviar();

    expect(await waitFor(() => errorDe('correo'))).toHaveTextContent('Ese correo ya tiene una cuenta.');
    // Solo junto al campo: no se repite arriba del botón.
    expect(screen.getAllByText('Ese correo ya tiene una cuenta.')).toHaveLength(1);
    expect(screen).toHavePathname(RUTA_DEL_REGISTRO);
  });

  // Sin escenario propio: sin conexión, los mensajes generales de la app, y lo escrito se queda.
  test('repartidor-E02 — sin conexión, lo dice con el mensaje general y lo escrito se queda', async () => {
    await abrirElRegistro('android');
    await escribirLosDatos();
    await tomarLaFoto();
    servidor().interceptar = () => {
      throw new TypeError('Network request failed');
    };

    await enviar();

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(screen.getByLabelText('Correo electrónico')).toHaveDisplayValue(DATOS.correo);
    expect(screen.getByLabelText('Tu foto de perfil')).toBeOnTheScreen();
  });
});

describe('repartidor-E03: un repartidor pendiente no opera', () => {
  test.each(PLATAFORMAS)(
    'repartidor-E03 — (%s) al abrir la app con la sesión de un repartidor pendiente, ve la espera y la app no pide sus pedidos',
    async (plataforma) => {
      servidor().estado = 'pendiente';

      await abrirLaAppConSesion(plataforma);

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));
      expect(await screen.findByText(MENSAJES_SIN_ACCESO.pendiente)).toBeOnTheScreen();
      expect(servidor().consultasDeLaLista()).toBe(0);
    },
  );

  test.each(PLATAFORMAS)(
    'repartidor-E03 — (%s) al iniciar sesión, un repartidor pendiente ve la espera',
    async (plataforma) => {
      servidor().estado = 'pendiente';
      await abrirLaAppSinSesion(plataforma);
      const persona = userEvent.setup();

      await persona.type(await screen.findByLabelText('Correo electrónico'), 'repartidor@ejemplo.com');
      await persona.type(screen.getByLabelText('Contraseña'), 'contrasena-de-prueba');
      await persona.press(screen.getByRole('button', { name: 'Iniciar sesión' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));
      expect(await screen.findByText(MENSAJES_SIN_ACCESO.pendiente)).toBeOnTheScreen();
      await expect(leerRol()).resolves.toBe('repartidor');
    },
  );

  // repartidor-E06 en la app: la suspensión también le cierra los pedidos.
  test('repartidor-E03 — un repartidor suspendido ve que su cuenta está suspendida, sin sus pedidos', async () => {
    servidor().estado = 'suspendido';

    await abrirLaAppConSesion('android');

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));
    expect(await screen.findByText(MENSAJES_SIN_ACCESO.suspendido)).toBeOnTheScreen();
    expect(servidor().consultasDeLaLista()).toBe(0);
  });

  test('repartidor-E03 — tampoco abre sus pedidos aunque la ruta los pida', async () => {
    servidor().estado = 'pendiente';

    await abrirLaAppConSesion('ios', RUTA_DE_LOS_PEDIDOS);

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));
    expect(servidor().consultasDeLaLista()).toBe(0);
  });

  test.each(PLATAFORMAS)(
    'repartidor-E03 — (%s) "Revisar de nuevo" vuelve a preguntar su estado y, ya dado de alta, abre sus pedidos',
    async (plataforma) => {
      servidor().estado = 'pendiente';
      await abrirLaAppConSesion(plataforma);
      await screen.findByText(MENSAJES_SIN_ACCESO.pendiente);

      // La administración lo da de alta en el panel (repartidor-E05).
      servidor().estado = 'activo';
      await fireEvent.press(screen.getByRole('button', { name: 'Revisar de nuevo' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
      expect(await screen.findByRole('header', { name: 'Pedidos asignados' })).toBeOnTheScreen();
      expect(servidor().consultasDelPerfil()).toBe(2);
    },
  );

  test('repartidor-E03 — sin alta todavía, "Revisar de nuevo" lo deja en la espera', async () => {
    servidor().estado = 'pendiente';
    await abrirLaAppConSesion('android');
    await screen.findByText(MENSAJES_SIN_ACCESO.pendiente);

    await fireEvent.press(screen.getByRole('button', { name: 'Revisar de nuevo' }));

    await waitFor(() => expect(servidor().consultasDelPerfil()).toBe(2));
    expect(screen).toHavePathname(RUTA_DE_LA_ESPERA);
    expect(await screen.findByText(MENSAJES_SIN_ACCESO.pendiente)).toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'repartidor-E03 — (%s) un repartidor activo entra a sus pedidos asignados y no a la espera',
    async (plataforma) => {
      await abrirLaAppConSesion(plataforma);

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
      expect(await screen.findByRole('header', { name: 'Pedidos asignados' })).toBeOnTheScreen();
      expect(screen.queryByText(MENSAJES_SIN_ACCESO.pendiente)).not.toBeOnTheScreen();
    },
  );

  // Sin escenario propio: si el perfil no llega, no se adivina si opera; lo dice, deja intentarlo y cerrar la sesión.
  test('repartidor-E03 — si su estado no llega, lo dice con el mensaje general y deja intentarlo de nuevo', async () => {
    servidor().interceptar = ({ ruta }) => {
      if (ruta === '/repartidor/perfil') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };
    await abrirLaAppConSesion('ios');

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(servidor().consultasDeLaLista()).toBe(0);

    servidor().interceptar = null;
    await fireEvent.press(screen.getByRole('button', { name: 'Intentar de nuevo' }));

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
  });
});

describe('repartidor-E04 (bloqueado por DD-053): el registro no tiene entrada desde la app', () => {
  test('repartidor-E04 — el inicio de sesión no lleva al registro del repartidor', async () => {
    await abrirLaAppSinSesion('android');
    await screen.findByLabelText('Correo electrónico');

    expect(screen.queryByText(/repartidor/i)).not.toBeOnTheScreen();
  });

  test('repartidor-E04 — con la sesión abierta, el registro del repartidor lleva al módulo de la cuenta', async () => {
    await abrirLaAppConSesion('ios', RUTA_DEL_REGISTRO);

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
  });
});
