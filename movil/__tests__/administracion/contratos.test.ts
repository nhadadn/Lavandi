// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, administracion-app-E11 ("La API
// y el panel dan lo mismo") y el escenario de cada ruta; design.md, sección 6, "Los contratos con la app". El contrato
// de cada ruta está en design.md, sección 4.
//
// Las pruebas de las pantallas abren la app contra un servidor simulado (__tests__/apoyo/administracion/), con
// respuestas escritas a mano. Si esas respuestas no tienen la forma de las del servidor real, las pruebas pasan y la app
// real dice "Algo salió mal". Esta prueba lo impide con los contratos que escribe el servidor
// (servidor/tests/Contratos/administracion/, una respuesta real por ruta y por estado; __tests__/apoyo/contratos.ts):
//
// 1. Cada respuesta real pasa por el cliente de la administración (pedirConElToken(), el mismo de useApiDeLaAdministracion)
//    y por la función de la app que la lee: la que tiene datos tiene que leerse sin falla; la de un 404, un 409, un 422
//    o un 503, como la falla que la pantalla muestra, con el campo de su error. Así, si la app exige un campo que el
//    servidor no manda, o el servidor deja de mandar uno que la app lee, esta prueba falla aquí y no en el teléfono.
// 2. La app hace la petición que dice el contrato: el mismo método y la misma ruta.
// 3. La misma petición, al servidor simulado, responde con el mismo código y la misma forma que la real: las mismas
//    llaves en cada nivel y los mismos tipos (null aparte). Así, el simulado no se aparta del real.
// 4. Cada contrato tiene su caso aquí y cada caso su contrato: una ruta o un estado nuevo en el servidor pide su caso.
//
// Si el servidor cambia una respuesta, su prueba falla (tests/Feature/AdministracionEnLaApp/, ContratosDeLaApp) hasta que
// se reescribe el contrato con CONTRATOS_ESCRIBIR=1; entonces esta prueba dice si la app y su simulado lo siguen.
import { pedirConElToken, type PedirALaAdministracion } from '../../src/administracion/api';
import { consultarAvisos, mandarAviso } from '../../src/administracion/avisos/api';
import {
  consultarComisiones,
  fijarElGlobal,
  fijarElPropio,
  quitarElPropio,
} from '../../src/administracion/comisiones/api';
import { consultarLaDescarga, consultarLasDescargas, pedirLaDescarga } from '../../src/administracion/descargas/api';
import { consultarIndicadores, type Categoria } from '../../src/administracion/indicadores/api';
import {
  cambiarElEstado,
  consultarLavanderia,
  consultarLavanderias,
  editarLavanderia,
} from '../../src/administracion/lavanderias/api';
import { consultarElMonitoreo } from '../../src/administracion/monitoreo/api';
import {
  darDeAltaAlRepartidor,
  darDeBajaAlRepartidor,
  consultarPlazas,
  consultarRepartidor,
  consultarRepartidores,
  editarRepartidor,
  levantarLaSuspensionDelRepartidor,
  suspenderAlRepartidor,
} from '../../src/administracion/repartidores/api';
import { conciliarPeriodo, consultarIngresos } from '../../src/administracion/reportes-financieros/api';
import {
  bloquearAlUsuario,
  consultarUsuario,
  consultarUsuarios,
  levantarElBloqueo,
} from '../../src/administracion/usuarios/api';
import {
  respuesta,
  ServidorDeLaAdministracion,
  TOKEN_DE_LA_ADMINISTRACION,
  usarElServidorDeLaAdministracion,
} from '../apoyo/administracion';
import { AVISOS, AvisosDelServidor } from '../apoyo/administracion/avisos';
import {
  CON_GLOBAL,
  ComisionesDelServidor,
  DE_TORREON,
  DURANGO_CENTRO,
  SIN_NINGUNO,
} from '../apoyo/administracion/comisiones';
import { descarga, DescargasDelServidor } from '../apoyo/administracion/descargas';
import {
  IndicadoresDelServidor,
  sociosCon,
  USUARIOS_DEL_MES_EN_CURSO,
} from '../apoyo/administracion/indicadores';
import { ACTIVA, LavanderiasDelServidor, SUSPENDIDA } from '../apoyo/administracion/lavanderias';
import { CONFIRMADO, EN_PROCESO, ENTREGADO, PedidosDelMonitoreo } from '../apoyo/administracion/monitoreo';
import {
  ACTIVO,
  PENDIENTE,
  QUE_NO_EXISTE,
  REPARTIDORES,
  RepartidoresDelServidor,
  SUSPENDIDO,
  type RepartidorGuardado,
} from '../apoyo/administracion/repartidores';
import { MARZO, MARZO_POR_SEMANA, ReportesFinancierosDelServidor } from '../apoyo/administracion/reportes-financieros';
import { agregarLosUsuarios, ANA, BETO } from '../apoyo/administracion/usuarios';
import {
  diferenciasDeForma,
  leerElContrato,
  nombresDeLosContratos,
  patronDeLaPeticion,
  type Contrato,
} from '../apoyo/contratos';

