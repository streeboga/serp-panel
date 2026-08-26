<?php

declare(strict_types=1);

namespace App\Services\Audit;

use SerpAudit\CheckRegistry;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final readonly class PageAuditor
{
    public function __construct(
        private CheckRegistry $registry,
    ) {}

    /**
     * Прогоняет выбранные проверки по одному разобранному документу.
     *
     * @param  array<int, string>|null  $categories  пусто — все категории
     * @param  array<int, string>|null  $codes  пусто — все проверки категорий
     * @return array{
     *     score: int,
     *     findings: array<int, array<string, mixed>>,
     *     metrics: array<string, mixed>,
     *     issues_critical: int,
     *     issues_warning: int,
     *     issues_notice: int
     * }
     */
    public function audit(PageContext $context, ?array $categories = null, ?array $codes = null): array
    {
        $findings = [];
        $metrics = [];

        foreach ($this->registry->select($categories, $codes) as $check) {
            $findings = [...$findings, ...$check->run($context)];
            $metrics = [...$metrics, ...$check->metrics($context)];
        }

        return [...self::summarize($findings), 'metrics' => $metrics];
    }

    /**
     * Оценка и счётчики по списку находок. Используется и для страницы, и для сайта.
     *
     * @param  array<int, Finding>  $findings
     * @return array{
     *     score: int,
     *     findings: array<int, array<string, mixed>>,
     *     issues_critical: int,
     *     issues_warning: int,
     *     issues_notice: int
     * }
     */
    public static function summarize(array $findings): array
    {
        $counts = [Severity::Critical->value => 0, Severity::Warning->value => 0, Severity::Notice->value => 0];
        $penalty = 0;

        foreach ($findings as $finding) {
            $counts[$finding->severity->value]++;
            $penalty += $finding->severity->penalty();
        }

        return [
            'score' => max(0, 100 - $penalty),
            'findings' => array_map(static fn (Finding $f): array => $f->toArray(), $findings),
            'issues_critical' => $counts[Severity::Critical->value],
            'issues_warning' => $counts[Severity::Warning->value],
            'issues_notice' => $counts[Severity::Notice->value],
        ];
    }
}
