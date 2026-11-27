// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "Gestión de
// repartidores en la app": administracion-app-E23 (editar a un repartidor), con E03 (la entrada abre su pantalla dentro
// de la app), E05 (el 401) y E12 (el 404). Tarea 6.3 de openspec/changes/administracion-en-la-app/tasks.md.
//
// El Módulo D #2 pide la "edición de socios repartidores" (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3), con los datos
// del registro del Módulo C #1: "nombre, teléfono, correo, foto de perfil y tipo de vehículo" (NSV-2026-001-v8.pdf:18).
// Arthur decidió que la administración lo hace en la app (C-247); cómo, lo decide ADR-0022 (decisión 9): la app no
// valida por su cuenta, el servidor aplica las reglas del registro (repartidor-E08) y responde 422 con el error de cada
// campo, también el del correo que ya es de otra cuenta, y la app lo pone junto a su campo.
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado de la
// administración (__tests__/apoyo/administracion.ts), los repartidores del contrato de RepartidoresController
// (__tests__/apoyo/administracion/repartidores.ts) y la cámara, la galería y la compresión simuladas (__mocks__/). La
// foto sale en multipart como la manda el teléfono (__tests__/apoyo/formulario.ts).
import * as ImageManipulator from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';
import { act, fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

import { MENSAJES } from '../../src/acceso/mensajes';
import { leerToken } from '../../src/sesion/almacen';
import {
  abrirLaAppConLaAdministracion,
  PLATAFORMAS,
  respuesta,
  RUTA_DEL_INICIO,
  usarElServidorDeLaAdministracion,
  type Plataforma,
} from '../apoyo/administracion';
import {
  ACTIVO,
  comoSale,
  consultas,
  NO_EXISTE,
  PENDIENTE,
  QUE_NO_EXISTE,
  usarLosRepartidores,
  type RepartidorGuardado,
} from '../apoyo/administracion/repartidores';
import { contenidoDelArchivo } from '../apoyo/formulario';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaAdministracion('administracion-edicion-de-repartidor');
const repartidores = usarLosRepartidores(servidor);

// Las simulaciones de __mocks__/, la misma instancia que importa la app.
const camara = ImagePicker as unknown as typeof import('../../__mocks__/expo-image-picker');
const manipulador = ImageManipulator as unknown as typeof import('../../__mocks__/expo-image-manipulator');

/** Una foto de la cámara de un teléfono, más grande que el lado máximo de la configuración. */
const FOTO_DE_LA_CAMARA = { uri: 'file:///camara/repartidor.jpg', width: 4032, height: 3024, mimeType: 'image/jpeg' };
/** Una foto vertical de la galería, en el formato de iPhone. */
const FOTO_DE_LA_GALERIA = { uri: 'file:///galeria/repartidor.heic', width: 3024, height: 4032, mimeType: 'image/heic' };

const RUTA_DE_LA_LISTA = '/administracion/repartidores';

/** El mensaje de App\Validacion\CorreoSinCuenta, como lo da el servidor. */
const CORREO_CON_CUENTA = 'Ese correo ya tiene una cuenta.';

/** Las etiquetas de los campos de texto, en su orden, con su nombre en la API. */
const CAMPOS: [campo: 'nombre' | 'telefono' | 'correo' | 'tipo_vehiculo', etiqueta: string][] = [
  ['nombre', 'Nombre'],
  ['telefono', 'Teléfono'],
  ['correo', 'Correo electrónico'],
  ['tipo_vehiculo', 'Tipo de vehículo'],
];

function rutaDeLaFicha(repartidor: Pick<RepartidorGuardado, 'id_publico'>): string {
  return `${RUTA_DE_LA_LISTA}/${repartidor.id_publico}`;
}

function rutaDeLaEdicion(repartidor: Pick<RepartidorGuardado, 'id_publico'>): string {
  return `${RUTA_DE_LA_LISTA}/editar/${repartidor.id_publico}`;
}

/** Abre la ficha y, desde ella, la edición con "Editar": así "Regresar" y guardar vuelven a la ficha. */
async function abrirLaEdicion(plataforma: Plataforma, repartidor: RepartidorGuardado = ACTIVO) {
  await abrirLaAppConLaAdministracion(plataforma, rutaDeLaFicha(repartidor));
  await screen.findByRole('header', { name: repartidor.nombre as string });
  await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Editar' }));
  await screen.findByRole('header', { name: 'Editar repartidor' });
  await screen.findByLabelText('Nombre');
}

async function guardar() {
  await fireEvent.press(screen.getByRole('button', { name: 'Guardar cambios' }));
}

/** El error de un campo, junto a él. */
function errorDe(campo: string) {
  return within(screen.getByTestId(`campo-${campo}`)).queryByRole('alert');
}

/** Las ediciones que la app mandó: el PATCH en JSON, o el POST multipart con _method=PATCH. */
function ediciones() {
  return servidor()
    .peticiones()
    .filter(({ metodo, ruta }) => metodo !== 'GET' && /^\/administracion\/repartidores\/[^/]+$/.test(ruta))
    .map(({ metodo, ruta, cuerpo, multipart }) => ({ metodo, ruta, cuerpo, multipart }));
}

describe('administracion-app-E03: la entrada abre su pantalla dentro de la app', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E03 — (%s) del inicio a la lista, a la ficha y, con "Editar", a la edición; "Regresar" vuelve a la ficha',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_INICIO);
      await screen.findByRole('header', { name: 'Administración' });

      await fireEvent.press(screen.getByRole('link', { name: 'Repartidores' }));
      await fireEvent.press(await screen.findByRole('link', { name: /^Repartidor de prueba 1,/ }));
      await screen.findByRole('header', { name: ACTIVO.nombre as string });
      await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Editar' }));

      await waitFor(() => expect(screen).toHavePathname(rutaDeLaEdicion(ACTIVO)));
      expect(await screen.findByRole('header', { name: 'Editar repartidor' })).toBeOnTheScreen();

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

      await waitFor(() => expect(screen).toHavePathname(rutaDeLaFicha(ACTIVO)));
      expect(await screen.findByRole('header', { name: ACTIVO.nombre as string })).toBeOnTheScreen();
      expect(ediciones()).toEqual([]);
    },
  );
});

