# SEO Monitor — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a multi-tenant SaaS for SEO position monitoring across Google/Yandex with full TOP-100 SERP snapshots, Wordstat analytics, and automatic site classification.

**Architecture:** Laravel 11 API with PostgreSQL (monthly partitioned SERP tables), Redis queues via Horizon, pluggable scraper adapters. Separate React + TanStack frontend SPA.

**Tech Stack:** PHP 8.3, Laravel 11, PostgreSQL 16, Redis, Horizon, React 18, TypeScript, TanStack (Router/Query/Table), Tailwind CSS, shadcn/ui, Docker Compose.

**Design doc:** `docs/plans/2026-03-25-seo-monitor-design.md`

---

## Phase 1: Project Scaffolding + Docker

### Task 1.1: Create Laravel project

**Files:**
- Create: `seo-monitor/` (Laravel project root)

**Step 1: Scaffold Laravel**

```bash
cd /Users/k.mazurov/PhpstormProjects
composer create-project laravel/laravel seo-monitor
cd seo-monitor
```

**Step 2: Install core dependencies**

```bash
composer require laravel/horizon
composer require laravel/sanctum
composer require spatie/laravel-query-builder
composer require timacdonald/json-api
composer require --dev phpunit/phpunit
```

**Step 3: Init git + commit**

```bash
git init
git add -A
git commit -m "chore: scaffold Laravel 11 project with core dependencies"
```

---

### Task 1.2: Docker Compose setup

**Files:**
- Create: `seo-monitor/docker-compose.yml`
- Create: `seo-monitor/docker/app/Dockerfile`
- Create: `seo-monitor/docker/app/php.ini`
- Create: `seo-monitor/docker/nginx/default.conf`
- Create: `seo-monitor/docker/scheduler/entrypoint.sh`

**Step 1: Write docker-compose.yml**

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
    volumes:
      - .:/var/www/html
    depends_on:
      - postgres
      - redis
    environment:
      - DB_CONNECTION=pgsql
      - DB_HOST=postgres
      - DB_PORT=5432
      - DB_DATABASE=seo_monitor
      - DB_USERNAME=seo_monitor
      - DB_PASSWORD=secret
      - REDIS_HOST=redis
      - CACHE_DRIVER=redis
      - QUEUE_CONNECTION=redis
      - SESSION_DRIVER=redis

  nginx:
    image: nginx:alpine
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

  postgres:
    image: postgres:16-alpine
    ports:
      - "5432:5432"
    environment:
      POSTGRES_DB: seo_monitor
      POSTGRES_USER: seo_monitor
      POSTGRES_PASSWORD: secret
    volumes:
      - postgres_data:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data

  scheduler:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
    volumes:
      - .:/var/www/html
    entrypoint: ["sh", "/var/www/html/docker/scheduler/entrypoint.sh"]
    depends_on:
      - postgres
      - redis

  horizon:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
    volumes:
      - .:/var/www/html
    command: php artisan horizon
    depends_on:
      - postgres
      - redis

volumes:
  postgres_data:
  redis_data:
```

**Step 2: Write Dockerfile**

```dockerfile
# docker/app/Dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    && docker-php-ext-install pdo_pgsql zip pcntl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/app/php.ini /usr/local/etc/php/conf.d/custom.ini
```

**Step 3: Write php.ini**

```ini
; docker/app/php.ini
memory_limit = 256M
max_execution_time = 120
upload_max_filesize = 50M
post_max_size = 50M
```

**Step 4: Write nginx config**

```nginx
# docker/nginx/default.conf
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

**Step 5: Write scheduler entrypoint**

```bash
#!/bin/sh
# docker/scheduler/entrypoint.sh
while true; do
    php /var/www/html/artisan schedule:run --verbose --no-interaction
    sleep 60
done
```

**Step 6: Configure .env for PostgreSQL**

Update `seo-monitor/.env`:
```
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=seo_monitor
DB_USERNAME=seo_monitor
DB_PASSWORD=secret
REDIS_HOST=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

**Step 7: Boot and verify**

```bash
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan --version
```
Expected: Laravel 11.x

**Step 8: Commit**

```bash
git add docker-compose.yml docker/ .env.example
git commit -m "chore: add Docker Compose with PostgreSQL, Redis, Horizon, scheduler"
```

---

### Task 1.3: Publish Horizon config

**Step 1: Publish and configure Horizon**

```bash
docker compose exec app php artisan horizon:install
```

**Step 2: Configure queues in `config/horizon.php`**

Edit `config/horizon.php` — set supervisor environments:

```php
'environments' => [
    'production' => [
        'serp-supervisor' => [
            'connection' => 'redis',
            'queue' => ['serp-scrape'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 10,
            'tries' => 3,
            'timeout' => 300,
        ],
        'wordstat-supervisor' => [
            'connection' => 'redis',
            'queue' => ['wordstat'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 5,
            'tries' => 3,
            'timeout' => 300,
        ],
        'classification-supervisor' => [
            'connection' => 'redis',
            'queue' => ['classification'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'tries' => 3,
            'timeout' => 60,
        ],
        'default-supervisor' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'tries' => 3,
            'timeout' => 60,
        ],
    ],
    'local' => [
        'default-supervisor' => [
            'connection' => 'redis',
            'queue' => ['serp-scrape', 'wordstat', 'classification', 'default'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'tries' => 3,
            'timeout' => 300,
        ],
    ],
],
```

**Step 3: Commit**

```bash
git add config/horizon.php app/Providers/HorizonServiceProvider.php
git commit -m "chore: configure Horizon with SERP/Wordstat/classification queues"
```

---

## Phase 2: Enums, Core Migrations, Models

### Task 2.1: Create Enums

**Files:**
- Create: `app/Enums/Engine.php`
- Create: `app/Enums/Device.php`
- Create: `app/Enums/OrganizationRole.php`
- Create: `app/Enums/ScrapeJobStatus.php`
- Create: `app/Enums/ClassifiedBy.php`
- Create: `app/Enums/ClassificationRuleType.php`
- Create: `app/Enums/WordstatSuggestionType.php`

**Step 1: Write all enums**

```php
// app/Enums/Engine.php
<?php

namespace App\Enums;

enum Engine: string
{
    case Google = 'google';
    case Yandex = 'yandex';
}
```

```php
// app/Enums/Device.php
<?php

namespace App\Enums;

enum Device: string
{
    case Desktop = 'desktop';
    case Mobile = 'mobile';
}
```

```php
// app/Enums/OrganizationRole.php
<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Analyst = 'analyst';
    case Viewer = 'viewer';
}
```

```php
// app/Enums/ScrapeJobStatus.php
<?php

namespace App\Enums;

enum ScrapeJobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Retrying = 'retrying';
}
```

```php
// app/Enums/ClassifiedBy.php
<?php

namespace App\Enums;

enum ClassifiedBy: string
{
    case Rule = 'rule';
    case Manual = 'manual';
}
```

```php
// app/Enums/ClassificationRuleType.php
<?php

namespace App\Enums;

enum ClassificationRuleType: string
{
    case DomainExact = 'domain_exact';
    case DomainContains = 'domain_contains';
    case DomainRegex = 'domain_regex';
    case UrlRegex = 'url_regex';
    case TitleContains = 'title_contains';
}
```

```php
// app/Enums/WordstatSuggestionType.php
<?php

namespace App\Enums;

enum WordstatSuggestionType: string
{
    case Suggestion = 'suggestion';
    case Association = 'association';
}
```

**Step 2: Commit**

```bash
git add app/Enums/
git commit -m "feat: add core enums (Engine, Device, Role, ScrapeJobStatus, etc.)"
```

---

### Task 2.2: Migrations — Organizations, Projects, Domains, Categories, Clusters

**Files:**
- Create: migration `create_organizations_table`
- Create: migration `create_organization_user_table`
- Create: migration `create_projects_table`
- Create: migration `create_domains_table`
- Create: migration `create_categories_table`
- Create: migration `create_clusters_table`

**Step 1: Generate and write migrations**

```bash
docker compose exec app php artisan make:migration create_organizations_table
docker compose exec app php artisan make:migration create_organization_user_table
docker compose exec app php artisan make:migration create_projects_table
docker compose exec app php artisan make:migration create_domains_table
docker compose exec app php artisan make:migration create_categories_table
docker compose exec app php artisan make:migration create_clusters_table
```

**organizations:**
```php
Schema::create('organizations', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->timestamps();
});
```

**organization_user:**
```php
Schema::create('organization_user', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('role')->default('viewer');
    $table->timestamps();
    $table->unique(['organization_id', 'user_id']);
});
```

**projects:**
```php
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->timestamps();
});
```

**domains:**
```php
Schema::create('domains', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->boolean('is_own')->default(false);
    $table->timestamps();
});
```

**categories:**
```php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

