# SERP Panel API — Полное руководство

**Base URL:** `https://api-serp.equity.su/api/v1/`
**Формат:** JSON:API v1.1
**HTTP методы:** GET, POST, PATCH, DELETE (никогда PUT)

---

## Аутентификация

Все запросы (кроме публичных) требуют:
```
Authorization: Bearer {token}
Content-Type: application/json
X-Organization-Id: {organizationId}   # для org-scoped эндпоинтов
```

Токен получается при регистрации или логине. `X-Organization-Id` — ID текущей организации.

---

## Роли и права

| Роль | Уровень | Возможности |
|------|---------|-------------|
| **viewer** | 1 | Только чтение всех данных |
| **analyst** | 2 | Чтение + экспорт |
| **manager** | 3 | Чтение + создание/изменение/удаление ресурсов |
| **admin** | 4 | Всё + управление организацией, членами, биллингом |

---

## Порядок настройки системы с нуля

### Шаг 1: Регистрация и авторизация

```
POST /auth/register
{
  "name": "Иван",
  "email": "ivan@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "organization_name": "Моё SEO-агентство"
}
→ 201: { user, organization, token }
```

```
POST /auth/login
{ "email": "...", "password": "..." }
→ 200: { user, token }
```

```
GET /auth/me                    # текущий юзер + организации
PATCH /auth/profile             # обновить имя/локаль/тему
POST /auth/logout               # инвалидировать токен
```

### Шаг 2: Организация и команда

```
GET    /organizations           # список организаций юзера
POST   /organizations           # создать новую организацию
GET    /organization            # текущая организация (по X-Organization-Id)
PATCH  /organization            # переименовать (admin)
DELETE /organization            # мягкое удаление (admin)
```

**Управление командой (admin):**
```
GET    /organization/members                    # список участников
POST   /organization/invite                     # { email, role }
PATCH  /organization/members/{userId}/role      # { role }
DELETE /organization/members/{userId}           # удалить из организации
```

### Шаг 3: Создание проекта

```
POST /projects
{ "name": "Интернет-магазин", "description": "Мониторинг основного сайта" }
→ 201: ProjectResource
```

```
GET    /projects                    # список
GET    /projects/{id}               # детали
PATCH  /projects/{id}               # обновить
DELETE /projects/{id}               # удалить (каскадно!)
PATCH  /projects/{id}/public        # { is_public: true } → генерирует публичную ссылку
```

### Шаг 4: Добавление доменов

```
POST /projects/{projectId}/domains
{ "name": "mysite.com", "is_own": true, "type": "target" }
→ 201: DomainResource
```

Типы: `target` (свой), `competitor` (конкурент)

```
GET    /projects/{projectId}/domains    # список доменов проекта
GET    /domains/{id}                     # детали
PATCH  /domains/{id}                     # обновить
DELETE /domains/{id}                     # удалить (каскадно!)
```

**Индексация домена (site: запрос):**
```
POST   /domains/{id}/index              # { engine: "google", limit: 1000 }
GET    /domains/{id}/index-status       # статус (running/completed/failed)
GET    /domains/{id}/index-results      # найденные страницы
DELETE /domains/{id}/index              # отменить
GET    /domains/{id}/keywords           # ключевые слова домена
```

### Шаг 5: Структура категорий и кластеров

```
POST /categories
{ "domain_id": 1, "name": "Электроника", "parent_id": null, "sort_order": 1 }

POST /clusters
{ "category_id": 1, "name": "Смартфоны", "sort_order": 1 }
```

**Чтение:**
```
GET /domains/{domainId}/categories      # категории домена
GET /projects/{projectId}/categories    # все категории проекта
GET /categories/{id}                     # детали
GET /categories/{categoryId}/clusters   # кластеры категории
GET /projects/{projectId}/clusters      # все кластеры проекта
GET /clusters/{id}                       # детали
```

**Изменение (manager+):**
```
PATCH  /categories/{id}    # обновить
DELETE /categories/{id}    # удалить (каскадно!)
PATCH  /clusters/{id}      # обновить
DELETE /clusters/{id}      # удалить (каскадно!)
```

### Шаг 6: Добавление ключевых слов

```
POST /keywords/bulk
{
  "keywords": [
    { "keyword": "купить смартфон", "cluster_id": 1, "engine": "google", "device": "desktop", "region_id": 213 },
    { "keyword": "цена iphone",     "cluster_id": 1, "engine": "yandex", "device": "mobile",  "region_id": 2 }
  ]
}
→ 201: Array of KeywordResource
```

