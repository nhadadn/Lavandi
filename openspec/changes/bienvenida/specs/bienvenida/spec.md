## ADDED Requirements

### Requirement: Pantalla de bienvenida

La app DEBE (MUST) mostrar una pantalla de bienvenida con el nombre del producto tomado de la configuración y con el
contenido que le toca en el archivo de contenido.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (Módulo A #1) · C → C-225 (bienvenida-E02)

#### Scenario: bienvenida-E01 — La primera apertura, sin sesión, empieza en la bienvenida

- **DADO** la app recién instalada y sin sesión
- **CUANDO** la persona la abre
- **ENTONCES** ve la pantalla de bienvenida
- **Y** la pantalla lleva el nombre del producto de la configuración y el contenido de la bienvenida del archivo de
  contenido

Criterio: A43-6 (en parte: F-A01)

#### Scenario: bienvenida-E02 — Las aperturas siguientes y con la sesión abierta

- **DADO** que la persona ya vio la bienvenida y las tres informativas
- **CUANDO** vuelve a abrir la app sin sesión
- **ENTONCES** llega directo a registrarse o iniciar sesión, sin la bienvenida
- **Y** si tiene la sesión abierta, entra directo, sin la bienvenida ni el inicio de sesión

### Requirement: Tres pantallas informativas sobre el servicio

Después de la bienvenida, la app DEBE (MUST) mostrar tres pantallas informativas sobre el servicio, en orden, y al
terminar la tercera DEBE (MUST) ofrecer registrarse o iniciar sesión.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (Módulo A #1 y #2)

#### Scenario: bienvenida-E03 — Se recorren las tres en orden

- **DADO** la pantalla de bienvenida
- **CUANDO** la persona avanza
- **ENTONCES** ve la primera pantalla informativa, luego la segunda y luego la tercera
- **Y** cada una muestra el contenido que le toca en el archivo de contenido

Criterio: A43-6 (en parte: F-A01)

#### Scenario: bienvenida-E04 — Al terminar, registrarse o iniciar sesión

- **DADO** la tercera pantalla informativa
- **CUANDO** la persona avanza
- **ENTONCES** la app le ofrece registrarse o iniciar sesión

La oferta es la pantalla de inicio de sesión, con su enlace a registrarse, como en el mock; no hay una pantalla de
entrada aparte (C-237).

### Requirement: Contenido de EL CLIENTE fuera del código

Los textos y las imágenes de la bienvenida y de las tres informativas DEBEN (MUST) salir de un archivo de contenido de
la app, no de las pantallas. Son insumo de EL CLIENTE: mientras no lleguen, el contenido es provisional y la función
no se acredita.

Cita: A → `NSV-2026-001-v8.pdf:6:Cláusula III, inciso B` y `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (Módulo A #1)

#### Scenario: bienvenida-E05 — Cambiar el contenido no toca las pantallas

- **DADO** un archivo de contenido de prueba con otros textos e imágenes
- **CUANDO** se muestran la bienvenida y las tres informativas
- **ENTONCES** aparecen los textos y las imágenes del archivo de prueba
- **Y** los componentes de las pantallas no traen ningún texto del servicio escrito a mano

#### Scenario: bienvenida-E06 — El contenido final de EL CLIENTE

- **DADO** los textos y las imágenes que entregue EL CLIENTE
- **CUANDO** se cargan en el archivo de contenido
- **ENTONCES** la bienvenida y las tres informativas los muestran y F-A01 se puede acreditar

SIN RESPUESTA — bloquea: bienvenida-E06 (DD-081)