**clusters:**
```php
Schema::create('clusters', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

**Step 2: Run migrations**

```bash
docker compose exec app php artisan migrate
```

**Step 3: Commit**

```bash
git add database/migrations/
git commit -m "feat: add migrations for organizations, projects, domains, categories, clusters"
```

---

### Task 2.3: Migrations — Regions + Keywords

**Files:**
- Create: migration `create_regions_table`
- Create: migration `create_keywords_table`
- Create: `database/seeders/RegionSeeder.php`

**Step 1: Generate and write migrations**

**regions:**
```php
Schema::create('regions', function (Blueprint $table) {
    $table->id();
    $table->string('engine'); // google|yandex
    $table->string('code');   // RU, RU-MOW, etc.
    $table->string('name');
    $table->integer('yandex_lr')->nullable();
    $table->string('google_gl')->nullable();
    $table->string('google_hl')->nullable();
    $table->timestamps();

    $table->unique(['engine', 'code']);
});
```

**keywords:**
```php
Schema::create('keywords', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cluster_id')->constrained()->cascadeOnDelete();
    $table->string('keyword');
    $table->string('engine'); // google|yandex
    $table->string('device')->default('desktop');
    $table->foreignId('region_id')->constrained();
    $table->timestamps();

    $table->index(['cluster_id', 'engine']);
});
```

**Step 2: Write RegionSeeder**

```php
// database/seeders/RegionSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            // Yandex regions
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
            // Google regions
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
```

**Step 3: Run**

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=RegionSeeder
```

**Step 4: Commit**

```bash
git add database/
git commit -m "feat: add regions and keywords tables with region seeder"
```

---

### Task 2.4: Migrations — SERP snapshots with monthly partitioning

**Files:**
- Create: migration `create_serp_snapshots_table`
- Create: migration `create_serp_results_table`
- Create: migration `create_serp_initial_partitions`

**Step 1: Write partitioned table migrations**

PostgreSQL native partitioning requires raw SQL — Laravel's Blueprint does not support `PARTITION BY`.

**serp_snapshots:**
```php
// Migration: create_serp_snapshots_table
public function up(): void
{
    DB::statement("
        CREATE TABLE serp_snapshots (
            id BIGSERIAL,
            keyword_id BIGINT NOT NULL,
            collected_at DATE NOT NULL,
            search_engine VARCHAR(10) NOT NULL,
            device VARCHAR(10) NOT NULL DEFAULT 'desktop',
            region_id BIGINT NOT NULL,
            total_results INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT NOW(),
            PRIMARY KEY (id, collected_at)
        ) PARTITION BY RANGE (collected_at)
    ");

    DB::statement("CREATE INDEX idx_serp_snapshots_keyword ON serp_snapshots (keyword_id, collected_at)");
    DB::statement("CREATE INDEX idx_serp_snapshots_engine ON serp_snapshots (search_engine, collected_at)");
}

public function down(): void
{
    DB::statement("DROP TABLE IF EXISTS serp_snapshots CASCADE");
}
```

**serp_results:**
```php
// Migration: create_serp_results_table
public function up(): void
{
    DB::statement("
        CREATE TABLE serp_results (
            id BIGSERIAL,
            snapshot_id BIGINT NOT NULL,
            collected_at DATE NOT NULL,
            position SMALLINT NOT NULL,
            url TEXT NOT NULL,
            domain VARCHAR(255) NOT NULL,
            title TEXT,
            description TEXT,
            snippet_type VARCHAR(50) DEFAULT 'organic',
            is_ads BOOLEAN DEFAULT FALSE,
            cached_page_url TEXT,
            PRIMARY KEY (id, collected_at)
        ) PARTITION BY RANGE (collected_at)
    ");

    DB::statement("CREATE INDEX idx_serp_results_snapshot ON serp_results (snapshot_id, collected_at)");
    DB::statement("CREATE INDEX idx_serp_results_domain ON serp_results (domain, collected_at)");
    DB::statement("CREATE INDEX idx_serp_results_position ON serp_results (position, collected_at)");
}

public function down(): void
{
    DB::statement("DROP TABLE IF EXISTS serp_results CASCADE");
}
```

