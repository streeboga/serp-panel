<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Models\SiteAudit;

/**
 * Журнал непроверенного.
 *
 * Этап, до которого мы не дотянулись (нет доступа, сервис молчит, нет данных),
 * обязан сказать об этом вслух. Молчаливый пропуск читается как «всё хорошо»,
 * а это враньё: читатель отчёта не отличит исправный сайт от неизмеренного.
 */
final readonly class Unchecked
{
    public function __construct(
        private SiteAuditRepositoryInterface $audits,
    ) {}

    public function record(SiteAudit $audit, string $stage, string $reason): void
    {
        $fresh = $this->audits->findById($audit->id);
        $metrics = $fresh->metrics ?? [];
        $metrics['unchecked'][$stage] = $reason;

        $this->audits->update($fresh, ['metrics' => $metrics]);
    }
}
