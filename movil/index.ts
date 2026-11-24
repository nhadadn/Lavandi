// La entrada de la app. Antes de montar las pantallas de expo-router (ADR-0017), registra el manejador de los mensajes
// de Firebase que llegan con la app al fondo o cerrada (avisos-E17; src/avisos/arranque.ts): la librería pide que se
// registre fuera de las pantallas y lo antes posible. El orden de los import es el orden en que corren.
import './src/avisos/arranque';
import 'expo-router/entry';
