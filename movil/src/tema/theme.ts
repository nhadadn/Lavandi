// ARCHIVO GENERADO: no se edita a mano.
// Sale de vault/design-system/tokens.json (versión 0.2.0, 2026-11-21) con
// herramientas/tokens/generar.js (ADR-0013). Para cambiar un valor se cambia tokens.json y se corre
// `npm run tokens` en movil/. Una prueba falla si este archivo no corresponde a tokens.json.
// Solo modo claro (C-141). Los componentes usan color.semantico, nunca color.marca directo (design-system.md,
// Decisión 3). Las medidas de tokens.json están en px y se usan tal cual como puntos de React Native.

export const color = {
  marca: {
    primario: '#2E86AB',
    primario_oscuro: '#297899',
    acento: '#E8C547',
    claro: '#A8D5E2',
  },
  neutro: {
    0: '#FFFFFF',
    50: '#F7F8F9',
    100: '#EDEFF2',
    200: '#DCE0E5',
    300: '#C0C6CE',
    400: '#98A1AC',
    500: '#6B7681',
    600: '#515A64',
    700: '#3B424A',
    800: '#262C33',
    900: '#171C21',
    1000: '#0E1216',
  },
  estado: {
    exito: '#1E6F45',
    atencion: '#8A5A00',
    error: '#A32222',
    informacion: '#1F5C8C',
  },
  semantico: {
    fondo: '#F7F4EF',
    superficie: '#FFFFFF',
    superficie_elevada: '#FFFFFF',
    superficie_hundida: '#EDE9E2',
    texto: '#171C21',
    texto_suave: '#515A64',
    texto_inverso: '#FFFFFF',
    texto_deshabilitado: '#6B7681',
    borde: '#DCE0E5',
    borde_fuerte: '#6B7681',
    accion: '#297899',
    accion_hover: '#1F5A73',
    accion_texto: '#FFFFFF',
    foco: '#297899',
  },
  pedido: {
    confirmado: '#1F5C8C',
    recolectado: '#5A4B8A',
    en_proceso: '#8A5A00',
    listo: '#1E6F45',
    entregado: '#515A64',
  },
  marca_extendida: {
    agua_claro: '#308CB3',
    limon_profundo: '#B39116',
    hueso: '#F7F4EF',
  },
} as const;

export const tipografia = {
  // La primera familia de cada pila de tokens.json: React Native no acepta pilas.
  familia: {
    titulares: 'Nunito',
    cuerpo: 'Source Sans 3',
    principal: 'Nunito',
    apoyo: 'Source Sans 3',
    monoespaciada: 'ui-monospace',
  },
  escala: {
    micro: 12,
    menor: 14,
    cuerpo: 16,
    cuerpo_grande: 18,
    titulo_menor: 20,
    titulo: 24,
    titulo_mayor: 30,
    despliegue: 36,
  },
  peso: {
    regular: 400,
    medio: 500,
    semifuerte: 600,
    fuerte: 700,
  },
  // Multiplicadores: en React Native, lineHeight va en puntos (tamaño × interlineado).
  interlineado: {
    apretado: 1.2,
    normal: 1.5,
    suelto: 1.7,
  },
  // En em: en React Native, letterSpacing va en puntos (tamaño × espaciado).
  espaciado_letra: {
    normal: 0,
    titular: -0.01,
    versalita: 0.04,
  },
} as const;

export const espaciado = {
  0: 0,
  1: 4,
  2: 8,
  3: 12,
  4: 16,
  5: 24,
  6: 32,
  7: 48,
  8: 64,
} as const;

export const radio = {
  nulo: 0,
  chico: 4,
  medio: 8,
  grande: 12,
  mayor: 16,
  pildora: 999,
} as const;

// Grosores de borde: control, el de campos y botones; foco, el contorno de foco visible.
export const grosor = {
  control: 1,
  foco: 3,
} as const;

// Altos fijos de controles. Los demás miden al menos accesibilidad.area_tactil_minima_dp.
export const alto = {
  boton_principal: 52,
} as const;

// Sombras con la sintaxis de CSS, que React Native acepta en la propiedad boxShadow.
export const elevacion = {
  0: 'none',
  1: '0 1px 2px rgba(14,18,22,.08)',
  2: '0 2px 8px rgba(14,18,22,.10)',
  3: '0 8px 24px rgba(14,18,22,.14)',
} as const;

// Milisegundos.
export const duracion = {
  instantanea: 0,
  rapida: 120,
  base: 200,
  lenta: 320,
} as const;

// Los cuatro números de Easing.bezier.
export const curva = {
  estandar: [0.2, 0, 0, 1],
  entrada: [0, 0, 0, 1],
  salida: [0.3, 0, 1, 1],
} as const;

export const accesibilidad = {
  contraste_minimo_texto_normal: 4.5,
  contraste_minimo_texto_grande: 3,
  contraste_minimo_componente: 3,
  texto_grande_desde_px: 18.66,
  area_tactil_minima_dp: 48,
  area_tactil_minima_pt_ios: 44,
  separacion_minima_entre_objetivos_dp: 8,
} as const;

export const tema = {
  modo: 'claro',
  color,
  tipografia,
  espaciado,
  radio,
  grosor,
  alto,
  elevacion,
  duracion,
  curva,
  accesibilidad,
} as const;

export type Tema = typeof tema;
