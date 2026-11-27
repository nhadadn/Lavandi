// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "Gestión de
// lavanderías en la app": administracion-app-E13 (la lista y la ficha), E15 (suspender y levantar la suspensión) y E16
// (dar de baja), con E17 y E18 todavía en espera de DD-053 y DD-104; del requisito "Una sola fuente de cada regla", E12
// (el 404 y el 409); y del requisito "La administración inicia sesión en la app", E03 (la entrada a Lavanderías desde el
// inicio del módulo) y E05 (la sesión vence en estas pantallas). Tareas 4.1 y 4.2. El contrato: "Alta, baja, suspensión
// y edición de socios." (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1). Las reglas son las del panel, en
// EstadoDeLaLavanderia; las prueba el servidor (servidor/tests/Feature/AdministracionEnLaApp/LavanderiasTest.php). Aquí,
// que la app muestra lo que da el servidor, ofrece solo las transiciones que trae la respuesta y no decide nada.
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado de la
// administración (__tests__/apoyo/administracion.ts) y sus lavanderías (__tests__/apoyo/administracion/lavanderias.ts).
// La edición (E14) está en edicion-de-lavanderia.test.tsx.
import { act, fireEvent, screen, waitFor, within } from 'expo-router/testing-library';
import { Alert, type AlertButton } from 'react-native';

import { leerRol, leerToken } from '../../src/sesion/almacen';
import {
  abrirLaAppConLaAdministracion,
  PLATAFORMAS,
  respuesta,
  RUTA_DEL_INICIO,
  RUTAS_DE_LOS_CONTEOS,
  usarElServidorDeLaAdministracion,
  type Plataforma,
} from '../apoyo/administracion';
import {
  ACTIVA,
  DE_BAJA,
  fichaDe,
  LavanderiasDelServidor,
  NO_EXISTE_LA_LAVANDERIA,
  PENDIENTE,
  PLAZA_DURANGO,
  PLAZA_TORREON,
  RUTA_DE_LAS_LAVANDERIAS,
  rutaDe,
  SUSPENDIDA,
  transicionNoPermitida,
  type LavanderiaDelServidor,
} from '../apoyo/administracion/lavanderias';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaAdministracion('administracion-lavanderias');
let lavanderias: LavanderiasDelServidor;
let dialogo: jest.SpyInstance;

beforeEach(() => {
  lavanderias = new LavanderiasDelServidor(servidor());
  // Alert.alert puede ser ya una simulación de react-native que guarda las llamadas de todo el archivo.
  dialogo = jest.spyOn(Alert, 'alert').mockImplementation(() => {});
  dialogo.mockClear();
});

/** La ruta de la pantalla de la ficha de una lavandería en la app. */
function fichaEnLaApp(lavanderia: LavanderiaDelServidor | string): string {
  return rutaDe(lavanderia);
}

/** Abre la app con la sesión de la administración y, desde el inicio, Lavanderías, ya con su lista. */
async function abrirLaLista(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma);
  expect(await screen.findByRole('header', { name: 'Administración' })).toBeOnTheScreen();
  await fireEvent.press(screen.getByRole('link', { name: 'Lavanderías' }));
  await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LAS_LAVANDERIAS));
  await screen.findByTestId('lista-de-lavanderias');
}

/** La fila de una lavandería de la lista, por su nombre. */
function fila(lavanderia: LavanderiaDelServidor) {
  return screen.getByTestId(`lavanderia-${lavanderia.datos.id_publico}`);
}

/** Abre la ficha de esa lavandería desde la lista, ya con sus datos. */
async function abrirLaFicha(plataforma: Plataforma, lavanderia: LavanderiaDelServidor) {
  await abrirLaLista(plataforma);
  await fireEvent.press(fila(lavanderia));
  await waitFor(() => expect(screen).toHavePathname(fichaEnLaApp(lavanderia)));
  await screen.findByRole('header', { name: lavanderia.datos.nombre });
}

