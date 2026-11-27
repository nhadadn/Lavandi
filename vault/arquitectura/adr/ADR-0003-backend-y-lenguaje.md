---
estado: vigente
grado: D
fuente: acta-2026-11-12-traspaso-del-proyecto.md C-145 y C-147; matriz-de-criterios-stack.md (pesos C-142); verificaciones-fase-6.md, sección del 2026-11-12 (E); nota del 2026-11-24: acta-2026-11-20-modelo-y-pasarela.md, decisión 56, del concilio del 2026-11-24, por delegación de Arthur (C-245) (C); nota del 2026-11-24 que remite a ADR-0022: acta-2026-11-20-modelo-y-pasarela.md, C-247 y decisión 69 (C)
fecha: 2026-11-12
dueño: Arthur Naranjo
adr: ADR-0003
supersede:
---
# ADR-0003 — El backend y el panel web se hacen con Laravel y Filament

## Contexto

- **El contenedor `api` (C2) concentra toda la lógica:**
  - las reglas del pedido;
  - la autorización por rol, tenencia y plaza;
  - el cobro con Conekta y sus webhooks;
  - los avisos;
  - los indicadores.
- **El contenedor `panel-admin`** es una aplicación web con el Módulo D completo (C-140). (Nota del 2026-11-24: desde
  C-247, el Módulo D completo vive en la app y `panel-admin` queda como respaldo interno; [[ADR-0022-administracion-en-la-app]].)
  - El contrato le exige exportar a CSV o Excel y mostrar 11 indicadores en tiempo real (A: O-16, O-21, A.4.4).
- **Restricciones del contrato:**
  - costo de 1,200 a 4,100 MXN al mes (A: RN-22);
  - cambio de estado visible en 60 s o menos, "sin sobreingeniería" (A: RN-05);
  - el código se cede al cliente y corre en cuentas a su nombre (A: Cláusula IX).
- **El equipo:** Arthur domina Laravel (C-135) y trabaja 14 h por semana (C-134).
- **La app:** se hace con React Native y Expo (`ADR-0002`).

## Criterios y pesos

Los de la matriz `vigente` (C-142), con la misma escala que el `ADR-0002`: de 1 a 5, total sobre 100. Puntajes
propuestos por Claude con datos grado E del 2026-11-12. Los costos son cálculos de Claude con el FIX de Banxico del
2026-11-12 (18.6188 MXN por USD), no cotizaciones.

## Alternativas evaluadas

Los cuatro candidatos caben en el costo del contrato.

| Candidato | Costo de arranque | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|---|
| **Laravel con Filament** | 750 a 1,180 MXN al mes, según el hosting | 5 | 5 | 5 | 4 | 4 | 5 | **96** |
| Supabase | unos 465 MXN al mes, más el panel | 3 | 3 | 3 | 5 | 4 | 5 | **70** |
| NestJS con PostgreSQL | unos 863 MXN al mes | 2 | 2 | 5 | 4 | 4 | 5 | **63** |
| Firebase | 0 a 93 MXN al mes, sin tope de gasto | 1 | 2 | 1 | 5 | 3 | 2 | **39** |

**Por qué cada puntaje:**

- **Familiaridad.** Laravel es el terreno de Arthur. Supabase pide RLS y funciones en Deno, NestJS es un framework
  que no conoce, y Firebase es NoSQL.
- **Velocidad.**
  - **Laravel 5.** Cada pieza ya existe:
    - Filament 5.11.2 (MIT) da el panel, con exportación a CSV y XLSX de fábrica.
    - Hay SDK oficial de Conekta para PHP (`conekta/conekta-php` 9.0.0).
    - Hay canal FCM para Laravel (`laravel-notification-channels/fcm` 6.2.0).
    - Sanctum, Socialite y `socialiteproviders/apple` cubren el inicio de sesión.
  - **Supabase 3.** El panel completo hay que construirlo.
  - **NestJS 2 y Firebase 2.** Casi todo hay que construirlo.
- **Proveedor y ruta de salida.** Laravel y NestJS son código abierto sobre una base de datos estándar. Supabase
  amarra el inicio de sesión, el RLS y el almacenamiento a su cliente. Firebase es la salida más cara.
