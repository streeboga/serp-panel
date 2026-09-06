import { createServer } from 'node:http';
import { chromium } from 'playwright-core';
import { collectorSource, observerSource } from './in-page.js';
import { runLighthouse, withDebugBrowser } from './lighthouse.js';

const PORT = Number(process.env.PORT || 8081);
const TOKEN = process.env.BROWSER_AUDIT_TOKEN || '';
const NAV_TIMEOUT = Number(process.env.NAV_TIMEOUT_MS || 25000);
const SETTLE_MS = Number(process.env.SETTLE_MS || 2500);
const PAGES_BEFORE_RESTART = Number(process.env.PAGES_BEFORE_RESTART || 40);

const VIEWPORTS = {
  desktop: { width: 1366, height: 900, isMobile: false },
  tablet: { width: 820, height: 1180, isMobile: true },
  mobile: { width: 390, height: 844, isMobile: true },
};

let browser = null;
let pagesServed = 0;
let busy = false;

/**
 * Chromium течёт на длинных сериях, поэтому его периодически перезапускаем.
 * На сервере с занятым свопом это дешевле, чем ловить OOM.
 */
async function getBrowser() {
  if (browser && pagesServed < PAGES_BEFORE_RESTART) return browser;

  if (browser) {
    await browser.close().catch(() => {});
    browser = null;
    pagesServed = 0;
  }

  browser = await chromium.launch({
    args: [
      '--no-sandbox',
      '--disable-dev-shm-usage',
      '--disable-gpu',
      '--disable-extensions',
      '--disable-background-networking',
      '--js-flags=--max-old-space-size=256',
    ],
  });

  return browser;
}

async function measure({ url, viewport = 'desktop', userAgent }) {
  const context = await (await getBrowser()).newContext({
    ...VIEWPORTS[viewport] ?? VIEWPORTS.desktop,
    userAgent,
    deviceScaleFactor: 1,
    ignoreHTTPSErrors: true,
  });

  try {
    const page = await context.newPage();
    // Наблюдатели должны стоять до навигации, иначе сдвиги и отрисовка уже прошли.
    await page.addInitScript({ content: observerSource });

    const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });

    // Даём странице доехать: ленивые картинки и шрифты сдвигают вёрстку именно тут.
    await page.waitForLoadState('networkidle', { timeout: NAV_TIMEOUT }).catch(() => {});
    await page.waitForTimeout(SETTLE_MS);

    const collected = await page.evaluate(collectorSource);
    const timing = await page.evaluate(`(() => {
      const nav = performance.getEntriesByType('navigation')[0];
      if (!nav) return {};
      return {
        ttfb: Math.round(nav.responseStart),
        dom_content_loaded: Math.round(nav.domContentLoadedEventEnd),
        load: Math.round(nav.loadEventEnd),
        transfer_size: nav.transferSize,
      };
    })()`);

    pagesServed++;

    return {
      url,
      viewport,
      status: response?.status() ?? null,
      ...collected,
      timing,
    };
  } finally {
    await context.close().catch(() => {});
  }
}

const send = (res, code, body) => {
  const payload = JSON.stringify(body);
  res.writeHead(code, { 'Content-Type': 'application/json; charset=utf-8', 'Content-Length': Buffer.byteLength(payload) });
  res.end(payload);
};

createServer(async (req, res) => {
  if (req.method === 'GET' && req.url === '/health') {
    return send(res, 200, { ok: true, pages_served: pagesServed, busy });
  }

  const isLighthouse = req.method === 'POST' && req.url === '/lighthouse';

  if (!isLighthouse && !(req.method === 'POST' && req.url === '/measure')) {
    return send(res, 404, { error: 'not found' });
  }

  if (TOKEN && req.headers['x-audit-token'] !== TOKEN) {
    return send(res, 401, { error: 'unauthorized' });
  }

  // Один замер за раз: на этой машине два Chromium разом не помещаются.
  if (busy) return send(res, 429, { error: 'busy' });

  let raw = '';
  for await (const chunk of req) {
    raw += chunk;
    if (raw.length > 8192) return send(res, 413, { error: 'payload too large' });
  }

  let payload;
  try {
    payload = JSON.parse(raw || '{}');
  } catch {
    return send(res, 400, { error: 'invalid json' });
  }

  if (typeof payload.url !== 'string' || !/^https?:\/\//i.test(payload.url)) {
    return send(res, 422, { error: 'url required' });
  }

  busy = true;

  try {
    if (isLighthouse) {
      // Lighthouse поднимает свой Chromium с отладочным портом: два браузера
      // разом эта машина не тянет, поэтому свой закрываем на время прогона.
      if (browser) {
        await browser.close().catch(() => {});
        browser = null;
        pagesServed = 0;
      }

      const report = await withDebugBrowser((port) =>
        runLighthouse(payload.url, { formFactor: payload.viewport === 'desktop' ? 'desktop' : 'mobile', port }),
      );

      return send(res, 200, report ?? { url: payload.url, error: 'lighthouse вернул пустой отчёт' });
    }

    send(res, 200, await measure(payload));
  } catch (error) {
    send(res, 200, { url: payload.url, error: String(error && error.message ? error.message : error) });
  } finally {
    busy = false;
  }
}).listen(PORT, () => console.log(`browser-audit слушает :${PORT}`));

for (const signal of ['SIGTERM', 'SIGINT']) {
  process.on(signal, async () => {
    await browser?.close().catch(() => {});
    process.exit(0);
  });
}