/** Los nombres de los botones fijos abajo de la ficha, en su orden. */
function botonesDeLaFicha(): string[] {
  return within(screen.getByTestId('acciones'))
    .queryAllByRole('button')
    .map((boton) => within(boton).queryAllByText(/.+/).map((texto) => texto.props.children).join(''));
}

/** Toca un botón de la ficha. */
async function tocar(nombre: string) {
  await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: nombre }));
}

/** El diálogo de confirmación que se abrió: su título, su texto y sus botones. */
function ultimoDialogo(): { titulo: string; texto: string | undefined; botones: AlertButton[] } {
  const [titulo, texto, botones] = dialogo.mock.calls[dialogo.mock.calls.length - 1] as [
    string,
    string | undefined,
    AlertButton[],
  ];
  return { titulo, texto, botones };
}

async function responder(texto: string) {
  const boton = ultimoDialogo().botones.find((opcion) => opcion.text === texto);
  expect(boton).toBeDefined();
  await act(async () => {
    boton?.onPress?.();
  });
}

/** La insignia del estado de la ficha dice ese estado. */
async function esperarElEstado(nombre: string) {
  await waitFor(() => expect(screen.getByTestId('estado-de-la-lavanderia')).toHaveTextContent(nombre));
}

describe('administracion-app-E03: la entrada a Lavanderías', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E03 — (%s) "Lavanderías" del inicio abre su pantalla dentro de la app, y "Regresar" vuelve al inicio',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma);
      expect(await screen.findByRole('header', { name: 'Administración' })).toBeOnTheScreen();

      await fireEvent.press(screen.getByRole('link', { name: 'Lavanderías' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LAS_LAVANDERIAS));
      expect(await screen.findByRole('header', { name: 'Lavanderías' })).toBeOnTheScreen();
      expect(await screen.findByTestId('lista-de-lavanderias')).toBeOnTheScreen();
      // Antes, el inicio pidió sus dos conteos (administracion-app-E03).
      expect(servidor().peticiones().map(({ metodo, ruta }) => `${metodo} ${ruta}`)).toEqual([
        'GET /cuenta',
        `GET ${RUTAS_DE_LOS_CONTEOS.repartidores}`,
        `GET ${RUTAS_DE_LOS_CONTEOS.monitoreo}`,
        `GET ${RUTA_DE_LAS_LAVANDERIAS}`,
      ]);

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_INICIO));
    },
  );
});

