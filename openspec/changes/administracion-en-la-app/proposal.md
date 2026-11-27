## Why

La Cláusula I pone el panel de administración dentro de la app: "Aplicación única para iPhone y Android, con módulo de usuario, módulo de socio lavandería, módulo de socio repartidor y panel de administración" (`NSV-2026-001-v8.pdf:3:Cláusula I`). En la misma tabla, el entregable 4, "Panel de indicadores del negocio", lleva por plazo "Incluido en app" (`NSV-2026-001-v8.pdf:3:Cláusula I`). Ese panel tiene las siete funciones del Módulo D (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`, Módulo D #1 a #7; O-16) y los once indicadores del A.5 (O-21), con su descarga en CSV y Excel "para cualquier rango de fechas" (O-22) (`NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`). Sus criterios piden "Los cuatro módulos (usuario, socio lavandería, socio repartidor y administración) accesibles y funcionales conforme a A.3", un panel que "muestra datos reales de pedidos y usuarios activos" (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`), los indicadores "en tiempo real" y los reportes exportables (`:21:ANEXO A, Sección A.4.4`).

Hoy todo eso vive en el panel web de Filament, y la app, a una cuenta de administración, no le da sesión: le ofrece un botón que abre el panel web (administracion-E25). Arthur lo vio en el emulador y lo rechazó: "El administrador debe iniciar sesión. Eso del panel es algo tonto, ya que en sí esta app es una app móvil, no una app web". Eligió "Todo el Módulo D en la app", con el panel web como respaldo interno que no se muestra (C-247; decisión 69 de `vault/meta/actas/acta-2026-11-20-modelo-y-pasarela.md`). Cómo se construye lo decide ADR-0022 (D → `vault/arquitectura/adr/ADR-0022-administracion-en-la-app.md`).

## What Changes

- **La administración inicia sesión en la app** con su correo y su contraseña. Recibe un token de Sanctum con la habilidad `administracion`, que vence a los 30 días; se revoca al cerrar la sesión y al recuperar la contraseña. Con Google, tenga o no Google ligado, la respuesta le dice que entra con su correo y su contraseña, sin tocar su cuenta (ADR-0022, decisiones 1 a 6).
- **Un módulo de administración en la app,** `movil/app/administracion/`, con su inicio y una pantalla por función:
  - **Gestión de lavanderías (F-D01):** lista con estado y plaza, ficha, edición, suspensión, levantamiento y baja.
  - **Gestión de repartidores (F-D02):** lista, ficha, alta en una plaza, suspensión, levantamiento, baja y edición.
  - **Gestión de usuarios (F-D03):** lista, ficha con su historial, bloqueo y levantamiento.
  - **Monitoreo de pedidos (F-D04):** los pedidos activos de todas las plazas, con su mapa, que se ponen al día solos cada 30 s.
  - **Comisiones (F-D05):** el porcentaje global y el propio de cada lavandería.
  - **Reportes financieros (F-D06):** los ingresos por periodo y la conciliación con la pasarela.
  - **Avisos masivos (F-D07):** escribir, mandar y ver los avisos enviados.
  - **Indicadores (A.5):** los once, por categoría, con su rango, refrescados solos.
  - **Descargas (A.5):** cada reporte en CSV y en Excel, que la app guarda en la carpeta que elija la administración.
- **Una API de administración,** `/api/administracion/…`, con sesión de Sanctum y el componente único de autorización (ADR-0019). Cada ruta llama a la misma clase de dominio que usa el panel web; las reglas que hoy viven dentro de Filament se mueven primero a una clase de dominio que usan los dos (ADR-0022, decisión 9).
- **`GET /api/cuenta` y `DELETE /api/sesion`** también son de la administración (reemplaza la decisión 31 en esa parte).
- **Se quitan** el aviso "Las cuentas de administración entran por el panel", el botón "Abrir el panel" y la variable `EXPO_PUBLIC_PANEL_URL` (administracion-E25; decisión 56).

