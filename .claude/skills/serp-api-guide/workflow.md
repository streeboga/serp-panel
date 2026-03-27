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
        └── Page (target URL)
              └── Pageable (привязка к keyword/cluster/category)
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
11. **Мониторинг** → GET /projects/{id}/positions (ежедневно)
12. **Анализ** → GET /serp/competitors, GET /export/keywords
