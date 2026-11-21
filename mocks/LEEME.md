# Mocks de Lavandi

Prototipo navegable de las pantallas del producto, para ir viendo todo desde los mocks. Lo pidió Arthur Naranjo
el 2026-11-18 (C-167; nombre, carpeta y rama según C-174 y C-175; `vault/meta/actas/acta-2026-11-18-entorno-y-mocks.md`,
decisiones 2 y 3).

**No es la app.** Es HTML, CSS y JavaScript sin dependencias ni compilación. No se conecta a ningún servidor, a
Conekta, a mapas ni a avisos reales. No es una spec ni una implementación: no cuenta como evidencia de que algo
esté construido.

## Cómo se abre

1. Doble clic en `index.html`. Se abre en el navegador como archivo (`file://`), sin servidor.
2. "Empezar el recorrido" lleva a la primera pantalla. Desde ahí, cada botón lleva a la siguiente.
3. Las fuentes (Nunito y Source Sans 3) vienen de Google Fonts. Sin internet todo funciona igual, con la
   tipografía del sistema.

Arriba de cada pantalla hay una barra del mock:

- **Catálogo**, **Anterior** y **Siguiente**, en el orden del recorrido del módulo A.
- **iPhone / Android**: cambia lo que depende de la plataforma (el inicio de sesión con Apple solo aparece en
  iPhone, ADR-0005; el mapa se rotula como de Apple o de Google, ADR-0009).
- **Ocultar anotaciones**: esconde el panel de la derecha.

Lo que se elige en el recorrido (lavandería, servicio, ventana, dirección, estado del pedido, variantes) se
guarda en el navegador de quien mira. "Reiniciar los datos del mock", al pie del catálogo, lo borra.

## Qué hay en la carpeta

| Ruta | Qué es |
|---|---|
| `index.html` | Catálogo de todas las pantallas del producto, por módulo, con su estado: `hecha` o `por hacer`. La lista sale de `vault/contrato/inventario-funcional.md` |
| `comun/tokens.css` | Los tokens de `vault/design-system/tokens.json` como variables CSS, **escritos a mano** (lo dice su encabezado). Solo modo claro |
| `comun/estilos.css` | Marco del teléfono, panel de anotaciones, controles y los componentes del design system |
| `comun/app.js` | Iconos, barra del mock, plataforma, variantes, el pedido de ejemplo, avisos simulados y el dibujo del mapa |
| `usuario/` | Una página por pantalla del módulo A (20 pantallas para las 17 funciones) |
| `LEEME.md` | Este archivo |
| `Lavandi - Propuesta 0X/` | Imágenes y prompts de propuestas visuales de Arthur. No son parte de estos mocks navegables ni las genera su código |

## Reglas

1. **Nombre.** El nombre visible de la app es **Lavandi** (C-174, que en esa parte reemplaza el "Desarrollo
   Temsa L" de C-167; el diseño de Claude Design conserva ese nombre). **No se usa el logotipo** ni ningún archivo de
   `vault/marca/`: en su lugar va un monograma neutro "L". La nota de decisión de marca sigue en borrador
   (DD-023, DD-030), así que en el código del producto el nombre irá como variable de configuración
   (`CLAUDE.md` §5). En los mocks está en una sola constante: `MARCA`, en `comun/app.js`, más los textos
   fijos de la bienvenida (A01), el inicio de sesión (A02), la página de Conekta simulada (A10) y el permiso de
   avisos (A17). El monograma "L" está en esas mismas pantallas, en la barra del mock y en los avisos.
2. **No se inventa el negocio.** Lavanderías ("Lavandería Ejemplo Centro"…), personas, folios ("EJ-000123"),
   distancias, calificaciones, horarios y reseñas son ficticios. Los precios van como **"$ —"**: "Aún no hay
   precios" (C-131). La única cifra con fuente es el **envío de $35**: "El reparto lo paga el usuario: $35 MXN
   mínimo" (C-31, `vault/meta/actas/acta-2026-09-07-fundacion.md:48`); su estructura sigue abierta (DD-019,
   DD-025) y se marca así en la pantalla. La libreta de precios recibida el 2026-11-14
   (`vault/negocio/lista-de-precios-recibida-2026-11-14.md`) **no** se usa: es un borrador por confirmar, de una
   lavandería sin identificar (DD-066), y la propia nota dice que no decide precio, unidad ni catálogo. Cada
   pantalla lleva la cinta **"Mock · datos de ejemplo"**.
