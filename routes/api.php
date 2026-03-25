<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClassificationController;
use App\Http\Controllers\Api\ClusterController;
use App\Http\Controllers\Api\CompetitorController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\KeywordController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ScraperController;
use App\Http\Controllers\Api\SerpController;
use App\Http\Controllers\Api\WordstatController;
use App\Http\Controllers\Api\WordstatScheduleController;
use App\Models\Category;
use App\Models\Cluster;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('organizations', [OrganizationController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'org'])->group(function () {
    // Organization
    Route::get('organization', [OrganizationController::class, 'show']);
    Route::put('organization', [OrganizationController::class, 'update']);
    Route::get('organization/members', [OrganizationController::class, 'members']);
    Route::post('organization/invite', [OrganizationController::class, 'invite']);
    Route::delete('organization/members/{userId}', [OrganizationController::class, 'removeMember']);
    Route::put('organization/members/{userId}/role', [OrganizationController::class, 'updateMemberRole']);

    // Dashboard
    Route::get('dashboard/summary', [DashboardController::class, 'summary']);

    // Projects
    Route::apiResource('projects', ProjectController::class);

    // Domains (nested under project)
    Route::apiResource('projects.domains', DomainController::class)->shallow();

    // Categories
    Route::get('domains/{domain}/categories', [CategoryController::class, 'index']);
    Route::apiResource('categories', CategoryController::class)->except(['index']);

    // Clusters
    Route::get('categories/{category}/clusters', [ClusterController::class, 'index']);
    Route::apiResource('clusters', ClusterController::class)->except(['index']);

    // Keywords
    Route::get('keywords', [KeywordController::class, 'index']);
    Route::post('keywords/bulk', [KeywordController::class, 'bulkStore']);
    Route::post('keywords/import', [KeywordController::class, 'import']);
    Route::put('keywords/{keyword}', [KeywordController::class, 'update']);
    Route::delete('keywords/bulk', [KeywordController::class, 'bulkDestroy']);

    // Project clusters (all clusters across all domains/categories in a project)
    Route::get('projects/{project}/clusters', function (Project $project) {
        $domainIds = $project->domains()->pluck('id');
        $categoryIds = Category::whereIn('domain_id', $domainIds)->pluck('id');

        return response()->json(
            Cluster::whereIn('category_id', $categoryIds)
                ->with('category')
                ->get()
        );
    });

    // Regions (read-only)
    Route::get('regions', [RegionController::class, 'index']);

    // Scrapers
    Route::apiResource('scrapers', ScraperController::class);
    Route::post('scrapers/{scraper}/test', [ScraperController::class, 'test']);

    // Scrape Schedules
    Route::apiResource('schedules', ScheduleController::class);
    Route::post('schedules/{schedule}/run-now', [ScheduleController::class, 'runNow']);

    // SERP
    Route::get('keywords/{keyword}/serp', [SerpController::class, 'index']);
    Route::get('keywords/{keyword}/serp/history', [SerpController::class, 'history']);

    // Classification
    Route::apiResource('classification/rules', ClassificationController::class);
    Route::put('domains/{domain}/classify', [ClassificationController::class, 'classifyDomain']);
    Route::get('site-types', [ClassificationController::class, 'siteTypes']);

    // Competitors
    Route::get('serp/competitors', [CompetitorController::class, 'index']);

    // Wordstat
    Route::get('keywords/{keyword}/wordstat', [WordstatController::class, 'frequencies']);
    Route::get('keywords/{keyword}/wordstat/trends', [WordstatController::class, 'trends']);
    Route::get('keywords/{keyword}/wordstat/suggestions', [WordstatController::class, 'suggestions']);
    Route::apiResource('wordstat-schedules', WordstatScheduleController::class);
    Route::post('wordstat-schedules/{wordstatSchedule}/run-now', [WordstatScheduleController::class, 'runNow']);
});
