---
stepsCompleted: ['step-01-init', 'step-02-discovery', 'step-02b-vision', 'step-02c-executive-summary', 'step-03-success', 'step-04-journeys', 'step-05-domain', 'step-06-innovation', 'step-07-project-type', 'step-08-scoping', 'step-09-functional', 'step-10-nonfunctional', 'step-11-polish', 'step-12-complete']
inputDocuments:
  - docs/plans/2026-03-25-seo-monitor-design.md
  - docs/plans/2026-03-25-seo-monitor-implementation.md
documentCounts:
  briefs: 0
  research: 0
  brainstorming: 0
  projectDocs: 2
workflowType: 'prd'
classification:
  projectType: 'SaaS B2B + Self-hosted option'
  domain: 'SEO Analytics / Competitive Intelligence'
  complexity: 'high'
  projectContext: 'brownfield'
vision:
  core: 'Affordable and convenient SEO position monitoring for everyone'
  differentiator: 'Price — cheap monitoring at scale without paying for 100 unnecessary features'
  insight: 'Market is overloaded with expensive all-in-one tools. Most SEOs just need to see positions and trends.'
  model: 'SaaS for all (freelancers → agencies → in-house → enterprise with self-hosted option)'
  auto_classification: 'auxiliary feature, not central'
---

# Product Requirements Document — SERP Panel

**Author:** K.mazurov
**Date:** 2026-03-26
**Status:** Complete

## Executive Summary

SERP Panel — доступная мульти-тенантная SaaS-платформа для мониторинга SEO-позиций в Google и Яндексе. Решает главную боль рынка: существующие инструменты (Topvisor ~500₽/мес, Rush Analytics ~500₽/мес, SE Ranking ~$55/мес, SEMrush ~$130/мес) неоправданно дороги на масштабе 10k+ ключевиков.

Целевая аудитория — все, кто отслеживает позиции: фрилансеры, SEO-агентства, in-house команды, enterprise. Ядро продукта: полный TOP-100 SERP-снэпшот с историей, Wordstat-аналитика (частотность + сезонность + подсказки), автоклассификация сайтов в выдаче по правилам.

Стек: Laravel 13 API + PostgreSQL 16 (партиционирование помесячное для масштаба 300M записей/месяц) + React SPA (TanStack + shadcn/ui). Плагинная архитектура скрейперов (XMLRiver, Yandex XML, OpenSERP, Camoufox). MVP реализован: 15 API контроллеров, 20 моделей, 25 миграций, полный React-фронтенд.

### What Makes This Special

**Цена как стратегия.** Рынок перегружен дорогими all-in-one комбайнами. SERP Panel — осознанно узкий инструмент: мониторинг позиций + Wordstat + классификация. Без лишних модулей (бэклинки, аудит сайта, PPC) = низкая себестоимость = доступная цена на любом объёме.

**Автоклассификация сайтов** — уникальная фича, отсутствующая у конкурентов (Topvisor, Rush Analytics, SE Ranking, SEMrush, Ahrefs). Каждый домен в выдаче автоматически размечается по типу (маркетплейс, интернет-магазин, инфо-сайт, агрегатор) — SEO-специалист сразу видит структуру конкуренции.

**Self-hosted опция** для enterprise — полный контроль данных, без зависимости от чужого облака. Docker Compose одним файлом.

## Project Classification

| Параметр | Значение |
|---|---|
| Тип проекта | SaaS B2B + Self-hosted option |
| Домен | SEO Analytics / Competitive Intelligence |
| Сложность | Высокая (масштаб данных, внешние зависимости, конкурентный рынок) |
| Контекст | Brownfield — MVP реализован (11 фаз, 19 коммитов) |
| Стек | Laravel 13 + PostgreSQL 16 + Redis/Horizon + React 18 + TanStack |
| Масштаб | 100k+ ключевиков, до 300M записей/месяц |

## Success Criteria

### User Success

- SEO-специалист добавляет проект и импортирует 1000 ключевиков за < 5 минут
- Первые SERP-данные появляются в течение 1 часа после настройки расписания
- Пользователь видит позицию своего сайта и динамику по любому ключевику в 2 клика
- Wordstat-частотность доступна рядом с позицией — не нужно переключаться в другой инструмент
- Автоклассификация размечает 80%+ доменов без ручного вмешательства

### Business Success

