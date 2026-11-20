---
estado: vigente
grado: B
fuente: estructura del repo
fecha: 2026-10-09
dueño: Nadir Hadad Navarrete
---
# Índice (MOC raíz)

Empieza por `CLAUDE.md` en la raíz del repo y por [[convenciones]].

## Meta

- [[prompt-maestro-01-fundacion-v5]] — **documento fuente del método**: las fases, el tablero, la regla de evidencia, el formato de respuesta y las prohibiciones. Es la v4 más la Fase 5-bis, análisis funcional (2026-09-25, C-93)
- [[arranque-sesion-fase-6]] — el bloque para abrir la sesión de la Fase 6: qué leer, cómo conducir la sala y qué no se negocia (C-105)
- [[prompt-recuperar-informe-fase1]] — el bloque para buscar en otro equipo el informe de junio y su Excel de lavanderías, y fusionarlo con el listado nuevo (C-113)
- [[prompt-maestro-01-fundacion-v4]] — `superseded` el 2026-09-25. Se conserva: las citas con número de línea de antes de esa fecha apuntan aquí
- [[glosario]] — **las nomenclaturas en una página**: grados, C y DD, identificadores, marcadores y estados
- [[convenciones]] — reglas del vault, grados, marcadores, trabajo entre dos personas
- [[pase-de-proyecto]] — **para retomar el proyecto en otra computadora**: qué está en GitHub, qué se quedó fuera y por qué, qué instalar, en qué estado quedó el trabajo y qué sigue (2026-11-12)
- [[estado-del-proyecto]] — **en qué estado está todo**: fases, calendario, obligaciones, pagos, trámites y lo que sigue, con evidencia por fila. Es la fuente del tablero
- **Tablero del proyecto**: <https://claude.ai/artifact/T2tZpPePdPhccB4GmFRjbK> — espejo del vault, privado. Cómo se actualiza: `herramientas/tablero/README.md`
- [[deuda-de-decision]] — qué falta decidir y quién bloquea
- Actas: [[acta-2026-09-07-fundacion]] · [[acta-2026-09-08-nombres-de-categoria]] · [[acta-2026-09-09-nombres-globales]] · [[acta-2026-09-10-brief-fijado]] · [[acta-2026-09-11-archivo-del-prompt-maestro]] · [[acta-2026-09-11-lavandi-y-arranque-tecnico]] · [[acta-2026-09-12-paleta-y-logotipo]] · [[acta-2026-09-12-marca-visual]] · [[acta-2026-09-12-aprobacion-del-nombre]] · [[acta-2026-09-17-riesgo-aceptado]] · [[acta-2026-09-17-apoyo-con-contenido]] · [[acta-2026-09-19-terminos-del-apoyo-y-canva]] · [[acta-2026-09-20-feedback-del-manual]] · [[acta-2026-09-25-analisis-funcional]] · [[acta-2026-09-29-sesion-tecnica-y-plantilla-landing]] · [[acta-2026-09-30-segunda-revision-del-manual]] · [[acta-2026-10-09-tablero-del-proyecto]] · [[acta-2026-10-09-antes-del-stack]] · [[acta-2026-10-17-listado-de-lavanderias]] · [[acta-2026-10-25-kit-de-socios]] · [[acta-2026-11-05-resultados-de-la-recalibracion]] · [[acta-2026-11-20-decisiones-de-maquetas]] · [[acta-2026-11-20-revision-del-pr-2]]

## Contrato (grado A leído)

