<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Contracts\Repositories\PositionAlertRepositoryInterface;
use App\Contracts\Repositories\SerpSnapshotRepositoryInterface;
use App\Events\SerpSnapshotCollected;
use App\Jobs\SendPositionAlertJob;
use App\Models\SerpSnapshot;
use Illuminate\Contracts\Queue\ShouldQueue;

final class CheckPositionAlertsListener implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly PositionAlertRepositoryInterface $alertRepository,
        private readonly SerpSnapshotRepositoryInterface $snapshotRepository,
        private readonly DomainRepositoryInterface $domainRepository,
    ) {}

    public function handle(SerpSnapshotCollected $event): void
    {
        $alerts = $this->alertRepository->activeForKeyword($event->keywordId);

        if ($alerts->isEmpty()) {
            return;
        }

        $snapshot = $this->snapshotRepository->findById($event->snapshotId);
        $keyword = $snapshot->keyword;

        if ($keyword === null) {
            return;
        }

        $keyword->load('cluster.category.domain.project');

        $project = $keyword->cluster->category->domain->project;

        $ownDomains = $this->domainRepository->ownDomainsForProject($project->id)
            ->pluck('name')
            ->toArray();

        if (empty($ownDomains)) {
            return;
        }

        $currentPosition = $this->findPositionInSnapshot($snapshot, $ownDomains);
        $previousPosition = $this->findPreviousPosition($event->keywordId, $event->collectedAt, $ownDomains);

        if ($currentPosition === null && $previousPosition === null) {
            return;
        }

        $effectiveCurrentPosition = $currentPosition ?? 101;
        $effectivePreviousPosition = $previousPosition ?? 101;

        foreach ($alerts as $alert) {
            $triggered = match ($alert->direction) {
                'drops_below' => $effectiveCurrentPosition > $alert->threshold_position
                    && $effectivePreviousPosition <= $alert->threshold_position,
                'rises_above' => $effectiveCurrentPosition <= $alert->threshold_position
                    && $effectivePreviousPosition > $alert->threshold_position,
                default => false,
            };

            if ($triggered) {
                SendPositionAlertJob::dispatch(
                    $alert->id,
                    $event->keywordId,
                    $effectivePreviousPosition,
                    $effectiveCurrentPosition,
                );

                $this->alertRepository->update($alert, ['last_triggered_at' => now()]);
            }
        }
    }

    /** @param string[] $ownDomains */
    private function findPositionInSnapshot(SerpSnapshot $snapshot, array $ownDomains): ?int
    {
        foreach ($snapshot->results as $result) {
            if (in_array($result->domain, $ownDomains, true)) {
                return $result->position;
            }
        }

        return null;
    }

    /** @param string[] $ownDomains */
    private function findPreviousPosition(int $keywordId, string $currentDate, array $ownDomains): ?int
    {
        $previousSnapshot = $this->snapshotRepository->previousForKeyword($keywordId, $currentDate);

        if ($previousSnapshot === null) {
            return null;
        }

        return $this->findPositionInSnapshot($previousSnapshot, $ownDomains);
    }
}
