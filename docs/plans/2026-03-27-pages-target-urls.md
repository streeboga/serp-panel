# PRD: Pages — реестр целевых и конкурентных страниц

**Дата:** 2026-03-27
**Статус:** Draft
**Автор:** K.mazurov + BMAD team

---

## 1. Проблема

SEO-специалист не может:
- Назначить целевую страницу для ключевого слова и отслеживать, совпадает ли она с фактической в SERP
- Размечать конкурентов прямо из поисковой выдачи (тип сайта, тип страницы, теги)
- Видеть агрегированную картину: доля видимости, каннибализация, динамика конкурентов

Сейчас `our_url` — вычисляемое поле из последнего SERP-снапшота. Нет способа указать "какой URL мы ХОТИМ видеть в выдаче".

## 2. Решение

Единый реестр страниц (`pages`) с:
- Полиморфной привязкой к keywords/clusters/categories
- Тегами через `spatie/laravel-tags`
- Автоматическим матчингом с SERP-результатами
- Каскадным наследованием целевых страниц (keyword → cluster → category)

## 3. Модель данных

### 3.1 Таблица `pages`

```sql
CREATE TABLE pages (
    id BIGSERIAL PRIMARY KEY,
    project_id BIGINT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    domain_id BIGINT NULL REFERENCES domains(id) ON DELETE SET NULL,
    url VARCHAR(2048) NOT NULL,
    path VARCHAR(2048) NOT NULL,           -- нормализованный path для матчинга
    title VARCHAR(512) NULL,
    page_type VARCHAR(32) NULL,            -- enum: commercial, informational, navigational, transactional
    notes TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    UNIQUE(project_id, url),
    INDEX(project_id, path),
    INDEX(project_id, domain_id)
);
```

**page_type enum:** `PageType` — `commercial`, `informational`, `navigational`, `transactional`.

### 3.2 Таблица `pageables` (полиморфная привязка)

```sql
CREATE TABLE pageables (
    id BIGSERIAL PRIMARY KEY,
    page_id BIGINT NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    pageable_type VARCHAR(255) NOT NULL,    -- App\Models\Keyword / Cluster / Category
    pageable_id BIGINT NOT NULL,
    engine VARCHAR(16) NULL,                -- null = все движки
    device VARCHAR(16) NULL,                -- null = все устройства
    priority INT NOT NULL DEFAULT 0,        -- 0 = основная, 1+ = альтернативы
    is_target BOOLEAN NOT NULL DEFAULT true, -- true = целевая, false = конкурент для отслеживания
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX(pageable_type, pageable_id),
    INDEX(page_id)
);
```

### 3.3 Таблица `page_serp_matches` (денормализованный матчинг)

```sql
CREATE TABLE page_serp_matches (
    id BIGSERIAL PRIMARY KEY,
    page_id BIGINT NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    serp_result_id BIGINT NOT NULL REFERENCES serp_results(id) ON DELETE CASCADE,
    snapshot_id BIGINT NOT NULL REFERENCES serp_snapshots(id) ON DELETE CASCADE,
    keyword_id BIGINT NOT NULL REFERENCES keywords(id) ON DELETE CASCADE,
    position INT NOT NULL,
    collected_at DATE NOT NULL,
    created_at TIMESTAMP,

    UNIQUE(page_id, serp_result_id),
    INDEX(page_id, keyword_id, collected_at),
    INDEX(snapshot_id)
);
```

Заполняется автоматически listener-ом после `SerpSnapshotCollected`.

### 3.4 Теги (spatie/laravel-tags)

Page модель использует `HasTags` trait. Теги scoped по `project_id` через кастомный Tag model.

## 4. Архитектура (Laravel API)

### 4.1 Backend слои

```
Controller (thin) → Service → Repository → QueryBuilder → Model
```

| Сущность | Controller | Service | Repository | QueryBuilder | Resource |
|----------|-----------|---------|------------|-------------|----------|
| Page | PageController | PageService | PageRepository | PageQueryBuilder | PageResource |
| Pageable | PageableController | PageService | PageableRepository | — | PageableResource |
| PageSerpMatch | — | PageMatchService | PageSerpMatchRepository | — | — |

### 4.2 Ключевые сервисы

**PageService** — CRUD pages, attach/detach к entities
**PageMatchService** — матчинг SERP → pages, вызывается из listener

### 4.3 Event flow

```
SerpSnapshotCollected
  → CheckPositionAlertsListener (existing)
  → MatchPagesFromSerpListener (NEW)
      → PageMatchService::matchSnapshot(snapshot)
          → load project pages by domain
          → for each serp_result: match by normalized path
          → upsert page_serp_matches
```

### 4.4 API endpoints

