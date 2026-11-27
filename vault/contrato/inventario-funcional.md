---
estado: vigente
grado: mixto
fuente: NSV-2026-001-v8.pdf pp. 4, 15-22, 30 y 31, cotejado contra el PDF el 2026-09-25 (A); brecha-ejecutivo-tecnico.md, restricciones-no-negociables.md, deuda-de-decision.md y las notas de las Fases 4 y 5 (B); acta C-89 a C-93 (C); tamaños y equivalencia en horas, estimación de Claude y supuesto S-006 (S)
fecha: 2026-09-25
dueño: Nadir Hadad Navarrete
---
# Inventario funcional — qué vamos a construir, función por función

Es el producto de la **Fase 5-bis** (`meta/instrucciones/prompt-maestro-01-fundacion-v5.md`). Nadir la
pidió el 2026-09-25 (C-93) con una pregunta que no tenía respuesta escrita: *"¿ya estamos conscientes de
lo que desarrollaremos?"*.

**Qué es.** El contrato desarmado en funciones: cada pantalla, sección e indicador que enumera, con lo
que dice, lo que calla, la capacidad técnica que exige y cuánto pesa.

**Qué no es.** No es una especificación: las specs son de la Fase 7. Tampoco decide cómo se construye:
aquí **no se nombra ningún candidato de stack**. Los únicos nombres de empresas que aparecen son los que
nombra el propio contrato.

## El resultado, en corto

1. **Son 76 funciones.** 35 de la app (17 del usuario, 6 de la lavandería, 5 del repartidor y 7 de
   administración), 12 secciones de la landing, 11 indicadores y 18 funciones transversales que el
   contrato pone en sus servicios, sus medidas de seguridad y sus anexos.
2. **Sí sabemos qué vamos a construir. Lo que falta es cómo se comporta, y eso depende de 12 decisiones
   abiertas** (sección 6). La brecha de la Fase 1 ya había registrado la mayoría de los huecos. El inventario encontró **cuatro nuevos**
   (BR-47 a BR-50) y **amplió tres** filas existentes. Faltaba la vista completa, no la lectura.
3. **Lo nuevo para el equipo es poco, pero es lo más grande.** De 21 capacidades técnicas, **7 son
   propiamente móviles**, justo el terreno donde Nadir no tiene experiencia (C-89). Tocan 14 de las 76
   funciones, y 7 de las 15 funciones grandes. El resto son formularios, listas, reglas en el servidor y
   páginas web: terreno conocido.
4. **Una buena noticia que el contrato trae escondida: la app no necesita ubicación en segundo plano.**
   El rastreo continuo del repartidor está excluido fuera de un pedido activo, y dentro no se exige
   (RN-12, BR-30). Es de las piezas móviles que las tiendas revisan con más cuidado (grado E,
   `SIN VERIFICAR`).
5. **Con la estimación media, no está listo.** El alcance pide unas **390 horas** de Nadir. La
   capacidad declarada da **entre 135 y 270** en las nueve semanas de desarrollo. El número depende de
   un supuesto (S-006), que se calibra en las primeras dos semanas de desarrollo. La sección 7 da el
   detalle.
6. **Para la Fase 6, una sola decisión abierta cambia la forma del stack: DD-040**, si la administración
   vive en la app o en web. Las demás cambian specs, no tecnología.

## Cómo se lee

- **Lo que dice el contrato** va literal, entre comillas. Es grado A.
- **Lo que no dice** remite a su fila de la brecha (`BR-`) o de la deuda (`DD-`). Si no tenía fila, se
  abrió.
- **Capacidad** es lo que la función le exige a la tecnología, sin decir cuál.
- **Corre en:** teléfono, servidor o web. "DD-040" significa que depende de dónde viva la administración.
- **Tamaño** es relativo y es estimación de Claude, no dato:
  - **S**: pantalla de lectura o formulario simple, sin servicio externo.
  - **M**: lógica de negocio, o un servicio externo que otra función ya resolvió.
  - **L**: servicio externo nuevo, tiempo real, o un flujo con varios estados.

---

## 1. Inventario

### Módulo A — Usuario final · `NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3`

