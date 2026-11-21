---
estado: borrador
grado: C
fuente: acta-2026-11-12-traspaso-del-proyecto.md C-132, C-133, C-134 y C-143 (C); acta-2026-11-17-fase-7-formas-de-trabajo.md C-157 y C-158 (C); acta-2026-11-18-entorno-y-mocks.md C-170 (C); acta-2026-11-19-normativa-y-arranque.md C-181 a C-185 (C); NSV-2026-001-v8.pdf:5-8:Cláusulas II, III y IV, :11-12:Cláusulas XII y XIV y :27-34:ANEXO B (A); meta/deuda-de-decision.md, contrato/calendario-contractual.md y meta/estado-del-proyecto.md (B)
fecha: 2026-11-19
dueño: Arthur Naranjo
---
# Reparto de tareas: Arthur, Nadir y EL CLIENTE

**Borrador para que Arthur lo apruebe y lo comparta con Nadir.** Arthur lo pidió el 2026-11-19: "comenzar a ver cómo
repartir las tareas de desarrollo de nuestra parte y con Nadir" (C-185). Claude lo propone con lo ya acordado; no
agrega acuerdos nuevos.

En una frase: **Arthur construye, Nadir trata con el cliente, y el cliente entrega lo que le toca y decide su
negocio.** Arthur le entrega el producto a Nadir, no al cliente (C-133).

## 1. Quién hace qué

| Quién | Hace | No hace | Cita |
|---|---|---|---|
| **Arthur, con los agentes** | Todo el desarrollo, de principio a fin: decisiones técnicas, ADRs, specs, código, pruebas y la entrega semanal del código a Nadir | Hablar con el cliente, firmar o enviarle algo | C-132, C-133, C-158 |
| **Nadir** | Todo lo de EL CLIENTE: firmas, Órdenes de Cambio, avisos, reportes, notificaciones de entrega y requerimientos escritos. Revisa y acepta la entrega semanal del código | Decisiones técnicas: desde el traspaso son de Arthur | C-133; traspaso, decisión 1 |
| **EL CLIENTE** | Entrega insumos, contrata a su nombre los servicios del ANEXO B, hace sus trámites, recluta lavanderías y decide su negocio (precios, comisión, envío, cobro, cancelación) | — | `NSV-2026-001-v8.pdf:6:Cláusula III.B`; `:27:ANEXO B, Sección B.1` |

### Arthur, con los agentes

- **Decide lo técnico:** el stack, los ADRs y las specs. Una spec pasa a `vigente` cuando él la aprueba (traspaso,
  decisión 1; [[ciclo-openspec-tdd]]).
- **Construye con test primero,** en `movil/` y `servidor/` (CLAUDE.md §9).
- **Trabaja todos los días de 22 a 24 h,** con agentes en paralelo (C-134). El sprint dura una semana y cierra el
  domingo (C-157).
- **Entrega a Nadir una vez por semana:** un solo pull request, es decir, la entrega del código de la semana para que
  Nadir la revise y la acepte en su repositorio (C-158; [[repositorios-ramas-y-revision]]).
- **Abre las cuentas de prueba** con su correo: Firebase, Google Cloud, Conekta en modo prueba y Expo (C-170).
- **Calcula las horas** de cada posible Orden de Cambio, para que Nadir la cotice ([[protocolos-con-el-cliente]]).
- **Lo que necesita de Nadir se lo dice él directamente,** sin issues (C-143). Lo que Nadir responde, Arthur lo
  asienta en un acta con la skill `asentar-acta`: sin acta no cuenta (CLAUDE.md §3).

Los seis agentes trabajan para Arthur ([[equipo-de-agentes]]). Ninguno habla con Nadir ni con el cliente.

| Agente | Qué hace en el día a día | Qué revisa Arthur antes de seguir |
|---|---|---|
| `especificador` | Escribe la spec de cada capacidad en `openspec/changes/<cambio>/` (C-183), con cita al contrato o a un ADR | Aprueba la spec y lee su lista "Fuera del contrato" |
| `implementador-servidor` | Programa con test primero en `servidor/`: la API, el panel y la landing, en su propia rama | La demostración y el reporte de traspaso, con los tests en rojo y en verde |
| `implementador-movil` | Lo mismo en `movil/`: la app | Lo mismo, más la prueba en el teléfono |
| `revisor` | Dictamina si una rama se puede fusionar | Los hallazgos graves, antes de fusionar |
| `verificador-de-aceptacion` | Decide si un criterio del ANEXO A.4 queda acreditado, con su test | Que lo acreditado sea lo único que se reporte como hecho |
| `redactor-cliente` | Prepara los borradores de los mensajes al cliente, con sus plantillas. **Nunca envía** | Pone lo técnico (avance, horas, bloqueos) y se los pasa a Nadir |