**Initial partitions (current month + next 2 months):**
```php
// Migration: create_serp_initial_partitions
public function up(): void
{
    $startMonth = now()->startOfMonth();

    for ($i = 0; $i < 3; $i++) {
        $from = $startMonth->copy()->addMonths($i)->format('Y-m-d');
        $to = $startMonth->copy()->addMonths($i + 1)->format('Y-m-d');
        $suffix = $startMonth->copy()->addMonths($i)->format('Y_m');

        DB::statement("
            CREATE TABLE serp_snapshots_{$suffix}
            PARTITION OF serp_snapshots
            FOR VALUES FROM ('{$from}') TO ('{$to}')
        ");

        DB::statement("
            CREATE TABLE serp_results_{$suffix}
            PARTITION OF serp_results
            FOR VALUES FROM ('{$from}') TO ('{$to}')
        ");
    }
}
```

**Step 2: Run migrations**

```bash
docker compose exec app php artisan migrate
```

**Step 3: Verify partitions exist**

```bash
docker compose exec postgres psql -U seo_monitor -d seo_monitor -c "\dt serp_*"
```
Expected: `serp_snapshots`, `serp_results`, + partition tables for 3 months.

**Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat: add partitioned serp_snapshots and serp_results tables"
```

---

### Task 2.5: Migrations — Scrapers, Schedules, Jobs

**Files:**
- Create: migration `create_scrapers_table`
- Create: migration `create_scrape_schedules_table`
- Create: migration `create_scrape_jobs_table`

**Step 1: Write migrations**

**scrapers:**
```php
Schema::create('scrapers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->string('type'); // xmlriver|yandex_xml|openserp|camoufox|custom
    $table->string('name');
    $table->string('base_url');
    $table->jsonb('credentials')->nullable(); // encrypted at app level
    $table->jsonb('supported_engines')->default('["google","yandex"]');
    $table->integer('rate_limit')->default(60); // requests per minute
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**scrape_schedules:**
```php
Schema::create('scrape_schedules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
    $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
    $table->foreignId('cluster_id')->nullable()->constrained()->cascadeOnDelete();
    $table->foreignId('keyword_id')->nullable()->constrained('keywords')->cascadeOnDelete();
    $table->foreignId('scraper_id')->constrained()->cascadeOnDelete();
    $table->integer('frequency_days')->default(1);
    $table->timestamp('last_run_at')->nullable();
    $table->timestamp('next_run_at')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**scrape_jobs:**
```php
Schema::create('scrape_jobs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
    $table->foreignId('scraper_id')->constrained();
    $table->foreignId('schedule_id')->nullable()->constrained('scrape_schedules')->nullOnDelete();
    $table->string('status')->default('pending');
    $table->string('engine');
    $table->foreignId('region_id')->constrained();
    $table->string('device')->default('desktop');
    $table->integer('attempts')->default(0);
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->text('error_message')->nullable();
    $table->text('raw_response')->nullable();
    $table->timestamps();

    $table->index(['status', 'created_at']);
    $table->index(['scraper_id', 'status']);
});
```

**Step 2: Run and commit**

```bash
docker compose exec app php artisan migrate
git add database/migrations/
git commit -m "feat: add scrapers, scrape_schedules, scrape_jobs tables"
```

---

### Task 2.6: Migrations — Classification

**Files:**
- Create: migration `create_site_types_table`
- Create: migration `create_classification_rules_table`
- Create: migration `create_domain_classifications_table`
- Create: `database/seeders/SiteTypeSeeder.php`

**Step 1: Write migrations**

**site_types:**
```php
Schema::create('site_types', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->string('name');
    $table->string('color', 7)->default('#6B7280'); // hex
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

**classification_rules:**
```php
Schema::create('classification_rules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->string('rule_type'); // domain_exact|domain_contains|domain_regex|url_regex|title_contains
    $table->string('pattern');
    $table->foreignId('site_type_id')->constrained();
    $table->integer('priority')->default(0);
    $table->boolean('is_system')->default(false);
    $table->timestamps();

    $table->index(['organization_id', 'priority']);
});
```

**domain_classifications:**
```php
Schema::create('domain_classifications', function (Blueprint $table) {
    $table->id();
    $table->string('domain');
    $table->foreignId('site_type_id')->constrained();
    $table->string('classified_by')->default('rule'); // rule|manual
    $table->foreignId('rule_id')->nullable()->constrained('classification_rules')->nullOnDelete();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->timestamps();

    $table->unique(['domain', 'organization_id']);
    $table->index('domain');
});
```

**Step 2: Write SiteTypeSeeder**

```php
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
```

**Step 3: Run and commit**

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=SiteTypeSeeder
git add database/
git commit -m "feat: add classification tables (site_types, rules, domain_classifications)"
```

---

### Task 2.7: Migrations — Wordstat

**Files:**
- Create: migration `create_wordstat_frequencies_table`
- Create: migration `create_wordstat_trends_table`
- Create: migration `create_wordstat_suggestions_table`
- Create: migration `create_wordstat_schedules_table`

**Step 1: Write migrations**

**wordstat_frequencies:**
```php
Schema::create('wordstat_frequencies', function (Blueprint $table) {
    $table->id();
    $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
    $table->foreignId('region_id')->constrained();
    $table->integer('frequency_exact')->default(0);
    $table->integer('frequency_broad')->default(0);
    $table->integer('frequency_phrase')->default(0);
    $table->date('collected_at');
    $table->timestamps();

    $table->index(['keyword_id', 'region_id', 'collected_at']);
});
```

**wordstat_trends:**
```php
Schema::create('wordstat_trends', function (Blueprint $table) {
    $table->id();
    $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
    $table->foreignId('region_id')->constrained();
    $table->date('month'); // first day of month
    $table->integer('absolute_value')->default(0);
    $table->date('collected_at');
    $table->timestamps();

    $table->index(['keyword_id', 'region_id', 'month']);
});
```

**wordstat_suggestions:**
```php
Schema::create('wordstat_suggestions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
    $table->string('suggestion');
    $table->integer('frequency')->default(0);
    $table->string('type'); // suggestion|association
    $table->date('collected_at');
    $table->timestamps();

    $table->index(['keyword_id', 'collected_at']);
});
```

**wordstat_schedules:**
```php
Schema::create('wordstat_schedules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
    $table->foreignId('cluster_id')->nullable()->constrained()->cascadeOnDelete();
    $table->foreignId('keyword_id')->nullable()->constrained('keywords')->cascadeOnDelete();
    $table->integer('frequency_days')->default(30);
    $table->boolean('collect_trends')->default(true);
    $table->boolean('collect_suggestions')->default(true);
    $table->jsonb('regions')->default('[]'); // array of region_ids
    $table->timestamp('last_run_at')->nullable();
    $table->timestamp('next_run_at')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**Step 2: Run and commit**

```bash
docker compose exec app php artisan migrate
git add database/migrations/
git commit -m "feat: add Wordstat tables (frequencies, trends, suggestions, schedules)"
```

---

### Task 2.8: Eloquent Models

**Files:**
- Create: `app/Models/Organization.php`
- Create: `app/Models/Project.php`
- Create: `app/Models/Domain.php`
- Create: `app/Models/Category.php`
- Create: `app/Models/Cluster.php`
- Create: `app/Models/Keyword.php`
- Create: `app/Models/Region.php`
- Create: `app/Models/SerpSnapshot.php`
- Create: `app/Models/SerpResult.php`
- Create: `app/Models/Scraper.php`
- Create: `app/Models/ScrapeSchedule.php`
- Create: `app/Models/ScrapeJob.php`
- Create: `app/Models/SiteType.php`
- Create: `app/Models/ClassificationRule.php`
- Create: `app/Models/DomainClassification.php`
- Create: `app/Models/WordstatFrequency.php`
- Create: `app/Models/WordstatTrend.php`
- Create: `app/Models/WordstatSuggestion.php`
- Create: `app/Models/WordstatSchedule.php`
- Modify: `app/Models/User.php`

**Step 1: Write all models with relationships**

Each model should have:
- `$fillable` array
- `$casts` for enums and dates
- Relationships

Key models (write the code — others follow the same pattern):

```php
// app/Models/Organization.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = ['name', 'slug'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function scrapers(): HasMany
    {
        return $this->hasMany(Scraper::class);
    }

    public function classificationRules(): HasMany
    {
        return $this->hasMany(ClassificationRule::class);
    }
}
```

```php
// app/Models/Keyword.php
<?php

namespace App\Models;

use App\Enums\Device;
use App\Enums\Engine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keyword extends Model
{
    protected $fillable = ['cluster_id', 'keyword', 'engine', 'device', 'region_id'];

    protected $casts = [
        'engine' => Engine::class,
        'device' => Device::class,
    ];

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function serpSnapshots(): HasMany
    {
        return $this->hasMany(SerpSnapshot::class);
    }

    public function wordstatFrequencies(): HasMany
    {
        return $this->hasMany(WordstatFrequency::class);
    }

    public function wordstatTrends(): HasMany
    {
        return $this->hasMany(WordstatTrend::class);
    }

    public function wordstatSuggestions(): HasMany
    {
        return $this->hasMany(WordstatSuggestion::class);
    }
}
```

```php
// app/Models/SerpSnapshot.php
<?php

namespace App\Models;

use App\Enums\Device;
use App\Enums\Engine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SerpSnapshot extends Model
{
    protected $fillable = [
        'keyword_id', 'collected_at', 'search_engine',
        'device', 'region_id', 'total_results',
    ];

    protected $casts = [
        'collected_at' => 'date',
        'search_engine' => Engine::class,
        'device' => Device::class,
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(SerpResult::class, 'snapshot_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
```

Write remaining models following same patterns. Every model has `$fillable`, `$casts`, and relationships.

**Step 2: Update User model — add organizations relationship**

```php
// Add to app/Models/User.php
public function organizations(): BelongsToMany
{
    return $this->belongsToMany(Organization::class)->withPivot('role')->withTimestamps();
}
```

**Step 3: Commit**

```bash
git add app/Models/
git commit -m "feat: add all Eloquent models with relationships and casts"
```

---

## Phase 3: Auth + Multi-tenancy Middleware

### Task 3.1: Auth via Sanctum

**Files:**
- Create: `app/Http/Controllers/Api/AuthController.php`
- Create: `routes/api.php` (modify)
- Test: `tests/Feature/AuthTest.php`

**Step 1: Write AuthController**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'organization_name' => 'required|string|max:255',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $org = Organization::create([
            'name' => $validated['organization_name'],
            'slug' => str($validated['organization_name'])->slug(),
        ]);

        $org->users()->attach($user->id, ['role' => 'admin']);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'organization' => $org,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
```

**Step 2: Write routes**

```php
// routes/api.php
use App\Http\Controllers\Api\AuthController;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    // All other routes go here
});
```

**Step 3: Write test**

```php
// tests/Feature/AuthTest.php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_name' => 'Test Org',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user', 'organization', 'token']);
    }

    public function test_user_can_login(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_name' => 'Test Org',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure(['user', 'token']);
    }
}
```

**Step 4: Run tests**

```bash
docker compose exec app php artisan test tests/Feature/AuthTest.php
```
Expected: 2 tests pass.

**Step 5: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php routes/api.php tests/Feature/AuthTest.php
git commit -m "feat: add auth (register/login/logout) with Sanctum"
```

