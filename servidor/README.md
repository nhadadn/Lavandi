# Servidor

La API de la app, el panel de administración y la landing: Laravel 13 con Sanctum y Filament 5, sobre PostgreSQL con
PostGIS.

## Requisitos

- PHP 8.4 con las extensiones `pdo_pgsql`, `pgsql`, `intl`, `mbstring`, `bcmath` y `zip`.
- Composer 2.
- PostgreSQL 17 con PostGIS 3.5.

## Arranque en local

```bash
composer install
cp .env.example .env          # y se llenan DB_PASSWORD y los demás secretos, solo en .env
php artisan key:generate
php artisan migrate:fresh     # la primera migración habilita PostGIS
php artisan serve             # http://localhost:8000; el panel está en /admin
```

El nombre del producto y la dirección salen de `APP_NAME` y `APP_URL`. Los enlaces absolutos usan `APP_URL`, así que
el panel se abre por esa misma dirección.

## Pruebas

Corren contra PostgreSQL, nunca contra SQLite. En local usan la base `nsv_lavanderia_pruebas` (`phpunit.xml`), con el
mismo servidor, usuario y contraseña del `.env`. Si el entorno ya define `DB_CONNECTION` o `DB_DATABASE`, como la
integración continua, manda el entorno.

```bash
php artisan test
vendor/bin/pint --test
```
