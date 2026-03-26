# ТЗ: Полная переработка Frontend SERP Panel

> Дата: 2026-03-26
> Статус: Draft
> Приоритет: Critical

---

## 1. Резюме проблем

### Текущее состояние
Frontend написан на React 19 + TanStack Router/Query + shadcn/ui + Tailwind CSS 4. Базовая структура есть, но **~30% функционала отсутствует**, а существующий код содержит критические баги и расхождения с API.

### Критические баги (P0)
| # | Проблема | Где | Причина |
|---|---------|-----|---------|
| 1 | **Settings крашится** | `/settings` | `Cannot read properties of undefined (reading 'role')` — `/auth/me` возвращает `role: null` т.к. pivot не загружен |
| 2 | **Неправильные HTTP-методы** | Все update-хуки | Используется `PUT` вместо `PATCH` (JSON:API spec) |
| 3 | **Неправильные эндпоинты** | Classification, Schedules | `/classification-rules` → `/classification/rules`, `/run` → `/run-now` |

### Отсутствующие страницы (P1)
| # | Страница | API готов? | Описание |
|---|---------|-----------|----------|
| 1 | **Alerts (Оповещения)** | Да | CRUD оповещений о позициях |
| 2 | **Wordstat Schedules** | Да | Расписания сбора частотности |
| 3 | **Billing/Usage** | Да | Использование лимитов тарифа |
| 4 | **Categories** | Да | Иерархические категории для доменов |
| 5 | **Clusters management** | Да | CRUD кластеров внутри категорий |
| 6 | **Organization switcher** | Да | Переключение между организациями |

### Неполный функционал (P2)
| # | Что | Проблема |
|---|-----|---------|
| 1 | Dashboard | Нет графиков, нет position changes, нет фильтра "Все проекты" |
| 2 | SERP History | Нет графика позиций по дням — только таблица |
| 3 | Wordstat Trends | Нет графика трендов — только таблица |
| 4 | Keywords | Нет bulk delete, нет редактирования, нет фильтра по кластеру/категории |
| 5 | Competitors | Нет поиска, нет фильтрации по site type |
| 6 | Classification | Нет редактирования правил, нет ручной переклассификации |
| 7 | Scrapers | Нет редактирования, нет toggle active/inactive |
| 8 | Schedules | Нет редактирования, нет pause/resume |

---

## 2. Полная карта расхождений Frontend → API

### 2.1. HTTP-методы (все update → PATCH)

| Хук | Файл | Сейчас | Должно быть |
|-----|------|--------|-------------|
| `useUpdateProject` | `useProjects.ts:43` | `api.put(...)` | `api.patch(...)` |
| `useUpdateDomain` | `useDomains.ts:36` | `api.put(...)` | `api.patch(...)` |
| `useUpdateClassificationRule` | `useClassification.ts:49` | `api.put(...)` | `api.patch(...)` |
| `useUpdateScraper` | `useScrapers.ts:43` | `api.put(...)` | `api.patch(...)` |
| `useUpdateSchedule` | `useSchedules.ts:37` | `api.put(...)` | `api.patch(...)` |
| `useUpdateMemberRole` | `useOrganization.ts:46` | `api.put(...)` | `api.patch(...)` |

### 2.2. Неправильные эндпоинты

| Хук | Сейчас | Должно быть |
|-----|--------|-------------|
| `useClassificationRules` | `GET /classification-rules` | `GET /classification/rules` |
| `useCreateClassificationRule` | `POST /classification-rules` | `POST /classification/rules` |
| `useUpdateClassificationRule` | `PUT /classification-rules/{id}` | `PATCH /classification/rules/{id}` |
| `useDeleteClassificationRule` | `DELETE /classification-rules/{id}` | `DELETE /classification/rules/{id}` |
| `useDomainClassifications` | `GET /domain-classifications` | `GET /classification/domains` — **нужна проверка** |
| `useRunSchedule` | `POST /schedules/{id}/run` | `POST /schedules/{id}/run-now` |
| `useInviteMember` | `POST /organization/invite` | `POST /organization/invite` — **верно** |
| `useDeleteKeyword` | `DELETE /projects/{pid}/keywords/{kid}` | `DELETE /keywords/bulk` (bulk endpoint) |