describe('administracion-app-E13: la lista y la ficha de las lavanderías', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E13 — (%s) ve todas, de las dos plazas y en cualquier estado, cada una con su nombre, su estado y su plaza',
    async (plataforma) => {
      await abrirLaLista(plataforma);

      // Por nombre, como las da el servidor.
      expect(
        within(screen.getByTestId('lista-de-lavanderias'))
          .getAllByRole('button')
          .map((boton) => boton.props.accessibilityLabel),
      ).toEqual([
        `Lavandería de prueba Durango Centro, Activa, ${PLAZA_DURANGO}`,
        `Lavandería de prueba Durango Norte, Pendiente, ${PLAZA_DURANGO}`,
        `Lavandería de prueba Durango Sur, Dada de baja, ${PLAZA_DURANGO}`,
        `Lavandería de prueba suspendida, Suspendida, ${PLAZA_TORREON}`,
      ]);
      expect(within(fila(SUSPENDIDA)).getByText('Lavandería de prueba suspendida')).toBeOnTheScreen();
      expect(within(fila(SUSPENDIDA)).getByTestId('estado-de-la-lavanderia')).toHaveTextContent('Suspendida');
      expect(within(fila(SUSPENDIDA)).getByText(PLAZA_TORREON)).toBeOnTheScreen();
      expect(within(fila(DE_BAJA)).getByTestId('estado-de-la-lavanderia')).toHaveTextContent('Dada de baja');
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E13 — (%s) la busca por nombre: el servidor recibe el texto y la lista trae solo las que coinciden',
    async (plataforma) => {
      await abrirLaLista(plataforma);

      await fireEvent.changeText(screen.getByLabelText('Buscar por nombre'), 'NORTE');

      await waitFor(() =>
        expect(within(screen.getByTestId('lista-de-lavanderias')).getAllByRole('button')).toHaveLength(1),
      );
      expect(fila(PENDIENTE)).toBeOnTheScreen();
      expect(lavanderias.pedidas()).toContain(`GET ${RUTA_DE_LAS_LAVANDERIAS}?buscar=NORTE`);

      await fireEvent.changeText(screen.getByLabelText('Buscar por nombre'), 'Ninguna así');
      // Texto provisional: la Propuesta 06 no dibuja la búsqueda sin coincidencias (src/administracion/lavanderias/
      // textos.ts). Lo de E13 aquí es que la búsqueda la hace el servidor y la lista se queda sin filas.
      expect(await screen.findByText('Ninguna lavandería tiene ese nombre.')).toBeOnTheScreen();
      expect(screen.queryByTestId('lista-de-lavanderias')).not.toBeOnTheScreen();
      expect(lavanderias.pedidas()).toContain(`GET ${RUTA_DE_LAS_LAVANDERIAS}?buscar=Ninguna%20as%C3%AD`);

      // Sin texto, otra vez todas.
      await fireEvent.changeText(screen.getByLabelText('Buscar por nombre'), '');
      await waitFor(() =>
        expect(within(screen.getByTestId('lista-de-lavanderias')).getAllByRole('button')).toHaveLength(4),
      );
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E13 — (%s) si la lista no llega, la pantalla lo dice y ofrece intentar de nuevo',
    async (plataforma) => {
      let caida = true;
      servidor().interceptar = ({ ruta }) => {
        if (caida && ruta === RUTA_DE_LAS_LAVANDERIAS) {
          throw new TypeError('Network request failed');
        }
        return undefined;
      };
      await abrirLaAppConLaAdministracion(plataforma);
      await fireEvent.press(await screen.findByRole('link', { name: 'Lavanderías' }));

      expect(await screen.findByRole('alert')).toHaveTextContent(
        'No se pudo conectar con el servidor. Revisa tu conexión e intenta de nuevo.',
      );
      caida = false;
      await fireEvent.press(screen.getByRole('button', { name: 'Intentar de nuevo' }));

      expect(await screen.findByTestId('lista-de-lavanderias')).toBeOnTheScreen();
      expect(screen.queryByRole('alert')).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E13 — (%s) al abrir una ve su ficha, con sus datos editables, su estado, su plaza y solo las acciones que su estado permite',
    async (plataforma) => {
      await abrirLaFicha(plataforma, ACTIVA);

      expect(screen.getByRole('header', { name: 'Lavandería' })).toBeOnTheScreen();
      expect(screen.getByTestId('estado-de-la-lavanderia')).toHaveTextContent('Activa');
      expect(screen.getByText(PLAZA_DURANGO)).toBeOnTheScreen();
      expect(screen.getByTestId('dato-direccion')).toHaveTextContent(
        'Dirección' + 'Calle de Prueba 100, Zona Centro, C. P. 34000, Durango, Durango',
      );
      expect(screen.getByTestId('dato-servicios')).toHaveTextContent(
        'Servicios y precios' + 'Lavado estándar: $18.50 por kilo',
      );
      expect(screen.getByTestId('dato-ventanas')).toHaveTextContent(
        'Ventanas de disponibilidad' + 'Lunes, de 09:00 a 11:00',
      );
      expect(screen.getByTestId('dato-descripcion-y-fotos')).toHaveTextContent(
        'Descripción y fotos' + 'Texto de prueba. · 2 fotos',
      );
      // "Editar" y solo las transiciones de una activa: suspender y dar de baja.
      expect(botonesDeLaFicha()).toEqual(['Editar', 'Suspender', 'Dar de baja']);
      expect(lavanderias.pedidas()).toEqual([`GET ${RUTA_DE_LAS_LAVANDERIAS}`, `GET ${rutaDe(ACTIVA)}`]);

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LAS_LAVANDERIAS));
    },
  );

  test('administracion-app-E13 — la ficha junta en un renglón los días seguidos con las mismas horas, como la imagen 07: "Lunes a sábado, de 09:00 a 18:00"', async () => {
    // Las ventanas de una hora de ese día, de una hora a otra, como las da el servidor.
    const horas = (dia: number, desde: number, hasta: number) =>
      Array.from({ length: hasta - desde }, (_, cual) => ({
        dia_semana: dia,
        hora_inicio: `${String(desde + cual).padStart(2, '0')}:00`,
        hora_fin: `${String(desde + cual + 1).padStart(2, '0')}:00`,
      }));
    lavanderias.de(ACTIVA.datos.id_publico).datos.ventanas = [
      ...[1, 2, 3, 4, 5, 6].flatMap((dia) => horas(dia, 9, 18)),
      ...horas(7, 10, 12),
      ...horas(7, 16, 18),
    ];

    await abrirLaFicha('android', ACTIVA);

    expect(within(screen.getByTestId('dato-ventanas')).getByText(/^Lunes/).props.children).toBe(
      'Lunes a sábado, de 09:00 a 18:00\nDomingo, de 10:00 a 12:00 y de 16:00 a 18:00',
    );
  });

  test('administracion-app-E13 — cada estado ofrece solo las acciones que trae la respuesta: pendiente, suspendida y de baja', async () => {
    await abrirLaFicha('ios', PENDIENTE);
    expect(screen.getByTestId('estado-de-la-lavanderia')).toHaveTextContent('Pendiente');
    expect(botonesDeLaFicha()).toEqual(['Editar', 'Suspender', 'Dar de baja']);

    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
    await fireEvent.press(await screen.findByTestId(`lavanderia-${SUSPENDIDA.datos.id_publico}`));
    await screen.findByRole('header', { name: SUSPENDIDA.datos.nombre });
    expect(screen.getByTestId('estado-de-la-lavanderia')).toHaveTextContent('Suspendida');
    expect(screen.getByText(PLAZA_TORREON)).toBeOnTheScreen();
    // La principal es levantar la suspensión; "Editar" y "Dar de baja", secundarias.
    expect(botonesDeLaFicha()).toEqual(['Levantar la suspensión', 'Editar', 'Dar de baja']);
    expect(
      screen.getByText(
        'Al levantar la suspensión, la lavandería recupera el estado que tenía: activa si ya tenía alta, pendiente si no.',
      ),
    ).toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
    await fireEvent.press(await screen.findByTestId(`lavanderia-${DE_BAJA.datos.id_publico}`));
    await screen.findByRole('header', { name: DE_BAJA.datos.nombre });
    expect(screen.getByTestId('estado-de-la-lavanderia')).toHaveTextContent('Dada de baja');
    // De baja, ninguna acción de estado: solo "Editar", como el panel.
    expect(botonesDeLaFicha()).toEqual(['Editar']);
    expect(
      screen.getByText('Ninguna acción de estado sale de la baja. Sus datos y sus pedidos pasados se quedan.'),
    ).toBeOnTheScreen();
  });

  test('administracion-app-E13 — la ficha no decide sus botones: ofrece las transiciones que trae la respuesta, aunque su estado diga otra cosa', async () => {
    servidor().interceptar = ({ metodo, ruta }) =>
      metodo === 'GET' && ruta === rutaDe(ACTIVA)
        ? respuesta(200, { lavanderia: { ...fichaDe(ACTIVA), transiciones: ['dar_de_baja'] } })
        : undefined;
    await abrirLaFicha('android', ACTIVA);

    expect(screen.getByTestId('estado-de-la-lavanderia')).toHaveTextContent('Activa');
    expect(botonesDeLaFicha()).toEqual(['Editar', 'Dar de baja']);
  });
});

