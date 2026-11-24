// Lo que corre al arrancar la app, antes de montar las pantallas (index.ts): el manejador de los mensajes de Firebase con
// la app al fondo o cerrada (avisos-E17).
import { registrarManejadorDeSegundoPlano } from './segundoPlano';

registrarManejadorDeSegundoPlano();
