<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\AuditResourceRepositoryInterface;
use App\Services\Audit\PageFetcher;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Queue\SerializesModels;

/**
 * Один ресурс: код ответа и размер без скачивания тела.
 */
final class CheckResourceJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Как и у страничной джобы: лимитер тратит попытки, поэтому ограничиваем время. */
    public int $tries = 0;

    public int $timeout = 45;

    public function __construct(
        public readonly int $resourceId,
        public readonly string $url,
    ) {
        $this->onQueue('audit-assets');
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        // Тот же лимитер, что у страниц: вежливость считается на сайт целиком,
        // а не на каждую очередь отдельно.
        return [new RateLimitedWithRedis('audit')];
    }

    public function handle(AuditResourceRepositoryInterface $resources, PageFetcher $fetcher): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $resources->markChecked($this->resourceId, $fetcher->probe($this->url));
    }
}