describe('administracion-app-E15: suspender una lavandería y levantar la suspensión', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E15 — (%s) suspende una activa con su confirmación: queda suspendida con solo sus acciones; al levantar la suspensión vuelve a activa',
    async (plataforma) => {
      await abrirLaFicha(plataforma, ACTIVA);

      await tocar('Suspender');
      expect(ultimoDialogo().titulo).toBe('Suspender la lavandería');
      expect(ultimoDialogo().texto).toBe(
        'Mientras esté suspendida, no aparece a los usuarios ni recibe pedidos nuevos. Lo que pasa con sus pedidos en curso todavía no está definido.',
      );
      expect(ultimoDialogo().botones.map(({ text }) => text)).toEqual(['Cancelar', 'Suspender']);
      await responder('Suspender');

      await esperarElEstado('Suspendida');
      expect(lavanderias.envios(ACTIVA)).toEqual([
        { metodo: 'POST', ruta: `${rutaDe(ACTIVA)}/suspension`, multipart: false, cuerpo: undefined },
      ]);
      expect(lavanderias.de(ACTIVA.datos.id_publico).estado).toBe('suspendida');
      expect(botonesDeLaFicha()).toEqual(['Levantar la suspensión', 'Editar', 'Dar de baja']);

      await tocar('Levantar la suspensión');
      expect(ultimoDialogo().titulo).toBe('Levantar la suspensión');
      expect(ultimoDialogo().texto).toBe(
        'La lavandería recupera el estado que tenía: activa si ya tenía alta, pendiente si no.',
      );
      await responder('Levantar la suspensión');

      await esperarElEstado('Activa');
      expect(lavanderias.envios(ACTIVA).map(({ metodo, ruta }) => `${metodo} ${ruta}`)).toEqual([
        `POST ${rutaDe(ACTIVA)}/suspension`,
        `DELETE ${rutaDe(ACTIVA)}/suspension`,
      ]);
      expect(botonesDeLaFicha()).toEqual(['Editar', 'Suspender', 'Dar de baja']);
    },
  );

  test('administracion-app-E15 — una pendiente suspendida recupera su estado: vuelve a pendiente al levantar la suspensión', async () => {
    await abrirLaFicha('android', PENDIENTE);

    await tocar('Suspender');
    await responder('Suspender');
    await esperarElEstado('Suspendida');
    await tocar('Levantar la suspensión');
    await responder('Levantar la suspensión');

    await esperarElEstado('Pendiente');
    expect(botonesDeLaFicha()).toEqual(['Editar', 'Suspender', 'Dar de baja']);
  });

  test.each(PLATAFORMAS)('administracion-app-E15 — (%s) si no lo confirma, nada cambia', async (plataforma) => {
    await abrirLaFicha(plataforma, ACTIVA);

    await tocar('Suspender');
    await responder('Cancelar');

    expect(lavanderias.envios(ACTIVA)).toEqual([]);
    expect(screen.getByTestId('estado-de-la-lavanderia')).toHaveTextContent('Activa');
    expect(botonesDeLaFicha()).toEqual(['Editar', 'Suspender', 'Dar de baja']);
  });

  test('administracion-app-E15 — mientras se manda, los botones se bloquean: un doble toque manda un solo cambio', async () => {
    await abrirLaFicha('ios', ACTIVA);
    let soltar: () => void = () => {};
    servidor().interceptar = ({ metodo, ruta }) => {
      if (metodo !== 'POST' || ruta !== `${rutaDe(ACTIVA)}/suspension`) {
        return undefined;
      }
      return new Promise((resolver) => {
        const suspendida = { ...ACTIVA, estado: 'suspendida' as const };
        soltar = () => resolver(respuesta(200, { lavanderia: fichaDe(suspendida) }));
      });
    };

    await tocar('Suspender');
    const suspender = ultimoDialogo().botones.find(({ text }) => text === 'Suspender');
    await act(async () => {
      suspender?.onPress?.();
      suspender?.onPress?.();
    });

    expect(lavanderias.envios(ACTIVA)).toHaveLength(1);
    for (const boton of within(screen.getByTestId('acciones')).getAllByRole('button')) {
      expect(boton).toBeDisabled();
    }
    await act(async () => {
      soltar();
    });
    await esperarElEstado('Suspendida');
    expect(lavanderias.envios(ACTIVA)).toHaveLength(1);
  });

  test('administracion-app-E15 — una consulta que salió antes de suspender y llega después no regresa la ficha a su estado viejo', async () => {
    await abrirLaFicha('android', ACTIVA);
    await tocar('Editar');
    await screen.findByLabelText('Nombre');
    // La consulta que la ficha hace al volver a verse se queda en camino, con la lavandería todavía activa.
    const antes = servidor().interceptar;
    let soltar: () => void = () => {};
    servidor().interceptar = (peticion) => {
      if (peticion.metodo !== 'GET' || peticion.ruta !== rutaDe(ACTIVA)) {
        return antes?.(peticion);
      }
      const activa = fichaDe(lavanderias.de(ACTIVA.datos.id_publico));
      return new Promise((resolver) => {
        soltar = () => resolver(respuesta(200, { lavanderia: activa }));
      });
    };
    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
    await waitFor(() => expect(lavanderias.pedidas()).toHaveLength(4));

    await tocar('Suspender');
    await responder('Suspender');
    await esperarElEstado('Suspendida');
    await act(async () => {
      soltar();
    });

    expect(screen.getByTestId('estado-de-la-lavanderia')).toHaveTextContent('Suspendida');
    expect(botonesDeLaFicha()).toEqual(['Levantar la suspensión', 'Editar', 'Dar de baja']);
  });
});

