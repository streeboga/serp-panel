<?php

return [
    'client_id' => env('YANDEX_CLIENT_ID'),
    'client_secret' => env('YANDEX_CLIENT_SECRET'),
    'redirect_uri' => env('YANDEX_REDIRECT_URI', 'https://oauth.yandex.ru/verification_code'),

    // If redirect_uri is verification_code, user must paste code manually
    // If redirect_uri is our callback URL, code exchange happens automatically
    'manual_code' => env('YANDEX_REDIRECT_URI', 'https://oauth.yandex.ru/verification_code') === 'https://oauth.yandex.ru/verification_code',
];