```
# Pages CRUD
GET    /api/v1/projects/{project}/pages           — список с фильтрами, тегами
POST   /api/v1/projects/{project}/pages           — создать
GET    /api/v1/pages/{page}                        — детали
PATCH  /api/v1/pages/{page}                        — обновить
DELETE /api/v1/pages/{page}                        — удалить

# Теги
PATCH  /api/v1/pages/{page}/tags                   — sync tags

# Привязка (полиморфная)
POST   /api/v1/pages/{page}/attach                 — привязать к keyword/cluster/category
DELETE /api/v1/pageables/{pageable}                 — отвязать
POST   /api/v1/pages/{page}/bulk-attach             — массовая привязка

# Быстрая разметка из SERP
POST   /api/v1/pages/match-or-create               — найти page по URL или создать + привязать

# Отчёты
GET    /api/v1/projects/{project}/pages/target-report — Target vs Actual сводка
```

### 4.5 Каскадное наследование

На модели Keyword:

```php
public function getEffectiveTargetPagesAttribute(): Collection
{
    $own = $this->targetPages; // через pageables где is_target=true
    if ($own->isNotEmpty()) return $own;

    $cluster = $this->cluster->targetPages;
    if ($cluster->isNotEmpty()) return $cluster;

    return $this->cluster->category->targetPages;
}
```

### 4.6 Нормализация URL для матчинга

```php
function normalizePath(string $url): string
{
    $parsed = parse_url($url);
    $path = rtrim($parsed['path'] ?? '/', '/');
    return mb_strtolower($path);
}
```

При сравнении: `pages.path == normalizePath(serp_result.url)`.
Если page.url без домена (`/budget/family`) — при матчинге сравниваем только path.

## 5. Фронтенд (React 19 + TanStack)

### 5.1 Новые страницы

| Route | Компонент | Назначение |
|-------|----------|------------|
| `/projects/{id}/pages` | PagesPage | CRUD таблица всех pages проекта |
| `/projects/{id}/pages/{pageId}` | PageDetailPage | Детали page + привязки + позиции |

### 5.2 Изменения в существующих страницах

**Keywords table (`keywords.lazy.tsx`):**
- Новый столбец "Цель" — `effective_target_url` (path) + индикатор совпадения
- Цвета: зелёный (целевая в ТОП-3), жёлтый (в ТОП-10 но другая), красный (не в ТОП-100), серый (не назначена)

**Keyword detail → SERP tab:**
- Каждая строка обогащена: бейдж page_type, теги, иконка наш/конкурент/неразмеченный
- Кнопка быстрой разметки (🏷️) — клик → попап создания/привязки page

**Categories/Clusters pages:**
- Показываем привязанные целевые страницы в карточке
- Inline-редактирование target URL

### 5.3 React паттерны (Vercel best practices)

- `async-parallel` — загрузка pages + keywords + serp параллельно через `Promise.all` в loader
- `bundle-dynamic-imports` — `React.lazy` для PagesPage и PageDetailPage (не в основном бандле)
- `rerender-memo` — мемоизация SerpRow с page-обогащением (тяжёлый матчинг)
- `rerender-derived-state` — `useMemo` для матчинга pages↔serp_results
- `js-index-maps` — `Map<string, Page>` по path для O(1) lookup при обогащении SERP
- `rendering-conditional-render` — тернарные операторы для индикаторов совпадения

### 5.4 Новые hooks

```typescript
// hooks/usePages.ts
usePages(projectId)                    — список pages проекта
usePage(pageId)                        — детали page
useCreatePage()                        — создание
useUpdatePage()                        — обновление
useDeletePage()                        — удаление
useAttachPage()                        — привязка к entity
useDetachPageable()                    — отвязка
useBulkAttachPage()                    — массовая привязка
useMatchOrCreatePage()                 — быстрая разметка из SERP
useTargetReport(projectId)             — отчёт Target vs Actual
```

### 5.5 TypeScript интерфейсы

```typescript
interface Page {
  id: number
  project_id: number
  domain_id: number | null
  domain?: Domain
  url: string
  path: string
  title: string | null
  page_type: 'commercial' | 'informational' | 'navigational' | 'transactional' | null
  notes: string | null
  tags: Tag[]
}

interface Pageable {
  id: number
  page_id: number
  page?: Page
  pageable_type: string
  pageable_id: number
  engine: 'google' | 'yandex' | null
  device: 'desktop' | 'mobile' | null
  priority: number
  is_target: boolean
}

interface TargetReportRow {
  keyword_id: number
  keyword: string
  engine: string
  device: string
  target_url: string | null
  target_source: 'keyword' | 'cluster' | 'category' | null
  actual_url: string | null
  actual_position: number | null
  match: boolean
}
```

## 6. Индикаторы совпадения

| Состояние | Цвет | Иконка | Описание |
|-----------|------|--------|----------|
| Целевая в ТОП-3 | Зелёный | ● | target_url совпадает с our_url, позиция 1-3 |
| Целевая в ТОП-10 | Зелёный светлый | ○ | target_url совпадает, позиция 4-10 |
| Каннибализация | Жёлтый | ⚠ | В SERP есть наш URL, но не целевой |
| Не в ТОП-100 | Красный | ✕ | target_url назначен, но не найден в SERP |
| Не назначен | Серый | — | target_url не задан |

## 7. Быстрая разметка из SERP

Пользователь открывает SERP → видит строку конкурента → кликает 🏷️:

