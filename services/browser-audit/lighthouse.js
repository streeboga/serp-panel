import { chromium } from 'playwright-core';

/**
 * Прогон Lighthouse поверх того же Chromium, что уже стоит в образе.
 *
 * Даёт привычную оценку 0-100, которую ждут в отчётах. Свои замеры мы и так
 * снимаем — Lighthouse нужен ради узнаваемой цифры и списка его собственных
 * замечаний, а не ради ещё одного LCP.
 */
export async function runLighthouse(url, { formFactor = 'mobile', port } = {}) {
  // Импорт внутри функции: пакет тяжёлый, и на замерах без Lighthouse его
  // грузить незачем.
  const lighthouse = (await import('lighthouse')).default;

  const result = await lighthouse(url, {
    port,
    output: 'json',
    logLevel: 'error',
    // Только производительность: остальные категории мы считаем сами и точнее.
    onlyCategories: ['performance'],
    formFactor,
    screenEmulation: formFactor === 'mobile'
      ? { mobile: true, width: 390, height: 844, deviceScaleFactor: 2, disabled: false }
      : { mobile: false, width: 1366, height: 900, deviceScaleFactor: 1, disabled: false },
    throttlingMethod: 'simulate',
  });

  const lhr = result?.lhr;

  if (!lhr) return null;

  const audit = (id) => {
    const a = lhr.audits?.[id];
    return a ? { score: a.score, value: a.numericValue ?? null, display: a.displayValue ?? null } : null;
  };

  // Замечания, которые стоит показать: провалившиеся аудиты с экономией.
  const opportunities = Object.values(lhr.audits ?? {})
    .filter((a) => a.details?.type === 'opportunity' && (a.numericValue ?? 0) > 100)
    .sort((a, b) => (b.numericValue ?? 0) - (a.numericValue ?? 0))
    .slice(0, 8)
    .map((a) => ({ title: a.title, saving_ms: Math.round(a.numericValue) }));

  return {
    score: Math.round((lhr.categories?.performance?.score ?? 0) * 100),
    form_factor: formFactor,
    metrics: {
      lcp: audit('largest-contentful-paint'),
      fcp: audit('first-contentful-paint'),
      cls: audit('cumulative-layout-shift'),
      tbt: audit('total-blocking-time'),
      speed_index: audit('speed-index'),
    },
    opportunities,
  };
}

/** Chromium с открытым отладочным портом: Lighthouse подключается по нему. */
export async function withDebugBrowser(fn) {
  const port = 9222;
  const browser = await chromium.launch({
    args: [`--remote-debugging-port=${port}`, '--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  });

  try {
    return await fn(port);
  } finally {
    await browser.close().catch(() => {});
  }
}
