---
stepsCompleted: ['step-01-init', 'step-02-discovery', 'step-03-core-experience', 'step-04-emotional-response', 'step-05-inspiration', 'step-06-design-system', 'step-07-defining-experience', 'step-08-visual-foundation', 'step-09-design-directions', 'step-10-user-journeys', 'step-11-component-strategy', 'step-12-ux-patterns', 'step-13-responsive-accessibility', 'step-14-complete']
workflowType: 'ux-design'
status: 'complete'
completedAt: '2026-03-26'
project_name: 'serp-panel'
inputDocuments:
  - _bmad-output/planning-artifacts/prd.md
  - _bmad-output/planning-artifacts/architecture.md
---

# UX Design Specification — SERP Panel

_Спецификация пользовательского опыта для SaaS-платформы мониторинга SEO-позиций._

## Core Experience Definition

### Product Personality

**Тон:** Профессиональный, утилитарный, быстрый. Это рабочий инструмент, не маркетинговый сайт. Никаких анимаций ради анимаций, никаких модалов ради модалов.

**Метафора:** Панель приборов автомобиля — всё важное видно сразу, детали — по клику. Водитель (SEO-специалист) смотрит на дорогу (позиции), панель показывает скорость (динамику) и уровень топлива (Wordstat).

**Ключевые UX-принципы:**
1. **Data-first:** Данные — главный герой каждого экрана. Минимум хрома, максимум информации.
2. **2-click rule:** Любая позиция по любому ключевику доступна в 2 клика от дашборда.
3. **Scan, don't read:** Цветовые индикаторы (зелёный ↑, красный ↓, серый =) для быстрого сканирования.
4. **No surprise navigation:** Sidebar всегда видна, breadcrumbs на каждой странице.

### Target Users & Their Context

| Persona | Контекст использования | Приоритет экрана |
|---|---|---|
| SEO-специалист | Рабочий день, desktop, 2-3 проекта | Таблица ключевиков, SERP-детали |
| Владелец агентства | Утренняя проверка, быстрый взгляд | Дашборд, сводка по проектам |
| Аналитик | Глубокий анализ, большие выгрузки | Фильтры, экспорт, графики |
| Админ | Настройка, мониторинг | Скрейперы, расписания, участники |

## Emotional Response Design

### Desired Emotional States

| Момент | Целевая эмоция | Как достигаем |
|---|---|---|
| Первый вход | «Понятно, с чего начать» | Пустое состояние с CTA «Создать проект» |
| Импорт ключевиков | «Быстро и легко» | Drag & drop CSV, progress bar, мгновенный feedback |
| Просмотр позиций | «Всё под контролем» | Цветовая динамика, сортировка, фильтры |
| Обнаружение падения | «Сразу понятно, что случилось» | Красная подсветка, бейджи ↓, ссылка на SERP |
| Экспорт данных | «Без лишних действий» | Одна кнопка, формат по умолчанию |

### Anti-Patterns (Что НЕ делаем)

- ❌ Модальные окна для информации (только для деструктивных действий)
- ❌ Infinite scroll в таблицах (только пагинация — данных слишком много)
- ❌ Tooltip-hell (не прячем важную информацию в тултипы)
- ❌ Skeleton loaders дольше 2 секунд (показать хоть что-то)
- ❌ Полноэкранные loading states (блокируют сканирование)

## Design System

### Foundation: shadcn/ui + Tailwind CSS

**Выбор:** shadcn/ui — не библиотека, а коллекция копируемых компонентов. Полный контроль, zero vendor lock-in, работает с Tailwind CSS.

**Установленные компоненты (используем):**
- `Button`, `Input`, `Select`, `Checkbox`, `Label` — формы
- `Table`, `DataTable` — таблицы с TanStack Table
- `Dialog`, `AlertDialog` — модальные окна (только для confirmations)
- `DropdownMenu`, `Command` — меню и поиск
- `Badge` — бейджи типов сайтов, движков, статусов
- `Card` — карточки дашборда
- `Tabs` — вкладки на деталях ключевика
- `Toast` — уведомления об успехе/ошибке
- `Skeleton` — loading states
- `Separator`, `ScrollArea` — layout utilities

