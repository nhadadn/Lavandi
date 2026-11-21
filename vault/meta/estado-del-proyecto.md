---
estado: vigente
grado: mixto
fuente: hechos del repo al 2026-11-12 (B), con cita en cada fila; declaraciones de Nadir y, desde el traspaso, de Arthur en las actas (C); calendario y obligaciones del contrato (A)
fecha: 2026-11-18
dueño: Arthur Naranjo
---
# Estado del proyecto

**La fuente del tablero.** Esta nota dice en qué estado está cada fase, cada actividad del calendario,
cada obligación, cada pago y cada trámite, con la evidencia de cada uno. El tablero se arma con
`herramientas/tablero/construir_tablero.py`, que lee esta nota y además la deuda de decisión, los
riesgos, los supuestos, las actas, el calendario contractual y el inventario funcional.

**Cómo se mantiene.** Cuando algo cambia, se cambia aquí, con su evidencia, en la misma sesión en que
pasa. Después se vuelve a correr el generador y se sube el resultado al tablero. Nada se cambia
directamente en el tablero: es un espejo del vault, como Notion.

**Los estados son un vocabulario fijo**, para que el tablero los pueda colorear. Si hace falta uno nuevo,
se agrega al generador y aquí, en la misma sesión.

- Fases: `cerrada` · `cerrada con pendientes` · `en curso` · `detenida` · `sin empezar`
- Actividades del calendario: `cumplida` · `en curso` · `atrasada` · `no ha empezado` · `por venir`
- Obligaciones: `completa` · `casi completa` · `en curso` · `sin empezar` · `sin registro` · `no aplica todavía`
- Pagos: `acreditado` · `pendiente`
- Trámites: `completo` · `iniciado` · `sin registro`

**"Sin registro" no quiere decir "no se hizo".** Quiere decir que en el vault no hay evidencia de que se
haya hecho. Si se hizo, se asienta en un acta y aquí cambia el estado.

## Fases del cimiento

| Fase | Nombre | Estado | Evidencia |
|---|---|---|---|
| 0 | Espacio de trabajo | cerrada | Repo, vault, `CLAUDE.md` y git; publicado en GitHub, privado (commit `0c99e21`) |
| 1 | Lectura ejecutiva del contrato | cerrada | `contrato/obligaciones-ejecutivas.md`, `brecha-ejecutivo-tecnico.md` y `restricciones-no-negociables.md`, los tres `vigente` |
| 2 | Negocio | cerrada con pendientes | Propuesta de valor, competencia, unit economics e hipótesis, en `borrador`. Abiertos el método de precio (DD-010) y el sondeo de precios (DD-026) |
| 3 | Marca | cerrada con pendientes | Nombre aprobado por escrito (C-65); manual 1.2 sin observaciones (C-103); guía de tono. Faltan las 12 plantillas (O-04), el `.ai` del símbolo, la búsqueda ante el IMPI (DD-030) y la licencia de la fuente del logotipo (DD-060) |
| 4 | Design system | cerrada | `design-system/design-system.md` `vigente`; tokens, componentes y muestra. DD-039 cerrada el 2026-11-12: la v1 lleva solo modo claro (C-141). El adaptador de tokens espera al ADR-0013 |
| 5 | Arquitectura C4 | cerrada con pendientes | C1, C2 y C3 en `borrador`; ADR-0001. DD-040 cerrada el 2026-11-12: panel web completo, más una vista reducida de administración en la app (C-140). Falta la máquina de estados del pedido (C3) |
| 5-bis | Análisis funcional | cerrada | `contrato/inventario-funcional.md` `vigente`: 76 funciones. Su veredicto, con la estimación media: **no está listo** (§7) |
| 6 | Stack | cerrada con pendientes | La lleva Arthur Naranjo desde el traspaso (C-132; acordado con Nadir, C-143). Matriz `vigente` con los pesos del 2026-09-25 (C-142). **13 de los 14 ADRs están `vigente`** (`arquitectura/adr/`): construir sobre piezas probadas (0015); React Native con Expo (0002); Laravel con Filament (0003); PostgreSQL con PostGIS (0004); correo y contraseña, Google con Credential Manager y Apple (0005); fotos en S3 privado (0006); consulta cada 30 s más aviso (0007); FCM directo (0008); `react-native-maps` con iOS 18 y Android 11 como mínimos (0009); checkout redirigido de Conekta (0010); landing en el mismo Laravel (0011); tres entornos (0012); script de tokens (0013). Pendientes: correo, `ADR-0014` en borrador (DD-071); hosting (DD-070); prueba en iPhone (DD-069); OXXO (DD-056); los cinco momentos de aviso (DD-011) |
| 7 | Formas de trabajo | cerrada con pendientes | La cerró Arthur el 2026-11-17 (C-155). Nueve notas en `formas-de-trabajo/` (índice: `formas-de-trabajo/formas-de-trabajo.md`): ciclo openspec + TDD, Definition of Ready y Definition of Done, trazabilidad de los 19 criterios del A.4 más las 4 medidas de seguridad, sprint de una semana (C-157), el código en este mismo repositorio (C-169, que reemplazó al repositorio aparte de C-156), PR a Nadir por sprint (C-158), integración continua y registro de horas. La integración continua del vault (`vault.yml`) y la del código (`ci.yml`) corren en este repositorio. Pendientes: los protocolos con el cliente, en borrador hasta que los apruebe Nadir (DD-074), y el espejo a Notion (DD-075) |
| 8 | Instrumental de Claude | cerrada | La cerró Arthur el 2026-11-18 (C-161). Seis agentes en `.claude/agents/` (especificador, implementador-servidor, implementador-movil, revisor, verificador-de-aceptacion y redactor-cliente), cuatro skills en `.claude/skills/` (arranque-de-sesion, cierre-de-sesion, cierre-de-sprint y asentar-acta) y el protocolo de traspaso en `formas-de-trabajo/equipo-de-agentes.md`. Skills externas elegidas para el sprint 1: Laravel Boost con Filament, el plugin de Expo, la skill `react-native-testing` y el MCP de Maestro (C-163). **Con esta fase cierra el cimiento:** desde aquí se escribe código del producto, en `movil/` y `servidor/` de este repositorio. Se ajustan con el uso en el sprint 1 |

