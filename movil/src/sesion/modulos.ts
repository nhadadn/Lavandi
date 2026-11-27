// A qué módulo de la app entra cada cuenta, por su rol (registro-E33): una sola app con los módulos del usuario, de la
// lavandería, del repartidor y de la administración (NSV-2026-001-v8.pdf:3:Cláusula I; NSV-2026-001-v8.pdf:16:ANEXO A,
// Sección A.3), con las pantallas separadas por tipo de usuario (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Medidas de
// seguridad). El rol lo da el servidor en la cuenta (GET /api/cuenta) y al iniciar la sesión (POST /api/sesion), y
// también al registrar un repartidor (POST /api/repartidor/registro).
//
// La administración inicia sesión en la app y entra a su módulo, como los otros roles (administracion-app-E02; C-247;
// ADR-0022, decisiones 1 y 11). Una cuenta sin ningún rol de la app no tiene módulo: la app no abre su sesión.
import type { Href } from 'expo-router';

import { RUTA_DEL_INICIO_DE_LA_ADMINISTRACION } from '../administracion/rutas';
import { RUTA_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA } from '../pedidos-lavanderia/rutas';
import { RUTA_DE_LOS_PEDIDOS_DEL_REPARTIDOR } from '../repartidor/rutas';

/**
 * Los módulos de la app, cada uno con el código de su rol en el catálogo de roles del servidor
 * (servidor/app/Models/Rol.php): la cuenta de usuario entra al Módulo A, la de socio lavandería al Módulo B, la de
 * socio repartidor al Módulo C (openspec/changes/repartidor) y la de administración al Módulo D
 * (openspec/changes/administracion-en-la-app).
 */
const MODULOS = ['usuario', 'lavanderia', 'repartidor', 'administracion'] as const;

export type Modulo = (typeof MODULOS)[number];

/**
 * El rol de una sesión guardada antes de que la app guardara el rol, si al abrirla no se llega al servidor: hasta
 * entonces solo la cuenta de usuario tenía módulo en la app. En cuanto el servidor contesta, manda el rol que dé.
 */
export const ROL_SIN_GUARDAR = 'usuario';

/** El módulo de la cuenta con ese rol, o null si la cuenta no usa la app. */
export function moduloDelRol(rol: string | null | undefined): Modulo | null {
  return MODULOS.find((modulo) => modulo === rol) ?? null;
}

/**
 * La primera pantalla de cada módulo: el inicio del usuario (lavanderias-cercanas), los pedidos de la lavandería, los
 * pedidos asignados del repartidor y el inicio de la administración, con sus secciones (administracion-app-E03). Al
 * repartidor que todavía no opera, su módulo le muestra la espera de su alta (repartidor-E03).
 */
export const INICIO_DEL_MODULO: Readonly<Record<Modulo, Href>> = {
  usuario: '/inicio',
  lavanderia: RUTA_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA,
  repartidor: RUTA_DE_LOS_PEDIDOS_DEL_REPARTIDOR,
  administracion: RUTA_DEL_INICIO_DE_LA_ADMINISTRACION,
};
