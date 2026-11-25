// Spec: openspec/changes/alta-de-lavanderias/specs/alta-lavanderias/spec.md, alta-lavanderias-E26 a E30 (tarea 4.3).
// La descripción y las fotos del perfil, en Perfil y horarios (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo
// A #5, "Fotos, descripción"; :17:Módulo B #6, "Edición de información de la lavandería"; acta-2026-11-20-modelo-y-
// pasarela.md, decisión 44): la descripción es opcional, y las fotos son una lista en su orden, con "Quitar" si hay más
// de una, "Mover antes" si no es la primera, y la toma o la elección de una foto nueva, comprimida como la del registro,
// mientras haya menos que el máximo que da el servidor. La edición manda la lista completa: cada foto que ya tiene por
// su lugar en la lectura, y cada nueva como archivo, en multipart por POST con _method=PATCH (design.md del cambio, "El
// contrato de la API"). El registro conserva su campo único de foto y no pide descripción.
//
// Se abre la app real con la sesión de una cuenta de socio lavandería con su lavandería, en iPhone y en Android, con el
// servidor simulado (__tests__/apoyo/lavanderia.ts). Las reglas (el máximo, la última foto) las prueba también el
// servidor (servidor/tests/Feature/AltaLavanderias/DescripcionYFotosTest.php); aquí, cada prueba pone el 422 que
// necesita.
import * as ImageManipulator from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';
import { fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

import {
  abrirLaAppConSesion,
  fotoGuardada,
  LAVANDERIA_DEL_SOCIO,
  PLATAFORMAS,
  respuesta,
  RUTA_DE_LOS_PEDIDOS,
  usarElServidorDeLaLavanderia,
  type Plataforma,
} from '../apoyo/lavanderia';
import { contenidoDelArchivo } from '../apoyo/formulario';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaLavanderia('alta-descripcion-y-fotos');

const RUTA_DEL_PERFIL = '/lavanderia/perfil';
const API_DE_LA_LAVANDERIA = `/socio/lavanderias/${LAVANDERIA_DEL_SOCIO.id_publico}`;

// Las simulaciones de __mocks__/, la misma instancia que importa la app.
const camara = ImagePicker as unknown as typeof import('../../__mocks__/expo-image-picker');
const manipulador = ImageManipulator as unknown as typeof import('../../__mocks__/expo-image-manipulator');

const FOTO_DE_LA_GALERIA = { uri: 'file:///galeria/mostrador.jpg', width: 4032, height: 3024, mimeType: 'image/jpeg' };
const FOTO_DE_LA_CAMARA = { uri: 'file:///camara/lavadoras.jpg', width: 3024, height: 4032, mimeType: 'image/jpeg' };

const FACHADA = fotoGuardada('fachada');
const MOSTRADOR = fotoGuardada('mostrador');
const LAVADORAS = fotoGuardada('lavadoras');

/** La lavandería de la cuenta en el servidor, para cambiarla antes de abrir la pantalla. */
function lavanderia() {
  return servidor().lavanderias[0];
}

/** La lavandería tiene esas fotos guardadas, en ese orden. */
function conFotos(...fotos: { url: string; vence_en: string }[]) {
  lavanderia().fotos = fotos;
  lavanderia().foto = fotos[0] ?? null;
}

/** Abre los pedidos de la lavandería y, desde ahí, su perfil y horarios, ya con su información. */
async function abrirElPerfil(plataforma: Plataforma) {
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  await screen.findByRole('header', { name: 'Pedidos activos' });
  await fireEvent.press(screen.getByRole('link', { name: 'Perfil y horarios' }));
  await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_PERFIL));
  await screen.findByLabelText('Nombre');
}

async function guardar() {
  await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Guardar cambios' }));
}

/** Lo que la app mandó para editar la lavandería. */
function ediciones() {
  return servidor()
    .envios()
    .filter(({ ruta }) => ruta === API_DE_LA_LAVANDERIA);
}

/** La URL de cada foto de la lista de la pantalla, en su orden. */
function fotosEnLaPantalla(): string[] {
  return within(screen.getByTestId('campo-fotos'))
    .queryAllByLabelText(/^Foto \d+ de la lavandería$/)
    .map((foto) => (foto.props.source as { uri: string }).uri);
}

/** El botón de una foto de la lista, por su lugar (1, la primera): el lector de pantalla dice de qué foto es. */
function botonDeLaFoto(numero: number, accion: 'Quitar' | 'Mover antes') {
  return within(screen.getByTestId(`foto-${numero}`)).queryByRole('button', { name: `${accion} la foto ${numero}` });
}