## Actividades del calendario contractual

Las actividades y sus semanas son las de la Cláusula II (`contrato/calendario-contractual.md`). El nombre
de cada actividad tiene que coincidir con el de esa nota: el generador las cruza por nombre.

| Actividad | Estado | Evidencia |
|---|---|---|
| Kickoff, levantamiento de información, arranque de trámites B.10 a cargo de EL CLIENTE | cumplida | Kickoff y levantamiento: acta del 2026-09-07. Los trámites del B.10 no tienen registro: ver abajo |
| Branding e identidad visual (A.1: 3 + 4 + 2 + 3 días = 12 días) | atrasada | La ventana cerró el 27 de septiembre. El manual quedó sin observaciones el 30 (C-103), pero faltan las 12 plantillas, el `.ai` del símbolo y la notificación de entrega (DD-050) |
| Diseño de la experiencia y pantallas | atrasada | Hay design system y seis componentes base (Fase 4), pero no hay pantallas diseñadas en el vault. La ventana cerró el 11 de octubre |
| Desarrollo de la aplicación, primera iteración | atrasada | Su ventana (28 de septiembre al 8 de noviembre) cerró sin que empezara. El método prohíbe escribir código hasta cerrar las Fases 6 a 8. La recalibración propone construir por etapas, con calendario nuevo firmado (`meta/recalibracion-2026-10-30.md` §3) |
| Desarrollo, segunda iteración | atrasada | Su ventana empezó el 2 de noviembre y no ha arrancado, porque depende de la primera |
| Landing page y plantillas de redes sociales | no ha empezado | Su ventana corre del 9 al 29 de noviembre. Las plantillas se adelantaron (C-73) y no han empezado. Para la landing, Nadir quiere comprar una plantilla, que se evalúa en el ADR-0011 (C-95) |
| Panel de indicadores del negocio | por venir | — |
| Pruebas de aceptación y ajustes | por venir | — |
| Envío de la aplicación a App Store y Google Play | por venir | Depende de las cuentas de desarrollador del cliente, sin registro |
| Revisión de las tiendas, corrección de rechazos y publicación | por venir | — |
| Capacitación y entrega formal | por venir | — |
| Un mes de operación en producción con ciclo de observaciones (IV, A.7) | por venir | — |

## Obligaciones

Las obligaciones y sus criterios son los de `contrato/obligaciones-ejecutivas.md`. Aquí va solo su estado.

