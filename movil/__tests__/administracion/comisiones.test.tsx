// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "Comisiones en la
// app": administracion-app-E32 (el porcentaje global y el de cada lavandería), E33 (fijar el global y el propio, y quitar
// el propio) y E34 (un porcentaje fuera de rango), con E03 (cada entrada abre su pantalla dentro de la app) y E05 (el 401
// en cada pantalla). Tareas 12.1 y 12.2 de openspec/changes/administracion-en-la-app/tasks.md. La comisión de los socios
// repartidores, administracion-app-E35, está bloqueada por DD-019 (tarea 22.5): aquí solo se prueba que su lugar se ve
// en ámbar, sin cifra ni botón.
//
// El Módulo D #5 pide "Porcentaje de comisión por socio o global." (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3). Arthur
// decidió que la administración lo hace en la app (C-247); cómo, lo decide ADR-0022 (decisiones 9 y 11). Las reglas son
// las del panel (administracion-E11 a E13): la app no propone ningún porcentaje, manda el que se escribe y muestra lo que
// responde el servidor. La composición y los textos son los de las imágenes 28 a 30, 67 y 68 de la Propuesta 06 (C-248).
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado de la
// administración (__tests__/apoyo/administracion.ts) y las comisiones del contrato de ComisionesController
// (__tests__/apoyo/administracion/comisiones.ts).
import { act, fireEvent, screen, waitFor, within } from 'expo-router/testing-library';
import { Alert, type AlertButton } from 'react-native';

import { MENSAJES } from '../../src/acceso/mensajes';
import { leerRol, leerToken } from '../../src/sesion/almacen';
import {
  abrirLaAppConLaAdministracion,
  PLATAFORMAS,
  respuesta,
  RUTA_DEL_INICIO,
  TOKEN_DE_LA_ADMINISTRACION,
  usarElServidorDeLaAdministracion,
  type Plataforma,
} from '../apoyo/administracion';
import {
  AHORA,
  cambiosMandados,
  consultas,
  DE_TORREON,
  DURANGO_CENTRO,
  GLOBAL,
  MENSAJES_DEL_PORCENTAJE,
  NO_EXISTE,
  SIN_GLOBAL,
  SIN_NINGUNO,
  usarLasComisiones,
  type ComisionesGuardadas,
  type LavanderiaGuardada,
} from '../apoyo/administracion/comisiones';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaAdministracion('administracion-comisiones');
const comisiones = usarLasComisiones(servidor);

const RUTA_DE_LAS_COMISIONES = '/administracion/comisiones';

let dialogo: jest.SpyInstance;

beforeEach(() => {
  // Alert.alert puede ser ya una simulación de react-native que guarda las llamadas de todo el archivo.
  dialogo = jest.spyOn(Alert, 'alert').mockImplementation(() => {});
  dialogo.mockClear();
});

/** Deja en el servidor esas comisiones, en lugar de las de siempre, antes de abrir la app. */
function guardarEnElServidor({ global, lavanderias }: ComisionesGuardadas) {
  comisiones().guardadas.global = global === null ? null : { ...global };
  comisiones().guardadas.lavanderias.splice(0, Infinity, ...lavanderias.map((lavanderia) => ({ ...lavanderia })));
}

async function abrirLasComisiones(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LAS_COMISIONES);
  await screen.findByRole('header', { name: 'Porcentaje global' });
}

function tarjetaDelGlobal() {
  return within(screen.getByTestId('comision-global'));
}

function tarjetaDe(lavanderia: Pick<LavanderiaGuardada, 'id_publico'>) {
  return within(screen.getByTestId(`comision-${lavanderia.id_publico}`));
}

/** Los textos de la tarjeta de una lavandería, en su orden: el nombre, la plaza y qué le aplica. */
function textosDe(lavanderia: Pick<LavanderiaGuardada, 'id_publico'>): string[] {
  return tarjetaDe(lavanderia)
    .getAllByText(/.+/)
    .map((texto) => texto.props.children as string);
}

/** El nombre de cada lavandería de la lista, en su orden. */
function nombresDeLaLista(): string[] {
  return screen
    .getAllByTestId(/^comision-[0-9a-z]{26}$/)
    .map((tarjeta) => within(tarjeta).getAllByText(/.+/)[0].props.children as string);
}

