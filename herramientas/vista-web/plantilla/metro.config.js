// Solo para la vista previa en el navegador. Lo copia herramientas/vista-web/preparar.ps1 a una copia aparte;
// nunca va en movil/ de la rama de trabajo.
const path = require('path');
const { getDefaultConfig } = require('expo/metro-config');
const config = getDefaultConfig(__dirname);
const sustitutos = {
  'expo-secure-store': path.resolve(__dirname, 'vista-web/secure-store.js'),
  'react-native-nitro-google-signin': path.resolve(__dirname, 'vista-web/google.js'),
};
const original = config.resolver.resolveRequest;
config.resolver.resolveRequest = (contexto, modulo, plataforma) => {
  if (plataforma === 'web' && sustitutos[modulo]) return { type: 'sourceFile', filePath: sustitutos[modulo] };
  return original ? original(contexto, modulo, plataforma) : contexto.resolveRequest(contexto, modulo, plataforma);
};
module.exports = config;