- **3 месяца:** 50 активных пользователей, подтверждена готовность платить
- **6 месяцев:** 500 пользователей, положительная unit-экономика на одного клиента
- **12 месяцев:** 2000 пользователей, self-sustaining (доходы покрывают инфраструктуру)
- Стоимость мониторинга 1 ключевика в 3-5 раз ниже чем у Topvisor/Rush Analytics
- Churn rate < 10%/месяц

### Technical Success

- Система стабильно обрабатывает 100k ключевиков/день без деградации
- P95 latency API-ответов < 500ms для таблиц с пагинацией
- Партиционирование PostgreSQL позволяет хранить 12 месяцев истории без замедления запросов
- Скрейпинг pipeline < 5% failed jobs при нормальной работе провайдеров
- Zero downtime deploys

### Measurable Outcomes

| Метрика | MVP | Growth | Vision |
|---|---|---|---|
| Ключевиков на аккаунт | до 10k | до 100k | до 1M |
| Скрейпинг-провайдеры | 1 (XMLRiver) | 3+ | 5+ с auto-failover |
| Время отклика дашборда | < 2с | < 1с | < 500ms |
| Автоклассификация | regex-правила | + ML-модель | полный auto |

## Product Scope

### MVP — Minimum Viable Product

**Готово (✅):**
- Auth + мульти-тенант с ролями (admin/manager/analyst/viewer)
- CRUD: проекты, домены, категории, кластеры, ключевики, регионы
- SERP-скрейпинг pipeline (XMLRiver adapter)
- Wordstat интеграция (частотность + тренды + подсказки)
- Автоклассификация (regex/вхождение правила + ручная корректировка)
- Dashboard summary API
- React-фронтенд (все основные страницы)

**Нужно доделать для MVP (❌):**
- Алерты при падении позиций (Telegram + Email)
- CSV/Excel экспорт данных
- Data retention policy (автоочистка старых партиций)
- Биллинг / тарифные планы (базовый)
- Тестовое покрытие (минимум 60% для критических путей)

### Growth Features (Post-MVP)

- White-label + PDF-отчёты для агентств
- Share of Voice метрика
- Дедупликация скрейпинга между тенантами (экономия на одинаковых запросах)
- 2-3 дополнительных скрейпер-адаптера (Yandex XML, Camoufox)
- SERP-фичи tracking (featured snippets, AI Overviews, local pack)
- Onboarding wizard для новых пользователей
- Telegram-бот для быстрых проверок позиций
- Google Search Console интеграция

### Vision (Future)

- AI-рекомендации («позиция упала, вероятная причина: апдейт алгоритма»)
- ML-классификация доменов (замена regex на обученную модель)
- Anomaly detection (автодетекция апдейтов алгоритмов Google/Yandex)
- Self-hosted enterprise вариант (Docker Compose одним файлом)
- BI-коннекторы (Looker Studio, Grafana)
- SERP-кластеризация (автогруппировка ключевиков по поисковому интенту)
- Мультиязычный интерфейс

## User Journeys

### Journey 1: Алина — SEO-специалист в агентстве (Primary, Happy Path)

**Ситуация:** Алина ведёт 5 клиентов в SEO-агентстве. Каждый месяц тратит 2 дня на сбор позиций из разных инструментов и сведение в Excel.

**Opening Scene:** Алина регистрируется, создаёт организацию «SEO Lab», приглашает коллегу как analyst.

**Rising Action:** Создаёт проект «Клиент А», добавляет домен client-a.ru (is_own=true), импортирует 500 ключевиков из CSV. Настраивает расписание: ежедневный сбор позиций через XMLRiver. В категории «Коммерческие» группирует ключевики в кластеры.

**Climax:** Через час открывает дашборд — видит сводку: 45 ключевиков в TOP-3, 120 в TOP-10, 3 упали за вчера. Кликает на «купить квартиру москва» — видит полный TOP-100 с подсвеченным client-a.ru на 7-й позиции. Рядом бейджи типов сайтов: ЦИАН (агрегатор), Авито (маркетплейс), ДомКлик (сервис). Сразу понятно, с кем конкурировать.

**Resolution:** Вместо 2 дней ручной работы — все данные в одном месте. Алина готовит отчёт клиенту за 15 минут. Wordstat показывает сезонный тренд — можно планировать контент.

**Требования:** импорт CSV, дашборд, SERP-таблица, автоклассификация, подсветка своего домена, Wordstat рядом с позициями.

### Journey 2: Иван — владелец SEO-агентства (Primary, Edge Case)

**Ситуация:** У Ивана 30 клиентов. Текущий инструмент (Topvisor) стоит 15k₽/мес. Хочет снизить расходы.