---

### Task 3.2: Organization middleware + tenant scoping

**Files:**
- Create: `app/Http/Middleware/SetOrganization.php`
- Create: `app/Http/Middleware/CheckOrganizationRole.php`

**Step 1: Write SetOrganization middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetOrganization
{
    public function handle(Request $request, Closure $next)
    {
        $orgId = $request->header('X-Organization-Id')
            ?? $request->route('organizationId');

        if (!$orgId) {
            return response()->json(['error' => 'Organization ID required'], 400);
        }

        $user = $request->user();
        $membership = $user->organizations()->where('organizations.id', $orgId)->first();

        if (!$membership) {
            return response()->json(['error' => 'Not a member of this organization'], 403);
        }

        $request->merge([
            'organization' => $membership,
            'organization_role' => $membership->pivot->role,
        ]);

        return $next($request);
    }
}
```

**Step 2: Write CheckOrganizationRole middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckOrganizationRole
{
    private const ROLE_HIERARCHY = [
        'admin' => 4,
        'manager' => 3,
        'analyst' => 2,
        'viewer' => 1,
    ];

    public function handle(Request $request, Closure $next, string $minimumRole)
    {
        $userRole = $request->get('organization_role');
        $userLevel = self::ROLE_HIERARCHY[$userRole] ?? 0;
        $requiredLevel = self::ROLE_HIERARCHY[$minimumRole] ?? 0;

        if ($userLevel < $requiredLevel) {
            return response()->json(['error' => 'Insufficient role'], 403);
        }

        return $next($request);
    }
}
```