/** Un ULID que no es de ningún registro del servidor simulado. */
const NINGUNO = '01jd8x6m3v2c9q4w5e7r8t0zzz';

/** La descarga lista del servidor simulado. */
const LISTA = '01JDDESCARGALISTA000000001';

const SEMANA = { desde: '2026-11-30', hasta: '2026-12-06' };

/** Una ruta y un estado del servidor, con lo que la app hace con su respuesta. */
interface Caso {
  /** El escenario de la ruta, el que nombra la prueba. */
  escenario: string;
  /** Lo que hace la app: pide la ruta con el cliente que recibe y lee la respuesta con su función leer…. */
  leer: (pedir: PedirALaAdministracion) => Promise<unknown>;
  /** En un 422, el campo cuyo error la pantalla muestra junto a él. */
  campo?: string;
  /** La falla con que la app reconoce una respuesta, si no es la de su código, como la pasarela que no responde. */
  motivo?: string;
  /**
   * Prepara el servidor simulado para la misma petición. null si esa respuesta no la da el simulado, sino cada prueba a
   * mano con su interceptor: los 422 de las validaciones de la edición, que prueba el servidor.
   */
  simulado: ((servidor: ServidorDeLaAdministracion) => void) | null;
}

function lavanderias(servidor: ServidorDeLaAdministracion) {
  new LavanderiasDelServidor(servidor);
}

function repartidores(iniciales: RepartidorGuardado[] = REPARTIDORES) {
  return (servidor: ServidorDeLaAdministracion) => servidor.agregar(new RepartidoresDelServidor(iniciales).manejador);
}

/** Los repartidores sin foto: así llegan los de la fábrica del servidor en sus contratos de los cambios de estado. */
const SIN_FOTO = REPARTIDORES.map((repartidor) => ({ ...repartidor, foto: null }));

function comisiones(iniciales = CON_GLOBAL) {
  return (servidor: ServidorDeLaAdministracion) => servidor.agregar(new ComisionesDelServidor(iniciales).manejador);
}

function reportes(pasarelaResponde = true) {
  return (servidor: ServidorDeLaAdministracion) => {
    const delServidor = new ReportesFinancierosDelServidor();
    delServidor.pasarelaResponde = pasarelaResponde;
    servidor.agregar(delServidor.manejador);
  };
}

function avisos(iniciales = AVISOS) {
  return (servidor: ServidorDeLaAdministracion) => servidor.agregar(new AvisosDelServidor(iniciales).manejador);
}

function indicadores(cambiar?: (delServidor: IndicadoresDelServidor) => void) {
  return (servidor: ServidorDeLaAdministracion) => {
    const delServidor = new IndicadoresDelServidor();
    cambiar?.(delServidor);
    servidor.agregar(delServidor.manejador);
  };
}

function descargas(conUna: boolean) {
  return (servidor: ServidorDeLaAdministracion) => {
    const delServidor = new DescargasDelServidor(servidor);
    delServidor.descargas = conUna ? [descarga({ id_publico: LISTA, reporte: 'pedidos-totales', ...SEMANA })] : [];
  };
}


