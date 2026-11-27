---
estado: vigente
grado: B
fuente: auditoría de solo lectura del código de movil/ y servidor/ de la rama feat/h2-lavanderias en 7d09b67 (lo que trae el PR #3), contra las 27 specs de openspec/changes/ y vault/contrato/; la pidió Nadir por medio de Arthur (C-242); hallazgos 4 y 9 cerrados por el concilio del 2026-11-24 (C-245; acta-2026-11-20-modelo-y-pasarela.md, decisiones 41 y 42)
fecha: 2026-11-22
dueño: Arthur Naranjo
---
# Auditoría del PR #3 contra el contrato (2026-11-22)

Nadir preguntó, por medio de Arthur, si se siguieron las instrucciones sin desviarse (C-242). Para responder con evidencia
y no de palabra, un agente revisó en modo de solo lectura cada función, pantalla, endpoint, integración, regla y dato
del código de la rama. La regla con que se midió es `CLAUDE.md` §2-bis y §9.2.

## Veredicto

En lo grueso, el código no se sale del contrato:
- no hay función, pantalla, endpoint ni integración sin cita;
- nada cae en las exclusiones expresas (sección C de [[obligaciones-ejecutivas]]);
- nada sustituye lo que el contrato nombra ([[restricciones-no-negociables]]): Conekta, mapas nativos de cada sistema,
  avisos por FCM, Google Sign-In, y GTM con GA4 y Meta Pixel.

**Pero sí estira la cita en varios puntos, y en uno se sale.** No es "cero desviaciones".

## Hallazgos y qué se hace con cada uno

| # | Hallazgo | Código | Qué se hace |
|---|---|---|---|
| 1 | La foto de evidencia al marcar Entregado se sostiene solo en que la nube "guarda las evidencias de entrega" (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`); ninguna pantalla del Módulo C la pide. El tipo `recoleccion` del CHECK no tiene respaldo | `movil/app/repartidor/pedidos/entrega/[id].tsx:27`; `servidor/app/Repartidores/EvidenciaDeEntrega.php:39` | Se queda la de entrega mientras EL CLIENTE responde DD-110 (ahí está la propuesta de Arthur, C-241). El tipo `recoleccion` se quitó (`bae546a`) |
| 2 | Las lavanderías "pendiente" aparecen al usuario y reciben pedidos. El contrato dice "lavanderías cercanas activas" (`:16:A.3 #3`) y la spec, que solo una con alta aparece. El panel no tiene acción de alta | `servidor/app/Models/Lavanderia.php:150-155`; `servidor/app/Lavanderias/RegistroDeLavanderia.php:24` | **Depende de DD-053 (Nadir):** si el registro espera la aprobación del Módulo D. Hoy el código aplica la respuesta "sin aprobación" para que el recorrido se pueda probar |
| 3 | La comisión "global" del contrato (`:19:A.3, Módulo D #5`: "por socio o global") quedó como global por plaza, sostenida solo por ADR-0016 | `servidor/app/Comisiones/PorcentajesDeComision.php:61`, `:139` | Se corrigió: un solo valor para todo el sistema (decisión 39, `38ffe1c`) |
| 4 | El catálogo de colonias por código postal no tiene cita A; sale de C-238. La lista de municipios sí se sostiene en la Cláusula I. La licencia de los datos no está verificada (DD-138) | `servidor/database/migrations/2026_11_21_900000_crear_catalogo_del_territorio.php` | Se queda como la forma del ingreso manual de la dirección (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #8`): lo aprobó Arthur (C-238) y lo asentó la decisión 29 con la adenda del 2026-11-21 de ADR-0009, decisión 6; lo especifican direcciones-E16 a E20 y direcciones-E20 no rechaza una colonia escrita a mano. Lo ratificó el concilio del 2026-11-24 por delegación de Arthur (C-245), en la decisión 41 de `acta-2026-11-20-modelo-y-pasarela.md` ([[concilio-2026-11-24]]). La carga del archivo real en producción sigue esperando DD-138 (Nadir) |
| 5 | Los testimonios se editan en el panel. El A.2 solo pide de 3 a 5 tarjetas con marcador de posición. El borrado no tenía escenario | `servidor/app/Filament/Resources/Testimonios/TestimonioResource.php` | Se queda como la forma de cumplir el A.2; el borrado recibió su escenario, landing-E26, y su test (`9c7afda`) |
| 6 | El reporte de soporte se rechaza si la cuenta no tiene plaza. El contrato pide "formulario de reporte" sin condiciones (`:17:A.3 #16`) | `servidor/app/Soporte/RecepcionDeReporte.php:46` | Se corrigió: el reporte sin plaza se guarda y lo ve solo la administración (decisión 39, `e128b9f`) |
| 7 | Esquema que ningún código escribe. `usuarios.terminos_aceptados_en` se sale del contrato: la Cláusula X deja los consentimientos a EL CLIENTE. El resto (`reembolsos`, `pedidos.envio`, `pedidos.comision_porcentaje`, `reportes_soporte.estado`) cuelga de escenarios bloqueados por DD-012, DD-016, DD-080 y DD-108 | `servidor/database/migrations/2026_11_20_200000_…:61` | Se quitó `terminos_aceptados_en` (`bae546a`); lo demás se queda sin efecto hasta que respondan sus DD |
| 8 | El renglón "Envío · Por confirmar" del resumen. El contrato enumera servicio, fecha, dirección, precio estimado y política de cancelación (`:16:A.3 #9`) | `movil/app/pedido/resumen.tsx:141` | Se quitó (`b5e0bf0`); el envío queda en DD-019 y DD-025 |
| 9 | La hora de cada estado en el seguimiento sale de la decisión 25 (grado C) y de un mock (DD-135), no del A.3 #12 | `servidor/app/Http/Resources/PedidoResource.php:58-61` | Se queda como detalle del seguimiento: es presentación del Módulo A #12 (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3`) con un dato que el contrato ya manda guardar, el historial completo (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`); lo especifican estados-E24 y la decisión 25, y lo prueban `servidor/tests/Feature/Estados/HoraDelSeguimientoTest.php:49`, `:64` y `:89` en el servidor y `movil/__tests__/estados/seguimiento.test.tsx:265` y `:281` en la app. Lo decidió el concilio del 2026-11-24 por delegación de Arthur (C-245), en la decisión 42 de `acta-2026-11-20-modelo-y-pasarela.md` ([[concilio-2026-11-24]]) |
| 10 | El código aplica lecturas provisionales con su escenario todavía bloqueado, probadas bajo el identificador de otro: calificar solo Entregado (DD-107), "En proceso de entrega" como Listo (ADR-0021), el ruteo por rol (DD-053) y el monto promedio (indicadores-E10) | `servidor/app/Calificaciones/CalificacionDelServicio.php:31` y otros | El `design.md` de cada uno dice ya la lectura provisional y el DD que la puede cambiar (`116939f`); no se marcó nada como resuelto |
| 11 | Comportamientos sin escenario propio: la caducidad de las descargas a los 7 días, el mapa arrastrable de la landing y el orden por distancia por omisión | `servidor/config/exportacion.php:27` | La caducidad recibió su escenario, exportacion-E12, y su test (`9c7afda`); los otros dos se quedan como detalle |

**Lo que no es producto queda apagado.** Las herramientas de desarrollo solo corren en local y en pruebas, y el candado
está en el código:
- la pasarela simulada (`servidor/app/Cobro/Pasarelas.php:17`);
- la página `/desarrollo/pasarela` (`servidor/routes/web.php:58`);
- los comandos `pago:cobro-de-prueba` y `pago:simular-aviso`;
- las cuentas de desarrollo.

Las plazas, las lavanderías de prueba y el geocodificador sin llave solo se apagan en producción: siguen en el entorno
de prueba en internet, como dice ADR-0012. El riesgo es acreditar el A.4.3 ahí con una geocodificación falsa.

## Lo que falta del contrato (no son excesos)

| Falta | Bloqueado por |
|---|---|
| Pago en efectivo, OXXO Pay (`:16:A.3 #10`) | DD-056 |
| Repetir pedido (`:17:A.3 #13`) | DD-107 |
| Lavado en seco | DD-010 |
| Alta de lavanderías en el panel (`:19:A.3, Módulo D #1`) | DD-053 |
| Usuarios activos, que hoy siempre da 0 | DD-111 |
| Avisos en iPhone | DD-069, DD-017 y DD-084 |
| Precio estimado y política de cancelación ("Por confirmar") | DD-012 y DD-016 |
| Aceptar los avisos de Conekta (la verificación de su firma) | Las llaves de prueba (C-170) |

## Lo que la auditoría no cubrió

- **Ni las pruebas ni los flujos de Maestro:** no corrió las pruebas, solo comprobó que existen y su identificador, y
  de Maestro solo buscó los identificadores.
- **Los hallazgos que no se volvieron a comprobar.** Los hallazgos 1 a 10 se comprobaron contra el código y contra el
  texto del contrato; el 11 y algunos faltantes vienen de las sub-revisiones.
- **El aviso crudo de Conekta.** No revisó qué datos personales guarda `eventos_pasarela.contenido`.