**Step 3: Register in `bootstrap/app.php`**

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'org' => \App\Http\Middleware\SetOrganization::class,
        'org.role' => \App\Http\Middleware\CheckOrganizationRole::class,
    ]);
})
```

**Step 4: Update routes to use middleware**

```php
Route::middleware(['auth:sanctum', 'org'])->group(function () {
    // All org-scoped routes
    Route::middleware('org.role:admin')->group(function () {
        // Admin-only routes
    });
});
```

**Step 5: Commit**

```bash
git add app/Http/Middleware/ bootstrap/app.php routes/api.php
git commit -m "feat: add organization middleware with role-based access control"
```

---

## Phase 4: Core CRUD APIs

### Task 4.1: Projects CRUD

**Files:**
- Create: `app/Http/Controllers/Api/ProjectController.php`
- Create: `app/Http/Requests/ProjectRequest.php`
- Test: `tests/Feature/ProjectTest.php`

Implement standard CRUD with tenant scoping:
- `index` — list projects for current org
- `store` — create project (manager+)
- `show` — get project with domain count
- `update` — update project (manager+)
- `destroy` — delete project (admin only)

All queries scoped: `Project::where('organization_id', $request->organization->id)`

**Step 1: Write controller, request, test**
**Step 2: Run tests**
**Step 3: Commit**

```bash
git commit -m "feat: add Projects CRUD API with tenant scoping"
```

---

### Task 4.2: Domains CRUD

Same pattern as Projects. Scoped to project. Include `is_own` flag.

```bash
git commit -m "feat: add Domains CRUD API"
```

---

### Task 4.3: Categories CRUD (tree structure)

Same pattern. Support `parent_id` for nesting. Return tree structure in `index`.

```bash
git commit -m "feat: add Categories CRUD API with tree structure"
```

---

### Task 4.4: Clusters CRUD

Same pattern. Scoped to category.

```bash
git commit -m "feat: add Clusters CRUD API"
```

---

### Task 4.5: Keywords CRUD + bulk import

**Files:**
- Create: `app/Http/Controllers/Api/KeywordController.php`
- Create: `app/Http/Requests/KeywordBulkRequest.php`

Key endpoints:
- `GET /api/keywords?cluster_id=&engine=&search=` — filterable via spatie/laravel-query-builder
- `POST /api/keywords/bulk` — accept array of `{keyword, engine, device, region_id, cluster_id}`
- `PUT /api/keywords/{id}`
- `DELETE /api/keywords/bulk` — accept array of IDs

```bash
git commit -m "feat: add Keywords CRUD with bulk import/delete and filtering"
```

---

### Task 4.6: Regions API (read-only)

```php
Route::get('/regions', function () {
    return Region::all()->groupBy('engine');
});
```

```bash
git commit -m "feat: add Regions read-only endpoint"
```

---

## Phase 5: Scraper Adapter Architecture

### Task 5.1: Adapter interface + DTOs

**Files:**
- Create: `app/Services/Scrapers/Contracts/SerpScraperAdapter.php`
- Create: `app/Services/Scrapers/DTO/ScrapeRequest.php`
- Create: `app/Services/Scrapers/DTO/ScrapeResponse.php`
- Create: `app/Services/Scrapers/DTO/SerpResultItem.php`

**Step 1: Write interface and DTOs**

```php
// app/Services/Scrapers/Contracts/SerpScraperAdapter.php
<?php

namespace App\Services\Scrapers\Contracts;

use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\DTO\ScrapeResponse;

interface SerpScraperAdapter
{
    public function scrape(ScrapeRequest $request): ScrapeResponse;
    public function supportedEngines(): array;
    public function healthCheck(): bool;
}
```

```php
// app/Services/Scrapers/DTO/ScrapeRequest.php
<?php

namespace App\Services\Scrapers\DTO;

readonly class ScrapeRequest
{
    public function __construct(
        public string $keyword,
        public string $engine,
        public string $device,
        public int $regionId,
        public int $limit = 100,
        public ?int $yandexLr = null,
        public ?string $googleGl = null,
        public ?string $googleHl = null,
    ) {}
}
```

```php
// app/Services/Scrapers/DTO/SerpResultItem.php
<?php

namespace App\Services\Scrapers\DTO;

readonly class SerpResultItem
{
    public function __construct(
        public int $position,
        public string $url,
        public string $domain,
        public ?string $title = null,
        public ?string $description = null,
        public string $snippetType = 'organic',
        public bool $isAds = false,
    ) {}
}
```

```php
// app/Services/Scrapers/DTO/ScrapeResponse.php
<?php

namespace App\Services\Scrapers\DTO;

readonly class ScrapeResponse
{
    /**
     * @param SerpResultItem[] $results
     */
    public function __construct(
        public array $results,
        public int $totalResults = 0,
        public string $rawResponse = '',
    ) {}
}
```

**Step 2: Commit**

```bash
git add app/Services/Scrapers/
git commit -m "feat: add scraper adapter interface and DTOs"
```

---

### Task 5.2: XMLRiver adapter

**Files:**
- Create: `app/Services/Scrapers/Adapters/XmlRiverAdapter.php`
- Test: `tests/Unit/Scrapers/XmlRiverAdapterTest.php`

**Step 1: Write adapter**

```php
<?php

namespace App\Services\Scrapers\Adapters;

use App\Services\Scrapers\Contracts\SerpScraperAdapter;
use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\DTO\ScrapeResponse;
use App\Services\Scrapers\DTO\SerpResultItem;
use Illuminate\Support\Facades\Http;

class XmlRiverAdapter implements SerpScraperAdapter
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly array $credentials,
    ) {}

    public function scrape(ScrapeRequest $request): ScrapeResponse
    {
        $params = [
            'user' => $this->credentials['user'] ?? '',
            'key' => $this->credentials['key'] ?? '',
            'query' => $request->keyword,
            'groupby' => $request->limit,
            'device' => $request->device,
        ];

        if ($request->engine === 'yandex') {
            $params['lr'] = $request->yandexLr;
            $params['engine'] = 'yandex';
        } else {
            $params['gl'] = $request->googleGl;
            $params['hl'] = $request->googleHl;
            $params['engine'] = 'google';
        }

        $response = Http::timeout(60)->get($this->baseUrl . '/search', $params);
        $body = $response->body();
        $data = json_decode($body, true);

        $results = [];
        foreach (($data['results'] ?? []) as $i => $item) {
            $url = $item['url'] ?? '';
            $results[] = new SerpResultItem(
                position: $i + 1,
                url: $url,
                domain: parse_url($url, PHP_URL_HOST) ?: '',
                title: $item['title'] ?? null,
                description: $item['snippet'] ?? $item['description'] ?? null,
                snippetType: $item['type'] ?? 'organic',
                isAds: (bool) ($item['is_ad'] ?? false),
            );
        }

        return new ScrapeResponse(
            results: $results,
            totalResults: $data['total_results'] ?? count($results),
            rawResponse: $body,
        );
    }

    public function supportedEngines(): array
    {
        return ['google', 'yandex'];
    }

    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(10)->get($this->baseUrl . '/status');
            return $response->ok();
        } catch (\Exception) {
            return false;
        }
    }
}
```

**Step 2: Write unit test with mocked HTTP**

```php
<?php

