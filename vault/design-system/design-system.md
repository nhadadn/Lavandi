---
estado: vigente
grado: mixto
fuente: prompt-maestro-01-fundacion-v4.md, Fase 4 (C); ANEXO A.1 y A.3 del contrato v8 (A); WCAG 2.1 AA (E); tokens.json (B); modo de la v1 por acta-2026-11-12-traspaso-del-proyecto.md C-141 (C)
fecha: 2026-11-12
dueño: Nadir Hadad Navarrete
---
# Design system de Lavandi

Fuente única que alimenta app, landing y plantillas sociales. Definirlo tres veces por separado es
como se pierden dos semanas.

`estado: vigente` desde el 2026-09-12. Estructura, paleta, tipografías y los seis componentes
dibujados: todo cerrado. La Fase 4 queda completa.

## La trampa de contraste que hay que conocer antes de tocar nada

El azul de marca **Agua `#2E86AB` no se puede usar para texto**: da **3.74:1** sobre Hueso y el
mínimo es 4.5. Va en superficies grandes, franjas e ilustración. Para texto, enlaces y botones se
usa **Agua Profundo `#297899`**, que da **4.51:1**.

Ese 4.51 pasa por **una centésima**. Cualquier retoque de Agua Profundo o de Hueso rompe el
criterio de aceptación, y es un retoque que un diseñador haría sin pensarlo por razones estéticas.
Conviene decírselo por escrito al diseñador que el cliente va a poner (C-63) antes de que empiece.

El **Limón `#E8C547` nunca es color de texto**: sobre Hueso da 1.53:1. **Como fondo sí funciona**, con texto en
Tinta `#171C21` encima, que da 10.22:1. Lo que no se pone nunca sobre Limón es texto blanco o hueso: 1.68:1.

Nota de corrección, 2026-09-17: hasta esta fecha el vault decía que el Limón "nunca lleva texto encima". Era
demasiado restrictivo y contradecía `tokens.json`. Lo que falla es el Limón como texto, no el texto oscuro sobre él.

## Qué está decidido y qué espera

| Pieza | Estado | Por qué |
|---|---|---|
| Estructura de tokens, escalas y nombres | **cerrada** | No depende de la marca |
| Modo claro y oscuro | **solo claro en la v1** | Decidido el 2026-11-12 (C-141, cierra DD-039). Ver abajo |
| Umbrales de accesibilidad | **cerrados y no renegociables** | El método lo exige así (`prompt-maestro:242`) |
| Los seis componentes base | **especificados y dibujados** | `componentes.md` y el canvas de `canvas/` |
| Paleta de 4 colores y tipografías de marca | **cerradas el 2026-09-12** | Paleta "El tiempo de vuelta" (C-55) y Nunito + Source Sans 3. Ver `marca/decision-visual.md` |
| Adaptador al stack | **espera** a la Fase 6 | Tiene su propio ADR |

## Decisión 1 — Modo claro y oscuro, los dos desde la v1

El método obliga a decidirlo en esta fase y no después. **Se implementan los dos.**

El motivo no es estético sino de costo: los componentes ya consumen una capa semántica, así que
soportar el segundo modo hoy cuesta un segundo bloque de doce valores en `tokens.json`. Añadirlo
después obliga a revisar cada componente uno por uno para encontrar los colores que alguien escribió
a mano. El trabajo extra hoy es de horas; el retrofit es de días.

Si Nadir decide solo claro (DD-039), se borra el bloque `color.semantico.oscuro` y nada más:
ningún componente cambia. Esa asimetría es exactamente la razón de decidirlo en este orden.

