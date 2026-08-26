import { collectorSource } from './in-page.js';

// Вытаскиваем чистые функции из внутристраничного скрипта и проверяем на известных
// значениях: композитинг и коэффициент контраста — арифметика, браузер для них не нужен.
const body = collectorSource.replace(/^\(\(\) => \{/, '').replace(/\}\)\(\)$/, '');
const harness = new Function(`
  const document = { querySelectorAll: () => [], documentElement: {} };
  const getComputedStyle = () => ({});
  const Node = { TEXT_NODE: 3 };
  const window = {};
  ${body.split('const contrast =')[0]}
  return { parseColor, contrastRatio, composite };
`);

const { parseColor, contrastRatio, composite } = harness();

const white = { r: 255, g: 255, b: 255, a: 1 };
const black = { r: 0, g: 0, b: 0, a: 1 };

const cases = [
  ['чёрный на белом', contrastRatio(black, white), 21],
  ['#767676 на белом', contrastRatio(parseColor('rgb(118,118,118)'), white), 4.54],
  ['#999999 на белом', contrastRatio(parseColor('rgb(153,153,153)'), white), 2.85],
  ['белый на белом', contrastRatio(white, white), 1],
  ['rgba(0,0,0,.7) поверх белого', contrastRatio(composite(parseColor('rgba(0,0,0,0.7)'), white), white), 8.54],
  ['rgba(255,255,255,.5) поверх чёрного', contrastRatio(composite(parseColor('rgba(255,255,255,0.5)'), black), black), 5.28],
];

let bad = 0;
for (const [name, got, want] of cases) {
  const ok = Math.abs(got - want) < 0.05;
  if (!ok) bad++;
  console.log(`  ${ok ? '✓' : '✗'} ${name}: ${got.toFixed(2)} (ожидалось ${want})`);
}
process.exit(bad === 0 ? 0 : 1);