namespace Tests\Unit\Scrapers;

use App\Services\Scrapers\Adapters\XmlRiverAdapter;
use App\Services\Scrapers\DTO\ScrapeRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XmlRiverAdapterTest extends TestCase
{
    public function test_scrape_returns_parsed_results(): void
    {
        Http::fake([
            '*/search*' => Http::response(json_encode([
                'results' => [
                    ['url' => 'https://example.com/page1', 'title' => 'Page 1', 'snippet' => 'Desc 1', 'type' => 'organic'],
                    ['url' => 'https://other.com/page2', 'title' => 'Page 2', 'snippet' => 'Desc 2', 'type' => 'organic'],
                ],
                'total_results' => 1000,
            ])),
        ]);

        $adapter = new XmlRiverAdapter('https://xmlriver.example.com', ['user' => 'u', 'key' => 'k']);

        $response = $adapter->scrape(new ScrapeRequest(
            keyword: 'test query',
            engine: 'google',
            device: 'desktop',
            regionId: 1,
            googleGl: 'ru',
            googleHl: 'ru',
        ));

        $this->assertCount(2, $response->results);
        $this->assertEquals('example.com', $response->results[0]->domain);
        $this->assertEquals(1, $response->results[0]->position);
        $this->assertEquals(1000, $response->totalResults);
    }
}
```

**Step 3: Run test**

```bash
docker compose exec app php artisan test tests/Unit/Scrapers/XmlRiverAdapterTest.php
```

**Step 4: Commit**

```bash
git add app/Services/Scrapers/Adapters/XmlRiverAdapter.php tests/Unit/Scrapers/
git commit -m "feat: add XMLRiver scraper adapter with tests"
```

---

### Task 5.3: Scraper Factory + registration

**Files:**
- Create: `app/Services/Scrapers/ScraperFactory.php`

```php
<?php

namespace App\Services\Scrapers;

use App\Models\Scraper;
use App\Services\Scrapers\Adapters\XmlRiverAdapter;
use App\Services\Scrapers\Contracts\SerpScraperAdapter;

class ScraperFactory
{
    public function make(Scraper $scraper): SerpScraperAdapter
    {
        return match ($scraper->type) {
            'xmlriver' => new XmlRiverAdapter($scraper->base_url, $scraper->credentials),
            default => throw new \InvalidArgumentException("Unknown scraper type: {$scraper->type}"),
        };
    }
}
```

New adapters are added by: (1) creating the adapter class, (2) adding a case to the `match`.

```bash
git commit -m "feat: add ScraperFactory for adapter instantiation"
```

---

### Task 5.4: Scrapers CRUD API

**Files:**
- Create: `app/Http/Controllers/Api/ScraperController.php`
- CRUD for scrapers + `POST /scrapers/{id}/test` (healthCheck)

```bash
git commit -m "feat: add Scrapers CRUD API with health check endpoint"
```

---

## Phase 6: SERP Scraping Pipeline

### Task 6.1: ScrapeSerp Job

**Files:**
- Create: `app/Jobs/ScrapeSerpJob.php`
- Create: `app/Services/SerpSnapshotService.php`

**Step 1: Write SerpSnapshotService**

Responsibilities:
- Call scraper adapter
- Save SerpSnapshot + SerpResults
- Dispatch classification for new domains

```php
<?php

namespace App\Services;

use App\Models\Keyword;
use App\Models\Region;
use App\Models\Scraper;
use App\Models\ScrapeJob;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\ScraperFactory;

class SerpSnapshotService
{
    public function __construct(
        private readonly ScraperFactory $scraperFactory,
    ) {}

    public function scrape(ScrapeJob $job): void
    {
        $keyword = $job->keyword;
        $scraper = $job->scraper;
        $region = Region::find($job->region_id);

        $adapter = $this->scraperFactory->make($scraper);

        $request = new ScrapeRequest(
            keyword: $keyword->keyword,
            engine: $job->engine,
            device: $job->device,
            regionId: $job->region_id,
            yandexLr: $region->yandex_lr,
            googleGl: $region->google_gl,
            googleHl: $region->google_hl,
        );

        $response = $adapter->scrape($request);

        $collectedAt = now()->toDateString();

        $snapshot = SerpSnapshot::create([
            'keyword_id' => $keyword->id,
            'collected_at' => $collectedAt,
            'search_engine' => $job->engine,
            'device' => $job->device,
            'region_id' => $job->region_id,
            'total_results' => $response->totalResults,
        ]);

        $rows = array_map(fn ($item) => [
            'snapshot_id' => $snapshot->id,
            'collected_at' => $collectedAt,
            'position' => $item->position,
            'url' => $item->url,
            'domain' => $item->domain,
            'title' => $item->title,
            'description' => $item->description,
            'snippet_type' => $item->snippetType,
            'is_ads' => $item->isAds,
        ], $response->results);

        // Bulk insert for performance
        foreach (array_chunk($rows, 50) as $chunk) {
            SerpResult::insert($chunk);
        }

        // Update job
        $job->update([
            'status' => 'completed',
            'completed_at' => now(),
            'raw_response' => $response->rawResponse,
        ]);
    }
}
```

**Step 2: Write ScrapeSerpJob**

```php
<?php

namespace App\Jobs;

