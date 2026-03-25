<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            ['engine' => 'yandex', 'code' => 'RU', 'name' => 'Россия', 'yandex_lr' => 225, 'google_gl' => null, 'google_hl' => null],
            ['engine' => 'yandex', 'code' => 'RU-MOW', 'name' => 'Москва', 'yandex_lr' => 213, 'google_gl' => null, 'google_hl' => null],
            ['engine' => 'yandex', 'code' => 'RU-SPE', 'name' => 'Санкт-Петербург', 'yandex_lr' => 2, 'google_gl' => null, 'google_hl' => null],
            ['engine' => 'yandex', 'code' => 'RU-NVS', 'name' => 'Новосибирск', 'yandex_lr' => 65, 'google_gl' => null, 'google_hl' => null],
            ['engine' => 'yandex', 'code' => 'RU-SVE', 'name' => 'Екатеринбург', 'yandex_lr' => 54, 'google_gl' => null, 'google_hl' => null],
            ['engine' => 'yandex', 'code' => 'RU-KDA', 'name' => 'Краснодар', 'yandex_lr' => 35, 'google_gl' => null, 'google_hl' => null],
            ['engine' => 'yandex', 'code' => 'RU-KAZ', 'name' => 'Казань', 'yandex_lr' => 43, 'google_gl' => null, 'google_hl' => null],
            ['engine' => 'yandex', 'code' => 'BY', 'name' => 'Беларусь', 'yandex_lr' => 149, 'google_gl' => null, 'google_hl' => null],
            ['engine' => 'yandex', 'code' => 'KZ', 'name' => 'Казахстан', 'yandex_lr' => 159, 'google_gl' => null, 'google_hl' => null],
            ['engine' => 'yandex', 'code' => 'TR', 'name' => 'Турция', 'yandex_lr' => 983, 'google_gl' => null, 'google_hl' => null],
            ['engine' => 'google', 'code' => 'RU', 'name' => 'Россия', 'yandex_lr' => null, 'google_gl' => 'ru', 'google_hl' => 'ru'],
            ['engine' => 'google', 'code' => 'US', 'name' => 'США', 'yandex_lr' => null, 'google_gl' => 'us', 'google_hl' => 'en'],
            ['engine' => 'google', 'code' => 'GB', 'name' => 'Великобритания', 'yandex_lr' => null, 'google_gl' => 'uk', 'google_hl' => 'en'],
            ['engine' => 'google', 'code' => 'DE', 'name' => 'Германия', 'yandex_lr' => null, 'google_gl' => 'de', 'google_hl' => 'de'],
            ['engine' => 'google', 'code' => 'TR', 'name' => 'Турция', 'yandex_lr' => null, 'google_gl' => 'tr', 'google_hl' => 'tr'],
        ];

        DB::table('regions')->insert(array_map(fn ($r) => array_merge($r, [
            'created_at' => now(),
            'updated_at' => now(),
        ]), $regions));
    }
}
