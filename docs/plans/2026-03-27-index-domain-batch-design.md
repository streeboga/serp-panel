# IndexDomainJob — Batch Redesign

**Дата:** 2026-03-27
**Статус:** Утверждён

## Проблемы текущей реализации

1. Дублирующие запросы при параллельном запуске для одного домена
2. Последовательная пагинация в одном джобе (1000 страниц = 100 запросов, timeout)
3. Не парсится `<found>` — не знаем общее число страниц заранее
4. Общая очередь `serp-scrape` — индексация блокирует SERP-скрапинг
5. Нет rate-limiting — поле `rate_limit` на Scraper не используется

## Контекст

- Типичный объём: 100–1000 страниц (бывает 10000+)
- Запуск: ручной + по расписанию
- Приоритет: SERP > индексация

## Архитектура джобов

```
DomainController::indexDomain()
  └→ IndexDomainJob (очередь: indexing, оркестратор)
       ├── 1. Cache::lock("index-domain:{domainId}:{engine}", 600)
       ├── 2. Запрос page=1 к XMLRiver с site:{domain}
       ├── 3. Парсинг <found> → totalResults
       ├── 4. Расчёт: totalPages = min(ceil(totalResults / 10), ceil(limit / 10))
       ├── 5. DELETE старых результатов (engine + collected_at)
       ├── 6. Сохранение результатов page=1
       └── 7. Bus::batch([
                FetchIndexPageJob(domainId, page=2, ...),
                FetchIndexPageJob(domainId, page=3, ...),
                ...
            ])
            ->name("index:{domain.name}")
            ->onQueue('indexing')
            ->allowFailures()
            ->finally(fn => update indexed_pages_count, сброс index_batch_id)
            ->dispatch()

FetchIndexPageJob (очередь: indexing)
  ├── middleware: [RateLimitedWithRedis('xmlriver')]
  ├── tries=3, backoff=[5, 15, 30], timeout=90
  ├── 1 запрос к XMLRiver (конкретная page)
  └── insert результатов в domain_index_results
```

## Защита от дубликатов

- Оркестратор перед батчем: `DELETE WHERE domain_id + engine + collected_at`
- Результаты page=1 сохраняет оркестратор
- FetchIndexPageJob делает insert (дубликатов нет — очистили)

## Защита от параллельного запуска

- `Cache::lock("index-domain:{domainId}:{engine}", 600)` — атомарный лок на 10 мин
- Если лок занят — джоб возвращается через `$this->release(30)`

## Rate Limiting и разделение пула

### Horizon supervisors

```
production:
  serp-supervisor:     queue=serp-scrape,    maxProcesses=7  (было 10)
  index-supervisor:    queue=indexing,        maxProcesses=3  (новый)
  wordstat-supervisor: queue=wordstat,        maxProcesses=5
  classification:      queue=classification,  maxProcesses=3
  default:             queue=default,         maxProcesses=3
```

### Rate Limiting (общий для SERP и индексации)

```php
// AppServiceProvider::boot()
RateLimiter::for('xmlriver', fn () => Limit::perSecond(10));

// В обоих джобах:
public function middleware(): array
{
    return [new RateLimitedWithRedis('xmlriver')];
}
```

SERP приоритет — за счёт 7 vs 3 воркеров.

## Изменения в XmlRiverAdapter

1. Парсинг `<found priority="all">` из XML → `ScrapeResponse.totalResults`
2. Новый метод `scrapePage(ScrapeRequest, int $page): ScrapeResponse` — один запрос, одна страница

## Данные

- Новое поле `domains.index_batch_id` (string, nullable) — ID текущего батча
- Таблица `domain_index_results` — без изменений

## API

1. `POST /domains/{domain}/index` — без изменений в сигнатуре, теперь запускает батч
2. `GET /domains/{domain}/index-status` — прогресс:
   ```json
   {
       "status": "indexing|completed|failed|idle",
       "total_found": 3450,
       "collected": 280,
       "progress": 28,
       "batch_id": "9b1d..."
   }
   ```
3. `DELETE /domains/{domain}/index` — отмена батча

## Обработка ошибок

- Оркестратор: `tries=3`, `backoff=[10, 30, 60]`, `timeout=120`
- FetchIndexPageJob: `tries=3`, `backoff=[5, 15, 30]`, `timeout=90`
- `allowFailures()` — отдельные страницы могут падать
- `finally()` — обновляет count по фактическим результатам

## Edge Cases

1. `<found>` = 0 → сохраняет `indexed_pages_count = 0`, батч не создаёт
2. `<found>` > 1000 → `totalPages = min(100, ceil(limit/10))`, warning в лог
3. Повторный запуск → `Cache::lock()` блокирует
4. Scraper не найден → лог, выход
5. Отмена → `$batch->cancel()` через DELETE endpoint
