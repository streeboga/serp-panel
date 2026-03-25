# SEO Monitor — Design Document

**Date:** 2026-03-25
**Status:** Approved

## Overview

Multi-tenant SaaS для мониторинга SEO-позиций в Google и Яндексе. Полный TOP-100 SERP-снэпшот с историей, Wordstat-аналитика, автоклассификация сайтов в выдаче.

## Stack

- **Backend:** Laravel 11, PHP 8.3, PostgreSQL 16, Redis, Horizon
- **Frontend:** React + TypeScript, TanStack (Router, Query, Table), Tailwind CSS + shadcn/ui
- **Scrapers:** Отдельные сервисы (XMLRiver, Yandex XML, OpenSERP, Camoufox, ...) + PHP-адаптеры
- **Infra:** Docker Compose, отдельные воркеры на очередь

## Requirements

| Параметр | Значение |
|---|---|
| Масштаб | 100 000+ ключевиков |
| Тенантность | Мульти-тенант SaaS |
| Роли | Организация -> admin / manager / analyst / viewer -> доступ к проектам |
| SERP | Полный TOP-100, история по дням, настраиваемая периодичность |
| Wordstat | Частотность (3 типа) + динамика + подсказки + регионы |
| Классификация | Правила (regex/вхождение) + ручная корректировка |
| Скрейперы | Плагинная архитектура, адаптеры под единый формат |
| Биллинг | Не в MVP |

---

## Секция 1: Схема БД — Мульти-тенант + Иерархия

```sql
organizations
  id, name, slug
  created_at, updated_at

organization_user (pivot)
  organization_id, user_id, role (admin|manager|analyst|viewer)

projects
  id, organization_id, name, description
  created_at, updated_at

domains
  id, project_id, name (equity.su), is_own (bool)
  created_at, updated_at

categories
  id, domain_id, name, parent_id (self-ref)
  sort_order

clusters
  id, category_id, name
  sort_order

keywords
  id, cluster_id, keyword, engine (google|yandex)
  region_id (FK -> regions), device (desktop|mobile)
  created_at, updated_at

regions
  id, engine, code, name, yandex_lr, google_gl, google_hl
```

**Решения:**
- `is_own` на домене — отделяем свой сайт от конкурентов
- `parent_id` на категории — дерево любой глубины
- `engine` на ключевике — один запрос может отслеживаться в обоих движках (две записи)
- `regions` — справочник регионов для обоих движков

---

## Секция 2: SERP-снэпшоты + партиционирование

```sql
serp_snapshots (партиционирована по collected_at, помесячно)
  id (bigint), keyword_id, collected_at (date)
  search_engine (google|yandex)
  device (desktop|mobile)
  region_id
  total_results (int)
  created_at

serp_results (партиционирована по collected_at, помесячно)
  id (bigint), snapshot_id, collected_at (date)
  position (1-100)
  url (text)
  domain (varchar — извлекаем из url)
  title (text)
  description (text)
  snippet_type (varchar — featured, video, image, local, organic, ...)
  is_ads (bool)
  cached_page_url (text, nullable)

scrape_schedules
  id, keyword_id (nullable), cluster_id (nullable),
  category_id (nullable), project_id (nullable)
  scraper_type (xmlriver|yandex_xml|openserp|...)
  frequency_days (int)
  last_run_at, next_run_at
  is_active (bool)
  created_at, updated_at
```

**Решения:**
- `serp_results` отдельно от `serp_snapshots` — один снэпшот = 100 записей
- Обе таблицы партиционируются по `collected_at` помесячно
- `domain` извлекается при сохранении — быстрая фильтрация без парсинга URL
- `scrape_schedules` каскадные: проект/категория/кластер/ключевик, конкретное перекрывает общее

**Масштаб (100k ключевиков, ежедневно):**
- `serp_snapshots`: ~100k/день, ~3M/месяц
- `serp_results`: ~10M/день, ~300M/месяц