**Дополнительно нужны:**
- `DatePicker` / `DateRangePicker` — фильтр по дате SERP
- `Combobox` (Command-based) — выбор региона, кластера

### Color System

```
Background:     hsl(0 0% 100%)           // белый
Card:           hsl(0 0% 98%)            // серый фон карточек
Border:         hsl(220 13% 91%)         // границы
Text Primary:   hsl(224 71% 4%)          // почти чёрный
Text Muted:     hsl(220 9% 46%)          // серый текст

Primary:        hsl(221 83% 53%)         // синий (actions)
Primary Hover:  hsl(221 83% 43%)

Destructive:    hsl(0 84% 60%)           // красный (удаление)

// Семантические цвета позиций
Position Up:    hsl(142 71% 45%)         // зелёный ↑
Position Down:  hsl(0 84% 60%)           // красный ↓
Position Stable: hsl(220 9% 46%)         // серый =

// Бейджи типов сайтов
Marketplace:    hsl(262 83% 58%)         // фиолетовый
Ecommerce:      hsl(221 83% 53%)         // синий
Info:           hsl(142 71% 45%)         // зелёный
Aggregator:     hsl(25 95% 53%)          // оранжевый
Government:     hsl(220 9% 46%)          // серый
Blog:           hsl(330 81% 60%)         // розовый
```

**Dark Mode:** Не в MVP. Добавить в Growth через CSS variables inversion.

### Typography

```
Font Family:    Inter (system-ui fallback)
Font Sizes:
  xs:    0.75rem / 1rem      // мелкие метки
  sm:    0.875rem / 1.25rem  // таблицы, secondary
  base:  1rem / 1.5rem       // основной текст
  lg:    1.125rem / 1.75rem  // заголовки секций
  xl:    1.25rem / 1.75rem   // заголовки страниц
  2xl:   1.5rem / 2rem       // dashboard numbers
  3xl:   1.875rem / 2.25rem  // hero metrics
```

### Spacing & Layout

```
Sidebar:        w-64 (256px), collapsed: w-16 (64px)
Content:        max-w-7xl mx-auto px-6
Page header:    py-6, flex justify-between items-center
Cards:          p-6, rounded-lg border
Tables:         full-width, border-separate, border-spacing-0
Gaps:           gap-4 (standard), gap-6 (sections), gap-2 (compact)
```

## Visual Foundation

### Layout Architecture

```
┌─────────────────────────────────────────────────────────┐
│  Topbar: org selector │ search (⌘K) │ notifications │ avatar │
├──────┬──────────────────────────────────────────────────┤
│      │  Breadcrumbs: Dashboard > Проект А > Ключевики  │
│  S   │─────────────────────────────────────────────────│
│  i   │  Page Header: title + actions (import, export)  │
│  d   │─────────────────────────────────────────────────│
│  e   │                                                  │
│  b   │  Content Area                                    │
│  a   │  ┌──────────────────────────────────────────┐   │
│  r   │  │  Filters bar (engine, region, search)    │   │
│      │  ├──────────────────────────────────────────┤   │
│  ↕   │  │  Data Table                              │   │
│      │  │  ...                                     │   │
│  N   │  │  ...                                     │   │
│  a   │  ├──────────────────────────────────────────┤   │
│  v   │  │  Pagination                              │   │
│      │  └──────────────────────────────────────────┘   │
└──────┴──────────────────────────────────────────────────┘
```

### Sidebar Navigation

```
📊 Dashboard
📁 Проекты
   └─ [Проект А]
      ├─ Обзор
      ├─ Ключевики
      ├─ Домены
      └─ Конкуренты
🏷️ Классификация
   ├─ Правила
   └─ Домены
🔧 Скрейперы
📅 Расписания
⚙️ Настройки
   ├─ Организация
   └─ Участники
```

