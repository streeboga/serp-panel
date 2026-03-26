<?php

return [
    'client_id' => env('YANDEX_CLIENT_ID'),
    'client_secret' => env('YANDEX_CLIENT_SECRET'),
    'redirect_uri' => env('YANDEX_REDIRECT_URI', 'http://localhost:8000/api/v1/auth/yandex/callback'),
];