| ID | Pantalla o función | Lo que dice el contrato | Lo que no dice | Capacidad | Corre en | Tam. |
|---|---|---|---|---|---|---|
| F-A01 | Splash & Onboarding | "Pantalla de bienvenida y 3 pantallas informativas sobre el servicio." | Los textos e imágenes son insumo de EL CLIENTE (III.B), en el tono de la guía | — | teléfono | S |
| F-A02 | Registro e inicio de sesión | "Correo y contraseña, con opción de Google Sign-In." | Verificación del correo; si la tienda de Apple exige además iniciar sesión con Apple → BR-14. La recuperación de contraseña sí está: B.5 presupuesta el correo "al recuperar una contraseña" | Inicio de sesión nativo con terceros; sesión persistente | teléfono + servidor | L |
| F-A03 | Pantalla principal | "Mapa con lavanderías cercanas activas. Acceso rápido a pedido nuevo." | Qué es "cercana" y qué es "activa" → BR-15 | Mapa nativo por plataforma; ubicación en primer plano | teléfono + servidor | L |
| F-A04 | Búsqueda y filtros | "Filtros por distancia, calificación y disponibilidad." | Radio, orden por defecto, qué es "disponibilidad" → BR-15 | Consulta geográfica | servidor | M |
| F-A05 | Perfil de la lavandería | "Fotos, descripción, servicios, precios por kilo, calificación y reseñas." | Unidad del lavado en seco → BR-16; quién fija el precio → BR-46 | Imágenes servidas | teléfono + servidor | M |
| F-A06 | Selección de servicio | "Tipos: lavado estándar, lavado con planchado y lavado en seco." | Catálogo por socio → BR-16. El calzado no está (DD-046) | — | teléfono | S |
| F-A07 | Programación de recolección | "Selector de fecha y hora, con ventanas de 1 hora." | Anticipación, cupo por ventana, ventana de entrega → BR-17 | — | teléfono + servidor | M |
| F-A08 | Dirección de recogida y entrega | "Ingreso manual o desde mapa. Guardado de direcciones favoritas." | Validación contra la zona y costo de geocodificar → BR-18, BR-12 | Mapa nativo por plataforma; geocodificación | teléfono + servidor | L |
| F-A09 | Resumen del pedido | "Servicio, fecha, dirección, precio estimado y política de cancelación." | Cómo se estima antes de pesar → BR-16, DD-012; la política misma → BR-19, DD-016 | — | teléfono + servidor | M |
| F-A10 | Pasarela de pago | "Conekta: tarjeta de crédito o débito y OXXO Pay." | Cuándo se cobra; OXXO confirma horas después → BR-20, DD-012 | Tarjeta capturada en el teléfono sin tocar el servidor; ficha OXXO; confirmación diferida de la pasarela | teléfono + servidor | L |
| F-A11 | Confirmación de pedido | "Número de folio, resumen y notificación push más correo." | Formato del folio y proveedor de correo → BR-21 | Avisos; correo | servidor | M |
| F-A12 | Seguimiento de estado | "Flujo: Confirmado → Recolectado → En proceso → Listo → Entregado." | Cómo se mapean los estados del socio y del repartidor a estos cinco, y **qué pasa con un pedido cancelado o rechazado**, que no es ninguno de los cinco → BR-22; el mecanismo para 60 s → BR-23 | Tiempo real ≤ 60 s | teléfono + servidor | L |
| F-A13 | Historial de pedidos | "Lista con filtro por fecha y opción de repetir pedido." | Qué se repite exactamente → BR-25 | — | teléfono + servidor | M |
| F-A14 | Calificación del servicio | "Escala de 1 a 5 estrellas con comentario opcional." | Cuándo se califica → BR-25. **A quién**: la lavandería y el repartidor reciben calificación (F-B04, F-C04) → **BR-47**, DD-052 | — | teléfono + servidor | S |
| F-A15 | Perfil de usuario | "Nombre, foto, direcciones guardadas, métodos de pago y notificaciones." | **Si las tarjetas se guardan para volver a usarlas** → **BR-50**, DD-055 | Cámara o galería; tarjeta guardada en la pasarela | teléfono + servidor | M |
| F-A16 | Soporte | "Chat de WhatsApp Business y formulario de reporte." | A quién le llega el formulario → BR-25. El número de WhatsApp es de EL CLIENTE | Enlace a WhatsApp | teléfono + servidor | S |
| F-A17 | Notificaciones push | "Confirmación, recolección, listo para entrega y entregado." | Son **cuatro** y el criterio de aceptación exige **cinco** → BR-22, BR-24, DD-011 | Avisos en iPhone y Android | teléfono + servidor | M |

### Módulo B — Socio lavandería · `:17:A.3`

| ID | Pantalla o función | Lo que dice el contrato | Lo que no dice | Capacidad | Corre en | Tam. |
|---|---|---|---|---|---|---|
| F-B01 | Registro de lavandería | "Nombre, dirección, foto, servicios, precios y horario." | Quién aprueba el alta → BR-26; cómo llega la lavandería a su módulo dentro de la app → **BR-48**, DD-053 | Cámara o galería | teléfono + servidor | M |
| F-B02 | Gestión de pedidos activos | "Lista en tiempo real. Aceptar o rechazar con motivo." | Plazo para aceptar y qué pasa si rechaza → BR-26. **Cómo se entera de un pedido nuevo con la app cerrada** → **BR-49**, DD-054 | Tiempo real; avisos al socio | teléfono + servidor | L |
| F-B03 | Actualización de estado | "Botones: Recolectado → En proceso → Listo → En proceso de entrega." | Si la lavandería entrega con su propia gente → BR-44, DD-022 | — | teléfono + servidor | M |
| F-B04 | Historial de pedidos | "Pedidos completados con fecha, monto y calificación recibida." | "Monto": bruto o neto de comisión → BR-31 | — | teléfono + servidor | S |
| F-B05 | Estadísticas básicas | "Pedidos del día y de la semana, ingreso total y calificación promedio." | Ídem → BR-31, DD-013 | — | teléfono + servidor | S |
| F-B06 | Perfil y horarios | "Edición de información de la lavandería y ventanas de disponibilidad." | Cupo por ventana y relación con las ventanas del usuario → BR-17 | — | teléfono + servidor | M |

