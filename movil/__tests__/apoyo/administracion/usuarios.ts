// Apoyo de las pruebas de la gestión de usuarios de la administración en la app (F-D03; administracion-app-E24 y E25).
// No es una prueba: Jest no lo corre como tal (testPathIgnorePatterns en package.json). Contesta las rutas de
// /administracion/usuarios en el servidor simulado de la administración (__tests__/apoyo/administracion.ts), con el
// contrato del comentario de servidor/app/Http/Controllers/Api/Administracion/UsuariosController.php:
//
// - GET usuarios, con ?buscar= (dentro del correo o del nombre, sin distinguir mayúsculas y sin los espacios de las
//   orillas) y ?pagina=, por correo y en páginas;
// - GET usuarios/{id_publico}, la ficha con su teléfono y su historial;
// - POST y DELETE usuarios/{id_publico}/bloqueo, que dejan la cuenta bloqueada o activa y responden su ficha;
// - 404 con solo su mensaje a una cuenta que no está.
//
// Las cuentas y sus pedidos son de prueba: lo que la app muestre sale de aquí, nunca de su código.
import { copia, respuesta, type Peticion, type ServidorDeLaAdministracion } from '../administracion';

/** El 404 de una cuenta que no existe, no es de usuario o no es un ULID (administracion-app-E12). */
export const MENSAJE_DE_NO_EXISTE = 'No existe el usuario.';

/** Cuántas cuentas trae cada página, como UsuariosController::POR_PAGINA. */
export const POR_PAGINA = 25;

/** Un pedido del historial, como sale en la ficha. */
export interface PedidoDeLaFicha {
  folio: string;
  fecha: string;
  lavanderia: string;
  estado: string;
  monto: string | null;
}

/** Una cuenta de usuario del servidor simulado, con todo lo de su ficha. */
export interface UsuarioSimulado {
  id_publico: string;
  correo: string;
  nombre: string | null;
  telefono: string | null;
  estado: 'activo' | 'bloqueado';
  /** Del más reciente al más antiguo, como los da el servidor. */
  historial: PedidoDeLaFicha[];
}

/**
 * Ana, de una plaza de Durango, con tres pedidos de fechas distintas, el más reciente sin monto todavía (DD-012,
 * DD-010), y uno de más de mil pesos.
 */
export const ANA: UsuarioSimulado = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0an1',
  correo: 'ana@ejemplo.com',
  nombre: 'Ana Prueba',
  telefono: '6180000101',
  estado: 'activo',
  historial: [
    { folio: '7K2M9QXA', fecha: '2026-11-24', lavanderia: 'Lavandería de prueba Durango', estado: 'En proceso', monto: null },
    { folio: '3HT8W2QZ', fecha: '2026-11-12', lavanderia: 'Lavandería de prueba Durango', estado: 'Entregado', monto: '1180.50' },
    { folio: 'M4P7R2KD', fecha: '2026-11-02', lavanderia: 'Lavandería de prueba Dos', estado: 'Entregado', monto: '250.00' },
  ],
};

/** Beto, de la plaza de Torreón, con un pedido; su cuenta está bloqueada. */
export const BETO: UsuarioSimulado = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0be2',
  correo: 'beto@ejemplo.com',
  nombre: 'Beto Prueba',
  telefono: '8710000202',
  estado: 'bloqueado',
  historial: [
    { folio: 'Q2W5E7RZ', fecha: '2026-11-05', lavanderia: 'Lavandería de prueba Torreón', estado: 'Entregado', monto: '90.00' },
  ],
};

/** Una cuenta recién registrada: sin nombre, sin teléfono y sin pedidos. */
export const SIN_DATOS: UsuarioSimulado = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0sd3',
  correo: 'cuenta-nueva@ejemplo.com',
  nombre: null,
  telefono: null,
  estado: 'activo',
  historial: [],
};

/** Las tres cuentas de las pruebas. */
export const USUARIOS: readonly UsuarioSimulado[] = [ANA, BETO, SIN_DATOS];

