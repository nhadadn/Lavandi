## Context

- Es el hito H0, sprint 00, del 19 al 22 de noviembre de 2026 (`vault/formas-de-trabajo/sprints/sprint-00.md`).
- No hay código. La integración continua ya existe y se salta sola mientras no existan `servidor/composer.json` ni
  `movil/package.json` (`vault/formas-de-trabajo/integracion-continua.md`).
- El entorno local está instalado (C-166): PostgreSQL 17.5 con PostGIS 3.5.2 en Docker, Composer, `eas-cli` y
  `cloudflared`. Arthur levanta el servidor con `php artisan serve` (C-168).
- Solo existe el entorno local mientras DD-070 siga abierta (ADR-0012). Las cuentas de prueba las abre Arthur con su
  correo (DD-072, cerrada por C-170), pero este cambio todavía no usa ninguna.

## Goals / Non-Goals

**Goals:**

- Crear `servidor/` y `movil/`, cada uno con sus pruebas de `base` en verde y con su trabajo de la integración
  continua en verde.
- Dejar lo que necesita el registro del lunes: Sanctum instalado, la base con PostGIS, la app que arranca y la
  dirección de la API en configuración.

**Non-Goals:**

- Tablas del negocio, plazas y zonas (llegan con `registro-e-inicio-de-sesion` y `plazas-y-zonas`).
- Las reglas comunes del modelo de datos y su prueba de esquema (llegan con `modelo-de-datos`).
- Entornos de prueba y de producción, compilaciones firmadas y envío a las tiendas (`publicacion`, DD-070).
- Mapas, avisos, pagos y correo.

## Decisions

No hay decisión técnica nueva. Todo lo que este cambio construye ya está decidido:

- **El stack:** ADR-0002 (Expo, TypeScript, development builds y compilación en la nube) y ADR-0003 (Laravel,
  Sanctum y Filament).
- **La base de datos:** ADR-0004 (PostgreSQL, con PostGIS habilitado desde la primera migración).
- **Las versiones mínimas:** ADR-0009 (iOS 18 y Android 11; si el SDK exige más, manda el mayor).
- **Los entornos y los secretos:** ADR-0012 (solo local; secretos en variables de entorno, nunca en el repositorio).

Lo que sigue son detalles de implementación, no decisiones:

- La ruta de salud de base-E01 es la que trae Laravel.
- Las versiones mínimas se fijan con el mecanismo que Expo documenta para eso. La prueba lee la configuración
  resultante, no el mecanismo.
- La app lee su configuración de las variables del entorno, a través de la configuración de Expo.

## Tablas y migración

- **No crea tablas del negocio.** El modelo de datos aprobado (ADR-0016, C-193; `vault/arquitectura/modelo-de-datos.md`)
  dice en su columna "Nace en" qué cambio crea cada tabla, y ninguna nace aquí.
- **El orden de los cambios:** `base-tecnica` → `modelo-de-datos` → `registro-e-inicio-de-sesion`. `modelo-de-datos` va
  justo después de este cambio y antes de la primera tabla del negocio: trae la prueba de esquema (modelo-E01 a
  modelo-E16), la clase base `App\Models\Modelo` y la función `impedir_cambio()`. Así, `usuarios`, la primera tabla,
  ya nace revisada.
- **Lo que este cambio sí deja en la base:** la extensión PostGIS (base-E02) y las tablas del framework que trae la
  instalación: `migrations`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`,
  `password_reset_tokens` y `personal_access_tokens` (Sanctum), más `spatial_ref_sys` de PostGIS. Conservan su nombre
  (ADR-0016, regla 8) y la prueba de esquema las excluye (`modelo-de-datos`, diseño, decisión 2).
- **La tabla `users` que trae Laravel no se crea.** La cuenta se llama `usuarios`, con llave `id_usuarios`, y nace en
  `registro-e-inicio-de-sesion` (ADR-0016, reglas 5 y 8). La migración inicial de Laravel se queda solo con
  `password_reset_tokens` y `sessions`, y se quitan el modelo `User` y su fábrica. Dejarla haría fallar la prueba de
  esquema en cuanto entre `modelo-de-datos` (llave `id`, nombre en inglés), y las migraciones solo van hacia adelante
  (regla 9). El panel de base-E03 no la necesita: solo pide iniciar sesión y en este cambio no existe ninguna cuenta.
- **Sin trigger:** este cambio no crea `pedidos`, `pedido_eventos`, `pagos` ni `reembolsos`, las únicas tablas que
  llevan `impedir_cambio()`.

## Risks / Trade-offs

- [Expo SDK 58 o una dependencia exigen más que iOS 18 o Android 11] → Manda la mayor (ADR-0009). La prueba de
  base-E06 se ajusta al número nuevo y el cambio se anota en el ADR.
- [Las versiones de la integración continua (PHP 8.4, Node 22 y PostGIS 17-3.5) no coinciden con las locales] → Se
  confirman al crear los proyectos (grado E) y `ci.yml` se corrige en la misma rama.
- [Filament 5 y Laravel 13 no son compatibles en la versión que se instala] → Se confirma al instalar (grado E). Si
  falla, se fija la última combinación compatible y se anota.
- [En Windows fallan los enlaces simbólicos de alguna herramienta] → Se anota como `SIN VERIFICAR` en
  `vault/formas-de-trabajo/equipo-de-agentes.md`, como ya prevé el sprint 00.

## Open Questions

1. **La regla de la marca no tiene ADR.** "Marca y dominio fuera del código" la imponen `CLAUDE.md` §5 y §9.3 y la
   verifica `herramientas/ci/verificar_marca.py`, pero ningún ADR la recoge. Por eso su requisito cita la regla del
   proyecto y no lleva cita A ni D. **Necesita ADR**, o una línea en ADR-0012.
2. **El identificador de la app en las tiendas** (bundle id en iPhone y paquete en Android). Sale de configuración
   (`CLAUDE.md` §5). El definitivo depende de la marca (DD-023, DD-030) y de a nombre de quién va la cuenta (DD-017),
   y no se cambia después de publicar. Para el entorno local hace falta un valor neutro. Lo fija Arthur.
3. **El ícono y la pantalla de arranque nativa** llevan imagen de marca, y la nota de decisión de marca no está
   vigente. ¿Va un marcador neutro, como el monograma de los mocks? Pregunta de pantalla para Arthur.
4. **El script de tokens** (ADR-0013) vive en `herramientas/tokens/` y ninguna tarea del plan lo crea. Lo necesita
   el registro para sus piezas de formulario (sprint 01).