describe('administracion-app-E16: dar de baja una lavandería', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E16 — (%s) la confirmación dice el texto del panel con "desde la app"; queda de baja y su ficha ya no ofrece ninguna acción de estado',
    async (plataforma) => {
      await abrirLaFicha(plataforma, ACTIVA);

      await tocar('Dar de baja');
      expect(ultimoDialogo().titulo).toBe('Dar de baja la lavandería');
      expect(ultimoDialogo().texto).toBe(
        'Deja de aparecer a los usuarios y de recibir pedidos. Sus datos y sus pedidos pasados se quedan en la base. La baja no se deshace desde la app.',
      );
      expect(ultimoDialogo().botones.map(({ text }) => text)).toEqual(['Cancelar', 'Dar de baja']);
      await responder('Dar de baja');

      await esperarElEstado('Dada de baja');
      expect(lavanderias.envios(ACTIVA).map(({ metodo, ruta }) => `${metodo} ${ruta}`)).toEqual([
        `POST ${rutaDe(ACTIVA)}/baja`,
      ]);
      expect(lavanderias.de(ACTIVA.datos.id_publico).estado).toBe('baja');
      // Sus datos se quedan, y ninguna acción sale de la baja: solo "Editar".
      expect(screen.getByRole('header', { name: ACTIVA.datos.nombre })).toBeOnTheScreen();
      expect(screen.getByTestId('dato-direccion')).toHaveTextContent(/Calle de Prueba 100, Zona Centro/);
      expect(botonesDeLaFicha()).toEqual(['Editar']);
      expect(screen.queryByTestId('pedidos-en-curso')).not.toBeOnTheScreen();
    },
  );

  test('administracion-app-E16 — una suspendida también se da de baja', async () => {
    await abrirLaFicha('android', SUSPENDIDA);

    await tocar('Dar de baja');
    await responder('Dar de baja');

    await esperarElEstado('Dada de baja');
    expect(botonesDeLaFicha()).toEqual(['Editar']);
  });
});

