<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\SerpSnapshotCollected;
use App\Listeners\CheckPositionAlertsListener;
use App\Listeners\MatchPagesFromSerpListener;
use App\Services\Wordstat\Contracts\WordstatAdapter;
use App\Services\Wordstat\WordstatAdapterFactory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WordstatAdapter::class, function () {
            return WordstatAdapterFactory::make();
        });
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        Event::listen(SerpSnapshotCollected::class, CheckPositionAlertsListener::class);
        Event::listen(SerpSnapshotCollected::class, MatchPagesFromSerpListener::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('xmlriver', function () {
            return Limit::perSecond(10);
        });

        // Yandex Cloud Wordstat API hard quota is 100 requests/hour. Each job makes
        // up to ~2 calls (topRequests + dynamics), so cap jobs at 45/hour (~90 calls).
        RateLimiter::for('wordstat', function () {
            return Limit::perHour(45);
        });
    }
}