use App\Models\ScrapeJob;
use App\Services\SerpSnapshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScrapeSerpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public readonly int $scrapeJobId,
    ) {
        $this->onQueue('serp-scrape');
    }

    public function handle(SerpSnapshotService $service): void
    {
        $job = ScrapeJob::findOrFail($this->scrapeJobId);
        $job->update(['status' => 'running', 'started_at' => now(), 'attempts' => $job->attempts + 1]);

        try {
            $service->scrape($job);
        } catch (\Exception $e) {
            $job->update([
                'status' => $job->attempts >= $this->tries ? 'failed' : 'retrying',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

**Step 3: Commit**

```bash
git add app/Jobs/ScrapeSerpJob.php app/Services/SerpSnapshotService.php
git commit -m "feat: add SERP scraping job and snapshot service"
```

---

### Task 6.2: SERP API endpoints

**Files:**
- Create: `app/Http/Controllers/Api/SerpController.php`

Endpoints:
- `GET /api/keywords/{id}/serp?from=&to=&limit=20` — snapshots with results for a keyword
- `GET /api/keywords/{id}/serp/history` — position of own domain over time (for chart)
- `GET /api/serp/competitors?project_id=&keyword_ids[]` — competitor summary

```bash
git commit -m "feat: add SERP API endpoints (snapshots, history, competitors)"
```

---

### Task 6.3: Schedule commands

**Files:**
- Create: `app/Console/Commands/CheckSchedulesCommand.php`
- Create: `app/Console/Commands/DispatchScrapeJobsCommand.php`
- Create: `app/Console/Commands/CreatePartitionsCommand.php`
- Create: `app/Console/Commands/CleanupRawResponsesCommand.php`

**CheckSchedulesCommand:**
- Find scrape_schedules where `next_run_at <= now()` and `is_active = true`
- For each schedule, resolve keywords (cascade: project->categories->clusters->keywords, or direct keyword_id)
- Create scrape_job records in `pending` status
- Update `last_run_at` and `next_run_at`

**DispatchScrapeJobsCommand:**
- Take pending scrape_jobs, group by scraper_id
- For each scraper, respect `rate_limit` — dispatch only N jobs per minute
- Dispatch ScrapeSerpJob for each

**CreatePartitionsCommand:**
- Create next month's partitions if they don't exist
- Run daily via scheduler

**CleanupRawResponsesCommand:**
- Delete raw_response from scrape_jobs older than 7 days
- Run daily via scheduler

**Register in `routes/console.php` or `app/Console/Kernel.php`:**

```php
Schedule::command('schedules:check')->everyMinute();
Schedule::command('scrape-jobs:dispatch')->everyMinute();
Schedule::command('partitions:create')->daily();
Schedule::command('cleanup:raw-responses')->daily();
```

```bash
git commit -m "feat: add scheduler commands (check schedules, dispatch jobs, partitions, cleanup)"
```

---

### Task 6.4: Schedules CRUD API

**Files:**
- Create: `app/Http/Controllers/Api/ScheduleController.php`

CRUD + `POST /schedules/{id}/run-now` (manually trigger).

```bash
git commit -m "feat: add Schedules CRUD API with manual trigger"
```

---

## Phase 7: Classification System

### Task 7.1: Classification service

**Files:**
- Create: `app/Services/ClassificationService.php`
- Create: `app/Jobs/ClassifyDomainsJob.php`
- Test: `tests/Unit/ClassificationServiceTest.php`

**Step 1: Write ClassificationService**

```php
<?php

namespace App\Services;

use App\Enums\ClassificationRuleType;
use App\Models\ClassificationRule;
use App\Models\DomainClassification;

class ClassificationService
{
    public function classify(string $domain, int $organizationId, ?string $url = null, ?string $title = null): ?DomainClassification
    {
        // Check if already manually classified
        $existing = DomainClassification::where('domain', $domain)
            ->where('organization_id', $organizationId)
            ->where('classified_by', 'manual')
            ->first();

        if ($existing) {
            return $existing;
        }

        $rules = ClassificationRule::where(function ($q) use ($organizationId) {
            $q->where('organization_id', $organizationId)
              ->orWhere('is_system', true);
        })
        ->orderBy('priority', 'desc')
        ->get();

        foreach ($rules as $rule) {
            if ($this->matchesRule($rule, $domain, $url, $title)) {
                return DomainClassification::updateOrCreate(
                    ['domain' => $domain, 'organization_id' => $organizationId],
                    ['site_type_id' => $rule->site_type_id, 'classified_by' => 'rule', 'rule_id' => $rule->id],
                );
            }
        }

        return null;
    }

    private function matchesRule(ClassificationRule $rule, string $domain, ?string $url, ?string $title): bool
    {
        return match ($rule->rule_type) {
            ClassificationRuleType::DomainExact->value => $domain === $rule->pattern,
            ClassificationRuleType::DomainContains->value => str_contains($domain, $rule->pattern),
            ClassificationRuleType::DomainRegex->value => (bool) @preg_match($rule->pattern, $domain),
            ClassificationRuleType::UrlRegex->value => $url && (bool) @preg_match($rule->pattern, $url),
            ClassificationRuleType::TitleContains->value => $title && str_contains(mb_strtolower($title), mb_strtolower($rule->pattern)),
            default => false,
        };
    }
}
```

**Step 2: Write test**

```php
<?php

namespace Tests\Unit;

use App\Models\ClassificationRule;
use App\Models\Organization;
use App\Models\SiteType;
use App\Services\ClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_classifies_by_exact_domain(): void
    {
        $org = Organization::factory()->create();
        $type = SiteType::create(['slug' => 'marketplace', 'name' => 'Маркетплейс', 'color' => '#8B5CF6']);
        ClassificationRule::create([
            'organization_id' => $org->id,
            'rule_type' => 'domain_exact',
            'pattern' => 'wildberries.ru',
            'site_type_id' => $type->id,
            'priority' => 10,
        ]);

        $service = new ClassificationService();
        $result = $service->classify('wildberries.ru', $org->id);

        $this->assertNotNull($result);
        $this->assertEquals($type->id, $result->site_type_id);
    }

    public function test_manual_classification_not_overridden(): void
    {
        // ... test that manual classifications are preserved
    }
}
```

**Step 3: Run tests, commit**

```bash
git commit -m "feat: add classification service with rule matching"
```

---

### Task 7.2: Classification API

**Files:**
- Create: `app/Http/Controllers/Api/ClassificationController.php`

CRUD for rules + `PUT /api/domains/{domain}/classify` for manual override.

```bash
git commit -m "feat: add Classification API (rules CRUD, manual classify)"
```

---

## Phase 8: Wordstat Integration

### Task 8.1: Wordstat API endpoints

**Files:**
- Create: `app/Http/Controllers/Api/WordstatController.php`

Endpoints:
- `GET /api/keywords/{id}/wordstat` — latest frequencies for keyword
- `GET /api/keywords/{id}/wordstat/trends` — monthly trend data

```bash
git commit -m "feat: add Wordstat API endpoints"
```

---

### Task 8.2: Wordstat schedules + collection job

**Files:**
- Create: `app/Jobs/CollectWordstatJob.php`
- Create: `app/Services/Wordstat/Contracts/WordstatAdapter.php`
- Create: `app/Services/Wordstat/DTO/WordstatResult.php`
- Create: `app/Console/Commands/CheckWordstatSchedulesCommand.php`

Same pattern as SERP: adapter interface -> job -> scheduler command.
Wordstat adapter will be pluggable (Yandex Direct API, custom service, etc.)

```bash
git commit -m "feat: add Wordstat collection pipeline (adapter, job, scheduler)"
```

---

### Task 8.3: Wordstat schedules CRUD API

Same pattern as scrape schedules.

```bash
git commit -m "feat: add Wordstat schedules CRUD API"
```

---

## Phase 9: Dashboard API

### Task 9.1: Dashboard summary endpoint

**Files:**
- Create: `app/Http/Controllers/Api/DashboardController.php`

`GET /api/dashboard/summary?project_id=`

Returns:
- Keywords count by engine
- Keywords in TOP-3 / TOP-10 / TOP-20 / TOP-100 (latest snapshot)
- Position changes vs previous period (improved / declined / stable)
- Visibility score (weighted by frequency and position)

```bash
git commit -m "feat: add Dashboard summary API endpoint"
```

---

## Phase 10: React Frontend Scaffold

### Task 10.1: Create React project

**Step 1: Scaffold**

```bash
cd /Users/k.mazurov/PhpstormProjects/seo-monitor
mkdir frontend && cd frontend
npm create vite@latest . -- --template react-ts
```

**Step 2: Install dependencies**

```bash
npm install @tanstack/react-router @tanstack/react-query @tanstack/react-table
npm install tailwindcss @tailwindcss/vite
npm install axios
npx shadcn@latest init
```

**Step 3: Configure Tailwind, TanStack Router, API client**

**Step 4: Commit**

```bash
git commit -m "chore: scaffold React frontend with TanStack + Tailwind + shadcn"
```

---

### Task 10.2: Auth pages (Login / Register)

**Files:**
- Create: `frontend/src/pages/login.tsx`
- Create: `frontend/src/pages/register.tsx`
- Create: `frontend/src/lib/api.ts` (axios instance with token)
- Create: `frontend/src/stores/auth.ts` (auth state)

```bash
git commit -m "feat: add Login/Register pages with auth state"
```

---

### Task 10.3: Layout + sidebar navigation

**Files:**
- Create: `frontend/src/layouts/AppLayout.tsx`
- Create: `frontend/src/components/Sidebar.tsx`

Navigation structure per design doc section 7.

```bash
git commit -m "feat: add app layout with sidebar navigation"
```

---

### Task 10.4: Projects list + CRUD

```bash
git commit -m "feat: add Projects list/create/edit pages"
```

---

### Task 10.5: Keywords table page

**Files:**
- Create: `frontend/src/pages/keywords/index.tsx`
- Create: `frontend/src/components/keywords/KeywordsTable.tsx`
- Create: `frontend/src/components/keywords/KeywordFilters.tsx`
- Create: `frontend/src/components/keywords/BulkImportDialog.tsx`

TanStack Table with:
- Server-side pagination
- Filters: engine, device, region, category, search
- Engine badge (Я/G)
- Position + delta column
- Bulk actions

```bash
git commit -m "feat: add Keywords table with filters, badges, bulk import"
```

---

### Task 10.6: Keyword detail page (SERP tab)

**Files:**
- Create: `frontend/src/pages/keywords/[keywordId]/index.tsx`
- Create: `frontend/src/components/serp/SerpTable.tsx`
- Create: `frontend/src/components/serp/SiteTypeBadge.tsx`

SERP table with:
- Date picker / period selector
- TOP-N filter (default 20)
- Position, domain, type badge, title, URL columns
- Own domain highlighted

```bash
git commit -m "feat: add Keyword detail page with SERP results table"
```

---

### Task 10.7: Keyword detail — History + Wordstat tabs

**Files:**
- Create: `frontend/src/components/serp/PositionChart.tsx` (line chart, position over time)
- Create: `frontend/src/components/wordstat/FrequencyCard.tsx`
- Create: `frontend/src/components/wordstat/TrendChart.tsx`
- Create: `frontend/src/components/wordstat/SuggestionsTable.tsx`

Use a charting library (recharts or chart.js) for:
- Position history line chart
- Wordstat trend bar chart (seasonality)

```bash
git commit -m "feat: add History and Wordstat tabs with charts"
```

---

### Task 10.8: Competitors page

**Files:**
- Create: `frontend/src/pages/competitors/index.tsx`

Summary table: domain, count in TOP-3/10/20, site type, trend.

```bash
git commit -m "feat: add Competitors summary page"
```

---

### Task 10.9: Classification management pages

**Files:**
- Create: `frontend/src/pages/classification/rules.tsx`
- Create: `frontend/src/pages/classification/domains.tsx`

Rules CRUD + domain list with type badges and manual override.

```bash
git commit -m "feat: add Classification rules and domains pages"
```

---

### Task 10.10: Scrapers + Schedules settings

**Files:**
- Create: `frontend/src/pages/scrapers/index.tsx`
- Create: `frontend/src/pages/settings/schedules.tsx`

Scrapers CRUD with health check button. Schedules with cascade selector (project/category/cluster/keyword).

```bash
git commit -m "feat: add Scrapers and Schedules management pages"
```

---

### Task 10.11: Dashboard page

**Files:**
- Create: `frontend/src/pages/dashboard/index.tsx`
- Create: `frontend/src/components/dashboard/SummaryCards.tsx`
- Create: `frontend/src/components/dashboard/VisibilityChart.tsx`

Cards: TOP-3/10/20/100 counts, improved/declined. Visibility chart over time.

```bash
git commit -m "feat: add Dashboard page with summary cards and visibility chart"
```

---

### Task 10.12: Organization settings + members

**Files:**
- Create: `frontend/src/pages/settings/organization.tsx`
- Create: `frontend/src/pages/settings/members.tsx`

Org settings, invite members, role management.

```bash
git commit -m "feat: add Organization settings and member management"
```

---

## Phase 11: Docker frontend container

### Task 11.1: Frontend Dockerfile + nginx

**Files:**
- Create: `frontend/Dockerfile`
- Create: `frontend/nginx.conf`
- Modify: `docker-compose.yml` — update frontend service

```dockerfile
# frontend/Dockerfile
FROM node:20-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM nginx:alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
```

```bash
git commit -m "chore: add frontend Docker build with nginx"
```

---

## Summary: 30 tasks across 11 phases

| Phase | Tasks | Description |
|---|---|---|
| 1 | 1.1-1.3 | Laravel scaffold, Docker, Horizon |
| 2 | 2.1-2.8 | Enums, all migrations, models |
| 3 | 3.1-3.2 | Auth + multi-tenant middleware |
| 4 | 4.1-4.6 | Core CRUD APIs |
| 5 | 5.1-5.4 | Scraper adapter architecture |
| 6 | 6.1-6.4 | SERP scraping pipeline + schedules |
| 7 | 7.1-7.2 | Classification system |
| 8 | 8.1-8.3 | Wordstat integration |
| 9 | 9.1 | Dashboard API |
| 10 | 10.1-10.12 | React frontend (all pages) |
| 11 | 11.1 | Frontend Docker |