describe('administracion-app-E12: un registro que no existe y una acción que ya no se puede', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E12 — (%s) otra sesión la dio de baja: al suspender, el 409 trae el mensaje tal cual arriba de la ficha, que se vuelve a pedir y ya no ofrece acciones de estado',
    async (plataforma) => {
      await abrirLaFicha(plataforma, ACTIVA);
      // Otra sesión la da de baja mientras la ficha está abierta.
      lavanderias.de(ACTIVA.datos.id_publico).estado = 'baja';

      await tocar('Suspender');
      await responder('Suspender');

      const mensaje = transicionNoPermitida('baja', 'suspender');
      expect(mensaje).toBe('Una lavandería en estado baja no se puede suspender.');
      expect(await screen.findByText(mensaje)).toBeOnTheScreen();
      await esperarElEstado('Dada de baja');
      expect(lavanderias.pedidas()).toEqual([
        `GET ${RUTA_DE_LAS_LAVANDERIAS}`,
        `GET ${rutaDe(ACTIVA)}`,
        `POST ${rutaDe(ACTIVA)}/suspension`,
        `GET ${rutaDe(ACTIVA)}`,
      ]);
      expect(botonesDeLaFicha()).toEqual(['Editar']);
      expect(lavanderias.de(ACTIVA.datos.id_publico).estado).toBe('baja');
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E12 — (%s) una lavandería que no existe: 404, la pantalla dice el mensaje del servidor y no ofrece ninguna acción',
    async (plataforma) => {
      const inexistente = '01jd8x6m3v2c9q4w5e7r8t0no1';
      await abrirLaAppConLaAdministracion(plataforma, fichaEnLaApp(inexistente));

      expect(await screen.findByText(NO_EXISTE_LA_LAVANDERIA)).toBeOnTheScreen();
      expect(lavanderias.pedidas()).toEqual([`GET ${rutaDe(inexistente)}`]);
      expect(screen.queryByTestId('acciones')).not.toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Intentar de nuevo' })).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E12 — (%s) si la lavandería ya no existe al suspender, el 404 dice el mensaje del servidor una sola vez y la ficha, pedida otra vez, no ofrece acciones',
    async (plataforma) => {
      await abrirLaFicha(plataforma, ACTIVA);
      lavanderias.lavanderias = lavanderias.lavanderias.filter(
        ({ datos }) => datos.id_publico !== ACTIVA.datos.id_publico,
      );

      await tocar('Suspender');
      await responder('Suspender');

      await waitFor(() => expect(screen.queryByTestId('acciones')).not.toBeOnTheScreen());
      expect(screen.getAllByText(NO_EXISTE_LA_LAVANDERIA)).toHaveLength(1);
      expect(lavanderias.pedidas().slice(-2)).toEqual([`POST ${rutaDe(ACTIVA)}/suspension`, `GET ${rutaDe(ACTIVA)}`]);
    },
  );

  test('administracion-app-E12 — el mensaje del 409 se va al volver a la ficha desde la edición, que se pide otra vez', async () => {
    await abrirLaFicha('ios', ACTIVA);
    lavanderias.de(ACTIVA.datos.id_publico).estado = 'baja';
    await tocar('Suspender');
    await responder('Suspender');
    const mensaje = transicionNoPermitida('baja', 'suspender');
    expect(await screen.findByText(mensaje)).toBeOnTheScreen();

    await tocar('Editar');
    await screen.findByLabelText('Nombre');
    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname(rutaDe(ACTIVA)));
    await waitFor(() => expect(screen.queryByText(mensaje)).not.toBeOnTheScreen());
    expect(lavanderias.pedidas().slice(-1)).toEqual([`GET ${rutaDe(ACTIVA)}`]);
    expect(botonesDeLaFicha()).toEqual(['Editar']);
  });
});