| ID | Estado | Evidencia |
|---|---|---|
| O-01 | casi completa | Símbolo y logotipo de la palabra en SVG, PDF, PNG a 300 y 72 dpi; el `.ai` del logotipo es el del diseñador. Falta el `.ai` del símbolo, que necesita Illustrator, y decidir si la horizontal lleva el símbolo (DD-059) |
| O-02 | completa | `design-system/tokens.json`; `cobertura-anexo-a1.md`, O-02 |
| O-03 | completa | Manual 1.2, 18 páginas; la única observación del cliente quedó aplicada (C-103). Sin notificación formal (DD-050) |
| O-04 | sin empezar | Herramienta decidida: Canva (C-74); adelantadas (C-73). Sin archivos. El criterio de aceptación del branding las exige (A.4.1) |
| O-05 | completa | Guía de tono, 3 páginas, alineada con el manual 1.2 |
| O-06 | completa | Lo condujo el diseñador de EL CLIENTE (`cobertura-anexo-a1.md`, O-06) |
| O-07 | sin empezar | Semanas 10 a 12. Nadir quiere comprar una plantilla (C-95) |
| O-08 | sin empezar | — |
| O-09 | sin empezar | — |
| O-10 | sin empezar | — |
| O-11 | sin empezar | `lavandi.mx` verificado disponible (C-54); no hay registro de que se haya comprado |
| O-12 | sin empezar | Depende del stack (Fase 6, detenida) |
| O-13 | sin empezar | Inventariado: 17 funciones (`inventario-funcional.md` §7) |
| O-14 | sin empezar | Inventariado: 6 funciones |
| O-15 | sin empezar | Inventariado: 5 funciones |
| O-16 | sin empezar | Inventariado: 7 funciones. Abierta DD-040: dónde vive |
| O-17 | sin empezar | — |
| O-18 | sin empezar | R-028: OXXO Pay ya no sería de Conekta (grado E) |
| O-19 | sin empezar | — |
| O-20 | sin empezar | — |
| O-21 | sin empezar | Semanas 12 y 13. Inventariado: 11 indicadores |
| O-22 | sin empezar | — |
| O-23 | sin registro | La primera sesión de consultoría tocaba en la semana 2 y la segunda en las 4 y 5. No hay agendas ni actas de consultoría |
| O-24 | no aplica todavía | Corre desde la publicación en las dos tiendas |
| O-25 | no aplica todavía | El apoyo con contenido es cortesía aparte (C-71) |
| O-26 | en curso | `arquitectura/modelo-multi-plaza.md`: criterio de aceptación propuesto |
| O-27 | no aplica todavía | — |
| O-28 | en curso | Inició el 7 de septiembre. El aviso del retraso del branding se cerró sin enviar porque el branding ya se entregó (DD-036, C-111). El del desarrollo tiene borrador y lo envía Nadir (DD-064) |
| O-29 | sin registro | `cliente/reportes-semanales/` vacío. El calendario pone el desarrollo desde la semana 4 |
| O-30 | no aplica todavía | Al término y una vez liquidado |
| O-31 | sin registro | `cliente/notificaciones-de-entrega/` vacío (DD-050) |
| O-32 | en curso | Ninguna Orden de Cambio firmada. Propuesta como tal: lavado de calzado (DD-046) |
| O-33 | sin registro | Pago 1 sin fecha ni comprobante (DD-007) |
| O-34 | en curso | Continuo |
| O-35 | no aplica todavía | — |
| O-36 | sin registro | `cliente/requerimientos/` vacío. El requerimiento de insumos del 2026-09-11 está sin enviar |
| O-37 | no aplica todavía | — |
| O-38 | no aplica todavía | Semanas 14 a 16 |

## Pagos

| Pago | Estado | Evidencia |
|---|---|---|
| 1 Arranque | acreditado | C-02. Fecha y comprobante sin declarar (DD-007) |
| 2 Despliegue | pendiente | Semanas 15 y 16 |
| 3 Operación | pendiente | 26 de enero de 2027 |

## Trámites a cargo de EL CLIENTE

| Trámite | Estado | Evidencia |
|---|---|---|
| Número D-U-N-S | sin registro | Debía iniciarse en la semana 1 y tarda hasta 30 días. Abierta DD-017: si la cuenta es de persona física o de sociedad |
| Cuenta de desarrollador de Google | sin registro | Debía iniciarse en la semana 1. DD-017 |
| Alta en la procesadora de pagos (Conekta) | sin registro | Debía iniciarse en la semana 4 y requiere cuenta bancaria empresarial |
| Cuenta de desarrollador de Apple | sin registro | Toca en las semanas 5 y 6, que son ahora, y necesita el D-U-N-S |
| Aviso de privacidad publicado | sin registro | Toca en la semana 12 |
| Registro de marca (IMPI) | sin registro | Falta la búsqueda fonética (DD-030) y saber con qué licencia se usó Berlin Sans FB (DD-060) |

Al 2026-11-05, sobre todos estos trámites el cliente escribió "Aún no se hace nada" (`cliente/comunicaciones/2026-11-05-reporte-del-formulario.md`).
Es una marca del cliente en papel, todavía sin confirmar por escrito.

## Lo que sigue

Ordenado por lo que más bloquea. Es la respuesta corta a "¿qué sigue?". La fecha límite va en formato
`AAAA-MM-DD` para que el tablero marque sola lo vencido; si no hay fecha, va `sin fecha`.