**Opening Scene:** Иван оценивает SERP Panel: импортирует 10k ключевиков для тестового проекта. Приглашает 3 сотрудников с разными ролями.

**Rising Action:** Обнаруживает проблему — один из клиентов вылетел из TOP-10 по основным запросам. Без алертов (MVP gap!) узнал поздно.

**Climax:** Иван хочет отправить клиенту отчёт — нет PDF-экспорта и white-label (Growth feature). Экспортирует в CSV, оформляет вручную.

**Resolution:** Цена устраивает, но без алертов и отчётов — не может полностью мигрировать с Topvisor. Ждёт Growth-фич.

**Требования:** массовый импорт, мульти-проект, ролевая модель, алерты (MVP gap), PDF/white-label (Growth), экспорт CSV.

### Journey 3: Сергей — системный администратор (Admin/Ops)

**Ситуация:** Сергей отвечает за инфраструктуру. Организация выросла до 50k ключевиков.

**Opening Scene:** Замечает что PostgreSQL диск заполнен на 85%. Нужно понять объём данных и настроить retention.

**Rising Action:** Проверяет Horizon — 500 pending jobs в очереди serp-scrape. Один из скрейперов возвращает ошибки 429 (rate limit). Нужно посмотреть health check скрейпера и скорректировать rate_limit.

**Climax:** Настраивает data retention: автоудаление SERP-партиций старше 12 месяцев. Снижает частоту сбора для некритичных ключевиков.

**Resolution:** Диск освобождён, очереди стабилизированы, rate limit скорректирован через UI скрейперов.

**Требования:** data retention policy, мониторинг очередей/jobs, health check скрейперов, настройка rate limit, управление расписаниями.

### Journey 4: API-потребитель — интеграция с внутренней BI-системой

**Ситуация:** Аналитик хочет вытягивать данные SERP Panel в корпоративный дашборд (Grafana/Metabase).

**Opening Scene:** Получает API-токен через Sanctum. Читает Scramble-документацию.

**Rising Action:** Строит запросы: `GET /api/v1/keywords?filter[cluster_id]=5&sort=-position` для получения ключевиков с позициями. `GET /api/v1/keywords/{id}/serp?filter[from]=2026-03-01` для исторических данных.

**Climax:** Фильтрация через Spatie QueryBuilder, пагинация через JSON:API — стандартный, предсказуемый API.

**Resolution:** Данные потекли в BI-систему. Обновления автоматические по cron.

**Требования:** REST API (JSON:API v1.1), Sanctum tokens, Spatie QueryBuilder фильтрация/сортировка, Scramble автодокументация, пагинация.

### Journey Requirements Summary

| Journey | Ключевые требования |
|---|---|
| Алина (SEO) | Импорт CSV, дашборд, SERP-таблица, классификация, Wordstat |
| Иван (Owner) | Мульти-проект, роли, алерты, экспорт, отчёты |
| Сергей (Admin) | Retention, мониторинг jobs, health check, rate limit |
| API Consumer | JSON:API, auth tokens, фильтрация, документация |

## Domain-Specific Requirements

### SEO Data Collection Constraints

- **Rate limiting скрейперов:** каждый провайдер (XMLRiver, Yandex XML) имеет свои лимиты. Система учитывает rate_limit на уровне scraper entity и throttle dispatch jobs.
- **Региональность:** Яндекс возвращает разные SERP для 200+ регионов (yandex_lr). Google — по gl/hl. Регион привязан к ключевику.
- **Anti-bot защита:** Yandex агрессивнее Google в блокировке автоматических запросов. Адаптеры обрабатывают captcha/block ответы и помечают job как failed/retrying.
- **Data freshness:** позиции меняются ежедневно. Минимальная гранулярность — 1 день. Расписания от 1 до 30 дней.

### Data Storage at Scale

- **Партиционирование:** serp_snapshots и serp_results партиционируются по collected_at помесячно. При 100k ключевиков: ~3M snapshots/мес, ~300M results/мес.
- **Retention policy:** автоматический drop партиций старше N месяцев (настраивается).
- **Индексация:** composite indexes на (keyword_id, collected_at) для быстрой выборки истории.

### Competitive Landscape Risks

- **Topvisor** (лидер RU): глубокая Yandex-интеграция, pay-per-check модель, 10+ лет на рынке.
- **Rush Analytics**: лучший Wordstat-сбор, SERP-кластеризация.
- **SE Ranking**: aggressive pricing, white-label, хорошая Yandex-поддержка.
- **Дифференциатор SERP Panel**: автоклассификация сайтов + ценовое лидерство + self-hosted опция.