**Импорт из CSV:**
```
POST /keywords/import (multipart/form-data)
  file: CSV (keyword, frequency)
  cluster_id, engine, device, region_id
```

**Чтение:**
```
GET /keywords?filter[cluster_id]=1&filter[engine]=google&sort=-created_at&page[size]=25
GET /keywords/{id}          # keyword + latest_position, position_change, frequency, our_url
```

**Изменение (manager+):**
```
PATCH  /keywords/{id}       # обновить
DELETE /keywords/bulk        # { ids: [1,2,3] }
```

**Регионы:**
```
GET /regions                # все доступные регионы для поиска
```

### Шаг 7: Настройка сбора SERP

**Скрейперы (источники данных):**
```
GET  /scraper-types                 # доступные типы: xmlriver, yandex_xml, webhook
POST /scrapers
{
  "type": "xmlriver",
  "name": "XMLRiver Production",
  "base_url": "http://xmlriver.com/search/xml",
  "credentials": { "user": "20272", "key": "api_key_here" },
  "supported_engines": ["google", "yandex"],
  "rate_limit": 10
}

GET    /scrapers                    # список
GET    /scrapers/{id}               # детали
PATCH  /scrapers/{id}               # обновить
DELETE /scrapers/{id}               # удалить
POST   /scrapers/{id}/test          # проверить соединение
```

**Расписания сбора:**
```
POST /schedules
{
  "project_id": 1,
  "scraper_id": 1,
  "frequency_days": 1,
  "is_active": true
}

GET    /schedules                   # список
GET    /schedules/{id}              # детали
PATCH  /schedules/{id}              # обновить
DELETE /schedules/{id}              # удалить
POST   /schedules/{id}/run-now      # запустить немедленно
```

### Шаг 8: Мониторинг позиций

**Матрица позиций (главный вид):**
```
GET /projects/{projectId}/positions?days=14
→ { dates: [...], keywords: [{ id, keyword, engine, device, positions: [{date, position, delta}] }] }
```

**SERP-результаты по ключевому слову:**
```
GET /keywords/{id}/serp                 # снапшоты с результатами (top-100)
GET /keywords/{id}/serp/dates           # доступные даты
GET /keywords/{id}/serp/history         # история позиций (для графика)
POST /keywords/{id}/rescrape            # пересобрать сейчас
```

**Конкуренты:**
```
GET /serp/competitors?project_id=1      # сводка по конкурентам (top3/top10/пересечения)
```

### Шаг 9: Wordstat (спрос Яндекса)

**Подключение Yandex OAuth:**
```
GET    /auth/yandex/redirect            # получить URL для авторизации
GET    /auth/yandex/callback            # обработка callback
POST   /organization/yandex/save-token  # сохранить токен (admin)
GET    /organization/yandex/status      # проверить подключение
DELETE /organization/yandex             # отключить (admin)
```

**Данные Wordstat:**
```
GET /keywords/{id}/wordstat             # частоты (exact, broad, phrase)
GET /keywords/{id}/wordstat/trends      # месячная динамика
GET /keywords/{id}/wordstat/suggestions # связанные запросы
```

**Расписания Wordstat:**
```
POST   /wordstat-schedules              # { project_id, frequency_days, collect_trends, collect_suggestions, regions: [213], adapter_type: "yandex" }
GET    /wordstat-schedules              # список
PATCH  /wordstat-schedules/{id}         # обновить
DELETE /wordstat-schedules/{id}         # удалить
POST   /wordstat-schedules/{id}/run-now # запустить
```

### Шаг 10: Алерты позиций

```
POST /alerts
{
  "keyword_id": 1,
  "threshold_position": 10,
  "direction": "drops_below",      # drops_below | rises_above
  "channel": "telegram",           # telegram | email
  "recipient": "@username",
  "is_active": true
}

GET    /alerts                  # список
GET    /alerts/{id}             # детали
PATCH  /alerts/{id}             # обновить
DELETE /alerts/{id}             # удалить
```

### Шаг 11: Целевые страницы (Pages)