**Qué NO cambia:**

- **Las reglas del Módulo D y del A.5.** Las fijan los escenarios de `administracion` (E01 a E24 y E26), `alta-de-lavanderias` (E11 a E17 y E31), `repartidor` (E05 a E09 y E31), `indicadores` (E01 a E24) y `exportacion` (E01 a E12). Aquí se llevan a la app; ninguna se calcula de otra forma.
- **Los escenarios bloqueados siguen bloqueados,** con sus mismas DD: DD-053, DD-111, DD-113, DD-083, DD-114, DD-115, DD-020, DD-013, DD-012, DD-016, DD-019 y DD-104.
- **El panel web de Filament.** Se queda como respaldo interno, con sus rutas, su entrada y sus pruebas; no se borra y no recibe funciones nuevas. Sigue siendo donde se edita el contenido de la portada (testimonios y logos de prensa, landing-E15 a E19), que no es del Módulo D; quién lo edita y por qué vía llega al panel espera DD-141.
- **Los módulos A, B y C,** y los tokens de sus cuentas.
- **La cuenta de administración se sigue creando con el comando** `administracion:crear-cuenta` (administracion-E26; decisión 68).

**Fuera del contrato** (posibles Órdenes de Cambio, `NSV-2026-001-v8.pdf:8:Cláusula IV`). Nadie ha pedido nada nuevo para este cambio. Siguen fuera, como en los cambios de los que sale:

- Dar de alta, editar o borrar usuarios desde la administración: el Módulo D #3 dice "Consulta de usuarios activos, historial y bloqueo" (O-16).
- La posición del repartidor en el mapa del monitoreo (`NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3`, Módulo C; DD-077).
- Liquidar o dispersar dinero a los socios, y asignar repartidores de forma automática (`:18:Módulo C`; DD-013 y DD-014).
- Filtrar los indicadores por plaza: dar de alta una ciudad "en los reportes" es parte de abrir una ciudad nueva, que va por Orden de Cambio (`NSV-2026-001-v8.pdf:38:ANEXO C, Sección C.3`).
- Métricas fuera de las once del A.5: el contrato excluye los "Reportes de métricas o análisis de desempeño adicionales a los previstos en la Sección A.5" (`NSV-2026-001-v8.pdf:25:ANEXO A, Sección A.8`).
- Otros formatos de reporte, o mandarlos por correo o de forma programada.
- Avisos al teléfono de la administración, por ejemplo de un pedido nuevo o de un archivo listo: el contrato no los enumera. La descarga se sigue en su pantalla.
- Crear cuentas de administración desde la app, o subroles de administración.

## Capabilities

### New Capabilities

- `administracion-en-la-app`: el Módulo D y los indicadores del A.5, con su descarga, dentro de la app móvil: la sesión de la administración, su API y sus pantallas.

### Modified Capabilities

- `registro`: una cuenta de administración inicia sesión en la app, consulta su cuenta y cierra su sesión (registro-E33); su token vence ("Sesión que se conserva sin volver a escribir la contraseña"); no entra con Google, y registro-E18 no la liga ni le anula la contraseña ("Inicio de sesión con Google").
- `autorizacion`: la administración entra al Módulo D en la app, bajo `/api/administracion`, y en el panel de respaldo; la cuenta y la sesión son comunes a los cuatro roles ("Cada rol entra solo a su módulo", autorizacion-E02); y el barrido contra fugas sabe que la administración ve todas las plazas en su módulo, pero no lo de otra cuenta de administración ("Prueba contra fugas en cada cambio", autorizacion-E14).
- `administracion`: se quita el requisito "La administración en la app" con administracion-E25, y administracion-E26 deja de decir que la cuenta recibe 403 en la app.

## Impact