## Innovation & Novel Patterns

### Автоклассификация сайтов в SERP

Ни один конкурент (Topvisor, Rush Analytics, SE Ranking, SEMrush, Ahrefs, Moz) не классифицирует автоматически сайты в поисковой выдаче по типам. SERP Panel размечает каждый домен: маркетплейс, интернет-магазин, инфо-сайт, блог, лендинг, агрегатор, гос.сайт.

**Ценность:** SEO-специалист видит не просто «10 ссылок в выдаче», а структуру конкуренции: «3 маркетплейса, 4 интернет-магазина, 2 агрегатора, 1 инфо-сайт». Это меняет подход к анализу.

### Validation Approach

- **MVP:** regex/вхождение правила (classification_rules), ручная корректировка. Оценка: 80%+ accuracy.
- **Growth:** ML-модель на основе накопленных domain_classifications (обучение на ручных корректировках).
- **Fallback:** если ML-модель не работает, regex-правила остаются базовым слоем.

### Risk Mitigation

- Классификация — auxiliary feature. Продукт ценен без неё (мониторинг позиций — core).
- Правила с priority — предсказуемый порядок применения.
- classified_by (rule|manual) — ручные корректировки не перезаписываются автоматикой.

## SaaS B2B Specific Requirements

### Multi-Tenancy Model

- **Tenant = Organization.** Каждый пользователь принадлежит 1+ организациям.
- **Изоляция данных:** middleware `EnsureOrganization` автоматически скоупит все запросы к данным организации. Пользователь не видит данные чужих организаций.
- **Роли:** admin (полный доступ), manager (проекты + ключевики), analyst (только чтение + экспорт), viewer (только чтение).

### RBAC Matrix

| Действие | Admin | Manager | Analyst | Viewer |
|---|---|---|---|---|
| Управление организацией | ✅ | ❌ | ❌ | ❌ |
| Приглашение участников | ✅ | ❌ | ❌ | ❌ |
| CRUD проектов | ✅ | ✅ | ❌ | ❌ |
| CRUD ключевиков | ✅ | ✅ | ❌ | ❌ |
| Управление скрейперами | ✅ | ✅ | ❌ | ❌ |
| Управление расписаниями | ✅ | ✅ | ❌ | ❌ |
| Правила классификации | ✅ | ✅ | ❌ | ❌ |
| Просмотр позиций/SERP | ✅ | ✅ | ✅ | ✅ |
| Экспорт данных | ✅ | ✅ | ✅ | ❌ |
| Просмотр дашборда | ✅ | ✅ | ✅ | ✅ |

### Technical Architecture (Laravel API)

**Архитектура:** Controller → Service → Repository → QueryBuilder → Model → DB

```
HTTP Request → Route → Middleware (Auth + Tenant) → Controller → Service → Repository → QueryBuilder → Model → DB
Response    ← JsonApiResource                      ← Service   ← Repository ← QueryBuilder
```

**Слои:**

| Слой | Ответственность |
|---|---|
| Controller | Принимает запрос, вызывает только Service, возвращает JsonApiResource |
| Service | Бизнес-логика, транзакции, события. Вызывает Repository |
| Repository | CRUD, делегирует сложные запросы в QueryBuilder |
| QueryBuilder | Фильтры, сортировки, eager loading, пагинация (Spatie) |
| Model | Отношения, casts, accessors. Без scopeXxx() |
| DTO | Type-safe передача данных между слоями (Spatie Data) |
| JsonApiResource | Трансформация в JSON:API формат (timacdonald/json-api) |

**API формат:** JSON:API v1.1. Все ответы: `{data: {type, id, attributes, relationships, links}}`. Фильтрация: `?filter[status]=active`. Сортировка: `?sort=-created_at`. Пагинация: `?page[number]=1&page[size]=20`. Обновление: `PATCH` (не PUT).

**Очереди (Redis + Horizon):**

| Очередь | Назначение | Max Workers |
|---|---|---|
| serp-scrape | SERP-скрейпинг | 10 |
| wordstat | Wordstat-сбор | 5 |
| classification | Классификация доменов | 3 |
| default | Остальное (email, notifications) | 3 |

### Integration Requirements

