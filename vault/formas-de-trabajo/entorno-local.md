---
estado: vigente
grado: mixto
fuente: revisión de la máquina de Arthur del 2026-11-15 e instalación del 2026-11-18 (B); acta-2026-11-18-entorno-y-mocks.md C-166 y C-168 (C); ADR-0002, ADR-0003, ADR-0004, ADR-0007, ADR-0008 y ADR-0012 (D); acta-2026-11-13-cierre-de-la-fase-6.md C-154 (C); versiones y precios de terceros (E)
fecha: 2026-11-18
dueño: Arthur Naranjo
---
# Entorno local

Mientras el hosting siga pendiente, todo corre en la máquina de Arthur (C-154; ADR-0012). Esto es lo que hay y lo que
falta para el sprint 1. **No hace falta Android Studio ni una Mac:** la app se compila en la nube con EAS
(ADR-0002).

## Lo que ya está instalado (B, revisado el 2026-11-15)

| Herramienta | Versión | Nota |
|---|---|---|
| PHP | 8.4.27, en `C:\xampp\php` | Es el `php.exe` del PATH. Arthur ya no usa el Apache de XAMPP: trabaja con `php artisan serve` y `npm run dev` (C-168) |
| Composer | 2.8.3 | De 2024: hay que actualizarlo |
| Node y npm | 22.23.4 y 10.9.9 | — |
| Git | 2.53.0 | — |
| Docker | 29.3.0 | — |
| Python | 3.9 | Lo usan las herramientas del vault |
| Java | Solo la 8 | Solo hace falta para compilar Android en local, que es opcional |
| SDK de Android | El de App_Ittob, en `AppData/Local/ittob-android/sdk` | `adb` no está en el PATH |

## Lo que faltaba, y qué se instaló el 2026-11-18

Arthur lo autorizó: "ok, instálalo" (C-166).

| # | Qué | Cómo | Para qué |
|---|---|---|---|
| 1 | ✅ Activar extensiones de PHP | En `C:\xampp\php\php.ini`, quitar el `;` a `extension=pdo_pgsql`, `extension=pgsql` y `extension=intl` | PostgreSQL (ADR-0004) y Filament (ADR-0003). **Hecho:** solo cambiaron esas tres líneas; respaldo en `php.ini.respaldo-2026-11-18`. Para App_Ittob y Temsa solo se agregan extensiones |
| 2 | ✅ Actualizar Composer | `composer self-update` | **Hecho:** de la 2.8.3 a la 2.11.2. Se regresa con `composer self-update --rollback` |
| 3 | ✅ PostgreSQL con PostGIS | Un contenedor de Docker con la imagen `postgis/postgis` | La base de datos local (ADR-0004). **Hecho:** contenedor `nsv-lavanderia-postgis` (PostgreSQL 17.5, PostGIS 3.5.2), solo en `127.0.0.1:5432`, base `nsv_lavanderia`, usuario `nsv`, volumen `nsv-lavanderia-pgdata`; arranca con Docker. La contraseña local no se versiona: va en `servidor/.env`. PHP ya se conecta |
| 4 | ✅ EAS CLI · ⏳ cuenta de Expo | `npm install -g eas-cli`; la cuenta de Expo es gratis | Compilar la app en la nube, incluido el iPhone (ADR-0002). La cuenta la abre Arthur con su correo (C-170). **Hecho:** `eas-cli` 27.10.0 |
| 5 | ✅ Un túnel | Cloudflare Tunnel (`cloudflared`) | Que el teléfono y los webhooks de Conekta lleguen a la máquina local (ADR-0012). **Hecho:** `cloudflared` 2026.11.1, bajado del repositorio oficial de Cloudflare con firma válida, en `AppData\Local\Programs\cloudflared` y en el PATH de usuario (`winget` no está en esta máquina). Túnel rápido, sin cuenta: `cloudflared tunnel --url http://localhost:8000` |
| 6 | ⏳ Cuentas de prueba | Firebase, Google Cloud (mapas e inicio de sesión con Google) y el sandbox de Conekta | Avisos, mapas y pagos en local. Las abre Arthur con su correo (C-170) |
| 7 | ⏳ Teléfonos | Un Android y un iPhone físicos | Sin Mac no hay simulador de iPhone. La cuenta de Apple para probar en iPhone es DD-069 |
| 8 | `adb` en el PATH (opcional) | Agregar `platform-tools` del SDK de Android | Instalar APK y ver los registros del teléfono |
| 9 | JDK 17 (opcional) | Solo si se quiere compilar Android en local | EAS lo hace en la nube |

Las versiones exactas se confirman al instalar (E).

## Las skills externas, en el sprint 1

Arthur las eligió el 2026-11-18 (C-163; [[equipo-de-agentes]]). Van en este repositorio, en `servidor/` o en `movil/`, cada una en una rama
aparte, y se leen antes de usarse. Los comandos salen de la documentación de cada una (E, 2026-11-18).

| # | Qué | Cómo | Ajuste |
|---|---|---|---|
| 1 | Laravel Boost | En `servidor/`: `composer require laravel/boost --dev` y después `php artisan boost:install` | Agrega un bloque al `CLAUDE.md`; las reglas del proyecto siguen arriba. En el `.env` local, `BOOST_RULES_ENABLED=false`. Tinker se queda apagado |
| 2 | La skill de Filament | La instala Boost cuando detecta Filament 5 | — |
| 3 | El plugin de Expo | En Claude Code: `/plugin install expo@claude-plugins-official` | Leer antes `eas-workflows`, porque preautoriza `eas-cli` |
| 4 | La skill de Callstack | `npx skills@latest add callstackincubator/agent-skills --skill react-native-testing` | Comprobar que funciona en Windows |
| 5 | Maestro y su MCP | Instalar la CLI de Maestro y después `claude mcp add maestro -- maestro mcp` | — |
