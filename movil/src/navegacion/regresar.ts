// El regreso y la pila de las pantallas de acceso (tarea 2.3, C-230; bienvenida-E04, C-237).
import { useCanGoBack, useRouter, type Href } from 'expo-router';

/**
 * "Regresar" en el encabezado: vuelve a la pantalla anterior, como el regreso de Android y el gesto de iPhone. Si la
 * pantalla se abrió sin una anterior, lleva a la que corresponde.
 */
export function useRegresar(siNoHayAnterior: Href): () => void {
  const router = useRouter();
  return () => {
    if (router.canGoBack()) {
      router.back();
    } else {
      router.replace(siNoHayAnterior);
    }
  };
}

/**
 * "Regresar" solo si hay a dónde: abierta como primera pantalla, no regresa a ningún lado. Es el caso del inicio de
 * sesión, la raíz sin sesión (bienvenida-E04, C-237); si se llegó a él desde otra pantalla, vuelve a ella.
 */
export function useRegresarSiHayAnterior(): (() => void) | undefined {
  const router = useRouter();
  const hayAnterior = useCanGoBack();
  return hayAnterior ? () => router.back() : undefined;
}

/**
 * Abre la pantalla como la primera de la pila: "atrás", el regreso de Android, ya no vuelve a las anteriores. Así
 * llegan el inicio de sesión al terminar las informativas y al cerrar la sesión (bienvenida-E04 y registro-E13; DD-131,
 * punto 3, por delegación, C-236), y el inicio al entrar (registro-E09).
 */
export function useAbrirComoPrimera(): (destino: Href) => void {
  const router = useRouter();
  return (destino) => {
    if (router.canDismiss()) {
      router.dismissAll();
    }
    router.replace(destino);
  };
}