- [[nota-de-saneamiento]] — estado del instrumento (bloqueo levantado el 2026-09-07 por C-24 a C-27)
- [[alcance-del-proyecto]] — **qué se entrega, qué no, y qué cuesta aparte**. Cotejado contra el PDF el 2026-09-12. Es el documento que zanja "¿esto estaba incluido?"
- [[obligaciones-ejecutivas]] — qué se entrega y con qué criterio se acepta; incluye las causas que **no** son rechazo válido (C-bis)
- [[inventario-funcional]] — **qué vamos a construir, función por función** (Fase 5-bis): las 35 pantallas de la app, las 12 secciones de la landing y los 11 indicadores, con lo que el contrato dice, lo que no, y la capacidad técnica que exige cada una
- [[brecha-ejecutivo-tecnico]] — qué NO dice el contrato y en qué fase se decide
- [[restricciones-no-negociables]] — lo que toda arquitectura debe cumplir
- [[calendario-contractual]] — semanas, fechas, trámites de terceros, hitos de pago

## Negocio (Fase 2)

- [[propuesta-de-valor]] — marketplace desde cero en las dos plazas; el producto se sostiene sobre el reclutamiento
- [[competencia]] — grado E: nadie con el modelo de esta plataforma en el territorio; los precios locales no están en internet. **Segundo barrido del 2026-09-25**: cuatro modelos de "app de lavandería"; el nuestro solo lo hacen LavaGo y Laundryheap, fuera del territorio; UrbanClean ya tiene sucursal con domicilio en Torreón
- [[listado-de-lavanderias]] — grado E: las 415 lavanderías y tintorerías del DENUE en el territorio, por plaza, en Excel para que el cliente las llame; lecciones aprendidas del informe de junio (la única copia de ese informe, con la marca "Limpia+", está en `negocio/antecedentes/informe_fase1.html`; no se manda así al cliente). Versión para el cliente, solo para prospectar: `cliente/comunicaciones/lavanderias-para-invitar.xlsx`
- [[2026-10-25-propuesta-kit-de-socios]] — propuesta al cliente del kit de 11 piezas para invitar lavanderías y comprobar el negocio, y las tres preguntas que tiene que responder; cortesía (C-116, C-119); sin enviar
- [[encargo-kit-de-prospeccion]] — qué piezas lleva el kit, el límite de la cortesía y las reglas que no rompe; las piezas viven en `cliente/kit-de-prospeccion/`
- [[encargo-revision-ux-y-maquetas-modulo-a]] — la revisión de las cuatro propuestas de maqueta del Módulo A y la Propuesta 5 convergida; las maquetas viven en el lienzo "Lavandi" de claude.ai
- [[recalibracion-2026-10-30]] — preparación de Nadir para la reunión larga con el cliente: quince hallazgos, cuatro caminos (recomendado B con algo de C), decisiones por tomar y agenda. No se comparte
- [[2026-10-30-minuta-de-recalibracion]] — la minuta para que el cliente confirme por escrito lo acordado en la recalibración; se llena con las 21 respuestas de la guía
- [[2026-11-05-reporte-del-formulario]] — reporte para el cliente sobre lo que dejó la reunión (formulario y guía llenados en papel): lo que quedó claro, la primera etapa, ocho descubrimientos y lo que falta; con la transcripción de las 20 fotos. Sin enviar
- Formulario de prioridades y operación (`herramientas/formulario-prioridades/`): 43 funciones del contrato para marcar "para abrir" o "puede esperar", cómo opera en Durango capital y los insumos del cliente. Versiones para imprimir de la guía y del formulario en `cliente/envios/`
- [[metodo-de-precio]] — cómo se genera el precio: cinco decisiones, tres modelos, guion de sondeo
- [[unit-economics]] — modelo, referencia contractual, sensibilidad al envío, tres escenarios
- [[hipotesis-criticas]] — H-001 a H-006

## Marca (Fase 3)

- [[territorio-de-marca]] — qué siente el usuario, qué siente el socio, la tensión Durango/Laguna
- [[mision-vision-valores]] — versión corta, con lo que cada valor prohíbe
- [[candidatos-de-nombre]] — rúbrica, diez candidatos, alternos, descartados, política de `.com`, recomendación
- [[lista-maestra-de-candidatos]] — los 43 nombres en un solo lugar, con puntaje y cuáles ha visto el cliente
- [[decision-de-marca]] — **nombre elegido el 2026-09-11 (C-49)**; sigue en `borrador` hasta verificar dominio, IMPI y colisiones
- guía de tono de voz — hecha: está en los entregables del A.1, abajo

