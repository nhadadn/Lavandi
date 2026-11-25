---
estado: vigente
grado: D
fuente: acta-2026-11-13-cierre-de-la-fase-6.md C-152; brecha-ejecutivo-tecnico.md BR-06, BR-08 y BR-38 (B); calendario-contractual.md, trámites B.10 (A); verificaciones-fase-6.md, sección del 2026-11-12 (E); ADR-0002, ADR-0003, ADR-0008 y ADR-0010 (D); DD-017, DD-069, DD-070 y DD-134 (B); adenda del 2026-11-24: acta-2026-11-20-modelo-y-pasarela.md, decisión 65, del concilio del 2026-11-24, por delegación de C-236 y C-245 (C); NSV-2026-001-v8.pdf:14:ANEXO A, Sección A.2 (A); servidor/lighthouserc.json (B)
fecha: 2026-11-13
dueño: Arthur Naranjo
adr: ADR-0012
supersede:
---
# ADR-0012 — Tres entornos (local, prueba y producción) y cuentas a nombre del cliente

## Contexto

- **El pago 2 tiene un hito alterno (A, BR-06):** si la publicación se atrasa por causas ajenas al prestador, basta
  "la aplicación compilada, desplegada en **ambiente de prueba** y validada conforme A.4.3". Hace falta un ambiente
  de prueba real, con su forma de distribuir la app.
- **Las cuentas (A, B.11; B, BR-38):** los costos de terceros van a nombre del cliente, con accesos de colaborador
  revocables. El código se cede al pago íntegro (BR-08).
- **Las cuentas del cliente no existen:** Apple, Google Play y Conekta (B.10) no han arrancado. El cliente escribió
  "Aún no se hace nada" (2026-11-05).
- **Lo que ya decidieron los otros ADRs:**
  - la app se compila en la nube con EAS (`ADR-0002`);
  - los avisos usan Firebase (`ADR-0008`);
  - los pagos usan Conekta, que tiene sandbox (`ADR-0010`);
  - el hosting del backend sigue abierto (DD-070).

## Criterios y pesos

Los de la matriz `vigente` (C-142).

## Alternativas evaluadas

| Alternativa | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **A. Tres entornos: local, prueba y producción** | 5 | 4 | 5 | 5 | 4 | 5 | **93** |
| B. Dos entornos: local y producción | 5 | 5 | 5 | 5 | 5 | 5 | descartada |
| C. Un entorno de vista previa por rama, más prueba y producción | 3 | 3 | 4 | 5 | 2 | 5 | **65** |

**B se descarta:** no hay "ambiente de prueba" para el hito alterno del pago 2 (BR-06), y cada prueba con pagos o
avisos se haría en producción.

**C** cuesta más y sobra para una sola persona.

## Decisión

| Entorno | Backend y panel | App | Pagos | Avisos y mapas |
|---|---|---|---|---|
| **Local** | En la máquina de Arthur, con PostgreSQL y PostGIS | Development build de EAS | Sandbox de Conekta | Proyecto de Firebase de prueba; llaves de Google de prueba |
| **Prueba** | En el hosting de DD-070, con datos de prueba | Perfil `preview` de EAS: APK interno en Android y TestFlight en iPhone | Sandbox de Conekta | Proyecto de Firebase de prueba |
| **Producción** | En el hosting de DD-070 | Perfil `production` de EAS, publicado en las dos tiendas | Producción de Conekta | Proyecto de Firebase de producción |

> **Mientras DD-070 siga abierta, solo existe el entorno local (C-154).** El hosting se decide con Nadir. Hasta
> entonces:
> - el backend corre en la máquina de Arthur;
> - la app de desarrollo se conecta a él por la red local o por un túnel;
> - para recibir webhooks de Conekta en local también hace falta un túnel.
>
> El entorno de prueba, que es el que acredita el hito alterno del pago 2, nace cuando se decida el hosting.

**El "ambiente de prueba" del BR-06 es el entorno de prueba.** La validación conforme al A.4.3 se documenta ahí, con
evidencia.

**Las cuentas:**

- **Producción va siempre en cuentas a nombre del cliente,** con Arthur como colaborador revocable (BR-38):
  - hosting, Firebase y Google Cloud (llaves de mapas);
  - Conekta, Apple, Google Play, la organización de Expo (EAS) y el correo.
