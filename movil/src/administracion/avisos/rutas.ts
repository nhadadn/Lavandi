// Las rutas de los avisos masivos en la app (F-D07): la lista, que abre "Avisos masivos" en el inicio del módulo, y
// "Escribir aviso", que se abre desde la lista (design.md, sección 5; imágenes 36 y 37 de la Propuesta 06).
import type { Href } from 'expo-router';

import { RUTAS_DE_LAS_SECCIONES } from '../rutas';

export const RUTA_DE_LOS_AVISOS: Href = RUTAS_DE_LAS_SECCIONES.avisos;

export const RUTA_DEL_AVISO_NUEVO: Href = `${RUTAS_DE_LAS_SECCIONES.avisos}/nuevo`;