**Sidebar behavior:**
- Desktop: always visible, collapsible to icons
- Mobile (Growth): drawer, hamburger toggle

## User Journey Screens

### Screen 1: Dashboard (/)

**Цель:** Быстрый обзор состояния всех проектов.

```
┌─ Dashboard ──────────────────────────────────────────┐
│                                                       │
│  [Проект selector: ▼ Все проекты]                    │
│                                                       │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐               │
│  │ TOP-3│ │TOP-10│ │TOP-20│ │TOP-100│              │
│  │  45  │ │  120 │ │  230 │ │  480  │              │
│  │ +3 ↑ │ │ -2 ↓ │ │ +5 ↑ │ │ +12 ↑│              │
│  └──────┘ └──────┘ └──────┘ └──────┘               │
│                                                       │
│  ┌─ Позиции изменились ──────────────────────────┐   │
│  │ ↑ Улучшились: 34    ↓ Упали: 12    = : 434   │   │
│  └───────────────────────────────────────────────┘   │
│                                                       │
│  ┌─ Visibility Score ────────────────────────────┐   │
│  │  [Line chart: visibility over 30 days]         │   │
│  └───────────────────────────────────────────────┘   │
└───────────────────────────────────────────────────────┘
```

**Компоненты:** SummaryCard (×4), PositionChangeSummary, VisibilityChart (Recharts).

### Screen 2: Keywords Table (/projects/:id/keywords)

**Цель:** Основной рабочий экран — таблица ключевиков с позициями.

```
┌─ Ключевики ─────────────────────────────── [Импорт] [Экспорт] ─┐
│                                                                   │
│  Filters: [Engine ▼] [Регион ▼] [Категория ▼] [🔍 Поиск...]   │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │ □ │ Ключевик           │Я/G│ Позиция│ Δ  │ Частотн.│ URL  │ │
│  ├───┼────────────────────┼───┼────────┼────┼─────────┼──────┤ │
│  │ □ │ купить квартиру    │ Я │    7   │ +2↑│  12,500 │ /... │ │
│  │ □ │ купить квартиру    │ G │   12   │ -3↓│   8,200 │ /... │ │
│  │ □ │ ипотека ставки     │ Я │    3   │  = │  45,000 │ /... │ │
│  │ □ │ новостройки москва │ Я │   25   │+10↑│   6,800 │      │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                   │
│  Showing 1-20 of 500       [← 1 2 3 ... 25 →]     20 per page ▼│
└───────────────────────────────────────────────────────────────────┘
```

**Ключевые UX-решения:**
- Engine badge: `Я` (синий) / `G` (зелёный) — мгновенное визуальное различие
- Позиция + дельта в одной строке с цветом (↑ зелёный, ↓ красный, = серый)
- Частотность Wordstat сразу в таблице — не нужно переходить
- Checkbox для bulk actions (удалить, переместить)
- Клик на строку → переход к деталям ключевика

**Bulk Import Dialog:**
```
┌─ Импорт ключевиков ──────────────────────┐
│                                            │
│  [Перетащите CSV файл или нажмите]        │
│                                            │
│  Кластер: [▼ Выберите кластер]            │
│  Регион:  [▼ Москва]                      │
│  Движок:  [▼ Yandex]                      │
│  Device:  [▼ Desktop]                      │
│                                            │
│  [Отмена]                    [Импортировать]│
└────────────────────────────────────────────┘
```

### Screen 3: Keyword Detail (/projects/:id/keywords/:keywordId)

**Цель:** Полная информация по одному ключевику.

