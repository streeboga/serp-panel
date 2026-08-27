<?php

declare(strict_types=1);

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
     * Второй этап: обход ссылок и картинок за кодом ответа и размером. Каждый URL
     * запрашивается один раз за прогон, сколько бы страниц на него ни ссылалось.
     */
    'check_resources' => (bool) env('AUDIT_CHECK_RESOURCES', true),
    'max_resources' => (int) env('AUDIT_MAX_RESOURCES', 2000),

    /*
     * Эталонный валидатор W3C. Ключ не нужен, но сервер общий на весь интернет —
     * держим свой лимитер и не частим. Нарушения спецификации отделяются от
     * предпочтений линтера: находкой становятся только первые.
     */
    'w3c' => [
        'enabled' => (bool) env('AUDIT_W3C_ENABLED', true),
        'endpoint' => env('AUDIT_W3C_ENDPOINT', 'https://validator.w3.org/nu/'),
        'timeout' => (int) env('AUDIT_W3C_TIMEOUT', 60),
        'requests_per_minute' => (int) env('AUDIT_W3C_RPM', 20),
    ],

    /*
     * Chrome UX Report: как сайт ведёт себя у живых людей. Нужен ключ Google Cloud,
     * 150 запросов в минуту бесплатно. Без ключа этап молчит — и это «данных нет»,
     * а не «всё хорошо».
     */
    'crux' => [
        'key' => env('AUDIT_CRUX_KEY'),
        'timeout' => (int) env('AUDIT_CRUX_TIMEOUT', 30),
    ],

    /*
     * Третий этап — браузер. Настоящий ответ по контрасту и сдвигам вёрстки даёт
     * только он: каскад CSS в PHP не воспроизвести. Выключен, пока не поднят сервис;
     * без него страницы остаются «не проверенными», а не «чистыми».
     *
     * Гоняется по выборке: полминуты на страницу означает, что весь сайт это часы.
     */
    'browser' => [
        'enabled' => (bool) env('AUDIT_BROWSER_ENABLED', false),
        'url' => env('AUDIT_BROWSER_URL'),
        'token' => env('AUDIT_BROWSER_TOKEN'),
        'timeout' => (int) env('AUDIT_BROWSER_TIMEOUT', 90),
        'max_pages' => (int) env('AUDIT_BROWSER_MAX_PAGES', 20),
        'viewport' => env('AUDIT_BROWSER_VIEWPORT', 'mobile'),
    ],

    /*
     * Проверки живут в пакетах и регистрируются в CheckRegistry их
     * сервис-провайдерами — список здесь держать больше негде и незачем.
     *
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
