# Graph Report - Lavandi  (2026-11-19)

## Corpus Check
- 156 files · ~194,592 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 19 file(s) not represented in the graph (top: (none) 15, .ai 2, .pptx 1)

## Summary
- 341 nodes · 408 edges · 31 communities (21 shown, 10 thin omitted)
- Extraction: 84% EXTRACTED · 16% INFERRED · 0% AMBIGUOUS · INFERRED: 64 edges (avg confidence: 0.82)
- Token cost: 586,937 input · 0 output

## Community Hubs (Navigation)
- CI del código (ci.yml)
- CLAUDE.md, agentes y skills
- Contrato y su lectura ejecutiva
- Variables de base de datos del CI
- ADRs del stack
- Nombre y marca Lavandi
- Método, encargos y competencia
- Fases 7 y 8: formas de trabajo
- Verificadores de marca y secretos
- CI del vault (vault.yml)
- Recalibración, traspaso y stack
- Actas de septiembre y octubre
- Configuración de OpenSpec
- Entornos, correo y tokens
- Revisiones del manual
- Proyectos base y Enatega
- Rondas de nombres
- Marca visual y paleta
- Marca y dominio de configuración
- Reunión de recalibración
- Fundación y Nazmelia
- Archivo del prompt maestro
- Construir sobre piezas probadas
- Pedidos de noche
- Avisos de retraso
- Kit para sumar lavanderías
- Ronda global y criterio del nombre
- Requerimiento de insumos
- Qué necesito de ti
- Acuerdo de apoyo con contenido
- Confirmación de la llamada

## God Nodes (most connected - your core abstractions)
1. `with` - 11 edges
2. `CLAUDE.md (instrucciones permanentes)` - 10 edges
3. `Verificaciones externas de la Fase 6` - 10 edges
4. `Contrato NSV-2026-001 v8` - 9 edges
5. `ADR-0003 Laravel y Filament` - 9 edges
6. `servidor` - 8 edges
7. `Alternativas a Conekta para tarjeta y efectivo` - 8 edges
8. `Contrato NSV-2026-001 v8` - 8 edges
9. `Decisión de marca — Lavandi` - 8 edges
10. `env` - 7 edges

## Surprising Connections (you probably didn't know these)
- `El contrato no es especificación técnica` --conceptually_related_to--> `Contrato NSV-2026-001 v8`  [EXTRACTED]
  CLAUDE.md → legal/NSV-2026-001-v8.pdf
- `Alternativas a Conekta para tarjeta y efectivo` --references--> `Restricción: pagos con Conekta y OXXO Pay (A.3)`  [EXTRACTED]
  vault/arquitectura/alternativas-de-pasarela-2026-11-18.md → legal/NSV-2026-001-v8.pdf
- `Alternativas a Conekta para tarjeta y efectivo` --references--> `Orden de Cambio`  [EXTRACTED]
  vault/arquitectura/alternativas-de-pasarela-2026-11-18.md → legal/NSV-2026-001-v8.pdf
- `CLAUDE.md (instrucciones permanentes)` --references--> `Contrato NSV-2026-001 v8`  [EXTRACTED]
  CLAUDE.md → legal/NSV-2026-001-v8.pdf
