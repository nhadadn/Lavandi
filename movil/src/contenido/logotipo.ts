// El logotipo de la app, en un archivo de nombre neutro (decisión 20 del acta del 2026-11-20, C-237): la marca no se
// escribe en el código (CLAUDE.md §5). En el repositorio, assets/marca/logotipo.png es el marcador neutro de la
// plantilla (una copia de assets/splash-icon.png); la compilación local lo reemplaza por el logotipo de EL CLIENTE.
import type { ImageSourcePropType } from 'react-native';

export const LOGOTIPO: ImageSourcePropType = require('../../assets/marca/logotipo.png');