1. Попап с предзаполненными данными (URL, domain из SERP-строки)
2. Поиск: "Уже есть page с таким URL?" → показать → привязать
3. Если нет — создать новый page: URL, title (из SERP), page_type, теги
4. Автоматически привязать к текущему keyword как `is_target=false`

API: `POST /api/v1/pages/match-or-create`

```json
{
  "url": "https://gazprombank.ru/...",
  "title": "Виды семейного бюджета",
  "page_type": "informational",
  "tags": ["прямой конкурент"],
  "attach_to": { "type": "keyword", "id": 39, "is_target": false }
}
```

---

## 8. Эпики

### Epic 1: Backend — модели, миграции, CRUD (бэкенд-фундамент)

**Stories:**
1. Установить `spatie/laravel-tags`, настроить Tag model scoped по project
2. Создать миграцию `create_pages_table`
3. Создать миграцию `create_pageables_table`
4. Создать миграцию `create_page_serp_matches_table`
5. Создать модели: `Page`, `Pageable`, `PageSerpMatch` + enum `PageType`
6. Создать `PageRepositoryInterface` + `PageRepository` + `PageQueryBuilder`
7. Создать `PageableRepository`
8. Создать `PageSerpMatchRepository`
9. Создать `PageService` — CRUD, attach/detach, bulk-attach, match-or-create
10. Создать `PageMatchService` — матчинг SERP → pages по normalized path
11. Создать `MatchPagesFromSerpListener` на event `SerpSnapshotCollected`
12. Создать `PageResource`, `PageableResource`
13. Создать `PageController` — index, store, show, update, destroy, tags, attach, bulkAttach, matchOrCreate
14. Добавить routes в `api.php`
15. Добавить `effective_target_pages` accessor на Keyword model
16. Обновить `KeywordResource` — добавить `effective_target_url`, `target_url_source`, `target_match_status`

### Epic 2: Frontend — страница Pages + CRUD

**Stories:**
1. Создать hooks: `usePages`, `usePage`, `useCreatePage`, `useUpdatePage`, `useDeletePage`
2. Создать route `/projects/{id}/pages` + компонент `PagesPage`
3. Таблица pages: URL, domain, page_type badge, tags, привязки count
4. Фильтры: по домену, page_type, тегам (spatie tags)
5. Диалог создания page: URL, title, page_type, domain (select), tags
6. Диалог редактирования page
7. Добавить ссылку "Страницы" в project navigation tabs

### Epic 3: Frontend — привязка pages к keywords/clusters/categories

**Stories:**
1. Создать hooks: `useAttachPage`, `useDetachPageable`, `useBulkAttachPage`
2. Keyword detail — секция "Целевые страницы" с привязкой/отвязкой
3. Cluster card — показать привязанные целевые pages
4. Category card — показать привязанные целевые pages
5. Keywords table — массовое назначение: выбрать ключи → назначить target page
6. Каскадное отображение: показать source (keyword/cluster/category) рядом с target URL

### Epic 4: Frontend — обогащение SERP + быстрая разметка

**Stories:**
1. Создать hook `useMatchOrCreatePage`
2. SERP tab — обогатить строки: page_type badge, теги, иконка наш/конкурент/неразмеченный
3. SERP tab — кнопка быстрой разметки 🏷️ → попап match-or-create
4. Попап разметки: предзаполнение из SERP, поиск существующего page, создание нового

### Epic 5: Frontend — индикаторы и отчёт Target vs Actual

**Stories:**
1. Keywords table — столбец "Цель" с цветовыми индикаторами совпадения
2. Создать hook `useTargetReport`
3. Backend endpoint `GET /projects/{project}/pages/target-report`
4. Страница/секция отчёта Target vs Actual: таблица keyword → target → actual → position → match
5. Keyword detail header — строка "Целевая: /path (из кластера)" + "Фактическая: /other" с индикацией

### Epic 6: Автоматический матчинг SERP → Pages

**Stories:**
1. `PageMatchService::matchSnapshot()` — логика матчинга по normalized path
2. `MatchPagesFromSerpListener` — вызов при `SerpSnapshotCollected`
3. Перематчинг при создании/обновлении page (backfill существующих снапшотов)
4. Тесты: listener fired, matches created, edge cases (www vs non-www, trailing slash)

---

## 9. Приоритеты реализации

| Порядок | Epic | Зависимости |
|---------|------|-------------|
| 1 | Epic 1 (Backend) | — |
| 2 | Epic 6 (Матчинг) | Epic 1 |
| 3 | Epic 2 (Pages CRUD) | Epic 1 |
| 4 | Epic 3 (Привязки) | Epic 1, 2 |
| 5 | Epic 4 (SERP обогащение) | Epic 1, 6 |
| 6 | Epic 5 (Индикаторы + отчёт) | Epic 1, 3, 6 |

---

## 10. V2 (будущее)

- Каннибализация detection — алерт когда 2+ наших URL в одной выдаче
- История позиций конкурентов — timeline позиций для размеченных pages
- Visibility share — % ТОП-10 наши vs конкуренты по кластеру/категории
- Bulk import pages из sitemap.xml
- Auto-suggest target URL по релевантности контента