- **`servidor/`:**
  - el grupo de rutas `/api/administracion/…` en `routes/api.php` y sus controladores, que solo llaman a clases de dominio;
  - `App\Autorizacion\Autorizacion`: `usaLaApp()` incluye a la administración; `permiteEnLaApi()`, con la habilidad literal del token para el rol de administración, que llama el middleware `Autorizar`; y `puedeEntrarConProveedorExterno()`;
  - la pieza que crea los tokens de la app, `App\Identidad\TokensDeLaApp`, con la habilidad y el vencimiento de la administración, y los días en `config/acceso.php`;
  - `POST /api/sesion/google` rechaza a la administración antes de las dos ramas, la de Google ya ligado y la del correo (`App\Identidad\EntradaConGoogle`);
  - el grupo de `GET /api/cuenta` y `DELETE /api/sesion` se separa del catálogo del territorio;
  - se mueven a clases de dominio, sin cambiar lo que hacen, `App\Filament\EstadosDelPedido`, `HistorialDelUsuario`, la validación de `EditLavanderia` y de `EditRepartidor`, la regla del rango, la creación del aviso masivo y la de la descarga (los nombres del estado ya están en `App\Pedidos\NombresDelEstado`, tarea 1.7, y la regla del rango en `App\Validacion\RangoDeFechas`);
  - `App\Exportacion\Exportacion` asigna el `id_publico` de cada descarga;
  - pruebas en `servidor/tests/Feature/AdministracionEnLaApp/` y, las de la sesión, en `servidor/tests/Feature/Registro/`; cambian las de la autorización que fijaban la regla vieja: `RolPorModuloTest`, `BarridoContraFugasTest` y su apoyo `LlamadasSinEfecto` (autorizacion-E02 y E14).
- **`movil/`:**
  - el módulo `app/administracion/` y su código en `src/administracion/`;
  - el rol `administracion` en `src/sesion/modulos.ts` y su guarda en `app/_layout.tsx`;
  - piezas nuevas en `src/piezas/` para las gráficas del A.5, hechas con vistas y los tokens (ADR-0013);
  - se quitan `src/acceso/BotonDelPanel.tsx`, `EXPO_PUBLIC_PANEL_URL` de `src/configuracion/entorno.ts`, el aviso de `src/acceso/mensajes.ts` y su uso en `app/inicio-de-sesion.tsx` y `app/registro.tsx`;
  - `src/piezas/Pestanas.tsx` se desplaza de lado cuando sus pestañas no caben;
  - pruebas en `__tests__/administracion/`, con su servidor simulado en `__tests__/apoyo/administracion.ts`; la de `abrir-el-panel.test.tsx` se reemplaza, y los apoyos `lavanderia.ts` y `repartidor.ts` dejan de responder 403 a la administración.
- **Base de datos:** una migración, solo hacia adelante: `exports` recibe `id_publico`, un ULID opcional y único, para que la API nombre cada descarga sin su `id` (ADR-0016, reglas 5 y 9; ADR-0022, decisión 15). `personal_access_tokens` ya tiene `expires_at`, y `notifications` ya existe.
- **Dependencias:** ninguna nueva. `react-native-maps`, `expo-file-system` y `expo-router` ya están (ADR-0022, alternativas 5 y 6).
- **Configuración y publicación:** la variable de los días del token, en cada entorno; se retira `EXPO_PUBLIC_PANEL_URL` de `movil/.env.example`, de la tabla de variables de `openspec/changes/publicacion/design.md` y del script del APK local (`herramientas/apk-local/compilar.ps1`).
- **Cambios de los que depende:** `registro-e-inicio-de-sesion`, `autorizacion-por-rol`, `administracion`, `alta-de-lavanderias`, `repartidor`, `indicadores` y `exportacion`.
- **ADRs:** ADR-0022, que se apoya en ADR-0003, ADR-0005, ADR-0007, ADR-0009, ADR-0013, ADR-0016, ADR-0017, ADR-0018 y ADR-0019. ADR-0003 y ADR-0009 llevan una nota del 2026-11-24 que remite a ADR-0022.
- **Pendientes:** DD-141, quién edita en el panel de respaldo el contenido de la portada (ADR-0022, decisión 21).