**CRUD:**
```
POST   /projects/{projectId}/pages      # { url, title, page_type, domain_id, tags }
GET    /projects/{projectId}/pages      # список
GET    /pages/{id}                       # детали
PATCH  /pages/{id}                       # обновить
DELETE /pages/{id}                       # удалить
PATCH  /pages/{id}/tags                 # { tags: ["electronics", "sale"] }
```

page_type: `commercial`, `informational`, `navigational`, `transactional`

**Привязка к ключевым словам/кластерам/категориям:**
```
POST   /pages/{id}/attach              # { pageable_type: "keyword", pageable_id: 1, is_target: true }
POST   /pages/{id}/bulk-attach         # { pageable_type, pageable_ids: [1,2,3], is_target: true }
POST   /projects/{id}/pages/bulk-attach # массовая привязка
DELETE /pageables/{id}                  # отвязать
```

**Быстрая разметка из SERP:**
```
POST /projects/{id}/pages/match-or-create   # найти или создать страницу + привязать
POST /projects/{id}/pages/import            # импорт массива страниц
```

**Отчёты:**
```
GET /keywords/{id}/pages                     # страницы ключевого слова
GET /projects/{id}/pages/target-report       # сводка по целевым страницам
```

### Шаг 12: Классификация доменов в SERP

```
GET  /site-types                        # типы сайтов (маркетплейс, магазин, инфо...)
GET  /classification/rules              # правила классификации
POST /classification/rules              # { rule_type, pattern, site_type_id, priority }
PATCH /classification/rules/{id}        # обновить
DELETE /classification/rules/{id}       # удалить
PATCH /domains/{id}/classify            # ручная классификация { site_type_id }
```

rule_type: `domain_exact`, `domain_contains`, `domain_regex`, `url_regex`, `title_contains`

### Шаг 13: Аудит сайта

Проверка качества страниц: технические данные, мета-теги, контент, ссылки, изображения.
Ходит по живому сайту, ничего не меняет.

**Запуск прогона:**
```
POST /projects/{projectId}/audits
```

| Поле | Значение |
|---|---|
| `scope` | `site` — весь сайт, `pages` — выбранные страницы, `url` — один адрес |
| `domain_id` | какой домен проекта проверяем (для `scope=site`) |
| `page_ids` | массив ID страниц (обязателен при `scope=pages`) |
| `url` | адрес (обязателен при `scope=url`) |
| `groups` | какие категории гонять; по умолчанию все |
| `check_codes` | коды отдельных проверок из каталога; по умолчанию все |

Категории смотрите в `GET /audit/checks` — их список зависит от установленных пакетов.
Помимо категорий прогон сужается отдельными проверками:
`check_codes: ["meta.title", "content.relevance"]`. Оба фильтра складываются,
пустой выбор означает «все проверки».

При `scope=site` список URL собирается из карты сайта, страниц из индекса
(собраны запросом `site:`) и страниц проекта. Адреса чужих доменов отбрасываются:
реестр Pages держит и конкурентов, но аудит остаётся на своём хосте.
Закрытое в robots.txt не запрашивается, потолок — 500 страниц на прогон.

Одновременно у проекта идёт только один прогон, иначе `409`.

**Наблюдение и результаты:**
```
GET    /projects/{projectId}/audits          # история прогонов
GET    /audits/{id}                          # статус, прогресс, оценка, находки уровня сайта
DELETE /audits/{id}                          # отменить прогон
GET    /audits/{id}/results                  # постранично; ?severity=critical|warning|notice&search=
GET    /pages/{id}/audit                     # последний результат по странице
```

Пока прогон идёт, `status` = `running`, а `progress` растёт от 0 до 100 — поле годится
для опроса раз в 3 секунды. По завершении `status` = `completed`, появляется `score` (0–100).
Если часть страниц взять не удалось, в `error` будет сказано сколько именно —
прогон не выдаёт неполный обход за успешный.

Находки уровня сайта лежат в самом прогоне (`findings`): robots.txt, карта сайта,
SSL, оформление 404, редиректы http→https и слэша, фавикон. Постраничные находки —
в результатах.

Каждая находка: `check` (код проверки), `code` (код дефекта, например `meta.title.long`),
`category`, `severity` (`critical` / `warning` / `notice`), `message`, `value`, `expected`.
Оценка = 100 минус штраф: ошибка −10, предупреждение −3, замечание −1.

**Релевантность целевым ключам.** Если у страницы через Pageable есть целевые ключи,
в `metrics.relevance` приходит матрица «ключ × зона» — процент вхождения ключа в
title, description, h1, остальные заголовки, анкоры и текст. Так видно не «сколько
слов на странице», а отражён ли на ней запрос, под который её продвигают.