```
┌─ ← Ключевики / "купить квартиру москва" ────────────────────┐
│                                                                │
│  [SERP] [История] [Wordstat] [Подсказки]                      │
│                                                                │
│  ─── Tab: SERP ──────────────────────────────────────────── │
│                                                                │
│  Дата: [📅 2026-03-26]    TOP: [▼ 20]                        │
│                                                                │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │ # │ Домен          │ Тип       │ Title         │ URL    │ │
│  ├───┼────────────────┼───────────┼───────────────┼────────┤ │
│  │ 1 │ cian.ru        │🟣 Агрегат │ Купить кварт..│ /...   │ │
│  │ 2 │ avito.ru       │🟣 Маркетп │ Квартиры в...│ /...   │ │
│  │ 3 │ domclick.ru    │🔵 Сервис  │ Ипотека и... │ /...   │ │
│  │ 7 │ ██client-a.ru██│🔵 Магазин │ Квартиры от..│ /...   │ │◄── подсветка
│  └──────────────────────────────────────────────────────────┘ │
│                                                                │
│  ─── Tab: История ──────────────────────────────────────── │
│  [Line chart: позиция (inverted Y-axis) за 30 дней]          │
│                                                                │
│  ─── Tab: Wordstat ─────────────────────────────────────── │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐                     │
│  │ Точная   │ │ Фразовая │ │ Широкая  │                     │
│  │  3,200   │ │   8,500  │ │  12,500  │                     │
│  └──────────┘ └──────────┘ └──────────┘                     │
│  [Bar chart: помесячная динамика (сезонность)]                │
│                                                                │
│  ─── Tab: Подсказки ────────────────────────────────────── │
│  │ Подсказка                        │ Частотность │ Тип     │ │
│  │ купить квартиру в москве недорого│     2,100   │ suggest │ │
│  │ купить квартиру в новостройке    │     1,800   │ suggest │ │
└────────────────────────────────────────────────────────────────┘
```

**Ключевые UX-решения:**
- Tabs (не pages) — быстрое переключение без потери контекста
- «Свой» домен подсвечивается фоном (bg-primary/10 + font-bold)
- Тип сайта — цветной Badge компонент
- История позиций — inverted Y-axis (1 вверху, 100 внизу)
- Wordstat — 3 карточки + график сезонности

### Screen 4: Competitors (/projects/:id/competitors)

```
┌─ Конкуренты ────────────────────────────────────────────────┐
│                                                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ Домен          │ Тип      │TOP-3│TOP-10│TOP-20│ Тренд  ││
│  ├────────────────┼──────────┼─────┼──────┼──────┼────────┤│
│  │ ██client-a.ru██│ Магазин  │  12 │   45 │   78 │  ↑ +5  ││
│  │ cian.ru        │ Агрегат  │  34 │   89 │  120 │  ↓ -3  ││
│  │ avito.ru       │ Маркетпл │  28 │   67 │  110 │  = 0   ││
│  └─────────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────┘
```

### Screen 5: Classification (/classification)

**Rules tab:** CRUD таблица правил с type, pattern, site_type, priority.
**Domains tab:** Таблица доменов с текущей классификацией, фильтр по типу, кнопка «Изменить тип» (ручная корректировка).

### Screen 6: Scrapers & Schedules

**Scrapers:** CRUD карточки с name, type, status badge (active/inactive), кнопка «Test» (health check).
**Schedules:** CRUD таблица с cascade selector (project → category → cluster → keyword), frequency, last/next run, toggle active.

### Screen 7: Settings (/settings)

**Organization tab:** name, slug.
**Members tab:** таблица участников с role dropdown, кнопка «Invite» → email input dialog.

## Component Strategy

### Custom Components (не из shadcn/ui)

| Компонент | Назначение | Приоритет |
|---|---|---|
| `PositionBadge` | Позиция + дельта с цветом (↑↓=) | MVP |
| `EngineBadge` | Бейдж Я/G | MVP |
| `SiteTypeBadge` | Цветной бейдж типа сайта | MVP |
| `SummaryCard` | Карточка дашборда (число + дельта) | MVP |
| `PositionChart` | Line chart позиции (inverted Y) | MVP |
| `TrendChart` | Bar chart Wordstat сезонности | MVP |
| `FrequencyCards` | 3 карточки частотности | MVP |
| `CsvImportDialog` | Drag & drop CSV + селекторы | MVP |
| `FilterBar` | Горизонтальная панель фильтров | MVP |
| `OwnDomainHighlight` | Row highlight для is_own доменов | MVP |

