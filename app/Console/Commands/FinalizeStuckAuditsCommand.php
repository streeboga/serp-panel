<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AuditStatus;
use App\Jobs\FinalizeSiteAuditJob;
use App\Models\SiteAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

/**
 * Закрывает прогоны, застрявшие в «идёт».
 *
 * Батч, чьи джобы пропали — перезапуск воркера, чистка Redis, выкатка посреди
 * этапа, — не вызывает finally никогда, и прогон висит в «running» вечно.
 * Пользователь при этом видит вращающийся прогресс и не понимает, чего ждать.
 */
final class FinalizeStuckAuditsCommand extends Command
{
    protected $signature = 'audit:finalize-stuck {--minutes=45 : Сколько минут без движения считать зависанием}';

    protected $description = 'Закрывает прогоны аудита, застрявшие из-за потерянного батча';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');

        $stuck = SiteAudit::query()
            ->whereIn('status', [AuditStatus::Pending->value, AuditStatus::Running->value])
            ->where('updated_at', '<', now()->subMinutes($minutes))
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('Зависших прогонов нет.');

            return self::SUCCESS;
        }

        foreach ($stuck as $audit) {
            // Батч ещё жив и движется — трогать нельзя.
            $batch = $audit->batch_id === null ? null : Bus::findBatch($audit->batch_id);

            if ($batch !== null && ! $batch->finished() && $batch->pendingJobs > 0
                && $batch->createdAt->gt(now()->subMinutes($minutes))) {
                continue;
            }

            $this->warn("Прогон {$audit->id} стоит с {$audit->updated_at} — закрываю по тому, что успело собраться.");

            FinalizeSiteAuditJob::dispatch($audit->id);
        }

        return self::SUCCESS;
    }
}