- **Tiempo real.**
  - Supabase y Firebase lo traen incluido.
  - En Laravel cuesta $0: consulta periódica más un aviso, o Laravel Reverb (MIT) en el propio servidor. Basta para
    60 s.
- **Costo.** Firebase no tiene tope de gasto.
- **Multi-plaza.** Las tres opciones relacionales lo cumplen por diseño. Firestore lo complica.

## Decisión

**El backend (`api`) y el panel de administración (`panel-admin`) se hacen con Laravel, en PHP.**

- La versión estable al 2026-11-10 es la 13.42.0 (E).
- La app usa la API con tokens de Sanctum.
- El panel web del Módulo D se hace con **Filament**.
- La vista reducida de administración en la app (C-140) usa la misma API. (Nota del 2026-11-24: la decisión 56 de
  `acta-2026-11-20-modelo-y-pasarela.md`, C-245, lo acota: la vista no tiene sesión con la API y solo abre el panel
  web dentro de la app, administracion-E25.)
- **Nota del 2026-11-24, después de la anterior:** C-247 (decisión 69 del mismo acta) reemplaza la vista reducida y la
  decisión 56. La administración inicia sesión en la app y ahí hace todo el Módulo D y el A.5, con la API de
  `/api/administracion`; el panel de Filament se queda como respaldo interno, sin enlace, y no recibe funciones nuevas.
  Cómo se construye lo decide [[ADR-0022-administracion-en-la-app]]. Laravel, Sanctum y Filament no cambian.

**Dónde corre se decide después** (C-147, DD-070). Las opciones son Laravel Cloud o Forge con DigitalOcean. Las dos
caben en el costo del contrato.

La decidió Arthur Naranjo el 2026-11-12 (`acta-2026-11-12-traspaso-del-proyecto.md`, C-145), con la opción que
recomendó Claude.

## Consecuencias

- **Positivas:**
  - El panel, con exportaciones y gráficas, sale casi hecho.
  - Hay SDK oficial de Conekta.
  - Lo más grande del sistema queda en el terreno que Arthur domina.
  - Nada amarra a un proveedor.
- **Negativas, y qué se hace con ellas:**
  - **Los mapas en Filament no son nativos.** Hay plugins de terceros: `cheesegrits/filament-google-maps` (en beta)
    y Pinpoint. Se decide en el ADR-0009.
  - **Reverb no tiene cliente oficial para React Native.** Por eso la opción base para los 60 s es consultar cada
    30 s más un aviso FCM. Se decide en el ADR-0007.
  - **Cómo se firman los webhooks de Conekta no está confirmado.** Hay que validarlo en el sandbox y escribir
    manejadores idempotentes. Se decide en el ADR-0010.
  - **La exportación de Filament usa colas con lotes de trabajos.** La protección contra fórmulas inyectadas en el
    CSV hay que activarla a mano.
- **Condiciona:**
  - ADR-0004: la base de datos.
  - ADR-0005: Sanctum, Socialite y Apple.
  - ADR-0006: almacenamiento S3 compatible.
  - ADR-0007: tiempo real.
  - ADR-0008: avisos.
  - ADR-0010: Conekta.
  - ADR-0011: la landing puede vivir en el mismo Laravel o aparte.
  - ADR-0012: entornos y hosting (DD-070).
  - ADR-0014: correo.

## Criterio de reversión

Se reabre si, al cerrar la segunda semana de desarrollo, el panel de Filament no logra la exportación a CSV o Excel
**o** el mapa del monitoreo de pedidos sin código a la medida que pase de 8 h. También se reabre si el costo real del
hosting elegido supera 4,100 MXN al mes.

## Costo estimado de revertir

Es alto después de las primeras semanas, porque se reescribiría toda la API y el panel. Las specs y la base de datos
se conservan.

## Trazabilidad

- **Obligaciones:** O-12 a O-18, O-20, O-21, O-22 y O-26.
- **Restricciones:** RN-05, RN-22 y RN-28.
- **Contenedores:** `api` y `panel-admin`.
- **ADRs relacionados:** ADR-0015, ADR-0002, ADR-0004 a ADR-0012 y ADR-0014.