### 2.3. Отсутствующие API-вызовы (есть в backend, нет во frontend)

```
# Организация
GET    /api/v1/organizations              — список организаций пользователя
PATCH  /api/v1/organization               — обновление текущей организации

# Категории
GET    /api/v1/domains/{domain}/categories
POST   /api/v1/categories
GET    /api/v1/categories/{category}
PATCH  /api/v1/categories/{category}
DELETE /api/v1/categories/{category}

# Кластеры (управление)
POST   /api/v1/clusters
PATCH  /api/v1/clusters/{cluster}
DELETE /api/v1/clusters/{cluster}

# Ключевые слова
POST   /api/v1/keywords/bulk              — bulk create (отличается от import)
PATCH  /api/v1/keywords/{keyword}          — обновление отдельного ключевика
DELETE /api/v1/keywords/bulk               — bulk delete

# Алерты
GET    /api/v1/alerts
POST   /api/v1/alerts
GET    /api/v1/alerts/{alert}
PATCH  /api/v1/alerts/{alert}
DELETE /api/v1/alerts/{alert}

# Wordstat расписания
GET    /api/v1/wordstat-schedules
POST   /api/v1/wordstat-schedules
GET    /api/v1/wordstat-schedules/{id}
PATCH  /api/v1/wordstat-schedules/{id}
DELETE /api/v1/wordstat-schedules/{id}
POST   /api/v1/wordstat-schedules/{id}/run-now

# Биллинг
GET    /api/v1/billing/usage
PATCH  /api/v1/billing/tier

# Экспорт
GET    /api/v1/export/keywords
GET    /api/v1/export/serp

# Классификация (ручная)
PATCH  /api/v1/domains/{domain}/classify

# Профиль
PATCH  /api/v1/auth/profile
```

---

## 3. Корневая причина краша Settings

**Файл:** `frontend/src/contexts/AuthContext.tsx`

**Проблема:** Эндпоинт `/auth/me` возвращает user с `role: null` потому что:
1. Backend делает `$user->load('organizations')` — загружает организации
2. UserResource использует `whenPivotLoaded('organization_user', fn() => $this->pivot->role)`
3. Но user загружен не через pivot, а напрямую — pivot не доступен
4. Frontend в `parseUserFromApi()` получает `role: null`
5. Settings page пытается рендерить `user.role` → crash

**Решение:**
- **Backend fix:** В AuthController::me() вернуть роль через организации: `organizations[0].pivot.role`
- **Frontend fix:** Парсить роль из `relationships.organizations[0].attributes.role` или из pivot данных

---

## 4. План работ (TDD подход)

### Фаза 0: Инфраструктура тестирования (1 день)

#### 0.1. Настройка тестовой среды
- [ ] Настроить Vitest + MSW (Mock Service Worker) для unit/integration тестов
- [ ] Настроить Playwright для E2E тестов с реальным backend
- [ ] Создать фабрики тестовых данных (factories) для всех сущностей
- [ ] Создать helpers: `renderWithProviders()`, `mockApi()`, `createAuthState()`

#### 0.2. Database seeder для E2E
- [ ] Создать `TestSeeder` с полным набором данных:
  - Организация + пользователи (admin, manager, analyst, viewer)
  - Проект + домены (свой + 3 конкурента)
  - Категории (3 уровня) + кластеры + ключевики (50+)
  - SERP данные за 30 дней
  - Wordstat данные
  - Classification rules + domain classifications
  - Scraper + schedules

---

### Фаза 1: Критические баги (P0) — 2 дня

#### 1.1. Fix AuthContext + Settings crash
**TDD:**
```
test: "parseUserFromApi extracts role from organizations pivot"
test: "Settings page renders without crash when role is available"
test: "Settings page handles missing role gracefully"
```
**Файлы:**
- `frontend/src/contexts/AuthContext.tsx` — исправить parseUserFromApi
- `backend: app/Http/Controllers/Api/V1/AuthController.php` — добавить role в ответ
- `frontend/src/routes/settings/index.tsx` — defensive coding