describe('alta-lavanderias-E26: la lavandería escribe su descripción', () => {
  test.each(PLATAFORMAS)(
    'alta-lavanderias-E26 — (%s) escribe su descripción y guarda: la app la manda y queda la que guardó el servidor',
    async (plataforma) => {
      await abrirElPerfil(plataforma);
      expect(screen.getByLabelText('Descripción')).toHaveDisplayValue('');

      await fireEvent.changeText(screen.getByLabelText('Descripción'), 'Lavamos y entregamos el mismo día.');
      await guardar();

      await waitFor(() => expect(ediciones()).toHaveLength(1));
      expect(ediciones()[0]).toEqual({
        metodo: 'PATCH',
        ruta: API_DE_LA_LAVANDERIA,
        multipart: false,
        cuerpo: { descripcion: 'Lavamos y entregamos el mismo día.' },
      });
      expect(await screen.findByText('Guardamos los cambios.')).toBeOnTheScreen();
      expect(lavanderia().descripcion).toBe('Lavamos y entregamos el mismo día.');
      expect(screen.getByLabelText('Descripción')).toHaveDisplayValue('Lavamos y entregamos el mismo día.');
    },
  );

  test('alta-lavanderias-E26 — la deja vacía: la manda vacía y el perfil queda sin descripción', async () => {
    lavanderia().descripcion = 'Descripción de prueba anterior.';
    await abrirElPerfil('android');
    expect(screen.getByLabelText('Descripción')).toHaveDisplayValue('Descripción de prueba anterior.');

    await fireEvent.changeText(screen.getByLabelText('Descripción'), '  ');
    await guardar();

    await waitFor(() => expect(ediciones()).toHaveLength(1));
    expect(ediciones()[0].cuerpo).toEqual({ descripcion: '' });
    expect(await screen.findByText('Guardamos los cambios.')).toBeOnTheScreen();
    expect(lavanderia().descripcion).toBeNull();
    expect(screen.getByLabelText('Descripción')).toHaveDisplayValue('');
  });

  test('alta-lavanderias-E26 — sin tocar la descripción ni las fotos, no se mandan', async () => {
    lavanderia().descripcion = 'Descripción de prueba.';
    conFotos(FACHADA, MOSTRADOR);
    await abrirElPerfil('ios');

    await fireEvent.changeText(screen.getByLabelText('Nombre'), 'Otro nombre');
    await guardar();

    await waitFor(() => expect(ediciones()).toHaveLength(1));
    expect(ediciones()[0].cuerpo).toEqual({ nombre: 'Otro nombre' });
  });

  test('alta-lavanderias-E26 — el error del servidor en la descripción va junto a ella', async () => {
    await abrirElPerfil('ios');
    servidor().interceptar = ({ metodo, ruta }) =>
      metodo !== 'GET' && ruta === API_DE_LA_LAVANDERIA
        ? respuesta(422, {
            message: 'La descripción puede tener hasta 1000 caracteres.',
            errors: { descripcion: ['La descripción puede tener hasta 1000 caracteres.'] },
          })
        : undefined;

    await fireEvent.changeText(screen.getByLabelText('Descripción'), 'a'.repeat(1001));
    await guardar();

    expect(await within(screen.getByTestId('campo-descripcion')).findByRole('alert')).toHaveTextContent(
      'La descripción puede tener hasta 1000 caracteres.',
    );
  });

  test('alta-lavanderias-E26 — el registro no pide descripción y conserva su campo único de foto', async () => {
    servidor().lavanderias = [];
    await abrirLaAppConSesion('android');
    await screen.findByRole('header', { name: 'Registrar lavandería' });

    expect(screen.queryByLabelText('Descripción')).not.toBeOnTheScreen();
    expect(screen.queryByTestId('campo-fotos')).not.toBeOnTheScreen();
    expect(within(screen.getByTestId('campo-foto')).getByRole('button', { name: 'Tomar foto' })).toBeOnTheScreen();
  });
});

