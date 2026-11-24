// A dónde lleva un enlace que abre la app (expo-router, ADR-0017). Solo cambia uno: el regreso del checkout de la
// pasarela, <esquema de la app>://pago?resultado=…&pago=<cobro> (openspec/changes/pago, pago-E02 y pago-E08).
//
// - Con la app abierta, el regreso no navega. En Android, al terminar el checkout el sistema abre la app con ese enlace
//   y lo reciben a la vez expo-web-browser, que cierra la sesión de openAuthSessionAsync, y expo-router, que sin esto
//   abriría la ruta /pago, que no existe. La pantalla de pago ya lo recibió y pasa a la espera del cobro. En iPhone no
//   llega aquí: lo recibe la sesión de ASWebAuthenticationSession.
// - Si el enlace abre la app, porque el sistema la cerró mientras se veía el checkout, lleva a la espera de ese cobro,
//   que consulta el servidor. Sin cobro en el enlace, al arranque de la app.
//
// Regresar no confirma el pago (pago-E07): en los dos casos, lo que sigue lo dice el servidor. Los demás enlaces siguen
// igual.
import { cobroDelRegreso, direccionDeLaEspera } from '../src/pago/rutas';

export function redirectSystemPath({ path, initial }: { path: string; initial: boolean }): string | null {
  const cobro = cobroDelRegreso(path);
  if (cobro === undefined) {
    return path;
  }
  if (!initial) {
    return null;
  }
  return cobro ? direccionDeLaEspera(cobro) : '/';
}