/** La marca de las fotos leídas solo viaja cuando la edición cambia las fotos (api.ts); estas ediciones no las cambian. */
const SIN_FOTOS = '';
const CASOS: Record<string, Caso> = {
  // F-D01, la gestión de lavanderías.
  'lavanderias.lista': {
    escenario: 'administracion-app-E13',
    leer: (pedir) => consultarLavanderias(pedir),
    simulado: lavanderias,
  },
  'lavanderias.lista-vacia': {
    escenario: 'administracion-app-E13',
    leer: (pedir) => consultarLavanderias(pedir, 'Sin coincidencia'),
    simulado: lavanderias,
  },
  'lavanderias.ficha': {
    escenario: 'administracion-app-E13',
    leer: (pedir) => consultarLavanderia(pedir, ACTIVA.datos.id_publico),
    simulado: lavanderias,
  },
  'lavanderias.no-existe': {
    escenario: 'administracion-app-E12',
    leer: (pedir) => consultarLavanderia(pedir, NINGUNO),
    simulado: lavanderias,
  },
  'lavanderias.edicion': {
    escenario: 'administracion-app-E14',
    leer: (pedir) => editarLavanderia(pedir, ACTIVA.datos.id_publico, { nombre: 'Lavandería de prueba Editada' }, SIN_FOTOS),
    simulado: lavanderias,
  },
  'lavanderias.edicion-invalida': {
    escenario: 'administracion-app-E14',
    leer: (pedir) => editarLavanderia(pedir, ACTIVA.datos.id_publico, { nombre: '' }, SIN_FOTOS),
    campo: 'nombre',
    simulado: null,
  },
  'lavanderias.suspension': {
    escenario: 'administracion-app-E15',
    leer: (pedir) => cambiarElEstado(pedir, ACTIVA.datos.id_publico, 'suspender'),
    simulado: lavanderias,
  },
  'lavanderias.suspension-ya-no-se-puede': {
    escenario: 'administracion-app-E12',
    leer: (pedir) => cambiarElEstado(pedir, SUSPENDIDA.datos.id_publico, 'suspender'),
    simulado: lavanderias,
  },
  'lavanderias.levantar-suspension': {
    escenario: 'administracion-app-E15',
    leer: (pedir) => cambiarElEstado(pedir, SUSPENDIDA.datos.id_publico, 'levantar_suspension'),
    simulado: lavanderias,
  },
  'lavanderias.baja': {
    escenario: 'administracion-app-E16',
    leer: (pedir) => cambiarElEstado(pedir, ACTIVA.datos.id_publico, 'dar_de_baja'),
    simulado: lavanderias,
  },

  // F-D02, la gestión de repartidores.
  'repartidores.lista': {
    escenario: 'administracion-app-E19',
    leer: (pedir) => consultarRepartidores(pedir),
    simulado: repartidores(),
  },
  'repartidores.lista-vacia': {
    escenario: 'administracion-app-E19',
    leer: (pedir) => consultarRepartidores(pedir),
    simulado: repartidores([]),
  },
  'repartidores.ficha': {
    escenario: 'administracion-app-E19',
    leer: (pedir) => consultarRepartidor(pedir, ACTIVO.id_publico),
    simulado: repartidores(),
  },
  'repartidores.ficha-pendiente': {
    escenario: 'administracion-app-E19',
    leer: (pedir) => consultarRepartidor(pedir, PENDIENTE.id_publico),
    simulado: repartidores(),
  },
  'repartidores.no-existe': {
    escenario: 'administracion-app-E12',
    leer: (pedir) => consultarRepartidor(pedir, QUE_NO_EXISTE),
    simulado: repartidores(),
  },
  'repartidores.edicion': {
    escenario: 'administracion-app-E23',
    leer: (pedir) => editarRepartidor(pedir, ACTIVO.id_publico, { nombre: 'Repartidor de prueba editado' }),
    simulado: repartidores(),
  },
  'repartidores.edicion-invalida': {
    escenario: 'administracion-app-E23',
    leer: (pedir) => editarRepartidor(pedir, ACTIVO.id_publico, { nombre: '' }),
    campo: 'nombre',
    simulado: null,
  },
  'repartidores.alta': {
    escenario: 'administracion-app-E20',
    leer: (pedir) => darDeAltaAlRepartidor(pedir, PENDIENTE.id_publico, 'Plaza de prueba Durango'),
    simulado: repartidores(),
  },
  'repartidores.alta-sin-plaza': {
    escenario: 'administracion-app-E20',
    leer: (pedir) => darDeAltaAlRepartidor(pedir, PENDIENTE.id_publico, ''),
    campo: 'plaza',
    simulado: repartidores(),
  },
  'repartidores.alta-ya-no-se-puede': {
    escenario: 'administracion-app-E12',
    leer: (pedir) => darDeAltaAlRepartidor(pedir, ACTIVO.id_publico, 'Plaza de prueba Durango'),
    simulado: repartidores(),
  },
  'repartidores.suspension': {
    escenario: 'administracion-app-E21',
    leer: (pedir) => suspenderAlRepartidor(pedir, ACTIVO.id_publico),
    simulado: repartidores(SIN_FOTO),
  },
  'repartidores.suspension-ya-no-se-puede': {
    escenario: 'administracion-app-E12',
    leer: (pedir) => suspenderAlRepartidor(pedir, SUSPENDIDO.id_publico),
    simulado: repartidores(),
  },
  'repartidores.levantar-suspension': {
    escenario: 'administracion-app-E21',
    leer: (pedir) => levantarLaSuspensionDelRepartidor(pedir, SUSPENDIDO.id_publico),
    simulado: repartidores(SIN_FOTO),
  },
  'repartidores.baja': {
    escenario: 'administracion-app-E22',
    leer: (pedir) => darDeBajaAlRepartidor(pedir, ACTIVO.id_publico),
    simulado: repartidores(SIN_FOTO),
  },
  'plazas.lista': {
    escenario: 'administracion-app-E20',
    leer: (pedir) => consultarPlazas(pedir),
    simulado: repartidores(),
  },

  // F-D03, la gestión de usuarios.
  'usuarios.lista': {
    escenario: 'administracion-app-E24',
    leer: (pedir) => consultarUsuarios(pedir, { buscar: '', pagina: 1 }),
    simulado: (servidor) => agregarLosUsuarios(servidor),
  },
  'usuarios.lista-vacia': {
    escenario: 'administracion-app-E24',
    leer: (pedir) => consultarUsuarios(pedir, { buscar: 'Sin coincidencia', pagina: 1 }),
    simulado: (servidor) => agregarLosUsuarios(servidor),
  },
  'usuarios.ficha': {
    escenario: 'administracion-app-E24',
    leer: (pedir) => consultarUsuario(pedir, ANA.id_publico),
    simulado: (servidor) => agregarLosUsuarios(servidor),
  },
  'usuarios.no-existe': {
    escenario: 'administracion-app-E12',
    leer: (pedir) => consultarUsuario(pedir, NINGUNO),
    simulado: (servidor) => agregarLosUsuarios(servidor),
  },
  'usuarios.bloqueo': {
    escenario: 'administracion-app-E25',
    leer: (pedir) => bloquearAlUsuario(pedir, ANA.id_publico),
    simulado: (servidor) => agregarLosUsuarios(servidor),
  },
  'usuarios.levantar-bloqueo': {
    escenario: 'administracion-app-E25',
    leer: (pedir) => levantarElBloqueo(pedir, BETO.id_publico),
    simulado: (servidor) => agregarLosUsuarios(servidor),
  },

  // F-D04, el monitoreo.
  monitoreo: {
    escenario: 'administracion-app-E28',
    leer: (pedir) => consultarElMonitoreo(pedir),
    simulado: (servidor) => servidor.agregar(new PedidosDelMonitoreo([EN_PROCESO, CONFIRMADO, ENTREGADO]).manejador),
  },
  'monitoreo.vacio': {
    escenario: 'administracion-app-E28',
    leer: (pedir) => consultarElMonitoreo(pedir),
    simulado: (servidor) => servidor.agregar(new PedidosDelMonitoreo([]).manejador),
  },

  // F-D05, las comisiones.
  comisiones: {
    escenario: 'administracion-app-E32',
    leer: (pedir) => consultarComisiones(pedir),
    simulado: comisiones(),
  },
  'comisiones.sin-global': {
    escenario: 'administracion-app-E32',
    leer: (pedir) => consultarComisiones(pedir),
    simulado: comisiones(SIN_NINGUNO),
  },
  'comisiones.global': {
    escenario: 'administracion-app-E33',
    leer: (pedir) => fijarElGlobal(pedir, '12.5'),
    simulado: comisiones(),
  },
  'comisiones.global-invalido': {
    escenario: 'administracion-app-E34',
    leer: (pedir) => fijarElGlobal(pedir, '120'),
    campo: 'porcentaje',
    simulado: comisiones(),
  },
  'comisiones.propio': {
    escenario: 'administracion-app-E33',
    leer: (pedir) => fijarElPropio(pedir, DE_TORREON.id_publico, '7.25'),
    simulado: comisiones(),
  },
  'comisiones.quitar-propio': {
    escenario: 'administracion-app-E33',
    leer: (pedir) => quitarElPropio(pedir, DURANGO_CENTRO.id_publico),
    simulado: comisiones(),
  },
  'comisiones.no-existe': {
    escenario: 'administracion-app-E12',
    leer: (pedir) => fijarElPropio(pedir, NINGUNO, '7.25'),
    simulado: comisiones(),
  },

  // F-D06, los reportes financieros.
  ingresos: {
    escenario: 'administracion-app-E37',
    leer: (pedir) => consultarIngresos(pedir, MARZO_POR_SEMANA),
    simulado: reportes(),
  },
  conciliaciones: {
    escenario: 'administracion-app-E38',
    leer: (pedir) => conciliarPeriodo(pedir, MARZO),
    simulado: reportes(),
  },
  'conciliaciones.rango-al-reves': {
    escenario: 'administracion-app-E38',
    leer: (pedir) => conciliarPeriodo(pedir, { desde: MARZO.hasta, hasta: MARZO.desde }),
    campo: 'hasta',
    simulado: reportes(),
  },
  'conciliaciones.sin-pasarela': {
    escenario: 'administracion-app-E38',
    leer: (pedir) => conciliarPeriodo(pedir, MARZO),
    motivo: 'sin-pasarela',
    simulado: reportes(false),
  },

  // F-D07, los avisos masivos.
  'avisos-masivos.lista': {
    escenario: 'administracion-app-E43',
    leer: (pedir) => consultarAvisos(pedir),
    simulado: avisos(),
  },
  'avisos-masivos.lista-vacia': {
    escenario: 'administracion-app-E43',
    leer: (pedir) => consultarAvisos(pedir),
    simulado: avisos([]),
  },
  'avisos-masivos.nuevo': {
    escenario: 'administracion-app-E42',
    leer: (pedir) => mandarAviso(pedir, { titulo: 'Aviso de prueba', mensaje: 'Texto de prueba del aviso.' }),
    simulado: avisos(),
  },
  'avisos-masivos.nuevo-invalido': {
    escenario: 'administracion-app-E42',
    leer: (pedir) => mandarAviso(pedir, { titulo: '', mensaje: 'a'.repeat(1001) }),
    campo: 'titulo',
    simulado: avisos(),
  },

  // Los indicadores del A.5.
  'indicadores.operaciones': {
    escenario: 'administracion-app-E46',
    leer: (pedir) => consultarIndicadores(pedir, 'operaciones', null, 'colonia'),
    simulado: indicadores(),
  },
  'indicadores.usuarios': {
    escenario: 'administracion-app-E47',
    leer: (pedir) => consultarIndicadores(pedir, 'usuarios', null, 'colonia'),
    simulado: indicadores(),
  },
  'indicadores.usuarios-sin-pedidos': {
    escenario: 'administracion-app-E47',
    leer: (pedir) => consultarIndicadores(pedir, 'usuarios', null, 'colonia'),
    simulado: indicadores((delServidor) =>
      delServidor.cambiarSinFiltros('usuarios', {
        ...USUARIOS_DEL_MES_EN_CURSO,
        vuelven_a_pedir: {
          meses: [{ inicio: '2026-11-01', mes: 'noviembre 2026', con_primer_pedido: 0, porcentaje: null }],
        },
        monto_promedio: {
          promedio: null,
          promedio_texto: null,
          promedio_anterior: null,
          promedio_anterior_texto: null,
          diferencia: null,
          tendencia: null,
          tendencia_texto: null,
        },
      }),
    ),
  },
  'indicadores.socios': {
    escenario: 'administracion-app-E48',
    leer: (pedir) => consultarIndicadores(pedir, 'socios', null, 'colonia'),
    simulado: indicadores(),
  },
  'indicadores.socios-sin-cortes': {
    escenario: 'administracion-app-E48',
    leer: (pedir) => consultarIndicadores(pedir, 'socios', null, 'colonia'),
    simulado: indicadores((delServidor) => {
      const socios = sociosCon([3, 0]) as { calificacion_por_lavanderia: { lavanderias: Record<string, unknown>[] } };
      delServidor.cambiarSinFiltros('socios', {
        ...socios,
        calificacion_por_lavanderia: {
          semaforo_configurado: false,
          lavanderias: socios.calificacion_por_lavanderia.lavanderias.map((lavanderia) => ({
            ...lavanderia,
            semaforo: null,
            semaforo_texto: null,
          })),
        },
      });
    }),
  },
  'indicadores.financiero': {
    escenario: 'administracion-app-E49',
    leer: (pedir) => consultarIndicadores(pedir, 'financiero', null, 'colonia'),
    simulado: indicadores(),
  },
  'indicadores.geografico-por-colonia': {
    escenario: 'administracion-app-E50',
    leer: (pedir) => consultarIndicadores(pedir, 'geografico', null, 'colonia'),
    simulado: indicadores(),
  },
  'indicadores.geografico-por-zona': {
    escenario: 'administracion-app-E50',
    leer: (pedir) => consultarIndicadores(pedir, 'geografico', null, 'zona'),
    simulado: indicadores(),
  },
  'indicadores.no-existe': {
    escenario: 'administracion-app-E45',
    // La app solo pide las cinco categorías; el 404 de otra es el de una ruta que cambió.
    leer: (pedir) => consultarIndicadores(pedir, 'comisiones' as Categoria, null, 'colonia'),
    simulado: indicadores(),
  },

  // F-T14, las descargas.
  'descargas.nueva': {
    escenario: 'administracion-app-E56',
    leer: (pedir) => pedirLaDescarga(pedir, { reporte: 'pedidos-totales', ...SEMANA, granularidad: 'dia' }),
    simulado: descargas(false),
  },
  'descargas.nueva-invalida': {
    escenario: 'administracion-app-E60',
    leer: (pedir) =>
      pedirLaDescarga(pedir, {
        reporte: 'pedidos-totales',
        desde: SEMANA.hasta,
        hasta: SEMANA.desde,
        granularidad: 'dia',
      }),
    campo: 'hasta',
    simulado: descargas(false),
  },
  'descargas.lista': {
    escenario: 'administracion-app-E59',
    leer: (pedir) => consultarLasDescargas(pedir),
    simulado: descargas(true),
  },
  'descargas.lista-vacia': {
    escenario: 'administracion-app-E59',
    leer: (pedir) => consultarLasDescargas(pedir),
    simulado: descargas(false),
  },
  'descargas.descarga': {
    escenario: 'administracion-app-E57',
    leer: (pedir) => consultarLaDescarga(pedir, LISTA),
    simulado: descargas(true),
  },
  'descargas.no-existe': {
    escenario: 'administracion-app-E62',
    leer: (pedir) => consultarLaDescarga(pedir, NINGUNO),
    simulado: descargas(true),
  },
  'descargas.archivo-que-no-existe': {
    escenario: 'administracion-app-E58',
    // El archivo lo baja expo-file-system (src/administracion/descargas/guardar.ts), que no lee este cuerpo: aquí basta
    // con que la ruta y su 404 sean los del contrato, y la del simulado igual.
    leer: (pedir) => pedir(`descargas/${LISTA}/pdf`),
    simulado: descargas(true),
  },
};

