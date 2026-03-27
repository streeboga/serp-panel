<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ClassificationController;
use App\Http\Controllers\Api\V1\ClusterController;
use App\Http\Controllers\Api\V1\CompetitorController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DomainController;
use App\Http\Controllers\Api\V1\ConnectedAccountController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\KeywordController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PositionMatrixController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\ScraperController;
use App\Http\Controllers\Api\V1\SerpController;
use App\Http\Controllers\Api\V1\WordstatController;
use App\Http\Controllers\Api\V1\WordstatScheduleController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\YandexOAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('json-api')->group(function () {
    // Webhook — no auth required (uses secret)
    Route::post('/webhooks/serp', [WebhookController::class, 'serp'])->withoutMiddleware(['json-api']);

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);

        Route::get('organizations', [OrganizationController::class, 'index']);

        // Yandex OAuth
        Route::get('auth/yandex/redirect', [YandexOAuthController::class, 'redirect']);
        Route::get('auth/yandex/callback', [YandexOAuthController::class, 'callback'])->withoutMiddleware(['json-api']);
    });

    Route::middleware(['auth:sanctum', 'org'])->group(function () {
        // Organization — read
        Route::get('organization', [OrganizationController::class, 'show']);
        Route::get('organization/members', [OrganizationController::class, 'members']);
        Route::get('organization/yandex/status', [YandexOAuthController::class, 'status']);

        // Connected Accounts — read
        Route::get('accounts', [ConnectedAccountController::class, 'index']);

        // Organization — admin-only write
        Route::middleware('org.role:admin')->group(function () {
            Route::patch('organization', [OrganizationController::class, 'update']);
            Route::post('organization/invite', [OrganizationController::class, 'invite']);
            Route::delete('organization/members/{userId}', [OrganizationController::class, 'removeMember']);
            Route::patch('organization/members/{userId}/role', [OrganizationController::class, 'updateMemberRole']);

            // Connected Accounts — admin
            Route::post('accounts', [ConnectedAccountController::class, 'store']);
            Route::patch('accounts/{account}', [ConnectedAccountController::class, 'update']);
            Route::delete('accounts/{account}', [ConnectedAccountController::class, 'destroy']);
            Route::post('accounts/{account}/test', [ConnectedAccountController::class, 'test']);

            // Yandex OAuth — admin
            Route::post('organization/yandex/save-token', [YandexOAuthController::class, 'saveToken']);
            Route::delete('organization/yandex', [YandexOAuthController::class, 'disconnect']);

            // Billing
            Route::patch('billing/tier', [BillingController::class, 'updateTier']);
        });

        // Dashboard
        Route::get('dashboard/summary', [DashboardController::class, 'summary']);

        // Projects — read
        Route::get('projects', [ProjectController::class, 'index']);
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::get('projects/{project}/positions', PositionMatrixController::class);

        // Projects — write (manager+)
        Route::middleware('org.role:manager')->group(function () {
            Route::post('projects', [ProjectController::class, 'store']);
            Route::patch('projects/{project}', [ProjectController::class, 'update']);
            Route::delete('projects/{project}', [ProjectController::class, 'destroy']);
        });

        // Domains — read
        Route::get('projects/{project}/domains', [DomainController::class, 'index']);
        Route::get('domains/{domain}', [DomainController::class, 'show'])->withoutScopedBindings();
        Route::get('domains/{domain}/index-results', [DomainController::class, 'indexResults']);
        Route::get('domains/{domain}/keywords', [DomainController::class, 'keywords']);

        // Domains — write (manager+)
        Route::middleware('org.role:manager')->group(function () {
            Route::post('projects/{project}/domains', [DomainController::class, 'store']);
            Route::patch('domains/{domain}', [DomainController::class, 'update']);
            Route::delete('domains/{domain}', [DomainController::class, 'destroy']);
            Route::post('domains/{domain}/index', [DomainController::class, 'indexDomain']);
        });

        // Categories — read
        Route::get('domains/{domain}/categories', [CategoryController::class, 'index']);
        Route::get('categories/{category}', [CategoryController::class, 'show']);

        // Categories — write (manager+)
        Route::middleware('org.role:manager')->group(function () {
            Route::post('categories', [CategoryController::class, 'store']);
            Route::patch('categories/{category}', [CategoryController::class, 'update']);
            Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
        });

        // Clusters — read
        Route::get('categories/{category}/clusters', [ClusterController::class, 'index']);
        Route::get('clusters/{cluster}', [ClusterController::class, 'show']);
        Route::get('projects/{project}/clusters', [ClusterController::class, 'byProject']);

        // Clusters — write (manager+)
        Route::middleware('org.role:manager')->group(function () {
            Route::post('clusters', [ClusterController::class, 'store']);
            Route::patch('clusters/{cluster}', [ClusterController::class, 'update']);
            Route::delete('clusters/{cluster}', [ClusterController::class, 'destroy']);
        });

        // Keywords — read
        Route::get('keywords', [KeywordController::class, 'index']);
        Route::get('keywords/{keyword}', [KeywordController::class, 'show']);

        // Keywords — write (manager+)
        Route::middleware('org.role:manager')->group(function () {
            Route::post('keywords/bulk', [KeywordController::class, 'bulkStore']);
            Route::post('keywords/import', [KeywordController::class, 'import']);
            Route::patch('keywords/{keyword}', [KeywordController::class, 'update']);
            Route::delete('keywords/bulk', [KeywordController::class, 'bulkDestroy']);
        });

        // Regions (read-only)
        Route::get('regions', [RegionController::class, 'index']);

        // Scrapers — read
        Route::get('scraper-types', [WebhookController::class, 'scraperTypes']);
        Route::get('scrapers', [ScraperController::class, 'index']);
        Route::get('scrapers/{scraper}', [ScraperController::class, 'show'])->name('scrapers.show');

        // Scrapers — write (manager+)
        Route::middleware('org.role:manager')->group(function () {
            Route::post('scrapers', [ScraperController::class, 'store']);
            Route::patch('scrapers/{scraper}', [ScraperController::class, 'update']);
            Route::delete('scrapers/{scraper}', [ScraperController::class, 'destroy']);
            Route::post('scrapers/{scraper}/test', [ScraperController::class, 'test']);
        });

        // Scrape Schedules — read
        Route::get('schedules', [ScheduleController::class, 'index']);
        Route::get('schedules/{schedule}', [ScheduleController::class, 'show'])->name('schedules.show');

        // Scrape Schedules — write (manager+)
        Route::middleware('org.role:manager')->group(function () {
            Route::post('schedules', [ScheduleController::class, 'store']);
            Route::patch('schedules/{schedule}', [ScheduleController::class, 'update']);
            Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy']);
            Route::post('schedules/{schedule}/run-now', [ScheduleController::class, 'runNow']);
        });

        // Pages — read
        Route::get('projects/{project}/pages', [PageController::class, 'index']);
        Route::get('projects/{project}/pages/target-report', [PageController::class, 'targetReport']);
        Route::get('pages/{page}', [PageController::class, 'show']);
        Route::get('keywords/{keyword}/pages', [PageController::class, 'keywordPages']);

        // Pages — write (manager+)
        Route::middleware('org.role:manager')->group(function () {
            Route::post('projects/{project}/pages', [PageController::class, 'store']);
            Route::post('projects/{project}/pages/import', [PageController::class, 'import']);
            Route::post('projects/{project}/pages/match-or-create', [PageController::class, 'matchOrCreate']);
            Route::patch('pages/{page}', [PageController::class, 'update']);
            Route::delete('pages/{page}', [PageController::class, 'destroy']);
            Route::patch('pages/{page}/tags', [PageController::class, 'syncTags']);
            Route::post('pages/{page}/attach', [PageController::class, 'attach']);
            Route::post('pages/{page}/bulk-attach', [PageController::class, 'bulkAttach']);
            Route::delete('pageables/{pageable}', [PageController::class, 'detachPageable']);
        });

        // SERP
        Route::get('keywords/{keyword}/serp', [SerpController::class, 'index']);
        Route::post('keywords/{keyword}/rescrape', [SerpController::class, 'rescrape']);
        Route::get('keywords/{keyword}/serp/dates', [SerpController::class, 'dates']);
        Route::get('keywords/{keyword}/serp/history', [SerpController::class, 'history']);

        // Classification — read
        Route::get('classification/rules', [ClassificationController::class, 'index']);
        Route::get('classification/rules/{rule}', [ClassificationController::class, 'show']);
        Route::get('site-types', [ClassificationController::class, 'siteTypes']);

        // Classification — write (manager+)
        Route::middleware('org.role:manager')->group(function () {
            Route::post('classification/rules', [ClassificationController::class, 'store']);
            Route::patch('classification/rules/{rule}', [ClassificationController::class, 'update']);
            Route::delete('classification/rules/{rule}', [ClassificationController::class, 'destroy']);
            Route::patch('domains/{domain}/classify', [ClassificationController::class, 'classifyDomain']);
        });

        // Competitors
        Route::get('serp/competitors', [CompetitorController::class, 'index']);

        // Wordstat
        Route::get('keywords/{keyword}/wordstat', [WordstatController::class, 'frequencies']);
        Route::get('keywords/{keyword}/wordstat/trends', [WordstatController::class, 'trends']);
        Route::get('keywords/{keyword}/wordstat/suggestions', [WordstatController::class, 'suggestions']);

        // Wordstat Schedules — read
        Route::get('wordstat-schedules', [WordstatScheduleController::class, 'index']);
        Route::get('wordstat-schedules/{wordstatSchedule}', [WordstatScheduleController::class, 'show']);

        // Wordstat Schedules — write (manager+)
        Route::middleware('org.role:manager')->group(function () {
            Route::post('wordstat-schedules', [WordstatScheduleController::class, 'store']);
            Route::patch('wordstat-schedules/{wordstatSchedule}', [WordstatScheduleController::class, 'update']);
            Route::delete('wordstat-schedules/{wordstatSchedule}', [WordstatScheduleController::class, 'destroy']);
            Route::post('wordstat-schedules/{wordstatSchedule}/run-now', [WordstatScheduleController::class, 'runNow']);
        });

        // Position Alerts — read
        Route::get('alerts', [AlertController::class, 'index']);
        Route::get('alerts/{alert}', [AlertController::class, 'show']);

        // Position Alerts — write (manager+)
        Route::middleware('org.role:manager')->group(function () {
            Route::post('alerts', [AlertController::class, 'store']);
            Route::patch('alerts/{alert}', [AlertController::class, 'update']);
            Route::delete('alerts/{alert}', [AlertController::class, 'destroy']);
        });

        // Export
        Route::get('export/keywords', [ExportController::class, 'keywords']);
        Route::get('export/serp', [ExportController::class, 'serp']);

        // Billing — read
        Route::get('billing/usage', [BillingController::class, 'usage']);
    });
});
