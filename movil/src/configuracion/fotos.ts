// La compresión de las fotos que la app sube (ADR-0006: "La app comprime las fotos antes de subirlas. El tamaño y la
// calidad los fija la spec"). Son dos: la de la lavandería en su registro y en su perfil, también cada foto que agrega a
// la lista de su perfil (alta-lavanderias-E03, E19 y E27), y la foto de perfil del usuario (perfil-usuario-E03), con los
// mismos valores: ningún escenario pide otros, como el servidor, que les pone los mismos límites (perfil-y-soporte,
// design.md, decisión 1). Viven en la configuración de la app, no en la pantalla
// (openspec/changes/alta-de-lavanderias/design.md, pregunta abierta 1).
//
// Ratificados por el concilio del 2026-11-24, por delegación de Arthur (C-245; acta-2026-11-20-modelo-y-pasarela.md,
// decisión 47); se miden en el Android de Arthur (C-243), y medir solo mueve el número. Con estos, una foto de 12
// megapíxeles sale de unos cientos de KB, lejos del peso máximo del servidor (FOTOS_PESO_MAXIMO_KB, 5120 por omisión,
// también ratificado). Cambiarlos no toca la pantalla.
export const COMPRESION_DE_FOTOS = {
  /** El lado más largo de la foto, en píxeles. Una foto más chica no se agranda. */
  ladoMaximoPx: 1600,
  /** La calidad del JPEG, de 0 (la menor) a 1 (sin pérdida). */
  calidad: 0.7,
} as const satisfies { ladoMaximoPx: number; calidad: number };
