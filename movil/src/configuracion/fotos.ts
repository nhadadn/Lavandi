// La compresión de las fotos que la app sube (ADR-0006: "La app comprime las fotos antes de subirlas. El tamaño y la
// calidad los fija la spec"). Son dos: la de la lavandería en su registro y en su perfil (alta-lavanderias-E03 y E19) y
// la foto de perfil del usuario (perfil-usuario-E03), con los mismos valores: ningún escenario pide otros, como el
// servidor, que les pone los mismos límites (perfil-y-soporte, design.md, decisión 1). Viven en la configuración de la
// app, no en la pantalla (openspec/changes/alta-de-lavanderias/design.md, pregunta abierta 1: "Se construye con
// parámetros de configuración; los valores los decide Arthur").
//
// PROVISIONALES. Los fija Arthur. Con estos, una foto de 12 megapíxeles sale de unos cientos de KB, lejos del peso
// máximo del servidor (FOTOS_PESO_MAXIMO_KB, 5120 por omisión, también provisional). Cambiarlos no toca la pantalla. El
// peso real con que sale cada foto se revisa en un teléfono real.
export const COMPRESION_DE_FOTOS = {
  /** El lado más largo de la foto, en píxeles. Una foto más chica no se agranda. */
  ladoMaximoPx: 1600,
  /** La calidad del JPEG, de 0 (la menor) a 1 (sin pérdida). */
  calidad: 0.7,
} as const satisfies { ladoMaximoPx: number; calidad: number };