- `README (nsv-lavanderia)` --references--> `Contrato NSV-2026-001 v8`  [EXTRACTED]
  README.md → legal/NSV-2026-001-v8.pdf

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Equipo de agentes del proyecto** — claude_agents_especificador, claude_agents_implementador_movil, claude_agents_implementador_servidor, claude_agents_redactor_cliente, claude_agents_revisor, claude_agents_verificador_de_aceptacion [EXTRACTED 1.00]
- **Skills del ritmo de sesión y sprint** — claude_skills_arranque_de_sesion_skill, claude_skills_asentar_acta_skill, claude_skills_cierre_de_sesion_skill, claude_skills_cierre_de_sprint_skill [INFERRED 0.85]
- **Pasarelas candidatas para reemplazar Conekta** — vault_arquitectura_alternativas_de_pasarela_2026_11_18_stripe, vault_arquitectura_alternativas_de_pasarela_2026_11_18_mercado_pago, vault_arquitectura_alternativas_de_pasarela_2026_11_18_openpay, vault_arquitectura_alternativas_de_pasarela_2026_11_18_clip [EXTRACTED 1.00]
- **ADRs del stack de la Fase 6** — vault_arquitectura_adr_adr_0002_framework_movil, vault_arquitectura_adr_adr_0003_backend_y_lenguaje, vault_arquitectura_adr_adr_0004_base_de_datos, vault_arquitectura_adr_adr_0007_tiempo_real, vault_arquitectura_adr_adr_0008_avisos_push, vault_arquitectura_adr_adr_0009_mapas, vault_arquitectura_adr_adr_0010_pasarela_de_pago [INFERRED 0.85]
- **Diagramas C4 C1 a C3** — vault_arquitectura_c1_contexto, vault_arquitectura_c2_contenedores, vault_arquitectura_c3_api [EXTRACTED 1.00]
- **Rondas de nombres de marca para el cliente** — vault_cliente_comunicaciones_2026_09_07_lista_de_nombres_para_el_cliente, vault_cliente_comunicaciones_2026_09_08_lista_de_nombres_de_categoria, vault_cliente_comunicaciones_2026_09_09_lista_de_nombres_globales [INFERRED 0.85]
- **Tandas archivadas de material recibido del cliente** — vault_cliente_recibido_2026_09_18_indice, vault_cliente_recibido_2026_09_30_indice, vault_cliente_recibido_2026_11_14_indice [INFERRED 0.85]
- **Ciclo de trabajo con agentes** — vault_formas_de_trabajo_equipo_de_agentes, vault_formas_de_trabajo_ciclo_openspec_tdd, vault_formas_de_trabajo_definicion_de_listo_y_terminado, vault_formas_de_trabajoepositorios_ramas_y_revision, vault_formas_de_trabajo_integracion_continua [INFERRED 0.85]
- **Lectura ejecutiva del contrato** — vault_contratolcance_del_proyecto, vault_contrato_obligaciones_ejecutivas, vault_contratoestricciones_no_negociables, vault_contratorecha_ejecutivo_tecnico, vault_contrato_calendario_contractual [INFERRED 0.85]
- **Identidad de marca Lavandi** — vault_marca_decision_de_marca, vault_marca_decision_visual, vault_marca_paleta_el_tiempo_de_vuelta, vault_marca_territorio_de_marca, vault_marca_mision_vision_valores [INFERRED 0.85]
- **Proceso de selección del nombre** — vault_marca_candidatos_de_nombre, vault_marca_lista_maestra_de_candidatos, vault_marca_decision_de_marca [EXTRACTED 1.00]
- **Rondas de selección del nombre Lavandi** — vault_meta_actas_acta_2026_09_07_fundacion, vault_meta_actas_acta_2026_09_08_nombres_de_categoria, vault_meta_actas_acta_2026_09_09_nombres_globales, vault_meta_actas_acta_2026_09_10_brief_fijado, vault_meta_actas_acta_2026_09_11_lavandi_y_arranque_tecnico, vault_meta_actas_acta_2026_09_12_aprobacion_del_nombre [INFERRED 0.85]
- **Decisión del stack técnico (Fase 6)** — vault_meta_actas_acta_2026_09_25_analisis_funcional, vault_meta_actas_acta_2026_10_09_antes_del_stack, vault_meta_actas_acta_2026_11_12_traspaso_del_proyecto, vault_meta_actas_acta_2026_11_13_cierre_de_la_fase_6 [INFERRED 0.85]
- **Marca visual y manual de identidad** — vault_meta_actas_acta_2026_09_12_marca_visual, vault_meta_actas_acta_2026_09_12_paleta_y_logotipo, vault_meta_actas_acta_2026_09_17_riesgo_aceptado, vault_meta_actas_acta_2026_09_20_feedback_del_manual, vault_meta_actas_acta_2026_09_30_segunda_revision_del_manual [INFERRED 0.85]
- **Actas que cierran el cimiento hacia el arranque del codigo** — vault_meta_actas_acta_2026_11_17_fase_7_formas_de_trabajo, vault_meta_actas_acta_2026_11_18_fase_8_instrumental, vault_meta_actas_acta_2026_11_18_entorno_y_mocks, vault_meta_actas_acta_2026_11_19_normativa_y_arranque [INFERRED 0.85]
- **Notas de negocio que sostienen el modelo economico** — vault_negocio_unit_economics, vault_negocio_metodo_de_precio, vault_negocio_competencia, vault_negocio_hipotesis_criticas, vault_negocio_propuesta_de_valor [INFERRED 0.85]

## Communities (31 total, 10 thin omitted)

### Community 0 - "CI del código (ci.yml)"
Cohesion: 0.07
Nodes (39): outputs, runs-on, steps, jobs, detectar, movil, reglas, tokens (+31 more)

