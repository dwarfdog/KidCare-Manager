const { defineConfig } = require('@playwright/test');
const path = require('node:path');

const databasePath = path.join(__dirname, 'var', 'e2e.db').replaceAll('\\', '/');
const environment = {
    ...process.env,
    APP_ENV: 'test',
    APP_DEBUG: '0',
    DATABASE_URL: `sqlite:///${databasePath}`,
    BANK_ENCRYPTION_KEY: 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
    DOCUMENT_ENCRYPTION_KEY: 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
    MESSENGER_TRANSPORT_DSN: 'doctrine://default?auto_setup=0',
};
Object.assign(process.env, environment);

module.exports = defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    use: {
        baseURL: 'http://127.0.0.1:8091',
        trace: 'retain-on-failure',
    },
    globalSetup: require.resolve('./tests/e2e/global-setup'),
});
