---
estado: vigente
grado: B
fuente: auditoría de solo lectura de arthur/feat/h3-concilio en 9d2497775966ef2d8c0c0f204b2f67cfb8432fbd (PR #4) contra vault/contrato/obligaciones-ejecutivas.md (O-07 a O-11, O-21, O-22), vault/contrato/inventario-funcional.md (F-L01 a F-L12, F-I01 a F-I11, F-D01 a F-D07) y vault/contrato/calendario-contractual.md; vistas, configuración, medición, pruebas de navegador y panel leídos directamente; sin correr pruebas, por decisión de Nadir Hadad Navarrete en sesión del 2026-11-24
fecha: 2026-11-25
dueño: Nadir Hadad Navarrete
---
# Auditoría Lavandi — Segmento 7: landing y panel

**Objeto:** rama `arthur/feat/h3-concilio` (PR #4) · SHA `9d24977` · fecha de corte 2026-11-24.

**Qué NO se revisó:**
- **No se corrieron pruebas** (ni Playwright ni Lighthouse). La portada no se abrió en ningún navegador.
  - Que las 12 secciones "se rendericen correctamente" en Chrome, Safari y Firefox: NO VERIFICADO. Solo se sabe que hay
    pruebas que lo declaran en los tres motores.
  - La calificación de PageSpeed: NO VERIFICADO.
- **No hay sitio publicado** que revisar: no existe `lavandi.mx` con la portada, ni el candado, ni las cuentas de
  medición de EL CLIENTE.
- **Las 12 plantillas de redes sociales (O-04)** caen en la misma ventana del calendario (semanas 10 a 12), pero son de
  branding (A.1), no de la landing. Quedan fuera.
- El detalle de cada indicador y de cada función del Módulo D ya está en la matriz del segmento 1 (F-I y F-D). La
  seguridad del panel, como el 2FA, está en el segmento 4. Aquí solo se citan.

**Veredicto: NO ESTÁ LISTO.**
- **La landing está bien armada:**
  - tiene las 12 secciones en el orden del contrato;
  - cada hueco de contenido está marcado como "Contenido de prueba";
  - tiene pruebas de navegador en los tres motores, Lighthouse en teléfono en la CI y medición por GTM diferida.
- **Pero a cinco días de que cierre su ventana (semana 12, hasta el 29 de noviembre), no se puede acreditar ninguno de
  los cinco criterios de A.4.2.** Todos esperan insumos o cuentas de EL CLIENTE.
- **El panel de indicadores tiene 10 de los 11 indicadores**, con refresco cada 30 s y exportación. Le faltan 2
  indicadores y tiene otros 4 a medias (ya en el segmento 1). Además vive en la web, cuando C-247 pasó el Módulo D a la
  app (S1-04).

### Resumen
- **Las 12 secciones están en el código y en orden** (`portada/index.blade.php:17-29` y el pie). Una prueba en
  Chromium, Firefox y WebKit las declara presentes.
- **Ningún criterio de A.4.2 se acredita todavía** (S7-01). Contenido de prueba en todas las secciones, formularios sin
  destino, sin hosting, sin cuentas de medición y sin Search Console. La landing es parte del hito del pago 2.
- **Diez textos de la portada están fijos en el código** (S7-02). Son los de "¿Cómo funciona?" y los seis pilares,
  escritos como `null` en `config/landing.php`. EL CLIENTE no puede cargarlos sin un desarrollador, y el contrato deja
  la carga de contenido fuera del soporte.
- **El aviso de derechos de autor desaparece en producción sin avisar** (S7-03). Si falta su configuración, no se ve.
- **Indicadores:** refresco cada 30 s (`config/indicadores.php:24`) y exportación CSV o Excel de 10 indicadores, con
  cualquier rango de fechas. Siguen pendientes F-I06 y F-I10, y F-I02, F-I03, F-I07 y F-I09 (segmento 1).

### Hallazgos
| ID | Severidad | Área | Hallazgo | Evidencia (grado + ruta:línea) | Impacto | Recomendación |
|---|---|---|---|---|---|---|
| S7-01 | Alta | Calendario / A.4.2 | **Ninguno de los cinco criterios de A.4.2 se puede acreditar, y la ventana de la landing cierra el 29 de noviembre.** (1) Las 12 secciones están, pero todas salvo el logo muestran "Contenido de prueba", y la tarea 7.1 de "Chrome, Safari y Firefox reales" está bloqueada. (2) Los eventos de GA4 y Pixel en las cuentas de EL CLIENTE, tarea 1.3, bloqueada. (3) Los dos formularios no tienen destino (S1-10). (4) PageSpeed ≥ 70 y (5) el candado se miden sobre el sitio publicado, que no existe (tarea 3.3) | A: `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.2`; `calendario-contractual.md:23,51`. B: `servidor/resources/views/components/portada/de-prueba.blade.php:7`; `openspec/changes/landing/tasks.md:42,51`; `openspec/changes/medicion-de-la-landing/tasks.md:12,17,24`. C: DD-117, DD-118, DD-119, DD-120 y DD-070 | Sin landing en dominio activo no se cumple el hito del pago 2 (`calendario-contractual.md:51`), aunque la app llegue a las tiendas | Nadir: requerimiento escrito a EL CLIENTE por los insumos, con la fecha del 29 de noviembre y la consecuencia sobre el pago 2 (Cláusula III.B). Si no llegan, notificar el retraso imputable a EL CLIENTE |
| S7-02 | Media | Contenido | **Diez textos de la portada están fijos como `null` en el código**, no en el entorno ni en el panel: los cuatro pasos y los seis pilares. El comentario del archivo dice que el contenido entra "sin programar (A.7)", pero A.7 dice otra cosa: deja la carga de contenido fuera del soporte. Solo los testimonios y la prensa se editan en el panel | B: `servidor/config/landing.php:11,45-48,56-58,97-99`; `servidor/.env.example:157-188` (no hay variables para esos diez). A: `NSV-2026-001-v8.pdf:24:ANEXO A, Sección A.7` (`obligaciones-ejecutivas.md:72`) | Cada texto que llegue, o que cambie después de la entrega, exige editar código y desplegar. Después del lanzamiento, eso es soporte de EL PRESTADOR o un cobro aparte | Pasar esos diez textos a variables de entorno, como el resto, o al panel. Corregir la cita a A.7 del comentario |
| S7-03 | Baja | Sección 12 | **El aviso de derechos de autor se omite en silencio en producción.** Sin titular o año en la configuración, fuera de producción se ve un marcador de prueba, pero en producción no se ve nada. Las demás secciones sí muestran su marcador en producción | B: `servidor/resources/views/publico/pie-legal.blade.php:30-34` frente a `servidor/resources/views/components/portada/de-prueba.blade.php:7`. C: DD-120 | La sección 12 puede salir incompleta sin que nadie lo vea, y el contrato pide el aviso de derechos de autor (F-L12) | El mismo trato que las demás secciones, o que el despliegue a producción falle sin `LANDING_DERECHOS_*` |
| S7-04 | Baja | Velocidad | **El Lighthouse de la CI no anticipa el PageSpeed real.** Mide `php artisan serve` (sin compresión ni caché del hosting) sobre la portada con contenido de prueba, sin las imágenes de EL CLIENTE. La CI lo dice: es una "alerta temprana" | B: `servidor/lighthouserc.json:4-5,10,16`; `.github/workflows/ci.yml:106-111` | Las imágenes reales del encabezado y de la descarga pueden bajar la calificación de 70 | Medir otra vez con las imágenes reales en cuanto lleguen, antes de publicar |
| S7-05 | Info | Medición | **GA4 y Meta Pixel cargarían sin consentimiento.** GTM se pide en diferido, sin aviso de cookies. Si hace falta, y cómo, es DD-118. Sin el identificador de GTM no se carga nada | B: `servidor/resources/views/publico/medicion.blade.php:21-45`; `servidor/config/medicion.php` (`'gtm' => env('MEDICION_GTM')`). C: DD-118 | Depende de lo que diga el aviso de privacidad de EL CLIENTE (B.7) | Resolver DD-118 junto con el aviso de privacidad, antes de poner el ID de GTM de producción |
| S7-06 | Info | Positivos | **La landing y el panel están bien construidos.** Las 12 secciones, en orden y marcadas con `data-seccion`. Marcadores de prueba honestos, sin textos, precios ni cifras inventados. Playwright en Chromium, Firefox y WebKit. Lighthouse en perfil de teléfono, con mínimo 0.7. GTM diferido y por entorno. Mapa del sitio y robots. Información estructurada. Indicadores con refresco de 30 s, por debajo de los 60 del contrato, y exportación de 10 indicadores | B: `servidor/resources/views/portada/index.blade.php:13,17-29`; `servidor/playwright.config.js:34-37`; `servidor/lighthouserc.json:16`; `servidor/routes/web.php:73-74`; `servidor/config/indicadores.php:24`; `servidor/app/Filament/Pages/Indicadores.php:116-125,139-151` | — | Conservar |

**Ya reportados, que tocan este segmento:**
- **S1-04:** C-247 pasa el Módulo D, con sus 7 funciones y 11 indicadores, a la app, pero hoy todo vive en Filament web.
- **S1-06:** "Usuarios activos" lee una tabla que nadie escribe.
- **S1-10:** los formularios no llegan a ningún lado y el contenido es de prueba.
- **S1-13:** la información estructurada usa `Service` en lugar de negocio local.
- **S4:** el panel no tiene 2FA.
- **S5-07:** la medición espera el ID de GTM.

### Las 12 secciones del contrato contra el código
Fuente del contrato: `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2` (F-L01 a F-L12). Ruta base:
`servidor/resources/views/`.

| # | Sección (contrato) | Dónde está | Elementos del contrato en el código | Contenido de EL CLIENTE | Estado |
|---|---|---|---|---|---|
| 1 | Hero / Encabezado | `portada/secciones/encabezado.blade.php` | Logo `:17`, tagline `:21-25`, botón de descarga `:27-30`, imagen del mockup `:33-38` | Tagline e imagen: de prueba (DD-117) | CODIFICADO |
| 2 | ¿Cómo funciona? | `portada/secciones/como-funciona.blade.php` | Pide → Recogemos → Lavamos → Entregamos `:8-13` | Texto de cada paso fijo en `null` (S7-02) | CODIFICADO |
| 3 | Beneficios para usuarios | `portada/secciones/beneficios-usuarios.blade.php` | Comodidad, precio, confianza `:10-14`; iconos animados `:23` | Fijo en `null` (S7-02) | CODIFICADO |
| 4 | Descarga la app | `portada/secciones/descarga.blade.php` | Botones de las dos tiendas `:19-37`; mockup `:41-46` | Enlaces vacíos hasta que la app se publique | CODIFICADO |
| 5 | Lavanderías partner | `portada/secciones/lavanderias.blade.php` | Invitación `:19`; formulario de interés `:22` | Sin destino (S1-10, DD-119) | CODIFICADO, parcial |
| 6 | Beneficios para socios | `portada/secciones/beneficios-socios.blade.php` | Tres pilares `:21` | Fijo en `null` (S7-02). "Cero gestión de cobranza" depende de DD-012 | CODIFICADO |
| 7 | Cobertura | `portada/secciones/cobertura.blade.php` | Mapa de las zonas activas `:15,32` | Sin las zonas reales del territorio (DD-093, DD-094) | CODIFICADO, parcial |
| 8 | Testimonios | `portada/secciones/testimonios.blade.php` | Tarjetas desde el panel `:9,17`; marcador de posición `:30` | El contrato acepta un marcador en el lanzamiento | CODIFICADO |
| 9 | Preguntas frecuentes | `portada/secciones/preguntas-frecuentes.blade.php` | Acordeón `<details>` `:19-20` | Las 6 a 8 preguntas: de prueba `:16` | CODIFICADO |
| 10 | Prensa / Reconocimientos | `portada/secciones/prensa.blade.php` | Logos desde el panel `:21`, activables | Sin logos `:30` | CODIFICADO |
| 11 | Contacto & Soporte | `portada/secciones/contacto.blade.php` | Correo `:27`, WhatsApp `:31-37`, formulario `:42` | Correo y número de prueba; formulario sin destino (S1-10) | CODIFICADO, parcial |
| 12 | Footer legal | `publico/pie-legal.blade.php` | Aviso de privacidad y términos `:22-26`; derechos de autor `:30-34` | Textos legales de EL CLIENTE (B.7); titular por DD-120; en producción se omite (S7-03) | CODIFICADO |

Ninguna sección está PROBADA. Hay pruebas escritas: `servidor/tests/Browser/Landing/DoceSecciones.spec.js` y sus
hermanas, y `servidor/tests/Feature/Landing/`. No se corrieron.

### Criterios de aceptación de la landing (A.4.2)
| Criterio | Qué hay | Estado | Bloquea |
|---|---|---|---|
| Las 12 secciones correctamente renderizadas en Chrome, Safari y Firefox | Playwright en los tres motores, con contenido de prueba | NO VERIFICADO · no acreditable | DD-117 (contenido), DD-070 (sitio) |
| GA4 y Meta Pixel registran eventos en su panel | Eventos `gtm.js`, `descarga` y `formulario_enviado` en la capa de datos | No acreditable | DD-118 (cuentas de EL CLIENTE), ID de GTM |
| Formularios funcionales | Validan, pero no entregan a ningún destino | No cumple | DD-119 |
| PageSpeed ≥ 70 en teléfono | Lighthouse ≥ 0.7 en la CI, como alerta temprana | NO VERIFICADO · no acreditable | DD-070, S7-04 |
| Sitio en la dirección acordada, con candado | Middleware `SoloHttps`; sin hosting | No acreditable | DD-070 |

### Medición y buscadores (O-08, O-09)
| Herramienta | En el código | Falta |
|---|---|---|
| Google Tag Manager | Cargador diferido, identificador por entorno (`publico/medicion.blade.php:21-45`) | ID del contenedor de EL CLIENTE (DD-118) |
| GA4 y Meta Pixel | Entran por GTM. Sus etiquetas se configuran en la cuenta, no en el código (`config/medicion.php`) | Cuentas de EL CLIENTE y su configuración (tarea 1.3) |
| Search Console | Verificación por DNS, planeada | Tarea 2.2, bloqueada por DD-023 y DD-118 |
| Títulos y descripciones | `publico/layout.blade.php:24` | Descripción de la portada: `LANDING_DESCRIPCION_PORTADA` vacía |
| Mapa del sitio y robots | `routes/web.php:73-74` | — |
| Datos estructurados | `portada/index.blade.php:13` | Tipo de negocio local (S1-13) |

### Panel de indicadores (O-21, O-22)
| Indicador (A.5) | Widget | Exporta | Estado (del segmento 1) |
|---|---|---|---|
| F-I01 Pedidos totales | `PedidosTotalesGrafica`, `PedidosTotalesTabla` | sí | C |
| F-I02 Pedidos por estado | `PedidosPorEstadoGrafica` | sí | P: sin cancelados (DD-016) |
| F-I03 Usuarios activos | `UsuariosActivosGrafica` | sí | P: la tabla que lee nadie la escribe (S1-06) |
| F-I04 Vuelven a pedir por mes de registro | `RetencionTabla` | sí | C |
| F-I05 Monto promedio | `MontoPromedioIndicador` | sí | C: estimado o final (DD-012) |
| F-I06 Lavanderías activas frente a registradas | — | no | **NO INICIADO** (DD-083) |
| F-I07 Calificación por lavandería con semáforo | `RankingDeLavanderiasTabla` | sí | P: semáforo sin cortes (DD-114) |
| F-I08 Entregas por repartidor | `EntregasPorRepartidorTabla` | sí | C |
| F-I09 Ingresos brutos | `IngresosBrutosGrafica` | sí | P: una serie, no barras agrupadas (DD-115) |
| F-I10 Comisiones por socio y total | — | no | **NO INICIADO** (DD-020) |
| F-I11 Mapa de calor por colonia o zona | `MapaDeCalor` | sí, por colonia y por zona | C: puntos y polígonos, no capa de calor |

- **Fuentes:**
  - los widgets están en `servidor/app/Filament/Pages/Indicadores.php:139-151`;
  - las exportaciones, en `:116-125`, con la exportación de Filament y su archivo CSV propio
    (`servidor/app/Exportacion/EscribirArchivoDelReporte.php:26`);
  - el refresco es de 30 s, configurable (`servidor/config/indicadores.php:24`, `servidor/app/Indicadores/Refresco.php`).
- **Alcance de la exportación:** la página de indicadores exporta 10. La conciliación y los ingresos exportan desde sus
  propias páginas (`servidor/app/Filament/Pages/Conciliacion.php:97`, `ReporteDeIngresos.php:92`).
- **La exportación usa la cola** (`QUEUE_CONNECTION=database`, `servidor/.env.example:52`). En producción necesita un
  proceso de colas, que espera DD-070.

### Panel de administración (Módulo D)
El estado de cada función, de F-D01 a F-D07, está en la matriz del segmento 1. Este segmento no encontró nada nuevo, pero
recuerda lo que ya está abierto:
- **Dónde vive (S1-04):** C-247 lo pasa a la app y hoy está en Filament web.
- **Funciones a medias:**
  - el alta de lavanderías (DD-053);
  - el filtro de usuarios activos (DD-111);
  - la comisión del repartidor y los reportes de comisiones (DD-019 y DD-020);
  - el envío segmentado de avisos (DD-113).
- **Seguridad (S4):** el panel no tiene 2FA.

### Preguntas abiertas
**Para Arthur:**
- ¿Los diez textos fijos en `config/landing.php` pasan a entorno o al panel antes de recibir el contenido?
- ¿El Lighthouse se puede correr contra un despliegue de prueba que sirva como producción (compresión y caché), en lugar
  de `php artisan serve`?
- ¿Cuándo entran F-I06 y F-I10? El calendario del panel es de las semanas 12 y 13.

**Para Nadir:**
- ¿Ya se pidieron por escrito a EL CLIENTE los insumos de DD-117, DD-118, DD-119 y DD-120, con la fecha del 29 de
  noviembre? Sin ellos, la landing no cierra en su semana y el pago 2 se mueve.
- DD-070: ¿qué hosting? Sin él no hay sitio publicado, candado ni PageSpeed real.

**Para EL CLIENTE, vía Nadir:**
- Los textos e imágenes (DD-117).
- Las cuentas de medición y el consentimiento (DD-118).
- El destino de los formularios (DD-119).
- El titular de los derechos (DD-120).
- Las zonas reales de cobertura (DD-093 y DD-094).

### Comandos ejecutados
```bash
# En el worktree de solo lectura del SHA 9d24977
find servidor/resources/views -type f | sort
cat -n servidor/resources/views/portada/index.blade.php
grep -n "data-seccion\|de-prueba\|<form\|<details\|@foreach" servidor/resources/views/portada/secciones/*.blade.php
cat -n servidor/resources/views/components/portada/de-prueba.blade.php servidor/resources/views/publico/pie-legal.blade.php
grep -n "=> null" servidor/config/landing.php; grep -n "LANDING_" servidor/.env.example
cat -n servidor/resources/views/publico/medicion.blade.php; cat servidor/config/medicion.php
grep -n "sitemap\|robots" servidor/routes/web.php
cat servidor/lighthouserc.json; grep -n "projects\|chromium\|firefox\|webkit" servidor/playwright.config.js
grep -n "\[ \]" openspec/changes/landing/tasks.md openspec/changes/medicion-de-la-landing/tasks.md
grep -n "DescargarReporte::de\|::class" servidor/app/Filament/Pages/Indicadores.php
grep -n "refresco" servidor/config/indicadores.php
```