const servidor = usarElServidorDeLaAdministracion('administracion-contratos');

/** La dirección base del servidor simulado con que se repite la petición de la app. */
const BASE_DEL_SIMULADO = 'http://api.prueba.invalid/contratos-simulado';

/** El cliente de la administración de la app, con el token de la sesión: el que usan todas las pantallas del módulo. */
const pedir: PedirALaAdministracion = (ruta, peticion) => pedirConElToken(TOKEN_DE_LA_ADMINISTRACION, ruta, peticion);

/** Lo que la app tiene que sacar de la respuesta real, según su código. */
function loQueLaAppEspera(nombre: string, contrato: Contrato, caso: Caso) {
  if (contrato.estado < 300) {
    return { ok: true };
  }
  if (caso.motivo) {
    return { ok: false, motivo: caso.motivo };
  }
  switch (contrato.estado) {
    case 404:
      return { ok: false, motivo: 'no-existe', mensaje: expect.any(String) };
    case 409:
      return { ok: false, motivo: 'conflicto', mensaje: expect.any(String) };
    case 422:
      if (!caso.campo) {
        throw new Error(`El caso ${nombre} es un 422: falta el campo cuyo error muestra la pantalla.`);
      }
      return {
        ok: false,
        motivo: 'invalido',
        errores: expect.objectContaining({ [caso.campo]: [expect.any(String)] }),
      };
    default:
      throw new Error(`El caso ${nombre} responde ${contrato.estado}: falta lo que la app hace con esa respuesta.`);
  }
}