---

## Секция 3: Wordstat

```sql
wordstat_frequencies
  id (bigint), keyword_id, region_id
  frequency_exact (int — "!слово")
  frequency_broad (int — широкая)
  frequency_phrase (int — "слово")
  collected_at (date)
  created_at

wordstat_trends
  id (bigint), keyword_id, region_id
  month (date — первый день месяца)
  absolute_value (int)
  collected_at (date)

wordstat_suggestions
  id (bigint), keyword_id
  suggestion (varchar)
  frequency (int)
  type (suggestion|association)
  collected_at (date)
  created_at

wordstat_schedules
  id, project_id (nullable), cluster_id (nullable), keyword_id (nullable)
  frequency_days (int — обычно 30)
  collect_trends (bool)
  collect_suggestions (bool)
  regions (jsonb — массив region_id)
  last_run_at, next_run_at
  is_active (bool)
```

**Решения:**
- Три типа частотности: точная (`!`), фразовая (`""`), широкая
- Отдельная таблица для помесячной динамики (сезонность)
- Подсказки с типом: suggestion vs association
- Отдельное расписание от SERP — Wordstat обычно реже (раз в месяц)

---

## Секция 4: Классификация сайтов

```sql
site_types
  id, slug (marketplace|ecommerce|info|blog|landing|aggregator|government|...)
  name, color (hex), sort_order

classification_rules
  id, organization_id
  rule_type (domain_exact|domain_contains|domain_regex|url_regex|title_contains)
  pattern (varchar)
  site_type_id (FK -> site_types)
  priority (int)
  is_system (bool)
  created_at

domain_classifications
  id, domain (varchar)
  site_type_id (FK -> site_types)
  classified_by (rule|manual)
  rule_id (nullable)
  organization_id
  updated_at
```

**Логика:**
1. При сохранении SERP — берём domain из serp_results
2. Проверяем domain_classifications — если размечен, пропускаем
3. Прогоняем по classification_rules в порядке priority
4. Первое совпадение -> classified_by=rule
5. Ручная корректировка -> classified_by=manual, правила не перезаписывают

---

## Секция 5: Адаптеры скрейперов

```sql
scrapers
  id, organization_id
  type (xmlriver|yandex_xml|openserp|camoufox|custom)
  name (varchar)
  base_url (varchar)
  credentials (jsonb, encrypted)
  supported_engines (jsonb — ["google","yandex"])
  rate_limit (int — макс запросов/мин)
  is_active (bool)
  created_at, updated_at

scrape_jobs
  id (bigint), keyword_id, scraper_id, schedule_id
  status (pending|running|completed|failed|retrying)
  engine, region_id, device
  attempts (int)
  started_at, completed_at
  error_message (text, nullable)
  raw_response (text, nullable)
  created_at
```

**PHP-интерфейс:**

```php
interface SerpScraperAdapter
{
    public function scrape(ScrapeRequest $request): ScrapeResponse;
    public function supportedEngines(): array;
    public function healthCheck(): bool;
}

class ScrapeRequest {
    string $keyword;
    string $engine;     // google|yandex
    string $device;     // desktop|mobile
    int $regionId;
    int $limit;         // 100
}

class ScrapeResponse {
    array $results;     // [{position, url, domain, title, description, snippet_type, is_ads}]
    int $totalResults;
    string $rawResponse;
}
```

**Архитектура:**
```
Laravel (API) -> dispatch -> Queue (Redis)
                                |
                          ScrapeJob (Laravel Job)
                                |
                    +-----------+-----------+
                    v           v           v
              XMLRiver      YandexXML    OpenSERP
              Adapter       Adapter      Adapter
                    |           |           |
                    v           v           v
              Unified SERP Response (DTO)
                    |
                    v
              Save to serp_snapshots + serp_results
              Run classification rules
```

---

## Секция 6: API-структура + Cron/Queue

### API Endpoints