### Community 1 - "CLAUDE.md, agentes y skills"
Cohesion: 0.08
Nodes (35): CLAUDE.md (instrucciones permanentes), Agente especificador, Agente implementador-movil, Agente implementador-servidor, Agente redactor-cliente, Agente revisor, Agente verificador-de-aceptacion, Arthur Naranjo (ejecutor técnico desde 2026-11-12) (+27 more)

### Community 2 - "Contrato y su lectura ejecutiva"
Cohesion: 0.09
Nodes (26): ANEXO A.4 criterios de aceptación, Contrato NSV-2026-001 v8, Alcance del proyecto, Brecha ejecutivo-técnico, Nota de saneamiento, Restricciones no negociables, Calendario contractual, Inventario funcional (+18 more)

### Community 3 - "Variables de base de datos del CI"
Cohesion: 0.08
Nodes (24): run, DB_CONNECTION, DB_DATABASE, DB_HOST, DB_PASSWORD, DB_PORT, DB_USERNAME, POSTGRES_DB (+16 more)

### Community 4 - "ADRs del stack"
Cohesion: 0.16
Nodes (23): ADR-0001 Formato de diagramas (Mermaid), ADR-0002 React Native con Expo, ADR-0003 Laravel y Filament, ADR-0004 PostgreSQL, ADR-0005 Autenticacion, ADR-0006 Almacenamiento S3 privado, ADR-0007 Tiempo real (polling 30 s + aviso), ADR-0008 Firebase Cloud Messaging directo (+15 more)

### Community 5 - "Nombre y marca Lavandi"
Cohesion: 0.11
Nodes (23): Candidatos de nombre y rúbrica, Nombre Lavandi (elegido C-49), Paleta Agua, Cielo, Hueso, Limón, Bajada De tu cel a tu closet, Cuatro valores de marca, Decisión de marca — Lavandi, Paquete bloqueante P-1 (verificación del nombre), Decisión visual de Lavandi (+15 more)

### Community 6 - "Método, encargos y competencia"
Cohesion: 0.10
Nodes (22): Arranque de sesion de la Fase 6 (stack), Arranque de sesion de marca visual, Encargo: kit de prospeccion y validacion (12 piezas), Prompt maestro 01 Fundacion v5 (metodo del proyecto), Fase 5-bis Analisis funcional del alcance (C-93), Prompt para recuperar informe Fase 1 de junio y su listado, Competencia: primer barrido (grado E), GoLaundry Guadalajara: referencia de estructura de precio a domicilio (+14 more)

### Community 7 - "Fases 7 y 8: formas de trabajo"
Cohesion: 0.10
Nodes (21): Acta 2026-11-17 Fase 7 formas de trabajo, ADR-0003 Laravel mantenido frente a Next.js (C-160), Repo aparte para el codigo (C-156, reemplazado por C-169), Sprints de una semana, PR por sprint a Nadir (C-157, C-158), Acta 2026-11-18 Entorno local y mocks, El codigo vive en este repositorio, movil/ y servidor/ (C-169), Cuentas de prueba abiertas por Arthur con su correo (C-170, DD-072), Mocks HTML navegables en mocks/ y diseno en Claude Design (C-167, C-171, C-174, C-175) (+13 more)

### Community 8 - "Verificadores de marca y secretos"
Cohesion: 0.22
Nodes (7): archivos_versionados(), main(), terminos(), archivos_versionados(), main(), claves_del_frontmatter(), main()

### Community 9 - "CI del vault (vault.yml)"
Cohesion: 0.14
Nodes (13): jobs, verificar, name, on, pull_request, push, name, run (+5 more)

### Community 10 - "Recalibración, traspaso y stack"
Cohesion: 0.15
Nodes (13): Marketplace desde cero en dos plazas (C-33), Acta 2026-10-17 Listado de lavanderías, Acta 2026-10-25 Kit de socios, Acta 2026-10-26 Comentarios a la guía, Acta 2026-11-05 Resultados de la recalibración, Ideas del cliente fuera del contrato (requieren Orden de Cambio), Acta 2026-11-12 Traspaso del proyecto a Arthur, Stack elegido: React Native Expo, Laravel Filament, PostgreSQL (+5 more)

### Community 11 - "Actas de septiembre y octubre"
Cohesion: 0.20
Nodes (10): Acta 2026-09-17 Apoyo con contenido, Acta 2026-09-17 Riesgo del parecido aceptado, Acta 2026-09-19 Términos del apoyo y Canva, Acta 2026-09-20 Feedback del manual de identidad, Acta 2026-09-25 Análisis funcional (Fase 5-bis), Acta 2026-09-29 Sesión técnica y plantilla landing, Acta 2026-09-30 Segunda revisión del manual, Acta 2026-10-09 Antes del stack (+2 more)