### Módulo C — Socio repartidor · `:18:A.3`

"Módulo incorporado a solicitud de EL CLIENTE en su versión operativa mínima", y su alcance es "el
expresamente descrito".

| ID | Pantalla o función | Lo que dice el contrato | Lo que no dice | Capacidad | Corre en | Tam. |
|---|---|---|---|---|---|---|
| F-C01 | Registro de repartidor | "Nombre, teléfono, correo, foto de perfil y tipo de vehículo. El alta, validación y autorización del repartidor corresponden a EL CLIENTE." | La validación es de EL CLIENTE y ocurre fuera de la plataforma; la aprobación se registra desde F-D02. Cómo llega el repartidor a su módulo → BR-48 | Cámara o galería | teléfono + servidor | S |
| F-C02 | Gestión de pedidos asignados | "Lista en tiempo real de pedidos asignados. Aceptar o rechazar con motivo." | Quién asigna y desde qué pantalla → BR-27, DD-014; aviso con la app cerrada → BR-49 | Tiempo real; avisos al socio | teléfono + servidor | M |
| F-C03 | Actualización de estado | "Botones: Recolectado → En tránsito → Entregado." | Mapeo a los cinco estados del usuario → BR-22; la evidencia fotográfica que el contrato guarda pero ninguna pantalla captura → BR-29 | Cámara (evidencia) | teléfono + servidor | M |
| F-C04 | Historial de entregas | "Entregas completadas con fecha, monto y calificación recibida." | Cuánto gana por entrega y quién le paga → BR-28, DD-019 | — | teléfono + servidor | S |
| F-C05 | Estadísticas básicas | "Entregas del día y de la semana, e ingreso total acumulado." | Ídem → BR-28 | — | teléfono + servidor | S |

### Módulo D — Panel de administración · `:19:A.3`

Lo usa EL CLIENTE. **Dónde vive —en la app o en web— es DD-040**, y por eso la columna "corre en" no
está resuelta.

> **Nota del 2026-11-24.** DD-040 la respondió C-140 y, en su parte web, la reemplazó C-247 (decisión 69 de
> `acta-2026-11-20-modelo-y-pasarela.md`): el Módulo D y el A.5 corren en el teléfono, con el servidor; el panel web
> queda como respaldo interno ([[ADR-0022-administracion-en-la-app]]). La columna "Corre en" lo dice desde esa fecha.

| ID | Pantalla o función | Lo que dice el contrato | Lo que no dice | Capacidad | Corre en | Tam. |
|---|---|---|---|---|---|---|
| F-D01 | Gestión de lavanderías | "Alta, baja, suspensión y edición de socios." | Flujo de aprobación del registro → BR-26 | — | teléfono + servidor | M |
| F-D02 | Gestión de repartidores | "Alta, baja, suspensión y edición de socios repartidores." | — | — | teléfono + servidor | M |
| F-D03 | Gestión de usuarios | "Consulta de usuarios activos, historial y bloqueo." | Qué es un usuario "activo"; lo usa también F-I03 → BR-33 | — | teléfono + servidor | M |
| F-D04 | Monitoreo de pedidos | "Vista en tiempo real de todos los pedidos activos con mapa." | Qué se pinta → BR-30: puntos del pedido, sin rastreo del repartidor | Mapa; tiempo real | teléfono + servidor | L |
| F-D05 | Configuración de comisiones | "Porcentaje de comisión por socio o global." | Base de la comisión → BR-31; gobierno del precio → BR-46 | — | teléfono + servidor | M |
| F-D06 | Reportes financieros | "Ingresos por periodo, comisiones y conciliación con Conekta." | Modelo del dinero → BR-31, DD-013 | Lectura de movimientos de la pasarela | teléfono + servidor | L |
| F-D07 | Notificaciones push masivas | "Envío segmentado o global para promociones o mantenimiento." | Por qué se segmenta → BR-32 | Avisos masivos | teléfono + servidor | M |

### Landing — 12 secciones · `:15:A.2`

Corre en web. La ve el público.