## Marca — arte final

- [[entregables/README|Entregables del A.1]] — **manual de identidad (18 pp., versión 1.2) y guía de tono de voz (3 pp.)**, en PDF. Dos rondas de EL CLIENTE: el 18 y 19 de septiembre y el 29. La 1.2 lleva el logotipo de la palabra del diseñador y "puntualidad" en vez de "rapidez"; **antes de mandarla** hay que confirmar las lecturas de las páginas 4 y 5 (DD-057)
- Las capturas marcadas a mano de EL CLIENTE viven en `cliente/recibido/`, no aquí
- [[README|logotipo]] — **arte oficial**. El símbolo, reconstruido en vector desde el PNG del diseñador (1.34 % de diferencia, todo antialiasing de borde). **El logotipo de la palabra**, extraído sin redibujar del `.ai` del diseñador (0 píxeles difieren en más de 32 niveles). SVG, PDF y PNG de los dos. Su tipografía, Berlin Sans FB, es comercial: R-030
- `marca/visual/svg/` — **superseded**: el símbolo previo, de antes de que el cliente entregara el suyo

## Design system (Fase 4)

- [[design-system]] — modos claro y oscuro, umbrales de accesibilidad y los 38 pares de contraste medidos
- [[componentes]] — los seis componentes base del ANEXO A.3, con lo que cada uno deja `ESPECIFICACIÓN PENDIENTE`
- [[cobertura-anexo-a1]] — **qué del entregable 1 NO resuelve un design system**; hace visible R-011
- `tokens.json` — tokens agnósticos de framework; el adaptador es un ADR de la Fase 6
- `muestra/index.html` — muestra renderizada; pide las dos fuentes a Google Fonts y sin conexión cae a la del sistema
- `canvas/` — los seis componentes dibujados como artboards, con interruptor de modo claro y oscuro
- [[prompt-claude-design]] — **prompt listo para pegar en Claude Design**, en tres rondas, con la trampa de contraste de Agua

## Arquitectura (Fase 5)

- [[c1-contexto]] — actores y sistemas externos, cada uno con su renglón del ANEXO B para que su costo tenga dueño
- [[c2-contenedores]] — seis contenedores con su responsabilidad en una frase; destapa **DD-040**
- [[c3-api]] — componentes de `api`, el contenedor crítico e incierto; `autorizacion` y la prueba anti-fuga
- [[modelo-multi-plaza]] — **criterio de aceptación propuesto para O-26**, la única obligación que no tenía
- [[presupuesto-de-mapas]] — cinco decisiones que bajan el consumo de 6 eventos por pedido a 1 o 2
- [[pasarelas-candidatas]] — ocho pasarelas distintas de Conekta contra seis requisitos; grado E, sin verificar; insumo de `ADR-0010`
- [[cobertura-de-la-brecha]] — dónde quedó cada una de las 25 filas BR de esta fase
- ADRs: [[ADR-0001-formato-de-diagramas]]

## Análisis funcional (Fase 5-bis)

- [[inventario-funcional]] — 76 funciones con su cita, sus huecos y la capacidad técnica que exige cada una. Mapa de capacidades, tamaño contra capacidad y cotejo con las Fases 4 y 5. Abrió BR-47 a BR-50 y DD-052 a DD-055

## Stack (Fase 6)