### Community 12 - "Configuración de OpenSpec"
Cohesion: 0.25
Nodes (7): context, rules, design, proposal, specs, tasks, schema

### Community 13 - "Entornos, correo y tokens"
Cohesion: 0.29
Nodes (6): Cuentas de terceros a nombre del cliente, Resend (correo transaccional), tokens.json (design tokens), ADR-0012 Tres entornos (local, prueba, produccion), ADR-0013 Script propio convierte tokens.json, ADR-0014 Correo automatico con Resend (borrador)

### Community 14 - "Revisiones del manual"
Cohesion: 0.40
Nodes (6): Respuesta a la revision del manual de identidad, Respuesta a la segunda revision del manual, Tanda 2026-09-18, primera revision del manual, Tanda 2026-09-30, segunda revision y logotipo, Tanda 2026-11-14, libreta de precios y video, Recibido de EL CLIENTE (regla de archivo)

### Community 15 - "Proyectos base y Enatega"
Cohesion: 0.50
Nodes (5): Enatega, Cruce Enatega contra inventario, Proyectos base (complemento 2026-10-28), Planteamiento critico de la Fase 6, Referentes de codigo abierto

### Community 16 - "Rondas de nombres"
Cohesion: 0.50
Nodes (4): Aprobacion del nombre de marca (no enviada), Diez nombres para que el cliente elija, Diez nombres de categoria, Diez nombres globales

### Community 17 - "Marca visual y paleta"
Cohesion: 0.50
Nodes (4): Acta 2026-09-12 Alfredo aprueba el nombre Lavandi, Acta 2026-09-12 Marca visual decidida, Acta 2026-09-12 Paleta elegida y lectura del logotipo, Paleta El tiempo de vuelta (Agua, Cielo, Hueso, Limón)

### Community 18 - "Marca y dominio de configuración"
Cohesion: 0.67
Nodes (3): Marca y dominio siempre de configuración ({{MARCA}}/{{DOMINIO}}), Prohibiciones permanentes (marca, dominio, stack, datos), terminos-de-marca.txt (términos prohibidos en código)

### Community 19 - "Reunión de recalibración"
Cohesion: 0.67
Nodes (3): Guia de reunion, decisiones del negocio, Minuta de la reunion de recalibracion, Reporte del formulario de prioridades

### Community 20 - "Fundación y Nazmelia"
Cohesion: 0.67
Nodes (3): Acta 2026-09-07 Sesión de fundación, Marca Nazmelia (del PRESTADOR, no se cede), Acta 2026-09-08 Nombres de categoría

## Knowledge Gaps
- **88 isolated node(s):** `Prohibiciones permanentes (marca, dominio, stack, datos)`, `Vault Obsidian y espejo a Notion`, `Fases 0 a 8 del método (incluye 5-bis)`, `Arthur Naranjo (ejecutor técnico desde 2026-11-12)`, `nsv-lavanderia, nombre clave provisional (no es la marca)` (+83 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 156 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **10 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `servidor` connect `Variables de base de datos del CI` to `CI del código (ci.yml)`?**
  _High betweenness centrality (0.018) - this node is a cross-community bridge._
- **What connects `Prohibiciones permanentes (marca, dominio, stack, datos)`, `Vault Obsidian y espejo a Notion`, `Fases 0 a 8 del método (incluye 5-bis)` to the rest of the system?**
  _88 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `CI del código (ci.yml)` be split into smaller, more focused modules?**
  _Cohesion score 0.06666666666666667 - nodes in this community are weakly interconnected._
- **Why does `jobs` connect `CI del código (ci.yml)` to `Variables de base de datos del CI`?**
  _High betweenness centrality (0.014) - this node is a cross-community bridge._
- **Should `CLAUDE.md, agentes y skills` be split into smaller, more focused modules?**
  _Cohesion score 0.07823613086770982 - nodes in this community are weakly interconnected._
- **Should `Contrato y su lectura ejecutiva` be split into smaller, more focused modules?**
  _Cohesion score 0.08620689655172414 - nodes in this community are weakly interconnected._
- **Should `Variables de base de datos del CI` be split into smaller, more focused modules?**
  _Cohesion score 0.08333333333333333 - nodes in this community are weakly interconnected._