---
estado: vigente
grado: B
fuente: auditoría de solo lectura de arthur/feat/h3-concilio en 9d2497775966ef2d8c0c0f204b2f67cfb8432fbd (PR #4) contra vault/contrato/ (lectura ejecutiva grado A de NSV-2026-001-v8.pdf), openspec/changes/ y el plan de sprints; código revisado por cuatro subagentes de solo lectura y verificado por muestreo en este SHA; sin correr las suites, por decisión de Nadir Hadad Navarrete en sesión del 2026-11-24
fecha: 2026-11-24
dueño: Nadir Hadad Navarrete
---
# Auditoría Lavandi — Segmento 1: Avance contra el contrato

**Objeto:** rama `arthur/feat/h3-concilio` (PR #4) · SHA `9d24977` · fecha de corte 2026-11-24, **semana 12** del
calendario contractual (23–29 nov; `vault/contrato/calendario-contractual.md:31-33`).

**Qué NO se revisó:**
- **No se corrieron las suites.** Ninguna función puede quedar PROBADA. Donde hay un test con el ID del escenario, se
  dice "test existe; NO VERIFICADO: no se corrieron las suites".
- El PDF del contrato no se releyó: las citas A salen de su lectura ejecutiva en `vault/contrato/`, que tiene grado A
  y no cambió en esta rama (`git diff origin/main 9d24977 -- vault/contrato legal` vacío).
- Los entregables de branding (A.4.1) no se cotejaron. Su estado viene de `vault/formas-de-trabajo/trazabilidad-criterio-test.md`.
- La calidad del código, la seguridad, la API y los flujos son de los segmentos 2 a 6.

**Veredicto: NO ESTÁ LISTO.**
- De los 19 criterios de aceptación del A.4, ninguno se cumple todavía: 9 están parciales y 10 en no.
- El recorrido del pedido (A.4.3) no se puede completar con datos reales: falta el precio, el cobro real, la
  asignación del repartidor y el acceso de los socios.
- El envío a las tiendas toca en la semana 14 (7–13 dic) y faltan los trámites del B.10, el hosting y la cuenta de Apple.

### Resumen

- **Avance:** las 76 funciones del inventario están ESPECIFICADAS en 27 changes de OpenSpec, con 498 escenarios, y
  387 de ellos tienen un test que lleva su ID. **43 de 76 están CODIFICADAS, 30 PARCIALES, 3 NO INICIADAS y 0 PROBADAS.**
- **Casi todo lo que falta espera una decisión de EL CLIENTE o de Nadir, no código:** el modelo de cobro (DD-012),
  la pasarela y el efectivo (DD-079, DD-056), quién asigna al repartidor (DD-014), quién marca Entregado (DD-022),
  cómo entra cada rol (DD-053) y los cinco momentos de aviso (DD-011).
- **No se construyó nada relevante fuera de contrato.** Conekta no se sustituyó y Stripe no existe en el código. Las
  exclusiones del Módulo C no existen.
- **La decisión 69 (C-247, 2026-11-24)** pasa el Módulo D completo a la app. Hoy vive solo en Filament web, y su ADR y
  su spec están "por escribir". Es trabajo nuevo, dentro del contrato, en plena semana 12.
- **Hacia EL CLIENTE, el vault no tiene evidencia** del aviso de retraso, de ningún reporte semanal, de ninguna
  notificación de entrega ni de ninguna Orden de Cambio.

### Hallazgos

| ID | Severidad | Área | Hallazgo | Evidencia (grado + ruta:línea) | Impacto | Recomendación |
|---|---|---|---|---|---|---|
| S1-01 | Alta | Criterio A43-2 | **El recorrido completo no se puede hacer con datos reales.** (a) El resumen no muestra precio estimado ni política de cancelación: son renglones reservados. (b) El cobro real no funciona: la autenticidad del aviso de Conekta lanza una excepción, la pasarela por omisión es la simulada y el efectivo se rechaza. (c) Ningún código crea la asignación de un repartidor. (d) La lavandería no puede marcar Entregado. (e) Ningún socio puede obtener su cuenta: el registro público solo da el rol usuario y el panel no permite dar de alta lavanderías | A: `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`. B: (a) `movil/app/pedido/resumen.tsx:142-146`; (b) `servidor/app/Cobro/PasarelaConekta.php:123-125`, `servidor/.env.example:148` (`PASARELA=simulada`), `servidor/app/Http/Requests/PedirCheckoutRequest.php:58-59`; (c) `servidor/app/Models/PedidoAsignacion.php:17-18`; (d) `servidor/app/Pedidos/CambioDeEstado.php` (de `en_entrega` a `entregado` solo transita el repartidor); (e) `servidor/app/Identidad/AltaDeCuenta.php:3,20`, `servidor/app/Filament/Resources/Lavanderias/LavanderiaResource.php:68-71` | Bloquea A43-2, A43-4 y A43-6, y con ellos el pago 2 (`calendario-contractual.md:51`) | Llevar a EL CLIENTE, con fecha, DD-012, DD-014, DD-016, DD-022 y DD-053. Del lado técnico, cerrar DD-102 (la autenticidad del webhook) |
| S1-02 | Alta | Calendario | **El envío a las tiendas de la semana 14 (7–13 dic) no está listo.** Ningún trámite del B.10 ha empezado (D-U-N-S, cuentas de Apple y Google, alta en Conekta). Solo existe el entorno local, sin despliegue ni hosting. Los textos legales están vacíos. El plan de sprints, en borrador, proyecta terminar el 25 de julio de 2027 | A: `NSV-2026-001-v8.pdf:5:Cláusula II`; `:33-34:ANEXO B, Sección B.10`. B: `vault/meta/deuda-de-decision.md` (DD-084 "Ninguno ha empezado", DD-070 abierta); `openspec/changes/publicacion/design.md:4-5`; `servidor/storage/app/` sin textos; `vault/formas-de-trabajo/sprints/plan-de-sprints.md:18-21` | El pago 2 depende de la publicación, o de la app "desplegada en ambiente de prueba y validada conforme A.4.3" si el retraso no es imputable (`calendario-contractual.md:51`). Hoy no hay ambiente de prueba | Nadir: arrancar ya el B.10 con EL CLIENTE y decidir el hosting (DD-070). Arthur: rehacer el plan de sprints con el ritmo real (ver S1-12) |
| S1-03 | Alta | Obligaciones de conducta | **No hay evidencia en el vault de las comunicaciones que exige el contrato:** el aviso de retraso está en borrador desde el 2026-10-09, y `reportes-semanales/`, `notificaciones-de-entrega/` y `ordenes-de-cambio/` están vacías. El desarrollo debía arrancar en la semana 4 (28 sep); el primer commit de código es del 2026-11-20 (`e9eb3dd`) | A: `NSV-2026-001-v8.pdf:6:Cláusula III.A` (aviso con 48 h, comunicación semanal), `:7:Cláusula IV` (notificación de entrega). B: `vault/cliente/comunicaciones/2026-10-09-aviso-de-retraso-del-desarrollo.md` (`estado: borrador`); carpetas vacías en `vault/cliente/`; DD-064 "en curso", DD-050 y DD-068 abiertas | Sin aviso escrito, el retraso queda sin respaldo (O-28). Sin notificación de entrega, no corren los 10 días de aceptación tácita (O-31) | Nadir: confirmar si se envió algo fuera del vault y archivarlo. Si no, enviar el aviso de retraso y abrir la Orden de Cambio de calendario (DD-068) |
| S1-04 | Alta | Alcance del Módulo D | **La decisión 69 (C-247) pasa el Módulo D completo a la app, pero 0 de sus 7 funciones y 0 de los 11 indicadores están en la app.** Hoy todo vive en Filament web y la app solo tiene un botón que abre el panel, que viene de una decisión revocada. Las specs vigentes dicen lo contrario: `exportacion` declara fuera de contrato las descargas desde la app | C: C-247; decisión 69 en `vault/meta/actas/acta-2026-11-20-modelo-y-pasarela.md:263` ("ADR y spec por escribir"). B: `movil/src/sesion/modulos.ts:7`; `servidor/routes/api.php:57`; `movil/src/acceso/BotonDelPanel.tsx:25`; `openspec/changes/administracion/proposal.md:20`; `openspec/changes/exportacion/proposal.md:18` | El contrato no dice dónde vive el Módulo D (inventario, DD-040). Rehacerlo en la app es trabajo grande en la semana 12, encima de A43-7 y A44 | Nadir decide si sostiene C-247 a la luz del calendario (S1-02). Si se sostiene, el ADR y la spec van antes del código, y las specs viejas se corrigen |
| S1-05 | Alta | iPhone | **Todo lo de iPhone está bloqueado:** Sign in with Apple, avisos, la medición de los 60 s y la compilación firmada. RN-01 pide que las dos versiones "se comporten de manera idéntica" y A43-5 pide avisos en iPhone | A: `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`; `:21:A.4.3`. B: `movil/app/inicio-de-sesion.tsx:3`; `openspec/changes/avisos/tasks.md:27`; `openspec/changes/estados-del-pedido/tasks.md:34`; `openspec/changes/publicacion/tasks.md:29,33`. DD-069, DD-017, DD-084 | No se puede acreditar A43-1, A43-3 ni A43-5 en iPhone | Resolver DD-069: una cuenta de Apple de prueba mientras llega el D-U-N-S |
| S1-06 | Alta | Criterio A43-7 / A44-1 | **"Usuarios activos" no funciona.** El indicador F-I03 lee `actividad_usuarios`, pero nada en `servidor/app` escribe esa tabla. La gestión de usuarios (F-D03) no tiene filtro de activos. F-I06 (lavanderías activas contra registradas) y F-I10 (comisiones) no existen | A: `NSV-2026-001-v8.pdf:21:A.4.3` ("datos reales de pedidos y usuarios activos"); `:22:ANEXO A, Sección A.5`. B: `servidor/app/Indicadores/UsuariosActivos.php:26-28` (solo lectura; la búsqueda de escrituras no dio nada); `servidor/app/Filament/Resources/Usuarios/UsuarioResource.php:29`; `servidor/app/Filament/Widgets/Indicadores/` (sin widget de F-I06 ni de F-I10) | A43-7 y A44-1 no se pueden acreditar | Cerrar DD-111 (qué es activo), DD-083 y DD-020/DD-013 |
| S1-07 | Media | Avisos | **El contrato pide cinco momentos de aviso y hay cuatro configurados.** DD-011 está abierta desde el 2026-09-27. Los socios no reciben avisos: el registro de teléfonos solo acepta el rol usuario | A: `NSV-2026-001-v8.pdf:21:A.4.3` (cinco momentos); `:17:A.3 #17` (enumera cuatro). B: `servidor/config/avisos.php:46-61`; `servidor/routes/api.php:71,124`. DD-011, DD-054 | A43-5 no acreditable; la lavandería no se entera de un pedido con la app cerrada | Nadir con EL CLIENTE: DD-011 y DD-054 |
| S1-08 | Media | Módulo A | **Faltan elementos literales del Módulo A:** "repetir pedido" (F-A13), "disponibilidad" en los filtros (F-A04), "métodos de pago" en el perfil (F-A15) y el precio y la unidad del lavado en seco (F-A05, F-A06). "Repetir pedido" no tiene DD, solo BR-25 | A: `NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3` #4, #5, #6, #13, #15. B: la búsqueda de `repetir` en `movil/` y `servidor/app` solo da `sinRepetir` en `movil/app/pedido/direccion.tsx:87`; `movil/src/cercanas/FiltrosDeLaBusqueda.tsx:3-4`; `movil/app/perfil/index.tsx:19-20`; `openspec/changes/historial-y-calificacion/tasks.md:23` | A43-6 ("conforme a A.3") queda parcial | Abrir una DD para "repetir pedido" (hoy sin dueño); cerrar DD-083, DD-055 y DD-010 |
| S1-09 | Media | Módulos B y C | **Historial y estadísticas sin monto, ingreso ni calificación** (F-B04, F-B05, F-C04, F-C05). F-B01 no registra el lavado en seco. Nada enlaza al registro de repartidor desde la app | A: `NSV-2026-001-v8.pdf:17-18:A.3`. B: `servidor/app/Http/Controllers/Api/HistorialDeLaLavanderiaController.php:46,61`; `HistorialDelRepartidorController.php:37,47`; `movil/src/repartidor/rutas.ts:8`. DD-013, DD-019, DD-020, DD-052, DD-053 | A43-6 parcial | Lo mismo que S1-01: decisiones del modelo del dinero |
| S1-10 | Media | Landing | **Los dos formularios de la landing no llegan a ningún lado:** ni se entregan ni se guardan, y le dicen a la persona que su envío no se entregó. El contenido de las 12 secciones es de prueba, salvo el logo. El mapa de cobertura no tiene las zonas reales del territorio inicial | A: `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2` (secciones 5, 7 y 11); `:4:Cláusula I` (O-27: configurar zonas del territorio inicial es de EL PRESTADOR). B: `servidor/app/Http/Controllers/FormularioDeLaPortadaController.php:49-55`; `servidor/resources/views/portada/index.blade.php:17-30`; `servidor/database/seeders/PlazasDePruebaSeeder.php:15-18`. DD-117, DD-119, DD-093, DD-094 | A42-1 parcial; O-27 sin cumplir | Cerrar DD-119 (destino de los formularios). Pedir el contenido a EL CLIENTE (DD-117). Cargar las zonas reales (DD-093, DD-094) |
| S1-11 | Media | Pasarela | **OXXO Pay no existe y Conekta está a medias.** El contrato nombra "tarjeta … y OXXO Pay" con Conekta. DD-056 registra que Conekta podría ya no generar fichas de OXXO (R-028). No hay Orden de Cambio firmada para otra pasarela: C-216 es un "OK expreso" con la firma pendiente. El código no sustituyó a Conekta | A: `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3 #10`; `:21:A.4.3`. B: `servidor/app/Cobro/PasarelaConekta.php`; `PedirCheckoutRequest.php:58-59`; `openspec/changes/pago/tasks.md:31-42`. C: C-216. DD-079, DD-056 | Si Conekta no ofrece OXXO, cumplir el contrato exige una Orden de Cambio | Nadir: confirmar por escrito con Conekta (DD-056) y firmar o descartar la Orden de Cambio (DD-079) |
| S1-12 | Media | Plan y trazabilidad | **El plan de sprints y la tabla de trazabilidad no reflejan el código.** El plan (2026-11-19, borrador) pone el registro en el sprint 02 y el Módulo D en abril–junio de 2027, pero a la fecha de corte ya hay código de casi todo. La trazabilidad (2026-11-17) marca `sin spec` en A43-5, A43-7, A44-1, A44-2, SEG-2, SEG-3 y SEG-4, que ya tienen spec y tests | B: `vault/formas-de-trabajo/sprints/plan-de-sprints.md:1-6,85-98`; `vault/formas-de-trabajo/trazabilidad-criterio-test.md` (filas citadas); este informe, matriz | Ninguno de los dos documentos sirve hoy para decidir fechas ni para preparar notificaciones de entrega | Arthur: actualizar la trazabilidad y rehacer el plan con el avance real antes de la Orden de Cambio de calendario |
| S1-13 | Baja | Landing / SEO | La información estructurada usa `@type: Service`. El contrato pide información estructurada de **negocio local** | A: `NSV-2026-001-v8.pdf:15:A.2`. B: `servidor/app/Medicion/InformacionEstructurada.php:37` | Desviación menor de O-09 | Usar `LocalBusiness`, o justificarlo en la spec |
| S1-14 | Baja | Coherencia | El botón "Abrir el panel" de la app sale de la decisión 56, que C-247 revocó. La tarea 6.2 sigue abierta: `POST /api/sesion/google` todavía da token a una cuenta de administración | B: `movil/src/acceso/BotonDelPanel.tsx:25`; `openspec/changes/administracion/tasks.md:47-59`. C: C-247 | Código vivo de una decisión revocada (detalle de seguridad: segmento 4) | Resolverlo junto con S1-04 |
| S1-15 | Info | Fuera de contrato | No se encontró ninguna funcionalidad grande sin cita. Lo que no tiene frase del contrato es menor y está en la tabla "Fuera de contrato" | B: ver la tabla | — | Que Nadir valide la tabla |

### Matriz de avance: las 76 funciones del inventario

**Leyenda:**
- Cita A: página y sección de `NSV-2026-001-v8.pdf`.
- Spec: el change en `openspec/changes/`.
- Test: hay test con el ID del escenario (NO VERIFICADO: no se corrieron las suites).
- Estado: E = especificado · **C** = codificado · **P** = parcial · **NI** = no iniciado. Ninguna está PROBADA.

**Módulo A — Usuario** (`:16-17:A.3`). Criterio A.4.3: el recorrido completo (A43-2) y los cuatro módulos (A43-6).

| F | Cita | Spec | Código (B) | Test | Estado | Lo que falta · bloqueo |
|---|---|---|---|---|---|---|
| F-A01 | :16 #1 | bienvenida | `movil/app/bienvenida.tsx`, `informativa/[numero].tsx` | sí | P | Contenido de relleno (`movil/src/contenido/bienvenida.ts:30`); sin splash nativo · DD-081 |
| F-A02 | :16 #2 | registro-e-inicio-de-sesion | `servidor/routes/api.php:36-46`; `movil/app/registro.tsx`, `inicio-de-sesion.tsx` | sí | C | Google en iPhone sin cliente de iOS; correo sin transporte (`.env.example:64`); Apple · DD-069, DD-071 |
| F-A03 | :16 #3 | lavanderias-cercanas | `movil/app/inicio.tsx`; `LavanderiasCercanasController.php:53` | sí | C | "Cercana" sin radio · DD-083 |
| F-A04 | :16 #4 | lavanderias-cercanas | `movil/src/cercanas/FiltrosDeLaBusqueda.tsx` | sí | P | Sin filtro de disponibilidad · DD-083, DD-078 |
| F-A05 | :16 #5 | perfil-de-lavanderia | `movil/app/lavanderias/[id].tsx`; `PerfilDeLavanderiaController.php:65` | sí | C | Precio del seco · DD-010; fotos en S3 · DD-070 |
| F-A06 | :16 #6 | perfil-de-lavanderia | `movil/app/pedido/servicio.tsx` | sí | C | El seco no tiene precio · DD-010, DD-098 |
| F-A07 | :16 #7 | agenda-de-recoleccion | `servidor/app/Agenda/Agenda.php:168` (1 h); `movil/app/pedido/fecha-y-hora.tsx` | sí | C | Cupo por ventana · DD-099, DD-078 |
| F-A08 | :16 #8 | direcciones | `movil/app/pedido/direccion-nueva.tsx`; `DireccionesController.php` | sí | C | Autocompletado · DD-072 |
| F-A09 | :16 #9 | resumen-y-confirmacion | `movil/app/pedido/resumen.tsx:142-146` | sí | P | Sin precio estimado ni política de cancelación · DD-012, DD-010, DD-016 |
| F-A10 | :16 #10; :19 | pago | `servidor/app/Cobro/PasarelaConekta.php`; `movil/app/pedido/pago/[id].tsx` | sí | P | Webhook en 503; sin OXXO; pasarela simulada por omisión · DD-102, DD-056, DD-079 |
| F-A11 | :16 #11 | resumen-y-confirmacion | `FolioDelPedido.php`, `CorreoDeConfirmacion.php`; `movil/app/pedido/confirmacion/[id].tsx` | sí | C | El correo no sale (`MAIL_MAILER=log`); sin push en iPhone · DD-071, DD-069 |
| F-A12 | :17 #12; :21 A.4.3 | estados-del-pedido | `movil/app/pedido/seguimiento/[id].tsx`; consulta cada 30 s `movil/src/estados/useConsultaPeriodica.ts:20-21` | sí | C | 60 s medidos solo en Android y en un paso (`movil/.maestro/estados/cambio-en-60-segundos.yaml`) · DD-022, DD-069 |
| F-A13 | :17 #13 | historial-y-calificacion | `movil/app/pedidos/index.tsx` | sí | P | Sin "repetir pedido" · BR-25, sin DD |
| F-A14 | :17 #14 | historial-y-calificacion | `CalificarPedidoRequest.php:28-29`; `movil/app/pedido/calificacion/[id].tsx` | sí | C | A quién se califica · DD-052 |
| F-A15 | :17 #15 | perfil-y-soporte | `movil/app/perfil/index.tsx` | sí | P | Sin métodos de pago · DD-055, DD-079 |
| F-A16 | :17 #16 | perfil-y-soporte | `movil/app/ayuda.tsx`; `ReportesDeSoporteController.php:61` | sí | C | El reporte no le llega a nadie · DD-108 |
| F-A17 | :17 #17; :19 | avisos | `servidor/app/Avisos/Fcm/CanalDeFcm.php`; `config/avisos.php:46-61` | sí | C | Solo Android; 4 de los 5 momentos · DD-011, DD-069 |

**Módulo B — Socio lavandería** (`:17:A.3`). Criterio: A43-6.

| F | Spec | Código (B) | Test | Estado | Lo que falta · bloqueo |
|---|---|---|---|---|---|
| F-B01 | alta-de-lavanderias | `movil/app/lavanderia/registro.tsx`; `LavanderiaDelSocioController.php:200` | sí | P | Sin seco; ningún socio llega a esta pantalla (`AltaDeCuenta.php:20`) · DD-053, DD-010 |
| F-B02 | pedidos-de-la-lavanderia | `PedidosDeLaLavanderiaController.php:148-175`; `movil/app/lavanderia/pedidos/index.tsx` | sí | C | Sin aviso con la app cerrada · DD-054 |
| F-B03 | estados-del-pedido | `PedidosDeLaLavanderiaController.php:188`; `CambioDeEstado.php` | sí | C | Quién marca Entregado · DD-022 |
| F-B04 | historial-de-la-lavanderia | `HistorialDeLaLavanderiaController.php:72` | sí | P | Sin monto ni calificación · DD-013, DD-020, DD-052 |
| F-B05 | historial-de-la-lavanderia | `HistorialDeLaLavanderiaController.php:86` | sí | P | Sin ingreso ni calificación promedio · ídem |
| F-B06 | alta-de-lavanderias | `LavanderiaDelSocioController.php:181`; `movil/app/lavanderia/perfil.tsx` | sí | C | Cupo · DD-099 |

**Módulo C — Socio repartidor** (`:18:A.3`). Criterio: A43-6.

| F | Spec | Código (B) | Test | Estado | Lo que falta · bloqueo |
|---|---|---|---|---|---|
| F-C01 | repartidor | `RepartidorController.php:77`; `movil/app/registro-de-repartidor.tsx` | sí | C | Nada enlaza al registro desde la app · DD-053 |
| F-C02 | repartidor | `PedidosDelRepartidorController.php:94-125` | sí | P | Nadie crea la asignación (`PedidoAsignacion.php:17-18`) · DD-014 |
| F-C03 | repartidor | `PedidosDelRepartidorController.php:142`; `EvidenciaDeEntrega.php` | sí | C | Evidencia opcional · DD-110, DD-140 |
| F-C04 | repartidor | `HistorialDelRepartidorController.php:53` | sí | P | Sin monto ni calificación · DD-019, DD-052 |
| F-C05 | repartidor | `HistorialDelRepartidorController.php:67` | sí | P | Sin ingreso acumulado · DD-019, DD-013 |

**Módulo D — Administración** (`:19:A.3`). Criterio: A43-7. Todo vive en Filament web; en la app no hay nada (S1-04).

| F | Spec | Código (B) | Test | Estado | Lo que falta · bloqueo |
|---|---|---|---|---|---|
| F-D01 | alta-de-lavanderias | `servidor/app/Filament/Resources/Lavanderias/` | sí | P | Sin "Alta" (`LavanderiaResource.php:68-71`) · DD-053 |
| F-D02 | repartidor | `servidor/app/Filament/Resources/Repartidores/Actions/AccionesDeEstado.php:34-95` | sí | C | — |
| F-D03 | administracion | `servidor/app/Filament/Resources/Usuarios/` | sí | P | Sin filtro de "usuarios activos" · DD-111 |
| F-D04 | administracion | `servidor/app/Filament/Pages/MonitoreoDePedidos.php:40` (consulta cada 30 s, con mapa) | sí | C | Pedido cancelado · DD-016 |
| F-D05 | administracion | `ComisionGlobal.php`, `ComisionesPorLavanderia.php` | sí | P | Comisión del repartidor y cálculo por pedido · DD-019, DD-020 |
| F-D06 | administracion | `ReporteDeIngresos.php`, `Conciliacion.php` | sí | P | Comisiones; conciliación contra la pasarela simulada · DD-020, DD-102 |
| F-D07 | administracion | `servidor/app/Filament/Resources/AvisosMasivos/` | sí | P | Sin envío segmentado · DD-113 |

**Landing** (`:15:A.2`). Criterios A42-1 a A42-4.

| F | Spec | Código (B) | Test | Estado | Lo que falta · bloqueo |
|---|---|---|---|---|---|
| F-L01 a F-L04, F-L06, F-L08 a F-L10, F-L12 | landing | `servidor/resources/views/portada/index.blade.php:17-30` y `portada/secciones/*`; pie en `publico/pie-legal.blade.php` | sí (PHP y Playwright) | C (9) | Contenido de prueba · DD-117, DD-080, DD-120 |
| F-L07 | landing | `portada/secciones/cobertura.blade.php` | sí | C | Sin zonas reales del territorio · DD-093, DD-094 |
| F-L05, F-L11 | landing | `FormularioDeLaPortadaController.php:49-55` | sí | P (2) | El formulario no llega a ningún lado · DD-119 |

**Indicadores** (`:22:A.5`). Criterios A44-1 y A44-2. Página Filament `Indicadores.php`; refresco cada 30 s (`servidor/config/indicadores.php:24`).

| F | Estado | Lo que falta · bloqueo |
|---|---|---|
| F-I01, F-I04, F-I08, F-I11 | C (4) | F-I11 pinta puntos y polígonos, no una capa de calor (`MapaDeCalor.php:17-19`) |
| F-I05 | C | Monto estimado o final · DD-012 |
| F-I02 | P | Sin "cancelados" · DD-016 |
| F-I03 | P | La tabla que lee nadie la escribe (S1-06) · DD-111 |
| F-I07 | P | Semáforo sin cortes (`servidor/config/indicadores.php:34-36`) · DD-114 |
| F-I09 | P | Una serie, no "barras agrupadas" · DD-115 |
| F-I06, F-I10 | **NI (2)** | No hay widget · DD-083, DD-020 |

**Transversales** (`:19-20:A.3` y otras).

| F | Estado | Evidencia o falta |
|---|---|---|
| F-T01, F-T11 | P / C | Cobro: ver F-A10. Ninguna pantalla pide la tarjeta y `servidor/app/Cobro/SinDatosDeTarjeta.php` limpia los avisos |
| F-T02 | P | FCM a usuarios en Android; ni socios ni iPhone · DD-054, DD-069 |
| F-T03 | C | `react-native-maps` sin `provider`: Apple en iPhone y Google en Android (`movil/src/cercanas/MapaDeLavanderias.tsx:38`); test cercanas-E01/E02 |
| F-T04 | **NI** | Sin despliegue ni hosting (`openspec/changes/publicacion/design.md:4-5`) · DD-070 |
| F-T05, F-T15 | C | Tablas de operación con `id_plazas`; test plazas-E09 (`servidor/tests/Feature/Plazas/FilaDeOperacionConPlazaTest.php`) |
| F-T06 | C | `servidor/app/Fotos/AlmacenDeFotos.php`; el bucket S3 no existe · DD-070 |
| F-T07 | C | `expo-secure-store` en `movil/src/sesion/almacen.ts` |
| F-T08 | C | `servidor/app/Http/Middleware/SoloHttps.php`; sin desplegar |
| F-T09 | C | `servidor/app/Models/Usuario.php:73` (`hashed`) |
| F-T10 | C | Componente único `servidor/app/Autorizacion/Autorizacion.php`; barrido contra fugas autorizacion-E14 |
| F-T12 | P | Una sola base de código (`movil/app.config.ts`, `eas.json`); sin compilación de iPhone ni envío · DD-017, DD-084 |
| F-T13 | C | Correos en cola; transporte `log` · DD-071 |
| F-T14 | P | CSV y Excel para cualquier rango; sin exportadores de F-I06, F-I10 ni de comisiones |
| F-T16 | P | GTM, GA4 y Pixel por GTM; sitemap y JSON-LD; sin ID de GTM ni Search Console · DD-118, DD-023 |
| F-T17 | P | Lighthouse CI ≥ 0.70 sobre `127.0.0.1` (`servidor/lighthouserc.json:4,16`); no sobre el sitio publicado |
| F-T18 | P | Páginas y carga por versión; textos vacíos · DD-080 |

**Conteo:** 76 especificadas · **43 C · 30 P · 3 NI** (F-I06, F-I10, F-T04) · 0 probadas.

| Bloque | Total | C | P | NI |
|---|---|---|---|---|
| Módulo A | 17 | 11 | 6 | 0 |
| Módulo B | 6 | 3 | 3 | 0 |
| Módulo C | 5 | 2 | 3 | 0 |
| Módulo D | 7 | 2 | 5 | 0 |
| Landing | 12 | 10 | 2 | 0 |
| Indicadores | 11 | 5 | 4 | 2 |
| Transversales | 18 | 10 | 7 | 1 |
| **Total** | **76** | **43** | **30** | **3** |

### Criterios de aceptación del A.4 (`NSV-2026-001-v8.pdf:20-21`)

| ID | Criterio | Hoy | Por qué |
|---|---|---|---|
| A41-1 | Logotipo en 4 formatos | parcial | Hay archivos; falta el cotejo contra el A.1 (trazabilidad). No se cotejó aquí |
| A41-2 | Manual de 15 páginas o más | parcial | 18 páginas según la trazabilidad; hay rondas abiertas y no está notificado. No se verificó aquí |
| A41-3 | 12 plantillas en Canva o Figma | **no** | No existen (DD-050, DD-085) |
| A41-4 | Guía de tono en PDF | parcial | Existe según la trazabilidad; no está notificada |
| A42-1 | 12 secciones en Chrome, Safari y Firefox | parcial | Codificadas y probadas en Playwright con los tres motores; contenido de prueba |
| A42-2 | GA4 y Pixel registran eventos | **no** | Sin cuentas ni ID de GTM (DD-118) |
| A42-3 | Publicado con candado | **no** | Sin hosting ni dominio desplegado (DD-070, DD-023) |
| A42-4 | PageSpeed móvil ≥ 70 | parcial | Umbral en Lighthouse CI local; no sobre el sitio publicado |
| A43-1 | App compilada y entregada a las tiendas | **no** | Sin compilación firmada ni cuentas (DD-017, DD-084) |
| A43-2 | Recorrido completo sin errores | **no** | S1-01 |
| A43-3 | Cambio de estado en ≤ 60 s | parcial | Maestro en Android, un paso; iPhone bloqueado |
| A43-4 | Conekta en producción | **no** | S1-11 |
| A43-5 | Avisos en iPhone y Android, cinco momentos | **no** | Cuatro momentos, sin iPhone (S1-07, S1-05) |
| A43-6 | Cuatro módulos accesibles y funcionales | **no** | Los socios no pueden entrar (DD-053); el Módulo D no está en la app (S1-04) |
| A43-7 | Panel con datos reales de pedidos y usuarios activos | parcial | Pedidos sí; usuarios activos no (S1-06) |
| A44-1 | 11 indicadores en tiempo real | parcial | 9 de 11 widgets, refresco cada 30 s |
| A44-2 | Reportes en CSV o Excel | parcial | Falta el exportador de 2 indicadores y el de comisiones |
| A45-1 | Perfiles de marca configurados | **no** | Sin evidencia |
| A45-2 | Capacitación impartida | **no** | Sin evidencia; va antes de la publicación |

**0 se cumplen · 9 parciales · 10 en no.**

### Lo que debía existir a la semana 12, contra lo que existe

| Según el calendario (`calendario-contractual.md:16-29`, `:37-44`) | Hoy |
|---|---|
| S1 (7–13 sep): EL CLIENTE arranca los trámites del B.10 | Ninguno empezado (DD-084) |
| S4 (28 sep–4 oct): alta en Conekta | Sin alta; DD-079 y DD-056 abiertas |
| S4–9 (28 sep–8 nov): primera iteración de la app | El primer commit de código es del 2026-11-20 (`e9eb3dd`). Del 20 al 24 de noviembre se escribió código de 73 de las 76 funciones |
| S5–6 (oct): cuenta de Apple | No existe (DD-069, DD-017) |
| S9–13 (2 nov–6 dic): segunda iteración | En curso |
| S10–12 (9–29 nov): landing y plantillas | Landing codificada con contenido de prueba; plantillas inexistentes |
| S12 (23–29 nov): aviso de privacidad publicado | No hay texto (DD-080) |
| S12–13 (23 nov–6 dic): panel de indicadores | 9 de 11 en Filament; la decisión 69 lo quiere en la app |
| S13–14 (30 nov–13 dic): pruebas de aceptación | No arrancan sin entorno de prueba (DD-070) |
| S14 (7–13 dic): envío a las tiendas | **No está listo** (S1-02) |

### Restricciones no negociables, una por una (`vault/contrato/restricciones-no-negociables.md`)

| RN | Hoy | Evidencia |
|---|---|---|
| RN-01 una sola base de código | cumple en código; "idéntico" sin probar en iPhone | `movil/app.config.ts` · S1-05 |
| RN-02 publicación simultánea | no iniciado | S1-02 |
| RN-03 multi-plaza | cumple en el esquema | plazas-E09 |
| RN-04 configurar solo el territorio inicial | no: sin zonas reales | `PlazasDePruebaSeeder.php:15-18` · DD-093 |
| RN-05 ≤ 60 s | parcial | F-A12 |
| RN-06 avisos en cinco momentos | parcial | S1-07 |
| RN-07 cifrado | codificado, sin desplegar | `SoloHttps.php` |
| RN-08 contraseñas no recuperables | cumple en código | `Usuario.php:73` |
| RN-09 separación por tipo de usuario | codificado | `Autorizacion.php`; segmento 4 |
| RN-10 sin números de tarjeta | cumple en diseño | checkout redirigido; `SinDatosDeTarjeta.php` |
| RN-11 sin datos sensibles ni verificación documental | cumple | la migración de repartidores no tiene columnas de documentos (`2026_11_23_100000_crear_repartidores_asignaciones_y_evidencias.php:15-16`) |
| RN-12 sin asignación automática, rutas, GPS continuo ni liquidaciones | cumple | no existen; ubicación solo en primer plano (`movil/src/cercanas/ubicacion.ts`) |
| RN-13 CSV y Excel, cualquier rango | parcial | F-T14 |
| RN-14 11 métricas en tiempo real | parcial | 9 de 11 |
| RN-15 ventanas de 1 h | cumple | `Agenda.php:168` |
| RN-16 tres tipos de servicio; precio por kilo | parcial | el seco sin precio · DD-010 |
| RN-17 correo y contraseña, más Google | cumple en código (Android) | F-A02 |
| RN-18 los cinco estados | cumple en código | `CambioDeEstado.php`; F-A12 |
| RN-19 recorrido completo | no | S1-01 |
| RN-20 cuentas a nombre del cliente | SIN EVIDENCIA | no hay cuentas creadas (DD-084) |
| RN-21 retención de accesos | no aplica todavía | — |
| RN-22 costo de infraestructura en rango | NO VERIFICADO: no hay hosting | DD-070 |
| RN-23 consumo de mapas acotado | NO VERIFICADO aquí | va al segmento 5 |
| RN-24 sin SMS | cumple | no existe |
| RN-25 landing | parcial | A42-1 a A42-4 |
| RN-26 formatos de logotipo | NO VERIFICADO aquí | A41-1 |
| RN-27 aviso y T&C los redacta el cliente | cumple: no hay textos propios | `textos-legales`; DD-080 |
| RN-28 Conekta, tarjeta y OXXO Pay | **no sustituida**; integración parcial; sin OXXO; sin Orden de Cambio | S1-11 |
| RN-29 Google en Android y Apple en iPhone | cumple en código | F-T03 |
| RN-30 servicio gratuito de Google (FCM) | cumple en Android | F-A17 |
| RN-31 GA4, Pixel, GTM y Search Console | parcial | F-T16 |
| RN-32 Google Sign-In | cumple en código; Apple sin iniciar | DD-069 |
| RN-33 comunicación semanal | SIN EVIDENCIA | S1-03 |
| RN-34 aviso de retraso con 48 h | SIN EVIDENCIA de envío | S1-03 |
| RN-35 notificación de entrega | ninguna | S1-03 |
| RN-36 Orden de Cambio antes de ejecutar | cumplida en la pasarela (no se cambió); ninguna Orden de Cambio archivada | S1-03, S1-11 |
| RN-37 requerimientos escritos | SIN EVIDENCIA | `vault/cliente/requerimientos/` |
| RN-38 soporte | después de la publicación | — |
| RN-39 envío en la semana 14 | **no está listo** | S1-02 |

**Ninguna tecnología que nombra el contrato fue sustituida.** Por eso no hay hallazgo Crítico por la regla 6 del método.

### Fuera de contrato

Lo que existe sin una frase del contrato que lo pida. Ninguna pieza es grande.

| Qué | Dónde (B) | En qué se apoya | Lectura |
|---|---|---|---|
| Borrar la cuenta desde la app, y la página web `/borrado-de-cuenta` | `movil/app/perfil/index.tsx:73`; `servidor/routes/api.php:119-120`; `servidor/routes/web.php:66` | Requisito de las tiendas, grado E SIN VERIFICAR; adenda de ADR-0005 | FUERA DE CONTRATO en sentido estricto; se sostiene en que el contrato manda publicar (inventario, sección 4) |
| Municipio de una lista y colonia por código postal | `servidor/routes/api.php:62-68` | C-238 (Arthur) | Extensión de F-A08 sin cita A |
| Hora de cada estado en el seguimiento | `movil/app/pedido/seguimiento/[id].tsx:5` | Decisión 25 del acta | Extensión de F-A12 sin cita A |
| Contraseña en el registro del repartidor | `movil/app/registro-de-repartidor.tsx:17` | ADR-0005 | El contrato enumera los campos y no la incluye; la sesión la necesita |
| Comando `administracion:crear-cuenta` | `openspec/changes/administracion/tasks.md:67` | Decisión 68 (C-245) | Herramienta de operación |
| Tablero por omisión de Filament | `servidor/app/Providers/Filament/AdminPanelProvider.php:39-46` | Ninguna | Restos del andamiaje |
| Botón "Abrir el panel" | `movil/src/acceso/BotonDelPanel.tsx` | Decisión 56, revocada | S1-14 |

### Preguntas abiertas

**Para Nadir**
1. ¿Se envió a EL CLIENTE, por algún medio, el aviso de retraso, algún reporte semanal o la notificación del branding?
   Si sí, ¿dónde está la constancia? (S1-03)
2. ¿Sostienes la decisión 69 de Arthur (Módulo D completo en la app) con el calendario de la semana 12? (S1-04)
3. ¿Cuándo arranca EL CLIENTE el B.10 y quién decide el hosting? (S1-02; DD-084, DD-070)
4. ¿Confirmó Conekta por escrito si genera fichas de OXXO? ¿Firmará EL CLIENTE la Orden de Cambio de la pasarela? (S1-11)

**Para Arthur**
1. ¿Quién debe tener la DD de "repetir pedido", que hoy solo es BR-25? (S1-08)
2. ¿Cuándo se actualizan la trazabilidad y el plan de sprints con el avance real? (S1-12)
3. ¿Qué tarea escribe `actividad_usuarios`? (S1-06)

**Para EL CLIENTE, vía Nadir:** DD-012 (cuándo se cobra), DD-014 (quién asigna), DD-016 (cancelación), DD-022
(quién marca Entregado), DD-011 (los cinco momentos), DD-053 (cómo entra cada socio), DD-081, DD-117 y DD-080
(contenido y textos legales), DD-093 y DD-094 (zonas del territorio).

### Comandos ejecutados

```bash
git -c core.longpaths=true worktree add --detach <scratchpad>/h3 9d2497775966ef2d8c0c0f204b2f67cfb8432fbd
git diff --stat origin/main 9d24977 -- vault/contrato legal
python -I <scratchpad>/matriz.py . 9d24977   # changes -> F-IDs, escenarios (#### Scenario: x-ENN) y tests que los declaran
                                               # (TestDox / it / test / describe con el ID); tareas [x]/[ ]
grep -nE '^#|^\| *H[0-9]' vault/formas-de-trabajo/sprints/plan-de-sprints.md
grep -m1 -E '\| *DD-0NN *\|' vault/meta/deuda-de-decision.md   # DD-011, 012, 013, 014, 040, 050, 055, 056, 064, 068, 069, 070, 079, 084, 085
ls vault/cliente/{reportes-semanales,notificaciones-de-entrega,ordenes-de-cambio}/
# Verificación propia de lo que reportaron los subagentes (muestreo):
sed -n '56,60p' servidor/app/Http/Requests/PedirCheckoutRequest.php
sed -n '120,126p' servidor/app/Cobro/PasarelaConekta.php
sed -n '140,147p' movil/app/pedido/resumen.tsx
grep -rniE 'repetir' movil/app movil/src servidor/app
grep -nE '^(PASARELA|MAIL_MAILER)=' servidor/.env.example
sed -n '66,71p' servidor/app/Filament/Resources/Lavanderias/LavanderiaResource.php
grep -rnE 'actividad_usuarios|ActividadUsuario' servidor/app
ls servidor/app/Filament/Widgets/Indicadores/
sed -n '14,20p' servidor/app/Models/PedidoAsignacion.php
sed -n '9,30p' servidor/app/Identidad/AltaDeCuenta.php
sed -n '60,92p' servidor/app/Pedidos/CambioDeEstado.php
sed -n '68,72p;122,126p' servidor/routes/api.php
sed -n '17,30p' servidor/resources/views/portada/index.blade.php
sed -n '45,56p' servidor/app/Http/Controllers/FormularioDeLaPortadaController.php
sed -n '35,38p' servidor/app/Medicion/InformacionEstructurada.php
```

Los cuatro subagentes de solo lectura (Módulo A; Módulos B y C; Módulo D, indicadores y exportación; landing y
medición) trabajaron sobre el mismo SHA. Sus filas se integraron aquí solo después de verificar por muestreo sus
afirmaciones principales.
