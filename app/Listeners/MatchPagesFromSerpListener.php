<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Repositories\SerpSnapshotRepositoryInterface;
use App\Events\SerpSnapshotCollected;
use App\Services\PageMatchService;
use Illuminate\Contracts\Queue\ShouldQueue;

final class MatchPagesFromSerpListener implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly PageMatchService $matchService,
        private readonly SerpSnapshotRepositoryInterface $snapshotRepository,
    ) {}

    public function handle(SerpSnapshotCollected $event): void
    {
        $snapshot = $this->snapshotRepository->findById($event->snapshotId);

        $this->matchService->matchSnapshot($snapshot);
    }
}