### Component Architecture

```
components/
├── ui/                    # shadcn/ui (копируемые)
│   ├── button.tsx
│   ├── table.tsx
│   ├── badge.tsx
│   └── ...
├── layout/
│   ├── Sidebar.tsx
│   ├── Topbar.tsx
│   ├── Breadcrumbs.tsx
│   └── PageHeader.tsx
├── keywords/
│   ├── KeywordsTable.tsx      # TanStack Table wrapper
│   ├── KeywordFilters.tsx     # FilterBar instance
│   ├── PositionBadge.tsx
│   ├── EngineBadge.tsx
│   └── CsvImportDialog.tsx
├── serp/
│   ├── SerpTable.tsx
│   ├── SiteTypeBadge.tsx
│   └── OwnDomainHighlight.tsx
├── wordstat/
│   ├── FrequencyCards.tsx
│   ├── TrendChart.tsx
│   └── SuggestionsTable.tsx
├── dashboard/
│   ├── SummaryCards.tsx
│   ├── PositionChangeSummary.tsx
│   └── VisibilityChart.tsx
└── shared/
    ├── PositionChart.tsx      # Recharts line chart
    ├── DataExportButton.tsx
    └── EmptyState.tsx
```

## UX Patterns

### Data Table Pattern

Все таблицы данных следуют единому паттерну:

1. **FilterBar** сверху — горизонтальные Select/Input фильтры
2. **Table** с server-side pagination (TanStack Table)
3. **Pagination** снизу — номера страниц + per_page selector
4. **Loading:** Skeleton rows (не spinner)
5. **Empty:** EmptyState компонент с CTA
6. **Error:** Toast notification + retry button

### Form Pattern

1. Inline validation (FormRequest → 422 → field errors)
2. Submit button disabled while loading
3. Success → Toast + redirect (create) или Toast (update)
4. Destructive actions → AlertDialog confirmation

### Navigation Pattern

1. **Sidebar** — primary navigation (pages)
2. **Breadcrumbs** — location context
3. **Tabs** — sub-views внутри страницы (keyword detail)
4. **Back arrow** — return to parent (keyword → keywords list)

### Loading Pattern

1. **Initial load:** Full skeleton (table rows, cards)
2. **Refetch:** Subtle opacity reduction + spinner in corner
3. **Mutation:** Button loading state + disabled
4. **Background:** No indicator (TanStack Query background refetch)

### Error Pattern

1. **Validation (422):** Inline field errors (red text under input)
2. **Server (500):** Toast with "Ошибка сервера, попробуйте позже"
3. **Network:** Toast with "Нет подключения к серверу"
4. **Auth (401):** Redirect to /login
5. **Forbidden (403):** Toast with "Недостаточно прав"

## Responsive & Accessibility

### Responsive Strategy

**MVP: Desktop-only (≥1024px)**
- Sidebar: fixed 256px
- Content: fluid max-w-7xl
- Tables: horizontal scroll if needed

**Growth: Tablet (≥768px)**
- Sidebar: collapsible overlay
- Tables: responsive column hiding
- Cards: 2-column grid

**Vision: Mobile (≥375px)**
- Sidebar: drawer
- Tables: card-based layout
- Bottom navigation

### Accessibility (MVP minimum)

- Semantic HTML: `<table>`, `<nav>`, `<main>`, `<header>`
- ARIA labels on icon-only buttons
- Keyboard navigation: Tab through interactive elements
- Focus visible: ring-2 ring-primary
- Color contrast: WCAG AA (4.5:1 for text)
- Screen reader: meaningful alt text, aria-label on badges

### Performance Targets

- First Contentful Paint: < 1.5s
- Time to Interactive: < 3s
- Bundle size: < 500KB gzipped
- Table render (100 rows): < 200ms
- Chart render: < 500ms