| # | Acción | Quién | Qué desbloquea | Fecha límite | De dónde sale la fecha |
|---|---|---|---|---|---|
| 1 | Asentar lo que Arthur acordó con Nadir sobre desde cuándo cuentan los "tres meses" (DD-068) y con qué cuenta de Apple se prueba en iPhone (DD-069) | Arthur | El calendario de la Orden de Cambio | 2026-11-15 | Fin de la semana 10 |
| 2 | Pedir por escrito a EL CLIENTE que arranque los trámites del B.10: D-U-N-S, cuentas de Apple y de Google y alta en Conekta | Nadir | La publicación en las tiendas y los cobros en producción, de los que depende el pago 2 | 2026-11-15 | El D-U-N-S y la cuenta de Apple pueden tardar hasta unas seis semanas juntos (`contrato/calendario-contractual.md`, E) |
| 3 | Enviar al cliente el reporte «Lo que nos dejó la reunión» y el kit actualizado, y confirmar las dos lecturas dudosas de su letra (`meta/pase-de-proyecto.md`, "Lo que sigue") | Nadir | Las decisiones del negocio: precio, comisión y quién entrega (DD-010, DD-020, DD-022) | 2026-11-15 | Fin de la semana 10 |
| 4 | Avisar por escrito a EL CLIENTE que el desarrollo va atrasado (RN-34, DD-064). Borrador listo: `cliente/comunicaciones/2026-10-09-aviso-de-retraso-del-desarrollo.md`. Lo envía Nadir | Nadir | Que el retraso quede explicado por escrito | 2026-10-11 | El contrato pide 48 h de aviso; el retraso existe desde el 2026-09-28 |
| 5 | Negociar con EL CLIENTE la Orden de Cambio por etapas (camino B de la recalibración), con alcance, calendario e hitos de pago por etapa | Nadir, con el cálculo de horas de Arthur | Que el calendario nuevo y los pagos 2 y 3 queden por escrito | sin fecha | Depende de DD-068 |
| 6 | Calcular las horas de la etapa 1: cruzar el inventario de 76 funciones con lo que el cliente marcó "para abrir" | Arthur, con Claude | El tamaño de la etapa 1 y la propuesta de la Orden de Cambio | 2026-11-19 | Estimación de Claude, con sesiones diarias de 2 h (C-134) |
| 7 | Cerrar los pendientes de la Fase 6: hosting (DD-070, lo decide Arthur con Nadir; mientras tanto, solo local, C-154), correo (DD-071) y prueba en iPhone (DD-069). La fase quedó cerrada con pendientes el 2026-11-13 | Arthur, con Claude | El primer despliegue y la prueba temprana (R-025) | 2026-11-22 | Estimación de Claude, con sesiones diarias de 2 h (C-134) |
| 8 | Cerrar los pendientes de la Fase 7: que Nadir apruebe los protocolos con el cliente (DD-074) y decidir el espejo a Notion (DD-075). El repositorio (DD-073) y las cuentas de prueba (DD-072) se cerraron el 2026-11-18 (C-169, C-170) | Arthur con Nadir | Los avisos formales al cliente | 2026-11-24 | Una semana, el plazo de un sprint (C-157) |
| 9 | Preparar el sprint 1. **Hecho el 2026-11-18:** el entorno local (`formas-de-trabajo/entorno-local.md`, C-166). **Falta:** abrir las cuentas de prueba con el correo de Arthur (C-170), los mocks (C-167) y, con ellos, la primera spec. La prueba temprana en iPhone espera (DD-069, C-164) | Arthur, con Claude | El desarrollo del producto | 2026-11-24 | Una semana, el plazo de un sprint (C-157) |
| 10 | Empezar las 12 plantillas en Canva, en una cuenta a nombre del cliente | Por definir: Nadir o Arthur | El criterio de aceptación del branding (A.4.1) | 2026-11-08 | Antes de la semana 10 (C-73) |
| 11 | Empezar el reporte semanal a EL CLIENTE | Nadir | La comunicación semanal durante el desarrollo (O-29) | 2026-10-09 | El calendario pone el desarrollo desde la semana 4 |
| 12 | Preguntar al diseñador con qué licencia usó Berlin Sans FB, y hacer la búsqueda fonética ante el IMPI | Nadir | El registro de la marca (DD-060, DD-030) | sin fecha | Antes del trámite ante el IMPI |
| 13 | Notificar por correo la entrega parcial del branding: logotipo, paleta, tipografías, manual y guía | Nadir | Que lo terminado quede con fecha cierta (DD-050) | 2026-10-11 | Fin de la semana 5 |
