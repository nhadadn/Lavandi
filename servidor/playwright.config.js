// Las pruebas de navegador de la landing, con Playwright en Chromium, Firefox y WebKit (openspec/changes/landing/,
// design.md, pregunta abierta 7; vault/formas-de-trabajo/ciclo-openspec-tdd.md, "Herramientas de prueba").
//
// Uso, desde servidor/:   npx playwright test
//
// Antes de abrir el navegador, el servidor de prueba compila los estilos, deja la base de pruebas desde cero con el
// contenido de tests/Browser/Soporte/PortadaDePrueba.php y sirve la aplicación en un puerto propio. La base es la de
// DB_DATABASE si el entorno la define, como en phpunit.xml; si no, nsv_lavanderia_pruebas. Nunca la de desarrollo.
//
// La versión de Playwright está fija en package.json: la 1.57.0 usa los navegadores chromium-1200, firefox-1497 y
// webkit-2227 (`npx playwright install` los descarga si faltan).
import { defineConfig, devices } from '@playwright/test';

import { CONFIGURACION_DE_PRUEBA } from './tests/Browser/Soporte/configuracion.js';

const PUERTO = Number(process.env.PORTADA_PUERTO ?? 8123);
const DIRECCION = `http://127.0.0.1:${PUERTO}`;
const BASE_DE_PRUEBAS = process.env.DB_DATABASE ?? 'nsv_lavanderia_pruebas';

if (BASE_DE_PRUEBAS === 'nsv_lavanderia') {
    throw new Error('Las pruebas de navegador borran su base: no corren contra la de desarrollo.');
}

export default defineConfig({
    testDir: 'tests/Browser',
    testMatch: '**/*.spec.js',
    outputDir: 'storage/framework/testing/playwright',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    reporter: 'list',
    use: {
        baseURL: DIRECCION,
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
        { name: 'webkit', use: { ...devices['Desktop Safari'] } },
    ],
    webServer: {
        command: [
            'npm run build',
            'php artisan migrate:fresh --force --seeder="Tests\\Browser\\Soporte\\PortadaDePrueba"',
            `php artisan serve --no-reload --host=127.0.0.1 --port=${PUERTO}`,
        ].join(' && '),
        url: `${DIRECCION}/up`,
        reuseExistingServer: false,
        timeout: 240_000,
        stdout: 'ignore',
        stderr: 'pipe',
        env: {
            APP_ENV: 'testing',
            APP_DEBUG: 'false',
            APP_URL: DIRECCION,
            DB_CONNECTION: 'pgsql',
            DB_DATABASE: BASE_DE_PRUEBAS,
            CACHE_STORE: 'array',
            SESSION_DRIVER: 'file',
            QUEUE_CONNECTION: 'sync',
            MAIL_MAILER: 'array',
            ...CONFIGURACION_DE_PRUEBA,
        },
    },
});