#### 1.2. Fix HTTP-методы (PUT → PATCH)
**TDD:**
```
test: "useUpdateProject sends PATCH request"
test: "useUpdateDomain sends PATCH request"
test: "useUpdateClassificationRule sends PATCH request"
test: "useUpdateScraper sends PATCH request"
test: "useUpdateSchedule sends PATCH request"
test: "useUpdateMemberRole sends PATCH request"
```
**Файлы:** Все хуки в `frontend/src/hooks/`

#### 1.3. Fix эндпоинты
**TDD:**
```
test: "useClassificationRules calls /classification/rules"
test: "useRunSchedule calls /schedules/{id}/run-now"
test: "useDeleteKeyword calls /keywords/bulk with keyword ids"
```
**Файлы:** `useClassification.ts`, `useSchedules.ts`, `useKeywords.ts`

---

### Фаза 2: API-слой — новые хуки (2 дня)

#### 2.1. Хуки для отсутствующих ресурсов
**TDD:** Для каждого хука — тест на правильный endpoint, метод, payload.

| Новый файл | Хуки |
|-----------|------|
| `useAlerts.ts` | `useAlerts`, `useAlert`, `useCreateAlert`, `useUpdateAlert`, `useDeleteAlert` |
| `useWordstatSchedules.ts` | `useWordstatSchedules`, `useCreateWordstatSchedule`, `useUpdateWordstatSchedule`, `useDeleteWordstatSchedule`, `useRunWordstatSchedule` |
| `useBilling.ts` | `useBillingUsage`, `useUpdateBillingTier` |
| `useCategories.ts` | `useCategories`, `useCreateCategory`, `useUpdateCategory`, `useDeleteCategory` |
| `useClusters.ts` (расширить) | `useCreateCluster`, `useUpdateCluster`, `useDeleteCluster` |
| `useOrganizations.ts` (расширить) | `useOrganizations`, `useUpdateOrganization`, `useSwitchOrganization` |
| `useProfile.ts` | `useUpdateProfile` |

#### 2.2. Исправление существующих хуков
| Файл | Изменения |
|------|-----------|
| `useKeywords.ts` | Добавить `useUpdateKeyword`, `useBulkDeleteKeywords`, `useBulkCreateKeywords` |
| `useClassification.ts` | Fix endpoints, добавить `useClassifyDomain` |
| `useSchedules.ts` | Fix run-now, добавить edit |
| `useScrapers.ts` | Добавить edit |

#### 2.3. Типы
**Файл:** `frontend/src/types/api.ts`

Добавить типы:
```typescript
interface Alert { id: number; organization_id: number; keyword_id: number; threshold_position: number; direction: 'drops_below' | 'rises_above'; channel: 'email' | 'telegram'; recipient: string; is_active: boolean; last_triggered_at: string | null; }
interface WordstatSchedule { id: number; project_id: number; cluster_id?: number; keyword_id?: number; frequency_days: number; collect_trends: boolean; collect_suggestions: boolean; regions: number[]; last_run_at: string | null; next_run_at: string | null; is_active: boolean; }
interface BillingUsage { keywords_used: number; keywords_limit: number; projects_used: number; projects_limit: number; scrapers_used: number; scrapers_limit: number; tier: string; }
interface Category { id: number; domain_id: number; name: string; parent_id: number | null; sort_order: number; children?: Category[]; }
```

---

### Фаза 3: Переработка существующих страниц (3 дня)

#### 3.1. Dashboard — полная переработка
**TDD:**
```
test: "Dashboard renders project selector with 'All projects' option"
test: "Dashboard shows position change cards (improved/declined/stable)"
test: "Dashboard renders visibility chart"
test: "Dashboard shows TOP distribution chart"
```
**Задачи:**
- [ ] Добавить библиотеку графиков (Recharts — легковесный, React-совместимый)
- [ ] Фильтр "Все проекты" / конкретный проект
- [ ] Карточки: TOP-3/10/20/100 с дельтами (↑↓) и цветами (зелёный/красный)
- [ ] Карточки: Improved / Declined / Stable keywords
- [ ] График: Visibility score за 30 дней
- [ ] График: Распределение по TOP-зонам (stacked bar)