| ID | Sección | Lo que dice el contrato | Lo que no dice | Capacidad | Tam. |
|---|---|---|---|---|---|
| F-L01 | Hero / Encabezado | "Logo, tagline, CTA principal de descarga e imagen hero (mockup de la app)." | El mockup necesita pantallas terminadas | — | S |
| F-L02 | ¿Cómo funciona? | "Paso a paso visual: Pide → Recogemos → Lavamos → Entregamos." | — | — | S |
| F-L03 | Beneficios para usuarios | "3 pilares: comodidad, precio y confianza. Iconos animados." | — | — | S |
| F-L04 | Descarga la app | "Botones de App Store y Google Play. Mockup de pantalla." | Los enlaces solo existen cuando la app está publicada | — | S |
| F-L05 | Lavanderías partner | "Invitación a lavanderías a unirse. Formulario de interés." | A dónde llega el formulario → BR-41 | Formulario con destino | M |
| F-L06 | Beneficios para socios | "3 pilares: más clientes, pagos seguros y cero gestión de cobranza." | **"Cero gestión de cobranza" promete que la plataforma cobra por el socio**, y eso depende de un modelo de dinero que no está decidido → BR-31, DD-013 | — | S |
| F-L07 | Cobertura | "Mapa interactivo con las zonas de servicio del TERRITORIO INICIAL: Estado de Durango, y municipios de Torreón y Matamoros, Coahuila." | Ya decidido: polígonos propios, sin teselas de pago (`arquitectura/presupuesto-de-mapas.md`, decisión 1) → BR-12, BR-02 | Mapa en web | M |
| F-L08 | Testimonios | "3 a 5 tarjetas de opiniones de usuarios beta (marcador de posición en lanzamiento)." | Cómo las edita EL CLIENTE → BR-11 | Contenido editable | S |
| F-L09 | Preguntas frecuentes | "Acordeón expandible con 6 a 8 preguntas." | Preguntas y respuestas son insumo de EL CLIENTE (III.B) | — | S |
| F-L10 | Prensa / Reconocimientos | "Logos de medios o menciones, activables conforme se obtengan." | Quién los activa → BR-11 | Contenido editable | S |
| F-L11 | Contacto & Soporte | "Correo, WhatsApp Business y formulario de contacto." | A dónde llega el formulario → BR-41, ampliada | Formulario con destino | S |
| F-L12 | Footer legal | "Aviso de privacidad, términos de servicio y aviso de derechos de autor." | Los textos los redacta EL CLIENTE → RN-27, F-T18 | — | S |

### Indicadores — 11 métricas · `:22:A.5`

"Panel de indicadores integrado en la sección de administración". Corre donde viva el Módulo D (DD-040): desde el
2026-11-24, en el teléfono, con el servidor (C-247; [[ADR-0022-administracion-en-la-app]]).

| ID | Categoría | Métrica | Visualización | Lo que no dice | Tam. |
|---|---|---|---|---|---|
| F-I01 | Operaciones | "Pedidos totales (diarios, semanales y mensuales)" | "Gráfica de barras y tabla comparativa" | Qué es "en tiempo real" en un indicador → BR-33 | M |
| F-I02 | Operaciones | "Pedidos por estado (activos, completados y cancelados)" | "Gráfica de dona" | **El estado "cancelado" no existe en ningún modelo del vault** → BR-22, ampliada | S |
| F-I03 | Usuarios | "Usuarios activos por día, por semana y por mes" | "Línea de tendencia" | Qué es un usuario activo → BR-33 | M |
| F-I04 | Usuarios | "Porcentaje de usuarios que vuelven a pedir, agrupados según el mes en que se registraron" | "Tabla comparativa" | Cohortes: costo de consulta → BR-33 | L |
| F-I05 | Usuarios | "Monto promedio por pedido" | "Indicador destacado con tendencia" | Monto estimado o final → BR-16 | S |
| F-I06 | Socios | "Lavanderías activas frente a registradas" | "Indicador destacado" | Qué es "activa" → BR-15 | S |
| F-I07 | Socios | "Calificación promedio por lavandería" | "Ranking con semáforo" | **Los umbrales del semáforo** → BR-33, ampliada | M |
| F-I08 | Socios | "Entregas completadas por repartidor" | "Tabla comparativa" | — | S |
| F-I09 | Financiero | "Ingresos brutos por periodo" | "Gráfica de barras agrupadas" | Agrupadas por qué → BR-31 | M |
| F-I10 | Financiero | "Comisiones generadas por socio y en total" | "Tabla detallada con total" | Base de la comisión → BR-31 | M |
| F-I11 | Geográfico | "Mapa de calor de pedidos por colonia o zona" | "Mapa interactivo" | De dónde sale la colonia → BR-33 | L |

La exportación en CSV y Excel que el A.5 pide al final va en F-T14.

### Transversales · `:19-20:A.3` y otras partes del contrato

Lo que ninguna pantalla muestra y todas necesitan. F-T01 a F-T11 son literales de "Servicios externos"
y "Medidas de seguridad"; F-T12 a F-T18 salen de otras cláusulas y anexos.

