// Cuándo enlaza la compilación los módulos nativos de Firebase (avisos-E03; ADR-0008 y su adenda). Lo lee
// react-native.config.js, que el enlace automático de Expo consulta al compilar: Gradle en Android y CocoaPods en iPhone.
//
// - Android los enlaza solo si su proyecto nativo trae el google-services.json, que Expo copia a android/app/ cuando el
//   entorno da GOOGLE_SERVICES_JSON (app.config.ts). Sin él, la compilación sale como antes de Firebase: sin su código
//   nativo, que sin el archivo cerraría la app en cuanto alguien pidiera la mensajería.
// - iPhone no los enlaza todavía: Firebase pide ahí el GoogleService-Info.plist y los frameworks dinámicos, que llegan
//   con avisos-E18 (tarea 5.2: DD-069, DD-017 y DD-084). Sin esto, la instalación de los pods fallaría.
//
// Se decide por el archivo del proyecto nativo y no por la variable del entorno: el enlace automático corre dentro de
// Gradle, que no lee el .env de la app.
const fs = require('node:fs');
const path = require('node:path');

const MODULOS_DE_FIREBASE = ['@react-native-firebase/app', '@react-native-firebase/messaging'];

/** Lo que react-native.config.js pone en "dependencies" para los módulos de Firebase, en el proyecto de esa carpeta. */
function dependenciasNativasDeFirebase(raiz) {
  const conArchivoEnAndroid = fs.existsSync(path.join(raiz, 'android', 'app', 'google-services.json'));
  // null apaga el enlace de esa plataforma; sin la clave, queda el de la librería.
  const plataformas = conArchivoEnAndroid ? { ios: null } : { ios: null, android: null };
  return Object.fromEntries(MODULOS_DE_FIREBASE.map((modulo) => [modulo, { platforms: { ...plataformas } }]));
}

module.exports = { dependenciasNativasDeFirebase };