function enlace(accion: string, lavanderia: Pick<LavanderiaGuardada, 'nombre'>) {
  return screen.getByRole('link', { name: `${accion} de ${lavanderia.nombre}` });
}

/** El título, el texto y los botones del último diálogo de confirmación. */
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

/** Escribe el porcentaje en el campo abierto y toca "Guardar". */
async function escribirYGuardar(etiqueta: string, porcentaje: string) {
  await fireEvent.changeText(screen.getByLabelText(etiqueta), porcentaje);
  await fireEvent.press(screen.getByRole('button', { name: 'Guardar' }));
}

describe('administracion-app-E03: cada entrada abre su pantalla dentro de la app', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E03 — (%s) "Comisiones" del inicio abre la pantalla, que pide las comisiones, y "Regresar" vuelve al inicio',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_INICIO);
      await screen.findByRole('header', { name: 'Administración' });

      await fireEvent.press(screen.getByRole('link', { name: 'Comisiones' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LAS_COMISIONES));
      expect(await screen.findByRole('header', { name: 'Comisiones' })).toBeOnTheScreen();
      expect(await screen.findByRole('header', { name: 'Porcentaje global' })).toBeOnTheScreen();
      expect(consultas(servidor())).toBe(1);

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_INICIO));
      expect(screen.getByRole('header', { name: 'Administración' })).toBeOnTheScreen();
    },
  );
});