| ID | Qué | Lo que dice el contrato | Lo que no dice | Corre en | Tam. |
|---|---|---|---|---|---|
| F-T01 | Cobro con tarjeta y en OXXO | "Permite que el usuario pague dentro de la aplicación. Se utilizará Conekta" (p. 19) | → F-A10, BR-20 | servidor | contado en F-A10 |
| F-T02 | Avisos al celular | "Envía mensajes automáticos al teléfono del usuario […] Se utiliza el servicio gratuito de Google" (p. 19) | Solo nombra al usuario; los socios también los necesitan → BR-49. RN-30 | teléfono + servidor | L |
| F-T03 | Mapas y ubicación | "Se utilizan los mapas de Google en teléfonos Android y los mapas de Apple en iPhone" (p. 19) | Qué mapa usa la web (landing y, si es web, la administración) → BR-12. RN-29 | teléfono + web | M |
| F-T04 | Servidor de la aplicación | "recibiendo y procesando cada pedido, cada inicio de sesión y cada consulta de los usuarios" (p. 19) | Proveedor y costo dentro del rango → BR-37 | servidor | L |
| F-T05 | Base de datos | "usuarios registrados, lavanderías, repartidores, pedidos, pagos e historial completo" (p. 20) | Proveedor → BR-37; modelo multi-plaza → BR-01 | servidor | L |
| F-T06 | Almacenamiento de imágenes | "Guarda las fotografías de las lavanderías, las fotos de perfil y las evidencias de entrega" (p. 20) | Cuándo es obligatoria la evidencia → BR-29 | servidor | M |
| F-T07 | Control de acceso | "mantiene su sesión abierta de forma segura, sin necesidad de que escriba su contraseña cada vez" (p. 20) | Proveedor → BR-14, BR-37 | teléfono + servidor | M |
| F-T08 | Comunicaciones cifradas | "Toda la información que viaja entre el teléfono del usuario y el servidor va codificada" (p. 20) | — RN-07 | servidor | S |
| F-T09 | Protección de contraseñas | "Las contraseñas no se guardan en texto legible" (p. 20) | — RN-08 | servidor | S |
| F-T10 | Separación por tipo de usuario | "Un socio lavandería no puede consultar los datos de otro socio, ni los de los usuarios que no le han hecho pedidos" (p. 20) | Modelo de autorización y prueba contra fugas → BR-35. RN-09 | servidor | L |
| F-T11 | Datos de tarjeta | "Los números de tarjeta nunca se guardan en la plataforma" (p. 20) | — RN-10. Con tarjetas guardadas, se guarda solo la referencia de la pasarela → BR-50 | teléfono + servidor | contado en F-A10 |
| F-T12 | Una sola app, publicada en las dos tiendas | "un solo programa que funciona tanto en iPhone como en teléfonos Android" y "se publicará de forma simultánea" (p. 16); "compatible con la gran mayoría de los teléfonos" (p. 21) | Versiones mínimas → BR-42; cómo conviven los cuatro roles en una app → BR-48; canal de prueba → BR-06 | teléfono | L |
| F-T13 | Correos automáticos | "Los correos que envía el sistema al confirmar un pedido o al recuperar una contraseña" (p. 30, B.5) | Proveedor → BR-21 | servidor | M |
| F-T14 | Exportación | "Descarga de reportes en formato CSV y Excel para cualquier rango de fechas" (p. 22) | Columnas y límite → BR-34 | servidor | M |
| F-T15 | Multi-plaza | "expansión a plazas adicionales de la República Mexicana sin necesidad de reescritura del sistema ni de rediseño de la base de datos" (p. 4, I) | Criterio verificable → BR-01, `arquitectura/modelo-multi-plaza.md` | servidor | contado en F-T05 |
| F-T16 | Medición y buscadores de la landing | Google Analytics, Meta Pixel, el administrador de etiquetas y la consola de búsqueda; "títulos y descripciones", "mapa del sitio" e "información estructurada" (p. 15) | Plan de eventos → BR-11. RN-31 | web | M |
| F-T17 | Velocidad, dominio y candado de la landing | Carga rápida "con prioridad en teléfonos celulares" (p. 16); "candado de seguridad" y 70 en PageSpeed (p. 21) | Cuándo y en qué URL se mide → BR-11. RN-25 | web | S |
| F-T18 | Aviso de privacidad y términos dentro del producto | "incorporará en el sitio web y en la aplicación los documentos que EL CLIENTE le proporcione" (p. 31, B.7) | Los redacta EL CLIENTE; las tiendas exigen el aviso publicado antes de revisar la app (p. 31) | teléfono + web | S |

---

## 2. Lo que la operación necesita y el contrato no enumera

Para que la plataforma funcione hacen falta cosas que el A.3 no lista como pantalla. **No se agregan
pantallas por iniciativa propia.** Cada una se resuelve de una de tres formas: dentro de una función
que sí está enumerada, como dato de configuración sin pantalla, o por Orden de Cambio.

| Qué hace falta | Por qué | Fila |
|---|---|---|
| Asignar un repartidor a un pedido | F-C02 dice "pedidos asignados", la asignación automática está excluida y el Módulo D no tiene sección para asignar | BR-27, DD-014 |
| Aprobar el registro de un socio | F-B01 y F-C01 registran; el alta está en F-D01 y F-D02 | BR-26 |
| Cancelar un pedido y reembolsar | F-A09 muestra una política de cancelación y F-I02 cuenta cancelados, pero no hay flujo | BR-19, BR-22, DD-016 |
| Cargar las zonas de servicio del territorio inicial | Es obligación de EL PRESTADOR (O-27), sin pantalla en el Módulo D | BR-02 |
| Abrir una plaza, y configurar el envío y la banda de precios por plaza | El criterio de O-26 es "abrir una plaza = datos de configuración, cero migraciones" | BR-01, DD-010, DD-019 |
| Recibir los formularios de la landing y el reporte de soporte | F-L05, F-L11 y F-A16 tienen formulario sin destinatario | BR-41, BR-25 |
| Eliminar la cuenta desde la app | Requisito de las tiendas, sección 4 | BR-09 |
| Pedir factura | El B.7 presupuesta la facturación electrónica como servicio de terceros a cargo de EL CLIENTE; el A.3 no tiene pantalla para pedirla | BR-31 |

