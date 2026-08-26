/**
 * Код, который выполняется внутри страницы. Держится отдельным файлом, потому что
 * это единственное место, где контраст можно посчитать честно: питон и PHP не
 * воспроизводят каскад CSS, и первая версия такого скрипта на eq.team выдала
 * 67 выдуманных нарушений — белый текст на якобы белом фоне.
 *
 * Правило здесь одно: чего мы не смогли определить, уходит в "не проверено",
 * а не в "нарушение".
 */
export const collectorSource = `(() => {
  const parseColor = (value) => {
    const m = String(value).match(/rgba?\\(([^)]+)\\)/);
    if (!m) return null;
    const parts = m[1].split(',').map((p) => parseFloat(p.trim()));
    if (parts.length < 3 || parts.some(Number.isNaN)) return null;
    return { r: parts[0], g: parts[1], b: parts[2], a: parts.length > 3 ? parts[3] : 1 };
  };

  const relativeLuminance = ({ r, g, b }) => {
    const channel = (c) => {
      const s = c / 255;
      return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
  };

  const contrastRatio = (fg, bg) => {
    const l1 = relativeLuminance(fg);
    const l2 = relativeLuminance(bg);
    const [light, dark] = l1 > l2 ? [l1, l2] : [l2, l1];
    return (light + 0.05) / (dark + 0.05);
  };

  // Фон элемента почти всегда задан предку. Поднимаемся, пока не встретим
  // непрозрачный. Если по дороге попался градиент или картинка — сдаёмся:
  // угадывать усреднённый цвет значит выдумывать.
  const effectiveBackground = (node) => {
    for (let el = node; el && el !== document.documentElement.parentNode; el = el.parentElement) {
      const style = getComputedStyle(el);
      if (style.backgroundImage && style.backgroundImage !== 'none') return { unknown: 'background-image' };
      const color = parseColor(style.backgroundColor);
      if (color && color.a === 1) return { color };
      if (color && color.a > 0 && color.a < 1) return { unknown: 'translucent-background' };
    }
    return { color: { r: 255, g: 255, b: 255 } };
  };

  const selectorFor = (el) => {
    if (el.id) return '#' + el.id;
    const cls = (el.className && typeof el.className === 'string')
      ? '.' + el.className.trim().split(/\\s+/).slice(0, 2).join('.')
      : '';
    return el.tagName.toLowerCase() + cls;
  };

  // «Не проверено» без причины бесполезно: пользователь должен видеть,
  // почему покрытие такое, а не гадать.
  const contrast = { violations: [], unchecked: 0, checked: 0, unchecked_reasons: {} };
  const skip = (reason) => {
    contrast.unchecked++;
    contrast.unchecked_reasons[reason] = (contrast.unchecked_reasons[reason] || 0) + 1;
  };
  const seen = new Set();

  document.querySelectorAll('body *').forEach((el) => {
    const text = Array.from(el.childNodes)
      .filter((n) => n.nodeType === Node.TEXT_NODE)
      .map((n) => n.textContent.trim())
      .join(' ')
      .trim();

    if (text.length < 3) return;

    const style = getComputedStyle(el);
    if (style.visibility === 'hidden' || style.display === 'none' || parseFloat(style.opacity) === 0) return;

    const rect = el.getBoundingClientRect();
    if (rect.width === 0 || rect.height === 0) return;

    const fg = parseColor(style.color);
    if (!fg) { skip('не разобран цвет текста'); return; }
    if (fg.a < 1) { skip('полупрозрачный текст'); return; }

    const bg = effectiveBackground(el);
    if (bg.unknown) { skip(bg.unknown === 'background-image' ? 'фон картинкой или градиентом' : 'полупрозрачный фон'); return; }

    contrast.checked++;

    const size = parseFloat(style.fontSize);
    const weight = parseInt(style.fontWeight, 10) || 400;
    // Крупным считается текст от 24px, либо от 18.66px при жирном начертании.
    const large = size >= 24 || (size >= 18.66 && weight >= 700);
    const required = large ? 3 : 4.5;
    const ratio = contrastRatio(fg, bg.color);

    if (ratio + 0.005 < required) {
      const key = selectorFor(el) + '|' + Math.round(ratio * 100);
      if (seen.has(key)) return;
      seen.add(key);
      contrast.violations.push({
        selector: selectorFor(el),
        text: text.slice(0, 60),
        ratio: Math.round(ratio * 100) / 100,
        required,
        color: style.color,
        background: 'rgb(' + [bg.color.r, bg.color.g, bg.color.b].map(Math.round).join(', ') + ')',
        font_size: Math.round(size * 10) / 10,
      });
    }
  });

  contrast.violations.sort((a, b) => a.ratio - b.ratio);
  contrast.violations = contrast.violations.slice(0, 25);

  // Мелкий шрифт на мобильном — из отчётов gvozd про мобильные данные.
  const smallText = [];
  document.querySelectorAll('p, li, td, span, div').forEach((el) => {
    const own = Array.from(el.childNodes).filter((n) => n.nodeType === Node.TEXT_NODE)
      .map((n) => n.textContent.trim()).join('').trim();
    if (own.length < 20) return;
    const size = parseFloat(getComputedStyle(el).fontSize);
    if (size > 0 && size < 12) smallText.push({ selector: selectorFor(el), font_size: size });
  });

  return {
    contrast,
    small_text: smallText.slice(0, 10),
    cls: window.__clsReport || { value: 0, sources: [] },
    paint: window.__paintReport || {},
  };
})()`;

/**
 * Ставится до загрузки страницы: наблюдатели должны видеть сдвиги и отрисовку
 * с самого начала, иначе CLS всегда получается нулевым.
 */
export const observerSource = `(() => {
  window.__clsReport = { value: 0, sources: [] };
  window.__paintReport = {};

  const describe = (node) => {
    if (!node || !node.tagName) return 'неизвестный узел';
    if (node.id) return '#' + node.id;
    const cls = (node.className && typeof node.className === 'string')
      ? '.' + node.className.trim().split(/\\s+/).slice(0, 2).join('.') : '';
    return node.tagName.toLowerCase() + cls;
  };

  try {
    new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        // Сдвиги сразу после действия пользователя не считаются.
        if (entry.hadRecentInput) continue;
        window.__clsReport.value += entry.value;
        for (const source of entry.sources || []) {
          window.__clsReport.sources.push({
            element: describe(source.node),
            shift: Math.round(entry.value * 10000) / 10000,
          });
        }
      }
    }).observe({ type: 'layout-shift', buffered: true });

    new PerformanceObserver((list) => {
      const entries = list.getEntries();
      const last = entries[entries.length - 1];
      if (last) {
        window.__paintReport.lcp = Math.round(last.startTime);
        window.__paintReport.lcp_element = describe(last.element);
      }
    }).observe({ type: 'largest-contentful-paint', buffered: true });

    new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        if (entry.name === 'first-contentful-paint') window.__paintReport.fcp = Math.round(entry.startTime);
      }
    }).observe({ type: 'paint', buffered: true });
  } catch (e) {
    window.__clsReport.error = String(e);
  }
})()`;
