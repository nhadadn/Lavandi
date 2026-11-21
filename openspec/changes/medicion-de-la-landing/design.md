## Context

- ADR-0011 ya decidió cómo se mide: GA4 y Meta Pixel entran por un contenedor de GTM, Search Console se verifica por
  DNS y PageSpeed se mide con GTM cargado. Su criterio de reversión: si no se llega a 70 después de optimizar imágenes
  y cargar GTM en diferido, se pasa a un sitio estático.
- Las cuentas de producción van a nombre de EL CLIENTE (`NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.11`; RN-31). Local y
  prueba pueden usar cuentas de EL PRESTADOR mientras tanto (ADR-0012).
- La integración continua ya prevé Lighthouse como alerta temprana y PageSpeed Insights sobre la dirección publicada al
  acreditar (`vault/formas-de-trabajo/integracion-continua.md`).
- La trazabilidad prevé Playwright interceptando las llamadas a GA4 y al Pixel (A42-2) y la prueba de http a https
  (A42-3) (`vault/formas-de-trabajo/trazabilidad-criterio-test.md`).
- El dominio no está comprado (DD-023) y el alojamiento no está decidido (DD-070): todo lo que pide la dirección
  publicada espera.

## Goals / Non-Goals

**Goals:**

- Acreditar A42-2, A42-3 y A42-4, y dejar verificable por presencia la preparación para buscadores (O-09).
- Que cambiar de cuenta de prueba a la del cliente sea cambiar configuración, no código.

**Non-Goals:**

- Diseñar campañas, reportes de métricas o tableros de analítica (`NSV-2026-001-v8.pdf:25:ANEXO A, Sección A.8`).
- La ficha del negocio en Google Maps y las páginas por ciudad.
- Un aviso de consentimiento de cookies (ver DD-118).
- La redirección a https del panel y de la API (`publicacion`).

## Decisions

No hay decisión técnica nueva: el cambio aplica ADR-0011 y ADR-0012, sin repetirlos. La redirección de http a https
usa el mismo mecanismo que `publicacion` (publicacion-E04); lo implementa la primera tarea que llegue y la otra solo lo
prueba en sus rutas.

## Tablas y migración

No crea tablas ni migraciones: ninguna tabla de `vault/arquitectura/modelo-de-datos.md` nace aquí (ADR-0016). Para la
zona de servicio de la información estructurada (medicion-E10) solo lee las plazas activas y sus zonas activas,
`plazas` (`id_plazas`, `nombre`, `activa`) y `zonas` (`id_plazas`, `nombre`, `activa`), que nacen en `plazas-y-zonas`.
El identificador del contenedor de GTM, el dominio y los datos de contacto salen de la configuración de cada entorno, y
ningún evento de medición se guarda en la base.

## Risks / Trade-offs

- [GTM y Meta Pixel bajan la velocidad en teléfono] → carga en diferido y criterio de reversión de ADR-0011.
- [Las cuentas del cliente no existen] → se prueba con un contenedor de prueba de EL PRESTADOR (ADR-0012) y se cambia
  el identificador antes de producción.
- [Medir sin consentimiento puede chocar con el aviso de privacidad del cliente] → la decisión legal es de EL CLIENTE,
  que es el responsable (`NSV-2026-001-v8.pdf:10:Cláusula X`); va en DD-118.
- [Search Console necesita un registro en el DNS del dominio del cliente] → espera a DD-023 y a los accesos de
  colaborador.
- [El inventario pesó F-T16 y F-T17 en 7 h] → las tareas suman 25 h; ver `tasks.md`.

## Open Questions

1. **El plan de eventos (BR-11).** La spec fija qué se mide —visita, descarga y formulario— pero no el nombre de cada
   evento. Se propone en la tarea 1.2 y lo aprueba Arthur.
2. **¿Se versiona en el repositorio la exportación del contenedor de GTM?** Sin ella, la configuración de etiquetas
   vive solo en la cuenta del cliente. Lo decide Arthur.
3. **¿El entorno de prueba pide a los buscadores que no lo indexen?** No lo dicen el contrato ni ADR-0012. **Necesita
   decisión de Arthur, en ADR-0012.**
4. **Dónde corre Lighthouse CI:** un trabajo más en `.github/workflows/ci.yml`, con la portada levantada en la máquina
   de la integración continua. Versiones por confirmar (E).
5. **Consentimiento para la medición** (DD-118).
