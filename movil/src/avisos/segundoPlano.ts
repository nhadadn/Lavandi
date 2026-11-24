// Los mensajes de Firebase que llegan con la app al fondo o cerrada (avisos-E17; ADR-0008). La librería pide registrar
// su manejador fuera de las pantallas y lo antes posible: lo hace la entrada de la app (index.ts, src/avisos/arranque.ts).
//
// El manejador no hace nada, y basta:
// - el aviso de un momento del pedido trae su parte visible (design.md de avisos, "Lo que recibe la app por FCM"), y con
//   la app al fondo o cerrada lo muestra el sistema, sin la app;
// - el mensaje de un cambio de estado solo adelanta la consulta del seguimiento abierto (avisos-E15), y al volver la app
//   al frente el seguimiento ya consulta de inmediato (src/estados/useConsultaPeriodica.ts, ADR-0007).
// Sin manejador, la librería deja un aviso en la bitácora del teléfono por cada mensaje.
import { avisosDisponibles, mensajeria } from './firebase';

export function registrarManejadorDeSegundoPlano(): void {
  if (!avisosDisponibles()) {
    return;
  }
  try {
    const libreria = mensajeria();
    libreria.setBackgroundMessageHandler(libreria.getMessaging(), async () => undefined);
  } catch {
    // La librería no cargó: la app arranca sin avisos.
  }
}