**Каталог проверок:**
```
GET /audit/checks       # категории и проверки в них
```

Проверки живут в пакетах и регистрируются в реестре: поставили пакет — его проверки
появились в каталоге и в прогонах. Поэтому список берут отсюда, а не хардкодят.

Ответ — массив категорий: `category`, `title` и `checks` из пар `code`/`title`.
Встроенные категории: `technical`, `meta`, `content`, `links`, `images`; свой пакет
вправе завести собственную.

Категории и их наполнение:

| Категория | Что проверяет |
|---|---|
| `technical` | код ответа, редиректы, скорость и вес, заголовки безопасности, сжатие, аналитика, технологии, подключённые ресурсы |
| `meta` | title, description, заголовки, canonical и robots, структура и viewport, OpenGraph и Schema.org, устаревшая разметка, совпадение lang с письменностью |
| `content` | объём текста, вода, тошнота, плотность и релевантность целевым ключам |
| `links` | внешние ссылки, пустые анкоры, смешанный протокол |
| `images` | alt, размеры, сторонние источники |
| `a11y` | ориентиры, скип-ссылка, дубли id, подписи полей, scope у таблиц, доступные имена |
| `legal` | согласие на обработку ПДн у форм, доступность политики (152-ФЗ) |

Уровень сайта (в `findings` самого прогона): robots.txt, карта сайта, SSL, оформление 404,
редиректы http→https и слэша, фавикон, сжатие ответа.

Чего аудит не делает: PageSpeed — лабораторную часть даёт свой браузер,
а полевую Google из PSI убирает в пользу CrUX.

У проверки код вида `meta.title`, у находки — `meta.title.long`: код проверки плюс
суффикс дефекта. В `check` находки лежит первый, в `code` — второй. Фильтровать
удобно по `check`, читать — по `code`.

**Второй этап — ссылки и файлы.** После обхода страниц прогон идёт по собранным
внутренним ссылкам и картинкам за кодом ответа и размером. Каждый URL запрашивается
один раз, сколько бы страниц на него ни ссылалось. Внешние ссылки записываются, но
не запрашиваются.

Появляются находки, которых постранично не бывает:

| Код | Что значит |
|---|---|
| `site.resources.broken` | внутренние ссылки и файлы с кодом 4xx/5xx, с числом ссылающихся страниц |
| `site.resources.heavy_images` | картинки тяжелее 300 КБ |
| `site.duplicate.title` | одинаковый title на разных страницах |
| `site.duplicate.description` | то же для description |

В `metrics.resources` прогона: `checked`, `broken`, `bytes` (суммарный вес картинок)
и `heaviest`. Этап выключается через `AUDIT_CHECK_RESOURCES=false`, потолок —
`AUDIT_MAX_RESOURCES` (по умолчанию 2000).

Из-за второго этапа прогон по сайту идёт дольше: оба этапа делят один лимитер
вежливости, поэтому время считайте как (страницы + ресурсы) / 2 в секунду.

**Третий этап — браузер.** То, что нельзя посчитать разбором HTML: сдвиги вёрстки,
контраст по вычисленным стилям, момент появления главного элемента.

| Код | Что значит |
|---|---|
| `browser.cls` | вёрстка едет при загрузке; в значении — CLS и виновники сдвига |
| `browser.lcp` | главный элемент появляется позже 2.5 с; в значении — сам элемент |
| `browser.contrast` | текст не дотягивает до 4.5:1 (для крупного 3:1) |
| `browser.small_text` | текст мельче 12px |

В `metrics.browser` страницы: `cls`, `lcp`, `fcp`, `timing` и покрытие контраста —
`checked`, `unchecked` и `unchecked_reasons`. Последнее важнее, чем кажется: где
фон задан картинкой или градиентом, честного ответа нет, и такие узлы уходят в
«не проверено», а не в «нарушение».

Этап идёт по выборке (`AUDIT_BROWSER_MAX_PAGES`, по умолчанию 20), сначала берутся
страницы проекта. Полминуты на страницу означает, что весь сайт занял бы часы.
Если браузерный сервис не поднят, у страниц просто нет браузерных находок и нет
блока `metrics.browser` — это «не проверено», а не «чисто».