## 3. La frontera: lo que el contrato excluye

Lo excluido también es inventario: son las funciones que **no** se construyen aunque alguien las pida.

- **Del Módulo C** (`:18`): verificación documental del repartidor; datos biométricos; cálculo de
  liquidaciones, propinas o dispersión de pagos; optimización de rutas o asignación automática; GPS
  continuo fuera de un pedido activo; verificación de antecedentes o de identidad. → RN-11, RN-12.
- **Verificación del teléfono por mensaje de texto**: "No forma parte del alcance base" (`:30:B.5`). → RN-24.
- **Datos personales sensibles** (`:10:Cláusula X`). → RN-11.
- **Redacción del aviso de privacidad y de los términos** (`:31:B.7`). → RN-27.

**La consecuencia técnica más útil:** sin rastreo continuo, **la app no pide ubicación en segundo
plano**. La ubicación solo se usa con la app abierta, para las lavanderías cercanas y para elegir una
dirección en el mapa.

## 4. Requisitos de las tiendas que el contrato no nombra

El contrato manda publicar en las dos tiendas, así que las reglas de las tiendas también son alcance
de hecho. **Verificadas el 2026-09-28, grado E:** `arquitectura/verificaciones-fase-6.md`. Solo la primera es grado A; el resto es conocimiento de Claude al 2026-09-25 y se verifica
en la Fase 6 contra las guías vigentes.

| Requisito | Grado | Fila |
|---|---|---|
| Aviso de privacidad publicado en una página accesible antes de la revisión | **A**: "Ambas tiendas exigen que esté publicado en una página web accesible antes de revisar la aplicación" (`:31:B.7`) | RN-27, F-T18 |
| Si se ofrece Google para iniciar sesión, la tienda de Apple puede exigir también iniciar sesión con Apple | E, `SIN VERIFICAR` | BR-14 |
| Eliminar la cuenta desde la propia app, en las dos tiendas | E, `SIN VERIFICAR` | BR-09 |
| Explicar al usuario por qué se piden ubicación, cámara y avisos | E, `SIN VERIFICAR` | F-T12 |

---

## 5. Mapa de capacidades

Es la entrada de la matriz de la Fase 6. **Terreno** separa lo que el equipo ya ha hecho de lo que no,
según lo que Nadir declaró el 2026-09-25 (C-89): experiencia en web y servidor, ninguna en apps móviles.

| # | Capacidad | Funciones que la exigen | Terreno |
|---|---|---|---|
| 1 | Mapa nativo, **distinto por plataforma** (RN-29) | F-A03, F-A08 | **móvil, nuevo** |
| 2 | Ubicación del teléfono, **solo con la app abierta** | F-A03, F-A08 | **móvil, nuevo** |
| 3 | Recibir avisos en el teléfono y pedir el permiso | F-A11, F-A17, F-B02, F-C02, F-T02 | **móvil, nuevo** |
| 4 | Cámara y galería | F-A15, F-B01, F-C01, F-C03 | **móvil, nuevo** |
| 5 | Capturar la tarjeta en el teléfono sin que el número pase por el servidor (RN-10) | F-A10, F-A15 | **móvil, nuevo** |
| 6 | Iniciar sesión con Google, y con Apple si se exige, desde el teléfono | F-A02 | **móvil, nuevo** |
| 7 | Compilar, firmar y publicar en las dos tiendas | F-T12 | **móvil, nuevo** (R-025) |
| 8 | Mapa en web | F-L07; F-D04 y F-I11 si la administración es web | web, conocido |
| 9 | Consulta geográfica y geocodificación | F-A04, F-A08 | servidor, conocido |
| 10 | Enviar avisos desde el servidor, individuales y masivos | F-A17, F-D07, F-T02 | servidor, conocido |
| 11 | Subir y servir imágenes | F-A05, F-A15, F-B01, F-C01, F-C03, F-T06 | servidor, conocido |
| 12 | Pago diferido en OXXO, confirmado por la pasarela más tarde | F-A10, F-D06 | servidor, conocido |
| 13 | Correo automático | F-A02, F-A11, F-T13 | servidor, conocido |
| 14 | Sesión persistente y autorización por rol, tenencia y plaza | F-T07, F-T10 | servidor, conocido |
| 15 | Tiempo real ≤ 60 s | F-A12, F-B02, F-C02, F-D04, F-I01 a F-I11 | servidor, conocido. Con la app cerrada lo cubre el aviso |
| 16 | Exportación CSV y Excel | F-T14 | servidor, conocido |
| 17 | Agregados, cohortes y rankings | F-I01 a F-I10 | servidor, conocido |
| 18 | Mapa de calor | F-I11 | web, conocido |
| 19 | Formularios públicos con destinatario | F-L05, F-L11, F-A16 | web, conocido |
| 20 | Medición web y preparación para buscadores | F-T16, F-T17 | web, conocido |
| 21 | Enlace a WhatsApp | F-A16, F-L11 | web y móvil, trivial |

