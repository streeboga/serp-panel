<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ClassificationRuleType;
use App\Enums\Device;
use App\Enums\Engine;
use App\Models\Category;
use App\Models\ClassificationRule;
use App\Models\Cluster;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\Organization;
use App\Models\PositionAlert;
use App\Models\Project;
use App\Models\Scraper;
use App\Models\WordstatFrequency;
use App\Models\ScrapeSchedule;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use App\Models\SiteType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RegionSeeder::class,
            SiteTypeSeeder::class,
        ]);

        // ═══════════════════════════════════════════
        // USER & ORGANIZATION
        // Login: admin@serp.test / password
        // ═══════════════════════════════════════════
        $user = User::factory()->create([
            'name' => 'Кирилл Мазуров',
            'email' => 'admin@serp.test',
            'password' => bcrypt('password'),
            'locale' => 'ru',
        ]);

        $org = Organization::create([
            'name' => 'SEO Agency Pro',
            'slug' => 'seo-agency-pro',
            'billing_tier' => 'pro',
            'max_keywords' => 50000,
            'max_projects' => 100,
            'max_scrapers' => 10,
        ]);
        $org->users()->attach($user->id, ['role' => 'admin']);

        // Second user (analyst)
        $analyst = User::factory()->create([
            'name' => 'Анна Петрова',
            'email' => 'analyst@serp.test',
            'password' => bcrypt('password'),
            'locale' => 'ru',
        ]);
        $org->users()->attach($analyst->id, ['role' => 'analyst']);

        // Second organization for org switcher testing
        $org2 = Organization::create([
            'name' => 'Freelance Projects',
            'slug' => 'freelance-projects',
        ]);
        $org2->users()->attach($user->id, ['role' => 'admin']);

        // ═══════════════════════════════════════════
        // PROJECT 1: E-commerce
        // ═══════════════════════════════════════════
        $project1 = Project::create([
            'organization_id' => $org->id,
            'name' => 'МойМагазин.ру',
            'description' => 'Мониторинг позиций интернет-магазина электроники и бытовой техники',
        ]);

        // Domains
        $ownDomain = Domain::create(['project_id' => $project1->id, 'name' => 'moymagazin.ru', 'is_own' => true]);
        Domain::create(['project_id' => $project1->id, 'name' => 'ozon.ru', 'is_own' => false]);
        Domain::create(['project_id' => $project1->id, 'name' => 'wildberries.ru', 'is_own' => false]);
        Domain::create(['project_id' => $project1->id, 'name' => 'dns-shop.ru', 'is_own' => false]);
        Domain::create(['project_id' => $project1->id, 'name' => 'mvideo.ru', 'is_own' => false]);
        Domain::create(['project_id' => $project1->id, 'name' => 'citilink.ru', 'is_own' => false]);

        // Categories & Clusters
        $catElectronics = Category::create(['domain_id' => $ownDomain->id, 'name' => 'Электроника', 'sort_order' => 1]);
        $catAppliances = Category::create(['domain_id' => $ownDomain->id, 'name' => 'Бытовая техника', 'sort_order' => 2]);
        $catAccessories = Category::create(['domain_id' => $ownDomain->id, 'name' => 'Аксессуары', 'sort_order' => 3]);

        $clSmartphones = Cluster::create(['category_id' => $catElectronics->id, 'name' => 'Смартфоны', 'sort_order' => 1]);
        $clLaptops = Cluster::create(['category_id' => $catElectronics->id, 'name' => 'Ноутбуки', 'sort_order' => 2]);
        $clTV = Cluster::create(['category_id' => $catElectronics->id, 'name' => 'Телевизоры', 'sort_order' => 3]);
        $clWashers = Cluster::create(['category_id' => $catAppliances->id, 'name' => 'Стиральные машины', 'sort_order' => 1]);
        $clFridges = Cluster::create(['category_id' => $catAppliances->id, 'name' => 'Холодильники', 'sort_order' => 2]);
        $clCases = Cluster::create(['category_id' => $catAccessories->id, 'name' => 'Чехлы', 'sort_order' => 1]);

        // Keywords with SERP data
        $regionMoscow = 2; // Moscow region from RegionSeeder
        $keywords = [
            [$clSmartphones, 'купить смартфон', Engine::Yandex, 12500],
            [$clSmartphones, 'смартфон цена', Engine::Yandex, 8200],
            [$clSmartphones, 'iphone 16 pro купить', Engine::Yandex, 45000],
            [$clSmartphones, 'samsung galaxy s25 купить', Engine::Yandex, 22000],
            [$clSmartphones, 'xiaomi 14 pro цена', Engine::Yandex, 15000],
            [$clSmartphones, 'buy smartphone', Engine::Google, 6800],
            [$clSmartphones, 'best smartphone 2026', Engine::Google, 9100],
            [$clLaptops, 'ноутбук для работы', Engine::Yandex, 18500],
            [$clLaptops, 'купить ноутбук', Engine::Yandex, 35000],
            [$clLaptops, 'macbook pro цена', Engine::Yandex, 28000],
            [$clLaptops, 'gaming laptop', Engine::Google, 12000],
            [$clTV, 'телевизор 55 дюймов', Engine::Yandex, 22000],
            [$clTV, 'smart tv купить', Engine::Yandex, 15000],
            [$clWashers, 'стиральная машина купить', Engine::Yandex, 42000],
            [$clWashers, 'стиральная машина с сушкой', Engine::Yandex, 18000],
            [$clFridges, 'холодильник купить', Engine::Yandex, 38000],
            [$clFridges, 'холодильник no frost', Engine::Yandex, 25000],
            [$clCases, 'чехол iphone 16', Engine::Yandex, 55000],
            [$clCases, 'чехол samsung galaxy', Engine::Yandex, 32000],
        ];

        $competitors = ['ozon.ru', 'wildberries.ru', 'dns-shop.ru', 'mvideo.ru', 'citilink.ru'];
        $siteTypes = SiteType::pluck('id', 'name')->toArray();

        foreach ($keywords as [$cluster, $text, $engine, $frequency]) {
            $kw = Keyword::create([
                'cluster_id' => $cluster->id,
                'keyword' => $text,
                'engine' => $engine,
                'device' => Device::Desktop,
                'region_id' => $regionMoscow,
            ]);

            // Wordstat frequency data (exact, broad, phrase)
            WordstatFrequency::create([
                'keyword_id' => $kw->id,
                'region_id' => $regionMoscow,
                'frequency_exact' => $frequency,
                'frequency_broad' => (int) ($frequency * 1.8),
                'frequency_phrase' => (int) ($frequency * 0.6),
                'collected_at' => Carbon::now(),
            ]);

            // Generate 14 days of SERP snapshots
            for ($day = 13; $day >= 0; $day--) {
                $date = Carbon::now()->subDays($day);
                $basePosition = rand(3, 35);

                $snapshot = SerpSnapshot::create([
                    'keyword_id' => $kw->id,
                    'collected_at' => $date->toDateString(),
                    'search_engine' => $engine,
                    'device' => Device::Desktop,
                    'region_id' => $regionMoscow,
                    'total_results' => 100,
                ]);

                // Own domain position (fluctuating)
                $ownPos = max(1, $basePosition + rand(-3, 3));
                SerpResult::create([
                    'snapshot_id' => $snapshot->id,
                    'collected_at' => $date->toDateString(),
                    'position' => $ownPos,
                    'url' => "https://moymagazin.ru/{$text}",
                    'domain' => 'moymagazin.ru',
                    'title' => "Купить {$text} - МойМагазин",
                    'description' => "Большой выбор {$text} по низким ценам",
                ]);

                // Competitor positions
                foreach ($competitors as $i => $comp) {
                    $compPos = rand(1, 50);
                    if ($compPos <= 20) {
                        SerpResult::create([
                            'snapshot_id' => $snapshot->id,
                            'collected_at' => $date->toDateString(),
                            'position' => $compPos,
                            'url' => "https://{$comp}/search?q={$text}",
                            'domain' => $comp,
                            'title' => ucfirst(str_replace('.ru', '', $comp)) . " - {$text}",
                        ]);
                    }
                }
            }
        }

        // ═══════════════════════════════════════════
        // PROJECT 2: Service business (smaller)
        // ═══════════════════════════════════════════
        $project2 = Project::create([
            'organization_id' => $org->id,
            'name' => 'Ремонт Квартир МСК',
            'description' => 'Мониторинг локального SEO для ремонтной компании',
        ]);

        $ownDomain2 = Domain::create(['project_id' => $project2->id, 'name' => 'remont-kvartir-msk.ru', 'is_own' => true]);
        Domain::create(['project_id' => $project2->id, 'name' => 'remont-f.ru', 'is_own' => false]);
        Domain::create(['project_id' => $project2->id, 'name' => 'stroy-podskazka.ru', 'is_own' => false]);

        $catRemont = Category::create(['domain_id' => $ownDomain2->id, 'name' => 'Ремонт', 'sort_order' => 1]);
        $clKosm = Cluster::create(['category_id' => $catRemont->id, 'name' => 'Косметический ремонт', 'sort_order' => 1]);
        $clKap = Cluster::create(['category_id' => $catRemont->id, 'name' => 'Капитальный ремонт', 'sort_order' => 2]);

        $remontKws = [
            [$clKosm, 'косметический ремонт квартиры москва', 8500],
            [$clKosm, 'ремонт квартир под ключ', 35000],
            [$clKap, 'капитальный ремонт квартиры', 12000],
            [$clKap, 'евроремонт москва цена', 6500],
        ];

        foreach ($remontKws as [$cluster, $text, $freq]) {
            Keyword::create([
                'cluster_id' => $cluster->id,
                'keyword' => $text,
                'engine' => Engine::Yandex,
                'device' => Device::Desktop,
                'region_id' => $regionMoscow,
            ]);
        }

        // ═══════════════════════════════════════════
        // SCRAPER
        // ═══════════════════════════════════════════
        $scraper = Scraper::create([
            'organization_id' => $org->id,
            'type' => 'xmlriver',
            'name' => 'XMLRiver Production',
            'base_url' => 'http://xmlriver.com/search/xml',
            'credentials' => ['user' => '20272', 'key' => '8857dd2a4f827c5e610bcd4e317a83b66399dd0d'],
            'supported_engines' => ['google', 'yandex'],
            'rate_limit' => 10,
            'is_active' => true,
        ]);

        // Schedule for project 1
        ScrapeSchedule::create([
            'project_id' => $project1->id,
            'scraper_id' => $scraper->id,
            'frequency_days' => 1,
            'is_active' => true,
            'next_run_at' => Carbon::now()->addDay(),
        ]);

        // ═══════════════════════════════════════════
        // CLASSIFICATION RULES
        // ═══════════════════════════════════════════
        $marketplace = $siteTypes['Маркетплейс'] ?? $siteTypes['marketplace'] ?? SiteType::first()?->id ?? 1;
        $ecommerce = $siteTypes['Интернет-магазин'] ?? $siteTypes['ecommerce'] ?? SiteType::skip(1)->first()?->id ?? 2;
        $info = $siteTypes['Информационный'] ?? $siteTypes['info'] ?? SiteType::skip(2)->first()?->id ?? 3;
        $aggregator = $siteTypes['Агрегатор'] ?? $siteTypes['aggregator'] ?? SiteType::skip(3)->first()?->id ?? 4;

        $rules = [
            ['domain_contains', 'ozon', $marketplace, 10, true],
            ['domain_contains', 'wildberries', $marketplace, 20, true],
            ['domain_exact', 'market.yandex.ru', $marketplace, 30, true],
            ['domain_contains', 'dns-shop', $ecommerce, 40, true],
            ['domain_contains', 'mvideo', $ecommerce, 50, true],
            ['domain_contains', 'citilink', $ecommerce, 60, true],
            ['domain_contains', 'eldorado', $ecommerce, 70, true],
            ['domain_regex', 'wikipedia\\.org', $info, 100, true],
            ['domain_contains', 'price.ru', $aggregator, 110, true],
            ['domain_contains', 'e-katalog', $aggregator, 120, true],
        ];

        foreach ($rules as [$type, $pattern, $siteTypeId, $priority, $isSystem]) {
            ClassificationRule::create([
                'organization_id' => $org->id,
                'rule_type' => ClassificationRuleType::from($type),
                'pattern' => $pattern,
                'site_type_id' => $siteTypeId,
                'priority' => $priority,
                'is_system' => $isSystem,
            ]);
        }

        // ═══════════════════════════════════════════
        // ALERTS
        // ═══════════════════════════════════════════
        $firstKeyword = Keyword::first();
        if ($firstKeyword) {
            PositionAlert::create([
                'organization_id' => $org->id,
                'keyword_id' => $firstKeyword->id,
                'threshold_position' => 10,
                'direction' => 'drops_below',
                'channel' => 'telegram',
                'recipient' => '@seo_alerts',
                'is_active' => true,
            ]);
        }

        $this->command->info('');
        $this->command->info('╔══════════════════════════════════════════════════╗');
        $this->command->info('║  SERP Panel seeded successfully!                 ║');
        $this->command->info('║                                                  ║');
        $this->command->info('║  Login:    admin@serp.test                       ║');
        $this->command->info('║  Password: password                              ║');
        $this->command->info('║                                                  ║');
        $this->command->info('║  Organizations:                                  ║');
        $this->command->info('║    - SEO Agency Pro (main)                       ║');
        $this->command->info('║    - Freelance Projects (for org switcher)       ║');
        $this->command->info('║                                                  ║');
        $this->command->info('║  Projects: 2                                     ║');
        $this->command->info('║  Keywords: ' . Keyword::count() . ' with 14 days SERP data        ║');
        $this->command->info('║  Classification rules: ' . ClassificationRule::count() . '                       ║');
        $this->command->info('╚══════════════════════════════════════════════════╝');
    }
}
