// El contenido de la bienvenida y de las tres informativas (bienvenida-E05): las pantallas lo toman de aquí y no traen
// ningún texto propio. La estructura es la del mock (C-225): lema e ilustración en la bienvenida; título, texto e
// ilustración en cada informativa.
//
// PROVISIONAL. Los textos y las imágenes son de EL CLIENTE (NSV-2026-001-v8.pdf:6:Cláusula III, inciso B) y no han
// llegado (DD-081). Mientras tanto van los textos de relleno de mocks/usuario/a01-bienvenida.html y
// a01-informativas.html; en la bienvenida, el logotipo de nombre neutro (./logotipo.ts, C-237), y en las informativas,
// la imagen neutra de la plantilla (assets/splash-icon.png). Con este contenido F-A01 no se acredita (bienvenida-E06).
import type { ImageSourcePropType } from 'react-native';

import { LOGOTIPO } from './logotipo';

export interface Informativa {
  titulo: string;
  texto: string;
  ilustracion: ImageSourcePropType;
}

export interface ContenidoDeBienvenida {
  bienvenida: { lema: string; ilustracion: ImageSourcePropType };
  informativas: readonly [Informativa, Informativa, Informativa];
  /** Las etiquetas de los botones para avanzar, las de los mocks. */
  botones: { comenzar: string; siguiente: string };
}

const ILUSTRACION_PROVISIONAL: ImageSourcePropType = require('../../assets/splash-icon.png');

export const contenidoDeBienvenida: ContenidoDeBienvenida = {
  bienvenida: {
    lema: 'Aquí va el lema del servicio.',
    ilustracion: LOGOTIPO,
  },
  informativas: [
    {
      titulo: 'Pide desde tu teléfono',
      texto: 'Elige una lavandería cercana, el servicio y la hora en que pasamos por tu ropa.',
      ilustracion: ILUSTRACION_PROVISIONAL,
    },
    {
      titulo: 'Recogemos, lavamos y entregamos',
      texto: 'Pasamos a tu domicilio en la ventana de una hora que elegiste y te regresamos tu ropa lista.',
      ilustracion: ILUSTRACION_PROVISIONAL,
    },
    {
      titulo: 'Sigue tu pedido',
      texto: 'Te avisamos en cada paso: confirmado, recolectado, en proceso, listo y entregado.',
      ilustracion: ILUSTRACION_PROVISIONAL,
    },
  ],
  botones: {
    comenzar: 'Comenzar',
    siguiente: 'Siguiente',
  },
};