> **Decisión del 2026-11-12 (C-141): la v1 lleva solo modo claro.** La tomó Arthur Naranjo, que lleva la parte
> técnica desde el traspaso (C-132), para ahorrar horas: cada pantalla se diseña y se prueba una vez.
>
> El bloque `color.semantico.oscuro` **no se borra**. Se queda en `tokens.json` sin usarse en la v1. Así, agregar el
> oscuro después cuesta lo mismo que hoy, porque los componentes ya consumen la capa semántica.
>
> La app de la v1 no ofrece el selector de modo ni sigue `prefers-color-scheme`. La muestra HTML conserva los tres
> modos como referencia.

**Cómo se resuelve el modo:** claro, oscuro, o el del sistema operativo. La muestra implementa los
tres y respeta `prefers-color-scheme`.

## Decisión 2 — Accesibilidad, con número y sin adjetivos

Fijados aquí. **No se renegocian en la Fase 5 ni en la 6.**

| Umbral | Valor | Se aplica a |
|---|---|---|
| Contraste de texto normal | **4.5:1** | Todo texto por debajo de 18.66 px |
| Contraste de texto grande | **3:1** | Títulos desde 18.66 px |
| Contraste de borde de control | **3:1** | Campos, casillas, indicador de foco |
| Área táctil mínima | **48 dp** (44 pt en iPhone) | Todo objetivo tocable |
| Separación entre objetivos | **8 dp** | Botones contiguos |

Origen: WCAG 2.1 nivel AA, más Material Design y Apple HIG para el área táctil (grado E, consultado
2026-09-11, `SIN VERIFICAR` en cuanto a versión vigente del documento).

**Por qué importa contractualmente, aunque el contrato no lo pida.** El criterio A.4.3 exige que la
app sea "compatible con la gran mayoría de los teléfonos actualmente en uso". Un objetivo táctil por
debajo de 48 dp falla en los teléfonos de pantalla chica, que son justo los que más abundan en el
territorio. El umbral no es un lujo: es lo que hace cierta esa frase.

## Decisión 3 — Capa semántica obligatoria

Ningún componente referencia un color de marca directamente. Todos pasan por
`color.semantico.<modo>`. Tres consecuencias, y las tres son el punto:

1. Cuando llegue la paleta del ANEXO A.1, se cambian **cuatro valores** y no se toca un componente.
2. El modo oscuro es un segundo diccionario, no un segundo design system.
3. Un componente no puede "inventar" un color: si necesita uno que no existe, se agrega al
   diccionario y se mide su contraste antes de usarlo.

## Verificación de contraste — medida, no afirmada

Los 38 pares de color se calcularon con la fórmula de luminancia relativa de WCAG 2.1.
**Los 38 cumplen.**

La primera corrida **falló en dos**: `borde_fuerte` daba 1.72:1 en claro y 2.45:1 en oscuro contra
su superficie, por debajo del 3:1 que exige un borde de control. Se corrigieron los dos valores
(a `#6B7681` y `#98A1AC`, pasos que ya existían en la rampa neutra) y se volvió a medir. Queda
anotado porque es la prueba de que la verificación sirve para algo: esos dos colores se veían bien
y estaban mal.

### Resultados completos