describe('alta-lavanderias-E27: agregar una foto', () => {
  test.each(PLATAFORMAS)(
    'alta-lavanderias-E27 — (%s) elige una de la galería: queda al final, comprimida, y la edición va en multipart con la lista completa',
    async (plataforma) => {
      conFotos(FACHADA);
      await abrirElPerfil(plataforma);
      expect(fotosEnLaPantalla()).toEqual([FACHADA.url]);
      camara.__fotoDeLaGaleria(FOTO_DE_LA_GALERIA);

      await fireEvent.press(within(screen.getByTestId('campo-fotos')).getByRole('button', { name: 'Elegir de la galería' }));
      await waitFor(() =>
        expect(fotosEnLaPantalla()).toEqual([FACHADA.url, manipulador.__preparada(FOTO_DE_LA_GALERIA.uri)]),
      );
      await guardar();

      await waitFor(() => expect(ediciones()).toHaveLength(1));
      expect(ediciones()[0]).toEqual({
        metodo: 'POST',
        ruta: API_DE_LA_LAVANDERIA,
        multipart: true,
        cuerpo: {
          _method: 'PATCH',
          fotos: [
            '0',
            {
              nombre: 'foto.jpg',
              tipo: 'image/jpeg',
              contenido: contenidoDelArchivo(manipulador.__preparada(FOTO_DE_LA_GALERIA.uri)),
            },
          ],
        },
      });
      // Comprimida como la del registro: el lado mayor al máximo de la configuración y en JPEG.
      expect(manipulador.__preparaciones().at(-1)).toMatchObject({
        fuente: FOTO_DE_LA_GALERIA.uri,
        guardado: { format: 'jpeg' },
      });
      // Al guardar, la lista es la que devolvió el servidor, con sus URLs firmadas.
      expect(await screen.findByText('Guardamos los cambios.')).toBeOnTheScreen();
      expect(fotosEnLaPantalla()).toEqual(lavanderia().fotos.map(({ url }) => url));
      expect(lavanderia().fotos).toHaveLength(2);
    },
  );

  test('alta-lavanderias-E27 — también la toma con la cámara, y va al final', async () => {
    conFotos(FACHADA, MOSTRADOR);
    await abrirElPerfil('android');
    camara.__fotoDeLaCamara(FOTO_DE_LA_CAMARA);

    await fireEvent.press(within(screen.getByTestId('campo-fotos')).getByRole('button', { name: 'Tomar foto' }));
    await waitFor(() => expect(fotosEnLaPantalla()).toHaveLength(3));
    await guardar();

    await waitFor(() => expect(ediciones()).toHaveLength(1));
    const { fotos } = ediciones()[0].cuerpo as { fotos: unknown[] };
    expect(fotos.slice(0, 2)).toEqual(['0', '1']);
    expect(fotos[2]).toMatchObject({ contenido: contenidoDelArchivo(manipulador.__preparada(FOTO_DE_LA_CAMARA.uri)) });
  });

  test('alta-lavanderias-E27 — el error del servidor de una foto de la lista va en la sección de fotos', async () => {
    await abrirElPerfil('ios');
    servidor().interceptar = ({ metodo, ruta }) =>
      metodo !== 'GET' && ruta === API_DE_LA_LAVANDERIA
        ? respuesta(422, { message: 'La foto pesa más de 5120 KB.', errors: { 'fotos.1': ['La foto pesa más de 5120 KB.'] } })
        : undefined;
    camara.__fotoDeLaGaleria(FOTO_DE_LA_GALERIA);

    await fireEvent.press(within(screen.getByTestId('campo-fotos')).getByRole('button', { name: 'Elegir de la galería' }));
    await waitFor(() => expect(fotosEnLaPantalla()).toHaveLength(2));
    await guardar();

    expect(await within(screen.getByTestId('campo-fotos')).findByRole('alert')).toHaveTextContent(
      'La foto pesa más de 5120 KB.',
    );
    // Lo elegido se queda para corregirlo.
    expect(fotosEnLaPantalla()).toHaveLength(2);
  });
});

describe('alta-lavanderias-E28: quitar una foto', () => {
  test.each(PLATAFORMAS)(
    'alta-lavanderias-E28 — (%s) con dos fotos quita la primera: la lista queda sin ella y la app manda la que queda',
    async (plataforma) => {
      conFotos(FACHADA, MOSTRADOR);
      await abrirElPerfil(plataforma);

      await fireEvent.press(botonDeLaFoto(1, 'Quitar')!);

      expect(fotosEnLaPantalla()).toEqual([MOSTRADOR.url]);
      await guardar();
      await waitFor(() => expect(ediciones()).toHaveLength(1));
      expect(ediciones()[0]).toEqual({ metodo: 'PATCH', ruta: API_DE_LA_LAVANDERIA, multipart: false, cuerpo: { fotos: [1] } });
      expect(await screen.findByText('Guardamos los cambios.')).toBeOnTheScreen();
      expect(lavanderia().fotos).toEqual([MOSTRADOR]);
    },
  );

  test('alta-lavanderias-E28 — la última foto no se puede quitar: no tiene "Quitar"', async () => {
    conFotos(FACHADA, MOSTRADOR);
    await abrirElPerfil('ios');
    expect(botonDeLaFoto(1, 'Quitar')).toBeOnTheScreen();
    expect(botonDeLaFoto(2, 'Quitar')).toBeOnTheScreen();

    await fireEvent.press(botonDeLaFoto(2, 'Quitar')!);

    expect(fotosEnLaPantalla()).toEqual([FACHADA.url]);
    expect(botonDeLaFoto(1, 'Quitar')).not.toBeOnTheScreen();
  });
});