**Валидация W3C и полевые данные.** Оба этапа идут вместе с браузерным.

`metrics.w3c` страницы: `errors`, `warnings`, `info`. Находкой (`w3c.validation.errors`)
становятся только нарушения спецификации. Стилевые замечания намеренно оставлены
числом: именно из них внешние отчёты складывают «192 ошибки валидации». На главной
eq.team это 0 нарушений против 36 стилевых замечаний.

`metrics.field` прогона — Chrome UX Report по живым пользователям: `scope` (`url` или
`origin`), `metrics` с `p75` и долями good/needs_improvement/poor, `period`. Находки
`field.largest_contentful_paint`, `field.interaction_to_next_paint`,
`field.cumulative_layout_shift`, `field.experimental_time_to_first_byte`.

Если по конкретному URL данных не набралось, ответ берётся по домену и в находке об
этом сказано прямо. Нет ключа или нет данных — блока `field` просто нет: это «данных
нет», а не «всё хорошо».

**Разовая проверка без записи в БД:**
```
POST /audit/url    # { url, groups? }
```

Отвечает синхронно, в базу ничего не пишет. Нужна внешним рутинам как воротца перед
публикацией: получил находки — решил, публиковать или чинить. Лимит 20 запросов/мин.

---

## Дополнительные возможности

### Подключённые аккаунты
```
GET    /accounts                    # список
POST   /accounts                    # { type, label, credentials } (admin)
PATCH  /accounts/{id}               # обновить (admin)
DELETE /accounts/{id}               # удалить (admin)
POST   /accounts/{id}/test          # проверить
```

### API-токены (для внешних интеграций)
```
GET    /tokens                      # список токенов
POST   /tokens                      # { name, role, project_id?, expires_at? }
DELETE /tokens/{id}                  # отозвать
```

Роль токена не может превышать роль пользователя. Токен показывается ОДИН раз при создании.

### Биллинг
```
GET   /billing/usage                # использование и лимиты
PATCH /billing/tier                 # сменить тариф (admin): free/starter/pro/enterprise
```

### Дашборд
```
GET /dashboard/summary?filter[project_id]=1     # KPI: top3/top10/top20, всего ключевых слов
```

### Экспорт
```
GET /export/keywords?filter[project_id]=1       # CSV с ключевыми словами
GET /export/serp?filter[keyword_ids][]=1&filter[from]=2025-01-01    # CSV с SERP-данными
```

### Вебхук (входящие SERP-данные)
```
POST /webhooks/serp     # без авторизации, использует webhook_secret
{ "scraper_id": 1, "secret": "...", "keyword_id": 5, "results": [...] }
```

### Публичный доступ (без авторизации)
```
GET /public/{slug}                  # данные проекта
GET /public/{slug}/positions?days=14 # матрица позиций
GET /public/{slug}/domains          # список доменов
```

Rate limit: 60 запросов/мин.

---

## Модель данных (иерархия)

```
Organization
  └── Project
        ├── Domain (own / competitor)
        │     └── Category
        │           └── Cluster
        │                 └── Keyword
        │                       ├── SerpSnapshot → SerpResult[]
        │                       ├── WordstatFrequency
        │                       └── PositionAlert
        ├── Page (target URL)
        │     └── Pageable (привязка к keyword/cluster/category)
        └── SiteAudit (прогон аудита)
              └── PageAuditResult (находки и метрики по одному URL)
```

---

## Типичный сценарий: мониторинг интернет-магазина

1. **Регистрация** → POST /auth/register
2. **Создать проект** → POST /projects
3. **Добавить домены** → POST /projects/{id}/domains (свой + конкуренты)
4. **Создать структуру** → POST /categories → POST /clusters
5. **Добавить ключевые слова** → POST /keywords/bulk (сотни/тысячи)
6. **Настроить скрейпер** → POST /scrapers (XMLRiver/Yandex)
7. **Создать расписание** → POST /schedules (ежедневный сбор)
8. **Настроить алерты** → POST /alerts (уведомления о падениях)
9. **Подключить Wordstat** → Yandex OAuth + POST /wordstat-schedules
10. **Разметить страницы** → POST /pages + attach к ключевым словам
11. **Проверить качество страниц** → POST /projects/{id}/audits (scope=site)
12. **Мониторинг** → GET /projects/{id}/positions (ежедневно)
13. **Анализ** → GET /serp/competitors, GET /export/keywords