| Modo | Par | Ratio | Umbral | Resultado |
|---|---|---|---|---|
| claro | texto / fondo | **16.13** | 4.5 | cumple |
| claro | texto / superficie | **17.15** | 4.5 | cumple |
| claro | texto / superficie_elevada | **17.15** | 4.5 | cumple |
| claro | texto_suave / superficie | **7.01** | 4.5 | cumple |
| claro | texto_suave / fondo | **6.59** | 4.5 | cumple |
| claro | texto_deshabilitado / superficie | **4.63** | 3.0 | cumple |
| claro | accion_texto / accion | **7.22** | 4.5 | cumple |
| claro | accion / superficie | **7.22** | 4.5 | cumple |
| claro | borde_fuerte / superficie | **4.63** | 3.0 | cumple |
| claro | foco / superficie | **11.23** | 3.0 | cumple |
| claro | estado exito / superficie | **6.15** | 4.5 | cumple |
| claro | estado atencion / superficie | **5.93** | 4.5 | cumple |
| claro | estado error / superficie | **7.48** | 4.5 | cumple |
| claro | estado informacion / superficie | **7.08** | 4.5 | cumple |
| claro | pedido confirmado / superficie | **7.08** | 4.5 | cumple |
| claro | pedido recolectado / superficie | **7.48** | 4.5 | cumple |
| claro | pedido en_proceso / superficie | **5.93** | 4.5 | cumple |
| claro | pedido listo / superficie | **6.15** | 4.5 | cumple |
| claro | pedido entregado / superficie | **7.01** | 4.5 | cumple |
| oscuro | texto / fondo | **17.68** | 4.5 | cumple |
| oscuro | texto / superficie | **16.13** | 4.5 | cumple |
| oscuro | texto / superficie_elevada | **13.25** | 4.5 | cumple |
| oscuro | texto_suave / superficie | **9.97** | 4.5 | cumple |
| oscuro | texto_suave / fondo | **10.93** | 4.5 | cumple |
| oscuro | texto_deshabilitado / superficie | **6.56** | 3.0 | cumple |
| oscuro | accion_texto / accion | **9.12** | 4.5 | cumple |
| oscuro | accion / superficie | **8.31** | 4.5 | cumple |
| oscuro | borde_fuerte / superficie | **6.56** | 3.0 | cumple |
| oscuro | foco / superficie | **11.29** | 3.0 | cumple |
| oscuro | estado exito / superficie | **9.21** | 4.5 | cumple |
| oscuro | estado atencion / superficie | **9.53** | 4.5 | cumple |
| oscuro | estado error / superficie | **7.51** | 4.5 | cumple |
| oscuro | estado informacion / superficie | **8.81** | 4.5 | cumple |
| oscuro | pedido confirmado / superficie | **8.81** | 4.5 | cumple |
| oscuro | pedido recolectado / superficie | **8.40** | 4.5 | cumple |
| oscuro | pedido en_proceso / superficie | **9.53** | 4.5 | cumple |
| oscuro | pedido listo / superficie | **9.21** | 4.5 | cumple |
| oscuro | pedido entregado / superficie | **9.97** | 4.5 | cumple |

## Adaptador al stack — Fase 6

`tokens.json` es deliberadamente agnóstico: valores numéricos crudos, sin unidades atadas a
ninguna plataforma. Los píxeles del bloque tipográfico son la unidad de referencia; el adaptador los
traduce a lo que pida el stack (sp y dp en Android, pt en iOS, rem en web).

La muestra incluye un **adaptador mínimo de demostración**, en JavaScript, que recorre el JSON y
escribe variables CSS. No es el adaptador definitivo: sirve para probar que el JSON alcanza para
pintar una interfaz completa sin escribir un color a mano. El generador real y su formato de salida
son un ADR de la Fase 6, ya previsto en la lista del método.

## Dónde están dibujados

Los seis componentes viven como artboards en `canvas/`, uno por componente más el de cimientos,
cada uno con interruptor de modo claro y oscuro. Los archivos de trabajo se versionan aquí; el
empaquetado se regenera desde ellos y no se guarda en git.

## Cómo se mira esto

Se abre `muestra/index.html` en cualquier navegador. No necesita servidor: los tokens van embebidos
en el archivo.

Una precisión, para no afirmar de más: desde el 2026-09-12 la página **pide Nunito y Source Sans 3
a Google Fonts**, así que ya no es estrictamente autocontenida. Sin conexión se ve completa y
correcta, pero con la tipografía del sistema en lugar de la de marca.

## Lo que este design system NO cubre

Ver `cobertura-anexo-a1.md`. En corto: cubre lo digital (color, tipografía, espaciado, componentes),
y **no** cubre el logotipo en cuatro versiones, el manual de 15 a 20 páginas ni las 12 plantillas
sociales. Eso es trabajo de diseño gráfico, no de sistema, y hoy no tiene quién lo haga (R-011).
