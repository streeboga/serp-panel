{{-- Печатный отчёт по прогону аудита. Верстается под A4: Chromium в контейнере
     печатает этот HTML напрямую, поэтому здесь только печатные стили. --}}
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Аудит {{ $domain }}</title>
    <style>
        @page { size: A4; }
        body { font-family: "DejaVu Sans", "Liberation Sans", Arial, sans-serif; font-size: 10pt; line-height: 1.45; color: #14181d; margin: 0; }
        h1 { font-size: 22pt; margin: 0 0 4pt; }
        h2 { font-size: 14pt; margin: 20pt 0 6pt; padding-bottom: 3pt; border-bottom: 1.5pt solid #c3ccd2; page-break-after: avoid; }
        h3 { font-size: 11pt; margin: 12pt 0 4pt; page-break-after: avoid; }
        .lede { color: #4a545e; margin: 0 0 14pt; }
        .cards { display: flex; gap: 8pt; margin: 12pt 0 18pt; }
        .card { flex: 1; border: 0.75pt solid #dde2e6; padding: 8pt; }
        .card .n { font-size: 20pt; font-weight: 700; line-height: 1; }
        .card .t { font-size: 8pt; color: #78838e; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 4pt; }
        .critical { color: #9d3423; } .warning { color: #96590a; } .notice { color: #4c5866; } .ok { color: #2c6e49; }
        table { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin: 6pt 0 12pt; }
        th { text-align: left; border-bottom: 1pt solid #c3ccd2; padding: 3pt 4pt; font-size: 8pt; text-transform: uppercase; color: #78838e; }
        td { border-bottom: 0.5pt solid #eceff1; padding: 3pt 4pt; vertical-align: top; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .url { font-family: "DejaVu Sans Mono", monospace; font-size: 7.5pt; word-break: break-all; }
        .finding { margin: 0 0 7pt; page-break-inside: avoid; }
        .badge { display: inline-block; font-size: 7.5pt; font-weight: 700; padding: 1pt 4pt; margin-right: 5pt; }
        .badge.critical { background: #f6e6e2; } .badge.warning { background: #f6eddd; } .badge.notice { background: #e8ebee; }
        .finding .msg { font-weight: 600; }
        .finding .detail { color: #4a545e; font-size: 8.5pt; }
        .muted { color: #78838e; }
        .break { page-break-before: always; }
    </style>
</head>
<body>

<h1>SEO-аудит {{ $domain }}</h1>
<p class="lede">
    Прогон от {{ $audit->created_at?->format('d.m.Y') }} · проверено страниц: {{ $audit->pages_done }} ·
    проверок в наборе: {{ $checksCount }}
    @if($audit->error) · <span class="critical">{{ $audit->error }}</span> @endif
</p>

<div class="cards">
    <div class="card"><div class="n {{ $audit->score >= 90 ? 'ok' : ($audit->score >= 60 ? 'warning' : 'critical') }}">{{ $audit->score ?? '—' }}</div><div class="t">Оценка</div></div>
    <div class="card"><div class="n critical">{{ $audit->issues_critical }}</div><div class="t">Ошибок</div></div>
    <div class="card"><div class="n warning">{{ $audit->issues_warning }}</div><div class="t">Предупреждений</div></div>
    <div class="card"><div class="n notice">{{ $audit->issues_notice }}</div><div class="t">Замечаний</div></div>
</div>

@if($siteFindings->isNotEmpty())
    <h2>Уровень сайта</h2>
    <p class="muted">robots.txt, карта сайта, SSL, оформление 404, редиректы, структура ссылок, дубли</p>

    @foreach($siteFindings as $finding)
        <div class="finding">
            <span class="badge {{ $finding['severity'] }}">{{ $severityLabels[$finding['severity']] ?? $finding['severity'] }}</span>
            <span class="msg">{{ $finding['message'] }}</span>
            @if(is_array($finding['value']))
                <div class="detail">затронуто: {{ count($finding['value']) }}</div>
            @elseif($finding['value'] !== null)
                <div class="detail">{{ \Illuminate\Support\Str::limit((string) $finding['value'], 160) }}</div>
            @endif
        </div>
    @endforeach
@endif

@if($technical)
    <h2>Технические данные</h2>
    <table>
        <tbody>
        @foreach($technical as $label => $value)
            <tr><td>{{ $label }}</td><td class="num">{{ $value }}</td></tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2 class="break">Проблемы по важности</h2>
@foreach(['critical' => 'Критические', 'warning' => 'Важные', 'notice' => 'Рекомендуемые'] as $severity => $title)
    @if(! empty($grouped[$severity]))
        <h3 class="{{ $severity }}">{{ $title }} — {{ array_sum(array_column($grouped[$severity], 'pages')) }} страниц</h3>
        <table>
            <thead><tr><th>Что не так</th><th style="width:14%">Страниц</th><th style="width:30%">Пример</th></tr></thead>
            <tbody>
            @foreach(array_slice($grouped[$severity], 0, 30) as $row)
                <tr>
                    <td>{{ $row['message'] }}</td>
                    <td class="num">{{ $row['pages'] }}</td>
                    <td class="url">{{ \Illuminate\Support\Str::limit($row['example'], 60) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endforeach

@if($competitorsSpeed)
    <h2>Скорость рядом с конкурентами</h2>
    <p class="muted">Главные страницы, мобильный вьюпорт. Конкуренты взяты из фактической выдачи проекта</p>
    <table>
        <thead><tr><th>Сайт</th><th style="width:14%">LCP</th><th style="width:14%">Ответ</th><th style="width:14%">Вес</th></tr></thead>
        <tbody>
        @foreach($competitorsSpeed as $row)
            <tr>
                <td>{{ $row['host'] }}@if($row['own']) <strong>— это вы</strong>@endif</td>
                <td class="num">{{ $row['lcp'] ? $row['lcp'] . ' мс' : '—' }}</td>
                <td class="num">{{ $row['ttfb'] ? $row['ttfb'] . ' мс' : '—' }}</td>
                <td class="num">{{ $row['html_kb'] ? $row['html_kb'] . ' КБ' : '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2 class="break">Худшие страницы</h2>
<table>
    <thead><tr><th>Адрес</th><th style="width:9%">Код</th><th style="width:9%">Оценка</th><th style="width:14%">Находки</th><th style="width:11%">Кликов</th></tr></thead>
    <tbody>
    @foreach($worstPages as $page)
        <tr>
            <td class="url">{{ $page->url }}</td>
            <td class="num">{{ $page->http_status ?? '—' }}</td>
            <td class="num">{{ $page->score ?? '—' }}</td>
            <td class="num">{{ $page->issues_critical }} / {{ $page->issues_warning }} / {{ $page->issues_notice }}</td>
            <td class="num">{{ $page->depth ?? '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

@if($orphans->isNotEmpty())
    <h2>Страницы без входящих ссылок</h2>
    <p class="muted">На них не ведёт ни одна внутренняя ссылка — ни робот, ни посетитель их не найдёт</p>
    <table>
        <tbody>
        @foreach($orphans as $url)
            <tr><td class="url">{{ $url }}</td></tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2 class="break">Что делать</h2>
<p class="muted">Порядок по важности: сначала то, что мешает индексации и доступности</p>
<table>
    <thead><tr><th style="width:12%">Приоритет</th><th>Задача</th><th style="width:16%">Кому</th></tr></thead>
    <tbody>
    @foreach($actionPlan as $item)
        <tr>
            <td class="{{ $item['severity'] }}">{{ $severityLabels[$item['severity']] ?? $item['severity'] }}</td>
            <td>{{ $item['message'] }} <span class="muted">({{ $item['pages'] }} стр.)</span></td>
            <td>{{ $item['owner'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<p class="muted" style="margin-top:18pt;font-size:8pt">
    Отчёт сформирован автоматически {{ now()->format('d.m.Y H:i') }}.
    Выводы, оценка трудозатрат и стратегические рекомендации — за аналитиком.
</p>

</body>
</html>