describe('Lo que la Propuesta 06 no dibuja (textos provisionales, sin escenario)', () => {
  // La lista sin ninguna lavandería no está en la Propuesta 06 ni en un escenario: su texto es nuevo de la app y espera
  // que Arthur lo apruebe en la spec o en una adenda de los mocks (src/administracion/lavanderias/textos.ts).
  test('sin ninguna lavandería, la lista lo dice con su texto provisional', async () => {
    lavanderias.lavanderias = [];
    await abrirLaAppConLaAdministracion('android');
    await fireEvent.press(await screen.findByRole('link', { name: 'Lavanderías' }));

    expect(await screen.findByText('Todavía no hay lavanderías.')).toBeOnTheScreen();
  });
});

describe('administracion-app-E17 (en espera de DD-053): el alta de una lavandería', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E17 — (%s) mientras DD-053 no responda, la lista lo dice en ámbar con su DD y ni la lista ni la ficha ofrecen el alta',
    async (plataforma) => {
      await abrirLaLista(plataforma);

      const alta = screen.getByTestId('alta-en-espera');
      expect(within(alta).getByText('Dar de alta una lavandería')).toBeOnTheScreen();
      expect(within(alta).getByText('Por confirmar · DD-053')).toBeOnTheScreen();
      expect(within(alta).getByText('La lista y las fichas no ofrecen el alta.')).toBeOnTheScreen();
      expect(within(alta).queryByRole('button')).not.toBeOnTheScreen();

      await fireEvent.press(fila(PENDIENTE));
      await screen.findByRole('header', { name: PENDIENTE.datos.nombre });
      expect(screen.queryByRole('button', { name: /alta/i })).not.toBeOnTheScreen();
    },
  );
});

