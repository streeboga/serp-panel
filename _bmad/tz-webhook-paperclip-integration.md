# ТЗ: Webhook-канал алертов + интеграция с Paperclip

## Цель

Добавить `webhook` канал в систему алертов serp-panel, чтобы при срабатывании алерта автоматически создавались задачи в Paperclip (или любой внешний сервис по webhook URL).

## Проблема

Сейчас AI-агент SERP Monitor тратит $0.50-1.00 за heartbeat на рутинную работу: запрос позиций из API, сравнение, формирование выводов. Всё это serp-panel уже делает алгоритмически — но не умеет отправлять результат наружу.

## Что есть сейчас

- Модель `PositionAlert` с каналами `email`, `telegram`
- `CheckPositionAlertsListener` → сравнивает позиции → `SendPositionAlertJob`
- Направления: `drops_below`, `rises_above`
- Поле `last_triggered_at` (без истории)

## Что нужно сделать

### 1. Миграция: добавить поддержку webhook

```php
// database/migrations/xxxx_add_webhook_to_position_alerts_table.php
Schema::table('position_alerts', function (Blueprint $table) {
    $table->string('webhook_url')->nullable();
    $table->json('webhook_headers')->nullable(); // кастомные заголовки (Authorization, X-Org-Id и т.д.)
    $table->string('webhook_method')->default('POST'); // POST/PUT/PATCH
});
```

Канал `channel` расширить: `email`, `telegram`, `webhook`.

### 2. Миграция: лог срабатываний алертов

```php
// database/migrations/xxxx_create_alert_triggers_log_table.php
Schema::create('position_alert_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('position_alert_id')->constrained()->cascadeOnDelete();
    $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
    $table->integer('old_position')->nullable();
    $table->integer('new_position')->nullable();
    $table->string('status'); // sent, failed, retrying
    $table->json('response_body')->nullable(); // ответ вебхука
    $table->integer('response_code')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamps();
});
```

### 3. SendPositionAlertJob: добавить webhook case

```php
// app/Jobs/SendPositionAlertJob.php — добавить в match($alert->channel):

'webhook' => $this->sendWebhook($alert, $keyword, $oldPosition, $newPosition),
```

```php
private function sendWebhook(PositionAlert $alert, Keyword $keyword, ?int $old, ?int $new): void
{
    $payload = [
        'event' => 'position_alert',
        'direction' => $alert->direction,
        'threshold' => $alert->threshold_position,
        'keyword' => [
            'id' => $keyword->id,
            'text' => $keyword->keyword,
            'engine' => $keyword->engine,
            'region' => $keyword->region?->name,
        ],
        'position' => [
            'old' => $old,
            'new' => $new,
            'change' => $old && $new ? $old - $new : null,
        ],
        'url' => $keyword->latestSerpUrl(), // URL нашей страницы в выдаче
        'triggered_at' => now()->toIso8601String(),
    ];

    $headers = array_merge(
        ['Content-Type' => 'application/json', 'User-Agent' => 'SerpPanel/1.0'],
        $alert->webhook_headers ?? []
    );

    $response = Http::withHeaders($headers)
        ->{strtolower($alert->webhook_method ?? 'post')}(
            $alert->webhook_url,
            $payload
        );

    PositionAlertLog::create([
        'position_alert_id' => $alert->id,
        'keyword_id' => $keyword->id,
        'old_position' => $old,
        'new_position' => $new,
        'status' => $response->successful() ? 'sent' : 'failed',
        'response_code' => $response->status(),
        'response_body' => $response->json(),
        'error_message' => $response->failed() ? $response->body() : null,
    ]);

    if ($response->failed()) {
        throw new \RuntimeException("Webhook failed: {$response->status()}");
        // Job retry ($tries = 3) подхватит
    }
}
```

### 4. Преднастроенный шаблон: Paperclip issue creation

Для интеграции с Paperclip webhook_url будет вида:
```
http://localhost:3100/api/companies/{companyId}/issues
```

webhook_headers:
```json
{
    "Authorization": "Bearer {agent-jwt-token}",
    "X-Paperclip-Run-Id": "webhook-alert"
}
```

Но Paperclip ожидает другой формат body (title, description, assigneeAgentId). Поэтому нужен **трансформер payload** — либо:

**Вариант A:** Добавить поле `webhook_payload_template` (Blade/Mustache шаблон)

**Вариант B (проще):** Добавить тип webhook `paperclip` с захардкоженным маппингом:

```php
'paperclip' => $this->sendPaperclipWebhook($alert, $keyword, $oldPosition, $newPosition),
```