**En números:** 7 de 21 capacidades son móviles. Tocan 14 de las 76 funciones, pero 7 de las 15 grandes
(F-A02, F-A03, F-A08, F-A10, F-B02, F-T02 y F-T12). La mitad del trabajo pesado está en terreno nuevo.

---

## 6. Huecos

El inventario cita **37 filas de la brecha** y **14 de la deuda**, contadas por script. Casi todo ya estaba registrado desde
la Fase 1: faltaba verlo junto.

**Filas nuevas en `brecha-ejecutivo-tecnico.md`:**

| BR | Hueco | Funciones | Deuda | Recomendación de Claude |
|---|---|---|---|---|
| BR-47 | **A quién califica el usuario.** La lavandería y el repartidor muestran "calificación recibida" y el A.5 hace ranking por lavandería, pero la pantalla del usuario es una sola "calificación del servicio" | F-A14, F-B04, F-C04, F-I07 | DD-052 | Dos calificaciones en la misma pantalla, lavandería y repartidor, cada una opcional |
| BR-48 | **Cómo llega cada rol a su módulo dentro de una sola app.** El contrato habla de "la aplicación" y de cuatro módulos. No dice si el socio se registra desde la misma app, si una cuenta puede tener dos roles, ni cómo se entra a la administración | F-T12, F-B01, F-C01, F-D01 a F-D07 | DD-053 | Una app. El usuario se registra directo; lavandería y repartidor se registran desde su propia entrada y esperan aprobación. Una cuenta, un rol en la v1. La administración, según DD-040 |
| BR-49 | **Avisos a los socios.** El contrato solo enumera avisos al usuario. La lavandería y el repartidor tienen "lista en tiempo real", pero con la app cerrada no se enteran de un pedido nuevo o asignado, y el pedido se queda esperando | F-B02, F-C02, F-T02 | DD-054 | Sí, con el mismo servicio de avisos. Cuesta poco una vez que existen los del usuario |
| BR-50 | **Métodos de pago guardados.** El perfil de usuario incluye "métodos de pago". No dice si la tarjeta se guarda para el siguiente pedido | F-A15, F-A10, F-T11 | DD-055 | Guardar solo la referencia que da la pasarela, nunca el número. Verificar en la Fase 6 que Conekta lo permite |

**Filas existentes que se amplían:**

- **BR-22**: además del mapeo de estados, **el estado de cancelación y el de rechazo**. F-I02 cuenta
  pedidos "cancelados" y ningún modelo del vault los tiene.
- **BR-33**: además de la latencia y las cohortes, **los umbrales del semáforo** del ranking de F-I07.
- **BR-41**: además del formulario de lavanderías (sección 5), **el de contacto** (sección 11).

**Decisiones que bloquean.** Ninguna bloquea el stack salvo **DD-040**. Las que siguen bloquean specs y
diseño de pantallas: DD-011, DD-012, DD-013, DD-014, DD-016, DD-019, DD-022 y las nuevas DD-052 a
DD-055. **Seis de ellas van en el requerimiento a EL CLIENTE del 2026-09-11**, y el vault se contradice
sobre si se envió (sección 8).

---

## 7. Tamaño contra capacidad

### Cuánto hay

| | S | M | L | Contadas en otra fila | Total |
|---|---|---|---|---|---|
| Módulo A | 4 | 8 | 5 | — | 17 |
| Módulo B | 2 | 3 | 1 | — | 6 |
| Módulo C | 3 | 2 | 0 | — | 5 |
| Módulo D | 0 | 5 | 2 | — | 7 |
| Landing | 10 | 2 | 0 | — | 12 |
| Indicadores | 4 | 5 | 2 | — | 11 |
| Transversales | 4 | 6 | 5 | 3 | 18 |
| **Total** | **27** | **31** | **15** | **3** | **76** |

### Cuánto cuesta, con un supuesto

La equivalencia en horas **no es un dato**. Es el supuesto **S-006** (`riesgos-supuestos/supuestos.md`):
horas de Nadir por función —revisar, probar en teléfono, integrar—, con Claude Code escribiendo la mayor
parte del código.

| Banda | S | M | L | Horas totales |
|---|---|---|---|---|
| Optimista | 1 h | 3 h | 8 h | **240** |
| Media | 2 h | 5 h | 12 h | **389** |
| Conservadora | 3 h | 8 h | 20 h | **629** |

### Cuánto hay de tiempo

- **Semanas de desarrollo:** del 5 de octubre, el arranque más temprano realista (R-026), al 6 de
  diciembre, fin de la semana 13. Son **nueve semanas**. Las semanas 13 y 14 son de pruebas; construir en
  ellas se come el margen.