Las skills `arranque-de-sesion`, `cierre-de-sesion` y `cierre-de-sprint` llevan el ritmo diario y semanal.

### Nadir

Nadir es EL PRESTADOR: lo que el contrato le pide al prestador frente al cliente lo firma y lo envía él (C-133).

| Tarea | Cuándo | Cita |
|---|---|---|
| Avisar por escrito de un retraso previsto | Con 48 h de anticipación por lo menos | `NSV-2026-001-v8.pdf:6:Cláusula III.A` |
| Mandar el reporte semanal | Cada semana del desarrollo | `NSV-2026-001-v8.pdf:6:Cláusula III.A` |
| Notificar cada entrega | Cuando el entregable cumple sus criterios del A.4. Desde ahí corren 10 días hábiles | `NSV-2026-001-v8.pdf:7:Cláusula IV` |
| Requerir por escrito los insumos que falten | Cuando un insumo del cliente detiene el trabajo | `NSV-2026-001-v8.pdf:11-12:Cláusula XII` |
| Negociar y firmar las Órdenes de Cambio | Antes de construir cualquier cosa fuera del contrato | `NSV-2026-001-v8.pdf:8:Cláusula IV`; `:12:Cláusula XIV` |
| Aprobar todo lo que sale hacia el cliente | Siempre | CLAUDE.md §5 |
| Revisar y aceptar la entrega semanal del código | Cada domingo, en `nhadadn/Lavandi` | C-158 |
| Aprobar los protocolos con el cliente | Una vez | DD-074 |
| Verificar dominio e IMPI | Antes de registrar la marca | CLAUDE.md §3, regla 5; DD-030 |
| Pasar al cliente las decisiones que son suyas y traer la respuesta por escrito | Según la deuda de decisión | C-26: el interlocutor es Alfredo Navarrete |

### EL CLIENTE

El interlocutor único es Alfredo Navarrete (C-26). Todo lo que se le pide, se le pide por Nadir.

| Qué le toca | Para qué sirve | Cita |
|---|---|---|
| Entregar insumos, contenidos e información a tiempo: por ejemplo, los textos e imágenes de la bienvenida de la app | Que el trabajo no se detenga | `NSV-2026-001-v8.pdf:6:Cláusula III.B` |
| Revisar cada entrega y contestar por escrito en 10 días hábiles | Si calla, la entrega queda aceptada | `NSV-2026-001-v8.pdf:7:Cláusula IV` |
| Contratar y pagar a su nombre los servicios: tiendas, dominio, alojamiento, correo, servidor, base de datos, mapas y Conekta | Que la plataforma sea suya | `NSV-2026-001-v8.pdf:27:ANEXO B, Sección B.1`; `:34:ANEXO B, Sección B.11` |
| Hacer los trámites del B.10: D-U-N-S, cuentas de Google y de Apple, alta en Conekta y aviso de privacidad publicado | Publicar la app y cobrar en producción | `NSV-2026-001-v8.pdf:33-34:ANEXO B, Sección B.10` |
| Abrir una cuenta bancaria empresarial y verificarse con Conekta | Cobrar en producción | `NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.6` |
| Entregar el aviso de privacidad y los términos y condiciones. **Redactarlos no es parte del contrato** | El registro de la app y el envío a las tiendas | `NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7` |
| Reclutar las lavanderías y los repartidores, y darlos de alta. El soporte del contrato no incluye las altas de socios | Que haya a quién pedirle | `NSV-2026-001-v8.pdf:24:ANEXO A, Sección A.7`; DD-021 |
| Decidir su negocio: precio, comisión, envío, cuándo se cobra, cancelación y los cinco avisos | Las pantallas de pedido y de pago | DD-010 a DD-025, DD-011 |

## 2. Lo que hoy está en manos de Nadir o del cliente

Sacado de las filas abiertas de [[deuda-de-decision]] y de los trámites del [[calendario-contractual]] al
2026-11-19. El orden sigue el Módulo A, por donde empieza el desarrollo (C-182). El número de sprint de cada
capacidad lo fija `sprints/plan-de-sprints.md`; aquí solo se nombran el sprint 0 (preparación, del 19 al 22 de
noviembre) y el sprint 1 (registro e inicio de sesión, del 23 al 29).

### Lo urgente, sea cual sea el sprint