3. **Design system.** Colores, tipografía, espacios y radios salen de `tokens.json`. Los componentes consumen
   solo la capa semántica (`--c-*`), nunca un color de marca directo. Solo modo claro (C-141). Objetivos
   táctiles de 48 px o más. Se reutilizan los seis componentes de `vault/design-system/componentes.md`; el
   módulo A usa cuatro (tarjeta de lavandería, selector de ventanas, seguimiento y resumen). Los dos de los
   socios esperan a los módulos B y C.
4. **Técnica.** Sin dependencias, sin compilación, sin llaves ni servicios externos (salvo Google Fonts). El
   mapa es un dibujo SVG con pines.
5. **Decisiones tomadas, respetadas.** Inicio de sesión con correo, Google y Apple solo en iPhone (ADR-0005);
   pago en el checkout redirigido de Conekta, la app nunca pide el número de tarjeta y el pago se confirma
   por webhook (ADR-0010); seguimiento con consulta cada 30 s más aviso (ADR-0007) y sin mapa; mapas nativos
   por plataforma (ADR-0009); avisos por Firebase Cloud Messaging (ADR-0008).
6. **Lo pendiente se ve.** Cada pantalla tiene un panel con la función y su cita del contrato, los
   componentes, las decisiones tomadas y lo pendiente con su fila (DD de `vault/meta/deuda-de-decision.md`, BR
   de `vault/contrato/brecha-ejecutivo-tecnico.md`).
7. **Recomendación no es decisión.** Cuando el vault tiene una recomendación escrita, el panel ofrece una
   variante para verla. Siempre abre con lo que dice el contrato o lo que ya decidió un ADR.

## Variantes disponibles

| Grupo | Dónde | Opciones | Fila |
|---|---|---|---|
| Entrada de socios | A02 registro | Solo usuario · con entrada de socios (recomendación) | DD-053 |
| Cuándo se cobra | A09, A10 | Al pedir · al pesar · apartar (opciones del canvas del Resumen, ninguna recomendada) | DD-012 |
| Efectivo | A10 | OXXO Pay como dice el contrato · (a) efectivo de Conekta (recomendación) · (c) solo tarjeta | DD-056 |
| Tarjeta guardada | A10, A15 | Cobrar cada vez · tarjeta guardada como referencia (recomendación) | DD-055 |
| A quién se califica | A14 | Una calificación (contrato) · lavandería y repartidor (recomendación) | DD-052 |

Además: A12 trae un simulador para avanzar el pedido por los cinco estados, y A17 deja ver los avisos en la
pantalla de bloqueo o la petición de permiso.

## Qué está hecho

Módulo A completo: 17 de 17 funciones, en 20 pantallas.

## Qué falta

- **Módulo B, socio lavandería** (F-B01 a F-B06): usa los componentes 4 (tarjeta de pedido de lavandería) y 3.
- **Módulo C, socio repartidor** (F-C01 a F-C05): usa el componente 5.
- **Módulo D, administración** (F-D01 a F-D07): es web (DD-040 cerrada, C-140), más una vista reducida en la
  app. Necesita otro marco: pantalla de escritorio, no teléfono.
- **Landing** (F-L01 a F-L12) y **los 11 indicadores** (F-I01 a F-I11).
- En el módulo A, lo que el contrato no define y por eso no se dibujó: el estado de un pedido cancelado o
  rechazado (BR-22), el flujo de cancelación (DD-016), el mensaje de "fuera de zona" (BR-18), el quinto momento
  de aviso (DD-011), la verificación del correo (BR-14) y los textos finales de bienvenida, avisos, aviso de
  privacidad y términos (insumos de EL CLIENTE).

## Cómo se agrega una pantalla

1. Copiar una pantalla de `usuario/` como base: ya trae el marco, la cinta, la barra de estado y el panel.
2. Poner su identificador en `<body data-pantalla="...">` y agregarla al arreglo `RECORRIDO` de `comun/app.js`
   (si es del módulo A) para que salga en "anterior / siguiente".
3. Llenar el panel: función con su cita literal del inventario, componentes, ADR que respeta y pendientes con
   su fila. Una recomendación del vault va como variante, nunca como decisión.
4. Marcarla como hecha en `index.html` (el arreglo `MODULOS`).

Si `vault/design-system/tokens.json` cambia, `comun/tokens.css` se actualiza a la par.
