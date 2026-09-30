> An open source project by [EQ Platform](https://eq.team) — [project page](https://eq.team/open-source/oss-serp-panel/)

# SERP Panel

Мультиарендная панель для SEO: мониторинг позиций в Яндексе и Google, частотность из Wordstat, разбор конкурентов по фактической выдаче и технический аудит сайта — целиком или постранично, через интерфейс, API и MCP.

Laravel 13 · PHP 8.3 · React 19 · PostgreSQL 16 · Redis 7

## Что умеет

**Позиции и выдача**
- Сбор полного ТОП-100 по ключам: Яндекс и Google, десктоп и мобайл, по регионам, по расписанию.
- Матрица позиций, история посуточно, доля видимости, ТОП-10 и ТОП-20.
- Алерты на изменение позиций в Telegram и на почту.

**Семантика**
- Иерархия «проект → домен → категория → кластер → ключ».
- Частотность Wordstat в трёх видах, тренды и подсказки.
- Целевые страницы: реестр своих и чужих URL, привязка к ключам, кластерам и категориям, статусы «топ-3 / топ-10 / каннибализация / нет в выдаче».

**Конкуренты**
- Определяются из собранной выдачи, а не из списка: кто реально стоит в топе по вашим запросам.
- Автоклассификация доменов по типу сайта, деление на федеральных и региональных.

**Аудит сайта**
- 46 проверок в 7 категориях, около 150 различаемых дефектов и 65 метрик на страницу.
- Уровень сайта: robots.txt, карта сайта, SSL, редиректы, дубли title и description, граф внутренних ссылок с глубиной и сиротами.
- Настоящий браузер: CLS с виновниками сдвига, LCP, контраст по вычисленным стилям, touch-цели, Lighthouse.
- Эталонный валидатор W3C, полевые данные CrUX, Яндекс.Метрика, Вебмастер и Search Console.
- Этап, до которого не удалось дотянуться, попадает в отчёт как «не проверено» с причиной, а не выдаётся за чистый.
- Выгрузки в CSV под Excel и PDF-отчёт.

**Остальное**
- Публичная ссылка на проект для клиента: позиции, частотность, аудит без входа в панель.
- AI-видимость: доля голоса бренда в ответах нейросети против конкурентов.
- MCP-сервер: панель как набор инструментов для ИИ-агента.

## Быстрый старт

Нужны PHP 8.3, Composer, Node 20+, PostgreSQL 16 и Redis 7.

```bash
cp .env.example .env          # прописать доступы к PostgreSQL и Redis
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

```bash
cd frontend
npm install
npm run dev                   # http://localhost:5174
```

Вход после сидов: `admin@serp.test` / `password`.

Воркеры очередей:

```bash
php artisan queue:work --queue=serp-scrape,indexing,wordstat,classification,audit,audit-assets,audit-browser,default
```

Планировщик нужен для сбора по расписанию и для закрытия зависших прогонов аудита:

```bash
php artisan schedule:work
```

## Архитектура

```
Controller → Service → Repository → QueryBuilder → Model
```

- **API** — JSON:API v1.1, всё под `/api/v1/`, обновление через PATCH. Документация генерируется Scramble: `/docs/api`.
- **Аренда** — данные изолированы по организации, она передаётся заголовком `X-Organization-Id`.
- **Авторизация** — Laravel Sanctum, Bearer-токен. Роли: admin, manager, analyst, viewer.
- **Фронтенд** — React 19, TanStack Router и Query, Tailwind 4. Ответы JSON:API разворачиваются в плоские объекты перехватчиком в `frontend/src/lib/api.ts`.

```
app/
├── Http/Controllers/Api/V1/   тонкие контроллеры
├── Services/                  бизнес-логика; Audit/, Integrations/, Scrapers/, Wordstat/
├── Repositories/Eloquent/     доступ к данным
├── Jobs/                      сбор выдачи, Wordstat, индексация, аудит
├── Mcp/                       MCP-сервер и его инструменты
packages/serp-audit/           проверки аудита отдельным пакетом
services/browser-audit/        Playwright и Lighthouse в Docker
frontend/src/                  routes, hooks, components
```

## Аудит сайта

Прогон идёт в три этапа, каждый — батч джоб со своим финализатором:

1. **Страницы** — один разбор DOM на страницу, все проверки пакета.
2. **Ресурсы** — ссылки и картинки: коды ответа и вес, по одному запросу на уникальный адрес.
3. **Браузер и внешние источники** — замеры в Chromium, W3C, Lighthouse, Метрика, Вебмастер, Search Console.

Адреса берутся из карты сайта, затем из индекса поисковика, затем из реестра страниц проекта. `robots.txt` соблюдается, частота запросов ограничена.

Проверки живут в пакете `packages/serp-audit` и регистрируются в `SerpAudit\CheckRegistry`. Новый набор проверок — это новый пакет со своим сервис-провайдером, код приложения менять не нужно.

```bash
# запустить аудит сайта
curl -X POST https://HOST/api/v1/projects/1/audits \
  -H "Authorization: Bearer $TOKEN" -H "X-Organization-Id: 1" \
  -H "Content-Type: application/json" \
  -d '{"scope":"site","domain_id":1}'

# разовая проверка одного URL, без записи в базу
curl -X POST https://HOST/api/v1/audit/url \
  -H "Authorization: Bearer $TOKEN" -H "X-Organization-Id: 1" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com/"}'
```

| Что | Метод |
|---|---|
| Каталог проверок | `GET /audit/checks` |
| Состояние прогона | `GET /audits/{id}` |
| Результаты по страницам | `GET /audits/{id}/results?severity=critical&search=catalog&page=2` |
| Выгрузка CSV | `GET /audits/{id}/export/{pages\|meta\|broken\|findings}` |
| Находки вместе с пройденными проверками | `GET /audits/{id}/export/findings?include_passed=1` |
| PDF-отчёт | `GET /audits/{id}/report` |

### Браузерный сервис

```bash
cd services/browser-audit
docker build -t serp-browser-audit .
docker run -d --name serp-browser-audit --restart unless-stopped \
  --memory=768m --memory-swap=768m --cpus=1.5 \
  -p 127.0.0.1:8081:8081 -e BROWSER_AUDIT_TOKEN=... serp-browser-audit
```

Версия `playwright-core` в `package.json` должна точно совпадать с версией образа. Сервис обрабатывает один запрос за раз, поэтому очередь `audit-browser` обслуживает один воркер.

## Интеграции

| Источник | Что даёт | Что нужно |
|---|---|---|
| XMLRiver, Yandex XML, webhook | выдача | ключ парсера в настройках |
| Яндекс OAuth | Wordstat, Метрика, Вебмастер | `YANDEX_CLIENT_ID`, `YANDEX_CLIENT_SECRET`; для Вебмастера право `webmaster:hostinfo` |
| Google OAuth | Search Console | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` |
| Chrome UX Report | скорость у реальных пользователей | `AUDIT_CRUX_KEY` |
| Anthropic | AI-видимость | `ANTHROPIC_API_KEY` |
| Telegram, SMTP | алерты | `TELEGRAM_BOT_TOKEN`, `MAIL_*` |

Метрика, Вебмастер и Search Console отдают данные только владельцу сайта. Внешние ресурсы сопоставляются с доменами на странице `/settings/integrations`.

## Переменные аудита

| Переменная | По умолчанию | Назначение |
|---|---|---|
| `AUDIT_MAX_PAGES` | 500 | потолок страниц в прогоне |
| `AUDIT_RPS` | 2 | запросов в секунду к проверяемому сайту |
| `AUDIT_BROWSER_ENABLED` | false | включить браузерный этап |
| `AUDIT_BROWSER_URL`, `AUDIT_BROWSER_TOKEN` | — | адрес и токен браузерного сервиса |
| `AUDIT_BROWSER_MAX_PAGES` | 20 | сколько страниц смотреть браузером |
| `AUDIT_LIGHTHOUSE_PAGES` | 3 | сколько страниц прогонять через Lighthouse |
| `AUDIT_W3C_ENABLED` | true | проверка валидатором W3C |

Остальные пороги — длина title, доля воды, тошнота — лежат в `config/audit.php`.

## MCP

Сервер поднят на `/mcp`, авторизация тем же Bearer-токеном Sanctum. Инструменты: список проектов и ключей, позиции, частотность, конкуренты, дашборд, запуск аудита, находки аудита, разовая проверка URL.

## Тесты и качество

```bash
php artisan test              # Pest
vendor/bin/phpstan analyse    # уровень 6
vendor/bin/pint               # стиль
cd frontend && npm run test   # Vitest
```

Известное состояние: 10 тестов красные, потому что партиции `serp_snapshots` создаются от текущей даты, а тесты привязаны к марту 2026; один из них ходит в живую сеть. PHPStan показывает 49 ошибок в старом коде. Новый код не должен добавлять ни того, ни другого.

## Документация

- [Руководство пользователя](docs/user-guide.md)
- [API страниц](docs/api-pages.md)
- [CLAUDE.md](CLAUDE.md) — устройство проекта и соглашения для разработки