#### 3.2. Settings — переделка с org management
**TDD:**
```
test: "Settings renders organization info with edit"
test: "Settings shows org switcher when user has multiple orgs"
test: "Settings members table shows roles correctly"
test: "Settings invite form validates email and role"
test: "Settings billing section shows usage vs limits"
```
**Задачи:**
- [ ] Org info + edit (name)
- [ ] Org switcher (если >1 организация)
- [ ] Members table с RBAC
- [ ] Billing/Usage section
- [ ] Profile editing (name, email, password)
- [ ] Danger zone: удаление аккаунта

#### 3.3. Keywords — расширение
**TDD:**
```
test: "Keywords table shows checkbox for bulk select"
test: "Keywords bulk delete works"
test: "Keywords edit dialog opens and saves"
test: "Keywords filter by cluster works"
test: "Keywords filter by category works"
```
**Задачи:**
- [ ] Bulk select + bulk delete
- [ ] Inline edit keyword
- [ ] Фильтр по кластеру/категории
- [ ] Улучшенный import с drag-n-drop

#### 3.4. Keyword Detail — графики
**TDD:**
```
test: "SERP History tab renders position chart"
test: "Wordstat tab renders trends chart"
test: "Charts are interactive (hover tooltip)"
```
**Задачи:**
- [ ] SERP History: line chart позиции по дням (ось Y инвертирована — 1 вверху)
- [ ] Wordstat Trends: bar chart помесячной частотности
- [ ] Tooltips при hover

#### 3.5. Classification — полный CRUD
**Задачи:**
- [ ] Edit rule dialog
- [ ] Manual classify domain button
- [ ] Фильтрация по site type
- [ ] Поиск по домену

#### 3.6. Scrapers + Schedules — полный CRUD
**Задачи:**
- [ ] Edit scraper dialog
- [ ] Edit schedule dialog
- [ ] Active/inactive toggle
- [ ] Entity selector для schedules (вместо raw ID)

---

### Фаза 4: Новые страницы (3 дня)

#### 4.1. Categories + Clusters management
**TDD:**
```
test: "Categories page renders tree structure"
test: "Create category with parent"
test: "Create cluster inside category"
test: "Delete category cascades warning"
```
**Маршрут:** `/projects/$projectId/categories`
**Задачи:**
- [ ] Tree view категорий с вложенностью
- [ ] CRUD категорий (с parent selector)
- [ ] CRUD кластеров внутри категорий
- [ ] Drag-n-drop переупорядочивание (nice to have)

#### 4.2. Alerts (Оповещения)
**TDD:**
```
test: "Alerts page lists all alerts"
test: "Create alert with keyword selector"
test: "Alert shows threshold and channel"
test: "Edit alert updates correctly"
test: "Delete alert removes from list"
```
**Маршрут:** `/alerts`
**Задачи:**
- [ ] Список алертов с фильтром по scope
- [ ] Create dialog: keyword/cluster/project selector, threshold, direction, channel
- [ ] Edit + Delete
- [ ] Active/inactive toggle

#### 4.3. Wordstat Schedules
**TDD:**
```
test: "Wordstat schedules page lists schedules"
test: "Create wordstat schedule with regions"
test: "Run now triggers collection"
```
**Маршрут:** `/wordstat-schedules`
**Задачи:**
- [ ] Список расписаний с проект/кластер/keyword scope
- [ ] Create dialog: scope selector, frequency, regions, flags (trends, suggestions)
- [ ] Edit + Delete
- [ ] Run now

#### 4.4. Billing/Usage
**TDD:**
```
test: "Billing page shows current tier"
test: "Billing page shows usage bars"
test: "Admin can change tier"
```
**Маршрут:** Раздел в `/settings` или отдельный `/billing`
**Задачи:**
- [ ] Текущий тариф
- [ ] Progress bars: keywords used/limit, projects used/limit, scrapers used/limit
- [ ] Upgrade tier (admin only)

---

### Фаза 5: E2E тесты (2 дня)

#### 5.1. Happy path flows
```
test: "Full user journey: register → create project → add domain → import keywords → view SERP"
test: "Organization flow: invite member → change role → remove member"
test: "Scraper flow: create scraper → test → create schedule → run now"
test: "Classification flow: create rule → view domain classifications"
test: "Alert flow: create alert → edit → delete"
```

#### 5.2. Error handling flows
```
test: "Login with wrong credentials shows error"
test: "Unauthorized access redirects to login"
test: "API errors display user-friendly messages"
test: "Network offline shows error state"
```

