<?php

declare(strict_types=1);

use App\Services\Audit\Checks\ContentCheck;
use App\Services\Audit\Checks\HttpCheck;
use App\Services\Audit\Checks\ImageCheck;
use App\Services\Audit\Checks\LinkCheck;
use App\Services\Audit\Checks\MetaCheck;

return [
    /*
     * Ходим по чужим (пусть и собственным для клиента) сайтам — представляемся честно
     * и держим себя в руках: лимит запросов на хост и потолок страниц на прогон.
     */
    'user_agent' => env('AUDIT_USER_AGENT', 'SerpPanelAudit/1.0 (+https://serp-panel.ru/bot)'),
    'timeout' => (int) env('AUDIT_TIMEOUT', 15),
    'max_redirects' => 5,
    'max_pages' => (int) env('AUDIT_MAX_PAGES', 500),
    'requests_per_second' => (int) env('AUDIT_RPS', 2),

    /*
     * Uncheck на свой страх и риск: без него аудит игнорирует Disallow владельца сайта.
     */
    'respect_robots' => (bool) env('AUDIT_RESPECT_ROBOTS', true),

    /*
     * Проверки уровня страницы. Ключ — группа из CheckGroup, значение — класс.
     * Каждая проверка получает один разобранный DOM и возвращает список находок.
     */
    'checks' => [
        HttpCheck::class,
        MetaCheck::class,
        ContentCheck::class,
        LinkCheck::class,
        ImageCheck::class,
    ],

    /*
     * Пороги. Взяты из отчёта gvozd.org и приёмки eq.team; правятся без правки кода.
     */
    'thresholds' => [
        'title_min' => 10,
        'title_max' => 70,
        'description_min' => 50,
        'description_max' => 320,
        'h2_max' => 8,
        'response_time_ms' => 1500,
        'html_size_kb' => 300,
        'text_html_ratio_min' => 25.0,
        'water_max' => 60.0,
        'classic_nausea_max' => 7.0,
        'academic_nausea_max' => 30.0,
        'keyword_density_max' => 5.0,
        'words_min' => 300,
        'ssl_expiry_warning_days' => 30,
        'ssl_expiry_critical_days' => 14,
    ],
];