| # | Qué falta | Quién | Por qué urge | Fila |
|---|---|---|---|---|
| 1 | **Avisar por escrito al cliente que el desarrollo va atrasado.** El borrador existe desde el 9 de octubre | Nadir | Es el riesgo contractual más alto: el contrato pide el aviso con 48 h y el desarrollo debía empezar el 28 de septiembre | DD-064 |
| 2 | **Arrancar los trámites del B.10.** El 5 de noviembre el cliente escribió "Aún no se hace nada". El D-U-N-S tarda hasta 30 días; una cuenta personal nueva de Google Play pide 14 días de prueba cerrada con 12 personas (E) | El cliente; Nadir se lo requiere por escrito | Sin esto no hay publicación en las tiendas ni pago 2 | DD-017; acción 2 de `meta/estado-del-proyecto.md` |
| 3 | **La Orden de Cambio por etapas,** con el calendario nuevo. Antes, asentar desde cuándo cuentan los "tres meses" | Nadir, con las horas de Arthur | El calendario del contrato ya no cierra ([[sprint-y-sesion-diaria]]) | DD-068; acción 5 de `meta/estado-del-proyecto.md` |
| 4 | **Aprobar los protocolos con el cliente:** reporte semanal, avisos, notificaciones, requerimientos y Órdenes de Cambio | Nadir | El reporte semanal sale desde el primer cierre de sprint | DD-074 |
| 5 | **El plan de reclutamiento de lavanderías** | El cliente | En local se prueba con datos de prueba, pero el recorrido completo del A.4.3 y el pago 2 necesitan lavanderías reales | DD-021, **crítica** |

### Lo que bloquea cada parte del Módulo A

| Parte de la app | Qué falta | Quién | ¿Detiene el código? | Fila |
|---|---|---|---|---|
| **Registro e inicio de sesión (F-A02), sprint 1** | El aviso de privacidad y los términos y condiciones | El cliente, vía Nadir | **No**: en el sprint 1 el enlace va como dato de configuración. **Sí** detiene el envío a las tiendas: el B.10 lo pone publicado en la semana 12, del 23 al 29 de noviembre | DD-080 |
| Registro e inicio de sesión (F-A02), sprint 1 | Resend o Amazon SES para el correo de confirmación | **Arthur**, no Nadir | Sí, el correo de confirmación. Límite: 22 de noviembre | DD-071 |
| Registro e inicio de sesión (F-A02), sprint 1 | Cómo entran la lavandería y el repartidor a su módulo | Nadir según la fila; se propone que lo decida Arthur | **No**: el sprint 1 solo necesita al usuario, que se registra directo | DD-053 |
| Bienvenida (F-A01) | Los textos e imágenes de las 3 pantallas | El cliente, vía Nadir | Sí, el contenido; la pantalla se puede construir con texto provisional | DD-081 |
| Pantalla principal y perfil de la lavandería (F-A03 a F-A05) | Quién fija el precio por kilo y la comisión | El cliente, vía Nadir | No el mapa; sí los precios que se muestran | DD-010, DD-020 |
| Agenda de recolección (F-A07) | La regla de los pedidos de noche y el "día para lavar", confirmada por escrito | El cliente, vía Nadir | Sí, la spec de agenda | DD-078 |
| Resumen del pedido (F-A09) | Cuándo se cobra, la política de cancelación y el envío | El cliente | Sí | DD-012, DD-016, DD-019, DD-025 |
| **Pago (F-A10)** | Si se cambia Conekta. **Hasta que haya Orden de Cambio firmada, se integra Conekta** (CLAUDE.md §2-bis). También el efectivo y si se guarda la tarjeta | Nadir con el cliente | Sí, la spec de pago. En modo prueba se usa la cuenta de Arthur (C-170) | DD-079, DD-056, DD-055 |
| Confirmación y avisos (F-A11, F-A17) | Los "cinco momentos" de aviso que pide el A.4.3, y si la lavandería recibe aviso de un pedido nuevo | Nadir con el cliente | Sí | DD-011, DD-054 |
| Seguimiento (F-A12) | Si quieren ver al repartidor en un mapa | Nadir con el cliente | **No**: el contrato pide seguimiento por estados y así se construye. El mapa solo entra con Orden de Cambio | DD-077 |
| Calificación (F-A14) | A quién califica el usuario | El cliente, vía Nadir | Sí, la spec de calificación | DD-052 |
| Soporte (F-A16) | El número de WhatsApp Business del cliente | El cliente, vía Nadir | No el código: el número va como configuración | DD-082 |
| Prueba en iPhone | Con qué cuenta de Apple se prueba mientras el cliente no tenga la suya | Arthur con Nadir ("ok, esperamos", C-164) | No el código; sí la prueba en iPhone | DD-069 |

**Fuera del Módulo A, para después:** quién asigna repartidores (DD-014), si la lavandería reparte por su cuenta
(DD-022), dónde corre el servidor de prueba, que es el camino alterno del pago 2 (DD-070), y el dominio y el IMPI,
que detienen la landing (DD-023, DD-030).

**La deuda está vencida.** De las filas abiertas, la mayoría pasó su fecha límite y varias ya no aplican. Revisarla
con Nadir es DD-061.

## 3. Cómo se trabaja con Nadir sin coincidir en horario