describe('administracion-app-E18 (en espera de DD-104): los pedidos en curso al suspender o dar de baja', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E18 — (%s) la ficha lo marca en ámbar sobre los botones, con su DD, y la confirmación no dice qué pasa con ellos',
    async (plataforma) => {
      await abrirLaFicha(plataforma, ACTIVA);

      const enEspera = within(screen.getByTestId('acciones')).getByTestId('pedidos-en-curso');
      expect(within(enEspera).getByText('Pedidos en curso al suspender o dar de baja')).toBeOnTheScreen();
      expect(within(enEspera).getByText('Por confirmar · DD-104')).toBeOnTheScreen();

      await tocar('Suspender');
      expect(ultimoDialogo().texto).not.toMatch(/DD-104/);
      await responder('Cancelar');
      await tocar('Dar de baja');
      expect(ultimoDialogo().texto).not.toMatch(/DD-104|pedidos en curso/);
      await responder('Cancelar');
    },
  );

  test('administracion-app-E18 — suspendida, solo al dar de baja; pendiente, sin pedidos que esperen, no lo marca', async () => {
    await abrirLaFicha('ios', SUSPENDIDA);
    expect(within(screen.getByTestId('pedidos-en-curso')).getByText('Pedidos en curso al dar de baja')).toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
    await fireEvent.press(await screen.findByTestId(`lavanderia-${PENDIENTE.datos.id_publico}`));
    await screen.findByRole('header', { name: PENDIENTE.datos.nombre });
    expect(screen.queryByTestId('pedidos-en-curso')).not.toBeOnTheScreen();
  });
});

describe('administracion-app-E05: la sesión vence en las pantallas de lavanderías', () => {
  async function esperarElInicioDeSesion() {
    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    expect(await screen.findByLabelText('Contraseña')).toBeOnTheScreen();
    await expect(leerToken()).resolves.toBeNull();
    await expect(leerRol()).resolves.toBeNull();
  }

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el 401 de la lista, la app borra la sesión del teléfono y abre el inicio de sesión',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma);
      await screen.findByRole('header', { name: 'Administración' });
      servidor().vencerLaSesion();

      await fireEvent.press(screen.getByRole('link', { name: 'Lavanderías' }));

      await esperarElInicioDeSesion();
      expect(lavanderias.pedidas()).toEqual([`GET ${RUTA_DE_LAS_LAVANDERIAS}`]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el 401 de la ficha, la app borra la sesión del teléfono y abre el inicio de sesión',
    async (plataforma) => {
      await abrirLaLista(plataforma);
      servidor().vencerLaSesion();

      await fireEvent.press(fila(ACTIVA));

      await esperarElInicioDeSesion();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) si la sesión vence antes de suspender, nada cambia y la app abre el inicio de sesión',
    async (plataforma) => {
      await abrirLaFicha(plataforma, ACTIVA);
      servidor().vencerLaSesion();

      await tocar('Suspender');
      await responder('Suspender');

      await esperarElInicioDeSesion();
      expect(lavanderias.de(ACTIVA.datos.id_publico).estado).toBe('activa');
    },
  );
});
