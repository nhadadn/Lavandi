// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "Gestión de
// lavanderías en la app": administracion-app-E14 (editar una lavandería); y del requisito "La administración inicia
// sesión en la app", E03 (la edición se abre dentro de la app desde el inicio del módulo) y E05 (la sesión vence en la
// edición). Tarea 4.3. El contrato: "Alta, baja, suspensión y edición de socios." (NSV-2026-001-v8.pdf:19:ANEXO A,
// Sección A.3, Módulo D #1). Las reglas son las del registro de la lavandería, en ReglasDeLaLavanderia y
// EdicionDeLavanderia; las prueba el servidor (servidor/tests/Feature/AdministracionEnLaApp/LavanderiasTest.php). Aquí,
// que la app manda solo lo que cambió, con las fotos en multipart por POST con _method=PATCH, que al guardar regresa a la
// ficha con los datos nuevos, que pone cada 422 junto a su campo sin validar nada por su cuenta, que el municipio se
// elige de la lista del territorio, que la administración lee (adenda del 2026-11-26 de ADR-0022, decisión 6), que la
// lista de fotos va con la marca de las fotos que se leyeron al abrir (design.md del cambio, F-D01), y que la pantalla
// del perfil del socio muestra lo que quedó.
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado de la
// administración (__tests__/apoyo/administracion.ts) y sus lavanderías (__tests__/apoyo/administracion/lavanderias.ts);
// el socio, con el de la lavandería (__tests__/apoyo/lavanderia.ts).
import * as ImageManipulator from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';
import { fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

import { borrarRol, leerRol, leerToken } from '../../src/sesion/almacen';
import {
  abrirLaAppConLaAdministracion,
  copia,
  PLATAFORMAS,
  TOKEN_DE_LA_ADMINISTRACION,
  usarElServidorDeLaAdministracion,
  type Plataforma,
} from '../apoyo/administracion';
import {
  ACTIVA,
  DE_BAJA,
  FOTOS_QUE_CAMBIARON,
  LavanderiasDelServidor,
  marcaDeLasFotos,
  NO_EXISTE_LA_LAVANDERIA,
  RUTA_DE_LAS_LAVANDERIAS,
  rutaDe,
  type LavanderiaDelServidor,
} from '../apoyo/administracion/lavanderias';
import { contenidoDelArchivo } from '../apoyo/formulario';
import { abrirLaAppConSesion, RUTA_DE_LOS_PEDIDOS, ServidorDeLaLavanderia } from '../apoyo/lavanderia';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaAdministracion('administracion-edicion-de-lavanderia');
let lavanderias: LavanderiasDelServidor;

// Las simulaciones de __mocks__/, la misma instancia que importa la app.
const camara = ImagePicker as unknown as typeof import('../../__mocks__/expo-image-picker');
const manipulador = ImageManipulator as unknown as typeof import('../../__mocks__/expo-image-manipulator');

const FOTO_DE_LA_GALERIA = { uri: 'file:///galeria/mostrador.jpg', width: 4032, height: 3024, mimeType: 'image/jpeg' };

beforeEach(() => {
  lavanderias = new LavanderiasDelServidor(servidor());
});

/** La ruta de la edición de una lavandería en la app. */
function edicionEnLaApp(lavanderia: LavanderiaDelServidor): string {
  return `${RUTA_DE_LAS_LAVANDERIAS}/editar/${lavanderia.datos.id_publico}`;
}

/** La lavandería del servidor, como está ahora. */
function enElServidor(lavanderia: LavanderiaDelServidor): LavanderiaDelServidor {
  return lavanderias.de(lavanderia.datos.id_publico);
}

/** Abre la app con la sesión de la administración, Lavanderías, la ficha de esa lavandería y su "Editar". */
async function abrirLaEdicion(plataforma: Plataforma, lavanderia: LavanderiaDelServidor = ACTIVA) {
  await abrirLaAppConLaAdministracion(plataforma);
  await fireEvent.press(await screen.findByRole('link', { name: 'Lavanderías' }));
  await fireEvent.press(await screen.findByTestId(`lavanderia-${lavanderia.datos.id_publico}`));
  await screen.findByRole('header', { name: lavanderia.datos.nombre });
  await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Editar' }));
  await waitFor(() => expect(screen).toHavePathname(edicionEnLaApp(lavanderia)));
  await screen.findByLabelText('Nombre');
}

async function escribir(etiqueta: string, texto: string) {
  await fireEvent.changeText(screen.getByLabelText(etiqueta), texto);
}

async function marcar(etiqueta: string, valor = true) {
  await fireEvent(screen.getByRole('switch', { name: etiqueta }), 'valueChange', valor);
}

async function guardar() {
  await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Guardar cambios' }));
}

async function errorDe(campo: string) {
  return within(screen.getByTestId(`campo-${campo}`)).findByRole('alert');
}

/** La URL de cada foto de la lista de la pantalla, en su orden. */
function fotosEnLaPantalla(): string[] {
  return within(screen.getByTestId('campo-fotos'))
    .queryAllByLabelText(/^Foto \d+ de la lavandería$/)
    .map((foto) => (foto.props.source as { uri: string }).uri);
}

describe('administracion-app-E03: la edición se abre dentro de la app', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E03 — (%s) del inicio a Lavanderías, de ahí a la ficha y de su "Editar" a la edición; "Regresar" vuelve a la ficha',
    async (plataforma) => {
      await abrirLaEdicion(plataforma);

      expect(screen.getByRole('header', { name: 'Editar lavandería' })).toBeOnTheScreen();
      expect(lavanderias.pedidas()).toEqual([
        `GET ${RUTA_DE_LAS_LAVANDERIAS}`,
        `GET ${rutaDe(ACTIVA)}`,
        `GET ${rutaDe(ACTIVA)}`,
      ]);

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
      await waitFor(() => expect(screen).toHavePathname(rutaDe(ACTIVA)));
      expect(await screen.findByRole('header', { name: ACTIVA.datos.nombre })).toBeOnTheScreen();
    },
  );
});