describe('administracion-app-E23: editar a un repartidor', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E23 — (%s) el formulario trae sus datos en el orden del registro, con su foto, y "Guardar cambios" fijo abajo',
    async (plataforma) => {
      await abrirLaEdicion(plataforma);

      for (const [campo, etiqueta] of CAMPOS) {
        expect(screen.getByLabelText(etiqueta).props.value).toBe(ACTIVO[campo]);
      }
      const etiquetas = within(screen.getByTestId('contenido'))
        .getAllByText(/^(Nombre|Teléfono|Correo electrónico|Tipo de vehículo|Foto de perfil)$/)
        .map((texto) => texto.props.children as string);
      expect(etiquetas).toEqual(['Nombre', 'Teléfono', 'Correo electrónico', 'Tipo de vehículo', 'Foto de perfil']);
      expect(screen.getByLabelText('Foto de perfil').props.source).toEqual({ uri: ACTIVO.foto?.url });
      expect(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Guardar cambios' })).toBeEnabled();
      // La contraseña no se edita.
      expect(screen.queryByLabelText('Contraseña')).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E23 y repartidor-E08 — (%s) sin foto, manda en JSON solo los datos que cambiaron, como se escribieron, y la ficha los muestra',
    async (plataforma) => {
      await abrirLaEdicion(plataforma);

      await fireEvent.changeText(screen.getByLabelText('Nombre'), '  Repartidor de prueba 1 editado ');
      await fireEvent.changeText(screen.getByLabelText('Tipo de vehículo'), 'Bicicleta');
      await guardar();

      await waitFor(() => expect(screen).toHavePathname(rutaDeLaFicha(ACTIVO)));
      expect(ediciones()).toEqual([
        {
          metodo: 'PATCH',
          ruta: rutaDeLaFicha(ACTIVO),
          cuerpo: { nombre: '  Repartidor de prueba 1 editado ', tipo_vehiculo: 'Bicicleta' },
          multipart: false,
        },
      ]);
      // La ficha se pide de nuevo y trae lo que guardó el servidor, sin los espacios de las orillas.
      expect(await screen.findByRole('header', { name: 'Repartidor de prueba 1 editado' })).toBeOnTheScreen();
      expect(screen.getByLabelText('Tipo de vehículo: Bicicleta')).toBeOnTheScreen();
      expect(consultas(servidor(), rutaDeLaFicha(ACTIVO))).toBeGreaterThanOrEqual(2);
      expect(repartidores().de(ACTIVO.id_publico)).toMatchObject({
        nombre: 'Repartidor de prueba 1 editado',
        telefono: ACTIVO.telefono,
        correo: ACTIVO.correo,
        tipo_vehiculo: 'Bicicleta',
        estado: 'activo',
      });
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E23 — (%s) con una foto nueva de la cámara, manda en multipart por POST con _method=PATCH, la foto comprimida y los datos que cambiaron',
    async (plataforma) => {
      await abrirLaEdicion(plataforma);
      camara.__fotoDeLaCamara(FOTO_DE_LA_CAMARA);

      await fireEvent.press(screen.getByRole('button', { name: 'Tomar foto' }));
      await waitFor(() =>
        expect(screen.getByLabelText('Foto de perfil').props.source).toEqual({
          uri: manipulador.__preparada(FOTO_DE_LA_CAMARA.uri),
        }),
      );
      await fireEvent.changeText(screen.getByLabelText('Teléfono'), '618 000 0099');
      await guardar();

      await waitFor(() => expect(screen).toHavePathname(rutaDeLaFicha(ACTIVO)));
      const contenido = contenidoDelArchivo(manipulador.__preparada(FOTO_DE_LA_CAMARA.uri));
      expect(ediciones()).toEqual([
        {
          metodo: 'POST',
          ruta: rutaDeLaFicha(ACTIVO),
          cuerpo: {
            _method: 'PATCH',
            telefono: '618 000 0099',
            foto: { nombre: 'foto.jpg', tipo: 'image/jpeg', contenido },
          },
          multipart: true,
        },
      ]);
      // La ficha trae la URL firmada nueva de la foto.
      await waitFor(() =>
        expect(screen.getByLabelText('Foto de perfil').props.source).toEqual({
          uri: repartidores().de(ACTIVO.id_publico).foto?.url,
        }),
      );
      expect(repartidores().de(ACTIVO.id_publico).foto?.url).not.toBe(ACTIVO.foto?.url);
      expect(screen.getByLabelText('Teléfono: 618 000 0099')).toBeOnTheScreen();
    },
  );

  test('administracion-app-E23 — la foto también se puede elegir de la galería, a un repartidor que no tenía', async () => {
    await abrirLaEdicion('ios', PENDIENTE);
    expect(screen.queryByLabelText('Foto de perfil')).not.toBeOnTheScreen();
    camara.__fotoDeLaGaleria(FOTO_DE_LA_GALERIA);

    await fireEvent.press(screen.getByRole('button', { name: 'Elegir de la galería' }));
    await screen.findByLabelText('Foto de perfil');
    await guardar();

    await waitFor(() => expect(screen).toHavePathname(rutaDeLaFicha(PENDIENTE)));
    expect(ediciones()).toEqual([
      {
        metodo: 'POST',
        ruta: rutaDeLaFicha(PENDIENTE),
        cuerpo: {
          _method: 'PATCH',
          foto: {
            nombre: 'foto.jpg',
            tipo: 'image/jpeg',
            contenido: contenidoDelArchivo(manipulador.__preparada(FOTO_DE_LA_GALERIA.uri)),
          },
        },
        multipart: true,
      },
    ]);
    expect(repartidores().de(PENDIENTE.id_publico).foto).not.toBeNull();
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E23 — (%s) el correo que ya es de otra cuenta responde 422: el error va junto al correo, nada cambia y sigue en la edición',
    async (plataforma) => {
      servidor().interceptar = ({ metodo, ruta }) =>
        metodo === 'PATCH' && ruta === rutaDeLaFicha(ACTIVO)
          ? respuesta(422, { message: CORREO_CON_CUENTA, errors: { correo: [CORREO_CON_CUENTA] } })
          : undefined;
      await abrirLaEdicion(plataforma);

      await fireEvent.changeText(screen.getByLabelText('Correo electrónico'), 'usuario1@prueba.test');
      await guardar();

      await waitFor(() => expect(errorDe('correo')).toHaveTextContent(CORREO_CON_CUENTA));
      expect(screen).toHavePathname(rutaDeLaEdicion(ACTIVO));
      // Lo escrito se queda para corregirlo, y el mensaje no se repite arriba del botón.
      expect(screen.getByLabelText('Correo electrónico').props.value).toBe('usuario1@prueba.test');
      expect(within(screen.getByTestId('acciones')).queryByRole('alert')).not.toBeOnTheScreen();
      expect(repartidores().de(ACTIVO.id_publico)).toEqual(ACTIVO);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E23 — (%s) cada 422 va junto a su campo, con el mensaje del servidor, también el de la foto, y nada cambia',
    async (plataforma) => {
      const errores = {
        nombre: ['Escribe tu nombre.'],
        telefono: ['El teléfono no es válido.'],
        correo: ['El correo no es válido.'],
        tipo_vehiculo: ['Escribe tu tipo de vehículo.'],
        foto: ['La foto debe ser una imagen: jpg, jpeg, png, webp.'],
      };
      servidor().interceptar = ({ metodo, ruta }) =>
        metodo === 'POST' && ruta === rutaDeLaFicha(ACTIVO)
          ? respuesta(422, { message: errores.nombre[0], errors: errores })
          : undefined;
      await abrirLaEdicion(plataforma);
      camara.__fotoDeLaGaleria(FOTO_DE_LA_GALERIA);
      await fireEvent.press(screen.getByRole('button', { name: 'Elegir de la galería' }));
      await waitFor(() =>
        expect(screen.getByLabelText('Foto de perfil').props.source).toEqual({
          uri: manipulador.__preparada(FOTO_DE_LA_GALERIA.uri),
        }),
      );
      for (const [, etiqueta] of CAMPOS) {
        await fireEvent.changeText(screen.getByLabelText(etiqueta), ' ');
      }

      await guardar();

      await waitFor(() => expect(errorDe('nombre')).toHaveTextContent('Escribe tu nombre.'));
      expect(errorDe('telefono')).toHaveTextContent('El teléfono no es válido.');
      expect(errorDe('correo')).toHaveTextContent('El correo no es válido.');
      expect(errorDe('tipo_vehiculo')).toHaveTextContent('Escribe tu tipo de vehículo.');
      expect(errorDe('foto')).toHaveTextContent('La foto debe ser una imagen: jpg, jpeg, png, webp.');
      expect(within(screen.getByTestId('acciones')).queryByRole('alert')).not.toBeOnTheScreen();
      expect(screen).toHavePathname(rutaDeLaEdicion(ACTIVO));
      expect(repartidores().de(ACTIVO.id_publico)).toEqual(ACTIVO);
      // La app no validó por su cuenta: mandó lo que se escribió y el servidor lo rechazó.
      expect(ediciones()).toEqual([
        expect.objectContaining({
          metodo: 'POST',
          multipart: true,
          cuerpo: expect.objectContaining({ _method: 'PATCH', nombre: ' ', telefono: ' ', correo: ' ', tipo_vehiculo: ' ' }),
        }),
      ]);
    },
  );

  test('administracion-app-E23 — al corregir y guardar otra vez, los errores se van y guarda', async () => {
    let rechazar = true;
    servidor().interceptar = ({ metodo, ruta }) =>
      rechazar && metodo === 'PATCH' && ruta === rutaDeLaFicha(ACTIVO)
        ? respuesta(422, { message: CORREO_CON_CUENTA, errors: { correo: [CORREO_CON_CUENTA] } })
        : undefined;
    await abrirLaEdicion('android');
    await fireEvent.changeText(screen.getByLabelText('Correo electrónico'), 'usuario1@prueba.test');
    await guardar();
    await waitFor(() => expect(errorDe('correo')).toHaveTextContent(CORREO_CON_CUENTA));

    rechazar = false;
    await fireEvent.changeText(screen.getByLabelText('Correo electrónico'), 'repartidor1.nuevo@prueba.test');
    await guardar();

    await waitFor(() => expect(screen).toHavePathname(rutaDeLaFicha(ACTIVO)));
    expect(await screen.findByLabelText('Correo: repartidor1.nuevo@prueba.test')).toBeOnTheScreen();
    expect(repartidores().de(ACTIVO.id_publico).correo).toBe('repartidor1.nuevo@prueba.test');
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E23 — (%s) mientras manda, el botón se bloquea: un doble toque manda una sola edición',
    async (plataforma) => {
      let soltar: () => void = () => {};
      servidor().interceptar = ({ metodo, ruta }) =>
        metodo === 'PATCH' && ruta === rutaDeLaFicha(ACTIVO)
          ? new Promise((resolver) => {
              soltar = () => {
                repartidores().cambiar(ACTIVO.id_publico, { nombre: 'Otro nombre' });
                resolver(respuesta(200, { repartidor: comoSale(repartidores().de(ACTIVO.id_publico)) }));
              };
            })
          : undefined;
      await abrirLaEdicion(plataforma);
      await fireEvent.changeText(screen.getByLabelText('Nombre'), 'Otro nombre');

      await guardar();
      await guardar();

      expect(ediciones()).toHaveLength(1);
      expect(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Guardar cambios' })).toBeDisabled();
      soltar();
      await waitFor(() => expect(screen).toHavePathname(rutaDeLaFicha(ACTIVO)));
      expect(ediciones()).toHaveLength(1);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E23 — (%s) si regresa a la ficha mientras guarda, la respuesta que llega después no lo saca de la ficha',
    async (plataforma) => {
      let soltar: () => void = () => {};
      servidor().interceptar = ({ metodo, ruta }) =>
        metodo === 'PATCH' && ruta === rutaDeLaFicha(ACTIVO)
          ? new Promise((resolver) => {
              soltar = () => {
                repartidores().cambiar(ACTIVO.id_publico, { nombre: 'Otro nombre' });
                resolver(respuesta(200, { repartidor: comoSale(repartidores().de(ACTIVO.id_publico)) }));
              };
            })
          : undefined;
      // Desde la lista: un segundo regreso, el de guardar, la sacaría de la ficha.
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LA_LISTA);
      await fireEvent.press(await screen.findByRole('link', { name: /^Repartidor de prueba 1,/ }));
      await screen.findByRole('header', { name: ACTIVO.nombre as string });
      await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Editar' }));
      await fireEvent.changeText(await screen.findByLabelText('Nombre'), 'Otro nombre');

      await guardar();
      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
      await waitFor(() => expect(screen).toHavePathname(rutaDeLaFicha(ACTIVO)));
      await act(async () => {
        soltar();
        // La respuesta llega cuando la edición ya no se ve, y la app termina de leerla.
        await jest.advanceTimersByTimeAsync(0);
      });

      expect(screen).toHavePathname(rutaDeLaFicha(ACTIVO));
      expect(screen.getByRole('header', { name: 'Repartidor' })).toBeOnTheScreen();
      expect(ediciones()).toHaveLength(1);
    },
  );

  test('administracion-app-E23 — sin conexión al guardar, lo dice arriba del botón y lo escrito se queda', async () => {
    await abrirLaEdicion('ios');
    servidor().interceptar = ({ metodo, ruta }) => {
      if (metodo === 'PATCH' && ruta === rutaDeLaFicha(ACTIVO)) {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };
    await fireEvent.changeText(screen.getByLabelText('Nombre'), 'Sin conexión');

    await guardar();

    expect(await within(screen.getByTestId('acciones')).findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(screen).toHavePathname(rutaDeLaEdicion(ACTIVO));
    expect(screen.getByLabelText('Nombre').props.value).toBe('Sin conexión');
    expect(repartidores().de(ACTIVO.id_publico).nombre).toBe(ACTIVO.nombre);
  });
});

describe('administracion-app-E12: un registro que no existe', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E12 — (%s) la edición de un repartidor que no existe dice el mensaje del 404, sin formulario',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, rutaDeLaEdicion({ id_publico: QUE_NO_EXISTE }));

      expect(await screen.findByText(NO_EXISTE)).toBeOnTheScreen();
      expect(screen.queryByLabelText('Nombre')).not.toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Guardar cambios' })).not.toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Intentar de nuevo' })).not.toBeOnTheScreen();
    },
  );

  test('administracion-app-E12 — si otra sesión lo borra mientras se edita, guardar dice el mensaje del 404 y no regresa', async () => {
    await abrirLaEdicion('android');
    servidor().interceptar = ({ metodo, ruta }) =>
      metodo === 'PATCH' && ruta === rutaDeLaFicha(ACTIVO) ? respuesta(404, { message: NO_EXISTE }) : undefined;
    await fireEvent.changeText(screen.getByLabelText('Nombre'), 'Otro nombre');

    await guardar();

    expect(await within(screen.getByTestId('acciones')).findByText(NO_EXISTE)).toBeOnTheScreen();
    expect(screen).toHavePathname(rutaDeLaEdicion(ACTIVO));
  });
});

describe('administracion-app-E05: la sesión vence o se revoca', () => {
  async function esperarElInicioDeSesion() {
    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    expect(await screen.findByLabelText('Contraseña')).toBeOnTheScreen();
    await expect(leerToken()).resolves.toBeNull();
  }

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el token vencido, la edición recibe 401 al abrirse y la app vuelve al inicio de sesión',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, rutaDeLaFicha(ACTIVO));
      await screen.findByRole('header', { name: ACTIVO.nombre as string });
      servidor().vencerLaSesion();

      await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Editar' }));

      await esperarElInicioDeSesion();
      expect(consultas(servidor(), rutaDeLaFicha(ACTIVO))).toBe(2);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el token vencido, guardar recibe 401, nada cambia y la app vuelve al inicio de sesión',
    async (plataforma) => {
      await abrirLaEdicion(plataforma);
      servidor().vencerLaSesion();
      await fireEvent.changeText(screen.getByLabelText('Nombre'), 'Otro nombre');

      await guardar();

      await esperarElInicioDeSesion();
      expect(ediciones()).toEqual([expect.objectContaining({ metodo: 'PATCH', ruta: rutaDeLaFicha(ACTIVO) })]);
      expect(repartidores().de(ACTIVO.id_publico)).toEqual(ACTIVO);
    },
  );
});