/** La última petición que hizo la app, como "GET /administracion/…", y su cuerpo. */
function laPeticionDeLaApp() {
  const peticiones = servidor().peticiones();
  expect(peticiones).toHaveLength(1);
  return peticiones[0];
}

const CONTRATOS = nombresDeLosContratos().map((nombre) => ({
  nombre,
  ...CASOS[nombre],
  contrato: leerElContrato(nombre),
}));

describe('administracion-app-E11: los contratos de la API de la administración con la app', () => {
  test('administracion-app-E11 — cada contrato del servidor tiene aquí lo que la app hace con él, y cada caso de aquí, su contrato', () => {
    expect(Object.keys(CASOS).sort()).toEqual(nombresDeLosContratos());
  });

  test.each(CONTRATOS)(
    '$escenario — $nombre: la app pide la ruta del contrato y lee la respuesta real del servidor',
    async ({ nombre, contrato, leer, ...caso }) => {
      servidor().interceptar = () => respuesta(contrato.estado, contrato.cuerpo);

      const leido = await leer(pedir);

      expect(leido).toMatchObject(loQueLaAppEspera(nombre, contrato, { leer, ...caso }));
      const { metodo, ruta } = laPeticionDeLaApp();
      expect(`${metodo} ${ruta}`).toMatch(patronDeLaPeticion(contrato.peticion));
    },
  );

  test.each(CONTRATOS.filter(({ simulado }) => simulado !== null))(
    '$escenario — $nombre: el servidor simulado responde la misma petición con el código y la forma del real',
    async ({ contrato, leer, simulado }) => {
      // La petición que hace la app, tal cual, al servidor simulado de las pruebas de las pantallas.
      servidor().interceptar = () => respuesta(contrato.estado, contrato.cuerpo);
      await leer(pedir);
      const { metodo, ruta, cuerpo } = laPeticionDeLaApp();
      const simuladoDeLasPantallas = new ServidorDeLaAdministracion(BASE_DEL_SIMULADO);
      simulado?.(simuladoDeLasPantallas);

      const contestada = (await simuladoDeLasPantallas.fetch(`${BASE_DEL_SIMULADO}${ruta}`, {
        method: metodo,
        headers: { Authorization: `Bearer ${TOKEN_DE_LA_ADMINISTRACION}`, 'Content-Type': 'application/json' },
        body: cuerpo === undefined ? undefined : JSON.stringify(cuerpo),
      })) as { status: number; json(): Promise<unknown> };

      expect(contestada.status).toBe(contrato.estado);
      expect(diferenciasDeForma(await contestada.json(), contrato.cuerpo)).toEqual([]);
    },
  );
});
