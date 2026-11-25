// A qué módulo de la app entra cada cuenta, por su rol (registro-E33): una sola app con los módulos del usuario, de la
// lavandería y del repartidor (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3), con las pantallas separadas por tipo de
// usuario (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Medidas de seguridad). El rol lo da el servidor en la cuenta
// (GET /api/cuenta) y al iniciar la sesión (POST /api/sesion), y también al registrar un repartidor
// (POST /api/repartidor/registro).
//
// La administración entra por el panel y no tiene módulo en la app: como cualquier rol sin módulo, la app no abre su
// sesión.
import type { Href } from 'expo-router';

import { RUTA_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA } from '../pedidos-lavanderia/rutas';
import { RUTA_DE_LOS_PEDIDOS_DEL_REPARTIDOR } from '../repartidor/rutas';

/**
 * Los módulos de la app, cada uno con el código de su rol en el catálogo de roles del servidor
 * (servidor/app/Models/Rol.php): la cuenta de usuario entra al Módulo A, la de socio lavandería al Módulo B y la de
 * socio repartidor al Módulo C (openspec/changes/repartidor).
 */
const MODULOS = ['usuario', 'lavanderia', 'repartidor'] as const;

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
 * La primera pantalla de cada módulo: el inicio del usuario (lavanderias-cercanas), los pedidos de la lavandería y los
 * pedidos asignados del repartidor. Al repartidor que todavía no opera, su módulo le muestra la espera de su alta
 * (repartidor-E03).
 */
export const INICIO_DEL_MODULO: Readonly<Record<Modulo, Href>> = {
  usuario: '/inicio',
  lavanderia: RUTA_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA,
  repartidor: RUTA_DE_LOS_PEDIDOS_DEL_REPARTIDOR,
};