#### 5.3. Visual regression
```
test: "Dashboard matches design spec"
test: "All pages render correctly in dark mode"
test: "Responsive layout works on mobile viewport"
```

---

### Фаза 6: Ручное тестирование + polish (1 день)

- [ ] Пройти все страницы в браузере
- [ ] Проверить dark mode на всех страницах
- [ ] Проверить i18n (RU/EN) на всех страницах
- [ ] Проверить responsive (мобильная версия)
- [ ] Проверить все CRUD операции end-to-end
- [ ] Проверить edge cases (пустые списки, длинные тексты, спецсимволы)
- [ ] Performance: загрузка bundle < 500KB gzipped

---

## 5. Архитектурные решения

### 5.1. Библиотека графиков
**Выбор: Recharts**
- React-нативный, composable API
- Лёгкий (~40KB gzipped)
- Хорошая поддержка line/bar/area charts
- Встроенные responsive containers и tooltips

### 5.2. Организация кода
```
src/
├── hooks/
│   ├── useAlerts.ts          ← NEW
│   ├── useCategories.ts      ← NEW
│   ├── useBilling.ts         ← NEW
│   ├── useWordstatSchedules.ts ← NEW
│   ├── useProfile.ts         ← NEW
│   ├── useClassification.ts  ← FIX endpoints
│   ├── useDomains.ts         ← FIX PUT→PATCH
│   ├── useKeywords.ts        ← FIX + add bulk ops
│   ├── useOrganization.ts    ← FIX + add switcher
│   ├── useProjects.ts        ← FIX PUT→PATCH
│   ├── useSchedules.ts       ← FIX endpoint + PUT→PATCH
│   └── useScrapers.ts        ← FIX PUT→PATCH
├── routes/
│   ├── alerts/
│   │   └── index.tsx          ← NEW
│   ├── projects/$projectId/
│   │   └── categories.tsx     ← NEW
│   ├── wordstat-schedules/
│   │   └── index.tsx          ← NEW
│   └── billing/
│       └── index.tsx          ← NEW (или в settings)
├── components/
│   ├── charts/
│   │   ├── PositionChart.tsx  ← NEW
│   │   ├── TrendChart.tsx     ← NEW
│   │   └── TopDistributionChart.tsx ← NEW
│   ├── CategoryTree.tsx       ← NEW
│   ├── OrgSwitcher.tsx        ← NEW
│   └── ConfirmDialog.tsx      ← NEW
```

### 5.3. Backend fixes (минимальные)
1. **AuthController::me()** — включить role в ответ (через организации pivot или напрямую)
2. **DashboardController** — добавить position_changes и visibility в summary
3. Всё остальное уже есть на backend

---

## 6. Порядок выполнения (приоритеты)

```
Фаза 0: Тестовая инфраструктура        [День 1]
Фаза 1: P0 баги (crash + методы + endpoints)  [День 2-3]
Фаза 2: Новые хуки + типы              [День 4-5]
Фаза 3: Переработка существующих страниц [День 6-8]
Фаза 4: Новые страницы                 [День 9-11]
Фаза 5: E2E тесты                      [День 12-13]
Фаза 6: Polish + ручное тестирование   [День 14]
```

---

## 7. Definition of Done

Для каждого элемента работ:
- [ ] Unit/integration тест написан ДО реализации (TDD red → green → refactor)
- [ ] API-вызовы соответствуют OpenAPI-спеке backend
- [ ] HTTP-методы: GET/POST/PATCH/DELETE (НЕ PUT)
- [ ] Работает в light и dark mode
- [ ] Работает на RU и EN
- [ ] Нет console errors/warnings
- [ ] E2E тест проходит на Playwright
- [ ] Скриншот совпадает с ожиданием

---

## 8. Риски

| Риск | Митигация |
|------|-----------|
| Backend API может отличаться от документации | Сначала проверять через `curl`, потом писать фронт |
| JSON:API flattening может ломать вложенные данные | Написать интеграционные тесты на `flattenJsonApi()` |
| Recharts увеличит bundle | Lazy loading через `React.lazy()` |
| E2E тесты хрупкие | Использовать data-testid, не полагаться на текст |