- **Local y prueba pueden arrancar en cuentas de EL PRESTADOR,** en niveles gratuitos, mientras el cliente no tenga
  las suyas. Antes de producción, todo se pasa a las del cliente.
  - **La cuenta de Apple para probar en iPhone es DD-069.**

**Los secretos** (llaves, tokens, credenciales de Conekta y Firebase) viven en las variables de entorno del hosting y
en los secretos de EAS. Nunca en el repositorio: el `.gitignore` ya excluye los `.env`.

La aprobó Arthur Naranjo el 2026-11-13 (`acta-2026-11-13-cierre-de-la-fase-6.md`, C-152), con la opción que recomendó Claude.

## Consecuencias

- **Positivas:**
  - El hito alterno del pago 2 tiene dónde acreditarse.
  - Pagos y avisos se prueban sin tocar producción.
  - La cesión queda limpia, porque producción nace a nombre del cliente.
- **Negativas:**
  - Mientras el cliente no abra sus cuentas, prueba vive en cuentas de EL PRESTADOR, y hay una migración antes de
    producción.
  - El entorno de prueba cuesta lo que cueste en el hosting de DD-070.
- **Condiciona:**
  - La Fase 7: ramas, integración continua y despliegues a prueba y producción.
  - El mapa de cuentas (BR-38).
  - DD-017: cuentas de las tiendas a nombre de una persona o de una sociedad.

## Criterio de reversión

Se reabre si el costo del entorno de prueba, sumado al de producción, se sale del rango del B.4. En ese caso, prueba
pasaría a encenderse solo cuando se use.

## Costo estimado de revertir

Bajo: es configuración, no código.

## Trazabilidad

- **Hitos:** pago 2 y su hito alterno (BR-06).
- **Restricciones:** BR-08, BR-38 y los trámites B.10.
- **Pendientes:** DD-017, DD-069 y DD-070.
- **ADRs relacionados:** ADR-0002, ADR-0003, ADR-0006, ADR-0008, ADR-0009 y ADR-0010.

## Adenda del 2026-11-24: solo producción se deja indexar

**Contexto.**

- El A.2 quiere el sitio "optimizado para conversión (descarga de la app) y posicionamiento orgánico en buscadores"
  (`NSV-2026-001-v8.pdf:14:ANEXO A, Sección A.2`). Lo que se posiciona es el sitio publicado, el de producción.
- La tabla de entornos de este ADR (líneas 46 a 50) tiene tres: local, prueba y producción. El de prueba estará en
  internet cuando exista (DD-070), y un buscador que lo indexe lo pone a competir con producción.
- El archivo de robots de la landing lo sirve una ruta del servidor, no un archivo fijo (medicion-E08,
  `openspec/changes/medicion-de-la-landing/design.md`), así que puede responder distinto en cada entorno.

**Decisión.**

- **En todo entorno cuyo `APP_ENV` no sea `production`, el archivo de robots responde `User-agent: *` y
  `Disallow: /`.** Ningún buscador que respete el archivo de robots rastrea local, prueba ni otro entorno que llegue.
- **Producción conserva el archivo de medicion-E08:** no le cierra el sitio a ningún buscador y declara la dirección
  del mapa del sitio.
- **Por qué "solo producción indexa" y no "prueba no indexa".** La tabla tiene tres entornos y el nombre del de prueba
  todavía no existe (DD-070): la regla por `APP_ENV` cubre a todos sin nombrar ninguno.

**Consecuencias.**

- La regla se prueba sin el entorno real: `servidor/tests/Feature/Medicion/BuscadoresTest.php` cambia el entorno de la
  aplicación (medicion-E15).
- La comprobación en el entorno de prueba en internet espera a que exista (DD-070).
- El entorno de prueba no lleva `APP_ENV=production`: con ese valor quedaría abierto a los buscadores sin que falle
  ninguna prueba. Su nombre se fija con DD-070 (DD-134, punto 6).
- Lighthouse CI solo exige rendimiento (`servidor/lighthouserc.json`: la única aserción es `categories:performance`),
  así que no lo afecta.

Cierra la pregunta 3 de `openspec/changes/medicion-de-la-landing/design.md` y la 5 de
`openspec/changes/publicacion/design.md`.

La asentó el concilio del 2026-11-24 por delegación de Arthur Naranjo (C-236, C-245), en la decisión 65 de
`acta-2026-11-20-modelo-y-pasarela.md`.