describe('administracion-app-E32: el porcentaje global y el de cada lavandería', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E32 — (%s) con un global guardado, una lavandería con propio y otra sin él, en plazas distintas: el global y desde cuándo rige, y cada una con el que le aplica y de dónde sale',
    async (plataforma) => {
      await abrirLasComisiones(plataforma);

      expect(tarjetaDelGlobal().getByText('12.50 %')).toBeOnTheScreen();
      expect(tarjetaDelGlobal().getByText('Vigente desde')).toBeOnTheScreen();
      // En UTC, como el panel: el global no es de ninguna plaza.
      expect(tarjetaDelGlobal().getByText('01/12/2026 12:00 UTC')).toBeOnTheScreen();
      expect(tarjetaDelGlobal().getByRole('button', { name: 'Fijar porcentaje' })).toBeOnTheScreen();
      expect(screen.getByRole('header', { name: 'Por lavandería' })).toBeOnTheScreen();
      // Todas, de todas las plazas, en el orden en que las da el servidor: por nombre.
      expect(nombresDeLaLista()).toEqual([DE_TORREON.nombre, DURANGO_CENTRO.nombre]);
      expect(textosDe(DURANGO_CENTRO)).toEqual([
        DURANGO_CENTRO.nombre,
        'Plaza de prueba Durango',
        'Propio: 7.25 % · le aplica el propio',
        'Fijar porcentaje propio',
        'Quitar porcentaje propio',
      ]);
      expect(textosDe(DE_TORREON)).toEqual([
        DE_TORREON.nombre,
        'Plaza de prueba Torreón',
        'Sin porcentaje propio · le aplica el global: 12.50 %',
        'Fijar porcentaje propio',
      ]);
      expect(enlace('Quitar porcentaje propio', DURANGO_CENTRO)).toBeOnTheScreen();
      expect(screen.queryByRole('link', { name: `Quitar porcentaje propio de ${DE_TORREON.nombre}` })).toBeNull();
      // Ningún campo abierto: nada se escribe hasta que la administración lo pide.
      expect(screen.queryByLabelText('Porcentaje')).toBeNull();
      expect(servidor().peticiones().filter(({ ruta }) => ruta.startsWith('/administracion'))).toEqual([
        expect.objectContaining({ metodo: 'GET', ruta: RUTA_DE_LAS_COMISIONES }),
      ]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E32 — (%s) sin porcentaje global guardado, la pantalla dice que no hay y no muestra ningún número del global',
    async (plataforma) => {
      guardarEnElServidor(SIN_GLOBAL);

      await abrirLasComisiones(plataforma);

      expect(tarjetaDelGlobal().getByText('Sin porcentaje')).toBeOnTheScreen();
      expect(tarjetaDelGlobal().queryByText('Vigente desde')).toBeNull();
      expect(tarjetaDelGlobal().queryAllByText(/\d/)).toEqual([]);
      expect(tarjetaDelGlobal().getByRole('button', { name: 'Fijar porcentaje' })).toBeOnTheScreen();
      expect(textosDe(DE_TORREON)).toContain('Sin porcentaje propio · no le aplica ninguno: falta el global');
      // La de propio sigue con el suyo.
      expect(textosDe(DURANGO_CENTRO)).toContain('Propio: 7.25 % · le aplica el propio');
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E32 — (%s) sin ningún porcentaje guardado, ni global ni propio, la pantalla no muestra ningún porcentaje',
    async (plataforma) => {
      guardarEnElServidor(SIN_NINGUNO);

      await abrirLasComisiones(plataforma);

      expect(tarjetaDelGlobal().getByText('Sin porcentaje')).toBeOnTheScreen();
      expect(screen.queryAllByText(/%/)).toEqual([]);
      for (const lavanderia of SIN_NINGUNO.lavanderias) {
        expect(textosDe(lavanderia)).toContain('Sin porcentaje propio · no le aplica ninguno: falta el global');
      }
      expect(screen.queryByRole('link', { name: /^Quitar porcentaje propio/ })).toBeNull();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E32 — (%s) sin ninguna lavandería, "Por lavandería" dice que no hay y la tarjeta del global sigue igual',
    async (plataforma) => {
      guardarEnElServidor({ global: GLOBAL, lavanderias: [] });

      await abrirLasComisiones(plataforma);

      expect(tarjetaDelGlobal().getByText('12.50 %')).toBeOnTheScreen();
      expect(tarjetaDelGlobal().getByText('01/12/2026 12:00 UTC')).toBeOnTheScreen();
      expect(tarjetaDelGlobal().getByRole('button', { name: 'Fijar porcentaje' })).toBeOnTheScreen();
      expect(screen.queryAllByTestId(/^comision-[0-9a-z]{26}$/)).toEqual([]);
      // El aviso va justo debajo del título "Por lavandería".
      expect(screen.getByRole('header', { name: 'Por lavandería' })).toBeOnTheScreen();
      const textos = screen.getAllByText(/.+/).map((texto) => texto.props.children as string);
      expect(textos.indexOf('No hay lavanderías.')).toBe(textos.indexOf('Por lavandería') + 1);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E32 — (%s) si las comisiones no llegan, la pantalla lo dice e "Intentar de nuevo" las vuelve a pedir',
    async (plataforma) => {
      servidor().interceptar = ({ ruta }) => {
        if (ruta === RUTA_DE_LAS_COMISIONES) {
          throw new TypeError('Network request failed');
        }
        return undefined;
      };
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LAS_COMISIONES);

      expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
      expect(screen.queryByRole('header', { name: 'Porcentaje global' })).toBeNull();

      servidor().interceptar = null;
      await fireEvent.press(screen.getByRole('button', { name: 'Intentar de nuevo' }));

      expect(await screen.findByRole('header', { name: 'Porcentaje global' })).toBeOnTheScreen();
      expect(screen.queryByText(MENSAJES.sinConexion)).toBeNull();
      expect(consultas(servidor())).toBe(2);
    },
  );
});

describe('administracion-app-E33: fijar el porcentaje global y el propio, y quitar el propio', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E33 — (%s) "Fijar porcentaje" abre el campo vacío, aunque haya uno vigente; al guardar, la app manda PUT comisiones/global y muestra el global nuevo y el que aplica a cada lavandería',
    async (plataforma) => {
      await abrirLasComisiones(plataforma);

      await fireEvent.press(screen.getByRole('button', { name: 'Fijar porcentaje' }));

      // La app no propone ningún número: los captura EL CLIENTE.
      expect(screen.getByLabelText('Porcentaje').props.value).toBe('');
      expect(tarjetaDelGlobal().queryByRole('button', { name: 'Fijar porcentaje' })).toBeNull();
      await escribirYGuardar('Porcentaje', '15');

      await waitFor(() => expect(tarjetaDelGlobal().getByText('15.00 %')).toBeOnTheScreen());
      expect(cambiosMandados(servidor())).toEqual([
        { metodo: 'PUT', ruta: `${RUTA_DE_LAS_COMISIONES}/global`, cuerpo: { porcentaje: '15' } },
      ]);
      expect(tarjetaDelGlobal().getByText('03/12/2026 18:45 UTC')).toBeOnTheScreen();
      expect(textosDe(DE_TORREON)).toContain('Sin porcentaje propio · le aplica el global: 15.00 %');
      // La de propio sigue con el suyo: el propio prevalece sobre el global.
      expect(textosDe(DURANGO_CENTRO)).toContain('Propio: 7.25 % · le aplica el propio');
      // El formulario se cierra y la pantalla no vuelve a consultar: la respuesta ya trae las comisiones nuevas.
      expect(screen.queryByLabelText('Porcentaje')).toBeNull();
      expect(tarjetaDelGlobal().getByRole('button', { name: 'Fijar porcentaje' })).toBeOnTheScreen();
      expect(consultas(servidor())).toBe(1);
      expect(comisiones().guardadas.global).toEqual({ porcentaje: '15.00', vigente_desde: AHORA });
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E33 — (%s) sin global, fijarlo lo pone y a la lavandería sin propio le empieza a aplicar',
    async (plataforma) => {
      guardarEnElServidor(SIN_GLOBAL);
      await abrirLasComisiones(plataforma);

      await fireEvent.press(screen.getByRole('button', { name: 'Fijar porcentaje' }));
      await escribirYGuardar('Porcentaje', '10.5');

      await waitFor(() => expect(tarjetaDelGlobal().getByText('10.50 %')).toBeOnTheScreen());
      expect(tarjetaDelGlobal().queryByText('Sin porcentaje')).toBeNull();
      expect(tarjetaDelGlobal().getByText('Vigente desde')).toBeOnTheScreen();
      expect(textosDe(DE_TORREON)).toContain('Sin porcentaje propio · le aplica el global: 10.50 %');
    },
  );

  test.each(PLATAFORMAS)('administracion-app-E33 — (%s) "Cancelar" cierra el campo sin mandar nada', async (plataforma) => {
    await abrirLasComisiones(plataforma);

    await fireEvent.press(screen.getByRole('button', { name: 'Fijar porcentaje' }));
    await fireEvent.changeText(screen.getByLabelText('Porcentaje'), '20');
    await fireEvent.press(screen.getByRole('button', { name: 'Cancelar' }));

    expect(screen.queryByLabelText('Porcentaje')).toBeNull();
    expect(tarjetaDelGlobal().getByText('12.50 %')).toBeOnTheScreen();
    expect(cambiosMandados(servidor())).toEqual([]);
    // Al abrirlo otra vez, empieza vacío.
    await fireEvent.press(screen.getByRole('button', { name: 'Fijar porcentaje' }));
    expect(screen.getByLabelText('Porcentaje').props.value).toBe('');
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E33 — (%s) "Fijar porcentaje propio" abre en su tarjeta lo que hace y el campo vacío; al guardar, la app manda PUT comisiones/lavanderias/{id} y la lavandería pasa a su propio',
    async (plataforma) => {
      await abrirLasComisiones(plataforma);

      await fireEvent.press(enlace('Fijar porcentaje propio', DE_TORREON));

      expect(textosDe(DE_TORREON)).toEqual([
        DE_TORREON.nombre,
        'Plaza de prueba Torreón',
        'Le aplica a esta lavandería en lugar del global. El anterior queda como historial.',
        'Porcentaje propio',
        'Guardar',
        'Cancelar',
      ]);
      expect(screen.getByLabelText('Porcentaje propio').props.value).toBe('');
      // Un formulario a la vez: el global sigue con su botón y la otra lavandería, con sus enlaces.
      expect(tarjetaDelGlobal().getByRole('button', { name: 'Fijar porcentaje' })).toBeOnTheScreen();
      expect(enlace('Fijar porcentaje propio', DURANGO_CENTRO)).toBeOnTheScreen();

      await escribirYGuardar('Porcentaje propio', '8.5');

      await waitFor(() => expect(textosDe(DE_TORREON)).toContain('Propio: 8.50 % · le aplica el propio'));
      expect(cambiosMandados(servidor())).toEqual([
        {
          metodo: 'PUT',
          ruta: `${RUTA_DE_LAS_COMISIONES}/lavanderias/${DE_TORREON.id_publico}`,
          cuerpo: { porcentaje: '8.5' },
        },
      ]);
      expect(enlace('Quitar porcentaje propio', DE_TORREON)).toBeOnTheScreen();
      expect(screen.queryByLabelText('Porcentaje propio')).toBeNull();
      // El global no cambia.
      expect(tarjetaDelGlobal().getByText('12.50 %')).toBeOnTheScreen();
      expect(comisiones().de(DE_TORREON.id_publico).propio).toBe('8.50');
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E33 — (%s) abrir el campo de otra tarjeta cierra el que estaba abierto, sin mandar nada',
    async (plataforma) => {
      await abrirLasComisiones(plataforma);

      await fireEvent.press(screen.getByRole('button', { name: 'Fijar porcentaje' }));
      await fireEvent.changeText(screen.getByLabelText('Porcentaje'), '30');
      await fireEvent.press(enlace('Fijar porcentaje propio', DURANGO_CENTRO));

      expect(screen.queryByLabelText('Porcentaje')).toBeNull();
      expect(screen.getByLabelText('Porcentaje propio').props.value).toBe('');
      expect(tarjetaDelGlobal().getByRole('button', { name: 'Fijar porcentaje' })).toBeOnTheScreen();
      expect(cambiosMandados(servidor())).toEqual([]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E33 — (%s) "Quitar porcentaje propio" pide confirmarlo con el texto del panel; al confirmar, la app manda DELETE y a la lavandería le vuelve a aplicar el global',
    async (plataforma) => {
      await abrirLasComisiones(plataforma);

      await fireEvent.press(enlace('Quitar porcentaje propio', DURANGO_CENTRO));

      const { titulo, texto, botones } = ultimoDialogo();
      expect(titulo).toBe('Quitar el porcentaje propio');
      expect(texto).toBe('A la lavandería le vuelve a aplicar el porcentaje global. El propio queda como historial.');
      expect(botones.map(({ text, style }) => [text, style])).toEqual([
        ['Cancelar', 'cancel'],
        ['Quitar', undefined],
      ]);
      // Hasta confirmar, nada sale.
      expect(cambiosMandados(servidor())).toEqual([]);

      await tocar(botones, 'Quitar');

      await waitFor(() =>
        expect(textosDe(DURANGO_CENTRO)).toContain('Sin porcentaje propio · le aplica el global: 12.50 %'),
      );
      expect(cambiosMandados(servidor())).toEqual([
        {
          metodo: 'DELETE',
          ruta: `${RUTA_DE_LAS_COMISIONES}/lavanderias/${DURANGO_CENTRO.id_publico}`,
          cuerpo: undefined,
        },
      ]);
      expect(screen.queryByRole('link', { name: `Quitar porcentaje propio de ${DURANGO_CENTRO.nombre}` })).toBeNull();
      expect(enlace('Fijar porcentaje propio', DURANGO_CENTRO)).toBeOnTheScreen();
      expect(consultas(servidor())).toBe(1);
      expect(comisiones().de(DURANGO_CENTRO.id_publico).propio).toBeNull();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E33 — (%s) si no confirma quitar el propio, no se manda nada y sigue con el suyo',
    async (plataforma) => {
      await abrirLasComisiones(plataforma);

      await fireEvent.press(enlace('Quitar porcentaje propio', DURANGO_CENTRO));
      await tocar(ultimoDialogo().botones, 'Cancelar');

      expect(cambiosMandados(servidor())).toEqual([]);
      expect(textosDe(DURANGO_CENTRO)).toContain('Propio: 7.25 % · le aplica el propio');
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E33 — (%s) si la lavandería ya no existe al quitar su propio, su tarjeta dice el mensaje del servidor',
    async (plataforma) => {
      await abrirLasComisiones(plataforma);
      // Otra sesión la borró mientras la pantalla estaba abierta.
      comisiones().guardadas.lavanderias.splice(
        comisiones().guardadas.lavanderias.findIndex(({ id_publico }) => id_publico === DURANGO_CENTRO.id_publico),
        1,
      );

      await fireEvent.press(enlace('Quitar porcentaje propio', DURANGO_CENTRO));
      await tocar(ultimoDialogo().botones, 'Quitar');

      expect(await tarjetaDe(DURANGO_CENTRO).findByText(NO_EXISTE)).toBeOnTheScreen();
      await expect(leerToken()).resolves.toBe(TOKEN_DE_LA_ADMINISTRACION);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E33 — (%s) con dos cambios en camino, la pantalla se queda con la respuesta del último que se mandó, aunque la del primero llegue después',
    async (plataforma) => {
      await abrirLasComisiones(plataforma);
      // El servidor fija el porcentaje en cuanto le llega, pero su respuesta tarda.
      let soltarLaRespuesta: () => void = () => {};
      servidor().interceptar = (peticion) => {
        if (peticion.metodo !== 'PUT') {
          return undefined;
        }
        const contestada = comisiones().manejador(peticion);
        return new Promise((resolver) => {
          soltarLaRespuesta = () => resolver(contestada);
        });
      };

      await fireEvent.press(enlace('Fijar porcentaje propio', DE_TORREON));
      await escribirYGuardar('Porcentaje propio', '8.5');
      await waitFor(() => expect(cambiosMandados(servidor())).toHaveLength(1));
      // Mientras se guarda, la administración quita el propio de otra lavandería, y esa respuesta llega primero, con los
      // dos cambios.
      await fireEvent.press(enlace('Quitar porcentaje propio', DURANGO_CENTRO));
      await tocar(ultimoDialogo().botones, 'Quitar');
      await waitFor(() =>
        expect(textosDe(DURANGO_CENTRO)).toContain('Sin porcentaje propio · le aplica el global: 12.50 %'),
      );

      // Llega la respuesta del porcentaje, que no trae el propio quitado: el formulario se cierra y la pantalla no la
      // pinta.
      await act(async () => {
        soltarLaRespuesta();
      });

      await waitFor(() => expect(screen.queryByLabelText('Porcentaje propio')).toBeNull());
      expect(textosDe(DURANGO_CENTRO)).toContain('Sin porcentaje propio · le aplica el global: 12.50 %');
      expect(textosDe(DE_TORREON)).toContain('Propio: 8.50 % · le aplica el propio');
      expect(cambiosMandados(servidor()).map(({ metodo }) => metodo)).toEqual(['PUT', 'DELETE']);
      expect(comisiones().de(DE_TORREON.id_publico).propio).toBe('8.50');
      expect(comisiones().de(DURANGO_CENTRO.id_publico).propio).toBeNull();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E33 — (%s) sin conexión al guardar, el campo sigue abierto con lo escrito y el mensaje de la app',
    async (plataforma) => {
      await abrirLasComisiones(plataforma);
      servidor().interceptar = ({ metodo }) => {
        if (metodo === 'PUT') {
          throw new TypeError('Network request failed');
        }
        return undefined;
      };

      await fireEvent.press(screen.getByRole('button', { name: 'Fijar porcentaje' }));
      await escribirYGuardar('Porcentaje', '15');

      expect(await tarjetaDelGlobal().findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
      expect(screen.getByLabelText('Porcentaje').props.value).toBe('15');
      expect(tarjetaDelGlobal().getByText('12.50 %')).toBeOnTheScreen();
    },
  );
});

describe('administracion-app-E34: un porcentaje fuera de rango', () => {
  test.each(PLATAFORMAS.flatMap((plataforma) => [[plataforma, '120'] as const, [plataforma, '-1'] as const]))(
    'administracion-app-E34 — (%s) el global %s: el servidor responde 422 con el mensaje de administracion-E13, la app lo muestra junto al campo y el global vigente no cambia',
    async (plataforma, porcentaje) => {
      await abrirLasComisiones(plataforma);

      await fireEvent.press(screen.getByRole('button', { name: 'Fijar porcentaje' }));
      await escribirYGuardar('Porcentaje', porcentaje);

      const campo = within(await screen.findByTestId('campo-porcentaje'));
      expect(await campo.findByRole('alert')).toHaveTextContent(MENSAJES_DEL_PORCENTAJE.between);
      expect(screen.getByLabelText('Porcentaje').props.value).toBe(porcentaje);
      // El mensaje va solo junto al campo, no repetido arriba.
      expect(screen.getAllByText(MENSAJES_DEL_PORCENTAJE.between)).toHaveLength(1);
      // El vigente sigue a la vista, igual, y en el servidor no cambió.
      expect(tarjetaDelGlobal().getByText('12.50 %')).toBeOnTheScreen();
      expect(tarjetaDelGlobal().getByText('01/12/2026 12:00 UTC')).toBeOnTheScreen();
      expect(textosDe(DE_TORREON)).toContain('Sin porcentaje propio · le aplica el global: 12.50 %');
      expect(comisiones().guardadas.global).toEqual(GLOBAL);
      expect(cambiosMandados(servidor())).toEqual([
        { metodo: 'PUT', ruta: `${RUTA_DE_LAS_COMISIONES}/global`, cuerpo: { porcentaje } },
      ]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E34 — (%s) el propio fuera de rango: el 422 va junto a su campo y, al cancelar, la lavandería sigue con el suyo',
    async (plataforma) => {
      await abrirLasComisiones(plataforma);

      await fireEvent.press(enlace('Fijar porcentaje propio', DURANGO_CENTRO));
      await escribirYGuardar('Porcentaje propio', '101');

      const campo = within(await screen.findByTestId('campo-porcentaje'));
      expect(await campo.findByRole('alert')).toHaveTextContent(MENSAJES_DEL_PORCENTAJE.between);
      expect(comisiones().de(DURANGO_CENTRO.id_publico).propio).toBe('7.25');

      await fireEvent.press(screen.getByRole('button', { name: 'Cancelar' }));

      expect(textosDe(DURANGO_CENTRO)).toContain('Propio: 7.25 % · le aplica el propio');
      expect(screen.queryByText(MENSAJES_DEL_PORCENTAJE.between)).toBeNull();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E34 — (%s) después del 422, con un porcentaje que vale, se guarda y el error se va',
    async (plataforma) => {
      await abrirLasComisiones(plataforma);

      await fireEvent.press(screen.getByRole('button', { name: 'Fijar porcentaje' }));
      await escribirYGuardar('Porcentaje', '120');
      await screen.findByText(MENSAJES_DEL_PORCENTAJE.between);

      await escribirYGuardar('Porcentaje', '100');

      await waitFor(() => expect(tarjetaDelGlobal().getByText('100.00 %')).toBeOnTheScreen());
      expect(screen.queryByText(MENSAJES_DEL_PORCENTAJE.between)).toBeNull();
      expect(screen.queryByLabelText('Porcentaje')).toBeNull();
    },
  );
});

describe('administracion-app-E35 (bloqueado por DD-019): la comisión de los socios repartidores solo tiene su lugar', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E35 — (%s) al final de la pantalla, en ámbar, "Por confirmar · DD-019", sin cifra ni botón',
    async (plataforma) => {
      await abrirLasComisiones(plataforma);

      const espera = within(screen.getByTestId('comision-de-los-repartidores'));
      expect(espera.getAllByText(/.+/).map((texto) => texto.props.children)).toEqual([
        'Comisión de los socios repartidores',
        'Por confirmar · DD-019',
      ]);
      expect(espera.queryAllByRole('button')).toEqual([]);
      expect(espera.queryAllByRole('link')).toEqual([]);
      expect(espera.queryAllByText(/%/)).toEqual([]);
    },
  );
});

describe('administracion-app-E05: la sesión vence o se revoca en la pantalla de comisiones', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con un 401 al pedir las comisiones, la app borra la sesión del teléfono y abre el inicio de sesión',
    async (plataforma) => {
      // El token se revoca entre la consulta de la cuenta y la de las comisiones.
      servidor().interceptar = ({ ruta }) =>
        ruta === RUTA_DE_LAS_COMISIONES ? respuesta(401, { message: 'Unauthenticated.' }) : undefined;

      await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LAS_COMISIONES);

      await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
      expect(await screen.findByLabelText('Contraseña')).toBeOnTheScreen();
      await expect(leerToken()).resolves.toBeNull();
      await expect(leerRol()).resolves.toBeNull();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el token vencido al guardar un porcentaje, la app borra la sesión y abre el inicio de sesión, sin cambiar nada',
    async (plataforma) => {
      await abrirLasComisiones(plataforma);
      servidor().vencerLaSesion();

      await fireEvent.press(screen.getByRole('button', { name: 'Fijar porcentaje' }));
      await escribirYGuardar('Porcentaje', '15');

      await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
      expect(await screen.findByLabelText('Contraseña')).toBeOnTheScreen();
      await expect(leerToken()).resolves.toBeNull();
      expect(comisiones().guardadas.global).toEqual(GLOBAL);
      // "Atrás" ya no vuelve a la pantalla del módulo.
      expect(screen.queryByRole('header', { name: 'Porcentaje global' })).toBeNull();
    },
  );
});