| Интеграция | Тип | Статус |
|---|---|---|
| XMLRiver | SERP scraping provider | ✅ Реализован |
| Yandex XML | SERP scraping provider | 📋 Growth |
| Camoufox/Playwright | Browser-based scraping | 📋 Growth |
| Yandex Wordstat API | Частотность и тренды | ✅ Реализован |
| Telegram Bot API | Алерты | ❌ MVP (доделать) |
| SMTP | Email-алерты | ❌ MVP (доделать) |
| Google Search Console | Клики/импрессии | 📋 Growth |

## Functional Requirements

### Аутентификация и управление доступом

- FR1: Пользователь может зарегистрироваться, войти и выйти из системы
- FR2: Пользователь может принадлежать нескольким организациям с разными ролями
- FR3: Admin может приглашать участников в организацию по email
- FR4: Admin может назначать и менять роли участников (admin/manager/analyst/viewer)
- FR5: Система изолирует данные между организациями — пользователь видит только данные своей организации

### Управление проектами и структурой

- FR6: Manager может создавать, редактировать и удалять проекты внутри организации
- FR7: Manager может добавлять домены в проект с пометкой «свой» (is_own) или «конкурент»
- FR8: Manager может создавать иерархию категорий (дерево любой глубины через parent_id)
- FR9: Manager может создавать кластеры внутри категорий для группировки ключевиков
- FR10: Manager может добавлять ключевики вручную или импортировать из CSV (bulk import до 10k за раз)
- FR11: Manager может привязывать ключевик к движку (Google/Yandex), региону и устройству (desktop/mobile)

### Мониторинг позиций (SERP)

- FR12: Система собирает полный TOP-100 SERP-снэпшот для каждого ключевика по расписанию
- FR13: Пользователь может просматривать SERP-результаты по ключевику с фильтром по дате и TOP-N
- FR14: Система подсвечивает позицию «своего» домена (is_own) в SERP-таблице
- FR15: Пользователь может просматривать историю позиций ключевика за произвольный период
- FR16: Dashboard отображает сводку: ключевики в TOP-3/10/20/100, улучшения/падения, visibility score
- FR17: Система извлекает и сохраняет домен из URL при сохранении SERP-результата

### Wordstat-аналитика

- FR18: Система собирает частотность (точная, фразовая, широкая) для ключевиков по расписанию
- FR19: Система собирает помесячную динамику (тренды сезонности) для ключевиков
- FR20: Система собирает подсказки и ассоциации для ключевиков
- FR21: Пользователь может просматривать Wordstat-данные рядом с позициями ключевика
- FR22: Wordstat-расписание настраивается отдельно от SERP (обычно реже — раз в месяц)

### Автоклассификация сайтов

- FR23: Admin/Manager может создавать правила классификации (domain_exact, domain_contains, domain_regex, url_regex, title_contains)
- FR24: Система автоматически классифицирует домены из SERP по правилам в порядке приоритета
- FR25: Пользователь может вручную корректировать классификацию домена (ручная не перезаписывается автоматикой)
- FR26: Пользователь видит тип сайта (бейдж) рядом с каждым доменом в SERP-таблице
- FR27: Система предоставляет системные правила классификации (is_system) для известных сайтов

### Управление скрейперами

- FR28: Manager может добавлять, редактировать и удалять скрейперы (XMLRiver, Yandex XML и др.)
- FR29: Manager может тестировать скрейпер (health check) через интерфейс
- FR30: Система поддерживает плагинную архитектуру скрейперов через единый интерфейс (SerpScraperAdapter)
- FR31: Каждый скрейпер имеет настраиваемый rate_limit (макс запросов/мин)

### Расписания и задания

- FR32: Manager может создавать расписания сбора с каскадной привязкой (проект/категория/кластер/ключевик)
- FR33: Manager может запустить сбор немедленно (run-now)
- FR34: Система создаёт scrape_jobs по расписанию с учётом rate_limit
- FR35: Система поддерживает retry (до 3 попыток) для failed jobs

### Конкуренты

- FR36: Пользователь может просматривать сводную таблицу конкурентов по проекту (домен, присутствие в TOP-3/10/20, тип сайта)
- FR37: Manager может добавлять домены-конкуренты в проект

### Алерты и уведомления (MVP — доделать)

- FR38: Пользователь может настраивать алерты при падении позиции ниже порога
- FR39: Система отправляет уведомления через Telegram и Email
- FR40: Пользователь может управлять своими подписками на алерты

### Экспорт данных (MVP — доделать)

- FR41: Пользователь может экспортировать таблицу ключевиков с позициями в CSV/Excel
- FR42: Пользователь может экспортировать SERP-данные по ключевику в CSV