**La recomendación: la entrega semanal del código es el punto de encuentro, y lo urgente se habla directo.** No hace
falta ninguna herramienta nueva.

| Cuándo | Quién | Qué |
|---|---|---|
| Todos los días, de 22 a 24 h | Arthur | Trabaja y deja todo escrito en el repositorio. No le escribe a Nadir, salvo urgencia |
| Cuando algo urge | Arthur | Le habla directo a Nadir por WhatsApp o por teléfono (C-143). Urge un plazo de 48 h del contrato o algo que detiene el sprint en curso |
| Domingo | Arthur | Cierra el sprint y abre la entrega semanal del código hacia `nhadadn/Lavandi` (C-158). Le avisa a Nadir con un WhatsApp de una línea |
| Domingo | La skill `cierre-de-sprint` prepara, y Arthur aprueba | La descripción de la entrega, de una página: lo que avanzó; el video corto en el teléfono; las horas contra las 11 comprometidas; **hasta 5 decisiones** que se necesitan de Nadir o del cliente, cada una con opciones, recomendación y su fila DD; lo que alguien pidió fuera del contrato; los insumos del reporte semanal |
| A más tardar el miércoles | Nadir | Revisa y acepta la entrega. Contesta las decisiones en un comentario de la entrega o directo con Arthur. Pasa al cliente las que son suyas |
| Cada semana | Nadir | Manda el reporte semanal al cliente, armado con lo que trae la entrega (`NSV-2026-001-v8.pdf:6:Cláusula III.A`) |
| Al recibir una respuesta | Arthur | La asienta en un acta y cierra su fila en la deuda |

**Si Nadir no contesta,** lo técnico lo decide Arthur, que es su dueño. **Lo del negocio o del contrato se queda
bloqueado:** vuelve a la siguiente entrega y nunca se supone (CLAUDE.md §3, regla 6).

**Dónde queda cada cosa:**
- **Las decisiones pendientes:** [[deuda-de-decision]].
- **Lo que alguien declaró:** `meta/actas/`.
- **Las tareas de la semana:** `sprints/sprint-NN.md`.
- **Los objetivos y las fechas:** `sprints/plan-de-sprints.md`.

El repositorio es la única fuente de verdad. WhatsApp sirve para avisar, no para decidir.

**Por qué no un tablero de GitHub Projects con issues,** como proponía el análisis del 2026-11-18:
- **Ya se descartó:** Arthur pidió no redactar issues para Nadir, porque lo hablan entre los dos (C-143, que
  reemplaza a C-138).
- **Duplicaría la deuda de decisión.**
- **Herramienta:** `gh`, la herramienta de GitHub para la terminal, no está instalada.

Si Arthur lo quiere más adelante, el tablero se puede crear desde la página de GitHub, sin instalar nada. El espejo
de Notion es solo de lectura y hoy no existe (DD-075): no sirve para contestar.

## 4. Cuando alguien pide algo que no está en el contrato

La NORMATIVA de CLAUDE.md §2-bis (C-181): **no se construye nada que el contrato no establezca.** Lo demás es una
posible Orden de Cambio, y ninguna Orden de Cambio vale sin la firma de los dos (`NSV-2026-001-v8.pdf:8:Cláusula IV`).

| Paso | Quién | Qué hace |
|---|---|---|
| 1. Lo detecta | Cualquiera. En la práctica:<br>• el `especificador`, que no deja entrar un requisito sin cita y lo pone en la lista "Fuera del contrato" de la propuesta;<br>• el `revisor`, que rechaza código sin escenario;<br>• Claude en la sesión, con la frase "eso está fuera del contrato" | Dice qué sí dice el contrato, con su cita |
| 2. Lo detiene | Arthur | No se construye "mientras tanto" |
| 3. Lo anota | Claude, en la sesión con Arthur | Una fila `contractual` en [[deuda-de-decision]] y, en el acta, quién lo pidió y con qué palabras |
| 4. Le pone horas | Arthur | Las estima con las bandas de S-006 |
| 5. Decide si se negocia | **Nadir** (C-133) | Si se negocia, le pide a Arthur el borrador; el `redactor-cliente` lo arma con `plantillas/plantilla-orden-de-cambio.md` |
| 6. La firma | Nadir y EL CLIENTE | Sin las dos firmas no existe |
| 7. Se construye | Arthur con los agentes | Solo después de firmada. La spec cita la Orden de Cambio |

**Si el cliente le pide algo directo a Arthur,** Arthur se lo pasa a Nadir, porque el canal con el cliente es Nadir
(C-133).

**Casos que ya están en este camino:**
- **Cambiar Conekta** (DD-079).
- **Ver al repartidor en un mapa** (DD-077).
- **El beneficio por llevar más prendas** (DD-076).
- **El lavado de calzado** (DD-046).
- **Recoger con Uber, DiDi o Rappi:** el contrato no lo menciona.
