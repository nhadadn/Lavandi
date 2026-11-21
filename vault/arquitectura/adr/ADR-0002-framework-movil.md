---
estado: vigente
grado: D
fuente: acta-2026-11-12-traspaso-del-proyecto.md C-144; matriz-de-criterios-stack.md (pesos C-142); verificaciones-fase-6.md, secciones del 2026-10-09 y del 2026-11-12 (E)
fecha: 2026-11-12
dueño: Arthur Naranjo
adr: ADR-0002
supersede:
---
# ADR-0002 — La app móvil se hace con React Native y Expo

## Contexto

- **Qué pide el contrato (A):**
  - una sola app para iPhone y Android, con comportamiento idéntico (RN-01, RN-02);
  - mapas de Google en Android y de Apple en iPhone (RN-29);
  - Conekta (RN-28);
  - avisos con el servicio gratuito de Google, e inicio de sesión con Google;
  - compatibilidad "con la gran mayoría de los teléfonos" (BR-42).
- **La forma de construir (D):** se construye sobre piezas probadas (`ADR-0015`).
- **El equipo (C):** Arthur domina Laravel, la web con Vite y las PWA, y ha hecho Android nativo en Java con Gradle.
  No ha hecho iPhone (C-135) y **no tiene Mac** (C-137).
- **Las horas (C, S):** 14 h por semana con agentes (C-134), contra 389 h estimadas (S-006).

## Criterios y pesos

Son los de `matriz-de-criterios-stack.md`, fijados el 2026-11-12 (C-142): familiaridad 30, velocidad 25, proveedor
único y ruta de salida 15, tiempo real 10, costo 10 y multi-plaza 10.

**Escala:** de 1 a 5 por criterio. El total ponderado es la suma de peso × puntaje / 5, sobre 100. Los puntajes los
propuso Claude con datos grado E consultados el 2026-11-12, y los aceptó Arthur.

## Alternativas evaluadas

**Puertas:**

| Candidato | Una app | Mapas | Conekta | Resultado |
|---|---|---|---|---|
| React Native con Expo | pasa | pasa | pasa, con riesgo | Se puntúa |
| Capacitor | pasa | incierto | incierto | Se puntúa |
| Flutter | pasa | incierto | incierto | Se puntúa |
| .NET MAUI | pasa | pasa | incierto | **Fuera:** compilar para iPhone exige Mac |
| Kotlin / Compose Multiplatform | pasa | incierto | incierto | **Fuera:** iPhone exige macOS con Xcode |

**Puntaje:**

| Candidato | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **React Native con Expo** | 3 | 4 | 4 | 4 | 4 | 5 | **76** |
| Capacitor | 4 | 3 | 3 | 4 | 4 | 5 | **74** |
| Flutter | 1 | 2 | 3 | 4 | 4 | 5 | **51** |

**Por qué cada puntaje:**

- **Familiaridad.**
  - Expo 3: es JavaScript con TypeScript y Android compila con Gradle, pero React es nuevo para Arthur.
  - Capacitor 4: reaprovecha la web, Vite y lo de las PWA.
  - Flutter 1: Dart y sus widgets son nuevos y no reutiliza nada.
- **Velocidad.**
  - Expo 4: mapas por plataforma maduros (`react-native-maps` 1.29.11, MIT), avisos maduros
    (`@react-native-firebase/messaging`) y compilación de iPhone en la nube (EAS Build).
  - Capacitor 3: el mapa de Apple depende de un solo plugin y Conekta dentro del WebView no está documentado, así
    que hay riesgo de rehacer.
  - Flutter 2: el plugin del mapa de Apple está en "developers preview" y lleva unos 21 meses sin cambios; Conekta no
    tiene SDK.
- **Proveedor y ruta de salida.**
  - Expo 4: todo es MIT y hay más de un servicio de compilación.
  - Capacitor 3: el mapa de Apple depende de `@capgo/capacitor-native-map`, de un solo proveedor, creado el
    2026-10-18, con 2 estrellas y licencia MPL-2.0.
  - Flutter 3: depende de plugins de mantenedores sueltos.
- **Tiempo real, costo y multi-plaza.** No distinguen entre candidatos, porque los decide el backend.
  - Tiempo real: los tres pueden consultar periódicamente y recibir avisos.
  - Costo: los mapas nativos no tienen costo y todos tienen un nivel gratuito de compilación en la nube.

**Decide la puerta, no los 2 puntos.** Expo y Capacitor quedan casi empatados. Lo que inclina la balanza es el
mapa de Apple: Expo lo cumple con librerías maduras, y Capacitor lo apoya en un solo plugin de 25 días. Si ese
plugin se abandona, se cae una obligación del contrato.

## Decisión

**La app única (módulos A, B y C, más la vista reducida de administración de C-140) se hace con React Native y
Expo, en TypeScript.**

- Se trabaja con *development builds*, no con Expo Go.
- La compilación y el envío a las tiendas se hacen en la nube con EAS Build y EAS Submit, sin Mac.

La decidió Arthur Naranjo el 2026-11-12 (`acta-2026-11-12-traspaso-del-proyecto.md`, C-144), con la opción que
recomendó Claude.

## Consecuencias

- **Positivas:**
  - Se cumplen las tres puertas.
  - Una sola base de código.
  - Se compila para iPhone sin Mac.
  - Los agentes conocen bien el ecosistema.
- **Negativas, y qué se hace con ellas:**
  - **Arthur tiene que aprender React.** Lo mitigan los agentes y la prueba temprana.
  - **El envoltorio oficial de Conekta para React Native está en la versión 0.0.1** (2026-03-03), mientras sus SDK
    nativos van en la 1.2.0. Como respaldo está el checkout redirigido de Conekta (`HostedPayment`), que confirma el
    pago por webhook. Se decide en el ADR-0010.
  - **El Google Sign-In gratuito para React Native se apoya en un SDK de Android en desuso;** la versión recomendada
    es de pago. Se decide en el ADR-0005.
  - **El plan gratuito de EAS** da 15 compilaciones de iPhone y 15 de Android, con cola lenta. El plan Starter
    cuesta 19 USD al mes. Es costo de desarrollo, no de la infraestructura del ANEXO B.4.
- **Condiciona:**
  - ADR-0005: inicio de sesión con Google y con Apple.
  - ADR-0008: avisos con FCM.
  - ADR-0009: `react-native-maps` o `expo-maps`, que exige iOS 17. Ahí se fijan las versiones mínimas de BR-42.
  - ADR-0010: Conekta.
  - ADR-0012: EAS y los entornos.
  - ADR-0013: adaptador de tokens a React Native.

## Criterio de reversión

Se reabre si la **prueba temprana en iPhone** (mapa de Apple, aviso recibido y versión en TestFlight; DD-069) no
sale en 3 sesiones de trabajo. También se reabre si **ninguna de las dos vías de Conekta** (envoltorio oficial o
checkout redirigido) logra cobrar en el sandbox.

## Costo estimado de revertir

- **Antes de la prueba temprana:** casi nulo.
- **Después de la primera semana de desarrollo:** crece con cada pantalla, porque las pantallas no se trasladan
  entre frameworks. La API y la lógica del backend no se pierden.

## Trazabilidad

- **Obligaciones:** O-12 a O-15, O-17, O-19.
- **Restricciones:** RN-01, RN-02, RN-28, RN-29 y BR-42.
- **ADRs relacionados:** ADR-0015, ADR-0003, ADR-0005, ADR-0008 a 0010, ADR-0012 y ADR-0013.