### Биллинг (MVP — базовый)

- FR43: Система поддерживает тарифные планы с лимитами (количество ключевиков, частота сбора)
- FR44: Admin может видеть текущее потребление vs лимиты тарифа

## Non-Functional Requirements

### Performance

- NFR1: API отвечает за < 500ms (P95) при пагинированных запросах до 100 записей
- NFR2: Дашборд summary API отвечает за < 2с при проекте до 10k ключевиков
- NFR3: CSV-импорт 10k ключевиков завершается за < 30с
- NFR4: SERP-таблица (TOP-100) рендерится за < 1с
- NFR5: Фронтенд bundle size < 500KB gzipped (initial load)

### Security

- NFR6: Все API-эндпоинты защищены Sanctum-токенами (кроме auth/login, auth/register)
- NFR7: Данные скрейперов (credentials) хранятся в encrypted jsonb
- NFR8: Организационная изоляция данных на уровне middleware — невозможен доступ к чужим данным
- NFR9: API rate limiting: 60 req/min для authenticated users
- NFR10: CORS ограничен доменами приложения (не wildcard)
- NFR11: Нет логирования PII (emails, tokens, пароли)

### Scalability

- NFR12: Система масштабируется горизонтально — отдельные Horizon workers на очередь
- NFR13: PostgreSQL партиционирование позволяет хранить 12+ месяцев без деградации запросов
- NFR14: Data retention: автоматический drop партиций старше N месяцев (конфигурируемо)
- NFR15: Система обрабатывает 100k scrape jobs/день при 10 workers на очередь serp-scrape
- NFR16: Архитектура скрейперов позволяет добавить новый адаптер без изменения core-логики

### Reliability

- NFR17: Scrape jobs выдерживают 3 retry при transient errors (timeout, 429, 503)
- NFR18: Scheduler идемпотентен — повторный запуск не создаёт дублирующих jobs
- NFR19: Redis persistence включён для защиты очередей от OOM/restart
- NFR20: Horizon auto-restart при crash workers

### Observability

- NFR21: Scrape jobs логируют статус, длительность, error_message
- NFR22: Horizon dashboard доступен для admin-пользователей
- NFR23: Метрики: failed jobs rate, queue depth, scraper success rate

## Competitive Analysis Summary

| Критерий | SERP Panel | Topvisor | Rush Analytics | SE Ranking |
|---|---|---|---|---|
| Yandex + Google | ✅ | ✅✅ | ✅ | ✅ |
| Wordstat | ✅ | ✅ | ✅✅ | ❌ |
| Автоклассификация | ✅✅ unique | ❌ | ❌ | ❌ |
| TOP-100 хранение | ✅ | TOP-50 | ❌ | ✅ |
| White-label | 📋 Growth | ⚠️ | ❌ | ✅✅ |
| Алерты | ❌ MVP gap | ✅ | ❌ | ✅ |
| Self-hosted | 📋 Vision | ❌ | ❌ | ❌ |
| Цена (target) | Lowest | ~500₽ | ~500₽ | ~$55 |

## Risk Mitigation Strategy

| Риск | Вероятность | Митигация |
|---|---|---|
| Стоимость скрейпинга неподъёмна | Высокая | Адаптеры для дешёвых/бесплатных провайдеров (Camoufox); дедупликация между тенантами |
| БД разрослась до неуправляемых размеров | Высокая | Data retention policy; monthly partitioning; мониторинг объёма |
| Единственный скрейпер (XMLRiver) недоступен | Средняя | Pluggable adapter architecture; Growth: 3+ адаптеров |
| Конкуренты снижают цены | Средняя | Cost leadership стратегия; уникальная фича (автоклассификация) |
| Тонкое тестовое покрытие | Средняя | MVP: довести до 60%; Growth: 85%+ с mutation testing |
| Нет onboarding → низкая конверсия | Средняя | Growth: onboarding wizard |

## Technical Debt (Current)

| Проблема | Severity | Рекомендация |
|---|---|---|
| 7 тестов на 15 контроллеров | High | Добавить Feature-тесты для SERP pipeline, Wordstat, schedules |
| Нет Repository layer | Medium | Внедрить Controller → Service → Repository |
| Нет API versioning (/v1/) | Medium | Добавить prefix /api/v1/ |
| Нет idempotency guard в scheduler | Medium | Добавить проверку дублей при создании jobs |
| raw_response хранится бессрочно | Low | Очищать после парсинга или по retention policy |
