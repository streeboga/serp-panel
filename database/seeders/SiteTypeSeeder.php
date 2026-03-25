<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SiteTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['slug' => 'marketplace', 'name' => 'Маркетплейс', 'color' => '#8B5CF6', 'sort_order' => 1],
            ['slug' => 'ecommerce', 'name' => 'Интернет-магазин', 'color' => '#3B82F6', 'sort_order' => 2],
            ['slug' => 'aggregator', 'name' => 'Агрегатор', 'color' => '#F59E0B', 'sort_order' => 3],
            ['slug' => 'info', 'name' => 'Инфосайт', 'color' => '#10B981', 'sort_order' => 4],
            ['slug' => 'blog', 'name' => 'Блог', 'color' => '#06B6D4', 'sort_order' => 5],
            ['slug' => 'landing', 'name' => 'Лендинг', 'color' => '#EC4899', 'sort_order' => 6],
            ['slug' => 'government', 'name' => 'Гос. сайт', 'color' => '#6B7280', 'sort_order' => 7],
            ['slug' => 'social', 'name' => 'Соц. сеть', 'color' => '#EF4444', 'sort_order' => 8],
            ['slug' => 'media', 'name' => 'СМИ', 'color' => '#F97316', 'sort_order' => 9],
            ['slug' => 'other', 'name' => 'Другое', 'color' => '#9CA3AF', 'sort_order' => 99],
        ];

        DB::table('site_types')->insert(array_map(fn ($t) => array_merge($t, [
            'created_at' => now(),
            'updated_at' => now(),
        ]), $types));
    }
}