describe('administracion-app-E14: editar una lavandería', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E14 — (%s) la edición trae sus datos en el orden del formulario del registro, con las instrucciones que hablan de la lavandería y el lavado en seco en espera de DD-010',
    async (plataforma) => {
      await abrirLaEdicion(plataforma);

      // Las secciones, en el orden del formulario: los datos y la dirección, las fotos, los servicios y las ventanas.
      expect(screen.getAllByRole('header').map((titulo) => titulo.props.children)).toEqual([
        'Editar lavandería',
        'Datos de la lavandería',
        'Dirección',
        'Fotos',
        'Servicios y precios',
        'Ventanas de disponibilidad',
      ]);
      expect(screen.getByLabelText('Nombre')).toHaveDisplayValue(ACTIVA.datos.nombre);
      expect(screen.getByLabelText('Descripción')).toHaveDisplayValue('Texto de prueba.');
      expect(screen.getByLabelText('Calle')).toHaveDisplayValue('Calle de Prueba');
      expect(screen.getByLabelText('Número')).toHaveDisplayValue('100');
      expect(screen.getByLabelText('Colonia')).toHaveDisplayValue('Zona Centro');
      expect(screen.getByLabelText('Código postal')).toHaveDisplayValue('34000');
      expect(screen.getByRole('button', { name: 'Municipio' })).toHaveAccessibilityValue({ text: 'Durango, Durango' });
      expect(fotosEnLaPantalla()).toEqual(ACTIVA.datos.fotos.map(({ url }) => url));
      expect(screen.getByRole('switch', { name: 'Lavado estándar' })).toBeChecked();
      expect(screen.getByLabelText('Precio por kilo de lavado estándar')).toHaveDisplayValue('18.50');
      expect(screen.getByRole('switch', { name: 'Lavado con planchado' })).not.toBeChecked();
      expect(screen.getByRole('switch', { name: '09:00 – 10:00' })).toBeChecked();
      expect(screen.getByRole('switch', { name: '10:00 – 11:00' })).toBeChecked();
      expect(screen.getByRole('switch', { name: '11:00 – 12:00' })).not.toBeChecked();

      // Las instrucciones hablan de la lavandería, no de tú, con el máximo de fotos que da el servidor.
      expect(screen.getByText('La primera es la principal. Puede tener hasta 5.')).toBeOnTheScreen();
      expect(
        screen.getByText('Marca, día por día, las horas en que la lavandería puede recolectar. Cada ventana dura una hora.'),
      ).toBeOnTheScreen();
      expect(screen.queryByText(/Puedes tener hasta|puedes recolectar|Tu lavandería/)).not.toBeOnTheScreen();

      // El lavado en seco, en su lugar al final de los servicios, sin interruptor.
      const lavadoEnSeco = within(screen.getByTestId('lavado-en-seco'));
      expect(lavadoEnSeco.getByText('Lavado en seco')).toBeOnTheScreen();
      expect(lavadoEnSeco.getByText('Por confirmar · DD-010')).toBeOnTheScreen();
      expect(lavadoEnSeco.getByText('La app no lo ofrece mientras no se fije su unidad.')).toBeOnTheScreen();
      expect(screen.queryByRole('switch', { name: 'Lavado en seco' })).not.toBeOnTheScreen();

      // "Guardar cambios", fijo abajo.
      expect(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Guardar cambios' })).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E14 — (%s) cambia su nombre, su descripción, su dirección, sus servicios y su horario: manda solo lo que cambió, en JSON, y regresa a la ficha, que muestra los datos nuevos',
    async (plataforma) => {
      await abrirLaEdicion(plataforma);

      await escribir('Nombre', 'Lavandería de prueba renovada');
      await escribir('Descripción', 'Lavamos y entregamos el mismo día.');
      await escribir('Calle', 'Calle Nueva');
      await escribir('Precio por kilo de lavado estándar', '20');
      await marcar('Lavado con planchado');
      await escribir('Precio por kilo de lavado con planchado', '25.50');
      await marcar('11:00 – 12:00');
      await guardar();

      // "Guardar cambios" lleva a la ficha (imágenes 12, 60 y 61 de la Propuesta 06), que vuelve a pedir la lavandería.
      await waitFor(() => expect(screen).toHavePathname(rutaDe(ACTIVA)));
      expect(await screen.findByRole('header', { name: 'Lavandería de prueba renovada' })).toBeOnTheScreen();
      expect(lavanderias.pedidas()).toEqual([
        `GET ${RUTA_DE_LAS_LAVANDERIAS}`,
        `GET ${rutaDe(ACTIVA)}`,
        `GET ${rutaDe(ACTIVA)}`,
        `PATCH ${rutaDe(ACTIVA)}`,
        `GET ${rutaDe(ACTIVA)}`,
      ]);
      expect(lavanderias.envios(ACTIVA)).toEqual([
        {
          metodo: 'PATCH',
          ruta: rutaDe(ACTIVA),
          multipart: false,
          cuerpo: {
            nombre: 'Lavandería de prueba renovada',
            descripcion: 'Lavamos y entregamos el mismo día.',
            // La dirección va completa, con el municipio que tenía.
            direccion: {
              calle: 'Calle Nueva',
              numero: '100',
              colonia: 'Zona Centro',
              codigo_postal: '34000',
              municipio: '10005',
            },
            servicios: [
              { tipo: 'estandar', precio: '20' },
              { tipo: 'planchado', precio: '25.50' },
            ],
            ventanas: [
              { dia_semana: 1, hora_inicio: '09:00', hora_fin: '10:00' },
              { dia_semana: 1, hora_inicio: '10:00', hora_fin: '11:00' },
              { dia_semana: 1, hora_inicio: '11:00', hora_fin: '12:00' },
            ],
          },
        },
      ]);
      // La edición no cambia su estado.
      expect(enElServidor(ACTIVA).estado).toBe('activa');
      expect(screen.getByTestId('estado-de-la-lavanderia')).toHaveTextContent('Activa');
      expect(screen.getByTestId('dato-direccion')).toHaveTextContent(
        'Dirección' + 'Calle Nueva 100, Zona Centro, C. P. 34000, Durango, Durango',
      );
      expect(screen.getByTestId('dato-servicios')).toHaveTextContent(
        'Servicios y precios' + 'Lavado estándar: $20.00 por kilo · Lavado con planchado: $25.50 por kilo',
      );
      expect(screen.getByTestId('dato-ventanas')).toHaveTextContent(
        'Ventanas de disponibilidad' + 'Lunes, de 09:00 a 12:00',
      );
      expect(screen.getByTestId('dato-descripcion-y-fotos')).toHaveTextContent(
        'Descripción y fotos' + 'Lavamos y entregamos el mismo día. · 2 fotos',
      );
    },
  );

  test('administracion-app-E14 — sin cambiar nada más, solo viaja el nombre', async () => {
    await abrirLaEdicion('ios');

    await escribir('Nombre', 'Otro nombre');
    await guardar();

    await waitFor(() => expect(screen).toHavePathname(rutaDe(ACTIVA)));
    expect(lavanderias.envios(ACTIVA)).toHaveLength(1);
    expect(lavanderias.envios(ACTIVA)[0].cuerpo).toEqual({ nombre: 'Otro nombre' });
  });

  test('administracion-app-E14 — abierta sin la ficha detrás, al guardar abre la ficha de esa lavandería', async () => {
    await abrirLaAppConLaAdministracion('android', edicionEnLaApp(ACTIVA));
    await screen.findByLabelText('Nombre');

    await escribir('Nombre', 'Otro nombre');
    await guardar();

    await waitFor(() => expect(screen).toHavePathname(rutaDe(ACTIVA)));
    expect(await screen.findByRole('header', { name: 'Otro nombre' })).toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E14 — (%s) sus fotos: mueve la segunda antes, agrega una de la galería y quita otra; la edición va en multipart por POST con _method=PATCH y el servidor lee el archivo',
    async (plataforma) => {
      await abrirLaEdicion(plataforma);
      const [primera, segunda] = ACTIVA.datos.fotos;
      camara.__fotoDeLaGaleria(FOTO_DE_LA_GALERIA);

      await fireEvent.press(
        within(screen.getByTestId('foto-2')).getByRole('button', { name: 'Mover antes la foto 2' }),
      );
      await fireEvent.press(
        within(screen.getByTestId('campo-fotos')).getByRole('button', { name: 'Elegir de la galería' }),
      );
      const nueva = manipulador.__preparada(FOTO_DE_LA_GALERIA.uri);
      await waitFor(() => expect(fotosEnLaPantalla()).toEqual([segunda.url, primera.url, nueva]));
      await fireEvent.press(within(screen.getByTestId('foto-2')).getByRole('button', { name: 'Quitar la foto 2' }));
      expect(fotosEnLaPantalla()).toEqual([segunda.url, nueva]);
      await guardar();

      await waitFor(() => expect(screen).toHavePathname(rutaDe(ACTIVA)));
      expect(lavanderias.envios(ACTIVA)).toEqual([
        {
          metodo: 'POST',
          ruta: rutaDe(ACTIVA),
          multipart: true,
          cuerpo: {
            _method: 'PATCH',
            // Cada una que ya tiene, por su lugar en la lectura; la nueva, con su archivo.
            fotos: ['1', { nombre: 'foto.jpg', tipo: 'image/jpeg', contenido: contenidoDelArchivo(nueva) }],
            // Y la marca de la lectura con que se abrió la edición, tal cual.
            fotos_leidas: marcaDeLasFotos(ACTIVA),
          },
        },
      ]);
      // El servidor guardó la lista en ese orden: la segunda es ahora la principal, y la nueva, con su URL firmada.
      const guardadas = enElServidor(ACTIVA).datos.fotos;
      expect(guardadas.map(({ url }) => url)).toEqual([
        segunda.url,
        `https://fotos.prueba.invalid/${encodeURIComponent(contenidoDelArchivo(nueva))}?firma=2`,
      ]);
      // La ficha se pide otra vez y cuenta las fotos que quedaron.
      await waitFor(() =>
        expect(lavanderias.pedidas().slice(-2)).toEqual([`POST ${rutaDe(ACTIVA)}`, `GET ${rutaDe(ACTIVA)}`]),
      );
      expect(screen.getByTestId('dato-descripcion-y-fotos')).toHaveTextContent(
        'Descripción y fotos' + 'Texto de prueba. · 2 fotos',
      );
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E14 — (%s) sus fotos en otro orden, sin una nueva: la lista va en JSON con la marca de la lectura con que se abrió',
    async (plataforma) => {
      await abrirLaEdicion(plataforma);
      const [primera, segunda] = ACTIVA.datos.fotos;

      await fireEvent.press(
        within(screen.getByTestId('foto-2')).getByRole('button', { name: 'Mover antes la foto 2' }),
      );
      await guardar();

      await waitFor(() => expect(screen).toHavePathname(rutaDe(ACTIVA)));
      expect(lavanderias.envios(ACTIVA)).toEqual([
        {
          metodo: 'PATCH',
          ruta: rutaDe(ACTIVA),
          multipart: false,
          cuerpo: { fotos: [1, 0], fotos_leidas: marcaDeLasFotos(ACTIVA) },
        },
      ]);
      expect(enElServidor(ACTIVA).datos.fotos.map(({ url }) => url)).toEqual([segunda.url, primera.url]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E14 — (%s) si su socio cambió las fotos después de abrir la edición, el 422 del servidor va junto a las fotos, nada cambia y la pantalla se queda',
    async (plataforma) => {
      await abrirLaEdicion(plataforma);
      // Su socio, desde su app, quita la segunda foto mientras la administración edita.
      const [primera] = ACTIVA.datos.fotos;
      enElServidor(ACTIVA).datos.fotos = [primera];
      enElServidor(ACTIVA).datos.foto = primera;

      await fireEvent.press(
        within(screen.getByTestId('foto-2')).getByRole('button', { name: 'Mover antes la foto 2' }),
      );
      await guardar();

      expect(await within(screen.getByTestId('campo-fotos')).findByText(FOTOS_QUE_CAMBIARON)).toBeOnTheScreen();
      // Mandó la marca de lo que leyó al abrir, y el servidor no cambió nada.
      expect(lavanderias.envios(ACTIVA)).toEqual([
        {
          metodo: 'PATCH',
          ruta: rutaDe(ACTIVA),
          multipart: false,
          cuerpo: { fotos: [1, 0], fotos_leidas: marcaDeLasFotos(ACTIVA) },
        },
      ]);
      expect(enElServidor(ACTIVA).datos.fotos).toEqual([primera]);
      expect(screen).toHavePathname(edicionEnLaApp(ACTIVA));
      expect(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Guardar cambios' })).toBeEnabled();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E14 — (%s) un dato que no cumple las reglas: cada error del 422 va junto a su campo y nada cambia',
    async (plataforma) => {
      await abrirLaEdicion(plataforma);
      lavanderias.rechazo = {
        message: 'Falta el nombre de la lavandería. (y 2 errores más)',
        errors: {
          nombre: ['Falta el nombre de la lavandería.'],
          'servicios.0.precio': ['El precio va en pesos, sin signo y con hasta dos decimales.'],
          'fotos.1': ['La foto debe ser una imagen JPG, PNG o WebP.'],
        },
      };

      await escribir('Nombre', '');
      await escribir('Precio por kilo de lavado estándar', '$20');
      await fireEvent.press(within(screen.getByTestId('foto-2')).getByRole('button', { name: 'Mover antes la foto 2' }));
      await guardar();

      expect(await errorDe('nombre')).toHaveTextContent('Falta el nombre de la lavandería.');
      expect(await errorDe('precio-estandar')).toHaveTextContent(
        'El precio va en pesos, sin signo y con hasta dos decimales.',
      );
      expect(await within(screen.getByTestId('campo-fotos')).findByText('La foto debe ser una imagen JPG, PNG o WebP.')).toBeOnTheScreen();
      // La pantalla se queda (imagen 13): nada cambió en el servidor, y lo escrito sigue ahí para corregirlo.
      expect(screen).toHavePathname(edicionEnLaApp(ACTIVA));
      expect(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Guardar cambios' })).toBeEnabled();
      expect(enElServidor(ACTIVA).datos).toEqual(ACTIVA.datos);
      expect(screen.getByLabelText('Precio por kilo de lavado estándar')).toHaveDisplayValue('$20');
    },
  );

  test('administracion-app-E14 — la dirección que cae en otra plaza se dice en la dirección, con su motivo, y nada cambia', async () => {
    await abrirLaEdicion('android');
    lavanderias.rechazo = {
      message: 'Texto del servidor.',
      errors: { direccion: ['Texto del servidor.'] },
      motivo: 'en_otra_plaza',
    };

    await escribir('Calle', 'Calle en Torreón');
    await guardar();

    expect(await errorDe('direccion')).toHaveTextContent(
      'La dirección nueva queda en otra plaza de servicio. Ese cambio todavía no se puede hacer.',
    );
    expect(enElServidor(ACTIVA).datos.direccion.calle).toBe('Calle de Prueba');
    expect(screen).toHavePathname(edicionEnLaApp(ACTIVA));
  });

  test('administracion-app-E14 — si la lavandería ya no existe al guardar, el mensaje del servidor va arriba del botón y la pantalla se queda', async () => {
    await abrirLaEdicion('ios');
    lavanderias.lavanderias = lavanderias.lavanderias.filter(({ datos }) => datos.id_publico !== ACTIVA.datos.id_publico);

    await escribir('Nombre', 'Otro nombre');
    await guardar();

    expect(await screen.findByText(NO_EXISTE_LA_LAVANDERIA)).toBeOnTheScreen();
    expect(screen).toHavePathname(edicionEnLaApp(ACTIVA));
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E14 — (%s) en cualquier estado: una lavandería de baja también se edita, y sigue de baja',
    async (plataforma) => {
      await abrirLaEdicion(plataforma, DE_BAJA);

      await escribir('Descripción', 'Descripción corregida.');
      await guardar();

      await waitFor(() => expect(screen).toHavePathname(rutaDe(DE_BAJA)));
      expect(lavanderias.envios(DE_BAJA)).toEqual([
        { metodo: 'PATCH', ruta: rutaDe(DE_BAJA), multipart: false, cuerpo: { descripcion: 'Descripción corregida.' } },
      ]);
      expect(enElServidor(DE_BAJA).estado).toBe('baja');
      expect(enElServidor(DE_BAJA).datos.descripcion).toBe('Descripción corregida.');
      // La ficha, pedida otra vez, sigue de baja y con la descripción nueva.
      await waitFor(() =>
        expect(screen.getByTestId('dato-descripcion-y-fotos')).toHaveTextContent(
          'Descripción y fotos' + 'Descripción corregida. · 2 fotos',
        ),
      );
      expect(screen.getByTestId('estado-de-la-lavanderia')).toHaveTextContent('Dada de baja');
    },
  );

  // Aquí, la pantalla del socio pinta la lavandería como la dejó la edición en el servidor simulado, que se le copia a
  // mano. Que la ruta del socio lea lo que editó la administración es del servidor, y lo prueba de punta a punta
  // servidor/tests/Feature/AdministracionEnLaApp/LavanderiasTest.php (paquete A05, rama admin/a05):
  // test_administracion_app_e14_edita_sus_datos_como_la_edicion_del_panel y
  // test_administracion_app_e14_edita_una_lavanderia_en_cualquier_estado editan por la API de la administración y leen por
  // GET /api/socio/lavanderias/{id_publico} con el token del socio.
  test.each(PLATAFORMAS)(
    'administracion-app-E14 — (%s) la pantalla del perfil del socio muestra la lavandería como la dejó la edición; la garantía de punta a punta es la prueba del servidor (LavanderiasTest.php)',
    async (plataforma) => {
      await abrirLaEdicion(plataforma);
      await escribir('Nombre', 'Lavandería de prueba renovada');
      await escribir('Descripción', 'Lavamos y entregamos el mismo día.');
      await guardar();
      await waitFor(() => expect(screen).toHavePathname(rutaDe(ACTIVA)));

      // Su socio abre la app en su teléfono, y el servidor le da su lavandería como la dejó la administración.
      screen.unmount();
      const delSocio = new ServidorDeLaLavanderia(process.env.EXPO_PUBLIC_API_URL ?? '');
      delSocio.lavanderias = [copia(enElServidor(ACTIVA).datos)];
      global.fetch = delSocio.fetch as unknown as typeof fetch;
      await borrarRol();
      await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
      await screen.findByRole('header', { name: 'Pedidos activos' });
      await fireEvent.press(screen.getByRole('link', { name: 'Perfil y horarios' }));

      expect(await screen.findByLabelText('Nombre')).toHaveDisplayValue('Lavandería de prueba renovada');
      expect(screen.getByLabelText('Descripción')).toHaveDisplayValue('Lavamos y entregamos el mismo día.');
      expect(delSocio.peticiones()).toContainEqual(
        expect.objectContaining({ metodo: 'GET', ruta: `/socio/lavanderias/${ACTIVA.datos.id_publico}` }),
      );
    },
  );
});

describe('administracion-app-E14: el municipio de su dirección', () => {
  // E14 pide cambiar "su dirección con su municipio": la lista es la del territorio, GET /api/catalogos/municipios, que
  // también es de la administración (adenda del 2026-11-26 de ADR-0022, decisión 6; registro-E33).
  test.each(PLATAFORMAS)(
    'administracion-app-E14 — (%s) elige otro municipio de la lista del territorio: viaja la dirección completa con el código nuevo, y la ficha lo muestra',
    async (plataforma) => {
      await abrirLaEdicion(plataforma);

      // La lista llega con la sesión de la administración, sin el error de la lista.
      await waitFor(() => expect(screen.getByRole('button', { name: 'Municipio' })).toBeEnabled());
      expect(screen.queryByText(/No pudimos cargar la lista de municipios/)).not.toBeOnTheScreen();
      expect(
        servidor()
          .peticiones()
          .filter(({ metodo, ruta }) => metodo === 'GET' && ruta === '/catalogos/municipios')
          .map(({ autorizacion }) => autorizacion),
      ).toEqual([`Bearer ${TOKEN_DE_LA_ADMINISTRACION}`]);

      await fireEvent.press(screen.getByRole('button', { name: 'Municipio' }));
      await fireEvent.press(
        within(screen.getByTestId('hoja-direccion.municipio')).getByRole('radio', {
          name: 'Torreón, Coahuila de Zaragoza',
        }),
      );
      expect(screen.getByRole('button', { name: 'Municipio' })).toHaveAccessibilityValue({
        text: 'Torreón, Coahuila de Zaragoza',
      });
      await guardar();

      await waitFor(() => expect(screen).toHavePathname(rutaDe(ACTIVA)));
      expect(lavanderias.envios(ACTIVA)).toEqual([
        {
          metodo: 'PATCH',
          ruta: rutaDe(ACTIVA),
          multipart: false,
          cuerpo: {
            // La dirección va completa, con las señas que tenía y el municipio nuevo.
            direccion: {
              calle: 'Calle de Prueba',
              numero: '100',
              colonia: 'Zona Centro',
              codigo_postal: '34000',
              municipio: '05035',
            },
          },
        },
      ]);
      expect(enElServidor(ACTIVA).datos.direccion.municipio).toEqual({
        codigo: '05035',
        nombre: 'Torreón',
        estado: 'Coahuila de Zaragoza',
      });
      expect(await screen.findByTestId('dato-direccion')).toHaveTextContent(
        'Dirección' + 'Calle de Prueba 100, Zona Centro, C. P. 34000, Torreón, Coahuila de Zaragoza',
      );
    },
  );
});

describe('administracion-app-E05: la sesión vence en la edición', () => {
  async function esperarElInicioDeSesion() {
    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    expect(await screen.findByLabelText('Contraseña')).toBeOnTheScreen();
    await expect(leerToken()).resolves.toBeNull();
    await expect(leerRol()).resolves.toBeNull();
  }

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el 401 al abrir la edición, la app borra la sesión del teléfono y abre el inicio de sesión',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma);
      await fireEvent.press(await screen.findByRole('link', { name: 'Lavanderías' }));
      await fireEvent.press(await screen.findByTestId(`lavanderia-${ACTIVA.datos.id_publico}`));
      await screen.findByRole('header', { name: ACTIVA.datos.nombre });
      servidor().vencerLaSesion();

      await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Editar' }));

      await esperarElInicioDeSesion();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) si la sesión vence antes de guardar, nada cambia y la app abre el inicio de sesión',
    async (plataforma) => {
      await abrirLaEdicion(plataforma);
      servidor().vencerLaSesion();

      await escribir('Nombre', 'Otro nombre');
      await guardar();

      await esperarElInicioDeSesion();
      expect(enElServidor(ACTIVA).datos.nombre).toBe(ACTIVA.datos.nombre);
    },
  );
});