describe('alta-lavanderias-E29: cambiar el orden', () => {
  test.each(PLATAFORMAS)(
    'alta-lavanderias-E29 — (%s) con tres fotos pasa la tercera antes de la segunda: la app manda el orden nuevo',
    async (plataforma) => {
      conFotos(FACHADA, MOSTRADOR, LAVADORAS);
      await abrirElPerfil(plataforma);

      await fireEvent.press(botonDeLaFoto(3, 'Mover antes')!);

      expect(fotosEnLaPantalla()).toEqual([FACHADA.url, LAVADORAS.url, MOSTRADOR.url]);
      await guardar();
      await waitFor(() => expect(ediciones()).toHaveLength(1));
      expect(ediciones()[0].cuerpo).toEqual({ fotos: [0, 2, 1] });
      expect(await screen.findByText('Guardamos los cambios.')).toBeOnTheScreen();
      expect(lavanderia().fotos).toEqual([FACHADA, LAVADORAS, MOSTRADOR]);
    },
  );

  test('alta-lavanderias-E29 — la primera es la principal: no tiene "Mover antes", y la que pasa al frente queda como principal', async () => {
    conFotos(FACHADA, MOSTRADOR);
    await abrirElPerfil('android');
    expect(screen.getByText('La primera es la principal. Puedes tener hasta 5.')).toBeOnTheScreen();
    expect(botonDeLaFoto(1, 'Mover antes')).not.toBeOnTheScreen();

    await fireEvent.press(botonDeLaFoto(2, 'Mover antes')!);
    await guardar();

    await waitFor(() => expect(ediciones()).toHaveLength(1));
    expect(ediciones()[0].cuerpo).toEqual({ fotos: [1, 0] });
    expect(await screen.findByText('Guardamos los cambios.')).toBeOnTheScreen();
    expect(lavanderia().foto).toEqual(MOSTRADOR);
  });
});

describe('alta-lavanderias-E30: el máximo de fotos', () => {
  test.each(PLATAFORMAS)(
    'alta-lavanderias-E30 — (%s) con el máximo del servidor, la app no ofrece agregar otra; al quitar una, vuelve a ofrecerlo',
    async (plataforma) => {
      lavanderia().maximo_de_fotos = 2;
      conFotos(FACHADA, MOSTRADOR);
      await abrirElPerfil(plataforma);

      const fotos = within(screen.getByTestId('campo-fotos'));
      expect(fotos.queryByRole('button', { name: 'Tomar foto' })).not.toBeOnTheScreen();
      expect(fotos.queryByRole('button', { name: 'Elegir de la galería' })).not.toBeOnTheScreen();
      expect(fotos.getByText('La primera es la principal. Puedes tener hasta 2.')).toBeOnTheScreen();

      await fireEvent.press(botonDeLaFoto(2, 'Quitar')!);

      expect(fotos.getByRole('button', { name: 'Tomar foto' })).toBeOnTheScreen();
      expect(fotos.getByRole('button', { name: 'Elegir de la galería' })).toBeOnTheScreen();
    },
  );

  test('alta-lavanderias-E30 — si el servidor rechaza la lista por el máximo, lo dice en las fotos y nada cambia', async () => {
    conFotos(FACHADA);
    await abrirElPerfil('ios');
    servidor().interceptar = ({ metodo, ruta }) =>
      metodo !== 'GET' && ruta === API_DE_LA_LAVANDERIA
        ? respuesta(422, {
            message: 'La lavandería puede tener hasta 1 fotos.',
            errors: { fotos: ['La lavandería puede tener hasta 1 fotos.'] },
          })
        : undefined;
    camara.__fotoDeLaGaleria(FOTO_DE_LA_GALERIA);

    await fireEvent.press(within(screen.getByTestId('campo-fotos')).getByRole('button', { name: 'Elegir de la galería' }));
    await waitFor(() => expect(fotosEnLaPantalla()).toHaveLength(2));
    await guardar();

    expect(await within(screen.getByTestId('campo-fotos')).findByRole('alert')).toHaveTextContent(
      'La lavandería puede tener hasta 1 fotos.',
    );
    expect(screen.queryByText('Guardamos los cambios.')).not.toBeOnTheScreen();
    expect(lavanderia().fotos).toEqual([FACHADA]);
  });
});
