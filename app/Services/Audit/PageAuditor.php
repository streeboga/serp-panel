<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\Severity;
use App\Services\Audit\Contracts\PageCheck;
use App\Services\Audit\DTO\Finding;
use App\Services\Audit\DTO\PageContext;

final class PageAuditor
{
    /**
     * Прогоняет включённые проверки по одному разобранному документу.
     *
     * @param  array<int, string>|null  $groups  Значения CheckGroup; null — все.
     * @return array{
     *     score: int,
     *     findings: array<int, array<string, mixed>>,
     *     metrics: array<string, mixed>,
     *     issues_critical: int,
     *     issues_warning: int,
     *     issues_notice: int
     * }
     */
    public function audit(PageContext $context, ?array $groups = null): array
    {
        $findings = [];
        $metrics = [];

        foreach ($this->checks($groups) as $check) {
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

    /**
     * @param  array<int, string>|null  $groups
     * @return array<int, PageCheck>
     */
    private function checks(?array $groups): array
    {
        /** @var array<int, class-string<PageCheck>> $classes */
        $classes = config('audit.checks', []);

        $checks = array_map(static fn (string $class): PageCheck => app($class), $classes);

        if ($groups === null || $groups === []) {
            return $checks;
        }

        return array_values(array_filter(
            $checks,
            static fn (PageCheck $check): bool => in_array($check->group()->value, $groups, true),
        ));
    }
}