```
Auth:
  POST   /api/auth/login
  POST   /api/auth/register
  POST   /api/auth/logout

Organizations:
  GET    /api/organizations
  POST   /api/organizations
  PUT    /api/organizations/{id}
  POST   /api/organizations/{id}/invite
  GET    /api/organizations/{id}/members

Projects:
  CRUD   /api/projects

Domains:
  CRUD   /api/projects/{projectId}/domains

Categories:
  CRUD   /api/projects/{projectId}/categories (tree via parent_id)

Clusters:
  CRUD   /api/categories/{categoryId}/clusters

Keywords:
  GET    /api/keywords?cluster_id=&engine=&search=
  POST   /api/keywords/bulk
  DELETE /api/keywords/bulk
  PUT    /api/keywords/{id}

SERP:
  GET    /api/keywords/{id}/serp?from=&to=&limit=20
  GET    /api/keywords/{id}/serp/history
  GET    /api/serp/competitors?project_id=&keyword_ids[]=

Wordstat:
  GET    /api/keywords/{id}/wordstat
  GET    /api/keywords/{id}/wordstat/trends

Classification:
  CRUD   /api/classification/rules
  PUT    /api/domains/{domain}/classify

Scrapers:
  CRUD   /api/scrapers
  POST   /api/scrapers/{id}/test

Schedules:
  CRUD   /api/schedules
  POST   /api/schedules/{id}/run-now

Dashboard:
  GET    /api/dashboard/summary?project_id=
```

### Queue/Cron

```
Cron (Laravel Scheduler, каждую минуту):
  CheckSchedulesCommand    -> создаёт scrape_jobs пачками
  DispatchScrapeJobsCommand -> dispatch с учётом rate_limit
  CleanupCommand (раз/сутки) -> чистит raw_response, создаёт партиции
  WordstatCollectCommand   -> аналогично для Wordstat

Queues (Redis, раздельные):
  serp-scrape       (SERP воркеры)
  wordstat           (Wordstat воркеры)
  classification     (классификация доменов)
  default            (остальное)
```

---

## Секция 7: React Frontend

**Стек:** React + TypeScript, TanStack (Router, Query, Table), Tailwind CSS + shadcn/ui

### Routing

```
/login, /register

/org/:orgId/
  dashboard
  projects/
    :projectId/
      overview
      domains
      keywords              (дерево: категории -> кластеры -> ключевики)
        :keywordId/
          tab: SERP          (TOP-N результатов, фильтр по дате/периоду)
          tab: History       (график позиции по дням)
          tab: Wordstat      (частотность + тренд)
          tab: Suggestions   (подсказки/ассоциации)
      competitors            (сводная таблица конкурентов)
      settings               (скрейперы, расписания, регионы)
  classification/
    rules
    domains
  scrapers/
  settings/                  (организация, участники, роли)
```

### Ключевые экраны

**Таблица ключевиков:**
- Фильтры: engine, device, регион, категория, поиск
- Колонки: ключевик, engine-бейдж (Я/G), позиция, изменение, частотность, URL нашего сайта
- Bulk actions: импорт CSV, удаление, перемещение

**SERP по ключевику:**
- Фильтр по дате, переключатель TOP-N (по умолчанию 20)
- Колонки: позиция, домен, тип сайта (бейдж), title, URL
- Свой домен подсвечивается

---

## Секция 8: Инфраструктура

```yaml
Docker Compose:
  app              (Laravel API — PHP 8.3 + FPM)
  frontend         (React — Node 20 + Vite / nginx)
  postgres         (PostgreSQL 16 с партиционированием)
  redis            (очереди + кеш + сессии)
  scheduler        (Laravel Scheduler)
  worker-serp      (Horizon, queue: serp-scrape)
  worker-wordstat  (Horizon, queue: wordstat)
  worker-class     (Horizon, queue: classification)
```

Скрейпер-сервисы (XMLRiver, OpenSERP, Camoufox) живут отдельно, подключаются по URL.
