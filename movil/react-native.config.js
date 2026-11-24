// Ajustes del enlace automático de los módulos nativos, que Expo consulta al compilar (expo-modules-autolinking). Solo
// cambia el de los módulos de Firebase, que se enlazan cuando la compilación trae su archivo: ver firebase-nativo.js.
/* global __dirname */
const { dependenciasNativasDeFirebase } = require('./firebase-nativo');

module.exports = {
  dependencies: dependenciasNativasDeFirebase(__dirname),
};