```php
private function sendPaperclipWebhook(PositionAlert $alert, Keyword $keyword, ?int $old, ?int $new): void
{
    $direction = $alert->direction === 'drops_below' ? 'Падение' : 'Рост';
    $change = $old && $new ? abs($old - $new) : '?';

    $payload = [
        'title' => "[Алерт] {$direction} позиции: «{$keyword->keyword}» — с {$old} на {$new}",
        'description' => implode("\n", [
            "## Алерт позиции",
            "",
            "- **Ключевое слово:** {$keyword->keyword}",
            "- **Позиция:** {$old} → {$new} (изменение: {$change})",
            "- **Порог:** {$alert->threshold_position}",
            "- **Движок:** {$keyword->engine}",
            "- **Дата:** " . now()->format('Y-m-d H:i'),
            "",
            "Проанализируй причины и предложи действия.",
        ]),
        'status' => 'todo',
        'priority' => abs($old - $new) >= 10 ? 'high' : 'medium',
        'assigneeAgentId' => $alert->webhook_headers['paperclip_agent_id'] ?? null,
        'projectId' => $alert->webhook_headers['paperclip_project_id'] ?? null,
    ];

    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . ($alert->webhook_headers['paperclip_token'] ?? ''),
        'X-Paperclip-Run-Id' => 'serp-panel-alert-' . $alert->id,
    ])->post($alert->webhook_url, $payload);

    // ... log как в webhook
}
```

### 5. AlertController: валидация

```php
// app/Http/Requests/StoreAlertRequest.php — расширить правила:
'channel' => ['required', 'in:email,telegram,webhook,paperclip'],
'webhook_url' => ['required_if:channel,webhook,paperclip', 'url', 'max:500'],
'webhook_headers' => ['nullable', 'array'],
'webhook_headers.*' => ['string', 'max:500'],
```

### 6. UI: форма создания алерта

В форме создания алерта добавить:
- Выпадающий список каналов: Email, Telegram, **Webhook**, **Paperclip**
- При выборе Webhook: поле URL + заголовки (JSON editor)
- При выборе Paperclip: поле URL + agent_id + project_id (автозаполнение из конфига)

### 7. Ежедневная сводка (новый функционал)

Помимо алертов на отдельные ключи, добавить **ежедневный отчёт** как webhook:

```php
// app/Jobs/SendDailyPositionSummaryJob.php
// Cron: ежедневно 08:00 MSK

class SendDailyPositionSummaryJob implements ShouldQueue
{
    public function handle(PositionMatrixService $matrix, CompetitorService $competitors): void
    {
        // Для каждой организации с настроенным webhook:
        // 1. Собрать позиции за вчера vs позавчера
        // 2. Посчитать: в ТОП-10, ТОП-30, ТОП-100
        // 3. Топ-5 роста, топ-5 падений
        // 4. Отправить POST на webhook_url как комментарий в Paperclip
    }
}
```

### 8. Конфигурация для Paperclip (env)

```env
# .env (опционально, для дефолтов)
PAPERCLIP_API_URL=http://72.56.126.35:3100/api
PAPERCLIP_COMPANY_ID=284b7e15-6ef7-43fd-b068-5f1c71dba231
PAPERCLIP_PROJECT_ID=a5c82512-6f48-4361-b376-07cce6f06502
PAPERCLIP_ALERT_AGENT_ID=cd6ad3c3-db18-4ea2-9e15-5ffe1c99222b
```

## Файлы для изменения

| Файл | Что сделать |
|---|---|
| `database/migrations/xxxx_add_webhook_*.php` | 2 миграции |
| `app/Models/PositionAlert.php` | fillable, casts |
| `app/Models/PositionAlertLog.php` | Новая модель |
| `app/Jobs/SendPositionAlertJob.php` | webhook + paperclip cases |
| `app/Jobs/SendDailyPositionSummaryJob.php` | Новый job |
| `app/Http/Requests/StoreAlertRequest.php` | Валидация |
| `app/Http/Requests/UpdateAlertRequest.php` | Валидация |
| `app/Http/Resources/PositionAlertResource.php` | Новые поля |
| `app/Console/Kernel.php` | Cron для daily summary |
| `frontend/src/components/AlertForm.tsx` | UI для webhook |
| `config/services.php` | Paperclip defaults |

## Приоритеты

1. **P0:** webhook канал + SendPositionAlertJob → рабочий push в Paperclip
2. **P1:** alert log (история срабатываний)
3. **P1:** ежедневная сводка
4. **P2:** UI для настройки webhook
5. **P2:** шаблоны payload (для других сервисов кроме Paperclip)

## Результат

- SERP Monitor агент **не нужен** для рутинного мониторинга → экономия $15-30/мес
- Алерты создают **готовые задачи** в Paperclip с данными
- SEO Analyst получает задачу "[Алерт] Падение: «ключ» с 8 на 15" и сразу анализирует
- Ежедневная сводка приходит как комментарий — board видит динамику без запуска агентов