- [[matriz-de-criterios-stack]] — cuatro puertas eliminatorias y siete criterios con peso propuesto, **revisados el 2026-09-25 con el inventario funcional**. DD-038 respondida. **Detenida hasta que Nadir ajuste los pesos**
- [[planteamiento-fase-6]] — **el paso previo a los pesos** (C-105 a C-107): lo que no está a discusión, cinco puertas, 20 preguntas críticas en seis pasos y las opciones de construir, adaptar o comprar. Es la fuente de la sala
- **Sala de la Fase 6**: <https://claude.ai/artifact/WN6jmpKuxSkgFrf8GDAXDB> — donde Nadir responde; privada. Cómo se usa: `herramientas/sala-fase-6/README.md`
- [[verificaciones-fase-6]] — grado E del 2026-09-28: **OXXO Pay ya no sería de Conekta** (R-028), iniciar sesión con Apple, prueba cerrada de Google Play (R-029), precios de mapas, versiones de iOS y Android en México
- pendiente: catorce ADRs, de `ADR-0002` a `ADR-0015` (el de correo se agregó el 2026-09-25; el de construir, adaptar o comprar, el 2026-10-09)

## Formas de trabajo (Fase 7)

- [[guia-de-arranque]] — **para quien llega**: cómo trabajamos, qué hace Claude solo y qué se le puede pedir
- [[colaboracion-en-el-vault]] — dos personas con Claude Code: ramas, pull requests, qué no edita un agente, cómo se evitan los choques y qué valida la CI
- Trabajo en curso: `meta/trabajo/`, una nota por tarea; el hook de inicio de sesión la lista
- Decisiones pendientes nuevas: `meta/deuda/`, una nota por decisión
- Instrumental de Claude (Fase 8, primera parte): `.claude/` — hooks de inicio y cierre, skills `cerrar-sesion`, `nueva-decision` y `nueva-spec`, subagente `revisor-de-evidencia`
- pendiente: sprint, definición de listo y de terminado, ciclo openspec + TDD, cadencia con el cliente

## Riesgos y supuestos

- [[riesgos]] — R-001 a R-029 (R-004 cerrado por C-49; R-010 superseded por R-010-bis; **R-017 materializado**) · [[supuestos]] — S-002, S-003, S-005 y S-006 (S-001 y S-004 cerrados)

## Cliente

- [[recibido/README|cliente/recibido]] — **el buzón de todo lo que llega de EL CLIENTE**: una subcarpeta por fecha, archivos sin renombrar, y un `indice.md` por tanda que dice qué dice cada uno y a dónde fue. Es el espejo de `comunicaciones/`, que guarda lo que sale
- `cliente/reportes-semanales/` · `cliente/notificaciones-de-entrega/` · `cliente/requerimientos/` · `cliente/comunicaciones/`
- Comunicaciones enviadas: [[2026-09-20-respuesta-al-feedback-del-manual]] (**2026-09-20 13:00, WhatsApp, literal**: devuelve el manual 1.1, pregunta el fondo hueso, deja el calzado como Orden de Cambio y propone que esta ronda cuente como la primera) · [[2026-09-07-lista-de-nombres-para-el-cliente]] y [[2026-09-08-lista-de-nombres-de-categoria]] (ambas enviadas, C-43)
- Sin enviar: [[2026-09-30-confirmacion-de-la-llamada]] (**la que sigue, hoy, por WhatsApp**: pone por escrito lo que se acordó en la llamada y manda el manual 1.2) · [[2026-09-30-respuesta-a-la-segunda-revision]] (superada por la llamada; se conserva como guion de lo que quedó abierto) · [[2026-09-19-acuerdo-de-apoyo-con-contenido]] (la cortesía con principio, final y límites) · [[2026-09-12-alcance-para-el-cliente]] (qué recibe, qué necesita hacer él, qué se cotiza aparte) · [[2026-09-09-aviso-de-retraso-branding]] (**vencido, DD-036**) · [[2026-09-11-requerimiento-de-insumos-de-producto]] (seis decisiones de operación) · [[2026-09-09-lista-de-nombres-globales]] (archivada por C-46, ya no se manda)

## Plantillas

- adr · acta-de-sesion · hipotesis · riesgo · supuesto · spec-openspec · nota-de-decision-de-marca · reporte-semanal-cliente · nota-ejecutiva-cliente · deuda-de-decision · tarea-en-curso
