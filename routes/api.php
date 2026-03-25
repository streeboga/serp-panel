<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClusterController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\KeywordController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\RegionController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
});

Route::middleware(['auth:sanctum', 'org'])->group(function () {
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
    Route::put('keywords/{keyword}', [KeywordController::class, 'update']);
    Route::delete('keywords/bulk', [KeywordController::class, 'bulkDestroy']);

    // Regions (read-only)
    Route::get('regions', [RegionController::class, 'index']);
});