- **Horas:** de 20 a 30 por semana (C-91), o sea **180 a 270**. Si las 5 horas "para el diseño" son del
  contenido de cortesía y no del producto, quedan **135 a 225**.
- `SIN RESPUESTA — bloquea: saber cuál de los dos rangos aplica`. Es la repregunta de C-91.

### La lectura

- **Banda media: no está listo.** Faltan entre **120 y 255 horas**. Haría falta dedicarle unas 43 horas
  por semana.
- **Banda optimista:** cabe solo si las 5 horas de diseño son del producto y Nadir trabaja cerca de 27
  horas por semana.
- **Banda conservadora:** no cabe en ningún caso.

**Lo que se puede mover sin tocar el alcance**, que es contractual y solo cambia por Orden de Cambio:

1. **Las horas.** Subir la dedicación durante las nueve semanas de desarrollo.
2. **El stack.** La matriz de la Fase 6 debe premiar lo que acorta las funciones grandes y nuevas:
   familiaridad, un solo lenguaje en todo el sistema, y servicios administrados donde el trabajo sea L y
   móvil. Son criterios, no candidatos.
3. **Las horas de cortesía.** Las 5 publicaciones semanales son meta, no compromiso (C-71). En las semanas
   de más carga pueden bajar.
4. **El equipo.** C-90 dice "por lo pronto".

**Cuándo se revisa:** al cerrar la segunda semana de desarrollo se comparan las horas reales contra S-006
y se recalcula. Si la banda real es la media o peor, hay que decirlo en el reporte semanal a EL CLIENTE
de esa semana, no al final.

---

## 8. Cotejo con lo que ya estaba hecho

### Contra la Fase 5 — corregido en su nota

1. **El almacenamiento solo hablaba de evidencias.** El contrato guarda también las fotos de las
   lavanderías y las de perfil (p. 20). Corregido en `arquitectura/c2-contenedores.md` y en
   `arquitectura/c3-api.md`.
2. **Nadie tenía a cargo las calificaciones.** Las usan F-A05, F-A14, F-B04, F-C04 y F-I07. Se agrega el
   componente `calificaciones` al C3.
3. **Los avisos a los socios y las tarjetas guardadas** quedan anotados en `avisos` y `cobro` del C3,
   pendientes de DD-054 y DD-055.
4. **Los ADR futuros tenían dos numeraciones que chocaban.** La matriz decía base de datos = 0004,
   tiempo real = 0007, mapas = 0009 y pasarela = 0010; el C2, la cobertura, el modelo multi-plaza y el
   presupuesto de mapas decían 0005, 0008, 0010 y 0011. Se alinearon a la matriz.
5. **El correo no tenía ADR.** La lista del método no lo incluye, y el inventario lo exige (F-T13). Se
   agrega **ADR-0014, correo automático**.

### Contra la Fase 4

Los seis componentes del design system cubren el corazón del pedido. Tocan **10 de las 35 funciones de
la app**: F-A03, F-A04, F-A05, F-A07, F-A09, F-A12, F-B02, F-B03, F-C02 y F-C03.

**Sin componente quedan:**

- **25 funciones de la app:** registro, bienvenida, perfiles, historiales, estadísticas, soporte, los
  registros de socios y **todo el Módulo D**.
- Las 12 secciones de la landing.
- Los 11 indicadores.

Es la carga real del diseño de pantallas que el contrato pone en las semanas 3 a 5. No se construyen
aquí: sirve para dimensionarlo.

### Contra la Fase 1

- **O-16** decía que a los usuarios se les da "alta, baja, suspensión, edición, bloqueo". El contrato
  dice "Consulta de usuarios activos, historial y bloqueo". Corregido.
- **Contradicción que no se puede resolver desde aquí.** El requerimiento de las seis decisiones de
  producto figura como no enviado en `cliente/comunicaciones/2026-09-11-requerimiento-de-insumos-de-producto.md`
  y en el índice. Pero `arquitectura/c3-api.md` dice "el requerimiento enviado al cliente". Lo aclara
  Nadir.

---

## 9. Lo que esto le entrega a la Fase 6

1. **El terreno ya no es una impresión.** 7 capacidades móviles y nuevas, que concentran la mitad del
   trabajo grande; todo lo demás es web y servidor, que el equipo conoce (C-89).
2. **Una decisión abierta cambia la forma del stack: DD-040.** Si la administración es web, sus 7
   funciones y los 11 indicadores caen en terreno conocido.
3. **Verificaciones que van dentro de los ADR:**
   - iniciar sesión con Apple (BR-14);
   - tarjetas guardadas en Conekta (BR-50);
   - vigencia de OXXO Pay;
   - las reglas de tienda de la sección 4.
4. **Un ADR más:** ADR-0014, correo automático.
5. **El tamaño contra la capacidad** pesa sobre dos criterios de la matriz, familiaridad y velocidad. La
   propuesta de pesos va en `arquitectura/matriz-de-criterios-stack.md`, y los ajusta Nadir.
