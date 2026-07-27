<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Default classification rules. Without any rules the classifier silently matched
 * nothing, so every domain stayed untyped. These are marked is_system, so they
 * apply to every organization; a manual classification always wins over them.
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> slug => domain fragments */
    private const RULES = [
        'marketplace' => ['ozon.ru', 'wildberries.ru', 'market.yandex.ru', 'avito.ru', 'aliexpress.', 'megamarket.ru', 'lamoda.ru', 'sbermegamarket.ru', 'flowwow.com'],
        'aggregator' => ['uslugi.yandex.ru', 'profi.ru', 'youdo.com', 'zoon.ru', '2gis.ru', 'yell.ru', 'tiu.ru', 'satom.ru', 'blizko.ru', 'ratingruneta.ru', 'workspace.ru'],
        'media' => ['rbc.ru', 'lenta.ru', 'ria.ru', 'kommersant.ru', 'vedomosti.ru', 'forbes.ru', 'tass.ru', 'iz.ru', 'gazeta.ru', 'interfax.ru', 'cnews.ru'],
        'blog' => ['vc.ru', 'habr.com', 'dzen.ru', 'pikabu.ru', 'livejournal.com', 'medium.com', 'teletype.in', 'spark.ru'],
        'info' => ['wikipedia.org', 'ruwiki.ru', 'wikihow.com', 'sky.pro', 'skillbox.ru', 'netology.ru', 'practicum.yandex.ru', 'gb.ru', 'stackoverflow.com'],
        'government' => ['gosuslugi.ru', 'nalog.ru', 'mos.ru', '.gov.ru', 'pfr.gov.ru', 'consultant.ru', 'garant.ru'],
        'social' => ['vk.com', 'ok.ru', 't.me', 'telegram.org', 'youtube.com', 'rutube.ru', 'tiktok.com', 'instagram.com', 'facebook.com', 'twitter.com', 'pinterest.'],
    ];

    public function up(): void
    {
        $organizationId = DB::table('organizations')->orderBy('id')->value('id');

        if ($organizationId === null) {
            return;
        }

        $siteTypes = DB::table('site_types')->pluck('id', 'slug');

        foreach (self::RULES as $slug => $patterns) {
            $siteTypeId = $siteTypes[$slug] ?? null;

            if ($siteTypeId === null) {
                continue;
            }

            foreach ($patterns as $pattern) {
                $exists = DB::table('classification_rules')
                    ->where('pattern', $pattern)
                    ->where('is_system', true)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('classification_rules')->insert([
                    'organization_id' => $organizationId,
                    'rule_type' => 'domain_contains',
                    'pattern' => $pattern,
                    'site_type_id' => $siteTypeId,
                    // More specific hosts first; everything here is equally specific.
                    'priority' => 100,
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('classification_rules')
            ->where('is_system', true)
            ->whereIn('pattern', array_merge(...array_values(self::RULES)))
            ->delete();
    }
};
