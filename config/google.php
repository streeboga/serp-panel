<?php

return [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI'),

    /*
     * Только чтение Search Console. Больше прав просить не за чем: мы забираем
     * список ресурсов и статистику запросов, ничего не меняем.
     */
    'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',

    'timeout' => (int) env('GOOGLE_TIMEOUT', 20),
];
