// Un enlace a una ruta que la app no tiene, como uno viejo a la entrada que ya no existe (C-237), no deja la pantalla en
// blanco: vuelve al arranque, que decide a dónde llevar a la persona (bienvenida-E01, E02 y E04; registro-E11).
import { Redirect } from 'expo-router';

export default function RutaDesconocida() {
  return <Redirect href="/" />;
}