/** Tantas cuentas de prueba como se pidan, "usuario-01@ejemplo.com", …, para llenar más de una página. */
export function variosUsuarios(cuantos: number): UsuarioSimulado[] {
  return Array.from({ length: cuantos }, (_, indice) => {
    const numero = String(indice + 1).padStart(2, '0');
    return {
      id_publico: `01jd8x6m3v2c9q4w5e7r8t00${numero}`,
      correo: `usuario-${numero}@ejemplo.com`,
      nombre: `Usuario ${numero}`,
      telefono: null,
      estado: 'activo',
      historial: [],
    };
  });
}

/** La cuenta como sale en la lista. */
function deLaLista({ id_publico, correo, nombre, estado }: UsuarioSimulado) {
  return { id_publico, correo, nombre, estado };
}

/** La cuenta como sale en su ficha. */
function deLaFicha({ id_publico, correo, nombre, telefono, estado, historial }: UsuarioSimulado) {
  return { id_publico, correo, nombre, telefono, estado, historial };
}

const LISTA = /^\/administracion\/usuarios(?:\?(.*))?$/;
const FICHA = /^\/administracion\/usuarios\/([^/?]+)$/;
const BLOQUEO = /^\/administracion\/usuarios\/([^/?]+)\/bloqueo$/;

/** Las cuentas de usuario del servidor simulado y sus rutas. */
export class UsuariosSimulados {
  readonly usuarios: UsuarioSimulado[];

  constructor(usuarios: readonly UsuarioSimulado[]) {
    this.usuarios = copia([...usuarios]);
  }

  /** La cuenta, como está ahora en el servidor. */
  cuenta(idPublico: string): UsuarioSimulado | undefined {
    return this.usuarios.find((usuario) => usuario.id_publico === idPublico);
  }

  /** Contesta las rutas de usuarios; lo demás, undefined, para el siguiente manejador. */
  readonly contestar = ({ metodo, ruta }: Peticion): unknown => {
    const lista = LISTA.exec(ruta);
    if (metodo === 'GET' && lista) {
      return respuesta(200, this.lista(new URLSearchParams(lista[1] ?? '')));
    }
    const ficha = FICHA.exec(ruta);
    if (metodo === 'GET' && ficha) {
      return this.conLaFicha(ficha[1], () => {});
    }
    const bloqueo = BLOQUEO.exec(ruta);
    if (bloqueo && (metodo === 'POST' || metodo === 'DELETE')) {
      return this.conLaFicha(bloqueo[1], (usuario) => {
        usuario.estado = metodo === 'POST' ? 'bloqueado' : 'activo';
      });
    }
    return undefined;
  };

  private lista(parametros: URLSearchParams) {
    const buscar = (parametros.get('buscar') ?? '').trim().toLowerCase();
    const pedida = Number(parametros.get('pagina'));
    const actual = Number.isInteger(pedida) && pedida >= 1 ? pedida : 1;
    const encontrados = this.usuarios
      .filter(
        ({ correo, nombre }) =>
          buscar === '' || correo.toLowerCase().includes(buscar) || (nombre ?? '').toLowerCase().includes(buscar),
      )
      .sort((una, otra) => una.correo.localeCompare(otra.correo));
    return {
      usuarios: encontrados.slice((actual - 1) * POR_PAGINA, actual * POR_PAGINA).map(deLaLista),
      pagina: {
        actual,
        ultima: Math.max(1, Math.ceil(encontrados.length / POR_PAGINA)),
        por_pagina: POR_PAGINA,
        total: encontrados.length,
      },
    };
  }

  private conLaFicha(idPublico: string, cambiar: (usuario: UsuarioSimulado) => void) {
    const usuario = this.cuenta(decodeURIComponent(idPublico));
    if (!usuario) {
      return respuesta(404, { message: MENSAJE_DE_NO_EXISTE });
    }
    cambiar(usuario);
    return respuesta(200, { usuario: deLaFicha(usuario) });
  }
}

/**
 * Pone las cuentas de usuario en el servidor simulado de la administración, las de las pruebas si no se dicen otras.
 * Devuelve las cuentas, para cambiarlas o leerlas durante la prueba.
 */
export function agregarLosUsuarios(
  servidor: ServidorDeLaAdministracion,
  usuarios: readonly UsuarioSimulado[] = USUARIOS,
): UsuariosSimulados {
  const simulados = new UsuariosSimulados(usuarios);
  servidor.agregar(simulados.contestar);
  return simulados;
}
