const { chromium } = require('@playwright/test');

const baseUrl = 'http://localhost:8080';
const credentials = { username: 'Lynda', password: 'Lynda27121979' };
const viewports = [
    { name: 'desktop', width: 1920, height: 1080 },
    { name: 'mobile', width: 390, height: 844 },
];
const excludedPaths = [
    '/logout', '/_profiler', '/_wdt', '/calendar/feed/', '/download', '/export/',
];

const isCrawlable = (url) => {
    if (url.origin !== new URL(baseUrl).origin) return false;
    if (!['http:', 'https:'].includes(url.protocol)) return false;
    if (excludedPaths.some((path) => url.pathname.includes(path))) return false;
    return !url.pathname.match(/\.(?:ics|pdf|csv|xlsx)$/i);
};

const routeKey = (url) => url.pathname
    .replace(/\/[0-9A-HJKMNP-TV-Z]{26}(?=\/|$)/g, '/:publicId')
    .replace(/\/\d{4}-\d{2}-\d{2}(?=\/|$)/g, '/:date');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const report = {};

    for (const viewport of viewports) {
        const context = await browser.newContext({ viewport });
        const page = await context.newPage();
        const runtimeErrors = [];
        page.on('pageerror', (error) => runtimeErrors.push(`pageerror: ${error.message}`));
        page.on('console', (message) => {
            if (message.type() === 'error') runtimeErrors.push(`console: ${message.text()}`);
        });
        page.on('response', (response) => {
            if (response.status() >= 400) runtimeErrors.push(`http ${response.status()}: ${response.url()}`);
        });

        await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
        await page.getByLabel('Identifiant').fill(credentials.username);
        await page.getByLabel('Mot de passe').fill(credentials.password);
        await Promise.all([
            page.waitForURL(`${baseUrl}/`),
            page.getByRole('button', { name: 'Se connecter' }).click(),
        ]);

        const queue = [`${baseUrl}/`];
        const visited = new Set();
        const pages = [];

        while (queue.length > 0 && visited.size < 80) {
            const target = queue.shift();
            const normalized = new URL(target);
            normalized.hash = '';
            const key = normalized.toString();
            const normalizedRoute = routeKey(normalized);
            if (visited.has(normalizedRoute) || !isCrawlable(normalized)) continue;
            visited.add(normalizedRoute);

            runtimeErrors.length = 0;
            let response;
            try {
                response = await page.goto(key, { waitUntil: 'domcontentloaded', timeout: 10_000 });
                await page.waitForTimeout(150);
            } catch (error) {
                pages.push({ url: normalized.pathname, navigationError: error.message });
                continue;
            }

            const audit = await page.evaluate(() => {
                const visible = (element) => {
                    const style = window.getComputedStyle(element);
                    const rect = element.getBoundingClientRect();
                    return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
                };
                const controlHasLabel = (element) => {
                    if (element.closest('label')) return true;
                    if (element.id && document.querySelector(`label[for="${CSS.escape(element.id)}"]`)) return true;
                    return ['aria-label', 'aria-labelledby', 'title'].some((name) => element.hasAttribute(name));
                };
                const actionableHasName = (element) => {
                    const text = element.textContent.trim();
                    if (text) return true;
                    if (['aria-label', 'aria-labelledby', 'title'].some((name) => element.hasAttribute(name))) return true;
                    const image = element.querySelector('img[alt]');
                    return Boolean(image && image.alt.trim());
                };
                const ids = [...document.querySelectorAll('[id]')].map((node) => node.id);
                const duplicateIds = [...new Set(ids.filter((id, index) => ids.indexOf(id) !== index))];
                const unlabeledControls = [...document.querySelectorAll('input:not([type="hidden"]), select, textarea')]
                    .filter(visible)
                    .filter((node) => !controlHasLabel(node))
                    .map((node) => `${node.tagName.toLowerCase()}#${node.id || '-'}[name="${node.getAttribute('name') || ''}"]`);
                const unnamedActions = [...document.querySelectorAll('a[href], button')]
                    .filter(visible)
                    .filter((node) => !actionableHasName(node))
                    .map((node) => `${node.tagName.toLowerCase()}#${node.id || '-'}@${node.getAttribute('href') || ''}`);
                const imagesWithoutAlt = [...document.querySelectorAll('img:not([alt])')].filter(visible).length;
                const headings = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')]
                    .filter(visible)
                    .map((node) => `${node.tagName}:${node.textContent.trim().replace(/\s+/g, ' ').slice(0, 80)}`);
                const links = [...document.querySelectorAll('a[href]')].map((node) => node.href);
                return {
                    title: document.title,
                    h1: [...document.querySelectorAll('h1')].filter(visible).map((node) => node.textContent.trim()),
                    horizontalOverflow: document.documentElement.scrollWidth - window.innerWidth,
                    unlabeledControls,
                    unnamedActions,
                    duplicateIds,
                    imagesWithoutAlt,
                    headings,
                    links,
                };
            });

            for (const href of audit.links) {
                const url = new URL(href);
                url.hash = '';
                if (isCrawlable(url) && !visited.has(routeKey(url))) queue.push(url.toString());
            }
            delete audit.links;
            pages.push({
                url: normalized.pathname + normalized.search,
                status: response?.status() ?? null,
                ...audit,
                runtimeErrors: [...new Set(runtimeErrors)],
            });
        }

        report[viewport.name] = pages;
        await context.close();
    }

    await browser.close();
    const condensed = {};
    for (const [viewport, pages] of Object.entries(report)) {
        condensed[viewport] = {
            pageCount: pages.length,
            paths: pages.map((page) => page.url),
            issues: pages.filter((page) =>
                page.navigationError || page.status >= 400 || page.horizontalOverflow > 1 ||
                page.unlabeledControls?.length || page.unnamedActions?.length ||
                page.duplicateIds?.length || page.imagesWithoutAlt || page.runtimeErrors?.length ||
                page.h1?.length !== 1
            ),
        };
    }
    console.log(JSON.stringify(condensed, null, 2));
})();
