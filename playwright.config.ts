import { defineConfig, devices } from '@playwright/test';

/*
 | A few flows through the built web app, served by Laravel, with the API
 | faked in the browser (tests/e2e/support/fakeDonna.ts). Nothing here reaches
 | a model, a mail server or the database.
 |
 |   npm run build && npm run test:e2e
 */

const port = 8123;
const origin = `http://127.0.0.1:${port}`;

export default defineConfig({
    testDir: 'tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    reporter: 'list',
    use: {
        baseURL: origin,
        // A service worker would answer requests before the fake API could.
        serviceWorkers: 'block',
        timezoneId: 'America/Chicago',
        launchOptions: process.env.PLAYWRIGHT_CHROMIUM_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH } : {},
    },
    projects: [
        { name: 'phone', use: { ...devices['iPhone 15'], browserName: 'chromium' } },
        { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    ],
    webServer: {
        // The page only needs the Blade shell, so the server runs without a database.
        command: `php artisan serve --host=127.0.0.1 --port=${port} --no-reload`,
        url: `${origin}/up`,
        env: { APP_ENV: 'testing', SESSION_DRIVER: 'array', CACHE_STORE: 'array', APP_URL: origin },
        reuseExistingServer: !process.env.CI,
        timeout: 30_000,
    },
});
